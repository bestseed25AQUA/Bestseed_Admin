<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRating;
use App\Models\Farmer;
use App\Models\Hatchery;
use App\Models\HatcheryLocation;
use App\Models\VehicleAvailability;
use App\Models\PushNotification;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\AppConfig;
use Exception;

use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{

    private static $statusLabels = [
        1 => 'Pending',
        2 => 'Confirmed',
        3 => 'Driver Assigned',
        4 => 'In Journey',
        5 => 'Delivered',
        6 => 'Cancelled',
    ];

    private function getStatusMessage(Booking $booking, int $status): array
    {
        return match ($status) {
            1 => [
                'title' => 'Booking Created Successfully',
                'body'  => "Congratulations! Your booking #{$booking->id} was created successfully. Please wait for vendor updates."
            ],
            2 => [
                'title' => 'Booking Confirmed',
                'body'  => "Good news! Your booking #{$booking->id} has been confirmed by the vendor."
            ],
            3 => [
                'title' => 'Driver Assigned',
                'body'  => "A driver has been assigned to your booking #{$booking->id}. Your order will be delivered soon."
            ],
            4 => [
                'title' => 'Journey Started',
                'body'  => "Your booking #{$booking->id} is now In Journey. The delivery has started."
            ],
            5 => [
                'title' => 'Delivered Successfully',
                'body'  => "Your booking #{$booking->id} has been delivered successfully. Thank you for choosing us!"
            ],
            6 => [
                'title' => 'Booking Cancelled',
                'body'  => "We regret to inform you that your booking #{$booking->id} has been cancelled. Please contact support if needed."
            ],
            default => [
                'title' => 'Booking Updated',
                'body'  => "Your booking #{$booking->id} status has been updated."
            ]
        };
    }

    /**
     * Send FCM notification to the booking's farmer on status change
     */
    private function sendBookingNotification(Booking $booking, int $status)
    {
        try {
            if (!$booking->farmer_id)
                return;

            $farmer = Farmer::find($booking->farmer_id);
            if (!$farmer)
                return;

            $statusLabel = self::$statusLabels[$status] ?? 'Updated';
            $message = $this->getStatusMessage($booking, $status);
            $title = $message['title'];
            $body = $message['body'];

            $data = [
                'type' => 'booking_status',
                'booking_id' => (string) $booking->id,
                'booking_uid' => $booking->booking_uid ?? '',
                'status' => (string) $status,
                'status_label' => $statusLabel,
            ];

            // Save notification to DB
            PushNotification::create([
                'title' => $title,
                'body' => $body,
                'type' => 'booking_status',
                'farmer_id' => $farmer->id,
                'data' => $data,
            ]);

            // Send FCM if farmer has token
            if ($farmer->fcm_token) {
                $fcm = new FirebaseNotificationService();
                $fcm->sendToDevice($farmer->fcm_token, $title, $body, null, $data);
            }
        } catch (\Exception $e) {
            Log::error('Booking notification failed: ' . $e->getMessage());
        }
    }

    private function generateBookingId($stateCode, $deliveryDate)
    {
        $state = strtoupper($stateCode);
        $date = \Carbon\Carbon::parse($deliveryDate)->format('dmY');
        $unique = rand(10000, 99999);

        return "OD-BS-{$state}-{$date}-{$unique}";
    }

    /**
     * 🟢 Normal Hatchery Booking
     */
    public function bookHatchery(Request $request)
    {
        return $this->createBooking($request, 0); // 0 => normal hatchery
    }

    /**
     * 🔵 Spot Hatchery Booking
     */
    public function bookSpotHatchery(Request $request)
    {
        return $this->createBooking($request, 1); // 1 => spot hatchery
    }

    public function bookVehicleHatchery(Request $request)
    {
        return $this->createBooking($request, 2); // 2 => spot hatchery
    }

    /**
     * 🧩 Common Booking Logic (used by both endpoints)
     */
    private function createBooking(Request $request, int $bookingType)
    {
        $validator = Validator::make($request->all(), [
            // Customer name is optional in the user app — the field can be left
            // blank when the customer is the same as the logged-in farmer or
            // when the booking is being placed on behalf of someone whose name
            // isn't known yet.
            'customer_name' => 'nullable|string|max:255',
            'customer_mobile' => 'required|string|max:20',
            'dropping_location' => 'nullable|string|max:255',

            'hatchery_id' => 'required',
            'hatchery_name' => 'required|string|max:255',

            'category_id' => 'required|exists:hatchery_categories,id',

            'unit' => 'nullable|string|max:255',
            'no_of_pieces' => 'nullable|integer|min:0',
            'price' => 'nullable|numeric|min:0',
            'packing_date' => 'nullable|date',
            'delivery_date' => 'nullable|date',
            'drop_lat' => 'nullable|numeric',
            'drop_lng' => 'nullable|numeric',
            'salinity' => 'nullable|integer|min:0|max:40',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $farmer = Auth::user();

            // Handle different booking types
            if ($bookingType === 2) {
                // Vehicle booking - use VehicleAvailability model
                $vehicle = VehicleAvailability::findOrFail($request->hatchery_id);
                $vendorId = $vehicle->vendor_id;
                $location = HatcheryLocation::find($vehicle->location_id);
                $hatcheryIdForBooking = $request->hatchery_id; // Store vehicle availability ID
            } else {
                // Normal hatchery (0) or Spot hatchery (1) - use Hatchery model
                $hatchery = Hatchery::findOrFail($request->hatchery_id);
                $vendorId = $hatchery->vendor_id;
                $location = HatcheryLocation::find($hatchery->location_id);
                $hatcheryIdForBooking = $request->hatchery_id;
            }

            $stateCode = $location->state_code ?? 'TS';
            $deliveryDate = $request->packing_date ?? now()->toDateString();
            $bookingUid = $this->generateBookingId($stateCode, $deliveryDate);
            // $pricePerPiece = (float) $request->price / max(1, (int) $request->no_of_pieces);
            $pieces = (int) ($request->no_of_pieces ?? 0);

            \Log::info('Packing date from request', [
                'packing_date' => $request->packing_date,
                'type' => gettype($request->packing_date),
            ]);

            $booking = Booking::create([
                'booking_uid' => $bookingUid,
                'vendor_id' => $vendorId,
                'hatchery_id' => $hatcheryIdForBooking,
                'farmer_id' => $farmer->id ?? null,
                'customer_name' => $request->customer_name ?? '',
                'customer_mobile' => $request->customer_mobile,
                'hatchery_name' => $request->hatchery_name,
                'hatchery_location' => $location->location_name ?? 'Unknown',
                'unit' => $request->unit,
                'no_of_pieces' => $request->no_of_pieces,
                'dropping_location' => $request->dropping_location,
                'drop_lat' => $request->drop_lat,
                'drop_lng' => $request->drop_lng,
                'packing_date' => $request->packing_date,
                'delivery_date' => $request->delivery_date,
                'delivery_datetime' => $request->delivery_date,
                'delivery_expected' => $request->delivery_date,
                'category_id' => $request->category_id,
                // 'price' => $pricePerPiece,
                'estimated_price' => $request->price,
                'salinity' => $request->salinity,
                'is_spot' => $bookingType,
            ]);

            if ($booking->farmer_id) {
                $this->sendBookingNotification($booking, 1);
            }

            // Push to the vendor who owns the hatchery so they see the new
            // booking immediately without needing to refresh the vendor app.
            // Wrapped in try/catch so a bad/missing token never breaks the
            // customer's booking creation response.
            try {
                $vendor = \App\Models\Vendor::find($booking->vendor_id);
                if ($vendor && !empty($vendor->fcm_token)) {
                    $hatcheryLabel = $booking->hatchery_name
                        ?: (\App\Models\Hatchery::find($booking->hatchery_id)?->hatchery_name ?? '');
                    $bookedByLabel = $booking->customer_name
                        ?: ($booking->customer_mobile ?? '');
                    // Multi-line body. FCM shows the first line collapsed and
                    // the rest on expand; on the Best-Seeds vendor app the
                    // BigTextStyle used for this channel renders all three
                    // lines together.
                    $bodyLines = [
                        'booking id : ' . $booking->booking_uid,
                    ];
                    if ($bookedByLabel !== '') {
                        $bodyLines[] = 'booked by : ' . $bookedByLabel;
                    }
                    if ($hatcheryLabel !== '') {
                        $bodyLines[] = 'hatchery name : ' . $hatcheryLabel;
                    }
                    $notifBody = implode("\n", $bodyLines);
                    app(FirebaseNotificationService::class)->sendToDevice(
                        $vendor->fcm_token,
                        'New Booking received',
                        $notifBody,
                        null,
                        [
                            'type'          => 'new_booking',
                            'booking_id'    => (string) $booking->id,
                            'booking_uid'   => (string) $booking->booking_uid,
                            'customer_name' => (string) ($booking->customer_name ?? ''),
                            'hatchery_name' => (string) $hatcheryLabel,
                        ]
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Vendor new_booking push failed', [
                    'booking_id' => $booking->id,
                    'vendor_id'  => $booking->vendor_id,
                    'err'        => $e->getMessage(),
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => match ($bookingType) {
                    1 => 'Your spot hatchery booking request was sent successfully!',
                    2 => 'Your vehicle hatchery booking request was sent successfully!',
                    default => 'Your hatchery booking request was sent successfully!',
                },
                'data' => [
                    'booking_id' => $booking->id,
                    'is_spot' => $booking->is_spot,
                    'hatchery_name' => $booking->hatchery_name,
                    'category_id' => $booking->category_id,
                    'category_name' => optional($booking->category)->category_name,
                    'customer_name' => $booking->customer_name,
                    'customer_mobile' => $booking->customer_mobile,
                    'unit' => $booking->unit,
                    'no_of_pieces' => $booking->no_of_pieces,
                    'dropping_location' => $booking->dropping_location,
                    'packing_date' => $booking->packing_date,
                    'delivery_date' => $booking->delivery_date,
                    'price' => $booking->price,
                    'estimated_price' => $booking->estimated_price,
                    'hatchery_location' => $booking->hatchery_location,
                ]
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to book hatchery.',
                'error' => $e->getMessage(),
            ], 200);
        }
    }

    /**
     * 🔍 Show Booking Details
     */
    public function show($id)
    {
        try {
            $farmer = Auth::user();

            $booking = Booking::where('id', $id)
                ->where('farmer_id', $farmer->id)
                ->first();

            if (!$booking) {
                return response()->json([
                    'status' => false,
                    'message' => 'Booking not found or unauthorized access.',
                ], 200);
            }

            return response()->json([
                'status' => true,
                'message' => 'Your booking details fetched successfully!',
                'data' => [
                    'booking_id' => $booking->id,
                    'is_spot' => $booking->is_spot,
                    'hatchery_name' => $booking->hatchery_name,
                    'category_id' => $booking->category_id,
                    'category_name' => optional($booking->category)->category_name,
                    'customer_name' => $booking->customer_name,
                    'customer_mobile' => $booking->customer_mobile,
                    'unit' => $booking->unit,
                    'no_of_pieces' => $booking->no_of_pieces,
                    'dropping_location' => $booking->dropping_location,
                    'packing_date' => $booking->packing_date
                        ? $booking->packing_date->format('d-m-Y')
                        : null,
                    'estimated_price' => $booking->estimated_price,
                    // 'packing_date'      => $booking->packing_date,
                    'hatchery_location' => $booking->hatchery_location,
                    'created_at' => $booking->created_at->format('Y-m-d H:i:s'),
                ]
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch booking details.',
                'error' => $e->getMessage(),
            ], 200);
        }
    }



    public function myBookings(Request $request)
    {
        try {
            $userId = $request->user()->id;

            $query = Booking::with(['hatchery', 'category'])
                ->where('farmer_id', $userId);

            // 🔹 Tabs filter: all / hatchery / spot
            if ($request->has('type')) {
                if ($request->type === 'hatchery') {
                    $query->where('is_spot', 0);
                }
                if ($request->type === 'spot') {
                    $query->where('is_spot', 1);
                }
            }

            // 🔹 Status filter — accepts a single status (e.g. ?status=4) or a
            // comma-separated list (e.g. ?status=3,4). Used by the live-tracking
            // FAB to fetch In-Journey bookings directly instead of scanning the
            // paginated list.
            if ($request->filled('status')) {
                $statuses = array_filter(array_map('trim', explode(',', $request->status)), 'strlen');
                if (!empty($statuses)) {
                    $query->whereIn('status', $statuses);
                }
            }

            // 🔹 Search box (mic + text)
            if ($request->has('search') && $request->search !== '') {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('hatchery_name', 'LIKE', "%$search%")
                        ->orWhere('hatchery_location', 'LIKE', "%$search%")
                        ->orWhereDate('created_at', $search)
                        ->orWhereDate('packing_date', $search);
                });
            }

            // 🔹 Filter by exact date
            if ($request->has('date')) {
                $query->whereDate('created_at', $request->date);
            }
            // 🔹 Filter by month + year
            else if ($request->has('month') && $request->has('year')) {
                $query->whereMonth('created_at', $request->month)
                    ->whereYear('created_at', $request->year);
            }
            // 🔹 Filter by month only
            else if ($request->has('month') && !$request->has('year')) {
                $query->whereMonth('created_at', $request->month);
            }
            // 🔹 Filter by year only
            else if ($request->has('year') && !$request->has('month')) {
                $query->whereYear('created_at', $request->year);
            }

            $statusMap = [
                1 => 'Pending',
                2 => 'Confirmed',
                3 => 'Driver Assigned',
                4 => 'In Journey',
                5 => 'Delivered',
                6 => 'Cancelled',
            ];

            $paginated = $query->latest()->paginate(10);

            $bookings = $paginated->getCollection()->map(function ($booking) use ($statusMap) {

                return [
                    'booking_uid' => $booking->booking_uid,
                    'booking_id' => $booking->id,
                    'hatchery_id' => $booking->hatchery_id,
                    'hatchery_name' => $booking->hatchery_name,
                    'category_id' => $booking->category_id,
                    'category_name' => optional($booking->category)->category_name,
                    'no_of_pieces' => $booking->no_of_pieces,
                    'packing_date' => $booking->packing_date ? $booking->packing_date->format('d/m/Y') : null,
                    'dropping_location' => $booking->dropping_location,
                    'estimated_price' => $booking->estimated_price,
                    'delivery_datetime' => $booking->delivery_datetime
                        ? $booking->delivery_datetime->format('d/m/Y')
                        : ($booking->delivery_date ? $booking->delivery_date->format('d/m/Y') : ($booking->delivery_expected ? \Carbon\Carbon::parse($booking->delivery_expected)->format('d/m/Y') : null)),
                    'delivery_date' => $booking->delivery_date
                        ? $booking->delivery_date->format('d-m-Y')
                        : ($booking->delivery_datetime ? $booking->delivery_datetime->format('d-m-Y') : ($booking->delivery_expected ? \Carbon\Carbon::parse($booking->delivery_expected)->format('d-m-Y') : null)),
                    'created_at' => $booking->created_at
                        ? $booking->created_at->format('d/m/Y h:i A')
                        : null,
                    'status' => [
                        'value' => $booking->status ?? 1,
                        'label' => $statusMap[$booking->status] ?? 'Pending'
                    ],
                    'is_spot' => [
                        'value' => $booking->is_spot,
                        'label' => $booking->is_spot ? 'Spot Hatchery Booking' : 'Hatchery Booking'
                    ],
                ];
            });

            return response()->json([
                'status' => true,
                'message' => $paginated->isEmpty() ? 'No bookings found' : 'Bookings fetched successfully',

                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'next_page_url' => $paginated->nextPageUrl(),
                    'prev_page_url' => $paginated->previousPageUrl(),
                ],

                'bookings' => $bookings,
            ], 200);


        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch bookings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function mybookingDetails($bookingId)
    {
        try {
            $userId = auth()->id();

            $booking = Booking::with(['hatchery.vendor', 'category'])
                ->where('id', $bookingId)
                ->where('farmer_id', $userId)
                ->first();

            if (!$booking) {
                return response()->json([
                    'status' => false,
                    'message' => 'Booking not found'
                ], 404);
            }

            // Status mappings
            $statusMap = [
                1 => ['Pending', 'Waiting for confirmation from vendor'],
                2 => ['Confirmed', 'Your order is confirmed'],
                3 => ['Driver Assigned', 'Driver has been assigned'],
                4 => ['In Journey', 'Vendor started processing your booking'],
                5 => ['Delivered', 'Order delivered successfully'],
                6 => ['Cancelled', 'Booking cancelled'],
            ];

            // Cancellation reasons
            $reasonMap = [
                1 => "Delay in processing",
                2 => "Incorrect order details",
                3 => "Wrong quantity requested",
                4 => "Stock quality issues",
                5 => "Other"
            ];

            // BASE response (common for all statuses)
            $response = [
                'booking_uid' => $booking->booking_uid,
                'booking_id' => $booking->id,
                'hatchery_id' => $booking->hatchery_id,
                'hatchery_name' => $booking->hatchery_name,

                'status' => [
                    'value' => $booking->status,
                    'label' => $statusMap[$booking->status][0],
                    'message' => $statusMap[$booking->status][1],
                ],

                'category_id' => $booking->category_id,
                'category_name' => optional($booking->category)->category_name,
                'delivery_datetime' => $booking->delivery_datetime
                    ? $booking->delivery_datetime->format('d/m/Y')
                    : ($booking->delivery_date ? $booking->delivery_date->format('d/m/Y') : ($booking->delivery_expected ? \Carbon\Carbon::parse($booking->delivery_expected)->format('d/m/Y') : null)),
                'unit' => $booking->unit,
                'salinity' => $booking->salinity,
                'no_of_pieces' => $booking->no_of_pieces,
                'estimated_price' => $booking->estimated_price,
                'dropping_location' => $booking->dropping_location,
                // Pickup: driver's start address once the trip is in progress;
                // otherwise fall back to the vendor/admin-configured hatchery
                // location. Never use `unit` (that's the seed unit field).
                'pickup_location' => !empty($booking->vehicle_start_address)
                    ? $booking->vehicle_start_address
                    : ($booking->hatchery_location ?? ''),
                'packing_date' => $booking->packing_date
                    ? $booking->packing_date->format('d-m-Y')
                    : null,
                'delivery_date' => $booking->delivery_date
                    ? $booking->delivery_date->format('d-m-Y')
                    : ($booking->delivery_datetime ? $booking->delivery_datetime->format('d-m-Y') : ($booking->delivery_expected ? \Carbon\Carbon::parse($booking->delivery_expected)->format('d-m-Y') : null)),
                // 'delivery_datetime' => $booking->created_at
                //     ? $booking->created_at->format('d/m/Y h:i A')
                //     : null,
                'note' => "For any changes, please contact us.",
            ];

            // Vendor contact number - use hatchery call_number first, fallback to vendor mobile
            $response['vendor_mobile'] = $booking->hatchery?->call_number ?? $booking->hatchery?->vendor?->mobile;

            // Driver details (when status >= 3: Driver Assigned)
            if ($booking->status >= 3 && $booking->status != 6) {
                $driverImage = $booking->driver_image;
                if ($driverImage && !preg_match('/^https?:\/\//', $driverImage)) {
                    $driverImage = url('public/' . ltrim($driverImage, '/'));
                }

                $response['driver'] = [
                    'name' => $booking->driver_name,
                    'mobile' => $booking->driver_mobile,
                    'image' => $driverImage,
                    'vehicle_number' => $booking->vehicle_number,
                    'vehicle_started_date' => $booking->vehicle_started_date
                        ? $booking->vehicle_started_date->format('d-m-Y h:i A')
                        : null,
                    'vehicle_start_address' => $booking->vehicle_start_address,
                ];
            }

            // Descriptions: per-booking override or default from app config
            $defaultBookingDesc = AppConfig::getValue(
                'default_booking_description',
                "We've received your booking. Within a few days, we will assign your vehicle."
            );
            $defaultVehicleDesc = AppConfig::getValue(
                'default_vehicle_description',
                "We've received your booking. Within a few days, we will assign your vehicle"
            );

            $response['booking_description'] = !empty($booking->vendor_booking_description)
                ? $booking->vendor_booking_description
                : $defaultBookingDesc;
            $response['vehicle_description'] = !empty($booking->vendor_vehicle_description)
                ? $booking->vendor_vehicle_description
                : $defaultVehicleDesc;

            // ADD STATUS-WISE EXTRA DATA
            if ($booking->status == 6) {
                // Cancelled
                $reasonText = $reasonMap[$booking->cancellation_reason] ?? null;
                if ($booking->cancellation_reason == 5 && $booking->cancellation_reason_text) {
                    $reasonText = $booking->cancellation_reason_text;
                }
                $response['cancellation_reason'] = [
                    'code' => $booking->cancellation_reason,
                    'text' => $reasonText,
                ];
            }

            if (in_array($booking->status, [2, 3, 4, 5])) {
                // SHOW TIMELINE (used in Figma design)
                $response['booking_status_timeline'] = [
                    [
                        'label' => 'Booking Confirmed',
                        'time' => $booking->confirmed_at
                            ? $booking->confirmed_at->format('d-M-Y h:i A')
                            : ($booking->status >= 2 ? $booking->created_at->format('d-M-Y h:i A') : ''),
                        'completed' => $booking->status >= 2
                    ],
                    [
                        'label' => 'Driver Assigned',
                        'time' => $booking->driver_assigned_at
                            ? $booking->driver_assigned_at->format('d-M-Y h:i A')
                            : '',
                        'completed' => $booking->status >= 3
                    ],
                    [
                        'label' => 'In Journey',
                        'time' => $booking->in_progress_at
                            ? $booking->in_progress_at->format('d-M-Y h:i A')
                            : '',
                        'completed' => $booking->status >= 4
                    ],
                    [
                        'label' => 'Delivered',
                        'time' => $booking->delivered_at
                            ? $booking->delivered_at->format('d-M-Y h:i A')
                            : '',
                        'completed' => $booking->status == 5
                    ]
                ];
            }

            return response()->json([
                'status' => true,
                'message' => 'Booking details fetched successfully',
                'booking' => $response,
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch booking details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function cancelBooking(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'reason_code' => 'required|integer|between:1,5',
            'other_reason' => 'nullable|string|max:500',
        ]);

        $reasonMap = [
            1 => "Delay in processing",
            2 => "Incorrect order details",
            3 => "Wrong quantity requested",
            4 => "Stock quality issues",
            5 => "Other"
        ];

        $booking = Booking::where('id', $request->booking_id)
            ->where('farmer_id', auth()->id())
            ->first();

        if (!$booking) {
            return response()->json([
                'status' => false,
                'message' => 'Booking not found'
            ], 404);
        }

        // Update booking
        $booking->status = 6;
        $booking->cancellation_reason = $request->reason_code;
        if ($request->reason_code == 5 && $request->other_reason) {
            $booking->cancellation_reason_text = $request->other_reason;
        }
        $booking->save();

        return response()->json([
            'status' => true,
            'message' => 'Booking cancelled successfully',
            'data' => [
                'booking_id' => $booking->id,
                'status' => 'Cancelled',
                'reason_text' => $reasonMap[$request->reason_code]  // FIXED ✔
            ]
        ], 200);
    }

    /**
     * Edit a booking's details — ONLY while it is still Pending (status 1).
     * Lets the farmer fix anything they missed before the vendor confirms it.
     * Once confirmed (status != 1) the booking is locked and this returns 422.
     */
    public function updateBooking(Request $request)
    {
        $request->validate([
            'booking_id'        => 'required|exists:bookings,id',
            'no_of_pieces'      => 'nullable|integer|min:0',
            'salinity'          => 'nullable|integer|min:0|max:40',
            'packing_date'      => 'nullable|date',
            'delivery_date'     => 'nullable|date',
            'delivery_datetime' => 'nullable|date',
            'delivery_expected' => 'nullable|date',
            'dropping_location' => 'nullable|string|max:255',
            'drop_lat'          => 'nullable|numeric',
            'drop_lng'          => 'nullable|numeric',
        ]);

        $user = auth()->user();
        $booking = Booking::where('id', $request->booking_id)
            ->where(function($q) use ($user) {
                $q->where('farmer_id', $user->id);
                if ($user && !empty($user->mobile)) {
                    $q->orWhere('customer_mobile', $user->mobile);
                }
            })
            ->first();

        if (!$booking) {
            return response()->json([
                'status'  => false,
                'message' => 'Booking not found',
            ], 404);
        }

        // Editing is only allowed in the Pending stage. After the vendor
        // confirms the order (status moves beyond 1) the details are locked.
        if ((int) $booking->status !== 1) {
            return response()->json([
                'status'  => false,
                'message' => 'This booking can no longer be edited because it is already confirmed.',
            ], 422);
        }

        // Only overwrite the fields actually sent, so a partial edit never
        // wipes the values the farmer left unchanged.
        foreach (['no_of_pieces', 'salinity', 'dropping_location', 'drop_lat', 'drop_lng'] as $field) {
            if ($request->has($field) && $request->{$field} !== null && $request->{$field} !== '') {
                $booking->{$field} = $request->{$field};
            }
        }
        if ($request->filled('packing_date')) {
            $booking->packing_date = \Carbon\Carbon::parse($request->packing_date)->format('Y-m-d');
        }
        $delivDate = $request->delivery_date ?? $request->delivery_datetime ?? $request->delivery_expected;
        if (!empty($delivDate)) {
            $parsedDate = \Carbon\Carbon::parse($delivDate);
            $booking->delivery_date = $parsedDate->format('Y-m-d');
            $booking->delivery_datetime = $parsedDate->format('Y-m-d H:i:s');
            $booking->delivery_expected = $parsedDate->format('Y-m-d');
        }
        $booking->save();

        return response()->json([
            'status'  => true,
            'message' => 'Booking updated successfully',
            'data'    => [
                'booking_id'        => $booking->id,
                'no_of_pieces'      => $booking->no_of_pieces,
                'salinity'          => $booking->salinity,
                'packing_date'      => $booking->packing_date,
                'delivery_date'     => $booking->delivery_date,
                'dropping_location' => $booking->dropping_location,
                'drop_lat'          => $booking->drop_lat,
                'drop_lng'          => $booking->drop_lng,
            ],
        ], 200);
    }


    public function updateBookingStatus(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'status' => 'required|integer|between:1,6',
        ]);

        $statusMap = [
            1 => 'Pending',
            2 => 'In Journey',
            3 => 'Confirmed',
            4 => 'Driver Assigned',
            5 => 'Delivered',
            6 => 'Cancelled'
        ];

        $booking = Booking::find($request->booking_id);

        if (!$booking) {
            return response()->json([
                'status' => false,
                'message' => 'Booking not found'
            ], 404);
        }

        // Update status
        $booking->status = $request->status;
        $booking->save();

        return response()->json([
            'status' => true,
            'message' => 'Booking status updated successfully',
            'data' => [
                'booking_id' => $booking->id,
                'new_status_value' => $booking->status,
                'new_status_label' => $statusMap[$booking->status]
            ]
        ], 200);
    }








    //working
    //         /**
    //  * ======================================
    //  * GET /api/farmer/my-bookings
    //  * ======================================
    //  * Fetch all bookings of the logged-in user
    //  */
    // public function myBookings(Request $request)
    // {
    //     try {
    //         $userId = $request->user()->id;

    //         $query = Booking::with(['hatchery', 'category'])
    //             ->where('farmer_id', $userId);

    //         // 🔹 Optional filter: specific date
    //         if ($request->has('date')) {
    //             $query->whereDate('created_at', $request->date);
    //         }
    //         // 🔹 Optional filter: filter by month & year
    //         else if ($request->has('month') && $request->has('year')) {
    //             $query->whereMonth('created_at', $request->month)
    //                 ->whereYear('created_at', $request->year);
    //         }
    //         // 🔹 Default: today's bookings
    //         else {
    //             $query->whereDate('created_at', now()->toDateString());
    //         }

    //         // Fetch bookings
    //         $bookings = $query->latest()->get()->map(function ($booking) {
    //             $hatchery = $booking->hatchery;

    //             // KEEPING YOUR ORIGINAL IMAGE LOGIC SAME
    //             $images = [];
    //             if ($hatchery && is_array($hatchery->image)) {
    //                 $images = $hatchery->image;
    //             }

    //             return [
    //                 'booking_id'        => $booking->id,
    //                 'hatchery_name'     => $booking->hatchery_name ?? ($hatchery->hatchery_name ?? 'Unknown'),

    //                 // 🔹 ADDED CATEGORY (single category)
    //                 'category_id'       => $booking->category_id,
    //                 'category_name'     => optional($booking->category)->category_name,

    //                 // 🔹 BOOKING TYPE
    //                 'booking_type'      => $booking->is_spot ? 'Spot Booking' : 'Normal Booking',

    //                 // EXISTING FIELDS (unchanged)
    //                 'customer_name'     => $booking->customer_name,
    //                 'customer_mobile'   => $booking->customer_mobile,
    //                 'no_of_pieces'      => $booking->no_of_pieces,
    //                 'dropping_location' => $booking->dropping_location,
    //                 'packing_date'      => $booking->packing_date ? $booking->packing_date->format('Y-m-d') : null,

    //                 // 🔹 ADDED MONTH/YEAR (for frontend filter)
    //                 'month'             => $booking->created_at ? $booking->created_at->format('F') : null,
    //                 'year'              => $booking->created_at ? $booking->created_at->format('Y') : null,

    //                 // KEEPING YOUR MEDIA LOGIC
    //                 'media_type'        => $hatchery->media_type ?? 'image',
    //                 'images'            => $images,
    //             ];
    //         });

    //         return response()->json([
    //             'status'   => true,
    //             'message'  => $bookings->isEmpty() ? 'No bookings found' : 'Bookings fetched successfully',
    //             'bookings' => $bookings,
    //         ], 200);

    //     } catch (Exception $e) {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Failed to fetch bookings',
    //             'error'   => $e->getMessage(),
    //         ], 500);
    //     }
    // }



    /**
     * GET /api/farmer/pending-rating
     * Returns the most recent delivered booking that the farmer hasn't rated
     * yet, so the app can show the rating popup. Works whether the booking was
     * delivered by a driver or marked delivered from the admin panel (both set
     * status = 5). Returns booking = null when there is nothing to rate.
     */
    public function pendingRating()
    {
        try {
            $farmer = Auth::user();

            // Only prompt for RECENT deliveries. Without this, an old backlog of
            // delivered-but-unrated bookings keeps surfacing one after another
            // ("the rating keeps popping up"). Deliveries older than this window
            // are considered stale to rate and are skipped.
            $ratingWindowDays = 7;

            $booking = Booking::with(['hatchery', 'category'])
                ->where('farmer_id', $farmer->id)
                ->where('status', 5) // Delivered
                ->whereDoesntHave('rating')   // not yet rated
                ->whereNull('rating_dismissed_at') // not dismissed by the user
                ->whereNotNull('delivered_at') // must have a delivery timestamp
                ->where('delivered_at', '>=', now()->subDays($ratingWindowDays)) // recent only
                ->orderByDesc('delivered_at')
                ->orderByDesc('id')
                ->first();

            if (!$booking) {
                return response()->json([
                    'status' => true,
                    'booking' => null,
                ]);
            }

            $hatcheryName = $booking->hatchery_name
                ?? ($booking->hatchery->hatchery_name ?? null);

            return response()->json([
                'status' => true,
                'booking' => [
                    'id' => $booking->id,
                    'booking_uid' => $booking->booking_uid,
                    'hatchery_name' => $hatcheryName,
                    'category_name' => $booking->category->category_name ?? null,
                    'no_of_pieces' => $booking->no_of_pieces,
                    'unit' => $booking->unit,
                    'driver_name' => $booking->driver_name,
                    'driver_mobile' => $booking->driver_mobile,
                    'delivered_at' => $booking->delivered_at,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Pending Rating Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
            ], 500);
        }
    }

    /**
     * POST /api/farmer/dismiss-rating
     * Farmer closed/cancelled the rating popup without rating. Mark the booking
     * so the popup is never shown again for it (persists across devices and
     * reinstalls — pending-rating excludes dismissed bookings).
     */
    public function dismissRating(Request $request)
    {
        try {
            $farmer = Auth::user();

            $validator = Validator::make($request->all(), [
                'booking_id' => 'required|exists:bookings,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $booking = Booking::where('id', $request->booking_id)
                ->where('farmer_id', $farmer->id)
                ->where('status', 5)
                ->first();

            if (!$booking) {
                return response()->json([
                    'status' => false,
                    'message' => 'Booking not found or not yet delivered',
                ], 404);
            }

            // Don't overwrite an existing dismissal; idempotent.
            if (!$booking->rating_dismissed_at) {
                $booking->rating_dismissed_at = now();
                $booking->save();
            }

            return response()->json([
                'status' => true,
                'message' => 'Got it, we won\'t ask again.',
            ]);
        } catch (\Exception $e) {
            Log::error('Dismiss Rating Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
            ], 500);
        }
    }

    /**
     * POST /api/farmer/rate-booking
     * Farmer submits rating and message after delivery
     */
    public function rateBooking(Request $request)
    {
        try {
            $farmer = Auth::user();

            $validator = Validator::make($request->all(), [
                'booking_id' => 'required|exists:bookings,id',
                'rating' => 'required|integer|min:1|max:5',
                'message' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $booking = Booking::where('id', $request->booking_id)
                ->where('farmer_id', $farmer->id)
                ->where('status', 5) // Only delivered bookings
                ->first();

            if (!$booking) {
                return response()->json([
                    'status' => false,
                    'message' => 'Booking not found or not yet delivered',
                ], 404);
            }

            // Check if already rated
            if ($booking->rating) {
                return response()->json([
                    'status' => false,
                    'message' => 'You have already rated this booking',
                ], 409);
            }

            $rating = BookingRating::create([
                'booking_id' => $booking->id,
                'farmer_id' => $farmer->id,
                'rating' => $request->rating,
                'message' => $request->message,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Thank you for your feedback!',
                'rating' => [
                    'rating' => $rating->rating,
                    'message' => $rating->message,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Rating Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
            ], 500);
        }
    }
}


