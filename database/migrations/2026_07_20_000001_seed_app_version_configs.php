<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed the app_version_* keys in app_configs so the /api/app-version-check
 * endpoint has something to return from day one.
 *
 * Naming convention:
 *   app_version_{app}_{platform}_{field}
 * where:
 *   app      = driver | vendor
 *   platform = ios    | android
 *   field    = min | latest | store_url | changelog
 *
 * Both driver and vendor apps read these on startup. Update the values
 * through the admin panel (or via SQL) whenever a new build ships.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Adjust these placeholders to your actual production values
        // through the admin panel once a build is out.
        $seeds = [
            // Driver — iOS
            ['app_version_driver_ios_min',       '1.0.7'],
            ['app_version_driver_ios_latest',    '1.0.8'],
            ['app_version_driver_ios_store_url', 'https://apps.apple.com/app/drive-bestseed/id0000000000'],
            ['app_version_driver_ios_changelog', 'Live-activity tile, on-demand fresh GPS, notification fixes.'],

            // Driver — Android
            ['app_version_driver_android_min',       '1.0.7'],
            ['app_version_driver_android_latest',    '1.0.8'],
            ['app_version_driver_android_store_url', 'https://play.google.com/store/apps/details?id=com.techlanditsolutions.bestseeds.driver'],
            ['app_version_driver_android_changelog', 'On-demand fresh GPS, notification fixes.'],

            // Vendor — iOS
            ['app_version_vendor_ios_min',       '1.0.0'],
            ['app_version_vendor_ios_latest',    '1.0.0'],
            ['app_version_vendor_ios_store_url', 'https://apps.apple.com/app/bestseed-vendor/id0000000000'],
            ['app_version_vendor_ios_changelog', ''],

            // Vendor — Android
            ['app_version_vendor_android_min',       '1.0.0'],
            ['app_version_vendor_android_latest',    '1.0.0'],
            ['app_version_vendor_android_store_url', 'https://play.google.com/store/apps/details?id=com.techlanditsolutions.bestseeds.vendor'],
            ['app_version_vendor_android_changelog', ''],
        ];

        foreach ($seeds as [$key, $value]) {
            DB::table('app_configs')->updateOrInsert(
                ['config_key' => $key],
                [
                    'config_value' => $value,
                    'config_group' => 'app_version',
                    'description'  => 'Force-update / version check',
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('app_configs')
            ->where('config_group', 'app_version')
            ->delete();
    }
};
