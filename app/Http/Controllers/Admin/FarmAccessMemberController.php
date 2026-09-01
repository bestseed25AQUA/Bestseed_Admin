<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\FarmAccessMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Admin management of who actually holds access to a farm.
 *
 * A grant is an invitation; a membership is the access itself. People reach a
 * farm two ways — scanning a QR and passing its PIN, or being picked directly
 * by the owner or by another member — and only the second leaves a grant row
 * behind. Without this screen, directly-assigned managers and partners were
 * invisible to admin and could not be revoked from here at all.
 *
 * Unlike the app, admin is not capped by anyone's own permissions: an admin
 * may grant or revoke anything on any farm.
 */
class FarmAccessMemberController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:farm-management.view')->only(['index']);
        $this->middleware('permission:farm-management.create')->only(['store']);
        $this->middleware('permission:farm-management.update')->only(['update', 'revoke', 'restore']);
        $this->middleware('permission:farm-management.delete')->only(['destroy']);
    }

    /** Every membership across every farm, for the standalone list screen. */
    public function index(Request $request)
    {
        $members = FarmAccessMember::with(['farm.farmer', 'farmer', 'grantedBy'])
            ->when($request->filled('farm_id'), fn ($q) => $q->where('farm_id', $request->input('farm_id')))
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->input('role')))
            ->when($request->input('status') === 'live', fn ($q) => $q->live())
            ->when($request->input('status') === 'revoked', fn ($q) => $q->whereNotNull('revoked_at'))
            ->orderByDesc('id')
            ->get();

        return view('admin.farm-management.members.index', [
            'members' => $members,
            'farms'   => Farm::with('farmer')->orderBy('farm_name')->get(),
        ]);
    }

    /** Give one or more farmers access to a farm, without any QR or PIN. */
    public function store(Request $request, $farmId)
    {
        $farm = Farm::withTrashed()->findOrFail($farmId);

        $validator = Validator::make($request->all(), [
            'farmer_ids'    => 'required|array|min:1',
            'farmer_ids.*'  => 'integer|exists:farmers,id',
            'role'          => ['required', Rule::in(['manager', 'partner'])],
            'view_access'   => 'nullable|boolean',
            'edit_access'        => 'nullable|boolean',
            'tank_status_access' => 'nullable|boolean',
            'total_feed_access'  => 'nullable|boolean',
            'create_access' => 'nullable|boolean',
            'delete_access' => 'nullable|boolean',
        ], [
            'farmer_ids.required' => 'Pick at least one person to give access to.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $permissions = $this->permissionsFrom($request);

        if (array_sum($permissions) === 0) {
            return redirect()->back()->withInput()
                ->with('error', 'Choose at least one permission, otherwise the person would have access to nothing.');
        }

        try {
            $added = 0;
            $skippedOwner = false;

            DB::transaction(function () use ($request, $farm, $permissions, &$added, &$skippedOwner) {
                foreach ($request->input('farmer_ids') as $farmerId) {
                    // The owner already has every permission by virtue of
                    // owning the farm; a membership row would be meaningless.
                    if ((int) $farmerId === (int) $farm->farmer_id) {
                        $skippedOwner = true;
                        continue;
                    }

                    // updateOrCreate, so re-adding someone re-grants rather
                    // than stacking a second row for the same person.
                    FarmAccessMember::updateOrCreate(
                        ['farm_id' => $farm->id, 'farmer_id' => $farmerId],
                        array_merge($permissions, [
                            'granted_by' => $farm->farmer_id,
                            'role'       => $request->input('role'),
                            'expires_at' => null,
                            'revoked_at' => null,
                        ])
                    );

                    $added++;
                }
            });

            Log::info('Admin added farm access members', ['farm_id' => $farm->id, 'count' => $added]);

            $message = $added . ' ' . str($request->input('role'))->plural($added) . ' given access.';

            if ($skippedOwner) {
                $message .= ' The farm owner was skipped — they already have full access.';
            }

            return redirect()->back()->with($added ? 'success' : 'error', $added ? $message : 'Nobody was added.' . ($skippedOwner ? ' The only person picked was the farm owner.' : ''));
        } catch (\Exception $e) {
            Log::error('Admin member add failed', ['farm_id' => $farmId, 'error' => $e->getMessage()]);

            return redirect()->back()->withInput()->with('error', 'Could not give access: ' . $e->getMessage());
        }
    }

    /** Change what an existing member may do, or how long they keep it. */
    public function update(Request $request, $memberId)
    {
        $member = FarmAccessMember::findOrFail($memberId);

        $validator = Validator::make($request->all(), [
            'role'          => ['required', Rule::in(['manager', 'partner'])],
            'view_access'   => 'nullable|boolean',
            'edit_access'        => 'nullable|boolean',
            'tank_status_access' => 'nullable|boolean',
            'total_feed_access'  => 'nullable|boolean',
            'create_access' => 'nullable|boolean',
            'delete_access' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $permissions = $this->permissionsFrom($request);

        if (array_sum($permissions) === 0) {
            return redirect()->back()->withInput()
                ->with('error', 'Choose at least one permission, or revoke this member instead.');
        }

        try {
            $member->update(array_merge($permissions, [
                'role'       => $request->input('role'),
                'expires_at' => null,
            ]));

            return redirect()->back()->with('success', 'Access updated.');
        } catch (\Exception $e) {
            Log::error('Admin member update failed', ['member_id' => $memberId, 'error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Could not update access: ' . $e->getMessage());
        }
    }

    /**
     * Stop access without losing the record of it having been given.
     * The row stays so the farm's access history remains auditable.
     */
    public function revoke($memberId)
    {
        $member = FarmAccessMember::findOrFail($memberId);

        try {
            $member->update(['revoked_at' => now()]);

            return redirect()->back()->with('success', 'Access revoked.');
        } catch (\Exception $e) {
            Log::error('Admin member revoke failed', ['member_id' => $memberId, 'error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Could not revoke access: ' . $e->getMessage());
        }
    }

    /** Undo a revoke. Expiry is left as it was — extend it separately. */
    public function restore($memberId)
    {
        $member = FarmAccessMember::findOrFail($memberId);

        try {
            $member->update(['revoked_at' => null]);

            return redirect()->back()->with(
                'success',
                $member->isLive()
                    ? 'Access restored.'
                    : 'Access restored, but it is already past its expiry date — extend it to make it usable.'
            );
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Could not restore access: ' . $e->getMessage());
        }
    }

    /** Permanent removal, for clearing out mistakes. */
    public function destroy($memberId)
    {
        $member = FarmAccessMember::findOrFail($memberId);

        try {
            $member->delete();

            return redirect()->back()->with('success', 'Member removed.');
        } catch (\Exception $e) {
            Log::error('Admin member delete failed', ['member_id' => $memberId, 'error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Could not remove the member: ' . $e->getMessage());
        }
    }

    private function permissionsFrom(Request $request): array
    {
        return [
            'view_access'        => (int) $request->boolean('view_access'),
            'edit_access'        => (int) $request->boolean('edit_access'),
            'tank_status_access' => (int) $request->boolean('tank_status_access'),
            'total_feed_access'  => (int) $request->boolean('total_feed_access'),
            'create_access'      => (int) $request->boolean('create_access'),
            'delete_access'      => (int) $request->boolean('delete_access'),
        ];
    }
}
