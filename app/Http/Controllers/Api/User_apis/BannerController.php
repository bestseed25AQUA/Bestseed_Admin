<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    /**
     * GET /api/farmer/banners/promo
     * Returns promotional slider banners (multiple)
     */
    // public function promoBanners()
    // {
    //     try {
    //         // dd("ravindra");
    //         $banners = Banner::where('screen', 'price')
    //             ->where('status', 1)
    //             // ->orderBy('order', 'asc')
    //             ->orderBy('id', 'asc')
    //             ->get();
    //         // dd( $banners);

    //         if ($banners->isEmpty()) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'No active Promotional banners found'
    //             ], 404);
    //         }

    //         $bannerList = $banners->map(function ($banner) {
    //             $filename = basename($banner->image);
    //             // $filePath = public_path('banners/' . $filename);
    //             // $fileUrl = file_exists($filePath) ? url('banners/' . $filename) : null;
    //             //  $fileUrl = url('public/banners/' . $filename);
    //             $fileUrl = asset('uploads/banners/' . $filename);
    //             $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    //             $type = in_array($extension, ['mp4', 'webm', 'mov', 'avi']) ? 'video' : 'image';

    //             return [
    //                 'id' => $banner->id,
    //                 'title' => $banner->title,
    //                 'type' => $type,
    //                 'url' => $fileUrl,
    //             ];
    //         });

    //         return response()->json([
    //             'status' => true,
    //             'promo_banners' => $bannerList
    //         ]);
    //     } catch (Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    /**
     * GET /api/farmer/banners/wanted
     * Returns wanted/sells banners (single category)
     */
    // public function wantedBanners()
    // {
    //     try {
    //         $banners = Banner::where('screen', 'wanted')
    //             ->where('status', 1)
    //             // ->orderBy('order', 'asc')
    //             ->orderBy('id', 'asc')
    //             ->get();

    //         if ($banners->isEmpty()) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'No active Wanted banners found'
    //             ], 404);
    //         }

    //         $bannerList = $banners->map(function ($banner) {
    //             $filename = basename($banner->image);
    //             // $filePath = public_path('banners/' . $filename);
    //             // $fileUrl = file_exists($filePath) ? url('banners/' . $filename) : null;
    //             $fileUrl = asset('uploads/banners/' . $filename);
    //             $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    //             $type = in_array($extension, ['mp4', 'webm', 'mov', 'avi']) ? 'video' : 'image';

    //             return [
    //                 'id' => $banner->id,
    //                 'title' => $banner->title,
    //                 'type' => $type,
    //                 'url' => $fileUrl,
    //             ];
    //         });

    //         return response()->json([
    //             'status' => true,
    //             'wanted_banners' => $bannerList
    //         ]);
    //     } catch (Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }


    /**
 * ===============================
 * GET /api/farmer/banner
 * ===============================
 * Fetch dynamic banner for Vehicle Availability
 * Filters: category_id (optional), location_id (optional)
 */
// public function banner(Request $request)
// {
//     try {
//         // Get optional category_id and location_id from query params
//         $categoryId = $request->query('category_id');
//         $locationId = $request->query('location_id');

//         // Base query: banners related to Vehicle Availability
//         $query = Banner::where('screen', 'home')
//             ->where('status', 1);

//         // If category_id is sent, filter by it
//         if ($categoryId) {
//             $query->where('category_id', $categoryId);
//         }

//         // If location_id is sent, filter by it
//         if ($locationId) {
//             $query->where('location_id', $locationId);
//         }

//         // Fetch banners
//         $banners = $query->orderBy('id', 'asc')->get();

//         // If no banners found
//         if ($banners->isEmpty()) {
//             return response()->json([
//                 'status' => false,
//                 'message' => $categoryId || $locationId
//                     ? 'No banners found for the given filters'
//                     : 'No active Vehicle Availability banners found'
//             ], 404);
//         }

//         // Map banners with URL and file type
//         $bannerList = $banners->map(function ($banner) {
//             $filename = basename($banner->image);
//             $filePath = public_path('uploads/banners/' . $filename);
//             $fileUrl = file_exists($filePath) ? url('uploads/banners/' . $filename) : null;

//             $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
//             $type = in_array($extension, ['mp4', 'webm', 'mov', 'avi', 'wmv']) ? 'video' : 'image';

