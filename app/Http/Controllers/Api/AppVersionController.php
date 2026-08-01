<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppConfig;
use Illuminate\Http\Request;

/**
 * Force-update / version check for both mobile apps.
 *
 * Both apps call this endpoint on startup and compare their own version
 * against `min_version`. If lower, they show a blocking update dialog
 * with a link to `store_url`. If lower than `latest_version` but at or
 * above `min_version`, they show an optional "update available" prompt.
 *
 * Config keys live in `app_configs` and are seeded by the migration
 * 2026_07_20_000001_seed_app_version_configs.php.
 */
class AppVersionController extends Controller
{
    /**
     * GET /api/app-version-check?platform=ios|android&app=driver|vendor
     *
     * Responds:
     *   {
     *     "status": true,
     *     "min_version":    "1.0.7",
     *     "latest_version": "1.0.8",
     *     "store_url":      "https://apps.apple.com/...",
     *     "changelog":      "Live Activity, on-demand tracking..."   // optional
     *   }
     */
    public function check(Request $request)
    {
        $platform = strtolower((string) $request->query('platform', ''));
        $app      = strtolower((string) $request->query('app', ''));

        if (!in_array($platform, ['ios', 'android'], true)) {
            return response()->json([
                'status'  => false,
                'message' => 'platform must be ios or android',
            ], 422);
        }
        if (!in_array($app, ['driver', 'vendor'], true)) {
            return response()->json([
                'status'  => false,
                'message' => 'app must be driver or vendor',
            ], 422);
        }

        $prefix = "app_version_{$app}_{$platform}";

        return response()->json([
            'status'         => true,
            'min_version'    => AppConfig::getValue("{$prefix}_min", '0.0.0'),
            'latest_version' => AppConfig::getValue("{$prefix}_latest", '0.0.0'),
            'store_url'      => AppConfig::getValue("{$prefix}_store_url", ''),
            'changelog'      => AppConfig::getValue("{$prefix}_changelog", ''),
        ]);
    }
}
