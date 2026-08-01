<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\Brand;
// use App\Models\Booking;
use App\Models\Hatchery;
use App\Models\HatcheryCategory;
use App\Models\HatcheryLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class HatcheryController extends Controller
{
    protected int $defaultRadiusKm = 20; // Nearby Radius in KM

    /** --------------------------------------------------------------
     *  API 1: HOME — 6 Hatchery Cards (each hatchery shown once with all categories)
     *  Groups duplicate hatchery rows by name and collects all their categories
     *  --------------------------------------------------------------
     */
    public function homeHatcheries(Request $request)
    {
        try {
            $categoryIds = $this->parseCsvIds($request->query('category_id'));
            $locationIds = $this->parseCsvIds($request->query('location_id'));

            $query = Hatchery::active()->with([
                'location:id,location_name,latitude,longitude',
                'category:id,category_name',
                'categories:id,category_name',
                'fishCategory:id,category_name'
            ])
                ->excludingSpot()
                ->when($categoryIds, function($q) use ($categoryIds) {
                    $q->where(function($query) use ($categoryIds) {
                        $query->whereIn('category_id', $categoryIds)
                              ->orWhereHas('categories', fn($cat) => $cat->whereIn('hatchery_categories.id', $categoryIds));
                    });
                })
                ->when($locationIds, fn($q) => $q->whereIn('location_id', $locationIds));

            // Fetch more to account for duplicates, then group by hatchery_name
            $allHatcheries = $query->latest()->take(20)->get();

            // Group by hatchery_name to deduplicate and collect all categories
            $grouped = $allHatcheries->groupBy('hatchery_name');

            $hatcheries = $grouped->map(function($group) {
                // Use the first hatchery as base
                $firstHatchery = $group->first();

                // Collect all unique categories from all duplicate rows
                $allCategories = collect();
                foreach ($group as $h) {
                    // From single category relationship
                    if ($h->category) {
                        $allCategories->push([
                            'id' => $h->category->id,
                            'name' => $h->category->category_name,
                        ]);
                    }
                    // From many-to-many relationship
                    if ($h->categories) {
                        foreach ($h->categories as $cat) {
                            $allCategories->push([
                                'id' => $cat->id,
                                'name' => $cat->category_name,
                            ]);
                        }
                    }
                    // From fishCategory (brand_id)
                    if ($h->fishCategory) {
                        $allCategories->push([
                            'id' => $h->fishCategory->id,
                            'name' => $h->fishCategory->category_name,
                        ]);
                    }
                }

                // Remove duplicate categories
                $uniqueCategories = $allCategories->unique('id')->values()->toArray();

                // Get logo from the most recently updated hatchery that has a logo
                $latestLogo = $group->filter(fn($h) => !empty($h->logo))
                    ->sortByDesc('updated_at')
                    ->first()?->logo;

                return [
                    'id' => $firstHatchery->id,
                    'unique_id' => $firstHatchery->unique_id,
                    'hatchery_name' => $firstHatchery->hatchery_name,
                    'brand_id' => $firstHatchery->brand_id,
                    'brand' => $firstHatchery->category?->category_name,
                    'category_id' => $firstHatchery->category_id ?? $firstHatchery->brand_id,
                    'category' => $uniqueCategories[0]['name'] ?? null,
                    'categories' => $uniqueCategories,
                    'location_id' => $firstHatchery->location_id,
                    'location' => $firstHatchery->location?->location_name,
                    'status_code' => $firstHatchery->overall_status_code,
                    'status' => $firstHatchery->status_label,
                    'available_on' => $firstHatchery->available_on,
                    'image' => $latestLogo,
                    'lat' => $firstHatchery->lat,
                    'lng' => $firstHatchery->lng,
                    'is_spot' => $firstHatchery->is_spot,
                    'opening_time' => $firstHatchery->opening_time,
                    'closing_time' => $firstHatchery->closing_time,
                ];
            })->values()->take(6);

            return response()->json([
                'status' => true,
                'message' => 'Home hatcheries fetched successfully',
                'count' => $hatcheries->count(),
                'data' => $hatcheries
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch home hatcheries',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $categoryIds = $this->parseCsvIds($request->query('category_id'));
            $brandIds = $this->parseCsvIds($request->query('brand_id'));

            // Live Search + Voice Search
            $search = trim($request->query('q', ''));

            // BASE QUERY
            $query = Hatchery::active()->with(['fishCategory:id,category_name', 'category:id,category_name'])
                ->excludingSpot();

            // Filters
            if ($categoryIds) {
                $query->where(function ($q) use ($categoryIds) {
                    $q->whereIn('category_id', $categoryIds)
                        ->orWhereIn('brand_id', $categoryIds);
                });
            }

            if ($brandIds) {
                $query->whereIn('brand_id', $brandIds);
            }

            // Improved Instant Search
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('hatchery_name', 'LIKE', "%$search%")
                        ->orWhereHas(
                            'fishCategory',
                            fn($b) =>
                            $b->where('category_name', 'LIKE', "%$search%")
                        )
                        ->orWhereHas(
                            'category',
                            fn($c) =>
                            $c->where('category_name', 'LIKE', "%$search%")
                        );
                });
            }

            // SORTING
            $query->orderBy('hatchery_name', 'asc');

            // ❌ Pagination removed
            $hatcheries = $query->get();

            // Format API response
            $data = $hatcheries->map(
                fn($h) => $this->mapHatcheryCard($h)
            );

            return response()->json([
                'status' => true,
                'message' => 'Hatcheries fetched successfully',
                'data' => $data
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch hatcheries',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /** --------------------------------------------------------------
     *  API 3: FILTER OPTIONS — Categories, Brands, Locations
     *  --------------------------------------------------------------
     */
    public function filters()
    {
        try {
            $categories = HatcheryCategory::select('id', 'category_name')
                ->orderBy('category_name', 'asc')
                // ->orderBy('priority')
                ->get();

            // $locations = HatcheryLocation::select('id', 'location_name', 'latitude', 'longitude')
            //     ->orderBy('location_name')
            //     ->get();

            $brands = Brand::select('id', 'brand_name')
                ->orderBy('brand_name')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Filters fetched successfully',
                'data' => [
                    'categories' => $categories,
                    // 'locations'  => $locations,
                    'brands' => $brands
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch filters',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /** --------------------------------------------------------------
     *  HELPER — Convert CSV to array
     *  -------------------------------------------------------------- */
    protected function parseCsvIds(?string $csv): array
    {
        if (empty($csv))
            return [];

        return array_values(
            array_filter(
                array_map(fn($v) => is_numeric(trim($v)) ? (int) trim($v) : null, explode(',', $csv))
            )
        );
    }

    /** --------------------------------------------------------------
     *  HELPER — Map Hatchery to API Response Card
     *  -------------------------------------------------------------- */
    protected function mapHatcheryCard(Hatchery $h, $distanceKm = null): array
    {
        return [
            'id' => $h->id,
            'hatchery_name' => $h->hatchery_name,
            'brand_id' => $h->brand_id,
            'brand' => $h->category?->category_name,
            'category_id' => $h->category_id
                ?? $h->brand_id,
            'category' => $h->category?->category_name
                ?? $h->fishCategory?->category_name
                ?? null,
            'location_id' => $h->location_id,
            'location' => $h->location?->location_name,
            'status_code' => $h->overall_status_code,
            'status' => $h->status_label,
            'available_on' => $h->available_on,
            'image' => $h->logo,
            'lat' => $h->lat,
            'lng' => $h->lng,
            'is_spot' => $h->is_spot,
            'opening_time' => $h->opening_time,
            'closing_time' => $h->closing_time,
            'distance_km' => $distanceKm ? round($distanceKm, 2) : null
        ];

    }

    /** --------------------------------------------------------------
     *  Map Hatchery Card WITH ALL CATEGORIES (for home screen)
     *  Shows each hatchery once with all its categories as an array
     *  -------------------------------------------------------------- */
    protected function mapHatcheryCardWithCategories(Hatchery $h, $distanceKm = null): array
    {
        // Collect all categories from both single category and many-to-many relationship
        $allCategories = collect();

        // Add single category if exists
        if ($h->category) {
            $allCategories->push([
                'id' => $h->category->id,
                'name' => $h->category->category_name,
            ]);
        }

        // Add categories from many-to-many relationship
        if ($h->categories && $h->categories->count() > 0) {
            foreach ($h->categories as $cat) {
                // Avoid duplicates
                if (!$allCategories->contains('id', $cat->id)) {
                    $allCategories->push([
                        'id' => $cat->id,
                        'name' => $cat->category_name,
                    ]);
                }
            }
        }

        // Fallback to fishCategory if no categories found
        if ($allCategories->isEmpty() && $h->fishCategory) {
            $allCategories->push([
                'id' => $h->fishCategory->id,
                'name' => $h->fishCategory->category_name,
            ]);
        }

        return [
            'id' => $h->id,
            'hatchery_name' => $h->hatchery_name,
            'brand_id' => $h->brand_id,
            'brand' => $h->category?->category_name,
            // Keep single category for backward compatibility
            'category_id' => $h->category_id ?? $h->brand_id,
            'category' => $h->category?->category_name
                ?? $h->fishCategory?->category_name
                ?? null,
            // NEW: All categories as array
            'categories' => $allCategories->values()->toArray(),
            'location_id' => $h->location_id,
            'location' => $h->location?->location_name,
            'status_code' => $h->overall_status_code,
            'status' => $h->status_label,
            'available_on' => $h->available_on,
            'image' => $h->logo,
            'lat' => $h->lat,
            'lng' => $h->lng,
            'is_spot' => $h->is_spot,
            'opening_time' => $h->opening_time,
            'closing_time' => $h->closing_time,
            'distance_km' => $distanceKm ? round($distanceKm, 2) : null
        ];
    }


    //working single hatchery detail of single category showing
//     public function show($id)
// {
//     try {
//         $h = Hatchery::with([
//             'category:id,category_name',
//             'location:id,location_name',
//             'vendor:id,mobile',
//             'units:id,hatchery_id,branch_name',
//             'broodstock:id,hatchery_id,count',
//             'prices:id,hatchery_id,price'
//         ])->findOrFail($id);

    //         // Images
//         // $images = is_array($h->image) ? $h->image : json_decode($h->image, true);
//         $images = is_array($h->image) ? $h->image : [];


    //         // Similar Hatcheries
//         $similar = Hatchery::where(function ($q) use ($h) {
//                 $q->where('category_id', $h->category_id)
//                   ->orWhere('location_id', $h->location_id);
//             })
//             ->where('id', '!=', $h->id)
//             ->limit(10)
//             ->get()
//             ->map(function ($s) {
//                 // $img = is_array($s->image) ? $s->image : json_decode($s->image, true);
//                 $img = $s->image;  // Accessor already returns an array


    //                 return [
//                     "id" => $s->id,
//                     "hatchery_name" => $s->hatchery_name,
//                     "category_id" => $s->category_id,
//                     "location_id" => $s->location_id,
//                     "status" => $s->status,
//                     "available_on" => $s->available_on,
//                     "image" => $img[0] ?? null,
//                     "is_spot" => $s->is_spot
//                 ];
//             });

    //         return response()->json([
//             "status" => true,
//             "message" => "Hatchery details fetched",
//             "data" => [
//                 "id" => $h->id,
//                 "hatchery_name" => $h->hatchery_name,
//                 "images" => $images,
//                 "status" => $h->status,
//                 "available_on" => $h->available_on,
//                 "is_spot" => $h->is_spot,

    //                 "category" => [
//                     "id" => $h->category_id,
//                     "name" => $h->category?->category_name
//                 ],

    //                 "location" => [
//                     "id" => $h->location_id,
//                     "name" => $h->location?->location_name
//                 ],

    //                 "units" => $h->units->map(fn($u) => [
//                     "id" => $u->id,
//                     "branch_name" => $u->branch_name
//                 ]),

    //                 "broodstock" => $h->broodstock->map(fn($b) => [
//                     "count" => $b->count
//                 ]),

    //                 "prices" => $h->prices->map(fn($p) => [
//                     "price" => $p->price
//                 ]),

    //                 "call_url" => $h->vendor?->mobile ? "tel:+".$h->vendor->mobile : null,
//                 "whatsapp_url" => $h->vendor?->mobile ? "https://wa.me/".$h->vendor->mobile : null,

    //                 "similar_hatcheries" => $similar
//             ]
//         ]);

    //     } catch (\Exception $e) {
//         return response()->json([
//             "status" => false,
//             "message" => "Failed to fetch details",
//             "error" => $e->getMessage()
//         ], 200);
//     }
// }


    // working fw changes
// public function categoryDetail($hatcheryId, $categoryId)
// {
//     try {

    //         // 1) Fetch Category
//         $category = HatcheryCategory::findOrFail($categoryId);

    //         // Category Gallery
//         $gallery = [];
//         if (!empty($category->image)) {
//             $decoded = json_decode($category->image, true) ?? [];
//             foreach ($decoded as $media) {
//                 $gallery[] = str_starts_with($media, "http") ? $media : asset($media);
//             }
//         }

    //         // 2) Fetch ONLY THIS HATCHERY WITH THIS CATEGORY
//         $h = Hatchery::with([
//                 'location:id,location_name,latitude,longitude',
//                 'vendor:id,mobile',
//                 'units:id,hatchery_id,branch_name',
//                 'broodstock:id,hatchery_id,count',
//                 'prices:id,hatchery_id,price'
//             ])
//             ->where('id', $hatcheryId)
//             ->where('category_id', $categoryId)
//             ->first();

    //         if (!$h) {
//             return response()->json([
//                 "status" => false,
//                 "message" => "No hatchery found with this category",
//                 "data" => null
//             ]);
//         }

    //         // Hatchery images
//         $images = is_array($h->image) ? $h->image : [];


    //         // FINAL RESPONSE
//         return response()->json([
//             "status" => true,
//             "message" => "Hatchery-category details fetched",
//             "data" => [
//                 "id" => $h->id,
//                 "hatchery_name" => $h->hatchery_name,
//                 "images" => $images,
//                 "status" => $h->status,
//                 "available_on" => $h->available_on,
//                 "is_spot" => $h->is_spot,

    //                 "category" => [
//                     "id" => $category->id,
//                     "name" => $category->category_name,
//                     "description" => $category->category_description,
//                     "gallery" => $gallery,
//                     "report" => $category->category_report
//                 ],

    //                 "location" => [
//                     "id" => $h->location_id,
//                     "name" => $h->location?->location_name
//                 ],

    //                 "units" => $h->units->map(fn($u) => [
//                     "id" => $u->id,
//                     "branch_name" => $u->branch_name
//                 ]),

    //                 "broodstock" => $h->broodstock->map(fn($b) => [
//                     "count" => $b->count
//                 ]),

    //                 "prices" => $h->prices->map(fn($p) => [
//                     "price" => $p->price
//                 ]),

    //                 "call_url" => $h->vendor?->mobile ? "tel:+".$h->vendor->mobile : null,
//                 "whatsapp_url" => $h->vendor?->mobile ? "https://wa.me/".$h->vendor->mobile : null,
//             ]
//         ]);

    //     } catch (\Exception $e) {
//         return response()->json([
//             "status" => false,
//             "message" => "Failed to fetch details",
//             "error" => $e->getMessage()
//         ], 200);
//     }
// }


    /**
     * Build the "similar hatcheries" list for a hatchery detail response.
     *
     * Filters candidates to within [$radiusKm] km of the viewed hatchery's
     * location and orders them nearest-first (shorter distances first, farther
     * ones later). Falls back to the previous status-ordered list when the
     * viewed hatchery has no coordinates, so the section is never empty due to
     * missing geo data. Similar hatcheries without coordinates are excluded
     * from the radius result (we can't confirm they're within range).
     *
     * @param  \App\Models\Hatchery  $firstHatchery  the hatchery being viewed
     * @param  array  $excludeIds     ids already shown (same unique_id group)
     */
    private function buildSimilarHatcheries($firstHatchery, array $excludeIds, float $radiusKm = 200.0, int $limit = 10)
    {
        $statusLabels = [1 => 'Available', 2 => 'Coming Soon', 3 => 'Upcoming', 4 => 'Shortly Available', 5 => 'Closed'];

        $map = function ($s, $distance = null) use ($statusLabels) {
            $img = $s->image; // full URLs (the first item is often a VIDEO)
            // Card thumbnail: prefer the hatchery LOGO (always a real image) so
            // the similar-hatchery cards show an image, not a broken video frame.
            // Fall back to the first media only when there's no logo.
            $logo = $s->logo;
            $thumb = !empty($logo)
                ? (str_starts_with($logo, 'http') ? $logo : asset($logo))
                : ($img[0] ?? null);
            $statusCode = $s->hatchery_status ?? 5;
            return [
                "id" => $s->id,
                "hatchery_name" => $s->hatchery_name,
                "category_id" => $s->category_id,
                "location" => $s->location?->location_name,
                "status" => $statusLabels[$statusCode] ?? 'Closed',
                "available_on" => $s->available_on,
                "image" => $thumb,
                "is_spot" => $s->is_spot,
                "unique_id" => $s->unique_id,
                "distance_km" => $distance !== null ? round($distance, 1) : null,
            ];
        };

        // Origin = the viewed hatchery's own map-pinned coordinates
        // (hatcheries.lat/lng, set via the map picker on the hatchery form).
        $originLat = $firstHatchery->lat;
        $originLng = $firstHatchery->lng;

        // Not pinned on the map → can't compute distance. Fall back to the
        // same-category / same-location list (status-ordered) so the section
        // still shows something instead of being empty.
        if (!is_numeric($originLat) || !is_numeric($originLng)) {
            $fallback = Hatchery::active()->with('location:id,location_name,latitude,longitude')
                ->where(function ($q) use ($firstHatchery) {
                    $q->where('category_id', $firstHatchery->category_id)
                        ->orWhere('location_id', $firstHatchery->location_id);
                })
                ->whereNotIn('id', $excludeIds)
                ->whereNotNull('hatchery_status')
                ->where('hatchery_status', '!=', 5)
                ->orderByRaw("FIELD(hatchery_status, 1, 4, 2, 3)")
                ->limit($limit)
                ->get();
            return $fallback->map(fn($s) => $map($s))->values();
        }

        $originLat = (float) $originLat;
        $originLng = (float) $originLng;

        // DISTANCE-ONLY similar hatcheries: any pinned, not-closed hatchery
        // within $radiusKm of the viewed one — regardless of category or
        // location. A bounding box keeps the query light; haversine refines it.
        // (~111 km per degree of latitude; pad so we never miss an edge case.)
        $latPad = ($radiusKm / 111.0) + 0.2;
        $cos = max(cos(deg2rad($originLat)), 0.01);
        $lngPad = ($radiusKm / (111.0 * $cos)) + 0.2;

        $candidates = Hatchery::active()->with('location:id,location_name,latitude,longitude')
            ->whereNotIn('id', $excludeIds)
            ->whereNotNull('hatchery_status')
            ->where('hatchery_status', '!=', 5)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->whereBetween('lat', [$originLat - $latPad, $originLat + $latPad])
            ->whereBetween('lng', [$originLng - $lngPad, $originLng + $lngPad])
            ->limit(200)
            ->get();

        $withDistance = [];
        foreach ($candidates as $s) {
            if (!is_numeric($s->lat) || !is_numeric($s->lng)) {
                continue;
            }
            $distance = $this->haversineKm($originLat, $originLng, (float) $s->lat, (float) $s->lng);
            if ($distance <= $radiusKm) {
                $withDistance[] = ['model' => $s, 'distance' => $distance];
            }
        }

        // Nearest first.
        usort($withDistance, fn($a, $b) => $a['distance'] <=> $b['distance']);

        // Dedupe by unique_id (a hatchery with several categories has multiple
        // rows at the same pin) so each hatchery appears once, nearest kept.
        $seen = [];
        $result = [];
        foreach ($withDistance as $row) {
            $uid = $row['model']->unique_id;
            if ($uid !== null && $uid !== '' && isset($seen[$uid])) {
                continue;
            }
            if ($uid !== null && $uid !== '') {
                $seen[$uid] = true;
            }
            $result[] = $map($row['model'], $row['distance']);
            if (count($result) >= $limit) {
                break;
            }
        }

        return collect($result)->values();
    }

    /**
     * Great-circle distance in kilometres between two lat/lng points.
     */
    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    /**
     * Show hatchery details by unique_id (for home screen flow)
     * Returns all hatcheries with the same unique_id
     * @param string $uniqueId - The unique_id to fetch hatcheries
     */
    public function show($uniqueId)
    {
        try {
            // Fetch all hatcheries with this unique_id
            $hatcheries = Hatchery::active()->with([
                'category:id,category_name,image',
                'categories:id,category_name,image',
                'location:id,location_name,latitude,longitude',
                'vendor:id,mobile',
                'units:id,hatchery_id,branch_name',
                'broodstock:id,hatchery_id,count',
                'prices:id,hatchery_id,price'
            ])->where('unique_id', $uniqueId)->get();

            if ($hatcheries->isEmpty()) {
                return response()->json([
                    "status" => false,
                    "message" => "No hatcheries found with this unique_id",
                    "data" => [],
                    "similar_hatcheries" => []
                ], 200);
            }

            // Use first hatchery for similar hatcheries query
            $firstHatchery = $hatcheries->first();

            // SIMILAR HATCHERIES WITH LOCATION NAME (exclude all hatcheries with same unique_id)
            // Priority: Available(1) > Shortly Available(4) > Coming Soon(2) > Upcoming(3), NO Closed(5)
            $hatcheryIds = $hatcheries->pluck('id')->toArray();
            // Similar hatcheries filtered to within 150 km of the viewed
            // hatchery's location and ordered nearest-first (see helper).
            $similar = $this->buildSimilarHatcheries($firstHatchery, $hatcheryIds);

            $data = collect();

            // Loop through each hatchery with this unique_id
            foreach ($hatcheries as $h) {
                // SINGLE BROODSTOCK
                $finalBroodstock = $h->broodstock_count
                    ?? $h->broodstock->first()->count
                    ?? null;

                // Get category_prices from hatchery
                $categoryPrices = $h->category_prices ?? [];

                // Get hatchery images
                $hatcheryImages = [];
                if (!empty($h->image)) {
                    $decoded = is_array($h->image) ? $h->image : json_decode($h->image, true);
                    foreach ($decoded ?? [] as $img) {
                        $hatcheryImages[] = str_starts_with($img, 'http') ? $img : asset($img);
                    }
                }

                // Determine category (shrimp or fish)
                $category = null;
                if ($h->category_id && $h->category) {
                    $category = [
                        "id" => $h->category->id,
                        "name" => $h->category->category_name
                    ];
                } elseif ($h->brand_id) {
                    $fishCat = \App\Models\HatcheryCategory::find($h->brand_id);
                    if ($fishCat) {
                        $category = [
                            "id" => $fishCat->id,
                            "name" => $fishCat->category_name
                        ];
                    }
                }

                $data->push([
                    "id" => $h->id,
                    "unique_id" => $h->unique_id,
                    "hatchery_name" => $h->hatchery_name,
                    "images" => $hatcheryImages,
                    "status" => $h->hatchery_status,
                    "status_label" => $h->status_label,
                    "available_on" => $h->available_on,
                    "is_spot" => $h->is_spot,
                    "category" => $category,
                    "location" => [
                        "id" => $h->location_id,
                        "name" => $h->location?->location_name
                    ],
                    "units" => $h->units->map(fn($u) => [
                        "id" => $u->id,
                        "branch" => [
                            "id" => $u->id,
                            "name" => $u->branch?->branch_name,
                            "address" => $u->branch?->address,
                        ],
                    ]),
                    "broodstock" => $finalBroodstock,
                    "price" => $h->price ?? 0,
                    "description" => $h->description,
                    "report" => $h->report ? asset($h->report) : null,
                    "call_url" => $h->call_number ? "tel:+91{$h->call_number}" : ($h->vendor?->mobile ? "tel:+{$h->vendor->mobile}" : null),
                    "whatsapp_url" => $h->whatsapp_number ? "https://wa.me/{$h->whatsapp_number}" : ($h->vendor?->mobile ? "https://wa.me/{$h->vendor->mobile}" : null),
                ]);
            }

            return response()->json([
                "status" => true,
                "message" => "Hatchery details fetched",
                "data" => $data,
                "similar_hatcheries" => $similar
            ]);

        } catch (\Exception $e) {
            return response()->json([
                "status" => false,
                "message" => "Failed to fetch details",
                "error" => $e->getMessage()
            ], 200);
        }
    }

    public function categoryDetail($hatcheryId, $categoryId)
    {
        try {
            $category = HatcheryCategory::findOrFail($categoryId);

            // Gallery
            $gallery = [];
            if (!empty($category->image)) {
                $decoded = json_decode($category->image, true) ?? [];
                foreach ($decoded as $media) {
                    $gallery[] = str_starts_with($media, "http") ? $media : asset($media);
                }
            }

            // Fetch hatchery for this category
            $query = Hatchery::active()->with([
                'location:id,location_name,latitude,longitude',
                'vendor:id,mobile',
                'units:id,hatchery_id,branch_name',
                'broodstock:id,hatchery_id,count',
                'prices:id,hatchery_id,price'
            ])->where('id', $hatcheryId);

            if ($category->type === 'fish') {
                // 🐟 FISH → brand_id
                $query->where('brand_id', $categoryId);
            } else {
                // 🦐 SHRIMP → category_id
                $query->where('category_id', $categoryId);
            }

            $h = $query->first();

            if (!$h) {
                return response()->json([
                    "status" => false,
                    "message" => "No hatchery found with this category",
                    "data" => null
                ]);
            }

            // SINGLE brood / price
            $brood = $h->broodstock->first()->count ?? null;
            $price = $h->prices->first()->price ?? null;

            return response()->json([
                "status" => true,
                "message" => "Hatchery-category details fetched",
                "data" => [
                    "id" => $h->id,
                    "hatchery_name" => $h->hatchery_name,
                    "images" => is_array($h->image) ? $h->image : [],
                    "status" => $h->hatchery_status,
                    "description" => $h->description,
                    "available_on" => $h->available_on,
                    "is_spot" => $h->is_spot,

                    "category" => [
                        "id" => $category->id,
                        "name" => $category->category_name,
                        "description" => $category->category_description,
                        "gallery" => $gallery,
                        "report" => $category->category_report
                    ],

                    "location" => [
                        "id" => $h->location_id,
                        "name" => $h->location?->location_name
                    ],

                    "units" => $h->units->map(fn($u) => [
                        "id" => $u->id,
                        "branch" => [
                            "id" => $u->id,
                            "name" => $u->branch?->branch_name,
                            "address" => $u->branch?->address,
                        ],
                    ]),

                    "broodstock" => $h->broodstock_count ?? $brood,    // ⭐ SINGLE
                    "price" => $h->price ?? $price,               // ⭐ SINGLE
                    "report" => $h->report ? asset($h->report) : null,

                    "call_url" => $h->call_number ? "tel:+91" . $h->call_number : ($h->vendor?->mobile ? "tel:+" . $h->vendor->mobile : null),
                    "whatsapp_url" => $h->whatsapp_number ? "https://wa.me/" . $h->whatsapp_number : ($h->vendor?->mobile ? "https://wa.me/" . $h->vendor->mobile : null),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                "status" => false,
                "message" => "Failed to fetch details",
                "error" => $e->getMessage()
            ], 200);
        }
    }

    /**
     * Show hatchery details by database id (for search flow)
     * Returns hatchery with the given database id
     * @param int $id - The database id to fetch hatchery
     */
    public function showById($id)
    {
        try {
            // Fetch hatchery by database id
            $hatcheries = Hatchery::active()->with([
                'category:id,category_name,image',
                'categories:id,category_name,image',
                'location:id,location_name,latitude,longitude',
                'vendor:id,mobile',
                'units:id,hatchery_id,branch_name',
                'broodstock:id,hatchery_id,count',
                'prices:id,hatchery_id,price'
            ])->where('id', $id)->get();

            if ($hatcheries->isEmpty()) {
                return response()->json([
                    "status" => false,
                    "message" => "No hatcheries found with this id",
                    "data" => [],
                    "similar_hatcheries" => []
                ], 200);
            }

            // Use first hatchery for similar hatcheries query
            $firstHatchery = $hatcheries->first();

            // SIMILAR HATCHERIES WITH LOCATION NAME
            // Priority: Available(1) > Shortly Available(4) > Coming Soon(2) > Upcoming(3), NO Closed(5)
            $hatcheryIds = $hatcheries->pluck('id')->toArray();
            // Similar hatcheries filtered to within 150 km of the viewed
            // hatchery's location and ordered nearest-first (see helper).
            $similar = $this->buildSimilarHatcheries($firstHatchery, $hatcheryIds);

            $data = collect();

            // Loop through each hatchery
            foreach ($hatcheries as $h) {
                // SINGLE BROODSTOCK
                $finalBroodstock = $h->broodstock_count
                    ?? $h->broodstock->first()->count
                    ?? null;

                // Get hatchery images
                $hatcheryImages = [];
                if (!empty($h->image)) {
                    $decoded = is_array($h->image) ? $h->image : json_decode($h->image, true);
                    foreach ($decoded ?? [] as $img) {
                        $hatcheryImages[] = str_starts_with($img, 'http') ? $img : asset($img);
                    }
                }

                // Determine category (shrimp or fish)
                $category = null;
                if ($h->category_id && $h->category) {
                    $category = [
                        "id" => $h->category->id,
                        "name" => $h->category->category_name
                    ];
                } elseif ($h->brand_id) {
                    $fishCat = \App\Models\HatcheryCategory::find($h->brand_id);
                    if ($fishCat) {
                        $category = [
                            "id" => $fishCat->id,
                            "name" => $fishCat->category_name
                        ];
                    }
                }

                $data->push([
                    "id" => $h->id,
                    "unique_id" => $h->unique_id,
                    "hatchery_name" => $h->hatchery_name,
                    "images" => $hatcheryImages,
                    "status" => $h->hatchery_status,
                    "status_label" => $h->status_label,
                    "available_on" => $h->available_on,
                    "is_spot" => $h->is_spot,
                    "category" => $category,
                    "location" => [
                        "id" => $h->location_id,
                        "name" => $h->location?->location_name
                    ],
                    "units" => $h->units->map(fn($u) => [
                        "id" => $u->id,
                        "branch" => [
                            "id" => $u->id,
                            "name" => $u->branch?->branch_name,
                            "address" => $u->branch?->address,
                        ],
                    ]),
                    "broodstock" => $finalBroodstock,
                    "price" => $h->price ?? 0,
                    "description" => $h->description,
                    "report" => $h->report ? asset($h->report) : null,
                    "call_url" => $h->call_number ? "tel:+91{$h->call_number}" : ($h->vendor?->mobile ? "tel:+{$h->vendor->mobile}" : null),
                    "whatsapp_url" => $h->whatsapp_number ? "https://wa.me/{$h->whatsapp_number}" : ($h->vendor?->mobile ? "https://wa.me/{$h->vendor->mobile}" : null),
                ]);
            }

            return response()->json([
                "status" => true,
                "message" => "Hatchery details fetched",
                "data" => $data,
                "similar_hatcheries" => $similar
            ]);

        } catch (\Exception $e) {
            return response()->json([
                "status" => false,
                "message" => "Failed to fetch details",
                "error" => $e->getMessage()
            ], 200);
        }
    }


}




// {

//     // working fine
//     public function homeHatcheries(Request $request)
//     {
//         try {
//             $categoryId = $request->category_id;
//             $locationId = $request->location_id;

//             $query = Hatchery::with(['brand:id,brand_name', 'category:id,category_name', 'location:id,location_name'])
//                 ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
//                 ->when($locationId, fn($q) => $q->where('location_id', $locationId));

//            $hatcheries = $query->latest()->take(6)->get()->map(function ($h) {
//             return [
//                 'id' => $h->id,
//                 'hatchery_name' => $h->hatchery_name,
//                 'category_id' => $h->category_id,
//                 'category' => $h->category?->category_name ?? '',
//                 'location_id' => $h->location_id,
//                 'location' => $h->location?->location_name ?? '',
//                 'status' => $h->status ?? 'coming soon',
//                 'available_on' => $h->available_on ?? null,
//                 'image' => is_array($h->image) ? $h->image[0] ?? null : $h->image,
//             ];
//         });

//             return response()->json([
//                 'status' => true,
//                 'message' => 'Home hatcheries fetched successfully',
//                 'count' => $hatcheries->count(),
//                 'data' => $hatcheries
//             ]);
//         } catch (Exception $e) {
//             return response()->json(['status' => false, 'message' => 'Failed to fetch home hatcheries', 'error' => $e->getMessage()]);
//         }
//     }

//     //spot hatcheries
//     //     public function homeHatcheries(Request $request)
//     // {
//     //     try {
//     //         $categoryId = $request->category_id;
//     //         $locationId = $request->location_id;

//     //         // Base query
//     //         $query = Hatchery::with(['brand:id,brand_name', 'category:id,category_name', 'location:id,location_name'])
//     //             ->where('is_spot', 0)
//     //             ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
//     //             ->when($locationId, fn($q) => $q->where('location_id', $locationId));

//     //         // Step 1: Get primary results
//     //         $hatcheries = $query->latest()->take(6)->get();

//     //         // Step 2: If less than 6, find nearby hatcheries
//     //         if ($hatcheries->count() < 6 && $locationId) {
//     //             $location = \App\Models\HatcheryLocation::find($locationId);

//     //             if ($location && $location->latitude && $location->longitude) {
//     //                 $nearby = Hatchery::with(['category:id,category_name', 'location:id,location_name'])
//     //                     ->where('is_spot', 0)
//     //                     ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
//     //                     ->select('*', DB::raw("
//     //                         (6371 * acos(
//     //                             cos(radians($location->latitude))
//     //                             * cos(radians(lat))
//     //                             * cos(radians(lng) - radians($location->longitude))
//     //                             + sin(radians($location->latitude)) * sin(radians(lat))
//     //                         )) AS distance
//     //                     "))
//     //                     ->whereNotNull('lat')
//     //                     ->whereNotNull('lng')
//     //                     ->orderBy('distance', 'asc')
//     //                     ->take(6 - $hatcheries->count())
//     //                     ->get();

//     //                 $hatcheries = $hatcheries->merge($nearby);
//     //             }
//     //         }

//     //         // Step 3: Map and format response
//     //         $responseData = $hatcheries->map(function ($h) {
//     //             return [
//     //                 'id' => $h->id,
//     //                 'hatchery_name' => $h->hatchery_name,
//     //                 'category_id' => $h->category_id,
//     //                 'category' => $h->category?->category_name ?? '',
//     //                 'location_id' => $h->location_id,
//     //                 'location' => $h->location?->location_name ?? '',
//     //                 'status' => $h->status ?? 'coming soon',
//     //                 'available_on' => $h->available_on ?? null,
//     //                 'image' => is_array($h->image) ? $h->image[0] ?? null : $h->image,
//     //             ];
//     //         });

//     //         return response()->json([
//     //             'status' => true,
//     //             'message' => 'Home hatcheries fetched successfully',
//     //             'count' => $responseData->count(),
//     //             'data' => $responseData
//     //         ]);
//     //     } catch (Exception $e) {
//     //         return response()->json([
//     //             'status' => false,
//     //             'message' => 'Failed to fetch home hatcheries',
//     //             'error' => $e->getMessage()
//     //         ]);
//     //     }
//     // }


//     // advs
//     // public function homeHatcheries(Request $request)
//     // {
//     //     try {
//     //         $categoryId = $request->category_id;
//     //         $locationId = $request->location_id;
//     //         $latitude   = $request->latitude;
//     //         $longitude  = $request->longitude;

//     //         // Base query
//     //         $query = Hatchery::with(['brand:id,brand_name', 'category:id,category_name', 'location:id,location_name'])
//     //             ->where('is_spot', 0)
//     //             ->when($categoryId, fn($q) => $q->where('category_id', $categoryId));

//     //         // CASE 1: Filter by location_id
//     //         if ($locationId) {
//     //             $query->where('location_id', $locationId);
//     //         }

//     //         // CASE 2: Filter by user’s GPS (latitude + longitude)
//     //         elseif ($latitude && $longitude) {
//     //             $query->select('*', DB::raw("
//     //                 (6371 * acos(
//     //                     cos(radians($latitude))
//     //                     * cos(radians(lat))
//     //                     * cos(radians(lng) - radians($longitude))
//     //                     + sin(radians($latitude)) * sin(radians(lat))
//     //                 )) AS distance
//     //             "))
//     //             ->whereNotNull('lat')
//     //             ->whereNotNull('lng')
//     //             ->orderBy('distance', 'asc');
//     //         }

//     //         // Fetch top 6 hatcheries
//     //         $hatcheries = $query->latest()->take(6)->get();

//     //         // Step 2: If less than 6, find nearby ones (fallback)
//     //         if ($hatcheries->count() < 6 && $locationId) {
//     //             $location = \App\Models\HatcheryLocation::find($locationId);

//     //             if ($location && $location->latitude && $location->longitude) {
//     //                 $nearby = Hatchery::with(['category:id,category_name', 'location:id,location_name'])
//     //                     ->where('is_spot', 0)
//     //                     ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
//     //                     ->select('*', DB::raw("
//     //                         (6371 * acos(
//     //                             cos(radians($location->latitude))
//     //                             * cos(radians(lat))
//     //                             * cos(radians(lng) - radians($location->longitude))
//     //                             + sin(radians($location->latitude)) * sin(radians(lat))
//     //                         )) AS distance
//     //                     "))
//     //                     ->whereNotNull('lat')
//     //                     ->whereNotNull('lng')
//     //                     ->orderBy('distance', 'asc')
//     //                     ->take(6 - $hatcheries->count())
//     //                     ->get();

//     //                 $hatcheries = $hatcheries->merge($nearby);
//     //             }
//     //         }

//     //         // Step 3: Format the response
//     //         $responseData = $hatcheries->map(function ($h) {
//     //             // Handle image properly
//     //             $image = null;
//     //             if (is_array($h->image)) {
//     //                 $image = $h->image[0] ?? null;
//     //             } elseif (is_string($h->image)) {
//     //                 // Sometimes JSON is stored as a string, so decode if needed
//     //                 $decoded = json_decode($h->image, true);
//     //                 $image = is_array($decoded) ? ($decoded[0] ?? null) : $h->image;
//     //             }

//     //             // Add base URL if image path is relative
//     //             if ($image && !str_contains($image, 'http')) {
//     //                 $image = url($image);
//     //             }

//     //             return [
//     //                 'id' => $h->id,
//     //                 'hatchery_name' => $h->hatchery_name,
//     //                 'category_id' => $h->category_id,
//     //                 'category' => $h->category?->category_name ?? '',
//     //                 'location_id' => $h->location_id,
//     //                 'location' => $h->location?->location_name ?? '',
//     //                 'status' => $h->status ?? 'coming soon',
//     //                 'available_on' => $h->available_on ?? null,
//     //                 'image' => $image ?? null,
//     //                 'distance_km' => isset($h->distance) ? round($h->distance, 2) . ' km' : null,
//     //             ];
//     //         });

//     //         return response()->json([
//     //             'status' => true,
//     //             'message' => 'Home hatcheries fetched successfully',
//     //             'count' => $responseData->count(),
//     //             'data' => $responseData
//     //         ]);

//     //     } catch (Exception $e) {
//     //         return response()->json([
//     //             'status' => false,
//     //             'message' => 'Failed to fetch home hatcheries',
//     //             'error' => $e->getMessage()
//     //         ]);
//     //     }
//     // }




//     public function allHatcheries(Request $request)
//     {
//         try {
//             $query = Hatchery::with(['brand:id,brand_name', 'category:id,category_name', 'location:id,location_name']);

//             if ($request->filled('brand_ids')) {
//                 $query->whereIn('brand_id', explode(',', $request->brand_ids));
//             }
//             if ($request->filled('category_ids')) {
//                 $query->whereIn('category_id', explode(',', $request->category_ids));
//             }
//             if ($request->filled('location_ids')) {
//                 $query->whereIn('location_id', explode(',', $request->location_ids));
//             }

//             if ($request->filled('lat') && $request->filled('lng')) {
//                 $lat = $request->lat;
//                 $lng = $request->lng;
//                 $query->select('*', DB::raw("(6371 * acos(cos(radians($lat))
//                     * cos(radians(lat))
//                     * cos(radians(lng) - radians($lng))
//                     + sin(radians($lat)) * sin(radians(lat)))) AS distance"))
//                     ->having('distance', '<=', 50)
//                     ->orderBy('distance', 'asc');
//             }

//             $hatcheries = $query->get()->map(function ($h) {
//                 return [
//                     'id' => $h->id,
//                     'hatchery_name' => $h->hatchery_name,
//                     'location' => $h->location?->location_name ?? 'Unknown',
//                     'category' => $h->category?->category_name ?? 'Unknown',
//                     'brand' => $h->brand?->brand_name ?? 'Unknown',
//                     'status' => $h->status ?? 'available',
//                     'available_on' => $h->available_on,
//                     'image' => $h->image[0] ?? null,
//                 ];
//             });

//             return response()->json([
//                 'status' => true,
//                 'message' => 'Filtered hatcheries fetched successfully',
//                 'count' => $hatcheries->count(),
//                 'data' => $hatcheries,
//             ]);
//         } catch (Exception $e) {
//             return response()->json(['status' => false, 'message' => 'Error fetching hatcheries', 'error' => $e->getMessage()]);
//         }
//     }


//     public function getHatcheryById($id)
//     {
//         try {
//             $hatchery = Hatchery::with(['brand', 'category', 'location'])->findOrFail($id);

//             $similar = Hatchery::where('id', '!=', $id)
//                 ->where('category_id', $hatchery->category_id)
//                 ->take(6)
//                 ->get(['id', 'hatchery_name', 'status', 'available_on', 'image']);

//             return response()->json([
//                 'status' => true,
//                 'message' => 'Hatchery details fetched successfully',
//                 'data' => [
//                     'hatchery' => $hatchery,
//                     'similar_hatcheries' => $similar,
//                 ],
//             ]);
//         } catch (ModelNotFoundException $e) {
//             return response()->json(['status' => false, 'message' => 'Hatchery not found'], 404);
//         }
//     }

//     public function hatcheryCategoryDetails($hatcheryId, $categoryId)
//     {
//         try {
//             $hatchery = Hatchery::with(['brand', 'location'])
//                 ->where('id', $hatcheryId)
//                 ->where('category_id', $categoryId)
//                 ->firstOrFail();

//             return response()->json([
//                 'status' => true,
//                 'message' => 'Category details fetched successfully',
//                 'data' => $hatchery,
//             ]);
//         } catch (ModelNotFoundException $e) {
//             return response()->json(['status' => false, 'message' => 'Category not found'], 404);
//         }
//     }


//     //no need see booking controller already wrote their function for this
//         /**
//      * 4️⃣ POST /api/user/hatcheries/{id}/book
//      * Book hatchery (Booking Slide)
//      */
//     // public function bookHatchery(Request $request, $hatcheryId)
//     // {
//     //     try {
//     //         $validated = $request->validate([
//     //             'customer_name' => 'required|string|max:255',
//     //             'customer_mobile' => 'required|string|max:15',
//     //             'unit' => 'nullable|string',
//     //             'no_of_pieces' => 'required|integer',
//     //             'dropping_location' => 'required|string|max:255',
//     //             'packing_date' => 'required|date',
//     //         ]);

//     //         $hatchery = Hatchery::with(['vendor', 'location'])->findOrFail($hatcheryId);

//     //         // ✅ Ensure hatchery has a vendor
//     //         if (!$hatchery->vendor_id) {
//     //             return response()->json([
//     //                 'status' => false,
//     //                 'message' => 'This hatchery is not linked to any vendor.',
//     //             ], 400);
//     //         }

//     //         $booking = Booking::create([
//     //             'vendor_id' => $hatchery->vendor_id,
//     //             'hatchery_id' => $hatchery->id,
//     //             'customer_name' => $validated['customer_name'],
//     //             'customer_mobile' => $validated['customer_mobile'],
//     //             'unit' => $validated['unit'] ?? null,
//     //             'no_of_pieces' => $validated['no_of_pieces'],
//     //             'dropping_location' => $validated['dropping_location'],
//     //             'packing_date' => $validated['packing_date'],
//     //             'hatchery_name' => $hatchery->hatchery_name,
//     //             'hatchery_location' => $hatchery->location->location_name ?? 'Unknown',
//     //         ]);

//     //         return response()->json([
//     //             'status' => true,
//     //             'message' => 'Your request booking was sent successfully',
//     //             'booking_id' => $booking->id,
//     //         ], 200);

//     //     } catch (Exception $e) {
//     //         return response()->json([
//     //             'status' => false,
//     //             'message' => 'Error creating booking',
//     //             'error' => $e->getMessage(),
//     //         ], 500);
//     //     }
//     // }


//     //     public function showBooking($id)
//     //     {
//     //         try {
//     //             $booking = Booking::with(['hatchery.brand', 'hatchery.category', 'hatchery.location'])->findOrFail($id);

//     //             return response()->json([
//     //                 'status' => true,
//     //                 'message' => 'Booking details fetched successfully',
//     //                 'data' => $booking,
//     //             ]);
//     //         } catch (ModelNotFoundException $e) {
//     //             return response()->json(['status' => false, 'message' => 'Booking not found'], 404);
//     //         }
//     //     }






// }


