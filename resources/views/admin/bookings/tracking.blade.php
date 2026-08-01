@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-map-marker-alt mr-2"></i>Vehicle Tracking - Booking #{{ $booking->id }}
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('bookings.index') }}">Bookings</a></li>
                    <li class="breadcrumb-item active">Vehicle Tracking</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body p-0">
                        <div style="position:relative;">
                        <div id="trackingMap" style="width: 100%; height: 550px; border-radius: 4px;"></div>
                        <button id="recenterBtn" onclick="recenterMap()" title="Re-center on vehicle"
                            style="display:none; position:absolute; bottom:16px; right:16px; z-index:10;
                                   background:#0077C8; color:#fff; border:none; border-radius:22px;
                                   padding:8px 16px; font-size:13px; font-weight:600; cursor:pointer;
                                   box-shadow:0 2px 8px rgba(0,0,0,0.25); align-items:center; gap:6px;">
                            <i class="fas fa-crosshairs"></i> Re-center
                        </button>
                    </div>
                    </div>
                    <div class="card-footer d-flex justify-content-between align-items-center">
                        <small class="text-muted"><i class="fas fa-sync-alt mr-1"></i> Live position every 3 s · Route every 2 min</small>
                        <button class="btn btn-sm btn-outline-primary" onclick="refreshTracking()">
                            <i class="fas fa-redo mr-1"></i> Refresh Now
                        </button>
                    </div>
                </div>

                <!-- Location Timeline -->
                <div class="card mt-3">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 font-weight-bold"><i class="fas fa-route mr-2"></i>Location Timeline</h6>
                        <button id="refreshRouteBtn" class="btn btn-sm btn-outline-warning" onclick="refreshRouteCache()" title="Force-regenerate intermediate stops (use when stops are missing)">
                            <i class="fas fa-map-signs mr-1"></i> Refresh Route
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div id="locationTimeline" class="p-3">
                            <p class="text-muted text-center mb-0">Loading timeline...</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white py-2">
                        <h6 class="mb-0"><i class="fas fa-user mr-1"></i> Driver Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-2"><small class="text-muted">Driver Name</small>
                            <div class="font-weight-bold" id="driverName">{{ $booking->driver_name ?? 'N/A' }}</div></div>
                        <div class="mb-2"><small class="text-muted">Mobile</small>
                            <div id="driverPhone">{{ $booking->driver_mobile ?? 'N/A' }}</div></div>
                        <div class="mb-2"><small class="text-muted">Vehicle Number</small>
                            <div class="font-weight-bold" id="vehicleNumber">{{ $booking->vehicle_number ?? 'N/A' }}</div></div>
                        <div class="mb-2"><small class="text-muted">Priority</small>
                            <div class="font-weight-bold">{{ $booking->priority ?? 'N/A' }}</div></div>
                        <div class="mb-2"><small class="text-muted">Drop Location</small>
                            <div class="font-weight-bold">{{ $booking->dropping_location ?? $booking->delivery_location ?? 'N/A' }}</div></div>
                        <div><small class="text-muted">Current Location</small>
                            <div id="currentLocation" class="text-primary">Loading...</div>
                            <small class="text-muted" id="locationUpdatedAt"></small></div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header bg-info text-white py-2">
                        <h6 class="mb-0"><i class="fas fa-truck mr-1"></i> Booking Info</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-2"><small class="text-muted">Customer</small>
                            <div class="font-weight-bold">{{ $booking->customer_name }}</div>
                            <small>{{ $booking->customer_mobile }}</small></div>
                        <div class="mb-2"><small class="text-muted">Hatchery</small>
                            <div>{{ $booking->hatchery_name }}</div></div>
                        <div class="mb-2"><small class="text-muted">Pickup</small>
                            <div id="pickupName" class="text-success"><i class="fas fa-circle mr-1" style="font-size: 8px;"></i>Loading...</div></div>
                        <div><small class="text-muted">Destination</small>
                            <div id="dropName" class="text-danger"><i class="fas fa-circle mr-1" style="font-size: 8px;"></i>Loading...</div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .loc-timeline { position: relative; padding: 10px 0; margin: 0; }
    .loc-timeline-item {
        position: relative; display: flex; align-items: flex-start;
        padding-left: 50px; min-height: 44px;
    }
    .loc-timeline-item .tl-line {
        position: absolute; left: 19px; top: 28px; bottom: 0;
        width: 2.5px; background: #e0e0e0;
    }
    .loc-timeline-item.completed .tl-line,
    .loc-timeline-item.current .tl-line { background: #4CAF50; }
    .loc-timeline-item:last-child .tl-line { display: none; }

    .loc-timeline-item .tl-icon {
        position: absolute; left: 4px; top: 2px; width: 32px; height: 32px;
        display: flex; align-items: center; justify-content: center;
        z-index: 2; border-radius: 50%;
    }
    .loc-timeline-item.type-pickup .tl-icon { background: #4CAF50; color: #fff; font-size: 14px; }
    .loc-timeline-item.type-sub-completed .tl-icon {
        background: transparent; width: 14px; height: 14px;
        left: 13px; top: 7px; border: 2.5px solid #4CAF50;
    }
    .loc-timeline-item.type-current .tl-icon { background: #4CAF50; color: #fff; font-size: 13px; }
    .loc-timeline-item.type-sub-pending .tl-icon {
        background: transparent; width: 22px; height: 22px;
        left: 9px; top: 4px; border: 2.5px solid #bdbdbd;
    }
    .loc-timeline-item.type-drop .tl-icon { background: #2196F3; color: #fff; font-size: 14px; }

    .loc-timeline-item .tl-content { flex: 1; padding-bottom: 18px; padding-top: 4px; }
    .loc-timeline-item .tl-title { font-size: 14px; font-weight: 700; color: #222; margin-bottom: 2px; line-height: 1.3; }
    .loc-timeline-item .tl-subtitle { font-size: 12.5px; color: #666; line-height: 1.4; }

    .loc-timeline-item.type-sub-completed .tl-content,
    .loc-timeline-item.type-sub-pending .tl-content { padding-bottom: 14px; padding-top: 0; }
    .loc-timeline-item.type-sub-completed .tl-title { font-size: 13px; font-weight: 500; color: #444; }
    .loc-timeline-item.type-sub-pending .tl-title { font-size: 13.5px; font-weight: 600; color: #888; }

    .loc-timeline-item .tl-time { text-align: right; min-width: 90px; padding-top: 4px; flex-shrink: 0; }
    .loc-timeline-item .tl-time-text { font-size: 13px; color: #333; font-weight: 600; }
    .loc-timeline-item .tl-date-text { font-size: 11.5px; color: #999; }

    .loc-timeline-item.type-sub-pending .tl-time-text { color: #aaa; }
    .loc-timeline-item.type-drop.pending-status .tl-title,
    .loc-timeline-item.type-drop.pending-status .tl-subtitle { color: #999; }
    .loc-timeline-item.type-drop.pending-status .tl-time-text { color: #aaa; }

    .loc-timeline-item.type-sub-completed .tl-line { left: 19px; top: 18px; }
    .loc-timeline-item.type-sub-pending .tl-line { left: 19px; top: 26px; }

    /* Clickable items */
    .loc-timeline-item.main-clickable { cursor: pointer; }
    .loc-timeline-item.main-clickable:hover .tl-title { color: #007bff; }
    .loc-timeline-item.main-clickable .tl-expand { display: none; }
    .loc-timeline-item.sub-hidden { display: none !important; }

    /* Sub-items: smaller dots, indented slightly */
    .loc-timeline-item.sub-item .tl-icon {
        width: 10px; height: 10px; left: 15px; top: 5px;
    }
    .loc-timeline-item.sub-item.completed .tl-icon { border: 2px solid #81C784; background: transparent; }
    .loc-timeline-item.sub-item.pending-sub .tl-icon { border: 2px solid #ccc; background: transparent; }
    .loc-timeline-item.sub-item .tl-content { padding-bottom: 10px; padding-top: 0; }
    .loc-timeline-item.sub-item .tl-title { font-size: 12.5px; font-weight: 400; color: #777; }
    .loc-timeline-item.sub-item .tl-time-text { font-size: 12px; font-weight: 500; color: #999; }
    .loc-timeline-item.sub-item .tl-line { left: 19px; top: 14px; }
    .loc-timeline-item.sub-item.completed .tl-line { background: #4CAF50; }

    /* Auto-generated hourly timeline points */
    .loc-timeline-item.type-auto-completed .tl-icon {
        background: transparent; width: 12px; height: 12px;
        left: 14px; top: 8px; border: 2px solid #81C784; border-radius: 50%;
    }
    .loc-timeline-item.type-auto-pending .tl-icon {
        background: transparent; width: 12px; height: 12px;
        left: 14px; top: 8px; border: 2px solid #ccc; border-radius: 50%;
    }
    .loc-timeline-item.type-auto-completed .tl-content,
    .loc-timeline-item.type-auto-pending .tl-content { padding-bottom: 12px; padding-top: 0; }
    .loc-timeline-item.type-auto-completed .tl-title { font-size: 12.5px; font-weight: 500; color: #555; }
    .loc-timeline-item.type-auto-pending .tl-title { font-size: 12.5px; font-weight: 500; color: #999; }
    .loc-timeline-item.type-auto-completed .tl-line { left: 19px; top: 18px; background: #4CAF50; }
    .loc-timeline-item.type-auto-pending .tl-line { left: 19px; top: 18px; }
    .loc-timeline-item.type-auto-completed .tl-time-text { font-size: 12px; font-weight: 500; color: #666; }
    .loc-timeline-item.type-auto-pending .tl-time-text { font-size: 12px; font-weight: 500; color: #aaa; }

    /* Deviation badge */
    .deviation-badge { display: inline-block; background: #FF9800; color: #fff; font-size: 12px; padding: 4px 12px; border-radius: 12px; margin: 8px 0; }
</style>
@endpush

@push('scripts')
<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_api_key') }}&libraries=geometry"></script>
<script>
    var map, driverMarker, pickupMarker, dropMarker;
    var directionsService;
    var refreshInterval;
    var _geocoder = null;
    var _routePath = null;
    var _totalRouteDistance = 0;
    var _driverDistOnRoute = 0;
    var _routeDuration = 0;
    var _pickupStartTime = null;
    var _subPointsCache = {};
    var _expandedSections = {};
    var routeWaypointMarkers = [];
    var routePolylines = [];
    var routeRenderVersion = 0;
    var _deviationThreshold = 3000; // 3km in meters
    var _isDeviated = false;

    // ─── In-memory route cache (per page session) ───
    var _cachedDirectionsResult = null;
    var _cachedRouteKey = null;
    var _cachedCustomRouteKey = null;
    var _routeInitialized = false;
    var _autoTimelinePoints = [];
    var _autoPointsGenerating = false;
    var _lastData = null;
    var _trackingHistory = [];
    var _lastDeviationLat = null;
    var _lastDeviationLng = null;

    // ─── Live tracking state (mirrors Flutter's VehicleTrackingMapScreenState) ───
    var _lastDriverPos    = null;   // previous driver LatLng (for bearing + animation)
    var _lastBearing      = 0;      // last computed truck heading (degrees)
    var _animFrameId      = null;   // requestAnimationFrame handle for smooth animation
    var _isFollowing      = true;   // camera follow mode on/off
    var _pollInterval     = 3000;   // 3-second live poll
    var _timelineInterval = 120000; // timeline/route rebuild every 2 min
    var _liveTimer        = null;
    var _timelineTimer    = null;
    var _isFetching       = false;  // guard against overlapping fetches

    // ─── Green-line live extension ───
    // References to the two green polylines (white outline + green fill) so the
    // silent poll can extend them when the driver moves, without a Directions API call.
    var _greenPolylines = [];

    // ─────────────────────────────────────────────────────────────────────────
    // PERSISTENT CACHE  (localStorage, 24 h TTL)
    // Mirrors Flutter's AppCacheHelper + GoogleMapsService caching strategy.
    //
    // Every Directions / Geocoding call costs money (~$5 per 1,000).
    // Without persistence, every page refresh re-fires all of them.
    //
    // Cache-key design (same as Flutter):
    //   • Pickup / Drop coords  → 4 decimal places  (~11 m precision, _r4)
    //   • Driver position       → 3 decimal places  (~110 m, _r3)
    //     Polls within one city block reuse the same key → free.
    //   • Geocode input         → 3 decimal places  (_r3)
    //
    // 24 h TTL: fresh enough for changed pickups/roads; persistent enough
    // that back-and-re-enter within the same day costs nothing.
    // ─────────────────────────────────────────────────────────────────────────
    var _LS_TTL   = 30 * 24 * 60 * 60 * 1000; // 30 days in ms
    var _LS_PFX   = 'bstrack_v1:b{{ $booking->id }}:'; // per-booking namespace

    function _r4(n) { return parseFloat(n).toFixed(4); }
    function _r3(n) { return parseFloat(n).toFixed(3); }

    /** Read a cache entry. Returns parsed data or null if missing / expired. */
    function _lsGet(key) {
        try {
            var raw = localStorage.getItem(_LS_PFX + key);
            if (!raw) return null;
            var entry = JSON.parse(raw);
            if (!entry || !entry.t || entry.d === undefined) return null;
            if (Date.now() - entry.t > _LS_TTL) {
                localStorage.removeItem(_LS_PFX + key);
                return null;
            }
            return entry.d;
        } catch (e) { return null; }
    }

    /** Write a cache entry. Silently ignores quota errors. */
    function _lsPut(key, data) {
        try {
            localStorage.setItem(_LS_PFX + key, JSON.stringify({ t: Date.now(), d: data }));
        } catch (e) { /* storage quota exceeded — non-fatal */ }
    }

    /** Delete a specific cache entry (used on deviation / route change). */
    function _lsDel(key) {
        try { localStorage.removeItem(_LS_PFX + key); } catch (e) {}
    }

    /**
     * Serialize a google.maps.DirectionsResult to a plain JSON-safe object
     * so it can be stored in localStorage and rehydrated later.
     * Only the fields consumed by processRouteTimeline / _drawSplitRoute
     * are stored (overview_path, leg durations).
     */
    function _serializeDirectionsResult(result) {
        var route = result.routes[0];
        var legs = route.legs.map(function(leg) {
            return { duration_value: leg.duration.value };
        });
        var path = route.overview_path.map(function(ll) {
            return { lat: ll.lat(), lng: ll.lng() };
        });
        return { path: path, legs: legs };
    }

    /**
     * Rehydrate a serialized directions result back into the shape that
     * processRouteTimeline and _drawSplitRoute expect.
     * Returns null if the payload is corrupt / old schema.
     */
    function _deserializeDirectionsResult(data) {
        try {
            var path = data.path.map(function(p) {
                return new google.maps.LatLng(p.lat, p.lng);
            });
            var legs = data.legs.map(function(l) {
                return { duration: { value: l.duration_value } };
            });
            return { routes: [{ overview_path: path, legs: legs }] };
        } catch (e) { return null; }
    }

    /** Build a rounded route cache key (mirrors Flutter's _r4 key scheme). */
    function _buildDirectionsCacheKey(origin, destination, stops) {
        var key = 'dir:'
            + _r4(origin.lat()) + ',' + _r4(origin.lng()) + '>'
            + _r4(destination.lat()) + ',' + _r4(destination.lng());
        stops.forEach(function(s) { key += '|' + _r4(s.lat) + ',' + _r4(s.lng); });
        return key;
    }

    function initMap() {
        map = new google.maps.Map(document.getElementById('trackingMap'), {
            zoom: 12, center: { lat: 17.3850, lng: 78.4867 },
            mapTypeControl: true, streetViewControl: false, fullscreenControl: true,
            styles: [
                { featureType: "poi", stylers: [{ visibility: "off" }] },
                { featureType: "transit", stylers: [{ visibility: "off" }] }
            ]
        });
        directionsService = new google.maps.DirectionsService();
        _geocoder = new google.maps.Geocoder();

        // Detect when user manually pans — pause follow mode
        map.addListener('dragstart', function() {
            _isFollowing = false;
            document.getElementById('recenterBtn').style.display = 'flex';
        });

        // First full load — poll starts as soon as it completes
        _fullRefresh();

        // 2-minute full refresh — rebuilds timeline + route (may hit Directions API if cache cold)
        _timelineTimer = setInterval(_fullRefresh, _timelineInterval);
    }

    /**
     * Silent poll — fetches latest driver position only.
     * Updates marker (animated) + polyline split. Does NOT rebuild timeline or
     * call Directions API. Mirrors Flutter's silent: true fetchSpecificVehicleTracking.
     */
    var _isPollFetching = false;
    function _silentPoll() {
        if (_isPollFetching) return;
        _isPollFetching = true;
        var url = '{{ route("bookings.tracking-data", $booking->id) }}?_t=' + Date.now();
        fetch(url, { cache: 'no-store', credentials: 'same-origin' })
            .then(function(res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function(data) {
                _trackingHistory = data.tracking_history || [];
                _updateDriverMarker(data);
                updateInfoPanel(data);
            })
            .catch(function(err) { console.error('[POLL] ERROR:', err); })
            .finally(function() { _isPollFetching = false; });
    }
    // Use setInterval — never breaks even if a fetch hangs or throws
    function _startLivePoll() {
        if (_liveTimer) return; // already running
        _liveTimer = setInterval(_silentPoll, _pollInterval);
    }

    /**
     * Full refresh — rebuilds markers, route polylines, timeline, info panel.
     * Called on first load and every 2 minutes.
     */
    function _fullRefresh() {
        if (_isFetching) return;
        _isFetching = true;
        fetch('{{ route("bookings.tracking-data", $booking->id) }}?_t=' + Date.now(), { cache: 'no-store' })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                _trackingHistory = data.tracking_history || [];
                updateMarkers(data);
                drawRoute(data);
                updateInfoPanel(data);
            })
            .catch(function(err) { console.error('Tracking fetch error:', err); })
            .finally(function() {
                _isFetching = false;
                _startLivePoll(); // idempotent — starts interval only once
            });
    }

    // Keep legacy name so the "Refresh Now" button still works
    function refreshTracking() { _fullRefresh(); }

    // Force-clear the server-side route cache and regenerate intermediate stops.
    // Use this when the timeline shows no intermediate stops for an active journey.
    function refreshRouteCache() {
        var btn = document.getElementById('refreshRouteBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Regenerating...';

        fetch('{{ route("bookings.refresh-route", $booking) }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-map-signs mr-1"></i> Refresh Route';
            if (data.success) {
                // Show result toast
                var color = data.stop_count > 0 ? '#28a745' : '#dc3545';
                var toast = document.createElement('div');
                toast.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;padding:12px 20px;border-radius:8px;color:#fff;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,0.2);background:' + color;
                toast.textContent = data.message;
                document.body.appendChild(toast);
                setTimeout(function() { toast.remove(); }, 4000);
                // Refresh the tracking data to show updated timeline
                if (data.stop_count > 0) _fullRefresh();
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-map-signs mr-1"></i> Refresh Route';
        });
    }

    function updateMarkers(data) {
        clearRouteWaypointMarkers();
        var bounds = new google.maps.LatLngBounds();
        var hasPoints = false;
        if (data.pickup.lat && data.pickup.lng) {
            var pos = { lat: data.pickup.lat, lng: data.pickup.lng };
            if (!pickupMarker) {
                pickupMarker = new google.maps.Marker({ position: pos, map: map, title: 'Pickup: ' + data.pickup.name,
                    icon: { url: 'https://maps.google.com/mapfiles/ms/icons/green-dot.png', scaledSize: new google.maps.Size(40, 40) } });
                var info = new google.maps.InfoWindow({ content: '<b>Pickup</b><br>' + data.pickup.name });
                pickupMarker.addListener('click', function() { info.open(map, pickupMarker); });
            } else pickupMarker.setPosition(pos);
            bounds.extend(pos); hasPoints = true;
        }
        if (data.drop.lat && data.drop.lng) {
            var pos = { lat: data.drop.lat, lng: data.drop.lng };
            if (!dropMarker) {
                dropMarker = new google.maps.Marker({ position: pos, map: map, title: 'Destination: ' + data.drop.name,
                    icon: { url: 'https://maps.google.com/mapfiles/ms/icons/red-dot.png', scaledSize: new google.maps.Size(40, 40) } });
                var info = new google.maps.InfoWindow({ content: '<b>Destination</b><br>' + data.drop.name });
                dropMarker.addListener('click', function() { info.open(map, dropMarker); });
            } else dropMarker.setPosition(pos);
            bounds.extend(pos); hasPoints = true;
        }
        if (data.driver_location.lat && data.driver_location.lng) {
            var pos = { lat: data.driver_location.lat, lng: data.driver_location.lng };
            var newLatLng = new google.maps.LatLng(pos.lat, pos.lng);
            if (!driverMarker) {
                driverMarker = new google.maps.Marker({
                    position: pos, map: map,
                    title: 'Driver: ' + data.driver_details.driver_name,
                    icon: getTruckMarkerIcon(_lastBearing),
                    zIndex: 999
                });
                var info = new google.maps.InfoWindow({ content: '<b>' + data.driver_details.driver_name + '</b><br>' + data.driver_details.vehicle_number + '<br><small>' + data.driver_location.name + '</small>' });
                driverMarker.addListener('click', function() { info.open(map, driverMarker); });
                _lastDriverPos = newLatLng;
            } else {
                var from = _lastDriverPos || driverMarker.getPosition();
                var moved = from ? google.maps.geometry.spherical.computeDistanceBetween(from, newLatLng) : 0;
                if (from && moved >= 2) {
                    _animateMarkerTo(from, newLatLng);
                } else {
                    driverMarker.setPosition(pos);
                    driverMarker.setIcon(getTruckMarkerIcon(_lastBearing));
                }
            }
            bounds.extend(pos); hasPoints = true;
        }

        getSortedRouteWaypoints(data).forEach(function(stop, index) {
            if (!(stop.lat && stop.lng)) return;

            var pos = { lat: stop.lat, lng: stop.lng };
            var marker = new google.maps.Marker({
                position: pos,
                map: map,
                title: 'Stop ' + (stop.priority || (index + 1)),
                label: {
                    text: String(stop.priority || (index + 1)),
                    color: '#ffffff',
                    fontSize: '11px',
                    fontWeight: '700'
                },
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    fillColor: stop.status === 5 ? '#43A047' : '#1E88E5',
                    fillOpacity: 1,
                    strokeColor: '#ffffff',
                    strokeWeight: 2,
                    scale: 11
                }
            });
            var info = new google.maps.InfoWindow({
                content: '<b>Priority ' + (stop.priority || (index + 1)) + '</b><br>' + (stop.name || 'Route stop')
            });
            marker.addListener('click', function() { info.open(map, marker); });
            routeWaypointMarkers.push(marker);
            bounds.extend(pos);
            hasPoints = true;
        });

        if (hasPoints && !window._boundsSet) { map.fitBounds(bounds, 60); window._boundsSet = true; }
    }

    // In-memory session key uses rounded coords (4 dp) — same as Flutter _r4.
    function _buildRouteKey(origin, destination, stops) {
        var key = _r4(origin.lat()) + ',' + _r4(origin.lng()) + '|'
                + _r4(destination.lat()) + ',' + _r4(destination.lng());
        stops.forEach(function(s) { key += '|' + _r4(s.lat) + ',' + _r4(s.lng); });
        return key;
    }

    function drawRoute(data) {
        var origin = null, destination = null;
        if (data.pickup.lat && data.pickup.lng) origin = new google.maps.LatLng(data.pickup.lat, data.pickup.lng);
        if (data.drop.lat && data.drop.lng) destination = new google.maps.LatLng(data.drop.lat, data.drop.lng);

        var driverPoint = null;
        if (data.driver_location.lat && data.driver_location.lng) {
            driverPoint = new google.maps.LatLng(data.driver_location.lat, data.driver_location.lng);
        }
        if (!origin || !destination) return;

        var pendingStops = getSortedRouteWaypoints(data).filter(function(stop) {
            return stop.is_before && stop.status !== 5 && stop.status !== 6 && stop.lat && stop.lng;
        });

        var routeKey = _buildRouteKey(origin, destination, pendingStops);
        var lsCacheKey = _buildDirectionsCacheKey(origin, destination, pendingStops);

        // ── 1. In-memory hit (same page session, route unchanged) ──
        if (_cachedDirectionsResult && _cachedRouteKey === routeKey && !_isDeviated) {
            processRouteTimeline(_cachedDirectionsResult, data);
            drawCustomMapRoute(data, origin, driverPoint, destination, pendingStops);
            return;
        }

        // ── 2. localStorage hit (page refresh / back-and-re-enter, 24 h TTL) ──
        if (!_isDeviated) {
            var lsData = _lsGet(lsCacheKey);
            if (lsData) {
                var rehydrated = _deserializeDirectionsResult(lsData);
                if (rehydrated) {
                    _cachedDirectionsResult = rehydrated;
                    _cachedRouteKey = routeKey;
                    processRouteTimeline(rehydrated, data);
                    drawCustomMapRoute(data, origin, driverPoint, destination, pendingStops);
                    return;
                }
            }
        }

        // ── 3. Live Directions API call (first load, deviation, cache miss) ──
        directionsService.route({
            origin: origin,
            destination: destination,
            waypoints: (driverPoint ? [{ location: driverPoint, stopover: false }] : []).concat(
                pendingStops.map(function(stop) {
                    return { location: new google.maps.LatLng(stop.lat, stop.lng), stopover: true };
                })
            ),
            travelMode: google.maps.TravelMode.DRIVING,
            optimizeWaypoints: false
        }, function(result, status) {
            if (status !== 'OK') return;
            _cachedDirectionsResult = result;
            _cachedRouteKey = routeKey;
            _lsPut(lsCacheKey, _serializeDirectionsResult(result)); // persist for 24 h
            processRouteTimeline(result, data);
            // Draw AFTER processRouteTimeline so _routePath is set → route-slice works on first load
            drawCustomMapRoute(data, origin, driverPoint, destination, pendingStops);
        });
    }

    function drawCustomMapRoute(data, origin, driverPoint, destination, pendingStops) {
        // Always include every pending priority stop (sorted by priority) in the
        // blue route — that's how the user expects multi-drop visualization to
        // look (e.g. viewing priority 2 must still draw through priority 1).
        // Delivered/cancelled stops were already filtered upstream by status.
        var forwardPendingStops = pendingStops;

        var customKey = origin.lat() + ',' + origin.lng() + '|' + destination.lat() + ',' + destination.lng();
        forwardPendingStops.forEach(function(s) { customKey += '|' + s.lat + ',' + s.lng; });
        // Include driver position (3 dp ≈ 110 m) so the road-snapped green line
        // is rebuilt on every full-refresh as the driver advances.
        if (driverPoint) customKey += '|drv:' + _r3(driverPoint.lat()) + ',' + _r3(driverPoint.lng());

        if (_cachedCustomRouteKey === customKey && !_isDeviated && routePolylines.length > 0) {
            return;
        }

        var renderVersion = ++routeRenderVersion;

        // Build completed breadcrumb points from tracking history (actual path taken).
        // No cap needed — we draw directly as a polyline (no Directions API for green line).
        // Mirrors Flutter's _driverBreadcrumbs approach.
        var completedViaPoints = [];
        if (data.tracking_history && data.tracking_history.length > 0) {
            var rawHistory = data.tracking_history.filter(function(t) { return t.lat && t.lng; });
            rawHistory.forEach(function(t) {
                completedViaPoints.push(new google.maps.LatLng(t.lat, t.lng));
            });
        } else {
            (data.timeline || []).forEach(function(item) {
                if (item.lat && item.lng) completedViaPoints.push(new google.maps.LatLng(item.lat, item.lng));
            });
        }
        if (driverPoint) completedViaPoints.push(driverPoint);

        var pendingRequests = 0;
        var nextPolylines = [];

        function addLine(config) {
            nextPolylines.push(new google.maps.Polyline(Object.assign({ map: map }, config)));
        }

        var _nextGreenPolylines = [];

        function finalizeRender() {
            if (pendingRequests > 0 || renderVersion !== routeRenderVersion) return;
            clearRoutePolylines();
            routePolylines = nextPolylines;
            _cachedCustomRouteKey = customKey;
            _greenPolylines = _nextGreenPolylines; // expose for silent-poll extension
        }

        // GREEN: completed path.
        // ── PRIMARY: route-slice (mirrors Flutter's _currentSegmentIndex approach) ──
        // Snap the driver onto the planned _routePath polyline and draw the route
        // from pickup to that snapped position. This guarantees the green line
        // follows the road exactly — no building cuts, no loops, no overlap with blue.
        // ── FALLBACK: RDP breadcrumbs (first load before _routePath is available) ──
        var greenPath;
        if (_routePath && _routePath.length >= 2 && driverPoint) {
            var snap = _snapDriverToRoute(driverPoint);
            // Route slice: pickup → all route vertices up to driver's segment → snapped driver pos
            greenPath = _routePath.slice(0, snap.idx + 1).concat([snap.snapped]);
        } else {
            // Fallback: draw GPS breadcrumbs simplified (cache-miss first load)
            greenPath = _rdpSimplify([origin].concat(completedViaPoints), 10);
        }
        if (greenPath.length >= 2) {
            var gWhite = new google.maps.Polyline({ map: map, path: greenPath, strokeColor: '#ffffff', strokeOpacity: 0.7, strokeWeight: 8, zIndex: 1 });
            var gGreen = new google.maps.Polyline({ map: map, path: greenPath, strokeColor: '#2E7D32', strokeOpacity: 0.95, strokeWeight: 5, zIndex: 2 });
            nextPolylines.push(gWhite);
            nextPolylines.push(gGreen);
            _nextGreenPolylines = [gWhite, gGreen];
        }

        // BLUE DASHED: pending path (snapped driver pos → forward stops → destination)
        // Use snapped driver position so blue starts exactly where green ends — no gap/overlap.
        var orderedFutureStops = forwardPendingStops.map(function(stop) {
            return new google.maps.LatLng(stop.lat, stop.lng);
        });
        var _blueSnap = (_routePath && _routePath.length >= 2 && driverPoint)
            ? _snapDriverToRoute(driverPoint).snapped
            : null;
        var pendingOrigin = _blueSnap || driverPoint || origin;
        if (pendingOrigin && destination) {
            pendingRequests++;
            getPriorityRouteSegments(pendingOrigin, orderedFutureStops, destination, function(segments) {
                if (renderVersion !== routeRenderVersion) return;
                segments.forEach(function(path) {
                    if (path.length < 2) return;
                    addLine({ path: path, strokeColor: '#ffffff', strokeOpacity: 0.9, strokeWeight: 10, zIndex: 3 });
                    addLine({
                        path: path, strokeColor: '#1565C0', strokeOpacity: 0, strokeWeight: 6, zIndex: 4,
                        icons: [{ icon: { path: 'M 0,-1 0,1', strokeOpacity: 1, strokeColor: '#42A5F5', scale: 4 }, offset: '0', repeat: '18px' }]
                    });
                });
                pendingRequests--;
                finalizeRender();
            });
        }

        finalizeRender();
    }

    function getRoutePath(origin, destination, waypointLatLngs, useViaWaypoints, callback) {
        var normalizedWaypoints = (waypointLatLngs || []).filter(function(point) {
            if (!point || !destination) return false;
            if (point.lat() === destination.lat() && point.lng() === destination.lng()) return false;
            if (origin && point.lat() === origin.lat() && point.lng() === origin.lng()) return false;
            return true;
        }).map(function(point) {
            return { location: point, stopover: !useViaWaypoints };
        });

        directionsService.route({
            origin: origin,
            destination: destination,
            waypoints: normalizedWaypoints,
            travelMode: google.maps.TravelMode.DRIVING,
            optimizeWaypoints: false
        }, function(result, status) {
            if (status === 'OK' && result.routes[0]) {
                callback(result.routes[0].overview_path);
            } else {
                var fallback = [origin].concat((waypointLatLngs || [])).concat([destination]);
                callback(dedupeLatLngPath(fallback));
            }
        });
    }

    function getPriorityRouteSegments(origin, orderedStops, destination, callback) {
        var checkpoints = [origin].concat(orderedStops || []).concat([destination]);
        if (checkpoints.length < 2) {
            callback([]);
            return;
        }

        var segments = [];
        var segmentIndex = 0;

        function nextSegment() {
            if (segmentIndex >= checkpoints.length - 1) {
                callback(segments);
                return;
            }

            var segmentOrigin = checkpoints[segmentIndex];
            var segmentDestination = checkpoints[segmentIndex + 1];

            getRoutePath(segmentOrigin, segmentDestination, [], false, function(segmentPath) {
                segments.push(dedupeLatLngPath(segmentPath.length ? segmentPath : [segmentOrigin, segmentDestination]));
                segmentIndex++;
                nextSegment();
            });
        }

        nextSegment();
    }

    function getSortedRouteWaypoints(data) {
        return (data.route_waypoints || []).slice().sort(function(a, b) {
            return (a.priority || 0) - (b.priority || 0);
        });
    }

    function dedupeLatLngPath(points) {
        var unique = [];
        points.forEach(function(point) {
            if (!point) return;
            var last = unique[unique.length - 1];
            if (!last || last.lat() !== point.lat() || last.lng() !== point.lng()) {
                unique.push(point);
            }
        });
        return unique;
    }

    /**
     * Ramer-Douglas-Peucker path simplification.
     * Mirrors Flutter's breadcrumb density optimization for long-distance travel.
     * Removes GPS jitter / noise while preserving real turns.
     * toleranceMeters: points within this perpendicular distance of the
     * line between neighbours are dropped. 10 m removes GPS jitter without
     * smoothing out real road bends.
     */
    function _rdpSimplify(points, toleranceMeters) {
        if (!points || points.length <= 2) return points || [];
        // Convert tolerance from meters to approximate degrees (1° lat ≈ 111 km)
        var tolDeg = toleranceMeters / 111000;
        var tolSq  = tolDeg * tolDeg;

        function perpDistSq(p, a, b) {
            var dx = b.lat() - a.lat(), dy = b.lng() - a.lng();
            if (dx === 0 && dy === 0) {
                var ex = p.lat() - a.lat(), ey = p.lng() - a.lng();
                return ex * ex + ey * ey;
            }
            var t = Math.max(0, Math.min(1, ((p.lat() - a.lat()) * dx + (p.lng() - a.lng()) * dy) / (dx * dx + dy * dy)));
            var fx = p.lat() - (a.lat() + t * dx), fy = p.lng() - (a.lng() + t * dy);
            return fx * fx + fy * fy;
        }

        function rdp(pts, start, end, keep) {
            if (end <= start + 1) return;
            var maxD = 0, maxI = 0;
            for (var i = start + 1; i < end; i++) {
                var d = perpDistSq(pts[i], pts[start], pts[end]);
                if (d > maxD) { maxD = d; maxI = i; }
            }
            if (maxD > tolSq) {
                rdp(pts, start, maxI, keep);
                keep.push(maxI);
                rdp(pts, maxI, end, keep);
            }
        }

        var keepIdx = [0];
        rdp(points, 0, points.length - 1, keepIdx);
        keepIdx.push(points.length - 1);
        keepIdx.sort(function(a, b) { return a - b; });
        return keepIdx.map(function(i) { return points[i]; });
    }

    /**
     * Snap point p onto segment a→b. Returns the nearest point on the segment.
     * Pure lat/lng math — fast, no API call needed.
     */
    function _snapPointToSegment(p, a, b) {
        var aLat = a.lat(), aLng = a.lng(), bLat = b.lat(), bLng = b.lng();
        var dx = bLat - aLat, dy = bLng - aLng;
        if (dx === 0 && dy === 0) return a;
        var t = Math.max(0, Math.min(1,
            ((p.lat() - aLat) * dx + (p.lng() - aLng) * dy) / (dx * dx + dy * dy)
        ));
        return new google.maps.LatLng(aLat + t * dx, aLng + t * dy);
    }

    /**
     * Find the best segment index and snapped position of `point` on `_routePath`.
     * Mirrors Flutter's _snapToRoute + _currentSegmentIndex.
     * Returns { idx, snapped } where idx is the segment-start index in _routePath.
     * If the driver is >150 m from the route (genuine deviation), snapped = raw point.
     */
    function _snapDriverToRoute(point) {
        if (!_routePath || _routePath.length < 2 || !point) {
            return { idx: 0, snapped: point };
        }
        var bestDist = Infinity, bestIdx = 0, bestSnapped = point;
        for (var i = 0; i < _routePath.length - 1; i++) {
            var snapped = _snapPointToSegment(point, _routePath[i], _routePath[i + 1]);
            var dist = google.maps.geometry.spherical.computeDistanceBetween(point, snapped);
            if (dist < bestDist) { bestDist = dist; bestIdx = i; bestSnapped = snapped; }
        }
        // >150 m off route = genuine deviation, use raw GPS position
        return { idx: bestIdx, snapped: bestDist > 150 ? point : bestSnapped };
    }

    function clearRouteWaypointMarkers() {
        routeWaypointMarkers.forEach(function(marker) { marker.setMap(null); });
        routeWaypointMarkers = [];
    }

    function clearRoutePolylines() {
        routePolylines.forEach(function(line) { line.setMap(null); });
    }

    /**
     * Truck marker icon with bearing rotation.
     * Mirrors Flutter's BitmapDescriptor + rotation: rotationAngle approach.
     * The SVG uses transform="rotate(deg, cx, cy)" so the truck points in the
     * direction of travel instead of always facing right.
     */
    function getTruckMarkerIcon(bearing) {
        var deg = bearing || 0;
        return {
            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(
                '<svg xmlns="http://www.w3.org/2000/svg" width="52" height="52" viewBox="0 0 52 52">' +
                '<g transform="rotate(' + deg + ' 26 26)">' +
                '<circle cx="26" cy="26" r="23" fill="#ffffff" fill-opacity="0.96" stroke="#1976D2" stroke-width="2.5"/>' +
                '<path d="M16 17h15v9h5l4 5v4h-2.2a4.3 4.3 0 0 1-8.1 0H22a4.3 4.3 0 0 1-8.1 0H12v-5h4z" fill="#1E88E5"/>' +
                '<circle cx="18.5" cy="35" r="3.2" fill="#263238"/>' +
                '<circle cx="33.5" cy="35" r="3.2" fill="#263238"/>' +
                '<path d="M31 28h5.6l-2.7-3.4H31z" fill="#BBDEFB"/>' +
                '</g></svg>'
            ),
            scaledSize: new google.maps.Size(48, 48),
            anchor: new google.maps.Point(24, 24)
        };
    }

    // ─── Cubic ease-in-out (mirrors Flutter's _easeInOutCubic) ───
    function _easeInOut(t) {
        return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
    }

    /**
     * Smoothly animate the driver marker from `from` to `to` over ~1 second.
     * 25 steps × 40 ms = 1 000 ms, same as Flutter's _animateVehicleMarker.
     * Bearing is interpolated so the truck rotates naturally mid-turn.
     */
    function _animateMarkerTo(from, to) {
        if (_animFrameId) { clearTimeout(_animFrameId); _animFrameId = null; }
        if (!driverMarker) return;

        var dist = google.maps.geometry.spherical.computeDistanceBetween(from, to);
        if (dist < 2) return; // not worth animating

        var newBearing = google.maps.geometry.spherical.computeHeading(from, to);
        // Shortest-path bearing interpolation (handles 350° → 10° correctly)
        var bearingDiff = newBearing - _lastBearing;
        if (bearingDiff > 180)  bearingDiff -= 360;
        if (bearingDiff < -180) bearingDiff += 360;

        var steps = 25, stepMs = 40, step = 0;
        var startBearing = _lastBearing;

        function tick() {
            step++;
            var t = _easeInOut(step / steps);
            var lat = from.lat() + (to.lat() - from.lat()) * t;
            var lng = from.lng() + (to.lng() - from.lng()) * t;
            var pos = new google.maps.LatLng(lat, lng);
            var bearing = startBearing + bearingDiff * t;

            driverMarker.setPosition(pos);
            driverMarker.setIcon(getTruckMarkerIcon(bearing));

            if (_isFollowing) {
                map.panTo(pos);
            }

            if (step < steps) {
                _animFrameId = setTimeout(tick, stepMs);
            } else {
                _lastBearing = newBearing;
                _lastDriverPos = to;
                _animFrameId = null;
            }
        }
        tick();
    }

    /**
     * Extend the green polylines when the driver moves on a silent poll.
     * ── PRIMARY: route-slice (mirrors Flutter's _currentSegmentIndex advance) ──
     * Snaps the new driver position to _routePath and replaces the green
     * polyline path with the route slice up to that point. This keeps the
     * green line perfectly on-road and prevents building cuts during live updates.
     * ── FALLBACK: append raw GPS (when _routePath not yet loaded) ──
     */
    function _appendDriverPosToGreenLine(newPos) {
        if (!_greenPolylines.length || !newPos) return;

        if (_routePath && _routePath.length >= 2) {
            // Route-slice: replace entire path with route[0..snap] + snapped pos
            var snap = _snapDriverToRoute(newPos);
            var slicedPath = _routePath.slice(0, snap.idx + 1).concat([snap.snapped]);
            _greenPolylines.forEach(function(poly) {
                if (poly && poly.setPath) poly.setPath(slicedPath);
            });
            return;
        }

        // Fallback: append raw GPS point
        _greenPolylines.forEach(function(poly) {
            if (!poly || !poly.getPath) return;
            var path = poly.getPath();
            var len = path.getLength();
            if (len === 0) { path.push(newPos); return; }
            var last = path.getAt(len - 1);
            var dist = google.maps.geometry.spherical.computeDistanceBetween(last, newPos);
            if (dist < 5) return; // below noise floor, skip
            path.push(newPos);
        });
    }

    /**
     * Update only the driver marker on each silent poll.
     * Animates to new position, rotates the truck icon, and extends the green line.
     */
    function _updateDriverMarker(data) {
        if (!data.driver_location.lat || !data.driver_location.lng) return;
        var newPos = new google.maps.LatLng(data.driver_location.lat, data.driver_location.lng);

        if (!driverMarker) {
            // Marker not created yet (DB was empty on first load, truck now moving).
            // Create it inline — can't call _fullRefresh() here because _isFetching is locked.
            driverMarker = new google.maps.Marker({
                position: { lat: data.driver_location.lat, lng: data.driver_location.lng },
                map: map,
                title: 'Driver',
                icon: getTruckMarkerIcon(_lastBearing),
                zIndex: 999
            });
            _lastDriverPos = newPos;
            return;
        }

        var from = _lastDriverPos || driverMarker.getPosition();
        if (!from) { driverMarker.setPosition(newPos); _lastDriverPos = newPos; return; }

        var moved = google.maps.geometry.spherical.computeDistanceBetween(from, newPos);
        if (moved < 2) return; // stale poll, skip

        _animateMarkerTo(from, newPos);
        _appendDriverPosToGreenLine(newPos); // extend green line without API call
    }

    /** Re-center camera on vehicle and resume follow mode. */
    function recenterMap() {
        _isFollowing = true;
        document.getElementById('recenterBtn').style.display = 'none';
        if (_lastDriverPos) {
            map.panTo(_lastDriverPos);
            map.setZoom(15);
        } else if (driverMarker) {
            map.panTo(driverMarker.getPosition());
            map.setZoom(15);
        }
    }

    function updateInfoPanel(data) {
        document.getElementById('driverName').textContent = data.driver_details.driver_name;
        document.getElementById('driverPhone').textContent = data.driver_details.driver_phone;
        document.getElementById('vehicleNumber').textContent = data.driver_details.vehicle_number;
        document.getElementById('currentLocation').textContent = data.driver_location.name;
        document.getElementById('pickupName').innerHTML = '<i class="fas fa-circle mr-1" style="font-size: 8px;"></i>' + data.pickup.name;
        document.getElementById('dropName').innerHTML = '<i class="fas fa-circle mr-1" style="font-size: 8px;"></i>' + data.drop.name;
        if (data.driver_location.updated_at)
            document.getElementById('locationUpdatedAt').textContent = 'Updated: ' + data.driver_location.updated_at;

        // Update driver location text in timeline if already built
        var drvEl = document.getElementById('tl-driver-name');
        if (drvEl) drvEl.textContent = data.driver_location.name;
    }

    // ========== Route Timeline ==========

    function processRouteTimeline(directionsResult, data) {
        var route = directionsResult.routes[0];
        _routePath = route.overview_path;
        if (!_routePath || _routePath.length < 2) return;

        _totalRouteDistance = google.maps.geometry.spherical.computeLength(_routePath);

        // Total duration from all legs
        _routeDuration = 0;
        route.legs.forEach(function(leg) { _routeDuration += leg.duration.value; });

        // Driver distance on route and deviation check
        _driverDistOnRoute = 0;
        var driverOffRouteDist = 0;
        if (data.driver_location.lat && data.driver_location.lng) {
            var drvLL = new google.maps.LatLng(data.driver_location.lat, data.driver_location.lng);
            var minSnap = Infinity, acc = 0;
            for (var i = 0; i < _routePath.length; i++) {
                if (i > 0) acc += google.maps.geometry.spherical.computeDistanceBetween(_routePath[i - 1], _routePath[i]);
                var d = google.maps.geometry.spherical.computeDistanceBetween(drvLL, _routePath[i]);
                if (d < minSnap) { minSnap = d; _driverDistOnRoute = acc; }
            }
            driverOffRouteDist = minSnap;
        }

        // Parse pickup start time for estimations
        var apiTimeline = data.timeline || [];
        if (!_pickupStartTime) {
            // Try in_progress_at first, then first timeline entry
            if (data.in_progress_at) {
                _pickupStartTime = new Date(data.in_progress_at);
            } else if (apiTimeline.length > 0) {
                _pickupStartTime = parseTimeDate(apiTimeline[0].time, apiTimeline[0].date);
            }
        }

        // 3km deviation detection
        if (driverOffRouteDist > _deviationThreshold && data.driver_location.lat && data.driver_location.lng) {
            // Check if driver moved significantly since last deviation recalculation
            var shouldRecalculate = !_isDeviated;
            if (_isDeviated && _lastDeviationLat !== null) {
                var movedSinceLast = google.maps.geometry.spherical.computeDistanceBetween(
                    new google.maps.LatLng(data.driver_location.lat, data.driver_location.lng),
                    new google.maps.LatLng(_lastDeviationLat, _lastDeviationLng)
                );
                if (movedSinceLast > 5000) shouldRecalculate = true; // recalculate if moved > 5km
            }

            _isDeviated = true;
            if (shouldRecalculate) {
                recalculateDeviatedRoute(data);
                return; // recalculateDeviatedRoute will re-render
            }
        } else if (_isDeviated) {
            // Driver back on original route
            _isDeviated = false;
            _autoTimelinePoints = [];
            _lastDeviationLat = null;
            _lastDeviationLng = null;
            // Will regenerate auto points below
        }

        _lastData = data;

        // Use server-provided intermediate stops when available — they are identical
        // to what the customer and employee Flutter apps receive, so all three apps
        // show the same location names in the timeline without any client-side geocoding.
        if (data.auto_timeline_points && data.auto_timeline_points.length > 0) {
            // Server returns dist_fraction (0–1); the rest of the JS treats `dist`
            // as absolute meters along the route (compared against
            // _driverDistOnRoute, fed into estimateTimeAtDist). Convert here, or
            // every auto-stop is classified as "behind driver" and inherits the
            // pickup timestamp instead of a live ETA.
            _autoTimelinePoints = data.auto_timeline_points.map(function(s) {
                return {
                    name:             s.name,
                    dist:             (s.dist_fraction || 0) * _totalRouteDistance,
                    lat:              s.lat,
                    lng:              s.lng,
                    isAuto:           true,
                    passedAt:         s.passed_at || null,
                    estimatedArrival: s.estimated_arrival || null,
                    estimatedDate:    s.estimated_date || null
                };
            });
        } else if (_autoTimelinePoints.length === 0 && !_autoPointsGenerating) {
            // Fallback: generate client-side when server didn't return stops
            generateHourlyTimelinePoints(function() {
                _subPointsCache = {};
                _expandedSections = {};
                renderTimeline([], _lastData);
            });
        }

        _subPointsCache = {};
        _expandedSections = {};
        renderTimeline([], data);
    }

    function geocodeWithRetry(latLng, attempt, callback) {
        // ── localStorage cache (3 dp, ~110 m — mirrors Flutter's _r3 reverseGeocode key) ──
        var geoKey = 'rgeocode:' + _r3(latLng.lat()) + ',' + _r3(latLng.lng());
        if (attempt === 0) {
            var cached = _lsGet(geoKey);
            if (cached && cached.name) { callback(cached.name); return; }
        }
        if (attempt > 2) { callback('Waypoint'); return; }
        setTimeout(function() {
            _geocoder.geocode({ location: { lat: latLng.lat(), lng: latLng.lng() } }, function(results, status) {
                if (status === 'OK' && results[0]) {
                    var name = extractLocalityName(results[0]);
                    _lsPut(geoKey, { name: name }); // cache for 24 h
                    callback(name);
                } else if (status === 'OVER_QUERY_LIMIT') {
                    geocodeWithRetry(latLng, attempt + 1, callback);
                } else {
                    callback('Waypoint');
                }
            });
        }, attempt * 400);
    }

    function extractLocalityName(result) {
        var comps = result.address_components;
        var locality = '', sublocality = '', district = '', state = '';
        for (var i = 0; i < comps.length; i++) {
            var t = comps[i].types;
            if (t.indexOf('locality') !== -1) locality = comps[i].long_name;
            if (t.indexOf('sublocality_level_1') !== -1) sublocality = comps[i].long_name;
            if (t.indexOf('administrative_area_level_2') !== -1) district = comps[i].long_name;
            if (t.indexOf('administrative_area_level_1') !== -1) state = comps[i].short_name;
        }
        var name = locality || sublocality || district || 'Waypoint';
        if (state && name !== 'Waypoint') name += ', ' + state;
        return name;
    }

    function updatePointStatuses(points) {
        points.forEach(function(p) {
            p.status = (p.dist <= _driverDistOnRoute * 1.05) ? 'completed' : 'pending';
        });
    }

    // Estimate arrival time at a given distance from start
    // For points ahead of driver: use driver's current time + remaining duration
    function estimateTimeAtDist(dist) {
        if (!_routeDuration || !_totalRouteDistance) return null;

        // For points ahead of driver, calculate from driver's current position and time
        var driverTime = _lastData && _lastData.driver_location && _lastData.driver_location.updated_at
            ? new Date(_lastData.driver_location.updated_at)
            : null;

        if (driverTime && _driverDistOnRoute > 0 && dist > _driverDistOnRoute) {
            var remainingDist = dist - _driverDistOnRoute;
            var driverToEnd   = _totalRouteDistance - _driverDistOnRoute;
            if (driverToEnd <= 0) return driverTime;

            // Fraction of the REMAINING journey (driver→destination) this stop sits at.
            // Previously divided by _totalRouteDistance (total trip) instead of driverToEnd
            // (remaining trip), making all fractions artificially small → all same ETA.
            var stopFraction      = remainingDist / driverToEnd;
            // Remaining duration = total × (remaining distance / total distance)
            var remainingDuration = _routeDuration * (driverToEnd / _totalRouteDistance);

            // When driver is halted (is_moving=false or speed ≈ 0) use now() as the
            // anchor so each minute of halt adds one minute to all future ETAs —
            // identical to Google Maps adaptive ETA behaviour.
            var isMoving   = _lastData && _lastData.driver_location
                             && (_lastData.driver_location.is_moving !== false)
                             && (_lastData.driver_location.speed_kmh > 0.5);
            var anchorTime = isMoving ? driverTime : new Date();

            var remainingMs = Math.round(stopFraction * remainingDuration * 1000);
            return new Date(anchorTime.getTime() + remainingMs);
        }

        // Points behind the driver: estimate from pickup start time
        if (!_pickupStartTime) return null;
        var fraction = Math.min(dist / _totalRouteDistance, 1);
        var ms = _pickupStartTime.getTime() + Math.round(fraction * _routeDuration * 1000);
        return new Date(ms);
    }

    function cleanTitle(title) {
        if (!title || title === 'N/A' || title === 'n/a') return '';
        return title;
    }

    // ========== Auto Hourly Timeline Points ==========

    /**
     * Generate intermediate timeline stops — mirrors Flutter's generateSubStops algorithm exactly:
     *   1. Compute stopCount from journey duration (same formula as Dart)
     *   2. Scale candidateCount for long routes (same thresholds as Dart)
     *   3. Sample candidateCount points evenly along _routePath
     *   4. Reverse-geocode all candidates in parallel
     *   5. Deduplicate by first place-name component (same as Dart)
     *   6. Thin to targetCount with even stride (same as Dart)
     *
     * This keeps the admin web timeline in sync with the customer and employee
     * Flutter apps so all three show the same intermediate town/city names.
     */
    function generateHourlyTimelinePoints(callback) {
        if (_autoPointsGenerating || !_routePath || !_totalRouteDistance || !_routeDuration) {
            if (callback) callback();
            return;
        }

        var totalHours = _routeDuration / 3600;

        // ── Stop-count formula (mirrors Flutter) ──────────────────────────
        var stopCount;
        if (totalHours <= 1)      stopCount = 3;
        else if (totalHours <= 3) stopCount = 4;
        else                      stopCount = Math.min(Math.max(Math.round(totalHours), 5), 20);

        // ── Candidate count: scale up for long routes (mirrors Flutter) ───
        var segmentKm = _totalRouteDistance / 1000;
        var desiredCount = segmentKm >= 220 ? 7
                         : segmentKm >= 160 ? 6
                         : segmentKm >= 120 ? 5
                         : segmentKm >= 80  ? 4
                         : stopCount;
        var targetCount    = Math.max(stopCount, desiredCount);
        var candidateCount = Math.min(Math.max(targetCount * 4, 8), 24);

        // ── Sample candidateCount points evenly along _routePath ──────────
        var interval = _totalRouteDistance / (candidateCount + 1);
        var samples  = [];
        var accDist  = 0;
        var nextAt   = interval;
        var picked   = 0;

        for (var i = 1; i < _routePath.length && picked < candidateCount; i++) {
            accDist += google.maps.geometry.spherical.computeDistanceBetween(
                _routePath[i - 1], _routePath[i]);
            while (accDist >= nextAt && picked < candidateCount && nextAt < _totalRouteDistance * 0.97) {
                samples.push({ latLng: _routePath[i], dist: nextAt });
                picked++;
                nextAt += interval;
            }
        }

        if (samples.length === 0) {
            _autoTimelinePoints = [];
            if (callback) callback();
            return;
        }

        // ── Reverse-geocode all candidates in parallel ────────────────────
        _autoPointsGenerating = true;
        var remaining = samples.length;
        var results   = new Array(samples.length);

        samples.forEach(function(item, idx) {
            geocodeWithRetry(item.latLng, 0, function(name) {
                results[idx] = {
                    name: name,
                    dist: item.dist,
                    lat:  item.latLng.lat(),
                    lng:  item.latLng.lng(),
                    isAuto: true
                };
                remaining--;
                if (remaining !== 0) return;

                // ── Deduplicate by first place-name component (mirrors Flutter) ──
                var seen   = {};
                var unique = [];
                results.forEach(function(r) {
                    if (!r) return;
                    var key = r.name.split(',')[0].trim().toLowerCase();
                    if (key && key !== 'waypoint' && key !== 'unknown' && !seen[key]) {
                        seen[key] = true;
                        unique.push(r);
                    }
                });

                // ── Thin to targetCount with even stride (mirrors Flutter) ──
                var finalPoints = unique;
                if (unique.length > targetCount) {
                    var step    = (unique.length - 1) / (targetCount - 1);
                    var thinned = [];
                    for (var t = 0; t < targetCount; t++) {
                        thinned.push(unique[Math.round(t * step)]);
                    }
                    // Final de-duplication after thinning
                    var seen2  = {};
                    finalPoints = [];
                    thinned.forEach(function(r) {
                        var key2 = r.name.split(',')[0].trim().toLowerCase();
                        if (!seen2[key2]) { seen2[key2] = true; finalPoints.push(r); }
                    });
                }

                _autoTimelinePoints    = finalPoints;
                _autoPointsGenerating  = false;
                if (callback) callback();
            });
        });
    }

    function isAutoPointNearPriorityStop(autoPoint, priorityStops) {
        for (var i = 0; i < priorityStops.length; i++) {
            if (!priorityStops[i].lat || !priorityStops[i].lng) continue;
            var d = google.maps.geometry.spherical.computeDistanceBetween(
                new google.maps.LatLng(autoPoint.lat, autoPoint.lng),
                new google.maps.LatLng(priorityStops[i].lat, priorityStops[i].lng)
            );
            if (d < 5000) return true; // within 5km of a priority stop
        }
        return false;
    }

    // ========== 3km Deviation Detection & Recalculation ==========

    function recalculateDeviatedRoute(data) {
        // Invalidate in-memory and localStorage caches on deviation
        // so the next call fetches a fresh route from the driver's new position.
        _cachedDirectionsResult = null;
        _cachedRouteKey = null;
        _cachedCustomRouteKey = null;
        // Clear the persisted route for this pickup/drop so it re-fetches clean
        if (data.pickup && data.drop && data.pickup.lat && data.drop.lat) {
            var deviationOrigin = new google.maps.LatLng(data.pickup.lat, data.pickup.lng);
            var deviationDest   = new google.maps.LatLng(data.drop.lat, data.drop.lng);
            var pendingWps = getSortedRouteWaypoints(data).filter(function(s) {
                return s.status !== 5 && s.status !== 6 && s.lat && s.lng;
            });
            _lsDel(_buildDirectionsCacheKey(deviationOrigin, deviationDest, pendingWps));
        }

        var driverLat = data.driver_location.lat;
        var driverLng = data.driver_location.lng;
        var driverPoint = new google.maps.LatLng(driverLat, driverLng);
        var destination = new google.maps.LatLng(data.drop.lat, data.drop.lng);

        var pendingWaypoints = getSortedRouteWaypoints(data).filter(function(stop) {
            return stop.status !== 5 && stop.status !== 6 && stop.lat && stop.lng;
        }).map(function(stop) {
            return { location: new google.maps.LatLng(stop.lat, stop.lng), stopover: true };
        });

        directionsService.route({
            origin: driverPoint,
            destination: destination,
            waypoints: pendingWaypoints,
            travelMode: google.maps.TravelMode.DRIVING,
            optimizeWaypoints: false
        }, function(result, status) {
            if (status === 'OK' && result.routes[0]) {
                var route = result.routes[0];
                _routePath = route.overview_path;
                _totalRouteDistance = google.maps.geometry.spherical.computeLength(_routePath);
                _routeDuration = 0;
                route.legs.forEach(function(leg) { _routeDuration += leg.duration.value; });
                _driverDistOnRoute = 0;

                // Use driver's current time as base for ETA
                var driverUpdateTime = data.driver_location.updated_at
                    ? new Date(data.driver_location.updated_at)
                    : new Date();
                _pickupStartTime = driverUpdateTime;

                _lastDeviationLat = driverLat;
                _lastDeviationLng = driverLng;

                // Regenerate auto points along new route
                _autoTimelinePoints = [];
                _lastData = data;
                generateHourlyTimelinePoints(function() {
                    _subPointsCache = {};
                    _expandedSections = {};
                    renderTimeline([], _lastData);
                });
            }
        });
    }

    function renderTimeline(routePoints, data) {
        var container = document.getElementById('locationTimeline');
        var apiTimeline = data.timeline || [];
        var pickupTime = apiTimeline.length > 0 ? apiTimeline[0] : {};
        var dropTime = apiTimeline.length > 1 ? apiTimeline[apiTimeline.length - 1] : {};
        var driverLocation = data.driver_location;

        // Pickup time from API or estimate
        var pickupTimeStr = pickupTime.time && pickupTime.time !== '-' ? pickupTime.time : '';
        var pickupDateStr = pickupTime.date || '';
        if (!pickupTimeStr && _pickupStartTime) {
            pickupTimeStr = formatTime12(_pickupStartTime);
            pickupDateStr = formatDateDMY(_pickupStartTime);
        }

        var html = '<div class="loc-timeline">';
        var majorPoints = buildMajorTimelinePoints(data, pickupTime, dropTime);
        majorPoints.forEach(function(point, index) {
            html += buildMainItem(
                point.typeClass,
                point.iconHtml,
                point.title,
                point.subtitle,
                point.time,
                point.date,
                null
            );
        });

        // 6. REMAINING DELIVERY TIME ESTIMATE
        if (data.status < 5 && _pickupStartTime && _routeDuration && _totalRouteDistance && _driverDistOnRoute > 0) {
            var remainingDist = _totalRouteDistance - _driverDistOnRoute;
            if (remainingDist > 0) {
                var remainingFraction = remainingDist / _totalRouteDistance;
                var remainingSecs = Math.round(remainingFraction * _routeDuration);
                var hrs = Math.floor(remainingSecs / 3600);
                var mins = Math.floor((remainingSecs % 3600) / 60);
                var etaText = 'Deliver in ';
                if (hrs > 0) etaText += hrs + ' hour' + (hrs > 1 ? 's' : '');
                if (hrs > 0 && mins > 0) etaText += ' ';
                if (mins > 0) etaText += mins + ' minute' + (mins > 1 ? 's' : '');
                if (hrs === 0 && mins === 0) etaText += 'less than a minute';

                html += '<div class="text-center mt-3 mb-2">';
                html += '<span class="badge badge-info" style="font-size: 13px; padding: 8px 16px; background: #2196F3; color: #fff; border-radius: 20px;">';
                html += '<i class="fas fa-clock mr-1"></i> ' + etaText;
                html += '</span></div>';
            }
        }

        html += '</div>';
        container.innerHTML = html;
    }

    function buildMajorTimelinePoints(data, pickupTime, dropTime) {
        var sortedStops = getSortedRouteWaypoints(data);
        var completedStops = sortedStops.filter(function(stop) {
            return stop.status === 5 || stop.status === 6;
        });
        var pendingStops = sortedStops.filter(function(stop) {
            return stop.status !== 5 && stop.status !== 6;
        });
        var allPriorityStops = sortedStops;
        var points = [];

        // 1. Pickup point
        points.push({
            id: 'pickup',
            typeClass: 'type-pickup completed',
            iconHtml: '<i class="fas fa-map-marker-alt"></i>',
            title: cleanTitle(pickupTime.title) || 'Pickup started from',
            subtitle: pickupTime.subtitle || data.pickup.name,
            time: pickupTime.time && pickupTime.time !== '-' ? pickupTime.time : (_pickupStartTime ? formatTime12(_pickupStartTime) : ''),
            date: pickupTime.date || (_pickupStartTime ? formatDateDMY(_pickupStartTime) : ''),
            lat: data.pickup.lat || null,
            lng: data.pickup.lng || null,
            subStyle: 'completed'
        });

        // 2. Build merged completed list (priority stops + auto points behind driver)
        var allCompleted = [];
        completedStops.forEach(function(stop) {
            var stopDist = getDistanceOnRoute(stop.lat, stop.lng);
            var match = findNearestTrackingRecord(stop.lat, stop.lng);
            allCompleted.push({
                type: 'priority', dist: stopDist,
                id: 'stop-' + stop.priority,
                typeClass: 'type-sub-completed completed',
                iconHtml: '',
                title: stop.name || ('Priority ' + stop.priority),
                subtitle: 'Priority ' + stop.priority + ' stop',
                lat: stop.lat || null,
                lng: stop.lng || null,
                subStyle: 'completed',
                realTime: match ? match.reached_at : null
            });
        });

        // Add auto points - use real tracking records to determine completed/pending
        _autoTimelinePoints.forEach(function(ap, idx) {
            if (isAutoPointNearPriorityStop(ap, allPriorityStops)) return;

            var match = findNearestTrackingRecord(ap.lat, ap.lng);
            // Trust the server's `passed_at` first — it's computed against the
            // full breadcrumb history and matches what the user/vendor apps show.
            // Fall back to local nearest-record match for older payloads.
            var serverPassedAt = ap.passedAt ? new Date(ap.passedAt) : null;
            var isCompleted = serverPassedAt !== null || match !== null;

            if (isCompleted) {
                allCompleted.push({
                    type: 'auto', dist: ap.dist,
                    id: 'auto-c-' + idx,
                    typeClass: 'type-auto-completed completed',
                    iconHtml: '',
                    title: ap.name,
                    subtitle: '',
                    lat: ap.lat,
                    lng: ap.lng,
                    subStyle: 'completed',
                    realTime: serverPassedAt || match.reached_at
                });
            } else {
                // No real tracking match - check if behind driver (fallback to distance)
                if (ap.dist <= _driverDistOnRoute * 1.05) {
                    allCompleted.push({
                        type: 'auto', dist: ap.dist,
                        id: 'auto-c-' + idx,
                        typeClass: 'type-auto-completed completed',
                        iconHtml: '',
                        title: ap.name,
                        subtitle: '',
                        lat: ap.lat,
                        lng: ap.lng,
                        subStyle: 'completed',
                        realTime: null
                    });
                }
            }
        });

        // Sort by distance and add times (use real time if available, else interpolate from tracking)
        allCompleted.sort(function(a, b) { return a.dist - b.dist; });

        // Monotonic clamp: after resolving each stop's time, ensure times never
        // go backwards along the route.  Without this, a stop that falls back to
        // the pickupStart+fraction estimate can show an earlier time than the
        // previous stop that had a real GPS tracking record (e.g. Chintalapalem
        // 09:14 appearing after Koduru 09:32 — which is impossible on-road).
        var _prevCompletedMs = 0; // tracks the last committed time in ms

        allCompleted.forEach(function(item) {
            var resolvedDate;
            if (item.realTime) {
                resolvedDate = item.realTime instanceof Date ? item.realTime : new Date(item.realTime);
            } else {
                resolvedDate = interpolateTimeFromTracking(item.lat, item.lng, item.dist);
            }

            // Clamp: must not be earlier than the previous stop's time.
            if (resolvedDate) {
                var ms = resolvedDate.getTime();
                if (ms < _prevCompletedMs) {
                    resolvedDate = new Date(_prevCompletedMs);
                } else {
                    _prevCompletedMs = ms;
                }
                item.time = formatTime12(resolvedDate);
                item.date = formatDateDMY(resolvedDate);
            } else {
                item.time = '';
                item.date = '';
            }
            points.push(item);
        });

        // 3. Current driver location
        if (data.driver_location.lat && data.driver_location.lng) {
            var updDt = data.driver_location.updated_at ? new Date(data.driver_location.updated_at) : null;
            points.push({
                id: 'driver',
                typeClass: 'type-current current',
                iconHtml: '<i class="fas fa-truck"></i>',
                title: data.driver_location.name || 'Current vehicle location',
                subtitle: _isDeviated ? 'Route deviated - recalculating' : 'Current vehicle location',
                time: updDt ? formatTime12(updDt) : '',
                date: updDt ? formatDateDMY(updDt) : '',
                lat: data.driver_location.lat,
                lng: data.driver_location.lng,
                subStyle: 'completed'
            });
        }

        // 4. Build merged pending list (priority stops + auto points ahead of driver)
        var allPending = [];
        pendingStops.forEach(function(stop) {
            var stopDist = getDistanceOnRoute(stop.lat, stop.lng);
            allPending.push({
                type: 'priority', dist: stopDist,
                id: 'stop-' + stop.priority,
                typeClass: 'type-sub-pending',
                iconHtml: '',
                title: stop.name || ('Priority ' + stop.priority),
                subtitle: 'Priority ' + stop.priority + ' stop',
                lat: stop.lat || null,
                lng: stop.lng || null,
                subStyle: 'pending'
            });
        });

        // Add auto points ahead of driver that have no real tracking match
        _autoTimelinePoints.forEach(function(ap, idx) {
            if (isAutoPointNearPriorityStop(ap, allPriorityStops)) return;
            var match = findNearestTrackingRecord(ap.lat, ap.lng);
            // Only show as pending if not yet passed AND ahead of driver
            var serverPassedAt = ap.passedAt ? new Date(ap.passedAt) : null;
            if (!serverPassedAt && !match && ap.dist > _driverDistOnRoute * 1.05) {
                allPending.push({
                    type: 'auto', dist: ap.dist,
                    id: 'auto-p-' + idx,
                    typeClass: 'type-auto-pending',
                    iconHtml: '',
                    title: ap.name,
                    subtitle: '',
                    lat: ap.lat,
                    lng: ap.lng,
                    subStyle: 'pending',
                    serverEtaTime: ap.estimatedArrival,
                    serverEtaDate: ap.estimatedDate
                });
            }
        });

        // Sort by distance and add estimated times for pending
        allPending.sort(function(a, b) { return a.dist - b.dist; });
        allPending.forEach(function(item) {
            // Prefer server-supplied ETA so the admin matches user/vendor apps
            // exactly. Falls back to client-side estimate for priority stops
            // (which don't carry server ETAs) or older payloads.
            if (item.serverEtaTime) {
                item.time = item.serverEtaTime;
                item.date = item.serverEtaDate || '';
            } else {
                var eta = estimateTimeAtDist(item.dist);
                item.time = eta ? formatTime12(eta) : '';
                item.date = eta ? formatDateDMY(eta) : '';
            }
            points.push(item);
        });

        // 5. Destination
        // Always use data.drop.name as the title — dropTime is the last API
        // timeline entry which can be the driver's current GPS position, not
        // the actual drop address. Only use dropTime for the timestamp (when
        // the booking has been delivered, status >= 5).
        var destDist = getDistanceOnRoute(data.drop.lat, data.drop.lng);
        var destEst = estimateTimeAtDist(destDist || _totalRouteDistance);
        var dropTitle = data.drop.name || 'Destination';
        var dropSubtitle = 'Drop location';
        points.push({
            id: 'destination',
            typeClass: 'type-drop' + ((data.status >= 5) ? '' : ' pending-status'),
            iconHtml: '<i class="fas fa-crosshairs"></i>',
            title: dropTitle,
            subtitle: dropSubtitle,
            time: (data.status >= 5 && dropTime.time && dropTime.time !== '-') ? dropTime.time : (destEst ? formatTime12(destEst) : ''),
            date: (data.status >= 5 && dropTime.date) ? dropTime.date : (destEst ? formatDateDMY(destEst) : ''),
            lat: data.drop.lat || null,
            lng: data.drop.lng || null,
            subStyle: 'pending'
        });

        return points;
    }

    function buildMainItem(typeClass, iconHtml, title, subtitle, time, date, clickInfo) {
        var cls = 'loc-timeline-item ' + typeClass;
        var attrs = '';
        var clickable = (clickInfo !== null);

        if (clickable) {
            cls += ' main-clickable';
            attrs = ' onclick="expandSub(\'' + clickInfo.id + '\',this,' + clickInfo.fromLat + ',' + clickInfo.fromLng + ',' + clickInfo.toLat + ',' + clickInfo.toLng + ',\'' + clickInfo.sub + '\')"';
        }

        var h = '<div class="' + cls + '"' + attrs + '>';
        h += '<div class="tl-line"></div>';
        h += '<div class="tl-icon">' + iconHtml + '</div>';
        h += '<div class="tl-content">';
        h += '<div class="tl-title">' + escapeHtml(title || '');
        if (clickable) h += ' <span class="tl-expand"><i class="fas fa-chevron-right"></i></span>';
        h += '</div>';
        if (subtitle) h += '<div class="tl-subtitle">' + escapeHtml(subtitle) + '</div>';
        h += '</div>';
        h += '<div class="tl-time">';
        if (time && time !== '-') h += '<div class="tl-time-text">' + escapeHtml(time) + '</div>';
        if (date) h += '<div class="tl-date-text">' + escapeHtml(date) + '</div>';
        h += '</div></div>';
        return h;
    }

    // ========== Expand / Collapse ==========

    function expandSub(secId, mainEl, fromLat, fromLng, toLat, toLng, subStyle) {
        var isExpanded = mainEl.classList.contains('expanded');

        // COLLAPSE - hide existing sub-items
        if (isExpanded) {
            var subs = document.querySelectorAll('.sub-of-' + secId);
            for (var i = 0; i < subs.length; i++) subs[i].classList.add('sub-hidden');
            mainEl.classList.remove('expanded');
            _expandedSections[secId] = false;
            return;
        }

        // EXPAND - check if sub-items already exist in DOM
        var existingSubs = document.querySelectorAll('.sub-of-' + secId);
        if (existingSubs.length > 0) {
            for (var i = 0; i < existingSubs.length; i++) existingSubs[i].classList.remove('sub-hidden');
            mainEl.classList.add('expanded');
            _expandedSections[secId] = true;
            return;
        }

        // FIRST TIME - sample, geocode, insert
        _expandedSections[secId] = true;

        // Loading indicator
        var loadEl = document.createElement('div');
        loadEl.className = 'loc-timeline-item sub-item sub-of-' + secId;
        loadEl.innerHTML = '<div class="tl-line"></div><div class="tl-icon" style="border:none;width:auto;height:auto;left:10px;top:4px;"><i class="fas fa-spinner fa-spin" style="font-size:10px;color:#999;"></i></div>' +
            '<div class="tl-content" style="padding-bottom:8px;"><small class="text-muted">Loading...</small></div><div class="tl-time"></div>';
        mainEl.parentNode.insertBefore(loadEl, mainEl);

        fetchSegmentSubPoints(secId, fromLat, fromLng, toLat, toLng, function(results) {
            loadEl.remove();

            results.forEach(function(r) {
                var el = createSubEl(r.name, r.dist, secId, subStyle);
                mainEl.parentNode.insertBefore(el, mainEl);
            });

            mainEl.classList.add('expanded');
        });
    }

    function fetchSegmentSubPoints(secId, fromLat, fromLng, toLat, toLng, callback) {
        if (_subPointsCache[secId]) {
            callback(_subPointsCache[secId]);
            return;
        }

        var origin = new google.maps.LatLng(fromLat, fromLng);
        var destination = new google.maps.LatLng(toLat, toLng);
        var segmentDistance = google.maps.geometry.spherical.computeDistanceBetween(origin, destination);
        if (segmentDistance < 5000) {
            _subPointsCache[secId] = [];
            callback([]);
            return;
        }

        directionsService.route({
            origin: origin,
            destination: destination,
            travelMode: google.maps.TravelMode.DRIVING,
            optimizeWaypoints: false
        }, function(result, status) {
            if (status !== 'OK' || !result.routes[0]) {
                _subPointsCache[secId] = [];
                callback([]);
                return;
            }

            var path = result.routes[0].overview_path || [];
            var samples = samplePathPoints(path);
            if (samples.length === 0) {
                _subPointsCache[secId] = [];
                callback([]);
                return;
            }

            var remaining = samples.length;
            var results = [];
            samples.forEach(function(item, idx) {
                geocodeWithRetry(item.latLng, 0, function(name) {
                    results[idx] = { name: name, dist: getDistanceOnRoute(item.latLng.lat(), item.latLng.lng()) };
                    remaining--;
                    if (remaining === 0) {
                        var seen = {}, unique = [];
                        results.forEach(function(r) {
                            var key = r.name.split(',')[0].trim().toLowerCase();
                            if (!seen[key]) { seen[key] = true; unique.push(r); }
                        });
                        _subPointsCache[secId] = unique;
                        callback(unique);
                    }
                });
            });
        });
    }

    function samplePathPoints(path) {
        if (!path || path.length < 2) return [];

        var totalDist = google.maps.geometry.spherical.computeLength(path);
        var count = Math.min(6, Math.max(3, Math.round(totalDist / 35000)));
        var interval = totalDist / (count + 1);
        var points = [];
        var accDist = 0;
        var nextAt = interval;

        for (var i = 1; i < path.length; i++) {
            accDist += google.maps.geometry.spherical.computeDistanceBetween(path[i - 1], path[i]);
            while (accDist >= nextAt && points.length < count) {
                points.push({ latLng: path[i], dist: nextAt });
                nextAt += interval;
            }
        }

        return points;
    }

    /**
     * Interpolate time for a point from real tracking records.
     * Finds the two nearest tracking records (before and after on route) and interpolates.
     */
    function interpolateTimeFromTracking(lat, lng, dist) {
        if (!_trackingHistory || _trackingHistory.length === 0) {
            return estimateTimeAtDist(dist);
        }

        // Build tracking records with route distances
        var records = [];
        for (var i = 0; i < _trackingHistory.length; i++) {
            var rec = _trackingHistory[i];
            if (!rec.lat || !rec.lng || !rec.reached_at) continue;
            var rDist = getDistanceOnRoute(rec.lat, rec.lng);
            records.push({ dist: rDist, time: new Date(rec.reached_at) });
        }
        if (records.length === 0) return estimateTimeAtDist(dist);

        records.sort(function(a, b) { return a.dist - b.dist; });

        // Find before and after records
        var before = null, after = null;
        for (var i = 0; i < records.length; i++) {
            if (records[i].dist <= dist) before = records[i];
            if (records[i].dist >= dist && !after) after = records[i];
        }

        if (before && after && before !== after) {
            var fraction = (dist - before.dist) / (after.dist - before.dist);
            var ms = before.time.getTime() + fraction * (after.time.getTime() - before.time.getTime());
            return new Date(ms);
        }
        // No bracketing pair found — fall through to distance-proportional estimate.
        // Previously returned before.time (or after.time) directly, which caused
        // every stop beyond the last tracking record to show the exact same timestamp.
        return estimateTimeAtDist(dist);
    }

    /**
     * Find the nearest real tracking record to a given lat/lng.
     * Returns { reached_at: Date, distance: meters } or null if no match within 15km.
     */
    function findNearestTrackingRecord(lat, lng) {
        if (!_trackingHistory || _trackingHistory.length === 0 || lat === null || lng === null) return null;
        var target = new google.maps.LatLng(lat, lng);
        var bestDist = Infinity;
        var bestRecord = null;

        for (var i = 0; i < _trackingHistory.length; i++) {
            var rec = _trackingHistory[i];
            if (!rec.lat || !rec.lng) continue;
            var recLL = new google.maps.LatLng(rec.lat, rec.lng);
            var d = google.maps.geometry.spherical.computeDistanceBetween(target, recLL);
            if (d < bestDist) {
                bestDist = d;
                bestRecord = rec;
            }
        }

        if (bestRecord && bestDist < 15000) {
            return {
                reached_at: bestRecord.reached_at ? new Date(bestRecord.reached_at) : null,
                distance: bestDist
            };
        }
        return null;
    }

    function getDistanceOnRoute(lat, lng) {
        if (!_routePath || !_routePath.length || lat === null || lng === null || lat === undefined || lng === undefined) return 0;
        var target = new google.maps.LatLng(lat, lng);
        var minSnap = Infinity;
        var snapDist = 0;
        var acc = 0;

        for (var i = 0; i < _routePath.length; i++) {
            if (i > 0) acc += google.maps.geometry.spherical.computeDistanceBetween(_routePath[i - 1], _routePath[i]);
            var d = google.maps.geometry.spherical.computeDistanceBetween(target, _routePath[i]);
            if (d < minSnap) {
                minSnap = d;
                snapDist = acc;
            }
        }

        return snapDist;
    }

    function createSubEl(name, dist, secId, subStyle) {
        var isComp = (subStyle === 'completed');
        var div = document.createElement('div');
        div.className = 'loc-timeline-item sub-item ' + (isComp ? 'completed' : 'pending-sub') + ' sub-of-' + secId;

        var est = estimateTimeAtDist(dist);
        var timeHtml = est ? '<div class="tl-time-text">' + formatTime12(est) + '</div><div class="tl-date-text">' + formatDateDMY(est) + '</div>' : '';

        div.innerHTML =
            '<div class="tl-line"></div>' +
            '<div class="tl-icon"></div>' +
            '<div class="tl-content"><div class="tl-title">' + escapeHtml(name) + '</div></div>' +
            '<div class="tl-time">' + timeHtml + '</div>';
        return div;
    }

    // ========== Helpers ==========

    function parseTimeDate(timeStr, dateStr) {
        if (!timeStr || timeStr === '-' || !dateStr) return null;
        var dp = dateStr.split('/');
        var tp = timeStr.match(/(\d+):(\d+)\s*(AM|PM)/i);
        if (!dp || dp.length !== 3 || !tp) return null;
        var h = parseInt(tp[1]), m = parseInt(tp[2]), ap = tp[3].toUpperCase();
        if (ap === 'PM' && h !== 12) h += 12;
        if (ap === 'AM' && h === 12) h = 0;
        return new Date(parseInt(dp[2]), parseInt(dp[1]) - 1, parseInt(dp[0]), h, m);
    }

    function formatTime12(d) {
        var h = d.getHours(), m = d.getMinutes(), ap = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ' ' + ap;
    }

    function formatDateDMY(d) {
        var dd = d.getDate(), mm = d.getMonth() + 1, yy = d.getFullYear();
        return (dd < 10 ? '0' : '') + dd + '/' + (mm < 10 ? '0' : '') + mm + '/' + yy;
    }

    function escapeHtml(text) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(text));
        return d.innerHTML;
    }

    document.addEventListener('DOMContentLoaded', function() { initMap(); });
    window.addEventListener('beforeunload', function() {
        if (_liveTimer) clearInterval(_liveTimer);
        if (_timelineTimer) clearInterval(_timelineTimer);
    });
</script>
@endpush
