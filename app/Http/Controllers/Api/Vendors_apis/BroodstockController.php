<?php

namespace App\Http\Controllers\Api\Vendors_apis;

use App\Http\Controllers\Controller;
use App\Models\Broodstock;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class BroodstockController extends Controller
{
    /**
     * 📌 List all broodstock (vendor-specific)
     */
    public function index()
    {
        try {
            $vendorId = Auth::id();
            $broodstocks = Broodstock::where('vendor_id', $vendorId)
                ->latest()
                ->get();

            return response()->json($broodstocks, 200);
        } catch (Exception $e) {
            Log::error("BroodstockController@index failed", ['error' => $e]);
            return response()->json([
                'error'   => true,
                'method'  => 'index',
                'message' => 'Failed to fetch broodstock',
                'details' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    /**
     * 📌 Add new broodstock
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'hatchery_name'   => 'required|string|max:255',
                'category'        => 'nullable|array',
                'category.*'      => 'string|max:255',
                'location'        => 'nullable|string|max:255',
                'count'           => 'required|integer|min:0',
                'available_date'  => 'nullable|date',
                'packing_date'    => 'nullable|date',
                'images.*'        => 'file|mimes:jpg,jpeg,png,heic,heif|max:5120',
            ]);

            $images = [];
            if ($request->hasFile('images')) {
                $vendor = Auth::user();
                $vendorName = preg_replace('/\s+/', '_', strtolower($vendor->name ?? 'vendor'));

                foreach ($request->file('images') as $file) {
                    $filename = date('dmY') . '_vendor_' . $vendor->id . '_' . $vendorName . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('broodstock', $filename, 'public');
                    $images[] = asset("storage/" . $path);
                }
            }

            $broodstock = Broodstock::create([
                'vendor_id'      => Auth::id(),
                'hatchery_name'  => $request->hatchery_name,
                'category'       => $request->category,
                'location'       => $request->location,
                'count'          => $request->count,
                'available_date' => $request->available_date,
                'packing_date'   => $request->packing_date,
                'images'         => $images,
            ]);

            return response()->json([
                'message' => 'Broodstock added successfully',
                'data'    => $broodstock,
            ], 201);

        } catch (Exception $e) {
            Log::error("BroodstockController@store failed", ['error' => $e]);
            return response()->json([
                'error'   => true,
                'method'  => 'store',
                'message' => 'Failed to add broodstock',
                'details' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    /**
     * 📌 Show single broodstock
     */
    public function show($id)
    {
        try {
            $broodstock = Broodstock::with('vendor')->findOrFail($id);
            return response()->json($broodstock, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error'   => true,
                'method'  => 'show',
                'message' => "Broodstock with ID {$id} not found or already deleted",
            ], 404);
        } catch (Exception $e) {
            Log::error("BroodstockController@show failed", ['error' => $e]);
            return response()->json([
                'error'   => true,
                'method'  => 'show',
                'message' => 'Failed to fetch broodstock',
                'details' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    /**
     * 📌 Delete broodstock
     */
    public function destroy($id)
    {
        try {
            $broodstock = Broodstock::where('vendor_id', Auth::id())->findOrFail($id);

            if ($broodstock->images) {
                foreach ($broodstock->images as $img) {
                    $path = str_replace(('storage/'), '', $img);
                    if (Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                    }
                }
            }

            $broodstock->delete();

            return response()->json(['message' => 'Broodstock deleted successfully'], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error'   => true,
                'method'  => 'destroy',
                'message' => "Broodstock with ID {$id} not found or already deleted",
            ], 404);
        } catch (Exception $e) {
            Log::error("BroodstockController@destroy failed", ['error' => $e]);
            return response()->json([
                'error'   => true,
                'method'  => 'destroy',
                'message' => 'Failed to delete broodstock',
                'details' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }
}
