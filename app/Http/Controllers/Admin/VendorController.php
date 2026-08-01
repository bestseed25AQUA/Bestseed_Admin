<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\Request;
use App\Models\Vendor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;



use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Contracts\Encryption\DecryptException; // Import for specific decryption error


class VendorController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:vendors.view')->only(['index', 'show', 'getCredentials']);
        $this->middleware('permission:vendors.create')->only(['create', 'store']);
        $this->middleware('permission:vendors.update')->only(['edit', 'update', 'activate', 'resetPassword', 'checkHatcheries', 'deleteProfileImage', 'forceLogout']);
        $this->middleware('permission:vendors.delete')->only(['destroy']);
    }

    public function index(){
        //dd('vendor create view');
        $vendors = Vendor::with('hatcheries.category', 'hatcheries.location')->orderBy('created_at', 'desc')->get();
        return view('admin.vendor.index', compact('vendors'));
    }
     protected function generateUniqueBestSeedId(): string
    {
        // Your existing logic for generating BSXXXX ID
        $lastVendor = Vendor::orderBy('id', 'desc')->first();
        $nextId = $lastVendor ? (int)substr($lastVendor->best_seeds_id, 2) + 1 : 1;
        return 'BS' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
    }

    public function store(Request $request)
    {

        $validated = $request->validate([
            'user_name'   => 'required|string|max:255',
            'user_mobile' => 'required|string|max:20|unique:vendors,mobile',
        ]);

        try {
            $bestSeedId = $this->generateUniqueBestSeedId();
            $tempPassword = Str::random(10);

            $vendor = Vendor::create([
                'name'                    => $validated['user_name'],
                'mobile'                  => $validated['user_mobile'],
                'password'                => $tempPassword, // Model mutator handles hashing
                'temp_password_encrypted' => Crypt::encryptString($tempPassword),
                'best_seeds_id'           => $bestSeedId,
                'role'                    => 'hatchery',
                'status'                  => 0,
                'is_first_login'          => true,
            ]);

            return redirect()
                ->route('vendors.index')
                ->with('success', "Hatchery Vendor '{$vendor->name}' created successfully. ID: **{$bestSeedId}**.")
                ->with('temp_password', $tempPassword);

        } catch (\Exception $e) {
            Log::error('Error creating vendor: ' . $e->getMessage());
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Vendor creation failed.');
        }
    }

    public function getCredentials($id)
    {
        $tempPassword = null;

        try {

            $vendor = Vendor::findOrFail($id);


            if ($vendor->temp_password_encrypted) {
                try {
                    $tempPassword = Crypt::decryptString($vendor->temp_password_encrypted);
                } catch (DecryptException $e) {
                    Log::warning("Decryption failed for Vendor ID {$id}. APP_KEY issue suspected.");
                    $tempPassword = 'Decryption Failed: Check Logs';
                }
            }

            return response()->json([
                'success'         => true,
                'best_seeds_id'   => $vendor->best_seeds_id,
                'temp_password'   => $tempPassword,
                'mobile'          => $vendor->mobile,
                'name'            => $vendor->name
            ]);

        } catch (ModelNotFoundException $e) {
            // Vendor not found (404)
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found.'
            ], 404);

        } catch (\Exception $e) {
            // Catch all other unexpected errors (500)
            Log::error('Error retrieving vendor credentials: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve vendor credentials.'
            ], 500);
        }
    }



// public function getCredentials($id)
// {
//     try {
//         $vendor = Vendor::findOrFail($id);

//         $tempPassword = null;
//         if ($vendor->temp_password_encrypted) {
//             try {
//                 $tempPassword = Crypt::decryptString($vendor->temp_password_encrypted);
//             } catch (\Exception $e) {
//                 Log::error('Failed to decrypt password for vendor ' . $id . ': ' . $e->getMessage());
//             }
//         }

//         return response()->json([
//             'success'        => true,
//             'best_seed_id'   => $vendor->best_seeds_id,
//             'temp_password'  => $tempPassword,
//             'mobile'         => $vendor->mobile,
//             'email'          => $vendor->email,
//             'name'           => $vendor->name
//         ]);

