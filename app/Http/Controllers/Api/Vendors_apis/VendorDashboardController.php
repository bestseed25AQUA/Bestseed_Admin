<?php

namespace App\Http\Controllers\Api\Vendors_apis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\User_apis\VehicleController;
use App\Models\Booking;
use App\Models\Hatchery;
use App\Models\VehicleAvailability;
use App\Services\RouteTimelineService;
use App\Models\VehicleTracking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Driver;
use App\Models\Farmer;
use App\Models\PushNotification;
use App\Models\TrackingAlert;
use App\Services\FirebaseNotificationService;
use Exception;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class VendorDashboardController extends Controller
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
     * Send FCM notification to the booking's farmer on status changes
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

    public function bookings(Request $request)
    {
        $vendor = Auth::user();
        $vendorId = $vendor->id;

        $query = Booking::with([
            'hatchery:id,hatchery_name,is_spot,category_id,location_id',
            'hatchery.category:id,category_name',
            'hatchery.location:id,location_name,full_address',
            'vehicleAvailability:id,vehicle_name,category_id,location_id',
            'vehicleAvailability.category:id,category_name',
            'vehicleAvailability.location:id,location_name,full_address',
            'farmer:id,first_name,last_name,mobile'
        ])
            ->where('vendor_id', $vendorId)
            ->orderByDesc('id');

        /* ---------------- TAB FILTER ---------------- */
        if ($request->filled('tab')) {
            match ($request->tab) {
                'new' => $query->where('status', 1),          // Pending
                'current' => $query->whereIn('status', [2, 3, 4]), // In progress → Driver assigned
                'tracking' => $query->where('status', 4),     // In Progress only (for tracking)
                'past' => $query->whereIn('status', [5, 6]),   // Delivered / Cancelled
                default => null
            };
        }

        /* ---------------- GLOBAL SEARCH ---------------- */
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {

                // 🔹 Booking ID / UID
                $q->where('id', 'like', "%$search%")
                    ->orWhere('booking_uid', 'like', "%$search%");

                // 🔹 Hatchery details
                $q->orWhereHas('hatchery', function ($hq) use ($search) {
                    $hq->where('hatchery_name', 'like', "%$search%")
                        ->orWhereHas('category', function ($cq) use ($search) {
                            $cq->where('category_name', 'like', "%$search%");
                        })
                        ->orWhereHas('location', function ($lq) use ($search) {
                            $lq->where('location_name', 'like', "%$search%")
                                ->orWhere('full_address', 'like', "%$search%");
                        });
                });

                // 🔹 Farmer name
                $q->orWhereHas('farmer', function ($fq) use ($search) {
                    $fq->where('first_name', 'like', "%$search%")
                        ->orWhere('last_name', 'like', "%$search%");
                });

                // 🔹 Pieces
                $q->orWhere('no_of_pieces', 'like', "%$search%");

                // 🔹 Booking type text search
                if (str_contains(strtolower($search), 'spot')) {
                    $q->orWhereHas('hatchery', fn($hq) => $hq->where('is_spot', 1));
                }

                if (str_contains(strtolower($search), 'hatchery')) {
                    $q->orWhereHas('hatchery', fn($hq) => $hq->where('is_spot', 0));
                }
            });
        }

        /* ---------------- CATEGORY FILTER ---------------- */
        if ($request->filled('category_id')) {
            $query->whereHas(
                'hatchery',
                fn($q) =>
                $q->where('category_id', $request->category_id)
            );
        }

        /* ---------------- LOCATION FILTER ---------------- */
        if ($request->filled('location_id')) {
            $query->whereHas(
                'hatchery',
                fn($q) =>
                $q->where('location_id', $request->location_id)
            );
        }

        /* ---------------- BOOKING TYPE FILTER ---------------- */
        if ($request->filled('booking_type')) {
            match ($request->booking_type) {
                'hatchery' =>
                $query->whereHas('hatchery', fn($q) => $q->where('is_spot', 0)),

                'spot' =>
                $query->whereHas('hatchery', fn($q) => $q->where('is_spot', 1)),

                'vehicle' =>
                $query->where('booking_type', 'vehicle'),

                'vehicle_availability' =>
                $query->whereHas('hatchery', fn($q) => $q->where('is_spot', 2)),

                default => null
            };
        }

        /* ---------------- STATUS FILTER (specific status value) ---------------- */
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        /* ---------------- VEHICLE AVAILABILITY FILTER ---------------- */
        if ($request->filled('vehicle_availability')) {
            if ($request->vehicle_availability === 'assigned') {
                $query->whereNotNull('driver_name')->where('driver_name', '!=', '');
            } elseif ($request->vehicle_availability === 'not_assigned') {
                $query->where(function ($q) {
                    $q->whereNull('driver_name')->orWhere('driver_name', '');
                });
            }
        }

        $bookings = $query->paginate(10);

        // Counts for every tab (unfiltered, just this vendor). One grouped
        // query instead of a COUNT per tab — the app shows a badge on each
        // status tab (In Journey / Confirmed / Driver Assigned / Delivered /
        // Failed), which would otherwise be five more round trips.
        $perStatus = Booking::where('vendor_id', $vendorId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $sumOf = fn(array $statuses) => collect($statuses)
            ->sum(fn($s) => (int) ($perStatus[$s] ?? 0));

        $counts = [
            'all' => (int) $perStatus->sum(),
            'new' => $sumOf([1]),
            'current' => $sumOf([2, 3, 4]),
            'past' => $sumOf([5, 6]),
            // Keyed by the same status values the app filters on, so a tab can
            // look its badge up directly by status instead of by name.
            'by_status' => [
                1 => $sumOf([1]),
                2 => $sumOf([2]),
                3 => $sumOf([3]),
                4 => $sumOf([4]),
                5 => $sumOf([5]),
                6 => $sumOf([6]),
            ],
        ];

        return response()->json([
            'status' => true,
            'message' => 'Bookings fetched successfully',
            'counts' => $counts,
            'pagination' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
                'next_page_url' => $bookings->nextPageUrl(),
                'prev_page_url' => $bookings->previousPageUrl(),
            ],
            'bookings' => $bookings->map(fn($b) => $this->formatBooking($b))
        ]);
    }

    private function formatBooking($b)
    {
        // Determine booking type from is_spot field
        $isVehicleAvailability = $b->is_spot == 2;

        // For vehicle availability bookings, use vehicleAvailability relationship
        $va = $isVehicleAvailability ? $b->vehicleAvailability : null;

        return [
            'booking_uid' => $b->booking_uid,
            'booking_id' => $b->id,

            'booking_type' => $b->booking_type ?? match ((int) $b->is_spot) {
                1 => 'spot_hatchery',
                2 => 'vehicle_availability',
                default => 'hatchery',
            },

            'hatchery_id' => $b->hatchery_id,
            'hatchery_name' => $isVehicleAvailability
                ? ($va?->vehicle_name)
                : $b->hatchery?->hatchery_name,

            'category_id' => $isVehicleAvailability
                ? ($va?->category?->id)
                : $b->hatchery?->category?->id,
            'category_name' => $isVehicleAvailability
                ? ($va?->category?->category_name)
                : $b->hatchery?->category?->category_name,

            'no_of_pieces' => $b->no_of_pieces,

            'packing_date' => optional($b->packing_date)->format('d/m/Y'),

            'delivery_datetime' => $b->delivery_datetime
                ? Carbon::parse($b->delivery_datetime)->format('d/m/Y')
                : ($b->delivery_date ? Carbon::parse($b->delivery_date)->format('d/m/Y') : ($b->delivery_expected ? Carbon::parse($b->delivery_expected)->format('d/m/Y') : null)),
            'delivery_date' => $b->delivery_date
                ? Carbon::parse($b->delivery_date)->format('d/m/Y')
                : ($b->delivery_datetime ? Carbon::parse($b->delivery_datetime)->format('d/m/Y') : ($b->delivery_expected ? Carbon::parse($b->delivery_expected)->format('d/m/Y') : null)),
            'drop_location' => $b->dropping_location,
            'salinity' => $b->salinity,
            'price' => $b->price,
            'vendor_booking_description' => $b->vendor_booking_description,
            'vendor_vehicle_description' => $b->vendor_vehicle_description,
            'farmer' => [
                'name' => trim($b->farmer?->first_name . ' ' . $b->farmer?->last_name),
                // 'mobile' => $b->farmer?->mobile,
            ],

            'driver' => [
                'driver_id' => $b->driver_id,
                'driver_name' => $b->driver_name,
                'driver_mobile' => $b->driver_mobile,
                'vehicle_number' => $b->vehicle_number,
                'vehicle_start_date' => $b->vehicle_started_date
                    ? Carbon::parse($b->vehicle_started_date)->format('Y-m-d')
                    : null,
                'vehicle_end_date' => $b->vehicle_end_date,
                'vehicle_start_lat' => $b->vehicle_start_lat,
                'vehicle_start_lng' => $b->vehicle_start_lng,
                'vehicle_start_address' => $b->vehicle_start_address,
                'priority' => $b->priority,
            ],

            // 'status' => $this->uiStatus($b),

            'status' => [
                'value' => $b->status,
                'label' => $this->uiStatus($b),
            ],
            // // ✅ ADD THIS (IMPORTANT)
            'cancellation_reason' => $b->status == 6 ? [
                'code' => $b->cancellation_reason,
                'text' => $b->cancel_reason_text,
            ] : null,

            'pickup' => [
                'location_name' => $b->vehicle_start_address
                    ?? ($isVehicleAvailability
                        ? $va?->location?->location_name
                        : $b->hatchery?->location?->location_name),
                'lat' => $b->vehicle_start_lat
                    ?? $b->pickup_lat
                    ?? ($isVehicleAvailability
                        ? $va?->location?->latitude
                        : $b->hatchery?->location?->latitude),
                'lng' => $b->vehicle_start_lng
                    ?? $b->pickup_lng
                    ?? ($isVehicleAvailability
                        ? $va?->location?->longitude
                        : $b->hatchery?->location?->longitude),
                'vehicle_started_date' => $b->vehicle_started_date
                    ? Carbon::parse($b->vehicle_started_date)->format('Y-m-d H:i:s')
                    : null,
                'in_progress_at' => $b->in_progress_at
                    ? Carbon::parse($b->in_progress_at)->format('Y-m-d H:i:s')
                    : null,
            ],

            // 🚚 LIVE VEHICLE LOCATION
            'current_location' => [
                'lat' => $b->driver_lat,
                'lng' => $b->driver_lng,
                'location_name' => $b->driver_location_name,
                'updated_at' => $b->updated_at
                    ? $b->updated_at->format('Y-m-d H:i:s')
                    : null,
            ],

            // 📍 DESTINATION
            'destination' => [
                'location_name' => $b->dropping_location,
                'lat' => $b->drop_lat,
                'lng' => $b->drop_lng,
            ],

            // Intermediate drop locations for multi-drop route calculation
            'route_waypoints' => $this->getRouteWaypoints($b),
        ];
    }


    private function getRouteWaypoints($b)
    {
        $waypoints = [];
        if ($b->priority && $b->priority > 1 && $b->driver_id && $b->vehicle_started_date) {
            $intermediateBookings = Booking::where('driver_id', $b->driver_id)
                ->where('vehicle_started_date', $b->vehicle_started_date)
                ->where('status', 4) // Only in-progress bookings — completed drops are already delivered
                ->where('priority', '<', $b->priority)
                ->orderBy('priority', 'asc')
                ->get();

            foreach ($intermediateBookings as $ib) {
                if ($ib->drop_lat && $ib->drop_lng) {
                    $waypoints[] = [
                        'lat' => (float) $ib->drop_lat,
                        'lng' => (float) $ib->drop_lng,
                    ];
                }
            }
        }
        return $waypoints;
    }

    private function uiStatus($booking)
    {
        return match ($booking->status) {
            1 => 'accept/reject',      // New booking → show buttons
            2 => 'in_progress',        // Processing
            3 => 'confirmed',          // Confirmed
            4 => 'vehicle tracking',   // Driver assigned → tracking enabled
            5 => 'completed',          // Delivered
            6 => 'cancelled',          // Cancelled
            default => 'unknown',
        };
    }



    public function acceptBooking($id)
    {
        try {
            $vendorId = Auth::id();

            $booking = Booking::where('id', $id)
                ->where('vendor_id', $vendorId)
                ->where('status', 1) // only NEW bookings
                ->first();

            if (!$booking) {
                return response()->json([
                    'status' => false,
                    'message' => 'Booking not found or already processed'
                ], 404);
            }

            // Status change: New → In Progress
            $booking->status = 2;
            $booking->save();

            $this->sendBookingNotification($booking, 2);

            return response()->json([
                'status' => true,
                'message' => 'Booking accepted successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Accept Booking Error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong'
            ], 500);
        }
    }



    public function rejectBooking(Request $request, $id)
    {
        try {
            $request->validate([
                'reason_code' => 'required|integer|between:1,5'
            ]);

            $vendorId = Auth::id();

            $booking = Booking::where('id', $id)
                ->where('vendor_id', $vendorId)
                ->where('status', 1) // only NEW bookings
                ->first();

            if (!$booking) {
                return response()->json([
                    'status' => false,
                    'message' => 'Booking not found or already processed'
                ], 404);
            }

            // ✅ Reason Map
            $reasonMap = [
                1 => "Delay in processing",
                2 => "Incorrect order details",
                3 => "Wrong quantity requested",
                4 => "Stock quality issues",
                5 => "Other"
            ];

            // ✅ Update booking
            $booking->status = 6; // Cancelled
            $booking->cancellation_reason = $request->reason_code;
            $booking->cancel_reason_text = $reasonMap[$request->reason_code]; // optional column
            $booking->save();

            $this->sendBookingNotification($booking, 6);

            return response()->json([
                'status' => true,
                'message' => 'Booking rejected successfully',
                'data' => [
                    'booking_id' => $booking->id,
                    'reason_code' => $request->reason_code,
                    'reason_text' => $reasonMap[$request->reason_code]
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Reject Booking Error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong'
            ], 500);
        }
    }


    public function updateBooking(Request $request, $id)
    {
        try {
            $vendorId = Auth::id();
            $booking = Booking::where('id', $id)
                ->where('vendor_id', $vendorId)
                ->whereIn('status', [1, 2, 3, 4]) // allow edit for active bookings
                ->first();

            if (!$booking) {
                return response()->json([
                    'status' => false,
                    'message' => 'Booking not found'
                ], 404);
            }

            // ✅ Validation
            $request->validate([
                'no_of_pieces' => 'nullable|integer|min:1',
                'salinity' => 'nullable|string|max:50',
                'dropping_location' => 'nullable|string',
                'packing_date' => 'nullable|date',
                'price' => 'nullable|numeric|min:0',
                'delivery_datetime' => 'nullable|date',
                'delivery_date' => 'nullable|date',
                'vendor_booking_description' => 'nullable|string',
                'vendor_vehicle_description' => 'nullable|string',

                // Driver
                'driver_name' => 'nullable|string|max:100',
                'driver_mobile' => 'nullable|string|max:20',
                'vehicle_number' => 'nullable|string|max:50',
                'drop_lat' => 'nullable|numeric',
                'drop_lng' => 'nullable|numeric',
                'status' => 'nullable|integer|in:2,3,4,5,6',
                'delivery_reason' => 'nullable|integer|between:1,5',
            ]);

            // ✅ Recalculate estimated_price when no_of_pieces changes
            $newPieces = $request->no_of_pieces ?? $booking->no_of_pieces;
            $estimatedPrice = $booking->estimated_price;
            if ($request->filled('no_of_pieces')) {
                $pricePerPiece = 0;
                if ($booking->is_spot == 2) {
                    $vehicle = VehicleAvailability::with('hatchery')->find($booking->hatchery_id);
                    $pricePerPiece = $vehicle->hatchery->price ?? 0;
                } else {
                    $hatchery = Hatchery::find($booking->hatchery_id);
                    $pricePerPiece = $hatchery->price ?? 0;
                }
                if ($pricePerPiece > 0) {
                    $estimatedPrice = $newPieces * $pricePerPiece;
                }
            }

            // ✅ Update booking fields
            $booking->update([
                'no_of_pieces' => $newPieces,
                'estimated_price' => $estimatedPrice,
                'salinity' => $request->salinity ?? $booking->salinity,
                'dropping_location' => $request->dropping_location ?? $booking->dropping_location,
                'drop_lat' => $request->drop_lat ?? $booking->drop_lat,
                'drop_lng' => $request->drop_lng ?? $booking->drop_lng,
                'packing_date' => $request->packing_date ?? $booking->packing_date,
                'price' => $request->price ?? $booking->price,
                'delivery_datetime' => $request->delivery_datetime ?? $request->delivery_date ?? $booking->delivery_datetime,
                'delivery_date' => $request->delivery_date ?? $request->delivery_datetime ?? $booking->delivery_date,
                'delivery_expected' => $request->delivery_datetime ?? $request->delivery_date ?? $booking->delivery_expected,
                'vendor_booking_description' => $request->vendor_booking_description ?? $booking->vendor_booking_description,
                'vendor_vehicle_description' => $request->vendor_vehicle_description ?? $booking->vendor_vehicle_description,
            ]);


            // ✅ Driver update (if provided)
            if ($request->filled('driver_name') || $request->filled('driver_mobile')) {
                $booking->driver_name = $request->driver_name;
                $booking->driver_mobile = $request->driver_mobile;
                $booking->vehicle_number = $request->vehicle_number;
                $booking->status = 4; // vehicle tracking
                $booking->save();
            }

            // ✅ Status update (if provided)
            if ($request->filled('status')) {
                $newStatus = (int) $request->status;
                $currentStatus = $booking->status;

                // Validate transition: Delivered (5) requires In Progress (4)
                if ($newStatus == 5 && $currentStatus != 4) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Booking must be In Progress to mark as Delivered'
                    ], 422);
                }

                $booking->status = $newStatus;

                // Save timestamps based on status
                $timestampMap = [
                    2 => 'confirmed_at',
                    3 => 'driver_assigned_at',
                    4 => 'in_progress_at',
                    5 => 'delivered_at',
                    6 => 'cancelled_at',
                ];

                // Latest transition wins — re-entering a status must refresh its
                // time, not keep the first one.
                if (isset($timestampMap[$newStatus])) {
                    $column = $timestampMap[$newStatus];
                    $booking->$column = now();
                }

                // Save cancellation reason if status is Failed
                if ($newStatus == 6 && $request->filled('delivery_reason')) {
                    $reasonMap = [
                        1 => 'Delay in processing',
                        2 => 'Incorrect order details',
                        3 => 'Wrong quantity requested',
                        4 => 'Stock quality issues',
                        5 => 'Other',
                    ];
                    $booking->cancellation_reason = $request->delivery_reason;
                    $booking->cancel_reason_text = $reasonMap[$request->delivery_reason] ?? 'Other';
                }

                $booking->save();

                // Clear tracking alerts on delivery/cancellation
                if (in_array($newStatus, [5, 6])) {
                    TrackingAlert::where('booking_id', $booking->id)->delete();
                    $booking->update(['driver_inactive_reason' => null]);
                }

                // Send notification for status change
                $this->sendBookingNotification($booking, $newStatus);
            }

            return response()->json([
                'status' => true,
                'message' => 'Booking details updated successfully',
                'data' => [
                    'booking_id' => $booking->id,
                    'status' => $booking->status
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Update Booking Error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong'
            ], 500);
        }
    }

    public function removeDriver($id)
    {
        try {
            $vendorId = Auth::id();

            // 1️⃣ Fetch booking FIRST
            $booking = Booking::where('id', $id)
                ->where('vendor_id', $vendorId)
                ->first();

            if (!$booking) {
                return response()->json([
                    'status' => false,
                    'message' => 'Booking not found'
                ], 404);
            }

            // 2️⃣ Status validation
            switch ($booking->status) {
                case 1:
                    return response()->json([
                        'status' => false,
                        'message' => 'Please accept the booking before removing driver'
                    ], 422);

                case 2:
                    return response()->json([
                        'status' => false,
                        'message' => 'No driver assigned to remove'
                    ], 422);

                case 4:
                case 5:
                    return response()->json([
                        'status' => false,
                        'message' => 'Driver cannot be removed while booking is ongoing'
                    ], 422);

                case 6:
                    return response()->json([
                        'status' => false,
                        'message' => 'Completed bookings cannot be modified'
                    ], 422);
            }

            // 3️⃣ Only status 3 allowed
            //
            // driver_id is cleared alongside the name/mobile so the booking is
            // genuinely unassigned — leaving it set kept the booking linked to
            // the removed driver and made the next assignment look like "the
            // same driver", which suppressed the driver_assigned_at refresh.
            // driver_assigned_at is cleared too: with no driver assigned there
            // is no assignment time to show.
            $booking->update([
                'driver_id' => null,
                'driver_name' => null,
                'driver_mobile' => null,
                'vehicle_number' => null,
                'driver_assigned_at' => null,
                'status' => 2
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Driver removed successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Remove Driver Error', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong'
            ], 500);
        }
    }

    /**
     * Get list of all drivers for dropdown selection
     */
    public function getDrivers(Request $request)
    {
        try {
            $drivers = Driver::where('status', 1)
                ->select('id', 'name', 'mobile')
                ->orderBy('name')
                ->get();

            return response()->json([
                'status' => true,
                'drivers' => $drivers->map(function ($driver) {
                    return [
                        'id' => $driver->id,
                        'name' => $driver->name,
                        'mobile' => $driver->mobile,
                        'display_name' => $driver->name . ' - ' . $driver->mobile,
                    ];
                })
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get Drivers Error', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong'
            ], 500);
        }
    }

    public function changeDriver(Request $request, $id)
    {
        try {
            $vendorId = Auth::id();

            $request->validate([
                'driver_id' => 'nullable|integer|exists:drivers,id',
                'driver_name' => 'nullable|string|max:100',
                'driver_mobile' => 'nullable|string|max:20',
                'vehicle_number' => 'required|string|max:50',
                'vehicle_start_date' => 'nullable|date',
                'vehicle_end_date' => 'nullable|date',
                'vehicle_start_lat' => 'nullable|numeric',
                'vehicle_start_lng' => 'nullable|numeric',
                'vehicle_start_address' => 'nullable|string|max:500',
                'priority' => 'nullable|integer|min:1',
            ]);

            // 1️⃣ Fetch booking FIRST
            $booking = Booking::where('id', $id)
                ->where('vendor_id', $vendorId)
                ->first();

            if (!$booking) {
                return response()->json([
                    'status' => false,
                    'message' => 'Booking not found'
                ], 404);
            }

            // 2️⃣ Status-based validation
            switch ($booking->status) {
                case 1:
                    return response()->json([
                        'status' => false,
                        'message' => 'Please accept the booking before assigning a driver'
                    ], 422);

                case 5:
                    return response()->json([
                        'status' => false,
                        'message' => 'Delivered bookings cannot be modified'
                    ], 422);

                case 6:
                    return response()->json([
                        'status' => false,
                        'message' => 'Completed bookings cannot be modified'
                    ], 422);
            }

            // 3️⃣ Resolve driver_id from the SAVED mobile so the booking always
            // follows the last-entered driver number (mirrors customer-mobile linking):
            //   • mobile given  → link to the Driver app account with that mobile
            //                     (find existing, else create one) so the booking
            //                     appears in that driver's account.
            //   • mobile blank  → leave unlinked (driver_id null). The driver name is
            //                     still stored on the booking, and a mobile can be added
            //                     later via edit to link it then.
            // Editing the mobile from X to Y moves the booking off X and onto Y.
            $driverId = null;

            if ($request->filled('driver_mobile')) {
                // Check if a driver already exists with this mobile number
                $existingDriver = Driver::where('mobile', $request->driver_mobile)->first();

                if ($existingDriver) {
                    $driverId = $existingDriver->id;
                    // Update name if provided and driver had no name
                    if ($request->filled('driver_name') && empty($existingDriver->name)) {
                        $existingDriver->update(['name' => $request->driver_name]);
                    }
                } else {
                    // Create a new driver record
                    $newDriver = Driver::create([
                        'name' => $request->driver_name ?? '',
                        'mobile' => $request->driver_mobile,
                        'status' => 1,
                    ]);
                    $driverId = $newDriver->id;
                }
            } elseif ($request->driver_id) {
                // Existing driver explicitly selected from the list but without a
                // mobile on file — keep that link.
                $driverId = $request->driver_id;
            }

            // 4️⃣ Allowed only for status 2 & 3
            $updateData = [
                'driver_id' => $driverId,
                'driver_name' => $request->driver_name,
                'driver_mobile' => $request->driver_mobile,
                'vehicle_number' => $request->vehicle_number,
                'status' => $booking->status == 4 ? 4 : 3
            ];

            // Add optional fields if provided
            if ($request->filled('vehicle_start_date')) {
                $updateData['vehicle_started_date'] = $request->vehicle_start_date;
            }
            if ($request->filled('vehicle_end_date')) {
                $updateData['vehicle_end_date'] = $request->vehicle_end_date;
            }
            if ($request->filled('vehicle_start_lat')) {
                $updateData['vehicle_start_lat'] = $request->vehicle_start_lat;
            }
            if ($request->filled('vehicle_start_lng')) {
                $updateData['vehicle_start_lng'] = $request->vehicle_start_lng;
            }
            if ($request->filled('vehicle_start_address')) {
                $updateData['vehicle_start_address'] = $request->vehicle_start_address;
            }
            if ($request->filled('priority')) {
                $updateData['priority'] = $request->priority;
            }

            $booking->update($updateData);

            $this->sendBookingNotification($booking, 3);

            // Push the assignment to the driver so they see the booking
            // pop into their app without needing to refresh. driver_id is
            // set in the update flow above (either an existing driver_id
            // was picked or a fresh Driver row was created earlier).
            try {
                $driverForPush = $booking->driver_id ? Driver::find($booking->driver_id) : null;
                if ($driverForPush && !empty($driverForPush->fcm_token)) {
                    app(FirebaseNotificationService::class)->sendToDevice(
                        $driverForPush->fcm_token,
                        'New booking assigned',
                        'Booking #' . $booking->booking_uid . ' — pickup ' . ($booking->hatchery_location ?: $booking->hatchery_name),
                        null,
                        [
                            'type'          => 'driver_assigned',
                            'booking_id'    => (string) $booking->id,
                            'booking_uid'   => (string) $booking->booking_uid,
                            'hatchery_name' => (string) ($booking->hatchery_name ?? ''),
                            'customer_name' => (string) ($booking->customer_name ?? ''),
                            'drop_location' => (string) ($booking->dropping_location ?? ''),
                        ]
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Driver assignment push failed', [
                    'booking_id' => $booking->id,
                    'driver_id'  => $booking->driver_id,
                    'err'        => $e->getMessage(),
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Driver changed successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Change Driver Error', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong'
            ], 500);
        }
    }
    public function vehicleTracking($id, Request $request)
    {
        try {
            $vendorId = Auth::id();

            $booking = Booking::with('hatchery.category', 'hatchery.location', 'farmer', 'trackings')
                ->where('id', $id)
                ->where('vendor_id', $vendorId)
                ->first();

            if (!$booking) {
                return response()->json([
                    'status' => false,
                    'message' => 'Tracking not available'
                ], 404);
            }

            // ── ON-DEMAND DRIVER LOCATION PING ──
            // The driver app posts location every 10–15 s while moving, but
            // can fall silent on iOS lock / Doze / hotspot switch. Whenever
            // a vendor opens the tracking screen, send a silent FCM data push
            // asking the driver phone to capture and POST a fresh GPS fix.
            // Driver-side handler: lib/services/notification_service.dart
            // (data.type === "request_location"). Fire-and-forget — never
            // block the tracking response on the push.
            //
            // Guards:
            //   • only active (status == 4) bookings — no ping for delivered/cancelled
            //   • driver must have an FCM token registered
            //   • skip if backend already received a fresh fix within the last 30 s
            //   • Cache lock to dedupe N vendors opening the same booking
            //     within a 5 s window (only 1 push reaches the driver)
            try {
                if (
                    $booking->status == 4
                    && $booking->driver_id
                ) {
                    $needsRefresh = !$booking->driver_location_updated_at
                        || $booking->driver_location_updated_at->lt(now()->subSeconds(30));

                    if ($needsRefresh) {
                        $lockKey = 'on_demand_loc_ping:' . $booking->id;
                        // Cache::add returns true only if the key didn't exist
                        // — atomic dedupe across vendor requests.
                        if (\Illuminate\Support\Facades\Cache::add($lockKey, 1, 5)) {
                            $driver = Driver::find($booking->driver_id);
                            if ($driver && !empty($driver->fcm_token)) {
                                app(FirebaseNotificationService::class)
                                    ->sendDataToDevice($driver->fcm_token, [
                                        'type'       => 'request_location',
                                        'booking_id' => (string) $booking->id,
                                    ]);
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Never let an on-demand-push failure break tracking — the
                // vendor screen still gets last-known data.
                \Log::warning('on_demand_loc_ping failed', [
                    'booking_id' => $booking->id,
                    'error'      => $e->getMessage(),
                ]);
            }

            // Route waypoints (other deliveries by same driver on same day)
            $routeWaypoints = [];
            if ($booking->driver_id && $booking->vehicle_started_date) {
                $routeWaypoints = Booking::where('driver_id', $booking->driver_id)
                    ->whereDate('vehicle_started_date', $booking->vehicle_started_date)
                    ->whereIn('status', [4, 5, 6])
                    ->where('id', '!=', $booking->id)
                    ->when($booking->priority, function ($query) use ($booking) {
                        $query->where('priority', '<', $booking->priority);
                    })
                    ->orderBy('priority', 'asc')
                    ->get()
                    ->filter(fn($rb) => $rb->drop_lat && $rb->drop_lng)
                    ->map(fn($rb) => [
                        'lat' => (float) $rb->drop_lat,
                        'lng' => (float) $rb->drop_lng,
                        'status' => (int) $rb->status,
                        'priority' => (int) $rb->priority,
                        'name' => $rb->dropping_location ?? $rb->delivery_location ?? '',
                        'is_before' => true,
                    ])
                    ->values()
                    ->all();
            }

            // ── ADMIN-SET PICKUP (separate from driver start) ──
            // Capture the admin's intended pickup BEFORE any heal/derived
            // logic touches `vehicle_start_lat`. Mirrors the farmer endpoint
            // so the vendor app can draw the same "approach" green line
            // from admin's pickup to where the driver actually began moving.
            $adminPickupLat = $booking->vehicle_start_lat
                ? (float) $booking->vehicle_start_lat
                : null;
            $adminPickupLng = $booking->vehicle_start_lng
                ? (float) $booking->vehicle_start_lng
                : null;
            $adminPickupName = $booking->vehicle_start_address;

            // ── DRIVER PICKUP (where the journey actually started) ──
            // Source of truth: the FIRST VehicleTracking row. Falls back to
            // vehicle_start_lat ONLY when no tracking rows exist yet.
            // Keeps parity with the farmer endpoint so both apps render the
            // pickup marker at the exact same spot.
            $firstTracking = VehicleTracking::where('booking_id', $booking->id)
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->orderBy('reached_at', 'asc')
                ->first();

            if ($firstTracking) {
                $pickup = [
                    'name' => $firstTracking->location_name
                        ?? $booking->vehicle_start_address
                        ?? 'Pickup location',
                    'lat' => (float) $firstTracking->lat,
                    'lng' => (float) $firstTracking->lng,
                ];
            } elseif ($booking->vehicle_start_lat && $booking->vehicle_start_lng) {
                $pickup = [
                    'name' => $booking->vehicle_start_address ?? 'N/A',
                    'lat' => (float) $booking->vehicle_start_lat,
                    'lng' => (float) $booking->vehicle_start_lng,
                ];
            } elseif ($booking->status == 4 && $booking->driver_lat && $booking->driver_lng) {
                $pickup = [
                    'name' => $booking->driver_location_name ?? 'N/A',
                    'lat' => (float) $booking->driver_lat,
                    'lng' => (float) $booking->driver_lng,
                ];
            } else {
                $pickup = ['name' => $booking->vehicle_start_address ?? 'N/A', 'lat' => null, 'lng' => null];
            }

            // Drop location
            $drop = [
                'name' => $booking->dropping_location ?? $booking->delivery_location ?? 'N/A',
                'lat' => $booking->drop_lat ? (float) $booking->drop_lat : null,
                'lng' => $booking->drop_lng ? (float) $booking->drop_lng : null,
            ];

            // ── DRIVER LOCATION + MOVEMENT DETECTION ──
            // is_moving = true only if driver moved >30 m in the last 60 s
            // (not just "GPS is sending"). Same logic as the farmer endpoint
            // so the vendor app's "Live"/"Stopped" badge stays consistent.
            $isMoving = false;
            $speedKmh = 0.0;
            if ($booking->driver_lat && $booking->driver_lng && $booking->driver_location_updated_at) {
                $secsSinceUpdate = Carbon::parse($booking->driver_location_updated_at)->diffInSeconds(now());

                if ($secsSinceUpdate <= 30) {
                    $recentPoint = VehicleTracking::where('booking_id', $booking->id)
                        ->whereNotNull('reached_at')
                        ->where('reached_at', '<=', now()->subSeconds(60))
                        ->latest('reached_at')
                        ->first();

                    if ($recentPoint) {
                        $netDist = VehicleController::haversineDistance(
                            (float) $recentPoint->lat, (float) $recentPoint->lng,
                            (float) $booking->driver_lat, (float) $booking->driver_lng
                        ) * 1000;
                        $isMoving = $netDist > 30;
                        $elapsed = Carbon::parse($recentPoint->reached_at)->diffInSeconds(now());
                        if ($elapsed > 0) {
                            $speedKmh = round(($netDist / $elapsed) * 3.6, 1);
                        }
                    } else {
                        // No old point yet — assume moving if GPS is fresh
                        $isMoving = true;
                    }
                }
            }

            $driverLocation = [
                'name' => $booking->driver_location_name ?? 'Location not available',
                'lat' => $booking->driver_lat ? round((float) $booking->driver_lat, 6) : null,
                'lng' => $booking->driver_lng ? round((float) $booking->driver_lng, 6) : null,
                'updated_at' => $booking->driver_location_updated_at
                    ? Carbon::parse($booking->driver_location_updated_at)->toDateTimeString()
                    : null,
                'is_moving' => $isMoving,
                'speed_kmh' => $speedKmh,
            ];

            // Driver details (with image URL normalization)
            $driverImage = $booking->driver_image;
            if ($driverImage && !preg_match('/^https?:\/\//', $driverImage)) {
                $driverImage = url('public/' . ltrim($driverImage, '/'));
            }
            $driverDetails = [
                'driver_name' => $booking->driver_name ?? 'N/A',
                'driver_phone' => $booking->driver_mobile ?? 'N/A',
                'vehicle_number' => $booking->vehicle_number ?? 'N/A',
                'driver_image' => $driverImage,
            ];

            // Delivery updates
            $deliveryUpdates = [
                'delivery_expected' => $booking->delivery_expected
                    ? Carbon::parse($booking->delivery_expected)->format('d/m/Y')
                    : ($booking->vehicle_end_date
                        ? Carbon::parse($booking->vehicle_end_date)->format('d/m/Y')
                        : null),
                'note' => $booking->delivery_note
                    ?? "We've received your booking. Within a few days, we will assign your vehicle",
            ];

            // ── TIMELINE: cleaned + smoothed pipeline (parity with farmer) ──
            // precision=low  → only pickup + drop (no tracking points)
            // precision=high → multi-stage filter:
            //   1. distance/time gate (15 m, reject >120 km/h jumps)
            //   2. backtrack guard (40 m vs last 5)
            //   3. bearing reversal guard (>150° on short legs)
            //   4. stationary cluster collapse (50 m, 4 points)
            //   5. moving-average smoothing (window 5)
            //   6. Douglas-Peucker simplification (8 m epsilon)
            //
            // Previously this endpoint returned RAW paginated trackings,
            // which the vendor app then had to render unfiltered — leading
            // to GPS jitter, looped polylines around stops, and visual
            // teleports. Now both apps consume the same cleaned feed.
            $precision = $request->query('precision', 'high');

            $timeline = [];
            $timelinePagination = null;

            if ($precision === 'low') {
                $timeline = $this->buildVendorDefaultTimeline($booking, $pickup, $drop);
            } elseif ($booking->trackings->count() > 0) {
                $allTrackings = VehicleTracking::where('booking_id', $booking->id)
                    ->whereNotNull('reached_at')
                    ->whereNotNull('lat')
                    ->whereNotNull('lng')
                    ->orderBy('reached_at', 'asc')
                    ->get();

                $cleanPoints = [];
                $prevLat = null;
                $prevLng = null;
                $prevTime = null;

                foreach ($allTrackings as $tracking) {
                    $lat = round((float) $tracking->lat, 6);
                    $lng = round((float) $tracking->lng, 6);
                    $time = Carbon::parse($tracking->reached_at);

                    // STEP 1: distance/time gate (200 km/h cap — matches
                    // the live update endpoints).
                    if ($prevLat !== null && $prevLng !== null) {
                        $dist = VehicleController::haversineDistance($prevLat, $prevLng, $lat, $lng) * 1000;
                        $secsDiff = $prevTime ? $prevTime->diffInSeconds($time) : 999;

                        if ($secsDiff > 0) {
                            $kmh = ($dist / $secsDiff) * 3.6;
                            if (($dist > 500 && $secsDiff < 5) || $kmh > 200) {
                                continue;
                            }
                        }

                        if ($dist < 15) {
                            continue;
                        }
                    }

                    // STEP 2: backtrack guard (last 5 points within 40 m)
                    $isBacktrack = false;
                    $lookback = min(5, count($cleanPoints));
                    for ($i = count($cleanPoints) - $lookback; $i < count($cleanPoints) - 1; $i++) {
                        if ($i < 0) continue;
                        $pt = $cleanPoints[$i];
                        $distBack = VehicleController::haversineDistance($pt['lat'], $pt['lng'], $lat, $lng) * 1000;
                        if ($distBack < 40) {
                            $isBacktrack = true;
                            break;
                        }
                    }
                    if ($isBacktrack) {
                        continue;
                    }

                    // STEP 3: bearing reversal guard (>150° on short legs)
                    if (count($cleanPoints) >= 2) {
                        $a = $cleanPoints[count($cleanPoints) - 2];
                        $b = $cleanPoints[count($cleanPoints) - 1];
                        $leg1 = VehicleController::haversineDistance($a['lat'], $a['lng'], $b['lat'], $b['lng']) * 1000;
                        $leg2 = VehicleController::haversineDistance($b['lat'], $b['lng'], $lat, $lng) * 1000;
                        if ($leg1 < 80 && $leg2 < 80) {
                            $bearing1 = VehicleController::bearingDeg($a['lat'], $a['lng'], $b['lat'], $b['lng']);
                            $bearing2 = VehicleController::bearingDeg($b['lat'], $b['lng'], $lat, $lng);
                            $delta = abs($bearing1 - $bearing2);
                            if ($delta > 180) $delta = 360 - $delta;
                            if ($delta > 150) {
                                continue;
                            }
                        }
                    }

                    $cleanPoints[] = [
                        'title' => $tracking->title ?? $tracking->location_name ?? '',
                        'subtitle' => $tracking->subtitle ?? '',
                        'time' => $time->format('g:i A'),
                        'date' => $time->format('d/m/Y'),
                        'status' => $tracking->status ?? 'pending',
                        'lat' => $lat,
                        'lng' => $lng,
                    ];
                    $prevLat = $lat;
                    $prevLng = $lng;
                    $prevTime = $time;
                }

                // STEP 4-6: collapse → smooth → simplify
                $cleanPoints = VehicleController::collapseStationaryClusters($cleanPoints, 50.0, 4);
                $cleanPoints = VehicleController::movingAverageSmooth($cleanPoints, 5);
                $cleanPoints = VehicleController::douglasPeucker($cleanPoints, 8.0);

                $timeline = array_values($cleanPoints);
                $timelinePagination = [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => count($timeline),
                    'total' => count($timeline),
                ];
            } else {
                $timeline = $this->buildVendorDefaultTimeline($booking, $pickup, $drop);
            }

            // ── Passed stops from DB (same as user endpoint) ──
            $passedStops = \App\Models\VehicleTrackingStop::getPassedStops($booking->id);

            // ── Distances (same as user endpoint) ──
            $totalDistanceKm = 0;
            $remainingDistanceKm = 0;
            $pickupLatCalc = (float) ($pickup['lat'] ?? 0);
            $pickupLngCalc = (float) ($pickup['lng'] ?? 0);
            $dropLatCalc = (float) ($drop['lat'] ?? 0);
            $dropLngCalc = (float) ($drop['lng'] ?? 0);
            $driverLatCalc = (float) ($driverLocation['lat'] ?? 0);
            $driverLngCalc = (float) ($driverLocation['lng'] ?? 0);

            $haversine = function ($lat1, $lng1, $lat2, $lng2) {
                $R = 6371;
                $dLat = deg2rad($lat2 - $lat1);
                $dLng = deg2rad($lng2 - $lng1);
                $a = sin($dLat/2)*sin($dLat/2) + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLng/2)*sin($dLng/2);
                return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
            };
            if ($pickupLatCalc && $pickupLngCalc && $dropLatCalc && $dropLngCalc) {
                $totalDistanceKm = round($haversine($pickupLatCalc, $pickupLngCalc, $dropLatCalc, $dropLngCalc), 1);
            }
            if ($driverLatCalc && $driverLngCalc && $dropLatCalc && $dropLngCalc) {
                $remainingDistanceKm = round($haversine($driverLatCalc, $driverLngCalc, $dropLatCalc, $dropLngCalc), 1);
            }

            // ── Driver breadcrumbs (same as user endpoint) ──
            $tripCutoff = $booking->in_progress_at
                ? \Carbon\Carbon::parse($booking->in_progress_at)->subMinutes(10)
                : \Carbon\Carbon::now()->subHours(12);
            $allBreadcrumbs = \App\Models\VehicleTracking::where('booking_id', $booking->id)
                ->whereNotNull('lat')->whereNotNull('lng')
                ->where('reached_at', '>=', $tripCutoff)
                ->orderBy('reached_at', 'asc')
                ->get(['lat', 'lng'])
                ->map(fn($t) => [round((float) $t->lat, 6), round((float) $t->lng, 6)])
                ->values()
                ->all();
            $bcTotal = count($allBreadcrumbs);
            if ($bcTotal <= 500) {
                $driverBreadcrumbs = $allBreadcrumbs;
            } else {
                $skipRate = (int) ceil($bcTotal / 500);
                $driverBreadcrumbs = [];
                for ($i = 0; $i < $bcTotal; $i++) {
                    if ($i % $skipRate === 0 || $i === $bcTotal - 1) {
                        $driverBreadcrumbs[] = $allBreadcrumbs[$i];
                    }
                }
            }

            return response()->json([
                'status' => true,
                'booking' => $this->formatBooking($booking),
                'pickup' => $pickup,
                'admin_pickup' => [
                    'name' => $adminPickupName,
                    'lat' => $adminPickupLat,
                    'lng' => $adminPickupLng,
                ],
                'drop' => $drop,
                'driver_location' => $driverLocation,
                'driver_details' => $driverDetails,
                'delivery_updates' => $deliveryUpdates,
                'timeline' => $timeline,
                'timeline_pagination' => $timelinePagination,
                'route_waypoints' => $routeWaypoints,
                'auto_timeline_points' => (new RouteTimelineService())->buildAutoTimelineWithETAsRerouteAware($booking, $driverLocation),

                // Fields matching user endpoint — for vendor app tracking screen
                'passed_stops' => $passedStops,
                'driver_breadcrumbs' => $driverBreadcrumbs,
                'total_distance_km' => $totalDistanceKm,
                'remaining_distance_km' => $remainingDistanceKm,
                'is_delivered' => (int) $booking->status === 5,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Tracking Error', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong'
            ], 500);
        }
    }

    private function buildVendorDefaultTimeline($booking, $pickup, $drop)
    {
        $status = (int) ($booking->status ?? 1);
        $timeline = [];

        $pickupStatus = in_array($status, [4, 5]) ? 'completed' : 'pending';
        $timeline[] = [
            'title' => 'Pickup started from',
            'subtitle' => $pickup['name'],
            'time' => $booking->vehicle_started_date
                ? \Carbon\Carbon::parse($booking->vehicle_started_date)->format('g:i A')
                : '-',
            'date' => $booking->vehicle_started_date
                ? \Carbon\Carbon::parse($booking->vehicle_started_date)->format('d/m/Y')
                : '',
            'status' => $pickupStatus,
            'lat' => $pickup['lat'] ?? null,
            'lng' => $pickup['lng'] ?? null,
        ];

        $destStatus = $status == 5 ? 'completed' : 'pending';
        $timeline[] = [
            'title' => 'Destination',
            'subtitle' => $drop['name'],
            'time' => $booking->vehicle_end_date
                ? \Carbon\Carbon::parse($booking->vehicle_end_date)->format('g:i A')
                : '-',
            'date' => $booking->vehicle_end_date
                ? \Carbon\Carbon::parse($booking->vehicle_end_date)->format('d/m/Y')
                : '',
            'status' => $destStatus,
            'lat' => $drop['lat'] ?? null,
            'lng' => $drop['lng'] ?? null,
        ];

        return $timeline;
    }

    /**
     * GET /api/vendor/tracking-alert-status
     * Returns tracking alert status for all drivers under this vendor's active bookings
     */
    public function trackingAlertStatus()
    {
        $vendor = Auth::user();

        // Fetch all tracking alerts saved for this vendor.
        // The "In Journey" requirement is already enforced at write time in
        // DriverBookingController@trackingAlert (it only creates alerts for bookings
        // with status = 4). Re-checking the booking status here would hide historical
        // alerts the moment the booking moves past In Journey, which is why the GET
        // was returning empty even after a successful POST.
        $alerts = TrackingAlert::where('vendor_id', $vendor->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $unreadCount = $alerts->where('is_read', false)->count();

        if ($alerts->isEmpty()) {
            return response()->json([
                'status' => true,
                'unread_count' => 0,
                'alerts' => [],
            ]);
        }

        $driverIds = $alerts->pluck('driver_id')->unique()->filter();
        $drivers = Driver::whereIn('id', $driverIds)->get()->keyBy('id');

        $result = $alerts->map(function ($alert) use ($drivers) {
            $driver = $drivers->get($alert->driver_id);
            return [
                'id' => $alert->id,
                'booking_id' => $alert->booking_id,
                'driver_id' => $alert->driver_id,
                'reason' => $alert->reason,
                'driver_name' => $driver->name ?? 'N/A',
                'driver_mobile' => $driver->mobile ?? 'N/A',
                'location_name' => $alert->location_name ?? 'Unknown',
                'time' => $alert->created_at->format('d M Y, h:i A'),
                'is_read' => (bool) $alert->is_read,
            ];
        });

        return response()->json([
            'status' => true,
            'unread_count' => $unreadCount,
            'alerts' => $result->values(),
        ]);
    }

    /**
     * POST /api/vendor/tracking-alerts/mark-read
     * Marks every unread tracking alert for the authenticated vendor as read.
     */
    public function markAlertsRead()
    {
        $vendor = Auth::user();

        $updated = TrackingAlert::where('vendor_id', $vendor->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'status' => true,
            'message' => 'Alerts marked as read',
            'marked' => $updated,
        ]);
    }

}
