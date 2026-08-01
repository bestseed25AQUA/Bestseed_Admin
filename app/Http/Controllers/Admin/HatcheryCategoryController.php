<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HatcheryCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HatcheryCategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:hatchery-categories.view')->only(['index', 'show']);
        $this->middleware('permission:hatchery-categories.create')->only(['create', 'store']);
        $this->middleware('permission:hatchery-categories.update')->only(['edit', 'update', 'removeImage']);
        $this->middleware('permission:hatchery-categories.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $type = $request->get('type', 'shrimp'); // default shrimp

        $data = HatcheryCategory::where('type', $type)
            ->orderBy('priority', 'asc')
            ->get();

        return view('admin.hatchery-categories.index', compact('data', 'type'));
    }


    public function create()
    {
        return view('admin.hatchery-categories.create');
    }


    public function store(Request $request)
    {
        //dd($request->all());
        $request->validate([
            'category_name' => 'required|string|max:255',
            'type' => 'required|in:shrimp,fish',
            'priority' => 'nullable|integer|min:0',
            'category_description' => 'nullable|string',
            'category_report' => 'nullable|file|mimes:pdf,doc,docx',
        ]);

        try {

            //multiple image upload of category beg
            $imagePaths = [];
            if ($request->hasFile('image')) {
                foreach ($request->file('image') as $file) {
                    $filename = time() . '_' . $file->getClientOriginalName();
                    $file->move(public_path('uploads/hatcheries_categories'), $filename);
                    $imagePaths[] = 'uploads/hatcheries_categories/' . $filename;
                }
            }


            // 📄 category report upload (single file)
            $reportPath = null;
            if ($request->hasFile('category_report')) {
                $reportFile = $request->file('category_report');
                $reportName = time() . '_' . $reportFile->getClientOriginalName();
                $reportFile->move(public_path('uploads/hatcheries_categories/reports'), $reportName);
                $reportPath = 'uploads/hatcheries_categories/reports/' . $reportName;
            }


            //multiple image upload of category end
            HatcheryCategory::create([
                'category_name' => $request->category_name,
                'type' => $request->type,
                'priority' => $request->priority ?? 0,
                'category_description' => $request->category_description,
                'category_report' => $reportPath,
                'image' => json_encode($imagePaths)

            ]);

            return redirect()->route('hatchery-categories.index')
                ->with('success', 'Hatchery category created successfully.');

        } catch (\Exception $e) {
            // dd($e);
            Log::error('Error creating hatchery category: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Failed to create category.');
        }
    }

    public function edit($id)
    {
        $data = HatcheryCategory::findOrFail($id);
        return view('admin.hatchery-categories.edit', compact('data'));
    }


    public function update(Request $request, $id)
    {
        $request->validate([
            'category_name' => 'required|string|max:255',
            'type' => 'required|in:shrimp,fish',
            'priority' => 'nullable|integer|min:0',
            'category_description' => 'nullable|string',
            'category_report' => 'nullable|file|mimes:pdf,doc,docx',
        ]);

        try {
            //multiple image upload of category beg
            $category = HatcheryCategory::findOrFail($id);
            $prev_files = $category->image;
            $prev_files_array = json_decode($prev_files);
            $imagePaths_prev = $prev_files_array; //take old image
            $imagePaths_prev = $imagePaths_prev ?? []; // ensures $images is an array

            $imagePaths = [];
            if ($request->hasFile('image')) {
                foreach ($request->file('image') as $file) {
                    $filename = time() . '_' . $file->getClientOriginalName();
                    $file->move(public_path('uploads/hatcheries_categories'), $filename);
                    $imagePaths[] = 'uploads/hatcheries_categories/' . $filename;
                }


            }

            if ($request->hasFile('image')) {
                $imagePaths_merged = array_merge($imagePaths_prev, $imagePaths);
                // $imagePaths[]= $imagePaths_merged;
                $imagePaths = $imagePaths_merged;

            } else {


                //$imagePaths[]= $imagePaths_prev;
                $imagePaths = $imagePaths_prev; //bcz already array
            }


            // multiple image upload end

            // 📄 handle category report
            $reportPath = $category->category_report; // keep old by default
            if ($request->hasFile('category_report')) {
                $reportFile = $request->file('category_report');
                $reportName = time() . '_' . $reportFile->getClientOriginalName();
                $reportFile->move(public_path('uploads/hatcheries_categories/reports'), $reportName);
                $reportPath = 'uploads/hatcheries_categories/reports/' . $reportName;
            }

            $category = HatcheryCategory::findOrFail($id);
            $category->update([
                'category_name' => $request->category_name,
                'type' => $request->type,
                'priority' => $request->priority ?? 0,
                'category_description' => $request->category_description,
                'category_report' => $reportPath,
                'image' => json_encode($imagePaths)
            ]);

            return redirect()->route('hatchery-categories.index')
                ->with('success', 'Category updated successfully.');

        } catch (\Exception $e) {
            Log::error('Error updating hatchery category: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Failed to update category.');
        }
    }


    public function destroy($id)
    {
        try {
            $category = HatcheryCategory::findOrFail($id);
            $category->delete();

            return redirect()->route('hatchery-categories.index')
                ->with('success', 'Category deleted successfully.');

        } catch (\Exception $e) {
            Log::error('Error deleting hatchery category: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete category.');
        }
    }

    /**
     * Remove a single image from category
     */
    public function removeImage(Request $request)
    {
        try {
            $categoryId = $request->category_id;
            $imagePath = $request->image_path;

            $category = HatcheryCategory::findOrFail($categoryId);

            // Get current images
            $images = json_decode($category->image, true) ?? [];

            // Remove the specified image from array
            $images = array_values(array_filter($images, function ($img) use ($imagePath) {
                return $img !== $imagePath;
            }));

            // Update category with remaining images
            $category->update([
                'image' => json_encode($images)
            ]);

            // Optionally delete the file from storage
            $fullPath = public_path($imagePath);
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            return response()->json([
                'success' => true,
                'message' => 'Image removed successfully',
                'remaining_images' => $images
            ]);

        } catch (\Exception $e) {
            Log::error('Error removing image: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove image'
            ], 500);
        }
    }
}
