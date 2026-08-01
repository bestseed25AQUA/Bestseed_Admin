<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleTrackingStop extends Model
{
    protected $fillable = [
        'booking_id',
        'name',
        'lat',
        'lng',
        'type',
        'parent_stop_id',
        'is_key_stop',
        'key_type',
        'order',
        'dist_from_pickup_km',
        'passed_at'
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'is_key_stop' => 'boolean',
        'dist_from_pickup_km' => 'float',
        'passed_at' => 'datetime'
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function parentStop()
    {
        return $this->belongsTo(self::class, 'parent_stop_id');
    }

    public function subStops()
    {
        return $this->hasMany(self::class, 'parent_stop_id')->orderBy('order');
    }

    /**
     * Get all passed main stops for a booking (with sub-stops).
     */
    public static function getPassedStops(int $bookingId): array
    {
        $mainStops = self::where('booking_id', $bookingId)
            ->where('type', 'main')
            ->whereNotNull('passed_at')
            ->orderBy('order')
            ->get();

        // Filter duplicates: skip stops within 1km of an already-added stop
        // with similar name (handles Ayyappa/Ayappa spelling variants)
        $filtered = [];
        foreach ($mainStops as $stop) {
            $isDuplicate = false;
            foreach ($filtered as $existing) {
                $dist = self::haversineKm(
                    (float) $existing->lat, (float) $existing->lng,
                    (float) $stop->lat, (float) $stop->lng
                );
                if ($dist < 1) {
                    $isDuplicate = true;
                    break;
                }
            }
            if (!$isDuplicate) {
                $filtered[] = $stop;
            }
        }

        return collect($filtered)->map(function ($stop) {
            return [
                'name' => $stop->name,
                'lat' => $stop->lat,
                'lng' => $stop->lng,
                'time' => $stop->passed_at->format('g:i A'),
                'date' => $stop->passed_at->format('d/m/Y'),
                'is_key_stop' => $stop->is_key_stop,
                'key_type' => $stop->key_type,
                'order' => $stop->order,
                'dist_from_pickup_km' => $stop->dist_from_pickup_km,
                'sub_stops' => $stop->subStops->filter(fn($s) => $s->passed_at !== null)->map(function ($sub) {
                    return [
                        'name' => $sub->name,
                        'lat' => $sub->lat,
                        'lng' => $sub->lng,
                        'time' => $sub->passed_at->format('g:i A'),
                        'date' => $sub->passed_at->format('d/m/Y'),
                        'order' => $sub->order,
                    ];
                })->values()->all(),
            ];
        })->all();
    }

    /**
     * Get key stops (delivery drops) for a booking — always shown, never disappear.
     */
    public static function getKeyStops(int $bookingId): array
    {
        return self::where('booking_id', $bookingId)
            ->where('is_key_stop', true)
            ->orderBy('order')
            ->get()
            ->map(function ($stop) {
                return [
                    'name' => $stop->name,
                    'lat' => $stop->lat,
                    'lng' => $stop->lng,
                    'key_type' => $stop->key_type,
                    'status' => $stop->passed_at ? 'delivered' : 'upcoming',
                    'time' => $stop->passed_at?->format('g:i A'),
                    'order' => $stop->order,
                ];
            })->all();
    }

    /**
     * Check driver's current lat/lng against upcoming stops and mark as passed.
     * Uses haversine distance — stop is "passed" when driver is closer to
     * pickup→drop line than the stop, and driver's distance from pickup > stop's distance.
     */
    public static function updatePassedStops(int $bookingId, float $driverLat, float $driverLng, float $pickupLat, float $pickupLng): int
    {
        $driverDistFromPickup = self::haversineKm($pickupLat, $pickupLng, $driverLat, $driverLng);

        $updated = 0;
        $unpassed = self::where('booking_id', $bookingId)
            ->whereNull('passed_at')
            ->where('dist_from_pickup_km', '<=', $driverDistFromPickup + 2) // 2 km buffer
            ->orderBy('order')
            ->get();

        foreach ($unpassed as $stop) {
            // Driver has passed this stop if driver's distance from pickup > stop's distance
            if ($driverDistFromPickup >= $stop->dist_from_pickup_km - 1) { // 1 km tolerance
                $stop->passed_at = now();
                $stop->save();
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Save a new stop to DB (when driver passes a client-generated stop).
     */
    public static function savePassedStop(
        int $bookingId,
        string $name,
        float $lat,
        float $lng,
        string $type = 'main',
        ?int $parentStopId = null,
        bool $isKeyStop = false,
        ?string $keyType = null,
        int $order = 0,
        float $distFromPickupKm = 0
    ): self {
        // Never overwrite existing stops — only create if not exists
        return self::firstOrCreate(
            [
                'booking_id' => $bookingId,
                'name' => $name,
                'type' => $type,
            ],
            [
                'lat' => $lat,
                'lng' => $lng,
                'parent_stop_id' => $parentStopId,
                'is_key_stop' => $isKeyStop,
                'key_type' => $keyType,
                'order' => $order,
                'dist_from_pickup_km' => $distFromPickupKm,
                'passed_at' => now(),
            ]
        );
    }

    /**
     * Auto-generate stops and substops as the driver moves.
     * Called from updateDriverLocation on every accepted tracking point.
     *
     * Logic:
     * 1. Calculate target stop distances (evenly spaced along total route distance)
     * 2. When driver's distance-from-pickup crosses a target → reverse-geocode
     *    the driver's actual lat/lng and save as a main stop
     * 3. Between consecutive main stops, generate substops at intermediate distances
     * 4. Once saved, stops never change. Only new ones are added.
     * 5. City/state names from pickup/drop address are excluded.
     */
    public static function autoGenerateStops(
        int $bookingId,
        float $driverLat,
        float $driverLng,
        float $pickupLat,
        float $pickupLng,
        float $dropLat,
        float $dropLng,
        float $totalDistanceKm,
        string $pickupName = '',
        string $dropName = ''
    ): void {
        if ($totalDistanceKm <= 0) return;

        // Use FRACTION-based comparison (not raw distance) because
        // haversine(pickup→driver) << road distance. Normalizing both
        // to the same 0-1 scale avoids the mismatch.
        $straightLineDistKm = self::haversineKm($pickupLat, $pickupLng, $dropLat, $dropLng);
        if ($straightLineDistKm <= 0) return;

        $driverDistFromPickup = self::haversineKm($pickupLat, $pickupLng, $driverLat, $driverLng);
        $driverFraction = min($driverDistFromPickup / $straightLineDistKm, 1.0);

        // Build exclude set from pickup/drop address parts
        $excludeNames = ['india', 'in'];
        // Names that ALWAYS get filtered (even on short routes)
        $alwaysExclude = [];
        foreach ([$pickupName, $dropName] as $addr) {
            foreach (explode(',', $addr) as $part) {
                $p = strtolower(trim($part));
                if (!empty($p)) {
                    $excludeNames[] = $p;
                    $alwaysExclude[] = $p;
                }
            }
        }

        // Calculate target main stop fractions (evenly spaced 0→1)
        $stopCount = self::recommendedStopCount($totalDistanceKm);
        $interval = $totalDistanceKm / ($stopCount + 1);

        // Get existing main stops for this booking (to avoid duplicates)
        $existingMainStops = self::where('booking_id', $bookingId)
            ->where('type', 'main')
            ->pluck('dist_from_pickup_km', 'name')
            ->toArray();

        $existingSubStops = self::where('booking_id', $bookingId)
            ->where('type', 'sub')
            ->pluck('name')
            ->map(fn($n) => strtolower(trim($n)))
            ->toArray();

        for ($s = 1; $s <= $stopCount; $s++) {
            $targetFraction = $s / ($stopCount + 1);
            $targetDistKm = $interval * $s;

            // Skip if driver hasn't reached this fraction yet
            if ($driverFraction < $targetFraction - 0.02) continue;

            // Check if a main stop already exists near this target distance (within 5% of total)
            $alreadyExists = false;
            $tolerance = $totalDistanceKm * 0.03;
            foreach ($existingMainStops as $name => $dist) {
                if (abs($dist - $targetDistKm) < $tolerance) {
                    $alreadyExists = true;
                    break;
                }
            }
            if ($alreadyExists) continue;

            // Interpolate position along straight line at this fraction
            $stopLat = $pickupLat + ($dropLat - $pickupLat) * $targetFraction;
            $stopLng = $pickupLng + ($dropLng - $pickupLng) * $targetFraction;

            // Always use driver's actual position — it's on the real road.
            // Straight-line interpolation can land in wrong areas.
            $stopLat = $driverLat;
            $stopLng = $driverLng;

            // Reverse geocode
            $name = self::reverseGeocodeLocation($stopLat, $stopLng, $totalDistanceKm);
            if (!$name) continue;

            // Always filter pickup/drop names. Other city names only for long routes.
            $nameLower = strtolower(trim($name));
            if (in_array($nameLower, $alwaysExclude)) continue;
            if ($totalDistanceKm >= 10 && in_array($nameLower, $excludeNames)) continue;

            // Check duplicate — by name OR by proximity (within 500m of existing stop)
            if (isset($existingMainStops[$name])) continue;
            $tooClose = false;
            foreach ($existingMainStops as $existName => $existDist) {
                if (abs($existDist - $targetDistKm) < 0.5) { $tooClose = true; break; }
            }
            // Also check DB for proximity
            $nearbyExists = self::where('booking_id', $bookingId)
                ->where('type', 'main')
                ->get()
                ->contains(function ($s) use ($stopLat, $stopLng) {
                    return self::haversineKm((float)$s->lat, (float)$s->lng, $stopLat, $stopLng) < 0.5;
                });
            if ($tooClose || $nearbyExists) continue;

            // Save main stop
            $maxOrder = self::where('booking_id', $bookingId)
                ->where('type', 'main')
                ->max('order') ?? -1;

            self::create([
                'booking_id' => $bookingId,
                'name' => $name,
                'lat' => round($stopLat, 6),
                'lng' => round($stopLng, 6),
                'type' => 'main',
                'order' => $maxOrder + 1,
                'dist_from_pickup_km' => round($targetDistKm, 2),
                'passed_at' => now(),
            ]);

            $existingMainStops[$name] = $targetDistKm;
            \Log::info("STOP_GEN booking={$bookingId} main stop \"{$name}\" at {$targetDistKm}km");

            // ── Sync stop to sibling bookings (same driver, shared path) ──
            self::syncStopToSiblingBookings(
                $bookingId, $name, round($stopLat, 6), round($stopLng, 6),
                $pickupLat, $pickupLng, $driverLat, $driverLng
            );

            // Generate substops for the segment ending at this stop
            self::generateSubstopsForSegment(
                $bookingId, $s, $interval, $targetDistKm,
                $pickupLat, $pickupLng, $dropLat, $dropLng,
                $totalDistanceKm, $excludeNames, $existingSubStops
            );
        }

        // ── Check route waypoints (priority booking drops) ──
        // Save priority booking drops as passed stops when driver is near them.
        // Includes active (4) and delivered (5) bookings with lower priority.
        $booking = \App\Models\Booking::find($bookingId);
        if ($booking && $booking->driver_id) {
            $query = \App\Models\Booking::where('driver_id', $booking->driver_id)
                ->whereIn('status', [4, 5])
                ->where('id', '!=', $bookingId);

            // Only bookings with lower or equal priority (they come before this one)
            if ($booking->priority !== null) {
                $query->where('priority', '<', $booking->priority);
            }

            // Same day filter with fallback
            if ($booking->vehicle_started_date) {
                $query->whereDate('vehicle_started_date', $booking->vehicle_started_date);
            } elseif ($booking->packing_date) {
                $query->whereDate('packing_date', $booking->packing_date);
            }

            $waypointBookings = $query->orderBy('priority')->get();

            foreach ($waypointBookings as $wpBooking) {
                $wpLat = (float) ($wpBooking->drop_lat ?? 0);
                $wpLng = (float) ($wpBooking->drop_lng ?? 0);
                if ($wpLat == 0 || $wpLng == 0) continue;

                $wpName = $wpBooking->dropping_location ?? '';
                $wpNameShort = trim(explode(',', $wpName)[0] ?? 'Stop');
                if (empty($wpNameShort)) continue;

                // Skip if name matches pickup/drop address
                if (in_array(strtolower($wpNameShort), $alwaysExclude)) continue;
                if (isset($existingMainStops[$wpNameShort])) continue;
                // Skip if too close to existing stop (prevents spelling-variant duplicates)
                $wpNearbyExists = self::where('booking_id', $bookingId)
                    ->where('type', 'main')
                    ->get()
                    ->contains(function ($s) use ($wpLat, $wpLng) {
                        return self::haversineKm((float)$s->lat, (float)$s->lng, $wpLat, $wpLng) < 0.5;
                    });
                if ($wpNearbyExists) continue;

                // Waypoint must be within 2x the route distance
                $wpDistFromPickup = self::haversineKm($pickupLat, $pickupLng, $wpLat, $wpLng);
                if ($wpDistFromPickup > $totalDistanceKm * 2) continue;

                // Driver must be near this waypoint (within 1km)
                $driverToWp = self::haversineKm($driverLat, $driverLng, $wpLat, $wpLng);
                if ($driverToWp > 1) continue;

                $maxOrder = self::where('booking_id', $bookingId)
                    ->where('type', 'main')
                    ->max('order') ?? -1;

                self::firstOrCreate(
                    ['booking_id' => $bookingId, 'name' => $wpNameShort, 'type' => 'main'],
                    [
                        'lat' => round($wpLat, 6),
                        'lng' => round($wpLng, 6),
                        'order' => $maxOrder + 1,
                        'dist_from_pickup_km' => round($wpDistFromPickup, 2),
                        'passed_at' => now(),
                        'is_key_stop' => true,
                    ]
                );
                $existingMainStops[$wpNameShort] = $wpDistFromPickup;
                \Log::info("STOP_GEN booking={$bookingId} waypoint \"{$wpNameShort}\" saved (driver {$driverToWp}km away)");
            }
        }
    }

    /**
     * Generate substops between main stop (s-1) and main stop (s).
     */
    private static function generateSubstopsForSegment(
        int $bookingId,
        int $stopIndex,
        float $interval,
        float $stopDistKm,
        float $pickupLat, float $pickupLng,
        float $dropLat, float $dropLng,
        float $totalDistKm,
        array $excludeNames,
        array &$existingSubStops
    ): void {
        $segmentStartKm = $stopDistKm - $interval;
        $segmentEndKm = $stopDistKm;
        $segmentLenKm = $segmentEndKm - $segmentStartKm;

        $subCount = self::recommendedSubStopCount($segmentLenKm);
        if ($subCount <= 0) return;

        // Find parent stop
        $parentStop = self::where('booking_id', $bookingId)
            ->where('type', 'main')
            ->where('dist_from_pickup_km', '>=', $stopDistKm - 1)
            ->where('dist_from_pickup_km', '<=', $stopDistKm + 1)
            ->first();

        $parentStopId = $parentStop?->id;

        for ($sub = 1; $sub <= $subCount; $sub++) {
            $subFrac = $sub / ($subCount + 1);
            $subDistKm = $segmentStartKm + ($segmentLenKm * $subFrac);
            $fraction = $subDistKm / $totalDistKm;

            $subLat = $pickupLat + ($dropLat - $pickupLat) * $fraction;
            $subLng = $pickupLng + ($dropLng - $pickupLng) * $fraction;

            $subName = self::reverseGeocodeLocation($subLat, $subLng, $segmentLenKm);
            if (!$subName) continue;

            $subLower = strtolower(trim($subName));
            // Always filter pickup/drop names for substops too
            if (in_array($subLower, $excludeNames)) continue;
            if (in_array($subLower, $existingSubStops)) continue;

            self::create([
                'booking_id' => $bookingId,
                'name' => $subName,
                'lat' => round($subLat, 6),
                'lng' => round($subLng, 6),
                'type' => 'sub',
                'parent_stop_id' => $parentStopId,
                'order' => $sub,
                'dist_from_pickup_km' => round($subDistKm, 2),
                'passed_at' => now(),
            ]);

            $existingSubStops[] = $subLower;
            \Log::info("STOP_GEN booking={$bookingId}   substop \"{$subName}\" at {$subDistKm}km");
        }
    }

    /**
     * Copy a stop to all sibling bookings (same driver, same day, active).
     * Only copies if the stop is on the shared path (between pickup and driver).
     */
    private static function syncStopToSiblingBookings(
        int $sourceBookingId,
        string $stopName,
        float $stopLat,
        float $stopLng,
        float $pickupLat,
        float $pickupLng,
        float $driverLat,
        float $driverLng
    ): void {
        try {
            $sourceBooking = \App\Models\Booking::find($sourceBookingId);
            if (!$sourceBooking || !$sourceBooking->driver_id) return;

            $siblings = \App\Models\Booking::where('driver_id', $sourceBooking->driver_id)
                ->where('status', 4)
                ->where('id', '!=', $sourceBookingId)
                ->get();

            $stopDistFromPickup = self::haversineKm($pickupLat, $pickupLng, $stopLat, $stopLng);

            foreach ($siblings as $sibling) {
                // Check if sibling has same pickup (shared start)
                $sibPickupLat = (float) ($sibling->vehicle_start_lat ?? $sibling->pickup_lat ?? 0);
                $sibPickupLng = (float) ($sibling->vehicle_start_lng ?? $sibling->pickup_lng ?? 0);
                if ($sibPickupLat == 0 || $sibPickupLng == 0) continue;

                $pickupDist = self::haversineKm($pickupLat, $pickupLng, $sibPickupLat, $sibPickupLng);
                if ($pickupDist > 1) continue; // Different pickup — not shared path

                // Stop must be between pickup and driver (on shared path)
                $driverDistFromPickup = self::haversineKm($pickupLat, $pickupLng, $driverLat, $driverLng);
                if ($stopDistFromPickup > $driverDistFromPickup + 0.5) continue;

                // Check if already exists in sibling (by name or proximity)
                $exists = self::where('booking_id', $sibling->id)
                    ->where('type', 'main')
                    ->get()
                    ->contains(function ($s) use ($stopName, $stopLat, $stopLng) {
                        if (strtolower($s->name) === strtolower($stopName)) return true;
                        return self::haversineKm((float)$s->lat, (float)$s->lng, $stopLat, $stopLng) < 0.5;
                    });
                if ($exists) continue;

                $maxOrder = self::where('booking_id', $sibling->id)
                    ->where('type', 'main')
                    ->max('order') ?? -1;

                self::create([
                    'booking_id' => $sibling->id,
                    'name' => $stopName,
                    'lat' => $stopLat,
                    'lng' => $stopLng,
                    'type' => 'main',
                    'order' => $maxOrder + 1,
                    'dist_from_pickup_km' => round($stopDistFromPickup, 2),
                    'passed_at' => now(),
                ]);

                \Log::info("STOP_SYNC booking={$sibling->id} ← \"{$stopName}\" from booking={$sourceBookingId}");
            }
        } catch (\Exception $e) {
            \Log::warning("STOP_SYNC error: {$e->getMessage()}");
        }
    }

    /**
     * Reverse geocode a lat/lng to a locality name.
     */
    private static function reverseGeocodeLocation(float $lat, float $lng, float $routeDistKm): ?string
    {
        try {
            // Reads from config/services.php → env('GOOGLE_MAPS_API_KEY').
            // (Previously used the wrong path 'services.google.maps_key' with
            // a hardcoded fallback — the fallback masked the typo AND leaked
            // a real key. Config path corrected here.)
            $apiKey = config('services.google.maps_api_key');
            if (empty($apiKey)) {
                return null;
            }
            $resultType = $routeDistKm > 100 ? 'locality' : 'sublocality|locality';
            $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$lat},{$lng}&key={$apiKey}&result_type={$resultType}";

            $response = \Illuminate\Support\Facades\Http::timeout(5)->get($url);
            if (!$response->ok()) return null;

            $data = $response->json();
            if (($data['status'] ?? '') !== 'OK') return null;
            $results = $data['results'] ?? [];
            if (empty($results)) return null;

            // Extract locality or sublocality name
            foreach ($results[0]['address_components'] ?? [] as $component) {
                $types = $component['types'] ?? [];
                if (in_array('sublocality_level_1', $types) || in_array('locality', $types)) {
                    return $component['long_name'] ?? null;
                }
            }

            // Fallback: first part of formatted address
            $formatted = $results[0]['formatted_address'] ?? null;
            return $formatted ? explode(',', $formatted)[0] : null;
        } catch (\Exception $e) {
            \Log::warning("STOP_GEN geocode failed: {$e->getMessage()}");
            return null;
        }
    }

    private static function recommendedStopCount(float $km): int
    {
        if ($km <= 0.5) return 0;
        if ($km <= 2) return 1;
        if ($km <= 5) return 2;
        if ($km <= 10) return 3;
        if ($km <= 20) return 4;
        if ($km <= 50) return 5;
        if ($km <= 100) return 7;
        if ($km <= 200) return 9;
        if ($km <= 500) return 12;
        if ($km <= 1000) return 15;
        return 18;
    }

    private static function recommendedSubStopCount(float $gapKm): int
    {
        // Surgical low-end change only: short city segments (2–5 km) now yield
        // ONE sub-stop instead of zero, so short routes like Kavuri Hills →
        // Secunderabad or the equivalent ~2–4 km uniform segments record an
        // intermediate stop (e.g. Yousufguda) instead of nothing.
        //
        // Segments < 2 km stay at 0 — too short to contain a distinct locality,
        // and the straight-line sampling there tends to land off-road or
        // duplicate the main-stop name.
        //
        // All bands >= 5 km are intentionally LEFT UNCHANGED, so long
        // inter-city bookings keep their exact existing sub-stop density and
        // geocode cost (their uniform segment interval is always >= 5 km).
        if ($gapKm < 2) return 0;
        if ($gapKm < 5) return 1;   // NEW: was 0
        if ($gapKm < 15) return 1;
        if ($gapKm < 40) return 2;
        if ($gapKm < 80) return 3;
        return 4;
    }

    private static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371; // Earth radius in km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $R * $c;
    }
}
