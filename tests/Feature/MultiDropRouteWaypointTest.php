<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\Farmer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Multi-drop tracking: one driver, two bookings.
 *
 *   priority 1 → Kakinada      (served first)
 *   priority 2 → Kona Forest   (served second)
 *
 * The driver physically delivered Kakinada but never pressed "Delivered", so
 * that booking is still status 4. The Kona Forest customer's map therefore
 * kept routing back through Kakinada — a detour that only disappeared once
 * someone pressed the button.
 *
 * What each customer must see:
 *   • Kakinada's own customer  — unchanged, still tracks the vehicle.
 *   • Kona Forest's customer   — Kakinada dropped from the route ONCE the
 *                                vehicle has actually been there; still shown
 *                                while the vehicle is on its way to it.
 */
class MultiDropRouteWaypointTest extends TestCase
{
    /** Kakinada — priority 1 drop. */
    private const KAKINADA = ['lat' => 16.9891, 'lng' => 82.2475];

    /** Kona Forest — priority 2 drop, ~70 km up the coast. */
    private const KONA = ['lat' => 17.5100, 'lng' => 82.8500];

    /** Pithapuram — where the vehicle is in the screenshots, past Kakinada. */
    private const PITHAPURAM = ['lat' => 17.1167, 'lng' => 82.2500];

