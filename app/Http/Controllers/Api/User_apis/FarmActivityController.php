<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Services\FarmAccessService;
use App\Support\FarmPermission;
use Illuminate\Http\Request;

/**
 * The farm's recent history, for the app.
 *
 * Fifteen days. Long enough to settle an argument about last week's figures,
 * short enough that a farm worked daily does not open onto a thousand rows.
 * The admin panel keeps thirty; both windows live on [FarmActivity].
 *
 * Owners and partners only. A manager's own entries are IN here — the point is
 * to see what the people working the farm have done — but a manager does not
 * get to audit the farm they work on any more than they get to hand it out.
 */
class FarmActivityController extends Controller
{
    public function __construct(private readonly FarmAccessService $access)
    {
    }

    /**
     * GET /api/farmer/farm/{farm}/activity
     *
     * Optional `category` narrows to farm | tank | feed | store | access, and
     * `tank_id` to one tank, so the history screen can be opened from a tank
     * already filtered to it.
     */
    public function index(Request $request, $farmId)
    {
        $farm = Farm::withTrashed()->find($farmId);

        if (!$farm) {
            return response()->json([
                'status'  => false,
                'message' => 'Farm not found',
            ], 404);
        }

        $permission = $this->access->permissionFor($request->user()->id, $farm);

        if ($permission->isDenied()) {
            return response()->json([
                'status'  => false,
                'message' => 'You do not have access to this farm.',
            ], 403);
        }

        // Owner or partner. Deliberately the same rule as giving access away —
        // both are "this is my farm" actions rather than "I work here" ones.
        if (!$permission->isOwner() && $permission->role !== FarmPermission::ROLE_PARTNER) {
            return response()->json([
                'status'  => false,
                'message' => 'Only the farm owner or a partner can view the farm history.',
            ], 403);
        }

        $days = FarmActivity::APP_WINDOW_DAYS;

        $query = FarmActivity::forFarm($farm->id)->recent($days);

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('tank_id')) {
            $query->where('tank_id', (int) $request->input('tank_id'));
        }

        $entries = $query->limit(500)->get();

        return response()->json([
            'status' => true,
            'data'   => [
                'window_days' => $days,
                // Sent so the empty state can say "nothing in the last 15
                // days" rather than "no history", which reads as never.
                'categories'  => collect(FarmActivity::categories())
                    ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])
                    ->values(),
                'entries'     => $entries->map(fn (FarmActivity $entry) => [
                    'id'             => $entry->id,
                    'category'       => $entry->category,
                    'category_label' => $entry->category_label,
                    'action'         => $entry->action,
                    'description'    => $entry->description,
                    'tank_id'        => $entry->tank_id,
                    'tank_name'      => $entry->tank_name,
                    'actor_name'     => $entry->actor_name,
                    'actor_mobile'   => $entry->actor_mobile,
                    'actor_role'     => $entry->actor_role,
                    'created_at'     => $entry->created_at?->toIso8601String(),
                    // Preformatted so every device shows one format regardless
                    // of its own locale settings.
                    'happened_on'    => $entry->created_at?->format('d M Y'),
                    'happened_at'    => $entry->created_at?->format('h:i A'),
                ])->values(),
            ],
        ]);
    }
}
