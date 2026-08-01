<?php

namespace App\Http\Controllers\Api\Vendors_apis;

use App\Http\Controllers\Controller;
use App\Models\HatcheryUpdate;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class VendorUpdatesController extends Controller
{
    /**
     * 📌 List all updates (by logged-in vendor)
     */
    public function index()
    {
        try {
            $vendorId = Auth::id();
            $updates = HatcheryUpdate::where('vendor_id', $vendorId)
                ->latest()
                ->get();

            return response()->json($updates, 200);

        } catch (Exception $e) {
            return response()->json([
                'error'   => true,
                'message' => 'Failed to fetch updates',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 📌 Store new update (image/video + caption + hashtags)
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'caption'       => 'nullable|string|max:500',
                'hashtags'      => 'nullable|array',
                'hashtags.*'    => 'string',
                'media_files.*' => 'file|max:512000', // support up to 500MB
            ]);

            $files = [];
            $mediaTypes = []; // allow multiple (image + video)

            if ($request->hasFile('media_files')) {
                $vendor = Auth::user(); 
                $vendorName = preg_replace('/\s+/', '_', strtolower($vendor->name ?? 'vendor'));

                foreach ($request->file('media_files') as $file) {
                    // filename format: date + vendorID + vendorName + random string
                    $filename = date('dmY')
                              . '_vendor_' . $vendor->id
                              . '_' . $vendorName
                              . '_' . uniqid()
                              . '.' . $file->getClientOriginalExtension();

                    $path = $file->storeAs('updates', $filename, 'public');
                    $files[] = asset("storage/" . $path);

                    // detect media type
                    $ext = strtolower($file->getClientOriginalExtension());
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                        $mediaTypes[] = 'image';
                    } elseif (in_array($ext, ['mp4', 'mov', 'avi', 'mkv'])) {
                        $mediaTypes[] = 'video';
                    }
                }
            }

           $uniqueTypes = array_unique($mediaTypes);
            $finalType = count($uniqueTypes) === 1 ? $uniqueTypes[0] : 'mixed';

            $update = HatcheryUpdate::create([
                'vendor_id'   => Auth::id(),
                'caption'     => $request->caption,
                'hashtags'    => $request->hashtags,
                'media_files' => $files,
                'media_type'  => $finalType,
            ]);


            return response()->json([
                'message' => 'Update posted successfully',
                'data'    => $update
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'error'   => true,
                'message' => 'Failed to post update',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 📌 Show single update
     */
    public function show($id)
    {
        try {
            $update = HatcheryUpdate::with('vendor')->findOrFail($id);
            return response()->json($update, 200);

        } catch (Exception $e) {
            return response()->json([
                'error'   => true,
                'message' => 'Failed to fetch update',
                'details' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * 📌 Delete update
     */
    // public function destroy($id)
    // {
    //     try {
    //         $update = HatcheryUpdate::where('vendor_id', Auth::id())->findOrFail($id);

    //         // delete media files from storage
    //         if ($update->media_files) {
    //             foreach ($update->media_files as $file) {
    //                   $path = str_replace('storage/', '', $file);
    //                 if (Storage::disk('public')->exists($path)) {
    //                     Storage::disk('public')->delete($path);
    //                 }
    //             }
    //         }

    //         $update->delete();

    //         return response()->json(['message' => 'Update deleted successfully'], 200);

    //     } catch (Exception $e) {
    //         return response()->json([
    //             'error'   => true,
    //             'message' => 'Failed to delete update',
    //             'details' => $e->getMessage(),
    //         ], 500);
    //     }
    // }


    /**
 * 📌 Delete update
 */
public function destroy($id)
{
    try {
        // Try to find the update for the logged-in vendor
        $update = HatcheryUpdate::where('vendor_id', Auth::id())->find($id);

        if (!$update) {
            // If not found, send a friendly message instead of default 404
            return response()->json([
                'message' => 'Update not found or already deleted.'
            ], 404);
        }

        // Delete media files from storage
        if ($update->media_files) {
            foreach ($update->media_files as $file) {
                $path = str_replace('storage/', '', $file);
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
        }

        $update->delete();

        return response()->json(['message' => 'Update deleted successfully'], 200);

    } catch (Exception $e) {
        return response()->json([
            'error'   => true,
            'message' => 'Failed to delete update',
            'details' => $e->getMessage(),
        ], 500);
    }
}

}