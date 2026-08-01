<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\AppConfig;
use App\Models\MarketPrice;
use App\Models\HatcheryCategory;
use App\Models\HatcheryLocation;
use Illuminate\Http\Request;
use Exception;

class PriceController extends Controller
{



    // public function getSeedPrices(Request $request)
    // {
    //     try {
    //         $categoryId = $request->input('category_id');
    //         $locationId = $request->input('location_id');

    //         $description = "Here you can check the updated price list";

    //         // ✅ CASE 1: If both filters are empty → return all
    //         if (!$categoryId && !$locationId) {
    //             $prices = MarketPrice::with(['category', 'location'])
    //                 ->orderBy('category_id')
    //                 ->orderBy('size')
    //                 ->get(['category_id', 'location_id', 'size', 'price']);

    //             if ($prices->isEmpty()) {
    //                 return response()->json([
    //                     'status' => true,
    //                     'message' => 'Prices not available right now.',
    //                     'description' => $description,
    //                     'msg' => "Prices coming shortly.",
    //                     'prices' => []
    //                 ]);
    //             }

    //             $formattedPrices = $prices->map(function ($item) {
    //                 return [
    //                     'category' => $item->category->category_name ?? null,
    //                     'location' => $item->location->location_name ?? null,
    //                     'size' => $item->size,
    //                     'today_price' => (float) $item->price
    //                 ];
    //             });

    //             return response()->json([
    //                 'status' => true,
    //                 'message' => 'All prices fetched successfully.',
    //                 'description' => $description,
    //                 'msg' => "Showing all seed prices.",
    //                 'prices' => $formattedPrices
    //             ]);
    //         }

    //         // ✅ CASE 2: When both filters provided
    //         if (!$categoryId || !$locationId) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Both category_id and location_id are required for filtered data.',
    //                 'description' => null,
    //                 'msg' => null,
    //                 'prices' => []
    //             ]);
    //         }

    //         $category = HatcheryCategory::find($categoryId);
    //         $location = HatcheryLocation::find($locationId);

    //         if (!$category || !$location) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Invalid category or location.',
    //                 'description' => null,
    //                 'msg' => null,
    //                 'prices' => []
    //             ]);
    //         }

    //         $prices = MarketPrice::where('category_id', $categoryId)
    //             ->where('location_id', $locationId)
    //             ->orderBy('size', 'asc')
    //             ->get(['size', 'price']);

    //         if ($prices->isEmpty()) {
    //             return response()->json([
    //                 'status' => true,
    //                 'category' => $category->category_name,
    //                 'location' => $location->location_name,
    //                 'description' => $description,
    //                 'msg' => "Prices coming shortly. Prices are not available right now. We'll update them soon.",
    //                 'prices' => []
    //             ]);
    //         }

    //         $formattedPrices = $prices->map(function ($item) {
    //             return [
    //                 'size' => $item->size,
    //                 'today_price' => (float) $item->price
    //             ];
    //         });

    //         return response()->json([
    //             'status' => true,
    //             'category' => $category->category_name,
    //             'location' => $location->location_name,
    //             'description' => $description,
    //             'msg' => "Today's prices are available below.",
    //             'prices' => $formattedPrices
    //         ]);

    //     } catch (Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Something went wrong.',
    //             'description' => null,
    //             'msg' => $e->getMessage(),
    //             'prices' => []
    //         ]);
    //     }
    // }


    public function getHomeSeedPrices()
{
    try {
        // Step 1: Get default category (Vannamei)
        $category = HatcheryCategory::where('category_name', 'Vannamei')->first();

        if (!$category) {
            return response()->json([
                'status' => false,
                'message' => 'Default category (Vannamei) not found.',
                'prices' => []
            ], 200);
        }

        // Step 2: Get required locations (handle variations like Godavari/Godawari)
        $locations = HatcheryLocation::where(function($query) {
            $query->where('location_name', 'LIKE', '%india%')
                  ->orWhere('location_name', 'LIKE', '%India%');
        })->get();

        if ($locations->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Required locations not found.',
                'prices' => []
            ], 200);
        }

