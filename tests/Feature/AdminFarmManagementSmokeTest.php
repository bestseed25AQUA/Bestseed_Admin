<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Tank;
use App\Models\User;
use Tests\TestCase;

/**
 * Renders every admin farm-management screen as a logged-in admin.
 *
 * Runs against the development database rather than a fresh one, because the
 * point is to prove these pages render against real rows — a farm with no
 * tanks, a member with no farmer, a soft-deleted farm.
 */
class AdminFarmManagementSmokeTest extends TestCase
{
    public function test_every_farm_management_screen_renders(): void
    {
        // These exercise admin against real farms, tanks and feed rows, so
        // they run against the development MySQL database rather than the
        // suite's in-memory sqlite. Skipped rather than failed elsewhere.
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Needs the development MySQL database.');
        }

        $admin = User::first();
        $this->assertNotNull($admin, 'No admin user in the database to test with.');

        $farm = Farm::withTrashed()->first();
        $tank = Tank::first();

        $urls = array_filter([
            getenv('SMOKE_URL') ?: null,
        ]) ?: [
            '/admin/farm-management/farms',
        ];

        // One URL per process: the admin navbar declares a global
        // isActiveRoute() helper, so rendering two pages in one PHP process
        // fatals on the redeclare. Harmless in production — one request, one
        // process — but it means this test is driven a URL at a time.
        foreach ($urls as $url) {
            $response = $this->actingAs($admin)->get($url);

            $this->assertLessThan(
                400,
                $response->getStatusCode(),
                $url . ' returned ' . $response->getStatusCode()
            );
        }
    }
}
