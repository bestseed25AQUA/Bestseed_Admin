<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketPrice;
use App\Models\HatcheryCategory;
use App\Models\HatcheryLocation;
use Illuminate\Http\Request;

class MarketPriceController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:market-prices.view')->only(['index', 'show']);
        $this->middleware('permission:market-prices.create')->only(['create', 'store']);
        $this->middleware('permission:market-prices.update')->only(['edit', 'update']);
        $this->middleware('permission:market-prices.delete')->only(['destroy']);
    }

public function index(Request $request)
{
    $categoryId = $request->query('category');

    $categories = HatcheryCategory::whereIn('category_name', ['Vannamei', 'Tiger'])
        ->orderByRaw("FIELD(category_name, 'Vannamei', 'Tiger')")
        ->get();

    $query = MarketPrice::with(['category', 'location'])
        ->orderBy('priority', 'asc')
        ->orderBy('id', 'desc');

    if ($categoryId) {
        $query->where('category_id', $categoryId);
    } elseif ($categories->isNotEmpty()) {
        $categoryId = $categories->first()->id;
        $query->where('category_id', $categoryId);
    }

    $prices = $query->get();

    return view('admin.market_prices.index', compact('prices', 'categories', 'categoryId'));
}


    public function create()
    {
        $categories = HatcheryCategory::all();
        $locations = HatcheryLocation::whereNull('farmer_id')->orderBy('priority')->get();
        return view('admin.market_prices.create', compact('categories', 'locations'));
    }
    public function store(Request $request)
    {
        $request->validate([
            'entries' => 'nullable',
            'entries.*.category_id' => 'nullable',
            'entries.*.location_id' => 'nullable',
            'entries.*.size' => 'nullable',
            'entries.*.price' => 'nullable',
            'entries.*.priority' => 'nullable|integer|min:1',
        ]);

        try {
            foreach ($request->entries as $entry) {
                MarketPrice::create([
                    'category_id' => $entry['category_id'],
                    'location_id' => $entry['location_id'],
                    'size' => $entry['size'],
                    'price' => $entry['price'],
                    'priority' => $entry['priority'] ?? null,
                ]);
            }
            return redirect()
                ->route('market_prices.index')
                ->with('success', 'Market price(s) added successfully.');
        } catch (\Exception $e) {
            dd($e->getMessage());
            \Log::error('Error while creating market price: ' . $e->getMessage());
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Something went wrong while adding the market price. Please try again later.');
        }
    }


    public function edit(string $id)
    {
        $price = MarketPrice::findOrFail($id);
        $categories = HatcheryCategory::all();
        $locations = HatcheryLocation::whereNull('farmer_id')->orderBy('priority')->get();
        return view('admin.market_prices.edit', compact('price', 'categories', 'locations'));
    }

public function update(Request $request, string $id)
{
    $request->validate([
        'size' => 'required|string|max:50',
        'price' => 'required|numeric|min:0',
        'category_id' => 'required|exists:hatchery_categories,id',
        'location_id' => 'required|exists:hatchery_locations,id',
        'priority' => 'nullable|integer|min:1',
    ]);

    $price = MarketPrice::findOrFail($id);

    $price->update([
        'species' => $request->species,
        'size' => $request->size,
        'price' => $request->price,
        'category_id' => $request->category_id,
        'location_id' => $request->location_id,
        'priority' => $request->priority,
    ]);

    return redirect()->route('market_prices.index')->with('success', 'Market price updated successfully.');
}

    public function destroy(string $id)
    {
        $price = MarketPrice::findOrFail($id);
        $price->delete();

        return redirect()->route('market_prices.index')->with('success', 'Market price deleted successfully.');
    }
}
