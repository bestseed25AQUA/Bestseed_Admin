<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\User_apis\UpdatesController;
use App\Http\Controllers\Api\User_apis\LanguageController;
use App\Http\Controllers\Api\User_apis\SpotHatcheryController;
use App\Http\Controllers\Api\User_apis\VehicleAvailabilityApiController;
use App\Http\Controllers\Api\User_apis\BookingController;
use App\Http\Controllers\Api\User_apis\HatcheryController;
use App\Http\Controllers\Api\User_apis\PriceController;
use App\Http\Controllers\Api\User_apis\SeedWantedListingController as SeedWantedApiController;
use App\Http\Controllers\Api\User_apis\UserAuthController;
use App\Http\Controllers\Api\User_apis\FarmController; //farm add
use App\Http\Controllers\Api\User_apis\FarmAccessController; //farm access qr + pin
// use App\Http\Controllers\Api\BookingController;
// use App\Http\Controllers\Api\Farmer_apis\FarmerAuthController;


// use App\Http\Controllers\Api\User_apis\UserAuthController;


use App\Http\Controllers\Api\GalleryController;
use App\Http\Controllers\Api\User_apis\BannerController;
use App\Http\Controllers\Api\User_apis\BroodStockController_user;
// use App\Http\Controllers\Api\User_apis\BannerController;
// use App\Http\Controllers\Api\User_apis\FarmerHomeController;

use App\Http\Controllers\Api\Vendors_apis\BroodstockController;
use App\Http\Controllers\Api\Vendors_apis\SeedBrandController;
// use App\Http\Controllers\Api\Vendors_apis\SeedController;
use App\Http\Controllers\Api\Vendors_apis\VendorAuthController;
use App\Http\Controllers\Api\Vendors_apis\VendorDashboardController;
use App\Http\Controllers\Api\Vendors_apis\VendorHatcheryController;
use App\Http\Controllers\Api\Vendors_apis\VendorNotificationController;
use App\Http\Controllers\Api\Vendors_apis\VendorUpdatesController;
use App\Http\Controllers\Api\Vendors_apis\VendorVehicleController;
// use App\Http\Controllers\Api\User_apis\FarmerHomeController;
use App\Http\Controllers\Api\User_apis\FarmerHomeController;
use App\Http\Controllers\Api\User_apis\HatcheryMetaController;
use App\Http\Controllers\Api\User_apis\NewsAdsController;
use App\Http\Controllers\Api\User_apis\SeedsRequests;
use App\Http\Controllers\Api\User_apis\ContactController;
// use App\Http\Controllers\Api\User_apis\UpdatesController;
use App\Http\Controllers\Api\User_apis\VehicleController;
use App\Http\Controllers\Api\User_apis\BestDealApiController;
use App\Http\Controllers\Api\User_apis\WantedController;
use App\Http\Controllers\Api\Vendors_apis\DriverAuthController;
use App\Http\Controllers\Api\Vendors_apis\DriverBookingController;
// use App\Http\Controllers\Api\User_apis\BroodstockController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/


Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/admin/dashboard', function () {
        return response()->json(['message' => 'Welcome Admin']);
    });
});

Route::middleware(['auth:sanctum', 'role:farmer,hatchery'])->group(function () {
    Route::get('/farmer/dashboard', function () {
        return response()->json(['message' => 'Welcome Farmer/Hatchery']);
    });
});


//manager api beg
Route::prefix('manager')->middleware(['auth:sanctum', 'farmer.active'])->group(function () {
    // add manager
    Route::post('/create', [FarmController::class, 'createManager']);
    //mangers list
    Route::get('/managers', [FarmController::class, 'getManagers']);

    //remove access of manager

    Route::post('/remove-access', [FarmController::class, 'removeManagerAccess']);

    //delete manager
    Route::post('/delete', [FarmController::class, 'deleteManager']);


});


//create partner beg
Route::prefix('partner')->middleware(['auth:sanctum', 'farmer.active'])->group(function () {
    // add manager
    Route::post('/create', [FarmController::class, 'createPartner']);
    //mangers list
    Route::get('/parteners', [FarmController::class, 'getPartners']);

    //remove access of partner

    Route::post('/remove-access', [FarmController::class, 'removePartnerAccess']);

    //delete partner
    Route::post('/delete', [FarmController::class, 'deletePartner']);


});
//create partner end

