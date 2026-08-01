<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Hatchery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;

class HatcheryController extends Controller{}
// {
//         /**
//          * ==========================================
//          * GET /api/user/hatcheries
//          * ==========================================
//          * Fetch all hatcheries (with filters)
//          * Example:
//          * /api/user/hatcheries?search=Tiger&brands=1,4&categories=2,5&lat=17.385044&lng=78.486671&radius=50
//          */
//         public function allHatcheries(Request $request)
//         {
//             try {
//                 $search       = $request->query('search');
//                 $brandIds     = $request->query('brands') ? explode(',', $request->query('brands')) : [];
//                 $categoryIds  = $request->query('categories') ? explode(',', $request->query('categories')) : [];
//                 $lat          = $request->query('lat');
//                 $lng          = $request->query('lng');
//                 $radius       = $request->query('radius', 50); // default 50 km

//                 $query = Hatchery::query()
//                     ->with(['category:id,category_name', 'location:id,location_name', 'brand:id,brand_name' ]);

//                 // 🔍 Search by hatchery name or location
//                 // if ($search) {
//                 //     $query->where(function ($q) use ($search) {
//                 //         $q->where('hatchery_name', 'like', "%{$search}%")
//                 //           ->orWhere('location', 'like', "%{$search}%");
//                 //     });
//                 // }

//                 if ($search) {
//                     $query->where(function ($q) use ($search) {
//                         $q->where('hatchery_name', 'like', "%{$search}%")
//                         ->orWhereHas('location', function ($q2) use ($search) {
//                             $q2->where('location_name', 'like', "%{$search}%");
//                         });
//                     });
//                 }


//                 // 🏷 Filter by brand IDs
//                 if (!empty($brandIds)) {
//                     $query->whereIn('brand_id', $brandIds);
//                 }

//                 // 📦 Filter by category IDs
//                 if (!empty($categoryIds)) {
//                     $query->whereIn('category_id', $categoryIds);
//                 }

//                 // 📍 Filter by location radius (Haversine formula)
//                 if ($lat && $lng) {
//                     $query->select('*', DB::raw("(6371 * acos(cos(radians($lat)) 
//                         * cos(radians(lat)) 
//                         * cos(radians(lng) - radians($lng)) 
//                         + sin(radians($lat)) 
//                         * sin(radians(lat)))) AS distance"))
//                         ->having('distance', '<=', $radius)
//                         ->orderBy('distance', 'asc');
//                 }

//                 $hatcheries = $query->get();

//         // ✅ Check if no hatcheries found
//                 if ($hatcheries->isEmpty()) {
//                     return response()->json([
//                         'status' => true,
//                         'message' => 'No hatcheries found with given filters',
//                         'count' => 0,
//                         'data' => []
//                     ], 200);
//                 }

//                 return response()->json([
//                     'status' => true,
//                     'message' => 'Hatcheries fetched successfully',
//                     'count' => $hatcheries->count(),
//                     'data' => $hatcheries
//                 ], 200);

//             } catch (QueryException $e) {
//                 return response()->json([
//                     'status' => false,
//                     'message' => 'Database query failed',
//                     'error' => $e->getMessage()
//                 ], 500);
//             } catch (Exception $e) {
//                 return response()->json([
//                     'status' => false,
//                     'message' => 'Failed to fetch hatcheries',
//                     'error' => $e->getMessage()
//                 ], 500);
//             }     
//    }



//             /**
//          * ==========================================
//          * GET /api/user/hatcheries/{id}
//          * ==========================================
//          * Fetch a single hatchery by ID
//          */
//         public function getHatcheryById($id)
//         {
//             try {
//                 $hatchery = Hatchery::with(['category:id,category_name', 'location:id,location_name', 'brand:id,brand_name'])
//                     ->findOrFail($id); // Throws ModelNotFoundException if not found

//                 return response()->json([
//                     'status' => true,
//                     'message' => 'Hatchery fetched successfully',
//                     'data' => $hatchery
//                 ], 200);

