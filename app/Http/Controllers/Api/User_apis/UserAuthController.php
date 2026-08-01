<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\Farmer;
use App\Models\UserOtp;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Helpers\SmsHelper;
use App\Services\FirebaseNotificationService;


class UserAuthController extends Controller
{

    /**
     * ======================
     * Login (alias for sendOtp)
     * ======================
     * Frontend can call this instead of sendOtp.
     */
    public function login(Request $request)
    {
        return $this->sendOtp($request);
    }
    /**
     * ======================
     * Send OTP (Login / Signup)
     * ======================
     * Always generate 6-digit OTP.
     * If user is new -> create Farmer record.
     * If already exists -> reuse Farmer record.
     */
            //working fine_testing purpose dummy otp
            // public function sendOtp(Request $request)
            // {
            //     $validator = Validator::make($request->all(), [
            //         'mobile' => 'required|digits:10',
            //     ]);

            //     if ($validator->fails()) {
            //         return response()->json(['message' => 'Invalid mobile number'], 422);
            //     }

            //     $mobile = $request->mobile;

            //     // ✅ create farmer if not exists
            //     $farmer = Farmer::firstOrCreate(
            //         ['mobile' => $mobile],
            //         ['role' => 'farmer']
            //     );

            //     // ✅ Generate 6-digit OTP
            //     $otp = rand(100000, 999999);

            //     // ✅ Save OTP in DB
            //     UserOtp::create([
            //         'farmer_id'  => $farmer->id,
            //         'otp_code'   => $otp,
            //         'expires_at' => Carbon::now()->addMinutes(5),
            //         'verified'   => false,
            //     ]);

            //     return response()->json([
            //         'message'   => 'OTP sent successfully',
            //         'mobile'    => $mobile,
            //         'otp_debug' => $otp // ❌ remove in production
            //     ]);
            // }
    public function sendOtp(Request $request)
{
    $validator = Validator::make($request->all(), [
        'mobile' => 'required|digits:10',
    ]);

    if ($validator->fails()) {
        return response()->json(['message' => 'Invalid mobile number'], 422);
    }

    $mobile = $request->mobile;

    // ✅ create farmer if not exists
    $farmer = Farmer::firstOrCreate(
        ['mobile' => $mobile],
        ['role' => 'farmer']
    );

    // ✅ 1) Check for recent unverified OTP (last 2 minutes)
    // If exists, allow user to proceed to OTP screen (they can use the same OTP)
    $recentOtp = UserOtp::where('farmer_id', $farmer->id)
        ->where('verified', false)
        ->where('expires_at', '>', Carbon::now())              // OTP still valid
        ->where('created_at', '>', Carbon::now()->subMinutes(2)) // requested in last 2 mins
        ->first();

    if ($recentOtp) {
        // Return success so user can proceed to OTP screen
        // They can use the OTP they already received via SMS
        return response()->json([
            'message' => 'OTP already sent. Please check your SMS.',
            'mobile'  => $mobile,
        ]);
    }

    // Invalidate all old unverified OTPs for this farmer before creating new one
    UserOtp::where('farmer_id', $farmer->id)
        ->where('verified', false)
        ->update(['verified' => true]);

    // ✅ 2) Generate 6-digit OTP (test bypass for 9999999999)
    $otp = ($mobile === '9999999999') ? 123456 : rand(100000, 999999);

    // ✅ 3) Save OTP in DB
    $userOtp = UserOtp::create([
        'farmer_id'  => $farmer->id,
        'otp_code'   => $otp,
        'expires_at' => Carbon::now()->addMinutes(5),
        'verified'   => false,
    ]);

    // ✅ 4) Skip SMS for test number, send via Fast2SMS for others
    if ($mobile !== '9999999999') {
        $smsMessage = $otp;
        $smsResponse = SmsHelper::sendSms($mobile, $smsMessage);

        $success = is_array($smsResponse) ? ($smsResponse['return'] ?? false) : false;

        if (!$success) {
            return response()->json([
                'message'  => 'Failed to send OTP. Please try again.',
            ], 500);
        }
    }

    return response()->json([
        'message'   => 'OTP sent successfully',
        'mobile'    => $mobile,
    ]);
}