//farm access beg
//
// The QR + PIN routes that used to sit here — access/generate, access,
// grantees, access/{grant}/revoke, access/redeem and access/verify-pin — are
// gone along with the flow itself. Access is granted by picking people
// directly, which is what the routes below do.
Route::prefix('farmer')->middleware(['auth:sanctum', 'farmer.active'])->group(function () {
    // Pick people and grant them the farm.
    // Anyone who already holds access may pass it on, capped at what they hold.
    Route::get('/farmers/search', [FarmAccessController::class, 'searchFarmers']);
    Route::get('/farm/{farm}/members', [FarmAccessController::class, 'members']);
    Route::post('/farm/{farm}/members', [FarmAccessController::class, 'addMembers']);
    Route::post('/members/{member}/revoke', [FarmAccessController::class, 'revokeMember']);
});
//farm access end

Route::prefix('farmer')->group(function () {
    // 👇 New Login route (alias of sendOtp)
    Route::post('/login', [UserAuthController::class, 'login']);

    // 🔑 Login / OTP
    Route::post('/send-otp', [UserAuthController::class, 'sendOtp']);
    Route::post('/verify-otp', [UserAuthController::class, 'verifyOtp']);
    Route::post('/resend-otp', [UserAuthController::class, 'resendOtp']);

    // 🌐 Public routes (no auth needed — banners, news, deals)
    Route::get('/banner_bg', [BannerController::class, 'bannerBackground']);
    Route::get('/banner_top', [BannerController::class, 'bannerTopBackground']);
    Route::get('/best_deals_banners', [BannerController::class, 'bestDealsBackground']);
    Route::get('/home_banner', [BannerController::class, 'homeBanner']);
    Route::get('/seed_price_banner', [BannerController::class, 'seedPriceBanner']);
    Route::get('/spot_hatcheries_icon', [BannerController::class, 'spotHatcheriesIcon']);
    Route::get('/farm_management_icon', [BannerController::class, 'farmManagementIcon']);
    Route::get('/home_section1_bg', [BannerController::class, 'homeSection1Background']);
    Route::get('/home-news', [NewsAdsController::class, 'homeNews']);
    Route::get('/news', [NewsAdsController::class, 'getNewsByType']);
    Route::get('/news/{id}', [NewsAdsController::class, 'getSingleNews']);
    Route::get('/best-deals', [BestDealApiController::class, 'index']);
    Route::get('/best-deals/{id}', [BestDealApiController::class, 'show']);

    // 🔒 Protected routes (need token)
    Route::middleware(['auth:sanctum', 'farmer.active'])->group(function () {

        // 🔒 Farm management (moved under auth — farmer resolved from token)
        Route::post('/create-farm', [FarmController::class, 'createFarm']);

        //farm- lists
        Route::get('/farm-lists', [FarmController::class, 'index']);
        Route::post('farms/{id}', [FarmController::class, 'update'])->middleware('farm.access:edit'); // edit farm

        //delete farm
        Route::get('/farm/delete/{id}', [FarmController::class, 'deleteFarm'])->middleware('farm.access:delete');

        //add tank for a farm
        Route::post('/farm/create-tank', [FarmController::class, 'createTank'])->middleware('farm.access:create');

        //get feed and store of a farm
        Route::get('/farm/feed-store/{id}', [FarmController::class, 'getTotalFeedandStore'])->middleware('farm.access:view');

        //update farm total feed used and store value
        Route::post('/farm/{id}/update-total-feed', [FarmController::class, 'updateTotalFeed'])->middleware('farm.access:total_feed');

        //add todays tank feed
        Route::post('/tanks/add-todays-tanks-quantity', [FarmController::class, 'addTodaysQuantity'])->middleware('farm.access:create');

        //update today tank feed
        Route::post('/tanks/update-tanks-quantity', [FarmController::class, 'updateTankQuantity'])->middleware('farm.access:edit');

        //change/update tank status on click on on/off button
        Route::post('/tank/status', [FarmController::class, 'changeTankStatus'])->middleware('farm.access:tank_status');

        //farm tank list-GET /api/farms/{farm_id}/tanks
        Route::get('/farms/{farm_id}/tanks', [FarmController::class, 'farmTanks'])->middleware('farm.access:view');
        //tank feed history
        Route::post('tank-feed-history', [FarmController::class, 'getTankFeedHistory'])->middleware('farm.access:view');
        // Edit one recorded feed entry from the tank history screen.
        Route::post('/tank-feed-entry', [FarmController::class, 'updateTankFeedEntry'])->middleware('farm.access:edit');
        Route::post('/tank-feed-entry/delete', [FarmController::class, 'deleteTankFeedEntry'])->middleware('farm.access:delete');

        //download tank feed report
        Route::post('/download-tank-feed-report', [FarmController::class, 'downloadFeedReport'])->middleware('farm.access:view');

        //low feed limit check and send notification
        Route::get('/feed/check-limit/{farm_id}', [FarmController::class, 'checkFeedLimit'])->middleware('farm.access:view');

        //farm management end

        Route::get('/profile', [UserAuthController::class, 'profile']);
        // Add this line for update profile
        Route::post('/update-profile', [UserAuthController::class, 'updateProfile']);
        Route::post('/logout', [UserAuthController::class, 'logout']);

        // Language
        Route::get('/language', [LanguageController::class, 'getLanguage']);
        Route::post('/language', [LanguageController::class, 'updateLanguage']);

        // 🏠 Home Screen API (real-time, needs token)
        Route::get('/home', [FarmerHomeController::class, 'index']);
        Route::post('/book-hatchery', [BookingController::class, 'bookHatchery']);
        Route::post('/book-spot-hatchery', [BookingController::class, 'bookSpotHatchery']);
        Route::post('/book-vehicle-hatchery', [BookingController::class, 'bookVehicleHatchery']);
        Route::get('/booking/{id}', [BookingController::class, 'show']);

        Route::get('/my-bookings', [BookingController::class, 'myBookings']);
        Route::get('/my-bookings-details/{booking_id}', [BookingController::class, 'mybookingDetails']);
        Route::post('/cancel-booking', [BookingController::class, 'cancelBooking']);
        Route::post('/update-booking', [BookingController::class, 'updateBooking']); // Edit booking details while still Pending
        Route::post('/update-booking-status', [BookingController::class, 'updateBookingStatus']);
        Route::post('/rate-booking', [BookingController::class, 'rateBooking']);
        Route::get('/pending-rating', [BookingController::class, 'pendingRating']);
        Route::post('/dismiss-rating', [BookingController::class, 'dismissRating']);

        // 💰 Market prices
        Route::get('/prices', [PriceController::class, 'getSeedPrices']);
        Route::get('/home-seed-prices', [PriceController::class, 'getHomeSeedPrices']); // without category_id

        // 🌱 Seed Wanted Listings
        Route::get('/seed-wanted-listings', [SeedWantedApiController::class, 'index']);

        Route::get('/categories', [HatcheryMetaController::class, 'categories']);
        Route::get('/locations', [HatcheryMetaController::class, 'locations']);
        Route::get('/brands', [HatcheryMetaController::class, 'brands']);
        Route::get('/hatcheries_filters', [HatcheryMetaController::class, 'hatcheries_filters']); // optional combined endpoint

        // ================================
        // 🌍 Location Management (Farmer/User)
        // ================================
        Route::post('/locations_farmer', [HatcheryMetaController::class, 'addLocation']);   // ➕ Add new location
        Route::put('/locations/{id}', [HatcheryMetaController::class, 'updateLocation']);   // ✏️ Edit location
        Route::get('/locations/search', [HatcheryMetaController::class, 'searchLocations']); // 🔍 Search location by name
        Route::get('/locations/default', [HatcheryMetaController::class, 'defaultLocation']); // 📍 Get default location
        Route::post('/locations/set-default/{id}', [HatcheryMetaController::class, 'setDefaultLocation']); // ⭐ Set default location
        Route::get('/locations/history', [HatcheryMetaController::class, 'locationHistory']);   // 🕓 Recent locations
        Route::delete('/locations/{id}', [HatcheryMetaController::class, 'deleteLocation']);
        Route::get('broodstocks', [BroodStockController_user::class, 'index']);
        Route::get('broodstocks_list', [BroodStockController_user::class, 'getBroodStockList']);
        Route::get('broodstock-default-filter', [BroodStockController_user::class, 'getDefaultFilter']);
        Route::get('broodstock-available-months', [BroodStockController_user::class, 'getAvailableMonths']);
        Route::get('/home-hatchery-updates', [UpdatesController::class, 'homeHatcheryUpdates']);
        Route::get('/hatchery-updates/{id}', [UpdatesController::class, 'hatcheryProfile']);
        Route::get('/hatchery-updates', [UpdatesController::class, 'allHatcheryUpdates']);
        // Home Screen — 6 Hatchery Cards
        Route::get('/home-hatcheries', [HatcheryController::class, 'homeHatcheries']);
        // View All Hatcheries — Full Filters + Pagination
        Route::get('/hatcheries', [HatcheryController::class, 'index']);
        // Filters List — Category, Brand, Location
        Route::get('/hatchery-filters', [HatcheryController::class, 'filters']);
        Route::get('/hatchery-all-category/{id}', [HatcheryController::class, 'show']); // by unique_id (for home screen)
        Route::get('/hatchery-by-id/{id}', [HatcheryController::class, 'showById']); // by database id (for search flow)
        Route::get('/hatchery/{hatchery_id}/category/{category_id}/detail', [HatcheryController::class, 'categoryDetail']);

        // 🚚 Vehicle Availability APIs (from VehicleController)
        Route::get('/vehicle-availability', [VehicleController::class, 'vehicleAvailability']);
        Route::get('/vehicle-availability', [VehicleAvailabilityApiController::class, 'getVehicleAvailabilityList']);
        Route::get('/vehicle_list', [VehicleController::class, 'vehicleList']);
        Route::get('/vehicle_available_booking', [VehicleController::class, 'vehicleAvailableBooking']);
        Route::post('/update_vehicle_booking_status', [VehicleController::class, 'updateVehicleBookingStatus']);
        Route::get('/vehicle_booking_detail/{id}', [VehicleController::class, 'vehicleBookingDetail']);
        Route::post('/vehicle_booking_delete/{id}', [VehicleController::class, 'vehicleBookingCancel']);
        Route::get('/vehicle_tracking/{id}', [VehicleController::class, 'vehicleTracking']);
        Route::post('/vehicle_tracking/{id}/save_stop', [VehicleController::class, 'savePassedStop']);
        Route::post('/book-vehicle', [VehicleController::class, 'bookVehicle']);
        Route::get('/vehicles/{id}', [VehicleController::class, 'myBookings']);

        // 🚚 Driver Location APIs
        Route::post('/update_driver_location', [VehicleController::class, 'updateDriverLocation']);
        Route::post('/add_tracking_point', [VehicleController::class, 'addTrackingPoint']);
        Route::get('/wanted-crops', [WantedController::class, 'index']);

        // Protected: Spot Hatcheries List (needs farmer auth)
        Route::get('/spot-hatcheries', [SpotHatcheryController::class, 'getSpotHatcheriesList']);

        Route::post('/seed-request', [SeedsRequests::class, 'store']);

        // Contacts API
        Route::get('/contacts', [ContactController::class, 'index']);

        // FCM Token Registration
        Route::post('/register-fcm-token', [UserAuthController::class, 'registerFcmToken']);

        // Push Notifications
        Route::get('/push-notifications', [\App\Http\Controllers\Api\Farmer_apis\NotificationController::class, 'index']);
        Route::post('/push-notifications/{id}/read', [\App\Http\Controllers\Api\Farmer_apis\NotificationController::class, 'markAsRead']);

        // Announcements (dialog on launch + Profile > Announcements)
        Route::get('/announcements', [\App\Http\Controllers\Api\AnnouncementController::class, 'index']);
        Route::get('/announcements/popup', [\App\Http\Controllers\Api\AnnouncementController::class, 'popup']);
        Route::post('/announcements/{id}/read', [\App\Http\Controllers\Api\AnnouncementController::class, 'markAsRead']);
    });
});


