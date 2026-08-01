<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Broodstock;
use App\Models\HatcheryCategory;
use App\Models\HatcheryLocation;
use App\Models\Hatchery;



// use Illuminate\Support\Facades\Storage;

class BroadStockController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:broad-stocks.view')->only(['index', 'show']);
        $this->middleware('permission:broad-stocks.create')->only(['create', 'store']);
        $this->middleware('permission:broad-stocks.update')->only(['edit', 'update']);
        $this->middleware('permission:broad-stocks.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $categoryId = $request->query('category');

        // Get only Vannamei and Tiger categories for tabs (Vannamei first)
        $categories = HatcheryCategory::whereIn('category_name', ['Vannamei', 'Tiger'])
            ->orderByRaw("FIELD(category_name, 'Vannamei', 'Tiger')")
            ->get();

        $query = Broodstock::with(['hatchery', 'category', 'location'])->latest();

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        } elseif ($categories->isNotEmpty()) {
            // Default to first category
            $categoryId = $categories->first()->id;
            $query->where('category_id', $categoryId);
        }

        $broadStocks = $query->get();

        return view('admin.broad-stocks.index', compact('broadStocks', 'categories', 'categoryId'));
    }

    public function create()
    {

        $data = Hatchery::excludingSpot()->with(['category:id,category_name', 'location:id,location_name'])->orderByDesc('created_at')->get();
        // $categories = HatcheryCategory::all();
        $categories = HatcheryCategory::whereIn('category_name', [
            'Vannamei',
            'Tiger'
        ])->get();
        $locations = HatcheryLocation::whereNull('farmer_id')->orderBy('priority')->get();
        $supplierCategories = HatcheryCategory::all();
        return view('admin.broad-stocks.create', compact('categories', 'locations','data', 'supplierCategories'));
    }

   public function store(Request $request)
{
   // dd($request->all());
    // Validate input
    $request->validate([
        'hatchery_id' => 'required|exists:hatcheries,id',
        'category_id' => 'required|exists:hatchery_categories,id',
        'location_id' => 'required|exists:hatchery_locations,id',
        'count' => 'required|integer|min:1',
        'available_date' => 'nullable|date',
        // 'seed_image' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
    ]);

    // Create record
    Broodstock::create([
        'hatchery_id' => $request->hatchery_id,
        'category_id' => $request->category_id,
        'location_id' => $request->location_id,
        'supplier_name' => $request->supplier_name,
        'imported_date' => $request->imported_date,
        'count' => $request->count,
        'available_date' => $request->available_date,
        'description' => $request->description,
        'status' => $request->status,
        // 'seed_image_path' => $imagePath,
        // 'seed_image_type' => $imageType,
        'is_active' => true,
    ]);

    return redirect()->route('broad-stocks.index')->with('success', 'Broad stock created successfully.');
}



    public function edit(Broodstock $broadStock)
    {

        $data = Hatchery::excludingSpot()->with(['category:id,category_name', 'location:id,location_name'])->orderByDesc('created_at')->get();
        // $categories = HatcheryCategory::all();
        $categories = HatcheryCategory::whereIn('category_name', [
            'Vannamei',
            'Tiger'
        ])->get();
        $locations = HatcheryLocation::whereNull('farmer_id')->orderBy('priority')->get();
        $supplierCategories = HatcheryCategory::all();
        return view('admin.broad-stocks.edit', compact('data','broadStock', 'categories', 'locations', 'supplierCategories'));
    }

    // public function update(Request $request, Broodstock $broadStock)
    // {
    //     $request->validate([
    //         'hatchery_id' => 'required|exists:hatcheries,id',
    //     'category_id' => 'required|exists:hatchery_categories,id',
    //     'location_id' => 'required|exists:hatchery_locations,id',
    //         'count' => 'required|integer|min:1',
    //         'available_date' => 'required|date',
    //         'packing_date' => 'required|date',
    //         'seed_image' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
    //     ]);

    //     if ($request->hasFile('seed_image')) {
    //         if ($broadStock->seed_image_path) {
    //             Storage::disk('public')->delete($broadStock->seed_image_path);
    //         }
    //         $image = $request->file('seed_image');
    //         $broadStock->seed_image_path = $image->store('seeds', 'public');
    //         $broadStock->seed_image_type = $image->extension();
    //     }

    //     $broadStock->update([
    //         'hatchery_name' => $request->hatchery_name,
    //         'category_id' => $request->category_id,
    //         'location_id' => $request->location_id,
    //         'count' => $request->count,
    //         'available_date' => $request->available_date,
    //         'packing_date' => $request->packing_date,
    //     ]);

    //     return redirect()->route('broad-stocks.index')->with('success', 'Broad stock updated successfully.');
    // }
    public function update(Request $request, Broodstock $broadStock)
{
    // Validate input
    $request->validate([
        'hatchery_id' => 'required|exists:hatcheries,id',
        'category_id' => 'required|exists:hatchery_categories,id',
        'location_id' => 'required|exists:hatchery_locations,id',
        'count' => 'required|integer|min:1',
        'available_date' => 'nullable|date',
        // 'seed_image' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
    ]);

    // Update record
    $broadStock->update([
        'hatchery_id' => $request->hatchery_id,
         'hatchery_name' => \App\Models\Hatchery::find($request->hatchery_id)->hatchery_name,
        'category_id' => $request->category_id,
        'location_id' => $request->location_id,
        'supplier_name' => $request->supplier_name,
        'imported_date' => $request->imported_date,
        'count' => $request->count,
        'available_date' => $request->available_date,
        'description' => $request->description,
        'status' => $request->status,
        // 'seed_image_path' => $imagePath,
        // 'seed_image_type' => $imageType,
    ]);

    return redirect()->route('broad-stocks.index')->with('success', 'Broad stock updated successfully.');
}


    public function destroy(Broodstock $broadStock)
    {
        // if ($broadStock->seed_image_path) {
        //     Storage::disk('public')->delete($broadStock->seed_image_path);
        // }

        $broadStock->delete();

        return redirect()->route('broad-stocks.index')->with('success', 'Broad stock deleted successfully.');
    }
}

