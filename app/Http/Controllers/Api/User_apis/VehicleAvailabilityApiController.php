<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\Hatchery;
use App\Models\VehicleAvailability;
use App\Models\HatcheryLocation;
use Illuminate\Http\Request;

class VehicleAvailabilityApiController extends Controller
{


    //     public function getVehicleAvailabilityList()
// {
//     try {
//         $hatcheries = Hatchery::with(['vendor', 'category', 'location'])
//             ->where('is_vehicle', true)
//             ->orderBy('id', 'desc')
//             ->get()
//             ->map(function ($item) {
//                 $mobile = $item->vendor ? preg_replace('/\D/', '', $item->vendor->mobile) : null;

    //                 // ✅ No need to decode — already handled by accessor
//                 $images = is_array($item->image) ? $item->image : [];

    //                 return [
//                     'hatchery_id'   => $item->id,
//                     'hatchery_name' => $item->hatchery_name,
//                     'category_id'   => $item->category_id,
//                     'category_name' => $item->category->category_name ?? null,
//                     'location_id'   => $item->location_id,
//                     'location_name' => $item->location->location_name ?? null,
//                     'is_vehicle'       => (bool) $item->is_vehicle,
//                     'available_on'  => $item->available_on,
//                     'images'        => $images, // ✅ clean + full URLs from accessor
//                     'call_url'      => $mobile ? "tel:+$mobile" : null,
//                     'whatsapp_url'  => $mobile ? "https://wa.me/$mobile" : null,
//                 ];
//             });

    //         return response()->json([
//             'status' => true,
//             'vehicle_availability' => $hatcheries,
//         ], 200);

    //     } catch (\Throwable $e) {
//         return response()->json([
//             'status' => false,
//             'message' => 'Something went wrong, please try again later.',
//             'error' => $e->getMessage(),
//             'vehicle_availability' => [],
//         ], 200);
//     }
// }

    public function getVehicleAvailabilityList()
    {
        try {
            $vehicleAvailabilities = VehicleAvailability::with([
                'hatchery.vendor',
                'hatchery.category',
                'hatchery.location',
                'hatchery.branch',
                'vendor',
                'category',
                'location',
                'gallery',
            ])
                ->where('is_active', true)
                // Drop vehicles whose availability window has closed — the end
                // date is inclusive, so one ending today is still listed today.
                ->notExpired()
                ->orderBy('id', 'desc')
                ->get()
                ->map(function ($item) {
                    // Use vehicle availability's own vendor for contact
                    $mobile = $item->vendor
                        ? preg_replace('/\D/', '', $item->vendor->mobile)
                        : null;

                    // Get gallery images from vehicle_gallery table
                    $galleryImages = $item->gallery->map(function ($g) {
                        return asset($g->file_path);
                    })->toArray();

                    // Get location names from location_ids array.
                    // Preserve the admin's chosen route order — the JSON column
                    // `location_ids` stores ids in the order the admin picked
                    // them (e.g. Chennai → Vijayawada → Amalapuram → Nellore),
                    // but whereIn()->get() returns rows in DB (id) order and
                    // loses that sequence. Re-sort by the position in
                    // $locationIds so the user app renders the exact route the
                    // admin configured — no nearest-first, no zigzag.
                    $locationIds = $item->location_ids ?? [];
                    $locations = HatcheryLocation::whereIn('id', $locationIds)
                        ->get()
                        ->sortBy(fn ($loc) => array_search($loc->id, $locationIds))
                        ->values();

                    $locationDetails = $locations->map(function ($loc) {
                        return [
                            'location_id' => $loc->id,
                            'location_name' => $loc->location_name,
                            // lat/lng required by the user app's "Current Location"
                            // (within 100 km radius) filter.
                            'latitude' => $loc->latitude !== null ? (float) $loc->latitude : null,
                            'longitude' => $loc->longitude !== null ? (float) $loc->longitude : null,
                        ];
                    })->toArray();

                    // Get selected/parent hatchery data
                    $selectedHatcheryData = null;
                    if ($item->hatchery) {
                        $parentHatchery = $item->hatchery;
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
                        'vehicle_id' => $item->id,
                        'vehicle_name' => $item->vehicle_name,

                        'category_id' => $item->category_id,
                        'category_name' => $item->category->category_name ?? null,

                        'price' => $item->price,
                        'description' => $item->description,

                        'location_id' => $item->location_id,
                        'location_name' => $item->location->location_name ?? null,
                        // Top-level lat/lng (primary location) — used by the
                        // user app's 100 km radius filter as a fallback when
                        // `locations[]` is empty.
                        'latitude' => $item->location && $item->location->latitude !== null
                            ? (float) $item->location->latitude
                            : null,
                        'longitude' => $item->location && $item->location->longitude !== null
                            ? (float) $item->location->longitude
                            : null,
                        'branch_name' => $item->hatchery?->branch?->branch_name,
                        'locations' => $locationDetails,

                        'is_vehicle' => true,
                        'available_on' => $item->available_on ? $item->available_on->format('Y-m-d') : null,
                        'start_date' => $item->start_date ? $item->start_date->format('Y-m-d') : null,
                        'end_date' => $item->end_date ? $item->end_date->format('Y-m-d') : null,

                        'start_time' => $item->start_time ? \Carbon\Carbon::parse($item->start_time)->format('H:i') : null,
                        'end_time' => $item->end_time ? \Carbon\Carbon::parse($item->end_time)->format('H:i') : null,

                        'available_space' => $item->available_space,

                        'images' => $galleryImages,

                        'call_url' => $mobile ? "tel:+$mobile" : null,
                        'whatsapp_url' => $mobile ? "https://wa.me/$mobile" : null,

                        // Selected/parent hatchery data for "View more about hatchery"
                        'selected_hatchery_id' => $item->hatchery_id,
                        'selected_hatchery' => $selectedHatcheryData,
                    ];
                });

            return response()->json([
                'status' => true,
                'vehicle_availability' => $vehicleAvailabilities->values(),
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong, please try again later.',
                'error' => $e->getMessage(),
                'vehicle_availability' => [],
            ], 200);
        }
    }

}
