<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
// use App\Models\Seed;
use App\Models\SeedRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class SeedsRequests extends Controller
{
    /**
     * POST /api/farmer/seed-request
     * User submits a new seed request.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'seed_id' => 'required|exists:seeds,id',
            'category_id' => 'required|exists:hatchery_categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'farmer_id' => 'required|exists:farmers,id',
            'name' => 'required|string',
            'mobile' => 'required|string',
            'quantity' => 'required|integer',
            'dropping_location' => 'required|string',
            'packing_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // $requestData = SeedRequest::create($request->all());

           // ? Use exact DB column names from your model
        $requestData = SeedRequest::create([
            'farmer_id' => $request->farmer_id,
            // 'seed_id' => $request->seed_id,
            'category_id' => $request->category_id,
            'brand_id' => $request->brand_id, // if using brand selection
            'name' => $request->name,
            'quantity' => $request->quantity,
            'mobile' => $request->mobile,
            'dropping_location' => $request->dropping_location,
            'packing_date' => $request->packing_date,
        ]);


        return response()->json([
            'status' => true,
            'message' => 'Your request was sent successfully!',
            'data' => $requestData
        ]);
    }

    /**
     * GET /api/farmer/seed-requests
     * List all requests for logged-in user
     */
    // public function index(Request $request)
    // {
    //     $requests = SeedRequest::where('farmer_id', $request->farmer_id)
    //         ->with(['category', 'brand'])
    //         ->latest()
    //         ->get();

    //     return response()->json([
    //         'status' => true,
    //         'data' => $requests
    //     ]);
    // }
}