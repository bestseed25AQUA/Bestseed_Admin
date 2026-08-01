<?php

namespace App\Http\Controllers\Api\Vendors_apis;

use App\Http\Controllers\Controller;
use App\Models\Hatchery;
use App\Models\Seed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SeedBrandController extends Controller
{
    // 🌱 List Seeds (with hatchery details)
    public function index()
    {
        $vendorId = Auth::id();
        $seeds = Seed::with('hatchery')
            ->where('vendor_id', $vendorId)
            ->get();

        return response()->json($seeds);
    }

     // 🌱 Create Seed
    public function store(Request $request)
    {       
        $request->validate([
            'hatchery_id'          => 'required|exists:hatcheries,id',
            'categories'           => 'required|array',
            'categories.*'         => 'string',
            'description'          => 'nullable|string',
            // 'locations'            => 'nullable|array',
            // 'locations.*'          => 'string',
             'locations'            => 'nullable', // now accepts string or array
            'broad_stock'          => 'nullable|integer',
            'stock_available_date' => 'nullable|date',
            'price'                => 'nullable|numeric',
            // 'seed_images.*'        => 'file|mimes:jpg,jpeg,png|max:2048',
            // 'seed_videos.*'        => 'file|mimes:mp4,mov,avi|max:10240',
            'seed_images.*' => 'file|max:51200', // allow all types, up to 50 MB
            'seed_videos.*' => 'file|max:512000', // allow all types, up to 500 MB
        ]);

        // ✅ Handle uploads
        $images = [];
        if ($request->hasFile('seed_images')) {
            foreach ($request->file('seed_images') as $file) {
                $filename = date('dmY') . '_' .
                            preg_replace('/\s+/', '_', strtolower($request->hatchery_id . '_hatchery')) .
                            '_' . uniqid() . '.' . $file->getClientOriginalExtension();

                $path = $file->storeAs('seeds/images', $filename, 'public');
                $images[] = asset("storage/" . $path); // store full URL
            }
        }

        $videos = [];
        if ($request->hasFile('seed_videos')) {
            foreach ($request->file('seed_videos') as $file) {
                $filename = date('dmY') . '_' .
                            preg_replace('/\s+/', '_', strtolower($request->hatchery_id . '_hatchery')) .
                            '_' . uniqid() . '.' . $file->getClientOriginalExtension();

                $path = $file->storeAs('seeds/videos', $filename, 'public');
                $videos[] = asset("storage/" . $path); // store full URL
            }
        }


        // 🔹 Parse locations robustly
        $locations = $request->input('locations');
        if ($locations) {
            if (is_string($locations)) {
                $locations = array_map('trim', explode(',', $locations));
            } elseif (!is_array($locations)) {
                $locations = [$locations]; // wrap single value
            }
        } else {
            $locations = []; // empty array if nothing provided
        }


        // 🔹 Optional: Add lat/lng for each location (commented if not needed)
        /*
        $latlngs = [];
        foreach ($locations as $loc) {
            $lat = null;
            $lng = null;
            $address = urlencode($loc);
            $apiKey = env('GEOCODING_API_KEY'); // put your Google API key in .env
            $url = "https://maps.googleapis.com/maps/api/geocode/json?address={$address}&key={$apiKey}";
            $response = @file_get_contents($url);
            if ($response) {
                $data = json_decode($response, true);
                if (isset($data['results'][0]['geometry']['location'])) {
                    $lat = $data['results'][0]['geometry']['location']['lat'];
                    $lng = $data['results'][0]['geometry']['location']['lng'];
                }
            }
            $latlngs[] = ['location' => $loc, 'lat' => $lat, 'lng' => $lng];
        }
        $locations = $latlngs; // store lat/lng if needed
        */

        $seed = Seed::create([
            'vendor_id'            => Auth::id(),
            'hatchery_id'          => $request->hatchery_id,
            'categories'           => $request->categories,
            'description'          => $request->description,
            // 'locations'            => $request->locations,
            'locations' => $locations,
            'broad_stock'          => $request->broad_stock,
            'stock_available_date' => $request->stock_available_date,
            'price'                => $request->price,
            'seed_images'          => $images,
            'seed_videos'          => $videos,
        ]);

        return response()->json([
            'message' => 'Seed created successfully',
            'data'    => $seed->load('hatchery'),
        ]);
    }

    // 🌱 Show Single Seed (with hatchery details)
    public function show($id)
    {
        $seed = Seed::with('hatchery')->findOrFail($id);
        return response()->json($seed);
    }

    // 🌱 Update Seed
        // public function update(Request $request, $id)
        // {
        //      try {
        //         // 🔹 Find seed belonging to logged-in vendor
        //         $seed = Seed::where('vendor_id', Auth::id())->find($id);

        //         if (!$seed) {
        //             return response()->json([
        //                 'message' => 'Seed not found or you do not have permission.'
        //             ], 404);
        //         }

        //         $request->validate([
        //             'hatchery_id'          => 'sometimes|exists:hatcheries,id',
        //             'categories'           => 'nullable|array',
        //             'categories.*'         => 'string',
        //             'description'          => 'nullable|string',
        //              'locations'             => 'nullable|string|max:255', 
        //             'broad_stock'          => 'nullable|integer',
        //             'stock_available_date' => 'nullable|date',
        //             'price'                => 'nullable|numeric',
        //             // 'seed_images.*'        => 'file|mimes:jpg,jpeg,png|max:2048',
        //             // 'seed_videos.*'        => 'file|mimes:mp4,mov,avi|max:10240',
        //             'seed_images.*' => 'file|max:51200', // allow all types, up to 50 MB
        //             'seed_videos.*' => 'file|max:512000', // allow all types, up to 500 MB
        //         ]);

        //         // 🔹 Handle new uploads if any
        //             if ($request->hasFile('seed_images')) {
        //             foreach ($request->file('seed_images') as $file) {
        //                 $filename = date('dmY') . '_' .
        //                             preg_replace('/\s+/', '_', strtolower($request->hatchery_id . '_hatchery')) .
        //                             '_' . uniqid() . '.' . $file->getClientOriginalExtension();

        //                 $path = $file->storeAs('seeds/images', $filename, 'public');
        //                 $images[] = asset("storage/" . $path);
        //             }
        //         }

        //             if ($request->hasFile('seed_videos')) {
        //                 foreach ($request->file('seed_videos') as $file) {
        //                     $filename = date('dmY') . '_' .
        //                                 preg_replace('/\s+/', '_', strtolower($request->hatchery_id . '_hatchery')) .
        //                                 '_' . uniqid() . '.' . $file->getClientOriginalExtension();

        //                     $path = $file->storeAs('seeds/videos', $filename, 'public');
        //                     $videos[] = asset("storage/" . $path);
        //                 }
        //             }


        //                     // 🔹 Update existing record only
        //                     $seed->update([
        //                         'hatchery_id'          => $request->hatchery_id ?? $seed->hatchery_id,
        //                         'categories'           => $request->categories ?? $seed->categories,
        //                         'description'          => $request->description ?? $seed->description,
        //                         'locations'             => $request->locations ?? $seed->locations,
        //                         'broad_stock'          => $request->broad_stock ?? $seed->broad_stock,
        //                         'stock_available_date' => $request->stock_available_date ?? $seed->stock_available_date,
        //                         'price'                => $request->price ?? $seed->price,
        //                         'seed_images'          => $images,
        //                         'seed_videos'          => $videos,
        //                     ]);

        //                     return response()->json([
        //                         'message' => 'Seed updated successfully',
        //                         'data'    => $seed->load('hatchery'),
        //                     ]);
        //                 } catch (\Throwable $e) {
        //                     return response()->json([
        //                         'message' => 'Updating seed failed',
        //                         'error'   => $e->getMessage(),
        //                     ], 500);
        //                 }
           
        // }

        //🌱 Update Seed
        public function update(Request $request, $id)
        {
            try {
                // 🔹 First check if seed exists
                $seed = Seed::find($id);

                if (!$seed) {
                    return response()->json([
                        'message' => 'Seed not found in the database.'
                    ], 404);
                }

                // 🔹 Then check vendor permission
                if ($seed->vendor_id !== Auth::id()) {
                    return response()->json([
                        'message' => 'You do not have permission to update this seed.',
                        'your_vendor_id' => Auth::id(),
                        'seed_vendor_id' => $seed->vendor_id,
                    ], 403);
                }

                $request->validate([
                    'hatchery_id'          => 'sometimes|exists:hatcheries,id',
                    'categories'           => 'nullable',
                    'description'          => 'nullable|string',
                    'locations'            => 'nullable',
                    'broad_stock'          => 'nullable|integer',
                    'stock_available_date' => 'nullable|date',
                    'price'                => 'nullable|numeric',
                    'seed_images.*'        => 'file|max:51200',
                    'seed_videos.*'        => 'file|max:512000',
                ]);

                // 🔹 Parse categories
                $categories = $request->input('categories');
                if (is_string($categories)) {
                    $categories = array_map('trim', explode(',', $categories));
                }

                // // 🔹 Parse locations
                // $locations = $request->input('locations');
                // if (is_string($locations)) {
                //     $locations = array_map('trim', explode(',', $locations));
                // }

                // 🔹 Parse locations
                $locations = $request->input('locations');
                if ($locations) {
                    if (is_string($locations)) {
                        $locations = array_map('trim', explode(',', $locations));
                    } elseif (!is_array($locations)) {
                        $locations = [$locations];
                    }
                } else {
                    $locations = $seed->locations ?? [];
                }

                // 🔹 Handle new uploads if any
                $images = $seed->seed_images ?? [];
                if ($request->hasFile('seed_images')) {
                    $images = [];
                    foreach ($request->file('seed_images') as $file) {
                        $filename = date('dmY') . '_' .
                                    preg_replace('/\s+/', '_', strtolower($request->hatchery_id . '_hatchery')) .
                                    '_' . uniqid() . '.' . $file->getClientOriginalExtension();

                        $path = $file->storeAs('seeds/images', $filename, 'public');
                        $images[] = asset("storage/" . $path);
                    }
                }

                $videos = $seed->seed_videos ?? [];
                if ($request->hasFile('seed_videos')) {
                    $videos = [];
                    foreach ($request->file('seed_videos') as $file) {
                        $filename = date('dmY') . '_' .
                                    preg_replace('/\s+/', '_', strtolower($request->hatchery_id . '_hatchery')) .
                                    '_' . uniqid() . '.' . $file->getClientOriginalExtension();

                        $path = $file->storeAs('seeds/videos', $filename, 'public');
                        $videos[] = asset("storage/" . $path);
                    }
                }

                // 🔹 Update existing record only
                $seed->update([
                    'hatchery_id'          => $request->hatchery_id ?? $seed->hatchery_id,
                    'categories'           => $categories ?? $seed->categories,
                    'description'          => $request->description ?? $seed->description,
                    'locations'            => $locations ?? $seed->locations,
                    'broad_stock'          => $request->broad_stock ?? $seed->broad_stock,
                    'stock_available_date' => $request->stock_available_date ?? $seed->stock_available_date,
                    'price'                => $request->price ?? $seed->price,
                    'seed_images'          => $images,
                    'seed_videos'          => $videos,
                ]);

                return response()->json([
                    'message' => 'Seed updated successfully',
                    'data'    => $seed->load('hatchery'),
                ]);
            } catch (\Throwable $e) {
                return response()->json([
                    'message' => 'Updating seed failed',
                    'error'   => $e->getMessage(),
                ], 500);
            }
        }



            // // 🌱 Delete Seed
            // public function destroy($id)
            // {
            //     $seed = Seed::where('vendor_id', Auth::id())->findOrFail($id);
            //     $seed->delete();

            //     return response()->json(['message' => 'Seed deleted successfully']);
            // }


            // 🌱 Delete Seed
public function destroy($id)
{
    $seed = Seed::where('vendor_id', Auth::id())->find($id);

    if (!$seed) {
        return response()->json([
            'message' => 'Seed not found or already deleted.'
        ], 404);
    }

    $seed->delete();

    return response()->json([
        'message' => 'Seed deleted successfully'
    ]);
}

}
