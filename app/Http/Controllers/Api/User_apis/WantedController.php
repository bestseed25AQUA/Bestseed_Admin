<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\HatcheryCategory;
use App\Models\HatcheryLocation;
use App\Models\WantedCrop;
use Illuminate\Http\Request;
use Carbon\Carbon;

class WantedController extends Controller
{
    /**
     * ====================================================
     * GET /api/farmer/wanted
     * ====================================================
     * Fetch available seed batches posted by hatcheries.
     * Optional filters: category, location
     * ----------------------------------------------------
     * Supports both image and video media.
     * ----------------------------------------------------
     */
    // public function index(Request $request)
    // {
    //     $category = $request->input('category');
    //     $location = $request->input('location');

    //     $query = WantedCrop::with('hatchery');

    //     if ($category) {
    //         $query->where('category', $category);
    //     }

    //     if ($location) {
    //         $query->where('location', $location);
    //     }

    //     $wantedList = $query->orderBy('created_at', 'desc')->get();

    //     // if ($wantedList->isEmpty()) {
    //     //     return response()->json([
    //     //         'status' => false,
    //     //         'message' => 'No available seeds found'
    //     //     ], 404);
    //     // }

    //     $result = $wantedList->map(function ($item) {

    //          // Ensure date is a Carbon instance
    //             $packing_date = $item->date ? Carbon::parse($item->date)->format('Y-m-d') : null;

    //             // Extract filename from media_url
    //             $filename = basename($item->media_url);

    //             // Detect if media is image or video
    //             $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    //             $media_type = in_array($extension, ['mp4', 'webm', 'mov', 'avi']) ? 'video' : 'image';

    //             // Build proper URL
    //             $media_url = $item->media_url
    //             ? (str_starts_with($item->media_url, 'http')
    //                 ? $item->media_url
    //                 : rtrim(config('app.url'), '/') . '/public/wanted_crops/' . basename($item->media_url))
    //             : null;


    //         return [
    //             'id'            => $item->id,
    //             'hatchery_name' => $item->hatchery->name ?? 'Unknown Hatchery',
    //             'category'      => $item->category,
    //             'location'      => $item->location,
    //             'packing_date'  => $item->date ? $item->date->format('Y-m-d') : null,
    //             'tons'          => $item->quantity,
    //             'payment'       => $item->payment,
    //             'price'         => $item->price,
    //             'description'   => $item->description, 
    //             'contact'       => $item->contact,
    //             'media_type'    => $item->media_type, // image or video
    //             'media_url'     => $media_url,
    //             ];
    //             // 'media_url'     => $item->media_url ? url('public/wanted_crops' . $item->media_url) : null,
    //         //     'media_url' => $item->media_url
    //         //     ? (str_starts_with($item->media_url, 'http') 
    //         //         ? $item->media_url 
    //         //         : url('public/wanted_crops/' . basename($item->media_url)))
    //         //     : null,
    //         // ];
    //     });

    //     // return response()->json([
    //     //     'status' => true,
    //     //     'wanted_crops' => $result
    //     // ]);

    //     return response()->json([
    //         'status' => true,
    //         'message' => $wantedList->isEmpty() ? 'No available seeds found' : 'Data fetched successfully',
    //         'wanted_crops' => $result
    //     ], 200);
    // }



    //working

    // public function index(Request $request)
    // {
    //     $categoryId = $request->input('category_id');
    //     $locationId = $request->input('location_id');

    //     $query = WantedCrop::with([
    //         'hatchery',
    //         'category:id,category_name',
    //         'location:id,location_name',
    //         'marketPrice'
    //     ]);

    //     if ($categoryId) {
    //         $query->where('category_id', $categoryId);
    //     }

    //     if ($locationId) {
    //         $query->where('location_id', $locationId);
    //     }

    //     $wantedList = $query->orderBy('created_at', 'desc')->get();

    //     $result = $wantedList->map(function ($item) {

    //         // Media detection
    //         $filename = basename($item->media_url);
    //         $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    //         $mediaType = in_array($ext, ['mp4','webm','mov','avi']) ? 'video' : 'image';

    //         // URL format
    //         $mediaUrl = $item->media_url
    //             ? (str_starts_with($item->media_url, 'http')
    //                 ? $item->media_url
    //                 : url('public/wanted_crops/' . $filename))
    //             : null;

    //         return [
    //             'id'              => $item->id,
    //             'hatchery_name'   => $item->hatchery->name ?? 'Unknown',
    //             'category'        => $item->category->category_name ?? null,
    //             'location'        => $item->location->location_name ?? null,
    //             // 'address'         => $item->location->full_address ?? null,
    //             'packing_date'    => $item->date ? $item->date->format('Y-m-d') : null,
    //             'tons'            => $item->quantity,
    //             'payment'         => $item->payment,
    //             'price'           => $item->marketPrice->price ?? $item->price,
    //             'contact'         => $item->contact,
    //             'media_type'      => $mediaType,
    //             'media_url'       => $mediaUrl,
    //         ];
    //     });

    //     return response()->json([
    //         'status' => true,
    //         'message' => $wantedList->isEmpty()
    //             ? 'No available seeds found'
    //             : 'Data fetched successfully',
    //         'wanted_crops' => $result
    //     ], 200);
    // }



    public function index(Request $request)
    {
        $categoryId = $request->input('category_id');
        $locationId = $request->input('location_id');

        // 1️⃣ Fetch categories (priority order)
        $categories = HatcheryCategory::orderBy('priority', 'asc')
            ->get(['id', 'category_name']);

        // 2️⃣ Fetch locations (priority order)
        $locations = HatcheryLocation::whereNotNull('priority')
            ->orderBy('priority', 'asc')
            ->get(['id', 'location_name']);

        // 3️⃣ Fetch wanted crops with filters
        $query = WantedCrop::with([
            'vendor',
            'hatchery',
            'category:id,category_name',
            'location:id,location_name',
            'marketPrice'
        ]);

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        $wantedList = $query->orderBy('created_at', 'desc')->get();

        $result = $wantedList->map(function ($item) {

            $filename = basename($item->media_url);
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $mediaType = in_array($ext, ['mp4','webm','mov','avi']) ? 'video' : 'image';

            $mediaUrl = $item->media_url
                ? (str_starts_with($item->media_url, 'http')
                    ? $item->media_url
                    : url('public/wanted_crops/' . $filename))
                : null;

            return [
                'id'              => $item->id,
                'hatchery_name'   => $item->hatchery->name ?? 'Unknown',
                'category'        => $item->category->category_name ?? null,
                'location'        => $item->location->location_name ?? null,
                'packing_date'    => $item->date ? $item->date->format('Y-m-d') : null,
                'tons'            => $item->quantity,
                'payment'         => $item->payment,
                'price'           => $item->marketPrice->price ?? $item->price,
                'contact'         => $item->vendor->mobile ?? null,
                'description'     => $item->description ?? '',
                'media_type'      => $mediaType,
                'media_url'       => $mediaUrl,
            ];
        });

        // 4️⃣ Return everything in ONE response
        return response()->json([
            'status' => true,
            'categories' => $categories,
            'locations' => $locations,
            'wanted_crops' => $result
        ], 200);
    }




}