//             } catch (ModelNotFoundException $e) {
//                 return response()->json([
//                 //     'status' => false,
//                 //     'message' => 'Hatchery not found',
//                 // ], 404);
//                 'status' => true,
//                 'message' => 'Hatchery not found',
//                 'data' => []
//             ], 200);
//             } catch (Exception $e) {
//                 return response()->json([
//                     'status' => false,
//                     'message' => 'Failed to fetch hatchery',
//                     'error' => $e->getMessage()
//                 ], 500);
//             }
//         }
//         /**
//          * ==========================================
//          * GET /api/user/hatcheries/nearby
//          * ==========================================
//          * Fetch nearby hatcheries (with optional brand/category filters)
//          * Example:
//          * /api/user/hatcheries/nearby?lat=17.385044&lng=78.486671&radius=100&brands=1,2&categories=3,5
//          */
//         public function nearbyHatcheries(Request $request)
//         {
//             try {
//                 $lat = $request->query('lat');
//                 $lng = $request->query('lng');
//                 $radius = $request->query('radius', 50); // default 50 km
//                 $brandIds = $request->query('brands') ? explode(',', $request->query('brands')) : [];
//                 $categoryIds = $request->query('categories') ? explode(',', $request->query('categories')) : [];

//                 // 🧭 Validate latitude/longitude
//                 if (!$lat || !$lng) {
//                     return response()->json([
//                         'status' => false,
//                         'message' => 'Latitude and longitude are required'
//                     ], 400);
//                 }

//                 // 🌍 Base query with distance calculation (Haversine formula)
//                 $query = Hatchery::select('*', DB::raw("(6371 * acos(cos(radians($lat)) 
//                             * cos(radians(lat)) 
//                             * cos(radians(lng) - radians($lng)) 
//                             + sin(radians($lat)) 
//                             * sin(radians(lat)))) AS distance"))
//                     ->having('distance', '<=', $radius)
//                     ->orderBy('distance', 'asc');

//                 // 🏷️ Filter by brands if provided
//                 if (!empty($brandIds)) {
//                     $query->whereIn('brand_id', $brandIds);
//                 }

//                 // 📦 Filter by categories if provided
//                 if (!empty($categoryIds)) {
//                     $query->whereIn('category_id', $categoryIds);
//                 }

//                 $hatcheries = $query->get();

//                 if ($hatcheries->isEmpty()) {
//                     return response()->json([
//                     //     'status' => false,
//                     //     'message' => 'No hatcheries found within the given radius or filters'
//                     // ], 404);
//                     'status' => true,
//                     'message' => 'No hatcheries found within the given radius or filters',
//                     'count' => 0,
//                     'data' => []
//                 ], 200);
//                 }

//                 return response()->json([
//                     'status' => true,
//                     'message' => 'Nearby hatcheries fetched successfully',
//                     'count' => $hatcheries->count(),
//                     'data' => $hatcheries
//                 ], 200);

//             } catch (Exception $e) {
//                 return response()->json([
//                     'status' => false,
//                     'message' => 'Failed to fetch nearby hatcheries',
//                     'error' => $e->getMessage()
//                 ], 500);
//             }
//         }

//         /**
//          * ==========================================
//          * GET /api/user/hatcheries/filters
//          * ==========================================
//          * Returns all distinct brands, categories, and locations
//          * for frontend dropdown filters
//          */
//         // public function filters()
//         // {
//         //     try {
//         //         $brands = DB::table('hatcheries')
//         //             ->select('brand')
//         //             ->distinct()
//         //             ->whereNotNull('brand')
//         //             ->pluck('brand');

//         //         $categories = DB::table('hatcheries')
//         //             ->select('category')
//         //             ->distinct()
//         //             ->whereNotNull('category')
//         //             ->pluck('category');

//         //         $locations = DB::table('hatcheries')
//         //             ->select('location_name')
//         //             ->distinct()
//         //             ->whereNotNull('location_name')
//         //             ->pluck('location_name');

//         //         return response()->json([
//         //             'status' => true,
//         //             'message' => 'Filter data fetched successfully',
//         //             'data' => [
//         //                 'brands' => $brands,
//         //                 'categories' => $categories,
//         //                 'locations' => $locations,
//         //             ],
//         //         ], 200);
//         //     } catch (Exception $e) {
//         //         return response()->json([
//         //             'status' => false,
//         //             'message' => 'Something went wrong',
//         //             'error' => $e->getMessage(),
//         //         ], 500);
//         //     }
//         // }

//         public function filters()
//         {
//             try {
//                 $brands = DB::table('brands')->pluck('brand_name');
//                 $categories = DB::table('hatchery_categories')->pluck('category_name');
//                 $locations = DB::table('hatchery_locations')->pluck('location_name');

