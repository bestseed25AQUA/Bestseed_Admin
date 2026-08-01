<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hatchery;
use App\Models\HatcheryCategory;
use App\Models\HatcheryLocation;
use App\Models\Brand; //for hatchery brand
use App\Models\Broodstock; //hatchery stock
use App\Models\HatcheryLocationBranch; //hatchery branch's locations
use App\Models\MarketPrice;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Helpers\VideoCompressor;


class HatcheryController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:hatcheries.view')->only(['index', 'show', 'getBranches']);
        $this->middleware('permission:hatcheries.create')->only(['create', 'store']);
        $this->middleware('permission:hatcheries.update')->only(['edit', 'update']);
        $this->middleware('permission:hatcheries.delete')->only(['destroy']);
    }

    public function index()
    {
        $data = Hatchery::select(
            'hatcheries.*',
            'hatchery_categories.category_name as cat_name',
            'hatchery_locations.location_name as loc_name'
        )
            ->where(function ($q) {
                $q->whereNull('hatcheries.is_spot')
                    ->orWhere('hatcheries.is_spot', 0);
            })
            ->leftJoin('hatchery_categories', 'hatchery_categories.id', '=', 'hatcheries.category_id')
            ->leftJoin('hatchery_locations', 'hatchery_locations.id', '=', 'hatcheries.location_id')
            ->orderByDesc('hatcheries.created_at')
            ->get();

        //brands
        $brands = Brand::orderBy('id')->get();
        $brand_name_array = []; //default
        foreach ($brands as $brand) {
            $brand_name_array[$brand->id] = $brand->brand_name;
        }

        //brodstock stock with hatcheryid
        $broodstocks = Broodstock::orderBy('id')->get();
        $hatcherystock_array = []; //default
        foreach ($broodstocks as $broodstock) {
            $hatcherystock_array[$broodstock->hatchery_id] = $broodstock->count;
        }

        //create branch id name array from hatchery_location_branches
        $hatchery_location_branches_array = []; //default

        $hatchery_location_branches = DB::table('hatchery_location_branches')->orderBy('id')->get();
        foreach ($hatchery_location_branches as $hatchery_location_branch) {
            $hatchery_location_branches_array[$hatchery_location_branch->id] = $hatchery_location_branch->branch_name;
        }

        // Get all branches grouped by hatchery_id
        $hatchery_branches_map = [];
        $all_hatchery_branches = DB::table('branch_hatchery')->get();
        foreach ($all_hatchery_branches as $hb) {
            if (!isset($hatchery_branches_map[$hb->hatchery_id])) {
                $hatchery_branches_map[$hb->hatchery_id] = [];
            }
            // branch_name column stores the branch ID
            if (isset($hatchery_location_branches_array[$hb->branch_name])) {
                $hatchery_branches_map[$hb->hatchery_id][] = $hatchery_location_branches_array[$hb->branch_name];
            }
        }

        $vendors = Vendor::pluck('name', 'id')->toArray();
        $categories = HatcheryCategory::pluck('category_name', 'id')->toArray();
        $locations = HatcheryLocation::whereNull('farmer_id')->orderBy('priority')->pluck('location_name', 'id')->toArray();
        $fishCategoryMap = HatcheryCategory::where('type', 'fish')
            ->pluck('category_name', 'id')
            ->toArray();

        return view('admin.hatcheries.index', compact(
            'data',
            'vendors',
            'categories',
            'locations',
            'brand_name_array',
            'hatcherystock_array',
            'hatchery_location_branches_array',
            'hatchery_branches_map',
            'fishCategoryMap'
        ));

    }

    public function create()
    {  // dd('here create hatchery');
        $vendors = Vendor::all();
        $shrimpCategories = HatcheryCategory::where('type', 'shrimp')
            ->orderBy('priority')
            ->get();
        $fishCategories = HatcheryCategory::where('type', 'fish')
            ->orderBy('priority')
            ->get();
        $locations = HatcheryLocation::whereNull('farmer_id')->orderBy('priority')->get();
        $branches = HatcheryLocationBranch::orderBy('id', 'desc')->get();

        return view('admin.hatcheries.create', compact('vendors', 'shrimpCategories', 'fishCategories', 'locations', 'branches'));
    }

    public function store(Request $request)
    {
        Log::info('Hatchery Store Request Data', [
            'all' => $request->all(),
            'files' => $request->allFiles()
        ]);

        // dd($request->all());
        $request->validate([
            'hatchery_name' => 'required|string|max:255',
            'vendor_id' => 'required|exists:vendors,id',
            'category_id' => 'nullable|array',
            'category_id.*' => 'exists:hatchery_categories,id',
            'location_id' => 'required|exists:hatchery_locations,id',
            'hatchery_status' => 'nullable|in:1,2,3,4,5',
            'status' => 'nullable|in:1,2,3,4,5',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        // Validate video file sizes (max 25MB per video) to prevent 4K uploads
        // when FFmpeg is not available for server-side compression
        $mediaInput = $request->file('category_media');
        if (is_array($mediaInput)) {
            $videoExtensions = ['mp4', 'mov', 'avi', 'webm', 'wmv', 'mkv', 'flv'];
            foreach ($mediaInput as $type => $categories) {
                foreach ($categories as $catId => $files) {
                    foreach ($files as $file) {
                        if ($file && $file->isValid()) {
                            $ext = strtolower($file->getClientOriginalExtension());
                            if (in_array($ext, $videoExtensions) && $file->getSize() > 25 * 1024 * 1024) {
                                return back()->withErrors([
                                    'category_media' => 'Video file "' . $file->getClientOriginalName() . '" is too large (' . round($file->getSize() / 1024 / 1024, 1) . 'MB). Please compress the video to 1080p resolution or below (max 25MB) before uploading.'
                                ])->withInput();
                            }
                        }
                    }
                }
            }
        }

        try {
            $categoryDetails = $request->category_details ?? [];
            $shrimpCategories = $request->category_id ?? [];
            $fishCategories = $request->fish_category_id ?? [];
            $createdHatcheryIds = [];

            // Ensure upload directories exist
            if (!file_exists(public_path('uploads/category_banners'))) {
                mkdir(public_path('uploads/category_banners'), 0755, true);
            }
            if (!file_exists(public_path('uploads/category_media'))) {
                mkdir(public_path('uploads/category_media'), 0755, true);
            }
            if (!file_exists(public_path('uploads/hatchery_logos'))) {
                mkdir(public_path('uploads/hatchery_logos'), 0755, true);
            }
            if (!file_exists(public_path('uploads/hatchery_reports'))) {
                mkdir(public_path('uploads/hatchery_reports'), 0755, true);
            }

            // Generate unique_id for grouping multiple category records
            $uniqueId = Hatchery::max('unique_id') + 1;
            if (!$uniqueId) {
                $uniqueId = 1;
            }

            // Handle logo upload
            $logoPath = null;
            if ($request->hasFile('logo')) {
                $logoFile = $request->file('logo');
                $logoFilename = time() . '_logo_' . uniqid() . '.' . $logoFile->getClientOriginalExtension();
                $logoFile->move(public_path('uploads/hatchery_logos'), $logoFilename);
                $logoPath = 'uploads/hatchery_logos/' . $logoFilename;
            }

            // Process banner uploads first
            $bannerPaths = [];
            $bannerInput = $request->file('category_banner');

            if (is_array($bannerInput)) {
                Log::info('Category banner files found');

                foreach ($bannerInput as $type => $categories) {
                    foreach ($categories as $catId => $file) {
                        if ($file && $file->isValid()) {
                            Log::info("Uploading banner", [
                                'type' => $type,
                                'cat_id' => $catId,
                                'name' => $file->getClientOriginalName()
                            ]);

                            $filename = time() . '_banner_' . uniqid() . '_' . $file->getClientOriginalName();
                            $file->move(public_path('uploads/category_banners'), $filename);

                            $bannerPaths[$type][$catId] = 'uploads/category_banners/' . $filename;
                        }
                    }
                }
            } else {
                Log::warning('No category_banner file uploaded');
            }

            // Process media uploads first
            $mediaPaths = [];
            $mediaInput = $request->file('category_media');

            if (is_array($mediaInput)) {
                Log::info('Category media files found');

                foreach ($mediaInput as $type => $categories) {
                    foreach ($categories as $catId => $files) {
                        foreach ($files as $file) {
                            if ($file && $file->isValid()) {
                                Log::info("Uploading media", [
                                    'type' => $type,
                                    'cat_id' => $catId,
                                    'name' => $file->getClientOriginalName()
                                ]);

                                $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                                $file->move(public_path('uploads/category_media'), $filename);

                                $mediaPath = 'uploads/category_media/' . $filename;
                                $mediaPath = VideoCompressor::compress($mediaPath);
                                $mediaPaths[$type][$catId][] = $mediaPath;
                            }
                        }
                    }
                }
            } else {
                Log::warning('No category_media file uploaded');
            }

            // Process report uploads
            $reportPaths = [];
            $reportInput = $request->file('category_report');

            if (is_array($reportInput)) {
                Log::info('Category report files found');

                foreach ($reportInput as $type => $categories) {
                    foreach ($categories as $catId => $file) {
                        if ($file && $file->isValid()) {
                            Log::info("Uploading report", [
                                'type' => $type,
                                'cat_id' => $catId,
                                'name' => $file->getClientOriginalName()
                            ]);

                            $filename = time() . '_report_' . uniqid() . '_' . $file->getClientOriginalName();
                            $file->move(public_path('uploads/hatchery_reports'), $filename);

                            $reportPaths[$type][$catId] = 'uploads/hatchery_reports/' . $filename;
                        }
                    }
                }
            } else {
                Log::warning('No category_report file uploaded');
            }

            // ⭐ CREATE SEPARATE RECORD FOR EACH SHRIMP CATEGORY ⭐
            foreach ($shrimpCategories as $catId) {
                $details = $categoryDetails['shrimp'][$catId] ?? [];
                $banner = $bannerPaths['shrimp'][$catId] ?? null;
                $media = $mediaPaths['shrimp'][$catId] ?? [];
                $report = $reportPaths['shrimp'][$catId] ?? null;
                Log::info('Creating Shrimp Hatchery Record', [
                    'cat_id' => $catId,
                    'banner' => $banner,
                    'media' => $media,
                    'report' => $report
                ]);

                $hatchery = Hatchery::create([
                    'hatchery_name' => $request->hatchery_name,
                    'logo' => $logoPath,
                    'vendor_id' => $request->vendor_id,
                    'category_id' => $catId,
                    'brand_id' => null,
                    'location_id' => $request->location_id,
                    'lat' => $request->lat,
                    'lng' => $request->lng,
                    'available_on' => $details['available_on'] ?? null,
                    'hatchery_status' => $details['status'] ?? null,
                    'status' => $request->status,
                    'is_spot' => 0,
                    'price' => $details['price'] ?? null,
                    'broodstock_count' => $details['broodstock_count'] ?? null,
                    'description' => $details['description'] ?? null,
                    'banner_image' => $banner,
                    'image' => json_encode($media),
                    'report' => $report,
                    'unique_id' => $uniqueId,
                    'call_number' => $request->call_number,
                    'whatsapp_number' => $request->whatsapp_number,
                    'facebook_link' => $request->facebook_link,
                ]);

                $createdHatcheryIds[] = $hatchery->id;

                // Insert into hatchery_category_map
                DB::table('hatchery_category_map')->insert([
                    'hatchery_id' => $hatchery->id,
                    'category_id' => $catId,
                ]);

                Log::info('Hatchery Created', ['id' => $hatchery->id]);
                // Insert branches for this hatchery
                if ($request->branch_id && is_array($request->branch_id)) {
                    foreach ($request->branch_id as $bid) {
                        DB::table('branch_hatchery')->insert([
                            'location_id' => $request->location_id,
                            'hatchery_id' => $hatchery->id,
                            'branch_name' => $bid
                        ]);
                    }
                }
            }

            // ⭐ CREATE SEPARATE RECORD FOR EACH FISH CATEGORY ⭐
            foreach ($fishCategories as $brandId) {
                $details = $categoryDetails['fish'][$brandId] ?? [];
                $banner = $bannerPaths['fish'][$brandId] ?? null;
                $media = $mediaPaths['fish'][$brandId] ?? [];
                $report = $reportPaths['fish'][$brandId] ?? null;

                Log::info('Creating Fish Hatchery Record', [
                    'brand_id' => $brandId,
                    'banner' => $banner,
                    'media' => $media,
                    'report' => $report
                ]);

                $hatchery = Hatchery::create([
                    'hatchery_name' => $request->hatchery_name,
                    'logo' => $logoPath,
                    'vendor_id' => $request->vendor_id,
                    'category_id' => null,
                    'brand_id' => $brandId,
                    'location_id' => $request->location_id,
                    'lat' => $request->lat,
                    'lng' => $request->lng,
                    'available_on' => $details['available_on'] ?? null,
                    'hatchery_status' => $details['status'] ?? null,
                    'status' => $request->status,
                    'is_spot' => 0,
                    'price' => $details['price'] ?? null,
                    'broodstock_count' => $details['broodstock_count'] ?? null,
                    'description' => $details['description'] ?? null,
                    'banner_image' => $banner,
                    'image' => json_encode($media),
                    'report' => $report,
                    'unique_id' => $uniqueId,
                    'call_number' => $request->call_number,
                    'whatsapp_number' => $request->whatsapp_number,
                    'facebook_link' => $request->facebook_link,
                ]);

                $createdHatcheryIds[] = $hatchery->id;
                Log::info('Hatchery Created', ['id' => $hatchery->id]);

                // Insert branches for this hatchery
                if ($request->branch_id && is_array($request->branch_id)) {
                    foreach ($request->branch_id as $bid) {
                        DB::table('branch_hatchery')->insert([
                            'location_id' => $request->location_id,
                            'hatchery_id' => $hatchery->id,
                            'branch_name' => $bid
                        ]);
                    }
                }
            }

            $count = count($createdHatcheryIds);
            return redirect()->route('hatcheries.index')
                ->with('success', "{$count} Hatchery record(s) created successfully.");

        } catch (\Exception $e) {
            Log::error('Hatchery Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withInput()->with('error', $e->getMessage());
        }

    }
    public function edit($id)
    {
        $hatchery = Hatchery::findOrFail($id);
        $vendors = Vendor::all();
        $shrimpCategories = HatcheryCategory::where('type', 'shrimp')
            ->orderBy('priority')
            ->get();
        $fishCategories = HatcheryCategory::where('type', 'fish')
            ->orderBy('priority')
            ->get();
        $locations = HatcheryLocation::whereNull('farmer_id')->orderBy('priority')->get();
        $branches = HatcheryLocationBranch::orderBy('id', 'desc')->get();

        // Get saved branches for this hatchery
        $hatcheryBranches = DB::table('branch_hatchery')->where('hatchery_id', $id)->get();

        // Get raw images (without accessor transformation) for JS pre-fill
        $rawImages = [];
        $rawImageValue = $hatchery->getRawOriginal('image');
        if (!empty($rawImageValue)) {
            $decoded = json_decode($rawImageValue, true);
            if (is_array($decoded)) {
                $rawImages = array_values(array_filter($decoded, fn($v) => is_string($v) && trim($v) !== ''));
            }
        }

        return view('admin.hatcheries.edit', compact(
            'hatchery', 'vendors', 'shrimpCategories', 'fishCategories',
            'locations', 'branches', 'hatcheryBranches', 'rawImages'
        ));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'hatchery_name' => 'required|string|max:255',
            'vendor_id' => 'required|exists:vendors,id',
            'location_id' => 'required|exists:hatchery_locations,id',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        try {
            $hatchery = Hatchery::findOrFail($id);
            $uniqueId = $hatchery->unique_id;

            // Active/Inactive — applied ONLY to the selected category record.
            // Inactive hides it from customers (see Hatchery::scopeActive used by the user APIs).
            // (Note: No longer updating the entire unique_id group, as per category-specific requirement).

            // Ensure upload directories exist
            foreach (['category_banners', 'category_media', 'hatchery_logos', 'hatchery_reports'] as $dir) {
                if (!file_exists(public_path("uploads/{$dir}"))) {
                    mkdir(public_path("uploads/{$dir}"), 0755, true);
                }
            }

            // Handle logo upload (shared across all records)
            $logoPath = $hatchery->logo;
            if ($request->has('remove_logo') && $request->remove_logo) {
                $logoPath = null;
            }
            if ($request->hasFile('logo')) {
                $logoFile = $request->file('logo');
                $logoFilename = time() . '_logo_' . uniqid() . '.' . $logoFile->getClientOriginalExtension();
                $logoFile->move(public_path('uploads/hatchery_logos'), $logoFilename);
                $logoPath = 'uploads/hatchery_logos/' . $logoFilename;
            }

            $categoryDetails = $request->category_details ?? [];
            $shrimpCategories = $request->category_id ?? [];
            $fishCategoryIds = $request->fish_category_id ?? [];

            // Process media uploads per category
            $mediaPaths = [];
            $mediaInput = $request->file('category_media');
            if (is_array($mediaInput)) {
                foreach ($mediaInput as $type => $categories) {
                    foreach ($categories as $catId => $files) {
                        foreach ($files as $file) {
                            if ($file && $file->isValid()) {
                                $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                                $file->move(public_path('uploads/category_media'), $filename);
                                $mediaPath = 'uploads/category_media/' . $filename;
                                $mediaPath = VideoCompressor::compress($mediaPath);
                                $mediaPaths[$type][$catId][] = $mediaPath;
                            }
                        }
                    }
                }
            }

            // Process report uploads per category
            $reportPaths = [];
            $reportInput = $request->file('category_report');
            if (is_array($reportInput)) {
                foreach ($reportInput as $type => $categories) {
                    foreach ($categories as $catId => $file) {
                        if ($file && $file->isValid()) {
                            $filename = time() . '_report_' . uniqid() . '_' . $file->getClientOriginalName();
                            $file->move(public_path('uploads/hatchery_reports'), $filename);
                            $reportPaths[$type][$catId] = 'uploads/hatchery_reports/' . $filename;
                        }
                    }
                }
            }

            // Handle removed images for existing record
            $removedImages = [];
            if ($request->filled('removed_images')) {
                $removedImages = json_decode($request->removed_images, true) ?? [];
            }

            $existingUpdated = false;
            $createdCount = 0;

            // Determine existing record's type to process it FIRST
            // This ensures the existing record keeps its category type and ID
            $existingIsShrimp = !empty($hatchery->category_id);

            // Build ordered list: process existing type first, then the other type
            $categoriesToProcess = [];
            if ($existingIsShrimp) {
                // Existing is shrimp -> process shrimp first, then fish
                foreach ($shrimpCategories as $catId) {
                    $categoriesToProcess[] = ['type' => 'shrimp', 'catId' => $catId];
                }
                foreach ($fishCategoryIds as $brandId) {
                    $categoriesToProcess[] = ['type' => 'fish', 'catId' => $brandId];
                }
            } else {
                // Existing is fish -> process fish first, then shrimp
                foreach ($fishCategoryIds as $brandId) {
                    $categoriesToProcess[] = ['type' => 'fish', 'catId' => $brandId];
                }
                foreach ($shrimpCategories as $catId) {
                    $categoriesToProcess[] = ['type' => 'shrimp', 'catId' => $catId];
                }
            }

            foreach ($categoriesToProcess as $cat) {
                $type = $cat['type'];
                $catId = $cat['catId'];
                $details = $categoryDetails[$type][$catId] ?? [];
                $media = $mediaPaths[$type][$catId] ?? [];
                $report = $reportPaths[$type][$catId] ?? null;

                if (!$existingUpdated) {
                    // Update existing record - merge existing images (minus removed) with new
                    $existingImages = [];
                    $rawImageValue = $hatchery->getRawOriginal('image');
                    if (!empty($rawImageValue)) {
                        $decoded = json_decode($rawImageValue, true);
                        $existingImages = is_array($decoded) ? $decoded : [];
                    }
                    $existingImages = array_filter($existingImages, fn($img) => !in_array($img, $removedImages));
                    $allMedia = array_values(array_merge(array_values($existingImages), $media));

                    $hatchery->update([
                        'hatchery_name' => $request->hatchery_name,
                        'logo' => $logoPath,
                        'vendor_id' => $request->vendor_id,
                        'category_id' => $type === 'shrimp' ? $catId : null,
                        'brand_id' => $type === 'fish' ? $catId : null,
                        'location_id' => $request->location_id,
                        'lat' => $request->filled('lat') ? $request->lat : $hatchery->lat,
                        'lng' => $request->filled('lng') ? $request->lng : $hatchery->lng,
                        'hatchery_status' => $details['status'] ?? null,
                        'status' => $request->status,
                        'is_active' => $request->boolean('is_active', $hatchery->is_active),
                        'is_spot' => 0,
                        'price' => $details['price'] ?? null,
                        'broodstock_count' => $details['broodstock_count'] ?? null,
                        'description' => $details['description'] ?? null,
                        'available_on' => $details['available_on'] ?? null,
                        'image' => json_encode($allMedia),
                        'report' => $report ?? $hatchery->report,
                        'unique_id' => $uniqueId,
                        'call_number' => $request->call_number,
                        'whatsapp_number' => $request->whatsapp_number,
                        'facebook_link' => $request->facebook_link,
                    ]);

                    // Update branches for existing record
                    DB::table('branch_hatchery')->where('hatchery_id', $id)->delete();
                    if ($request->branch_id && is_array($request->branch_id)) {
                        foreach ($request->branch_id as $bid) {
                            DB::table('branch_hatchery')->insert([
                                'location_id' => $request->location_id,
                                'hatchery_id' => $id,
                                'branch_name' => $bid
                            ]);
                        }
                    }

                    $existingUpdated = true;
                } else {
                    // Create new record with same unique_id
                    $newHatchery = Hatchery::create([
                        'hatchery_name' => $request->hatchery_name,
                        'logo' => $logoPath,
                        'vendor_id' => $request->vendor_id,
                        'category_id' => $type === 'shrimp' ? $catId : null,
                        'brand_id' => $type === 'fish' ? $catId : null,
                        'location_id' => $request->location_id,
                        'lat' => $request->lat,
                        'lng' => $request->lng,
                        'hatchery_status' => $details['status'] ?? null,
                        'status' => $request->status,
                        'is_spot' => 0,
                        'price' => $details['price'] ?? null,
                        'broodstock_count' => $details['broodstock_count'] ?? null,
                        'description' => $details['description'] ?? null,
                        'available_on' => $details['available_on'] ?? null,
                        'image' => json_encode($media),
                        'report' => $report,
                        'unique_id' => $uniqueId,
                        'call_number' => $request->call_number,
                        'whatsapp_number' => $request->whatsapp_number,
                        'facebook_link' => $request->facebook_link,
                    ]);

                    // Insert branches for new record
                    if ($request->branch_id && is_array($request->branch_id)) {
                        foreach ($request->branch_id as $bid) {
                            DB::table('branch_hatchery')->insert([
                                'location_id' => $request->location_id,
                                'hatchery_id' => $newHatchery->id,
                                'branch_name' => $bid
                            ]);
                        }
                    }

                    $createdCount++;
                }
            }

            // Sync overall status across all records with the same unique_id
            if ($request->status && $uniqueId) {
                Hatchery::where('unique_id', $uniqueId)
                    ->update(['status' => $request->status]);
            }

            $msg = 'Hatchery updated successfully.';
            if ($createdCount > 0) {
                $msg .= " {$createdCount} new category record(s) created.";
            }

            return redirect()->route('hatcheries.index')->with('success', $msg);
        } catch (\Exception $e) {
            Log::error('Error updating hatchery: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return back()->withInput()
                ->with('error', 'Failed to update hatchery. Please try again.');
        }
    }

    public function getBranches($locationId)
    {
        $branches = HatcheryLocationBranch::where('location_id', $locationId)->get();

        return response()->json($branches);
    }





    public function destroy($id)
    {
        try {
            $hatchery = Hatchery::findOrFail($id);
            $hatchery->delete();

            return redirect()->route('hatcheries.index')->with('success', 'Hatchery deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Error deleting hatchery: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete hatchery.');
        }
    }

    /**
     * Toggle a hatchery category between Active and Inactive (quick action on the list).
     * Inactive hides it from customers.
     */
    public function toggleActive($id)
    {
        try {
            $hatchery = Hatchery::findOrFail($id);
            $newStatus = !$hatchery->is_active;
            $hatchery->update(['is_active' => $newStatus]);

            return back()->with('success', 'Hatchery category marked as ' . ($newStatus ? 'Active' : 'Inactive') . '.');
        } catch (\Exception $e) {
            Log::error('Error toggling hatchery active: ' . $e->getMessage());
            return back()->with('error', 'Failed to update status.');
        }
    }
}
