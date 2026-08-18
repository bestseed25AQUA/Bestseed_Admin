<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Hatchery;
use App\Models\Banner;
use App\Models\HatcheryCategory;
use Illuminate\Support\Facades\Validator;
use App\Helpers\VideoCompressor;

class BannerController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:banners.view')->only(['index', 'show']);
        $this->middleware('permission:banners.create')->only(['create', 'store']);
        $this->middleware('permission:banners.update')->only(['edit', 'update']);
        $this->middleware('permission:banners.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $tab = $request->query('tab', 'best_deals');

        $query = Banner::with('category');

        if ($request->filled('screen')) {
            $query->where('screen', $request->screen);
        } elseif ($tab === 'home') {
            $query->where(function ($q) {
                $q->where('screen', '!=', 'home_best_deals')->orWhereNull('screen');
            });
        } else {
            $tab = 'best_deals';
            $query->where('screen', 'home_best_deals');
        }

        $banners = $query->orderByDesc('id')->get();

        return view('admin.banners.index', compact('banners', 'tab'));
    }

    public function create()
    {
        $data = Hatchery::excludingSpot()->with(['category:id,category_name', 'location:id,location_name'])->get();
        $categories = HatcheryCategory::all();
        return view('admin.banners.create', compact('categories', 'data'));
    }
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'category_id' => 'nullable',
                'hatchery_id'  => 'nullable',
                'title'        => 'nullable|string',
                'image'        => 'nullable|mimes:jpg,jpeg,png,webp,gif,mp4,mov,avi,wmv,webm',
                'thumbnail'    => 'nullable|mimes:jpg,jpeg,png,webp,gif',
                'redirect_url' => 'nullable',
                'status'       => 'nullable|boolean',
                'screen'       => 'nullable',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $data = $validator->validated();

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = 'banner_' . time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/banners/'), $filename);
                $data['image'] = 'uploads/banners/' . $filename;
                $data['image'] = VideoCompressor::compress($data['image']);
            }

            if ($request->hasFile('thumbnail')) {
                $data['thumbnail'] = $this->compressAndSaveThumbnail($request->file('thumbnail'));
            }

            Banner::create($data);

            return redirect()->route('banners.index')->with('success', 'Banner created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Something went wrong. Please try again.')->withInput();
        }
    }



    public function edit(Banner $banner)
    {
        $data = Hatchery::excludingSpot()->with(['category:id,category_name', 'location:id,location_name'])->get();
        $categories = HatcheryCategory::all();
        return view('admin.banners.edit', compact('banner', 'categories','data'));
    }

    public function update(Request $request, Banner $banner)
    {
        $rules = [
            'category_id'  => 'nullable',
            'hatchery_id'  => 'nullable',
            'title'        => 'nullable|string|max:255',
            'redirect_url' => 'nullable|url|max:255',
            'status'       => 'nullable|boolean',
            'screen'       => 'nullable|string',
        ];

        // Only validate image/thumbnail if a real file was uploaded
        if ($request->hasFile('image')) {
            $rules['image'] = 'file|mimes:jpg,jpeg,png,webp,gif,mp4,mov,avi,wmv,webm';
        }
        if ($request->hasFile('thumbnail')) {
            $rules['thumbnail'] = 'file|mimes:jpg,jpeg,png,webp,gif';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        if ($request->hasFile('image')) {
            if ($banner->image && file_exists(public_path($banner->image))) {
                unlink(public_path($banner->image));
            }

            $file = $request->file('image');
            $filename = 'banner_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/banners/'), $filename);
            $data['image'] = 'uploads/banners/' . $filename;
            $data['image'] = VideoCompressor::compress($data['image']);
        } else {
            $data['image'] = $banner->image;
        }

        if ($request->hasFile('thumbnail')) {
            if ($banner->thumbnail && file_exists(public_path($banner->thumbnail))) {
                unlink(public_path($banner->thumbnail));
            }
            $data['thumbnail'] = $this->compressAndSaveThumbnail($request->file('thumbnail'));
        } else {
            $data['thumbnail'] = $banner->thumbnail;
        }

        $banner->update($data);

        return redirect()->route('banners.index')->with('success', 'Banner updated successfully.');
    }


    /**
     * Compress and save thumbnail to max 800px width, JPEG quality 70.
     * Any image the admin uploads gets auto-compressed to ~50-100KB.
     */
    private function compressAndSaveThumbnail($file)
    {
        $filename = 'thumb_' . time() . '.jpg';
        $destPath = public_path('uploads/banners/' . $filename);

        $image = imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (!$image) {
            // Fallback: save original if GD can't process it
            $filename = 'thumb_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/banners/'), $filename);
            return 'uploads/banners/' . $filename;
        }

        $origW = imagesx($image);
        $origH = imagesy($image);
        $maxW = 800;

        if ($origW > $maxW) {
            $newW = $maxW;
            $newH = intval($origH * $maxW / $origW);
            $resized = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
            imagedestroy($image);
            $image = $resized;
        }

        imagejpeg($image, $destPath, 70);
        imagedestroy($image);

        return 'uploads/banners/' . $filename;
    }

    public function destroy(Banner $banner)
    {
        if ($banner->image && file_exists(public_path($banner->image))) {
            unlink(public_path($banner->image));
        }
        if ($banner->thumbnail && file_exists(public_path($banner->thumbnail))) {
            unlink(public_path($banner->thumbnail));
        }

        $banner->delete();

        return redirect()->route('banners.index')->with('success', 'Banner deleted successfully.');
    }
}
