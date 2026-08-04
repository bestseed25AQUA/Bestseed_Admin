<?php

namespace App\Http\Controllers\Api\Vendors_apis;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Farmer;
use App\Models\Vendor;
use App\Models\Contact;
use Illuminate\Http\Request;
use App\Models\PushNotification;
use App\Services\FirebaseNotificationService;
use App\Helpers\SmsHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Booking;
use App\Models\TrackingAlert;
use App\Models\VehicleTracking;
use App\Models\VehicleTrackingStop;
use Carbon\Carbon;

class DriverBookingController extends Controller
{
    /**
     * Calculate distance between two coordinates using Haversine formula.
     * Returns distance in kilometers.
     */
    private static function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
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

    public function index(Request $request)
    {
        // 1. Get authenticated driver
        $driver = Auth::user();

        // 2. Build query for this driver's bookings using driver_id
        $query = Booking::where('driver_id', $driver->id)
            ->with([
                'hatchery:id,hatchery_name,is_spot,location_id',
                'hatchery.location:id,location_name,latitude,longitude',
                'category:id,category_name'
            ]);

        /* ---------------- TAB FILTER ---------------- */
        if ($request->filled('tab')) {
            match ($request->tab) {
                'live' => $query->whereIn('status', 4),
                'assigned' => $query->where('status', 3),
                'past' => $query->where('status', [5, 6]),
                default => null
            };
        }

        /* ---------------- GLOBAL SEARCH ---------------- */
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%$search%")
                    ->orWhere('booking_uid', 'like', "%$search%")
                    ->orWhere('customer_name', 'like', "%$search%")
                    ->orWhere('dropping_location', 'like', "%$search%")
                    ->orWhereHas('hatchery', function ($hq) use ($search) {
                        $hq->where('hatchery_name', 'like', "%$search%");
                    })
                    ->orWhereHas('category', function ($cq) use ($search) {
                        $cq->where('category_name', 'like', "%$search%");
                    });
            });
        }

        /* ---------------- BOOKING TYPE FILTER ---------------- */
        if ($request->filled('booking_type')) {
            match ($request->booking_type) {
                'hatchery' => $query->whereHas('hatchery', fn($q) => $q->where('is_spot', 0)),
                'spot' => $query->whereHas('hatchery', fn($q) => $q->where('is_spot', 1)),
                default => null
            };
        }

        /* ---------------- STATUS FILTER ---------------- */
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $bookings = $query->orderBy('vehicle_started_date', 'desc')->get();

        // 3. Group bookings into routes — vehicle_started_date + status +
        // hatchery_id. A driver can be assigned to bookings from MULTIPLE
        // hatcheries that share the same start date and status (e.g.
        // Manjunadha + Raja both started today, both in journey). Grouping
        // only by date+status collapsed them into one route and the route
        // card showed only the first booking's hatchery name. Adding
        // hatchery_id to the key keeps each hatchery as its own route.
        $routes = $bookings
            ->groupBy(function ($booking) {
                $date = $booking->vehicle_started_date?->format('Y-m-d') ?? 'unknown';
                $status = $booking->status ?? 0;
                $hatchery = $booking->hatchery_id ?? 0;
                return $date . '_' . $status . '_' . $hatchery;
            })
            ->map(function ($group) {

                $first = $group->first();

                // Get start location from booking's vehicle_start fields
                // Check first booking, if not available check second, then third, etc.
                $startLocation = null;
                foreach ($group as $booking) {
                    if (
                        !empty($booking->vehicle_start_address) ||
                        !empty($booking->vehicle_start_lat) ||
                        !empty($booking->vehicle_start_lng)
                    ) {
                        $startLocation = [
                            'location_id' => null,
                            'location_name' => $booking->vehicle_start_address,
                            'lat' => $booking->vehicle_start_lat,
                            'lng' => $booking->vehicle_start_lng,
                        ];
                        break;
                    }
                }

                // Fallback to hatchery location if no booking has vehicle_start fields
                if ($startLocation === null) {
                    $startLocation = [
                        'location_id' => $first->hatchery->location->id ?? null,
                        'location_name' => $first->hatchery->location->location_name ?? null,
                        'lat' => $first->hatchery->location->latitude ?? null,
                        'lng' => $first->hatchery->location->longitude ?? null,
                    ];
                }

                return [
                    // ROUTE INFO (for Home screen)
                    'vehicle_started_date' => $first->vehicle_started_date,
                    'start_location' => $startLocation,
                    'hatchery_id' => $first->hatchery_id,
                    'hatchery' => [
                        'id' => $first->hatchery->id ?? null,
                        'category_name' => $first->hatchery_name ?? $first->hatchery->hatchery_name ?? null,
                    ],
                    'category_id' => $first->category_id,
                    'category' => [
                        'id' => $first->category->id ?? null,
                        'category_name' => $first->category->category_name ?? null,
                    ],
                    'total_drops' => $group->count(),
                    'total_pieces' => $group->sum('no_of_pieces'),

                    // 👇 BOOKINGS FOR 3RD SCREEN
                    'bookings' => $group->sortBy('priority')->values()->map(function ($booking) {
                        return [
                            'id' => $booking->id,
                            'booking_uid' => $booking->booking_uid,
                            'customer_name' => $booking->customer_name,
                            'customer_mobile' => $booking->customer_mobile,
                            'dropping_location' => $booking->dropping_location,
                            'no_of_pieces' => $booking->no_of_pieces,
                            'status' => $booking->status,
                            'drop_lat' => $booking->drop_lat,
                            'drop_lng' => $booking->drop_lng,
                            'delivery_note' => $booking->delivery_note,
                            'delivery_datetime' => $booking->delivery_datetime
                                ? Carbon::parse($booking->delivery_datetime)->format('d/m/Y')
                                : ($booking->delivery_date ? Carbon::parse($booking->delivery_date)->format('d/m/Y') : ($booking->delivery_expected ? Carbon::parse($booking->delivery_expected)->format('d/m/Y') : null)),
                            'delivery_date' => $booking->delivery_date
                                ? Carbon::parse($booking->delivery_date)->format('d/m/Y')
                                : ($booking->delivery_datetime ? Carbon::parse($booking->delivery_datetime)->format('d/m/Y') : ($booking->delivery_expected ? Carbon::parse($booking->delivery_expected)->format('d/m/Y') : null)),
                            'priority' => $booking->priority,
                            // Per-booking category. Bookings grouped into the
                            // same route can have different categories (e.g.
                            // "syaqua" + "hyderline"), so the route-level
                            // `category.category_name` only reflects the first
                            // one. Driver home screen needs the full set to
                            // render per-booking chips.
                            'category_name' => $booking->category->category_name ?? null,
                        ];
                    })->values(),
                ];
            })
            ->values();

        // 4. Calculate ROUTE counts for tabs (not booking counts)
        $liveCount = 0;
        $assignedCount = 0;
        $pastCount = 0;

        foreach ($routes as $route) {
            $bookingStatuses = array_column($route['bookings']->toArray(), 'status');

            // Live: has any booking with status 3 or 4
            if (in_array(4, $bookingStatuses)) {
                $liveCount++;
            }

            // Assigned: has any booking with status 3
            if (in_array(3, $bookingStatuses)) {
                $assignedCount++;
            }

            // Past: all bookings have status 5 or 6
            if (!empty($bookingStatuses) && count(array_diff($bookingStatuses, [5, 6])) === 0) {
                $pastCount++;
            }
        }

        $routeCounts = [
            'all' => $routes->count(),
            'live' => $liveCount,
            'assigned' => $assignedCount,
            'past' => $pastCount,
        ];

        // 5. Response
        return response()->json([
            'status' => true,
            'counts' => $routeCounts,
            'routes' => $routes
        ]);
    }

    public function startJourney(Request $request)
    {
        $request->validate([
            'booking_ids' => 'required|array|min:1',
            'booking_ids.*' => 'integer|exists:bookings,id',
            'start_lat' => 'nullable|numeric',
            'start_lng' => 'nullable|numeric',
            'start_address' => 'nullable|string|max:500',
        ]);

        $driver = Auth::user();

        $bookings = Booking::whereIn('id', $request->booking_ids)
            ->where('driver_id', $driver->id)
            ->where('status', 3)
            ->get();

        $updated = 0;
        foreach ($bookings as $booking) {
            // Clear old tracking entries so timeline starts fresh from this journey
            $booking->trackings()->delete();

            $data = [
                'status' => 4,
                'vehicle_started_date' => now(),
                'in_progress_at' => now(),
                // Seed driver position from start location so the GPS spike
                // filter has a reference point on the very first location update.
                'driver_lat' => $request->start_lat,
                'driver_lng' => $request->start_lng,
                'driver_location_name' => $request->start_address,
                'driver_location_updated_at' => now(),
            ];

            // Save driver's current location as start location only if not already set
            if (empty($booking->vehicle_start_lat) && $request->start_lat) {
                $data['vehicle_start_lat'] = $request->start_lat;
            }
            if (empty($booking->vehicle_start_lng) && $request->start_lng) {
                $data['vehicle_start_lng'] = $request->start_lng;
            }
            if (empty($booking->vehicle_start_address) && $request->start_address) {
                $data['vehicle_start_address'] = $request->start_address;
            }

            $booking->update($data);

            // Create first tracking entry at the journey start location
            if ($request->start_lat && $request->start_lng) {
                $booking->trackings()->create([
                    'lat' => $request->start_lat,
                    'lng' => $request->start_lng,
                    'location_name' => $request->start_address ?? 'Journey started',
                    'status' => 'current',
                    'reached_at' => now(),
                    'order' => 1,
                ]);
            }

            $this->sendBookingNotification($booking->fresh(), 4);
            $updated++;
        }

        return response()->json([
            'status' => true,
            'message' => 'Journey started successfully',
            'updated_bookings' => $updated,
        ]);
    }


    public function updateDriverLocation(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'location_name' => 'nullable|string',
        ]);

        $driver = Auth::user();

        $lat = round((float) $request->lat, 6);
        $lng = round((float) $request->lng, 6);
        $locName = $request->location_name ?? '';
        $acc     = $request->accuracy ?? '?';

        Log::info("LOC_UPDATE driver={$driver->id} lat={$lat} lng={$lng} acc={$acc}m name=\"{$locName}\"");

        // Get all active bookings for this driver using driver_id
        $bookings = Booking::where('driver_id', $driver->id)
            ->where('status', 4) // In Process
            ->get();

        if ($bookings->isEmpty()) {
            Log::info("LOC_UPDATE driver={$driver->id} → no active booking (status 4) — rejected");
            return response()->json([
                'status' => false,
                'message' => 'No active journey found',
            ]);
        }

        foreach ($bookings as $booking) {
            // Auto-clear stale tracking alert when driver resumes sending location.
            // If there was a tracking alert and location was stale for 5+ minutes,
            // notify vendor that tracking has resumed.
            $wasStale = false;
            if ($booking->last_tracking_alert_at && $booking->driver_location_updated_at) {
                $lastUpdate = Carbon::parse($booking->driver_location_updated_at);
                $wasStale = $lastUpdate->diffInMinutes(now()) >= 5;
            }

            // ── Normalize GPS precision to 6 decimal places ──
            // More than 6 decimals creates fake jitter (~0.1m precision noise)
            $lat = round((float) $request->lat, 6);
            $lng = round((float) $request->lng, 6);

            // ── GPS spike detection (multi-layer) ──
            //
            // Anchor against the most recent KNOWN-GOOD tracking row, NOT
            // against $booking->driver_lat. Reason: $booking->driver_lat is
            // updated on every accepted poll, so if a borderline-spike sneaks
            // through it becomes the new "previous" position and the next
            // real point looks like a reverse jump. Tracking rows are
            // append-only and pre-validated, so they're a stable anchor.
            //
            // Simulation bypass: X-Simulation header skips spike filter (staging only).
            $simulationMode = app()->environment('local', 'staging') && $request->header('X-Simulation') === 'true';
            $isGpsSpike = false;
            $distMeters = 0;
            $secsSinceLastUpdate = 0;
            $anchorPoint = VehicleTracking::where('booking_id', $booking->id)
                ->whereNotNull('reached_at')
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->latest('reached_at')
                ->first();

            if ($anchorPoint && !$simulationMode) {
                $prevLat = (float) $anchorPoint->lat;
                $prevLng = (float) $anchorPoint->lng;
                $distMeters = self::haversineDistance($prevLat, $prevLng, $lat, $lng) * 1000;
                $secsSinceLastUpdate = Carbon::parse($anchorPoint->reached_at)->diffInSeconds(now());

                // ── GPS spike layers ──
                // Tuned for a 200 km/h cap (≈55.6 m/s) so cars/buses on
                // expressways aren't rejected as spikes. The thresholds
                // below are the SMALLEST values that still catch real
                // teleports without false-rejecting legitimate fast hops.
                //
                // Layer 1: >500m in <5s = teleport (>360 km/h instantaneous)
                if ($distMeters > 500 && $secsSinceLastUpdate < 5) {
                    $isGpsSpike = true;
                    Log::info("GPS spike (teleport): booking={$booking->id}, dist={$distMeters}m in {$secsSinceLastUpdate}s");
                }
                // Layer 2: >4km in <60s (was >1km — sustained 200 km/h
                // covers 3.3 km/min and was being rejected here).
                elseif ($distMeters > 4000 && $secsSinceLastUpdate < 60) {
                    $isGpsSpike = true;
                    Log::info("GPS spike (speed): booking={$booking->id}, dist={$distMeters}m in {$secsSinceLastUpdate}s");
                }
                // Layer 3: speed > 200 km/h = impossible for any road vehicle
                elseif ($secsSinceLastUpdate > 0 && ($distMeters / $secsSinceLastUpdate) * 3.6 > 200) {
                    $isGpsSpike = true;
                    $speedKmh = round(($distMeters / $secsSinceLastUpdate) * 3.6, 1);
                    Log::info("GPS spike (>200km/h): booking={$booking->id}, speed={$speedKmh}km/h");
                }
                // Layer 4: >700m in <10s = mid-range jump (was >300m —
                // 200 km/h covers 555 m in 10s, was being rejected here).
                // Still catches the booking 547 5 km lat-flip case which
                // landed in the dead zone between Layer 1 and Layer 2.
                elseif ($distMeters > 700 && $secsSinceLastUpdate < 10) {
                    $isGpsSpike = true;
                    Log::info("GPS spike (mid-jump): booking={$booking->id}, dist={$distMeters}m in {$secsSinceLastUpdate}s");
                }
            }
            // NOTE: "far from pickup" check removed.
            // It caused an infinite rejection loop when admin set wrong pickup coords:
            // no anchor row → compare against pickup → 7km away → reject → no row saved
            // → still no anchor → still reject → forever frozen on user app.
            // The 4 speed/distance layers above are sufficient for real GPS teleport detection.

            // ── Is driver actually moving? ──
            // Don't use instantaneous speed (GPS noise of 25m in 10s = 9km/h = false positive).
            // Instead: check if driver moved >30m from where they were 1 minute ago.
            $isMoving = true; // default true for first few updates
            if (!$isGpsSpike && $booking->driver_lat && $booking->driver_lng) {
                $recentTracking = VehicleTracking::where('booking_id', $booking->id)
                    ->whereNotNull('reached_at')
                    ->where('reached_at', '<=', now()->subSeconds(60))
                    ->latest('reached_at')
                    ->first();

                if ($recentTracking) {
                    $netDist = self::haversineDistance(
                        (float) $recentTracking->lat, (float) $recentTracking->lng,
                        $lat, $lng
                    ) * 1000;
                    $isMoving = $netDist > 30; // moved >30m in last minute = actually moving
                }
            }

            Log::info("LOC_UPDATE booking={$booking->id} spike=" . ($isGpsSpike ? 'YES' : 'no') . " moving=" . ($isMoving ? 'yes' : 'NO') . " dist={$distMeters}m secs={$secsSinceLastUpdate}s");

            // Update booking — skip lat/lng on GPS spikes but always refresh timestamp
            $updateData = [
                'driver_location_updated_at' => now(),
                'last_tracking_alert_at' => null,
            ];
            if (!$isGpsSpike) {
                $updateData['driver_lat'] = $lat;
                $updateData['driver_lng'] = $lng;
                $updateData['driver_location_name'] = $request->location_name;
            }
            $booking->update($updateData);

            // ── Distance-based tracking point insertion with multi-layer validation ──
            // GPS bounces between 2 positions ~25m apart even when stationary.
            // Pipeline: net displacement → zig-zag rejection → distance threshold → save
            if (!$isGpsSpike && $isMoving) {
                $lastTracking = VehicleTracking::where('booking_id', $booking->id)
                    ->whereNotNull('reached_at')
                    ->latest('reached_at')
                    ->first();

                // Anchor point: position from 2+ minutes ago (detects if driver actually moved)
                $anchorTracking = VehicleTracking::where('booking_id', $booking->id)
                    ->whereNotNull('reached_at')
                    ->where('reached_at', '<=', now()->subMinutes(2))
                    ->latest('reached_at')
                    ->first();

                $shouldSave = false;
                if (!$lastTracking) {
                    $shouldSave = true;
                } else {
                    $distFromLast = self::haversineDistance(
                        (float) $lastTracking->lat, (float) $lastTracking->lng,
                        $lat, $lng
                    ) * 1000;

                    $secsSinceLast = Carbon::parse($lastTracking->reached_at)->diffInSeconds(now());

                    // Net displacement check: if driver hasn't moved >30m from anchor,
                    // they're bouncing in place (GPS noise), not actually moving.
                    $isActuallyMoving = true;
                    if ($anchorTracking) {
                        $netDisplacement = self::haversineDistance(
                            (float) $anchorTracking->lat, (float) $anchorTracking->lng,
                            $lat, $lng
                        ) * 1000;
                        if ($netDisplacement < 30) {
                            $isActuallyMoving = false; // bouncing in place
                        }
                    }

                    // Zig-zag rejection: if new point is within 15m of 2-points-ago, it's noise
                    $isZigZag = false;
                    if ($isActuallyMoving && $distFromLast < 50) {
                        $secondLastTracking = VehicleTracking::where('booking_id', $booking->id)
                            ->whereNotNull('reached_at')
                            ->where('id', '<', $lastTracking->id)
                            ->latest('reached_at')
                            ->first();
                        if ($secondLastTracking) {
                            $distBackToOld = self::haversineDistance(
                                (float) $secondLastTracking->lat, (float) $secondLastTracking->lng,
                                $lat, $lng
                            ) * 1000;
                            if ($distBackToOld < 15) {
                                $isZigZag = true;
                                Log::info("Zig-zag rejected: booking={$booking->id}, dist_back={$distBackToOld}m");
                            }
                        }
                    }

                    // Save if: moved ≥ 25m from last point AND actually moving AND not zig-zag
                    if ($distFromLast >= 25 && $secsSinceLast >= 10 && $isActuallyMoving && !$isZigZag) {
                        $shouldSave = true;
                    }
                    // Also save if 2+ minutes passed with some movement (>10m)
                    elseif ($distFromLast >= 10 && $secsSinceLast >= 120 && $isActuallyMoving && !$isZigZag) {
                        $shouldSave = true;
                    }
                }

                if ($shouldSave) {
                    Log::info("LOC_UPDATE booking={$booking->id} → TRACKING POINT SAVED lat={$lat} lng={$lng} name=\"{$locName}\"");
                    $maxOrder = VehicleTracking::where('booking_id', $booking->id)->max('order') ?? 0;

                    VehicleTracking::where('booking_id', $booking->id)
                        ->where('status', 'current')
                        ->update(['status' => 'completed']);

                    VehicleTracking::create([
                        'booking_id' => $booking->id,
                        'location_name' => $request->location_name,
                        'lat' => $lat,
                        'lng' => $lng,
                        'title' => $request->location_name,
                        'subtitle' => '',
                        'status' => 'current',
                        'reached_at' => now(),
                        'order' => $maxOrder + 1,
                    ]);
                } else {
                    Log::info("LOC_UPDATE booking={$booking->id} → point filtered (not saved) distFromLast=" . ($lastTracking ? round(self::haversineDistance((float)$lastTracking->lat,(float)$lastTracking->lng,$lat,$lng)*1000) . 'm' : 'n/a'));
                }
            }

            // Log resume alert if there was an inactive reason
            if ($booking->driver_inactive_reason) {
                TrackingAlert::create([
                    'booking_id' => $booking->id,
                    'driver_id' => $driver->id,
                    'vendor_id' => $booking->vendor_id,
                    'reason' => 'Driver is back online - Tracking resumed',
                    'location_name' => $request->location_name,
                ]);
                $booking->update(['driver_inactive_reason' => null]);
            }

            // Notify vendor that tracking has resumed after being stale
            if ($wasStale) {
                try {
                    $bookingRef = "#{$booking->id}";
                    $driverName = $driver->name ?? 'Driver';
                    $title = "Tracking Resumed - Booking Id {$bookingRef}";
                    $body = "{$driverName} is back online. Location tracking has resumed at {$request->location_name}.";

                    if ($booking->vendor_id) {
                        $vendor = Vendor::find($booking->vendor_id);
                        if ($vendor && !empty($vendor->fcm_token)) {
                            $fcm = new FirebaseNotificationService();
                            $fcm->sendToDevice($vendor->fcm_token, $title, $body, null, [
                                'type' => 'tracking_resumed',
                                'booking_id' => (string) $booking->id,
                            ]);
                        }
                    }
                    Log::info("Tracking resumed: Booking Id {$bookingRef}, Driver: {$driverName}");
                } catch (\Exception $e) {
                    Log::error("Tracking resumed notification failed: " . $e->getMessage());
                }
            }

            // History is now handled by the distance-based insertion above.
            // The old duplicate trackings()->create() was removed because it saved
            // EVERY GPS ping without timestamp/order/filtering — causing noisy,
            // unordered timeline data that broke the frontend green line.

            // ── Auto-generate passed stops and substops ──
            // When driver moves, check if new stops should be created along the route.
            // Stops are permanent once created — only new ones are added.
            if (!$isGpsSpike && $isMoving) {
                try {
                    $pickupLat = (float) ($booking->pickup_lat ?? 0);
                    $pickupLng = (float) ($booking->pickup_lng ?? 0);

                    // Use vehicle start location as pickup if available (admin may set it)
                    if ($booking->vehicle_start_lat && $booking->vehicle_start_lng) {
                        $pickupLat = (float) $booking->vehicle_start_lat;
                        $pickupLng = (float) $booking->vehicle_start_lng;
                    }

                    $dropLat   = (float) ($booking->drop_lat ?? 0);
                    $dropLng   = (float) ($booking->drop_lng ?? 0);

                    // Compute total distance (haversine) — not stored in DB
                    $totalDist = 0;
                    if ($pickupLat && $pickupLng && $dropLat && $dropLng) {
                        $totalDist = round(self::haversineDistance($pickupLat, $pickupLng, $dropLat, $dropLng), 1);
                    }

                    $pickupName = $booking->vehicle_start_address ?? $booking->hatchery_location ?? '';
                    $dropName   = $booking->dropping_location ?? '';

                    if ($pickupLat && $pickupLng && $dropLat && $dropLng && $totalDist > 1) {
                        VehicleTrackingStop::autoGenerateStops(
                            bookingId: $booking->id,
                            driverLat: $lat,
                            driverLng: $lng,
                            pickupLat: $pickupLat,
                            pickupLng: $pickupLng,
                            dropLat: $dropLat,
                            dropLng: $dropLng,
                            totalDistanceKm: $totalDist,
                            pickupName: $pickupName,
                            dropName: $dropName,
                        );
                    }
                } catch (\Exception $e) {
                    Log::warning("STOP_GEN error booking={$booking->id}: {$e->getMessage()}");
                }
            }

            // Check if driver is approaching drop location (within 15km)
            $this->checkAndNotifyApproaching($booking, $request->lat, $request->lng);
        }

        return response()->json([
            'status' => true,
            'message' => 'Location updated',
            'affected_bookings' => $bookings->count(),
            'saved_to_db' => $shouldSave ?? false,
        ]);
    }

    /**
     * POST /api/driver/location/batch-update
     *
     * Accepts up to 200 GPS points that were queued locally on the device
     * while the internet was offline. Each point carries its original GPS
     * timestamp so spike-detection and reached_at ordering are correct.
     *
     * Why a separate endpoint instead of looping the single-point endpoint:
     *  - One HTTP request instead of up to 200, so reconnect is fast.
     *  - The single-point endpoint always uses now() for reached_at; batch
     *    uses the actual GPS capture time so the timeline stays in order.
     *  - No race condition with the live GPS stream marking points as sent.
     */
    public function batchUpdateLocations(Request $request)
    {
        $request->validate([
            'points'                    => 'required|array|min:1|max:200',
            'points.*.lat'              => 'required|numeric',
            'points.*.lng'              => 'required|numeric',
            'points.*.location_name'    => 'nullable|string|max:255',
            'points.*.gps_timestamp'    => 'required|string',
        ]);

        $driver = Auth::user();

        $bookings = Booking::where('driver_id', $driver->id)
            ->where('status', 4)
            ->get();

        if ($bookings->isEmpty()) {
            return response()->json([
                'status'  => false,
                'message' => 'No active journey found',
            ]);
        }

        // Sort all incoming points by their GPS timestamp so we process
        // them in chronological order regardless of send order.
        $points = collect($request->points)
            ->sortBy('gps_timestamp')
            ->values();

        $totalSaved = 0;

        foreach ($bookings as $booking) {
            // Cache max order once per booking — incremented in-memory
            // to avoid one MAX() query per point.
            $maxOrder = VehicleTracking::where('booking_id', $booking->id)
                ->max('order') ?? 0;

            // Track the last accepted position so we can update the
            // booking's driver_lat/lng after the loop.
            $latestLat          = null;
            $latestLng          = null;
            $latestLocationName = null;

            foreach ($points as $point) {
                $lat = round((float) $point['lat'], 6);
                $lng = round((float) $point['lng'], 6);

                // Parse GPS timestamp. Skip malformed entries.
                try {
                    $pointTime = Carbon::parse($point['gps_timestamp']);
                } catch (\Exception $e) {
                    continue;
                }

                // Skip future timestamps (device clock skew protection).
                if ($pointTime->isAfter(now()->addMinutes(5))) {
                    continue;
                }

                // Skip anything older than 24 hours (already irrelevant).
                if ($pointTime->isBefore(now()->subHours(24))) {
                    continue;
                }

                // ── GPS spike detection using actual GPS timestamps ──
                //
                // Anchor: the most recent tracking row whose reached_at
                // is at or before this point's GPS time. This keeps the
                // comparison time-coherent even when batch points
                // interleave with live-stream points already in the DB.
                $isGpsSpike = false;
                $anchorPoint = VehicleTracking::where('booking_id', $booking->id)
                    ->whereNotNull('reached_at')
                    ->whereNotNull('lat')
                    ->whereNotNull('lng')
                    ->where('reached_at', '<=', $pointTime)
                    ->latest('reached_at')
                    ->first();

                if ($anchorPoint) {
                    $distMeters = self::haversineDistance(
                        (float) $anchorPoint->lat, (float) $anchorPoint->lng,
                        $lat, $lng
                    ) * 1000;

                    // diffInSeconds(false) returns a signed value so
                    // out-of-order duplicates (secsSince < 0) are caught.
                    $secsSince = (int) Carbon::parse($anchorPoint->reached_at)
                        ->diffInSeconds($pointTime, false);

                    if ($secsSince <= 0) {
                        // Duplicate or out-of-order — skip silently.
                        continue;
                    }

                    $speedKmh = ($distMeters / $secsSince) * 3.6;

                    if ($speedKmh > 200) {
                        $isGpsSpike = true;
                    } elseif ($distMeters > 500 && $secsSince < 5) {
                        $isGpsSpike = true; // teleport
                    } elseif ($distMeters > 700 && $secsSince < 10) {
                        $isGpsSpike = true; // mid-range jump
                    }
                } elseif ($booking->vehicle_start_lat && $booking->vehicle_start_lng) {
                    // First point ever for this booking — sanity-check
                    // against the pickup location (same as single endpoint).
                    $distFromPickup = self::haversineDistance(
                        (float) $booking->vehicle_start_lat,
                        (float) $booking->vehicle_start_lng,
                        $lat, $lng
                    ) * 1000;

                    if ($distFromPickup > 1500) {
                        $isGpsSpike = true;
                    }
                }

                if ($isGpsSpike) {
                    continue;
                }

                // ── Distance / time gate — same thresholds as single endpoint ──
                $lastTracking = VehicleTracking::where('booking_id', $booking->id)
                    ->whereNotNull('reached_at')
                    ->where('reached_at', '<=', $pointTime)
                    ->latest('reached_at')
                    ->first();

                $shouldSave = false;

                if (!$lastTracking) {
                    $shouldSave = true;
                } else {
                    $distFromLast = self::haversineDistance(
                        (float) $lastTracking->lat, (float) $lastTracking->lng,
                        $lat, $lng
                    ) * 1000;

                    $secsSinceLast = (int) Carbon::parse($lastTracking->reached_at)
                        ->diffInSeconds($pointTime, false);

                    if ($secsSinceLast <= 0) {
                        continue; // duplicate
                    }

                    // Moved ≥ 25 m AND ≥ 10 s (normal driving)
                    if ($distFromLast >= 25 && $secsSinceLast >= 10) {
                        $shouldSave = true;
                    }
                    // Or moved ≥ 10 m after 2+ minutes (slow traffic / idle)
                    elseif ($distFromLast >= 10 && $secsSinceLast >= 120) {
                        $shouldSave = true;
                    }
                }

                if (!$shouldSave) {
                    continue;
                }

                // Mark any existing 'current' row that pre-dates this
                // point as 'completed' before inserting the new one.
                VehicleTracking::where('booking_id', $booking->id)
                    ->where('status', 'current')
                    ->where('reached_at', '<', $pointTime)
                    ->update(['status' => 'completed']);

                VehicleTracking::create([
                    'booking_id'    => $booking->id,
                    'location_name' => $point['location_name'] ?? null,
                    'lat'           => $lat,
                    'lng'           => $lng,
                    'title'         => $point['location_name'] ?? null,
                    'subtitle'      => '',
                    'status'        => 'current',
                    'reached_at'    => $pointTime,   // original GPS time
                    'order'         => ++$maxOrder,
                ]);

                $totalSaved++;
                $latestLat          = $lat;
                $latestLng          = $lng;
                $latestLocationName = $point['location_name'] ?? null;
            }

            // Update booking's live position to the last accepted batch
            // point. Always use now() for driver_location_updated_at so
            // the stale-driver alert knows the driver just reconnected.
            if ($latestLat !== null) {
                $booking->update([
                    'driver_lat'                 => $latestLat,
                    'driver_lng'                 => $latestLng,
                    'driver_location_name'       => $latestLocationName,
                    'driver_location_updated_at' => now(),
                    'last_tracking_alert_at'     => null,
                    'driver_inactive_reason'     => null,
                ]);
            }
        }

        return response()->json([
            'status'       => true,
            'message'      => 'Batch location update processed',
            'saved_points' => $totalSaved,
        ]);
    }

    public function updateDropStatus(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|integer|exists:bookings,id',
            'status' => 'required|integer|in:5,6', // 5=Delivered, 6=Failed
        ]);

        $driver = Auth::user();

        $booking = Booking::where('id', $request->booking_id)
            ->where('driver_id', $driver->id)
            ->whereIn('status', [4]) // Only allow update if In Journey
            ->first();

        if (!$booking) {
            return response()->json([
                'status' => false,
                'message' => 'Booking not found or cannot be updated'
            ], 400);
        }

        $updateData = ['status' => $request->status];

        if ($request->status == 5) {
            $updateData['delivered_at'] = now();
        } elseif ($request->status == 6) {
            $updateData['cancelled_at'] = now();
        }

        $booking->update($updateData);

        // Clear tracking alerts on delivery/cancellation
        TrackingAlert::where('booking_id', $booking->id)->delete();
        $booking->update(['driver_inactive_reason' => null]);

        $this->sendBookingNotification($booking->fresh(), $request->status);

        return response()->json([
            'status' => true,
            'message' => 'Drop status updated successfully',
            'booking_id' => $booking->id,
            'new_status' => $booking->status
        ]);
    }

    /**
     * Cleanup GPS tracking points after delivery — keep every 2nd point.
     *
     * Every 10th was too aggressive (90% loss). Every 2nd keeps ~50%
     * so a 1260-point journey retains ~630 points for history viewing.
     * The first and last points are always kept so pickup and delivery
     * positions are never lost.
     */
    private function cleanupTrackingPoints($bookingId)
    {
        try {
            $allIds = \DB::table('vehicle_trackings')
                ->where('booking_id', $bookingId)
                ->orderBy('id')
                ->pluck('id');

            if ($allIds->count() <= 100) return; // Don't cleanup if already small

            $keepIds = [];
            for ($i = 0; $i < $allIds->count(); $i += 2) {
                $keepIds[] = $allIds[$i];
            }
            // Always keep the first and last point
            $keepIds[] = $allIds->first();
            $keepIds[] = $allIds->last();
            $keepIds = array_unique($keepIds);

            \DB::table('vehicle_trackings')
                ->where('booking_id', $bookingId)
                ->whereNotIn('id', $keepIds)
                ->delete();

            \Log::info("Cleaned up tracking points for booking {$bookingId}: kept " . count($keepIds) . " of " . $allIds->count());
        } catch (\Exception $e) {
            \Log::error("Tracking cleanup failed for booking {$bookingId}: " . $e->getMessage());
        }
    }

    // =====================================================================
    // APPROACHING NOTIFICATION — sent to customer when driver is within 5km
    // =====================================================================

    private function checkAndNotifyApproaching(Booking $booking, float $driverLat, float $driverLng): void
    {
        // Skip if already notified or no drop coordinates
        if ($booking->approaching_notified_at) return;
        if (!$booking->drop_lat || !$booking->drop_lng) return;

        $distance = self::haversineDistance(
            $driverLat, $driverLng,
            (float) $booking->drop_lat, (float) $booking->drop_lng
        );

        if ($distance > 15.0) return; // Not within 15km yet

        try {
            $booking->update(['approaching_notified_at' => now()]);

            $farmer = Farmer::find($booking->farmer_id);
            if (!$farmer) return;

            $bookingRef = "#{$booking->id}";
            $title = "Vehicle Approaching! (Booking {$bookingRef})";
            $body = "Your vehicle is approximately " . round($distance, 1) . " km away. Please be ready for delivery.";

            // Save push notification record
            PushNotification::create([
                'title' => $title,
                'body' => $body,
                'type' => 'driver_approaching',
                'farmer_id' => $farmer->id,
                'data' => json_encode([
                    'type' => 'driver_approaching',
                    'booking_id' => (string) $booking->id,
                    'booking_uid' => $bookingRef,
                    'distance_km' => round($distance, 1),
                ]),
            ]);

            // Send FCM push notification
            if (!empty($farmer->fcm_token)) {
                $fcm = new FirebaseNotificationService();
                $fcm->sendToDevice($farmer->fcm_token, $title, $body, null, [
                    'type' => 'driver_approaching',
                    'booking_id' => (string) $booking->id,
                    'booking_uid' => $bookingRef,
                ]);
            }

            Log::info("Approaching notification sent: Booking Id {$bookingRef}, Distance: " . round($distance, 1) . " km");
        } catch (\Exception $e) {
            Log::error("Approaching notification failed for booking {$booking->id}: " . $e->getMessage());
        }
    }

    // =====================================================================
    // TRACKING ALERT — called by driver app when GPS/internet/tracking fails
    // =====================================================================

    /**
     * issue_type => the sentence the admin bell and the vendor push show.
     *
     * The driver app and this endpoint were written against DIFFERENT
     * vocabularies. The app sends no_internet / location_off /
     * location_not_sending / battery_low, none of which were listed here or in
     * the validation rule below — so every one of those alerts was rejected
     * 422 and never reached the admin bell. Only `gps_off` happened to match,
     * which is why GPS alerts worked and "internet off / phone switched off /
     * others" silently never appeared.
     *
     * Both vocabularies are accepted. Drivers already have the app installed,
     * so widening the SERVER fixes every device immediately; the app-side keys
     * cannot be changed retroactively.
     */
    private static $issueDescriptions = [
        // Keys this endpoint has always accepted.
        'gps_off' => 'Driver has turned off GPS/Location',
        'internet_off' => 'Driver internet connection is down',
        'permission_denied' => 'Driver location permissions are not properly enabled',
        'tracking_stopped' => 'Driver device has stopped sending location data',
        'tracking_restarted' => 'Tracking was interrupted but has been automatically restarted',

        // Keys the driver app actually sends (TrackingAlertService).
        'no_internet' => 'Driver internet connection is down',
        'location_off' => 'Driver location permissions are not properly enabled',
        'location_not_sending' => 'Driver device has stopped sending location data',
        'battery_low' => 'Driver phone battery is critically low — tracking may stop',
    ];

    /** Alerts that mean "tracking is healthy again", so the reason is cleared. */
    private static $resolvedIssueTypes = ['tracking_restarted'];

    public function trackingAlert(Request $request)
    {
        // Accept every key in the description map — see the note there about the
        // app/server vocabulary split. Deriving the rule from the map means the
        // two can never drift apart again.
        $request->validate([
            'issue_type' => 'required|string|in:' . implode(',', array_keys(self::$issueDescriptions)),
            'battery_level' => 'nullable|integer|min:0|max:100',
        ]);

        $driver = Auth::user();
        $issueType = $request->issue_type;

        $bookings = Booking::where('driver_id', $driver->id)
            ->where('status', 4)
            ->get();

        if ($bookings->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No active bookings found',
            ]);
        }

        // Map issue_type to readable reason for admin notification
        $reasonLabel = self::$issueDescriptions[$issueType] ?? 'Tracking issue';
        $isRestarted = in_array($issueType, self::$resolvedIssueTypes, true);
        if ($isRestarted) {
            $reasonLabel = null; // Clear reason when tracking resumes
        }

        $alertsSent = 0;

        foreach ($bookings as $booking) {
            // Save to tracking_alerts history
            TrackingAlert::create([
                'booking_id' => $booking->id,
                'driver_id' => $driver->id,
                'vendor_id' => $booking->vendor_id,
                'reason' => $isRestarted
                    ? 'Driver is back online - Tracking resumed'
                    : $reasonLabel,
                'location_name' => $booking->driver_location_name,
            ]);

            // Save reason for admin notification bell
            $booking->update(['driver_inactive_reason' => $reasonLabel]);

            // Rate limit vendor notification: max 1 per 10 minutes per booking
            if ($booking->last_tracking_alert_at) {
                $lastAlert = Carbon::parse($booking->last_tracking_alert_at);
                if ($lastAlert->diffInMinutes(now()) < 10) {
                    continue;
                }
            }

            $this->sendTrackingAlertNotifications(
                $booking,
                $driver,
                $issueType
            );

            $booking->update(['last_tracking_alert_at' => now()]);
            $alertsSent++;
        }

        return response()->json([
            'status' => true,
            'message' => $alertsSent > 0 ? 'Tracking alerts sent' : 'Alerts already sent recently',
            'alerts_sent' => $alertsSent,
        ]);
    }

    /**
     * Send tracking alert notifications to vendor and admin
     */
    public static function sendTrackingAlertNotifications(Booking $booking, $driver, string $issueType)
    {
        $issueDesc = self::$issueDescriptions[$issueType] ?? 'Tracking interruption detected';
        $bookingRef = "#{$booking->id}";
        $driverName = $driver->name ?? 'Driver';
        $lastUpdate = $booking->updated_at ? $booking->updated_at->format('h:i A') : 'Unknown';

        $title = "Tracking Alert - Booking Id {$bookingRef}";
        $body = "{$driverName}: {$issueDesc}. Last location update: {$lastUpdate}";

        // Send FCM push notification to Vendor (no SMS)
        try {
            if ($booking->vendor_id) {
                $vendor = Vendor::find($booking->vendor_id);
                if ($vendor && !empty($vendor->fcm_token)) {
                    $fcm = new FirebaseNotificationService();
                    $fcm->sendToDevice($vendor->fcm_token, $title, $body, null, [
                        'type' => 'tracking_alert',
                        'booking_id' => (string) $booking->id,
                        'booking_uid' => $bookingRef,
                        'issue_type' => $issueType,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error("Vendor tracking alert failed for booking {$booking->id}: " . $e->getMessage());
        }

        Log::info("Tracking alert sent: Booking Id {$bookingRef}, Issue: {$issueType}, Driver: {$driverName}");
    }

    /**
     * Static method for scheduled stale tracking detection with escalation.
     * Called by the CheckStaleTracking command every 5 minutes.
     *
     * Escalation levels:
     *   - 5 min stale  → FCM to Vendor ("Tracking lost")
     *   - 15 min stale → CRITICAL FCM to Vendor with urgency
     *   - Repeated every 10 min while stale
     *   - Auto-cleared when driver resumes (in updateDriverLocation)
     */
    public static function checkAndAlertStaleBookings()
    {
        // Find all active bookings (status=4) where driver location hasn't updated in 5+ minutes
        // Uses driver_location_updated_at (not updated_at which changes on any field update)
        $staleBookings = Booking::where('status', 4)
            ->where(function ($q) {
                $q->where('driver_location_updated_at', '<', now()->subMinutes(5))
                    ->orWhereNull('driver_location_updated_at');
            })
            ->where(function ($q) {
                $q->whereNull('last_tracking_alert_at')
                    ->orWhere('last_tracking_alert_at', '<', now()->subMinutes(10));
            })
            ->get();

        $alertCount = 0;

        foreach ($staleBookings as $booking) {
            $driver = Driver::find($booking->driver_id);
            if (!$driver) continue;

            // Calculate how long the tracking has been stale
            $staleSince = $booking->driver_location_updated_at
                ? Carbon::parse($booking->driver_location_updated_at)
                : Carbon::parse($booking->in_progress_at ?? $booking->updated_at);
            $staleMinutes = $staleSince->diffInMinutes(now());

            if ($staleMinutes >= 15) {
                // CRITICAL (15+ min): Send urgent notification to vendor
                $bookingRef = "#{$booking->id}";
                $driverName = $driver->name ?? 'Driver';
                $lastLocation = $booking->driver_location_name ?? 'Unknown';
                $lastTime = $staleSince->format('h:i A');

                $title = "CRITICAL: Tracking Lost - Booking Id {$bookingRef}";
                $body = "{$driverName} has not sent location for {$staleMinutes} min. "
                    . "Last seen: {$lastLocation} at {$lastTime}. "
                    . "Phone may be off or app was killed.";

                try {
                    if ($booking->vendor_id) {
                        $vendor = Vendor::find($booking->vendor_id);
                        if ($vendor && !empty($vendor->fcm_token)) {
                            $fcm = new FirebaseNotificationService();
                            $fcm->sendToDevice($vendor->fcm_token, $title, $body, null, [
                                'type' => 'tracking_critical',
                                'booking_id' => (string) $booking->id,
                                'stale_minutes' => (string) $staleMinutes,
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("Critical tracking alert failed for booking {$booking->id}: " . $e->getMessage());
                }

                Log::warning("CRITICAL stale tracking: Booking Id {$bookingRef}, "
                    . "Driver: {$driverName}, Stale: {$staleMinutes} min");
            } else {
                // Normal (5+ min): Standard vendor notification
                self::sendTrackingAlertNotifications(
                    $booking,
                    $driver,
                    'tracking_stopped'
                );
            }

            // Save reason for admin notification bell (only if no Flutter-reported reason exists)
            if (!$booking->driver_inactive_reason) {
                $reason = $staleMinutes >= 15
                    ? "Phone off or app killed ({$staleMinutes}m)"
                    : "Location not updating ({$staleMinutes}m)";
                $booking->update(['last_tracking_alert_at' => now(), 'driver_inactive_reason' => $reason]);

                // Save to tracking_alerts history
                TrackingAlert::create([
                    'booking_id' => $booking->id,
                    'driver_id' => $driver->id,
                    'vendor_id' => $booking->vendor_id,
                    'reason' => $reason,
                    'location_name' => $booking->driver_location_name,
                ]);
            } else {
                $booking->update(['last_tracking_alert_at' => now()]);
            }
            $alertCount++;
        }

        return $alertCount;
    }

    /**
     * GET /api/driver/tracking-alert-status
     * Returns current tracking alert status for driver's active bookings
     */
    public function trackingAlertStatus()
    {
        $driver = Auth::user();

        $bookings = Booking::where('driver_id', $driver->id)
            ->where('status', 4)
            ->get();

        $activeAlert = $bookings->firstWhere('driver_inactive_reason', '!=', null);

        if (!$activeAlert) {
            return response()->json([
                'status' => true,
                'alert' => null,
            ]);
        }

        return response()->json([
            'status' => true,
            'alert' => [
                'driver_id' => $driver->id,
                'reason' => $activeAlert->driver_inactive_reason,
                'driver_name' => $driver->name ?? 'N/A',
                'driver_mobile' => $driver->mobile ?? 'N/A',
                'driver_location_name' => $activeAlert->driver_location_name ?? 'Unknown',
            ],
        ]);
    }

}
