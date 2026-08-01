<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\Farmer;
use App\Models\UserOtp;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

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
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile' => [
                'required',
                'digits:10',
                'regex:/^(?!0{10})[0-9]{10}$/'
            ],
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

        // ✅ Generate 6-digit OTP
        $otp = rand(100000, 999999);

        // ✅ Save OTP in DB
        UserOtp::create([
            'farmer_id' => $farmer->id,
            'otp_code' => $otp,
            'expires_at' => Carbon::now()->addMinutes(5),
            'verified' => false,
        ]);

        return response()->json([
            'message' => 'OTP sent successfully',
            'mobile' => $mobile,
            'otp_debug' => $otp // ❌ remove in production
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
            'mobile' => 'required|digits:10',
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

        // ✅ Generate Sanctum token
        $token = $farmer->createToken('user_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'mobile' => $farmer->mobile,
            'token' => $token,
        ]);
    }

    /**
     * ======================
     * Resend OTP
     * ======================
     * Generates a new 6-digit OTP for an existing farmer.
     * Does NOT generate a token.
     */
    public function resendOtp(Request $request)
    {
        // Validate mobile number
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|digits:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid mobile number',
                'errors' => $validator->errors()
            ], 422);
        }

        // Find the farmer by mobile
        $farmer = Farmer::where('mobile', $request->mobile)->first();

        if (!$farmer) {
            return response()->json(['message' => 'Mobile number not found'], 404);
        }

        // Generate new 6-digit OTP
        $otp = rand(100000, 999999);

        // Save OTP in DB
        UserOtp::create([
            'farmer_id' => $farmer->id,
            'otp_code' => $otp,
            'expires_at' => Carbon::now()->addMinutes(5),
            'verified' => false,
        ]);

        return response()->json([
            'message' => 'New OTP sent successfully',
            'mobile' => $farmer->mobile,
            'otp_debug' => $otp // ❌ Remove in production
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
            $request->Farmer()->tokens()->delete();
            return response()->json(['message' => 'Logged out successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Logout failed',
                'error' => $e->getMessage()
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
            return response()->json($request->user());
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Fetching profile failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * ======================
     * Update Profile
     * ======================
     */
    public function updateProfile(Request $request)
    {
        $user = $request->Farmer(); // Logged-in farmer

        $validator = Validator::make($request->all(), [
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'language' => 'nullable|string|in:en,te,hi,ta,kn,ml,mr,gu,pa,bn,or,ur', // optional
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid input',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update first name and last name
        if ($request->has('first_name')) {
            $user->first_name = trim($request->first_name);
        }
        if ($request->has('last_name')) {
            $user->last_name = trim($request->last_name);
        }

        // Update language if provided
        if ($request->has('language')) {
            $user->language = $request->language;
        }

        // Update profile image if uploaded
        if ($request->hasFile('profile_image')) {
            // Delete old image if exists
            if ($user->profile_image) {
                $oldPath = str_replace(url('/storage/'), '', $user->profile_image);
                Storage::disk('public')->delete($oldPath);
            }

            // Store new image
            // $path = $request->file('profile_image')->store('farmer_profiles', 'public');
            // $user->profile_image = url('storage/' . $path);

            $filename = time() . '_' . $request->file('profile_image')->getClientOriginalName();
            $request->file('profile_image')->move(public_path('farmer_profiles'), $filename);
            $user->profile_image = url('farmer_profiles/' . $filename);

        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'mobile' => $user->mobile,
                'profile_image' => $user->profile_image,
                'language' => $user->language ?? 'en',
            ]
        ]);
    }



}
