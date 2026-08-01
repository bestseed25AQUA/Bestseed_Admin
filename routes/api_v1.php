<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\User_apis\LanguageController;
use App\Http\Controllers\Api\User_apis\BookingController;
use App\Http\Controllers\Api\User_apis\HatcheryController;
use App\Http\Controllers\Api\User_apis\PriceController;
use App\Http\Controllers\Api\User_apis\UserAuthController;
use App\Http\Controllers\Api\User_apis\SpotHatcheryController;
use App\Http\Controllers\Api\User_apis\SeedsRequests;
use App\Http\Controllers\Api\User_apis\HatcheryMetaController;
// use App\Http\Controllers\Api\BookingController;
// use App\Http\Controllers\Api\Farmer_apis\FarmerAuthController;


// use App\Http\Controllers\Api\User_apis\UserAuthController;


use App\Http\Controllers\Api\GalleryController;
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
use App\Http\Controllers\Api\User_apis\VehicleController;
use App\Http\Controllers\Api\User_apis\BannerController;
use App\Http\Controllers\Api\User_apis\WantedController;
// use App\Http\Controllers\Api\User_apis\FarmerHomeController;
use App\Http\Controllers\Api\User_apis\FarmerHomeController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });

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


// -----------------------
// 🌐 Global Gallery Routes
// -----------------------
// Route::middleware('auth:sanctum')->group(function () {
//     Route::post('/galleries', [GalleryController::class, 'store']);       // Upload images/videos
//     Route::get('/galleries/{type}/{id}', [GalleryController::class, 'index']); // List files for model
//     Route::delete('/galleries/{id}', [GalleryController::class, 'destroy']);   // Delete file
// });

// // Public Routes
// // Farmer registration
// Route::post('/register', [AuthController::class, 'register']);

// // Login
// // Admin → email + password
// // Farmer → phone → OTP
// // Hatchery → best_seeds_id + password (if applicable)
// Route::post('/login', [AuthController::class, 'login']);

// // OTP verification (Farmer only)
// Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);

// Route::middleware('auth:sanctum')->group(function () {
//     Route::get('/profile', [AuthController::class, 'profile']);
//     Route::post('/logout', [AuthController::class, 'logout']);


