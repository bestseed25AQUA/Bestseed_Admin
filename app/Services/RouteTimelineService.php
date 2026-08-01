<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * RouteTimelineService
 *
 * Generates intermediate timeline stops for a booking route server-side,
 * caches the result for 24 hours, and returns it to all three apps
 * (customer, employee/vendor, admin web) so they always display
 * the exact same stop names in the location timeline.
 *
 * Algorithm mirrors Flutter's GoogleMapsService.generateSubStops:
 *   1. Fetch high-resolution route via Google Directions API (step polylines)
 *   2. Compute stop count from journey duration (same formula as Dart)
 *   3. Scale candidate count for long routes (same thresholds as Dart)
 *   4. Sample candidate points evenly along the polyline
 *   5. Reverse-geocode all candidates concurrently via Http::pool()
 *   6. Deduplicate by first place-name component (same as Dart)
 *   7. Thin to targetCount with even stride (same as Dart)
 */
class RouteTimelineService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.google.maps_api_key', '');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return cached intermediate stops for the booking, generating them on
     * first call. Uses admin-set pickup (vehicle_start_lat/lng) as origin so
     * the route is identical regardless of which app requests it.
     *
     * @return array<int, array{name:string, lat:float, lng:float, dist_fraction:float, is_key_stop:false}>
     */
    public function getIntermediateStops(Booking $booking): array
    {
        return $this->getMeta($booking)['stops'] ?? [];
    }

    /**
     * Return the total route duration in seconds (from cache, no extra API call).
     * Returns 0 when the route hasn't been generated yet or if inputs are missing.
     */
    public function getTotalDurationSeconds(Booking $booking): int
    {
        return (int) ($this->getMeta($booking)['total_duration_seconds'] ?? 0);
    }

    /**
     * Return the full cached meta: { stops, total_duration_seconds }.
     * Generates on first call.  Cached for 30 days under key route_stops_v5:.
     *
     * IMPORTANT: empty/failed results are NOT cached so transient API errors
     * (Google quota, network blip, missing coords at journey-start) are retried
     * on the next request instead of being frozen for 30 days.
     */
    public function getMeta(Booking $booking): array
    {
        if (empty($this->apiKey)) return ['stops' => [], 'total_duration_seconds' => 0];

        // Need *some* valid origin + a destination. Admin's vehicle_start_*
        // is preferred but optional — when missing, the route is built from
        // whatever pickup signal is available (booking pickup, first
        // tracking row, or current driver position). Without this fallback
        // a booking whose admin never set vehicle_start_lat/lng would
        // return empty stops forever — which was the "no next locations"
        // bug for Priority 2 / 3 customers on shared vehicle trips.
        if (!$booking->drop_lat || !$booking->drop_lng) {
            return ['stops' => [], 'total_duration_seconds' => 0];
        }
        if ($this->resolveOriginCoords($booking) === null) {
            return ['stops' => [], 'total_duration_seconds' => 0];
        }

        // Bumped to v7 — invalidates v6 entries that included the
        // destination short name as an auto stop (e.g. "Kokapet" showing
        // up both as an intermediate stop and as the destination). v7
        // pre-seeds the destination name in the dedup set.
        $cacheKey = 'route_stops_v7:' . $booking->id;

        // Return cached result only when it actually contains stops.
        // A previously cached empty result (from a transient failure) is treated
        // as a cache miss so generation is retried on the next request.
        $cached = Cache::get($cacheKey);
        if ($cached !== null && !empty($cached['stops'])) {
            return $cached;
        }

        $result = $this->generate($booking);

        // Normalise — generate() may return a bare [] on internal early-returns.
        if (empty($result) || !isset($result['stops'])) {
            $result = ['stops' => [], 'total_duration_seconds' => 0];
        }

        // Only persist to cache when we have real stops.
        // Failures stay uncached so the next request retries automatically.
        if (!empty($result['stops'])) {
            Cache::put($cacheKey, $result, 30 * 86400);
        }

        return $result;
    }

    /**
     * Return intermediate stops enriched with per-stop ETAs and, for already-passed
     * stops, an accurate `passed_at` ISO timestamp.
     *
     * Key fix over the old VehicleController version:
     *   - Driver fraction is computed from GPS position (nearest stop to driver's
     *     current lat/lng) instead of time-elapsed / totalDuration.  The old
     *     approach was wrong when the driver travelled faster than the Google Maps
     *     estimate: e.g. covering 33 % of a 19-hour route in 1.5 hours yields a
     *     time-fraction of only 0.076, so stops at 0.27–0.33 were incorrectly
     *     treated as "future" and shown as 6:31 PM instead of ~2:28 PM.
     *   - Passed stops use interpolation: passedAt = startTime + (f/driverF)*elapsed
     *   - A `passed_at` ISO field is added so Flutter can parse and display it
     *     directly without any client-side computation.
     *
     * @param  array  $driverLocation  Array with keys: lat, lng, updated_at, driver_status
     * @return array
     */
    public function buildAutoTimelineWithETAs(Booking $booking, array $driverLocation): array
    {
        $stops         = $this->getIntermediateStops($booking);
        $totalDuration = $this->getTotalDurationSeconds($booking);

        if (empty($stops) || !$booking->in_progress_at) {
            return $stops;
        }

        $now          = Carbon::now();
        $driverLat    = (float) ($driverLocation['lat']  ?? 0);
        $driverLng    = (float) ($driverLocation['lng']  ?? 0);
        $driverTime   = !empty($driverLocation['updated_at'])
            ? Carbon::parse($driverLocation['updated_at'])
            : $now;
        $driverStatus = $driverLocation['driver_status'] ?? 'moving';
        $anchorTime   = in_array($driverStatus, ['idle', 'stopped']) ? $now : $driverTime;
        $startTime    = Carbon::parse($booking->in_progress_at);

        // ── Load actual GPS breadcrumbs (vehicle_trackings) ───────────────────
        // These give exact times for when the driver was near each stop.
        //
        // Important: a booking can accumulate breadcrumbs across many sessions
        // (reroutes, simulator re-runs, days-old test data). The customer
        // cares about THIS trip's pass times, not every historical pass.
        //
        // Strategy: find the latest breadcrumb, then keep only breadcrumbs
        // within a 6-hour window before it. That isolates the active trip
        // session and excludes stale data from previous runs of the same
        // booking. Stops with no breadcrumb in that window are treated as
        // not-yet-passed even if the route fraction says otherwise — so we
        // don't display "passed at 10 AM yesterday" for a stop the truck
        // hasn't physically been near today.
        $latestTracking = \App\Models\VehicleTracking::where('booking_id', $booking->id)
            ->whereNotNull('reached_at')
            ->orderBy('reached_at', 'desc')
            ->first();

        $tripCutoff = $latestTracking
            ? Carbon::parse($latestTracking->reached_at)->subHours(6)
            : $startTime;

        $trackings = \App\Models\VehicleTracking::where('booking_id', $booking->id)
            ->whereNotNull('reached_at')
            ->where('reached_at', '>=', $tripCutoff)
            ->orderBy('reached_at', 'desc')
            ->get(['lat', 'lng', 'reached_at'])
            ->map(fn ($t) => [
                'lat'        => (float) $t->lat,
                'lng'        => (float) $t->lng,
                'reached_at' => $t->reached_at,
            ])
            ->all();

        // Also treat the current driver_location as a candidate "breadcrumb"
        // — but only once the driver has actually moved away from pickup.
        // When the truck is parked AT pickup (driver_lat ≈ pickup_lat), the
        // proximity matcher would wrongly stamp the first auto stop as
        // "passed at <now>" simply because pickup and that stop are
        // geographically near each other. We need the driver to be at
        // least ~150 m past pickup before letting their live position
        // count as a passed-stop signal. Saved tracking rows (which only
        // appear after real movement) are always considered.
        $pickupLatForCheck = (float) ($booking->vehicle_start_lat ?? $booking->pickup_lat ?? 0);
        $pickupLngForCheck = (float) ($booking->vehicle_start_lng ?? $booking->pickup_lng ?? 0);
        $driverMovedFromPickup = true;
        if ($driverLat && $driverLng && $pickupLatForCheck && $pickupLngForCheck) {
            $distFromPickup = $this->haversineMeters(
                $pickupLatForCheck, $pickupLngForCheck, $driverLat, $driverLng
            );
            $driverMovedFromPickup = $distFromPickup > 150;
        }
        if ($driverLat && $driverLng && $driverMovedFromPickup) {
            array_unshift($trackings, [
                'lat'        => $driverLat,
                'lng'        => $driverLng,
                'reached_at' => $driverTime,
            ]);
        }

        // ── GPS driver fraction (straight-line from pickup) ───────────────────
        // Uses the furthest point the driver has actually reached (from tracking
        // history + current position) divided by pickup→drop straight-line dist.
        // Far more accurate than "nearest auto-stop fraction" which falsely marks
        // the first stop as passed the moment the driver is anywhere nearby.
        $pickupLat = (float) ($booking->vehicle_start_lat ?? 0);
        $pickupLng = (float) ($booking->vehicle_start_lng ?? 0);
        $dropLat   = (float) ($booking->drop_lat ?? 0);
        $dropLng   = (float) ($booking->drop_lng ?? 0);

        $totalStraightDist = ($pickupLat && $dropLat)
            ? $this->haversineMeters($pickupLat, $pickupLng, $dropLat, $dropLng)
            : 0;

        // Max distance from pickup among all recorded breadcrumbs + current pos
        $maxFromPickup = ($pickupLat && $driverLat)
            ? $this->haversineMeters($pickupLat, $pickupLng, $driverLat, $driverLng)
            : 0;
        foreach ($trackings as $t) {
            $d = $this->haversineMeters($pickupLat, $pickupLng, $t['lat'], $t['lng']);
            if ($d > $maxFromPickup) $maxFromPickup = $d;
        }

        $gpsDriverFraction = ($totalStraightDist > 0)
            ? min($maxFromPickup / $totalStraightDist, 0.99)
            : 0.0;

        // ── Remaining seconds for future ETAs ─────────────────────────────────
        $actualElapsed    = max(1, abs($driverTime->diffInSeconds($startTime)));
        $remainingSeconds = $totalDuration > 0
            ? max(60, $totalDuration - $actualElapsed)
            : 3600;

        // Proximity radius: half the average stop spacing, clamped 500 m – 5 km
        $stopSpacingM = $totalStraightDist > 0 && count($stops) > 0
            ? $totalStraightDist / count($stops)
            : 3000;
        $proximityM = (float) min(max($stopSpacingM * 0.5, 500), 5000);

        // Sort stops by route fraction so the monotonic-time clamp below
        // applies in the actual drive order (closest-breadcrumb matching can
        // pick breadcrumbs out of chronological order — e.g. CBI Colony's
        // closest breadcrumb is at 10:28 while Kavuri Hills' closest is at
        // 10:29 even though Kavuri Hills comes earlier on the route).
        usort($stops, fn($a, $b) => ($a['dist_fraction'] ?? 0) <=> ($b['dist_fraction'] ?? 0));

        $result = [];
        $prevPassedAt = null; // Carbon — rolling lower bound for next stop
        foreach ($stops as $stop) {
            $fraction = (float) ($stop['dist_fraction'] ?? 0);
            $stopLat  = (float) $stop['lat'];
            $stopLng  = (float) $stop['lng'];

            // ── Progress gate ─────────────────────────────────────────────
            // Only consider this stop "passable" if the driver's overall
            // progress along the route has actually reached its fraction.
            $passedTracking = null;
            if ($fraction <= $gpsDriverFraction + 0.01) {
                // ── Find the CLOSEST breadcrumb to this stop within proximity ──
                // Each candidate must satisfy TWO conditions:
                //   (a) within the proximity radius of the stop, AND
                //   (b) be closer to the stop than to pickup.
                //
                // (b) is the right geographic-progression check: it filters
                // out breadcrumbs that are still hugging pickup even when
                // pickup happens to be within proximity radius of the stop.
                // E.g. for booking 780 the auto-sampled CBI Colony point
                // landed only 69 m straight-line from pickup; a breadcrumb
                // 21 m from pickup is 90 m from that stop — geographically
                // close, but obviously hasn't crossed the stop's threshold
                // because it's closer to where it started. Skipping those
                // leaves only breadcrumbs that have physically moved into
                // the stop's neighbourhood.
                $bestDist = PHP_FLOAT_MAX;
                foreach ($trackings as $t) {
                    $dStop = $this->haversineMeters($stopLat, $stopLng, $t['lat'], $t['lng']);
                    if ($dStop > $proximityM) continue;
                    if ($pickupLat && $pickupLng) {
                        $dPickup = $this->haversineMeters($pickupLat, $pickupLng, $t['lat'], $t['lng']);
                        // Skip breadcrumbs still closer to pickup than to
                        // the stop — they haven't reached the stop yet.
                        if ($dStop > $dPickup) continue;
                    }
                    if ($dStop < $bestDist) {
                        $bestDist = $dStop;
                        $passedTracking = $t;
                    }
                }
            }

            if ($passedTracking !== null) {
                $eta = Carbon::parse($passedTracking['reached_at']);
                // Monotonic clamp: enforce passed_at non-decreasing in route
                // order. A later-route stop can never have an earlier
                // timestamp than an earlier-route stop. When the closest
                // breadcrumb for stop N is earlier than stop N-1's, bump
                // stop N's timestamp up by one second to keep ordering
                // strictly increasing without inventing a fake long pause.
                // Use <= so two stops that landed on the SAME closest
                // breadcrumb (common on short routes where the proximity
                // radius overlaps several stops) get distinct timestamps —
                // not the same one repeated. The bump is just +1 s so it
                // doesn't manufacture a fake long pause between stops.
                if ($prevPassedAt !== null && $eta->lessThanOrEqualTo($prevPassedAt)) {
                    $eta = $prevPassedAt->copy()->addSecond();
                }
                $prevPassedAt = $eta;
                $stop['passed_at'] = $eta->toIso8601String();
            } else {
                // 🔜 Not passed in current trip — ETA proportional to the
                // remaining route fraction. Clamped at 0 so stops "behind"
                // the driver (fraction < gpsDriverFraction) don't get
                // negative-offset timestamps in the past.
                $routeAhead        = max(0.001, 1.0 - $gpsDriverFraction);
                $deltaFraction     = max(0.0, $fraction - $gpsDriverFraction);
                $secondsFromAnchor = (int) (($deltaFraction / $routeAhead) * $remainingSeconds);
                $eta = $anchorTime->copy()->addSeconds($secondsFromAnchor);
                $stop['estimated_arrival'] = $eta->format('g:i A');
                $stop['estimated_date']    = $eta->format('d/m/Y');
            }

            $result[] = $stop;
        }
        return $result;
    }

    /**
     * Reroute-aware variant: passed stops keep their original lat/lng/name
     * and `passed_at` timestamp (frozen history), while FUTURE stops are
     * regenerated from the driver's current position to the drop. This way
     * the timeline reflects the road the driver is actually on now, even
     * after a mid-trip reroute.
     *
     * Falls back to the static `buildAutoTimelineWithETAs` whenever:
     *   - driver location is missing, OR
     *   - the remaining-route Directions call fails (quota / network), OR
     *   - we don't have a future-stop count to regenerate.
     */
    public function buildAutoTimelineWithETAsRerouteAware(Booking $booking, array $driverLocation): array
    {
        $original = $this->buildAutoTimelineWithETAs($booking, $driverLocation);
        if (empty($original)) return $original;

        // Split into passed (frozen) and future (to be regenerated)
        $passed = [];
        $futureCount = 0;
        foreach ($original as $stop) {
            if (!empty($stop['passed_at'])) {
                $passed[] = $stop;
            } else {
                $futureCount++;
            }
        }

        // No future stops → nothing to regenerate, return as-is
        if ($futureCount === 0) return $original;

        $driverLat = (float) ($driverLocation['lat'] ?? 0);
        $driverLng = (float) ($driverLocation['lng'] ?? 0);
        if ($driverLat === 0.0 || $driverLng === 0.0) return $original;

        $remaining = $this->getRemainingRouteFromDriver($booking, $driverLat, $driverLng);
        $freshStops = $remaining['stops'] ?? [];
        if (empty($freshStops)) return $original;

        $remainingSeconds = max(60, (int) ($remaining['total_duration_seconds'] ?? 3600));

        // ETA anchor: when the driver was last seen (or now if status is idle)
        $driverStatus = $driverLocation['driver_status'] ?? 'moving';
        $driverTime = !empty($driverLocation['updated_at'])
            ? Carbon::parse($driverLocation['updated_at'])
            : Carbon::now();
        $anchorTime = in_array($driverStatus, ['idle', 'stopped']) ? Carbon::now() : $driverTime;

        // Highest dist_fraction among passed stops — future stops slot in after this
        $passedFraction = 0.0;
        foreach ($passed as $p) {
            $f = (float) ($p['dist_fraction'] ?? 0);
            if ($f > $passedFraction) $passedFraction = $f;
        }

        // Compute ETAs and re-base dist_fraction onto the original 0..1 scale
        $futureWithETAs = array_map(function (array $s) use ($anchorTime, $remainingSeconds, $passedFraction) {
            $relFraction = (float) ($s['dist_fraction'] ?? 0); // 0..1 within driver→drop
            $eta = $anchorTime->copy()->addSeconds((int) ($relFraction * $remainingSeconds));
            $s['dist_fraction']     = round($passedFraction + $relFraction * (1.0 - $passedFraction), 6);
            $s['estimated_arrival'] = $eta->format('g:i A');
            $s['estimated_date']    = $eta->format('d/m/Y');
            return $s;
        }, $freshStops);

        // Keep the total stop count stable so the UI doesn't suddenly show
        // 12 stops where there used to be 8. Sample evenly if we got more
        // fresh stops than we need.
        if (count($futureWithETAs) > $futureCount && $futureCount > 0) {
            if ($futureCount === 1) {
                $futureWithETAs = [end($futureWithETAs)];
            } else {
                $step    = (count($futureWithETAs) - 1) / ($futureCount - 1);
                $sampled = [];
                for ($t = 0; $t < $futureCount; $t++) {
                    $sampled[] = $futureWithETAs[(int) round($t * $step)];
                }
                $futureWithETAs = $sampled;
            }
        }

        // Final dedup ACROSS passed and future. Two paths can produce a
        // duplicate name: the original generate() included "Sri Sai Nagar"
        // at fraction 0.077 (now in $passed), and the regenerated future
        // route via getRemainingRouteFromDriver independently sampled
        // "Sri Sai Nagar" again at a different fraction (now in
        // $futureWithETAs). Dedup-by-name keeps the passed entry (it has
        // the real passed_at timestamp) and drops the future copy.
        $merged = array_merge($passed, $futureWithETAs);
        $seen   = [];
        $final  = [];
        foreach ($merged as $stop) {
            $key = strtolower(trim(explode(',', $stop['name'] ?? '')[0]));
            if ($key === '') { $final[] = $stop; continue; }
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $final[] = $stop;
        }
        return $final;
    }

    /**
     * Generate intermediate stops between the driver's current position and
     * the booking's drop. Cached for 3 minutes per ~110 m driver-position
     * grid cell so polling doesn't burn Directions/Geocoding quota.
     *
     * Prior-priority drops (HITEC City, Kokapet, …) are pulled from the same
     * shared-vehicle trip and forwarded so the regenerated future stops
     * include them as key stops — the customer of priority N keeps seeing
     * the prior customers' drops in their timeline even after a reroute.
     */
    public function getRemainingRouteFromDriver(Booking $booking, float $driverLat, float $driverLng): array
    {
        if (empty($this->apiKey)) {
            return ['stops' => [], 'total_duration_seconds' => 0];
        }
        if (!$booking->drop_lat || !$booking->drop_lng) {
            return ['stops' => [], 'total_duration_seconds' => 0];
        }

        // Bumped to v3 — v2 cached entries can contain the destination
        // short name as an auto stop. v3 filters that out.
        $cacheKey = sprintf(
            'route_stops_remaining_v3:%d:%s:%s',
            $booking->id,
            number_format($driverLat, 3, '.', ''),
            number_format($driverLng, 3, '.', '')
        );

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && !empty($cached['stops'])) {
            return $cached;
        }

        // Collect prior-priority drops on the same vehicle trip that haven't
        // been delivered yet (status 4 = in progress). Already-delivered
        // (status 5) and failed (status 6) drops belong to the past so we
        // skip them — they don't need to appear in the future-route stops.
        $waypointCoords = [];
        $priorityStops  = [];
        if ($booking->driver_id && $booking->vehicle_started_date) {
            $wps = Booking::where('driver_id', $booking->driver_id)
                ->whereDate('vehicle_started_date', $booking->vehicle_started_date)
                ->where('status', 4)
                ->where('id', '!=', $booking->id)
                ->when($booking->priority, fn($q) => $q->where('priority', '<', $booking->priority))
                ->orderBy('priority')
                ->get()
                ->filter(fn($b) => $b->drop_lat && $b->drop_lng);

            foreach ($wps as $wp) {
                $waypointCoords[] = $wp->drop_lat . ',' . $wp->drop_lng;
                $rawName = $wp->dropping_location ?? $wp->delivery_location ?? "Stop #{$wp->id}";
                $shortName = trim(explode(',', $rawName)[0]) ?: $rawName;
                $priorityStops[] = [
                    'lat'         => (float) $wp->drop_lat,
                    'lng'         => (float) $wp->drop_lng,
                    'name'        => $shortName,
                    'priority'    => (int) $wp->priority,
                    'is_key_stop' => true,
                ];
            }
        }

        $dropName = $booking->dropping_location ?? $booking->delivery_location ?? '';
        $result = $this->buildStopsFromDirections(
            $driverLat . ',' . $driverLng,
            $booking->drop_lat . ',' . $booking->drop_lng,
            $waypointCoords,
            $booking->id,
            $priorityStops,
            $dropName
        );

        if (!empty($result['stops'])) {
            Cache::put($cacheKey, $result, 180); // 3 min
        }
        return $result;
    }

    /**
     * Clear the cached stops for a booking (call when route changes).
     */
    public function clearCache(int $bookingId): void
    {
        Cache::forget('route_stops_v2:' . $bookingId);
        Cache::forget('route_stops_v3:' . $bookingId);
        Cache::forget('route_stops_v4:' . $bookingId);
        Cache::forget('route_stops_v5:' . $bookingId);
        Cache::forget('route_stops_v6:' . $bookingId);
        Cache::forget('route_stops_v7:' . $bookingId);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE GENERATION LOGIC
    // ─────────────────────────────────────────────────────────────────────────

    private function generate(Booking $booking): array
    {
        // ── Waypoints (prior deliveries on the same route) ────────────────
        // Carry lat/lng AND the place name so we can splice each one into
        // the timeline as a "key stop" — that way the customer sees their
        // route's actual customer drops ("HITEC City", "Kokapet") in order,
        // not just the auto-sampled nearby localities.
        $waypointCoords = [];
        $priorityStops  = [];
        if ($booking->driver_id && $booking->vehicle_started_date) {
            $wps = Booking::where('driver_id', $booking->driver_id)
                ->whereDate('vehicle_started_date', $booking->vehicle_started_date)
                ->whereIn('status', [4, 5, 6])
                ->where('id', '!=', $booking->id)
                ->when($booking->priority, fn($q) => $q->where('priority', '<', $booking->priority))
                ->orderBy('priority')
                ->get()
                ->filter(fn($b) => $b->drop_lat && $b->drop_lng);

            foreach ($wps as $wp) {
                $waypointCoords[] = $wp->drop_lat . ',' . $wp->drop_lng;

                // Prefer the leading place-name segment (e.g. "HITEC City"
                // from "HITEC City, Hyderabad, Telangana, India") so the
                // timeline matches the visual style of the auto stops.
                $rawName = $wp->dropping_location ?? $wp->delivery_location ?? "Stop #{$wp->id}";
                $shortName = trim(explode(',', $rawName)[0]) ?: $rawName;

                $priorityStops[] = [
                    'lat'         => (float) $wp->drop_lat,
                    'lng'         => (float) $wp->drop_lng,
                    'name'        => $shortName,
                    'priority'    => (int) $wp->priority,
                    'is_key_stop' => true,
                ];
            }
        }

        $origin = $this->resolveOriginCoords($booking);
        if ($origin === null) return ['stops' => [], 'total_duration_seconds' => 0];

        $dropName = $booking->dropping_location ?? $booking->delivery_location ?? '';
        return $this->buildStopsFromDirections(
            $origin,
            $booking->drop_lat . ',' . $booking->drop_lng,
            $waypointCoords,
            $booking->id,
            $priorityStops,
            $dropName,
        );
    }

    /**
     * Resolve the trip's origin in priority order:
     *   1. admin-set vehicle_start_lat/lng   (most accurate when admin sets it)
     *   2. booking's own pickup_lat/lng      (customer/farmer-set)
     *   3. first vehicle_trackings row       (where the journey actually began)
     *   4. current driver_lat/lng            (for in-journey bookings)
     *
     * Returns "lat,lng" or null when nothing is usable. This fallback chain
     * is the root fix for empty `auto_timeline_points` on bookings whose
     * admin never manually entered vehicle_start_lat/lng — including
     * Priority 2/3 customers on shared vehicle trips.
     */
    private function resolveOriginCoords(Booking $booking): ?string
    {
        if ($booking->vehicle_start_lat && $booking->vehicle_start_lng) {
            return $booking->vehicle_start_lat . ',' . $booking->vehicle_start_lng;
        }
        if (!empty($booking->pickup_lat) && !empty($booking->pickup_lng)) {
            return $booking->pickup_lat . ',' . $booking->pickup_lng;
        }
        $firstTrack = \App\Models\VehicleTracking::where('booking_id', $booking->id)
            ->whereNotNull('lat')->whereNotNull('lng')
            ->orderBy('reached_at', 'asc')
            ->first();
        if ($firstTrack) {
            return $firstTrack->lat . ',' . $firstTrack->lng;
        }
        if ($booking->driver_lat && $booking->driver_lng) {
            return $booking->driver_lat . ',' . $booking->driver_lng;
        }
        return null;
    }

    /**
     * Generate intermediate stops between any origin and destination.
     * Extracted from generate() so we can reuse the pipeline for reroute
     * regeneration (driver_pos → drop) without duplicating the polyline +
     * sampling + reverse-geocode logic.
     *
     * @param  string  $origin       "lat,lng"
     * @param  string  $destination  "lat,lng"
     * @param  array   $waypoints       optional ["lat,lng", ...] in route order
     * @param  int|null $logBookingId   for warning logs
     * @param  array   $priorityStops   prior-priority drops to splice into
     *                                  the timeline as is_key_stop entries.
     * @param  string  $destShortName   first component of the destination's
     *                                  address (e.g. "Kokapet"). Any
     *                                  auto-sampled stop with the same short
     *                                  name is dropped so the destination
     *                                  doesn't appear twice in the timeline.
     * @return array   { stops: [...], total_duration_seconds: int }
     */
    private function buildStopsFromDirections(
        string $origin,
        string $destination,
        array $waypoints = [],
        ?int $logBookingId = null,
        array $priorityStops = [],
        string $destShortName = ''
    ): array {
        try {
            // ── Call Directions API ───────────────────────────────────────────
            $params = [
                'origin'      => $origin,
                'destination' => $destination,
                'key'         => $this->apiKey,
            ];
            if (!empty($waypoints)) {
                $params['waypoints'] = implode('|', $waypoints);
            }

            $resp = Http::timeout(15)->get(
                'https://maps.googleapis.com/maps/api/directions/json', $params
            );

            if (!$resp->ok()) return [];
            $data = $resp->json();
            if (($data['status'] ?? '') !== 'OK' || empty($data['routes'])) return [];

            // ── Decode per-step polylines (high-res, mirrors getDirectionsHighRes) ──
            $polyline      = [];
            $totalDuration = 0;
            foreach ($data['routes'][0]['legs'] as $leg) {
                $totalDuration += $leg['duration']['value'] ?? 0;
                foreach ($leg['steps'] as $step) {
                    $pts = $this->decodePolyline($step['polyline']['points']);
                    if (!empty($polyline) && !empty($pts)) {
                        array_shift($pts); // deduplicate leg boundary
                    }
                    $polyline = array_merge($polyline, $pts);
                }
            }

            if (count($polyline) < 2) return [];

            // ── Cumulative distances (metres) ─────────────────────────────────
            $cumDist = [0.0];
            for ($i = 1; $i < count($polyline); $i++) {
                $cumDist[] = $cumDist[$i - 1] + $this->haversineMeters(
                    $polyline[$i - 1][0], $polyline[$i - 1][1],
                    $polyline[$i][0],     $polyline[$i][1]
                );
            }
            $totalDist = end($cumDist);

            // ── Stop count based on journey duration ──────────────────────────
            // <1.5h → 4,  <3h → 6,  <6h → 8,  <10h → 10,  <20h → 12,  20h+ → 15
            // Multi-day (>48h): 1 stop per 8 hours, cap 50.
            $totalHours = $totalDuration / 3600.0;
            if ($totalHours < 1.5)      $stopCount = 4;
            elseif ($totalHours < 3.0)  $stopCount = 6;
            elseif ($totalHours < 6.0)  $stopCount = 8;
            elseif ($totalHours < 10.0) $stopCount = 10;
            elseif ($totalHours < 20.0) $stopCount = 12;
            elseif ($totalHours <= 48)  $stopCount = 15;
            else                        $stopCount = min((int) round($totalHours / 8), 50);

            // Scale candidate count for long routes
            $segmentKm    = $totalDist / 1000;
            $desiredCount = $segmentKm >= 500 ? 15
                          : ($segmentKm >= 220 ? 10
                          : ($segmentKm >= 160 ? 8
                          : ($segmentKm >= 120 ? 6
                          : ($segmentKm >= 80  ? 5
                          : $stopCount))));
            $targetCount    = max($stopCount, $desiredCount);
            $candidateCount = min(max($targetCount * 3, 12), 60);
            $interval       = $totalDist / ($candidateCount + 1);

            // ── Sample candidate points evenly along the polyline ─────────────
            $candidates = [];
            for ($i = 1; $i <= $candidateCount; $i++) {
                $targetD = $interval * $i;
                if ($targetD >= $totalDist * 0.97) break;
                $pt = $this->interpolate($polyline, $cumDist, $targetD);
                if ($pt !== null) {
                    $candidates[] = [
                        'lat'           => $pt[0],
                        'lng'           => $pt[1],
                        'dist_fraction' => $totalDist > 0 ? $targetD / $totalDist : 0,
                    ];
                }
            }

            if (empty($candidates)) return [];

            // ── Reverse-geocode all candidates concurrently ───────────────────
            $names = $this->reverseGeocodeMany($candidates);

            // ── Collect named stops ───────────────────────────────────────────
            $stops = [];
            foreach ($candidates as $idx => $c) {
                $name = $names[$idx] ?? null;
                if ($name && strtolower(trim($name)) !== 'unknown') {
                    $stops[] = [
                        'name'          => $name,
                        'lat'           => round($c['lat'], 6),
                        'lng'           => round($c['lng'], 6),
                        'dist_fraction' => round($c['dist_fraction'], 6),
                        'is_key_stop'   => false,
                    ];
                }
            }

            // Seed the "seen" set with the destination's short name so any
            // auto-sampled stop in the same place (e.g. an auto stop at
            // fraction 0.93 reverse-geocoded as "Kokapet" when the actual
            // drop is also Kokapet) is dropped here. The destination is
            // rendered separately as the timeline's last item, so showing
            // the same place name as an intermediate stop is confusing.
            // ── Deduplicate by first place-name component (mirrors Flutter) ───
            $seen   = [];
            if ($destShortName !== '') {
                $destKey = strtolower(trim(explode(',', $destShortName)[0]));
                if ($destKey !== '') $seen[$destKey] = true;
            }
            $unique = [];
            foreach ($stops as $s) {
                $key = strtolower(trim(explode(',', $s['name'])[0]));
                if ($key && !isset($seen[$key])) {
                    $seen[$key] = true;
                    $unique[]   = $s;
                }
            }

            // ── Thin to targetCount with even stride (mirrors Flutter) ─────────
            if (count($unique) > $targetCount && $targetCount > 1) {
                $step    = (count($unique) - 1) / ($targetCount - 1);
                $thinned = [];
                for ($t = 0; $t < $targetCount; $t++) {
                    $thinned[] = $unique[(int) round($t * $step)];
                }
                // Final dedup after thinning
                $seen2  = [];
                $unique = [];
                foreach ($thinned as $s) {
                    $key = strtolower(trim(explode(',', $s['name'])[0]));
                    if (!isset($seen2[$key])) {
                        $seen2[$key] = true;
                        $unique[]    = $s;
                    }
                }
            }

            // ── Splice in prior-priority customer drops as key stops ─────────
            // On shared multi-drop trips, the customer of priority N expects
            // to see "I'm going via [HITEC City], [Kokapet], ..." in their
            // own timeline. Each priority drop is anchored to the route via
            // its closest polyline vertex (gives a real dist_fraction), then
            // de-duplicated against auto stops by short-name so we never
            // show the same place twice.
            if (!empty($priorityStops)) {
                $existingShortNames = [];
                foreach ($unique as $s) {
                    $existingShortNames[strtolower(trim(explode(',', $s['name'])[0]))] = true;
                }
                foreach ($priorityStops as $ps) {
                    $psLat = (float) $ps['lat'];
                    $psLng = (float) $ps['lng'];
                    // Find the polyline vertex closest to this priority drop;
                    // its cumulative-distance share gives the dist_fraction.
                    $bestIdx = 0; $bestD = PHP_FLOAT_MAX;
                    for ($i = 0; $i < count($polyline); $i++) {
                        $d = $this->haversineMeters($psLat, $psLng, $polyline[$i][0], $polyline[$i][1]);
                        if ($d < $bestD) { $bestD = $d; $bestIdx = $i; }
                    }
                    $fraction = $totalDist > 0 ? $cumDist[$bestIdx] / $totalDist : 0;
                    $shortKey = strtolower(trim(explode(',', $ps['name'])[0]));
                    if (isset($existingShortNames[$shortKey])) continue;
                    $existingShortNames[$shortKey] = true;
                    $unique[] = [
                        'name'          => $ps['name'],
                        'lat'           => round($psLat, 6),
                        'lng'           => round($psLng, 6),
                        'dist_fraction' => round($fraction, 6),
                        'is_key_stop'   => true,
                    ];
                }
                // Re-sort everything by route fraction so priority drops
                // sit in the right place along the timeline.
                usort($unique, fn($a, $b) => ($a['dist_fraction'] ?? 0) <=> ($b['dist_fraction'] ?? 0));
            }

            return [
                'stops'                  => array_values($unique),
                'total_duration_seconds' => $totalDuration,
            ];

        } catch (\Throwable $e) {
            Log::warning('RouteTimelineService: stop generation failed', [
                'booking_id' => $logBookingId,
                'error'      => $e->getMessage(),
            ]);
            return ['stops' => [], 'total_duration_seconds' => 0];
        }
    }

    /**
     * Reverse-geocode multiple lat/lng pairs concurrently via Http::pool().
     * Returns a name (string) or null per candidate index.
     */
    private function reverseGeocodeMany(array $candidates): array
    {
        $apiKey = $this->apiKey;
        try {
            // Drop the `result_type` filter — on long rural routes Google has
            // no `locality|sublocality|admin_area_3` match for most highway
            // points, so the strict filter returned `null` for nearly every
            // candidate and the customer's timeline came back empty. Without
            // the filter we get whatever Google has nearby (often a smaller
            // place / route segment / point of interest), then prefer a
            // locality-level component out of the returned address_components.
            $responses = Http::pool(fn($pool) => array_map(
                fn($c) => $pool->timeout(8)->get(
                    'https://maps.googleapis.com/maps/api/geocode/json',
                    [
                        'latlng' => round($c['lat'], 6) . ',' . round($c['lng'], 6),
                        'key'    => $apiKey,
                    ]
                ),
                $candidates
            ));

            $names = [];
            foreach ($responses as $idx => $resp) {
                $name = null;
                if (!($resp instanceof \Throwable) && $resp->ok()) {
                    $results = $resp->json()['results'] ?? [];
                    // Walk results + their components and grab the first
                    // place-name-like value. Sublocality (neighbourhood /
                    // area name like "Madhapur", "Kavuri Hills") is
                    // preferred over locality (city name like
                    // "Hyderabad") so the customer sees specific area
                    // names along the route instead of every intermediate
                    // stop labelled as the same city. Falls back through
                    // ever-larger admin levels.
                    $prefer = [
                        'sublocality_level_2',
                        'sublocality_level_1',
                        'sublocality',
                        'neighborhood',
                        'administrative_area_level_3',
                        'locality',
                        'administrative_area_level_2',
                        'administrative_area_level_1',
                    ];
                    foreach ($prefer as $type) {
                        foreach ($results as $r) {
                            foreach (($r['address_components'] ?? []) as $comp) {
                                if (in_array($type, $comp['types'] ?? [], true)) {
                                    $name = $comp['long_name'] ?? null;
                                    break 3;
                                }
                            }
                        }
                    }
                    if ($name === null && !empty($results)) {
                        $name = $results[0]['address_components'][0]['long_name'] ?? null;
                    }
                }
                $names[$idx] = $name;
            }
            return $names;
        } catch (\Throwable $e) {
            Log::warning('RouteTimelineService: geocode pool failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PURE HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /** Decode a Google-encoded polyline string into [[lat, lng], ...] */
    private function decodePolyline(string $encoded): array
    {
        $points = [];
        $index  = 0;
        $len    = strlen($encoded);
        $lat    = 0;
        $lng    = 0;

        while ($index < $len) {
            $shift = $result = 0;
            do {
                $b       = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift  += 5;
            } while ($b >= 0x20 && $index < $len);
            $lat += ($result & 1) ? ~($result >> 1) : ($result >> 1);

            $shift = $result = 0;
            do {
                $b       = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift  += 5;
            } while ($b >= 0x20 && $index < $len);
            $lng += ($result & 1) ? ~($result >> 1) : ($result >> 1);

            $points[] = [$lat / 1e5, $lng / 1e5];
        }

        return $points;
    }

    /** Linear interpolation of a point at $targetDist metres along the polyline. */
    private function interpolate(array $poly, array $cumDist, float $targetDist): ?array
    {
        $n = count($poly);
        if ($n < 2)                        return null;
        if ($targetDist <= 0)              return $poly[0];
        if ($targetDist >= $cumDist[$n-1]) return $poly[$n-1];

        for ($i = 1; $i < $n; $i++) {
            if ($cumDist[$i] >= $targetDist) {
                $segLen = $cumDist[$i] - $cumDist[$i - 1];
                if ($segLen <= 0) return $poly[$i];
                $t = ($targetDist - $cumDist[$i - 1]) / $segLen;
                return [
                    $poly[$i-1][0] + $t * ($poly[$i][0] - $poly[$i-1][0]),
                    $poly[$i-1][1] + $t * ($poly[$i][1] - $poly[$i-1][1]),
                ];
            }
        }
        return $poly[$n - 1];
    }

    /** Haversine distance in metres between two lat/lng points. */
    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $R * asin(min(1.0, sqrt($a)));
    }
}
