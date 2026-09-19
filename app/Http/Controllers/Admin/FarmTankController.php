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
        $this->middleware('permission:farm-management.update')->only(['update', 'toggleStatus', 'updateFeed', 'restore']);
        $this->middleware('permission:farm-management.delete')->only(['destroy', 'destroyFeed', 'forceDestroy']);
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
            app(\App\Services\FarmActivityLogger::class)->tankAdded($farm, $tank);

            // After the log entry for the tank itself, so the history reads in
            // the order things happened: the tank exists, then a crop starts
            // in it.
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
            $data = $validator->validated();

            // `status` is pulled OUT and applied through TankBatchService.
            //
            // This used to mass-assign it with everything else, which moved the
            // column without touching the batch — the exact split
            // TankBatchService exists to prevent. Saving the edit form with
            // Active selected on a harvested tank set status=1 while its batch
            // stayed closed, and the two then disagreed for good: the app reads
            // `tanks.status` for the tank's switch, so it showed ACTIVE, while
            // every batch-aware surface still treated the crop as finished —
            // and feed recorded against it landed on the harvested crop.
            $wanted = (int) $data['status'];
            unset($data['status']);

            $tank->update($data);

            // What the save actually wrote, so reopening the form and pressing
            // Save without editing anything leaves no entry behind.
            $moved = collect($tank->getChanges())
                ->except(['updated_at'])
                ->mapWithKeys(fn ($value, $key) => [
                    ucfirst(str_replace('_', ' ', $key)) => (string) $value,
                ])
                ->all();

            app(\App\Services\FarmActivityLogger::class)->tankUpdated($tank, $moved);

            if ((int) $tank->status !== $wanted) {
                // Opens a new crop, or closes the running one, and moves the
                // column with it. Idempotent, so this only ever runs on a real
                // change.
                app(TankBatchService::class)->setStatus($tank, $wanted);
            }

            return redirect()->back()->with('success', 'Tank updated.');
        } catch (\Exception $e) {
            Log::error('Admin tank update failed', ['tank_id' => $tankId, 'error' => $e->getMessage()]);

            return redirect()->back()->withInput()->with('error', 'Could not update the tank: ' . $e->getMessage());
        }
    }

    /**
     * Hide a tank. Its feed records stay exactly where they are.
     *
     * This used to be permanent, and it destroyed every feed row on the way
     * out — so an admin removing the wrong tank wiped weeks of records with no
     * way back. Tanks are soft-deleted now, like farms, and the rows are what
     * make restoring one worth doing rather than handing back an empty pond.
     *
     * The running crop is CLOSED as it goes. The farm's Total Feed Used counts
     * open batches only, so leaving it open would have a hidden tank still
     * adding to the farm's figures. The store is deliberately NOT given back:
     * that feed genuinely left the shed, and deleting a record of a pond does
     * not put food back in the bag.
     */
    public function destroy($farmId, $tankId)
    {
        $tank = Tank::where('farm_id', $farmId)->findOrFail($tankId);

        try {
            // Logged first, while the tank still has a name and a farm to
            // record it against.
            app(\App\Services\FarmActivityLogger::class)->tankDeleted($tank);

            DB::transaction(function () use ($tank) {
                TankBatch::where('tank_id', $tank->id)
                    ->whereNull('ended_at')
                    ->update(['ended_at' => now()]);

                $tank->status = 0;
                $tank->save();

                $tank->delete(); // soft
            });

            return redirect()->back()->with(
                'success',
                'Tank deleted. Its feed records are kept — restore it from the Deleted tanks list.'
            );
        } catch (\Exception $e) {
            Log::error('Admin tank delete failed', ['tank_id' => $tankId, 'error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Could not delete the tank: ' . $e->getMessage());
        }
    }

    /**
     * Put a deleted tank back.
     *
     * It comes back INACTIVE with its crop still closed, whatever it was when
     * it went. Reopening the batch would restart a crop nobody asked to
     * restart and quietly change the farm's totals; an admin who wants it
     * running again starts a crop deliberately, and is asked for the stocking
     * date when they do.
     */
    public function restore($farmId, $tankId)
    {
        $tank = Tank::withTrashed()->where('farm_id', $farmId)->findOrFail($tankId);

        if (!$tank->trashed()) {
            return redirect()->back()->with('error', 'That tank is not deleted.');
        }

        try {
            $tank->restore();

            app(\App\Services\FarmActivityLogger::class)->record(
                (int) $farmId,
                \App\Models\FarmActivity::CATEGORY_TANK,
                \App\Models\FarmActivity::ACTION_UPDATED,
                "Restored {$tank->tank_name}. It is inactive until a crop is started.",
                $tank
            );

            return redirect()->back()->with(
                'success',
                'Tank restored. It is inactive — start a crop when it is stocked again.'
            );
        } catch (\Exception $e) {
            Log::error('Admin tank restore failed', ['tank_id' => $tankId, 'error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Could not restore the tank: ' . $e->getMessage());
        }
    }

    /**
     * Permanent removal, feed records and all.
     *
     * Only reachable for a tank that is ALREADY deleted, so it cannot be
     * mistaken for the ordinary delete button — this is the one there is no
     * way back from, and it exists for a tank created by mistake rather than
     * one that is finished with.
     */
    public function forceDestroy($farmId, $tankId)
    {
        $tank = Tank::withTrashed()->where('farm_id', $farmId)->findOrFail($tankId);

        if (!$tank->trashed()) {
            return redirect()->back()->with(
                'error',
                'Delete the tank first. Permanent removal is only for tanks already in the deleted list.'
            );
        }

        try {
            DB::transaction(function () use ($tank) {
                TankFeedHistory::where('tank_id', $tank->id)->delete();
                Feed::where('tank_id', $tank->id)->delete();
                TankBatch::where('tank_id', $tank->id)->delete();
                $tank->forceDelete();
            });

            return redirect()->back()->with('success', 'Tank permanently removed along with its feed records.');
        } catch (\Exception $e) {
            Log::error('Admin tank force delete failed', ['tank_id' => $tankId, 'error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Could not remove the tank: ' . $e->getMessage());
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
    public function toggleStatus(Request $request, $farmId, $tankId)
    {
        $tank = Tank::where('farm_id', $farmId)->findOrFail($tankId);

        $activating = !$tank->status;

        // Starting a crop asks the same two questions the app asks.
        //
        // Activating a tank begins a NEW crop, so it needs a date to count
        // days from — defaulting silently to today made a pond stocked a
        // fortnight ago read as Day 1 — and, when that date is in the past,
        // whatever it has already been fed. This screen asked neither, so a
        // tank started here was a crop with no history and the wrong age.
        if ($activating) {
            $validator = Validator::make($request->all(), [
                // before_or_equal:today — a crop cannot have been stocked on a
                // day that has not happened.
                'stocking_date'    => ['required', 'date', 'before_or_equal:today'],
                'feed_used_before' => ['nullable', 'numeric', 'min:0'],
            ], [
                'stocking_date.required' => 'Choose the date this crop was stocked.',
                'stocking_date.before_or_equal' => 'The stocking date cannot be in the future.',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }
        }

        try {
            app(TankBatchService::class)->setStatus(
                $tank,
                $activating ? 1 : 0,
                $activating ? $request->input('stocking_date') : null,
                $activating ? (float) $request->input('feed_used_before', 0) : 0,
            );

            return redirect()->back()->with(
                'success',
                $activating
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

        // An inactive tank has no crop to feed. The form is not rendered in
        // that state, so reaching here means a stale page or a direct POST —
        // answered with a sentence rather than the service's exception, which
        // would surface as "Could not add the feed entry: ...".
        if (!TankBatch::openFor((int) $tank->id)) {
            return redirect()->back()->withInput()->with(
                'error',
                $tank->tank_name . ' is inactive, so there is no crop to record feed against. '
                . 'Activate the tank to start a new crop first.'
            );
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
