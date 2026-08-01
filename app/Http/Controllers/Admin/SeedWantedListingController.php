<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeedWantedListing;
use Illuminate\Http\Request;

class SeedWantedListingController extends Controller
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
        $species = $request->query('species', 'shrimp');
        $listings = SeedWantedListing::where('species', $species)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.seed_wanted_listings.index', compact('listings', 'species'));
    }

    public function create()
    {
        return view('admin.seed_wanted_listings.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'             => 'required|string|max:255',
            'species'           => 'required|in:shrimp,fish',
            'count_or_kg'       => 'required|in:count,kg',
            'count_or_kg_value' => 'nullable|string|max:100',
            'payment'           => 'nullable|string|max:255',
            'minimum'           => 'nullable|string|max:100',
            'area'              => 'nullable|string|max:255',
            'price'             => 'nullable|string|max:100',
            'phone'             => 'nullable|string|max:20',
        ]);

        SeedWantedListing::create($request->only([
            'title', 'species', 'count_or_kg', 'count_or_kg_value',
            'payment', 'minimum', 'area', 'price', 'phone',
        ]));

        return redirect()->route('seed-wanted-listings.index', ['species' => $request->species])
            ->with('success', 'Listing added successfully.');
    }

    public function edit(string $id)
    {
        $listing = SeedWantedListing::findOrFail($id);
        return view('admin.seed_wanted_listings.edit', compact('listing'));
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'title'             => 'required|string|max:255',
            'species'           => 'required|in:shrimp,fish',
            'count_or_kg'       => 'required|in:count,kg',
            'count_or_kg_value' => 'nullable|string|max:100',
            'payment'           => 'nullable|string|max:255',
            'minimum'           => 'nullable|string|max:100',
            'area'              => 'nullable|string|max:255',
            'price'             => 'nullable|string|max:100',
            'phone'             => 'nullable|string|max:20',
        ]);

        $listing = SeedWantedListing::findOrFail($id);
        $listing->update($request->only([
            'title', 'species', 'count_or_kg', 'count_or_kg_value',
            'payment', 'minimum', 'area', 'price', 'phone',
        ]));

        return redirect()->route('seed-wanted-listings.index', ['species' => $request->species])
            ->with('success', 'Listing updated successfully.');
    }

    public function destroy(string $id)
    {
        SeedWantedListing::findOrFail($id)->delete();
        return redirect()->back()->with('success', 'Listing deleted successfully.');
    }
}
