<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminLoginOtp;
use App\Models\User;
use App\Mail\AdminOtpMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class AdminOtpController extends Controller
{
    public function showOtpForm()
    {

         // 🔒 If already logged in, block OTP page
        if (Auth::check()) {
            return redirect()->route('admin');
        }


        if (!session('admin_2fa_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.admin-otp');
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => 'required|digits:6',
        ]);

        $otp = AdminLoginOtp::where('user_id', session('admin_2fa_user_id'))
            ->where('otp_code', $request->otp)
            ->where('verified', 0)
            ->where('expires_at', '>=', Carbon::now())
            ->latest()
            ->first();

        if (!$otp) {
            return back()->withErrors(['otp' => 'Invalid or expired OTP']);
        }

        $otp->update(['verified' => 1]);

        $user = User::find($otp->user_id);

        $request->session()->forget('admin_2fa_user_id');

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('admin');
    }

    public function resendOtp()
    {
        $user = User::find(session('admin_2fa_user_id'));

        $otp = rand(100000, 999999);

        AdminLoginOtp::where('user_id', $user->id)->update(['verified' => 1]);

        AdminLoginOtp::create([
            'user_id' => $user->id,
            'otp_code' => $otp,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        Mail::to($user->email)->send(new AdminOtpMail($otp));

        return back()->with('success', 'New OTP sent to your email');
    }
}
