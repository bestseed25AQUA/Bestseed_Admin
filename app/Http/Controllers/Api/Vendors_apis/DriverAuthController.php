<?php

namespace App\Http\Controllers\Api\Vendors_apis;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\DriverOtp;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Helpers\SmsHelper;

class DriverAuthController extends Controller
{
    /**
     * ======================
     * Login (alias for sendOtp)
     * ======================
     */
    public function login(Request $request)
    {
        return $this->sendOtp($request);
    }

    /**
     * ======================
     * Send OTP (Login)
     * ======================
     */
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|digits:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid mobile number'], 422);
        }

        $mobile = $request->mobile;

        // // ✅ Driver must already exist
        // $driver = Driver::where('mobile', $mobile)->first();

        // if (!$driver) {
        //     return response()->json(['message' => 'Driver not found'], 404);
        // }

        // if ($driver->status == 0) {
        //     return response()->json(['message' => 'Driver account inactive'], 403);
        // }

        // ✅ NEW CODE (SAME AS USER FLOW)
            $driver = Driver::firstOrCreate(
                ['mobile' => $mobile],
                [
                    'status' => 1,   // auto active
                    'name'   => null // can update later
                ]
            );


        // ✅ RATE LIMIT – if a previous OTP is still unexpired, reuse it.
        // OTP validity matches the resend cooldown, so as soon as it expires
        // the driver can request a fresh OTP immediately.
        $recentOtp = DriverOtp::where('driver_id', $driver->id)
            ->where('verified', false)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if ($recentOtp) {
            // Return success so user can navigate to OTP screen with existing OTP
            return response()->json([
                'message' => 'OTP already sent. Please check your SMS.',
                'mobile' => $mobile,
                'existing_otp' => true,
            ]);
        }

        // ✅ Generate 6-digit OTP (test bypass for 9999999999)
        $otp = ($mobile === '9999999999') ? 123456 : rand(100000, 999999);

        // ✅ Save OTP — valid for 2 minutes
        $driverOtp = DriverOtp::create([
            'driver_id'  => $driver->id,
            'otp_code'   => $otp,
            'expires_at' => Carbon::now()->addMinutes(2),
            'verified'   => false,
        ]);

        // ✅ Skip SMS for test number, send via Fast2SMS for others
        if ($mobile !== '9999999999') {      
            $smsMessage = $otp;
            $smsResponse = SmsHelper::sendSms($mobile, $smsMessage, 221703); // driver login template (SMS Retriever hash Cpa0qamvt0R — Play App Signing)

            $success = is_array($smsResponse) ? ($smsResponse['return'] ?? false) : false;

            if (!$success) {
                return response()->json([
                    'message' => 'Failed to send OTP. Please enter proper mobile number.',
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

        $driver = Driver::where('mobile', $request->mobile)->first();

        if (!$driver) {
            return response()->json(['message' => 'Driver not found'], 404);
        }

        $otpRecord = DriverOtp::where('driver_id', $driver->id)
            ->where('otp_code', $request->otp_code)
            ->where('expires_at', '>=', Carbon::now())
            ->where('verified', false)
            ->latest()
            ->first();

        if (!$otpRecord) {
            // 422 (not 401) — the client treats 401 as "token revoked, kick
            // back to login", which would defeat the OTP-retry UX.
            return response()->json([
                'message' => 'Incorrect or expired OTP. Please try again.'
            ], 422);
        }

        // ✅ Mark OTP verified
        $otpRecord->update(['verified' => true]);

        // ✅ Single-device login policy:
        //   - If driver has NO existing tokens → first/clean login, allow.
        //   - If driver has existing tokens AND is currently in journey
        //     (any booking with status = 4 "In Journey") → REJECT new login,
        //     so the active device on the road keeps streaming GPS without interruption.
        //   - If driver has existing tokens but is NOT in journey → revoke
        //     old tokens (auto-logout old device) and allow the new device.
        $hasExistingTokens = $driver->tokens()->exists();

        if ($hasExistingTokens) {
            $isInJourney = Booking::where('driver_id', $driver->id)
                ->where('status', 4)
                ->exists();

            if ($isInJourney) {
                // Re-mark OTP unverified so the user can retry from the same OTP
                // after they finish the journey on the old device.
                $otpRecord->update(['verified' => false]);

                return response()->json([
                    'status'     => false,
                    'error_code' => 'DRIVER_IN_JOURNEY',
                    'message'    => 'You are currently in an active journey on another device. Please complete the journey before logging in here.',
                ], 409);
            }

            // Not in journey — push a force-logout FCM data message to the
            // old device first so it can sign itself out instantly (foreground
            // or background), then revoke the old tokens. The old fcm_token
            // is still on the driver record at this point because the new
            // device hasn't called registerFcmToken yet.
            if (!empty($driver->fcm_token)) {
                try {
                    app(FirebaseNotificationService::class)->sendToDevice(
                        $driver->fcm_token,
                        'Signed out',
                        'Your account was logged in on another device.',
                        null,
                        ['type' => 'force_logout', 'role' => 'driver']
                    );
                } catch (\Throwable $e) {
                    \Log::warning('Force-logout FCM failed', ['err' => $e->getMessage()]);
                }
            }

            $driver->tokens()->delete();
        }

        // ✅ Generate Sanctum token (only this device has a valid token now)
        $token = $driver->createToken('driver_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'mobile'  => $driver->mobile,
            'token'   => $token,
        ]);
    }

    /**
     * ======================
     * Resend OTP
     * ======================
     */
    public function resendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|digits:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid mobile number'], 422);
        }

        // $driver = Driver::where('mobile', $request->mobile)->first();

        // if (!$driver) {
        //     return response()->json(['message' => 'Driver not found'], 404);
        // }

        // if ($driver->status == 0) {
        //     return response()->json(['message' => 'Driver account inactive'], 403);
        // }

        $driver = Driver::firstOrCreate(
            ['mobile' => $request->mobile],
            [
                'status' => 1,
                'name'   => null
            ]
        );


        // No backend rate limit — the driver app's resend button is gated by
        // its own 2-min countdown, so the user can only trigger this once per
        // window anyway. Invalidate any previous unverified OTP for this
        // driver so only the freshly-issued code is valid.
        DriverOtp::where('driver_id', $driver->id)
            ->where('verified', false)
            ->update(['expires_at' => Carbon::now()]);

        // ✅ Generate OTP (test bypass for 9999999999)
        $otp = ($driver->mobile === '9999999999') ? 123456 : rand(100000, 999999);

        DriverOtp::create([
            'driver_id'  => $driver->id,
            'otp_code'   => $otp,
            'expires_at' => Carbon::now()->addMinutes(2),
            'verified'   => false,
        ]);

        // ✅ Skip SMS for test number
        if ($driver->mobile !== '9999999999') {
            $smsResponse = SmsHelper::sendSms($driver->mobile, $otp, 221703); // driver login template (SMS Retriever hash Cpa0qamvt0R — Play App Signing)
            $success = is_array($smsResponse) ? ($smsResponse['return'] ?? false) : false;

            if (!$success) {
                return response()->json([
                    'message' => 'Failed to send OTP. Please try again.',
                ], 500);
            }
        }

        return response()->json([
            'message'   => 'New OTP sent successfully',
            'mobile'    => $driver->mobile,
        ]);
    }

    /**
     * ======================
     * Logout
     * ======================
     */
    public function logout(Request $request)
    {
        $driver = $request->user();
        $driver->fcm_token = null;
        $driver->save();
        $driver->tokens()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * ======================
     * Profile
     * ======================
     */
    public function profile(Request $request)
    {
        return response()->json($request->user());
    }
    /**
 * ======================
 * Update Driver Profile
 * ======================
 */
public function updateProfile(Request $request)
{
    $driver = $request->user(); // logged-in driver

    $validator = Validator::make($request->all(), [
        'name' => 'nullable|string|max:100',
        'profile_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Invalid input',
            'errors' => $validator->errors()
        ], 422);
    }

    // Update name
    if ($request->filled('name')) {
        $driver->name = trim($request->name);
    }

    // Update profile image
    if ($request->hasFile('profile_image')) {
        $filename = time().'_'.$request->file('profile_image')->getClientOriginalName();
        $request->file('profile_image')->move(public_path('driver_profiles'), $filename);
        $driver->profile_image = asset('driver_profiles/'.$filename);
    }

    $driver->save();

    return response()->json([
        'message' => 'Profile updated successfully',
        'driver' => [
            'name' => $driver->name,
            'mobile' => $driver->mobile,
            'profile_image' => $driver->profile_image
        ]
    ]);
}

    /**
     * ======================
     * Update Driver Location
     * ======================
     */
    public function updateLocation(Request $request)
    {
        $driver = $request->user();

        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'address' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid input',
                'errors' => $validator->errors()
            ], 422);
        }

        $driver->current_latitude = $request->latitude;
        $driver->current_longitude = $request->longitude;
        $driver->current_location_address = $request->address;
        $driver->save();

        return response()->json([
            'message' => 'Location updated successfully',
            'location' => [
                'latitude' => $driver->current_latitude,
                'longitude' => $driver->current_longitude,
                'address' => $driver->current_location_address,
            ]
        ]);
    }

    /**
     * ==========================================
     * Update Driver Device Permission Status
     * ==========================================
     * Called from the driver app's Profile → Permissions toggles so the admin
     * can see, per driver, whether Location and Battery-optimization (background)
     * are enabled on the device. Either field may be sent independently.
     */
    public function updatePermissions(Request $request)
    {
        $driver = $request->user();

        if (!$driver) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'location_permission' => 'sometimes|boolean',
            'battery_optimization_disabled' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid input',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            if ($request->has('location_permission')) {
                $driver->location_permission = $request->boolean('location_permission');
            }
            if ($request->has('battery_optimization_disabled')) {
                $driver->battery_optimization_disabled = $request->boolean('battery_optimization_disabled');
            }
            $driver->permissions_updated_at = now();
            $driver->save();

            return response()->json([
                'status' => true,
                'message' => 'Permission status updated',
                'permissions' => [
                    'location_permission' => (bool) $driver->location_permission,
                    'battery_optimization_disabled' => (bool) $driver->battery_optimization_disabled,
                    'permissions_updated_at' => optional($driver->permissions_updated_at)->toDateTimeString(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update permission status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Register FCM token for push notifications
     */
    public function registerFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string|max:500',
        ]);

        $driver = $request->user();
        $driver->fcm_token = $request->fcm_token;
        $driver->save();

        return response()->json([
            'status' => true,
            'message' => 'FCM token registered successfully',
        ]);
    }
}
