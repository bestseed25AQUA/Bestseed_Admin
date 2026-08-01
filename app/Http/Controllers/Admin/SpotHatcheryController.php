<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hatchery;
use App\Models\HatcheryCategory;
use App\Models\HatcheryLocation;
use App\Models\HatcheryLocationBranch;
use App\Models\Brand;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Helpers\VideoCompressor;

class SpotHatcheryController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:spot-hatcheries.view')->only(['index', 'show', 'getHatcheryDetails']);
        $this->middleware('permission:spot-hatcheries.create')->only(['create', 'store']);
        $this->middleware('permission:spot-hatcheries.update')->only(['edit', 'update']);
        $this->middleware('permission:spot-hatcheries.delete')->only(['destroy']);
    }

    public function index()
    {
        $data = Hatchery::where('is_spot', 1)->orderByDesc('created_at')->get();
        $vendors = Vendor::pluck('name', 'id')->toArray();
        $categories = HatcheryCategory::pluck('category_name', 'id')->toArray();
        $locations = HatcheryLocation::whereNull('farmer_id')->orderBy('priority')->pluck('location_name', 'id')->toArray();

        // Get hatchery names for selected_hatchery_id display
        $hatcheryNames = Hatchery::excludingSpot()
            ->pluck('hatchery_name', 'id')
            ->toArray();

        return view('admin.spot_hatcheries.index', compact('data', 'vendors', 'categories', 'locations', 'hatcheryNames'));
    }

    public function create()
    {
        $vendors = Vendor::all();
        $categories = HatcheryCategory::orderBy('priority')->get();
        $locations = HatcheryLocation::whereNull('farmer_id')->orderBy('priority')->get();
        $brands = Brand::orderBy('id')->get();
        $branches = HatcheryLocationBranch::orderBy('id', 'desc')->get();

        // Get all non-spot hatcheries for the dropdown
        $hatcheries = Hatchery::excludingSpot()
            ->orderBy('hatchery_name')
            ->get(['id', 'hatchery_name', 'category_id', 'brand_id', 'vendor_id', 'location_id', 'available_on', 'price', 'broodstock_count', 'description']);

        return view('admin.spot_hatcheries.create', compact('vendors', 'categories', 'locations', 'brands', 'branches', 'hatcheries'));
    }

    /**
     * Get hatchery details for auto-fill in spot hatchery form
     */
    public function getHatcheryDetails($id)
    {
        try {
            $hatchery = Hatchery::with(['vendor'])->findOrFail($id);

            // Get branches for the hatchery's location
            $branches = [];
            if ($hatchery->location_id) {
                $branches = HatcheryLocationBranch::where('location_id', $hatchery->location_id)
                    ->get(['id', 'branch_name']);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $hatchery->id,
                    'hatchery_name' => $hatchery->hatchery_name,
                    'category_id' => $hatchery->category_id,
                    'brand_id' => $hatchery->brand_id,
                    'vendor_id' => $hatchery->vendor_id,
                    'location_id' => $hatchery->location_id,
                    'branch_id' => $hatchery->branch_id ?? null,
                    'available_on' => $hatchery->available_on,
                    'price' => $hatchery->price,
                    'broodstock_count' => $hatchery->broodstock_count,
                    'description' => $hatchery->description,
                    'branches' => $branches,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching hatchery details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch hatchery details'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        Log::info('Creating new Spot Hatchery', ['request' => $request->all()]);
        Log::info('Has file image?', ['hasFile' => $request->hasFile('image'), 'allFiles' => $request->allFiles()]);

        $request->validate([
            'hatchery_name' => 'required|string|max:255',
            'vendor_id' => 'required|exists:vendors,id',
            'category_id' => 'nullable|exists:hatchery_categories,id',
            'location_id' => 'required|exists:hatchery_locations,id',
            'branch_id' => 'nullable',
            'available_on' => 'nullable|date',
            'price' => 'nullable|numeric',
            'broodstock_count' => 'nullable|integer',
            'no_of_pieces' => 'nullable|integer',
            'description' => 'nullable|string',
            'selected_hatchery_id' => 'nullable|exists:hatcheries,id',
            'is_spot' => 'nullable',
            'image' => 'nullable|array',
            'image.*' => 'nullable|file|mimes:jpg,jpeg,png,mp4,mov,avi,webm|max:51200',
        ]);

        Log::info('Validation passed for Spot Hatchery creation');

        try {
            // Create upload directory if it doesn't exist
            $uploadDir = public_path('uploads/spot_hatcheries');
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $imagePaths = [];
            if ($request->hasFile('image')) {
                Log::info('Processing uploaded files', ['count' => count($request->file('image'))]);
                foreach ($request->file('image') as $file) {
                    $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                    $file->move($uploadDir, $filename);
                    $mediaPath = 'uploads/spot_hatcheries/' . $filename;
                    $mediaPath = VideoCompressor::compress($mediaPath);
                    $imagePaths[] = $mediaPath;
                }
            } else {
                Log::info('No files detected in request');
            }
            Log::info('Uploaded images/videos for Spot Hatchery', ['images' => $imagePaths]);

            $hatchery = Hatchery::create([
                'hatchery_name' => $request->hatchery_name,
                'vendor_id' => $request->vendor_id,
                'category_id' => $request->category_id,
                'location_id' => $request->location_id,
                'available_on' => $request->available_on,
                'price' => $request->price,
                'broodstock_count' => $request->broodstock_count,
                'no_of_pieces' => $request->no_of_pieces,
                'description' => $request->description,
                'selected_hatchery_id' => $request->selected_hatchery_id,
                'image' => json_encode($imagePaths, JSON_UNESCAPED_SLASHES),
                'is_spot' => 1,
                'status' => $request->status ?? 'active',
            ]);

            Log::info('Spot Hatchery created', ['id' => $hatchery->id]);

            // Insert branches for this hatchery
            if ($request->branch_id && is_array($request->branch_id)) {
                foreach ($request->branch_id as $bid) {
                    DB::table('branch_hatchery')->insert([
                        'location_id' => $request->location_id,
                        'hatchery_id' => $hatchery->id,
                        'branch_name' => $bid
                    ]);
                }
                Log::info('Branches inserted for Spot Hatchery', ['hatchery_id' => $hatchery->id, 'branches' => $request->branch_id]);
            }

            Log::info('Spot Hatchery created successfully');
            return redirect()->route('spot-hatcheries.index')
                ->with('success', 'Spot Hatchery created successfully.');
        } catch (\Exception $e) {
            Log::error('Error creating Spot Hatchery: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Failed to create Spot Hatchery. Please try again.');
        }
    }

    public function edit($id)
    {
        $hatchery = Hatchery::findOrFail($id);
        $vendors = Vendor::all();
        $categories = HatcheryCategory::orderBy('priority')->get();
        $locations = HatcheryLocation::whereNull('farmer_id')->orderBy('priority')->get();
        $brands = Brand::orderBy('id')->get();
        $branches = HatcheryLocationBranch::orderBy('id', 'desc')->get();

        // Get all non-spot hatcheries for the dropdown
        $hatcheries = Hatchery::excludingSpot()
            ->orderBy('hatchery_name')
            ->get(['id', 'hatchery_name', 'category_id', 'brand_id', 'vendor_id', 'location_id', 'available_on', 'price', 'broodstock_count', 'description']);

        // Get saved branches for this hatchery
        $hatcheryBranches = DB::table('branch_hatchery')->where('hatchery_id', $id)->pluck('branch_name')->toArray();

        return view('admin.spot_hatcheries.edit', compact('hatchery', 'vendors', 'categories', 'locations', 'brands', 'branches', 'hatcheries', 'hatcheryBranches'));
    }

    public function update(Request $request, $id)
    {
        Log::info('Updating Spot Hatchery', ['request' => $request->all(), 'id' => $id]);
        Log::info('Has file image?', ['hasFile' => $request->hasFile('image'), 'allFiles' => $request->allFiles()]);

        $request->validate([
            'hatchery_name' => 'required|string|max:255',
            'vendor_id' => 'required|exists:vendors,id',
            'category_id' => 'nullable|exists:hatchery_categories,id',
            'location_id' => 'required|exists:hatchery_locations,id',
            'branch_id' => 'nullable',
            'available_on' => 'nullable|date',
            'price' => 'nullable|numeric',
            'broodstock_count' => 'nullable|integer',
            'no_of_pieces' => 'nullable|integer',
            'description' => 'nullable|string',
            'selected_hatchery_id' => 'nullable|exists:hatcheries,id',
            'is_spot' => 'nullable',
            'image' => 'nullable|array',
            'image.*' => 'nullable|file|mimes:jpg,jpeg,png,mp4,mov,avi,webm|max:51200',
        ]);

        Log::info('Validation passed for Spot Hatchery update');
        try {
            $hatchery = Hatchery::findOrFail($id);

            // Create upload directory if it doesn't exist
            $uploadDir = public_path('uploads/spot_hatcheries');
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Get raw image value from database (not the accessor)
            $rawImage = $hatchery->getRawOriginal('image');
            Log::info('Raw image from DB', ['rawImage' => $rawImage]);

            // Decode existing images
            $imagePaths = [];
            if (!empty($rawImage)) {
                $decoded = json_decode($rawImage, true);
                if (is_array($decoded)) {
                    $imagePaths = $decoded;
                }
            }
            Log::info('Existing images after decode', ['imagePaths' => $imagePaths]);

            // Handle removed images
            if ($request->filled('removed_images')) {
                $removedImages = json_decode($request->removed_images, true) ?? [];
                Log::info('Removing images', ['removedImages' => $removedImages]);

                // Remove both full URLs and relative paths
                $imagePaths = array_values(array_filter($imagePaths, function ($img) use ($removedImages) {
                    // Check if the image path or its full URL is in the removed list
                    foreach ($removedImages as $removed) {
                        if ($img === $removed || str_contains($removed, $img)) {
                            return false;
                        }
                    }
                    return true;
                }));
                Log::info('Images after removal', ['imagePaths' => $imagePaths]);
            }

            // Upload new images/videos
            if ($request->hasFile('image')) {
                Log::info('Processing uploaded files', ['count' => count($request->file('image'))]);
                foreach ($request->file('image') as $file) {
                    $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                    $file->move($uploadDir, $filename);
                    $mediaPath = 'uploads/spot_hatcheries/' . $filename;
                    $mediaPath = VideoCompressor::compress($mediaPath);
                    $imagePaths[] = $mediaPath;
                }
            }
            Log::info('Final images for Spot Hatchery', ['images' => $imagePaths]);

            // Update hatchery
            $hatchery->update([
                'hatchery_name' => $request->hatchery_name,
                'vendor_id' => $request->vendor_id,
                'category_id' => $request->category_id,
                'location_id' => $request->location_id,
                'available_on' => $request->available_on,
                'price' => $request->price,
                'broodstock_count' => $request->broodstock_count,
                'no_of_pieces' => $request->no_of_pieces,
                'description' => $request->description,
                'selected_hatchery_id' => $request->selected_hatchery_id,
                'image' => json_encode($imagePaths, JSON_UNESCAPED_SLASHES),
                'is_spot' => 1,
                'status' => $request->status ?? 'active',
            ]);

            // Update branches - delete old and insert new
            DB::table('branch_hatchery')->where('hatchery_id', $id)->delete();

            if ($request->branch_id && is_array($request->branch_id)) {
                foreach ($request->branch_id as $bid) {
                    DB::table('branch_hatchery')->insert([
                        'location_id' => $request->location_id,
                        'hatchery_id' => $id,
                        'branch_name' => $bid
                    ]);
                }
                Log::info('Branches updated for Spot Hatchery', ['hatchery_id' => $id, 'branches' => $request->branch_id]);
            }

            Log::info('Spot Hatchery updated successfully', ['id' => $id]);
            return redirect()->route('spot-hatcheries.index')
                ->with('success', 'Spot Hatchery updated successfully.');

        } catch (\Exception $e) {
            Log::error('Error updating Spot Hatchery: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Failed to update Spot Hatchery. Please try again.');
        }
    }


    public function destroy($id)
    {
        try {
            $hatchery = Hatchery::findOrFail($id);
            $hatchery->delete();

            return redirect()->route('spot-hatcheries.index')
                ->with('success', 'Spot Hatchery deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Error deleting Spot Hatchery: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete Spot Hatchery.');
        }
    }
}
