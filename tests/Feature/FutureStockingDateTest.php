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
 * A pond is often prepared before it is stocked, so a stocking date may be in
 * the future. Three separate layers used to prevent it: the validator refused
 * it, the meta parser threw it away, and the day count would have gone
 * negative.
 */
class FutureStockingDateTest extends TestCase
{
    public function test_a_future_stocking_date_is_kept_and_reads_day_zero(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Needs the development MySQL database.');
        }

        $farmer = Farmer::first();
        $future = now()->addDays(5)->toDateString();

        $response = $this->actingAs($farmer, 'sanctum')
            ->postJson('/api/farmer/create-farm', [
                'farm_name'  => 'Future Stocking Farm',
                'tanks'      => 2,
                'tanks_meta' => json_encode([
                    ['stocking_date' => $future, 'feed_used_before' => 0],
                    ['stocking_date' => $future, 'feed_used_before' => 0],
                ]),
            ]);

        $farm = Farm::where('farm_name', 'Future Stocking Farm')->latest('id')->first();

        try {
            $response->assertStatus(201);
            $this->assertNotNull($farm);

            // 1. the date survived — it used to be silently discarded
            $tanks = Tank::where('farm_id', $farm->id)->get();
            $this->assertCount(2, $tanks);

            foreach ($tanks as $tank) {
                $this->assertSame(
                    $future,
                    \Illuminate\Support\Carbon::parse($tank->stocking_date)->toDateString(),
                    'A future stocking date must be stored, not dropped.'
                );
            }

            // 2. no history was invented for days that have not happened
            $this->assertSame(0, Feed::where('farm_id', $farm->id)->count());
            $this->assertSame(0, TankFeedHistory::where('farm_id', $farm->id)->count());

            // 3. the tank list reports Day 0, never a negative count
            $list = $this->actingAs($farmer, 'sanctum')
                ->getJson("/api/farmer/farms/{$farm->id}/tanks")
                ->assertOk()
                ->json();

            foreach ($list['data'] as $tank) {
                $this->assertSame(
                    0,
                    (int) $tank['day'],
                    'A tank stocked in the future has not started: Day 0.'
                );
            }
        } finally {
            if ($farm) {
                TankBatch::where('farm_id', $farm->id)->delete();
                Feed::where('farm_id', $farm->id)->delete();
                TankFeedHistory::where('farm_id', $farm->id)->delete();
                Tank::where('farm_id', $farm->id)->delete();
                $farm->forceDelete();
            }
        }
    }
}
