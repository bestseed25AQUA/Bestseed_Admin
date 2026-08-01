<?php

namespace App\Http\Controllers\Admin;

use App\Models\News;
use App\Http\Controllers\Controller;
use App\Models\Hatchery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Helpers\VideoCompressor;

class NewsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:news.view')->only(['index', 'show']);
        $this->middleware('permission:news.create')->only(['create', 'store']);
        $this->middleware('permission:news.update')->only(['edit', 'update']);
        $this->middleware('permission:news.delete')->only(['destroy']);
    }

    /**
     * Display a listing of the news posts.
     */
    public function index()
    {
        $posts = News::with('category')->latest()->get();
        $hatcheries = Hatchery::excludingSpot()->get();
        return view('admin.news.index', compact('posts', 'hatcheries'));
    }

    /**
     * Show the form for creating a new news post.
     */
    public function create()
    {
        $hatcheries = Hatchery::excludingSpot()->get();
        return view('admin.news.create', compact('hatcheries'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'category_id' => 'nullable|integer',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'media_files' => 'required|array|min:1',
                'media_files.*' => 'file|max:51200|mimetypes:image/jpeg,image/png,image/jpg,video/mp4,video/avi',
                'hashtags' => 'nullable|string',
                'is_active' => 'required|in:0,1',
            ]);

            $categories = [
                1 => 'trending update',
                2 => 'medicine news',
                3 => 'climate news',
            ];

            $type = $categories[$request->category_id] ?? null;

            $mediaFiles = [];
            $mediaTypes = [];
            $firstMediaPath = null;
            $firstMediaType = null;
            $thumbnailPath = null;

            $allowedImage = ['jpg', 'jpeg', 'png'];
            $allowedVideo = ['mp4', 'avi'];
            $folder = 'uploads/updates/';

            if (!file_exists(public_path($folder))) {
                mkdir(public_path($folder), 0777, true);
            }

            if ($request->hasFile('media_files')) {
                foreach ($request->file('media_files') as $file) {
                    $extension = strtolower($file->getClientOriginalExtension());

                    if (in_array($extension, $allowedImage)) {
                        $fileType = 'image';
                    } elseif (in_array($extension, $allowedVideo)) {
                        $fileType = 'video';
                    } else {
                        continue;
                    }

                    $filename = time() . '_' . uniqid() . '.' . $extension;
                    $file->move(public_path($folder), $filename);
                    $filePath = $folder . $filename;
                    $filePath = VideoCompressor::compress($filePath);

                    $mediaFiles[] = $filePath;
                    $mediaTypes[] = $fileType;

                    if ($firstMediaPath === null) {
                        $firstMediaPath = $filePath;
                        $firstMediaType = $fileType;
                    }

                    if ($thumbnailPath === null && $fileType === 'image') {
                        $thumbnailPath = $filePath;
                    }
                }
            }

            $newsData = [
                'hatchery_id' => $request->hatchery_id,
                'category_id' => null,
                'type' => $type,
                'title' => $request->title,
                'description' => $request->description,
                'media_path' => $firstMediaPath,
                'media_type' => $firstMediaType,
                'media_files' => $mediaFiles,
                'media_types' => $mediaTypes,
                'hashtags' => $request->hashtags,
                'is_active' => $request->is_active,
                'image' => $thumbnailPath,
            ];

            // Add subtitle for medicine news & climate news
            if (in_array($type, ['medicine news', 'climate news'])) {
                $newsData['subtitle'] = $request->subtitle;
            }

            // Add contact fields for medicine news only
            if ($type === 'medicine news') {
                $newsData['call_number'] = $request->call_number;
                $newsData['whatsapp_number'] = $request->whatsapp_number;
            }

            News::create($newsData);

            return redirect()
                ->route('news.index')
                ->with('success', 'Post created successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            \Log::error('Error creating news post: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with('error', 'Post creation failed. Please try again or contact support.')
                ->withInput();
        }
    }


//     public function store(Request $request)
// {
//     Log::info("🟦 [NEWS STORE] Store method called", [
//         'input' => $request->all()
//     ]);

//     try {
//         Log::info("🟦 [NEWS STORE] Validating input...");

//         $request->validate([
//             'category_id' => 'nullable|integer',
//             'title' => 'required|string|max:255',
//             'description' => 'nullable|string',
//             'media_type' => 'required|in:image,video',
//             'media_path' => 'nullable|file|max:51200|mimetypes:image/jpeg,image/png,video/mp4,video/avi',
//             'image' => $request->media_type === 'video'
//                 ? 'required|file|image|mimes:jpeg,png,jpg|max:5120'
//                 : 'nullable|file|image|mimes:jpeg,png,jpg|max:5120',
//             'hashtags' => 'nullable|string',
//             'is_active' => 'required|boolean',
//         ]);

