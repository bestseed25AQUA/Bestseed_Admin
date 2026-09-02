<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\Feed;
use App\Models\Tank;
use App\Models\TankBatch;
use App\Models\TankFeedHistory;
use App\Services\TankBatchService;
use App\Services\TankFeedReportService;
use App\Services\TankFeedService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Admin management of a farm's tanks and their feed records.
 *
 * The app lets a farmer add tanks and log feed; without this an admin could
 * only watch. Feed writes go through TankFeedService so admin edits land in
 * both feed tables exactly as the app's do.
 */
class FarmTankController extends Controller
{
    public function __construct(private TankFeedService $feed)
    {
        $this->middleware('permission:farm-management.view')->only(['feedHistory', 'feedReport']);
        $this->middleware('permission:farm-management.create')->only(['store', 'storeFeed']);
        $this->middleware('permission:farm-management.update')->only(['update', 'toggleStatus', 'updateFeed']);
        $this->middleware('permission:farm-management.delete')->only(['destroy', 'destroyFeed']);
    }

    public function store(Request $request, $farmId)
    {
        $farm = Farm::withTrashed()->findOrFail($farmId);

        $validator = Validator::make($request->all(), $this->tankRules());

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $tank = Tank::create(array_merge($validator->validated(), [
                'farm_id'       => $farm->id,
                // A new tank starts from the farm's stocking date unless the
                // admin gave it one of its own.
                'stocking_date' => $request->input('stocking_date') ?: $farm->stocking_date,
            ]));

            // Open its first crop cycle, as the app does. A tank with no batch
            // swallows every feed row written against it: they land with a NULL
            // batch_id, which the farm total and the report both skip.
            if ((int) $tank->status === 1) {
                app(TankBatchService::class)->open($tank, $tank->stocking_date);
            }

            Log::info('Admin created tank', ['tank_id' => $tank->id, 'farm_id' => $farm->id]);

            return redirect()->back()->with('success', 'Tank added.');
        } catch (\Exception $e) {
            Log::error('Admin tank create failed', ['farm_id' => $farmId, 'error' => $e->getMessage()]);

            return redirect()->back()->withInput()->with('error', 'Could not add the tank: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $farmId, $tankId)
    {
        $tank = Tank::where('farm_id', $farmId)->findOrFail($tankId);

        $validator = Validator::make($request->all(), $this->tankRules());

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $tank->update($validator->validated());

            return redirect()->back()->with('success', 'Tank updated.');
        } catch (\Exception $e) {
            Log::error('Admin tank update failed', ['tank_id' => $tankId, 'error' => $e->getMessage()]);

            return redirect()->back()->withInput()->with('error', 'Could not update the tank: ' . $e->getMessage());
        }
    }

    /**
     * Delete a tank and the feed logged against it.
     *
     * Tanks are not soft-deleted, so leaving the feed rows behind would leave
     * them pointing at a tank that no longer exists — and still counted in the
     * farm's total feed used.
     */
    public function destroy($farmId, $tankId)
    {
        $tank = Tank::where('farm_id', $farmId)->findOrFail($tankId);

        try {
            DB::transaction(function () use ($tank) {
                TankFeedHistory::where('tank_id', $tank->id)->delete();
                \App\Models\Feed::where('tank_id', $tank->id)->delete();
                $tank->delete();
            });

            return redirect()->back()->with('success', 'Tank deleted along with its feed records.');
        } catch (\Exception $e) {
            Log::error('Admin tank delete failed', ['tank_id' => $tankId, 'error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Could not delete the tank: ' . $e->getMessage());
        }
    }

    /**
     * Harvest a tank, or start it on a fresh crop.
     *
     * Goes through [TankBatchService] rather than flipping `tanks.status`,
     * which is all this used to do. The column and the crop cycle then said
     * different things: a tank the admin marked harvested kept an OPEN batch,
     * so the farmer's app went on counting its feed in the farm total and
     * treating the crop as running — and marking it active again started no new
     * cycle, so the next crop's feed piled onto the finished one instead of
     * starting from zero.
     */
    public function toggleStatus($farmId, $tankId)
    {
        $tank = Tank::where('farm_id', $farmId)->findOrFail($tankId);

        try {
            app(TankBatchService::class)->setStatus($tank, $tank->status ? 0 : 1);

            return redirect()->back()->with(
                'success',
                $tank->status
                    ? 'Tank is now active on a new crop.'
                    : 'Tank is now inactive. Its crop is finished and its report stays available.'
            );
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Could not change the tank status: ' . $e->getMessage());
        }
    }

    /**
     * One tank's feed, batch by batch.
     *
     * The farmer's app only ever shows the CURRENT crop cycle — a finished
     * batch drops out of the farm totals, and an earlier one cannot be reached
     * from the app at all. Admin is where the whole history lives, so every
     * batch the tank has carried is listed here with its own records.
     *
     * `?batch=` narrows to one cycle; without it the newest is shown.
     */
    public function feedHistory(Request $request, $farmId, $tankId)
    {
        $tank = Tank::where('farm_id', $farmId)->findOrFail($tankId);

        $batches = TankBatch::where('tank_id', $tank->id)
            ->orderByDesc('batch_no')
            ->get()
            ->map(function (TankBatch $batch) {
                $batch->feed_total = (float) Feed::where('batch_id', $batch->id)
                    ->sum('feed_quantity');

                $batch->fed_days = TankFeedHistory::where('batch_id', $batch->id)
                    ->distinct()
                    ->count(DB::raw('DATE(feed_date)'));

                return $batch;
            });

        $selected = $request->filled('batch')
            ? $batches->firstWhere('id', (int) $request->input('batch'))
            : $batches->first();

        $entries = TankFeedHistory::where('tank_id', $tank->id)
            // A tank whose rows predate batches has none to filter by; showing
            // everything beats showing nothing.
            ->when($selected, fn ($q) => $q->where('batch_id', $selected->id))
            ->orderByDesc('feed_date')
            ->orderByDesc('id')
            ->get();

        return view('admin.farm-management.tanks.feed', [
            'farm'     => Farm::withTrashed()->findOrFail($farmId),
            'tank'     => $tank,
            'entries'  => $entries,
            'batches'  => $batches,
            'selected' => $selected,
        ]);
    }

    public function storeFeed(Request $request, $farmId, $tankId)
    {
        $tank = Tank::where('farm_id', $farmId)->findOrFail($tankId);

        $validator = Validator::make($request->all(), $this->feedRules());

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $this->feed->record(
                $tank->id,
                (int) $farmId,
                $request->input('feed_date'),
                (float) $request->input('meals'),
                (float) $request->input('feed_quantity'),
            );

            return redirect()->back()->with('success', 'Feed entry added.');
        } catch (\Exception $e) {
            Log::error('Admin feed create failed', ['tank_id' => $tankId, 'error' => $e->getMessage()]);

            return redirect()->back()->withInput()->with('error', 'Could not add the feed entry: ' . $e->getMessage());
        }
    }

    public function updateFeed(Request $request, $farmId, $tankId, $historyId)
    {
        $history = TankFeedHistory::where('tank_id', $tankId)->findOrFail($historyId);

        $validator = Validator::make($request->all(), [
            'meals'         => 'required|numeric|min:0',
            'feed_quantity' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $this->feed->update(
                $history,
                (float) $request->input('meals'),
                (float) $request->input('feed_quantity'),
            );

            return redirect()->back()->with('success', 'Feed entry updated.');
        } catch (\Exception $e) {
            Log::error('Admin feed update failed', ['history_id' => $historyId, 'error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Could not update the feed entry: ' . $e->getMessage());
        }
    }

    public function destroyFeed($farmId, $tankId, $historyId)
    {
        $history = TankFeedHistory::where('tank_id', $tankId)->findOrFail($historyId);

        try {
            $this->feed->delete($history);

            return redirect()->back()->with('success', 'Feed entry deleted.');
        } catch (\Exception $e) {
            Log::error('Admin feed delete failed', ['history_id' => $historyId, 'error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Could not delete the feed entry: ' . $e->getMessage());
        }
    }

    /**
     * The same feed report the app offers, streamed straight to the browser.
     *
     * The app's endpoint writes a file into public/reports and returns a link,
     * because a phone needs a URL to hand to its downloader. A browser does
     * not, so this streams the rows instead — no files accumulating on disk.
     */
    public function feedReport(Request $request, $farmId, $tankId)
    {
        $tank = Tank::where('farm_id', $farmId)->findOrFail($tankId);
        $farm = Farm::withTrashed()->findOrFail($farmId);

        // `?format=pdf` renders the SAME document the farmer downloads in the
        // app, for whichever batch is being looked at. Admin could only export
        // a CSV before, so a farmer ringing up about a figure in their report
        // was reading something nobody on this side could see.
        if ($request->query('format') === 'pdf') {
            $batch = $request->filled('batch')
                ? TankBatch::where('tank_id', $tank->id)->find($request->query('batch'))
                : null;

            $data = app(TankFeedReportService::class)->build($tank, $batch);

            if ($data === null) {
                return redirect()->back()->with(
                    'error',
                    'This tank has no stocking date, so there is nothing to report yet.'
                );
            }

            return Pdf::loadView('reports.tank-feed', $data)
                ->setPaper('a4')
                ->download(sprintf(
                    '%s_%s_feed_%s.pdf',
                    \Illuminate\Support\Str::slug($farm->farm_name ?: 'farm'),
                    \Illuminate\Support\Str::slug($tank->tank_name ?: 'tank'),
                    now()->format('Y_m_d')
                ));
        }

        $filename = sprintf(
            '%s_%s_feed_%s.csv',
            \Illuminate\Support\Str::slug($farm->farm_name ?: 'farm'),
            \Illuminate\Support\Str::slug($tank->tank_name ?: 'tank'),
            now()->format('Y_m_d')
        );

        $entries = TankFeedHistory::where('tank_id', $tank->id)
            ->orderBy('feed_date')
            ->orderBy('id')
            ->get();

        return response()->streamDownload(function () use ($entries, $farm, $tank) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Farm', $farm->farm_name]);
            fputcsv($out, ['Tank', $tank->tank_name]);
            fputcsv($out, ['Stocking Date', $tank->stocking_date ?: $farm->stocking_date]);
            fputcsv($out, ['Total Feed Used (kg)', $tank->total_feed_used]);
            fputcsv($out, []);
            fputcsv($out, ['ID', 'Date', 'Meals', 'Feed Quantity (kg)', 'Source']);

            foreach ($entries as $entry) {
                fputcsv($out, [
                    $entry->id,
                    \Illuminate\Support\Carbon::parse($entry->feed_date)->format('Y-m-d'),
                    $entry->meals,
                    $entry->feed_quantity,
                    $entry->is_backfill ? 'Backfilled' : 'Logged',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function tankRules(): array
    {
        return [
            'tank_name'     => 'required|string|max:255',
            'status'        => 'required|in:0,1',
            'meals'         => 'nullable|numeric|min:0',
            'store'         => 'nullable|numeric|min:0',
            'stocking_date' => 'nullable|date',
        ];
    }

    private function feedRules(): array
    {
        return [
            'feed_date'     => 'required|date',
            // The meal's NUMBER within its day, not how many there were: one
            // row is one meal, the same shape the app writes. Accepting a count
            // here put a single row saying "4 meals" beside four rows numbered
            // 1-4 in the same tank, and the report — which counts rows — read
            // that day as one meal.
            'meals'         => 'required|integer|min:1|max:20',
            'feed_quantity' => 'required|numeric|min:0',
        ];
    }
}