// -----------------------
// Driver Public Routes
// -----------------------
Route::prefix('driver')->group(function () {

    // 🔐 Driver OTP Login
    Route::post('/login', [DriverAuthController::class, 'login']);
    Route::post('/send-otp', [DriverAuthController::class, 'sendOtp']);
    Route::post('/verify-otp', [DriverAuthController::class, 'verifyOtp']);
    Route::post('/resend-otp', [DriverAuthController::class, 'resendOtp']);

    // 🔒 Protected routes (need token + active status check)
    Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckVendorActive::class])->group(function () {
        Route::get('/profile', [DriverAuthController::class, 'profile']);
        Route::post('/update-profile', [DriverAuthController::class, 'updateProfile']); // POST
        Route::post('/update-location', [DriverAuthController::class, 'updateLocation']); // Update driver's current location
        Route::post('/update-permissions', [DriverAuthController::class, 'updatePermissions']); // Sync device permission status (location + battery)
        Route::post('/logout', [DriverAuthController::class, 'logout']);
        Route::get('/bookings', [DriverBookingController::class, 'index']);
        Route::post('/start-journey', [DriverBookingController::class, 'startJourney']);
        Route::post('/location/update', [DriverBookingController::class, 'updateDriverLocation']);
        Route::post('/update-drop-status', [DriverBookingController::class, 'updateDropStatus']);
        Route::post('/tracking-alert', [DriverBookingController::class, 'trackingAlert']);
        Route::get('/tracking-alert-status', [DriverBookingController::class, 'trackingAlertStatus']);
        Route::post('/register-fcm-token', [DriverAuthController::class, 'registerFcmToken']);

        // Announcements (dialog on launch + Profile > Announcements)
        Route::get('/announcements', [\App\Http\Controllers\Api\AnnouncementController::class, 'index']);
        Route::get('/announcements/popup', [\App\Http\Controllers\Api\AnnouncementController::class, 'popup']);
        Route::post('/announcements/{id}/read', [\App\Http\Controllers\Api\AnnouncementController::class, 'markAsRead']);
    });
});



