<?php

namespace App\Http\Controllers\Api\Vendors_apis;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VendorVehicleController extends Controller
{
    /**
     * 📌 List all bookings (vendor-specific)
     */
    public function index()
    {
        try {
            $vendorId = Auth::id();
            $bookings = Booking::where('vendor_id', $vendorId)
                ->latest()
                ->get();

            // ✅ Attach full URLs for images
            $bookings->transform(function ($booking) {
                if ($booking->vehicle_images) {
                    $booking->vehicle_images = array_map(function ($path) {
                        return asset('storage/' . $path);
                    }, $booking->vehicle_images);
                }
                return $booking;
            });

            return response()->json($bookings, 200);
        } catch (Exception $e) {
            Log::error("VendorVehicleController@index failed", ['error' => $e]);
            return response()->json([
                'error'   => true,
                'method'  => 'index',
                'message' => 'Failed to fetch vehicle bookings',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 📌 Add new booking (Vehicle availability details)
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                // ✅ Vehicle Images
                'vehicle_images'   => 'nullable|array',
                'vehicle_images.*' => 'file|mimes:jpg,jpeg,png|max:5120',

                // ✅ Customer Details
                'customer_id'       => 'nullable|string|max:255',
                'customer_name'     => 'required|string|max:255',
                'customer_mobile'   => 'required|string|max:20',
                'delivery_location' => 'nullable|string|max:255',

                // ✅ Stock Info
                'hatchery_name'     => 'required|string|max:255',
                'categories'        => 'nullable|array',
                'categories.*'      => 'string|max:255',
                'count'             => 'required|integer|min:0',

                // ✅ Driver Details
                'driver_name'       => 'required|string|max:255',
                'driver_mobile'     => 'required|string|max:20',
                'vehicle_number'    => 'required|string|max:50',

                // ✅ Accept both Y-m-d and d-m-Y
                'vehicle_started_date' => [
                    'nullable',
                    'date',
                    'date_format:Y-m-d',
                ],
            ]);

            $images = [];
            if ($request->hasFile('vehicle_images')) {
                try {
                    $vendor = Auth::user();
                    $vendorName = preg_replace('/\s+/', '_', strtolower($vendor->name ?? 'vendor'));

                    foreach ($request->file('vehicle_images') as $file) {
                        $filename = date('dmY') . '_vendor_' . $vendor->id . '_' . $vendorName . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

                        // ✅ Save inside storage/app/public/vendor_bookings/
                        $relativePath = $file->storeAs('vendor_bookings', $filename, 'public');

                        // ✅ Store only relative path in DB
                        $images[] = $relativePath;
                    }
                } catch (Exception $imgEx) {
                    Log::error("VendorVehicleController@store image upload failed", ['error' => $imgEx]);
                    return response()->json([
                        'error'   => true,
                        'method'  => 'store',
                        'message' => 'Image upload failed',
                        'details' => $imgEx->getMessage()
                    ], 500);
                }
            }

            $booking = Booking::create([
                'vendor_id'         => Auth::id(),
                'vehicle_images'    => $images,

                'customer_id'       => $request->customer_id,
                'customer_name'     => $request->customer_name,
                'customer_mobile'   => $request->customer_mobile,
                'delivery_location' => $request->delivery_location,

                'hatchery_name'     => $request->hatchery_name,
                'categories'        => $request->categories,
                'count'             => $request->count,

                'driver_name'       => $request->driver_name,
                'driver_mobile'     => $request->driver_mobile,
                'vehicle_number'    => $request->vehicle_number,
                'vehicle_started_date' => $request->vehicle_started_date,
            ]);

            // ✅ Add full URLs to response
            if ($booking->vehicle_images) {
                $booking->vehicle_images = array_map(function ($path) {
                    return asset('storage/' . $path);
                }, $booking->vehicle_images);
            }

            return response()->json([
                'message' => '✅ Vehicle booking added successfully',
                'data'    => $booking,
            ], 201);

        } catch (ValidationException $ve) {
            // ✅ Return exact validation errors
            return response()->json([
                'error'   => true,
                'method'  => 'store',
                'message' => 'Validation failed',
                'details' => $ve->errors(), // 👉 detailed field-wise errors
            ], 422);
        } catch (Exception $e) {
            Log::error("VendorVehicleController@store failed", ['error' => $e]);
            return response()->json([
                'error'   => true,
                'method'  => 'store',
                'message' => 'Failed to add vehicle booking',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 📌 Show single booking
     */
    public function show($id)
    {
        try {
            $booking = Booking::with('vendor')->findOrFail($id);

            if ($booking->vehicle_images) {
                $booking->vehicle_images = array_map(function ($path) {
                    return asset('storage/' . $path);
                }, $booking->vehicle_images);
            }

            return response()->json($booking, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error'   => true,
                'method'  => 'show',
                'message' => "Booking with ID {$id} not found or already deleted",
            ], 404);
        } catch (Exception $e) {
            Log::error("VendorVehicleController@show failed", ['error' => $e]);
            return response()->json([
                'error'   => true,
                'method'  => 'show',
                'message' => 'Failed to fetch booking',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 📌 Delete booking
     */
    public function destroy($id)
    {
        try {
            $booking = Booking::where('vendor_id', Auth::id())->findOrFail($id);

            if ($booking->vehicle_images) {
                foreach ($booking->vehicle_images as $img) {
                    if (Storage::disk('public')->exists($img)) {
                        Storage::disk('public')->delete($img);
                    }
                }
            }

            $booking->delete();

            return response()->json(['message' => '✅ Vehicle booking deleted successfully'], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error'   => true,
                'method'  => 'destroy',
                'message' => "Booking with ID {$id} not found or already deleted",
            ], 404);
        } catch (Exception $e) {
            Log::error("VendorVehicleController@destroy failed", ['error' => $e]);
            return response()->json([
                'error'   => true,
                'method'  => 'destroy',
                'message' => 'Failed to delete booking',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}