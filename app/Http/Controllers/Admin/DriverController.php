<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DriverController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:drivers.view')->only(['index', 'show']);
        $this->middleware('permission:drivers.create')->only(['create', 'store']);
        $this->middleware('permission:drivers.update')->only(['edit', 'update', 'toggleStatus', 'forceLogout']);
        $this->middleware('permission:drivers.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = Driver::query();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search by name or mobile
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('mobile', 'like', "%{$search}%");
            });
        }

        $drivers = $query->orderByDesc('id')->get();

        return view('admin.drivers.index', compact('drivers'));
    }

    public function create()
    {
        return view('admin.drivers.create');
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'mobile' => 'required|string|max:15|unique:drivers,mobile',
                'status' => 'nullable|boolean',
                'profile_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $data = $validator->validated();
            $data['status'] = $request->has('status') ? 1 : 0;

            if ($request->hasFile('profile_image')) {
                $file = $request->file('profile_image');
                $filename = 'driver_' . time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/drivers/'), $filename);
                $data['profile_image'] = 'uploads/drivers/' . $filename;
            }

            Driver::create($data);

            return redirect()->route('drivers.index')->with('success', 'Driver created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Something went wrong. Please try again.')->withInput();
        }
    }

    public function edit(Driver $driver)
    {
        return view('admin.drivers.edit', compact('driver'));
    }

    public function update(Request $request, Driver $driver)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'mobile' => 'required|string|max:15|unique:drivers,mobile,' . $driver->id,
                'status' => 'nullable|boolean',
                'profile_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $data = $validator->validated();
            $data['status'] = $request->has('status') ? 1 : 0;

            if ($request->hasFile('profile_image')) {
                // Delete old file if exists
                if ($driver->profile_image && file_exists(public_path($driver->profile_image))) {
                    unlink(public_path($driver->profile_image));
                }

                $file = $request->file('profile_image');
                $filename = 'driver_' . time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/drivers/'), $filename);
                $data['profile_image'] = 'uploads/drivers/' . $filename;
            } else {
                $data['profile_image'] = $driver->profile_image;
            }

            $driver->update($data);

            return redirect()->route('drivers.index')->with('success', 'Driver updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Something went wrong. Please try again.')->withInput();
        }
    }

    public function destroy(Driver $driver)
    {
        if ($driver->profile_image && file_exists(public_path($driver->profile_image))) {
            unlink(public_path($driver->profile_image));
        }

        $driver->delete();

        return redirect()->route('drivers.index')->with('success', 'Driver deleted successfully.');
    }

    public function toggleStatus(Driver $driver)
    {
        $driver->status = !$driver->status;
        $driver->save();

        return redirect()->back()->with('success', 'Driver status updated successfully.');
    }

    public function forceLogout(Driver $driver)
    {
        // Send FCM force-logout regardless of journey status
        if (!empty($driver->fcm_token)) {
            try {
                app(FirebaseNotificationService::class)->sendToDevice(
                    $driver->fcm_token,
                    'Signed out',
                    'You have been logged out by the administrator.',
                    null,
                    ['type' => 'force_logout', 'role' => 'driver']
                );
            } catch (\Throwable $e) {
                \Log::warning('Admin force-logout FCM failed', ['driver_id' => $driver->id, 'err' => $e->getMessage()]);
            }
        }

        // Revoke all Sanctum tokens
        $driver->tokens()->delete();

        return redirect()->back()->with('success', 'Driver has been logged out successfully.');
    }
}
