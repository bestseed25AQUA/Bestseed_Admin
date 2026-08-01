<?php
/**
 * Real-world GPS simulator for multi-booking priority-order delivery.
 *
 * The driver follows bookings in priority order:
 *   pickup (from booking 1) → drop(booking 1) → drop(booking 2) → ... → drop(booking N)
 *
 * On every step the driver location is pushed to ALL booking IDs so every
 * customer's tracking screen shows live movement.
 *
 * Usage (single booking):
 *   php simulate.php --booking=606 --scenario=clean_drive
 *
 * Usage (multi-booking, priority order):
 *   php simulate.php --bookings=608,609,610 --scenario=clean_drive --imin=2 --imax=4
 *
 * Scenarios available:
 *   clean_drive           — normal driving, no disruptions
 *   gps_drift_stationary  — driver parked, GPS drifts in place
 *   network_loss          — drops several updates mid-route
 */

const BASE_URL    = 'https://aqua.bestseed.in';
// CLI simulator secrets. Pass via env before running — never commit real
// values. Sanctum tokens grant full API access as the associated user.
//   SIM_FARMER_TOKEN=... SIM_VENDOR_TOKEN=... SIM_GOOGLE_KEY=... php simulate.php
define('FARMER_TOKEN', getenv('SIM_FARMER_TOKEN') ?: '');
define('VENDOR_TOKEN', getenv('SIM_VENDOR_TOKEN') ?: '');
define('GOOGLE_KEY',   getenv('SIM_GOOGLE_KEY')   ?: '');
if (FARMER_TOKEN === '' || VENDOR_TOKEN === '' || GOOGLE_KEY === '') {
    fwrite(STDERR, "simulate.php: set SIM_FARMER_TOKEN, SIM_VENDOR_TOKEN, SIM_GOOGLE_KEY env vars before running (see .env.example).\n");
    exit(1);
}

const ENDPOINT_UPDATE_LOCATION = '/api/farmer/update_driver_location';
const ENDPOINT_ADD_TRACKING    = '/api/farmer/add_tracking_point';

class RouteSimulator
{
    /** @var int[] */
    private array  $bookingIds;
    private string $scenario;
    private array  $config;

    private array  $route        = [];   // Full polyline [[lat, lng], ...]
    private array  $stepLogs     = [];

    /** Ordered stops: pickup first, then each booking's drop in priority order */
    private ?array $pickup       = null;
    /** @var array[] [{lat,lng,booking_id,name}, ...] */
    private array  $drops        = [];
    private ?array $currentDriver = null;

    private float  $progress     = 0.0;
    private float  $totalRouteM  = 0.0;
    private string $forcedMode   = '';

    // ── Notification / geocode tracking ───────────────────────────────
    /** Geocode cache keyed by 200m grid cell — avoids re-calling Google on every step */
    private array  $geocodeCache      = [];
    /** The last location name that was sent — used to detect notification updates */
    private string $lastLocationName  = '';
    /** Per-booking snapshot of which auto_timeline_points were already passed */
    private array  $lastTimelineState = [];

    public function __construct(array $bookingIds, string $scenario, array $config = [])
    {
        $this->bookingIds = $bookingIds;
        $this->scenario   = $scenario;
        $this->config     = array_merge([
            'interval_min' => 7,
            'interval_max' => 12,
            'max_points'   => 300,
            'driverloc'    => '',
        ], $config);
    }

    // ── Entry point ────────────────────────────────────────────────────
    public function run(): void
    {
        $this->log("─── Vehicle Tracking Simulator ───");
        $this->log("Bookings: " . implode(', ', $this->bookingIds) . "  Scenario: {$this->scenario}");
        $this->log("Interval: {$this->config['interval_min']}-{$this->config['interval_max']}s random");

        $this->fetchAllBookings();
        $this->buildRoadRoute();

        if (count($this->route) < 2) {
            $this->log("✘ Could not build route. Aborting.");
            return;
        }
        $this->log("Route loaded: " . count($this->route) . " points, " . round($this->totalRouteM / 1000, 1) . " km");

        $this->simulateScenario();
        $this->validateResults();
        $this->writeReport();
    }

