<?php

namespace App\Http\Controllers\Admin;

use App\Models\HatcheryPost;
use App\Http\Controllers\Controller;
use App\Models\HatcheryLocation;
use App\Models\Hatchery;
use App\Models\HatcheryUpdate;
use App\Models\HatcheryCategory;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Helpers\VideoCompressor;

class HatcheryPostController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:hatcheries.view')->only(['index', 'show']);
        $this->middleware('permission:hatcheries.create')->only(['create', 'store']);
        $this->middleware('permission:hatcheries.update')->only(['edit', 'update']);
        $this->middleware('permission:hatcheries.delete')->only(['destroy']);
    }

    /**
     * Display a listing of the posts.
     */
    public function index()
    {
        $posts = HatcheryUpdate::latest()->get();
        $hatcheries = Hatchery::excludingSpot()->with(['category:id,category_name', 'location:id,location_name'])->get();
        return view('admin.hatchery-updates.index', compact('posts', 'hatcheries'));
    }

    /**
     * Show the form for creating a new post.
     */
    public function create()
    {
        $hatcheries = Hatchery::excludingSpot()->with(['category:id,category_name', 'location:id,location_name'])->get();
        $categories = HatcheryCategory::all();
        $locations = HatcheryLocation::whereNull('farmer_id')->orderBy('priority')->get();
        $vendors = Vendor::all();  
        return view('admin.hatchery-updates.create', compact('hatcheries', 'categories', 'locations','vendors'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'hatchery_id' => 'required|exists:hatcheries,id',
            'vendor_id' => 'required|exists:vendors,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            // Thumbnail/logo image
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            // Multiple media files support
            'media_files' => 'required|array|min:1',
            'media_files.*' => 'file|max:51200|mimetypes:image/jpeg,image/png,image/jpg,video/mp4,video/avi',
            'hashtags' => 'nullable|string',
            'is_active' => 'required|boolean',
            'category_id' => 'required|exists:hatchery_categories,id',
            'location_id' => 'nullable|exists:hatchery_locations,id',
            'expires_at' => 'nullable|date',
        ]);

        $mediaFiles = [];
        $mediaTypes = [];
        $firstMediaPath = null;
        $firstMediaType = null;
        $thumbnailPath = null;

        $allowedImage = ['jpg', 'jpeg', 'png'];
        $allowedVideo = ['mp4', 'avi'];
        $folder = 'uploads/updates/';
        $thumbnailFolder = 'uploads/updates/thumbnails/';

        if (!file_exists(public_path($folder))) {
            mkdir(public_path($folder), 0777, true);
        }
        if (!file_exists(public_path($thumbnailFolder))) {
            mkdir(public_path($thumbnailFolder), 0777, true);
        }

        // Process separate thumbnail upload
        if ($request->hasFile('thumbnail')) {
            $thumbnailFile = $request->file('thumbnail');
            $thumbnailFilename = time() . '_thumb_' . uniqid() . '.' . $thumbnailFile->getClientOriginalExtension();
            $thumbnailFile->move(public_path($thumbnailFolder), $thumbnailFilename);
            $thumbnailPath = $thumbnailFolder . $thumbnailFilename;
        }

        // Process multiple media files
        if ($request->hasFile('media_files')) {
            foreach ($request->file('media_files') as $file) {
                $extension = strtolower($file->getClientOriginalExtension());

                // Determine media type based on extension
                if (in_array($extension, $allowedImage)) {
                    $type = 'image';
                } elseif (in_array($extension, $allowedVideo)) {
                    $type = 'video';
                } else {
                    continue; // Skip unsupported files
                }

                $filename = time() . '_' . uniqid() . '.' . $extension;
                $file->move(public_path($folder), $filename);
                $filePath = $folder . $filename;
                $filePath = VideoCompressor::compress($filePath);

                $mediaFiles[] = $filePath;
                $mediaTypes[] = $type;

                // Keep first file for backward compatibility
                if ($firstMediaPath === null) {
                    $firstMediaPath = $filePath;
                    $firstMediaType = $type;
                }

                // If no separate thumbnail uploaded, use first image as fallback
                if ($thumbnailPath === null && $type === 'image') {
                    $thumbnailPath = $filePath;
                }
            }
        }

        HatcheryUpdate::create([
            'hatchery_id' => $request->hatchery_id,
            'vendor_id' => $request->vendor_id,
            'title' => $request->title,
            'description' => $request->description,
            'media_path' => $firstMediaPath, // Backward compatibility
            'media_type' => $firstMediaType, // Backward compatibility
            'media_files' => $mediaFiles, // New: array of file paths
            'media_types' => $mediaTypes, // New: array of types
            'hashtags' => $request->hashtags,
            'is_active' => $request->is_active,
            'image' => $thumbnailPath, // Use first image as thumbnail
            'category_id' => $request->category_id,
            'location_id' => $request->location_id,
            'expires_at' => now()->addDays(60),
        ]);

        return redirect()
            ->route('hatchery-updates.index')
            ->with('success', 'Post created successfully.');
    }



    //debugged and improved method (harsha)
    // public function store(Request $request)
    // {
    //     Log::info("🔥 HatcheryUpdate Store() started", ['request' => $request->all()]);

    //     try {

    //         Log::debug("📌 Step 1: Validating request...");
    //         $validated = $request->validate([
    //             'hatchery_id' => 'required|exists:hatcheries,id',
    //             'title' => 'required|string|max:255',
    //             'description' => 'nullable|string',
    //             'media_type' => 'required|in:image,video',

    //             'media_path' => $request->media_type === 'image'
    //                 ? 'required|image|mimes:jpeg,png,jpg|max:51200'
    //                 : 'required|mimetypes:video/mp4,video/avi|max:51200',

    //             'image' => $request->media_type === 'video'
    //                 ? 'required|image|mimes:jpeg,png,jpg|max:5120'
    //                 : 'nullable',

    //             'hashtags' => 'nullable|string',
    //             'is_active' => 'required|boolean',
    //             'category_id' => 'required|exists:hatchery_categories,id',
    //             'location_id' => 'required|exists:hatchery_locations,id',
    //             'expires_at' => 'nullable|date',
    //         ]);

    //         Log::debug("✅ Step 1 Completed: Validation Successful");

    //         $mediaPath = null;
    //         $thumbnailPath = null;

    //         Log::debug("📌 Step 2: Checking main media upload...");

    //         if ($request->hasFile('media_path')) {

    //             Log::debug("➡️ media_path file detected");
    //             $file = $request->file('media_path');
    //             $extension = strtolower($file->getClientOriginalExtension());

    //             Log::debug("📄 Uploaded media extension", ['extension' => $extension]);

    //             $allowedImage = ['jpg', 'jpeg', 'png'];
    //             $allowedVideo = ['mp4', 'avi'];

    //             if ($request->media_type === 'image' && !in_array($extension, $allowedImage)) {
    //                 Log::error("❌ Invalid Image Format", ['extension' => $extension]);
    //                 return back()->withErrors(['media_path' => 'Invalid image format.']);
    //             }

    //             if ($request->media_type === 'video' && !in_array($extension, $allowedVideo)) {
    //                 Log::error("❌ Invalid Video Format", ['extension' => $extension]);
    //                 return back()->withErrors(['media_path' => 'Invalid video format.']);
    //             }

    //             $filename = time() . '_' . uniqid() . '.' . $extension;
    //             $folder = 'uploads/updates/';

    //             Log::debug("📁 Saving media to folder", ['folder' => $folder]);

    //             if (!file_exists(public_path($folder))) {
    //                 mkdir(public_path($folder), 0777, true);
    //                 Log::debug("📂 Folder created", ['folder' => $folder]);
    //             }

    //             $file->move(public_path($folder), $filename);
    //             $mediaPath = $folder . $filename;

    //             Log::debug("✅ Media saved successfully", ['media_path' => $mediaPath]);
    //         }

    //         Log::debug("📌 Step 3: Checking thumbnail (only for videos)...");

    //         if ($request->media_type === 'video' && $request->hasFile('image')) {

    //             Log::debug("➡️ Thumbnail image detected");

    //             $image = $request->file('image');
    //             $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
    //             $imageFolder = 'uploads/updates/thumbnails/';

    //             Log::debug("📁 Saving thumbnail", ['folder' => $imageFolder]);

    //             if (!file_exists(public_path($imageFolder))) {
    //                 mkdir(public_path($imageFolder), 0777, true);
    //                 Log::debug("📂 Thumbnail folder created");
    //             }

    //             $image->move(public_path($imageFolder), $imageName);
    //             $thumbnailPath = $imageFolder . $imageName;

    //             Log::debug("✅ Thumbnail saved", ['thumbnail' => $thumbnailPath]);
    //         }

    //         Log::debug("📌 Step 4: Creating HatcheryUpdate record...");

    //         $createData = [
    //             'hatchery_id' => $request->hatchery_id,
    //             'title' => $request->title,
    //             'description' => $request->description,
    //             'media_path' => $mediaPath,
    //             'media_type' => $request->media_type,
    //             'hashtags' => $request->hashtags,
    //             'is_active' => $request->is_active,
    //             'image' => $thumbnailPath,
    //             'category_id' => $request->category_id,
    //             'location_id' => $request->location_id,
    //             'expires_at' => now()->addDays(60),
    //         ];

    //         Log::debug("📝 Insert data", $createData);

    //         HatcheryUpdate::create($createData);

    //         Log::info("🎉 HatcheryUpdate Created Successfully!");

    //         return redirect()
    //             ->route('hatchery-updates.index')
    //             ->with('success', 'Post created successfully.');

    //     } catch (\Exception $e) {

    //         Log::error("💀 ERROR in HatcheryUpdate Store()", [
    //             'error_message' => $e->getMessage(),
    //             'line' => $e->getLine(),
    //             'file' => $e->getFile(),
    //             'trace' => $e->getTraceAsString(),
    //         ]);

    //         return back()->withErrors([
    //             'error' => "Something went wrong: " . $e->getMessage()
    //         ])->withInput();
    //     }
    // }






    /**
     * Show the form for editing the specified post.
     */
    public function edit($id)
    {
        $post = HatcheryUpdate::findOrFail($id);
        $hatcheries = Hatchery::excludingSpot()->with(['category:id,category_name', 'location:id,location_name'])->get();
        $categories = HatcheryCategory::all();
        $locations = HatcheryLocation::whereNull('farmer_id')->orderBy('priority')->get();
        $vendors = Vendor::all();  
        return view('admin.hatchery-updates.edit', compact('post', 'hatcheries', 'categories', 'locations', 'vendors'));
    }

    /**
     * Update the specified post in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:hatchery_categories,id',
            'location_id' => 'nullable|exists:hatchery_locations,id',
            // Thumbnail/logo image
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'media_files' => 'nullable|array',
            'media_files.*' => 'file|max:51200|mimetypes:image/jpeg,image/png,image/jpg,video/mp4,video/avi',
            'hashtags' => 'nullable|string',
            'is_active' => 'required|boolean',
            'hatchery_id' => 'required|exists:hatcheries,id',
            'vendor_id' => 'required|exists:vendors,id',
            'remove_media' => 'nullable|array', // Array of file paths to remove
        ]);

        $post = HatcheryUpdate::findOrFail($id);

        $allowedImage = ['jpg', 'jpeg', 'png'];
        $allowedVideo = ['mp4', 'avi'];
        $folder = 'uploads/updates/';
        $thumbnailFolder = 'uploads/updates/thumbnails/';

        if (!file_exists(public_path($folder))) {
            mkdir(public_path($folder), 0777, true);
        }
        if (!file_exists(public_path($thumbnailFolder))) {
            mkdir(public_path($thumbnailFolder), 0777, true);
        }

        // Get existing media files
        $mediaFiles = $post->media_files ?? [];
        $mediaTypes = $post->media_types ?? [];

        // Process separate thumbnail upload
        $thumbnailPath = $post->image; // Keep existing thumbnail by default
        if ($request->hasFile('thumbnail')) {
            // Delete old thumbnail if exists
            if ($post->image && file_exists(public_path($post->image))) {
                unlink(public_path($post->image));
            }
            $thumbnailFile = $request->file('thumbnail');
            $thumbnailFilename = time() . '_thumb_' . uniqid() . '.' . $thumbnailFile->getClientOriginalExtension();
            $thumbnailFile->move(public_path($thumbnailFolder), $thumbnailFilename);
            $thumbnailPath = $thumbnailFolder . $thumbnailFilename;
        }

        // Remove selected media files
        if ($request->has('remove_media') && is_array($request->remove_media)) {
            foreach ($request->remove_media as $removeFile) {
                $index = array_search($removeFile, $mediaFiles);
                if ($index !== false) {
                    // Delete file from disk
                    if (file_exists(public_path($removeFile))) {
                        unlink(public_path($removeFile));
                    }
                    // Remove from arrays
                    array_splice($mediaFiles, $index, 1);
                    array_splice($mediaTypes, $index, 1);
                }
            }
        }

        // Add new media files
        if ($request->hasFile('media_files')) {
            foreach ($request->file('media_files') as $file) {
                $extension = strtolower($file->getClientOriginalExtension());

                // Determine media type based on extension
                if (in_array($extension, $allowedImage)) {
                    $type = 'image';
                } elseif (in_array($extension, $allowedVideo)) {
                    $type = 'video';
                } else {
                    continue; // Skip unsupported files
                }

                $filename = time() . '_' . uniqid() . '.' . $extension;
                $file->move(public_path($folder), $filename);
                $filePath = $folder . $filename;
                $filePath = VideoCompressor::compress($filePath);

                $mediaFiles[] = $filePath;
                $mediaTypes[] = $type;
            }
        }

        // Re-index arrays
        $mediaFiles = array_values($mediaFiles);
        $mediaTypes = array_values($mediaTypes);

        // Update backward compatibility fields with first file
        $firstMediaPath = $mediaFiles[0] ?? null;
        $firstMediaType = $mediaTypes[0] ?? null;

        // If no separate thumbnail uploaded and no existing thumbnail, use first image as fallback
        if (empty($thumbnailPath)) {
            foreach ($mediaTypes as $index => $type) {
                if ($type === 'image') {
                    $thumbnailPath = $mediaFiles[$index];
                    break;
                }
            }
        }

        $post->update([
            'hatchery_id' => $request->hatchery_id,
            'vendor_id' => $request->vendor_id,
            'title' => $request->title,
            'description' => $request->description,
            'media_path' => $firstMediaPath,
            'media_type' => $firstMediaType,
            'media_files' => $mediaFiles,
            'media_types' => $mediaTypes,
            'hashtags' => $request->hashtags,
            'is_active' => $request->is_active,
            'image' => $thumbnailPath, // Use first image as thumbnail
            'category_id' => $request->category_id,
            'location_id' => $request->location_id,
        ]);

        return redirect()
            ->route('hatchery-updates.index')
            ->with('success', 'Post updated successfully.');
    }

    /**
     * Remove the specified post from storage.
     */
    // public function destroy($id)
    // {
    //     $post = HatcheryUpdate::findOrFail($id);
    //     $post->delete();

    //     return redirect()->route('hatchery-updates.index')->with('success', 'Post deleted successfully.');
    // }


    //nabbu_method
    public function destroy($id)
    {
        $post = HatcheryUpdate::findOrFail($id);

        // Delete all media files
        if (!empty($post->media_files) && is_array($post->media_files)) {
            foreach ($post->media_files as $filePath) {
                if ($filePath && file_exists(public_path($filePath))) {
                    unlink(public_path($filePath));
                }
            }
        }

        // Delete legacy single media file (backward compatibility)
        if ($post->media_path && file_exists(public_path($post->media_path))) {
            unlink(public_path($post->media_path));
        }

        // Delete thumbnail (video thumbnail)
        if ($post->image && file_exists(public_path($post->image))) {
            unlink(public_path($post->image));
        }

        $post->delete();

        return redirect()
            ->route('hatchery-updates.index')
            ->with('success', 'Post deleted successfully.');
    }

}
