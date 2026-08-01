<?php

namespace App\Http\Controllers\Api\Vendors_apis;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

use Illuminate\Validation\ValidationException;

class VendorAuthController extends Controller
{

    // ravindra
// public function login(Request $request)
// {

    //     try {

    //         $request->validate([
//             'best_seeds_id' => 'required|string|exists:vendors,best_seeds_id',
//             'password'      => 'required|string',
//         ]);

    // $login_vendor=$request->best_seeds_id;
// // dd($login_vendor);
//         $vendor = Vendor::where('best_seeds_id', $login_vendor)
//                         ->where('role', 'hatchery')
//                         ->first();

    //         // dd($vendor);

    //         if (!$vendor) {
//             return response()->json(['message' => 'Invalid1 hatchery credentials'], 401);
//         }

    //         if (Hash::check($request->password, $vendor->password)) {
//             $token = $vendor->createToken('api_token')->plainTextToken;

    //             return response()->json([
//                 'message' => 'Hatchery login successful',
//                 'token'   => $token,
//                 'vendor'  => $vendor,
//             ]);
//         }

    //         if ($vendor->is_first_login == 0) {
//             $tempPassword = Crypt::decryptString($vendor->temp_password_encrypted);



    //             if ($request->password === $tempPassword) {
//                 return response()->json([
//                     'message' => 'First login — please set a new password.',
//                     'require_password_reset' => true,
//                     'vendor_id' => $vendor->id,
//                 ], 200);
//             }
//         }

    //         return response()->json(['message' => 'Invalid hatchery credentials'], 401);

    //     } catch (ValidationException $e) {
//         return response()->json(['errors' => $e->errors()], 422);

    //     } catch (\Exception $e) {
//         \Log::error('Hatchery Login failed: ' . $e->getMessage(), [
//             'best_seeds_id' => $request->best_seeds_id
//         ]);
//         return response()->json(['message' => 'Login failed'], 500);
//     }
// }
    public function login(Request $request)
    {
        try {
            // Validate input
            $request->validate([
                'best_seeds_id' => 'required|string|exists:vendors,best_seeds_id',
                'password' => 'required|string',
            ]);

            $login_vendor = $request->best_seeds_id;

            // Find vendor with the given best_seeds_id and role hatchery
            $vendor = Vendor::where('best_seeds_id', $login_vendor)
                ->where('role', 'hatchery')
                ->first();

            if (!$vendor) {
                return response()->json(['message' => 'Invalid Id and Password'], 401);
            }

            // Block login ONLY if admin manually deactivated AFTER first login
            if ($vendor->status == 0 && !$vendor->is_first_login) {
                return response()->json([
                    'message' => 'Your account is inactive. Please contact admin.'
                ], 403);
            }

            if (Hash::check($request->password, $vendor->password)) {

                // Check if this is a first login with temporary password
                if ($vendor->is_first_login) {
                    return response()->json([
                        'message' => 'First login — please set a new password.',
                        'require_password_reset' => true,
                        'vendor_id' => $vendor->id,
                    ], 200);
                }

                // Single-device login policy: block if already logged in on another device.
                if ($vendor->tokens()->exists()) {
                    return response()->json([
                        'status'     => false,
                        'error_code' => 'EMPLOYEE_ALREADY_LOGGED_IN',
                        'message'    => 'You are already logged in on another device. Please contact admin to force logout.',
                    ], 409);
                }

                $token = $vendor->createToken('api_token')->plainTextToken;

                return response()->json([
                    'message' => 'Bestseed Employee login successful',
                    'token' => $token,
                    'vendor' => [
                        'id' => $vendor->id,
                        'name' => $vendor->name,
                        'best_seeds_id' => $vendor->best_seeds_id,
                        'mobile' => $vendor->mobile,
                        'alternate_mobile' => $vendor->alternate_mobile,
                        'address' => $vendor->address,
                        'pincode' => $vendor->pincode,
                        'profile_image' => $vendor->profile_image,
                        'is_profile_complete' => $vendor->is_profile_complete,
                    ]
                ]);
            }





            // If this is the first login, check against the temporary password
            if ($vendor->is_first_login) {
                try {
                    $tempPassword = Crypt::decryptString($vendor->temp_password_encrypted);

                    if ($request->password === $tempPassword) {
                        return response()->json([
                            'message' => 'First login — please set a new password.',
                            'require_password_reset' => true,
                            'vendor_id' => $vendor->id,
                        ], 200);
                    }
                } catch (DecryptException $e) {
                    \Log::error("Failed to decrypt temp password for Vendor ID {$vendor->id}: " . $e->getMessage());
                    return response()->json(['message' => 'Login failed due to internal error'], 500);
                }
            }

            // If none of the conditions met, invalid credentials
            return response()->json(['message' => 'Invalid Id and Password'], 401);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);

        } catch (\Exception $e) {
            \Log::error('Hatchery Login failed: ' . $e->getMessage(), [
                'best_seeds_id' => $request->best_seeds_id,
            ]);
            return response()->json(['message' => 'Login failed'], 500);
        }
    }

    public function setnewpassword(Request $request)
    {
        $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        try {
            $vendor = Vendor::findOrFail($request->vendor_id);

            if (!$vendor->is_first_login) {
                return response()->json(['message' => 'Password already updated.'], 400);
            }

            // $vendor->password = Hash::make($request->new_password);
            $vendor->password = $request->new_password;
            $vendor->is_first_login = false;
            $vendor->status = 1;
            $vendor->temp_password_encrypted = null;
            $vendor->save();

            return response()->json(['message' => 'Password updated successfully. You can now log in.']);

        } catch (\Exception $e) {
            \Log::error('Error updating password: ' . $e->getMessage());
            return response()->json(['message' => 'Password update failed.'], 500);
        }
    }


    // public function setnewpassword(Request $request)
