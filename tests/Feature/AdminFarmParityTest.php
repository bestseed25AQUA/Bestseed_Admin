<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Farmer;
use App\Models\Feed;
use App\Models\FarmAccessMember;
use App\Models\Tank;
use App\Models\TankFeedHistory;
use App\Models\User;
use Tests\TestCase;

/**
 * A farm an admin creates must be indistinguishable from one the farmer made.
 *
 * The whole point of the admin panel is that a farmer in trouble can be helped
 * without touching the database by hand — so what admin writes has to land in
 * the same tables, in the same shape, and show up in the app.
 */
class AdminFarmParityTest extends TestCase
{
    public function test_admin_created_farm_matches_the_app(): void
    {
        // These exercise admin against real farms, tanks and feed rows, so
        // they run against the development MySQL database rather than the
        // suite's in-memory sqlite. Skipped rather than failed elsewhere.
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Needs the development MySQL database.');
        }

        $admin  = User::first();
        $farmer = Farmer::first();

        $stocked = now()->subDays(9)->toDateString();

        $response = $this->actingAs($admin)->post('/admin/farm-management/farms', [
            'farm_name'        => 'Parity Test Farm',
            'farmer_id'        => $farmer->id,
            'status'           => 1,
            'stocking_date'    => $stocked,
            'no_of_tanks'      => 3,
            'store'            => 5000,
            'low_feed_limit'   => 200,
            'feed_used_before' => 1500,
        ]);

        $farm = Farm::where('farm_name', 'Parity Test Farm')->latest('id')->first();
        $this->assertNotNull($farm, 'Admin farm create did not persist a farm.');

        try {
            $response->assertRedirect();

            // 1. tanks exist — the app creates them with the farm
            $tanks = Tank::where('farm_id', $farm->id)->get();
            $this->assertCount(3, $tanks, 'Admin must create the farm\'s tanks.');
            $this->assertSame('Tank1', $tanks->first()->tank_name);

            // 2. the backfill landed in BOTH feed tables
            $days = 10; // 9 days ago .. today, inclusive
            $expectedRows = 3 * $days;

            $this->assertSame($expectedRows, Feed::where('farm_id', $farm->id)->count());
            $this->assertSame($expectedRows, TankFeedHistory::where('farm_id', $farm->id)->count());

            // 3. and it adds up to EXACTLY what was entered, not 1500.08
            $this->assertEqualsWithDelta(
                1500.0,
                (float) Feed::where('farm_id', $farm->id)->sum('feed_quantity'),
                0.001,
                'Backfilled rows must reconcile with the figure entered.'
            );

            // 4. every tank's running total is in step
            $this->assertEqualsWithDelta(
                1500.0,
                (float) Tank::where('farm_id', $farm->id)->sum('total_feed_used'),
                0.001
            );

            // 5. meals ramp the way the app ramps them
            $firstDay = TankFeedHistory::where('farm_id', $farm->id)
                ->whereDate('feed_date', $stocked)->first();
            $lastDay = TankFeedHistory::where('farm_id', $farm->id)
                ->whereDate('feed_date', now()->toDateString())->first();

            $this->assertSame(2, (int) $firstDay->meals, 'Day 1 is 2 meals.');
            $this->assertSame(3, (int) $lastDay->meals, 'Day 10 is 3 meals.');

            // 6. the farm is visible to its owner through the app's own query
            $this->assertTrue(
                Farm::accessibleBy($farmer->id)->where('id', $farm->id)->exists(),
                'A farm created by admin must appear in the owner\'s app.'
            );

            // 7. correcting the figure replaces the history rather than adding
            $this->actingAs($admin)->put("/admin/farm-management/farms/{$farm->id}", [
                'farm_name'        => 'Parity Test Farm',
                'farmer_id'        => $farmer->id,
                'status'           => 1,
                'stocking_date'    => $stocked,
                'no_of_tanks'      => 3,
                'feed_used_before' => 3000,
            ])->assertRedirect();

            $this->assertSame(
                $expectedRows,
                Feed::where('farm_id', $farm->id)->count(),
                'Correcting the figure must replace generated rows, not stack more.'
            );
            $this->assertEqualsWithDelta(
                3000.0,
                (float) Feed::where('farm_id', $farm->id)->sum('feed_quantity'),
                0.001
            );

            // 8. the CSV report downloads
            $csv = $this->actingAs($admin)
                ->get("/admin/farm-management/farms/{$farm->id}/tanks/{$tanks->first()->id}/feed/report");
            $csv->assertOk();
            $this->assertStringContainsString('text/csv', $csv->headers->get('Content-Type'));
        } finally {
            Feed::where('farm_id', $farm->id)->delete();
            TankFeedHistory::where('farm_id', $farm->id)->delete();
            Tank::where('farm_id', $farm->id)->delete();
            FarmAccessMember::where('farm_id', $farm->id)->delete();
            $farm->forceDelete();
        }
    }
}
