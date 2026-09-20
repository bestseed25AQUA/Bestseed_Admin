<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\Feed;
use App\Models\TankBatch;

/**
 * What is left in a farm's feed store, and what to write when someone edits it.
 *
 * One definition, because the figure appears in five places — the farm list,
 * the farm header, the low-feed warning, the app's edit sheet and the admin
 * panel's farm form — and they must never disagree about the same farm.
 *
 * Only feed the farmer RECORDS draws on the store: today's meals, and anything
 * entered against a past day in the tank history. Two things do not:
 *
 *  - The "feed already used" figure, wherever it is entered. It generates
 *    history so a tank reads its true age and consumption, but it was fed
 *    before any of this was recorded here; it never came out of the stock the
 *    farmer is counting.
 *
 *  - Which batch the feed belongs to. Every batch counts, open or closed, so
 *    deactivating a tank does not hand its feed back to the store and
 *    reactivating one does not take it away again.
 */
class FarmStoreService
{
    /**
     * Feed that has actually come out of this farm's store.
     *
     * One query, two sums: the farm list would otherwise run two per farm.
     *
     * The generated total is SUBTRACTED rather than filtered out with
     * `is_backfill != 1`, because rows written before that column existed hold
     * NULL — a `!=` comparison silently drops them, and they are hand-entered
     * feed that must count.
     */
    /**
     * Feed used by the crops CURRENTLY RUNNING on this farm.
     *
     * The figure the app shows as "Total feed used". A finished crop's feed is
     * history and drops out — the farm's total is what is in progress, not what
     * the farm has ever been fed.
     *
     * Two conditions, not one. The batch must be open AND its tank must be
     * active, because the two can disagree: a tank deactivated by an older code
     * path, or edited straight in the database, can be left showing inactive
     * while its batch is still open. Counting on the batch alone then added a
     * harvested tank's feed to the total — a farm reading 560 kg when the two
     * running tanks held 260 between them, the extra 300 belonging to a tank
     * the farmer could see was switched off.
     *
     * Requiring both means the total can only ever agree with what is on
     * screen. Reconciling the underlying rows is a separate job:
     * `php artisan tanks:reconcile-status --fix`.
     *
     * One place, because this was computed in two and nothing kept them in
     * step.
     */
    public function totalFeedUsedFor(Farm $farm): float
    {
        $runningBatches = TankBatch::query()
            ->where('tank_batches.farm_id', $farm->id)
            ->open()
            ->whereExists(function ($q) {
                $q->selectRaw(1)
                    ->from('tanks')
                    ->whereColumn('tanks.id', 'tank_batches.tank_id')
                    ->where('tanks.status', 1)
                    ->whereNull('tanks.deleted_at');
            })
            ->pluck('id');

        if ($runningBatches->isEmpty()) {
            return 0.0;
        }

        return (float) Feed::where('farm_id', $farm->id)
            ->whereIn('batch_id', $runningBatches)
            ->sum('feed_quantity');
    }

    public function recordedFeedFor(Farm $farm): float
    {
        // Aliased `total_fed` / `backfilled`, not `total` / `generated`:
        // GENERATED is a reserved word in MySQL 8, and an unquoted alias by
        // that name is a syntax error, not a warning.
        $fed = Feed::where('farm_id', $farm->id)
            ->selectRaw('COALESCE(SUM(feed_quantity), 0) AS total_fed')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_backfill = 1 THEN feed_quantity ELSE 0 END), 0) AS backfilled')
            ->first();

        return (float) $fed->total_fed - (float) $fed->backfilled;
    }

    /**
     * What is left, or NULL when no stock figure has been entered.
     *
     * NULL rather than 0: `store` is optional, and casting a missing one to 0
     * reported the farm as thousands of kilos overdrawn rather than unknown.
     */
    public function remainingFor(Farm $farm): ?float
    {
        if ($farm->store === null || $farm->store === '') {
            return null;
        }

        return round((float) $farm->store - $this->recordedFeedFor($farm), 2);
    }

    /**
     * The `store` column to save when someone types what the farm has NOW.
     *
     * Every store field shows a REMAINDER, so what comes back from one is a
     * remainder too. Written straight to `store` — which every remaining figure
     * computes as `store − feed recorded` — it would immediately have that feed
     * taken off a second time: a farm holding 9,900 with 100 kg recorded, saved
     * untouched, would drop to 9,800 and keep dropping on every save.
     *
     * Adding the recorded feed back makes `store` the total ever put in, so the
     * remainder comes out as exactly the figure that was typed and falls from
     * there as feed is recorded. Buying 5,000 more and typing 14,900 does the
     * same thing: the shed reads 14,900 from that moment on.
     */
    public function columnForRemaining(Farm $farm, float $remaining): float
    {
        return round($remaining + $this->recordedFeedFor($farm), 2);
    }
}
