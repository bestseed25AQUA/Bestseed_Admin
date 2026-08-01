<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeedRequest;
use Illuminate\Http\Request;

class SeedRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:hatchery-seeds.view')->only(['index', 'show']);
        $this->middleware('permission:hatchery-seeds.update')->only(['updateStatus']);
        $this->middleware('permission:hatchery-seeds.delete')->only(['destroy']);
    }

    public function index()
    {
        $seedRequests = SeedRequest::with(['farmer', 'category', 'hatchery', 'handler'])
            ->latest()
            ->get();

        $statuses = SeedRequest::getStatuses();

        return view('admin.seed-requests.index', compact('seedRequests', 'statuses'));
    }

    public function show(SeedRequest $seedRequest)
    {
        $seedRequest->load(['farmer', 'category', 'hatchery', 'handler']);
        $statuses = SeedRequest::getStatuses();

        return view('admin.seed-requests.show', compact('seedRequest', 'statuses'));
    }

    public function updateStatus(Request $request, SeedRequest $seedRequest)
    {
        $request->validate([
            'status' => 'required|in:pending,processing,completed,rejected',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $seedRequest->update([
            'status' => $request->status,
            'admin_notes' => $request->admin_notes,
            'handled_by' => auth()->id(),
            'handled_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Seed request updated successfully.');
    }

    public function destroy(SeedRequest $seedRequest)
    {
        $seedRequest->delete();

        return redirect()->route('admin.seed-requests.index')
            ->with('success', 'Seed request deleted successfully.');
    }
}
