<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Farmer;
use App\Models\Feed;
use App\Models\Tank;
use App\Models\TankBatch;
use App\Models\TankFeedHistory;
use Tests\TestCase;

/**
 * A farm's Total Feed Used counts feed belonging to a running batch, so every
 * tank must be given one the moment it is created. Farm creation used to skip
 * that, orphaning every backfilled row at batch_id NULL — the farm showed
 * "0 kgs" while the rows sat in the table.
 */
class FarmTotalFeedUsedTest extends TestCase
{
    public function test_a_new_farm_reports_the_feed_it_was_given(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Needs the development MySQL database.');
        }

        $farmer = Farmer::first();

        $response = $this->actingAs($farmer, 'sanctum')
            ->postJson('/api/farmer/create-farm', [
                'farm_name'  => 'Total Feed Used Farm',
                'tanks'      => 3,
                'tanks_meta' => json_encode([
                    ['stocking_date' => now()->subDays(9)->toDateString(), 'feed_used_before' => 500],
                    ['stocking_date' => now()->subDays(4)->toDateString(), 'feed_used_before' => 300],
                    // Stocked today: nothing has been fed yet.
                    ['stocking_date' => now()->toDateString(), 'feed_used_before' => 0],
                ]),
            ]);

        $farm = Farm::where('farm_name', 'Total Feed Used Farm')->latest('id')->first();

        try {
            $response->assertStatus(201);

            // 1. every tank got a batch
            $this->assertSame(
                3,
                TankBatch::where('farm_id', $farm->id)->count(),
                'Each tank must open a crop cycle when the farm is created.'
            );

            // 2. no feed row is orphaned
            $this->assertSame(
                0,
                Feed::where('farm_id', $farm->id)->whereNull('batch_id')->count(),
                'Backfilled rows must belong to their tank\'s batch.'
            );

            // 3. the farm list reports the real figure, not 0
            $list = $this->actingAs($farmer, 'sanctum')
                ->getJson('/api/farmer/farm-lists')
                ->assertOk()
                ->json();

            $row = collect($list['data'])->firstWhere('id', $farm->id);
            $this->assertNotNull($row);

            $this->assertEqualsWithDelta(
                800.0,
                (float) $row['total_feed_used'],
                0.01,
                'Total Feed Used must be 500 + 300, not 0.'
            );
        } finally {
            if ($farm) {
                Feed::where('farm_id', $farm->id)->delete();
                TankFeedHistory::where('farm_id', $farm->id)->delete();
                TankBatch::where('farm_id', $farm->id)->delete();
                Tank::where('farm_id', $farm->id)->delete();
                $farm->forceDelete();
            }
        }
    }
}
