<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\FarmImage;
use App\Models\Farmer;
use App\Models\Feed;
use App\Models\Manager;
use App\Models\Tank;
use App\Services\FeedBackfillService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Admin view over farm management.
 *
 * The farmer app scopes every farm query to the logged-in farmer; admin
 * deliberately does not — an admin sees and edits every farm, its tanks, its
 * team and its access codes.
 */
class FarmManagementController extends Controller
{
    public function __construct(private FeedBackfillService $backfill)
    {
        $this->middleware('permission:farm-management.view')->only(['index', 'show']);
        $this->middleware('permission:farm-management.create')->only(['create', 'store']);
        $this->middleware('permission:farm-management.update')->only(['edit', 'update', 'toggleStatus', 'restore']);
        $this->middleware('permission:farm-management.delete')->only(['destroy', 'forceDestroy']);
    }

    public function index(Request $request)
    {
        // 'trashed' rows are included so a deleted farm can be found and
        // restored; the default view hides them.
        $farms = Farm::withTrashed()
            ->with(['farmer', 'images'])
            ->withCount([
                'tanks',
                'tanks as active_tanks_count' => fn ($q) => $q->where('status', 1),
                'accessGrants',
                'accessGrants as live_grants_count' => fn ($q) => $q->live(),
            ])
            ->when($request->filled('farmer_id'), fn ($q) => $q->where('farmer_id', $request->input('farmer_id')))
            ->when($request->input('status') === 'active', fn ($q) => $q->whereNull('deleted_at')->where('status', 1))
            ->when($request->input('status') === 'inactive', fn ($q) => $q->whereNull('deleted_at')->where('status', 0))
            ->when($request->input('status') === 'deleted', fn ($q) => $q->whereNotNull('deleted_at'))
            ->when(!$request->filled('status'), fn ($q) => $q->whereNull('deleted_at'))
            ->orderByDesc('id')
            ->get();

        // Team counts in one query rather than one per farm.
        $teamCounts = Manager::selectRaw('farm_id, is_partner, COUNT(*) as total')
            ->whereIn('farm_id', $farms->pluck('id'))
            ->groupBy('farm_id', 'is_partner')
            ->get();

        foreach ($farms as $farm) {
            $farm->managers_count = (int) optional($teamCounts->firstWhere(
                fn ($row) => $row->farm_id == $farm->id && !$row->is_partner
            ))->total;

            $farm->partners_count = (int) optional($teamCounts->firstWhere(
                fn ($row) => $row->farm_id == $farm->id && $row->is_partner
            ))->total;
        }

        $farmers = Farmer::orderBy('first_name')->get();

        return view('admin.farm-management.farms.index', compact('farms', 'farmers'));
    }

