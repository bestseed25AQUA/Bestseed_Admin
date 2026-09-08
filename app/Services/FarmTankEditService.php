<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\Tank;
use App\Models\TankBatch;
use Carbon\Carbon;

/**
 * Corrections to the tanks a farm already has.
 *
 * Lifted out of the API's FarmController so the admin panel applies exactly
 * the same rules. The logic is subtle — it rewrites GENERATED history while
 * preserving feed the farmer recorded by hand, and moves the crop cycle with
 * the tank — and two copies of it would drift.
 */
class FarmTankEditService
{
    /**
     * Apply per-tank date and prior-feed corrections.
     *
     * Each row is `{id, stocking_date, feed_used_before}`. A tank whose date or
     * figure has changed has its GENERATED history rewritten: the old
     * is_backfill rows go and new ones are written from the new values. Feed
     * the farmer recorded by hand is never touched — it carries no backfill
     * mark — so correcting a stocking date does not cost them their entries.
     *
     * Ids not belonging to this farm are ignored rather than refused, so a
     * stale form cannot reach into another farm's tanks.
     *
     * @param  array<int, mixed>  $rows
     * @return int  how many tanks were changed
     */
    public function applyExistingEdits(Farm $farm, array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        $tanks = Tank::where('farm_id', $farm->id)->get()->keyBy('id');
        $backfill = app(FeedBackfillService::class);
        $today = Carbon::now()->startOfDay();
        $changed = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $tank = $tanks->get((int) ($row['id'] ?? 0));
            if (!$tank) {
                continue;
            }

            // Same parsing as a new tank: a future date is treated as unset
            // rather than generating history that has not happened.
            $date = null;
            if (!empty($row['stocking_date'])) {
                try {
                    // A future date is kept: a pond can be set up before it
                    // is stocked. No history is generated for it — the
                    // backfill stops short of dates that have not arrived —
                    // and the tank simply reads Day 0 until the day comes.
                    $date = Carbon::parse($row['stocking_date'])
                        ->startOfDay()
                        ->toDateString();
                } catch (\Throwable $e) {
                    $date = null;
                }
            }

            $used = (float) ($row['feed_used_before'] ?? 0);
            $used = $used > 0 ? $used : 0.0;

            $oldDate = $tank->stocking_date
                ? Carbon::parse($tank->stocking_date)->toDateString()
                : null;
            $oldUsed = $backfill->backfilledTotalFor((int) $tank->id);

            $dateChanged = $oldDate !== $date;
            $usedChanged = abs($oldUsed - $used) > 0.001;

            if (!$dateChanged && !$usedChanged) {
                continue;
            }

            $tank->stocking_date = $date;
            $tank->save();

            // The tank's crop cycle has to move with it.
            //
            // Everything scoped to a batch reads the BATCH's stocking date, not
            // the tank's: the day count, and the feed report's day-by-day
            // range. Leaving it behind meant correcting a tank from today back
            // to 1 August rewrote a month of history and then reported on a
            // single day — the report opened at the stale date and every row
            // before it fell outside the window, so a tank with 546 kg across
            // 33 days produced "Days 1, 61.92 kg".
            //
            // currentFor(): the open batch, or the most recent one when the
            // tank has been harvested — the same batch the app and the report
            // are showing.
            $batch = TankBatch::currentFor((int) $tank->id);

            if ($batch) {
                $batch->stocking_date = $date;
                $batch->feed_used_before = $used > 0 ? $used : null;
                $batch->save();
            }

            // Rewrite, not append.
            $backfill->clearForTank((int) $tank->id);
            $backfill->applyForTank($farm, (int) $tank->id, $date, $used);

            $changed++;
        }

        return $changed;
    }
}
