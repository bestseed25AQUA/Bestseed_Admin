<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AppConfig;
use App\Models\HatcheryCategory;

class AppConfigController extends Controller
{

    public function __construct()
    {
        $this->middleware('permission:settings.view')->only(['index', 'broodstockConfig', 'seedPriceConfig', 'bookingDescriptionConfig']);
        $this->middleware('permission:settings.update')->only(['update', 'updateBroodstockConfig', 'updateSeedPriceConfig', 'updateBookingDescriptionConfig']);
    }

    /**
     * Display broodstock configuration page
     */
    public function broodstockConfig()
    {
        // Get broodstock config values
        $configs = AppConfig::getGroupAsArray('broodstock');

        // Get categories for dropdown
        $categories = HatcheryCategory::whereIn('category_name', ['Vannamei', 'Tiger'])->get();

        // Generate months for dropdown
        $months = [
            '01' => 'January',
            '02' => 'February',
            '03' => 'March',
            '04' => 'April',
            '05' => 'May',
            '06' => 'June',
            '07' => 'July',
            '08' => 'August',
            '09' => 'September',
            '10' => 'October',
            '11' => 'November',
            '12' => 'December',
        ];

        // Generate years for dropdown (previous year up to 12 years ahead)
        $currentYear = date('Y');
        $years = [];
        for ($i = -1; $i <= 12; $i++) {
            $year = $currentYear + $i;
            $years[$year] = $year;
        }

        return view('admin.app-config.broodstock', compact('configs', 'categories', 'months', 'years'));
    }

    /**
     * Update broodstock configuration
     */
    public function updateBroodstockConfig(Request $request)
    {
        $request->validate([
            'broodstock_default_category' => 'required|string',
            'broodstock_default_month' => 'required|string|size:2',
            'broodstock_default_year' => 'required|string|size:4',
        ]);

        // Update each config value
        AppConfig::setValue(
            'broodstock_default_category',
            $request->broodstock_default_category,
            'broodstock',
            'Default category filter for broodstock screen'
        );

        AppConfig::setValue(
            'broodstock_default_month',
            $request->broodstock_default_month,
            'broodstock',
            'Default month filter for broodstock screen (01-12)'
        );

        AppConfig::setValue(
            'broodstock_default_year',
            $request->broodstock_default_year,
            'broodstock',
            'Default year filter for broodstock screen'
        );

        return redirect()->back()->with('success', 'Broodstock configuration updated successfully!');
    }

    /**
     * Display seed price disclaimer configuration page
     */
    public function seedPriceConfig()
    {
        $disclaimer = AppConfig::getValue('seed_price_disclaimer', 'The above prices may vary between above or below 5 rs per kilogram.');

        return view('admin.app-config.seed-price', compact('disclaimer'));
    }

    /**
     * Update seed price disclaimer configuration
     */
    public function updateSeedPriceConfig(Request $request)
    {
        $request->validate([
            'seed_price_disclaimer' => 'required|string|max:1000',
        ]);

        AppConfig::setValue(
            'seed_price_disclaimer',
            $request->seed_price_disclaimer,
            'seed_price',
            'Disclaimer text shown below seed prices in the app'
        );

        return redirect()->back()->with('success', 'Seed price disclaimer updated successfully!');
    }

    /**
     * Display booking description configuration page
     */
    public function bookingDescriptionConfig()
    {
        $defaultBookingDescription = AppConfig::getValue(
            'default_booking_description',
            "We've received your booking. Within a few days, we will assign your vehicle."
        );
        $defaultVehicleDescription = AppConfig::getValue(
            'default_vehicle_description',
            "We've received your booking. Within a few days, we will assign your vehicle"
        );

        return view('admin.app-config.booking-description', compact('defaultBookingDescription', 'defaultVehicleDescription'));
    }

    /**
     * Update booking description configuration
     */
    public function updateBookingDescriptionConfig(Request $request)
    {
        $request->validate([
            'default_booking_description' => 'required|string|max:1000',
            'default_vehicle_description' => 'required|string|max:1000',
        ]);

        AppConfig::setValue(
            'default_booking_description',
            $request->default_booking_description,
            'booking',
            'Default booking description text shown in booking detail screen'
        );

        AppConfig::setValue(
            'default_vehicle_description',
            $request->default_vehicle_description,
            'booking',
            'Default vehicle description text shown in vehicle tracking screen'
        );

        return redirect()->back()->with('success', 'Booking description configuration updated successfully!');
    }

    /**
     * Display all app configurations
     */
    public function index()
    {
        $configs = AppConfig::orderBy('config_group')->orderBy('config_key')->get();
        return view('admin.app-config.index', compact('configs'));
    }

    /**
     * Update a single config value
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'config_value' => 'nullable|string',
        ]);

        $config = AppConfig::findOrFail($id);
        $config->update([
            'config_value' => $request->config_value,
        ]);

        return redirect()->back()->with('success', 'Configuration updated successfully!');
    }
}
