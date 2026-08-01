<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Hatchery;
use App\Models\HatcheryCategory;
use App\Models\HatcheryLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;

class HatcheryMetaController extends Controller
{
    /**
     * GET /api/hatchery/categories
     * Fetch all hatchery categories
     */
    public function categories()
    {
        try {
            $categories = HatcheryCategory::orderBy('priority', 'asc')
                ->get(['id', 'category_name']);

            return response()->json([
                'status' => true,
                'categories' => $categories
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/hatchery/locations
     * Fetch all hatchery locations
     */
    public function locations()
    {
        try {
            $locations = HatcheryLocation::orderBy('priority', 'asc')
                ->get(['id', 'location_name', 'longitude', 'latitude']);

            return response()->json([
                'status' => true,
                'locations' => $locations
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch locations',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    /**
     * GET /api/hatchery/brands
     * Fetch all brands
     */
    public function brands()
    {
        try {
            $brands = Brand::orderBy('brand_name', 'asc')
                ->get(['id', 'brand_name']);

            return response()->json([
                'status' => true,
                'brands' => $brands
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch brands',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    /**
     * GET /api/hatchery/filters
     * Optional: combine categories + locations in one call
     */
    // public function filters()
    // {
    //     try {
    //         return response()->json([
    //             'status' => true,
    //             'categories' => HatcheryCategory::orderBy('priority', 'asc')->get(['id','category_name']),
    //             'locations' => HatcheryLocation::orderBy('priority', 'asc')->get(['id','location_name','longitude','latitude']),
                    // 'brands' => Brand::orderBy('brand_name', 'asc')->get(['id','brand_name']),
    //         ], 200);

    //     } catch (Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to fetch filters',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
} 