    // ── Fetch all booking info and build ordered stops list ────────────
    private function fetchAllBookings(): void
    {
        $this->drops = [];

        foreach ($this->bookingIds as $idx => $bookingId) {
            $url = BASE_URL . '/api/farmer/vehicle_tracking/' . $bookingId;
            $this->log("GET booking #$bookingId → $url");
            $raw  = $this->httpGetRaw($url, true, 'vendor');
            $resp = $raw ? json_decode($raw, true) : null;

            if (!$resp || empty($resp['data'])) {
                throw new RuntimeException("Failed to fetch booking $bookingId");
            }
            $d = $resp['data'];

            // First booking supplies the pickup (driver start)
            if ($idx === 0) {
                $this->pickup = [
                    'lat' => (float)($d['pickup']['lat'] ?? 0),
                    'lng' => (float)($d['pickup']['lng'] ?? 0),
                ];
                // Optional driver-loc override
                $startOverride = $this->config['driverloc'];
                if (!empty($startOverride)) {
                    $parts = explode(',', $startOverride);
                    if (count($parts) === 2) {
                        $this->pickup = ['lat' => (float)$parts[0], 'lng' => (float)$parts[1]];
                        $this->log("Driver start OVERRIDDEN to: {$this->pickup['lat']},{$this->pickup['lng']}");
                        $initResp = $this->sendLocationToAll($this->pickup['lat'], $this->pickup['lng'], 'Driver start override');
                        $this->log("Initial push done.");
                    }
                }
                // If a --driverloc override was applied, anchor currentDriver
                // to that position so progress snapping starts at the override
                // instead of the stale API value read before the push.
                if (!empty($startOverride)) {
                    $this->currentDriver = $this->pickup;
                } elseif (!empty($d['driver_location']['lat'])) {
                    $this->currentDriver = [
                        'lat' => (float)$d['driver_location']['lat'],
                        'lng' => (float)$d['driver_location']['lng'],
                    ];
                }
            }

            // Every booking contributes its drop
            $dropLat = (float)($d['drop']['lat'] ?? 0);
            $dropLng = (float)($d['drop']['lng'] ?? 0);
            if ($dropLat && $dropLng) {
                $this->drops[] = [
                    'lat'        => $dropLat,
                    'lng'        => $dropLng,
                    'booking_id' => $bookingId,
                    'name'       => $d['drop']['name'] ?? "Drop #$bookingId",
                ];
                $this->log("  Booking #$bookingId drop: $dropLat,$dropLng — " . ($d['drop']['name'] ?? ''));
            } else {
                $this->log("  ⚠ Booking #$bookingId has no drop coords — skipped");
            }
        }

        if (!$this->pickup || empty($this->drops)) {
            throw new RuntimeException("Could not build pickup/drop list from bookings.");
        }

        $this->log("Pickup: {$this->pickup['lat']},{$this->pickup['lng']}");
        $this->log("Route order: Pickup → " . implode(' → ', array_column($this->drops, 'name')));
    }

    // ── Build road-snapped route via Google Directions API ─────────────
    // Includes ALL drops as ordered waypoints so the driver follows
    // priority 1 → priority 2 → priority 3 along real roads.
    private function buildRoadRoute(): void
    {
        $finalDrop = end($this->drops);
        $midDrops  = array_slice($this->drops, 0, count($this->drops) - 1);

        $url = 'https://maps.googleapis.com/maps/api/directions/json'
            . '?origin='      . $this->pickup['lat'] . ',' . $this->pickup['lng']
            . '&destination=' . $finalDrop['lat']    . ',' . $finalDrop['lng']
            . '&mode=driving&key=' . GOOGLE_KEY;

        if (!empty($midDrops)) {
            $waypts = array_map(fn($d) => $d['lat'] . ',' . $d['lng'], $midDrops);
            $url .= '&waypoints=' . urlencode(implode('|', $waypts));
            $this->log("Waypoints added: " . implode(' | ', array_column($midDrops, 'name')));
        }

        $resp = $this->httpGetRaw($url);
        $data = json_decode($resp, true);
        if (($data['status'] ?? '') !== 'OK') {
            $this->log("✘ Directions API failed: " . ($data['status'] ?? '?') . " — " . ($data['error_message'] ?? ''));
            return;
        }
        $encoded = $data['routes'][0]['overview_polyline']['points'] ?? '';
        $points  = $this->decodePolyline($encoded);
        if (count($points) < 2) {
            $this->log("✘ Polyline empty");
            return;
        }

        $this->route        = $points;
        $this->totalRouteM  = 0;
        for ($i = 1; $i < count($points); $i++) {
            $this->totalRouteM += $this->haversine(
                $points[$i-1][0], $points[$i-1][1],
                $points[$i][0],   $points[$i][1]
            );
        }

        // If a prior run left the driver mid-route, resume from there
        if ($this->currentDriver) {
            $bestDist     = INF;
            $bestProgress = 0.0;
            $covered      = 0.0;
            for ($i = 1; $i < count($points); $i++) {
                $seg = $this->haversine(
                    $points[$i-1][0], $points[$i-1][1],
                    $points[$i][0],   $points[$i][1]
                );
                $d = $this->haversine(
                    $this->currentDriver['lat'], $this->currentDriver['lng'],
                    $points[$i][0], $points[$i][1]
                );
                if ($d < $bestDist) { $bestDist = $d; $bestProgress = $covered + $seg; }
                $covered += $seg;
            }
            $this->progress = $bestProgress;
            $this->log("Snapped start: " . round($bestProgress / 1000, 2) . " km into route ("
                . round($bestDist) . "m from backend driver pos)");
        }
    }

