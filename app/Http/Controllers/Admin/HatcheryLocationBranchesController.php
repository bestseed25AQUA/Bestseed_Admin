<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HatcheryLocation;
use App\Models\HatcheryLocationBranch;
use Illuminate\Http\Request;

class HatcheryLocationBranchesController extends Controller
{
    // Get all branches for a location
    public function index($location_id)
    {
        $location = HatcheryLocation::findOrFail($location_id);
        $branches = HatcheryLocationBranch::where('location_id', $location_id)->latest()->get();

        return view('admin.hatchery-location-branches.index', compact('location', 'branches'));
    }

    // Show create form
    public function create($location_id)
    {
        $location = HatcheryLocation::findOrFail($location_id);
        return view('admin.hatchery-location-branches.create', compact('location'));
    }

    // Store branch
    public function store(Request $request, $location_id)
    {
        $request->validate([
            'branch_name' => 'required|string|max:255'
        ]);

        HatcheryLocationBranch::create([
            'location_id' => $location_id,
            'branch_name' => $request->branch_name,
            'address'     => $request->address
        ]);

        return redirect()->route('location.branches.index', $location_id)
                         ->with('success', 'Branch added successfully.');
    }



    
    // Edit page
    public function edit($id)
    {
        $branch = HatcheryLocationBranch::findOrFail($id);
        $location = HatcheryLocation::findOrFail($branch->location_id);

        return view('admin.hatchery-location-branches.edit', compact('branch', 'location'));
    }

    // Update branch
    public function update(Request $request, $id)
    {
        $request->validate([
            'branch_name' => 'required|max:255',
        ]);

        $branch = HatcheryLocationBranch::findOrFail($id);

        $branch->update([
            'branch_name' => $request->branch_name,
            'address'     => $request->address,
        ]);

        return redirect()->route('location.branches.index', $branch->location_id)
                         ->with('success', 'Branch updated successfully.');
    }

    // Delete branch
    public function destroy($id)
    {
        $branch = HatcheryLocationBranch::findOrFail($id);
        $location_id = $branch->location_id;
        $branch->delete();

        return redirect()->route('location.branches.index', $location_id)
                         ->with('success', 'Branch deleted successfully.');
    }
}
