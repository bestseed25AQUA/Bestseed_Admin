<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\Vendor;
use App\Models\HatcheryLocation;
use App\Models\Hatchery;
use App\Models\HatcheryCategory;
use App\Models\Driver;
use App\Models\Farmer;
use App\Models\SpotHatchery;
use App\Models\VehicleAvailability;
use App\Models\PushNotification;
use App\Models\TrackingAlert;
use App\Services\FirebaseNotificationService;
use App\Services\RouteTimelineService;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:bookings.view')->only(['index', 'show', 'tracking', 'trackingData', 'getHatcheryDetails', 'inactiveDrivers']);
        $this->middleware('permission:bookings.create')->only(['create', 'store']);
        $this->middleware('permission:bookings.update')->only(['edit', 'update', 'updateStatus', 'assignDriver', 'cancel', 'storeDriver']);
        $this->middleware('permission:bookings.delete')->only(['destroy']);
    }

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

    public function index()
    {
        $bookings = Booking::with('vendor', 'rating')->latest()->get();
        return view('admin.bookings.index', compact('bookings'));
    }

    private function generateBookingId($stateCode, $deliveryDate)
    {
        $state = strtoupper($stateCode);
        $date = \Carbon\Carbon::parse($deliveryDate)->format('dmY');
        $unique = rand(10000, 99999);

        return "OD-BS-{$state}-{$date}-{$unique}";
    }

    public function show(Booking $booking)
    {
        $booking->load('rating');
        $drivers = Driver::orderBy('name')->get();
        return view('admin.bookings.show', compact('booking', 'drivers'));
    }

    public function create()
    {
        $vendors = Vendor::all();
        $locations = HatcheryLocation::whereNull('farmer_id')->orderBy('priority')->get();
        $allcategories = HatcheryCategory::all();
        $customers = Farmer::orderBy('first_name')->get();
        $drivers = Driver::orderBy('name')->get();

        // Get hatcheries (is_spot = 0 or null)
        $hatcheries = Hatchery::where(function ($q) {
            $q->whereNull('is_spot')->orWhere('is_spot', 0);
        })->orderByDesc('created_at')->get();

        // Get spot hatcheries (is_spot = 1)
        $spotHatcheries = Hatchery::where('is_spot', 1)->orderByDesc('created_at')->get();

        // Get vehicle availability from the vehicle_availability table
        $vehicleAvailability = VehicleAvailability::with(['hatchery', 'category'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'vehicle_name' => $item->vehicle_name ?? ($item->hatchery->hatchery_name ?? 'Vehicle #' . $item->id),
                    'hatchery_name' => $item->vehicle_name ?? ($item->hatchery->hatchery_name ?? 'Vehicle #' . $item->id),
                    'category_id' => $item->category_id,
                    'price' => $item->hatchery->price ?? 0,
                    'vendor_id' => $item->vendor_id,
                    'location_id' => $item->location_id,
                    'available_space' => $item->available_space,
                ];
            });

        return view('admin.bookings.create', compact(
            'vendors',
            'locations',
            'allcategories',
            'customers',
            'drivers',
            'hatcheries',
            'spotHatcheries',
            'vehicleAvailability'
        ));
    }


    public function store(Request $request)
    {
        try {
            // ✅ Validate request
            $validated = $request->validate([
                'is_spot' => 'required|in:0,1,2',
                'hatchery_id' => 'required|integer',
                'vendor_id' => 'nullable',
                'vehicle_images' => 'nullable|array',
                'vehicle_images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
                'customer_id' => 'nullable',
                'customer_name' => 'nullable|string|max:255',
                'customer_mobile' => 'nullable|string|max:20',
                'delivery_location' => 'nullable|string|max:255',
                'hatchery_name' => 'nullable|string|max:255',
                'unit' => 'nullable|string|max:255',
                'salinity' => 'nullable|integer|min:0|max:40',
                'no_of_pieces' => 'nullable|integer',
                'estimated_price' => 'nullable|numeric',
                'dropping_location' => 'nullable|string|max:500',
                'drop_lat' => 'nullable|numeric',
                'drop_lng' => 'nullable|numeric',
                'packing_date' => 'nullable|date',
                'delivery_expected' => 'nullable|date',
                'category_id' => 'required|integer',
                'count' => 'nullable|integer',
                'driver_id' => 'nullable|integer',
                'driver_name' => 'nullable|string|max:255',
                'driver_mobile' => 'nullable|string|max:20',
                'vehicle_number' => 'nullable|string|max:50',
                'vehicle_started_date' => 'nullable|date',
                'vehicle_end_date' => 'nullable|date',
                'vehicle_start_address' => 'nullable|string|max:500',
                'vehicle_start_lat' => 'nullable|numeric',
                'vehicle_start_lng' => 'nullable|numeric',
                'priority' => 'nullable|integer|min:1|max:20',
            ]);

            Log::info('Booking validation passed', $validated);

            // ✅ Handle image uploads
            $paths = [];
            if ($request->hasFile('vehicle_images')) {
                foreach ($request->file('vehicle_images') as $image) {
                    $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                    $image->move(public_path('uploads/bookings/'), $filename);
                    $paths[] = 'uploads/bookings/' . $filename;
                }
            }

            $validated['vehicle_images'] = json_encode($paths);
            $validated['farmer_id'] = $request->customer_id ?: null;

            // Populate customer name and mobile from farmer record if customer is selected
            if ($request->customer_id) {
                $customer = Farmer::find($request->customer_id);
                if ($customer) {
                    $validated['customer_name'] = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
                    $validated['customer_mobile'] = $customer->mobile ?? ($validated['customer_mobile'] ?? '');
                }
            } elseif (!empty($validated['customer_mobile'])) {
                // No customer picked, but a mobile was entered — link the booking to
                // the app account (Farmer) with that mobile so it appears in their
                // My Bookings. Matching is by exact mobile.
                $farmer = Farmer::where('mobile', $validated['customer_mobile'])->first();
                if ($farmer) {
                    $validated['farmer_id'] = $farmer->id;
                }
            }
            $validated['customer_name'] = $validated['customer_name'] ?? '';
            $validated['customer_mobile'] = $validated['customer_mobile'] ?? '';

            // Get state code from dropping location or default
            $stateCode = 'TS';
            if ($request->dropping_location) {
                $location = HatcheryLocation::where('location_name', 'like', '%' . $request->dropping_location . '%')->first();
                if ($location) {
                    $stateCode = $location->state_code ?? 'TS';
                }
            }

            $validated['booking_uid'] = $this->generateBookingId(
                $stateCode,
                $validated['packing_date'] ?? now()->toDateString()
            );

            // Set status based on driver assignment
            $validated['status'] = $request->driver_id ? 3 : 1; // 3=Driver Assigned, 1=Pending
            if ($request->driver_id) {
                $validated['driver_assigned_at'] = now();
            }

            if ($request->filled('delivery_expected')) {
                $validated['delivery_datetime'] = $request->delivery_expected;
                $validated['delivery_date'] = $request->delivery_expected;
                $validated['delivery_expected'] = $request->delivery_expected;
            }

            // ✅ Handle categories array
            if (isset($validated['categories']) && is_array($validated['categories'])) {
                $validated['categories'] = json_encode($validated['categories']);
            }

            Log::info('Booking data before insert', $validated);

            // ✅ Create booking record
            $booking = Booking::create($validated);

            if ($booking->farmer_id) {
                $this->sendBookingNotification($booking, 1);
            }

            // If the admin also picked a driver at creation time, notify the
            // driver so the booking appears in their app immediately. The
            // customer notification above already covers the farmer side.
            if (!empty($booking->driver_id)) {
                try {
                    $driverForPush = Driver::find($booking->driver_id);
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
                    Log::warning('Admin-store driver assignment push failed', [
                        'booking_id' => $booking->id,
                        'driver_id'  => $booking->driver_id,
                        'err'        => $e->getMessage(),
                    ]);
                }
            }

            return redirect()->route('bookings.index')
                ->with('success', 'Booking created successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {

            Log::error('Booking validation failed', [
                'errors' => $e->errors(),
                'payload' => $request->all(),
            ]);
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Booking creation exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            \Log::error('Booking creation failed: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Something went wrong. Please try again.')
                ->withInput();
        }
    }

    public function edit(Booking $booking)
    {
        $vendors = Vendor::all();
        $locations = HatcheryLocation::whereNull('farmer_id')->orderBy('priority')->get();
        $allcategories = HatcheryCategory::all();
        $customers = Farmer::orderBy('first_name')->get();
        $drivers = Driver::orderBy('name')->get();

        // Get hatcheries (is_spot = 0 or null)
        $hatcheries = Hatchery::where(function ($q) {
            $q->whereNull('is_spot')->orWhere('is_spot', 0);
        })->orderByDesc('created_at')->get();

        // Get spot hatcheries (is_spot = 1)
        $spotHatcheries = Hatchery::where('is_spot', 1)->orderByDesc('created_at')->get();

        // Get vehicle availability
        $vehicleAvailability = VehicleAvailability::with(['hatchery', 'category'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'vehicle_name' => $item->vehicle_name ?? ($item->hatchery->hatchery_name ?? 'Vehicle #' . $item->id),
                    'hatchery_name' => $item->vehicle_name ?? ($item->hatchery->hatchery_name ?? 'Vehicle #' . $item->id),
                    'category_id' => $item->category_id,
                    'price' => $item->hatchery->price ?? 0,
                    'vendor_id' => $item->vendor_id,
                    'location_id' => $item->location_id,
                    'available_space' => $item->available_space,
                ];
            });

        // Get source price for estimated price calculation
        $sourcePrice = 0;
        if ($booking->is_spot == 2) {
            $vehicle = VehicleAvailability::with('hatchery')->find($booking->hatchery_id);
            $sourcePrice = $vehicle->hatchery->price ?? 0;
        } else {
            $hatchery = Hatchery::find($booking->hatchery_id);
            $sourcePrice = $hatchery->price ?? 0;
        }

        return view('admin.bookings.edit', compact(
            'vendors',
            'booking',
            'locations',
            'allcategories',
            'customers',
            'drivers',
            'hatcheries',
            'spotHatcheries',
            'vehicleAvailability',
            'sourcePrice'
        ));
    }


    public function update(Request $request, Booking $booking)
    {
        try {

            $validated = $request->validate([
                'is_spot' => 'nullable|in:0,1,2',
                'hatchery_id' => 'nullable|integer',
                'vendor_id' => 'nullable|integer',
                'vehicle_images' => 'nullable|array',
                'vehicle_images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
                'customer_id' => 'nullable',
                'customer_name' => 'nullable|string|max:255',
                'customer_mobile' => 'nullable|string|max:20',
                'delivery_location' => 'nullable|string|max:255',
                'hatchery_name' => 'nullable|string|max:255',
                'unit' => 'nullable|string|max:255',
                'salinity' => 'nullable|integer|min:0|max:40',
                'no_of_pieces' => 'nullable|integer',
                'estimated_price' => 'nullable|numeric',
                'dropping_location' => 'nullable|string|max:500',
                'drop_lat' => 'nullable|numeric',
                'drop_lng' => 'nullable|numeric',
                'packing_date' => 'nullable|date',
                'price' => 'nullable|numeric', // Travel Cost
                'delivery_expected' => 'nullable|date', // Expected Delivery Date
                'vendor_booking_description' => 'nullable|string',
                'vendor_vehicle_description' => 'nullable|string',
                'category_id' => 'required',
                'count' => 'nullable|integer',
                'driver_id' => 'nullable|integer',
                'driver_name' => 'nullable|string|max:255',
                'driver_mobile' => 'nullable|string|max:20',
                'vehicle_number' => 'nullable|string|max:50',
                'vehicle_started_date' => 'nullable|date',
                'vehicle_end_date' => 'nullable|date',
                'vehicle_start_lat' => 'nullable|numeric',
                'vehicle_start_lng' => 'nullable|numeric',
                'vehicle_start_address' => 'nullable|string|max:500',
                'priority' => 'nullable|integer|min:1|max:20',
            ]);

            $validated['customer_name'] = $validated['customer_name'] ?? '';
            $validated['customer_mobile'] = $validated['customer_mobile'] ?? '';

            // Re-link the booking to the app account (Farmer) on EVERY save so it
            // always follows the LAST saved customer mobile. Editing from mobile X
            // to mobile Y moves the booking to Y and off X. If no Farmer has the
            // saved mobile (or it's blank), the booking is unlinked (farmer_id null)
            // so it no longer shows in the previous account.
            if (!empty($validated['customer_mobile'])) {
                $farmer = Farmer::where('mobile', $validated['customer_mobile'])->first();
                $validated['farmer_id'] = $farmer ? $farmer->id : null;
            } else {
                $validated['farmer_id'] = null;
            }
            unset($validated['customer_id']);

            // If driver_id is selected, ensure driver_name and driver_mobile are set from driver record
            if ($request->driver_id) {
                $driver = Driver::find($request->driver_id);
                if ($driver) {
                    $validated['driver_id'] = $driver->id;
                    $validated['driver_name'] = $driver->name;
                    $validated['driver_mobile'] = $driver->mobile;

                    // Move to "Driver Assigned" (3) when there was no driver before.
                    if (empty($booking->driver_id)) {
                        $validated['status'] = 3;
                    }

                    // Stamp on FIRST assignment and on every re-assignment. The
                    // old code only stamped when driver_id was previously empty,
                    // so swapping driver A for driver B kept A's timestamp.
                    if ((int) $booking->driver_id !== (int) $driver->id) {
                        $validated['driver_assigned_at'] = now();
                    }
                }
            }

            // Preserve original time portion of vehicle_started_date if only date was submitted
            if ($request->filled('vehicle_started_date') && $booking->vehicle_started_date) {
                $oldDate = \Carbon\Carbon::parse($booking->vehicle_started_date);
                $newDate = \Carbon\Carbon::parse($request->vehicle_started_date);
                if ($newDate->format('H:i:s') === '00:00:00' && $oldDate->format('Y-m-d') === $newDate->format('Y-m-d')) {
                    unset($validated['vehicle_started_date']);
                } elseif ($newDate->format('H:i:s') === '00:00:00') {
                    $validated['vehicle_started_date'] = $newDate->setTimeFrom($oldDate);
                }
            }

            // Map delivery_expected to delivery_datetime, delivery_date and delivery_expected columns
            if ($request->filled('delivery_expected')) {
                $validated['delivery_datetime'] = $request->delivery_expected;
                $validated['delivery_date'] = $request->delivery_expected;
                $validated['delivery_expected'] = $request->delivery_expected;
            } else {
                unset($validated['delivery_expected']);
            }

            $newPaths = [];

            if ($request->hasFile('vehicle_images')) {
                foreach ($request->file('vehicle_images') as $image) {
                    $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                    $image->move(public_path('uploads/bookings/'), $filename);
                    $newPaths[] = 'uploads/bookings/' . $filename;
                }
            }


            $existing = [];
            if (!empty($booking->vehicle_images)) {
                $existing = is_array($booking->vehicle_images) ? $booking->vehicle_images : (json_decode($booking->vehicle_images, true) ?? []);
            }

            $validated['vehicle_images'] = json_encode(array_merge($existing, $newPaths));

            // Protect the vehicle_start_lat/lng/address columns from being
            // wiped by the admin edit form. Once a journey has started those
            // values represent the click-start location and must never be
            // overwritten by an admin update unless the admin explicitly
            // provides a new value. Empty/missing form fields → keep the
            // existing DB value. (The previous behavior nullified them
            // whenever the form was submitted without those fields filled,
            // which is what made the home marker drift to the live driver
            // position for bookings 544, 545.)
            foreach (['vehicle_start_lat', 'vehicle_start_lng', 'vehicle_start_address'] as $startField) {
                if (!$request->filled($startField)) {
                    unset($validated[$startField]);
                }
            }

            // Server-side recalculation of estimated_price when no_of_pieces changes
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
                    $validated['estimated_price'] = (int) $request->no_of_pieces * $pricePerPiece;
                }
            }

            $booking->update($validated);

            return redirect()->route('bookings.index')
                ->with('success', 'Booking updated successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            \Log::error('Booking update failed: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Something went wrong. Please try again.')
                ->withInput();
        }
    }


    public function destroy(Booking $booking)
    {
        $booking->delete();
        return redirect()->route('bookings.index')->with('success', 'Booking deleted.');
    }

    public function getFormattedBookings()
    {
        return Booking::latest()->get()->map(function ($booking) {
            return [
                'id' => $booking->id,
                'vendor_id' => $booking->vendor_id,
                'customer' => [
                    'id' => $booking->customer_id,
                    'name' => $booking->customer_name,
                    'mobile' => $booking->customer_mobile,
                ],
                'hatchery' => [
                    'name' => $booking->hatchery_name,
                    'delivery_location' => $booking->delivery_location,
                ],
                'driver' => [
                    'name' => $booking->driver_name,
                    'mobile' => $booking->driver_mobile,
                ],
                'vehicle' => [
                    'number' => $booking->vehicle_number,
                    'started_date' => optional($booking->vehicle_started_date)->format('Y-m-d'),
                ],
                'packing_date' => optional($booking->packing_date)->format('Y-m-d'),
                'unit' => $booking->unit,
                'no_of_pieces' => $booking->no_of_pieces,
                'dropping_location' => $booking->dropping_location,
                'categories' => json_decode($booking->categories ?? '[]'),
                'count' => $booking->count,
                'vehicle_images' => json_decode($booking->vehicle_images ?? '[]'),
                'created_at' => $booking->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $booking->updated_at->format('Y-m-d H:i:s'),
            ];
        });
    }

    // ✅ Update Booking Status (Admin)
    public function updateStatus(Request $request, Booking $booking)
    {
        $request->validate([
            'status' => 'required|integer|between:1,6',
            // When cancelling from admin, a reason is required so it shows in
            // the user app. Free text is required only for "Other" (code 5).
            'reason_code' => 'required_if:status,6|nullable|integer|between:1,5',
            'other_reason' => 'required_if:reason_code,5|nullable|string|max:500',
        ]);

        $booking->status = $request->status;

        // Persist the cancellation reason so the user app can display it.
        if ((int) $request->status === 6) {
            $booking->cancellation_reason = $request->reason_code;
            $booking->cancellation_reason_text =
                ((int) $request->reason_code === 5) ? $request->other_reason : null;
        }

        // Save timestamp for the status change
        $timestampMap = [
            2 => 'confirmed_at',
            3 => 'driver_assigned_at',
            4 => 'in_progress_at',
            5 => 'delivered_at',
            6 => 'cancelled_at',
        ];

        // Always record the LATEST transition, not just the first one — a
        // booking moved back to a status it already visited must show the new
        // time. Booking::booted() also enforces this for every other write path.
        if (isset($timestampMap[$request->status])) {
            $column = $timestampMap[$request->status];
            $booking->$column = now();
        }

        $booking->save();

        // Clear tracking alerts on delivery/cancellation
        if (in_array((int) $request->status, [5, 6])) {
            TrackingAlert::where('booking_id', $booking->id)->delete();
            $booking->update(['driver_inactive_reason' => null]);
        }

        // Send push notification to farmer
        $this->sendBookingNotification($booking, $request->status);

        return back()->with('success', 'Status updated');
    }

    // ✅ Assign Driver & Vehicle (Admin)
    public function assignDriver(Request $request, Booking $booking)
    {
        $request->validate([
            'driver_id' => 'nullable|integer|exists:drivers,id',
            'driver_name' => 'required|string|max:255',
            'driver_mobile' => 'required|string|max:20',
        ]);

        // If driver_id is provided, use that driver
        if ($request->driver_id) {
            $driver = Driver::find($request->driver_id);
        } else {
            // Otherwise create or find by mobile
            $driver = Driver::firstOrCreate(
                ['mobile' => $request->driver_mobile],
                [
                    'name' => $request->driver_name,
                    'status' => 1
                ]
            );
        }

        $booking->update([
            'driver_id' => $driver->id,
            'driver_name' => $request->driver_name,
            'driver_mobile' => $request->driver_mobile,
            'vehicle_started_date' => now(),
            'status' => 3, // Driver Assigned (status 3 as per correct map)
            // Re-assigning a driver must show the NEW assignment time. This used
            // to be `$booking->driver_assigned_at ?? now()`, which kept the very
            // first assignment forever — the reported "shows yesterday" bug.
            'driver_assigned_at' => now(),
        ]);

        // Send push notification to farmer
        $this->sendBookingNotification($booking, 3);

        // Also notify the newly assigned driver so the booking pops up in
        // their app immediately.
        if (!empty($driver->fcm_token)) {
            try {
                app(FirebaseNotificationService::class)->sendToDevice(
                    $driver->fcm_token,
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
            } catch (\Throwable $e) {
                Log::warning('Admin assignDriver push failed', [
                    'booking_id' => $booking->id,
                    'driver_id'  => $driver->id,
                    'err'        => $e->getMessage(),
                ]);
            }
        }

        return back()->with('success', 'Driver assigned successfully');
    }


    public function cancel(Request $request, Booking $booking)
    {
        $request->validate([
            'reason_code' => 'required|integer|between:1,5',
            'other_reason' => 'required_if:reason_code,5|nullable|string|max:500',
        ]);

        $booking->update([
            'status' => 6,
            'cancellation_reason' => $request->reason_code,
            'cancellation_reason_text' =>
                ((int) $request->reason_code === 5) ? $request->other_reason : null,
            'cancelled_at' => now(), // add column if not exists
        ]);

        // Send push notification to farmer
        $this->sendBookingNotification($booking, 6);

        return back()->with('success', 'Booking cancelled successfully');
    }

    /**
     * Get hatchery details for booking form
     */
    public function getHatcheryDetails($id)
    {
        $hatchery = Hatchery::with('category')->find($id);

        if (!$hatchery) {
            return response()->json(['error' => 'Hatchery not found'], 404);
        }
/////
        return response()->json([
            'id' => $hatchery->id,
            'hatchery_name' => $hatchery->hatchery_name,
            'category_id' => $hatchery->category_id,
            'category_name' => $hatchery->category->category_name ?? null,
            'brand_id' => $hatchery->brand_id,
            'price' => $hatchery->price,
            'location_id' => $hatchery->location_id,
            'vendor_id' => $hatchery->vendor_id,
        ]);
    }

    /**
     * Store a new driver (AJAX)
     */

    // store driver
    public function storeDriver(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'mobile' => 'required|string|max:20|unique:drivers,mobile',
        ]);

        $driver = Driver::create([
            'name' => $request->name,
            'mobile' => $request->mobile,
            'status' => 1,
        ]);

        return response()->json([
            'success' => true,
            'driver' => $driver,
        ]);
    }

    /**
     * AJAX endpoint: return drivers who haven't sent location updates in 4+ minutes
     */
    public function inactiveDrivers(Request $request)
    {
        $perPage = 10;
        $pageParam = $request->get('page', 1);
        $loadAll = ($pageParam === 'all');
        $page = $loadAll ? 1 : max(1, (int) $pageParam);

        // Only fetch drivers where Flutter reported a reason
        $inactiveBookings = Booking::where('status', 4)
            ->whereNotNull('driver_id')
            ->whereNotNull('driver_inactive_reason')
            ->get();

        $totalCount = $inactiveBookings->count();

        // Sort by latest first
        $sorted = $inactiveBookings->sortByDesc(function ($booking) {
            return $booking->driver_location_updated_at ?? $booking->in_progress_at ?? now()->subYear();
        })->values();

        $paged = $loadAll ? $sorted : $sorted->slice(($page - 1) * $perPage, $perPage);
        $hasMore = $loadAll ? false : ($page * $perPage) < $totalCount;

        $drivers = $paged->map(function ($booking) {
            $lastUpdate = $booking->driver_location_updated_at;
            $minutesInactive = $lastUpdate
                ? (int) now()->diffInMinutes($lastUpdate, true)
                : ($booking->in_progress_at
                    ? (int) now()->diffInMinutes($booking->in_progress_at, true)
                    : 0);

            return [
                'booking_id' => $booking->id,
                'booking_uid' => $booking->booking_uid ?? '',
                'driver_name' => $booking->driver_name ?? 'N/A',
                'driver_mobile' => $booking->driver_mobile ?? 'N/A',
                'vehicle_number' => $booking->vehicle_number ?? 'N/A',
                'customer_name' => $booking->customer_name ?? 'N/A',
                'last_updated' => $lastUpdate ? $lastUpdate->format('d M, g:i A') : 'Never',
                'minutes_inactive' => $minutesInactive,
                'reason' => $booking->driver_inactive_reason ?? 'Unknown',
                'driver_location_name' => $booking->driver_location_name ?? 'Unknown',
            ];
        })->values()->all();

        return response()->json([
            'count' => $totalCount,
            'page' => $page,
            'has_more' => $hasMore,
            'drivers' => $drivers,
        ]);
    }

    /**
     * Show vehicle tracking page for a booking
     */
    public function tracking(Booking $booking)
    {
        return view('admin.bookings.tracking', compact('booking'));
    }

    /**
     * Force-clear the route timeline cache for a booking and regenerate it.
     * Used from the admin tracking page when intermediate stops are missing.
     */
    public function refreshRoute(Booking $booking)
    {
        $service = new \App\Services\RouteTimelineService();
        $service->clearCache($booking->id);
        $stops = $service->getIntermediateStops($booking);

        return response()->json([
            'success'     => true,
            'stop_count'  => count($stops),
            'stops'       => $stops,
            'message'     => count($stops) > 0
                ? 'Route regenerated: ' . count($stops) . ' intermediate stops found.'
                : 'Route regenerated but no stops returned — check booking coordinates and Google API key.',
        ]);
    }

    /**
     * AJAX endpoint: return live tracking data for a booking
     */
    public function trackingData(Booking $booking)
    {
        $booking->load('trackings');

        $routeWaypoints = [];
        if ($booking->driver_id && $booking->vehicle_started_date) {
            $routeBookings = Booking::query()
                ->where('driver_id', $booking->driver_id)
                ->whereDate('vehicle_started_date', $booking->vehicle_started_date)
                ->whereNotNull('priority')
                ->where('id', '!=', $booking->id)
                ->orderBy('priority')
                ->get();

            $routeWaypoints = $routeBookings
                ->filter(function ($routeBooking) use ($booking) {
                    return $routeBooking->priority !== null
                        && $routeBooking->priority < $booking->priority
                        && $routeBooking->drop_lat
                        && $routeBooking->drop_lng;
                })
                ->map(function ($routeBooking) use ($booking) {
                    return [
                        'lat' => (float) $routeBooking->drop_lat,
                        'lng' => (float) $routeBooking->drop_lng,
                        'status' => (int) ($routeBooking->status ?? 0),
                        'priority' => (int) ($routeBooking->priority ?? 0),
                        'name' => $routeBooking->dropping_location ?? $routeBooking->delivery_location ?? 'Route stop',
                        'is_before' => $routeBooking->priority < $booking->priority,
                    ];
                })
                ->values()
                ->all();
        }

        // Pickup location
        if ($booking->vehicle_start_lat && $booking->vehicle_start_lng) {
            $pickup = [
                'name' => $booking->vehicle_start_address ?? $booking->hatchery_name ?? 'Pickup',
                'lat' => (float) $booking->vehicle_start_lat,
                'lng' => (float) $booking->vehicle_start_lng,
            ];
        } elseif ($booking->driver_lat && $booking->driver_lng) {
            $pickup = [
                'name' => $booking->driver_location_name ?? 'Pickup',
                'lat' => (float) $booking->driver_lat,
                'lng' => (float) $booking->driver_lng,
            ];
        } else {
            $pickup = ['name' => $booking->hatchery_name ?? 'Pickup', 'lat' => null, 'lng' => null];
        }

        // Drop location
        $drop = [
            'name' => $booking->dropping_location ?? $booking->delivery_location ?? 'Destination',
            'lat' => $booking->drop_lat ? (float) $booking->drop_lat : null,
            'lng' => $booking->drop_lng ? (float) $booking->drop_lng : null,
        ];

        // Driver current location
        $driverLocation = [
            'name' => $booking->driver_location_name ?? 'Location not available',
            'lat' => $booking->driver_lat ? (float) $booking->driver_lat : null,
            'lng' => $booking->driver_lng ? (float) $booking->driver_lng : null,
            'updated_at' => ($booking->driver_location_updated_at ?? $booking->updated_at)?->toDateTimeString(),
        ];

        // Driver details
        $driverDetails = [
            'driver_name' => $booking->driver_name ?? 'N/A',
            'driver_phone' => $booking->driver_mobile ?? 'N/A',
            'vehicle_number' => $booking->vehicle_number ?? 'N/A',
        ];

        // Pickup start time (in_progress_at or vehicle_started_date)
        $pickupStartTime = $booking->vehicle_started_date
            ? \Carbon\Carbon::parse($booking->vehicle_started_date)
            : ($booking->in_progress_at
                ? \Carbon\Carbon::parse($booking->in_progress_at)
                : null);

        $status = $booking->status;
        $inProgressStatus = in_array($status, [4, 5]) ? 'completed' : 'pending';
        $deliveredStatus = $status == 5 ? 'completed' : 'pending';

        // Timeline from vehicle_trackings table
        $timeline = [];
        if ($booking->trackings->count() > 0) {
            foreach ($booking->trackings as $index => $t) {
                $entry = [
                    'title' => $t->title ?? $t->location_name ?? '',
                    'subtitle' => $t->subtitle ?? '',
                    'time' => $t->reached_at
                        ? \Carbon\Carbon::parse($t->reached_at)->format('g:i A')
                        : '-',
                    'date' => $t->reached_at
                        ? \Carbon\Carbon::parse($t->reached_at)->format('d/m/Y')
                        : '',
                    'status' => $t->status ?? 'pending',
                    'lat' => $t->lat ? (float) $t->lat : null,
                    'lng' => $t->lng ? (float) $t->lng : null,
                ];

                // First item: ensure "Pickup started from" title and use in_progress_at time
                if ($index === 0) {
                    $entry['title'] = 'Pickup started from';
                    $entry['subtitle'] = $entry['subtitle'] ?: $pickup['name'];
                    if ($entry['time'] === '-' && $pickupStartTime) {
                        $entry['time'] = $pickupStartTime->format('g:i A');
                        $entry['date'] = $pickupStartTime->format('d/m/Y');
                    }
                }

                // Last item: only label as "Destination" if booking is delivered
                if ($index === $booking->trackings->count() - 1 && $status == 5) {
                    $entry['title'] = 'Destination';
                    $entry['subtitle'] = $entry['subtitle'] ?: $drop['name'];
                    if ($booking->delivered_at) {
                        $entry['time'] = \Carbon\Carbon::parse($booking->delivered_at)->format('g:i A');
                        $entry['date'] = \Carbon\Carbon::parse($booking->delivered_at)->format('d/m/Y');
                    }
                }

                $timeline[] = $entry;
            }
        } else {
            // Build default timeline if no tracking points
            $timeline[] = [
                'title' => 'Pickup started from',
                'subtitle' => $pickup['name'],
                'time' => $pickupStartTime
                    ? $pickupStartTime->format('g:i A')
                    : '-',
                'date' => $pickupStartTime
                    ? $pickupStartTime->format('d/m/Y')
                    : '',
                'status' => $inProgressStatus,
            ];

            $timeline[] = [
                'title' => 'Destination',
                'subtitle' => $drop['name'],
                'time' => $booking->delivered_at
                    ? \Carbon\Carbon::parse($booking->delivered_at)->format('g:i A')
                    : '-',
                'date' => $booking->delivered_at
                    ? \Carbon\Carbon::parse($booking->delivered_at)->format('d/m/Y')
                    : '',
                'status' => $deliveredStatus,
            ];
        }

        // Real tracking history (lat/lng saved every ~2 mins by driver app)
        // Apply GPS filtering to remove noise — mirrors Flutter's breadcrumb filter logic:
        //   • Skip < 20 m movements (noise floor)
        //   • Skip speed spikes (> 200 km/h within 60 s, or > 500 m in < 5 s)
        //   • Skip zigzags (new point returns within 15 m of a recent point)
        //   • Skip backward jumps (direction reversal > 150° for tiny hops < 30 m)
        $rawPoints = $booking->trackings
            ->map(function ($t) {
                return [
                    'lat' => $t->lat ? (float) $t->lat : null,
                    'lng' => $t->lng ? (float) $t->lng : null,
                    'reached_at' => $t->reached_at ?? $t->created_at?->toDateTimeString(),
                ];
            })
            ->filter(fn($t) => $t['lat'] !== null && $t['lng'] !== null)
            ->values()
            ->all();

        $filteredHistory = [];
        $prev = null;
        $prevPrev = null;
        $prevPrevPrev = null;
        $prevTime = null;

        foreach ($rawPoints as $pt) {
            if ($prev === null) {
                $filteredHistory[] = $pt;
                $prev = $pt;
                $prevTime = $pt['reached_at'] ? strtotime($pt['reached_at']) : null;
                continue;
            }

            $meters = $this->_haversineMeters($prev['lat'], $prev['lng'], $pt['lat'], $pt['lng']);
            $timeSec = 0;
            if ($prevTime && $pt['reached_at']) {
                $timeSec = abs(strtotime($pt['reached_at']) - $prevTime);
            }

            // Noise floor: skip if moved < 20 m
            if ($meters < 20) {
                continue;
            }

            // Speed spike: > 200 km/h within 60 s
            if ($timeSec > 0 && $timeSec <= 60 && ($meters / $timeSec) > 55.5) {
                continue;
            }

            // Absolute spike: > 500 m in < 5 s
            if ($meters > 500 && $timeSec < 5) {
                continue;
            }

            // Zigzag: new point very close to a point 2-3 steps back
            if ($meters < 50 && $prevPrev !== null) {
                $d2 = $this->_haversineMeters($pt['lat'], $pt['lng'], $prevPrev['lat'], $prevPrev['lng']);
                if ($d2 < 15) {
                    continue;
                }
                if ($prevPrevPrev !== null) {
                    $d3 = $this->_haversineMeters($pt['lat'], $pt['lng'], $prevPrevPrev['lat'], $prevPrevPrev['lng']);
                    if ($d3 < 15) {
                        continue;
                    }
                }
            }

            // Backward jump: direction reversal > 150° for tiny hops < 30 m
            if ($meters < 30 && $prevPrev !== null) {
                $b1 = $this->_getBearing($prevPrev['lat'], $prevPrev['lng'], $prev['lat'], $prev['lng']);
                $b2 = $this->_getBearing($prev['lat'], $prev['lng'], $pt['lat'], $pt['lng']);
                $diff = abs($b2 - $b1);
                if ($diff > 180) $diff = 360 - $diff;
                if ($diff > 150) {
                    continue;
                }
            }

            $filteredHistory[] = $pt;
            $prevPrevPrev = $prevPrev;
            $prevPrev = $prev;
            $prev = $pt;
            $prevTime = $pt['reached_at'] ? strtotime($pt['reached_at']) : null;
        }

        $trackingHistory = $filteredHistory;

        // Server-side intermediate stops — same list returned to all three apps
        // so customer, employee and admin web always show identical location names.
        $autoTimelinePoints = (new RouteTimelineService())->getIntermediateStops($booking);

        return response()->json([
            'pickup' => $pickup,
            'drop' => $drop,
            'driver_location' => $driverLocation,
            'driver_details' => $driverDetails,
            'timeline' => $timeline,
            'route_waypoints' => $routeWaypoints,
            'tracking_history' => $trackingHistory,
            'status' => $booking->status,
            'in_progress_at' => $pickupStartTime?->toDateTimeString(),
            'auto_timeline_points' => $autoTimelinePoints,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /** Haversine distance in metres between two lat/lng points. */
    private function _haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $R * asin(sqrt($a));
    }

    /** Compass bearing (0-360°) from point A to point B. */
    private function _getBearing(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLng = deg2rad($lng2 - $lng1);
        $lat1r = deg2rad($lat1);
        $lat2r = deg2rad($lat2);
        $y = sin($dLng) * cos($lat2r);
        $x = cos($lat1r) * sin($lat2r) - sin($lat1r) * cos($lat2r) * cos($dLng);
        return fmod(rad2deg(atan2($y, $x)) + 360, 360);
    }
}