//         Log::info("🟩 [NEWS STORE] Validation success");

//         $categories = [
//             1 => 'trending update',
//             2 => 'medicine news',
//             3 => 'climate news',
//         ];

//         $type = $categories[$request->category_id] ?? null;

//         Log::info("🟦 [NEWS STORE] Category & type resolved", [
//             'category_id' => $request->category_id,
//             'type' => $type
//         ]);

//         $mediaPath = null;
//         $thumbnailPath = null;

//         // 🔵 Upload main media (image/video)
//         if ($request->hasFile('media_path')) {
//             Log::info("🟦 [NEWS STORE] Uploading media file");

//             $file = $request->file('media_path');
//             $extension = strtolower($file->getClientOriginalExtension());
//             $allowedImage = ['jpg', 'jpeg', 'png'];
//             $allowedVideo = ['mp4', 'avi'];

//             if ($request->media_type === 'image' && !in_array($extension, $allowedImage)) {
//                 Log::error("❌ Invalid image format uploaded", compact('extension'));
//                 return back()->withErrors(['media_path' => 'Invalid image format.']);
//             }

//             if ($request->media_type === 'video' && !in_array($extension, $allowedVideo)) {
//                 Log::error("❌ Invalid video format uploaded", compact('extension'));
//                 return back()->withErrors(['media_path' => 'Invalid video format.']);
//             }

//             $filename = time() . '_' . uniqid() . '.' . $extension;
//             $folder = 'uploads/updates/';

//             if (!file_exists(public_path($folder))) {
//                 mkdir(public_path($folder), 0777, true);
//                 Log::info("🟦 Folder created", ['path' => $folder]);
//             }

//             $file->move(public_path($folder), $filename);
//             $mediaPath = $folder . $filename;

//             Log::info("🟩 [NEWS STORE] Media upload success", [
//                 'media_path' => $mediaPath
//             ]);
//         }

//         // 🔵 Upload thumbnail if video
//         if ($request->media_type === 'video' && $request->hasFile('image')) {
//             Log::info("🟦 [NEWS STORE] Uploading video thumbnail");

//             $image = $request->file('image');
//             $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
//             $imageFolder = 'uploads/updates/thumbnails/';

//             if (!file_exists(public_path($imageFolder))) {
//                 mkdir(public_path($imageFolder), 0777, true);
//                 Log::info("🟦 Thumbnail folder created", ['path' => $imageFolder]);
//             }

//             $image->move(public_path($imageFolder), $imageName);
//             $thumbnailPath = $imageFolder . $imageName;

//             Log::info("🟩 [NEWS STORE] Thumbnail upload success", [
//                 'thumbnail_path' => $thumbnailPath
//             ]);
//         }

//         // 🔵 Create News Record
//         Log::info("🟦 [NEWS STORE] Creating record in DB");

//         $news = News::create([
//             'hatchery_id' => $request->hatchery_id,
//             'category_id' => $request->category_id,
//             'type' => $type,
//             'title' => $request->title,
//             'description' => $request->description,
//             'media_path' => $mediaPath,
//             'media_type' => $request->media_type,
//             'hashtags' => $request->hashtags,
//             'is_active' => $request->is_active,
//             'image' => $thumbnailPath,
//         ]);

//         Log::info("🟩 [NEWS STORE] News created successfully", [
//             'news_id' => $news->id
//         ]);

//         return redirect()
//             ->route('news.index')
//             ->with('success', 'Post created successfully.');

//     } catch (\Illuminate\Validation\ValidationException $e) {

//         Log::error("❌ [NEWS STORE] Validation failed", [
//             'errors' => $e->errors()
//         ]);

//         return back()->withErrors($e->errors())->withInput();

//     } catch (\Exception $e) {

//         Log::error("❌ [NEWS STORE] Unexpected error", [
//             'error' => $e->getMessage(),
//             'line' => $e->getLine(),
//             'file' => $e->getFile(),
//             'trace' => $e->getTraceAsString()
//         ]);

//         return back()
//             ->with('error', 'Post creation failed. Error: ' . $e->getMessage())
//             ->withInput();
//     }
// }








    /**
     * Show the form for editing the specified news post.
     */
    public function edit($id)
    {
        $post = News::findOrFail($id);
        $hatcheries = Hatchery::excludingSpot()->get();
        return view('admin.news.edit', compact('post', 'hatcheries'));
    }

    /**
     * Update the specified news post in storage.
     */
