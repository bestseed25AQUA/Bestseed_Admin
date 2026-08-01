<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Driver;
use App\Models\Farmer;
use App\Models\Vendor;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AnnouncementController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:announcements.view')->only(['index', 'show']);
        $this->middleware('permission:announcements.create')->only(['create', 'store']);
        $this->middleware('permission:announcements.update')->only(['edit', 'update', 'toggleStatus']);
        $this->middleware('permission:announcements.delete')->only(['destroy']);
    }

    public function index()
    {
        $announcements = Announcement::withCount([
            'reads as read_count' => fn ($query) => $query->whereNotNull('read_at'),
        ])->orderByDesc('id')->get();

        return view('admin.announcements.index', compact('announcements'));
    }

    public function create()
    {
        return view('admin.announcements.create', [
            'audiences' => Announcement::AUDIENCES,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255',
            'description' => 'required|string',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            'audience'    => 'required|in:' . implode(',', array_keys(Announcement::AUDIENCES)),
            'is_active'   => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $data = $validator->validated();
            $data['is_active'] = $request->boolean('is_active', true);

            if ($request->hasFile('image')) {
                $data['image'] = $this->storeImage($request->file('image'));
            }

            $announcement = Announcement::create($data);

            // Push straight away so the dialog can appear while the app is open;
            // apps that are closed pick it up from the API on next launch.
            $result = $announcement->is_active
                ? $this->pushToAudience($announcement)
                : ['sent' => 0, 'failed' => 0];

            $message = 'Announcement created successfully.';
            if ($announcement->is_active) {
                $message .= " Notified {$result['sent']} {$announcement->audience}(s).";
            }

            return redirect()->route('announcements.index')->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Announcement create failed: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Something went wrong. Please try again.')->withInput();
        }
    }

    public function edit(Announcement $announcement)
    {
        return view('admin.announcements.edit', [
            'announcement' => $announcement,
            'audiences'    => Announcement::AUDIENCES,
        ]);
    }

    public function update(Request $request, Announcement $announcement)
    {
        $rules = [
            'title'       => 'required|string|max:255',
            'description' => 'required|string',
            'audience'    => 'required|in:' . implode(',', array_keys(Announcement::AUDIENCES)),
            'is_active'   => 'nullable|boolean',
        ];

        if ($request->hasFile('image')) {
            $rules['image'] = 'image|mimes:jpg,jpeg,png,webp|max:10240';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $data['is_active'] = $request->boolean('is_active', false);

        if ($request->hasFile('image')) {
            $this->deleteImage($announcement->image);
            $data['image'] = $this->storeImage($request->file('image'));
        } else {
            $data['image'] = $announcement->image;
        }

        $announcement->update($data);

        return redirect()->route('announcements.index')->with('success', 'Announcement updated successfully.');
    }

    /**
     * Flip active/inactive from the list screen. An inactive announcement stops
     * being served to the apps entirely — it neither pops up nor lists.
     */
    public function toggleStatus(Announcement $announcement)
    {
        $announcement->update(['is_active' => ! $announcement->is_active]);

        $state = $announcement->is_active ? 'activated' : 'deactivated';

        return redirect()->route('announcements.index')->with('success', "Announcement {$state} successfully.");
    }

    public function destroy(Announcement $announcement)
    {
        $this->deleteImage($announcement->image);
        $announcement->delete();

        return redirect()->route('announcements.index')->with('success', 'Announcement deleted successfully.');
    }

    private function storeImage($file): string
    {
        $filename = 'announcement_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('uploads/announcements/'), $filename);

        return 'uploads/announcements/' . $filename;
    }

    private function deleteImage(?string $path): void
    {
        if ($path && file_exists(public_path($path))) {
            @unlink(public_path($path));
        }
    }

    /**
     * Fan the announcement out to every device of the chosen audience.
     *
     * The payload is deliberately light — the app only needs `type` and
     * `announcement_id` to know it should refresh and show the dialog.
     */
    private function pushToAudience(Announcement $announcement): array
    {
        $query = match ($announcement->audience) {
            'driver' => Driver::query(),
            'vendor' => Vendor::query(),
            default  => Farmer::query(),
        };

        $imageUrl = $announcement->image ? asset($announcement->image) : null;
        $body = \Illuminate\Support\Str::limit(strip_tags($announcement->description), 180);

        $data = [
            'type'            => 'announcement',
            'announcement_id' => (string) $announcement->id,
            'audience'        => $announcement->audience,
        ];

        $fcm = new FirebaseNotificationService();
        $sent = 0;
        $failed = 0;

        $query->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->select(['id', 'fcm_token'])
            ->chunkById(200, function ($recipients) use ($fcm, $announcement, $body, $imageUrl, $data, &$sent, &$failed) {
                foreach ($recipients as $recipient) {
                    try {
                        $fcm->sendToDevice($recipient->fcm_token, $announcement->title, $body, $imageUrl, $data);
                        $sent++;
                    } catch (\Exception $e) {
                        Log::error("Announcement FCM failed for {$announcement->audience} #{$recipient->id}: " . $e->getMessage());
                        $failed++;
                    }
                }
            });

        return ['sent' => $sent, 'failed' => $failed];
    }
}
