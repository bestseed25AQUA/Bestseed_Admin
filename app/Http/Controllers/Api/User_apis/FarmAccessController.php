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
        $mobile = preg_replace('/\D/', '', (string) $request->input('q', ''));

        // Access is given by mobile number and nothing else. A name search
        // invites picking the wrong "Ramesh" out of a list of suggestions and
        // handing a stranger the farm, so there are no partial matches here:
        // ten digits, one exact answer, or none.
        if (strlen($mobile) !== 10) {
            return response()->json([
                'status'  => false,
                'message' => 'Enter the full 10-digit mobile number.',
                'data'    => [],
            ], 422);
        }

        if ((string) $request->user()->mobile === $mobile) {
            return response()->json([
                'status'  => false,
                'message' => 'That is your own number.',
                'data'    => [],
            ], 422);
        }

        $farmer = Farmer::where('mobile', $mobile)
            ->first(['id', 'first_name', 'last_name', 'mobile', 'profile_image']);

        // Not an error — the caller is told plainly that nobody holds this
        // number, so the app can offer to add them by it.
        if (!$farmer) {
            return response()->json([
                'status'      => true,
                'found'       => false,
                'mobile'      => $mobile,
                'message'     => 'No one is registered with this number yet.',
                'data'        => [],
            ]);
        }

        return response()->json([
            'status' => true,
            'found'  => true,
            'mobile' => $mobile,
            'data'   => [[
                'id'     => $farmer->id,
                'name'   => trim($farmer->first_name . ' ' . $farmer->last_name),
                'mobile' => $farmer->mobile,
                'image'  => $farmer->profile_image,
            ]],
        ]);
    }

    /**
     * POST /api/farmer/farm/{farm}/members
     *   { farmer_ids: [..], role, view_access, edit_access, create_access,
     *     delete_access }
     *
     * Grant access to one or more people. They can open the farm immediately.
     */
    public function addMembers(Request $request, $farmId)
    {
        [$farm, $mine] = $this->callerPermission($request, $farmId);

        $validator = validator($request->all(), [
            // Either existing people, or numbers nobody has registered yet.
            'farmer_ids'    => 'nullable|array',
            'farmer_ids.*'  => 'integer|exists:farmers,id',
            'mobiles'       => 'nullable|array',
            'mobiles.*'     => 'digits:10',
            'role'          => ['required', Rule::in(['manager', 'partner'])],
            'view_access'        => 'nullable|boolean',
            'edit_access'        => 'nullable|boolean',
            'tank_status_access' => 'nullable|boolean',
            'total_feed_access'  => 'nullable|boolean',
            'create_access' => 'nullable|boolean',
            'delete_access' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $farmerIds = (array) $request->input('farmer_ids', []);
        $mobiles   = (array) $request->input('mobiles', []);

        if (empty($farmerIds) && empty($mobiles)) {
            return response()->json([
                'status'  => false,
                'message' => 'Choose at least one person to give access to.',
            ], 422);
        }

        // A number nobody has registered becomes a farmer here and now, with
        // the same shape the login flow creates: mobile plus role, nothing
        // else. When that person later asks for an OTP, firstOrCreate finds
        // THIS row rather than making a second one, so the farm is already
        // waiting for them the first time they sign in.
        foreach ($mobiles as $mobile) {
            $farmerIds[] = Farmer::firstOrCreate(
                ['mobile' => $mobile],
                ['role' => 'farmer']
            )->id;
        }

        $farmerIds = array_values(array_unique(array_map('intval', $farmerIds)));

        // Nobody can hand out more than they hold.
        $wanted = [
            'view_access'        => (int) $request->boolean('view_access'),
            'edit_access'        => (int) $request->boolean('edit_access'),
            'tank_status_access' => (int) $request->boolean('tank_status_access'),
            'total_feed_access'  => (int) $request->boolean('total_feed_access'),
            'create_access'      => (int) $request->boolean('create_access'),
            'delete_access'      => (int) $request->boolean('delete_access'),
        ];

        $capped = [
            'view_access'        => (int) ($wanted['view_access'] && $mine->view),
            'edit_access'        => (int) ($wanted['edit_access'] && $mine->edit),
            'tank_status_access' => (int) ($wanted['tank_status_access'] && $mine->tankStatus),
            'total_feed_access'  => (int) ($wanted['total_feed_access'] && $mine->totalFeed),
            'create_access'      => (int) ($wanted['create_access'] && $mine->create),
            'delete_access'      => (int) ($wanted['delete_access'] && $mine->delete),
        ];

        if (array_sum($capped) === 0) {
            return response()->json([
                'status'  => false,
                'message' => 'You cannot grant permissions you do not hold yourself.',
            ], 403);
        }

        $added = [];

        DB::transaction(function () use (
            $request, $farm, $capped, $farmerIds, &$added
        ) {
            foreach ($farmerIds as $farmerId) {
                // The owner does not need granting access to their own farm.
                if ((int) $farmerId === (int) $farm->farmer_id) {
                    continue;
                }

                $member = FarmAccessMember::updateOrCreate(
                    ['farm_id' => $farm->id, 'farmer_id' => $farmerId],
                    array_merge($capped, [
                        'granted_by' => $request->user()->id,
                        'role'       => $request->input('role'),
                        'expires_at' => null,
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
                'status'     => $m->isLive() ? 'active' : 'revoked',
                'permissions' => [
                    'view'        => (bool) $m->view_access,
                    'edit'        => (bool) $m->edit_access,
                    'tank_status' => (bool) $m->tank_status_access,
                    'total_feed'  => (bool) $m->total_feed_access,
                    'create'      => (bool) $m->create_access,
                    'delete'      => (bool) $m->delete_access,
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
