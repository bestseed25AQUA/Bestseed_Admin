<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Booking;
use App\Models\Hatchery;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class VehicleController extends Controller
{
    /**
     * ===============================
     * GET /api/farmer/banner
     * ===============================
     * Fetch dynamic banner for Vehicle Availability
     */

        // public function banner()
        // {
        //     try {
        //         // Fetch all active banners for Vehicle Availability
        //         $banners = Banner::where('title', 'Vehicle Availability')
        //             ->where('status', 1)
        //             ->get();

        //         if ($banners->isEmpty()) {
        //             return response()->json([
        //                 'status' => false,
        //                 'message' => 'No active Vehicle Availability banners found'
        //             ], 404);
        //         }

        //         $bannerList = $banners->map(function ($banner) {
        //             // Get the filename
        //             $filename = basename($banner->image);

        //             // Check if file exists in public/banners/
        //             $filePath = public_path('banners/' . $filename);
        //             $fileUrl = file_exists($filePath) ? url('public/banners/' . $filename) : null;

        //             // Determine file type (image or video)
        //             $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        //             $type = in_array($extension, ['mp4', 'webm', 'mov', 'avi']) ? 'video' : 'image';

        //             return [
        //                 'title' => $banner->title,
        //                 'type' => $type,
        //                 'url' => $fileUrl,
        //             ];
        //         });

        //         // Return JSON response
        //         return response()->json([
        //             'status' => true,
        //             'banners' => $bannerList
        //         ]);

        //     } catch (Exception $e) {
        //         return response()->json([
        //             'status' => false,
        //             'error' => $e->getMessage()
        //         ], 500);
        //     }
        // }





    /**
     * ===============================
     * GET /api/farmer/vehicle-availability
     * ===============================
     * Fetch vehicle availability dynamically
     */
    public function vehicleAvailability()
    {
        try {
            $bookings = Booking::with('hatchery')->get();

            $vehicles = $bookings->groupBy('vehicle_number')->map(function ($group) {
                $v = $group->first();
                $total_booked = $group->sum('count');

                return [
                    'vehicle_number' => $v->vehicle_number,
                    'driver_name' => $v->driver_name,
                    'driver_mobile' => $v->driver_mobile,
                    // 'vehicle_images' => json_decode($v->vehicle_images) ?? [],
                    // 'vehicle_images' => is_string($v->vehicle_images) ? json_decode($v->vehicle_images) : ($v->vehicle_images ?? []),
                    'vehicle_images' => is_string($v->vehicle_images) 
                    ? array_map(function($img) {
                        if (preg_match('/^https?:\/\//', $img)) {
                            return $img; // already full URL
                        }
                        return env('APP_URL') . '/public/' . ltrim($img, '/');
                    }, json_decode($v->vehicle_images)) 
                    : ($v->vehicle_images ? array_map(function($img) {
                        return env('APP_URL') . '/public/' . ltrim($img, '/');
                    }, $v->vehicle_images) : []),
                    'hatchery_name' => $v->hatchery->hatchery_name ?? $v->hatchery_name,
                    // 'hatchery_location' => $v->hatchery->location ?? null,
                    // 'vechile_location_tracking' => ($v->hatchery->location ?? '') . ' -> ' . implode(' -> ', $group->pluck('delivery_location')->filter()->toArray()),
                    'hatchery_location' => $v->hatchery->location ?? '',
                    'vechile_location_tracking' => array_merge(
                        [$v->hatchery->location ?? ''], // starting point
                        $group->pluck('delivery_location')->filter()->toArray() // delivery locations
                    ),
                    'start_date' => $v->vehicle_started_date,
                    'end_date' => $v->vehicle_end_date,
                    // 'available_space' => max(0, 100 - $total_booked), // example logic
                    'available_space' => $v->available_space, // example logic
                    'call_now' =>  $v->driver_mobile,
                    //  'call_now' => 'tel:' . $v->driver_mobile,
                    'whatsapp' => 'https://wa.me/' . $v->driver_mobile,
                    // 'book_now_link' => url('/api/farmer/book-vehicle/' . $v->vehicle_number),
                ];
            })->values();

            return response()->json([
                'status' => true,
                'vehicles' => $vehicles
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error fetching vehicle availability',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }


//   /**
//  * ===============================
//  * POST /api/farmer/book-vehicle
//  * ===============================
//  * Store user booking request
//  */
//     public function bookVehicle(Request $request)
//     {
//         $request->validate([
//             'vehicle_number' => 'required|string',
//             'hatchery_name'  => 'required|string',
//             'name' => 'required|string|max:255',
//             'mobile' => 'required|string|max:20',
//             'date' => 'required|date',
//             'pickup_address' => 'required|string',
//             'delivery_address' => 'required|string',
//             'seed_quantity' => 'required|numeric'
//         ]);

//         try {
//             // Fetch hatchery by name
//             $hatchery = Hatchery::where('hatchery_name', $request->hatchery_name)->first();

//             // Check if hatchery exists to prevent null vendor_id
//             if (!$hatchery) {
//                 return response()->json([
//                     'status' => false,
//                     'message' => 'Invalid hatchery name. Please provide a valid hatchery.'
//                 ], 422);
//             }

//             $booking = Booking::create([
//                 'vehicle_number'   => $request->vehicle_number,
//                 'vendor_id'        => $hatchery->vendor_id, // never null now
//                 'customer_id'      => Auth::id() ?? null,
//                 'hatchery_name'    => $request->hatchery_name,
//                 'customer_name'    => $request->name,
//                 'customer_mobile'  => $request->mobile,
//                 'delivery_location'=> $request->delivery_address,
//                 'dropping_location'=> $request->pickup_address,
//                 'no_of_pieces'     => $request->seed_quantity,
//                 'categories'       => json_encode(['seed_booking']),
//             ]);

//             return response()->json([
//                 'status' => true,
//                 'message' => 'Your request was recorded successfully!',
//                 'booking' => $booking
//             ]);
//         } catch (Exception $e) {
//             return response()->json([
//                 'status' => false,
//                 'message' => 'Booking failed',
//                 'error' => $e->getMessage()
//             ], 500);
//         }
//     }


/**
 * ===============================
 * POST /api/farmer/book-vehicle
 * ===============================
 * Store user booking request
 */
public function bookVehicle(Request $request)
{
    $request->validate([
        'vehicle_number'   => 'required|string',
        'hatchery_name'    => 'required|string',
        'name'             => 'required|string|max:255',
        'mobile'           => 'required|string|max:20',
        'date'             => 'required|date',
        'pickup_address'   => 'required|string',
        'delivery_address' => 'required|string',
        'seed_quantity'    => 'required|numeric'
    ]);

    try {
        // ✅ Find hatchery by name
        $hatchery = Hatchery::where('hatchery_name', $request->hatchery_name)->first();

        if (!$hatchery) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid hatchery name. Please provide a valid hatchery.'
            ], 422);
        }

        // ✅ Create a new booking record
        $booking = Booking::create([
            'vendor_id'         => $hatchery->vendor_id,
            'hatchery_id'       => $hatchery->id,
            'customer_id'       => Auth::id() ?? null,
            'vehicle_number'    => $request->vehicle_number,
            'hatchery_name'     => $hatchery->hatchery_name,
             'hatchery_location'=> $hatchery->location,
            'customer_name'     => $request->name,
            'customer_mobile'   => $request->mobile,
            'pickup_location'   => $request->pickup_address,
            'delivery_location' => $request->delivery_address,
            'no_of_pieces'      => $request->seed_quantity,
            'categories'        => json_encode(['seed_booking']),
            'packing_date'      => $request->date,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Your request was recorded successfully!',
            'data'    => $booking
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status'  => false,
            'message' => 'Booking failed!',
            'error'   => $e->getMessage()
        ], 500);
    }
}


 /**
 * ======================================
 * GET /api/farmer/my-bookings
 * ======================================
 * Fetch all bookings for the logged-in user
 */
public function myBookings()
{
    try {
        $userId = Auth::id();

        // Fetch all bookings for this user
        $bookings = Booking::with('hatchery')
            ->where('customer_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($bookings->isEmpty()) {
            return response()->json([
                'status'  => true,
                'message' => 'No bookings found.',
                'data'    => []
            ]);
        }

        // Map the bookings to a nice format
        $data = $bookings->map(function ($booking) {
            return [
                'booking_id'       => $booking->id,
                'vehicle_number'   => $booking->vehicle_number,
                'driver_name'      => $booking->driver_name ?? null,
                'driver_mobile'    => $booking->driver_mobile ?? null,
                'hatchery_name'    => $booking->hatchery->hatchery_name ?? $booking->hatchery_name,
                'pickup_location'  => $booking->pickup_location,
                'delivery_location'=> $booking->delivery_location,
                'no_of_pieces'     => $booking->no_of_pieces,
                'packing_date'     => $booking->packing_date,
                'available_space'  => $booking->available_space,
                'vehicle_images'   => $booking->vehicle_images ? json_decode($booking->vehicle_images, true) : [],
            ];
        });

        return response()->json([
            'status'  => true,
            'message' => 'Your bookings fetched successfully!',
            'data'    => $data
        ], 200);

    } catch (Exception $e) {
        return response()->json([
            'status'  => false,
            'message' => 'Failed to fetch bookings',
            'error'   => $e->getMessage()
        ], 500);
    }
}



}