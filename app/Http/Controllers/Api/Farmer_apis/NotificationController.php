<?php

namespace App\Http\Controllers\Api\Farmer_apis;

use App\Http\Controllers\Controller;
use App\Models\PushNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * GET /api/farmer/push-notifications
     * Returns broadcast notifications + personal notifications for this farmer
     */
    public function index(Request $request)
    {
        $farmerId = $request->user()->id;
        $perPage = (int) $request->query('per_page', 10);

        $paginated = PushNotification::where(function ($query) use ($farmerId) {
                // Broadcast notifications (farmer_id is null)
                $query->whereNull('farmer_id')
                      ->where('type', 'admin_broadcast');
            })
            ->orWhere(function ($query) use ($farmerId) {
                // Personal notifications for this farmer
                $query->where('farmer_id', $farmerId);
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $notifications = collect($paginated->items())->map(function ($notification) {
            return [
                'id' => $notification->id,
                'title' => $notification->title,
                'body' => $notification->body,
                'image' => $notification->image,
                'module' => $notification->module,
                'type' => $notification->type,
                'data' => $notification->data,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => true,
            'notifications' => $notifications,
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
        ]);
    }

    /**
     * POST /api/farmer/push-notifications/{id}/read
     * Mark a single notification as read
     */
    public function markAsRead(Request $request, $id)
    {
        $farmerId = $request->user()->id;

        $notification = PushNotification::where('id', $id)
            ->where(function ($query) use ($farmerId) {
                $query->whereNull('farmer_id')
                      ->orWhere('farmer_id', $farmerId);
            })
            ->first();

        if (!$notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        $notification->update(['read_at' => now()]);

        return response()->json(['message' => 'Notification marked as read']);
    }
}
