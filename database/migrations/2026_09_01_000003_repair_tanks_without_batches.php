<?php

use App\Models\Feed;
use App\Models\Tank;
use App\Models\TankBatch;
use App\Models\TankFeedHistory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give a batch to every tank that has none, and adopt its orphaned feed.
 *
 * create_tank_batches opened a batch for every tank that existed when it ran,
 * but farm creation went on making tanks without one. Since the farm's Total
 * Feed Used counts only feed belonging to a running batch, those farms read
 * "0 kgs" no matter how much feed was recorded — the rows were there, just
 * attached to nothing.
 *
 * The cause is fixed in FarmController::createFarm; this repairs the farms
 * created in between.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tank_batches') || !Schema::hasTable('tanks')) {
            return;
        }

        $withBatch = TankBatch::query()->pluck('tank_id')->unique()->all();

        $orphans = Tank::query()
            ->when($withBatch, fn ($q) => $q->whereNotIn('id', $withBatch))
            ->get();

        foreach ($orphans as $tank) {
            DB::transaction(function () use ($tank) {
                $farmDate = DB::table('farms')->where('id', $tank->farm_id)->value('stocking_date');

                $batch = TankBatch::create([
                    'tank_id'       => $tank->id,
                    'farm_id'       => $tank->farm_id,
                    'batch_no'      => 1,
                    'stocking_date' => $tank->stocking_date ?: $farmDate,
                    'started_at'    => $tank->created_at ?: now(),
                    'ended_at'      => null,
                ]);

                // Adopt only rows belonging to no batch: anything already
                // assigned belongs to a crop cycle of its own.
                Feed::where('tank_id', $tank->id)->whereNull('batch_id')
                    ->update(['batch_id' => $batch->id]);

                TankFeedHistory::where('tank_id', $tank->id)->whereNull('batch_id')
                    ->update(['batch_id' => $batch->id]);

                // The tank's own total is rebuilt from what it now owns.
                Tank::where('id', $tank->id)->update([
                    'total_feed_used' => (float) Feed::where('tank_id', $tank->id)->sum('feed_quantity'),
                ]);
            });
        }
    }

    public function down(): void
    {
        // The batches are now the tanks' real crop cycles; removing them would
        // orphan the feed all over again.
    }
};
