<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\SeedWantedListing;
use Illuminate\Http\Request;

class SeedWantedListingController extends Controller
{
    public function index(Request $request)
    {
        try {
            $species = $request->input('species', 'shrimp');

            $listings = SeedWantedListing::where('species', $species)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'id'                => $item->id,
                        'title'             => $item->title,
                        'species'           => $item->species,
                        'count_or_kg'       => $item->count_or_kg,
                        'count_or_kg_value' => $item->count_or_kg_value,
                        'payment'           => $item->payment,
                        'minimum'           => $item->minimum,
                        'area'              => $item->area,
                        'price'             => $item->price,
                        'phone'             => $item->phone,
                    ];
                });

            return response()->json([
                'status'  => true,
                'species' => $species,
                'data'    => $listings,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong.',
                'data'    => [],
            ], 200);
        }
    }
}