// -----------------------
// Vendor (Hatchery) Public Routes
// -----------------------
Route::prefix('vendor')->group(function () {
    Route::post('/login', [VendorAuthController::class, 'login']);
    Route::post('/set-new-password', [VendorAuthController::class, 'setnewpassword']);

    // Protected routes (need token + active status check)
    Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckVendorActive::class])->group(function () {
        Route::get('/profile', [VendorAuthController::class, 'profile']); // Profile
        Route::post('/update-profile', [VendorAuthController::class, 'updateProfile']); // Update Profile
        Route::post('/update-location', [VendorAuthController::class, 'updateLocation']); // Update vendor's current location
        Route::post('/register-fcm-token', [VendorAuthController::class, 'registerFcmToken']); // FCM token for push notifications
        Route::post('/logout', [VendorAuthController::class, 'logout']);  // Logout
        // 📦 Vendor Bookings (Dashboard)
        Route::get('/bookings', [VendorDashboardController::class, 'bookings']);
        Route::post('/bookings/{id}/accept', [VendorDashboardController::class, 'acceptBooking']);
        Route::post('/bookings/{id}/reject', [VendorDashboardController::class, 'rejectBooking']);
        Route::put('/bookings/{id}/update', [VendorDashboardController::class, 'updateBooking']);
        Route::post('/bookings/{id}/remove-driver', [VendorDashboardController::class, 'removeDriver']);
        Route::post('/bookings/{id}/change-driver', [VendorDashboardController::class, 'changeDriver']);
        Route::get('/bookings/{id}/tracking', [VendorDashboardController::class, 'vehicleTracking']);
        Route::get('/drivers', [VendorDashboardController::class, 'getDrivers']);
        Route::get('/tracking-alert-status', [VendorDashboardController::class, 'trackingAlertStatus']);
        Route::post('/tracking-alerts/mark-read', [VendorDashboardController::class, 'markAlertsRead']);

        // 📣 Announcements (dialog on launch + Profile > Announcements)
        Route::get('/announcements', [\App\Http\Controllers\Api\AnnouncementController::class, 'index']);
        Route::get('/announcements/popup', [\App\Http\Controllers\Api\AnnouncementController::class, 'popup']);
        Route::post('/announcements/{id}/read', [\App\Http\Controllers\Api\AnnouncementController::class, 'markAsRead']);


        // 📊 Vendor Dashboard
        // Route::get('/dashboard/summary', [VendorDashboardController::class, 'summary']);


        // 🔔 Vendor Notification Settings
        Route::get('/notifications', [VendorNotificationController::class, 'index']);
        Route::put('/notifications', [VendorNotificationController::class, 'update']);




        // -----------------------
        // Hatchery Management Routes (only for vendors with role hatchery)
        // -----------------------
        Route::middleware('role:hatchery')->group(function () {
            Route::post('/hatcheries', [VendorHatcheryController::class, 'registerHatchery']);   // Create
            Route::get('/hatcheries', [VendorHatcheryController::class, 'myHatcheries']);      // List
            Route::post('/hatcheries/{id}', [VendorHatcheryController::class, 'updateHatchery']); // Update
            Route::delete('/hatcheries/{id}', [VendorHatcheryController::class, 'deleteHatchery']); // Delete
        });

        // -----------------------
        // 🌱 Seed Management Routes
        // -----------------------
        Route::post('/seeds', [SeedBrandController::class, 'store']);
        Route::get('/seeds', [SeedBrandController::class, 'index']);
        Route::get('/seeds/{id}', [SeedBrandController::class, 'show']);
        Route::post('/seeds/{id}', [SeedBrandController::class, 'update']);
        Route::delete('/seeds/{id}', [SeedBrandController::class, 'destroy']);


        // -----------------------// -----------------------
        // 📢 Hatchery Updates (Social Feed)
        // -----------------------
        Route::get('/updates', [VendorUpdatesController::class, 'index']);
        Route::post('/updates', [VendorUpdatesController::class, 'store']);
        Route::get('/updates/{id}', [VendorUpdatesController::class, 'show']);
        Route::delete('/updates/{id}', [VendorUpdatesController::class, 'destroy']);

        // 🐟 Broodstock Management
        Route::get('/broodstocks', [BroodstockController::class, 'index']);
        Route::post('/broodstocks', [BroodstockController::class, 'store']);
        Route::get('/broodstocks/{id}', [BroodstockController::class, 'show']);
        Route::delete('/broodstocks/{id}', [BroodstockController::class, 'destroy']);

        // 🚚 Vehicle Booking Routes
        Route::get('/vehicles', [VendorVehicleController::class, 'index']);   // List all
        Route::post('/vehicles', [VendorVehicleController::class, 'store']);  // Create new
        Route::get('/vehicles/{id}', [VendorVehicleController::class, 'show']); // Show single
        Route::delete('/vehicles/{id}', [VendorVehicleController::class, 'destroy']); // Delete

    });


    // -----------------------
    // Role-based Booking Routes
    // -----------------------

    // Admin
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/bookings', [BookingController::class, 'adminIndex']);
        Route::post('/bookings/{id}/status', [BookingController::class, 'adminUpdateStatus']);
        Route::post('/bookings/{id}/cancel', [BookingController::class, 'adminCancel']);
    });

    // Hatchery
    Route::prefix('hatchery')->middleware('role:hatchery')->group(function () {
        Route::get('/bookings', [BookingController::class, 'hatcheryIndex']);
        Route::post('/bookings', [BookingController::class, 'hatcheryStore']);
        Route::post('/bookings/{id}/cancel', [BookingController::class, 'hatcheryCancel']);
        Route::post('/bookings/{id}/status', [BookingController::class, 'hatcheryUpdateStatus']);
    });

    // // Farmer
    // Route::prefix('farmer')->middleware('role:farmer')->group(function () {
    //     Route::get('/bookings', [BookingController::class, 'farmerIndex']);
    //     Route::post('/bookings', [BookingController::class, 'farmerStore']);
    //     Route::post('/bookings/{id}/cancel', [BookingController::class, 'farmerCancel']);
    // });

    // -----------------------
    // Common Route (all roles)
    // -----------------------
    // Route::get('/bookings/{id}', [BookingController::class, 'show']);

}); // <-- closes Route::prefix('vendor')->group(function () {