    /**
     * ======================
     * Verify OTP
     * ======================
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile'   => 'required|digits:10',
            'otp_code' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid input'], 422);
        }

        $farmer = Farmer::where('mobile', $request->mobile)->first();

        if (!$farmer) {
            return response()->json(['message' => 'Mobile number not found'], 404);
        }

        $otpRecord = UserOtp::where('farmer_id', $farmer->id)
            ->where('otp_code', $request->otp_code)
            ->where('expires_at', '>=', Carbon::now())
            ->where('verified', false)
            ->latest()
            ->first();

        if (!$otpRecord) {
            return response()->json(['message' => 'Incorrect or expired OTP. Please try again.'], 401);
        }

        // ✅ Mark OTP as verified
        $otpRecord->update(['verified' => true]);

        // ✅ Single-device login policy:
        //   - If farmer has existing tokens, revoke them so only this device
        //     stays authenticated.
        //   - Send a force-logout FCM to the OLD device ONLY when the
        //     incoming fcm_token differs from the stored one. Otherwise the
        //     same device re-logging in would receive its own force_logout
        //     and immediately bounce back to login.
        if ($farmer->tokens()->exists()) {
            $newFcmToken = $request->input('fcm_token');
            $isSameDevice = !empty($newFcmToken)
                && !empty($farmer->fcm_token)
                && $newFcmToken === $farmer->fcm_token;

            if (!$isSameDevice && !empty($farmer->fcm_token)) {
                try {
                    // Data-only, high-priority so the OLD device acts on it even
                    // when backgrounded or terminated (a notification-payload
                    // message would just sit in the tray until tapped).
                    app(FirebaseNotificationService::class)->sendDataToDevice(
                        $farmer->fcm_token,
                        ['type' => 'force_logout', 'role' => 'farmer']
                    );
                } catch (\Throwable $e) {
                    \Log::warning('Force-logout FCM failed', ['err' => $e->getMessage()]);
                }
            }

            $farmer->tokens()->delete();
        }

        // ✅ Generate Sanctum token (only this device has a valid token now)
        $token = $farmer->createToken('user_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'mobile'  => $farmer->mobile,
            'token'   => $token,
        ]);
    }


    /**
 * ======================
 * Resend OTP
 * ======================
 * Generates a new 6-digit OTP for an existing farmer.
 * Does NOT generate a token.
 */
        //working fine_testing purpose dummy otp
        // public function resendOtp(Request $request)
        // {
        //     // Validate mobile number
        //     $validator = Validator::make($request->all(), [
        //         'mobile' => 'required|digits:10',
        //     ]);

        //     if ($validator->fails()) {
        //         return response()->json([
        //             'message' => 'Invalid mobile number',
        //             'errors'  => $validator->errors()
        //         ], 422);
        //     }

        //     // Find the farmer by mobile
        //     $farmer = Farmer::where('mobile', $request->mobile)->first();

        //     if (!$farmer) {
        //         return response()->json(['message' => 'Mobile number not found'], 404);
        //     }

        //     // Generate new 6-digit OTP
        //     $otp = rand(100000, 999999);

        //     // Save OTP in DB
        //     UserOtp::create([
        //         'farmer_id'  => $farmer->id,
        //         'otp_code'   => $otp,
        //         'expires_at' => Carbon::now()->addMinutes(5),
        //         'verified'   => false,
        //     ]);

        //     return response()->json([
        //         'message'   => 'New OTP sent successfully',
        //         'mobile'    => $farmer->mobile,
        //         'otp_debug' => $otp // ❌ Remove in production
        //     ]);
        // }

   public function resendOtp(Request $request)
{
    $validator = Validator::make($request->all(), [
        'mobile' => 'required|digits:10',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Invalid mobile number',
            'errors'  => $validator->errors()
        ], 422);
    }

    $farmer = Farmer::where('mobile', $request->mobile)->first();

    if (!$farmer) {
        return response()->json(['message' => 'Mobile number not found'], 404);
    }

    // ✅ Rate limit for resend (same 2-minute rule)
    $recentOtp = UserOtp::where('farmer_id', $farmer->id)
        ->where('verified', false)
        ->where('expires_at', '>', Carbon::now())
        ->where('created_at', '>', Carbon::now()->subMinutes(2))
        ->first();

