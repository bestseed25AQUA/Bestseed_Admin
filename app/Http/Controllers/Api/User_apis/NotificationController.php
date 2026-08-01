<?php

namespace App\Http\Controllers\Api\Farmer_apis;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * GET /api/farmer/notifications
     * Get list of user notifications
     */
    public function index(Request $request)
    {
        // Assuming notifications are stored in a notifications table related to user
        $notifications = $request->user()->notifications()->latest()->get();
        return response()->json($notifications);
    }

    /**
     * POST /api/farmer/notifications/mark-as-read
     * Mark notifications as read
     */
    public function markAsRead(Request $request)
    {
        $request->user()->notifications()->update(['read_at' => now()]);
        return response()->json(['message' => 'Notifications marked as read']);
    }
}