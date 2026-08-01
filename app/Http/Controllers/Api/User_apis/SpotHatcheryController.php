<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\Hatchery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SpotHatcheryController extends Controller
{
    public function getSpotHatcheriesList()
    {
        try {
            $hatcheries = Hatchery::active()->with(['vendor', 'category', 'location', 'branch', 'broodstock', 'selectedHatchery', 'selectedHatchery.vendor', 'selectedHatchery.category', 'selectedHatchery.location'])
                ->where('is_spot', true)
                ->where('status', 'active')
                ->orderBy('id', 'desc')
                ->get()
                ->map(function ($item) {

                    // Use price from spot hatchery itself, fallback to category_prices
                    $price = $item->price;
                    if (empty($price)) {
                        $categoryId = (string) $item->category_id;
                        $prices = $item->category_prices ?? [];
                        if (isset($prices['shrimp'][$categoryId])) {
                            $price = $prices['shrimp'][$categoryId];
                        } elseif (isset($prices['fish'][$categoryId])) {
                            $price = $prices['fish'][$categoryId];
                        }
                    }

                    $mobile = $item->vendor
                        ? preg_replace('/\D/', '', $item->vendor->mobile)
                        : null;

                    $images = is_array($item->image) ? $item->image : [];

                    Log::info('Selected Images', [
                        'hatchery_id' => $item->id,
                        'images' => $images,
                        'raw_image_column' => $item->image,
                    ]);
                    // Use broodstock_count from spot hatchery itself, fallback to broodstock relation
                    $brood = $item->broodstock_count;
                    if (empty($brood)) {
                        $brood = $item->broodstock->first()->count ?? null;
                    }

                    // Get selected/parent hatchery data
                    $selectedHatcheryData = null;
                    if ($item->selectedHatchery) {
                        $parentHatchery = $item->selectedHatchery;
                        $parentMobile = $parentHatchery->vendor
                            ? preg_replace('/\D/', '', $parentHatchery->vendor->mobile)
                            : null;
                        $parentImages = is_array($parentHatchery->image) ? $parentHatchery->image : [];

                        $selectedHatcheryData = [
                            'id' => $parentHatchery->id,
                            'hatchery_name' => $parentHatchery->hatchery_name,
                            'category_id' => $parentHatchery->category_id,
                            'category_name' => $parentHatchery->category->category_name ?? null,
                            'location_id' => $parentHatchery->location_id,
                            'location_name' => $parentHatchery->location->location_name ?? null,
                            'description' => $parentHatchery->description,
                            'price' => $parentHatchery->price,
                            'broodstock_count' => $parentHatchery->broodstock_count,
                            'available_on' => $parentHatchery->available_on,
                            'images' => $parentImages,
                            'call_url' => $parentHatchery->call_number ? "tel:+91" . $parentHatchery->call_number : ($parentMobile ? "tel:+$parentMobile" : null),
                            'whatsapp_url' => $parentHatchery->whatsapp_number ? "https://wa.me/" . $parentHatchery->whatsapp_number : ($parentMobile ? "https://wa.me/$parentMobile" : null),
                        ];
                    }

                    return [
                        'hatchery_id' => $item->id,
                        'hatchery_name' => $item->hatchery_name,

                        'category_id' => $item->category_id,
                        'category_name' => $item->category->category_name ?? null,

                        'price' => $price,
                        'description' => $item->description,

                        'location_id' => $item->location_id,
                        'location_name' => $item->location->location_name ?? null,
                        // Get branch from pivot table (branch_hatchery), not from hatchery.branch_id
                        'branch_name' => (function () use ($item) {
                            $pivotBranch = \App\Models\BranchHatchery::where('hatchery_id', $item->id)
                                ->first();
                            if ($pivotBranch && $pivotBranch->branch) {
                                return $pivotBranch->branch->branch_name;
                            }
                            // Fallback to direct branch relationship
                            return $item->branch?->branch_name;
                        })(),

                        'is_spot' => (bool) $item->is_spot,
                        'available_on' => $item->available_on,

                        'broodstock' => $brood,
                        'no_of_pieces' => $item->no_of_pieces,

                        'images' => $images,

                        'call_url' => $item->call_number ? "tel:+91" . $item->call_number : ($mobile ? "tel:+$mobile" : null),
                        'whatsapp_url' => $item->whatsapp_number ? "https://wa.me/" . $item->whatsapp_number : ($mobile ? "https://wa.me/$mobile" : null),

                        // Selected/parent hatchery data for "View more about hatchery"
                        'selected_hatchery_id' => $item->selected_hatchery_id,
                        'selected_hatchery' => $selectedHatcheryData,
                    ];
                });

            return response()->json([
                'status' => true,
                'spot_hatcheries' => $hatcheries,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong, please try again later.',
                'error' => $e->getMessage(),
                'spot_hatcheries' => [],
            ], 200);
        }
    }

}
