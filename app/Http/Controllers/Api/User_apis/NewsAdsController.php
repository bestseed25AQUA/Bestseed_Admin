<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\News;
use Exception;

class NewsAdsController extends Controller
{


//     public function homeNews(Request $request)
// {
//     try {
//         $categoryId = $request->category_id;
//         $locationId = $request->location_id;

//         $result = [];

//         // ✅ Only fetching medicine news
//         $query = News::where('type', 'medicine news')
//             ->where('status', 1)
//             ->where('is_active', 1)
//             ->orderBy('priority', 'desc')
//             ->orderBy('id', 'desc');

//         if ($categoryId) {
//             $query->where('category_id', $categoryId);
//         }

//         if ($locationId) {
//             $query->where('location_id', $locationId);
//         }

//         $items = $query->take(4)->get()->map(fn($item) => $this->formatNews($item));

//         // ✅ Add only medicine news in JSON
//         $result['medicine_news'] = $items;

//         return response()->json([
//             'status' => true,
//             'message' => 'Home news fetched successfully',
//             'data' => $result,
//         ]);

//     } catch (Exception $e) {
//         return $this->errorResponse($e);
//     }
// }




//     /**
//      * 🏠 1️⃣ HOME SCREEN API
//      * GET /api/user/home-news?category_id=1&location_id=3
//      */
   public function homeNews(Request $request)
{
    try {
        $categoryId = $request->category_id;
        $locationId = $request->location_id;

        $types = ['trending update', 'medicine news', 'climate news'];
        $result = [];

        foreach ($types as $type) {
            $query = News::where('type', $type)
                ->where('status', 1)
                 ->where('is_active', 1)
                ->orderBy('priority', 'desc')
                ->orderBy('id', 'desc');

            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            if ($locationId) {
                $query->where('location_id', $locationId);
            }

            $items = $query->take(4)->get()->map(function ($item) {
                return $this->formatNews($item);
            });

            $result[str_replace(' ', '_', $type)] = $items;
        }

        return response()->json([
            'status' => true,
            'message' => 'Home news fetched successfully',
            'data' => $result,
        ]);
    } catch (Exception $e) {
        // dd($e);
        return $this->errorResponse($e);
    }
}





// private function formatNews($item)
// {
//     return [
//         'id' => $item->id,
//         'title' => $item->title,
//         'description' => $item->description,
//         'type' => $item->type,
//         'category_id' => $item->category_id,
//         'location_id' => $item->location_id,
//         'media_path' => $item->media_path ? asset($item->media_path) : null,
//         'created_at' => $item->created_at,
//         'updated_at' => $item->updated_at,
//     ];
// }


