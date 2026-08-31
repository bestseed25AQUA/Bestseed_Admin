<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\FarmAccessMember;
use App\Models\Farmer;
use App\Services\FarmAccessService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Farm access sharing.
 *
 * A farmer grants one of their farms to people they pick by name or mobile,
 * with a role, permissions and an optional duration. Anyone already holding
 * access may pass on what they hold, capped server-side at the giver's own
 * permissions.
 *
 * The QR + PIN flow this class used to carry — generate, index, grantees,
 * revoke, redeem and verifyPin — has been removed along with its routes.
 */
class FarmAccessController extends Controller
{
    /**
     * The caller's own standing on a farm, or abort if they have none.
     *
     * Anyone with access may pass it on — an owner to a manager, that manager
     * to someone else, and so on. What they may NOT do is hand out more than
     * they hold, so the grant is capped against this.
     */
    private function callerPermission(Request $request, $farmId): array
    {
        $farm = Farm::find($farmId);

        if (!$farm) {
            throw new HttpResponseException(response()->json([
                'status'  => false,
                'message' => 'Farm not found',
            ], 404));
        }

        $permission = app(FarmAccessService::class)
            ->permissionFor($request->user()->id, $farm);

        if ($permission->isDenied()) {
            throw new HttpResponseException(response()->json([
                'status'  => false,
                'message' => 'You do not have access to this farm.',
            ], 403));
        }

        return [$farm, $permission];
    }

    /**
     * GET /api/farmer/farmers/search?q=...
     *
     * Find people to grant access to, by name or mobile. Authenticated, and
     * deliberately thin: enough to recognise someone, nothing more.
     */
    public function searchFarmers(Request $request)
    {
        $term = trim((string) $request->input('q', ''));

        if (mb_strlen($term) < 3) {
            return response()->json([
                'status'  => false,
                'message' => 'Type at least 3 characters to search.',
                'data'    => [],
            ], 422);
        }

        $farmers = Farmer::query()
            ->where('id', '!=', $request->user()->id)
            ->where(function ($q) use ($term) {
                $q->where('mobile', 'like', "%{$term}%")
                  ->orWhere('first_name', 'like', "%{$term}%")
                  ->orWhere('last_name', 'like', "%{$term}%");
            })
            ->orderBy('first_name')
            ->limit(20)
            ->get(['id', 'first_name', 'last_name', 'mobile', 'profile_image']);

        return response()->json([
            'status' => true,
            'data'   => $farmers->map(fn ($f) => [
                'id'     => $f->id,
                'name'   => trim($f->first_name . ' ' . $f->last_name),
                'mobile' => $f->mobile,
                'image'  => $f->profile_image,
            ]),
        ]);
    }

    /**
     * POST /api/farmer/farm/{farm}/members
     *   { farmer_ids: [..], role, view_access, edit_access, create_access,
     *     delete_access, duration_days? }
     *
     * Grant access to one or more people. They can open the farm immediately.
     */
    public function addMembers(Request $request, $farmId)
    {
        [$farm, $mine] = $this->callerPermission($request, $farmId);

        $validator = validator($request->all(), [
            'farmer_ids'    => 'required|array|min:1',
            'farmer_ids.*'  => 'integer|exists:farmers,id',
            'role'          => ['required', Rule::in(['manager', 'partner'])],
            'view_access'   => 'nullable|boolean',
            'edit_access'   => 'nullable|boolean',
            'create_access' => 'nullable|boolean',
            'delete_access' => 'nullable|boolean',
            'duration_days' => 'nullable|integer|min:1|max:365',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Nobody can hand out more than they hold.
        $wanted = [
            'view_access'   => (int) $request->boolean('view_access'),
            'edit_access'   => (int) $request->boolean('edit_access'),
            'create_access' => (int) $request->boolean('create_access'),
            'delete_access' => (int) $request->boolean('delete_access'),
        ];

        $capped = [
            'view_access'   => (int) ($wanted['view_access'] && $mine->view),
            'edit_access'   => (int) ($wanted['edit_access'] && $mine->edit),
            'create_access' => (int) ($wanted['create_access'] && $mine->create),
            'delete_access' => (int) ($wanted['delete_access'] && $mine->delete),
        ];

        if (array_sum($capped) === 0) {
            return response()->json([
                'status'  => false,
                'message' => 'You cannot grant permissions you do not hold yourself.',
            ], 403);
        }

        $expiresAt = $request->filled('duration_days')
            ? now()->addDays((int) $request->input('duration_days'))
            : null;

        $added = [];

        DB::transaction(function () use (
            $request, $farm, $capped, $expiresAt, &$added
        ) {
            foreach ($request->input('farmer_ids') as $farmerId) {
                // The owner does not need granting access to their own farm.
                if ((int) $farmerId === (int) $farm->farmer_id) {
                    continue;
                }

                $member = FarmAccessMember::updateOrCreate(
                    ['farm_id' => $farm->id, 'farmer_id' => $farmerId],
                    array_merge($capped, [
                        'granted_by' => $request->user()->id,
                        'role'       => $request->input('role'),
                        'expires_at' => $expiresAt,
                        'revoked_at' => null,
                    ])
                );

                $added[] = $member->id;
            }
        });

        return response()->json([
            'status'  => true,
            'message' => count($added) . ' person(s) given access.',
            'data'    => ['member_ids' => $added],
        ], 201);
    }

    /**
     * GET /api/farmer/farm/{farm}/members
     *
     * Everyone who currently holds access, however they got it.
     */
    public function members(Request $request, $farmId)
    {
        [$farm] = $this->callerPermission($request, $farmId);

        $members = FarmAccessMember::with(['farmer', 'grantedBy'])
            ->where('farm_id', $farm->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (FarmAccessMember $m) => [
                'id'         => $m->id,
                'farmer_id'  => $m->farmer_id,
                'name'       => trim(($m->farmer->first_name ?? '') . ' ' . ($m->farmer->last_name ?? '')),
                'mobile'     => $m->farmer->mobile ?? null,
                'role'       => $m->role,
                'granted_by' => $m->grantedBy
                    ? trim($m->grantedBy->first_name . ' ' . $m->grantedBy->last_name)
                    : null,
                'status'     => $m->isLive() ? 'active' : ($m->revoked_at ? 'revoked' : 'expired'),
                'expires_at' => optional($m->expires_at)->toIso8601String(),
                'permissions' => [
                    'view'   => (bool) $m->view_access,
                    'edit'   => (bool) $m->edit_access,
                    'create' => (bool) $m->create_access,
                    'delete' => (bool) $m->delete_access,
                ],
            ]);

        return response()->json([
            'status' => true,
            'data'   => $members,
        ]);
    }

    /**
     * POST /api/farmer/members/{member}/revoke
     *
     * Take access away. The owner may revoke anyone; a member may only revoke
     * someone they themselves admitted, so nobody can lock out the person who
     * let them in.
     */
    public function revokeMember(Request $request, $memberId)
    {
        $member = FarmAccessMember::find($memberId);

        if (!$member) {
            return response()->json([
                'status'  => false,
                'message' => 'That access record no longer exists.',
            ], 404);
        }

        [$farm, $mine] = $this->callerPermission($request, $member->farm_id);

        $isOwner = $mine->isOwner();
        $admittedThem = (int) $member->granted_by === (int) $request->user()->id;

        if (!$isOwner && !$admittedThem) {
            return response()->json([
                'status'  => false,
                'message' => 'You can only remove people you gave access to.',
            ], 403);
        }

        $member->update(['revoked_at' => now()]);

        return response()->json([
            'status'  => true,
            'message' => 'Access removed.',
        ]);
    }
}
