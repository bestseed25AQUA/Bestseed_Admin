<?php

namespace App\Http\Controllers\Admin;

use App\Models\BestDeal;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helpers\VideoCompressor;

class BestDealController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:news.view')->only(['index', 'show']);
        $this->middleware('permission:news.create')->only(['create', 'store']);
        $this->middleware('permission:news.update')->only(['edit', 'update']);
        $this->middleware('permission:news.delete')->only(['destroy']);
    }

    public function index()
    {
        $deals = BestDeal::orderBy('sort_order', 'asc')->latest()->get();
        return view('admin.best_deals.index', compact('deals'));
    }

    public function create()
    {
        return view('admin.best_deals.create');
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'subtitle' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'media_files' => 'required|array|min:1',
                'media_files.*' => 'file|max:51200|mimetypes:image/jpeg,image/png,image/jpg,video/mp4,video/avi',
                'call_number' => 'nullable|string',
                'whatsapp_number' => 'nullable|string',
                'is_active' => 'required|in:0,1',
                'sort_order' => 'nullable|integer',
            ]);

            $mediaFiles = [];
            $mediaTypes = [];
            $firstMediaPath = null;
            $firstMediaType = null;

            $allowedImage = ['jpg', 'jpeg', 'png'];
            $allowedVideo = ['mp4', 'avi'];
            $folder = 'uploads/best_deals/';

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
                }
            }

            BestDeal::create([
                'title' => $request->title,
                'subtitle' => $request->subtitle,
                'description' => $request->description,
                'media_path' => $firstMediaPath,
                'media_type' => $firstMediaType,
                'media_files' => $mediaFiles,
                'media_types' => $mediaTypes,
                'call_number' => $request->call_number,
                'whatsapp_number' => $request->whatsapp_number,
                'is_active' => $request->is_active,
                'sort_order' => $request->sort_order ?? 0,
            ]);

            return redirect()
                ->route('best-deals.index')
                ->with('success', 'Best Deal created successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            \Log::error('Error creating best deal: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with('error', 'Failed to create best deal. Please try again.')
                ->withInput();
        }
    }

    public function edit($id)
    {
        $deal = BestDeal::findOrFail($id);
        return view('admin.best_deals.edit', compact('deal'));
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'subtitle' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'media_files' => 'nullable|array',
                'media_files.*' => 'file|max:51200|mimetypes:image/jpeg,image/png,image/jpg,video/mp4,video/avi',
                'remove_media' => 'nullable|array',
                'call_number' => 'nullable|string',
                'whatsapp_number' => 'nullable|string',
                'is_active' => 'required|in:0,1',
                'sort_order' => 'nullable|integer',
            ]);

            $deal = BestDeal::findOrFail($id);

            $mediaFiles = $deal->media_files ?? [];
            $mediaTypes = $deal->media_types ?? [];

            $allowedImage = ['jpg', 'jpeg', 'png'];
            $allowedVideo = ['mp4', 'avi'];
            $folder = 'uploads/best_deals/';

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

            $mediaFiles = array_values($mediaFiles);
            $mediaTypes = array_values($mediaTypes);

            $firstMediaPath = $mediaFiles[0] ?? null;
            $firstMediaType = $mediaTypes[0] ?? null;

            $deal->update([
                'title' => $request->title,
                'subtitle' => $request->subtitle,
                'description' => $request->description,
                'media_path' => $firstMediaPath,
                'media_type' => $firstMediaType,
                'media_files' => $mediaFiles,
                'media_types' => $mediaTypes,
                'call_number' => $request->call_number,
                'whatsapp_number' => $request->whatsapp_number,
                'is_active' => $request->is_active,
                'sort_order' => $request->sort_order ?? 0,
            ]);

            return redirect()
                ->route('best-deals.index')
                ->with('success', 'Best Deal updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            \Log::error('Error updating best deal: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update. Please try again.')->withInput();
        }
    }

    public function destroy($id)
    {
        $deal = BestDeal::findOrFail($id);

        if (!empty($deal->media_files) && is_array($deal->media_files)) {
            foreach ($deal->media_files as $filePath) {
                if ($filePath && file_exists(public_path($filePath))) {
                    unlink(public_path($filePath));
                }
            }
        }

        $deal->delete();

        return redirect()->route('best-deals.index')->with('success', 'Best Deal deleted successfully.');
    }
}
