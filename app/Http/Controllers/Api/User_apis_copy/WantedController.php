<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
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
    public function index(Request $request)
    {
        $category = $request->input('category');
        $location = $request->input('location');

        $query = WantedCrop::with('hatchery');

        if ($category) {
            $query->where('category', $category);
        }

        if ($location) {
            $query->where('location', $location);
        }

        $wantedList = $query->orderBy('created_at', 'desc')->get();

        // if ($wantedList->isEmpty()) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'No available seeds found'
        //     ], 404);
        // }

        $result = $wantedList->map(function ($item) {

             // Ensure date is a Carbon instance
                $packing_date = $item->date ? Carbon::parse($item->date)->format('Y-m-d') : null;

                // Extract filename from media_url
                $filename = basename($item->media_url);

                // Detect if media is image or video
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $media_type = in_array($extension, ['mp4', 'webm', 'mov', 'avi']) ? 'video' : 'image';

                // Build proper URL
                $media_url = $item->media_url
                ? (str_starts_with($item->media_url, 'http')
                    ? $item->media_url
                    : rtrim(config('app.url'), '/') . '/public/wanted_crops/' . basename($item->media_url))
                : null;


            return [
                'id'            => $item->id,
                'hatchery_name' => $item->hatchery->name ?? 'Unknown Hatchery',
                'category'      => $item->category,
                'location'      => $item->location,
                'packing_date'  => $item->date ? $item->date->format('Y-m-d') : null,
                'tons'          => $item->quantity,
                'payment'       => $item->payment,
                'price'         => $item->price,
                'description'   => $item->description, 
                'contact'       => $item->contact,
                'media_type'    => $item->media_type, // image or video
                'media_url'     => $media_url,
                ];
                // 'media_url'     => $item->media_url ? url('public/wanted_crops' . $item->media_url) : null,
            //     'media_url' => $item->media_url
            //     ? (str_starts_with($item->media_url, 'http') 
            //         ? $item->media_url 
            //         : url('public/wanted_crops/' . basename($item->media_url)))
            //     : null,
            // ];
        });

        // return response()->json([
        //     'status' => true,
        //     'wanted_crops' => $result
        // ]);

        return response()->json([
            'status' => true,
            'message' => $wantedList->isEmpty() ? 'No available seeds found' : 'Data fetched successfully',
            'wanted_crops' => $result
        ], 200);
    }
}
