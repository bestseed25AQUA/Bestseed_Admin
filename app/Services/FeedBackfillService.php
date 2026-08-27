<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\Feed;
use App\Models\Tank;
use App\Models\TankFeedHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Generates a farm's feed history from a single "already used" figure.
 *
 * A farm registered weeks after it was stocked has history nothing recorded,
 * so every tank reads "0 kgs, Day 0". One number — the total fed so far — is
 * spread evenly across every tank and every day since stocking:
 *
 *     per tank         = total / tankCount
 *     per tank per day = per tank / days since stocking
 *
 * A row per tank per day means the tank history screen shows every date from
 * stocking to today, exactly as if it had been recorded daily. Totals, the
 * Days count and the low-feed check all pick it up, because they read these
 * same tables.
 *
 * Lives here rather than in a controller because both the farmer app and the
 * admin panel create farms, and a farm built by an admin must be
 * indistinguishable from one the farmer made themselves.
 */
class FeedBackfillService
{
    /** Refuse rather than lock up the request on an absurd date range. */
    public const MAX_ROWS = 5000;

    /**
     * Spread `$totalUsed` across the tanks and days since stocking.
     *
     * Safe to call with nothing to do — no total, no tanks, or no stocking
     * date all return quietly.
     */
    public function apply(Farm $farm, array $tankIds, float $totalUsed): void
    {
        if ($totalUsed <= 0 || empty($tankIds) || empty($farm->stocking_date)) {
            return;
        }

        try {
            $start = Carbon::parse($farm->stocking_date)->startOfDay();
            $today = Carbon::now()->startOfDay();

            // Nothing to spread for a farm stocked today or in the future.
            if ($start->greaterThanOrEqualTo($today)) {
                return;
            }

            $days = $start->diffInDays($today) + 1; // inclusive of both ends

            if ($days * count($tankIds) > self::MAX_ROWS) {
                Log::warning('Skipped feed backfill: too many rows', [
                    'farm_id' => $farm->id,
                    'days'    => $days,
                    'tanks'   => count($tankIds),
                ]);
                return;
            }

            $rowCount = count($tankIds) * $days;

            // Split so the rows add up to EXACTLY what was entered.
            //
            // Rounding each row to 2dp independently drifts: 25000 over 138
            // rows is 181.159420, which rounds to 181.16 and multiplies back
            // to 25000.08. Round DOWN to a base, then hand out the leftover a
            // paisa at a time, so the sum reconciles with the entered figure.
            $base      = floor($totalUsed / $rowCount * 100) / 100;
            $remainder = (int) round(($totalUsed - $base * $rowCount) * 100);

            if ($base <= 0 && $remainder <= 0) {
                return;
            }

            $now   = now();
            $rows  = [];
            $index = 0;

            foreach ($tankIds as $tankId) {
                for ($d = 0; $d < $days; $d++) {
                    // The first $remainder rows carry one extra paisa.
                    $quantity = $base + ($index < $remainder ? 0.01 : 0);
                    $index++;

                    $rows[] = [
                        'meals'         => $this->mealsForDay($d + 1),
                        'tank_id'       => $tankId,
                        'farm_id'       => $farm->id,
                        'feed_quantity' => round($quantity, 2),
                        'feed_date'     => $start->copy()->addDays($d)->toDateString(),
                        'is_backfill'   => 1,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ];
                }
            }

            DB::transaction(function () use ($rows, $tankIds) {
                foreach (array_chunk($rows, 500) as $chunk) {
                    Feed::insert($chunk);
                    TankFeedHistory::insert($chunk);
                }

                // Keep each tank's running total in step with its own rows —
                // they can differ by a paisa after the remainder is handed out.
                foreach ($tankIds as $tankId) {
                    Tank::where('id', $tankId)->update([
                        'total_feed_used' => (float) Feed::where('tank_id', $tankId)->sum('feed_quantity'),
                    ]);
                }
            });

            // Remember what was entered so the edit form can show it back, and
            // so a later correction knows what it is replacing.
            $farm->forceFill(['feed_used_before' => $totalUsed])->save();
        } catch (\Throwable $e) {
            Log::error('Feed backfill failed', [
                'farm_id' => $farm->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove a farm's generated back-history, leaving hand-entered feed alone.
     *
     * Only rows written by [apply] carry is_backfill = 1, so someone who has
     * been recording daily since keeps every one of those entries when the
     * "already used" figure is corrected.
     */
    public function clear(Farm $farm): void
    {
        // A farm generated before is_backfill existed has no marked rows, so
        // there is no way to tell its generated history from hand-entered
        // feed. Correcting its figure therefore rewrites the farm's feed
        // history wholesale; from then on the marks exist and only generated
        // rows go.
        $legacy = $farm->feed_used_before === null
            && Feed::where('farm_id', $farm->id)->where('is_backfill', 1)->doesntExist();

        DB::transaction(function () use ($farm, $legacy) {
            Feed::where('farm_id', $farm->id)
                ->when(!$legacy, fn ($q) => $q->where('is_backfill', 1))
                ->delete();

            TankFeedHistory::where('farm_id', $farm->id)
                ->when(!$legacy, fn ($q) => $q->where('is_backfill', 1))
                ->delete();

            // Rebuild each tank's running total from whatever survived.
            foreach (Tank::where('farm_id', $farm->id)->pluck('id') as $tankId) {
                Tank::where('id', $tankId)->update([
                    'total_feed_used' => (float) Feed::where('tank_id', $tankId)->sum('feed_quantity'),
                ]);
            }

            $farm->forceFill(['feed_used_before' => null])->save();
        });
    }

    /**
     * Meals per day for a stock that is [$dayNumber] days old (1 = stocking).
     *
     *   days  1-7   -> 2 meals
     *   days  8-14  -> 3 meals
     *   day  15+    -> 4 meals
     *
     * Feeding steps up as the stock grows, so a backfilled history using a
     * flat 1 meal a day did not resemble how the farm was actually run.
     */
    public function mealsForDay(int $dayNumber): int
    {
        if ($dayNumber <= 7) {
            return 2;
        }

        if ($dayNumber <= 14) {
            return 3;
        }

        return 4;
    }
}
