<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admin\VendorController;
use App\Http\Controllers\Admin\HatcheryCategoryController;
use App\Http\Controllers\Admin\HatcheryLocationController;
use App\Http\Controllers\Admin\HatcheryController;
use App\Http\Controllers\Admin\SpotHatcheryController;
use App\Http\Controllers\Admin\VehicleAvailabilityController;
use App\Http\Controllers\Admin\HatcheryPostController;
use App\Http\Controllers\Admin\HatcherySeedController;
use App\Http\Controllers\Admin\BroadStockController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\HatcheryLocationBranchesController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\MarketPriceController;
use App\Http\Controllers\Admin\SeedWantedListingController;
use App\Http\Controllers\Admin\NewsController;
use App\Http\Controllers\Auth\AdminOtpController;
use App\Http\Controllers\Admin\DriverController;
use App\Http\Controllers\Admin\ContactController;
use App\Http\Controllers\Admin\FarmManagementController;
use App\Http\Controllers\Admin\FarmAccessMemberController;
use App\Http\Controllers\Admin\FarmTankController;
use App\Http\Controllers\Admin\FarmTeamController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SubAdminController;
use App\Http\Controllers\Admin\SeedRequestController;
use App\Http\Controllers\Admin\AppConfigController;
use App\Http\Controllers\Admin\BestDealController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\PublicUpdateController;

// Public routes (no auth required)
Route::get('/hatchery-update/{id}', [PublicUpdateController::class, 'show'])->name('public.hatchery-update');

// Redirect root URL to login

Route::get('/', function () {
    //dd('here'); //fine
    return redirect('/admin');
});

Route::middleware(['guest'])->group(function () {
    Route::get('/login', function () {
        // dd('here login'); //not coming here
        return view('welcome');
    })->name('login');
});

// 🔐 ADMIN OTP ROUTES (2FA)
Route::get('/admin/otp', [AdminOtpController::class, 'showOtpForm'])
    ->middleware('guest')
    ->name('admin.otp');


// Route::get('/admin/otp', [AdminOtpController::class, 'showOtpForm'])
//     ->name('admin.otp');

Route::post('/admin/otp', [AdminOtpController::class, 'verifyOtp'])
    ->name('admin.otp.verify');

Route::post('/admin/otp/resend', [AdminOtpController::class, 'resendOtp'])
    ->name('admin.otp.resend');


// Admin Profile Routes
Route::get('admin/profile', [AdminController::class, 'profile'])->name('admin_profile');
Route::put('admin/profile', [AdminController::class, 'updateAdminProfile'])->name('update_admin_profile');

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->name('logout');
Auth::routes();

