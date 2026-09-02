<?php

namespace App\Services;

use App\Models\Feed;
use App\Models\Tank;
use App\Models\TankBatch;
use App\Models\TankFeedHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Every write to a tank's feed record goes through here.
 *
 * Feed is stored twice — `feeds` and `tank_feed_histories` — and the two have
 * drifted apart before, because each caller reimplemented the write. One
 * service means the app and the admin panel cannot disagree about what a
 * tank was fed.
 *
 * `feeds.feed_date` is a DATETIME and `tank_feed_histories.feed_date` a DATE,
 * so pairing the two rows always compares by day, never by equality.
 */
class TankFeedService
{
    /**
     * Add one feed entry to a tank on a given day. Returns the history row.
     *
     * `$meals` is the meal's NUMBER within its day — 1 for the first feed, 2
     * for the next — not a count, because one row IS one meal. That is how the
     * app writes them and how the report counts a day's meals.
     */
    public function record(int $tankId, int $farmId, string $date, float $meals, float $quantity): TankFeedHistory
    {
        $day = Carbon::parse($date)->toDateString();

        // Which crop cycle this belongs to.
        //
        // Feed with no batch is invisible to the farmer: the farm's Total Feed
        // Used counts only the feed of OPEN batches, and a tank's report is
        // built for one batch, so a row written without this reached neither.
        // Every entry the admin panel added would have vanished from the app.
        $batchId = optional(TankBatch::currentFor($tankId))->id;

        return DB::transaction(function () use ($tankId, $farmId, $day, $meals, $quantity, $batchId) {
            $row = [
                'tank_id'       => $tankId,
                'farm_id'       => $farmId,
                'batch_id'      => $batchId,
                'feed_date'     => $day,
                'meals'         => $meals,
                'feed_quantity' => $quantity,
                // Feed someone actually recorded, so it comes off the store.
                // Generated back-history carries 1 here and does not.
                'is_backfill'   => 0,
            ];

            $history = TankFeedHistory::create($row);

            Feed::create($row);

            $this->recomputeTankTotal($tankId);

            return $history;
        });
    }

    /** Change an existing entry, moving its `feeds` twin with it. */
    public function update(TankFeedHistory $history, float $meals, float $quantity): TankFeedHistory
    {
        $oldMeals    = $history->meals;
        $oldQuantity = $history->feed_quantity;

        return DB::transaction(function () use ($history, $oldMeals, $oldQuantity, $meals, $quantity) {
            $history->update(['meals' => $meals, 'feed_quantity' => $quantity]);

            $feed = $this->twin($history, $oldMeals, $oldQuantity);

            if ($feed) {
                $feed->update(['meals' => $meals, 'feed_quantity' => $quantity]);
            }

            $this->recomputeTankTotal($history->tank_id);

            return $history->fresh();
        });
    }

    /** Remove an entry and its twin. */
    public function delete(TankFeedHistory $history): void
    {
        DB::transaction(function () use ($history) {
            $tankId = $history->tank_id;
            $feed   = $this->twin($history, $history->meals, $history->feed_quantity);

            $history->delete();
            $feed?->delete();

            $this->recomputeTankTotal($tankId);
        });
    }

    /**
     * The `feeds` row that pairs with a history row. Two identical entries on
     * one day are interchangeable — they read the same either way.
     */
    private function twin(TankFeedHistory $history, $meals, $quantity): ?Feed
    {
        return Feed::where('tank_id', $history->tank_id)
            ->whereDate('feed_date', $history->feed_date)
            ->where('meals', $meals)
            ->where('feed_quantity', $quantity)
            ->first();
    }

    /** Recompute rather than increment, so a bad total repairs itself. */
    public function recomputeTankTotal(int $tankId): void
    {
        Tank::where('id', $tankId)->update([
            'total_feed_used' => (float) Feed::where('tank_id', $tankId)->sum('feed_quantity'),
        ]);
    }
}
