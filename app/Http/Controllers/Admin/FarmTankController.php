<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\Tank;
use App\Models\TankFeedHistory;
use App\Services\TankFeedService;
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
        $this->middleware('permission:farm-management.view')->only(['feedHistory']);
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

    public function toggleStatus($farmId, $tankId)
    {
        $tank = Tank::where('farm_id', $farmId)->findOrFail($tankId);

        try {
            $tank->update(['status' => $tank->status ? 0 : 1]);

            return redirect()->back()->with('success', $tank->status ? 'Tank is now active.' : 'Tank is now inactive.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Could not change the tank status: ' . $e->getMessage());
        }
    }

    /** Every feed entry for one tank, newest day first. */
    public function feedHistory($farmId, $tankId)
    {
        $tank = Tank::where('farm_id', $farmId)->findOrFail($tankId);

        $entries = TankFeedHistory::where('tank_id', $tank->id)
            ->orderByDesc('feed_date')
            ->orderByDesc('id')
            ->get();

        return view('admin.farm-management.tanks.feed', [
            'farm'    => Farm::withTrashed()->findOrFail($farmId),
            'tank'    => $tank,
            'entries' => $entries,
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
            'meals'         => 'required|numeric|min:0',
            'feed_quantity' => 'required|numeric|min:0',
        ];
    }
}