    protected function setUp(): void
    {
        parent::setUp();

        // Built by hand rather than migrate:fresh — several tables in this
        // project are created by two migrations each, so a fresh migrate
        // aborts. Same approach as DriverTrackingAlertTest.
        Schema::dropIfExists('bookings');
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_uid')->nullable();
            $table->integer('status')->default(1);
            $table->integer('priority')->nullable();
            $table->unsignedBigInteger('farmer_id')->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedBigInteger('hatchery_id')->nullable();
            $table->string('customer_mobile')->nullable();
            $table->string('dropping_location')->nullable();
            $table->string('delivery_location')->nullable();
            $table->decimal('drop_lat', 12, 8)->nullable();
            $table->decimal('drop_lng', 12, 8)->nullable();
            $table->decimal('pickup_lat', 12, 8)->nullable();
            $table->decimal('pickup_lng', 12, 8)->nullable();
            $table->decimal('vehicle_start_lat', 12, 8)->nullable();
            $table->decimal('vehicle_start_lng', 12, 8)->nullable();
            $table->string('vehicle_start_address')->nullable();
            $table->decimal('driver_lat', 12, 8)->nullable();
            $table->decimal('driver_lng', 12, 8)->nullable();
            $table->string('driver_location_name')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('driver_mobile')->nullable();
            $table->string('vehicle_number')->nullable();
            $table->date('vehicle_started_date')->nullable();
            $table->date('packing_date')->nullable();
            $table->text('tracking_path')->nullable();
            $table->timestamp('tracking_path_at')->nullable();
            $table->timestamp('driver_location_updated_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('driver_assigned_at')->nullable();
            $table->timestamp('in_progress_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('approaching_notified_at')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('drivers');
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('mobile')->nullable();
            $table->string('fcm_token')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::dropIfExists('farmers');
        Schema::create('farmers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('mobile')->nullable();
            $table->integer('status')->default(1);
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();
        });

        // Columns copied from the real migrations, not invented — an invented
        // column would let a test pass against a table production doesn't have.
        Schema::dropIfExists('vehicle_trackings');
        Schema::create('vehicle_trackings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->string('location_name')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('status')->default('pending');
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->timestamp('reached_at')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        Schema::dropIfExists('vehicle_tracking_stops');
        Schema::create('vehicle_tracking_stops', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id')->index();
            $table->string('name');
            $table->decimal('lat', 10, 6);
            $table->decimal('lng', 10, 6);
            $table->string('type')->default('main');
            $table->unsignedBigInteger('parent_stop_id')->nullable();
            $table->boolean('is_key_stop')->default(false);
            $table->string('key_type')->nullable();
            $table->integer('order')->default(0);
            $table->decimal('dist_from_pickup_km', 8, 2)->default(0);
            $table->timestamp('passed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * One driver, two drops. Both in journey (status 4); neither marked
     * delivered. Returns [driver, kakinadaBooking, konaBooking].
     */
    private function seedTwoDropRoute(): array
    {
        $driver = Driver::create([
            'name' => 'ramu',
            'mobile' => '9000000001',
            'status' => 1,
        ]);

        $kakinadaFarmer = Farmer::create(['first_name' => 'Kaki', 'mobile' => '9111111111']);
        $konaFarmer = Farmer::create(['first_name' => 'Kona', 'mobile' => '9222222222']);

        $common = [
            'status' => 4,
            'driver_id' => $driver->id,
            'driver_name' => 'ramu',
            'vehicle_started_date' => now()->toDateString(),
            // Vehicle currently at Pithapuram — already past Kakinada.
            'driver_lat' => self::PITHAPURAM['lat'],
            'driver_lng' => self::PITHAPURAM['lng'],
            'driver_location_updated_at' => now(),
            'in_progress_at' => now()->subHours(2),
            'vehicle_start_lat' => 17.0000,
            'vehicle_start_lng' => 81.7800,
        ];

        $kakinada = Booking::create($common + [
            'priority' => 1,
            'farmer_id' => $kakinadaFarmer->id,
            'customer_mobile' => $kakinadaFarmer->mobile,
            'dropping_location' => 'Kakinada',
            'drop_lat' => self::KAKINADA['lat'],
            'drop_lng' => self::KAKINADA['lng'],
        ]);

        $kona = Booking::create($common + [
            'priority' => 2,
            'farmer_id' => $konaFarmer->id,
            'customer_mobile' => $konaFarmer->mobile,
            'dropping_location' => 'Kona Forest',
            'drop_lat' => self::KONA['lat'],
            'drop_lng' => self::KONA['lng'],
        ]);

        return [$driver, $kakinada, $kona, $kakinadaFarmer, $konaFarmer];
    }

    /**
     * Lay a GPS breadcrumb trail for $bookingId.
     *
     * Each entry is [lat, lng, minutesAgo]. Breadcrumbs are only written while
     * the truck is moving, so a delivery halt is represented by a large jump in
     * minutesAgo between two consecutive points near the drop.
     */
    private function seedBreadcrumbs(int $bookingId, array $points): void
    {
        foreach ($points as $i => [$lat, $lng, $minutesAgo]) {
            \App\Models\VehicleTracking::create([
                'booking_id' => $bookingId,
                'lat' => $lat,
                'lng' => $lng,
                'reached_at' => now()->subMinutes($minutesAgo),
                'order' => $i,
            ]);
        }
    }

    /**
     * The truck drove to Kakinada, stood still for 20 minutes (the unload —
     * no crumbs are written while stationary), then drove on to Pithapuram.
     */
    private function seedTrailThatStoppedAtKakinada(int $bookingId): void
    {
        $this->seedBreadcrumbs($bookingId, [
            [16.9500, 82.2300, 120],   // approaching
            [16.9700, 82.2400, 115],
            [16.9880, 82.2470, 110],   // arrived at Kakinada
            // ── 20-minute gap: standing still, unloading ──
            [16.9900, 82.2480, 90],    // moving again
            [17.0200, 82.2490, 80],    // departed, > 1.5 km away
            [17.1167, 82.2500, 60],    // now at Pithapuram
        ]);
    }

    /**
     * The truck merely drove past Kakinada on the highway without stopping —
     * crumbs every minute, no gap.
     */
    private function seedTrailThatDroveStraightPast(int $bookingId): void
    {
        $this->seedBreadcrumbs($bookingId, [
            [16.9500, 82.2300, 120],
            [16.9700, 82.2400, 119],
            [16.9880, 82.2470, 118],   // right beside the drop, but not stopping
            [16.9900, 82.2480, 117],
            [17.0200, 82.2490, 116],
            [17.1167, 82.2500, 115],
        ]);
    }

    private function trackingFor(Booking $booking, Farmer $farmer): array
    {
        Sanctum::actingAs($farmer, ['*']);
        $response = $this->getJson("/api/farmer/vehicle_tracking/{$booking->id}");
        if ($response->status() !== 200) {
            $this->fail(
                "tracking returned {$response->status()}: " . $response->getContent()
            );
        }
        return $response->json();
    }

    /** The raw route_waypoints array from the response. */
    private function waypoints(array $payload): array
    {
        return data_get($payload, 'data.route_waypoints')
            ?? data_get($payload, 'route_waypoints')
            ?? [];
    }

    /** Drop names present in the returned route_waypoints. */
    private function waypointNames(array $payload): array
    {
        return array_values(array_filter(array_map(
            fn ($w) => $w['name'] ?? null,
            $this->waypoints($payload)
        )));
    }

    /** The is_passed flag for a named waypoint, or null if absent. */
    private function passedFlagFor(array $payload, string $name): ?bool
    {
        foreach ($this->waypoints($payload) as $w) {
            if (($w['name'] ?? null) === $name) {
                return array_key_exists('is_passed', $w) ? (bool) $w['is_passed'] : null;
            }
        }
        return null;
    }

    /**
     * Baseline. The truck has NOT been to Kakinada, so the Kona Forest
     * customer must still be routed through it — the truck really is stopping
     * there first. This is the case that must NOT change.
     */
    public function test_kona_forest_still_routes_through_kakinada_before_the_truck_gets_there(): void
    {
        [, , $kona, , $konaFarmer] = $this->seedTwoDropRoute();

        // A trail that stays well south of Kakinada — never arrives.
        $this->seedBreadcrumbs($kona->id, [
            [16.7000, 82.0000, 40],
            [16.7500, 82.0500, 30],
            [16.8000, 82.1000, 20],
        ]);
        Booking::query()->update(['driver_lat' => 16.8000, 'driver_lng' => 82.1000]);

        $payload = $this->trackingFor($kona, $konaFarmer);

        $this->assertContains('Kakinada', $this->waypointNames($payload));
        $this->assertFalse(
            $this->passedFlagFor($payload, 'Kakinada'),
            'Kakinada must not be marked visited before the truck has been there.'
        );
    }

    /**
     * The reported bug. The truck drove to Kakinada, stood still 20 minutes to
     * unload, then moved on — but the driver never pressed "Delivered", so the
     * booking is still status 4 and the Kona Forest customer was being routed
     * back to it.
     */
    public function test_kona_forest_stops_backtracking_once_the_truck_has_delivered_there(): void
    {
        [, $kakinada, $kona, , $konaFarmer] = $this->seedTwoDropRoute();

        $this->seedTrailThatStoppedAtKakinada($kona->id);

        $payload = $this->trackingFor($kona, $konaFarmer);

        $this->assertTrue(
            $this->passedFlagFor($payload, 'Kakinada'),
            'Kakinada must be marked visited: the truck stopped there for 20 minutes and left.'
        );

        // The booking itself must NOT be touched — it is still undelivered.
        $this->assertSame(4, (int) $kakinada->fresh()->status);
    }

    /**
     * Guards the false positive the design has to avoid: a drop that sits
     * beside a highway the truck merely drives along must NOT count as
     * delivered. Same positions as the test above, but no stop.
     */
    public function test_driving_straight_past_does_not_count_as_delivered(): void
    {
        [, , $kona, , $konaFarmer] = $this->seedTwoDropRoute();

        $this->seedTrailThatDroveStraightPast($kona->id);

        $payload = $this->trackingFor($kona, $konaFarmer);

        $this->assertFalse(
            $this->passedFlagFor($payload, 'Kakinada'),
            'Driving past without stopping must not be treated as a delivery.'
        );
    }

    /**
     * The waypoint is FLAGGED, never removed. The client slices the green
     * "covered" line out of the full route and computes the arrival time as a
     * fraction of it, so dropping a waypoint would corrupt both.
     */
    public function test_the_visited_waypoint_is_still_returned_not_removed(): void
    {
        [, , $kona, , $konaFarmer] = $this->seedTwoDropRoute();

        $this->seedTrailThatStoppedAtKakinada($kona->id);

        $payload = $this->trackingFor($kona, $konaFarmer);

        $this->assertContains(
            'Kakinada',
            $this->waypointNames($payload),
            'The waypoint must still be present so the full route and ETA stay intact.'
        );
    }

    /**
     * The Kakinada customer's own view must be untouched: their delivery is
     * unconfirmed, so they keep tracking the truck exactly as before.
     */
    public function test_kakinada_customer_view_is_unaffected(): void
    {
        [, $kakinada, $kona, $kakinadaFarmer] = $this->seedTwoDropRoute();

        $this->seedTrailThatStoppedAtKakinada($kona->id);
        $this->seedTrailThatStoppedAtKakinada($kakinada->id);

        $payload = $this->trackingFor($kakinada, $kakinadaFarmer);

        // Priority 1 has no earlier stops, so it gets no waypoints at all —
        // which is precisely why the new logic cannot affect this customer.
        $this->assertSame(
            [],
            $this->waypoints($payload),
            'Priority 1 has no earlier-priority stops, so its waypoint list stays empty.'
        );
        $this->assertSame(4, (int) $kakinada->fresh()->status);
    }
}
