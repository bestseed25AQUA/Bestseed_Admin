<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HatcheryLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HatcheryLocationController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:hatchery-locations.view')->only(['index', 'show']);
        $this->middleware('permission:hatchery-locations.create')->only(['create', 'store']);
        $this->middleware('permission:hatchery-locations.update')->only(['edit', 'update']);
        $this->middleware('permission:hatchery-locations.delete')->only(['destroy']);
    }

    public function index()
    {
        $data = HatcheryLocation::whereNull('farmer_id')->orderBy('priority', 'asc')->get();
        return view('admin.hatchery-locations.index', compact('data'));
    }

    public function create()
    {
        return view('admin.hatchery-locations.create');
    }

public function store(Request $request)
{
    $request->validate([
        'priority'      => 'nullable|integer|min:0',
        'location_name' => 'nullable|string|max:255',
        'latitude'      => 'nullable|numeric',
        'longitude'     => 'nullable|numeric',
    ]);

    try {
        HatcheryLocation::create([
            'priority'      => $request->priority ?? 0,
            'location_name' => $request->location_name,
            'latitude'      => $request->latitude,
            'longitude'     => $request->longitude,
        ]);

        return redirect()->route('hatchery-locations.index')
            ->with('success', 'Hatchery location created successfully.');

    } catch (\Exception $e) {
        \Log::error('Error creating hatchery location: ' . $e->getMessage());
        return back()->withInput()->with('error', 'Failed to create location.');
    }
}


    public function edit($id)
    {
        $data = HatcheryLocation::findOrFail($id);
        return view('admin.hatchery-locations.edit', compact('data'));
    }

    // public function update(Request $request, $id)
    // {
    //     $request->validate([
    //         'name'     => 'required|string|max:255',
    //         'priority' => 'nullable|integer|min:0',
    //     ]);

    //     try {
    //         $location = HatcheryLocation::findOrFail($id);
    //         $location->update([
    //             'name'     => $request->name,
    //             'priority' => $request->priority ?? 0,
    //         ]);

    //         return redirect()->route('hatchery-locations.index')
    //             ->with('success', 'Location updated successfully.');

    //     } catch (\Exception $e) {
    //         Log::error('Error updating hatchery location: ' . $e->getMessage());
    //         return back()->withInput()->with('error', 'Failed to update location.');
    //     }
    // }
    public function update(Request $request, $id)
{
    // dd($request->all());
    $request->validate([
        'priority'      => 'nullable|integer|min:0',
        'location_name' => 'nullable|string|max:255',
        'latitude'      => 'nullable|numeric',
        'longitude'     => 'nullable|numeric',
    ]);

    try {
        $location = HatcheryLocation::findOrFail($id);

        $location->update([
            'priority'      => $request->priority ?? 0,
            'location_name' => $request->location_name,
            'latitude'      => $request->latitude,
            'longitude'     => $request->longitude,
        ]);

        return redirect()->route('hatchery-locations.index')
            ->with('success', 'Hatchery location updated successfully.');

    } catch (\Exception $e) {
        \Log::error('Error updating hatchery location: ' . $e->getMessage());
        return back()->withInput()->with('error', 'Failed to update location.');
    }
}



    public function destroy($id)
    {
        try {
            $location = HatcheryLocation::findOrFail($id);
            $location->delete();

            return redirect()->route('hatchery-locations.index')
                ->with('success', 'Location deleted successfully.');

        } catch (\Exception $e) {
            Log::error('Error deleting hatchery location: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete location.');
        }
    }
}
