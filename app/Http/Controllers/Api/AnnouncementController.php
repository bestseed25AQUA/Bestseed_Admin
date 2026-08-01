<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Driver;
use App\Models\Vendor;
use Illuminate\Http\Request;

/**
 * Announcements for all three apps (farmer / driver / vendor).
 *
 * The audience is derived from whichever model sanctum resolved, so the same
 * controller serves /api/farmer/announcements, /api/driver/announcements and
 * /api/vendor/announcements without duplicating logic.
 */
class AnnouncementController extends Controller
{
    /**
     * GET /api/{app}/announcements
     * Full list for this audience — powers Profile > Announcements.
     */
    public function index(Request $request)
    {
        [$audience, $recipientId] = $this->resolveRecipient($request);

        $announcements = Announcement::active()
            ->forAudience($audience)
            ->orderByDesc('id')
            ->get();

        $reads = $this->readsFor($audience, $recipientId, $announcements->pluck('id'));

        $items = $announcements->map(function ($announcement) use ($reads) {
            return $this->transform($announcement, $reads->get($announcement->id));
        })->values();

        return response()->json([
            'status'        => true,
            'unread_count'  => $items->where('is_read', false)->count(),
            'announcements' => $items,
        ]);
    }

    /**
     * GET /api/{app}/announcements/popup
     *
     * Returns the single announcement that should pop up as a dialog right now,
     * or null. Every other announcement that has not been offered yet is marked
     * as shown at the same time — so only the newest ever pops, and the older
     * ones simply wait, still unread, in the Profile list.
     */
    public function popup(Request $request)
    {
        [$audience, $recipientId] = $this->resolveRecipient($request);

        $announcements = Announcement::active()
            ->forAudience($audience)
            ->orderByDesc('id')
            ->get();

        $reads = $this->readsFor($audience, $recipientId, $announcements->pluck('id'));

        // Never offered to this recipient before.
        $pending = $announcements->filter(function ($announcement) use ($reads) {
            $read = $reads->get($announcement->id);

            return $read === null || $read->popup_shown_at === null;
        });

        $popup = $pending->first();

        foreach ($pending as $announcement) {
            AnnouncementRead::updateOrCreate(
                [
                    'announcement_id' => $announcement->id,
                    'audience'        => $audience,
                    'recipient_id'    => $recipientId,
                ],
                ['popup_shown_at' => now()]
            );
        }

        $unreadCount = $announcements->filter(function ($announcement) use ($reads) {
            return optional($reads->get($announcement->id))->read_at === null;
        })->count();

        return response()->json([
            'status'       => true,
            'unread_count' => $unreadCount,
            'announcement' => $popup ? $this->transform($popup, $reads->get($popup->id)) : null,
        ]);
    }

    /**
     * POST /api/{app}/announcements/{id}/read
     * Called when the dialog is closed or a list row is opened.
     */
    public function markAsRead(Request $request, $id)
    {
        [$audience, $recipientId] = $this->resolveRecipient($request);

        $announcement = Announcement::forAudience($audience)->find($id);

        if (! $announcement) {
            return response()->json([
                'status'  => false,
                'message' => 'Announcement not found',
            ], 404);
        }

        AnnouncementRead::updateOrCreate(
            [
                'announcement_id' => $announcement->id,
                'audience'        => $audience,
                'recipient_id'    => $recipientId,
            ],
            [
                'read_at'        => now(),
                'popup_shown_at' => now(),
            ]
        );

        $unreadCount = Announcement::active()
            ->forAudience($audience)
            ->whereDoesntHave('reads', function ($query) use ($audience, $recipientId) {
                $query->where('audience', $audience)
                    ->where('recipient_id', $recipientId)
                    ->whereNotNull('read_at');
            })
            ->count();

        return response()->json([
            'status'       => true,
            'message'      => 'Announcement marked as read',
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Map the authenticated model onto the audience stored on announcements.
     */
    private function resolveRecipient(Request $request): array
    {
        $user = $request->user();

        $audience = match (true) {
            $user instanceof Driver => 'driver',
            $user instanceof Vendor => 'vendor',
            default                 => 'user',
        };

        return [$audience, $user->id];
    }

    private function readsFor(string $audience, $recipientId, $announcementIds)
    {
        if ($announcementIds->isEmpty()) {
            return collect();
        }

        return AnnouncementRead::where('audience', $audience)
            ->where('recipient_id', $recipientId)
            ->whereIn('announcement_id', $announcementIds)
            ->get()
            ->keyBy('announcement_id');
    }

    private function transform(Announcement $announcement, ?AnnouncementRead $read): array
    {
        return [
            'id'          => $announcement->id,
            'title'       => $announcement->title,
            'description' => $announcement->description,
            'image'       => $announcement->image_url,
            'audience'    => $announcement->audience,
            'is_read'     => $read !== null && $read->read_at !== null,
            'read_at'     => $read?->read_at?->toIso8601String(),
            'created_at'  => $announcement->created_at?->toIso8601String(),
        ];
    }
}