Route::group(['middleware' => ['auth', \App\Http\Middleware\IsAdmin::class]], function () {
    // Route::group([
//     'middleware' => ['auth', 'admin.otp', \App\Http\Middleware\IsAdmin::class]], function () {
    Route::get('/admin', [HomeController::class, 'index'])->name('admin');
    Route::resource('admin/site-settings', '\App\Http\Controllers\Admin\SettingsController');

    // Vendor routes
    Route::resource('admin/vendors', VendorController::class);
    Route::get('admin/vendors/{vendor}/credentials', [VendorController::class, 'getCredentials'])
        ->name('vendors.credentials');
    // Route::put('admin/vendors/{vendor}/activate', [VendorController::class, 'activate'])
    //     ->name('vendors.activate');
    Route::post('admin/vendors/{vendor}/activate', [VendorController::class, 'activate'])
        ->name('vendors.activate');
    Route::post('admin/vendors/{vendor}/reset-password', [VendorController::class, 'resetPassword'])
        ->name('vendors.reset-password');
    Route::get('admin/vendors/{vendor}/check-hatcheries', [VendorController::class, 'checkHatcheries'])
        ->name('vendors.check-hatcheries');
    Route::post('admin/vendors/{vendor}/force-logout', [VendorController::class, 'forceLogout'])
        ->name('vendors.force-logout');

    Route::post('/admin/vendor-profile-image-delete', [VendorController::class, 'deleteProfileImage'])
        ->name('admin.image.delete');

    // Diagnostic: Check if FFmpeg is available for video compression
    Route::get('admin/check-ffmpeg', function () {
        $ffmpeg = \App\Helpers\VideoCompressor::getFFmpegPath();
        $ffprobe = \App\Helpers\VideoCompressor::getFFprobePath();

        $execEnabled = function_exists('exec');
        $info = [
            'exec_enabled' => $execEnabled,
            'ffmpeg_path' => $ffmpeg ?? 'NOT FOUND',
            'ffprobe_path' => $ffprobe ?? 'NOT FOUND',
            'os' => PHP_OS_FAMILY,
            'status' => $ffmpeg ? 'Video compression is WORKING' : 'Video compression is NOT WORKING - videos will be uploaded as-is',
        ];

        if ($ffmpeg) {
            exec(escapeshellarg($ffmpeg) . ' -version 2>&1', $output);
            $info['ffmpeg_version'] = $output[0] ?? 'unknown';
        }

        return response()->json($info);
    })->name('admin.check-ffmpeg');

    Route::resource('admin/hatchery-categories', HatcheryCategoryController::class);
    Route::post('admin/hatchery-categories/remove-image', [HatcheryCategoryController::class, 'removeImage'])->name('hatchery-categories.remove-image');
    Route::resource('admin/hatchery-locations', HatcheryLocationController::class);
    Route::resource('admin/hatcheries', HatcheryController::class);
    Route::post('admin/hatcheries/{id}/toggle-active', [HatcheryController::class, 'toggleActive'])->name('hatcheries.toggle-active');
    Route::get('admin/get-branches/{locationId}', [HatcheryController::class, 'getBranches']);
    Route::get('admin/spot-hatcheries/get-hatchery-details/{id}', [SpotHatcheryController::class, 'getHatcheryDetails'])->name('spot-hatcheries.get-details');
    Route::resource('admin/spot-hatcheries', SpotHatcheryController::class); //spot-hatchery replica
    Route::resource('admin/vehicle-availability', VehicleAvailabilityController::class);
    Route::get('admin/vehicle-availability/get-hatchery-details/{id}', [VehicleAvailabilityController::class, 'getHatcheryDetails']);
    Route::delete('admin/vehicle-gallery/{id}', [VehicleAvailabilityController::class, 'deleteGalleryItem'])->name('vehicle-gallery.destroy');
    Route::resource('admin/hatchery-updates', HatcheryPostController::class);
    Route::resource('admin/hatchery-seeds', HatcherySeedController::class);

    // Seed Requests from App
    Route::get('admin/seed-requests', [SeedRequestController::class, 'index'])->name('admin.seed-requests.index');
    Route::get('admin/seed-requests/{seedRequest}', [SeedRequestController::class, 'show'])->name('admin.seed-requests.show');
    Route::post('admin/seed-requests/{seedRequest}/status', [SeedRequestController::class, 'updateStatus'])->name('admin.seed-requests.updateStatus');
    Route::delete('admin/seed-requests/{seedRequest}', [SeedRequestController::class, 'destroy'])->name('admin.seed-requests.destroy');

    Route::resource('admin/broad-stocks', BroadStockController::class);

    // Broodstock Configuration Routes
    Route::get('admin/app-config/broodstock', [AppConfigController::class, 'broodstockConfig'])->name('app-config.broodstock');
    Route::post('admin/app-config/broodstock', [AppConfigController::class, 'updateBroodstockConfig'])->name('app-config.broodstock.update');

    // Seed Price Disclaimer Configuration Routes
    Route::get('admin/app-config/seed-price', [AppConfigController::class, 'seedPriceConfig'])->name('app-config.seed-price');
    Route::post('admin/app-config/seed-price', [AppConfigController::class, 'updateSeedPriceConfig'])->name('app-config.seed-price.update');

    // Booking Description Configuration Routes
    Route::get('admin/app-config/booking-description', [AppConfigController::class, 'bookingDescriptionConfig'])->name('app-config.booking-description');
    Route::post('admin/app-config/booking-description', [AppConfigController::class, 'updateBookingDescriptionConfig'])->name('app-config.booking-description.update');

    Route::get('/admin/bookings/inactive-drivers', [BookingController::class, 'inactiveDrivers'])->name('bookings.inactive-drivers');
    Route::resource('admin/bookings', BookingController::class);
    Route::get('/admin/bookings/{booking}/tracking', [BookingController::class, 'tracking'])->name('bookings.tracking');
    Route::get('/admin/bookings/{booking}/tracking-data', [BookingController::class, 'trackingData'])->name('bookings.tracking-data');
    Route::post('/admin/bookings/{booking}/refresh-route', [BookingController::class, 'refreshRoute'])->name('bookings.refresh-route');
    Route::post('/admin/bookings/{booking}/status', [BookingController::class, 'updateStatus'])->name('admin.bookings.updateStatus');
    Route::post('/admin/bookings/{booking}/assign-driver', [BookingController::class, 'assignDriver'])->name('admin.bookings.assignDriver');
    Route::post('/admin/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('admin.bookings.cancel');
    Route::get('/admin/bookings/get-hatchery/{id}', [BookingController::class, 'getHatcheryDetails'])->name('admin.bookings.getHatchery');
    Route::post('/admin/bookings/store-driver', [BookingController::class, 'storeDriver'])->name('admin.bookings.storeDriver');
    Route::resource('admin/banners', BannerController::class);
    Route::resource('admin/contacts', ContactController::class);

    // Farm Management — farms and their teams.
    Route::get('admin/farm-management/farms', [FarmManagementController::class, 'index'])->name('farm-management.farms.index');
    Route::get('admin/farm-management/farms/create', [FarmManagementController::class, 'create'])->name('farm-management.farms.create');
    Route::post('admin/farm-management/farms', [FarmManagementController::class, 'store'])->name('farm-management.farms.store');
    Route::get('admin/farm-management/farms/{farm}', [FarmManagementController::class, 'show'])->name('farm-management.farms.show');
    Route::get('admin/farm-management/farms/{farm}/edit', [FarmManagementController::class, 'edit'])->name('farm-management.farms.edit');
    Route::put('admin/farm-management/farms/{farm}', [FarmManagementController::class, 'update'])->name('farm-management.farms.update');
    Route::delete('admin/farm-management/farms/{farm}', [FarmManagementController::class, 'destroy'])->name('farm-management.farms.destroy');
    Route::post('admin/farm-management/farms/{farm}/toggle-status', [FarmManagementController::class, 'toggleStatus'])->name('farm-management.farms.toggle-status');
    Route::post('admin/farm-management/farms/{farm}/restore', [FarmManagementController::class, 'restore'])->name('farm-management.farms.restore');
    Route::delete('admin/farm-management/farms/{farm}/force', [FarmManagementController::class, 'forceDestroy'])->name('farm-management.farms.force-destroy');

    Route::get('admin/farm-management/team', [FarmTeamController::class, 'index'])->name('farm-management.team.index');
    Route::get('admin/farm-management/team/create', [FarmTeamController::class, 'create'])->name('farm-management.team.create');
    Route::post('admin/farm-management/team', [FarmTeamController::class, 'store'])->name('farm-management.team.store');
    Route::get('admin/farm-management/team/{member}/edit', [FarmTeamController::class, 'edit'])->name('farm-management.team.edit');
    Route::put('admin/farm-management/team/{member}', [FarmTeamController::class, 'update'])->name('farm-management.team.update');
    Route::delete('admin/farm-management/team/{member}', [FarmTeamController::class, 'destroy'])->name('farm-management.team.destroy');

    // Tanks and their feed records — the admin equivalent of what a farmer
    // can do inside a farm in the app.
    Route::post('admin/farm-management/farms/{farm}/tanks', [FarmTankController::class, 'store'])->name('farm-management.tanks.store');
    Route::put('admin/farm-management/farms/{farm}/tanks/{tank}', [FarmTankController::class, 'update'])->name('farm-management.tanks.update');
    Route::delete('admin/farm-management/farms/{farm}/tanks/{tank}', [FarmTankController::class, 'destroy'])->name('farm-management.tanks.destroy');
    Route::post('admin/farm-management/farms/{farm}/tanks/{tank}/toggle-status', [FarmTankController::class, 'toggleStatus'])->name('farm-management.tanks.toggle-status');
    Route::get('admin/farm-management/farms/{farm}/tanks/{tank}/feed', [FarmTankController::class, 'feedHistory'])->name('farm-management.tanks.feed');
    Route::get('admin/farm-management/farms/{farm}/tanks/{tank}/feed/report', [FarmTankController::class, 'feedReport'])->name('farm-management.tanks.feed.report');
    Route::post('admin/farm-management/farms/{farm}/tanks/{tank}/feed', [FarmTankController::class, 'storeFeed'])->name('farm-management.tanks.feed.store');
    Route::put('admin/farm-management/farms/{farm}/tanks/{tank}/feed/{history}', [FarmTankController::class, 'updateFeed'])->name('farm-management.tanks.feed.update');
    Route::delete('admin/farm-management/farms/{farm}/tanks/{tank}/feed/{history}', [FarmTankController::class, 'destroyFeed'])->name('farm-management.tanks.feed.destroy');

    // Who actually holds access to a farm. This is the access itself, and now
    // the only way it is given — the QR/PIN access-code routes that used to
    // follow are gone along with the flow.
    Route::get('admin/farm-management/members', [FarmAccessMemberController::class, 'index'])->name('farm-management.members.index');
    Route::post('admin/farm-management/farms/{farm}/members', [FarmAccessMemberController::class, 'store'])->name('farm-management.members.store');
    Route::put('admin/farm-management/members/{member}', [FarmAccessMemberController::class, 'update'])->name('farm-management.members.update');
    Route::post('admin/farm-management/members/{member}/revoke', [FarmAccessMemberController::class, 'revoke'])->name('farm-management.members.revoke');
    Route::post('admin/farm-management/members/{member}/restore', [FarmAccessMemberController::class, 'restore'])->name('farm-management.members.restore');
    Route::delete('admin/farm-management/members/{member}', [FarmAccessMemberController::class, 'destroy'])->name('farm-management.members.destroy');

    Route::resource('admin/drivers', DriverController::class);
    Route::post('admin/drivers/{driver}/toggle-status', [DriverController::class, 'toggleStatus'])->name('drivers.toggle-status');
    Route::post('admin/drivers/{driver}/force-logout', [DriverController::class, 'forceLogout'])->name('drivers.force-logout');
    Route::post('admin/user_profile/{user}/force-logout', [UserController::class, 'forceLogout'])->name('user_profile.force-logout');
    Route::resource('admin/user_profile', UserController::class);

    Route::resource('admin/market_prices', MarketPriceController::class);
    Route::resource('admin/seed-wanted-listings', SeedWantedListingController::class);
    Route::resource('admin/news', NewsController::class);
    Route::resource('admin/best-deals', BestDealController::class);

    // Announcements (in-app dialog + Profile > Announcements list)
    Route::patch('admin/announcements/{announcement}/toggle-status', [AnnouncementController::class, 'toggleStatus'])->name('announcements.toggle-status');
    Route::resource('admin/announcements', AnnouncementController::class);

    // Push Notifications (Admin Broadcast)
    Route::get('admin/notifications/search-farmers', [NotificationController::class, 'searchFarmers'])->name('admin.notifications.search-farmers');
    Route::get('admin/notifications/search-hatcheries', [NotificationController::class, 'searchHatcheries'])->name('admin.notifications.search-hatcheries');
    Route::get('admin/notifications', [NotificationController::class, 'index'])->name('admin.notifications.index');
    Route::get('admin/notifications/create', [NotificationController::class, 'create'])->name('admin.notifications.create');
    Route::post('admin/notifications', [NotificationController::class, 'store'])->name('admin.notifications.store');
    Route::delete('admin/notifications/{notification}', [NotificationController::class, 'destroy'])->name('admin.notifications.destroy');

    // ⭐⭐⭐ ADD YOUR BRANCH ROUTES HERE ⭐⭐⭐
    Route::prefix('admin')->controller(HatcheryLocationBranchesController::class)->group(function () {
        Route::get('hatchery-locations/{location}/branches', 'index')->name('location.branches.index');
        Route::get('hatchery-locations/{location}/branches/create', 'create')->name('location.branches.create');
        Route::post('hatchery-locations/{location}/branches', 'store')->name('location.branches.store');

        Route::get('hatchery-location-branches/{branch}/edit', 'edit')->name('location.branches.edit');
        Route::put('hatchery-location-branches/{branch}', 'update')->name('location.branches.update');
        Route::delete('hatchery-location-branches/{branch}', 'destroy')->name('location.branches.delete');
    });

    // Role Management Routes
    Route::resource('admin/roles', RoleController::class)->names([
        'index' => 'admin.roles.index',
        'create' => 'admin.roles.create',
        'store' => 'admin.roles.store',
        'show' => 'admin.roles.show',
        'edit' => 'admin.roles.edit',
        'update' => 'admin.roles.update',
        'destroy' => 'admin.roles.destroy',
    ]);

    // Super-admin self-service password change with email OTP.  Lives
    // outside the roles resource on purpose — it doesn't mutate the role,
    // it mutates the currently-logged-in user's password.
    Route::post('admin/roles/change-password', [RoleController::class, 'changeSuperAdminPassword'])
        ->name('admin.roles.change-password');

    // Sub-Admin Management Routes
    Route::resource('admin/sub-admins', SubAdminController::class)->names([
        'index' => 'admin.sub-admins.index',
        'create' => 'admin.sub-admins.create',
        'store' => 'admin.sub-admins.store',
        'show' => 'admin.sub-admins.show',
        'edit' => 'admin.sub-admins.edit',
        'update' => 'admin.sub-admins.update',
        'destroy' => 'admin.sub-admins.destroy',
    ]);
    Route::post('admin/sub-admins/{sub_admin}/toggle-status', [SubAdminController::class, 'toggleStatus'])
        ->name('admin.sub-admins.toggle-status');

});
