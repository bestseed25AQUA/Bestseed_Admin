<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Vendor;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    // public function index()
    // {
    //    return view('admin.dashboard.index');
    // }
    public function index()
{
    $vendors = Vendor::orderBy('created_at', 'desc')->take(5)->get(); // Limit to latest 5 vendors
    return view('admin.dashboard.index', compact('vendors'));
}


}
