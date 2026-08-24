<?php

namespace App\Services;

use App\Models\Feed;
use App\Models\Tank;
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
    /** Add one feed entry to a tank on a given day. Returns the history row. */
    public function record(int $tankId, int $farmId, string $date, float $meals, float $quantity): TankFeedHistory
    {
        $day = Carbon::parse($date)->toDateString();

        return DB::transaction(function () use ($tankId, $farmId, $day, $meals, $quantity) {
            $history = TankFeedHistory::create([
                'tank_id'       => $tankId,
                'farm_id'       => $farmId,
                'feed_date'     => $day,
                'meals'         => $meals,
                'feed_quantity' => $quantity,
            ]);

            Feed::create([
                'tank_id'       => $tankId,
                'farm_id'       => $farmId,
                'feed_date'     => $day,
                'meals'         => $meals,
                'feed_quantity' => $quantity,
            ]);

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
