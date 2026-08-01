<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Hatchery;
use App\Models\HatcheryCategory;
use App\Models\HatcheryLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;

class HatcheryMetaController extends Controller
{
    /**
     * GET /api/hatchery/categories
     * Fetch all hatchery categories
     */
    public function categories()
    {
        try {
            $categories = HatcheryCategory::orderBy('priority', 'asc')
                ->get(['id', 'category_name']);

            return response()->json([
                'status' => true,
                'categories' => $categories
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // /**
    //  * GET /api/hatchery/locations
    //  * Fetch all hatchery locations
    //  */
    // public function locations()
    // {
    //     try {
    //         $locations = HatcheryLocation::orderBy('priority', 'asc')
    //             ->get(['id', 'location_name', 'longitude', 'latitude']);

    //         return response()->json([
    //             'status' => true,
    //             'locations' => $locations
    //         ], 200);

    //     } catch (Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to fetch locations',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }


    public function locations()
{
    try {
        // $locations = HatcheryLocation::orderBy('priority', 'asc')->get();.
        $locations = HatcheryLocation::whereNotNull('priority')
            ->orderBy('priority', 'asc')
            ->get();


        $formatted = $locations->map(function ($loc) {
            // 🧩 Check if full_address already contains "India"
            $address = $loc->full_address;

            if (!$address) {
                $address = "{$loc->location_name}, India";
            } elseif (!str_ends_with(trim($address), 'India')) {
                $address .= ', India';
            }

            return [
                'id' => $loc->id,
                'title' => $loc->location_name,
                'subtitle' => $address,
                'latitude' => $loc->latitude,
                'longitude' => $loc->longitude,
                'is_default' => (bool) $loc->is_default,
            ];
        });

        return response()->json([
            'status' => true,
            'locations' => $formatted
        ], 200);

    } catch (Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to fetch locations',
            'error' => $e->getMessage()
        ], 500);
    }
}




    /**
     * GET /api/hatchery/brands
     * Fetch all brands
     */
    public function brands()
    {
        try {
            $brands = Brand::orderBy('brand_name', 'asc')
                ->get(['id', 'brand_name']);

            return response()->json([
                'status' => true,
                'brands' => $brands
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch brands',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // /**
    //  * ============================================================
    //  * ✅ GET /api/hatchery/filters
    //  * ============================================================
    //  * Fetch all hatchery categories and locations.
    //  * Optional: filter hatcheries by one or many category_id(s) or location_id(s)
    //  * Example:
    //  * /api/hatchery/filters?category_id[]=1&category_id[]=2&location_id[]=3
    //  * ============================================================
    //  */
    // public function hatcheries_filters(Request $request)
    // {
    //     try {
    //         // Step 1️⃣: Get all categories and locations for dropdowns
    //         $categories = HatcheryCategory::orderBy('priority', 'asc')
    //             ->get(['id', 'category_name']);

    //         $locations = HatcheryLocation::orderBy('priority', 'asc')
    //             ->get(['id', 'location_name', 'longitude', 'latitude']);

    //         // Step 2️⃣: Prepare base hatchery query
    //         $query = Hatchery::query();

    //         // Step 3️⃣: Apply category filter (supports multiple)
    //         if ($request->has('category_id')) {
    //             $categoryIds = is_array($request->category_id)
    //                 ? $request->category_id
    //                 : [$request->category_id];

    //             $query->whereIn('category_id', $categoryIds);
    //         }

    //         // Step 4️⃣: Apply location filter (supports multiple)
    //         if ($request->has('location_id')) {
    //             $locationIds = is_array($request->location_id)
    //                 ? $request->location_id
    //                 : [$request->location_id];

    //             $query->whereIn('location_id', $locationIds);
    //         }

    //         // Step 5️⃣: Fetch filtered hatcheries
    //         $hatcheries = $query->orderBy('id', 'desc')
    //             ->get([
    //                 'id',
    //                 'hatchery_name',
    //                 'location_id',
    //                 'category_id',
    //                 'available_on',
    //                 'status'
    //             ]);

    //         // Step 6️⃣: Return success response
    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Filters and hatchery data fetched successfully',
    //             'filters' => [
    //                 'categories' => $categories,
    //                 'locations' => $locations
    //             ],
    //             'hatcheries' => $hatcheries
    //         ], 200);

    //     } catch (Exception $e) {
    //         // Step 7️⃣: Catch errors but still return 200 OK
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Something went wrong while fetching filters or hatcheries',
    //             'error_details' => $e->getMessage()
    //         ], 200);
    //     }
    // }


/**
 * ===========================================================
 * POST /api/hatchery/locations
 * Add new location (Token-based Farmer)
 * ===========================================================
 */
public function addLocation(Request $request)
{
    $request->validate([
        'location_name' => 'required|string|max:255',
        'latitude' => 'required|numeric',
        'longitude' => 'required|numeric',
        'full_address' => 'nullable|string',
    ]);

    try {
        /** @var \App\Models\Farmer $farmer */
        $farmer = auth()->user();
        $farmerId = $farmer->id;

        // 🧩 Check if same location already exists for this farmer
        $existing = HatcheryLocation::where('farmer_id', $farmerId)
            ->where('latitude', $request->latitude)
            ->where('longitude', $request->longitude)
            ->first();

        if ($existing) {
            // 🧩 If existing record has no full_address but new request provides it — update it
            if (!$existing->full_address && $request->filled('full_address')) {
                $address = $request->full_address;
                if (!str_ends_with(trim($address), 'India')) {
                    $address .= ', India';
                }

                $existing->update(['full_address' => $address]);
            }

            // 🧩 Build clean subtitle
            $subtitle = $existing->full_address 
                ? $existing->full_address 
                : "{$existing->location_name}, India";

            return response()->json([
                'status' => true,
                'message' => 'Location already exists',
                'data' => [
                    'id' => $existing->id,
                    'title' => $existing->location_name,
                    'subtitle' => $subtitle,
                    'latitude' => $existing->latitude,
                    'longitude' => $existing->longitude,
                    'is_default' => (bool) $existing->is_default,
                ]
            ], 200);
        }

        // 🧩 Clean up full_address if empty or missing "India"
        $address = $request->full_address;
        if (!$address) {
            $address = "{$request->location_name}, India";
        } elseif (!str_ends_with(trim($address), 'India')) {
            $address .= ', India';
        }

        // ✅ Create new location
        $location = HatcheryLocation::create([
            'location_name' => $request->location_name,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'farmer_id' => $farmerId,
            'is_default' => false,
            'full_address' => $address
        ]);

        // ✅ Return formatted response
        return response()->json([
            'status' => true,
            'message' => 'Location added successfully',
            'data' => [
                'id' => $location->id,
                'title' => $location->location_name,
                'subtitle' => $location->full_address,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'is_default' => (bool) $location->is_default,
            ]
        ], 201);

    } catch (Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to add location',
            'error' => $e->getMessage()
        ], 500);
    }
}


/**
 * ===========================================================
 * PUT /api/hatchery/locations/{id}
 * Update an existing location
 * ===========================================================
 */
public function updateLocation(Request $request, $id)
{
    $request->validate([
        'location_name' => 'sometimes|string|max:255',
        'latitude' => 'sometimes|numeric',
        'longitude' => 'sometimes|numeric',
        'full_address' => 'nullable|string',
    ]);

    try {
        $farmer = auth()->user();
        $farmerId = $farmer->id;

        $location = HatcheryLocation::where('id', $id)
            ->where('farmer_id', $farmerId)
            ->firstOrFail();

        // 🧩 Clean address before updating
        $address = $request->full_address;
        if ($address && !str_ends_with(trim($address), 'India')) {
            $address .= ', India';
        }

        $location->update([
            'location_name' => $request->location_name ?? $location->location_name,
            'latitude' => $request->latitude ?? $location->latitude,
            'longitude' => $request->longitude ?? $location->longitude,
            'full_address' => $address ?? $location->full_address,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Location updated successfully',
            'data' => [
                'id' => $location->id,
                'title' => $location->location_name,
                'subtitle' => $location->full_address,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'is_default' => (bool) $location->is_default,
            ]
        ], 200);

    } catch (ModelNotFoundException $e) {
        return response()->json(['status' => false, 'message' => 'Location not found'], 404);
    } catch (Exception $e) {
        return response()->json(['status' => false, 'message' => 'Failed to update location', 'error' => $e->getMessage()], 500);
    }
}

/**
 * ===========================================================
 * GET /api/hatchery/locations/search?keyword=Hyderabad
 * Search locations by name
 * ===========================================================
 */
public function searchLocations(Request $request)
{
    $keyword = $request->get('keyword', '');
    $farmer = auth()->user();
    $farmerId = $farmer->id;

    $locations = HatcheryLocation::where('farmer_id', $farmerId)
        ->where('location_name', 'like', "%{$keyword}%")
        ->orderBy('updated_at', 'desc')
        ->limit(20)
        ->get();

    $formatted = $locations->map(function ($loc) {
        $address = $loc->full_address;
        if (!$address) {
            $address = "{$loc->location_name}, India";
        } elseif (!str_ends_with(trim($address), 'India')) {
            $address .= ', India';
        }

        return [
            'id' => $loc->id,
            'title' => $loc->location_name,
            'subtitle' => $address,
            'latitude' => $loc->latitude,
            'longitude' => $loc->longitude,
            'is_default' => (bool) $loc->is_default,
        ];
    });

    return response()->json([
        'status' => true,
        'message' => 'Locations fetched successfully',
        'data' => $formatted
    ], 200);
}

/**
 * ===========================================================
 * POST /api/hatchery/locations/set-default/{id}
 * Set a location as default (Token-based)
 * ===========================================================
 */


public function setDefaultLocation($id)
{
    try {
        $farmer = auth()->user();
        $farmerId = $farmer->id;

        HatcheryLocation::where('farmer_id', $farmerId)->update(['is_default' => false]);

        $location = HatcheryLocation::where('id', $id)
            ->where('farmer_id', $farmerId)
            ->firstOrFail();

        $location->is_default = true;
        $location->save();        // store to DB
        $location->refresh();     // reload fresh

        $address = $location->full_address;
        if (!$address) {
            $address = "{$location->location_name}, India";
        } elseif (!str_ends_with(trim($address), 'India')) {
            $address .= ', India';
        }

        return response()->json([
            'status' => true,
            'message' => 'Default location updated successfully',
            'data' => [
                'id' => $location->id,
                'title' => $location->location_name,
                'subtitle' => $address,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'is_default' => (bool) $location->is_default,
            ]
        ], 200);

    } catch (ModelNotFoundException $e) {
        return response()->json(['status' => false, 'message' => 'Location not found'], 404);
    } catch (Exception $e) {
        return response()->json(['status' => false, 'message' => 'Failed to set default location', 'error' => $e->getMessage()], 500);
    }
}

/**
 * ===========================================================
 * GET /api/hatchery/locations/default
 * Get the current default location
 * ===========================================================
 */
public function defaultLocation()
{
    $farmer = auth()->user();
    $farmerId = $farmer->id;

    $location = HatcheryLocation::where('farmer_id', $farmerId)
        ->where('is_default', true)
        ->first();

    if (!$location) {
        return response()->json([
            'status' => false,
            'message' => 'No default location found',
            'data' => null
        ], 404);
    }

    $address = $location->full_address;
    if (!$address) {
        $address = "{$location->location_name}, India";
    } elseif (!str_ends_with(trim($address), 'India')) {
        $address .= ', India';
    }

    return response()->json([
        'status' => true,
        'message' => 'Default location fetched successfully',
        'data' => [
            'id' => $location->id,
            'title' => $location->location_name,
            'subtitle' => $address,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'is_default' => (bool) $location->is_default,
        ]
    ], 200);
}

/**
 * ===========================================================
 * GET /api/hatchery/locations/history
 * Recently used or added locations (last 10)
 * ===========================================================
 */
public function locationHistory()
{
    $farmer = auth()->user();
    $farmerId = $farmer->id;

    $locations = HatcheryLocation::where('farmer_id', $farmerId)
        ->orderBy('updated_at', 'desc')
        ->take(10)
        ->get();

    $formatted = $locations->map(function ($loc) {
        $address = $loc->full_address;
        if (!$address) {
            $address = "{$loc->location_name}, India";
        } elseif (!str_ends_with(trim($address), 'India')) {
            $address .= ', India';
        }

        return [
            'id' => $loc->id,
            'title' => $loc->location_name,
            'subtitle' => $address,
            'latitude' => $loc->latitude,
            'longitude' => $loc->longitude,
            'is_default' => (bool) $loc->is_default,
        ];
    });

    return response()->json([
        'status' => true,
        'message' => 'Recent location history fetched successfully',
        'data' => $formatted
    ], 200);
}

/**
 * ===========================================================
 * DELETE /api/hatchery/locations/{id}
 * Delete a location by ID
 * ===========================================================
 */
public function deleteLocation($id)
{
    try {
        $farmer = auth()->user();
        $farmerId = $farmer->id;

        $location = HatcheryLocation::where('id', $id)
            ->where('farmer_id', $farmerId)
            ->firstOrFail();

        $location->delete();

        return response()->json([
            'status' => true,
            'message' => 'Location deleted successfully'
        ], 200);

    } catch (ModelNotFoundException $e) {
        return response()->json(['status' => false, 'message' => 'Location not found'], 404);
    } catch (Exception $e) {
        return response()->json(['status' => false, 'message' => 'Failed to delete location', 'error' => $e->getMessage()], 500);
    }
}


} 