//     } catch (\Exception $e) {
//         Log::error('Error getting vendor credentials: ' . $e->getMessage());
//         return response()->json([
//             'success' => false,
//             'message' => 'Failed to retrieve vendor credentials'
//         ], 500);
//     }
// }

    /**
     * Activate a vendor
     */
    public function activate($id)
    {
        try {
            $vendor = Vendor::findOrFail($id);
            $vendor->update(['status' => 1]);

            return response()->json([
                'success' => true,
                'message' => 'Vendor activated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate vendor'
            ], 500);
        }
    }
      public function create()
    {
       // dd('create vendor view'); //fine
        return view('admin.vendor.create');
    }

//    public function store(Request $request)
//     {
//         // 1. Validation
//         $validated = $request->validate([
//             'user_name'   => 'required|string|max:255',
//             // Use 'name' for the field being saved, but 'user_mobile' for input
//             'user_mobile' => 'required|string|max:20|unique:vendors,mobile',
//         ]);

//         try {
//             // 2. Data Preparation
//             $bestSeedId = $this->generateUniqueBestSeedId();
//             $tempPassword = Str::random(10);

//             // Create the data array for the model
//             $vendorData = [
//                 'name'                    => $validated['user_name'],
//                 'mobile'                  => $validated['user_mobile'],
//                 'password'                => Hash::make($tempPassword), // ALWAYS HASH
//                 'temp_password_encrypted' => Crypt::encryptString($tempPassword),
//                 'best_seeds_id'           => $bestSeedId,
//                 'role'                    => 'hatchery',
//                 // 'status'                  => 1, // Changed status to 1 (active) for a new user, assuming 0 means inactive
//                 'is_first_login'          => true, // Added this field for completeness
//             ];

//             // 3. Database Operation
//             $vendor = Vendor::create($vendorData);

//             // Optional: Send $tempPassword via SMS/email (Implementation depends on external service)
//             // Example: Mail::to($vendor->email)->send(new NewVendorCredentials($vendor, $tempPassword));

//             // 4. Response
//             return redirect()
//                 ->route('vendors.index')
//                 ->with('success', "Hatchery Vendor '{$vendor->name}' created successfully. ID: **{$bestSeedId}**. Temporary Password: **{$tempPassword}** (Please communicate this to the vendor securely).");

//         } catch (\Exception $e) {
//             // 5. Error Handling
//             Log::error('Error creating vendor (hatchery role): ' . $e->getMessage(), ['request' => $request->all()]);

//             return redirect()
//                 ->back()
//                 ->withInput()
//                 ->with('error', 'Vendor creation failed. Please check logs for details.');
//         }
//     }

//     /**
//      * Generates the next unique 'BSXXXX' ID.
//      * @return string
//      */
//     protected function generateUniqueBestSeedId(): string
//     {
//         $lastVendor = Vendor::orderBy('id', 'desc')->first();

//         // Start with 1 if no vendors exist, otherwise increment the number after 'BS'
//         $nextId = $lastVendor
//             ? (int)substr($lastVendor->best_seeds_id, 2) + 1
//             : 1;

//         // Pad the number with leading zeros to 4 digits
//         return 'BS' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
//     }


        public function edit($id)
    {
        $data = Vendor::where('id', $id)->first();
        return view('admin.vendor.edit', compact('data'));
    }
//      public function update(Request $request, $id)
//     {
//         // dd($request->File());
//         $request->validate([
//             'name' => 'required|string|max:255',
//             'mobile' => 'required|string|max:20',
//             'alternate_mobile' => 'nullable|string|max:20',
//             'address' => 'required|string|max:500',
//             'pincode' => 'required|string|max:10',
//         ]);

//     if ($request->hasFile('profile_image')) {
//     // Delete old image if exists
//     if (!empty($vendor->profile_image) && file_exists(public_path('uploads/vendor/profile/' . $vendor->profile_image))) {
//         File::delete(public_path('uploads/vendor/profile/' . $vendor->profile_image));
//     }

//     // Upload new image
//     $image = 'profile_' . time() . '.' . $request->profile_image->extension();
//     $request->profile_image->move(public_path('uploads/vendor/profile/'), $image);
//     $data['profile_image'] = $image;
// }


//         $vendor = Vendor::findOrFail($id);

//         $vendor->update([
//             'name' => $request->name,
//             'mobile' => $request->mobile,
//             'alternate_mobile' => $request->alternate_mobile,
//             'address' => $request->address,
//             'pincode' => $request->pincode,
//         ]);

