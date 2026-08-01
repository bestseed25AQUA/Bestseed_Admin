<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Helpers\BannerHelper;
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
    public function PriceBanners()
    {
        try {
            $banners = Banner::where('title', 'Price')
                ->where('status', 1)
                // ->orderBy('order', 'asc')
                ->orderBy('id', 'asc')
                ->get();

                //       $banners = Banner::where('screen', 'home')
                // ->where('status', 1)
                // // ->orderBy('order', 'asc')
                // ->orderBy('priority')
                // ->get();

            if ($banners->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active Price banners found'
                ], 404);
            }

            $bannerList = $banners->map(function ($banner) {
                $filename = basename($banner->image);
                // $filePath = public_path('banners/' . $filename);
                // $fileUrl = file_exists($filePath) ? url('banners/' . $filename) : null;
                //  $fileUrl = url('public/banners/' . $filename);
                 $fileUrl = asset('public/uploads/banners/' . $filename);
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $type = in_array($extension, ['mp4', 'webm', 'mov', 'avi']) ? 'video' : 'image';

                return [
                    'id' => $banner->id,
                    'title' => $banner->title,
                    'type' => $type,
                    'url' => $fileUrl,
                ];
            });

            return response()->json([
                'status' => true,
                'Price_banners' => $bannerList
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/farmer/banners/wanted
     * Returns wanted/sells banners (single category)
     */
    public function wantedBanners()
    {
        try {
            $banners = Banner::where('title', 'Wanted')
                ->where('status', 1)
                // ->orderBy('order', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            if ($banners->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active Wanted banners found'
                ], 404);
            }

            $bannerList = $banners->map(function ($banner) {
                $filename = basename($banner->image);
                // $filePath = public_path('banners/' . $filename);
                // $fileUrl = file_exists($filePath) ? url('banners/' . $filename) : null;
                $fileUrl = url('public/uploads/banners/' . $filename);
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $type = in_array($extension, ['mp4', 'webm', 'mov', 'avi']) ? 'video' : 'image';

                return [
                    'id' => $banner->id,
                    'title' => $banner->title,
                    'type' => $type,
                    'url' => $fileUrl,
                ];
            });

            return response()->json([
                'status' => true,
                'wanted_banners' => $bannerList
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
     * Returns banners for the "Updates" slider
     */
    public function updatesBanners()
    {
        try {
            $banners = Banner::where('title', 'Updates')
                ->where('status', 1)
                ->orderBy('id', 'asc')
                ->get();

            if ($banners->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active Updates banners found'
                ], 404);
            }

            $bannerList = $banners->map(function ($banner) {
                $filename = basename($banner->image);
                $fileUrl = url('public/uploads/banners/' . $filename);
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $type = in_array($extension, ['mp4', 'webm', 'mov', 'avi']) ? 'video' : 'image';

                return [
                    'id' => $banner->id,
                    'title' => $banner->title,
                    'type' => $type,
                    'url' => $fileUrl,
                ];
            });

            return response()->json([
                'status' => true,
                'updates_banners' => $bannerList
            ]);

        }
        //  catch (Exception $e) {
        //     return response()->json([
        //         'status' => false,
        //         'error' => $e->getMessage()
        //     ], 500);
        //  }
        //  }
        catch (Exception $e) {
            Log::error('updatesBanners error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/farmer/banners/news
     * Returns banners for the "News & Ads" slider
     */
    public function newsBanners()
    {
        try {
            // DB title assumed "News & Ads" (match exactly with your DB)
            $banners = Banner::where('title', 'News & Ads')
                ->where('status', 1)
                ->orderBy('id', 'asc')
                ->get();

            if ($banners->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active News & Ads banners found'
                ], 404);
            }

            $bannerList = $banners->map(function ($banner) {
                $filename = basename($banner->image);
                $fileUrl = url('public/banners/' . $filename);
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $type = in_array($extension, ['mp4', 'webm', 'mov', 'avi']) ? 'video' : 'image';

                return [
                    'id' => $banner->id,
                    'title' => $banner->title,
                    'type' => $type,
                    'url' => $fileUrl,
                ];
            });

            return response()->json([
                'status' => true,
                'news_banners' => $bannerList
            ]);

        }

    //      catch (Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
        catch (Exception $e) {
            Log::error('newsBanners error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }



     /**
     * ===============================
     * GET /api/farmer/banner
     * ===============================
     * Fetch dynamic banner for Vehicle Availability
     */

         public function banner()
    {
        try {
            // Fetch all active banners for Vehicle Availability
            $banners = Banner::where('title', 'Vehicle Availability')
                ->where('status', 1)
                ->orderBy('id', 'asc')
                ->get();

            if ($banners->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active Vehicle Availability banners found'
                ], 404);
            }

            $bannerList = $banners->map(function ($banner) {
                // Get the filename
                $filename = basename($banner->image);

                // Check if file exists in public/banners/
                $filePath = public_path('banners/uploads/' . $filename);
                $fileUrl = file_exists($filePath) ? url('public/banners/' . $filename) : null;

                // Determine file type (image or video)
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $type = in_array($extension, ['mp4', 'webm', 'mov', 'avi']) ? 'video' : 'image';

                return [
                    'id' => $banner->id,
                    'title' => $banner->title,
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


      /**
 * ==========================================
 * GET /api/farmer/banners/spot-hatcheries
 * ==========================================
 * Returns banners for the "Spot Hatcheries" slider
 * ------------------------------------------
 * Similar to promoBanners(), but fetches
 * banners where title = 'Spot Hatcheries'
 * ------------------------------------------
 */
public function spotHatcheriesBanners()
{
    try {
        // Fetch all active spot hatchery banners
        $banners = Banner::where('title', 'Spot Hatcheries')
            ->where('status', 1)
            ->orderBy('id', 'asc')
            ->get();

        // If no active banners found
        if ($banners->isEmpty()) {
            return response()->json([
                'status' => true,
                'message' => 'No active Spot Hatcheries banners found',
                'spot_hatcheries_banners' => []
            ], 200); // <-- changed to 200 OK
        }

        // Format banner list
        $bannerList = $banners->map(function ($banner) {
            $filename = basename($banner->image);
            $fileUrl = asset('public/uploads/banners/' . $filename); // dynamic public path

            // detect media type
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $type = in_array($extension, ['mp4', 'webm', 'mov', 'avi']) ? 'video' : 'image';

            return [
                'id' => $banner->id,
                'title' => $banner->title,
                'type' => $type,
                'url' => $fileUrl,
            ];
        });

        // return successful response
        return response()->json([
            'status' => true,
            'spot_hatcheries_banners' => $bannerList
        ]);

    } catch (Exception $e) {
        return response()->json([
            'status' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}


/**
 * ==========================================
 * GET /api/farmer/banners/hatcheries
 * ==========================================
 * Returns banners for the "Hatcheries" slider
 * ------------------------------------------
 */
// public function hatcheriesBanners()
// {
//     try {
//         // Fetch all active hatchery banners
//         $banners = Banner::where('title', 'Hatcheries_banners')
//             ->where('status', 1)
//             ->orderBy('id', 'asc')
//             ->get();

//         // If no active banners found
//         if ($banners->isEmpty()) {
//             return response()->json([
//                 'status' => true,
//                 'message' => 'No active Hatcheries banners found',
//                 'hatcheries_banners' => []
//             ], 200);
//         }

//         // Format banner list
//         $bannerList = $banners->map(function ($banner) {
//             $filename = basename($banner->image);
//             $fileUrl = asset('public/banners/' . $filename);

//             // detect media type
//             $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
//             $type = in_array($extension, ['mp4', 'webm', 'mov', 'avi']) ? 'video' : 'image';

//             return [
//                 'id' => $banner->id,
//                 'title' => $banner->title,
//                 'type' => $type,
//                 'url' => $fileUrl,
//             ];
//         });

//         // return successful response
//         return response()->json([
//             'status' => true,
//             'hatcheries_banners' => $bannerList
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
 * Returns banners for a specific hatchery
 */
public function hatcheryBanners($hatcheryId)
{
    try {
        // Fetch active banners for this hatchery
        $banners = Banner::where('hatchery_id', $hatcheryId)
            ->where('status', 1)
            ->orderBy('id', 'asc')
            ->get();

        if ($banners->isEmpty()) {
            return response()->json([
                'status' => true,
                'message' => 'No banners found for this hatchery',
                'banners' => []
            ], 200);
        }

        $bannerList = $banners->map(function ($banner) {
            $filename = basename($banner->image);
            $fileUrl = asset('public/uploads/banners/' . $filename);
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $type = in_array($extension, ['mp4', 'webm', 'mov', 'avi']) ? 'video' : 'image';

            return [
                'id' => $banner->id,
                 'hatchery_id' => $banner->hatchery_id,
                'title' => $banner->title,
                'type' => $type,
                'url' => $fileUrl,
            ];
        });

        return response()->json([
            'status' => true,
            'banners' => $bannerList
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}




}