    // ── Decode Google encoded polyline ─────────────────────────────────
    private function decodePolyline(string $encoded): array
    {
        $points = [];
        $index = 0; $len = strlen($encoded);
        $lat = 0; $lng = 0;
        while ($index < $len) {
            $b = 0; $shift = 0; $result = 0;
            do { $b = ord($encoded[$index++]) - 63; $result |= ($b & 0x1f) << $shift; $shift += 5; } while ($b >= 0x20);
            $lat += (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $shift = 0; $result = 0;
            do { $b = ord($encoded[$index++]) - 63; $result |= ($b & 0x1f) << $shift; $shift += 5; } while ($b >= 0x20);
            $lng += (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $points[] = [$lat * 1e-5, $lng * 1e-5];
        }
        return $points;
    }

    // ── Main simulation loop ───────────────────────────────────────────
    // Scenarios:
    //   clean_drive          — smooth drive, no disruptions
    //   gps_drift_stationary — driver parked the whole run
    //   network_loss         — drops ~8 consecutive updates mid-route
    private function simulateScenario(): void
    {
        $this->log("─── Starting scenario: {$this->scenario} ───");

        $this->forcedMode = match ($this->scenario) {
            'gps_drift_stationary' => 'stop',
            default                => '',
        };

        $maxPts      = $this->config['max_points'];
        $networkLoss = [];
        if ($this->scenario === 'network_loss') {
            $start       = (int)($maxPts * 0.4);
            $networkLoss = range($start, $start + 8);
        }

        $virtualTime = 0;
        $prevSent    = null;

        for ($step = 0; $step < $maxPts; $step++) {

            if ($this->progress >= $this->totalRouteM - 5) {
                $this->log("✅ Reached end of route after $step steps");
                break;
            }

            // Random interval — matches real GPS app cadence
            $interval     = mt_rand($this->config['interval_min'], $this->config['interval_max']);
            $virtualTime += $interval;

            $mode     = $this->forcedMode ?: $this->getDrivingMode();
            $speed    = $this->getSpeedByMode($mode);
            $distance = ($speed * 1000.0 / 3600.0) * $interval;

            $cleanPoint = $this->moveAlongRoute($distance);
            $noisyPoint = $this->applyModeNoise($cleanPoint, $mode);

            // Occasional micro-backtrack to exercise bearing checks
            if ($mode !== 'stop' && mt_rand(0, 30) === 0) {
                $back       = -mt_rand(5, 12);
                $noisyPoint = $this->moveAlongRoute($back);
            }

            [$simLat, $simLng] = $noisyPoint;

            // Network loss: skip this step entirely
            if (in_array($step, $networkLoss, true)) {
                $this->log(sprintf("[%03d] ⏸  network_loss — skipped", $step));
                $this->stepLogs[] = ['step' => $step, 'skipped' => true, 'reason' => 'network_loss'];
                sleep($interval);
                continue;
            }

            // ── Geocode this position (mirrors Flutter driver app) ──────
            // Sends a real place name so backend stores the same value the
            // driver notification would show. Cache means nearby steps reuse
            // the same API result without extra Google calls.
            $locationName = $this->geocodePosition($simLat, $simLng);

            // ── Detect notification update ──────────────────────────────
            // A location-name change = driver's foreground notification would
            // update. Log it prominently so we can verify the fix works.
            if ($this->lastLocationName !== '' && $locationName !== $this->lastLocationName) {
                $this->log(sprintf(
                    "🔔 NOTIFICATION UPDATE  '%s'  →  '%s'",
                    $this->lastLocationName, $locationName
                ));
            }
            $this->lastLocationName = $locationName;

            $distFromPrev  = $prevSent ? $this->haversine($prevSent[0], $prevSent[1], $simLat, $simLng) : 0;
            $reportedSpeed = $interval > 0 ? ($distFromPrev / $interval) * 3.6 : 0;

            // Send to ALL booking IDs with the geocoded location name
            $allResponses = $this->sendLocationToAll($simLat, $simLng, $locationName);
            $firstResp    = $allResponses[$this->bookingIds[0]] ?? [];
            $accepted     = ($firstResp['status'] ?? false) === true;

            // Insert tracking row for significant movement
            if ($accepted && $distFromPrev >= 25 && $mode !== 'stop') {
                $this->addTrackingPointToAll($simLat, $simLng, $locationName);
            }

            // Which drop are we approaching?
            $dropLabel = $this->nearestDropLabel($simLat, $simLng);

            $tag = $accepted ? '✓' : '✗';
            $this->log(sprintf(
                "[%03d] %s %-22s mode=%-10s speed=%2dkm/h dist=%4dm  → %s",
                $step, $tag, "[$locationName]", $mode, (int)$speed, (int)$distFromPrev,
                $dropLabel
            ));

            // ── TIMELINE CHECK: every 2 accepted steps so we catch FUTURE→PASSED transitions quickly ──
            $timelineSnapshot = null;
            $timelineInterval = $this->config['timeline_check_every'] ?? 2;
            if ($accepted && ($step % $timelineInterval === 0)) {
                $timelineSnapshot = $this->fetchAndLogTimelines();
            }

            $this->stepLogs[] = [
                'step'             => $step,
                'virtual_time'     => $virtualTime,
                'mode'             => $mode,
                'speed_kmh'        => $speed,
                'progress_m'       => round($this->progress, 1),
                'simulated'        => [$simLat, $simLng],
                'dist_prev_m'      => round($distFromPrev, 1),
                'accepted'         => $accepted,
                'drop_approaching' => $dropLabel,
                'responses'        => $allResponses,
                'timeline'         => $timelineSnapshot,
            ];

            if ($accepted) $prevSent = [$simLat, $simLng];

            sleep($interval);
        }

        $this->log("─── Scenario complete ───");
    }

    // ── Return label of the nearest drop ──────────────────────────────
    private function nearestDropLabel(float $lat, float $lng): string
    {
        $best = INF; $label = '';
        foreach ($this->drops as $drop) {
            $d = $this->haversine($lat, $lng, $drop['lat'], $drop['lng']);
            if ($d < $best) { $best = $d; $label = $drop['name'] . ' [#' . $drop['booking_id'] . '] ' . round($d / 1000, 1) . 'km'; }
        }
        return $label;
    }

    // ── Fetch and log timeline for every booking ───────────────────────
    // Calls the user tracking API, reads auto_timeline_points, and logs:
    //   ✅ PASSED  stop_name  passed_at
    //   🔜 FUTURE  stop_name  estimated_arrival
    // Returns snapshot for the JSON report.
    private function fetchAndLogTimelines(): array
    {
        $snapshot = [];
        foreach ($this->bookingIds as $bookingId) {
            $resp  = $this->httpGet(BASE_URL . '/api/farmer/vehicle_tracking/' . $bookingId, 'vendor');
            $stops = $resp['data']['auto_timeline_points'] ?? [];
            $driverName = $resp['data']['driver_location']['location_name'] ?? '—';

            $this->log("  ┌── Timeline #$bookingId  (driver now: $driverName) ──");
            $passedCount = 0;
            $lines = [];
            foreach ($stops as $stop) {
                $name      = $stop['name']              ?? '?';
                $passedAt  = $stop['passed_at']         ?? null;
                $eta       = $stop['estimated_arrival'] ?? null;
                $etaDate   = $stop['estimated_date']    ?? '';
                $fraction  = isset($stop['dist_fraction']) ? sprintf('%.3f', $stop['dist_fraction']) : '?';

                // ── Detect FUTURE→PASSED transition ──────────────────────
                // This is the key event: driver just passed this stop.
                // On the customer/employee screen the timeline item switches
                // from "upcoming" to "reached" — equivalent to a notification
                // update in the driver app.
                $wasAlreadyPassed = $this->lastTimelineState[$bookingId][$name] ?? false;

                if ($passedAt) {
                    $passedCount++;
                    try { $t = (new DateTime($passedAt))->format('h:i A'); } catch (\Exception $e) { $t = $passedAt; }
                    if (!$wasAlreadyPassed) {
                        // First time we see this stop as passed — highlight it
                        $this->log("  📍 STOP REACHED  #$bookingId  '$name'  at $t");
                    }
                    $lines[] = "  │  ✅ PASSED   [$fraction] $name  →  $t";
                    $this->lastTimelineState[$bookingId][$name] = true;
                } else {
                    $etaStr = $eta ? "$eta $etaDate" : '—';
                    $lines[] = "  │  🔜 FUTURE   [$fraction] $name  →  $etaStr";
                    $this->lastTimelineState[$bookingId][$name] = false;
                }
            }
            foreach ($lines as $line) $this->log($line);
            $this->log("  └── $passedCount/" . count($stops) . " stops passed ──");

            $snapshot[$bookingId] = [
                'driver_at'    => $driverName,
                'passed'       => $passedCount,
                'total'        => count($stops),
                'stops'        => $stops,
            ];
        }
        return $snapshot;
    }

    // ── Send location to EVERY booking ID ─────────────────────────────
    private function sendLocationToAll(float $lat, float $lng, string $name): array
    {
        $results = [];
        foreach ($this->bookingIds as $bookingId) {
            $results[$bookingId] = $this->httpPost(BASE_URL . ENDPOINT_UPDATE_LOCATION, [
                'booking_id'    => $bookingId,
                'lat'           => $lat,
                'lng'           => $lng,
                'location_name' => $name,
            ]);
        }
        return $results;
    }

    // ── Add tracking point to EVERY booking ID ─────────────────────────
    private function addTrackingPointToAll(float $lat, float $lng, string $name): void
    {
        foreach ($this->bookingIds as $bookingId) {
            $this->httpPost(BASE_URL . ENDPOINT_ADD_TRACKING, [
                'booking_id'    => $bookingId,
                'lat'           => $lat,
                'lng'           => $lng,
                'location_name' => $name,
                'title'         => $name,
                'status'        => 'current',
            ]);
        }
    }

    // ── Geocoding (mirrors Flutter driver app reverseGeocodeHttp) ─────
    // Uses a 200m-grid cache so nearby steps share the same API result.
    // Locality > sublocality > state, matching the Flutter implementation.
    private function geocodePosition(float $lat, float $lng): string
    {
        // Round to ~200m grid to maximise cache hits between consecutive steps
        $key = round($lat * 500) . ',' . round($lng * 500);
        if (isset($this->geocodeCache[$key])) {
            return $this->geocodeCache[$key];
        }

        try {
            $url = 'https://maps.googleapis.com/maps/api/geocode/json'
                . '?latlng=' . $lat . ',' . $lng
                . '&language=en&key=' . GOOGLE_KEY;
            $resp = $this->httpGetRaw($url);
            if (!$resp) return $this->geocodeCache[$key] = '—';

            $data = json_decode($resp, true);
            if (($data['status'] ?? '') !== 'OK') {
                return $this->geocodeCache[$key] = '—';
            }

            $locality = $subLocality = $adminArea = null;
            foreach (array_slice($data['results'] ?? [], 0, 5) as $result) {
                foreach ($result['address_components'] as $comp) {
                    $types = $comp['types'] ?? [];
                    if (in_array('locality', $types))
                        $locality    ??= $comp['long_name'];
                    if (in_array('sublocality_level_1', $types))
                        $subLocality ??= $comp['long_name'];
                    if (in_array('administrative_area_level_1', $types))
                        $adminArea   ??= $comp['short_name'];
                }
                if ($locality) break;  // locality found — precise enough
            }

            $place = $locality ?? $subLocality ?? '—';
            $name  = $adminArea ? "$place, $adminArea" : $place;
            return $this->geocodeCache[$key] = $name;
        } catch (\Exception $e) {
            return $this->geocodeCache[$key] = '—';
        }
    }

    // ── Driving model helpers ──────────────────────────────────────────
    private function getDrivingMode(): string
    {
        $r = mt_rand(1, 100);
        if ($r <= 15) return 'stop';
        if ($r <= 40) return 'city_slow';
        if ($r <= 70) return 'city_move';
        return 'highway';
    }

    private function getSpeedByMode(string $mode): float
    {
        return match ($mode) {
            'stop'      => 0,
            'city_slow' => mt_rand(5, 20),
            'city_move' => mt_rand(20, 40),
            'highway'   => mt_rand(40, 70),
            default     => 0,
        };
    }

    private function moveAlongRoute(float $meters): array
    {
        $this->progress += $meters;
        $this->progress  = max(0, min($this->progress, $this->totalRouteM));

        $covered = 0.0;
        for ($i = 1; $i < count($this->route); $i++) {
            $segLen = $this->haversine(
                $this->route[$i-1][0], $this->route[$i-1][1],
                $this->route[$i][0],   $this->route[$i][1]
            );
            if ($covered + $segLen >= $this->progress) {
                $ratio = $segLen > 0 ? ($this->progress - $covered) / $segLen : 0;
                return [
                    round($this->route[$i-1][0] + ($this->route[$i][0] - $this->route[$i-1][0]) * $ratio, 6),
                    round($this->route[$i-1][1] + ($this->route[$i][1] - $this->route[$i-1][1]) * $ratio, 6),
                ];
            }
            $covered += $segLen;
        }
        return end($this->route);
    }

    private function applyModeNoise(array $point, string $mode): array
    {
        $noiseM = match ($mode) {
            'highway'   => mt_rand(2, 5),
            'city_move' => mt_rand(5, 10),
            'city_slow' => mt_rand(10, 15),
            'stop'      => mt_rand(15, 25),
            default     => 5,
        };
        return $this->jitterMeters($point[0], $point[1], $noiseM);
    }

    private function jitterMeters(float $lat, float $lng, float $meters): array
    {
        if ($meters <= 0) return [$lat, $lng];
        $angle  = (mt_rand() / mt_getrandmax()) * 2 * M_PI;
        $r      = (mt_rand() / mt_getrandmax()) * $meters;
        $newLat = $lat + (cos($angle) * $r / 111320.0);
        $newLng = $lng + (sin($angle) * $r / (111320.0 * cos(deg2rad($lat))));
        return [round($newLat, 6), round($newLng, 6)];
    }

    // ── Validation ─────────────────────────────────────────────────────
    private array $validation = [];

    private function validateResults(): void
    {
        $this->log("─── Validating ───");
        $primaryId = $this->bookingIds[0];
        $resp      = $this->httpGet(BASE_URL . '/api/farmer/vehicle_tracking/' . $primaryId, 'vendor');
        $timeline  = $resp['data']['timeline'] ?? [];
        $this->log("Timeline points (booking #$primaryId): " . count($timeline));

        $maxJump = 0; $backwardCount = 0; $issues = [];
        $finalDrop = end($this->drops);

        for ($i = 1; $i < count($timeline); $i++) {
            $a = $timeline[$i-1]; $b = $timeline[$i];
            $d = $this->haversine($a['lat'], $a['lng'], $b['lat'], $b['lng']);
            if ($d > $maxJump) $maxJump = $d;
            if ($d > 150) $issues[] = "Jump " . round($d) . "m between #" . ($i-1) . " and #$i";
        }

        $endNearDrop = false;
        if ($timeline && $finalDrop) {
            $last = end($timeline);
            $endDist = $this->haversine($last['lat'], $last['lng'], $finalDrop['lat'], $finalDrop['lng']);
            $endNearDrop = $endDist < 200;
            $this->log("Final point → last drop: " . round($endDist) . "m");
        }

        $checks = [
            'no_large_jumps'        => count($issues) === 0,
            'final_point_near_drop' => $endNearDrop,
        ];
        foreach ($checks as $name => $pass) {
            $this->log(($pass ? '✓ PASS  ' : '✘ FAIL  ') . $name);
        }
        $this->validation = [
            'timeline_count' => count($timeline),
            'max_jump_m'     => round($maxJump, 1),
            'checks'         => $checks,
            'overall_pass'   => !in_array(false, $checks, true),
        ];
    }

    // ── Report ─────────────────────────────────────────────────────────
    private function writeReport(): void
    {
        $file = __DIR__ . '/simulate_report_' . implode('-', $this->bookingIds) . '_' . $this->scenario . '_' . date('Ymd_His') . '.json';
        file_put_contents($file, json_encode([
            'booking_ids' => $this->bookingIds,
            'scenario'    => $this->scenario,
            'drops'       => $this->drops,
            'route_km'    => round($this->totalRouteM / 1000, 2),
            'steps'       => $this->stepLogs,
            'validation'  => $this->validation,
            'finished_at' => date('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->log("Report: " . basename($file));
        $this->log(($this->validation['overall_pass'] ?? false) ? '✅ OVERALL: PASS' : '❌ OVERALL: FAIL');
    }

    // ── HTTP helpers ───────────────────────────────────────────────────
    private function httpPost(string $url, array $body): array
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($body),
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: Bearer ' . VENDOR_TOKEN,
                ],
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $raw  = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $decoded = json_decode($raw, true) ?: ['raw' => $raw];
            if ($code >= 200 && $code < 500) { $decoded['_http_code'] = $code; return $decoded; }
            if ($attempt < 3) sleep(1);
        }
        return ['status' => false, 'message' => 'http_failed', '_http_code' => $code ?? 0];
    }

    private function httpGet(string $url, string $tokenType = 'farmer'): ?array
    {
        $raw = $this->httpGetRaw($url, true, $tokenType);
        return $raw ? json_decode($raw, true) : null;
    }

    private function httpGetRaw(string $url, bool $auth = false, string $tokenType = 'farmer'): ?string
    {
        $ch = curl_init($url);
        $headers = ['Accept: application/json'];
        if ($auth) $headers[] = 'Authorization: Bearer ' . ($tokenType === 'vendor' ? VENDOR_TOKEN : FARMER_TOKEN);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if (!$raw) { $this->log("cURL GET failed HTTP=$code err=$err"); return null; }
        $this->log("cURL GET ok: HTTP=$code " . strlen($raw) . " bytes");
        return $raw;
    }

    // ── Math ───────────────────────────────────────────────────────────
    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $R * asin(sqrt($a));
    }

    private function log(string $msg): void
    {
        echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
    }
}

// ── CLI ────────────────────────────────────────────────────────────────
$opts = getopt('', ['booking::', 'bookings::', 'scenario:', 'imin::', 'imax::', 'points::', 'driverloc::', 'tcheck::']);

// Accept either --booking=606 or --bookings=608,609,610
$rawIds = $opts['bookings'] ?? $opts['booking'] ?? '';
if (!$rawIds || !isset($opts['scenario'])) {
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  php simulate.php --booking=606  --scenario=clean_drive\n");
    fwrite(STDERR, "  php simulate.php --bookings=608,609,610 --scenario=clean_drive --imin=2 --imax=4\n\n");
    fwrite(STDERR, "Options:\n");
    fwrite(STDERR, "  --bookings   comma-separated booking IDs in priority order\n");
    fwrite(STDERR, "  --imin       min seconds between GPS updates (default 7)\n");
    fwrite(STDERR, "  --imax       max seconds between GPS updates (default 12)\n");
    fwrite(STDERR, "  --points     max simulation steps (default 300)\n");
    fwrite(STDERR, "  --driverloc  lat,lng override for driver start position\n");
    fwrite(STDERR, "  --tcheck     fetch+print timeline every N accepted steps (default 5)\n\n");
    fwrite(STDERR, "Scenarios: clean_drive, gps_drift_stationary, network_loss\n");
    exit(1);
}

$bookingIds = array_map('intval', array_filter(explode(',', (string)$rawIds)));
if (empty($bookingIds)) { fwrite(STDERR, "No valid booking IDs.\n"); exit(1); }

$validScenarios = ['clean_drive', 'gps_drift_stationary', 'network_loss'];
if (!in_array($opts['scenario'], $validScenarios, true)) {
    fwrite(STDERR, "Unknown scenario: {$opts['scenario']}\nValid: " . implode(', ', $validScenarios) . "\n");
    exit(1);
}

$config = [];
if (isset($opts['imin']))      $config['interval_min'] = (int)$opts['imin'];
if (isset($opts['imax']))      $config['interval_max'] = (int)$opts['imax'];
if (isset($opts['points']))    $config['max_points']   = (int)$opts['points'];
if (isset($opts['driverloc'])) $config['driverloc']           = (string)$opts['driverloc'];
if (isset($opts['tcheck']))   $config['timeline_check_every'] = (int)$opts['tcheck'];

try {
    (new RouteSimulator($bookingIds, $opts['scenario'], $config))->run();
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(2);
}