//         return redirect()->route('vendors.index')->with('success', 'Vendor updated successfully.');
//     }
public function update(Request $request, $id)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'mobile' => 'required|string|max:20',
        'status' => 'required|boolean',
        'alternate_mobile' => 'nullable|string|max:20',
        'address' => 'nullable|string|max:500',
        'pincode' => 'nullable|string|max:10',
    ]);

    $vendor = Vendor::findOrFail($id);

    // Check if trying to disable vendor with assigned hatcheries (skip if force_disable)
    if ($request->status == 0 && $vendor->status == 1 && !$request->has('force_disable') && $vendor->hatcheries()->count() > 0) {
        return redirect()->back()->withInput()
            ->with('error', 'Cannot disable this vendor. There are ' . $vendor->hatcheries()->count() . ' hatcheries assigned to this vendor. Please reassign them first.');
    }

    $updateData = $request->except('_token', '_method', 'profile_image', 'force_disable');

    if ($request->hasFile('profile_image')) {
        if (!empty($vendor->profile_image) && file_exists(public_path('uploads/vendor/profile/' . $vendor->profile_image))) {
            File::delete(public_path('uploads/vendor/profile/' . $vendor->profile_image));
        }

        $image = 'profile_' . time() . '.' . $request->profile_image->extension();
        $request->profile_image->move(public_path('uploads/vendor/profile/'), $image);
        $updateData['profile_image'] = $image;
    }

    $vendor->update($updateData);

    return redirect()->route('vendors.index')->with('success', 'Vendor updated successfully.');
}

/**
 * Reset vendor password
 */
public function resetPassword($id)
{
    try {
        $vendor = Vendor::findOrFail($id);
        $tempPassword = Str::random(10);

        $vendor->update([
            'password'                => $tempPassword, // Model mutator handles hashing
            'temp_password_encrypted' => Crypt::encryptString($tempPassword),
            'is_first_login'          => true,
        ]);

        return response()->json([
            'success'       => true,
            'vendor_name'   => $vendor->name,
            'best_seeds_id' => $vendor->best_seeds_id,
            'temp_password' => $tempPassword,
            'mobile'        => $vendor->mobile,
        ]);
    } catch (\Exception $e) {
        Log::error('Error resetting vendor password: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to reset vendor password.',
        ], 500);
    }
}

/**
 * Force logout a vendor — revokes all tokens and sends FCM to the device.
 */
public function forceLogout(Vendor $vendor)
{
    if (!empty($vendor->fcm_token)) {
        try {
            app(FirebaseNotificationService::class)->sendToDevice(
                $vendor->fcm_token,
                'Signed out',
                'You have been logged out by the administrator.',
                null,
                ['type' => 'force_logout', 'role' => 'employee']
            );
        } catch (\Throwable $e) {
            \Log::warning('Admin vendor force-logout FCM failed', ['vendor_id' => $vendor->id, 'err' => $e->getMessage()]);
        }
    }

    $vendor->tokens()->delete();

    return redirect()->back()->with('success', 'Vendor has been logged out successfully.');
}

/**
 * Check if vendor has hatcheries (AJAX)
 */
public function checkHatcheries($id)
{
    $vendor = Vendor::findOrFail($id);
    $hatcheries = $vendor->hatcheries()->get(['id', 'hatchery_name']);
    $count = $hatcheries->count();

    return response()->json([
        'has_hatcheries' => $count > 0,
        'count' => $count,
        'hatcheries' => $hatcheries->map(function($h) {
            return ['id' => $h->id, 'name' => $h->hatchery_name];
        })->values(),
    ]);
}
    public function destroy($id)
{
    try {

        $vendor = Vendor::findOrFail($id);

        $vendor->delete();

        return redirect()->route('vendors.index')
                         ->with('danger', 'Vendor deleted successfully!');
    } catch (\Exception $e) {

        return redirect()->route('vendors.index')
                         ->with('error', 'Something went wrong while deleting the vendor.');
    }
}






public function deleteProfileImage(Request $request)
{
    $request->validate([
        'image_name' => 'required|string',
        'model_id' => 'required',
        'model_type' => 'required|string'
    ]);

    try {
        $filePath = public_path('uploads/vendor/profile/' . $request->image_name);

        // Delete the file
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Update the model to remove the image reference
        if ($request->model_type === 'Vendor') {
            $vendor = \App\Models\Vendor::find($request->model_id);
            if ($vendor) {
                $vendor->update(['profile_image' => null]);
            }
        }

        return response()->json(['success' => true]);
    } catch (\Exception $e) {
        \Log::error('Profile image deletion failed: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to delete profile image. Please try again.'
        ], 500);
    }
}


}
