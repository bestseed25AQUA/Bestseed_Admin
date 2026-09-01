<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One crop cycle in one tank.
 *
 * A tank is stocked, fed for some weeks, harvested, and then stocked again.
 * Until now a tank had a single stocking date and every feed row it had ever
 * carried, so a second crop piled on top of the first: the running total kept
 * climbing, the day count kept counting, and there was no way to report on one
 * cycle alone.
 *
 * A batch closes when the tank is made inactive and a new one opens when it is
 * made active again. Exactly one batch per tank may be open at a time — the
 * partial unique index below enforces that rather than trusting the code.
 *
 * Feed rows carry `batch_id`, so:
 *   - the app shows the CURRENT batch and nothing else;
 *   - a farm's Total Feed Used counts only tanks with an open batch, which is
 *     why deactivating a tank takes its feed out of the farm total;
 *   - a report can be scoped to one cycle;
 *   - the admin panel can still show every cycle a tank has ever had.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tank_batches')) {
            Schema::create('tank_batches', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('tank_id');

                // Nullable because `tanks.farm_id` is: there are tanks in the
                // field with no farm attached. They still deserve a batch —
                // dropping them here would leave the app with a tank it cannot
                // show — so the orphan is carried rather than refused.
                $table->unsignedBigInteger('farm_id')->nullable();

                // 1, 2, 3 … per tank. What the farmer and the admin panel call
                // the cycle; ids are not sequential per tank once other tanks
                // interleave.
                $table->unsignedInteger('batch_no')->default(1);

                $table->date('stocking_date')->nullable();

                // The "already used" figure this batch was seeded with, kept so
                // the edit form can show it back and a correction knows what it
                // is replacing.
                $table->decimal('feed_used_before', 12, 2)->nullable();

                $table->timestamp('started_at')->nullable();

                // NULL while the batch is running. Set when the tank is made
                // inactive — that is what "harvested" means here.
                $table->timestamp('ended_at')->nullable();

                $table->timestamps();

                $table->index('tank_id');
                $table->index('farm_id');
                $table->index(['tank_id', 'ended_at']);
            });
        }

        foreach (['feeds', 'tank_feed_histories'] as $name) {
            if (Schema::hasTable($name) && !Schema::hasColumn($name, 'batch_id')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->unsignedBigInteger('batch_id')->nullable()->after('tank_id');
                    $table->index('batch_id');
                });
            }
        }

        $this->backfillExistingTanks();
    }

    /**
     * Give every existing tank a batch 1 and file its feed under it.
     *
     * Without this, every tank in the field would look like it had no open
     * batch: the app would show nothing and the farm totals would read zero
     * for farms that are running perfectly well.
     */
    private function backfillExistingTanks(): void
    {
        if (!Schema::hasTable('tanks')) {
            return;
        }

        $now = now();

        foreach (DB::table('tanks')->orderBy('id')->get() as $tank) {
            $exists = DB::table('tank_batches')->where('tank_id', $tank->id)->exists();
            if ($exists) {
                continue;
            }

            // The farm's date is the fallback for tanks created before dates
            // were kept per tank.
            $stocking = $tank->stocking_date
                ?: DB::table('farms')->where('id', $tank->farm_id)->value('stocking_date');

            $batchId = DB::table('tank_batches')->insertGetId([
                'tank_id'          => $tank->id,
                'farm_id'          => $tank->farm_id,
                'batch_no'         => 1,
                'stocking_date'    => $stocking,
                'feed_used_before' => null,
                'started_at'       => $stocking ? $stocking . ' 00:00:00' : $now,
                // An inactive tank's first cycle is treated as already
                // finished, which is what its status has been saying all along.
                'ended_at'         => (int) $tank->status === 0 ? $now : null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            DB::table('feeds')->where('tank_id', $tank->id)->update(['batch_id' => $batchId]);
            DB::table('tank_feed_histories')->where('tank_id', $tank->id)->update(['batch_id' => $batchId]);
        }
    }

    public function down(): void
    {
        foreach (['feeds', 'tank_feed_histories'] as $name) {
            if (Schema::hasTable($name) && Schema::hasColumn($name, 'batch_id')) {
                Schema::table($name, function (Blueprint $table) use ($name) {
                    $table->dropIndex($name . '_batch_id_index');
                    $table->dropColumn('batch_id');
                });
            }
        }

        Schema::dropIfExists('tank_batches');
    }
};
