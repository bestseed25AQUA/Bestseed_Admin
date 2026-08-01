<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SubAdminController extends Controller
{
    public function index()
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->hasPermission('sub-admins.view')) {
            abort(403, 'Unauthorized access');
        }

        // Get users who are admins OR have roles assigned (sub-admins)
        $subAdmins = User::where('id', '!=', auth()->id())
            ->where(function($query) {
                $query->where('is_admin', 1)
                      ->orWhereHas('roles');
            })
            ->with('roles')
            ->latest()
            ->get();

        return view('admin.sub-admins.index', compact('subAdmins'));
    }

    public function create()
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->hasPermission('sub-admins.create')) {
            abort(403, 'Unauthorized access');
        }

        $roles = Role::all();
        return view('admin.sub-admins.create', compact('roles'));
    }

    public function store(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->hasPermission('sub-admins.create')) {
            abort(403, 'Unauthorized access');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,id',
        ]);

        // Only super admin can create other super admins
        if ($request->has('is_super_admin') && $request->is_super_admin && !auth()->user()->isSuperAdmin()) {
            abort(403, 'Only Super Admin can create other Super Admins.');
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_admin' => 1,
            'is_super_admin' => $request->has('is_super_admin') && auth()->user()->isSuperAdmin(),
        ]);

        $user->roles()->sync($request->roles);

        return redirect()->route('admin.sub-admins.index')
            ->with('success', 'Sub Admin created successfully.');
    }

    public function show(User $sub_admin)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->hasPermission('sub-admins.view')) {
            abort(403, 'Unauthorized access');
        }

        $sub_admin->load('roles.permissions');
        $allPermissions = $sub_admin->getAllPermissions();

        return view('admin.sub-admins.show', compact('sub_admin', 'allPermissions'));
    }

    public function edit(User $sub_admin)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->hasPermission('sub-admins.update')) {
            abort(403, 'Unauthorized access');
        }

        // Prevent editing super admin by non-super admin
        if ($sub_admin->isSuperAdmin() && !auth()->user()->isSuperAdmin()) {
            abort(403, 'You cannot edit a Super Admin.');
        }

        $roles = Role::all();
        $userRoles = $sub_admin->roles->pluck('id')->toArray();

        return view('admin.sub-admins.edit', compact('sub_admin', 'roles', 'userRoles'));
    }

    public function update(Request $request, User $sub_admin)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->hasPermission('sub-admins.update')) {
            abort(403, 'Unauthorized access');
        }

        // Prevent editing super admin by non-super admin
        if ($sub_admin->isSuperAdmin() && !auth()->user()->isSuperAdmin()) {
            abort(403, 'You cannot edit a Super Admin.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $sub_admin->id,
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,id',
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        // Only super admin can grant/revoke super admin status
        if (auth()->user()->isSuperAdmin()) {
            $data['is_super_admin'] = $request->has('is_super_admin');
        }

        $sub_admin->update($data);
        $sub_admin->roles()->sync($request->roles);

        return redirect()->route('admin.sub-admins.index')
            ->with('success', 'Sub Admin updated successfully.');
    }

    public function destroy(User $sub_admin)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->hasPermission('sub-admins.delete')) {
            abort(403, 'Unauthorized access');
        }

        // Prevent self-deletion
        if ($sub_admin->id === auth()->id()) {
            return redirect()->route('admin.sub-admins.index')
                ->with('error', 'You cannot delete yourself.');
        }

        // Prevent deleting super admin by non-super admin
        if ($sub_admin->isSuperAdmin() && !auth()->user()->isSuperAdmin()) {
            return redirect()->route('admin.sub-admins.index')
                ->with('error', 'You cannot delete a Super Admin.');
        }

        $sub_admin->roles()->detach();
        $sub_admin->delete();

        return redirect()->route('admin.sub-admins.index')
            ->with('success', 'Sub Admin deleted successfully.');
    }

    public function toggleStatus(User $sub_admin)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->hasPermission('sub-admins.update')) {
            abort(403, 'Unauthorized access');
        }

        // Prevent toggling self
        if ($sub_admin->id === auth()->id()) {
            return redirect()->route('admin.sub-admins.index')
                ->with('error', 'You cannot disable yourself.');
        }

        // Prevent toggling super admin by non-super admin
        if ($sub_admin->isSuperAdmin() && !auth()->user()->isSuperAdmin()) {
            return redirect()->route('admin.sub-admins.index')
                ->with('error', 'You cannot modify a Super Admin.');
        }

        $sub_admin->update(['is_admin' => !$sub_admin->is_admin]);

        $status = $sub_admin->is_admin ? 'enabled' : 'disabled';
        return redirect()->route('admin.sub-admins.index')
            ->with('success', "Sub Admin {$status} successfully.");
    }
}