        $description = "Here you can check the updated price list";

        // Step 3: Fetch prices for both locations
        $responseData = [];

        foreach ($locations as $location) {
            $prices = MarketPrice::where('category_id', $category->id)
                ->where('location_id', $location->id)
                ->orderBy('priority', 'asc')
                ->orderBy('size', 'asc')
                ->get(['size', 'price', 'priority']);

            if ($prices->isEmpty()) {
                $responseData[] = [
                    'location' => $location->location_name,
                    'msg' => "Prices coming shortly.",
                    'prices' => []
                ];
            } else {
                $formattedPrices = $prices->map(function ($item) {
                    return [
                        'priority' => $item->priority,
                        'size' => $item->size,
                        'today_price' => (float) $item->price
                    ];
                });

                $responseData[] = [
                    'location' => $location->location_name,
                    'msg' => "Today's prices are available below.",
                    'prices' => $formattedPrices
                ];
            }
        }

        // Step 4: Send final JSON response
        return response()->json([
            'status' => true,
            'category' => $category->category_name,
            'description' => $description,
            'data' => $responseData
        ], 200);

    } catch (Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Something went wrong.',
            'msg' => $e->getMessage(),
            'prices' => []
        ], 200);
    }
}








    public function getSeedPrices(Request $request)
{
    try {
        $categoryId = $request->input('category_id');
        $locationId = $request->input('location_id');

        // 🟢 Step 1: Make Vannamei default if category_id not sent
        if (!$categoryId) {
            $defaultCategory = HatcheryCategory::where('category_name', 'Vannamei')->first();
            if ($defaultCategory) {
                $categoryId = $defaultCategory->id;
            }
        }


                if (!$locationId) {
            // ⭐ auto-select highest priority location
            $locationId = HatcheryLocation::whereNotNull('priority')
                ->orderBy('priority', 'asc')
                ->value('id');
        }



        // 🛑 Step 2: location_id must be present (required for home)
        // if (!$locationId) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'location_id is required.',
        //         'description' => null,
        //         'msg' => null,
        //         'prices' => []
        //     ], 200);
        // }

        $category = HatcheryCategory::find($categoryId);
        // $location = HatcheryLocation::find($locationId);
        $location = HatcheryLocation::where('id', $locationId)
        ->whereNotNull('priority') // ⭐ only priority locations allowed
        ->first();


        if (!$category || !$location) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid category or location.',
                'description' => null,
                'msg' => null,
                'prices' => []
            ], 200);
        }

        // 🟢 Step 3: Fetch prices normally (ordered by priority)
        $prices = MarketPrice::where('category_id', $categoryId)
            ->where('location_id', $locationId)
            ->orderBy('priority', 'asc')
            ->orderBy('size', 'asc')
            ->get(['size', 'price', 'priority']);

        $description = "Here you can check the updated price list";

        if ($prices->isEmpty()) {
            return response()->json([
                'status' => true,
                'category' => $category->category_name,
                'location' => $location->location_name,
                'description' => $description,
                'msg' => "Prices coming shortly. Prices are not available right now. We'll update them soon.",
                'prices' => []
            ], 200);
        }

        $formattedPrices = $prices->map(function ($item) {
            return [
                'priority' => $item->priority,
                'size' => $item->size,
                'today_price' => (float) $item->price
            ];
        });

        $disclaimer = AppConfig::getValue('seed_price_disclaimer', '');

        return response()->json([
            'status' => true,
            'category' => $category->category_name,
            'location' => $location->location_name,
            'description' => $description,
            'disclaimer' => $disclaimer,
            'msg' => "Today's prices are available below.",
            'prices' => $formattedPrices
        ], 200);

    } catch (Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Something went wrong.',
            'description' => null,
            'disclaimer' => '',
            'msg' => $e->getMessage(),
            'prices' => []
        ], 200);
    }
}





    //working code  previous code
    // public function getSeedPrices(Request $request)
    // {
    //     try {
    //         $categoryId = $request->input('category_id');
    //         $locationId = $request->input('location_id');

    //         if (!$categoryId || !$locationId) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Both category_id and location_id are required.',
    //                 'description' => null,
    //                 'msg' => null,
    //                 'prices' => []
    //             ], 200);
    //         }

    //         $category = HatcheryCategory::find($categoryId);
    //         $location = HatcheryLocation::find($locationId);

    //         if (!$category || !$location) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Invalid category or location.',
    //                 'description' => null,
    //                 'msg' => null,
    //                 'prices' => []
    //             ], 200);
    //         }

    //         $prices = MarketPrice::where('category_id', $categoryId)
    //         ->where('location_id', $locationId)
    //         ->orderBy('size', 'asc')
    //         ->get(['size', 'price']);


    //         $description = "Here you can check the updated price list";

    //         if ($prices->isEmpty()) {
    //             return response()->json([
    //                 'status' => true,
    //                 'category' => $category->category_name,
    //                 'location' => $location->location_name,
    //                 'description' => $description,
    //                 'msg' => "Prices coming shortly. Prices are not available right now. We'll update them soon.",
    //                 'prices' => []
    //             ], 200);
    //         }

    //         $formattedPrices = $prices->map(function ($item) {
    //             return [
    //                 'size' => $item->size,
    //                 'today_price' => (float) $item->price
    //             ];
    //         });

    //         return response()->json([
    //             'status' => true,
    //             'category' => $category->category_name,
    //             'location' => $location->location_name,
    //             'description' => $description,
    //             'msg' => "Today's prices are available below.",
    //             'prices' => $formattedPrices
    //         ], 200);

    //     } catch (Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Something went wrong.',
    //             'description' => null,
    //             'msg' => $e->getMessage(),
    //             'prices' => []
    //         ], 200);
    //     }
    // }


    //  /**
    //  * 🔹 2️⃣ Get All Prices by Location (no category filter)
    //  */
    // public function getAllSeedPrices(Request $request)
    // {
    //     try {
    //         $locationId = $request->input('location_id');

    //         if (!$locationId) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'location_id is required.',
    //                 'description' => null,
    //                 'msg' => null,
    //                 'prices' => []
    //             ], 200);
    //         }

    //         $location = HatcheryLocation::find($locationId);

    //         if (!$location) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Invalid location.',
    //                 'description' => null,
    //                 'msg' => null,
    //                 'prices' => []
    //             ], 200);
    //         }

    //         $prices = MarketPrice::where('location_id', $locationId)
    //             ->with('category:id,category_name')
    //             ->orderBy('category_id', 'asc')
    //             ->orderBy('size', 'asc')
    //             ->get(['category_id', 'size', 'price']);

    //         $description = "Here you can check the updated price list for all categories.";

    //         if ($prices->isEmpty()) {
    //             return response()->json([
    //                 'status' => true,
    //                 'location' => $location->location_name,
    //                 'description' => $description,
    //                 'msg' => "Prices coming shortly. Prices are not available right now. We'll update them soon.",
    //                 'prices' => []
    //             ], 200);
    //         }

    //         $groupedPrices = $prices->groupBy('category_id')->map(function ($items, $catId) {
    //             $categoryName = optional($items->first()->category)->category_name ?? 'Unknown';
    //             return [
    //                 'category' => $categoryName,
    //                 'prices' => $items->map(function ($item) {
    //                     return [
    //                         'size' => $item->size,
    //                         'today_price' => (float) $item->price
    //                     ];
    //                 })->values()
    //             ];
    //         })->values();

    //         return response()->json([
    //             'status' => true,
    //             'location' => $location->location_name,
    //             'description' => $description,
    //             'msg' => "Today's prices by category are available below.",
    //             'prices' => $groupedPrices
    //         ], 200);

    //     } catch (Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Something went wrong.',
    //             'description' => null,
    //             'msg' => $e->getMessage(),
    //             'prices' => []
    //         ], 200);
    //     }
    // }





}
