<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\Farmer;
use App\Models\Tank;
use App\Models\TankBatch;
use App\Models\TankFeedHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds a tank's day-by-day feed report for one crop cycle.
 *
 * Shared so the farmer's PDF and the admin panel's are the same document. They
 * were separate before — the app had a report and the admin panel had a CSV —
 * which meant a farmer ringing up about a figure was reading something nobody
 * on the other end could see.
 */
class TankFeedReportService
{
    /** A bad date must not produce a thousand-page document. */
    public const MAX_DAYS = 400;

    /**
     * Everything `reports.tank-feed` needs, or null when there is no date to
     * count from.
     *
     * @return array<string, mixed>|null
     */
    public function build(Tank $tank, ?TankBatch $batch = null): ?array
    {
        $farm  = Farm::withTrashed()->find($tank->farm_id);
        $batch ??= TankBatch::currentFor((int) $tank->id);

        $startDate = optional($batch?->stocking_date)->toDateString()
            ?: ($tank->stocking_date ?: optional($farm)->stocking_date);

        if (!$startDate) {
            return null;
        }

        $start = Carbon::parse($startDate)->startOfDay();

        // A finished crop stops at its harvest date; a running one runs to
        // today. Either way the report never invents days beyond the crop.
        $isFinished = $batch && $batch->ended_at !== null;

        $end = $isFinished
            ? Carbon::parse($batch->ended_at)->startOfDay()
            : Carbon::now()->startOfDay();

        // Never open the window after the feed it is meant to report on.
        //
        // The range comes from the batch, but the ROWS are the point: a batch
        // whose stocking date sits later than its own earliest entry would
        // silently drop every record before it and print a one-day report over
        // a month of feeding.
        $recorded = TankFeedHistory::where('tank_id', $tank->id)
            ->when($batch, fn ($q) => $q->where('batch_id', $batch->id))
            ->selectRaw('MIN(feed_date) AS first_fed, MAX(feed_date) AS last_fed')
            ->first();

        if ($recorded?->first_fed) {
            $firstFed = Carbon::parse($recorded->first_fed)->startOfDay();
            if ($firstFed->lessThan($start)) {
                $start = $firstFed;
            }
        }

        if ($recorded?->last_fed) {
            $lastFed = Carbon::parse($recorded->last_fed)->startOfDay();
            if ($lastFed->greaterThan($end)) {
                $end = $lastFed;
            }
        }

        if ($end->lessThan($start)) {
            $end = $start->copy();
        }

        if ($start->diffInDays($end) > self::MAX_DAYS) {
            $start = $end->copy()->subDays(self::MAX_DAYS);
        }

        // One query, grouped by day, rather than one per day.
        $byDate = TankFeedHistory::where('tank_id', $tank->id)
            ->when($batch, fn ($q) => $q->where('batch_id', $batch->id))
            ->whereBetween('feed_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy(fn ($row) => Carbon::parse($row->feed_date)->toDateString());

        $rows          = [];
        $totalMeals    = 0;
        $totalQuantity = 0.0;
        $fedDays       = 0;
        $day           = 1;

        for ($date = $start->copy(); $date->lessThanOrEqualTo($end); $date->addDay()) {
            $entries  = $byDate->get($date->toDateString(), collect());
            $meals    = $this->mealsInDay($entries);
            $quantity = (float) $entries->sum('feed_quantity');

            $rows[] = [
                'day'       => $day++,
                'date'      => $date->copy(),
                'meals'     => $meals,
                'quantity'  => $quantity,
                'entries'   => $entries->count(),
                'breakdown' => $this->mealBreakdown($entries),
            ];

            $totalMeals    += $meals;
            $totalQuantity += $quantity;

            if ($entries->isNotEmpty()) {
                $fedDays++;
            }
        }

        $farmer = $farm ? Farmer::find($farm->farmer_id) : null;

        return [
            'tank'          => $tank,
            'farm'          => $farm,
            'farmerName'    => $farmer ? trim($farmer->first_name . ' ' . $farmer->last_name) : null,
            'start'         => $start,
            'end'           => $end,
            'isFinished'    => $isFinished,
            'rows'          => $rows,
            'totalMeals'    => $totalMeals,
            'totalQuantity' => $totalQuantity,
            'fedDays'       => $fedDays,
            'generatedAt'   => now(),
        ];
    }

    /**
     * How many meals a day's feed rows represent.
     *
     * `meals` on a row is the meal's NUMBER within its day — 1, 2, 3 — not a
     * count, because every row IS one meal. Summing the column added up the
     * numbering instead: two meals reported as 1+2 = 3, three as 6, four as 10.
     *
     * The one exception is history generated before the per-meal split, which
     * wrote a single row per DAY whose `meals` genuinely is a count. Such a row
     * is generated (is_backfill) and alone on its day, which is what separates
     * it from someone who recorded one meal and numbered it 3.
     */
    public function mealsInDay(Collection $entries): int
    {
        if ($entries->isEmpty()) {
            return 0;
        }

        if ($entries->count() === 1) {
            $only = $entries->first();
            $declared = (int) $only->meals;

            if ((int) ($only->is_backfill ?? 0) === 1 && $declared > 1) {
                return $declared;
            }
        }

        return $entries->count();
    }

    /**
     * One day's meals, in order, each with what it weighed.
     *
     * Empty for the old single-row-per-day shape, where the meals were never
     * itemised and there is nothing honest to break down.
     *
     * @return array<int, array{meal: int, quantity: float}>
     */
    public function mealBreakdown(Collection $entries): array
    {
        if ($entries->isEmpty()) {
            return [];
        }

        if ($entries->count() === 1) {
            $only = $entries->first();

            if ((int) ($only->is_backfill ?? 0) === 1 && (int) $only->meals > 1) {
                return [];
            }
        }

        return $entries
            ->sortBy(fn ($row) => (int) $row->meals)
            ->map(fn ($row) => [
                'meal'     => (int) $row->meals,
                'quantity' => (float) $row->feed_quantity,
            ])
            ->values()
            ->all();
    }
}
