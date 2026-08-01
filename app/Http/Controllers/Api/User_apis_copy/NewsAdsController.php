<?php

namespace App\Http\Controllers\Api\Farmer_apis;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

// class NewsAdsController extends Controller
// {
//     /**
//      * GET /api/farmer/news
//      * Returns latest news and active ads for the feed
//      */
//     public function index(Request $request)
//     {
//         try {
//             // Fetch latest 10 news
//             $news = News::latest()->limit(10)->get();

//             // Fetch all active ads
//             $ads = Ad::where('active', true)->get();

//             // Return as JSON
//             return response()->json([
//                 'news' => $news,
//                 'ads'  => $ads,
//             ]);

//         } catch (QueryException $e) {
//             // Database query error
//             return response()->json([
//                 'message' => 'Database query failed',
//                 'error' => $e->getMessage(),
//                 'trace' => $e->getTraceAsString()
//             ], 500);

//         } catch (Exception $e) {
//             // Any other unexpected error
//             return response()->json([
//                 'message' => 'Something went wrong while fetching news and ads',
//                 'error' => $e->getMessage(),
//                 'trace' => $e->getTraceAsString()
//             ], 500);
//         }
//     }
// }

