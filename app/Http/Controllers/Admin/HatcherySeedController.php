<?php



namespace App\Http\Controllers\Admin;
use App\Models\HatcherySeed;
use App\Models\HatcheryPost;
use App\Http\Controllers\Controller;
use App\Models\HatcheryCategory;
use App\Models\HatcheryLocation;
use App\Models\Hatchery;
use App\Models\BroadStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class HatcherySeedController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:hatchery-seeds.view')->only(['index', 'show']);
        $this->middleware('permission:hatchery-seeds.create')->only(['create', 'store']);
        $this->middleware('permission:hatchery-seeds.update')->only(['edit', 'update']);
        $this->middleware('permission:hatchery-seeds.delete')->only(['destroy']);
    }

    public function index()
    {
          $seeds = HatcherySeed::with(['hatchery', 'category', 'location'])
                    ->latest()
                    ->get();
        // $seeds = HatcherySeed::latest()->paginate(20);
        return view('admin.hatchery-seeds.index', compact('seeds'));
    }

    public function create()
    {

        $data = Hatchery::excludingSpot()->with(['category:id,category_name', 'location:id,location_name'])->orderByDesc('created_at')->get();
        $categories = HatcheryCategory::all();
        $locations = HatcheryLocation::whereNull('farmer_id')->orderBy('priority')->get();
        return view('admin.hatchery-seeds.create', compact('categories', 'locations','data'));

    }

public function store(Request $request)
{
    // dd( $request->all());
    $validated = $request->validate([
        'seed_brand' => 'nullable|string|max:255',
        'hatchery_id' => 'required|exists:hatcheries,id',
        'category_id' => 'required|exists:hatchery_categories,id',
        'location_id' => 'required|exists:hatchery_locations,id',
        'description' => 'nullable|string',
        'broad_stock' => 'nullable|integer',
        'count' => 'nullable|integer',
        'stock_available_date' => 'nullable|date',
        'packing_start_date' => 'nullable|date',
        'expire_date' => 'nullable|date',
        'price_per_piece' => 'nullable|numeric',
        'delivery_location' => 'nullable|string|max:255',
        'pincode' => 'nullable|string|max:10',
        'seed_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    ]);

    // Handle image upload
    if ($request->hasFile('seed_image')) {
        $image = $request->file('seed_image');
        $filename = 'seed_' . time() . '.' . $image->getClientOriginalExtension();
        $image->move(public_path('uploads/seeds'), $filename);
        $validated['image_path'] = 'uploads/seeds/' . $filename;
    }

    HatcherySeed::create($validated);

    return redirect()->route('hatchery-seeds.index')->with('success', 'Seed added successfully.');
}

    public function edit(HatcherySeed $hatchery_seed)
    {
        // dd($hatchery_seed);

        $data = Hatchery::excludingSpot()->with(['category:id,category_name', 'location:id,location_name'])->orderByDesc('created_at')->get();
        $categories = HatcheryCategory::all();
        $locations = HatcheryLocation::whereNull('farmer_id')->orderBy('priority')->get();
        $seed=HatcherySeed::where('id',$hatchery_seed)->first();




        return view('admin.hatchery-seeds.edit', compact('hatchery_seed','data','categories','locations','seed'));
    }

   public function update(Request $request, HatcherySeed $hatchery_seed)
{
    // dd( $request->all());
    // dd($request->hasFile('image_path'));
    $validated = $request->validate([
        'seed_brand' => 'nullable|string|max:255',
        'hatchery_id' => 'required|exists:hatcheries,id',
        'category_id' => 'required|exists:hatchery_categories,id',
        'location_id' => 'required|exists:hatchery_locations,id',
        'description' => 'nullable|string',
        'broad_stock' => 'nullable|integer',
        'brood_stock' => 'nullable|integer',
        'count' => 'nullable|integer',
        'stock_available_date' => 'nullable|date',
        'packing_start_date' => 'nullable|date',
        'expire_date' => 'nullable|date',
        'price_per_piece' => 'nullable|numeric',
        'delivery_location' => 'nullable|string|max:255',
        'pincode' => 'nullable|string|max:10',
        'seed_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    ]);

    // Handle image replacement
    if ($request->hasFile('image_path')) {
        // Delete old image if exists
        if ($hatchery_seed->image_path && file_exists(public_path($hatchery_seed->image_path))) {
            unlink(public_path($hatchery_seed->image_path));
        }

        $image = $request->file('image_path');
        $filename = 'seed_' . time() . '.' . $image->getClientOriginalExtension();
        $image->move(public_path('uploads/seeds'), $filename);
        $validated['image_path'] = 'uploads/seeds/' . $filename;
    }
// dd($validated['image_path']);
    $hatchery_seed->update($validated);

    return redirect()->route('hatchery-seeds.index')->with('success', 'Seed updated successfully.');
}

    public function destroy(HatcherySeed $hatchery_seed)
    {
        $hatchery_seed->delete();
        return redirect()->route('hatchery-seeds.index')->with('success', 'Seed deleted successfully.');
    }
}