//                 return response()->json([
//                     'status' => true,
//                     'message' => 'Filter data fetched successfully',
//                     'data' => [
//                         'brands' => $brands,
//                         'categories' => $categories,
//                         'locations' => $locations,
//                     ],
//                 ], 200);
//             } catch (Exception $e) {
//                 return response()->json([
//                     'status' => false,
//                     'message' => 'Something went wrong',
//                     'error' => $e->getMessage(),
//                 ], 500);
//             }
//         }

//         /**
//          * ==========================================
//          * GET /api/user/hatcheries/{id}/details
//          * ==========================================
//          * Hatchery overview (Slide 2)
//          */
//         public function hatcheryDetails($id)
//         {
//             try {
//                 $hatchery = Hatchery::with(['brand:id,brand_name', 'category:id,category_name', 'location:id,location_name'])
//                     ->findOrFail($id);

//                 // Get related hatcheries by category or location
//                 $similar = Hatchery::where('id', '!=', $id)
//                     ->where(function($q) use ($hatchery) {
//                         $q->where('category_id', $hatchery->category_id)
//                         ->orWhere('location_id', $hatchery->location_id);
//                     })
//                     ->take(5)
//                     ->get(['id', 'hatchery_name', 'image', 'status']);

//                 return response()->json([
//                     'status' => true,
//                     'message' => 'Hatchery details fetched successfully',
//                     'data' => [
//                         'hatchery' => $hatchery,
//                         'similar_hatcheries' => $similar
//                     ]
//                 ], 200);

//             } catch (ModelNotFoundException $e) {
//                 // return response()->json(['status' => false, 'message' => 'Hatchery not found'], 404);
//                  return response()->json([
//                     'status' => true,
//                     'message' => 'Hatchery not found',
//                     'data' => [
//                         'hatchery' => [],
//                         'similar_hatcheries' => []
//                     ]
//                 ], 200);
//              }
//         }

//         /**
//          * ==========================================
//          * GET /api/user/hatcheries/{hatchery_id}/categories/{category_id}
//          * ==========================================
//          * Hatchery category details (Slide 3)
//          */
//         public function hatcheryCategoryDetails($hatcheryId, $categoryId)
//         {
//             try {
//                 $hatchery = Hatchery::with(['brand:id,brand_name', 'location:id,location_name'])
//                     ->where('id', $hatcheryId)
//                     ->where('category_id', $categoryId)
//                     ->firstOrFail();

//                 return response()->json([
//                     'status' => true,
//                     'message' => 'Hatchery category details fetched successfully',
//                     'data' => $hatchery
//                 ], 200);

//             } catch (ModelNotFoundException $e) {
//                 // return response()->json(['status' => false, 'message' => 'Category not found in this hatchery'], 404);
//                 return response()->json([
//                     'status' => true,
//                     'message' => 'Category not found in this hatchery',
//                     'data' => []
//                 ], 200);
//             }
//         }

//         /**
//          * ==========================================
//          * POST /api/user/hatcheries/{id}/book
//          * ==========================================
//          * Book seeds from hatchery (Slide 4)
//          */
//         // public function bookHatchery(Request $request, $id)
//         // {
//         //     try {
//         //         $validated = $request->validate([
//         //             'customer_name' => 'required|string',
//         //             'customer_mobile' => 'required|string',
//         //             'unit' => 'nullable|string',
//         //             'no_of_pieces' => 'required|integer',
//         //             'dropping_location' => 'required|string',
//         //             'packing_date' => 'required|date',
//         //         ]);

//         //         $hatchery = Hatchery::findOrFail($id);

//         //         $booking = DB::table('bookings')->insertGetId([
//         //             'vendor_id' => $hatchery->vendor_id,
//         //             'hatchery_name' => $hatchery->hatchery_name,
//         //             // 'hatchery_location' => $hatchery->location,
//         //             'hatchery_location' => $hatchery->location->location_name ?? null,
//         //             'customer_name' => $validated['customer_name'],
//         //             'customer_mobile' => $validated['customer_mobile'],
//         //             'unit' => $validated['unit'] ?? null,
//         //             'no_of_pieces' => $validated['no_of_pieces'],
//         //             'dropping_location' => $validated['dropping_location'],
//         //             'packing_date' => $validated['packing_date'],
//         //             'categories' => json_encode(['seed_booking']),
//         //             'created_at' => now(),
//         //             'updated_at' => now(),
//         //         ]);

