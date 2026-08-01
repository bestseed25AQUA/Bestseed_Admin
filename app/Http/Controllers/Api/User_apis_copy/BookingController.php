<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Hatchery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class BookingController extends Controller
// {
//     /**
//      * ==========================
//      * GET /api/farmer/hatcheries/{id}
//      * ==========================
//      * Hatchery details page
//      */
//     public function show($id)
//     {
//         try {
//             // Removed gallery and similarHatcheries relationships to prevent errors
//             $hatchery = Hatchery::findOrFail($id);
//             return response()->json($hatchery);
//         } catch (ModelNotFoundException $e) {
//             return response()->json([
//                 'message' => 'Hatchery not found',
//                 'error' => $e->getMessage()
//             ], 404);
//         } catch (Exception $e) {
//             return response()->json([
//                 'message' => 'An error occurred while fetching hatchery details',
//                 'error' => $e->getMessage()
//             ], 500);
//         }
//     }

//     /**
//      * ==========================
//      * POST /api/farmer/hatcheries/{id}/book
//      * ==========================
//      * Booking a seed
//      */
//     public function book(Request $request, $id)
//     {
//         try {
//             $validator = Validator::make($request->all(), [
//                 'name' => 'required|string',
//                 'phone_number' => 'required|digits:10',
//                 'unit' => 'required|string',
//                 'no_of_pieces' => 'required|numeric|min:1',
//                 'dropping_location' => 'required|string',
//                 'packing_date' => 'required|date',
//             ]);

//             if ($validator->fails()) {
//                 return response()->json([
//                     'message' => 'Invalid input',
//                     'errors' => $validator->errors()
//                 ], 422);
//             }

//             $hatchery = Hatchery::findOrFail($id);

//             $booking = Booking::create([
//                 'hatchery_id' => $id,
//                 'name' => $request->name,
//                 'phone_number' => $request->phone_number,
//                 'unit' => $request->unit,
//                 'no_of_pieces' => $request->no_of_pieces,
//                 'dropping_location' => $request->dropping_location,
//                 'packing_date' => $request->packing_date,
//             ]);

//             return response()->json([
//                 'message' => 'Booking request sent successfully',
//                 'booking' => $booking
//             ], 201);

//         } catch (ModelNotFoundException $e) {
//             return response()->json([
//                 'message' => 'Hatchery not found',
//                 'error' => $e->getMessage()
//             ], 404);
//         } catch (Exception $e) {
//             return response()->json([
//                 'message' => 'An error occurred while creating the booking',
//                 'error' => $e->getMessage()
//             ], 500);
//         }
//     }
// }

{
    /**
     * ======================================
     * GET /api/farmer/hatcheries/{id}
     * ======================================
     * Fetch single hatchery (used in detail page before booking)
     */
    public function show($id)
    {
        try {
            $hatchery = Hatchery::findOrFail($id);
            return response()->json([
                'hatchery' => $hatchery
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Hatchery not found',
                'error' => $e->getMessage()
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error fetching hatchery details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ======================================
     * POST /api/farmer/hatcheries/{id}/book
     * ======================================
     * Booking API for farmers
     */
    public function book(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string',
                'phone_number' => 'required|digits:10',
                'unit' => 'required|string',
                'no_of_pieces' => 'required|numeric|min:1',
                'dropping_location' => 'required|string',
                'packing_date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $hatchery = Hatchery::findOrFail($id);

            $booking = Booking::create([
                'hatchery_id' => $id,
                'name' => $request->name,
                'phone_number' => $request->phone_number,
                'unit' => $request->unit,
                'no_of_pieces' => $request->no_of_pieces,
                'dropping_location' => $request->dropping_location,
                'packing_date' => $request->packing_date,
                'status' => 'pending'
            ]);

            return response()->json([
                'message' => 'Booking request submitted successfully',
                'booking_id' => $booking->id,
                'hatchery_name' => $hatchery->hatchery_name,
                'status' => $booking->status
            ], 201);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Hatchery not found',
                'error' => $e->getMessage()
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error creating booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ======================================
     * GET /api/farmer/bookings
     * ======================================
     * Optional future endpoint to view all bookings of a user
     */
    public function list()
    {
        try {
            $bookings = Booking::latest()->paginate(10);
            return response()->json([
                'data' => $bookings
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch bookings',
                'error' => $e->getMessage()
            ], 500);
        }
    }




}
