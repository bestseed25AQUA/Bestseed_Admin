<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\FarmAccessGrant;
use App\Models\Manager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Managers and partners — the people attached to a farm.
 *
 * Both live in the `managers` table and are told apart by `is_partner`, so one
 * controller covers both and the role is just a filter.
 */
class FarmTeamController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:farm-management.view')->only(['index']);
        $this->middleware('permission:farm-management.create')->only(['create', 'store']);
        $this->middleware('permission:farm-management.update')->only(['edit', 'update']);
        $this->middleware('permission:farm-management.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $team = Manager::with('farm.farmer')
            ->when($request->filled('farm_id'), fn ($q) => $q->where('farm_id', $request->input('farm_id')))
            ->when($request->input('role') === 'manager', fn ($q) => $q->managers())
            ->when($request->input('role') === 'partner', fn ($q) => $q->partners())
            ->orderByDesc('id')
            ->get();

        $farms = Farm::with('farmer')->orderBy('farm_name')->get();

        return view('admin.farm-management.team.index', compact('team', 'farms'));
    }

    public function create(Request $request)
    {
        return view('admin.farm-management.team.create', [
            'farms'         => Farm::with('farmer')->orderBy('farm_name')->get(),
            'selectedFarm'  => $request->input('farm_id'),
            'selectedRole'  => $request->input('role', 'manager'),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules(), $this->messages());

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // (farm_id, phone) is unique — the same person can be on two farms, but
        // not twice on one.
        $exists = Manager::where('farm_id', $request->input('farm_id'))
            ->where('phone', $request->input('phone'))
            ->exists();

        if ($exists) {
            return redirect()->back()->withInput()
                ->with('error', 'That phone number is already on this farm.');
        }

        try {
            $member = Manager::create($this->payload($request));

            return redirect()->route('farm-management.team.index')
                ->with('success', ucfirst($member->role_label) . ' added successfully.');
        } catch (\Exception $e) {
            Log::error('Admin team create failed', ['error' => $e->getMessage()]);

            return redirect()->back()->withInput()
                ->with('error', 'Could not add this person: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        return view('admin.farm-management.team.edit', [
            'member' => Manager::with('farm')->findOrFail($id),
            'farms'  => Farm::with('farmer')->orderBy('farm_name')->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $member = Manager::findOrFail($id);

        $validator = Validator::make($request->all(), $this->rules($id), $this->messages());

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $clash = Manager::where('farm_id', $request->input('farm_id'))
            ->where('phone', $request->input('phone'))
            ->where('id', '!=', $member->id)
            ->exists();

        if ($clash) {
            return redirect()->back()->withInput()
                ->with('error', 'That phone number is already on this farm.');
        }

        try {
            $member->update($this->payload($request));

            return redirect()->route('farm-management.team.index')
                ->with('success', 'Access updated successfully.');
        } catch (\Exception $e) {
            Log::error('Admin team update failed', ['manager_id' => $id, 'error' => $e->getMessage()]);

            return redirect()->back()->withInput()
                ->with('error', 'Could not update this person: ' . $e->getMessage());
        }
    }

    /**
     * Removing a person also revokes the grant that produced them, otherwise
     * the QR would still be redeemable and would silently recreate the row.
     */
    public function destroy($id)
    {
        $member = Manager::findOrFail($id);

        try {
            DB::transaction(function () use ($member) {
                FarmAccessGrant::where('manager_id', $member->id)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);

                $member->delete();
            });

            return redirect()->route('farm-management.team.index')
                ->with('success', 'Removed, and any access code that created them was revoked.');
        } catch (\Exception $e) {
            Log::error('Admin team delete failed', ['manager_id' => $id, 'error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Could not remove this person: ' . $e->getMessage());
        }
    }

    private function rules($ignoreId = null): array
    {
        return [
            'farm_id'       => 'required|integer|exists:farms,id',
            'name'          => 'required|string|max:255',
            'phone'         => 'required|digits:10',
            'is_partner'    => ['required', Rule::in([0, 1, '0', '1'])],
            'view_access'   => 'nullable|boolean',
            'edit_access'   => 'nullable|boolean',
            'create_access' => 'nullable|boolean',
            'delete_access' => 'nullable|boolean',
        ];
    }

    private function messages(): array
    {
        return [
            'farm_id.required' => 'Please choose the farm this person belongs to.',
            'phone.digits'     => 'Phone number must be exactly 10 digits.',
        ];
    }

    private function payload(Request $request): array
    {
        return [
            'farm_id'       => $request->input('farm_id'),
            'name'          => $request->input('name'),
            'phone'         => $request->input('phone'),
            'is_partner'    => (int) $request->input('is_partner'),
            'view_access'   => (int) $request->boolean('view_access'),
            'edit_access'   => (int) $request->boolean('edit_access'),
            'create_access' => (int) $request->boolean('create_access'),
            'delete_access' => (int) $request->boolean('delete_access'),
        ];
    }
}