//             return [
//                 'id' => $banner->id,
//                 'title' => $banner->title,
//                 'category_id' => $banner->category_id ?? null,
//                 'location_id' => $banner->location_id ?? null,
//                 'type' => $type,
//                 'url' => $fileUrl,
//             ];
//         });

//         // Return JSON response
//         return response()->json([
//             'status' => true,
//             'banners' => $bannerList
//         ]);
//     } catch (Exception $e) {
//         return response()->json([
//             'status' => false,
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }



public function bannerBackground(Request $request)
{
    try {
        // Get optional category_id and location_id from query params
        $categoryId = $request->query('category_id');
        $locationId = $request->query('location_id');

        // Base query: banners related to Vehicle Availability
        $query = Banner::where('screen', 'home_bg')
            ->where('status', 1);

        // If category_id is sent, filter by it
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        // If location_id is sent, filter by it
        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        // Fetch banners
        $banners = $query->orderBy('id', 'asc')->get();

        // If no banners found
        if ($banners->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => $categoryId || $locationId
                    ? 'No banners found for the given filters'
                    : 'No bg banners found'
            ], 404);
        }

        // Map banners with URL and file type
        $bannerList = $banners->map(function ($banner) {
            // Use the stored image path directly from database
            $fileUrl = $banner->image ? url($banner->image) : null;

            $extension = strtolower(pathinfo($banner->image ?? '', PATHINFO_EXTENSION));
            $type = in_array($extension, ['mp4', 'webm', 'mov', 'avi', 'wmv']) ? 'video' : 'image';

            return [
                'id' => $banner->id,
                'title' => $banner->title,
                'category_id' => $banner->category_id ?? null,
                'location_id' => $banner->location_id ?? null,
                'type' => $type,
                'url' => $fileUrl,
            ];
        });

        // Return JSON response
        return response()->json([
            'status' => true,
            'banners' => $bannerList
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}


public function bannerTopBackground(Request $request)
{
    try {
        // Get optional category_id and location_id from query params
        $categoryId = $request->query('category_id');
        $locationId = $request->query('location_id');

        // Base query: banners related to Vehicle Availability
        $query = Banner::where('screen', 'home_top_bg')
            ->where('status', 1);

        // If category_id is sent, filter by it
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        // If location_id is sent, filter by it
        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        // Fetch banners
        $banners = $query->orderBy('id', 'asc')->get();

        // If no banners found
        if ($banners->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => $categoryId || $locationId
                    ? 'No banners found for the given filters'
                    : 'No bg banners found'
            ], 404);
        }

        // Map banners with URL and file type
        $bannerList = $banners->map(function ($banner) {
            // Use the stored image path directly from database
            $fileUrl = $banner->image ? url($banner->image) : null;

            $extension = strtolower(pathinfo($banner->image ?? '', PATHINFO_EXTENSION));
            $type = in_array($extension, ['mp4', 'webm', 'mov', 'avi', 'wmv']) ? 'video' : 'image';

            return [
                'id' => $banner->id,
                'title' => $banner->title,
                'category_id' => $banner->category_id ?? null,
                'location_id' => $banner->location_id ?? null,
                'type' => $type,
                'url' => $fileUrl,
            ];
        });

        // Return JSON response
        return response()->json([
            'status' => true,
            'banners' => $bannerList
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

public function bestDealsBackground(Request $request)
{
    try {
        // Get optional category_id and location_id from query params
        $categoryId = $request->query('category_id');
        $locationId = $request->query('location_id');

        // Base query: banners related to Vehicle Availability
        $query = Banner::where('screen', 'home_best_deals')
            ->where('status', 1);

        // If category_id is sent, filter by it
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        // If location_id is sent, filter by it
        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        // Fetch banners
        $banners = $query->orderBy('id', 'asc')->get();

        // If no banners found
        if ($banners->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => $categoryId || $locationId
                    ? 'No banners found for the given filters'
                    : 'No bg banners found'
            ], 404);
        }

        // Map banners with URL and file type
        $bannerList = $banners->map(function ($banner) {
            // Use the stored image path directly from database
            $fileUrl = $banner->image ? url($banner->image) : null;

            $extension = strtolower(pathinfo($banner->image ?? '', PATHINFO_EXTENSION));
            $type = in_array($extension, ['mp4', 'webm', 'mov', 'avi', 'wmv']) ? 'video' : 'image';

            return [
                'id' => $banner->id,
                'title' => $banner->title,
                'category_id' => $banner->category_id ?? null,
                'location_id' => $banner->location_id ?? null,
                'type' => $type,
                'url' => $fileUrl,
            ];
        });

        // Return JSON response
        return response()->json([
            'status' => true,
            'banners' => $bannerList
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}


public function homeBanner(Request $request)
{
    try {
        $query = Banner::where('screen', 'home_banner')
            ->where('status', 1);

        if ($request->query('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }
        if ($request->query('location_id')) {
            $query->where('location_id', $request->query('location_id'));
        }

        $banners = $query->orderBy('id', 'asc')->get();

        if ($banners->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No home banners found',
                'banners' => []
            ], 200);
        }

        $bannerList = $banners->map(function ($banner) {
            $fileUrl = $banner->image ? url($banner->image) : null;
            $extension = strtolower(pathinfo($banner->image ?? '', PATHINFO_EXTENSION));
            $type = in_array($extension, ['mp4', 'webm', 'mov', 'avi', 'wmv']) ? 'video' : 'image';
            $thumbnailUrl = $banner->thumbnail ? url($banner->thumbnail) : null;

            return [
                'id' => $banner->id,
                'title' => $banner->title,
                'category_id' => $banner->category_id ?? null,
                'location_id' => $banner->location_id ?? null,
                'type' => $type,
                'url' => $fileUrl,
                'thumbnail' => $thumbnailUrl,
            ];
        });

        return response()->json([
            'status' => true,
            'banners' => $bannerList
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

public function seedPriceBanner(Request $request)
{
    try {
        $query = Banner::where('screen', 'seed_price_banner')
            ->where('status', 1);

        if ($request->query('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }
        if ($request->query('location_id')) {
            $query->where('location_id', $request->query('location_id'));
        }

        $banners = $query->orderBy('id', 'asc')->get();

        if ($banners->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No seed price banners found',
                'banners' => []
            ], 200);
        }

        $bannerList = $banners->map(function ($banner) {
            $fileUrl = $banner->image ? url($banner->image) : null;
            $extension = strtolower(pathinfo($banner->image ?? '', PATHINFO_EXTENSION));
            $type = in_array($extension, ['mp4', 'webm', 'mov', 'avi', 'wmv']) ? 'video' : 'image';

            return [
                'id' => $banner->id,
                'title' => $banner->title,
                'category_id' => $banner->category_id ?? null,
                'location_id' => $banner->location_id ?? null,
                'type' => $type,
                'url' => $fileUrl,
            ];
        });

        return response()->json([
            'status' => true,
            'banners' => $bannerList
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

public function spotHatcheriesIcon(Request $request)
{
    try {
        $banners = Banner::where('screen', 'spot_hatcheries_icon')
            ->where('status', 1)
            ->orderBy('id', 'asc')
            ->get();

        if ($banners->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No spot hatcheries icon found',
                'banners' => []
            ], 200);
        }

        $bannerList = $banners->map(function ($banner) {
            $fileUrl = $banner->image ? url($banner->image) : null;
            $extension = strtolower(pathinfo($banner->image ?? '', PATHINFO_EXTENSION));
            $type = in_array($extension, ['mp4', 'webm', 'mov', 'avi', 'wmv']) ? 'video' : 'image';

            return [
                'id' => $banner->id,
                'title' => $banner->title,
                'type' => $type,
                'url' => $fileUrl,
            ];
        });

        return response()->json([
            'status' => true,
            'banners' => $bannerList
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

public function farmManagementIcon(Request $request)
{
    try {
        $banners = Banner::where('screen', 'farm_management_icon')
            ->where('status', 1)
            ->orderBy('id', 'asc')
            ->get();

        if ($banners->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No farm management icon found',
                'banners' => []
            ], 200);
        }

        $bannerList = $banners->map(function ($banner) {
            $fileUrl = $banner->image ? url($banner->image) : null;
            $extension = strtolower(pathinfo($banner->image ?? '', PATHINFO_EXTENSION));
            $type = in_array($extension, ['mp4', 'webm', 'mov', 'avi', 'wmv']) ? 'video' : 'image';

            return [
                'id' => $banner->id,
                'title' => $banner->title,
                'type' => $type,
                'url' => $fileUrl,
            ];
        });

        return response()->json([
            'status' => true,
            'banners' => $bannerList
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

public function homeSection1Background(Request $request)
{
    try {
        $banners = Banner::where('screen', 'home_section1_bg')
            ->where('status', 1)
            ->orderBy('id', 'asc')
            ->get();

        if ($banners->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No section1 background found',
                'banners' => []
            ], 200);
        }

        $bannerList = $banners->map(function ($banner) {
            $fileUrl = $banner->image ? url($banner->image) : null;
            $extension = strtolower(pathinfo($banner->image ?? '', PATHINFO_EXTENSION));
            $type = in_array($extension, ['mp4', 'webm', 'mov', 'avi', 'wmv']) ? 'video' : 'image';

            return [
                'id' => $banner->id,
                'title' => $banner->title,
                'type' => $type,
                'url' => $fileUrl,
            ];
        });

        return response()->json([
            'status' => true,
            'banners' => $bannerList
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
 * GET /api/farmer/banners/updates
 * Returns banners for the "Updates" slider (using screen column)
 */
// public function updatesBanners()
// {
//     try {
//         $banners = Banner::where('screen', 'update')
//             ->where('status', 1)
//             ->orderBy('id', 'asc')
//             ->get();

//         if ($banners->isEmpty()) {
//             return response()->json([
//                 'status' => false,
//                 'message' => 'No active Updates banners found'
//             ], 404);
//         }

//         $bannerList = $banners->map(function ($banner) {
//             $filename = basename($banner->image);
//             $fileUrl = asset('uploads/banners/' . $filename);
//             $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
//             $type = in_array($extension, ['mp4', 'webm', 'mov', 'avi']) ? 'video' : 'image';

//             return [
//                 'id' => $banner->id,
//                 'screen' => $banner->screen,
//                 'type' => $type,
//                 'url' => $fileUrl,
//             ];
//         });

//         return response()->json([
//             'status' => true,
//             'updates_banners' => $bannerList
//         ]);
//     } catch (Exception $e) {
//         Log::error('updatesBanners error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
//         return response()->json([
//             'status' => false,
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }


/**
 * GET /api/farmer/banners/spot-hatcheries
 * Returns banners for the "Spot Hatcheries" slider (using screen column)
 */
// public function spotHatcheriesBanners()
// {
//     try {
//         $banners = Banner::where('screen', 'spothatchery')
//             ->where('status', 1)
//             ->orderBy('id', 'asc')
//             ->get();

//         if ($banners->isEmpty()) {
//             return response()->json([
//                 'status' => true,
//                 'message' => 'No active Spot Hatcheries banners found',
//                 'spot_hatcheries_banners' => []
//             ], 200);
//         }

//         $bannerList = $banners->map(function ($banner) {
//             $filename = basename($banner->image);
//             $fileUrl = asset('uploads/banners/' . $filename);
//             $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
//             $type = in_array($extension, ['mp4', 'webm', 'mov', 'avi']) ? 'video' : 'image';

//             return [
//                 'id' => $banner->id,
//                 'screen' => $banner->screen,
//                 'type' => $type,
//                 'url' => $fileUrl,
//             ];
//         });

//         return response()->json([
//             'status' => true,
//             'spot_hatcheries_banners' => $bannerList
//         ]);
//     } catch (Exception $e) {
//         return response()->json([
//             'status' => false,
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }

/**
 * GET /api/farmer/banners/hatchery/{id}
 * Returns banners for a specific hatchery (using screen column)
 */
// public function hatcheryBanners($hatcheryId)
// {
//     try {
//         $banners = Banner::where('hatchery_id', $hatcheryId)
//             ->where('screen', 'hatcherybanner')
//             ->where('status', 1)
//             ->orderBy('id', 'asc')
//             ->get();

//         if ($banners->isEmpty()) {
//             return response()->json([
//                 'status' => true,
//                 'message' => 'No banners found for this hatchery',
//                 'banners' => []
//             ], 200);
//         }

//         $bannerList = $banners->map(function ($banner) {
//             $filename = basename($banner->image);
//             $fileUrl = asset('uploads/banners/' . $filename);
//             $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
//             $type = in_array($extension, ['mp4', 'webm', 'mov', 'avi']) ? 'video' : 'image';

//             return [
//                 'id' => $banner->id,
//                 'hatchery_id' => $banner->hatchery_id,
//                 'screen' => $banner->screen,
//                 'type' => $type,
//                 'url' => $fileUrl,
//             ];
//         });

//         return response()->json([
//             'status' => true,
//             'banners' => $bannerList
//         ], 200);
//     } catch (Exception $e) {
//         return response()->json([
//             'status' => false,
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }



}

