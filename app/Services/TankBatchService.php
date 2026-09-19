<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\Tank;
use App\Models\TankBatch;
use Illuminate\Support\Facades\DB;

/**
 * Opening and closing a tank's crop cycle.
 *
 * A tank without an open batch is not a tank at rest — it is a tank whose feed
 * has nowhere to go. Feed rows carry `batch_id`, the farm's Total Feed Used
 * counts only the feed of OPEN batches, and the report is built for one batch,
 * so a row written against a tank with no batch is invisible everywhere the
 * farmer looks: the farm reads 0 kgs however much was entered.
 *
 * The app has always opened a batch when a tank is created and when a tank is
 * re-activated. The admin panel did neither — it created tanks directly and
 * flipped `tanks.status` on its own — so a farm set up by an admin, or a tank
 * an admin harvested, behaved differently from an identical one made in the
 * app. This is the shared rule both sides now use.
 */
class TankBatchService
{
    /**
     * Start a crop in a tank, unless one is already running.
     *
     * Numbered on from the batches the tank has already carried, so its history
     * reads 1, 2, 3 rather than restarting.
     *
     * @param  ?string  $stockingDate  yyyy-MM-dd; the tank's own date when omitted.
     * @param  float    $usedBefore    Feed given before this was recorded, if any.
     */
    public function open(Tank $tank, ?string $stockingDate = null, float $usedBefore = 0): TankBatch
    {
        $existing = TankBatch::openFor((int) $tank->id);

        if ($existing) {
            return $existing;
        }

        $date = $stockingDate ?: ($tank->stocking_date ?: null);

        $batch = DB::transaction(function () use ($tank, $date, $usedBefore) {
            $next = (int) TankBatch::where('tank_id', $tank->id)->max('batch_no') + 1;

            $batch = TankBatch::create([
                'tank_id'          => $tank->id,
                'farm_id'          => $tank->farm_id,
                'batch_no'         => $next,
                'stocking_date'    => $date,
                'feed_used_before' => $usedBefore > 0 ? $usedBefore : null,
                'started_at'       => now(),
                'ended_at'         => null,
            ]);

            // The tank's own date follows the crop currently in it, so the day
            // count and the meal schedule restart with the new batch. Without
            // this the tank kept the PREVIOUS crop's date and a pond stocked
            // yesterday reported itself as ninety days old.
            if ($date && (string) $tank->stocking_date !== (string) $date) {
                $tank->stocking_date = $date;
                $tank->save();
            }

            return $batch;
        });

        // Stocked before today with feed already given: build the history for
        // the days that have passed, exactly as a newly added tank does.
        //
        // This lives here rather than in the caller because BOTH the app and
        // the admin panel start crops. It was in the app's controller alone,
        // so a tank the farmer activated got its back-history and a tank an
        // admin activated got none — same button, same tank, two outcomes.
        //
        // Outside the transaction above: the backfill runs its own, and
        // nesting a second one around thousands of row inserts holds the
        // batch write open for the whole job.
        if ($usedBefore > 0 && $date) {
            $farm = $tank->farm_id ? Farm::find($tank->farm_id) : null;

            if ($farm) {
                app(FeedBackfillService::class)->applyForTank(
                    $farm,
                    (int) $tank->id,
                    $date,
                    $usedBefore,
                    (int) $batch->id
                );
            }
        }

        // Logged here rather than in each caller, for the same reason the work
        // itself is: the app and the admin panel both start crops, and a
        // history that only recorded one of them would be worse than none.
        app(FarmActivityLogger::class)->tankActivated($tank, $date, $usedBefore);

        return $batch;
    }

    /**
     * Finish the crop a tank is carrying. Harmless when none is running.
     *
     * The feed rows stay exactly where they are — closing a batch is what makes
     * them history rather than deleting them, which is how a finished crop drops
     * out of the farm's running total while its report stays downloadable.
     */
    public function close(Tank $tank, ?float $harvestQuantity = null): ?TankBatch
    {
        $open = TankBatch::openFor((int) $tank->id);

        if (!$open) {
            return null;
        }

        $open->ended_at = now();

        // What it weighed, when a figure was given. Left alone otherwise: null
        // means "not weighed", which is not the same as harvesting nothing,
        // and a blank field must not wipe a figure recorded on an earlier
        // attempt.
        if ($harvestQuantity !== null) {
            $open->harvest_quantity = $harvestQuantity;
        }

        $open->save();

        app(FarmActivityLogger::class)->tankHarvested($tank, $harvestQuantity);

        return $open;
    }

    /**
     * Put a tank into a status, moving its batch with it.
     *
     * Active means a crop is running; inactive means the last one finished.
     * Flipping the column alone left the two disagreeing — a tank the admin
     * marked harvested whose crop the app still counted as running.
     */
    public function setStatus(
        Tank $tank,
        int $status,
        ?string $stockingDate = null,
        float $usedBefore = 0,
        ?float $harvestQuantity = null
    ): void {
        // NOT wrapped in a transaction here.
        //
        // open() may generate thousands of back-history rows in its own
        // transaction, and nesting that inside one held around the whole
        // status change kept the outer write open for the entire job. Each
        // step below already commits atomically; the flag is written last, so
        // a failure part-way leaves the tank in its previous state rather than
        // marked active with no crop behind it.
        if ($status === 1) {
            $this->open($tank, $stockingDate, $usedBefore);
        } else {
            $this->close($tank, $harvestQuantity);
        }

        $tank->status = $status;
        $tank->save();
    }
}
