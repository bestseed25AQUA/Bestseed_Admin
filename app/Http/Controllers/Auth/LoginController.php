<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\AdminOtpMail;
use App\Models\AdminLoginOtp;
use Carbon\Carbon;
// use Illuminate\Container\Attributes\Auth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Mail;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/admin';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    
    protected function authenticated($request, $user)
{
    if ($user->is_admin == 1) {

        // 🔐 Invalidate all previous OTPs for this user
        AdminLoginOtp::where('user_id', $user->id)
            ->update(['verified' => 1]);

        $otp = rand(100000, 999999);

        AdminLoginOtp::create([
            'user_id' => $user->id,
            'otp_code' => $otp,
            'verified' => 0,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        Mail::to($user->email)->send(
            new AdminOtpMail($otp)
        );

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $request->session()->put('admin_2fa_user_id', $user->id);

        return redirect()->route('admin.otp');
    }
}




}
