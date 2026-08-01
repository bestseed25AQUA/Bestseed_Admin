<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\Broodstock;
use App\Models\Hatchery;
use App\Models\HatcheryCategory;
use App\Models\AppConfig;
use Illuminate\Http\Request;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class BroodStockController_user extends Controller
{
    public function index(Request $request)
    {
        try {
            // 🧠 1️⃣ Fetch filters from query
            $categoryId = $request->query('category_id');
            $locationId = $request->query('location_id');

            // 🧠 2️⃣ Base query with relationships
            $query = Broodstock::with(['vendor', 'category', 'location', 'hatchery.location'])
                ->orderByDesc('id');

            // 🧠 3️⃣ Apply filters dynamically
            // if (!empty($categoryId)) {
            //     $query->where('category_id', $categoryId);
            // }

            $allowedCategories = HatcheryCategory::whereIn('category_name', [
                'Vannamei',
                'Tiger'
            ])->pluck('id');

            $query->whereIn('category_id', $allowedCategories);




            if (!empty($locationId)) {
                $query->where('location_id', $locationId);
            }

            $broodstocks = $query->get();

            // ✅ Base URL (from .env or fallback)
            $baseUrl = rtrim(config('app.url'), '/') . '/';

            // 🧠 4️⃣ Format response for frontend
            $data = $broodstocks->map(function ($item) use ($baseUrl) {

                //  $location = $item->hatchery?->location;
                $location = $item->location ?? $item->hatchery?->location;

                $today = now()->startOfDay();
                $availableDate = $item->available_date
                    ? \Carbon\Carbon::parse($item->available_date)->startOfDay()
                    : null;

                if (!$availableDate) {

                    $availableOnText = 'Upcoming';

                } elseif ($availableDate->isBefore($today)) {

                    $availableOnText = 'Closed ' . $availableDate->format('d/m/Y');

                } elseif ($availableDate->isSameDay($today)) {

                    $availableOnText = 'Available ' . $availableDate->format('d/m/Y');

                } elseif ($availableDate->diffInDays($today) <= 7) {

                    $availableOnText = 'Shortly Available ' . $availableDate->format('d/m/Y');

                } else {

                    $availableOnText = 'Upcoming ' . $availableDate->format('d/m/Y');
                }

                return [
                    'id' => $item->id,
                    // 'hatchery_name' => $item->hatchery_name ?? '',
                    'hatchery_name' => $item->hatchery->hatchery_name
                        ?? $item->hatchery_name
                        ?? '',
                    'supplier_name' => $item->supplier_name ?? '',
                    'category' => $item->category ? [
                        'category_name' => $item->category->category_name ?? '',
                    ] : [],
                    'available_quantity' => ($item->count ?? 0) . ' Pieces',
                    'available_on' => $availableOnText,
                    'packing_start' => $item->packing_date
                        ? 'Packing Start ' . \Carbon\Carbon::parse($item->packing_date)->format('d/m/Y')
                        : '',
                    'imported_date' => $item->created_at
                        ? \Carbon\Carbon::parse($item->created_at)->format('d/m/Y')
                        : '',
                    // 'image' => $imageUrl,

                    'location' => $location ? [
                        'longitude' => $location->longitude ?? '',
                        'latitude' => $location->latitude ?? '',
                        'location_name' => $location->location_name ?? '',
                    ] : [],
                ];
            });

            return response()->json([
                'status' => true,
                'message' => $data->isEmpty()
                    ? 'No broodstock data found'
                    : 'Broodstock data fetched successfully',
                'data' => $data,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch broodstock data',
                'error' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    //updated code
    public function getBroodStockList(Request $request)
    {
        try {

            $query = Broodstock::with(['category', 'location', 'hatchery.location'])
                ->orderBy('id', 'desc');

            // Search
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('hatchery_name', 'like', "%{$search}%")
                        ->orWhere('supplier_name', 'like', "%{$search}%")
                        ->orWhereHas('hatchery', function ($hq) use ($search) {
                            $hq->where('hatchery_name', 'like', "%{$search}%");
                        });
                });
            }

            // Allowed categories
            $allowedCategories = HatcheryCategory::whereIn('category_name', [
                'Vannamei',
                'Tiger'
            ])->pluck('id');

            $query->whereIn('category_id', $allowedCategories);

            // Filter by category_id
            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            // Filter by category_name (e.g., "Tiger" or "Vannamei")
            if ($request->filled('category_name')) {
                $categoryByName = HatcheryCategory::where('category_name', $request->category_name)->first();
                if ($categoryByName) {
                    $query->where('category_id', $categoryByName->id);
                }
            }

            // Filter by category (supports both id and name)
            if ($request->filled('category')) {
                $categoryValue = $request->category;
                if (is_numeric($categoryValue)) {
                    $query->where('category_id', $categoryValue);
                } else {
                    $categoryByName = HatcheryCategory::where('category_name', $categoryValue)->first();
                    if ($categoryByName) {
                        $query->where('category_id', $categoryByName->id);
                    }
                }
            }

            // Filter by Year (based on imported_date)
            if ($request->filled('year')) {
                $query->whereYear('imported_date', $request->year);
            }

            // Filter by Month (supports both name and number, based on imported_date)
            if ($request->filled('month')) {
                $monthValue = $request->month;
                if (!is_numeric($monthValue)) {
                    // Convert month name to number (jan -> 01, feb -> 02, etc.)
                    $monthValue = date('m', strtotime($monthValue . ' 1'));
                }
                $query->whereMonth('imported_date', $monthValue);
            }

            // Fetch
            $broodStocks = $query->get();

            // Status mapping: 1=Available, 2=Coming Soon, 3=Upcoming, 4=Shortly Available, 5=Closed
            $statusMap = [
                1 => 'available',
                2 => 'coming_soon',
                3 => 'upcoming',
                4 => 'shortly_available',
                5 => 'closed',
            ];

            $data = $broodStocks->map(function ($item) use ($statusMap) {

                // Get status from status column
                $status = $statusMap[$item->status] ?? 'upcoming';

                // If not active, override to shutdown
                if (!$item->is_active) {
                    $status = 'shutdown';
                }

                // Format dates
                $availableOn = '';
                $packingStart = '';
                $importedDate = '';

                if ($item->available_date) {
                    $availableOn = 'Available on ' . $item->available_date->format('d/m/Y');
                }

                if ($item->packing_date) {
                    $packingStart = 'Packing Start ' . $item->packing_date->format('d/m/Y');
                }

                if ($item->imported_date) {
                    $importedDate = $item->imported_date->format('d/m/Y');
                }

                $location = $item->location ?? $item->hatchery?->location;

                return [
                    'id' => $item->id,
                    'hatchery_id' => $item->hatchery_id,
                    'hatchery_name' => $item->hatchery?->hatchery_name ?? '',
                    'supplier_name' => $item->supplier_name ?? '',
                    'category' => [
                        'category_name' => $item->category->category_name ?? '',
                    ],
                    'description' => $item->description ?? '',
                    'location' => $location ? [
                        'longitude' => $location->longitude ?? '',
                        'latitude' => $location->latitude ?? '',
                        'location_name' => $location->location_name ?? '',
                    ] : [],

                    // Status from database column
                    'status' => $status,

                    'available_quantity' => ($item->count ?? 0) . ' Pieces',

                    // Date fields
                    'available_on' => $availableOn,
                    'packing_start' => $packingStart,
                    'imported_date' => $importedDate,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Brood stocks fetched successfully',
                'data' => $data,
            ], 200);

        } catch (\Exception $e) {
            \Log::error($e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Error occurred',
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get available months that have broodstock data (based on imported_date)
     */
    public function getAvailableMonths(Request $request)
    {
        try {
            $query = Broodstock::whereNotNull('imported_date');

            // Only include allowed categories (Vannamei, Tiger)
            $allowedCategories = HatcheryCategory::whereIn('category_name', [
                'Vannamei',
                'Tiger'
            ])->pluck('id');
            $query->whereIn('category_id', $allowedCategories);

            // Optionally filter by category
            if ($request->filled('category')) {
                $categoryValue = $request->category;
                if (is_numeric($categoryValue)) {
                    $query->where('category_id', $categoryValue);
                } else {
                    $categoryByName = HatcheryCategory::where('category_name', $categoryValue)->first();
                    if ($categoryByName) {
                        $query->where('category_id', $categoryByName->id);
                    }
                }
            }

            // Get distinct year-month combinations from imported_date
            $months = $query->selectRaw('YEAR(imported_date) as year, MONTH(imported_date) as month')
                ->groupBy('year', 'month')
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->get();

            $monthNames = [
                1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
                5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
                9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
            ];

            $availableMonths = $months->map(function ($item) use ($monthNames) {
                return [
                    'month' => str_pad($item->month, 2, '0', STR_PAD_LEFT),
                    'year' => (string) $item->year,
                    'month_name' => $monthNames[$item->month] ?? '',
                    'display' => ($monthNames[$item->month] ?? '') . ' ' . $item->year,
                ];
            })->values();

            return response()->json([
                'status' => true,
                'message' => 'Available months fetched successfully',
                'data' => $availableMonths,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error fetching available months: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error fetching available months',
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get default broodstock filter settings
     */
    public function getDefaultFilter()
    {
        try {
            // Get values from app_configs table using AppConfig model
            $defaultCategory = AppConfig::getValue('broodstock_default_category', 'Tiger');
            $defaultMonth = AppConfig::getValue('broodstock_default_month', date('m'));
            $defaultYear = AppConfig::getValue('broodstock_default_year', date('Y'));

            // Convert month number to name
            $monthNames = [
                '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
                '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Aug',
                '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dec',
                '1' => 'Jan', '2' => 'Feb', '3' => 'Mar', '4' => 'Apr',
                '5' => 'May', '6' => 'Jun', '7' => 'Jul', '8' => 'Aug',
                '9' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dec',
            ];

            $monthName = $monthNames[$defaultMonth] ?? 'Jan';

            return response()->json([
                'status' => true,
                'message' => 'Default filter fetched successfully',
                'data' => [
                    'category' => $defaultCategory,
                    'month' => $defaultMonth,
                    'year' => $defaultYear,
                    'month_name' => $monthName,
                    'display_month_year' => $monthName . ' ' . $defaultYear,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error fetching default filter',
                'data' => [
                    'category' => 'Tiger',
                    'month' => date('m'),
                    'year' => date('Y'),
                    'month_name' => date('M'),
                    'display_month_year' => date('M Y'),
                ]
            ], 200);
        }
    }
}
