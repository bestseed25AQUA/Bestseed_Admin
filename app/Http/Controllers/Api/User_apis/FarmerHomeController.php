<?php

// namespace App\Http\Controllers\Api\User_apis;
namespace App\Http\Controllers\Api\User_apis;


use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Hatchery;
use App\Models\MarketPrice;
use App\Models\Seed;
use App\Models\HatcheryUpdate;
use App\Models\SupplierStock;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FarmerHomeController extends Controller
{
    /**
     * GET /api/farmer/home
     *
     * Query params:
     *  - city (string) -> if given, we auto-convert to lat/lng
     *  - lat, lng (optional) -> for "nearby" search
     *  - radius (km) default 50
     *  - search (string)
     *  - brands, categories (comma separated)
     *  - species (default "vannamei")
     *  - page, per_page
     */
    public function index(Request $request)
    {
        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $city = $request->input('city');
        $radius = (float) $request->input('radius', 50); // km
        $search = $request->input('search');
        $brands = $request->input('brands');
        $categories = $request->input('categories');
        $species = $request->input('species', 'vannamei');
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 12);

        // ---------------------------------------------------------
        // (1) Convert City -> lat/lng (Geocoding API)
        // ---------------------------------------------------------
        // if ((!$lat || !$lng) && $city) {
        //     $geoKey = env('GEOCODING_API_KEY'); // OpenCage / Google
        //     if ($geoKey) {
        //         $geoResp = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
        //             'q' => $city,
        //             'key' => $geoKey,
        //             'limit' => 1,
        //         ]); 
        //         if ($geoResp->successful() && isset($geoResp['results'][0])) {
        //             $lat = $geoResp['results'][0]['geometry']['lat'];
        //             $lng = $geoResp['results'][0]['geometry']['lng'];
        //         }
        //     }
        // }


            // ---------------------------------------------------------
            // (1) Convert City -> lat/lng (Geocoding API)
            // ---------------------------------------------------------
            if ((!$lat || !$lng) && $city) {
                $geoKey = env('GEOCODING_API_KEY'); // Google Maps API key
                if ($geoKey) {
                    $geoResp = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                        'address' => $city,
                        'key' => $geoKey,
                    ]);

                    if ($geoResp->successful() && isset($geoResp['results'][0]['geometry']['location'])) {
                        $lat = $geoResp['results'][0]['geometry']['location']['lat'];
                        $lng = $geoResp['results'][0]['geometry']['location']['lng'];
                    }
                }
            }


        // ---------------------------------------------------------
        // (2) Quick Actions (static metadata for frontend)
        // ---------------------------------------------------------
        $quickActions = [
            ['key' => 'vehicle_availability', 'title' => 'Vehicle Availability', 'icon' => 'truck'],
            ['key' => 'farm_management', 'title' => 'Farm Management', 'icon' => 'farm'],
            ['key' => 'spot_hatcheries', 'title' => 'Spot Hatcheries', 'icon' => 'hatchery'],
            ['key' => 'seeds_requests', 'title' => 'Seeds Requests', 'icon' => 'seed'],
        ];

        // ---------------------------------------------------------
        // (3) Banners
        // ---------------------------------------------------------
        $banners = Banner::where('status', true)->latest()->get()->map(function ($banner) {
            $banner->image = $banner->image ? url($banner->image) : null;
            return $banner;
        });

        // ---------------------------------------------------------
        // (4) Hatcheries (with filters, search, geo-distance)
        // ---------------------------------------------------------
        // ->active() hides Inactive hatcheries from customers.
        $hatcheryQuery = Hatchery::query()->active();

        // search
        if ($search) {
            $hatcheryQuery->where(function($q) use ($search) {
                $q->where('hatchery_name', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        // filter by brands
        if ($brands) {
            $arr = is_array($brands) ? $brands : array_filter(array_map('trim', explode(',', $brands)));
            if (count($arr)) {
                $hatcheryQuery->where(function($q) use ($arr) {
                    foreach ($arr as $v) {
                        $q->orWhereJsonContains('category', $v);
                    }
                });
            }
        }

        // filter by categories
        if ($categories) {
            $arr = is_array($categories) ? $categories : array_filter(array_map('trim', explode(',', $categories)));
            if (count($arr)) {
                $hatcheryQuery->where(function($q) use ($arr) {
                    foreach ($arr as $v) {
                        $q->orWhereJsonContains('category', $v);
                    }
                });
            }
        }

        // Geo distance (Haversine)
        if ($lat && $lng) {
            $haversine = "(6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat))))";
            $hatcheryQuery->selectRaw("hatcheries.*, {$haversine} AS distance", [$lat, $lng, $lat])
                ->having('distance', '<=', $radius)
                ->orderBy('distance', 'asc');
        } else {
            $hatcheryQuery->latest();
        }

        $hatcheries = $hatcheryQuery->paginate($perPage, ['*'], 'page', $page);

        // Add status for each hatchery
        $now = Carbon::now();
        foreach ($hatcheries as $h) {
            if ($h->opening_time && $h->closing_time &&
                $h->opening_time <= $now && $h->closing_time >= $now) {
                $h->status = "Available";
            } elseif ($h->available_date && $h->available_date > $now) {
                $h->status = "Coming Soon";
            } else {
                $h->status = "Closed";
            }
        }

        // ---------------------------------------------------------
        // (5) Today's prices
        // ---------------------------------------------------------
        $marketPrices = MarketPrice::with('location')
            ->where('species', $species)
            ->orderBy('location_id')
            ->get()
            ->groupBy(fn($row) => $row->location->location_name ?? 'Unknown')
            ->map(function($group) {
                return $group->map(function($row) {
                    return [
                        'size' => $row->size,
                        'price' => (float) $row->price,
                    ];
                })->values();
            });

        // ---------------------------------------------------------
        // (6) Supplier stocks
        // ---------------------------------------------------------
        $suppliers = SupplierStock::with(['hatchery', 'vendor'])
            ->orderBy('available_date','desc')
            ->limit(10)
            ->get();

        // ---------------------------------------------------------
        // (7) Medicine News
        // ---------------------------------------------------------
        $medicineNews = collect([]);

        // ---------------------------------------------------------
        // (8) Hatchery Updates
        // ---------------------------------------------------------
        $hatcheryUpdates = HatcheryUpdate::with('vendor:id,name,profile_image')
            ->latest()
            ->limit(8)
            ->get()
            ->map(function ($update) {
                $update->media_path = $update->media_path ? url($update->media_path) : null;
                $update->image = $update->image ? url($update->image) : null;
                if ($update->media_files && is_array($update->media_files)) {
                    $update->media_files = array_map(fn($f) => url($f), $update->media_files);
                }
                if ($update->vendor && $update->vendor->profile_image) {
                    $update->vendor->profile_image = url($update->vendor->profile_image);
                }
                return $update;
            });

        // ---------------------------------------------------------
        // (9) Weather (OpenWeather API)
        // ---------------------------------------------------------
        $weather = null;
        $owKey = env('OPENWEATHER_API_KEY');
        if ($lat && $lng && $owKey) {
            try {
                $resp = Http::get('https://api.openweathermap.org/data/2.5/weather', [
                    'lat' => $lat,
                    'lon' => $lng,
                    'appid' => $owKey,  
                    'units' => 'metric'
                ]);
                if ($resp->successful()) {
                    $d = $resp->json();
                    $weather = [
                        'temp' => isset($d['main']['temp']) ? round($d['main']['temp']) . '°C' : null,
                        'desc' => $d['weather'][0]['description'] ?? null,
                        'icon' => isset($d['weather'][0]['icon']) ? "https://openweathermap.org/img/wn/{$d['weather'][0]['icon']}@2x.png" : null,
                    ];
                }
            } catch (\Throwable $e) {
                $weather = null;
            }
        }

        // ---------------------------------------------------------
        // (10) Vehicle Availability (placeholder for now)
        // ---------------------------------------------------------
        $vehicleAvailability = [
            'total' => 15,
            'available_today' => 5,
        ];

        // ---------------------------------------------------------
        // Final Payload
        // ---------------------------------------------------------
        $payload = [
            'location' => [
                'city' => $city,
                'lat' => $lat,
                'lng' => $lng,
                'weather' => $weather,
            ],
            'language' => auth()->user()->language ?? 'en',
            'quick_actions' => $quickActions,
            'banners' => $banners,
            'hatcheries' => $hatcheries,
            'today_prices' => $marketPrices,
            'suppliers' => $suppliers,
            'medicine_news' => $medicineNews,
            'hatchery_updates' => $hatcheryUpdates,
            'vehicle_availability' => $vehicleAvailability,
        ];

        return response()->json($payload);
    }
    
}




// <?php

// namespace App\Http\Controllers\Api\User_apis;

// use App\Http\Controllers\Controller;
// use App\Models\Banner;
// use App\Models\Hatchery;
// use App\Models\MarketPrice;
// use App\Models\Seed;
// use App\Models\MedicineNews;
// use App\Models\HatcheryUpdate;
// use App\Models\SupplierStock;
// use Carbon\Carbon;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Http;

// class FarmerHomeController extends Controller
// {
//     /**
//      * GET /api/farmer/home
//      */
//     public function index(Request $request)
//     {
//         $lat = $request->input('lat');
//         $lng = $request->input('lng');
//         $city = $request->input('city');
//         $radius = (float) $request->input('radius', 50); // km
//         $search = $request->input('search');
//         $brands = $request->input('brands');
//         $categories = $request->input('categories');
//         $species = $request->input('species', 'vannamei');
//         $page = (int) $request->input('page', 1);
//         $perPage = (int) $request->input('per_page', 12);

//         // Convert City -> lat/lng (Geocoding API)
//         if ((!$lat || !$lng) && $city) {
//             $geoKey = env('GEOCODING_API_KEY'); // Google Maps API key
//             if ($geoKey) {
//                 $geoResp = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
//                     'address' => $city,
//                     'key' => $geoKey,
//                 ]);

//                 if ($geoResp->successful() && isset($geoResp['results'][0]['geometry']['location'])) {
//                     $lat = $geoResp['results'][0]['geometry']['location']['lat'];
//                     $lng = $geoResp['results'][0]['geometry']['location']['lng'];
//                 }
//             }
//         }

//         // Quick Actions (static metadata for frontend)
//         $quickActions = [
//             ['key' => 'vehicle_availability', 'title' => 'Vehicle Availability', 'icon' => 'truck'],
//             ['key' => 'farm_management', 'title' => 'Farm Management', 'icon' => 'farm'],
//             ['key' => 'spot_hatcheries', 'title' => 'Spot Hatcheries', 'icon' => 'hatchery'],
//             ['key' => 'seeds_requests', 'title' => 'Seeds Requests', 'icon' => 'seed'],
//         ];

//         // Banners
//         $banners = Banner::where('status', true)->latest()->get();

//         // Hatcheries (with filters, search, geo-distance)
//         $hatcheryQuery = Hatchery::query();

//         if ($search) {
//             $hatcheryQuery->where(function($q) use ($search) {
//                 $q->where('hatchery_name', 'like', "%{$search}%")
//                   ->orWhere('location', 'like', "%{$search}%");
//             });
//         }

//         if ($brands) {
//             $arr = is_array($brands) ? $brands : array_filter(array_map('trim', explode(',', $brands)));
//             if (count($arr)) {
//                 $hatcheryQuery->where(function($q) use ($arr) {
//                     foreach ($arr as $v) {
//                         $q->orWhereJsonContains('category', $v);
//                     }
//                 });
//             }
//         }

//         if ($categories) {
//             $arr = is_array($categories) ? $categories : array_filter(array_map('trim', explode(',', $categories)));
//             if (count($arr)) {
//                 $hatcheryQuery->where(function($q) use ($arr) {
//                     foreach ($arr as $v) {
//                         $q->orWhereJsonContains('category', $v);
//                     }
//                 });
//             }
//         }

//         if ($lat && $lng) {
//             $haversine = "(6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat))))";
//             $hatcheryQuery->selectRaw("hatcheries.*, {$haversine} AS distance", [$lat, $lng, $lat])
//                 ->having('distance', '<=', $radius)
//                 ->orderBy('distance', 'asc');
//         } else {
//             $hatcheryQuery->latest();
//         }

//         $hatcheries = $hatcheryQuery->paginate($perPage, ['*'], 'page', $page);

//         // Add status for each hatchery
//         $now = Carbon::now();
//         foreach ($hatcheries as $h) {
//             if ($h->opening_time && $h->closing_time &&
//                 $h->opening_time <= $now && $h->closing_time >= $now) {
//                 $h->status = "Available";
//             } elseif ($h->available_date && $h->available_date > $now) {
//                 $h->status = "Coming Soon";
//             } else {
//                 $h->status = "Closed";
//             }
//         }

//         // Today's prices
//         $marketPrices = MarketPrice::where('species', $species)
//             ->orderBy('location')
//             ->get()
//             ->groupBy('location')
//             ->map(function($group) {
//                 return $group->map(function($row) {
//                     return [
//                         'size' => $row->size,
//                         'price' => (float) $row->price,
//                     ];
//                 })->values();
//             });

//         // Supplier stocks
//         $suppliers = SupplierStock::with(['hatchery', 'vendor'])
//             ->orderBy('available_date','desc')
//             ->limit(10)
//             ->get();

//         // Medicine News
//         $medicineNews = MedicineNews::latest()->limit(6)->get();

//         // Hatchery Updates
//         $hatcheryUpdates = HatcheryUpdate::with('vendor:id,name,profile_image')
//             ->latest()
//             ->limit(8)
//             ->get();

//         // Weather (OpenWeather API)
//         $weather = null;
//         $owKey = env('OPENWEATHER_API_KEY');
//         if ($lat && $lng && $owKey) {
//             try {
//                 $resp = Http::get('https://api.openweathermap.org/data/2.5/weather', [
//                     'lat' => $lat,
//                     'lon' => $lng,
//                     'appid' => $owKey,
//                     'units' => 'metric'
//                 ]);
//                 if ($resp->successful()) {
//                     $d = $resp->json();
//                     $weather = [
//                         'temp' => isset($d['main']['temp']) ? round($d['main']['temp']) . '°C' : null,
//                         'desc' => $d['weather'][0]['description'] ?? null,
//                         'icon' => isset($d['weather'][0]['icon']) ? "https://openweathermap.org/img/wn/{$d['weather'][0]['icon']}@2x.png" : null,
//                     ];
//                 }
//             } catch (\Throwable $e) {
//                 $weather = null;
//             }
//         }

//         // Vehicle Availability (placeholder)
//         $vehicleAvailability = [
//             'total' => 15,
//             'available_today' => 5,
//         ];

//         // Final payload
//         $payload = [
//             'location' => [
//                 'city' => $city,
//                 'lat' => $lat,
//                 'lng' => $lng,
//                 'weather' => $weather,
//             ],
//             'language' => auth()->user()->language ?? 'en',
//             'quick_actions' => $quickActions,
//             'banners' => $banners,
//             'hatcheries' => $hatcheries,
//             'today_prices' => $marketPrices,
//             'suppliers' => $suppliers,
//             'medicine_news' => $medicineNews,
//             'hatchery_updates' => $hatcheryUpdates,
//             'vehicle_availability' => $vehicleAvailability,
//         ];

//         return response()->json($payload);
//     }
// }