// {
//     $request->validate([
//         'vendor_id' => 'required|exists:vendors,id',
//         'new_password' => 'required|string|min:8|confirmed',
//     ]);

    //     try {
//         $vendor = Vendor::findOrFail($request->vendor_id);


    //         if (!$vendor->is_first_login) {
//             return response()->json(['message' => 'Password already updated.'], 400);
//         }


    //         $vendor->password = Hash::make($request->new_password);
//         $vendor->is_first_login = false;
//         $vendor->temp_password_encrypted = null;
//         $vendor->save();

    //         return response()->json(['message' => 'Password updated successfully. You can now log in.']);

    //     } catch (\Exception $e) {
//         \Log::error('Error updating password: ' . $e->getMessage());
//         return response()->json(['message' => 'Password update failed.'], 500);
//     }
// }



    // public function login(Request $request)
    // {
    //     try {
    //         $request->validate([
    //             'best_seeds_id' => 'required|exists:vendors,best_seeds_id', // ✅ changed to vendors table
    //             'password'      => 'required',
    //         ]);

    //         $vendor = Vendor::where('best_seeds_id', $request->best_seeds_id) // ✅ using Vendor model
    //                     ->where('role', 'hatchery')
    //                     ->first();

    //         if (!$vendor || !Hash::check($request->password, $vendor->password)) {
    //             return response()->json(['message' => 'Invalid hatchery credentials'], 401);
    //         }

    //         $token = $vendor->createToken('api_token')->plainTextToken;

    //         return response()->json([
    //             'message' => 'Hatchery login successful',
    //             'token'   => $token,
    //             'vendor'  => $vendor   // ✅ renamed
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => 'Login failed',
    //             'error'   => $e->getMessage(),
    //             'line'    => $e->getLine(),
    //             'file'    => $e->getFile()
    //         ], 500);
    //     }
    // }

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
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
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
            $vendor = $request->user();
            // $vendor->profile_image = $vendor->profile_image
            //     ? asset('storage/'.$vendor->profile_image)
            //     : null;

            // return response()->json($vendor);

            return response()->json([
                'id' => $vendor->id,
                'name' => $vendor->name,
                'mobile' => $vendor->mobile,
                'alternate_mobile' => $vendor->alternate_mobile,
                'address' => $vendor->address,
                'pincode' => $vendor->pincode,
                'profile_image' => $vendor->profile_image,
                'is_profile_complete' => $vendor->is_profile_complete,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Fetching profile failed',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }


    // 🔹 Update Personal Information
    //

    public function updateProfile(Request $request)
    {
        try {
            $vendor = $request->user();

            // 🔹 Reusable closure for mobile number uniqueness check
            $uniqueMobile = function ($attribute, $value, $fail) use ($vendor) {
                $exists = \App\Models\Vendor::where('id', '!=', $vendor->id)
                    ->where('alternate_mobile', $value)
                    ->exists();

                if ($exists) {
                    $fail("The {$attribute} is already taken");
                }
            };

            $request->validate([
                'name' => 'nullable|string|max:255',
                'alternate_mobile' => ['nullable', 'string', 'regex:/^\+[1-9]\d{1,14}$/', $uniqueMobile],
                'address' => 'nullable|string|max:500',
                'pincode' => 'nullable|string|max:10',
                'profile_image' => 'nullable|image|max:2048',
            ]);

            // 📸 Upload image directly to public/vendor_profiles
            if ($request->hasFile('profile_image')) {
                $file = $request->file('profile_image');

                $filename = date('dmY') . '_' .
                    preg_replace('/\s+/', '_', strtolower($vendor->name)) .
                    '_' . uniqid() . '.' .
                    $file->getClientOriginalExtension();

                $destinationPath = public_path('vendor_profiles');

                // create folder if not exists
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }

                $file->move($destinationPath, $filename);

                // Save relative path
                $vendor->profile_image = 'vendor_profiles/' . $filename;
            }

            $vendor->name = $request->name;
            $vendor->alternate_mobile = $request->alternate_mobile;
            $vendor->address = $request->address;
            $vendor->pincode = $request->pincode;

            $vendor->save();

            return response()->json([
                'message' => 'Profile updated successfully',
                'vendor' => [
                    'id' => $vendor->id,
                    'name' => $vendor->name,
                    'mobile' => $vendor->mobile,
                    'best_seeds_id' => $vendor->best_seeds_id,
                    'alternate_mobile' => $vendor->alternate_mobile,
                    'address' => $vendor->address,
                    'pincode' => $vendor->pincode,
                    'profile_image' => $vendor->profile_image,
                    'profile_image_url' => $vendor->profile_image
                        ? url($vendor->profile_image)
                        : null,
                    'is_profile_complete' => $vendor->is_profile_complete,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Profile update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ======================
     * Update Vendor/Employee Location
     * ======================
     */
    public function updateLocation(Request $request)
    {
        try {
            $vendor = $request->user();

            $request->validate([
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
                'address' => 'nullable|string|max:255',
            ]);

            $vendor->current_latitude = $request->latitude;
            $vendor->current_longitude = $request->longitude;
            $vendor->current_location_address = $request->address;
            $vendor->save();

            return response()->json([
                'message' => 'Location updated successfully',
                'location' => [
                    'latitude' => $vendor->current_latitude,
                    'longitude' => $vendor->current_longitude,
                    'address' => $vendor->current_location_address,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Location update failed',
                'error' => $e->getMessage()
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

        $vendor = $request->user();
        $vendor->fcm_token = $request->fcm_token;
        $vendor->save();

        return response()->json([
            'status' => true,
            'message' => 'FCM token registered successfully',
        ]);
    }
}