// ── Deploy version check (no auth needed) ──
Route::get('/deploy-version', function () {
    return response()->json([
        'version' => 59,
        'deployed_at' => now()->toDateTimeString(),
        'status' => 'ok',
    ]);
});

// ── App version / force-update check (no auth needed) ──
// Both driver + vendor apps call this on startup with ?platform= & ?app=
// and get min_version / latest_version / store_url back. Values live in
// the `app_configs` table and are edited via the admin panel per release.
Route::get(
    '/app-version-check',
    [\App\Http\Controllers\Api\AppVersionController::class, 'check']
);

// Check if cron/scheduler is running — call from browser to test
Route::get('/cron-check', function () {
    $cacheKey = 'last_scheduler_run';
    $lastRun = \Illuminate\Support\Facades\Cache::get($cacheKey);

    // Also manually run the stale check to test it
    $staleBookings = \App\Models\Booking::where('status', 4)
        ->where(function ($q) {
            $q->where('driver_location_updated_at', '<', now()->subMinutes(5))
                ->orWhereNull('driver_location_updated_at');
        })
        ->select('id', 'driver_id', 'driver_location_updated_at', 'driver_location_name', 'last_tracking_alert_at')
        ->get();

    return response()->json([
        'scheduler_last_run' => $lastRun ?? 'NEVER (cron not set up)',
        'current_time' => now()->toDateTimeString(),
        'stale_bookings_count' => $staleBookings->count(),
        'stale_bookings' => $staleBookings->map(fn($b) => [
            'booking_id' => $b->id,
            'driver_id' => $b->driver_id,
            'last_location_at' => $b->driver_location_updated_at,
            'stale_minutes' => $b->driver_location_updated_at
                ? now()->diffInMinutes($b->driver_location_updated_at)
                : 'NEVER sent',
            'last_alert_at' => $b->last_tracking_alert_at,
        ]),
    ]);
});