    public function create()
    {
        return view('admin.farm-management.farms.create', [
            'farmers' => Farmer::orderBy('first_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules(), $this->messages());

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            // `images` is a file upload, not a farms column.
            $farm = Farm::create(collect($validator->validated())->except(['images', 'feed_used_before'])->all());

            $this->saveImages($request, $farm);

            // The app creates a farm's tanks with it; admin must too, or the
            // farmer opens a farm with nowhere to record feed.
            $tankIds = $this->createTanks($farm, (int) $request->input('no_of_tanks'));

            // A farm stocked weeks ago has history nothing recorded. One
            // figure spreads across every tank and every day since stocking,
            // exactly as it does in the app.
            $this->backfill->apply(
                $farm,
                $tankIds,
                (float) $request->input('feed_used_before', 0)
            );

            Log::info('Admin created farm', ['farm_id' => $farm->id]);

            return redirect()->route('farm-management.farms.show', $farm->id)
                ->with('success', 'Farm created successfully.');
        } catch (\Exception $e) {
            Log::error('Admin farm create failed', ['error' => $e->getMessage()]);

            return redirect()->back()->withInput()
                ->with('error', 'Could not create the farm: ' . $e->getMessage());
        }
    }

    /**
     * Farm detail — the one screen that shows the whole picture: owner, tanks,
     * team, and every access code issued for the farm.
     */
    public function show($id)
    {
        $farm = Farm::withTrashed()->with(['farmer', 'images'])->findOrFail($id);

        $tanks = Tank::where('farm_id', $farm->id)->orderByDesc('id')->get();

        $team = Manager::where('farm_id', $farm->id)->orderByDesc('id')->get();

        $grants = $farm->accessGrants()
            ->with('manager')
            ->orderByDesc('id')
            ->get();

        $totalFeedUsed = Feed::where('farm_id', $farm->id)->sum('feed_quantity');

        // Who actually holds access — QR scanners and directly-assigned
        // people alike. Distinct from $grants, which are only invitations.
        $members = $farm->accessMembers()
            ->with(['farmer', 'grantedBy'])
            ->orderByDesc('id')
            ->get();

        // For the "give access directly" picker.
        $farmers = Farmer::where('id', '!=', $farm->farmer_id)
            ->orderBy('first_name')
            ->get();

        return view('admin.farm-management.farms.show', compact(
            'farm', 'tanks', 'team', 'grants', 'totalFeedUsed', 'members', 'farmers'
        ));
    }

    public function edit($id)
    {
        return view('admin.farm-management.farms.edit', [
            'farm'    => Farm::findOrFail($id),
            'farmers' => Farmer::orderBy('first_name')->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $farm = Farm::findOrFail($id);

        $validator = Validator::make($request->all(), $this->rules(), $this->messages());

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $farm->update(collect($validator->validated())->except(['images', 'feed_used_before'])->all());

            $this->saveImages($request, $farm);

            // Only regenerate when the figure actually changed — rebuilding
            // wipes and rewrites every generated row, and doing that on an
            // unrelated edit would churn the farm's history for nothing.
            $entered = $request->filled('feed_used_before')
                ? (float) $request->input('feed_used_before')
                : null;

            if ($entered !== null && (float) ($farm->feed_used_before ?? 0) !== $entered) {
                $this->backfill->clear($farm);

                $this->backfill->apply(
                    $farm->fresh(),
                    Tank::where('farm_id', $farm->id)->pluck('id')->all(),
                    $entered
                );
            }

            return redirect()->route('farm-management.farms.show', $farm->id)
                ->with('success', 'Farm updated successfully.');
        } catch (\Exception $e) {
            Log::error('Admin farm update failed', ['farm_id' => $id, 'error' => $e->getMessage()]);

            return redirect()->back()->withInput()
                ->with('error', 'Could not update the farm: ' . $e->getMessage());
        }
    }

    /**
     * Soft-delete the farm, and revoke — not delete — the access codes.
     *
     * The farm row survives, so its team and its codes must survive too;
     * otherwise a restore brings back a farm whose whole access history has
     * been silently destroyed. Codes are revoked rather than left live so a
     * QR already in someone's hands cannot quietly work again after a restore.
     * Use forceDestroy() when the farm really is meant to disappear.
     */
    public function destroy($id)
    {
        $farm = Farm::withTrashed()->findOrFail($id);

        try {
            DB::transaction(function () use ($farm) {
                $farm->accessGrants()->whereNull('revoked_at')->update(['revoked_at' => now()]);

                Manager::where('farm_id', $farm->id)->update([
                    'view_access'   => 0,
                    'edit_access'   => 0,
                    'create_access' => 0,
                    'delete_access' => 0,
                ]);

                $farm->delete();
            });

            return redirect()->route('farm-management.farms.index')
                ->with('success', 'Farm deleted. Its access codes were revoked and its team kept, so it can be restored.');
        } catch (\Exception $e) {
            Log::error('Admin farm delete failed', ['farm_id' => $id, 'error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Could not delete the farm: ' . $e->getMessage());
        }
    }

    /** Bring back a soft-deleted farm. Codes stay revoked — reissue as needed. */
    public function restore($id)
    {
        $farm = Farm::withTrashed()->findOrFail($id);

        try {
            $farm->restore();

            return redirect()->back()
                ->with('success', 'Farm restored. Its previous access codes stay revoked — issue new ones if needed.');
        } catch (\Exception $e) {
            Log::error('Admin farm restore failed', ['farm_id' => $id, 'error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Could not restore the farm: ' . $e->getMessage());
        }
    }

    /**
     * Permanent removal. Only here does the team and the code history go too —
     * there is no farm left for them to belong to.
     */
    public function forceDestroy($id)
    {
        $farm = Farm::withTrashed()->findOrFail($id);

        try {
            DB::transaction(function () use ($farm) {
                $farm->accessGrants()->delete();
                Manager::where('farm_id', $farm->id)->delete();
                $farm->forceDelete();
            });

            return redirect()->route('farm-management.farms.index')
                ->with('success', 'Farm permanently deleted along with its team and access codes.');
        } catch (\Exception $e) {
            Log::error('Admin farm force delete failed', ['farm_id' => $id, 'error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Could not permanently delete the farm: ' . $e->getMessage());
        }
    }

    /**
     * Flip active/inactive. An inactive farm stays intact but disappears from
     * the app for its owner and for every manager and partner, because
     * Farm::scopeAccessibleBy() only returns active farms.
     */
    public function toggleStatus($id)
    {
        $farm = Farm::withTrashed()->findOrFail($id);

        try {
            $farm->update(['status' => $farm->status ? 0 : 1]);

            return redirect()->back()->with(
                'success',
                $farm->status
                    ? 'Farm is now active and visible in the app.'
                    : 'Farm is now inactive and hidden from the app.'
            );
        } catch (\Exception $e) {
            Log::error('Admin farm status toggle failed', ['farm_id' => $id, 'error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Could not change the status: ' . $e->getMessage());
        }
    }

    /**
     * Store uploaded farm photos the same way the app does: files under
     * public/uploads/images/farms, and a JSON array of absolute URLs in
     * farm_images.images. Absolute so existing app clients keep working.
     *
     * Uploading replaces the whole set — the app only ever shows two photos,
     * so appending would quietly hide what was just added.
     */
    private function saveImages(Request $request, Farm $farm): void
    {
        if (!$request->hasFile('images')) {
            return;
        }

        $baseUrl = rtrim(config('app.url'), '/');
        $paths   = [];

        foreach ($request->file('images') as $file) {
            $name = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/images/farms'), $name);
            $paths[] = $baseUrl . '/uploads/images/farms/' . $name;
        }

        if (empty($paths)) {
            return;
        }

        FarmImage::updateOrCreate(
            ['farm_id' => $farm->id],
            ['images' => json_encode($paths)]
        );
    }

    /**
     * Create a farm's tanks, named the way the app names them.
     *
     * Returns the new tank ids so a feed backfill can be spread across them.
     */
    private function createTanks(Farm $farm, int $count): array
    {
        if ($count < 1) {
            return [];
        }

        $ids = [];

        for ($i = 1; $i <= $count; $i++) {
            $ids[] = Tank::create([
                'farm_id'       => $farm->id,
                'tank_name'     => 'Tank' . $i,
                'status'        => 1,
                'stocking_date' => $farm->stocking_date,
            ])->id;
        }

        return $ids;
    }

    private function rules(): array
    {
        return [
            'farm_name'      => 'required|string|max:255',
            'status'         => 'required|in:0,1',
            'farmer_id'      => 'required|integer|exists:farmers,id',
            'stocking_date'  => 'nullable|date',
            'no_of_tanks'    => 'nullable|integer|min:0',
            'store'          => 'nullable|numeric|min:0',
            'low_feed_limit' => 'nullable|numeric|min:0',
            'feed_used_before' => 'nullable|numeric|min:0',
            'images'         => 'nullable|array|max:2',
            'images.*'       => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }

    private function messages(): array
    {
        return [
            'farmer_id.required' => 'Please choose the farmer who owns this farm.',
            'farmer_id.exists'   => 'That farmer no longer exists.',
        ];
    }
}
