<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AdminOtpMail;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class RoleController extends Controller
{
    public function index()
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->hasPermission('roles.view')) {
            abort(403, 'Unauthorized access');
        }

        $roles = Role::withCount(['users', 'permissions'])->latest()->get();
        return view('admin.roles.index', compact('roles'));
    }

    public function create()
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->hasPermission('roles.create')) {
            abort(403, 'Unauthorized access');
        }

        $modules = Permission::select('module')->distinct()->pluck('module');
        $permissions = Permission::all()->groupBy('module');
        return view('admin.roles.create', compact('modules', 'permissions'));
    }

    public function store(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->hasPermission('roles.create')) {
            abort(403, 'Unauthorized access');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'name' => $request->name,
            'slug' => \Str::slug($request->name),
            'description' => $request->description,
            'is_default' => $request->has('is_default'),
        ]);

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role created successfully.');
    }

    public function show(Role $role)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->hasPermission('roles.view')) {
            abort(403, 'Unauthorized access');
        }

        $role->load(['permissions', 'users']);
        return view('admin.roles.show', compact('role'));
    }

    public function edit(Role $role)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->hasPermission('roles.update')) {
            abort(403, 'Unauthorized access');
        }

        $modules = Permission::select('module')->distinct()->pluck('module');
        $permissions = Permission::all()->groupBy('module');
        $rolePermissions = $role->permissions->pluck('id')->toArray();
        return view('admin.roles.edit', compact('role', 'modules', 'permissions', 'rolePermissions'));
    }

    public function update(Request $request, Role $role)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->hasPermission('roles.update')) {
            abort(403, 'Unauthorized access');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update([
            'name' => $request->name,
            'slug' => \Str::slug($request->name),
            'description' => $request->description,
            'is_default' => $request->has('is_default'),
        ]);

        $role->permissions()->sync($request->permissions ?? []);

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role updated successfully.');
    }

    /**
     * Super-admin self-service password change with email OTP.
     *
     * Flow:
     *   1. Admin submits the current + new + confirm passwords (no OTP).
     *      We validate the current password, generate a 6-digit OTP,
     *      email it to their bound email, and stash the OTP + the hashed
     *      new password in the session so the second submit can pick it up.
     *   2. Admin enters the emailed OTP and submits again. We verify the
     *      OTP + expiry from the session, apply the pre-hashed new password,
     *      wipe the session state, and force a re-login.
     *
     * The password never touches the database until step 2. If the admin
     * abandons the flow, the session slot expires and nothing changes.
     */
    public function changeSuperAdminPassword(Request $request)
    {
        // Same gate as the Blade view: only super admins may use this.
        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Unauthorized access');
        }

        // ── Step 2 — verify the OTP and apply the change ────────────────
        if ($request->filled('otp')) {
            $request->validate(['otp' => 'required|digits:6']);

            $pending = $request->session()->get('super_admin_pw_change');
            if (!$pending
                || empty($pending['otp'])
                || empty($pending['new_password_hash'])
                || empty($pending['expires_at'])
                || \Carbon\Carbon::parse($pending['expires_at'])->isPast()
            ) {
                return back()->withErrors([
                    'otp' => 'OTP expired. Please request a new one.',
                ]);
            }

            if ((string) $pending['otp'] !== (string) $request->otp) {
                return back()->withErrors([
                    'otp' => 'Incorrect OTP. Please try again.',
                ]);
            }

            $user = Auth::user();
            $user->password = $pending['new_password_hash'];
            $user->save();

            // Wipe the pending state so a stale replay can't reuse it.
            $request->session()->forget('super_admin_pw_change');

            // Force re-login. Mirrors AdminController::updateAdminProfile so
            // no session stays authenticated with the old password.
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')
                ->with('success', 'Password updated. Please log in again.');
        }

        // ── Step 1 — validate + generate OTP + email + stash in session ─
        $request->validate([
            'old_password' => 'required|string',
            'password'     => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();
        if (!Hash::check($request->old_password, $user->password)) {
            return back()->withErrors([
                'old_password' => 'Current password is incorrect.',
            ]);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $request->session()->put('super_admin_pw_change', [
            'otp'                => $otp,
            'new_password_hash'  => Hash::make($request->password),
            'expires_at'         => now()->addMinutes(10)->toDateTimeString(),
        ]);

        try {
            Mail::to($user->email)->send(new AdminOtpMail($otp));
        } catch (\Throwable $e) {
            // Clear the pending slot so the user can retry cleanly. Log the
            // real reason but tell the user something actionable.
            $request->session()->forget('super_admin_pw_change');
            \Log::error('Super admin password OTP email failed', [
                'user_id' => $user->id,
                'err'     => $e->getMessage(),
            ]);
            return back()->withErrors([
                'old_password' => 'Could not send OTP email. Please try again in a moment.',
            ]);
        }

        return back()->with([
            'password_otp_sent' => true,
            'success'           => 'OTP sent to ' . $user->email . '. Enter it below to confirm the change.',
        ]);
    }

    public function destroy(Role $role)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->hasPermission('roles.delete')) {
            abort(403, 'Unauthorized access');
        }

        // Prevent deleting roles that have users
        if ($role->users()->count() > 0) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'Cannot delete role with assigned users. Remove users from this role first.');
        }

        // Prevent deleting super-admin role
        if ($role->slug === 'super-admin') {
            return redirect()->route('admin.roles.index')
                ->with('error', 'Cannot delete the Super Admin role.');
        }

        $role->permissions()->detach();
        $role->delete();

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role deleted successfully.');
    }
}