// Manually trigger stale tracking check for a SPECIFIC booking
// Usage: https://staging.bestseed.in/api/test-stale-alert?booking_id=842
Route::get('/test-stale-alert', function (\Illuminate\Http\Request $request) {
    $bookingId = $request->query('booking_id');
    if (!$bookingId) {
        return response()->json(['error' => 'Pass ?booking_id=XXX'], 400);
    }

    $booking = \App\Models\Booking::find($bookingId);
    if (!$booking) {
        return response()->json(['error' => "Booking $bookingId not found"], 404);
    }
    if ($booking->status != 4) {
        return response()->json(['error' => "Booking $bookingId status is {$booking->status}, not 4 (active)"], 400);
    }

    $driver = \App\Models\Driver::find($booking->driver_id);
    $vendor = $booking->vendor_id ? \App\Models\Vendor::find($booking->vendor_id) : null;

    // Send notification to this vendor only
    $sent = false;
    if ($vendor && !empty($vendor->fcm_token)) {
        try {
            $driverName = $driver->name ?? 'Driver';
            $title = "TEST: Tracking Alert - Booking #{$bookingId}";
            $body = "{$driverName} location not updating. This is a test notification.";

            $fcm = new \App\Services\FirebaseNotificationService();
            $fcm->sendToDevice($vendor->fcm_token, $title, $body, null, [
                'type' => 'tracking_stopped',
                'booking_id' => (string) $bookingId,
            ]);
            $sent = true;
        } catch (\Exception $e) {
            return response()->json(['error' => 'FCM send failed: ' . $e->getMessage()], 500);
        }
    }

    return response()->json([
        'booking_id' => (int) $bookingId,
        'driver' => $driver->name ?? 'Unknown',
        'vendor_id' => $booking->vendor_id,
        'vendor_fcm_token' => $vendor ? (empty($vendor->fcm_token) ? 'EMPTY' : 'SET') : 'NO VENDOR',
        'notification_sent' => $sent,
        'last_location_at' => $booking->driver_location_updated_at,
    ]);
});