Route::prefix('farmer')->group(function () {
       // 👇 New Login route (alias of sendOtp)
    Route::post('/login', [UserAuthController::class, 'login']);

    // 🔑 Login / OTP
    Route::post('/send-otp', [UserAuthController::class, 'sendOtp']);
    Route::post('/verify-otp', [UserAuthController::class, 'verifyOtp']);
    Route::post('/resend-otp', [UserAuthController::class, 'resendOtp']);

    // 🌍 Public Home API (no login required) → useful for Postman testing
    // Route::get('/home-public', [FarmerHomeController::class, 'index']);

    // 🔒 Protected routes (need token)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/profile', [UserAuthController::class, 'profile']);

        Route::post('/update-profile', [UserAuthController::class, 'updateProfile']);
        Route::post('/logout', [UserAuthController::class, 'logout']);

         // Language
        Route::get('/language', [LanguageController::class, 'getLanguage']);
        Route::post('/language', [LanguageController::class, 'updateLanguage']);

        // 🏠 Home Screen API (real-time, needs token)
        Route::get('/home', [FarmerHomeController::class, 'index']);

         // 🧾 Hatchery list (filters + nearby)
        // Route::get('/hatcheries', [HatcheryController::class, 'index']);

        // // 📍 Hatchery details + booking
        // Route::get('/hatcheries/{id}', [BookingController::class, 'show']);
        // Route::post('/hatcheries/{id}/book', [BookingController::class, 'book']);

        // 💰 Market prices
        Route::get('/prices', [PriceController::class, 'getSeedPrices']);


        Route::get('/categories', [HatcheryMetaController::class, 'categories']);
        Route::get('/locations', [HatcheryMetaController::class, 'locations']);
        Route::get('/brands', [HatcheryMetaController::class, 'brands']);

          // ✅ Add these new Hatchery & Booking APIs below:
        // Route::get('/hatcheries', [HatcheryController::class, 'index']);
        // Route::get('/hatcheries/{id}', [HatcheryController::class, 'show']);
        // Route::get('/hatcheries/filters', [HatcheryController::class, 'filters']);
        // Route::get('/hatcheries/updates', [HatcheryController::class, 'updates']);


         Route::get('/hatcheries', [HatcheryController::class, 'allHatcheries']);         // /api/user/hatcheries
        Route::get('/hatcheries/{id}', [HatcheryController::class, 'getHatcheryById']);
        Route::get('/nearby', [HatcheryController::class, 'nearbyHatcheries']); // /api/user/hatcheries/nearby
        Route::get('/filters', [HatcheryController::class, 'filters']);         // /api/user/hatcheries/filters
        // 🧾 Get hatchery overview
          Route::get('/{id}/details', [HatcheryController::class, 'hatcheryDetails']);
          // 📦 Get hatchery category details
         Route::get('/{hatchery_id}/categories/{category_id}', [HatcheryController::class, 'hatcheryCategoryDetails']);
         // 🧾 Book hatchery
         Route::post('/book/{id}', [HatcheryController::class, 'bookHatchery']);
         Route::get('/show/{id}', [HatcheryController::class, 'showBooking']);



        Route::post('/hatcheries/{id}/book', [BookingController::class, 'book']);
        Route::get('/bookings', [BookingController::class, 'list']);
         // ✅ My Bookings API (logged-in farmer)
        Route::get('/my-bookings', [BookingController::class, 'myBookings']);



        // 🚚 Vehicle Availability APIs (from VehicleController)
        // Route::get('/banner', [VehicleController::class, 'banner']);
        Route::get('/vehicle-availability', [VehicleController::class, 'vehicleAvailability']);
        Route::post('/book-vehicle', [VehicleController::class, 'bookVehicle']);
        Route::get('/vehicles/{id}', [VehicleController::class, 'myBookings']);


        Route::get('/price', [BannerController::class, 'PriceBanners']);
         Route::get('/wanted', [BannerController::class, 'wantedBanners']);
         Route::get('/updates', [BannerController::class, 'updatesBanners']);
         Route::get('/news', [BannerController::class, 'newsBanners']);
         Route::get('/banner', [BannerController::class, 'banner']);
         Route::get('/spot-hatcheries-banner', [BannerController::class, 'spotHatcheriesBanners']);
         Route::get('/hatcheryBanners/{id}', [BannerController::class, 'hatcheryBanners']);



         Route::get('/wanted-crops', [WantedController::class, 'index']);
          // Protected: Spot Hatcheries List (needs farmer auth)
         Route::get('/spot-hatcheries', [SpotHatcheryController::class, 'getSpotHatcheriesList']);


             Route::post('/seed-request', [SeedsRequests::class, 'store']);
        //   Route::get('/seed-requests', [SeedsRequests::class, 'index']);


    });
});
// <-- closes Route::prefix('farmer')->group(function () {


// -----------------------
// Vendor (Hatchery) Public Routes
// -----------------------
Route::prefix('vendor')->group(function () {
    Route::post('/login', [VendorAuthController::class, 'login']);  // Login endpoint

    // Protected routes (need token)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/profile', [VendorAuthController::class, 'profile']); // Profile
        Route::post('/update-profile', [VendorAuthController::class, 'updateProfile']); // Update Profile
        Route::post('/logout', [VendorAuthController::class, 'logout']);  // Logout


        // 📊 Vendor Dashboard
        Route::get('/dashboard/summary', [VendorDashboardController::class, 'summary']);


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

    // Farmer
    Route::prefix('farmer')->middleware('role:farmer')->group(function () {
        Route::get('/bookings', [BookingController::class, 'farmerIndex']);
        Route::post('/bookings', [BookingController::class, 'farmerStore']);
        Route::post('/bookings/{id}/cancel', [BookingController::class, 'farmerCancel']);
    });

    // -----------------------
    // Common Route (all roles)
    // -----------------------
    Route::get('/bookings/{id}', [BookingController::class, 'show']);

}); // <-- closes Route::prefix('vendor')->group(function () {
