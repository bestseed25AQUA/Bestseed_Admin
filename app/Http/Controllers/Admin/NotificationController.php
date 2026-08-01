<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Farmer;
use App\Models\Hatchery;
use App\Models\HatcheryCategory;
use App\Models\PushNotification;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:settings.view')->only(['index', 'searchFarmers', 'searchHatcheries']);
        $this->middleware('permission:settings.create')->only(['create', 'store']);
        $this->middleware('permission:settings.delete')->only(['destroy']);
    }

    /**
     * List all broadcast notifications
     */
    public function index()
    {
        $notifications = PushNotification::where('type', 'admin_broadcast')
            ->with('farmer')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.notifications.index', compact('notifications'));
    }

    /**
     * Show create notification form
     */
    public function create()
    {
        // Categories power the auto-filled dropdown shown after a hatchery is
        // picked (module == 'Hatchery'). Same source as the spot-hatchery form.
        $categories = HatcheryCategory::orderBy('priority')->get();

        return view('admin.notifications.create', compact('categories'));
    }

    /**
     * AJAX endpoint: search farmers for Select2
     */
    public function searchFarmers(Request $request)
    {
        $search = $request->get('q', '');

        $farmers = Farmer::with('latestLocation')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('mobile', 'like', "%{$search}%")
                      ->orWhereHas('latestLocation', function ($locQuery) use ($search) {
                          $locQuery->where('location_name', 'like', "%{$search}%")
                                   ->orWhere('full_address', 'like', "%{$search}%");
                      });
                });
            })
            ->limit(20)
            ->get();

        $results = $farmers->map(function ($farmer) {
            $name = trim(($farmer->first_name ?? '') . ' ' . ($farmer->last_name ?? ''));
            $mobile = $farmer->mobile ?? '';
            $location = $farmer->latestLocation->location_name ?? ($farmer->latestLocation->full_address ?? '');

            $label = $name;
            if ($mobile) $label .= ' - ' . $mobile;
            if ($location) $label .= ' - ' . $location;

            return [
                'id' => $farmer->id,
                'text' => $label,
            ];
        });

        return response()->json(['results' => $results]);
    }

    /**
     * AJAX endpoint: search hatcheries for Select2 (used when module == 'Hatchery'
     * to tie a notification to a specific hatchery).
     */
    public function searchHatcheries(Request $request)
    {
        $search = $request->get('q', '');
        $page = max(1, (int) $request->get('page', 1));
        $perPage = 20;

        // Base query — with no search term this returns the FULL list
        // (paginated) so the admin can browse and pick a hatchery without
        // having to type anything first.
        $query = Hatchery::excludingSpot()
            ->when($search, function ($query) use ($search) {
                $query->where('hatchery_name', 'like', "%{$search}%");
            })
            ->orderBy('hatchery_name');

        $total = $query->count();

        $hatcheries = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get(['id', 'hatchery_name', 'category_id']);

        $results = $hatcheries->map(function ($hatchery) {
            return [
                'id' => $hatchery->id,
                'text' => $hatchery->hatchery_name ?? ('Hatchery #' . $hatchery->id),
                // Drives the auto-filled category dropdown on the create form.
                'category_id' => $hatchery->category_id,
            ];
        });

        return response()->json([
            'results' => $results,
            // Tells Select2 whether to fetch another page on scroll (infinite scroll).
            'pagination' => ['more' => ($page * $perPage) < $total],
        ]);
    }

    /**
     * Store and send notification (broadcast or selected farmers)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:20480',
            'module' => 'nullable|string|max:100',
            'module_id' => 'nullable|integer',
            'category_id' => 'nullable|exists:hatchery_categories,id',
            'recipient_type' => 'required|in:all,selected',
            'farmer_ids' => 'required_if:recipient_type,selected|array',
            'farmer_ids.*' => 'exists:farmers,id',
        ]);

        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/notifications/'), $filename);
            $imagePath = 'uploads/notifications/' . $filename;
        }

        $module = $validated['module'] ?? null;
        $body = $validated['body'] ?? '';
        $recipientType = $validated['recipient_type'];
        $imageUrl = $imagePath ? asset($imagePath) : null;

        // When the notification targets a specific hatchery (module == 'Hatchery'
        // with a chosen hatchery), attach it so the app can deep-link straight to
        // that hatchery's details page instead of the general hatchery list.
        $hatcheryId = null;
        $hatcheryName = null;
        if ($module === 'Hatchery' && !empty($validated['module_id'])) {
            $hatchery = Hatchery::find($validated['module_id']);
            if ($hatchery) {
                $hatcheryId = (string) $hatchery->id;
                $hatcheryName = $hatchery->hatchery_name;
            }
        }

        // Category is auto-filled from the chosen hatchery but the admin may
        // override it; whatever is submitted is what we persist/send.
        $categoryId = null;
        $categoryName = null;
        if ($module === 'Hatchery' && !empty($validated['category_id'])) {
            $category = HatcheryCategory::find($validated['category_id']);
            if ($category) {
                $categoryId = (string) $category->id;
                $categoryName = $category->category_name;
            }
        }

        // Persisted on the notification row (model casts `data` to array) and read
        // by the farmer notifications API.
        $storedData = array_filter([
            'type' => 'admin_broadcast',
            'hatchery_id' => $hatcheryId,
            'hatchery_name' => $hatcheryName,
            'category_id' => $categoryId,
            'category_name' => $categoryName,
        ], fn ($v) => $v !== null);

        // Extra keys merged into the FCM data payload (values must be strings).
        $fcmExtra = array_filter([
            'hatchery_id' => $hatcheryId,
            'hatchery_name' => $hatcheryName,
            'category_id' => $categoryId,
            'category_name' => $categoryName,
        ], fn ($v) => $v !== null);

        $fcm = new FirebaseNotificationService();

        if ($recipientType === 'all') {
            // Broadcast to all users by sending to each device token
            $notification = PushNotification::create([
                'title' => $validated['title'],
                'body' => $body,
                'image' => $imagePath,
                'module' => $module,
                'recipient_type' => 'all',
                'type' => 'admin_broadcast',
                'farmer_id' => null,
                'data' => $storedData,
            ]);

            $data = array_merge([
                'type' => 'admin_broadcast',
                'notification_id' => (string) $notification->id,
                'title' => $validated['title'],
                'body' => $body,
                'image' => $imageUrl ?? '',
                'module' => $module ?? '',
            ], $fcmExtra);

            $sentCount = 0;
            $failedCount = 0;

            // Send to all farmers who have FCM tokens
            $farmers = Farmer::whereNotNull('fcm_token')->where('fcm_token', '!=', '')->get();

            foreach ($farmers as $farmer) {
                try {
                    $fcm->sendToDevice($farmer->fcm_token, $validated['title'], $body, $imageUrl, $data);
                    $sentCount++;
                } catch (\Exception $e) {
                    Log::error("Failed to send broadcast FCM to farmer #{$farmer->id}: " . $e->getMessage());
                    $failedCount++;
                }
            }

            $message = "Notification sent to {$sentCount} user(s).";
            if ($failedCount > 0) {
                $message .= " {$failedCount} user(s) could not be reached.";
            }

            return redirect()->route('admin.notifications.index')
                ->with('success', $message);

        } else {
            // Send to selected farmers
            $farmerIds = $validated['farmer_ids'];
            $farmers = Farmer::whereIn('id', $farmerIds)->get();

            $sentCount = 0;
            $failedCount = 0;

            foreach ($farmers as $farmer) {
                $notification = PushNotification::create([
                    'title' => $validated['title'],
                    'body' => $body,
                    'image' => $imagePath,
                    'module' => $module,
                    'recipient_type' => 'selected',
                    'type' => 'admin_broadcast',
                    'farmer_id' => $farmer->id,
                    'data' => $storedData,
                ]);

                if ($farmer->fcm_token) {
                    try {
                        $fcm->sendToDevice($farmer->fcm_token, $validated['title'], $body, $imageUrl, array_merge([
                            'type' => 'admin_broadcast',
                            'notification_id' => (string) $notification->id,
                            'title' => $validated['title'],
                            'body' => $body,
                            'image' => $imageUrl ?? '',
                            'module' => $module ?? '',
                        ], $fcmExtra));
                        $sentCount++;
                    } catch (\Exception $e) {
                        Log::error("Failed to send FCM to farmer #{$farmer->id}: " . $e->getMessage());
                        $failedCount++;
                    }
                } else {
                    $failedCount++;
                }
            }

            $message = "Notification sent to {$sentCount} user(s).";
            if ($failedCount > 0) {
                $message .= " {$failedCount} user(s) could not be reached.";
            }

            return redirect()->route('admin.notifications.index')
                ->with('success', $message);
        }
    }

    /**
     * Delete a notification
     */
    public function destroy(PushNotification $notification)
    {
        $notification->delete();
        return redirect()->route('admin.notifications.index')
            ->with('success', 'Notification deleted.');
    }
}
