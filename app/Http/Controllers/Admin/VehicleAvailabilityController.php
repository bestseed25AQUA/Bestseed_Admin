<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hatchery;
use App\Models\HatcheryCategory;
use App\Models\HatcheryLocation;
use App\Models\HatcheryLocationBranch;
use App\Models\Vendor;
use App\Models\VehicleAvailability;
use App\Models\VehicleGallery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use App\Helpers\VideoCompressor;

class VehicleAvailabilityController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:vehicle-availability.view')->only(['index', 'show', 'getHatcheryDetails']);
        $this->middleware('permission:vehicle-availability.create')->only(['create', 'store']);
        $this->middleware('permission:vehicle-availability.update')->only(['edit', 'update']);
        $this->middleware('permission:vehicle-availability.delete')->only(['destroy', 'deleteGalleryItem']);
    }

    public function index()
    {
        $data = VehicleAvailability::with(['hatchery', 'selectedHatchery', 'vendor', 'category', 'location', 'gallery'])
            ->orderByDesc('created_at')
            ->get();
        $locations = HatcheryLocation::whereNull('farmer_id')->orderBy('priority')->pluck('location_name', 'id')->toArray();
        $vendors = Vendor::pluck('name', 'id')->toArray();
        $categories = HatcheryCategory::pluck('category_name', 'id')->toArray();

        // Get hatchery names for hatchery_id display
        $hatcheryNames = Hatchery::excludingSpot()
            ->pluck('hatchery_name', 'id')
            ->toArray();

        return view('admin.vehicle_availability.index', compact('data', 'locations', 'hatcheryNames', 'categories', 'vendors'));
    }

    public function create()
    {
        $hatcheries = Hatchery::excludingSpot()
            ->with(['category:id,category_name', 'location:id,location_name'])
            ->orderBy('hatchery_name')
            ->get(['id', 'hatchery_name', 'category_id', 'vendor_id', 'location_id', 'available_on', 'price', 'broodstock_count', 'description']);
        $locations = HatcheryLocation::whereNull('farmer_id')->orderBy('priority')->get();
        $vendors = Vendor::orderBy('name')->get();
        $categories = HatcheryCategory::orderBy('priority')->get();
        $branches = HatcheryLocationBranch::orderBy('id', 'desc')->get();

        return view('admin.vehicle_availability.create', compact('hatcheries', 'locations', 'vendors', 'categories', 'branches'));
    }

    public function getHatcheryDetails($id)
    {
        try {
            $hatchery = Hatchery::with(['vendor', 'category', 'location'])->findOrFail($id);

            // Get branches for this hatchery's location
            $branches = [];
            if ($hatchery->location_id) {
                $branches = HatcheryLocationBranch::where('location_id', $hatchery->location_id)
                    ->get(['id', 'branch_name']);
            }

            return response()->json([
                'success' => true,
                'vendor_id' => $hatchery->vendor_id,
                'vendor_name' => $hatchery->vendor->name ?? '',
                'category_id' => $hatchery->category_id,
                'category_name' => $hatchery->category->category_name ?? '',
                'location_id' => $hatchery->location_id,
                'location_name' => $hatchery->location->location_name ?? '',
                'price' => $hatchery->price,
                'broodstock_count' => $hatchery->broodstock_count,
                'description' => $hatchery->description,
                'available_on' => $hatchery->available_on,
                'images' => $hatchery->image ?? [],
                'branches' => $branches,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching hatchery details: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Hatchery not found'], 404);
        }
    }

    public function store(Request $request)
    {
        Log::info('Vehicle Availability Store Request', $request->all());

        $request->validate([
            'vehicle_name' => 'required|string|max:255',
            'hatchery_id' => 'nullable|exists:hatcheries,id',
            'vendor_id' => 'required|exists:vendors,id',
            'category_id' => 'nullable|exists:hatchery_categories,id',
            'location_id' => 'required|exists:hatchery_locations,id',
            'branch_id' => 'nullable',
            'price' => 'nullable|numeric',
            'description' => 'nullable|string',
            'location_ids' => 'required|array',
            'location_ids.*' => 'exists:hatchery_locations,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'available_on' => 'nullable|date',
            'available_space' => 'nullable|integer|min:0',
            'gallery_images.*' => 'nullable|file|mimes:jpg,jpeg,png,gif,mp4,mov,avi,webm|max:51200',
        ]);

        try {
            $vehicleAvailability = VehicleAvailability::create([
                'vehicle_name' => $request->vehicle_name,
                'hatchery_id' => $request->hatchery_id,
                'vendor_id' => $request->vendor_id,
                'category_id' => $request->category_id,
                'location_id' => $request->location_id,
                'price' => $request->price,
                'description' => $request->description,
                'location_ids' => $request->location_ids,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'available_on' => $request->available_on,
                'available_space' => $request->available_space,
                'is_active' => $request->is_active ?? true,
            ]);

            Log::info('Vehicle Availability created', ['id' => $vehicleAvailability->id]);

            // Insert branches for this vehicle availability
            if ($request->branch_id && is_array($request->branch_id)) {
                foreach ($request->branch_id as $bid) {
                    DB::table('branch_hatchery')->insert([
                        'location_id' => $request->location_id,
                        'hatchery_id' => $vehicleAvailability->id,
                        'branch_name' => $bid
                    ]);
                }
                Log::info('Branches inserted for Vehicle Availability', ['id' => $vehicleAvailability->id, 'branches' => $request->branch_id]);
            }

            // Handle gallery uploads
            if ($request->hasFile('gallery_images')) {
                $this->uploadGalleryFiles($vehicleAvailability, $request->file('gallery_images'));
            }

            return redirect()->route('vehicle-availability.index')
                ->with('success', 'Vehicle Availability created successfully.');
        } catch (\Exception $e) {
            Log::error('Vehicle Availability creation failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return back()->withInput()->with('error', 'Failed to create Vehicle Availability. Please try again.');
        }
    }

    public function edit($id)
    {
        $vehicleAvailability = VehicleAvailability::with(['hatchery', 'selectedHatchery', 'vendor', 'category', 'location', 'gallery'])->findOrFail($id);
        $hatcheries = Hatchery::excludingSpot()
            ->with(['category:id,category_name', 'location:id,location_name'])
            ->orderBy('hatchery_name')
            ->get(['id', 'hatchery_name', 'category_id', 'vendor_id', 'location_id', 'available_on', 'price', 'broodstock_count', 'description']);
        $locations = HatcheryLocation::whereNull('farmer_id')->orderBy('priority')->get();
        $vendors = Vendor::orderBy('name')->get();
        $categories = HatcheryCategory::orderBy('priority')->get();
        $branches = HatcheryLocationBranch::orderBy('id', 'desc')->get();

        // Get saved branches for this vehicle availability
        $vehicleBranches = DB::table('branch_hatchery')->where('hatchery_id', $id)->pluck('branch_name')->toArray();

        return view('admin.vehicle_availability.edit', compact('vehicleAvailability', 'hatcheries', 'locations', 'vendors', 'categories', 'branches', 'vehicleBranches'));
    }

    public function update(Request $request, $id)
    {
        Log::info('Updating Vehicle Availability', ['request' => $request->all(), 'id' => $id]);

        $request->validate([
            'vehicle_name' => 'required|string|max:255',
            'hatchery_id' => 'nullable|exists:hatcheries,id',
            'vendor_id' => 'required|exists:vendors,id',
            'category_id' => 'nullable|exists:hatchery_categories,id',
            'location_id' => 'required|exists:hatchery_locations,id',
            'branch_id' => 'nullable',
            'price' => 'nullable|numeric',
            'description' => 'nullable|string',
            'location_ids' => 'required|array',
            'location_ids.*' => 'exists:hatchery_locations,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'available_on' => 'nullable|date',
            'available_space' => 'nullable|integer|min:0',
            'gallery_images.*' => 'nullable|file|mimes:jpg,jpeg,png,gif,mp4,mov,avi,webm|max:51200',
        ]);

        try {
            $vehicleAvailability = VehicleAvailability::findOrFail($id);

            $vehicleAvailability->update([
                'vehicle_name' => $request->vehicle_name,
                'hatchery_id' => $request->hatchery_id,
                'vendor_id' => $request->vendor_id,
                'category_id' => $request->category_id,
                'location_id' => $request->location_id,
                'price' => $request->price,
                'description' => $request->description,
                'location_ids' => $request->location_ids,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'available_on' => $request->available_on,
                'available_space' => $request->available_space,
                'is_active' => $request->is_active ?? true,
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
                Log::info('Branches updated for Vehicle Availability', ['id' => $id, 'branches' => $request->branch_id]);
            }

            // Handle new gallery uploads
            if ($request->hasFile('gallery_images')) {
                $this->uploadGalleryFiles($vehicleAvailability, $request->file('gallery_images'));
            }

            Log::info('Vehicle Availability updated successfully', ['id' => $id]);
            return redirect()->route('vehicle-availability.index')
                ->with('success', 'Vehicle Availability updated successfully.');

        } catch (\Exception $e) {
            Log::error('Error updating Vehicle Availability: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Failed to update Vehicle Availability. Please try again.');
        }
    }

    public function destroy($id)
    {
        try {
            $vehicleAvailability = VehicleAvailability::findOrFail($id);

            // Delete all gallery files
            foreach ($vehicleAvailability->gallery as $item) {
                $this->deleteGalleryFile($item);
            }

            // Delete branches
            DB::table('branch_hatchery')->where('hatchery_id', $id)->delete();

            $vehicleAvailability->delete();

            return redirect()->route('vehicle-availability.index')
                ->with('success', 'Vehicle Availability deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Error deleting Vehicle Availability: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete Vehicle Availability.');
        }
    }

    /**
     * Delete a single gallery item
     */
    public function deleteGalleryItem($id)
    {
        try {
            $galleryItem = VehicleGallery::findOrFail($id);
            $this->deleteGalleryFile($galleryItem);
            $galleryItem->delete();

            return response()->json(['success' => true, 'message' => 'Gallery item deleted successfully.']);
        } catch (\Exception $e) {
            Log::error('Error deleting gallery item: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete gallery item.'], 500);
        }
    }

    /**
     * Upload gallery files
     */
    private function uploadGalleryFiles(VehicleAvailability $vehicleAvailability, array $files)
    {
        $uploadPath = public_path('uploads/vehicle_gallery');
        if (!File::exists($uploadPath)) {
            File::makeDirectory($uploadPath, 0755, true);
        }

        $sortOrder = $vehicleAvailability->gallery()->max('sort_order') ?? 0;

        foreach ($files as $file) {
            $sortOrder++;
            $extension = $file->getClientOriginalExtension();
            $filename = 'vehicle_' . $vehicleAvailability->id . '_' . time() . '_' . $sortOrder . '.' . $extension;
            $file->move($uploadPath, $filename);

            $fileType = in_array(strtolower($extension), ['mp4', 'mov', 'avi', 'webm']) ? 'video' : 'image';

            // Compress video if needed (max 1080p)
            $relPath = 'uploads/vehicle_gallery/' . $filename;
            $relPath = VideoCompressor::compress($relPath);

            VehicleGallery::create([
                'vehicle_availability_id' => $vehicleAvailability->id,
                'file_path' => $relPath,
                'file_type' => $fileType,
                'original_name' => $file->getClientOriginalName(),
                'sort_order' => $sortOrder,
            ]);
        }
    }

    /**
     * Delete gallery file from storage
     */
    private function deleteGalleryFile(VehicleGallery $galleryItem)
    {
        $filePath = public_path($galleryItem->file_path);
        if (File::exists($filePath)) {
            File::delete($filePath);
        }
    }
}
