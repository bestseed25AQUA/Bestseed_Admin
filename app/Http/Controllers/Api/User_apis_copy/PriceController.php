<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\MarketPrice;
use App\Models\HatcheryCategory;
use App\Models\HatcheryLocation;
use Illuminate\Http\Request;
use Exception;

class PriceController extends Controller
{
    public function getSeedPrices(Request $request)
    {
        try {
            $categoryId = $request->input('category_id');
            $locationId = $request->input('location_id');

            if (!$categoryId || !$locationId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Both category_id and location_id are required.',
                    'description' => null,
                    'msg' => null,
                    'prices' => []
                ], 200);
            }

            $category = HatcheryCategory::find($categoryId);
            $location = HatcheryLocation::find($locationId);

            if (!$category || !$location) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid category or location.',
                    'description' => null,
                    'msg' => null,
                    'prices' => []
                ], 200);
            }

            $prices = MarketPrice::where('species', $category->category_name)
                ->where('location', $location->location_name)
                ->orderBy('size', 'asc')
                ->get(['size', 'price']);

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
                    'size' => $item->size,
                    'today_price' => (float) $item->price
                ];
            });

            return response()->json([
                'status' => true,
                'category' => $category->category_name,
                'location' => $location->location_name,
                'description' => $description,
                'msg' => "Today's prices are available below.",
                'prices' => $formattedPrices
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong.',
                'description' => null,
                'msg' => $e->getMessage(),
                'prices' => []
            ], 200);
        }
    }
}
