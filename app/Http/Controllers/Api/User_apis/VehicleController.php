<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\Farmer;
use App\Models\Hatchery;
use App\Models\VehicleTracking;
use App\Models\AppConfig;
use App\Services\FirebaseNotificationService;
use App\Services\RouteTimelineService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class VehicleController extends Controller
{
    /**
     * ===============================
     * GET /api/farmer/banner
     * ===============================
     * Fetch dynamic banner for Vehicle Availability
     */
    //        public function banner()
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
                        ? array_map(function ($img) {
                            if (preg_match('/^https?:\/\//', $img)) {
                                return $img; // already full URL
                            }
                            return env('APP_URL') . '/public/' . ltrim($img, '/');
                        }, json_decode($v->vehicle_images))
                        : ($v->vehicle_images ? array_map(function ($img) {
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
                    'call_now' => $v->driver_mobile,
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



    /**
     * ===============================
     * POST /api/farmer/book-vehicle
     * ===============================
     * Store user booking request
     */
    public function bookVehicle(Request $request)
    {
        // dd('book vehicle');
        $request->validate([
            'vehicle_number' => 'required|string',
            'hatchery_name' => 'required|string',
            'name' => 'required|string|max:255',
            'mobile' => 'required|string|max:20',
            'date' => 'required|date',
            'pickup_address' => 'required|string',
            'delivery_address' => 'required|string',
            'seed_quantity' => 'required|numeric'
        ]);

        try {
            // ✅ Find hatchery by name
            $hatchery = Hatchery::where('hatchery_name', $request->hatchery_name)->first();

            if (!$hatchery) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid hatchery name. Please provide a valid hatchery.'
                ], 422);
            }

            // ✅ Create a new booking record
            $booking = Booking::create([
                'vendor_id' => $hatchery->vendor_id,
                'hatchery_id' => $hatchery->id,
                'customer_id' => Auth::id() ?? null,
                'vehicle_number' => $request->vehicle_number,
                'hatchery_name' => $hatchery->hatchery_name,
                'hatchery_location' => $hatchery->location,
                'customer_name' => $request->name,
                'customer_mobile' => $request->mobile,
                'pickup_location' => $request->pickup_address,
                'delivery_location' => $request->delivery_address,
                'no_of_pieces' => $request->seed_quantity,
                'categories' => json_encode(['seed_booking']),
                'packing_date' => $request->date,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Your request was recorded successfully!',
                'data' => $booking
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Booking failed!',
                'error' => $e->getMessage()
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
                    'status' => true,
                    'message' => 'No bookings found.',
                    'data' => []
                ]);
            }

            // Map the bookings to a nice format
            $data = $bookings->map(function ($booking) {
                return [
                    'booking_id' => $booking->id,
                    'vehicle_number' => $booking->vehicle_number,
                    'driver_name' => $booking->driver_name ?? null,
                    'driver_mobile' => $booking->driver_mobile ?? null,
                    'hatchery_name' => $booking->hatchery->hatchery_name ?? $booking->hatchery_name,
                    'pickup_location' => $booking->pickup_location,
                    'delivery_location' => $booking->delivery_location,
                    'no_of_pieces' => $booking->no_of_pieces,
                    'packing_date' => $booking->packing_date,
                    'available_space' => $booking->available_space,
                    'vehicle_images' => $booking->vehicle_images ? json_decode($booking->vehicle_images, true) : [],
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Your bookings fetched successfully!',
                'data' => $data
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch bookings',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * ===============================
     * GET /api/farmer/vehicle_list
     * ===============================
     * Fetch vehicle tracking list for the logged-in farmer
     *
     * Filters:
     * - month (e.g., 02)
     * - year (e.g., 2025)
     * - date (e.g., 2025-02-15)
     */
    public function vehicleList(Request $request)
    {
        try {
            $farmer = Auth::user();
            // dd($farmer);

            if (!$farmer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized. Please login.',
                ], 401);
            }

            // Build the query - match bookings by farmer's mobile number
            $query = Booking::with(['hatchery', 'category'])
                ->where(function ($q) use ($farmer) {
                    $q->where('customer_mobile', $farmer->mobile)
                        ->orWhere('farmer_id', $farmer->id);
                });

            // Apply filters
            if ($request->has('date') && $request->date) {
                // Filter by exact date
                $query->whereDate('packing_date', $request->date);
            } elseif ($request->has('month')) {
                // Filter by month (and optionally year)
                $month = str_pad($request->month, 2, '0', STR_PAD_LEFT);
                $year = $request->year ?? Carbon::now()->year;

                $query->whereMonth('packing_date', $month)
                    ->whereYear('packing_date', $year);
            }

            // Order by packing date
            $bookings = $query->orderBy('packing_date', 'desc')->get();

            // Format the response
            $vehicles = $bookings->map(function ($booking) {
                // Get hatchery name
                $hatcheryName = $booking->hatchery->hatchery_name ?? $booking->hatchery_name ?? 'Unknown Hatchery';

                // Get category name
                $categoryName = $booking->category->category_name ?? 'N/A';

                // Get vehicle images with full URLs
                $images = [];
                if ($booking->vehicle_images) {
                    $vehicleImages = is_string($booking->vehicle_images)
                        ? json_decode($booking->vehicle_images, true)
                        : $booking->vehicle_images;

                    if (is_array($vehicleImages)) {
                        $images = array_map(function ($img) {
                            if (preg_match('/^https?:\/\//', $img)) {
                                return $img;
                            }
                            return url('public/' . ltrim($img, '/'));
                        }, $vehicleImages);
                    }
                }

                // Get customer details from farmers table
                $customer = Farmer::where('mobile', $booking->customer_mobile)->first();
                $customerDetails = [
                    'name' => $customer->name ?? $booking->customer_name ?? 'N/A',
                    'mobile' => $booking->customer_mobile ?? 'N/A',
                ];

                // Booking details
                $bookingDetails = [
                    'id' => $booking->id,
                    'pieces' => $booking->no_of_pieces ?? '0',
                    'unit_name' => $booking->unit ?? 'PCS',
                    'available_date' => $booking->packing_date
                        ? Carbon::parse($booking->packing_date)->format('Y-m-d')
                        : null,
                ];

                // Driver details
                $driverDetails = [
                    'driver_name' => $booking->driver_name ?? 'N/A',
                    'driver_mobile' => $booking->driver_mobile ?? 'N/A',
                    'vehicle_number' => $booking->vehicle_number ?? 'N/A',
                ];

                return [
                    'hatchery_name' => $hatcheryName,
                    'category_name' => $categoryName,
                    'images' => $images,
                    'customer' => $customerDetails,
                    'booking_details' => $bookingDetails,
                    'driver_details' => $driverDetails,
                    'sms_to' => $booking->driver_mobile ?? $booking->customer_mobile ?? null,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Tracking data fetched successfully',
                'vehicles' => $vehicles,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error fetching vehicle list',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ===============================
     * GET /api/farmer/vehicle_available_booking
     * ===============================
     * Fetch vehicle availability bookings for the logged-in farmer
     */
    public function vehicleAvailableBooking(Request $request)
    {
        try {
            $farmer = Auth::user();

            if (!$farmer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Please login.',
                ], 401);
            }

            // Fetch bookings for this farmer (same query as my-bookings)
            $bookings = Booking::with(['hatchery', 'category'])
                ->where('farmer_id', $farmer->id)
                ->whereIn('status', [4]) // In Journey only
                ->orderBy('created_at', 'desc')
                ->get();

            $statusMap = [
                1 => 'Pending',
                2 => 'Confirmed',
                3 => 'Driver Assigned',
                4 => 'In Journey',
                5 => 'Delivered',
                6 => 'Cancelled',
            ];


            // Format the response
            $data = $bookings->map(function ($booking) use ($statusMap) {
                // Get hatchery name
                $hatcheryName = $booking->hatchery->hatchery_name ?? $booking->hatchery_name ?? 'Unknown Hatchery';

                // Get category name
                $categoryName = $booking->category->category_name ?? 'N/A';

                // Format date and time
                $date = $booking->packing_date
                    ? Carbon::parse($booking->packing_date)->format('d/m/Y')
                    : null;
                $time = $booking->created_at
                    ? Carbon::parse($booking->created_at)->format('h:i A')
                    : null;

                // Determine status
                // $status = $booking->status ?? 'Pending';
                $status = [
                    'value' => $booking->status ?? 1,
                    'label' => $statusMap[$booking->status] ?? 'Pending'
                ];


                // Pickup location (hatchery location)
                $pickupLocation = $booking->hatchery->hatchery_name
                    ?? $booking->hatchery_location
                    ?? $booking->hatchery_name
                    ?? 'N/A';

                // Drop location
                $dropLocation = $booking->dropping_location
                    ?? $booking->delivery_location
                    ?? 'N/A';

                // Quantity
                $quantity = ($booking->no_of_pieces ?? '0') . ' ' . ($booking->unit ?? 'Pieces');

                return [
                    'id' => (string) $booking->id,
                    'booking_id' => $booking->booking_uid ?? (string) $booking->id,
                    'time' => $time,
                    'date' => $date,
                    'hatchery_name' => $hatcheryName,
                    'category_name' => $categoryName,
                    'status' => $status,
                    'is_spot' => (int) $booking->is_spot,
                    // 'status' => ucfirst($status),
                    'pickup_location' => $pickupLocation,
                    'drop_location' => $dropLocation,
                    'quantity' => $quantity,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Vehicle availability bookings fetched successfully',
                'data' => $data,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching vehicle availability bookings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ===============================
     * GET /api/farmer/vehicle_booking_detail/{id}
     * ===============================
     * Fetch detailed information about a specific vehicle booking
     */
    public function vehicleBookingDetail($id)
    {
        try {
            $farmer = Auth::user();

            if (!$farmer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Please login.',
                ], 401);
            }

            // Fetch the booking
            $booking = Booking::with(['hatchery', 'category', 'brand'])
                ->where('id', $id)
                ->where(function ($q) use ($farmer) {
                    $q->where('customer_mobile', $farmer->mobile)
                        ->orWhere('farmer_id', $farmer->id);
                })
                ->first();

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found or access denied.',
                ], 404);
            }

            // Determine status
            // $status = ucfirst($booking->status ?? 'Pending');
            // Convert numeric status to text
            $statusLabelMap = [
                1 => 'Pending',
                2 => 'Confirmed',
                3 => 'Driver_assigned',
                4 => 'In_progress',
                5 => 'Delivered',
                6 => 'Cancelled'
            ];

            $status = $statusLabelMap[$booking->status] ?? 'Pending';


            // Status descriptions
            $statusDescriptions = [
                'Pending' => "Your booking is pending confirmation.",
                'Confirmed' => "We'll notify you when the driver assigned and start their journey.",
                'Driver_assigned' => "Driver has been assigned to your booking.",
                'In_progress' => "Your delivery is in progress.",
                'Delivered' => "Your order has been delivered successfully.",
                'Cancelled' => "This booking has been cancelled.",
            ];
            $statusDescription = $statusDescriptions[$status] ?? "Booking status: {$status}";

            // Check if vehicle is assigned
            $isVehicleAssigned = !empty($booking->vehicle_number) && !empty($booking->driver_name);

            // Pickup details
            $pickupDetails = [
                'location' => $booking->hatchery->hatchery_name
                    ?? $booking->hatchery_location
                    ?? $booking->hatchery_name
                    ?? 'N/A',
                'date' => $booking->vehicle_started_date
                    ? Carbon::parse($booking->vehicle_started_date)->format('d/m/Y')
                    : ($booking->packing_date ? Carbon::parse($booking->packing_date)->format('d/m/Y') : null),
                'time' => $booking->vehicle_started_date
                    ? Carbon::parse($booking->vehicle_started_date)->format('h:i A')
                    : null,
            ];

            // Drop details
            $dropDetails = [
                'location' => $booking->delivery_location
                    ?? $booking->dropping_location
                    ?? 'N/A',
                'date' => $booking->vehicle_end_date
                    ? Carbon::parse($booking->vehicle_end_date)->format('d/m/Y')
                    : null,
                'time' => $booking->vehicle_end_date
                    ? Carbon::parse($booking->vehicle_end_date)->format('h:i A')
                    : null,
            ];

            // Vehicle booking details
            $vehicleBookingDetails = [
                'hatchery_name' => $booking->hatchery->hatchery_name ?? $booking->hatchery_name ?? 'N/A',
                'brand_type' => $booking->brand->name ?? $booking->category->category_name ?? 'N/A',
                'seed_qty' => ($booking->no_of_pieces ?? '0') . ' ' . ($booking->unit ?? 'Pieces'),
                'booking_date' => $booking->created_at
                    ? Carbon::parse($booking->created_at)->format('d/m/Y')
                    : null,
                'booking_time' => $booking->created_at
                    ? Carbon::parse($booking->created_at)->format('h:i A')
                    : null,
            ];

            // Build response data
            $data = [
                'booking_id' => $booking->id,
                'vehicle_id' => $booking->vehicle_number ? $booking->id : null,
                'driver_id' => $booking->driver_mobile ? $booking->id : null,
                'is_vehicle_assigned' => $isVehicleAssigned,
                'status' => $status,
                'status_description' => $statusDescription,
                'pickup_details' => $pickupDetails,
                'drop_details' => $dropDetails,
                'vehicle_booking_details' => $vehicleBookingDetails,
                'note' => "For any changes, please contact us.",
            ];
            // Add cancellation reason if booking is cancelled
            if ($booking->status == 6) {
                $reasonMap = [
                    1 => "Delay in processing",
                    2 => "Incorrect order details",
                    3 => "Wrong quantity requested",
                    4 => "Stock quality issues",
                    5 => "Other",
                ];

                $data['cancellation_reason'] = [
                    'code' => $booking->cancellation_reason,
                    'text' => $reasonMap[$booking->cancellation_reason] ?? null
                ];
            }

            // dd($status);

            // Add vehicle_booking_status only if status is Confirmed or beyond
            $confirmedStatuses = ['Confirmed', 'Driver_assigned', 'In_progress', 'Delivered'];
            if (in_array($status, $confirmedStatuses)) {
                $data['vehicle_booking_status'] = $this->buildBookingStatusTimeline($booking, $status);
            }

            return response()->json([
                'success' => true,
                'message' => 'Booking details fetched successfully',
                'data' => $data,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching booking details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function updateVehicleBookingStatus(Request $request)
    {
        try {
            $request->validate([
                'booking_id' => 'required|exists:bookings,id',
                'status' => 'required|integer|between:1,6',
            ]);

            $statusMap = [
                1 => 'Pending',
                2 => 'Confirmed',
                3 => 'Driver_assigned',
                4 => 'In_progress',
                5 => 'Delivered',
                6 => 'Cancelled'
            ];


            $booking = Booking::find($request->booking_id);

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found.'
                ], 404);
            }

            // Update status
            // $booking->status = $request->status;
            $booking->status = 6;
            $booking->save();

            return response()->json([
                'success' => true,
                'message' => 'Vehicle booking status updated successfully.',
                'data' => [
                    'booking_id' => $booking->id,
                    'new_status_value' => $booking->status,
                    'new_status_label' => $statusMap[$booking->status]
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Build booking status timeline
     */
    // private function buildBookingStatusTimeline($booking, $currentStatus)
    // {
    //     $statuses = ['confirm', 'driver_assigned', 'in_progress', 'delivered'];
    //     $statusMap = [
    //         'Confirmed' => 0,
    //         'Driver_assigned' => 1,
    //         'In_progress' => 2,
    //         'Delivered' => 3,
    //     ];

    //     $currentIndex = $statusMap[$currentStatus] ?? -1;
    //     $timeline = [];

    //     foreach ($statuses as $index => $statusKey) {
    //         // Only include statuses up to and including current status
    //         if ($index <= $currentIndex) {
    //             $date = null;
    //             $time = null;

    //             // Use appropriate timestamps based on status
    //             switch ($statusKey) {
    //                 case 'confirm':
    //                     $date = $booking->created_at;
    //                     break;
    //                 case 'driver_assigned':
    //                     $date = $booking->updated_at ?? $booking->created_at;
    //                     break;
    //                 case 'in_progress':
    //                     $date = $booking->vehicle_started_date ?? $booking->updated_at;
    //                     break;
    //                 case 'delivered':
    //                     $date = $booking->vehicle_end_date ?? $booking->updated_at;
    //                     break;
    //             }

    //             $timeline[] = [
    //                 'title' => $statusKey,
    //                 'date' => $date ? Carbon::parse($date)->format('D, d-m-Y') : null,
    //                 'time' => $date ? Carbon::parse($date)->format('h:i A') : null,
    //             ];
    //         }
    //     }

    //     return $timeline;
    // }


    private function buildBookingStatusTimeline($booking, $currentStatus)
    {
        // FIXED ORDER YOU WANT
        $statuses = ['confirm', 'driver_assigned', 'in_progress', 'delivered'];

        // MAP STATUS TEXT TO INDEX BASED ON YOUR LOGIC
        $statusMap = [
            'Confirmed' => 0, // only confirm true
            'Driver_assigned' => 1, // confirm + in_progress true
            'In_progress' => 2, // confirm + in_progress + driver_assigned true
            'Delivered' => 3, // all true
        ];

        $currentIndex = $statusMap[$currentStatus] ?? 0;

        $timeline = [];

        foreach ($statuses as $index => $statusKey) {

            // SELECT DATE per status
            switch ($statusKey) {
                case 'confirm':
                    $date = $booking->created_at;
                    break;

                case 'in_progress':
                    $date = $booking->vehicle_started_date ?? $booking->updated_at;
                    break;

                case 'driver_assigned':
                    $date = $booking->driver_assigned_at ?? $booking->updated_at;
                    break;

                case 'delivered':
                    $date = $booking->vehicle_end_date ?? $booking->updated_at;
                    break;
            }

            $timeline[] = [
                'title' => $statusKey,
                'date' => $date ? Carbon::parse($date)->format('D, d-m-Y') : null,
                'time' => $date ? Carbon::parse($date)->format('h:i A') : null,
                'completed' => $index <= $currentIndex   // MAIN TRUE/FALSE LOGIC
            ];
        }

        return $timeline;
    }







    /**
     * ===============================
     * GET /api/farmer/vehicle_booking_delete/{id}
     * ===============================
     * Delete a vehicle booking
     */
    // public function vehicleBookingDelete($id)
    // {
    //     try {
    //         $farmer = Auth::user();

    //         if (!$farmer) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Unauthorized. Please login.',
    //             ], 401);
    //         }

    //         // Fetch the booking
    //         $booking = Booking::where('id', $id)
    //             ->where(function ($q) use ($farmer) {
    //                 $q->where('customer_mobile', $farmer->mobile)
    //                   ->orWhere('farmer_id', $farmer->id);
    //             })
    //             ->first();

    //         if (!$booking) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Booking not found or access denied.',
    //             ], 404);
    //         }

    //         // Check if booking can be deleted (only pending or confirmed bookings)
    //         $status = strtolower($booking->status ?? 'pending');
    //         $nonDeletableStatuses = ['in_progress', 'delivered'];

    //         if (in_array($status, $nonDeletableStatuses)) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Cannot delete booking. Booking is already ' . $status . '.',
    //             ], 400);
    //         }

    //         // Delete the booking
    //         $booking->delete();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Booking deleted successfully.',
    //         ]);

    //     } catch (Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error deleting booking',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    //for vechicle canacellation with reason code
    public function vehicleBookingCancel(Request $request)
    {
        try {
            $request->validate([
                'booking_id' => 'required|exists:bookings,id',
                'reason_code' => 'required|integer|between:1,5',
            ]);

            $reasonMap = [
                1 => "Delay in processing",
                2 => "Incorrect order details",
                3 => "Wrong quantity requested",
                4 => "Stock quality issues",
                5 => "Other",
            ];

            $booking = Booking::find($request->booking_id);

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found.'
                ], 404);
            }

            // CANCEL ONLY
            $booking->status = 6;  // Cancelled
            $booking->cancellation_reason = $request->reason_code;
            $booking->save();

            return response()->json([
                'success' => true,
                'message' => 'Vehicle booking cancelled successfully.',
                'data' => [
                    'booking_id' => $booking->id,
                    'status' => 'Cancelled',
                    'reason_text' => $reasonMap[$request->reason_code]
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error cancelling booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }





    /**
     * ===============================
     * GET /api/farmer/vehicle_tracking/{id}
     * ===============================
     * Fetch vehicle tracking details for a specific booking
     */
    public function vehicleTracking($id, Request $request)
    {
        try {
            $farmer = Auth::user();

            if (!$farmer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized. Please login.',
                ], 401);
            }

            // Fetch the booking with trackings
            $booking = Booking::with(['hatchery.vendor', 'trackings'])
                ->where('id', $id)
                ->where(function ($q) use ($farmer) {
                    $q->where('customer_mobile', $farmer->mobile)
                        ->orWhere('farmer_id', $farmer->id);
                })
                ->first();

            if (!$booking) {
                return response()->json([
                    'status' => false,
                    'message' => 'Booking not found or access denied.',
                ], 404);
            }

            // ── ON-DEMAND DRIVER LOCATION PING ──
            // The driver app posts location every 10–15 s while moving, but
            // can fall silent on iOS lock / Doze / hotspot switch. Whenever
            // a farmer opens the tracking screen, send a silent FCM data push
            // asking the driver phone to capture and POST a fresh GPS fix.
            // Mirrors the vendor-side trigger so customers also get fresh
            // location on open. Driver-side handler:
            //   Best-Seeds/lib/services/notification_service.dart
            // (data.type === "request_location"). Fire-and-forget — never
            // block the tracking response on the push.
            //
            // Guards:
            //   • only active (status == 4) bookings — no ping for delivered/cancelled
            //   • driver must have an FCM token registered
            //   • skip if backend already received a fresh fix within the last 30 s
            //   • Cache lock to dedupe N farmers + vendors opening the same booking
            //     within a 5 s window (only 1 push reaches the driver, shared
            //     with the vendor endpoint via the same cache key)
            try {
                if (
                    $booking->status == 4
                    && $booking->driver_id
                ) {
                    $needsRefresh = !$booking->driver_location_updated_at
                        || $booking->driver_location_updated_at->lt(now()->subSeconds(30));

                    if ($needsRefresh) {
                        $lockKey = 'on_demand_loc_ping:' . $booking->id;
                        if (Cache::add($lockKey, 1, 5)) {
                            $driver = Driver::find($booking->driver_id);
                            if ($driver && !empty($driver->fcm_token)) {
                                app(FirebaseNotificationService::class)
                                    ->sendDataToDevice($driver->fcm_token, [
                                        'type'       => 'request_location',
                                        'booking_id' => (string) $booking->id,
                                    ]);
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('on_demand_loc_ping failed (farmer)', [
                    'booking_id' => $booking->id,
                    'error'      => $e->getMessage(),
                ]);
            }

            $routeWaypoints = [];
            if ($booking->driver_id) {
                $query = Booking::where('driver_id', $booking->driver_id)
                    ->whereIn('status', [4, 5, 6])
                    ->where('id', '!=', $booking->id);

                // Match bookings on the same day (vehicle_started_date or packing_date)
                if ($booking->vehicle_started_date) {
                    $query->whereDate('vehicle_started_date', $booking->vehicle_started_date);
                } elseif ($booking->packing_date) {
                    $query->whereDate('packing_date', $booking->packing_date);
                } else {
                    // Fallback: bookings started today or still active
                    $query->where(function ($q) {
                        $q->whereDate('vehicle_started_date', today())
                          ->orWhere(function ($q2) {
                              $q2->whereNull('vehicle_started_date')
                                 ->where('status', 4);
                          });
                    });
                }

                // Include bookings with lower priority (they come before this one)
                if ($booking->priority !== null && $booking->priority > 0) {
                    $query->where('priority', '<', $booking->priority);
                }

                $routeWaypoints = $query
                    ->orderBy('priority', 'asc')
                    ->get()
                    ->filter(function ($routeBooking) {
                        return $routeBooking->drop_lat && $routeBooking->drop_lng;
                    })
                    ->map(function ($routeBooking) {
                        return [
                            'lat' => (float) $routeBooking->drop_lat,
                            'lng' => (float) $routeBooking->drop_lng,
                            'status' => (int) $routeBooking->status,
                            'priority' => (int) $routeBooking->priority,
                            'name' => $routeBooking->dropping_location ?? $routeBooking->delivery_location ?? '',
                            'is_before' => true,
                        ];
                    })
                    ->values()
                    ->all();
            }

            // ── ADMIN-SET PICKUP (separate from driver start) ──
            // Capture the admin's intended pickup BEFORE any heal/derived
            // logic touches `vehicle_start_lat`. Admin sets this in the
            // booking form; the driver may actually start the journey
            // somewhere else (e.g. admin entered Vikarabad but the truck
            // started from Madhapur). The customer app uses this field to
            // draw the "approach" green line from admin's pickup to where
            // the driver actually began moving.
            $adminPickupLat = $booking->vehicle_start_lat
                ? (float) $booking->vehicle_start_lat
                : null;
            $adminPickupLng = $booking->vehicle_start_lng
                ? (float) $booking->vehicle_start_lng
                : null;
            $adminPickupName = $booking->vehicle_start_address;

            // ── DRIVER PICKUP (where the journey actually started) ──
            // The first VehicleTracking row is where the driver tapped
            // "start trip". This is the start of actual movement.
            //
            // The previous version of this code "healed" vehicle_start_lat
            // to match the first tracking row, which destroyed the admin's
            // intended pickup forever. The heal has been removed — admin's
            // value is now exposed via `admin_pickup` separately, while
            // `pickup` continues to mean "driver journey start".
            $firstTracking = VehicleTracking::where('booking_id', $booking->id)
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->orderBy('reached_at', 'asc')
                ->first();

            if ($firstTracking) {
                $pickup = [
                    'name' => $firstTracking->location_name
                        ?? $booking->vehicle_start_address
                        ?? 'Pickup location',
                    'lat' => (float) $firstTracking->lat,
                    'lng' => (float) $firstTracking->lng,
                ];
            } elseif ($booking->vehicle_start_lat && $booking->vehicle_start_lng) {
                // No tracking rows yet (driver just started, GPS hasn't fired).
                // The vehicle_start_lat from startJourney is the best we have.
                $pickup = [
                    'name' => $booking->vehicle_start_address ?? "N/A",
                    'lat' => (float) $booking->vehicle_start_lat,
                    'lng' => (float) $booking->vehicle_start_lng,
                ];
            } else {
                $pickup = [
                    'name' => $booking->vehicle_start_address ?? "N/A",
                    'lat' => null,
                    'lng' => null,
                ];
            }

            // Drop details
            $dropName = $booking->dropping_location ?? $booking->delivery_location ?? 'N/A';
            $drop = [
                'name' => $dropName,
                'lat' => $booking->drop_lat ? (float) $booking->drop_lat : null,
                'lng' => $booking->drop_lng ? (float) $booking->drop_lng : null,
            ];

            // ── Driver status — 5-state model (Uber-style) ─────────────────────
            //
            // Only mark "idle/stopped" when updates ARE arriving and speed is 0.
            // A missing update is NEVER "halted" — it's a signal gap.
            //
            //  driver_status values:
            //   'moving'      – fresh GPS, net displacement > 30 m in last 60 s
            //   'idle'        – fresh GPS, displacement ≤ 30 m (genuine short stop)
            //   'signal_lost' – 2–5 min gap (poor signal / Doze / OEM throttle)
            //   'offline'     – 5–30 min gap (no signal, last position stale)
            //   'stopped'     – > 30 min gap (long break / loading / end of shift)
            //
            // `is_moving` kept for backward-compat.  Clients should prefer `driver_status`.
            $driverStatus  = 'stopped';   // default: no data at all
            $isMoving      = false;
            $locationStale = false;
            $speedKmh      = 0.0;

            if ($booking->driver_lat && $booking->driver_lng && $booking->driver_location_updated_at) {
                $secsSinceUpdate = (int) Carbon::parse($booking->driver_location_updated_at)->diffInSeconds(now());

                if ($secsSinceUpdate < 120) {
                    // ── Fresh GPS (< 2 min) — check actual net displacement ──
                    $recentPoint = VehicleTracking::where('booking_id', $booking->id)
                        ->whereNotNull('reached_at')
                        ->where('reached_at', '<=', now()->subSeconds(60))
                        ->latest('reached_at')
                        ->first();

                    if ($recentPoint) {
                        $netDist = self::haversineDistance(
                            (float) $recentPoint->lat, (float) $recentPoint->lng,
                            (float) $booking->driver_lat, (float) $booking->driver_lng
                        ) * 1000;
                        $elapsed  = max(1, (int) Carbon::parse($recentPoint->reached_at)->diffInSeconds(now()));
                        $speedKmh = round(($netDist / $elapsed) * 3.6, 1);
                        // speed > 0 or moved >30 m → moving; otherwise idle short stop
                        if ($netDist > 30) {
                            $driverStatus = 'moving';
                            $isMoving     = true;
                        } else {
                            $driverStatus = 'idle';   // stopped but GPS is live
                            $isMoving     = false;
                        }
                    } else {
                        // No 60 s-old anchor yet → assume moving (journey just started)
                        $driverStatus = 'moving';
                        $isMoving     = true;
                    }

                } elseif ($secsSinceUpdate < 300) {
                    // ── Poor signal (2–5 min gap) — WorkManager / Doze hiccup ──
                    // Driver was likely still moving. Show "signal lost" — never "halted".
                    $driverStatus  = 'signal_lost';
                    $isMoving      = true;   // preserve last-known moving state
                    $locationStale = true;

                } elseif ($secsSinceUpdate < 1800) {
                    // ── Offline (5–30 min gap) — no signal / OEM kill ──
                    // Show last known position with "location unavailable" badge.
                    $driverStatus  = 'offline';
                    $isMoving      = true;   // don't assume stopped
                    $locationStale = true;

                } else {
                    // ── Stopped (> 30 min gap) — long break / loading / end of shift ──
                    $driverStatus  = 'stopped';
                    $isMoving      = false;
                    $locationStale = true;
                }
            }

            $driverLocation = [
                'name'          => $booking->driver_location_name ?? 'Location not available',
                'lat'           => $booking->driver_lat ? round((float) $booking->driver_lat, 6) : null,
                'lng'           => $booking->driver_lng ? round((float) $booking->driver_lng, 6) : null,
                'updated_at'    => $booking->driver_location_updated_at
                    ? Carbon::parse($booking->driver_location_updated_at)->toDateTimeString()
                    : null,
                'driver_status' => $driverStatus,   // moving | idle | signal_lost | offline | stopped
                'is_moving'     => $isMoving,        // backward-compat bool
                'location_stale'=> $locationStale,
                'speed_kmh'     => $speedKmh,
            ];

            // Driver details
            $driverImage = $booking->driver_image;
            if ($driverImage && !preg_match('/^https?:\/\//', $driverImage)) {
                $driverImage = url('public/' . ltrim($driverImage, '/'));
            }
            $driverDetails = [
                'driver_name' => $booking->driver_name ?? 'N/A',
                'driver_phone' => $booking->driver_mobile ?? 'N/A',
                'vehicle_number' => $booking->vehicle_number ?? 'N/A',
                'driver_image' => $driverImage,
            ];

            // Delivery updates
            $deliveryUpdates = [
                'delivery_expected' => $booking->delivery_expected
                    ? Carbon::parse($booking->delivery_expected)->format('d/m/Y')
                    : ($booking->vehicle_end_date
                        ? Carbon::parse($booking->vehicle_end_date)->format('d/m/Y')
                        : null),
                'note' => $booking->delivery_note
                    ?? "We've received your booking. Within a few days, we will assign your vehicle",
            ];

            // precision=low  → only pickup + drop lat/lng (no tracking points)
            // precision=high → all tracking points (sampled max 100)
            $precision = $request->query('precision', 'high');

            // Timeline: only pickup + destination (frontend doesn't use raw GPS points)
            $timeline = $this->buildDefaultTimeline($booking, $pickup, $drop);

            if (false) { // Raw tracking points disabled — frontend uses passed_stops instead
            if ($booking->trackings->count() > 0) {
                // High precision: cleaned, filtered, last N points only
                // Pipeline: sort → deduplicate clusters → reject impossible jumps → limit → normalize

                // Fetch ALL points ordered by timestamp (single source of truth)
                $allTrackings = VehicleTracking::where('booking_id', $booking->id)
                    ->whereNotNull('reached_at')
                    ->whereNotNull('lat')
                    ->whereNotNull('lng')
                    ->orderBy('reached_at', 'asc')
                    ->get();

                $cleanPoints = [];
                $prevLat = null;
                $prevLng = null;
                $prevTime = null;

                foreach ($allTrackings as $tracking) {
                    // Normalize to 6 decimal places (more = fake jitter)
                    $lat = round((float) $tracking->lat, 6);
                    $lng = round((float) $tracking->lng, 6);
                    $time = Carbon::parse($tracking->reached_at);

                    // STEP 1: Distance/time gate — drop GPS clusters and drift.
                    //
                    // Lowered back to 15 m (was 30 m). The 30 m threshold was
                    // too aggressive: it killed slow movement (traffic jams,
                    // signals, narrow lanes) and made the timeline look like
                    // the truck was "stopping" mid-road. 15 m sits just above
                    // the typical urban GPS noise floor (10–12 m) so real
                    // crawling movement still gets recorded while drift is
                    // still filtered.
                    //
                    // Stationary clusters are handled later by
                    // collapseStationaryClusters, so we don't need this gate
                    // to do double duty.
                    if ($prevLat !== null && $prevLng !== null) {
                        $dist = self::haversineDistance($prevLat, $prevLng, $lat, $lng) * 1000;
                        $secsDiff = $prevTime ? $prevTime->diffInSeconds($time) : 999;

                        // Reject impossible jumps (speed > 200 km/h cap).
                        // Cars/buses on expressways routinely hit 130-150
                        // km/h, so 200 km/h is the realistic upper bound.
                        if ($secsDiff > 0) {
                            $speedKmh = ($dist / $secsDiff) * 3.6;
                            if (($dist > 500 && $secsDiff < 5) || $speedKmh > 200) {
                                continue;
                            }
                        }

                        // Skip drift / clustered points: must move at least 15 m
                        if ($dist < 15) {
                            continue;
                        }
                    }

                    // STEP 2: Backtrack guard — if this point is within 40 m
                    // of any of the last 5 kept points, treat it as zig-zag
                    // noise. Reverted from 60 m / last 10 (too aggressive —
                    // killed legitimate slow turns and tight U-turns). The
                    // stationary-cluster collapse below handles the wider
                    // case.
                    $isBacktrack = false;
                    $lookback = min(5, count($cleanPoints));
                    for ($i = count($cleanPoints) - $lookback; $i < count($cleanPoints) - 1; $i++) {
                        if ($i < 0) continue;
                        $pt = $cleanPoints[$i];
                        $distBack = self::haversineDistance($pt['lat'], $pt['lng'], $lat, $lng) * 1000;
                        if ($distBack < 40) {
                            $isBacktrack = true;
                            break;
                        }
                    }
                    if ($isBacktrack) {
                        continue;
                    }

                    // STEP 3: Bearing reversal guard — if direction reversed
                    // > 150° between two short legs (< 80 m each), it's a
                    // u-turn artifact, not real driving.
                    if (count($cleanPoints) >= 2) {
                        $a = $cleanPoints[count($cleanPoints) - 2];
                        $b = $cleanPoints[count($cleanPoints) - 1];
                        $leg1 = self::haversineDistance($a['lat'], $a['lng'], $b['lat'], $b['lng']) * 1000;
                        $leg2 = self::haversineDistance($b['lat'], $b['lng'], $lat, $lng) * 1000;
                        if ($leg1 < 80 && $leg2 < 80) {
                            $bearing1 = self::bearingDeg($a['lat'], $a['lng'], $b['lat'], $b['lng']);
                            $bearing2 = self::bearingDeg($b['lat'], $b['lng'], $lat, $lng);
                            $delta = abs($bearing1 - $bearing2);
                            if ($delta > 180) $delta = 360 - $delta;
                            if ($delta > 150) {
                                continue;
                            }
                        }
                    }

                    $cleanPoints[] = [
                        'title' => $tracking->title ?? $tracking->location_name ?? '',
                        'subtitle' => $tracking->subtitle ?? '',
                        'time' => $time->format('g:i A'),
                        'date' => $time->format('d/m/Y'),
                        'status' => $tracking->status ?? 'pending',
                        'lat' => $lat,
                        'lng' => $lng,
                    ];
                    $prevLat = $lat;
                    $prevLng = $lng;
                    $prevTime = $time;
                }

                // STEP 4: Local smoothing — no external API, no cost.
                //
                // Pipeline (order matters):
                //   (a) Stationary-cluster collapse: when 3+ consecutive
                //       points all lie inside an 80 m bounding circle,
                //       the driver is parked / waiting and GPS is just
                //       drifting. Collapse the whole run to ONE point
                //       (the centroid). This is what was causing the
                //       visible "circles" in the customer app — Google
                //       Directions was being asked to route through
                //       6 jittery points within 150 m and producing
                //       loops.
                //   (b) Moving-average filter, window=5: replaces each
                //       interior point's lat/lng with the average of
                //       itself and its neighbors. Kills high-frequency
                //       GPS jitter that causes the polyline to wiggle
                //       between parallel lanes.
                //   (c) Douglas-Peucker simplification, epsilon=25 m:
                //       drops points that lie within 25 m of the straight
                //       line between their neighbors. Removes redundant
                //       points on straight road segments while preserving
                //       turns. Cuts payload size and prevents residual
                //       loop artifacts.
                //
                //       WHY 25 m instead of 8 m:
                //       At ε=8 m, D-P was context-sensitive — as new GPS
                //       points were added to the end of a long journey,
                //       the global simplification would retroactively remove
                //       interior points that were kept on the previous API
                //       call. A user checking at hour 3 got 80 timeline
                //       points; the same user reopening at hour 8 got only
                //       45 because D-P now saw those earlier points as lying
                //       within 8 m of a much longer straight-line context.
                //       This "shrinking timeline" made the app think the
                //       driver took a different route on reopen and rendered
                //       two overlapping green lines.
                //       25 m sits well above the GPS noise floor (10–15 m)
                //       so it still removes jitter, but only removes a point
                //       when it is truly redundant regardless of how many
                //       points are added later — the result is stable across
                //       API calls for the same trip.
                //
                // First and last points are always preserved (start of
                // journey + most recent driver position) so the customer
                // app's pickup marker and live marker never shift.
                // Stationary collapse: tightened from (80 m, 3) to (50 m, 4).
                // The wider/looser version was wrongly collapsing slow-moving
                // traffic into a single point, making the truck appear stuck.
                // 50 m / 4 points still catches genuine stops (parked vehicles
                // drift well within 50 m for many consecutive readings) while
                // preserving crawling movement.
                // ── LAZY SNAP-ON-READ ──
                // The persisted `bookings.tracking_path` is the green-line
                // source of truth. We refresh it here, on the read path,
                // because that means we only ever pay for snap-to-roads
                // when somebody is actually looking at the tracking screen.
                // The refresh itself is throttled (>=8 unsnapped points OR
                // >=25 s since last refresh) so a customer polling every
                // 7 s shares one snap call across multiple polls.
                try {
                    $this->maybeRefreshTrackingPath($booking);
                    $booking->refresh();
                } catch (\Throwable $e) {
                    Log::warning('maybeRefreshTrackingPath failed (read path)', [
                        'booking_id' => $booking->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
                $persistedPath = is_array($booking->tracking_path) ? $booking->tracking_path : [];
                if (!empty($persistedPath)) {
                    $cleanPoints = array_map(function ($p) {
                        // Stored as [lat, lng] pairs (compact). Re-hydrate.
                        return [
                            'title'    => '',
                            'subtitle' => '',
                            'time'     => '',
                            'date'     => '',
                            'status'   => 'completed',
                            'lat'      => isset($p[0]) ? (float) $p[0] : (float) ($p['lat'] ?? 0),
                            'lng'      => isset($p[1]) ? (float) $p[1] : (float) ($p['lng'] ?? 0),
                        ];
                    }, $persistedPath);
                } else {
                    // Legacy / first-deploy fallback: run the snap pipeline
                    // on the fly. Once `addTrackingPoint` fires once with the
                    // new column populated, this branch stops being hit.
                    $cleanPoints = self::collapseStationaryClusters($cleanPoints, 50.0, 4);
                    $preSnap = $cleanPoints;
                    $snapped = $this->snapTimelineToRoads($booking->id, $preSnap);
                    if ($snapped !== $preSnap) {
                        $cleanPoints = $snapped;
                    } else {
                        $cleanPoints = self::movingAverageSmooth($preSnap, 5);
                        $cleanPoints = self::douglasPeucker($cleanPoints, 25.0);
                    }
                }

                // Return the FULL smoothed timeline — no cap.
                // Always bookend with titled Pickup + Destination so the
                // Flutter _buildMergedTimeline has named first/last entries.
                $pickupEntry = [
                    'title' => 'Pickup started from',
                    'subtitle' => $pickup['name'] ?? '',
                    'time' => $booking->vehicle_started_date
                        ? Carbon::parse($booking->vehicle_started_date)->format('g:i A')
                        : '-',
                    'date' => $booking->vehicle_started_date
                        ? Carbon::parse($booking->vehicle_started_date)->format('d/m/Y')
                        : '',
                    'status' => 'completed',
                    'lat' => $pickup['lat'] ?? null,
                    'lng' => $pickup['lng'] ?? null,
                ];
                $destEntry = [
                    'title' => 'Destination',
                    'subtitle' => $drop['name'] ?? '',
                    'time' => $booking->vehicle_end_date
                        ? Carbon::parse($booking->vehicle_end_date)->format('g:i A')
                        : '-',
                    'date' => $booking->vehicle_end_date
                        ? Carbon::parse($booking->vehicle_end_date)->format('d/m/Y')
                        : '',
                    'status' => ((int) $booking->status === 5) ? 'completed' : 'pending',
                    'lat' => $drop['lat'] ?? null,
                    'lng' => $drop['lng'] ?? null,
                ];
                $timeline = array_merge([$pickupEntry], array_values($cleanPoints), [$destEntry]);

                $timelinePagination = [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => count($timeline),
                    'total' => count($timeline),
                ];
            } else {
                // Default timeline if no trackings exist
                $timeline = $this->buildDefaultTimeline($booking, $pickup, $drop);
            }
            } // end disabled raw tracking block

            // ── Update passed stops in DB based on driver's current position ──
            $pickupLatForStops = (float) ($pickup['lat'] ?? 0);
            $pickupLngForStops = (float) ($pickup['lng'] ?? 0);
            $driverLatForStops = (float) ($driverLocation['lat'] ?? 0);
            $driverLngForStops = (float) ($driverLocation['lng'] ?? 0);

            if ($driverLatForStops && $driverLngForStops && $pickupLatForStops && $pickupLngForStops) {
                \App\Models\VehicleTrackingStop::updatePassedStops(
                    $booking->id, $driverLatForStops, $driverLngForStops,
                    $pickupLatForStops, $pickupLngForStops
                );
            }

            // ── Get passed stops from DB (clean, saved, permanent) ──
            $passedStops = \App\Models\VehicleTrackingStop::getPassedStops($booking->id);

            // ── Calculate distances ──
            $totalDistanceKm = 0;
            $remainingDistanceKm = 0;
            if ($pickupLatForStops && $pickupLngForStops && ($drop['lat'] ?? 0) && ($drop['lng'] ?? 0)) {
                $totalDistanceKm = round(self::haversineDistance(
                    $pickupLatForStops, $pickupLngForStops,
                    (float) $drop['lat'], (float) $drop['lng']
                ), 1);
            }
            if ($driverLatForStops && $driverLngForStops && ($drop['lat'] ?? 0) && ($drop['lng'] ?? 0)) {
                $remainingDistanceKm = round(self::haversineDistance(
                    $driverLatForStops, $driverLngForStops,
                    (float) $drop['lat'], (float) $drop['lng']
                ), 1);
            }

            // ── Driver breadcrumbs: compact [lat,lng] arrays for client path drawing ──
            $tripCutoffForBreadcrumbs = $booking->in_progress_at
                ? Carbon::parse($booking->in_progress_at)->subMinutes(10)
                : Carbon::now()->subHours(12);
            // reached_at is kept (not just lat/lng) because the visited-stop
            // detection below reads the gaps BETWEEN crumbs, not just their
            // positions. One query serves both.
            $allTrackingPoints = \App\Models\VehicleTracking::where('booking_id', $booking->id)
                ->whereNotNull('lat')->whereNotNull('lng')
                ->where('reached_at', '>=', $tripCutoffForBreadcrumbs)
                ->orderBy('reached_at', 'asc')
                ->get(['lat', 'lng', 'reached_at'])
                ->values();

            $allBreadcrumbs = $allTrackingPoints
                ->map(fn($t) => [round((float) $t->lat, 6), round((float) $t->lng, 6)])
                ->values()
                ->all();

            // Downsample if > 500 points: skip every Nth point to stay under 500
            $total = count($allBreadcrumbs);
            if ($total <= 500) {
                $driverBreadcrumbs = $allBreadcrumbs;
            } else {
                $skipRate = (int) ceil($total / 500);
                $driverBreadcrumbs = [];
                for ($i = 0; $i < $total; $i++) {
                    if ($i % $skipRate === 0 || $i === $total - 1) {
                        $driverBreadcrumbs[] = $allBreadcrumbs[$i];
                    }
                }
            }

            // ── Mark earlier drops the truck has ALREADY physically visited ──
            //
            // A driver can deliver an earlier-priority drop and forget to press
            // "Delivered", leaving that booking at status 4 — so a later
            // customer's map keeps routing BACK to it.
            //
            // The visit is detected from THIS booking's own GPS breadcrumbs.
            // Crumbs are only written while the truck is moving (>= 25 m since
            // the last one, see DriverBookingController), so a truck standing
            // still to unload writes nothing — the halt appears as a TIME GAP
            // between consecutive crumbs. That gap is what separates "stopped
            // here and delivered" from "drove past on the highway", which no
            // distance test alone can do.
            //
            // Deliberately additive: this sets a flag and never removes a
            // waypoint. The full route must keep every stop, because the green
            // covered line is sliced from it and the arrival time is computed
            // as a fraction of the whole route. Older app builds ignore the
            // extra key, so the backend can ship independently.
            if (!empty($routeWaypoints) && $allTrackingPoints->count() >= 2) {
                $stopRadiusKm = 0.6;    // must actually reach the drop
                $departedKm   = 1.5;    // and must have left again
                $dwellSecs    = 8 * 60; // an unload, not a traffic light
                $pointCount   = $allTrackingPoints->count();

                foreach ($routeWaypoints as $idx => $wp) {
                    $routeWaypoints[$idx]['is_passed'] = false;

                    $wLat = (float) $wp['lat'];
                    $wLng = (float) $wp['lng'];
                    if (!$wLat || !$wLng) {
                        continue;
                    }

                    // Never flag this booking's own destination.
                    if (
                        ($drop['lat'] ?? 0) && ($drop['lng'] ?? 0)
                        && self::haversineDistance($wLat, $wLng, (float) $drop['lat'], (float) $drop['lng']) < 0.2
                    ) {
                        continue;
                    }

                    $firstNear = null;
                    $lastNear = null;
                    for ($i = 0; $i < $pointCount; $i++) {
                        $t = $allTrackingPoints[$i];
                        if (self::haversineDistance((float) $t->lat, (float) $t->lng, $wLat, $wLng) <= $stopRadiusKm) {
                            if ($firstNear === null) {
                                $firstNear = $i;
                            }
                            $lastNear = $i;
                        }
                    }
                    // Never got there — keep routing through it.
                    if ($firstNear === null) {
                        continue;
                    }

                    // Must have left again, so a truck still approaching (or
                    // still parked at the drop) is not treated as done.
                    $departed = false;
                    for ($i = $lastNear + 1; $i < $pointCount; $i++) {
                        $t = $allTrackingPoints[$i];
                        if (self::haversineDistance((float) $t->lat, (float) $t->lng, $wLat, $wLng) >= $departedKm) {
                            $departed = true;
                            break;
                        }
                    }
                    if (!$departed) {
                        continue;
                    }

                    // Must have STOPPED there — the largest gap between crumbs
                    // while near the drop.
                    $maxGap = 0;
                    for ($i = $firstNear; $i <= $lastNear && $i + 1 < $pointCount; $i++) {
                        $gap = Carbon::parse($allTrackingPoints[$i]->reached_at)
                            ->diffInSeconds(Carbon::parse($allTrackingPoints[$i + 1]->reached_at));
                        if ($gap > $maxGap) {
                            $maxGap = $gap;
                        }
                    }
                    // Just drove past.
                    if ($maxGap < $dwellSecs) {
                        continue;
                    }

                    $routeWaypoints[$idx]['is_passed'] = true;
                }
            }

            // ── Road-snapped ACTUAL driven path (GREEN line source of truth) ──
            // Refresh (throttled) and expose the persisted snapped path. This is
            // the driver's real breadcrumbs snapped to the road network via the
            // Google Roads API, so the customer's green line COVERS the actual
            // driven points but follows road geometry — never a straight chord
            // and never the planned route. Empty until the driver has at least
            // a couple of tracking points (then the client green falls back to
            // just the short home→vehicle connector).
            $trackingPath = [];
            try {
                $this->maybeRefreshTrackingPath($booking);
                $booking->refresh();
                $persistedTrackingPath = is_array($booking->tracking_path) ? $booking->tracking_path : [];
                $trackingPath = array_map(function ($p) {
                    return [
                        'lat' => isset($p[0]) ? (float) $p[0] : (float) ($p['lat'] ?? 0),
                        'lng' => isset($p[1]) ? (float) $p[1] : (float) ($p['lng'] ?? 0),
                    ];
                }, $persistedTrackingPath);
            } catch (\Throwable $e) {
                Log::warning('tracking_path refresh failed (read path)', [
                    'booking_id' => $booking->id,
                    'error'      => $e->getMessage(),
                ]);
            }

            $data = [
                'vehicle_id' => $booking->vehicle_number ? $booking->id : null,
                'booking_id' => $booking->id,
                'pickup' => $pickup,
                'admin_pickup' => [
                    'name' => $adminPickupName,
                    'lat' => $adminPickupLat,
                    'lng' => $adminPickupLng,
                ],
                'drop' => $drop,
                'driver_location' => $driverLocation,
                'driver_details' => $driverDetails,
                'delivery_updates' => $deliveryUpdates,

                // ── Timeline (GPS tracking points, cleaned + snapped) ──
                'timeline' => $timeline,

                // ── PASSED STOPS (from DB — permanent, actual passed_at times) ──
                'passed_stops' => $passedStops,

                // ── Driver breadcrumbs for client path + stop detection ──
                'driver_breadcrumbs' => $driverBreadcrumbs,

                // ── Road-snapped actual driven path (green line source) ──
                'tracking_path' => $trackingPath,

                // ── Route waypoints for multi-drop ──
                'route_waypoints' => $routeWaypoints,

                // ── Distances ──
                'total_distance_km' => $totalDistanceKm,
                'remaining_distance_km' => $remainingDistanceKm,

                'travel_cost' => $booking->price ?? null,
                'expected_delivery' => $booking->delivery_expected
                    ? Carbon::parse($booking->delivery_expected)->format('d/m/Y')
                    : ($booking->vehicle_end_date
                        ? Carbon::parse($booking->vehicle_end_date)->format('d/m/Y')
                        : null),
                'vendor_mobile' => $booking->hatchery?->call_number ?? $booking->hatchery?->vendor?->mobile,
                'in_progress_at' => $booking->in_progress_at
                    ? Carbon::parse($booking->in_progress_at)->format('H:i')
                    : null,
                'is_delivered' => (int) $booking->status === 5,
                'delivered_at' => $booking->delivered_at
                    ? Carbon::parse($booking->delivered_at)->format('h:i A')
                    : null,
            ];

            // ── Status text (the tracking screen "Status" card) ───────────────
            // Previously this endpoint never returned a description, so the app
            // always showed the stale "we'll assign your vehicle" fallback even
            // after the vendor moved the booking to In Journey. Use the vendor's
            // per-booking override when set, otherwise a default that matches the
            // CURRENT booking status so the text stays in sync with the status.
            $statusDefaults = [
                1 => "We've received your booking. Within a few days, we will assign your vehicle.",
                2 => "Your booking is confirmed. We're arranging a vehicle for your order.",
                3 => "A driver has been assigned and will start the journey soon.",
                4 => "Your vehicle is on the way to your location.",
                5 => "Your order has been delivered. Thank you!",
                6 => "This booking has been cancelled.",
            ];
            $defaultDesc = $statusDefaults[(int) $booking->status] ?? $statusDefaults[1];

            $data['booking_description'] = !empty($booking->vendor_booking_description)
                ? $booking->vendor_booking_description
                : $defaultDesc;
            $data['vehicle_description'] = !empty($booking->vendor_vehicle_description)
                ? $booking->vendor_vehicle_description
                : $defaultDesc;

            return response()->json([
                'status' => true,
                'message' => 'Tracking data fetched successfully',
                'data' => $data,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error fetching tracking data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Save a passed stop from client to DB.
     * Called when client-generated upcoming stop gets passed by the driver.
     */
    public function savePassedStop($id, Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'lat' => 'required|numeric',
                'lng' => 'required|numeric',
                'type' => 'in:main,sub',
                'parent_stop_name' => 'nullable|string',
                'order' => 'nullable|integer',
                'dist_from_pickup_km' => 'nullable|numeric',
            ]);

            $parentStopId = null;
            if ($request->parent_stop_name) {
                $parent = \App\Models\VehicleTrackingStop::where('booking_id', $id)
                    ->where('name', $request->parent_stop_name)
                    ->where('type', 'main')
                    ->first();
                $parentStopId = $parent?->id;
            }

            $stop = \App\Models\VehicleTrackingStop::savePassedStop(
                bookingId: (int) $id,
                name: $request->name,
                lat: (float) $request->lat,
                lng: (float) $request->lng,
                type: $request->type ?? 'main',
                parentStopId: $parentStopId,
                order: $request->order ?? 0,
                distFromPickupKm: $request->dist_from_pickup_km ?? 0,
            );

            return response()->json([
                'status' => true,
                'message' => 'Stop saved successfully',
                'stop' => [
                    'id' => $stop->id,
                    'name' => $stop->name,
                    'passed_at' => $stop->passed_at?->format('g:i A'),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error saving stop',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Build default timeline when no tracking records exist
     */
    private function buildDefaultTimeline($booking, $pickup, $drop)
    {
        $status = strtolower($booking->status ?? 'pending');
        $timeline = [];

        // Pickup point
        $pickupStatus = in_array($status, ['in_progress', 'delivered']) ? 'completed' : 'pending';
        $timeline[] = [
            'title' => 'Pickup started from',
            'subtitle' => $pickup['name'],
            'time' => $booking->vehicle_started_date
                ? Carbon::parse($booking->vehicle_started_date)->format('g:i A')
                : '-',
            'date' => $booking->vehicle_started_date
                ? Carbon::parse($booking->vehicle_started_date)->format('d/m/Y')
                : '',
            'status' => $pickupStatus,
        ];

        // Destination point
        $destStatus = $status === 'delivered' ? 'completed' : 'pending';
        $timeline[] = [
            'title' => 'Destination',
            'subtitle' => $drop['name'],
            'time' => $booking->vehicle_end_date
                ? Carbon::parse($booking->vehicle_end_date)->format('g:i A')
                : '-',
            'date' => $booking->vehicle_end_date
                ? Carbon::parse($booking->vehicle_end_date)->format('d/m/Y')
                : '',
            'status' => $destStatus,
        ];

        return $timeline;
    }

    /**
     * Build auto_timeline_points with dynamically-computed ETAs.
     *
     * Stop names / positions are cached for 30 days so we never re-hit the
     * Directions + Geocoding APIs for them.  ETAs are recomputed on every
     * request so halt time and driver progress are always current.
     *
     * Algorithm:
     *   • driver_fraction — time-elapsed proxy: (now − in_progress_at) / totalDuration.
     *     Good enough for ETA ordering; avoids a polyline snap API call per request.
     *   • Completed stops (fraction ≤ driver_fraction): ETA = in_progress_at + fraction × totalDuration.
     *   • Pending stops   (fraction >  driver_fraction): ETA = anchorTime + proportional remaining.
     *   • anchorTime = now() when driver is halted (is_moving=false), otherwise
     *     driver_location.updated_at.  This means every extra minute the driver
     *     parks pushes future ETAs forward by one minute — identical to Google Maps.
     */
    private function buildAutoTimelineWithETAs(Booking $booking, array $driverLocation): array
    {
        $service       = new RouteTimelineService();
        $stops         = $service->getIntermediateStops($booking);
        $totalDuration = $service->getTotalDurationSeconds($booking);

        if (empty($stops) || $totalDuration <= 0 || !$booking->in_progress_at) {
            return $stops; // no ETA data available — return stops as-is
        }

        $startTime = Carbon::parse($booking->in_progress_at);
        $now       = now();

        // How far along the route is the driver, measured in elapsed journey time.
        $elapsedSeconds = max(0, $now->diffInSeconds($startTime));
        $driverFraction = min($elapsedSeconds / $totalDuration, 0.99);

        // Remaining seconds from driver's current position to destination.
        $remainingSeconds = max($totalDuration - $elapsedSeconds, 60); // floor 60s

        // ETA anchor strategy per driver_status:
        //   'moving'      → anchor = last GPS timestamp (driver is en route)
        //   'idle'        → anchor = now()  (driver paused; halt time adds to ETAs)
        //   'signal_lost' → anchor = last GPS timestamp (don't penalise a gap)
        //   'offline'     → anchor = last GPS timestamp (same)
        //   'stopped'     → anchor = now()  (long break; ETAs shift forward)
        $driverStatus  = $driverLocation['driver_status'] ?? 'moving';
        $locationStale = (bool) ($driverLocation['location_stale'] ?? false);
        $driverTime    = !empty($driverLocation['updated_at'])
            ? Carbon::parse($driverLocation['updated_at'])
            : $now;
        $anchorTime = in_array($driverStatus, ['idle', 'stopped']) ? $now : $driverTime;

        return array_map(function (array $stop) use (
            $startTime, $totalDuration, $driverFraction, $anchorTime, $remainingSeconds
        ): array {
            $fraction = (float) ($stop['dist_fraction'] ?? 0);

            if ($fraction <= $driverFraction) {
                // Behind the driver — show the historical estimated pass time.
                $eta = $startTime->copy()->addSeconds((int) ($fraction * $totalDuration));
            } else {
                // Ahead of the driver — project forward from anchor.
                $routeAhead       = max(1.0 - $driverFraction, 0.001);
                $stopAhead        = $fraction - $driverFraction;
                $secondsFromAnchor = (int) (($stopAhead / $routeAhead) * $remainingSeconds);
                $eta              = $anchorTime->copy()->addSeconds($secondsFromAnchor);
            }

            $stop['estimated_arrival'] = $eta->format('g:i A');
            $stop['estimated_date']    = $eta->format('d/m/Y');
            return $stop;
        }, $stops);
    }

    /**
     * ===============================
     * POST /api/driver/update_location
     * ===============================
     * Update driver's current location (called by driver app)
     */
    public function updateDriverLocation(Request $request)
    {
        $logPrefix = '[DriverLocation][booking=' . $request->booking_id . ']';

        // ── LOG 1: Raw incoming payload ──
        \Log::channel('driver_location')->info("{$logPrefix} REQUEST received", [
            'ip'            => $request->ip(),
            'booking_id'    => $request->booking_id,
            'lat'           => $request->lat,
            'lng'           => $request->lng,
            'location_name' => $request->location_name,
            'is_moving'     => $request->is_moving,
            'timestamp'     => now()->toDateTimeString(),
        ]);

        try {
            $request->validate([
                'booking_id' => 'required|integer|exists:bookings,id',
                'lat' => 'required|numeric',
                'lng' => 'required|numeric',
                'location_name' => 'nullable|string|max:255',
            ]);

            $booking = Booking::find($request->booking_id);

            if (!$booking) {
                \Log::channel('driver_location')->warning("{$logPrefix} REJECTED — booking not found");
                return response()->json([
                    'status' => false,
                    'message' => 'Booking not found.',
                ], 404);
            }

            $newLat = round((float) $request->lat, 6);
            $newLng = round((float) $request->lng, 6);

            // ── LOG 2: Parsed & rounded coords ──
            \Log::channel('driver_location')->info("{$logPrefix} PARSED coords lat={$newLat}, lng={$newLng}");

            // ── GPS spike validation (same 4-layer rules as the loop endpoint) ──
            //
            // driver_lat / driver_lng on the bookings row drive the LIVE
            // MARKER on the customer app. If we let raw GPS through here, the
            // marker will jump 200–500 m even when the green breadcrumb line
            // stays clean — the user sees a smooth path with a teleporting
            // truck and assumes the whole tracking system is broken.
            //
            // Anchor against the most recent KNOWN-GOOD tracking row, NOT
            // against $booking->driver_lat (which would drift if a borderline
            // spike sneaks through).
            $isSpike = false;
            $spikeReason = '';
            $anchorPoint = VehicleTracking::where('booking_id', $booking->id)
                ->whereNotNull('reached_at')
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->latest('reached_at')
                ->first();

            if ($anchorPoint) {
                $distMeters = self::haversineDistance(
                    (float) $anchorPoint->lat, (float) $anchorPoint->lng,
                    $newLat, $newLng
                ) * 1000;
                $secs = Carbon::parse($anchorPoint->reached_at)->diffInSeconds(now());
                $speedKmh = $secs > 0 ? round(($distMeters / $secs) * 3.6, 1) : 0;

                // ── LOG 3: Spike check details ──
                \Log::channel('driver_location')->info("{$logPrefix} SPIKE CHECK", [
                    'anchor_lat'   => $anchorPoint->lat,
                    'anchor_lng'   => $anchorPoint->lng,
                    'anchor_time'  => $anchorPoint->reached_at,
                    'dist_meters'  => round($distMeters, 1),
                    'secs_elapsed' => $secs,
                    'speed_kmh'    => $speedKmh,
                ]);

                // Tuned for 200 km/h cap. See DriverBookingController for
                // the per-layer math (5/10/60 s windows + speed gate).
                if ($distMeters > 500 && $secs < 5) {
                    $isSpike = true; $spikeReason = "dist={$distMeters}m in {$secs}s (>500m/<5s)";
                } elseif ($distMeters > 4000 && $secs < 60) {
                    $isSpike = true; $spikeReason = "dist={$distMeters}m in {$secs}s (>4km/<60s)";
                } elseif ($distMeters > 700 && $secs < 10) {
                    $isSpike = true; $spikeReason = "dist={$distMeters}m in {$secs}s (>700m/<10s)";
                } elseif ($secs > 0 && $speedKmh > 200) {
                    $isSpike = true; $spikeReason = "speed={$speedKmh}km/h (>200 cap)";
                }

                if ($isSpike) {
                    \Log::channel('driver_location')->warning("{$logPrefix} SPIKE REJECTED — {$spikeReason}");
                    \Log::info("updateDriverLocation spike rejected: booking={$booking->id}, dist={$distMeters}m in {$secs}s");
                }
            } elseif ($booking->vehicle_start_lat && $booking->vehicle_start_lng) {
                // First-ever update — sanity check vs pickup
                $distFromPickup = self::haversineDistance(
                    (float) $booking->vehicle_start_lat, (float) $booking->vehicle_start_lng,
                    $newLat, $newLng
                ) * 1000;

                \Log::channel('driver_location')->info("{$logPrefix} FIRST-POINT CHECK dist_from_pickup={$distFromPickup}m");

                if ($distFromPickup > 1500) {
                    $isSpike = true;
                    $spikeReason = "first point {$distFromPickup}m from pickup (>1500m)";
                    \Log::channel('driver_location')->warning("{$logPrefix} FIRST-POINT REJECTED — {$spikeReason}");
                    \Log::info("updateDriverLocation first-point too far from pickup: booking={$booking->id}, dist={$distFromPickup}m");
                }
            } else {
                // No anchor and no pickup coords — cannot validate, allow through
                \Log::channel('driver_location')->info("{$logPrefix} NO ANCHOR — no prior tracking point or pickup coords, accepting as-is");
            }

            if ($isSpike) {
                return response()->json([
                    'status' => false,
                    'message' => 'GPS spike rejected — location not updated.',
                ], 422);
            }

            // Update driver location in booking
            $locationName = $request->location_name ?? $booking->driver_location_name;
            $booking->update([
                'driver_lat' => $newLat,
                'driver_lng' => $newLng,
                'driver_location_name' => $locationName,
                'driver_location_updated_at' => now(),
            ]);

            // ── PROPAGATE TO SIBLING DROPS ON THE SAME JOURNEY ──
            // A driver serving a multi-drop route (priority 1, 2, 3…) only
            // posts location for the ACTIVE booking_id. Without this, the
            // sibling bookings (priority 2/3) keep a stale driver_lat/lng, so
            // their customer's live marker freezes at the start while the
            // planned route still renders. Mirror the position onto every
            // active sibling of the same driver + same journey day (same
            // grouping used to build route_waypoints above).
            if ($booking->driver_id) {
                $siblingQuery = Booking::where('driver_id', $booking->driver_id)
                    ->whereIn('status', [4, 5, 6])
                    ->where('id', '!=', $booking->id);

                if ($booking->vehicle_started_date) {
                    $siblingQuery->whereDate('vehicle_started_date', $booking->vehicle_started_date);
                } elseif ($booking->packing_date) {
                    $siblingQuery->whereDate('packing_date', $booking->packing_date);
                } else {
                    $siblingQuery->where(function ($q) {
                        $q->whereDate('vehicle_started_date', today())
                          ->orWhere(function ($q2) {
                              $q2->whereNull('vehicle_started_date')->where('status', 4);
                          });
                    });
                }

                $propagated = $siblingQuery->update([
                    'driver_lat' => $newLat,
                    'driver_lng' => $newLng,
                    'driver_location_name' => $locationName,
                    'driver_location_updated_at' => now(),
                ]);

                \Log::channel('driver_location')->info("{$logPrefix} PROPAGATED to {$propagated} sibling drop(s)");
            }

            // ── LOG 4: Success ──
            \Log::channel('driver_location')->info("{$logPrefix} SAVED lat={$newLat}, lng={$newLng}", [
                'location_name' => $request->location_name ?? $booking->driver_location_name,
                'saved_at'      => now()->toDateTimeString(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Driver location updated successfully.',
                'data' => [
                    'booking_id' => $booking->id,
                    'driver_lat' => $booking->driver_lat,
                    'driver_lng' => $booking->driver_lng,
                    'driver_location_name' => $booking->driver_location_name,
                    'updated_at' => $booking->updated_at->format('Y-m-d H:i:s'),
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $ve) {
            \Log::channel('driver_location')->error("{$logPrefix} VALIDATION FAILED", [
                'errors' => $ve->errors(),
                'input'  => $request->only(['booking_id', 'lat', 'lng', 'location_name']),
            ]);
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $ve->errors(),
            ], 422);
        } catch (Exception $e) {
            \Log::channel('driver_location')->error("{$logPrefix} EXCEPTION — " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Error updating driver location',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ===============================
     * POST /api/driver/add_tracking_point
     * ===============================
     * Add a new tracking point to the timeline
     */
    public function addTrackingPoint(Request $request)
    {
        try {
            $request->validate([
                'booking_id' => 'required|integer|exists:bookings,id',
                'location_name' => 'required|string|max:255',
                'lat' => 'nullable|numeric',
                'lng' => 'nullable|numeric',
                'title' => 'nullable|string|max:255',
                'subtitle' => 'nullable|string|max:255',
                'status' => 'nullable|in:completed,current,pending',
            ]);

            $booking = Booking::find($request->booking_id);

            if (!$booking) {
                return response()->json([
                    'status' => false,
                    'message' => 'Booking not found.',
                ], 404);
            }

            // ── Validate BEFORE storing (same rules as updateDriverLocation) ──
            // addTrackingPoint inserts straight into the timeline, so without
            // validation a single bad GPS sample becomes a permanent kink.
            $newLat = $request->lat !== null ? round((float) $request->lat, 6) : null;
            $newLng = $request->lng !== null ? round((float) $request->lng, 6) : null;

            if ($newLat !== null && $newLng !== null) {
                $anchorPoint = VehicleTracking::where('booking_id', $request->booking_id)
                    ->whereNotNull('reached_at')
                    ->whereNotNull('lat')
                    ->whereNotNull('lng')
                    ->latest('reached_at')
                    ->first();

                if ($anchorPoint) {
                    $distMeters = self::haversineDistance(
                        (float) $anchorPoint->lat, (float) $anchorPoint->lng,
                        $newLat, $newLng
                    ) * 1000;
                    $secs = Carbon::parse($anchorPoint->reached_at)->diffInSeconds(now());

                    // Same 200 km/h cap as updateDriverLocation — see
                    // DriverBookingController for the layer math.
                    $isSpike = false;
                    if ($distMeters > 500 && $secs < 5) {
                        $isSpike = true;
                    } elseif ($distMeters > 4000 && $secs < 60) {
                        $isSpike = true;
                    } elseif ($distMeters > 700 && $secs < 10) {
                        $isSpike = true;
                    } elseif ($secs > 0 && ($distMeters / $secs) * 3.6 > 200) {
                        $isSpike = true;
                    }

                    if ($isSpike) {
                        \Log::info("addTrackingPoint spike rejected: booking={$request->booking_id}, dist={$distMeters}m in {$secs}s");
                        return response()->json([
                            'status' => false,
                            'message' => 'GPS spike rejected — point not stored.',
                        ], 422);
                    }
                }
                // NOTE: "first-point within 1.5 km of pickup" check removed.
                // It caused an infinite rejection loop when the admin's pickup
                // coords (vehicle_start_lat/lng) differed from the driver's
                // actual journey start by >1.5 km — the customer journey would
                // pull pickup from booking.pickup_lat/lng while admin would
                // override with vehicle_start_lat/lng. Every first-point call
                // rejected → no anchor saved → next call still has no anchor
                // → still rejected → vehicle_trackings stayed empty forever
                // for that booking. The 4 spike layers above are sufficient
                // for real GPS teleport detection without this gate.
            }

            // Get the next order number
            $maxOrder = VehicleTracking::where('booking_id', $request->booking_id)->max('order') ?? 0;

            // Mark previous "current" as "completed"
            VehicleTracking::where('booking_id', $request->booking_id)
                ->where('status', 'current')
                ->update(['status' => 'completed']);

            // Create new tracking point
            $tracking = VehicleTracking::create([
                'booking_id' => $request->booking_id,
                'location_name' => $request->location_name,
                'lat' => $newLat,
                'lng' => $newLng,
                'title' => $request->title ?? $request->location_name,
                'subtitle' => $request->subtitle ?? '',
                'status' => $request->status ?? 'current',
                'reached_at' => now(),
                'order' => $maxOrder + 1,
            ]);

            // Also update driver current location
            $booking->update([
                'driver_lat' => $request->lat,
                'driver_lng' => $request->lng,
                'driver_location_name' => $request->location_name,
            ]);

            // NOTE: snap-to-roads is intentionally NOT run here.
            // Drivers push points constantly throughout a 10h journey,
            // but customers/vendors usually only open the tracking screen
            // a handful of times per journey. Running snap on every push
            // would burn Roads API quota for paths nobody is watching.
            // Instead, the snap runs lazily inside `vehicleTracking` GET
            // (the read path) and persists into `bookings.tracking_path`
            // so subsequent reads are free until the next refresh window.

            return response()->json([
                'status' => true,
                'message' => 'Tracking point added successfully.',
                'data' => $tracking,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error adding tracking point',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Haversine distance between two lat/lng points in kilometers.
     *
     * Public so the vendor tracking endpoint
     * (`VendorDashboardController::vehicleTracking`) can reuse the exact
     * same cleaning pipeline without duplicating it. Was previously
     * private to this controller.
     */
    public static function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    /**
     * Forward bearing from point 1 to point 2, in degrees (0..360).
     * Used by the timeline cleaner to detect u-turn artifacts.
     */
    public static function bearingDeg(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dLon = deg2rad($lng2 - $lng1);
        $y = sin($dLon) * cos($phi2);
        $x = cos($phi1) * sin($phi2) - sin($phi1) * cos($phi2) * cos($dLon);
        $brng = rad2deg(atan2($y, $x));
        return fmod($brng + 360, 360);
    }

    /**
     * Collapse stationary GPS clusters into a single point.
     *
     * When the driver is parked / idling, GPS drifts and the backend
     * stores 4–8 readings inside a tight bounding circle (typically
     * 30–150 m wide). If we hand all of them to a routing engine as
     * required waypoints, it tries to thread a route through every one
     * and produces loops across adjacent streets — the visible "circle"
     * artifact in the customer app.
     *
     * This function walks the timeline, identifies any run of
     * [$minClusterSize] or more consecutive points whose pairwise
     * distance from the run's first point stays within [$radiusMeters],
     * and replaces the entire run with a single point at the run's
     * centroid (carrying the metadata of the run's first point so the
     * timestamp, title, etc. still make sense).
     *
     * Non-clustered points pass through unchanged.
     *
     * @param  array $points          Cleaned timeline points.
     * @param  float $radiusMeters    Bounding circle radius for "stationary".
     * @param  int   $minClusterSize  Minimum run length to be considered a cluster.
     * @return array
     */
    public static function collapseStationaryClusters(array $points, float $radiusMeters, int $minClusterSize): array
    {
        $count = count($points);
        if ($count < $minClusterSize) {
            return $points;
        }

        $result = [];
        $i = 0;
        while ($i < $count) {
            // Try to extend a cluster starting at $i: keep adding points
            // while every member is within $radiusMeters of point $i.
            $anchor = $points[$i];
            $j = $i + 1;
            while ($j < $count) {
                $d = self::haversineDistance(
                    (float) $anchor['lat'], (float) $anchor['lng'],
                    (float) $points[$j]['lat'], (float) $points[$j]['lng']
                ) * 1000;
                if ($d > $radiusMeters) {
                    break;
                }
                $j++;
            }

            $clusterLen = $j - $i;
            if ($clusterLen >= $minClusterSize) {
                // Collapse [$i .. $j-1] into one centroid point.
                $sumLat = 0.0;
                $sumLng = 0.0;
                for ($k = $i; $k < $j; $k++) {
                    $sumLat += (float) $points[$k]['lat'];
                    $sumLng += (float) $points[$k]['lng'];
                }
                $centroid = $points[$i]; // keep title/time/status from first
                $centroid['lat'] = round($sumLat / $clusterLen, 6);
                $centroid['lng'] = round($sumLng / $clusterLen, 6);
                $result[] = $centroid;
                $i = $j;
            } else {
                $result[] = $points[$i];
                $i++;
            }
        }

        return $result;
    }

    /**
     * Sliding-window moving average over a timeline's lat/lng.
     *
     * For each point, replaces lat/lng with the arithmetic mean of itself
     * and the [$window-1] surrounding points (centered window). The first
     * and last points are preserved verbatim so pickup and the live
     * driver position never shift.
     *
     * Metadata (title, time, status, etc.) is preserved on each point —
     * only lat/lng are smoothed.
     *
     * @param  array $points
     * @param  int   $window  Odd window size (e.g. 3, 5, 7). Even values are bumped to next odd.
     * @return array
     */
    public static function movingAverageSmooth(array $points, int $window): array
    {
        $count = count($points);
        if ($count < 3 || $window < 2) {
            return $points;
        }
        if ($window % 2 === 0) {
            $window++;
        }
        $half = intdiv($window, 2);

        $smoothed = $points;
        for ($i = 1; $i < $count - 1; $i++) {
            $start = max(0, $i - $half);
            $end = min($count - 1, $i + $half);
            $sumLat = 0.0;
            $sumLng = 0.0;
            $n = 0;
            for ($k = $start; $k <= $end; $k++) {
                $sumLat += (float) $points[$k]['lat'];
                $sumLng += (float) $points[$k]['lng'];
                $n++;
            }
            $smoothed[$i]['lat'] = round($sumLat / $n, 6);
            $smoothed[$i]['lng'] = round($sumLng / $n, 6);
        }
        return $smoothed;
    }

    /**
     * Ramer–Douglas–Peucker polyline simplification.
     *
     * Returns a subset of [$points] that preserves the overall shape
     * within [$epsilonMeters] of the original. Points whose perpendicular
     * distance from the segment between their kept neighbors is below
     * the threshold are dropped. First and last points are always kept.
     *
     * Iterative implementation (explicit stack) to avoid recursion depth
     * issues on long timelines.
     *
     * @param  array $points
     * @param  float $epsilonMeters
     * @return array
     */
    public static function douglasPeucker(array $points, float $epsilonMeters): array
    {
        $count = count($points);
        if ($count < 3) {
            return $points;
        }

        $keep = array_fill(0, $count, false);
        $keep[0] = true;
        $keep[$count - 1] = true;

        $stack = [[0, $count - 1]];
        while (!empty($stack)) {
            [$start, $end] = array_pop($stack);
            $maxDist = 0.0;
            $maxIdx = -1;
            for ($i = $start + 1; $i < $end; $i++) {
                $d = self::perpendicularDistanceMeters(
                    (float) $points[$i]['lat'], (float) $points[$i]['lng'],
                    (float) $points[$start]['lat'], (float) $points[$start]['lng'],
                    (float) $points[$end]['lat'], (float) $points[$end]['lng']
                );
                if ($d > $maxDist) {
                    $maxDist = $d;
                    $maxIdx = $i;
                }
            }
            if ($maxIdx !== -1 && $maxDist > $epsilonMeters) {
                $keep[$maxIdx] = true;
                $stack[] = [$start, $maxIdx];
                $stack[] = [$maxIdx, $end];
            }
        }

        $out = [];
        for ($i = 0; $i < $count; $i++) {
            if ($keep[$i]) {
                $out[] = $points[$i];
            }
        }
        return $out;
    }

    /**
     * Perpendicular distance (meters) from point P to segment A–B,
     * using a local equirectangular projection. Accurate at the small
     * scales (<a few km) we deal with here.
     */
    private static function perpendicularDistanceMeters(
        float $pLat, float $pLng,
        float $aLat, float $aLng,
        float $bLat, float $bLng
    ): float {
        $metersPerDegLat = 111320.0;
        $metersPerDegLng = 111320.0 * cos(deg2rad($aLat));

        $bx = ($bLng - $aLng) * $metersPerDegLng;
        $by = ($bLat - $aLat) * $metersPerDegLat;
        $px = ($pLng - $aLng) * $metersPerDegLng;
        $py = ($pLat - $aLat) * $metersPerDegLat;

        $lenSq = $bx * $bx + $by * $by;
        if ($lenSq == 0.0) {
            return sqrt($px * $px + $py * $py);
        }
        $t = ($px * $bx + $py * $by) / $lenSq;
        if ($t < 0) $t = 0;
        if ($t > 1) $t = 1;
        $projX = $t * $bx;
        $projY = $t * $by;
        $dx = $px - $projX;
        $dy = $py - $projY;
        return sqrt($dx * $dx + $dy * $dy);
    }

    /**
     * Snap a cleaned timeline to the road network using Google Roads API.
     *
     * Why: raw GPS jitters between parallel lanes / nearby roads, so when
     * we draw a polyline through it the line zig-zags into loops that
     * look like the driver did circles. Roads API forces every point onto
     * the actual road graph and (with interpolate=true) returns extra
     * intermediate points along the road, producing a smooth path.
     *
     * Behavior:
     *  - Caches the snapped result per booking for 20 s. Customer app
     *    polls this endpoint frequently; without caching we'd burn quota
     *    and add ~300 ms to every poll.
     *  - Splits into 100-point chunks (Roads API hard limit).
     *  - On any failure / missing key, returns the input unchanged so
     *    the timeline still works — snap-to-roads is best-effort, not
     *    a hard dependency.
     *
     * @param  int   $bookingId
     * @param  array $points    cleaned timeline points (with title/time/lat/lng/...)
     * @return array            same shape; lat/lng replaced with snapped values + interpolated points appended
     */
    /**
     * Snap-on-write: extend the persisted `bookings.tracking_path` JSON
     * column with the most recent vehicle_trackings rows, snapped to the
     * road network in batches.
     *
     * Trigger conditions (any one suffices):
     *   - tracking_path is empty (first add since deploy)
     *   - >= 8 unsnapped vehicle_trackings rows accumulated
     *   - last refresh was >= 25 seconds ago
     *
     * Worst-case green-line lag = min(8 × push_interval, 25s) ≈ 25s.
     *
     * Cost model: instead of re-snapping the whole growing timeline on every
     * customer poll (the old read-side `snapTimelineToRoads` call burned a
     * Roads API request per cache miss × full timeline / 100 chunks), we now
     * snap a small overlap window of recent points at write time and append
     * the result. One Roads API call per ~30 GPS pushes, regardless of how
     * often customers poll.
     */
    private function maybeRefreshTrackingPath(\App\Models\Booking $booking): void
    {
        $newest = VehicleTracking::where('booking_id', $booking->id)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->orderByDesc('id')
            ->first();
        if (!$newest) return;

        $lastSnappedId = (int) ($booking->tracking_path_last_id ?? 0);
        $unsnapped = VehicleTracking::where('booking_id', $booking->id)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->where('id', '>', $lastSnappedId)
            ->count();

        $hasPath = is_array($booking->tracking_path) && !empty($booking->tracking_path);
        $stale = $booking->tracking_path_at
            ? Carbon::parse($booking->tracking_path_at)->diffInSeconds(now()) >= 25
            : true;

        $shouldRefresh = !$hasPath || $unsnapped >= 8 || ($unsnapped > 0 && $stale);
        if (!$shouldRefresh) return;

        // Build a snap window: last ~10 already-snapped raw rows for context
        // + every unsnapped row. Roads API needs context to pick the right
        // road at intersections, so we re-feed a bit of the previous tail.
        $contextWindow = 10;
        $rows = VehicleTracking::where('booking_id', $booking->id)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->where(function ($q) use ($lastSnappedId, $contextWindow) {
                $q->where('id', '>', $lastSnappedId)
                  ->orWhere(function ($q2) use ($lastSnappedId, $contextWindow) {
                      $q2->where('id', '<=', $lastSnappedId)
                         ->whereIn('id', function ($sub) use ($lastSnappedId, $contextWindow) {
                             $sub->select('id')
                                 ->from('vehicle_trackings')
                                 ->where('id', '<=', $lastSnappedId)
                                 ->orderByDesc('id')
                                 ->limit($contextWindow);
                         });
                  });
            })
            ->orderBy('reached_at')
            ->orderBy('id')
            ->get(['id', 'lat', 'lng']);

        if ($rows->count() < 2) return;

        // Convert to the cleaning-pipeline shape
        $rawPoints = $rows->map(fn($r) => [
            'lat' => (float) $r->lat,
            'lng' => (float) $r->lng,
        ])->all();

        // Same input shape as the read-side pipeline so cleaning + Roads API
        // produces equivalent output.
        $cleaned = self::collapseStationaryClusters($rawPoints, 50.0, 4);
        $snapped = $this->snapTimelineToRoads($booking->id, $cleaned);
        if ($snapped === $cleaned) {
            // Roads API failed → fall back to local smoothing so we still
            // persist *something* better than raw GPS.
            $cleaned = self::movingAverageSmooth($cleaned, 5);
            $snapped = self::douglasPeucker($cleaned, 25.0);
        }

        // Reduce snapped output to bare [lat, lng] pairs (smaller JSON).
        $snappedLatLng = array_map(
            fn($p) => [round((float) $p['lat'], 6), round((float) $p['lng'], 6)],
            $snapped
        );

        // Splice: drop the tail of the existing path that overlaps with the
        // context window we re-snapped, then append the fresh result.
        $existing = is_array($booking->tracking_path) ? $booking->tracking_path : [];
        if (!empty($existing) && $lastSnappedId > 0) {
            // Remove overlap tail proportional to the context window. We
            // can't know the exact mapping (Roads API may return a different
            // number of interpolated points), so we drop a generous tail
            // (2× context) and accept a tiny re-render — the snap result
            // for this region is what we want anyway.
            $dropTail = min(count($existing), $contextWindow * 2);
            $existing = array_slice($existing, 0, count($existing) - $dropTail);
        }
        $merged = array_merge($existing, $snappedLatLng);

        $booking->update([
            'tracking_path'         => $merged,
            'tracking_path_last_id' => (int) $newest->id,
            'tracking_path_at'      => now(),
        ]);
    }

    private function snapTimelineToRoads(int $bookingId, array $points): array
    {
        if (count($points) < 2) {
            return $points;
        }

        $apiKey = config('services.maps_api_key') ?? env('GOOGLE_MAPS_API_KEY');
        if (empty($apiKey)) {
            return $points;
        }

        // Cache key includes the count + last point so the cache invalidates
        // as soon as a new GPS ping is appended.
        $last = end($points);
        reset($points);
        $cacheKey = "vt_snap_{$bookingId}_" . count($points) . "_"
            . round((float) $last['lat'], 5) . "_" . round((float) $last['lng'], 5);

        return Cache::remember($cacheKey, 20, function () use ($points, $apiKey) {
            try {
                // Roads API limit: 100 points per request.
                $chunks = array_chunk($points, 100);

                // Fire all chunks CONCURRENTLY via Guzzle's multi-handle pool.
                // Sequential was 100 chunks × ~300 ms = ~30 s on a fresh 10-hour
                // timeline (first read after a long un-watched journey).
                // Pool brings that down to ~the slowest single request.
                //
                // Guzzle handles the multi-loop natively, but we cap concurrency
                // to 25 by chunking the chunks — Roads API has been observed to
                // return 429s when ~50+ requests fire from a single IP at once.
                $resultsByIdx = [];
                foreach (array_chunk($chunks, 25, true) as $batch) {
                    $batchResults = Http::pool(fn ($pool) => array_map(
                        fn ($chunk) => $pool
                            ->timeout(8)
                            ->get('https://roads.googleapis.com/v1/snapToRoads', [
                                'path' => collect($chunk)
                                    ->map(fn($p) => $p['lat'] . ',' . $p['lng'])
                                    ->implode('|'),
                                'interpolate' => 'true',
                                'key'         => $apiKey,
                            ]),
                        $batch
                    ));
                    // $batchResults is keyed by the same indices as $batch so
                    // we can re-stitch them back to global $chunks order below.
                    foreach ($batchResults as $idx => $resp) {
                        $resultsByIdx[$idx] = $resp;
                    }
                }
                // Sort by chunk index so output order matches input order.
                ksort($resultsByIdx);

                $snapped = [];
                foreach ($resultsByIdx as $idx => $resp) {
                    // Pool returns a Throwable on connection-level failures.
                    if ($resp instanceof \Throwable) {
                        Log::warning('snapToRoads chunk threw', [
                            'chunk_index' => $idx,
                            'error'       => $resp->getMessage(),
                        ]);
                        return $points; // bail — return raw cleaned points
                    }
                    if (!$resp->ok()) {
                        Log::warning('snapToRoads HTTP failure', [
                            'chunk_index' => $idx,
                            'status'      => $resp->status(),
                            'body'        => $resp->body(),
                        ]);
                        return $points;
                    }

                    $body = $resp->json();
                    if (empty($body['snappedPoints'])) {
                        return $points;
                    }

                    $chunk = $chunks[$idx];
                    foreach ($body['snappedPoints'] as $sp) {
                        // originalIndex is present only for the points we
                        // submitted; interpolated points have no index. We
                        // copy metadata from the originating point when we
                        // can, and inherit from the previous one otherwise.
                        $origIdx = $sp['originalIndex'] ?? null;
                        $source  = $origIdx !== null && isset($chunk[$origIdx])
                            ? $chunk[$origIdx]
                            : (end($snapped) ?: $chunk[0]);
                        reset($snapped);

                        $snapped[] = [
                            'title'    => $source['title']    ?? '',
                            'subtitle' => $source['subtitle'] ?? '',
                            'time'     => $source['time']     ?? '',
                            'date'     => $source['date']     ?? '',
                            'status'   => $source['status']   ?? 'pending',
                            'lat'      => round((float) $sp['location']['latitude'], 6),
                            'lng'      => round((float) $sp['location']['longitude'], 6),
                        ];
                    }
                }

                return $snapped ?: $points;
            } catch (\Throwable $e) {
                Log::warning('snapToRoads exception', ['error' => $e->getMessage()]);
                return $points;
            }
        });
    }
}