//         //         return response()->json([
//         //             'status' => true,
//         //             'message' => 'Booking confirmed successfully',
//         //             'booking_id' => $booking
//         //         ], 200);

//         //     } catch (Exception $e) {
//         //         return response()->json(['status' => false, 'message' => 'Failed to book hatchery', 'error' => $e->getMessage()], 500);
//         //     }
//         // }


//         /**
//          * ==========================================
//          * POST /api/user/hatcheries/{id}/book
//          * ==========================================
//          * Book seeds from hatchery (Slide 4)
//          * ==========================================
//          */
//         // public function bookHatchery(Request $request, $id)
//         // {
//         //     try {
//         //         // ✅ Step 1: Validate the request
//         //         $validated = $request->validate([
//         //             'customer_name' => 'required|string',
//         //             'customer_mobile' => 'required|string',
//         //             'unit' => 'nullable|string',
//         //             'no_of_pieces' => 'required|integer',
//         //             'dropping_location' => 'required|string',
//         //             'packing_date' => 'required|date',
//         //         ]);

//         //         // ✅ Step 2: Find the hatchery
//         //         $hatchery = Hatchery::with('location')->findOrFail($id);

//         //         // ✅ Step 3: Create booking using Eloquent model
//         //         $booking = new \App\Models\Booking();
//         //         $booking->vendor_id = $hatchery->vendor_id;
//         //         $booking->hatchery_name = $hatchery->hatchery_name;
//         //         $booking->hatchery_location = $hatchery->location->location_name ?? null;
//         //         $booking->customer_name = $validated['customer_name'];
//         //         $booking->customer_mobile = $validated['customer_mobile'];
//         //         $booking->unit = $validated['unit'] ?? null;
//         //         $booking->no_of_pieces = $validated['no_of_pieces'];
//         //         $booking->dropping_location = $validated['dropping_location'];
//         //         $booking->packing_date = $validated['packing_date'];
//         //         $booking->categories = json_encode(['seed_booking']);
//         //         $booking->save(); // ✅ auto handles created_at & updated_at

//         //         // ✅ Step 4: Return success response
//         //         return response()->json([
//         //             'status' => true,
//         //             'message' => 'Booking confirmed successfully',
//         //             'booking_id' => $booking->id
//         //         ], 200);

//         //     } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
//         //         return response()->json([
//         //             'status' => false,
//         //             'message' => 'Hatchery not found',
//         //             'error' => $e->getMessage()
//         //         ], 404);

//         //     } catch (Exception $e) {
//         //         return response()->json([
//         //             'status' => false,
//         //             'message' => 'Failed to book hatchery',
//         //             'error' => $e->getMessage()
//         //         ], 500);
//         //     }
//         // }

//         public function bookHatchery(Request $request, $hatcheryId)
//         {
//             try {
//                 $validated = $request->validate([
//                     'customer_name' => 'required|string|max:255',
//                     'customer_mobile' => 'required|string|max:15',
//                     'unit' => 'nullable|string',
//                     'no_of_pieces' => 'required|integer',
//                     'dropping_location' => 'required|string|max:255',
//                     'packing_date' => 'required|date',
//                 ]);

//                 // 🔹 1. Get hatchery details
//                 $hatchery = Hatchery::with('location')->findOrFail($hatcheryId);

//                 // 🔹 2. Identify farmer (logged in user)
//                 $farmer = auth()->user(); // adjust based on guard (e.g. auth('farmer')->user())

//                 // 🔹 3. Create booking
//                 $booking = Booking::create([
//                     'vendor_id'         => $hatchery->vendor_id,
//                     'hatchery_id'       => $hatchery->id,
//                     'farmer_id'         => $farmer->id ?? null,
//                     'booking_by'        => 'farmer',
//                     // 'customer_id'       => $farmer->id ?? null,
//                     'customer_name'     => $validated['customer_name'],
//                     'customer_mobile'   => $validated['customer_mobile'],
//                     'delivery_location' => $validated['dropping_location'],
//                     'hatchery_name'     => $hatchery->hatchery_name,
//                     'hatchery_location' => $hatchery->location->location_name ?? null,
//                     'unit'              => $validated['unit'] ?? null,
//                     'no_of_pieces'      => $validated['no_of_pieces'],
//                     'dropping_location' => $validated['dropping_location'],
//                     'packing_date'      => $validated['packing_date'],
//                     'categories'        => json_encode(['seed_booking']),
//                 ]);

