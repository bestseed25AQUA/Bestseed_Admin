<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;


use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Farmer;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:users.view')->only(['index', 'show']);
        $this->middleware('permission:users.create')->only(['create', 'store']);
        $this->middleware('permission:users.update')->only(['edit', 'update', 'forceLogout']);
        $this->middleware('permission:users.delete')->only(['destroy']);
    }

    public function index()
    {
     $users = Farmer::with('latestLocation')->orderBy('id', 'desc')->get();

        return view('admin.user_profile.index', compact('users'));
    }

    public function create()
    {
        return view('admin.user_profile.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name'    => 'required|string|max:50',
            'last_name'     => 'required|string|max:50',
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|min:6|confirmed',
            'profile_image' => 'nullable|image|mimes:jpg,png,jpeg,gif|max:2048',
        ]);

        $user = new User();
        $user->first_name = $request->first_name;
        $user->last_name  = $request->last_name;
        $user->email      = $request->email;
        $user->password   = bcrypt($request->password);


        if ($request->hasFile('profile_image')) {
            $filename = time() . '_' . $request->file('profile_image')->getClientOriginalName();
            $request->file('profile_image')->move(public_path('farmer_profiles'), $filename);
            $user->profile_image = url('farmer_profiles/' . $filename);
        }

        $user->save();

        return redirect()->route('user_profile.index')->with('success', 'User created successfully!');
    }


    public function edit($id)
    {
        $user = Farmer::findOrFail($id);
        return view('admin.user_profile.edit', compact('user'));
    }

    public function update(Request $request, $id)
    {
        // dd($request->all());
        $user = Farmer::findOrFail($id);

        $request->validate([
            'first_name'    => 'required|string|max:50',
            'last_name'     => 'required|string|max:50',
            // 'profile_image' => 'nullable|image|mimes:jpg,png,jpeg,gif|max:2048',
            'first_name'    => 'required',
        ]);

        $user->first_name = $request->first_name;
        $user->last_name  = $request->last_name;
        $user->mobile     = $request->mobile;

        if ($request->hasFile('profile_image')) {
            if ($user->profile_image) {
                $oldPath = str_replace(url('/') . '/', '', $user->profile_image);
                if (file_exists(public_path($oldPath))) {
                    unlink(public_path($oldPath));
                }
            }

            $filename = time() . '_' . $request->file('profile_image')->getClientOriginalName();
            $request->file('profile_image')->move(public_path('farmer_profiles'), $filename);
            $user->profile_image = url('farmer_profiles/' . $filename);
        }

        $user->save();

        return redirect()->route('user_profile.index')->with('success', 'User updated successfully!');
    }

    /**
     * Force-logout a user (farmer) from their mobile device.
     *
     * Use only when the user is facing app issues — logging out and back in
     * can clear a bad session. Sends an FCM `force_logout` push (the app clears
     * its session on receipt — handled in foreground, background AND terminated
     * states) and revokes all the user's Sanctum tokens so any further API call
     * is rejected (401 → app drops to login) even if the push doesn't arrive.
     */
    public function forceLogout($id)
    {
        $user = Farmer::findOrFail($id);

        $hasToken = !empty($user->fcm_token);
        $pushSent = false;

        // 1) Push an instant force-logout to the device (best-effort). The app
        //    handles `type=force_logout` in foreground, background AND terminated
        //    states (clears its session and drops to login).
        if ($hasToken) {
            try {
                $pushSent = app(\App\Services\FirebaseNotificationService::class)->sendToDevice(
                    $user->fcm_token,
                    'Signed out',
                    'You have been logged out by the administrator.',
                    null,
                    ['type' => 'force_logout', 'role' => 'farmer']
                );
            } catch (\Throwable $e) {
                \Log::warning('Admin user force-logout FCM failed', [
                    'farmer_id' => $user->id,
                    'err' => $e->getMessage(),
                ]);
            }

            // Drop the device token: a logged-out device should not keep
            // receiving this account's pushes, and the app registers a fresh
            // token on the next login. (Done AFTER sending so the push above
            // still goes out.)
            $user->fcm_token = null;
            $user->save();
        }

        // 2) Revoke all Sanctum tokens — the real guarantee. Even if the push
        //    was missed (no token / offline / send failure), the next API
        //    request returns 401 and the app logs out.
        $user->tokens()->delete();

        // Feedback reflects what actually happened with the device token.
        if ($pushSent) {
            $msg = 'User has been logged out from their device.';
        } elseif ($hasToken) {
            $msg = 'User session revoked. Push could not be delivered, but they will be signed out the next time the app contacts the server.';
        } else {
            $msg = 'User session revoked (no active device token). They will be signed out the next time the app contacts the server.';
        }

        return redirect()->back()->with('success', $msg);
    }

    public function destroy($id)
    {
        $user = Farmer::findOrFail($id);

        if ($user->profile_image) {
            $oldPath = str_replace(url('/') . '/', '', $user->profile_image);
            if (file_exists(public_path($oldPath))) {
                unlink(public_path($oldPath));
            }
        }

        $user->delete();

        return redirect()->route('user_profile.index')->with('success', 'User deleted successfully!');
    }
}



