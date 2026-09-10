<?php

namespace App\Console\Commands;

use App\Models\Tank;
use App\Models\TankBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Finds tanks whose `status` column disagrees with their crop cycle, and makes
 * the column follow the cycle.
 *
 * The two drifted apart wherever something wrote `tanks.status` without moving
 * the batch with it — the admin tank EDIT form did exactly that until it was
 * routed through TankBatchService. A tank left at status=1 with no open batch
 * read as Active in the app while every batch-aware surface treated its crop as
 * finished, and feed recorded against it attached to the harvested crop.
 *
 * The COLUMN is corrected, never the batch. A batch carries real dated history
 * — started_at, ended_at, its feed rows, its harvest weight — so rewriting one
 * to match a flag would invent or destroy a crop. The flag carries nothing, so
 * moving it is free and reversible.
 */
class ReconcileTankStatus extends Command
{
    protected $signature = 'tanks:reconcile-status
                            {--fix : Write the corrections. Without this the command only reports.}
                            {--farm= : Limit to one farm id.}';

    protected $description = "Report (or fix) tanks whose status column disagrees with their crop cycle";

    public function handle(): int
    {
        $farmId = $this->option('farm');

        // One query rather than one per tank: these tables are large enough on
        // a live database that a per-tank lookup would be thousands of round
        // trips to find a handful of rows.
        $rows = DB::table('tanks')
            ->leftJoin('tank_batches', function ($join) {
                $join->on('tank_batches.tank_id', '=', 'tanks.id')
                    ->whereNull('tank_batches.ended_at');
            })
            ->when($farmId, fn ($q) => $q->where('tanks.farm_id', $farmId))
            ->select(
                'tanks.id',
                'tanks.farm_id',
                'tanks.tank_name',
                'tanks.status',
                DB::raw('MAX(tank_batches.id) AS open_batch_id')
            )
            ->groupBy('tanks.id', 'tanks.farm_id', 'tanks.tank_name', 'tanks.status')
            ->havingRaw('(tanks.status = 1 AND MAX(tank_batches.id) IS NULL)
                      OR (tanks.status = 0 AND MAX(tank_batches.id) IS NOT NULL)')
            ->orderBy('tanks.id')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Every tank agrees with its crop cycle. Nothing to do.');

            return self::SUCCESS;
        }

        $this->warn($rows->count() . ' tank(s) disagree with their crop cycle:');

        $this->table(
            ['Tank', 'Farm', 'Name', 'status now', 'open batch', 'status should be'],
            $rows->map(fn ($r) => [
                $r->id,
                $r->farm_id,
                $r->tank_name,
                $r->status,
                $r->open_batch_id ?? '—',
                $r->open_batch_id ? 1 : 0,
            ])->all()
        );

        if (!$this->option('fix')) {
            $this->newLine();
            $this->line('This was a report only. Re-run with --fix to apply the corrections above.');

            return self::SUCCESS;
        }

        $fixed = 0;

        foreach ($rows as $row) {
            $should = $row->open_batch_id ? 1 : 0;

            // Written straight to the column on purpose. TankBatchService would
            // be the right call for a status CHANGE, but here the batch is
            // already in the state we want and asking the service to set it
            // again would close a running crop or open a crop that never ran.
            Tank::where('id', $row->id)->update(['status' => $should]);

            $this->line("  tank {$row->id} ({$row->tank_name}): status {$row->status} → {$should}");
            $fixed++;
        }

        $this->newLine();
        $this->info("Corrected {$fixed} tank(s).");

        // Said plainly, because the figure an admin sees next depends on it.
        $this->line('Tanks now reading inactive had already finished their crop — their feed '
            . 'stays on that crop and their report is unchanged.');

        return self::SUCCESS;
    }
}
