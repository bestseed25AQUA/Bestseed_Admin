<?php

namespace App\Http\Controllers\Api\Vendors_apis;

use App\Http\Controllers\Controller;
use App\Models\Hatchery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class VendorHatcheryController extends Controller
{
    // 🔹 Register Hatchery
    public function registerHatchery(Request $request)
    {
        try {
            // ✅ Validate incoming request fields
            $request->validate([
                'hatchery_name'   => 'nullable|string|max:255',
                'category'        => 'nullable', // can be string or array
                'location'        => 'nullable|string|max:255',
                'opening_time'    => 'nullable',
                'closing_time'    => 'nullable',
                'hatchery_images' => 'nullable|array',
                'hatchery_images.*' => 'nullable|max:5120', // allow all types up to 5MB
            ]);

            $paths = [];
            // ✅ If vendor uploads multiple images
            if ($request->hasFile('hatchery_images')) {
                foreach ($request->file('hatchery_images') as $file) {
                    // generate unique filename
                    $filename = date('dmY') . '_' .
                        preg_replace('/\s+/', '_', strtolower($request->hatchery_name ?? 'hatchery')) .
                        '_' . uniqid() . '.' . $file->getClientOriginalExtension();

                    // store file in "public/hatcheries"
                    $paths[] = $file->storeAs('hatcheries', $filename, 'public');
                }
            }

             // 🔹 Added: Auto-calculate lat/lng based on location
            $lat = null;
            $lng = null;
            if ($request->location) {
                $address = urlencode($request->location);
                $apiKey = env('GEOCODING_API_KEY');
                $url = "https://maps.googleapis.com/maps/api/geocode/json?address={$address}&key={$apiKey}";
                $response = @file_get_contents($url);
                if ($response) {
                    $data = json_decode($response, true);
                    if (isset($data['results'][0]['geometry']['location'])) {
                        $lat = $data['results'][0]['geometry']['location']['lat'];
                        $lng = $data['results'][0]['geometry']['location']['lng'];
                    }
                }
            }



            // ✅ Save hatchery in DB
            $hatchery = Hatchery::create([
                'vendor_id'     => $request->user()->id, // logged-in vendor
                'hatchery_name' => $request->hatchery_name,
                // 'category'      => is_array($request->category) ? json_encode($request->category) : $request->category,
                'category'      => $request->category,
                'location'      => $request->location,
                'opening_time'  => $request->opening_time,
                'closing_time'  => $request->closing_time,
                'category' => $request->category,
                'image'    => $paths,
                'brand'         => $request->brand, // ✅ Added
                // 'lat'           => $request->lat,   // ✅ Added
                // 'lng'           => $request->lng,   // ✅ Added
                'lat' => $lat ?? $request->lat,
                'lng' => $lng ?? $request->lng,
                'status'        => 'open',          // ✅ Default
                // 'image'         => json_encode($paths), // store multiple images
                // 'status'        => 'open', // ✅ default status
            ]);

            // return response()->json([
            //     'message'  => 'Hatchery registered successfully',
            //     'hatchery' => $hatchery
            // ]);
            return response()->json([
                'message'  => 'Hatchery registered successfully',
                'hatchery' => [
                    'id'            => $hatchery->id,
                    'vendor_id'     => $hatchery->vendor_id,
                    'hatchery_name' => $hatchery->hatchery_name,
                    'category'      => $hatchery->category,
                    'location'      => $hatchery->location,
                    'opening_time'  => $hatchery->opening_time,
                    'closing_time'  => $hatchery->closing_time,
                    'brand'         => $hatchery->brand,
                    'image'         => $hatchery->image,
                    'status'        => $hatchery->status,
                    // 🔹 lat/lng omitted from response
                ]
            ]);
        } catch (\Throwable $e) {
            // ❌ Error logging
            Log::error('Register Hatchery Error: ' . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // ❌ Return error response
            return response()->json([
                'message' => 'Registering hatchery failed',
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ], 500);
        }
    }

    // 🔹 List Hatcheries (by vendor)
    public function myHatcheries(Request $request)
    {
        try {
            // ✅ Fetch only hatcheries belonging to logged-in vendor
            $hatcheries = Hatchery::where('vendor_id', $request->user()->id)->get();

            return response()->json($hatcheries);
        } catch (\Throwable $e) {
            Log::error('List Hatcheries Error: ' . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Fetching hatcheries failed',
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ], 500);
        }
    }

    // 🔹 Update Hatchery
    public function updateHatchery(Request $request, $id)
    {
        try {
            // ✅ Find hatchery only if it belongs to logged-in vendor
            try {
                $hatchery = Hatchery::where('vendor_id', $request->user()->id)->findOrFail($id);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return response()->json([
                    'message' => 'This ID is not there in DB or deleted or not created'
                ], 404);
            }


            // ✅ Validate update request
            $request->validate([
                'hatchery_name'   => 'nullable|string|max:255',
                'category'        => 'nullable',
                'location'        => 'nullable|string|max:255',
                'opening_time'    => 'nullable',
                'closing_time'    => 'nullable',
                'hatchery_images' => 'nullable|array',
                'hatchery_images.*' => 'nullable|max:5120',
            ]);

              // Replace images if new ones uploaded
            if ($request->hasFile('hatchery_images')) {
                if ($hatchery->image) {
                    foreach ($hatchery->image as $img) {
                        if (Storage::disk('public')->exists($img)) {
                            Storage::disk('public')->delete($img);
                        }
                    }
                }

                // ✅ Save new images
                $paths = [];
                foreach ($request->file('hatchery_images') as $file) {
                    $filename = date('dmY') . '_' .
                        preg_replace('/\s+/', '_', strtolower($request->hatchery_name ?? $hatchery->hatchery_name)) .
                        '_' . uniqid() . '.' . $file->getClientOriginalExtension();

                    $paths[] = $file->storeAs('hatcheries', $filename, 'public');
                }
               $hatchery->image = $paths;
            }


             // 🔹 Added: Update lat/lng if location changed
            if ($request->location && $request->location !== $hatchery->location) {
                $address = urlencode($request->location);
                $apiKey = env('GEOCODING_API_KEY');
                $url = "https://maps.googleapis.com/maps/api/geocode/json?address={$address}&key={$apiKey}";
                $response = @file_get_contents($url);
                if ($response) {
                    $data = json_decode($response, true);
                    if (isset($data['results'][0]['geometry']['location'])) {
                        $hatchery->lat = $data['results'][0]['geometry']['location']['lat'];
                        $hatchery->lng = $data['results'][0]['geometry']['location']['lng'];
                    }
                }
            }

            // ✅ Update fields if provided
            $hatchery->hatchery_name = $request->hatchery_name ?? $hatchery->hatchery_name;
            $hatchery->category      = $request->category ?? $hatchery->category;
            $hatchery->location      = $request->location ?? $hatchery->location;
            $hatchery->opening_time  = $request->opening_time ?? $hatchery->opening_time;
            $hatchery->closing_time  = $request->closing_time ?? $hatchery->closing_time;
            $hatchery->brand = $request->brand ?? $hatchery->brand;
            // $hatchery->lat = $request->lat ?? $hatchery->lat;  //new one added 
            // $hatchery->lng = $request->lng ?? $hatchery->lng; //new one added 
            $hatchery->status = $request->status ?? $hatchery->status;  //new one added 


            // ✅ Save updates
            $hatchery->save();

            return response()->json([
                'message'  => 'Hatchery updated successfully',
                'hatchery' => $hatchery
            ]);
        } catch (\Throwable $e) {
            Log::error('Update Hatchery Error: ' . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Updating hatchery failed',
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ], 500);
        }
    }

    // 🔹 Delete Hatchery
public function deleteHatchery(Request $request, $id)
{
    try {
        // ✅ Instead of findOrFail(), use find() so we control the error
        $hatchery = Hatchery::where('vendor_id', $request->user()->id)->find($id);

        // ✅ Custom message if not found
        if (!$hatchery) {
            return response()->json([
                'message' => 'This ID is not there in DB or deleted or not created'
            ], 404);
        }

        // ✅ Delete images if exist
        if ($hatchery->image) {
            foreach ($hatchery->image as $img) {
                if (Storage::disk('public')->exists($img)) {
                    Storage::disk('public')->delete($img);
                }
            }
        }

        // ✅ Delete hatchery record
        $hatchery->delete();

        return response()->json(['message' => 'Hatchery deleted successfully']);
    } catch (\Throwable $e) {
        Log::error('Delete Hatchery Error: ' . $e->getMessage(), [
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'message' => 'Deleting hatchery failed',
            'error'   => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'trace'   => $e->getTraceAsString(),
        ], 500);
    }
  }
}