//                 return response()->json([
//                     'status' => true,
//                     'message' => 'Booking confirmed successfully',
//                     'booking_id' => $booking->id,
//                 ], 200);

//             } catch (\Exception $e) {
//                 return response()->json([
//                     'status' => false,
//                     'message' => 'Failed to book hatchery',
//                     'error' => $e->getMessage()
//                 ], 500);
//             }
//         }


//         public function showBooking($id)
//         {
//             try {
//                 $booking = Booking::with(['hatchery.location', 'hatchery.brand', 'hatchery.category'])
//                     ->findOrFail($id);

//                     // Build custom data
//         // $data = [
//         //     'id' => $booking->id,
//         //     'vendor_id' => $booking->vendor_id,
//         //     'hatchery_id' => $booking->hatchery_id,
//         //     'farmer_id' => $booking->farmer_id,
//         //     'booking_by' => $booking->booking_by,
//         //     'customer_id' => $booking->customer_id,
//         //     'customer_name' => $booking->customer_name,
//         //     'customer_mobile' => $booking->customer_mobile,
//         //     'delivery_location' => $booking->delivery_location,
//         //     'hatchery_name' => optional($booking->hatchery)->hatchery_name,
//         //     'hatchery_location' => optional($booking->hatchery->location)->location_name ?? 'Not Available',
//         //     'unit' => $booking->unit,
//         //     'no_of_pieces' => $booking->no_of_pieces,
//         //     'dropping_location' => $booking->dropping_location,
//         //     'packing_date' => $booking->packing_date,
//         //     'categories' => json_decode($booking->categories, true),
//         //     'available_space' => optional($booking->hatchery)->available_space ?? 'N/A',
//         //     'driver_name' => $booking->driver_name ?? 'Pending',
//         //     'driver_mobile' => $booking->driver_mobile ?? 'Pending',
//         //     'vehicle_number' => $booking->vehicle_number ?? 'Pending',
//         //     'vehicle_started_date' => $booking->vehicle_started_date ?? null,
//         //     'vehicle_end_date' => $booking->vehicle_end_date ?? null,
//         //     'vehicle_images' => $booking->vehicle_images ? json_decode($booking->vehicle_images, true) : [],
//         //     'created_at' => $booking->created_at,
//         //     'updated_at' => $booking->updated_at,
//         //     'hatchery' => $booking->hatchery,
//         // ];

//                 return response()->json([
//                     'status' => true,
//                     'message' => 'Booking details fetched successfully',
//                     'data' => $booking,
//                 ], 200);

//             } catch (\Exception $e) {
//                 return response()->json([
//                     'status' => false,
//                     'message' => 'Failed to fetch booking details',
//                     'error' => $e->getMessage(),
//                 ], 500);
//             }
//         }





// }



// {
//     /**
//      * ======================================
//      * GET /api/farmer/hatcheries
//      * ======================================
//      * List all hatcheries with filters and pagination.
//      */
//     public function index(Request $request)
//     {
//         try {
//             $query = Hatchery::query();

//             // 🔍 Search by name or location
//             if ($search = $request->input('search')) {
//                 $query->where(function ($q) use ($search) {
//                     $q->where('hatchery_name', 'like', "%$search%")
//                       ->orWhere('location', 'like', "%$search%");
//                 });
//             }

//             // 🏷️ Filter by brand
//             if ($brands = $request->input('brands')) {
//                 $brandsArr = explode(',', $brands);
//                 if (!empty($brandsArr)) {
//                     $query->whereIn('brand', $brandsArr);
//                 }
//             }

//             // 🐟 Filter by category
//             if ($categories = $request->input('categories')) {
//                 $catArr = explode(',', $categories);
//                 if (!empty($catArr)) {
//                     $query->whereIn('category', $catArr);
//                 }
//             }

//             // 📍 Nearby search (Haversine formula)
//             $lat = $request->input('lat');
//             $lng = $request->input('lng');
//             $radius = $request->input('radius', 50);

