<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\Hatchery;
use Illuminate\Http\Request;

class SpotHatcheryController extends Controller
{
    public function getSpotHatcheriesList()
    {
        try {
//            $data = Hatchery::with(['vendor', 'category', 'location'])
//     ->orderBy('id', 'desc')
//     ->get();

// dd($data->first()->category->category_name);

            $hatcheries = Hatchery::with(['vendor', 'category', 'location'])
                ->orderBy('id', 'desc')
                ->get()
                // dd( $hatcheries);
                ->map(function ($item) {
                    $mobile = $item->vendor ? preg_replace('/\D/', '', $item->vendor->mobile) : null;

                    // Prepare all image URLs
                    $images = [];
                    if ($item->image && is_array($item->image)) {
                        foreach ($item->image as $img) {
                            $images[] = asset('/public/hatcheries/' . basename($img));
                        }
                    }

                    return [
                        'hatchery_id' => $item->id,
                        'hatchery_name' => $item->hatchery_name,
                        'category_name' => $item->category ? $item->category->category_name : null,
                        // 'location' => $item->location ? $item->location->location_name : null,
                        // 'location' => $item->location ? $item->location->location_name : $item->location,
                        'location' => $item->location, // just return string from hatcheries table
                        'available_on' => $item->available_on,
                        'images' => $images, // now returns all images
                        'call_url' => $mobile ? "tel:+$mobile" : null,
                        'whatsapp_url' => $mobile ? "https://wa.me/$mobile" : null,
                    ];
                });

            return response()->json([
                'status' => true,
                'spot_hatcheries' => $hatcheries
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage()
            ], 500);
        }

    }
}
