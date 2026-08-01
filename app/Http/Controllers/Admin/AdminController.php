<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminController extends Controller
{
    /**
     * Show admin profile edit form
     */
    public function profile()
    {
        $user = Auth::user();
        return view('admin.profile', compact('user'));
    }

    /**
     * Update admin profile
     */
    public function updateAdminProfile(Request $request)
    {
        $user = Auth::user();

        // Validation rules
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'old_password' => 'nullable|required_with:password',
            'password' => 'nullable|min:8|confirmed',
        ];

        // Custom error messages
        $messages = [
            'old_password.required_with' => 'Current password is required when changing password',
            'password.confirmed' => 'Password confirmation does not match',
        ];

        // Validate the request
        $request->validate($rules, $messages);

        // Prepare data for update
        $userData = [
            'name' => $request->name,
            'email' => $request->email,
        ];

        // Handle password change if requested
        if ($request->filled('password')) {
            if (!Hash::check($request->old_password, $user->password)) {
                return redirect()->back()
                    ->withInput($request->except('password', 'password_confirmation'))
                    ->with('danger', 'Current password is incorrect');
            }

            $userData['password'] = Hash::make($request->password);
        }

        // Update user data
        try {
            $user->update($userData);

            // If password was changed, log the user out
            if ($request->filled('password')) {
                Auth::logout();
                return redirect()->route('login')
                    ->with('success', 'Profile updated successfully. Please login with your new password.');
            }

            return redirect()->route('admin_profile')
                ->with('success', 'Profile updated successfully');

        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('danger', 'Error updating profile: ' . $e->getMessage());
        }
    }
}
