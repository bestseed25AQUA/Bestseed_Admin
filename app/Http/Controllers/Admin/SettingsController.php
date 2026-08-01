<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;

class SettingsController extends Controller
{
    /**
     * Show the form for editing the specified resource.
     */
    /**
     * Display a listing of the resource.
     */
    public function edit($id)
    {
      
        $data = Setting::findOrFail($id);
        return view('admin.settings.general_settings.edit', compact('data'));
    }

    public function update(Request $request, $id)
    {
        $requestData = $request->validate([
            'site_name'     => 'required|string|max:255',
            'address'       => 'required|string',
            'description'   => 'required|string',
            'iframe'        => 'required|string',
            'iframe2'       => 'nullable|string',
            'mobile'        => 'nullable|string|max:20',
            'mobile2'       => 'nullable|string|max:20',
            'email'         => 'nullable|email',
            'email2'        => 'nullable|email',
        ]);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $filename = 'logo_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('site_settings'), $filename);
            $requestData['logo'] = $filename;
        }

        // Handle favicon upload
        if ($request->hasFile('favicon')) {
            $file = $request->file('favicon');
            $filename = 'favicon_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('site_settings'), $filename);
            $requestData['favicon'] = $filename;
        }

        // Handle footer logo upload
        if ($request->hasFile('footer_logo')) {
            $file = $request->file('footer_logo');
            $filename = 'footer_logo_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('site_settings'), $filename);
            $requestData['footer_logo'] = $filename;
        }

        // Handle home page banner upload
        if ($request->hasFile('home_page_banner')) {
            $file = $request->file('home_page_banner');
            $filename = 'home_page_banner_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('site_settings'), $filename);
            $requestData['home_page_banner'] = $filename;
        }

        // Handle header image upload
        if ($request->hasFile('header_image')) {
            $file = $request->file('header_image');
            $filename = 'header_image_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('site_settings'), $filename);
            $requestData['header_image'] = $filename;
        }

        // Update settings in database
        $setting = Setting::findOrFail($id);
        $setting->update($requestData);

        return redirect()->back()->with('success', 'Site Settings Updated Successfully');
    }
}
