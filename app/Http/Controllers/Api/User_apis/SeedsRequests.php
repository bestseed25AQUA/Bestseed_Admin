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
            'category_id' => 'required|exists:hatchery_categories,id',
            'hatchery_id' => 'nullable|exists:hatcheries,id',
            'hatchery_name' => 'nullable|string|max:255',
            'farmer_id' => 'nullable|exists:farmers,id',
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

        // Get farmer_id from request or from authenticated user
        $farmerId = $request->farmer_id;
        if (!$farmerId && $request->user()) {
            $farmerId = $request->user()->id;
        }

        $requestData = SeedRequest::create([
            'farmer_id' => $farmerId,
            'category_id' => $request->category_id,
            'hatchery_id' => $request->hatchery_id,
            'hatchery_name' => $request->hatchery_name,
            'name' => $request->name,
            'quantity' => $request->quantity,
            'mobile' => $request->mobile,
            'dropping_location' => $request->dropping_location,
            'packing_date' => $request->packing_date,
            'status' => 'pending',
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