    if ($recentOtp) {
        $secondsRemaining = Carbon::now()->diffInSeconds($recentOtp->created_at->addMinutes(2));
        return response()->json([
            'message' => 'Please wait ' . $secondsRemaining . ' seconds before requesting a new OTP.',
            'wait_seconds' => $secondsRemaining,
        ], 429);
    }

    // Invalidate old unverified OTPs before creating new one
    UserOtp::where('farmer_id', $farmer->id)
        ->where('verified', false)
        ->update(['verified' => true]);

    // ✅ Generate new 6-digit OTP (test bypass for 9999999999)
    $otp = ($farmer->mobile === '9999999999') ? 123456 : rand(100000, 999999);

    // ✅ Save OTP in DB
    $userOtp = UserOtp::create([
        'farmer_id'  => $farmer->id,
        'otp_code'   => $otp,
        'expires_at' => Carbon::now()->addMinutes(5),
        'verified'   => false,
    ]);

    // ✅ Skip SMS for test number, send via Fast2SMS for others
    if ($farmer->mobile !== '9999999999') {
        $smsMessage = $otp;
        $smsResponse = SmsHelper::sendSms($farmer->mobile, $smsMessage);
        $success     = is_array($smsResponse) ? ($smsResponse['return'] ?? false) : false;

        if (!$success) {
            return response()->json([
                'message' => 'Failed to send OTP. Please try again.',
            ], 500);
        }
    }

    return response()->json([
        'message'   => 'New OTP sent successfully',
        'mobile'    => $farmer->mobile,
    ]);
}




    /**
     * ======================
     * Logout
     * ======================
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->tokens()->delete();
            return response()->json(['message' => 'Logged out successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Logout failed',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ======================
     * Profile
     * ======================
     */
    public function profile(Request $request)
    {
        try {
            //dd($request->user());
            return response()->json($request->user());
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Fetching profile failed',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

  /**
 * ======================
 * Update Profile
 * ======================
 */
/**
     * ======================
     * Register FCM Token
     * ======================
     */
    public function registerFcmToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string|max:500',
            'device_type' => 'nullable|string|in:android,ios',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid input'], 422);
        }

        $user = $request->user();
        $user->fcm_token = $request->fcm_token;
        $user->save();

        return response()->json(['message' => 'FCM token registered successfully']);
    }

    /**
     * ======================
     * Update Profile
     * ======================
     */
    public function updateProfile(Request $request)
    {   //dd('update profile');
    $user = $request->user(); // Logged-in farmer
    //dd($user); //fine

    $validator = Validator::make($request->all(), [
        'first_name'    => 'nullable|string|max:50',
        'last_name'     => 'nullable|string|max:50',
        'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        'language'      => 'nullable|string|in:en,te,hi,ta,kn,ml,mr,gu,pa,bn,or,ur',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Invalid input',
            'errors'  => $validator->errors()
        ], 422);
    }

    // Update first name and last name
    if ($request->filled('first_name')) {
        $user->first_name = trim($request->first_name);
    }
    if ($request->filled('last_name')) {
        $user->last_name = trim($request->last_name);
    }

    // Update language if provided
    if ($request->filled('language')) {
        $user->language = $request->language;
    }

    // Update profile image if uploaded
    if ($request->hasFile('profile_image')) {
        // Delete old image from public/farmer_profiles if exists
        if ($user->profile_image) {
            $oldFilename = basename(parse_url($user->profile_image, PHP_URL_PATH));
            $oldPath = public_path('farmer_profiles/' . $oldFilename);
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        // Store new image in public/farmer_profiles
        $filename = time() . '_' . $request->file('profile_image')->getClientOriginalName();
        $request->file('profile_image')->move(public_path('farmer_profiles'), $filename);
        $user->profile_image = asset('farmer_profiles/' . $filename);
    }

    $user->save();

    return response()->json([
        'message' => 'Profile updated successfully',
        'user'    => [
            'first_name'    => $user->first_name,
            'last_name'     => $user->last_name,
            'mobile'        => $user->mobile,
            'profile_image' => $user->profile_image,
            'language'      => $user->language ?? 'en',
        ]
    ]);
}



}
