<?php
namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\Hatchery;
use App\Models\HatcheryUpdate;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class UpdatesController extends Controller
{
    /**
     * 1️⃣ HOME → HATCHERY SHORT CARDS
     * GET /api/user/home-hatchery-updates
     */
    public function homeHatcheryUpdates(Request $request)
    {
        try {
            $query = Hatchery::active()->with(['vendor:id,name,mobile'])
                ->whereHas('updates', function ($q) {
                    // Show all active updates regardless of age. We intentionally
                    // do NOT filter on expires_at — the admin panel lists updates
                    // indefinitely, and the auto 60-day expiry was silently hiding
                    // older posts from the app.
                    $q->where('is_active', 1);
                })
                ->latest();

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->filled('location_id')) {
                $query->where('location_id', $request->location_id);
            }

            $hatcheries = $query->take(10)->get();

            $data = $hatcheries->map(function ($h) {

                // ⭐ NEW: Decode image array and take first image
                $images = is_string($h->image) ? json_decode($h->image, true) : [];
                $firstImage = $images[0] ?? null;

                return [
                    'hatchery_id'   => $h->id,
                    'hatchery_name' => $h->hatchery_name,
                    'vendor_id'     => $h->vendor_id,
                    'category_id'   => $h->category_id,
                    'location_id'   => $h->location_id,

                    'hatchery_logo' => $h->logo ? asset($h->logo) : null,
                    // 'profile_image' => $h->vendor?->profile_image
                    //     ? asset('uploads/vendors/' . $h->vendor->profile_image)
                    //     : null,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Hatchery cards fetched successfully.',
                'data' => $data,
            ]);

        } 
        // catch (Exception $e) {
        //     Log::error('Error homeHatcheryUpdates: ' . $e->getMessage());
        //     return response()->json(['status' => false, 'message' => 'Something went wrong nabbu.'], 500);
        // }

                catch (Exception $e) {
            Log::error("Error homeHatcheryUpdates: " . $e->getMessage());

            return response()->json([
                'status' => false,
                'error' => $e->getMessage(),  // 🔥 real error
                'line'  => $e->getLine(),
                'file'  => $e->getFile(),
            ], 500);
        }

    }


    /**
     * 2️⃣ UPDATES LIST (ALL UPDATES / FILTERED)
     * GET /api/user/hatchery-updates?category_id=&location_id=&hatchery_id=
     */
    public function allHatcheryUpdates(Request $request)
    {
        try {
            $query = HatcheryUpdate::with([
                'vendor:id,name,mobile',
                'hatchery:id,hatchery_name,logo,category_id,location_id,call_number,whatsapp_number,vendor_id,facebook_link',
                'location:id,location_name'
            ])
            // Show all active updates regardless of age (see homeHatcheryUpdates):
            // the auto 60-day expires_at was hiding older posts that admin still lists.
            ->where('is_active', 1)
            // Hide posts of INACTIVE hatcheries from users.
            ->whereHas('hatchery', fn($q) => $q->where('is_active', 1))
            ->latest();

            // FILTERS
            if ($request->filled('hatchery_id')) {
                $query->where('hatchery_id', $request->hatchery_id);
            }

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->filled('location_id')) {
                $query->where('location_id', $request->location_id);
            }

            $updates = $query->paginate(10);

            $data = $updates->map(function ($u) {

                $mobile = $u->vendor ? preg_replace('/\D/', '', $u->vendor->mobile) : null;

                // Build media_files array with full URLs
                $mediaFiles = [];
                $mediaTypes = $u->media_types ?? [];

                // Use new media_files array if available
                if (!empty($u->media_files) && is_array($u->media_files)) {
                    foreach ($u->media_files as $filePath) {
                        $mediaFiles[] = asset($filePath);
                    }
                }
                // Fallback to legacy single media_path
                elseif ($u->media_path) {
                    $mediaFiles[] = asset($u->media_path);
                    $mediaTypes[] = $u->media_type ?? 'image';
                }

                return [
                    'id'          => $u->id,
                    'title'       => $u->title,
                    'description' => $u->description ?? '',   // remove HTML
                    'hashtags'    => $u->hashtags ?? "",            // return string

                    // New: arrays of media files and types
                    'media_files' => $mediaFiles,
                    'media_types' => $mediaTypes,

                    // Legacy fields for backward compatibility
                    'media_type'  => $u->media_type,
                    'media_path'  => $u->media_path ? asset($u->media_path) : null,
                    'thumbnail'   => $u->image ? asset($u->image) : null,
                    'posted_on'   => $u->created_at->diffForHumans(),

                    'vendor' => [
                        'name' => $u->vendor?->name,
                        'mobile' => $u->vendor?->mobile,
                        'call_url' => $u->hatchery?->call_number ? "tel:+91" . $u->hatchery->call_number : ($mobile ? "tel:+$mobile" : null),
                        'whatsapp_url' => $u->hatchery?->whatsapp_number ? "https://wa.me/" . $u->hatchery->whatsapp_number : ($mobile ? "https://wa.me/$mobile" : null),
                        'facebook_url' => $u->hatchery?->facebook_link,
                    ],

                    'hatchery' => [
                        'id' => $u->hatchery_id,
                        'hatchery_name' => $u->hatchery?->hatchery_name,
                        'hatchery_logo' => $u->hatchery?->logo
                            ? asset($u->hatchery->logo)
                            : null,
                        'category_id' => $u->category_id,
                        'location_id' => $u->location_id,
                        'location_name' => $u->location?->location_name,
                    ],

                    'share_link' => url('/hatchery-update/' . $u->id),
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Hatchery updates fetched successfully.',
                'data' => $data,
                'pagination' => [
                    'current_page' => $updates->currentPage(),
                    'last_page' => $updates->lastPage(),
                    'per_page' => $updates->perPage(),
                    'total' => $updates->total(),
                ]
            ]);

        } 
        // catch (Exception $e) {
        //     Log::error('Error allHatcheryUpdates: ' . $e->getMessage());
        //     return response()->json(['status' => false, 'message' => 'Something went wrong.'], 500);
        // }

                catch (Exception $e) {
            Log::error("Error allHatcheryUpdates: " . $e->getMessage());

            return response()->json([
                'status' => false,
                'error' => $e->getMessage(),  // 🔥 real error
                'line'  => $e->getLine(),
                'file'  => $e->getFile(),
            ], 500);
        }



    }



    /**
     * 3️⃣ HATCHERY PROFILE (ONLY SELECTED HATCHERY POSTS)
     * GET /api/user/hatchery-profile/{hatchery_id}
     */
    public function hatcheryProfile($hatchery_id)
    {
        try {
            $hatchery = Hatchery::active()->with(['vendor', 'location:id,location_name'])->find($hatchery_id);

            if (!$hatchery) {
                return response()->json([
                    'status' => false,
                    'message' => 'Hatchery not found.',
                ], 404);
            }

            $updates = HatcheryUpdate::where('hatchery_id', $hatchery_id)
                // Show all active updates regardless of age (see homeHatcheryUpdates):
                // the auto 60-day expires_at was hiding older posts that admin still lists.
                ->where('is_active', 1)
                ->latest()
                ->get();

            $data = $updates->map(function ($u) use ($hatchery) {

                $mobile = $hatchery->vendor
                ? preg_replace('/\D/', '', $hatchery->vendor->mobile)
                : null;

                // Build media_files array with full URLs
                $mediaFiles = [];
                $mediaTypes = $u->media_types ?? [];

                // Use new media_files array if available
                if (!empty($u->media_files) && is_array($u->media_files)) {
                    foreach ($u->media_files as $filePath) {
                        $mediaFiles[] = asset($filePath);
                    }
                }
                // Fallback to legacy single media_path
                elseif ($u->media_path) {
                    $mediaFiles[] = asset($u->media_path);
                    $mediaTypes[] = $u->media_type ?? 'image';
                }

                return [
                    'id'          => $u->id,
                    'title'       => $u->title,
                    'description' => $u->description ?? '',
                    'hashtags'    => $u->hashtags ?? "",

                    // New: arrays of media files and types
                    'media_files' => $mediaFiles,
                    'media_types' => $mediaTypes,

                    // Legacy fields for backward compatibility
                    'media_type'  => $u->media_type,
                    'media_path'  => $u->media_path ? asset($u->media_path) : null,
                    'thumbnail'   => $u->image ? asset($u->image) : null,
                    'posted_on'   => $u->created_at->diffForHumans(),

                     'vendor' => [
                    'name' => $hatchery->vendor->name ?? null,
                    'mobile' => $hatchery->vendor->mobile ?? null,
                    'call_url' => $hatchery->call_number ? "tel:+91" . $hatchery->call_number : ($mobile ? "tel:+$mobile" : null),
                    'whatsapp_url' => $hatchery->whatsapp_number ? "https://wa.me/" . $hatchery->whatsapp_number : ($mobile ? "https://wa.me/$mobile" : null),
                    'facebook_url' => $hatchery->facebook_link,
                ],

                    'hatchery' => [
                        'id' => $hatchery->id,
                        'hatchery_name' => $hatchery->hatchery_name,
                        'hatchery_logo' => $hatchery->logo
                            ? asset($hatchery->logo)
                            : null,
                        'category_id' => $hatchery->category_id,
                        'location_id' => $hatchery->location_id,
                        'location_name' => $hatchery->location->location_name ?? null,
                    ],

                    'share_link' => url('/hatchery-update/' . $u->id),
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Hatchery profile posts fetched successfully.',
                'data' => $data,
            ]);

        }
        
        // catch (Exception $e) {
        //     Log::error('Error hatcheryProfile: ' . $e->getMessage());
        //     return response()->json(['status' => false, 'message' => 'Something went wrong.'], 500);
        // }

                    catch (Exception $e) {
                Log::error("Error hatcheryProfile: " . $e->getMessage());

                return response()->json([
                    'status' => false,
                    'error' => $e->getMessage(),  // 🔥 real error
                    'line'  => $e->getLine(),
                    'file'  => $e->getFile(),
                ], 500);
            }





    }
}
