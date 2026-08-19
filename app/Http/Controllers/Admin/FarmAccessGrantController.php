<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\FarmAccessGrant;
use App\Models\Farmer;
use App\Models\Manager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin management of QR/PIN access codes.
 *
 * Mirrors the farmer-side API (Api\User_apis\FarmAccessController) so a code
 * issued here is indistinguishable from one issued in the app: same opaque
 * token in the QR, same encrypted PIN, same revoke semantics.
 */
class FarmAccessGrantController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:farm-management.view')->only(['index', 'show']);
        $this->middleware('permission:farm-management.create')->only(['store']);
        $this->middleware('permission:farm-management.update')->only(['revoke']);
        $this->middleware('permission:farm-management.delete')->only(['destroy']);
    }

    /** Every access code across every farm, newest first. */
    public function index(Request $request)
    {
        $grants = FarmAccessGrant::with(['farm.farmer', 'manager'])
            ->when($request->filled('farm_id'), fn ($q) => $q->where('farm_id', $request->input('farm_id')))
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->input('role')))
            ->when($request->input('status') === 'live', fn ($q) => $q->live())
            ->when($request->input('status') === 'revoked', fn ($q) => $q->whereNotNull('revoked_at'))
            ->when($request->input('status') === 'pending', fn ($q) => $q->whereNull('redeemed_at')->whereNull('revoked_at'))
            ->orderByDesc('id')
            ->get();

        // Who actually scanned each code. redeemed_by holds a farmer id.
        $scanners = Farmer::whereIn('id', $grants->pluck('redeemed_by')->filter()->unique())
            ->get()
            ->keyBy('id');

        $farms = Farm::with('farmer')->orderBy('farm_name')->get();

        return view('admin.farm-management.grants.index', compact('grants', 'scanners', 'farms'));
    }

    /**
     * Issue a code for a farm. The PIN is stored encrypted (not hashed) because
     * the issuing side re-reads it to re-share — same reason as the app flow.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'farm_id'       => 'required|integer|exists:farms,id',
            'role'          => ['required', Rule::in(['manager', 'partner'])],
            'duration_days' => 'required|integer|min:1|max:365',
            'pin'           => 'required|digits:4',
            'view_access'   => 'nullable|boolean',
            'edit_access'   => 'nullable|boolean',
            'create_access' => 'nullable|boolean',
            'delete_access' => 'nullable|boolean',
        ], [
            'pin.digits' => 'The PIN must be exactly 4 digits.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $farm = Farm::findOrFail($request->input('farm_id'));
            $durationDays = (int) $request->input('duration_days');

            $grant = FarmAccessGrant::create([
                'farm_id'       => $farm->id,
                'issued_by'     => $farm->farmer_id,
                'token'         => (string) Str::uuid(),
                'pin_secret'    => Crypt::encryptString($request->input('pin')),
                'role'          => $request->input('role'),
                'view_access'   => (int) $request->boolean('view_access'),
                'edit_access'   => (int) $request->boolean('edit_access'),
                'create_access' => (int) $request->boolean('create_access'),
                'delete_access' => (int) $request->boolean('delete_access'),
                'duration_days' => $durationDays,
                'expires_at'    => now()->addDays($durationDays),
            ]);

            Log::info('Admin issued farm access grant', [
                'grant_id' => $grant->id,
                'farm_id'  => $farm->id,
            ]);

            return redirect()->back()->with('success', 'Access code generated. Share the QR and PIN with the ' . $grant->role . '.');
        } catch (\Exception $e) {
            Log::error('Admin grant create failed', ['error' => $e->getMessage()]);

            return redirect()->back()->withInput()
                ->with('error', 'Could not generate the access code: ' . $e->getMessage());
        }
    }

    /**
     * Revoke without deleting: the row stays for audit, and any manager row it
     * created has its permissions stripped so access stops immediately.
     */
    public function revoke($id)
    {
        $grant = FarmAccessGrant::findOrFail($id);

        try {
            DB::transaction(function () use ($grant) {
                $grant->update(['revoked_at' => now()]);

                if ($grant->manager_id) {
                    Manager::where('id', $grant->manager_id)->update([
                        'view_access'   => 0,
                        'edit_access'   => 0,
                        'create_access' => 0,
                        'delete_access' => 0,
                    ]);
                }
            });

            return redirect()->back()->with('success', 'Access revoked.');
        } catch (\Exception $e) {
            Log::error('Admin grant revoke failed', ['grant_id' => $id, 'error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Could not revoke access: ' . $e->getMessage());
        }
    }

    /** Permanent removal, for clearing out test or mistaken codes. */
    public function destroy($id)
    {
        $grant = FarmAccessGrant::findOrFail($id);

        try {
            $grant->delete();

            return redirect()->back()->with('success', 'Access code deleted.');
        } catch (\Exception $e) {
            Log::error('Admin grant delete failed', ['grant_id' => $id, 'error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Could not delete the access code: ' . $e->getMessage());
        }
    }
}