//             if ($lat && $lng) {
//                 $haversine = "(6371 * acos(cos(radians($lat)) * cos(radians(lat)) * cos(radians(lng) - radians($lng)) + sin(radians($lat)) * sin(radians(lat))))";
//                 $query->select('*', DB::raw("$haversine AS distance"))
//                       ->having('distance', '<=', $radius)
//                       ->orderBy('distance');
//             }

//             $hatcheries = $query->paginate(12);

//             return response()->json([
//                 'filters' => [
//                     'brands' => ['SyAqua', 'SIS Hardline', 'Kona Bay'],
//                     'categories' => ['Tiger', 'Vannamei'],
//                     'locations' => ['Nearby me', 'Vizag', 'Bapatla'],
//                 ],
//                 'data' => $hatcheries
//             ]);

//         } catch (QueryException $e) {
//             return response()->json([
//                 'message' => 'Database query failed',
//                 'error' => $e->getMessage()
//             ], 500);

//         } catch (Exception $e) {
//             return response()->json([
//                 'message' => 'Something went wrong while fetching hatcheries',
//                 'error' => $e->getMessage()
//             ], 500);
//         }
//     }

//     /**
//      * ======================================
//      * GET /api/farmer/hatcheries/{id}
//      * ======================================
//      * Show detailed hatchery info with related data
//      * * and Call/WhatsApp links.
//      */
//     public function show($id)
//     {

//         try {
//             $hatchery = Hatchery::findOrFail($id);

//             // Prepare Call Now & WhatsApp links
//             $callNumber = $hatchery->mobile;
//             $whatsappNumber = preg_replace('/[^0-9]/', '', $hatchery->mobile);
//             $whatsappLink = "https://wa.me/$whatsappNumber";

//             // 🧩 Add related hatcheries (same brand/category)
//             $related = Hatchery::where('id', '!=', $id)
//                 ->where(function ($q) use ($hatchery) {
//                     $q->where('brand', $hatchery->brand)
//                       ->orWhere('category', $hatchery->category);
//                 })
//                 ->take(5)
//                 ->get(['id', 'hatchery_name', 'brand', 'location', 'image']);

//             return response()->json([
//                 'hatchery' => $hatchery,
//                 'call_now' => "tel:$callNumber",
//                 'whatsapp' => $whatsappLink,
//                 'related_hatcheries' => $related
//                 // 'hatchery' => $hatchery,
//                 // 'related_hatcheries' => $related
//             ]);

//         // } catch (Exception $e) {
//         //     return response()->json([
//         //         'message' => 'Hatchery not found',
//         //         'error' => $e->getMessage()
//         //     ], 404);
//         // }
//         } catch (ModelNotFoundException $e) {
//             return response()->json([
//                 'message' => 'Hatchery not found',
//                 'error' => $e->getMessage()
//             ], 404);

//         } catch (Exception $e) {
//             return response()->json([
//                 'message' => 'Error fetching hatchery details',
//                 'error' => $e->getMessage()
//             ], 500);
//         }
//     }

//     /**
//      * ======================================
//      * GET /api/farmer/hatcheries/filters
//      * ======================================
//      * Fetch all available filters dynamically from DB
//      */
//     public function filters()
//     {
//         try {
//             $brands = Hatchery::select('brand')->distinct()->pluck('brand');
//             $categories = Hatchery::select('category')->distinct()->pluck('category');
//             $locations = Hatchery::select('location')->distinct()->pluck('location');

//             return response()->json([
//                 'brands' => $brands,
//                 'categories' => $categories,
//                 'locations' => $locations
//             ]);
//         } catch (Exception $e) {
//             return response()->json([
//                 'message' => 'Failed to fetch filters',
//                 'error' => $e->getMessage()
//             ], 500);
//         }
//     }

//     /**
//      * ======================================
//      * GET /api/farmer/hatcheries/updates
//      * ======================================
//      * Optional future: Hatchery news/updates section
//      */
//     public function updates()
//     {
//         try {
//             $updates = DB::table('hatchery_updates')->latest()->get();

//             return response()->json([
//                 'updates' => $updates
//             ]);
//         } catch (Exception $e) {
//             return response()->json([
//                 'message' => 'Failed to fetch updates',
//                 'error' => $e->getMessage()
//             ], 500);
//         }
//     }
// }