    /**
     * 📰 2️⃣ COMMON NEWS LIST (For all types)
     * GET /api/user/news?type=medicine news
     * or GET /api/user/news?type=trending update
     */
    public function getNewsByType(Request $request)
    {
        try {
            $type = $request->type;

            if (!$type) {
                return response()->json([
                    'status' => false,
                    'message' => 'Type is required (e.g., trending update, medicine news, climate news)',
                    'data' => [],
                ], 400);
            }

            $news = News::where('type', $type)
                // ->where('status', 'active')
                ->where('status', 1)
                ->where('is_active', 1)
                ->orderBy('priority', 'desc')
                ->orderBy('id', 'desc')
                ->get()
                ->map(fn($item) => $this->formatNews($item));

            return response()->json([
                'status' => true,
                'message' => ucfirst($type) . ' fetched successfully',
                'data' => $news,
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * 📍 3️⃣ SINGLE NEWS DETAILS (Common for all types)
     * GET /api/user/news/{id}?type=medicine news
     */
    public function getSingleNews($id, Request $request)
    {
        try {
            $type = $request->type;

            if (!$type) {
                return response()->json([
                    'status' => false,
                    'message' => 'Type is required (e.g., trending update, medicine news, climate news)',
                    'data' => [],
                ], 400);
            }

            $news = News::where('id', $id)
                ->where('type', $type)
                // ->where('status', 'active')
                ->where('status', 1)
                ->where('is_active', 1)
                ->first();

            if (!$news) {
                return response()->json([
                    'status' => false,
                    'message' => ucfirst($type) . ' not found',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => ucfirst($type) . ' details fetched successfully',
                'data' => $this->formatSingleNews($news),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    // 🧩 Helper functions
    // private function formatNews($item)
    // {
    //     return [
    //         'id' => $item->id,
    //         'title' => $item->title ?? '',
    //         'medicine_name' => $item->medicine_name ?? '',
    //         'cures_for' => $item->cures_for ?? '',
    //         'caption' => $item->caption ?? '',
    //         'media_type' => $item->media_type ?? '', // 🔹 added
    //         'media_path' => $item->media_path
    //         ? asset($item->media_path)
    //         : ($item->image ? url('uploads/news/' . $item->image) : ''),
    //         // 'image' => $item->image ? url('storage/news/' . $item->image) : '',
    //         // 'video_url' => $item->video_url ?? '',
    //         'created_at' => $item->created_at ? $item->created_at->format('d-m-Y') : '',
    //     ];
    // }


    private function formatNews($item)
{
    $baseMediaPath = $item->media_path
        ? asset($item->media_path)
        : ($item->image ? url('uploads/news/' . $item->image) : '');

    // Build media_files and media_types arrays with fallback for old records
    if (!empty($item->media_files) && is_array($item->media_files)) {
        $mediaFiles = array_map(fn($path) => asset($path), $item->media_files);
        $mediaTypes = $item->media_types ?? [];
    } elseif ($item->media_path) {
        $mediaFiles = [asset($item->media_path)];
        $mediaTypes = [$item->media_type ?? 'image'];
    } else {
        $mediaFiles = [];
        $mediaTypes = [];
    }

    $common = [
        'id' => $item->id,
        'media_type' => $item->media_type ?? '',
        'media_path' => $baseMediaPath,
        'media_files' => $mediaFiles,
        'media_types' => $mediaTypes,
        'created_at' => $item->created_at ? $item->created_at->format('d-m-Y') : null,
    ];

    switch (strtolower($item->type)) {
        case 'medicine news':
            return array_merge($common, [
                'medicine_name' => $item->title ?? '',
                'subtitle' => $item->subtitle ?? '',
                'cures_for' => $item->cures_for ?? '',
            ]);

        case 'trending update':
            return array_merge($common, [
                'title' => $item->title ?? '',
            ]);

        case 'climate news':
            return array_merge($common, [
                'title' => $item->title ?? '',
                'subtitle' => $item->subtitle ?? '',
            ]);

        default:
            return $common;
    }
}


    // private function formatSingleNews($item)
    // {
    //     return [
    //         'id' => $item->id,
    //         'title' => $item->title ?? '',
    //         'medicine_name' => $item->medicine_name ?? '',
    //         'cures_for' => $item->cures_for ?? '',
    //         'caption' => $item->caption ?? '',
    //         'description' => $item->description ?? '',

    //         'media_type' => $item->media_type ?? '', // 🔹 added
    //         'media_path' => $item->media_path
    //         ? asset($item->media_path)
    //         : ($item->image ? url('uploads/news/' . $item->image) : ''),
    //         // 'media_path' => $item->media_path ? asset($item->media_path) : null,
    //         // 'video_url' => $item->video_url ?? '',
    //         'whatsapp_number' => $item->whatsapp_number ?? '',
    //         'call_number' => $item->call_number ?? '',
    //     ];
    // }


    private function formatSingleNews($item)
{
    $baseMediaPath = $item->media_path
        ? asset($item->media_path)
        : ($item->image ? url('uploads/news/' . $item->image) : '');

    // Build media_files and media_types arrays with fallback for old records
    if (!empty($item->media_files) && is_array($item->media_files)) {
        $mediaFiles = array_map(fn($path) => asset($path), $item->media_files);
        $mediaTypes = $item->media_types ?? [];
    } elseif ($item->media_path) {
        $mediaFiles = [asset($item->media_path)];
        $mediaTypes = [$item->media_type ?? 'image'];
    } else {
        $mediaFiles = [];
        $mediaTypes = [];
    }

    $common = [
        'id' => $item->id,
        'title' => $item->title ?? '',
        'description' => $item->description ?? '',
        'media_type' => $item->media_type ?? '',
        'media_path' => $baseMediaPath,
        'media_files' => $mediaFiles,
        'media_types' => $mediaTypes,
    ];

    switch (strtolower($item->type)) {
        case 'medicine news':
            return array_merge($common, [
                'medicine_name' => $item->medicine_name ?? '',
                'subtitle' => $item->subtitle ?? '',
                'cures_for' => $item->cures_for ?? '',
                'whatsapp_number' => $item->whatsapp_number
                    ? 'https://wa.me/' . preg_replace('/\D/', '', $item->whatsapp_number)
                    : '',
                'call_number' => $item->call_number
                    ? 'tel:' . preg_replace('/\D/', '', $item->call_number)
                    : '',
            ]);

        case 'trending update':
            return array_merge($common, [
                'caption' => $item->caption ?? '',
            ]);

        case 'climate news':
            return array_merge($common, [
                'subtitle' => $item->subtitle ?? '',
                'caption' => $item->caption ?? '',
            ]);

        default:
            return $common;
    }
}


    private function errorResponse(Exception $e)
    {
        return response()->json([
            'status' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'data' => [],
        ]);
    }
}
    //nabbu_code
    // /**
    //  * 🏠 1️⃣ HOME SCREEN API
    //  * GET /api/user/home-news?category_id=1&location_id=3
    //  */
    // public function homeNews(Request $request)
    // {
    //     try {
    //         $categoryId = $request->category_id;
    //         $locationId = $request->location_id;

    //         $types = ['trending update', 'medicine news', 'climate news'];
    //         $result = [];

    //         foreach ($types as $type) {
    //             $query = News::where('type', $type)
    //                 ->where('status', 'active')
    //                 ->orderBy('priority', 'desc')
    //                 ->orderBy('id', 'desc');

    //             if ($categoryId) $query->where('category_id', $categoryId);
    //             if ($locationId) $query->where('location_id', $locationId);

    //             $items = $query->take(4)->get()->map(function ($item) {
    //                 return [
    //                     'id' => $item->id,
    //                     'title' => $item->title ?? '',
    //                     'medicine_name' => $item->medicine_name ?? '',
    //                     'cures_for' => $item->cures_for ?? '',
    //                     'caption' => $item->caption ?? '',
    //                     'image' => $item->image ? url('storage/news/' . $item->image) : '',
    //                     'video_url' => $item->video_url ?? '',
    //                     'created_at' => $item->created_at ? $item->created_at->format('d-m-Y') : '',
    //                 ];
    //             });

    //             $result[str_replace(' ', '_', $type)] = $items;
    //         }

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Home news fetched successfully',
    //             'data' => $result,
    //         ]);
    //     } catch (Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Error: ' . $e->getMessage(),
    //             'data' => [],
    //         ]);
    //     }
    // }

    // /**
    //  * 📰 2️⃣ NEWS & ADS MAIN PAGE
    //  * GET /api/user/news-ads-list
    //  */
    // public function newsAdsList()
    // {
    //     try {
    //         $types = ['trending update', 'medicine news', 'climate news'];
    //         $data = [];

    //         foreach ($types as $type) {
    //             $data[$type] = News::where('type', $type)
    //                 ->where('status', 'active')
    //                 ->orderBy('priority', 'desc')
    //                 ->orderBy('id', 'desc')
    //                 ->get()
    //                 ->map(function ($item) {
    //                     return [
    //                         'id' => $item->id,
    //                         'title' => $item->title ?? '',
    //                         'medicine_name' => $item->medicine_name ?? '',
    //                         'cures_for' => $item->cures_for ?? '',
    //                         'caption' => $item->caption ?? '',
    //                         'description' => $item->description ?? '',
    //                         'image' => $item->image ? url('storage/news/' . $item->image) : '',
    //                         'video_url' => $item->video_url ?? '',
    //                         'created_at' => $item->created_at ? $item->created_at->format('d-m-Y') : '',
    //                     ];
    //                 });
    //         }

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'News & Ads fetched successfully',
    //             'data' => $data,
    //         ]);
    //     } catch (Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Error: ' . $e->getMessage(),
    //             'data' => [],
    //         ]);
    //     }
    // }

    // /**
    //  * 🔥 3️⃣ TRENDING NEWS LIST
    //  * GET /api/user/trending-news
    //  */
    // public function trendingNews()
    // {
    //     return $this->getByType('trending update');
    // }

    // /**
    //  * 📍 4️⃣ SINGLE TRENDING NEWS
    //  * GET /api/user/trending-news/{id}
    //  */
    // public function trendingNewsShow($id)
    // {
    //     return $this->showItem($id, 'trending update');
    // }

    // /**
    //  * 💊 5️⃣ MEDICINE NEWS LIST
    //  * GET /api/user/medicine-news
    //  */
    // public function medicineNews()
    // {
    //     return $this->getByType('medicine news');
    // }

    // /**
    //  * 💊 6️⃣ SINGLE MEDICINE NEWS
    //  * GET /api/user/medicine-news/{id}
    //  */
    // public function medicineNewsShow($id)
    // {
    //     return $this->showItem($id, 'medicine news');
    // }

    // /**
    //  * 🌦️ 7️⃣ CLIMATE NEWS LIST (Optional)
    //  * GET /api/user/climate-news
    //  */
    // public function climateNews()
    // {
    //     return $this->getByType('climate news');
    // }

    // /**
    //  * 🌦️ SINGLE CLIMATE NEWS
    //  * GET /api/user/climate-news/{id}
    //  */
    // public function climateNewsShow($id)
    // {
    //     return $this->showItem($id, 'climate news');
    // }

    // // ✅ Common reusable function
    // private function getByType($type)
    // {
    //     try {
    //         $news = News::where('type', $type)
    //             ->where('status', 'active')
    //             ->orderBy('priority', 'desc')
    //             ->orderBy('id', 'desc')
    //             ->get()
    //             ->map(function ($item) {
    //                 return [
    //                     'id' => $item->id,
    //                     'title' => $item->title ?? '',
    //                     'medicine_name' => $item->medicine_name ?? '',
    //                     'cures_for' => $item->cures_for ?? '',
    //                     'caption' => $item->caption ?? '',
    //                     'description' => $item->description ?? '',
    //                     'image' => $item->image ? url('storage/news/' . $item->image) : '',
    //                     'video_url' => $item->video_url ?? '',
    //                     'created_at' => $item->created_at ? $item->created_at->format('d-m-Y') : '',
    //                 ];
    //             });

    //         return response()->json([
    //             'status' => true,
    //             'message' => ucfirst($type) . ' fetched successfully',
    //             'data' => $news,
    //         ]);
    //     } catch (Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Error: ' . $e->getMessage(),
    //             'data' => [],
    //         ]);
    //     }
    // }

    // private function showItem($id, $type)
    // {
    //     try {
    //         $news = News::where('id', $id)
    //             ->where('type', $type)
    //             ->where('status', 'active')
    //             ->first();

    //         if (!$news) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => ucfirst($type) . ' not found',
    //                 'data' => null,
    //             ], 404);
    //         }

    //         $data = [
    //             'id' => $news->id,
    //             'title' => $news->title ?? '',
    //             'medicine_name' => $news->medicine_name ?? '',
    //             'cures_for' => $news->cures_for ?? '',
    //             'caption' => $news->caption ?? '',
    //             'description' => $news->description ?? '',
    //             'image' => $news->image ? url('storage/news/' . $news->image) : '',
    //             'video_url' => $news->video_url ?? '',
    //             'whatsapp_number' => $news->whatsapp_number ?? '',
    //             'call_number' => $news->call_number ?? '',
    //         ];

    //         return response()->json([
    //             'status' => true,
    //             'message' => ucfirst($type) . ' details fetched successfully',
    //             'data' => $data,
    //         ]);
    //     } catch (Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Error: ' . $e->getMessage(),
    //             'data' => [],
    //         ]);
    //     }
    // }
