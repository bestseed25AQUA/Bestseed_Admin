<?php

namespace App\Http\Controllers\Api\Vendors_apis;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
// use App\Models\VendorNotificationSetting;
use Exception;

class VendorNotificationController extends Controller
{
    /**
     * 📌 Get current vendor notification settings
     */
    public function index()
    {
        try {
            $vendorId = Auth::id();
            $settings = Notification::firstOrCreate(
                ['vendor_id' => $vendorId],
                [
                    'seed_updates'     => true,
                    'hatchery_updates' => true,
                    'broodstock_updates' => true,
                    'vehicle_updates'  => true,
                ]
            );

            return response()->json($settings, 200);
        } catch (Exception $e) {
            return response()->json([
                'error'   => true,
                'method'  => 'index',
                'message' => 'Failed to fetch notification settings',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 📌 Update vendor notification settings
     */
    public function update(Request $request)
    {
        try {
            $vendorId = Auth::id();

            $request->validate([
                'seed_updates'       => 'nullable|boolean',
                'hatchery_updates'   => 'nullable|boolean',
                'broodstock_updates' => 'nullable|boolean',
                'vehicle_updates'    => 'nullable|boolean',
            ]);

            $settings = Notification::updateOrCreate(
                ['vendor_id' => $vendorId],
                $request->only([
                    'seed_updates',
                    'hatchery_updates',
                    'broodstock_updates',
                    'vehicle_updates',
                ])
            );

            return response()->json([
                'message' => 'Notification settings updated successfully',
                'data'    => $settings,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'error'   => true,
                'method'  => 'update',
                'message' => 'Failed to update notification settings',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
