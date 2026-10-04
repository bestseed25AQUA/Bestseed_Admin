<?php

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
 *
 * ── Why the query builder, and not the Eloquent models ──────────────────────
 *
 * This used `Tank::query()`, and on a fresh database it died:
 *
 *   Unknown column 'tanks.deleted_at' in 'where clause'
 *
 * The Tank MODEL gained `SoftDeletes` on 2026-09-19, eighteen days after this
 * migration is dated, so Eloquent appends `deleted_at IS NULL` to a column
 * that does not exist yet at this point in the run. It passed on the original
 * database only because it had already run there before the model changed —
 * the kind of bug that stays invisible until somebody builds from scratch.
 *
 * A migration has to describe the schema as it was ON ITS OWN DATE. Models
 * describe the schema as it is TODAY, and the two drift apart the moment a
 * column or a trait is added. So nothing here goes through a model: the
 * builder sees only the columns that actually exist as this runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tank_batches') || !Schema::hasTable('tanks')) {
            return;
        }

        // Tanks with no batch at all.
        //
        // A subquery rather than pulling every batch's tank_id into PHP and
        // sending it back as one enormous IN list — that was 1,100+ bindings
        // in a single statement on a database of any age.
        $orphans = DB::table('tanks')
            ->whereNotIn('id', function ($query) {
                $query->select('tank_id')
                    ->from('tank_batches')
                    ->whereNotNull('tank_id');
            })
            ->get();

        // Columns this migration reads that were added later are checked
        // rather than assumed, for the same reason as above.
        $hasStockingDate = Schema::hasColumn('tanks', 'stocking_date');

        foreach ($orphans as $tank) {
            DB::transaction(function () use ($tank, $hasStockingDate) {
                $farmDate = DB::table('farms')
                    ->where('id', $tank->farm_id)
                    ->value('stocking_date');

                $stockingDate = $hasStockingDate
                    ? ($tank->stocking_date ?: $farmDate)
                    : $farmDate;

                // insertGetId, so the feed below can be pointed at this batch.
                // The builder does not fill timestamps the way a model would,
                // so they are written explicitly.
                $batchId = DB::table('tank_batches')->insertGetId([
                    'tank_id'       => $tank->id,
                    'farm_id'       => $tank->farm_id,
                    'batch_no'      => 1,
                    'stocking_date' => $stockingDate,
                    'started_at'    => $tank->created_at ?: now(),
                    'ended_at'      => null,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);

                // Adopt only rows belonging to no batch: anything already
                // assigned belongs to a crop cycle of its own.
                DB::table('feeds')
                    ->where('tank_id', $tank->id)
                    ->whereNull('batch_id')
                    ->update(['batch_id' => $batchId]);

                DB::table('tank_feed_histories')
                    ->where('tank_id', $tank->id)
                    ->whereNull('batch_id')
                    ->update(['batch_id' => $batchId]);

                // The tank's own total is rebuilt from what it now owns.
                DB::table('tanks')
                    ->where('id', $tank->id)
                    ->update([
                        'total_feed_used' => (float) DB::table('feeds')
                            ->where('tank_id', $tank->id)
                            ->sum('feed_quantity'),
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