public function update(Request $request, $id)
{
    try {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'hashtags' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'hatchery_id' => 'nullable|exists:hatcheries,id',
            'media_files' => 'nullable|array',
            'media_files.*' => 'file|max:51200|mimetypes:image/jpeg,image/png,image/jpg,video/mp4,video/avi',
            'remove_media' => 'nullable|array',
        ]);

        $post = News::findOrFail($id);

        $categories = [
            1 => 'trending update',
            2 => 'medicine news',
            3 => 'climate news',
        ];

        $type = $categories[$request->category_id] ?? $post->type;

        // Get existing media arrays
        $mediaFiles = $post->media_files ?? [];
        $mediaTypes = $post->media_types ?? [];

        $allowedImage = ['jpg', 'jpeg', 'png'];
        $allowedVideo = ['mp4', 'avi'];
        $folder = 'uploads/updates/';

        if (!file_exists(public_path($folder))) {
            mkdir(public_path($folder), 0777, true);
        }

        // Remove selected media files
        if ($request->has('remove_media') && is_array($request->remove_media)) {
            foreach ($request->remove_media as $removeFile) {
                $index = array_search($removeFile, $mediaFiles);
                if ($index !== false) {
                    if (file_exists(public_path($removeFile))) {
                        unlink(public_path($removeFile));
                    }
                    array_splice($mediaFiles, $index, 1);
                    array_splice($mediaTypes, $index, 1);
                }
            }
        }

        // Add new media files
        if ($request->hasFile('media_files')) {
            foreach ($request->file('media_files') as $file) {
                $extension = strtolower($file->getClientOriginalExtension());

                if (in_array($extension, $allowedImage)) {
                    $fileType = 'image';
                } elseif (in_array($extension, $allowedVideo)) {
                    $fileType = 'video';
                } else {
                    continue;
                }

                $filename = time() . '_' . uniqid() . '.' . $extension;
                $file->move(public_path($folder), $filename);
                $filePath = $folder . $filename;
                $filePath = VideoCompressor::compress($filePath);

                $mediaFiles[] = $filePath;
                $mediaTypes[] = $fileType;
            }
        }

        // Re-index arrays
        $mediaFiles = array_values($mediaFiles);
        $mediaTypes = array_values($mediaTypes);

        // Update backward compatibility fields with first file
        $firstMediaPath = $mediaFiles[0] ?? null;
        $firstMediaType = $mediaTypes[0] ?? null;

        $updateData = [
            'hatchery_id' => $request->hatchery_id,
            'category_id' => null,
            'type' => $type,
            'title' => $request->title,
            'description' => $request->description,
            'media_path' => $firstMediaPath,
            'media_type' => $firstMediaType,
            'media_files' => $mediaFiles,
            'media_types' => $mediaTypes,
            'hashtags' => $request->hashtags,
            'is_active' => $request->is_active,
        ];

        // Add subtitle for medicine news & climate news
        if (in_array($type, ['medicine news', 'climate news'])) {
            $updateData['subtitle'] = $request->subtitle;
        } else {
            $updateData['subtitle'] = null;
        }

        // Add contact fields for medicine news only
        if ($type === 'medicine news') {
            $updateData['call_number'] = $request->call_number;
            $updateData['whatsapp_number'] = $request->whatsapp_number;
        } else {
            $updateData['call_number'] = null;
            $updateData['whatsapp_number'] = null;
        }

        $post->update($updateData);

        return redirect()
            ->route('news.index')
            ->with('success', 'Post updated successfully.');

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return redirect()->route('news.index')->withErrors('Post not found.');
    } catch (\Illuminate\Validation\ValidationException $e) {
        return back()->withErrors($e->errors())->withInput();
    } catch (\Exception $e) {
        \Log::error('Error updating post: ' . $e->getMessage());
        return back()->withErrors('An unexpected error occurred: ' . $e->getMessage())->withInput();
    }
}


    /**
     * Remove the specified news post from storage.
     */
    public function destroy($id)
    {
        $post = News::findOrFail($id);

        // Delete media files from disk
        if (!empty($post->media_files) && is_array($post->media_files)) {
            foreach ($post->media_files as $filePath) {
                if ($filePath && file_exists(public_path($filePath))) {
                    unlink(public_path($filePath));
                }
            }
        }
        if ($post->media_path && file_exists(public_path($post->media_path))) {
            unlink(public_path($post->media_path));
        }
        if ($post->image && file_exists(public_path($post->image))) {
            unlink(public_path($post->image));
        }

        $post->delete();

        return redirect()->route('news.index')->with('success', 'Post deleted successfully.');
    }
}