// Reset tracking data for bookings — call /api/reset-tracking?bookings=839,840,841
Route::get('/reset-tracking', function () {
    $ids = explode(',', request()->query('bookings', ''));
    $ids = array_filter(array_map('intval', $ids));
    if (empty($ids)) return response()->json(['status' => 'error', 'message' => 'Pass ?bookings=839,840,841']);

    $results = [];
    foreach ($ids as $id) {
        $booking = \App\Models\Booking::find($id);
        if (!$booking) { $results[$id] = 'not found'; continue; }

        $tracks = \App\Models\VehicleTracking::where('booking_id', $id)->count();
        \App\Models\VehicleTracking::where('booking_id', $id)->delete();

        $stops = \App\Models\VehicleTrackingStop::where('booking_id', $id)->count();
        \App\Models\VehicleTrackingStop::where('booking_id', $id)->delete();

        $booking->update([
            'driver_lat' => $booking->pickup_lat,
            'driver_lng' => $booking->pickup_lng,
            'driver_location_name' => null,
            'tracking_path' => null,
            'tracking_path_last_id' => null,
            'tracking_path_at' => null,
        ]);

        $results[$id] = "deleted {$tracks} tracking points, {$stops} stops. Location reset.";
    }
    return response()->json(['status' => 'ok', 'results' => $results]);
});

