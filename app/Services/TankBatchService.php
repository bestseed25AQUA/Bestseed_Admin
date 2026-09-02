<?php

namespace App\Services;

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

        return DB::transaction(function () use ($tank, $date, $usedBefore) {
            $next = (int) TankBatch::where('tank_id', $tank->id)->max('batch_no') + 1;

            return TankBatch::create([
                'tank_id'          => $tank->id,
                'farm_id'          => $tank->farm_id,
                'batch_no'         => $next,
                'stocking_date'    => $date,
                'feed_used_before' => $usedBefore > 0 ? $usedBefore : null,
                'started_at'       => now(),
                'ended_at'         => null,
            ]);
        });
    }

    /**
     * Finish the crop a tank is carrying. Harmless when none is running.
     *
     * The feed rows stay exactly where they are — closing a batch is what makes
     * them history rather than deleting them, which is how a finished crop drops
     * out of the farm's running total while its report stays downloadable.
     */
    public function close(Tank $tank): ?TankBatch
    {
        $open = TankBatch::openFor((int) $tank->id);

        if (!$open) {
            return null;
        }

        $open->ended_at = now();
        $open->save();

        return $open;
    }

    /**
     * Put a tank into a status, moving its batch with it.
     *
     * Active means a crop is running; inactive means the last one finished.
     * Flipping the column alone left the two disagreeing — a tank the admin
     * marked harvested whose crop the app still counted as running.
     */
    public function setStatus(Tank $tank, int $status, ?string $stockingDate = null, float $usedBefore = 0): void
    {
        DB::transaction(function () use ($tank, $status, $stockingDate, $usedBefore) {
            if ($status === 1) {
                $this->open($tank, $stockingDate, $usedBefore);
            } else {
                $this->close($tank);
            }

            $tank->status = $status;
            $tank->save();
        });
    }
}