// TEMP diagnostic: inspect why tracking_path isn't populating — /api/debug-tracking-path?booking=861
Route::get('/debug-tracking-path', function () {
    $id = (int) request()->query('booking', 0);
    $booking = \App\Models\Booking::find($id);
    if (!$booking) return response()->json(['error' => 'booking not found', 'id' => $id]);

    $cols = [
        'tracking_path'         => \Illuminate\Support\Facades\Schema::hasColumn('bookings', 'tracking_path'),
        'tracking_path_last_id' => \Illuminate\Support\Facades\Schema::hasColumn('bookings', 'tracking_path_last_id'),
        'tracking_path_at'      => \Illuminate\Support\Facades\Schema::hasColumn('bookings', 'tracking_path_at'),
    ];

    $tp = $booking->tracking_path;
    $info = [
        'booking_id'             => $booking->id,
        'columns_exist'          => $cols,
        'tracking_path_is_array' => is_array($tp),
        'tracking_path_count'    => is_array($tp) ? count($tp) : null,
        'tracking_path_raw_type' => gettype($tp),
        'tracking_path_last_id'  => $booking->tracking_path_last_id,
        'tracking_path_at'       => (string) $booking->tracking_path_at,
        'vehicle_trackings_count' => \App\Models\VehicleTracking::where('booking_id', $id)
                                        ->whereNotNull('lat')->whereNotNull('lng')->count(),
        'maps_api_key_set'       => !empty(config('services.maps_api_key') ?? env('GOOGLE_MAPS_API_KEY')),
    ];

    // Try to force a refresh and capture any exception that the read path swallows.
    try {
        $ctrl = app(\App\Http\Controllers\Api\User_apis\VehicleController::class);
        $m = new \ReflectionMethod($ctrl, 'maybeRefreshTrackingPath');
        $m->setAccessible(true);
        $m->invoke($ctrl, $booking);
        $booking->refresh();
        $info['after_refresh_count'] = is_array($booking->tracking_path) ? count($booking->tracking_path) : null;
        $info['refresh_error'] = null;
    } catch (\Throwable $e) {
        $info['refresh_error'] = $e->getMessage();
        $info['refresh_error_class'] = get_class($e);
        $info['refresh_error_at'] = $e->getFile() . ':' . $e->getLine();
    }

    return response()->json($info);
});

// One-time fix: swap order of Jubilee Hills and Durgam Cheruvu for booking 835
Route::get('/fix-835', function () {
    $jh = \App\Models\VehicleTrackingStop::where('booking_id', 835)->where('name', 'Jubilee Hills')->first();
    $dc = \App\Models\VehicleTrackingStop::where('booking_id', 835)->where('name', 'Durgam Cheruvu')->first();
    if ($jh && $dc) {
        $jh->update(['order' => 14]);
        $dc->update(['order' => 15]);
        return response()->json(['status' => 'fixed', 'jubilee_hills_order' => 14, 'durgam_cheruvu_order' => 15]);
    }
    return response()->json(['status' => 'not_found']);
});
