<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppConfig;
use App\Models\Farm;
use App\Services\FarmLicenceService;
use Illuminate\Http\Request;

/**
 * The free plan, and the demo video.
 *
 * Both were fixed in code: two free farms for ever, and no video at all. They
 * are commercial decisions, so they belong to whoever is selling — "one free
 * farm for three months" is a form here rather than a deploy.
 */
class FarmSettingsController extends Controller
{
    /** Months a free farm may last. 0 is "never expires". */
    public const DURATIONS = [0, 1, 2, 3, 6, 12];

    public function edit()
    {
        $licence = app(FarmLicenceService::class);

        return view('admin.farm-management.settings', [
            'freeCount'  => $licence->freeCount(),
            'freeMonths' => $licence->freeMonths(),
            'videoUrl'   => AppConfig::getValue('farm_demo_video_url', ''),
            'videoTitle' => AppConfig::getValue('farm_demo_video_title', 'How Farm Management works'),
            'durations'  => self::DURATIONS,

            // What changing this would mean right now, so the decision is made
            // with the consequence in view rather than discovered afterwards.
            //
            // Farms that SPENT a free slot, not farms that merely have no
            // subscription today: a farm whose free period lapsed and which
            // was then put on a package still used its slot up, and counting
            // by "has no subscription" both missed those and swept in every
            // grandfathered farm as though it were on the current free plan.
            'farmsOnFree' => Farm::withTrashed()->where('took_free_slot', true)->count(),

            // Created under the old unlimited free plan. Named separately
            // because these are the farms the settings genuinely cannot reach.
            'legacyFarms' => Farm::withTrashed()->where('legacy_free', true)->count(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'farm_free_count'  => ['required', 'integer', 'min:0', 'max:50'],
            'farm_free_months' => ['required', 'integer', 'min:0', 'max:120'],
            'farm_demo_video_url'   => ['nullable', 'url', 'max:500'],
            'farm_demo_video_title' => ['nullable', 'string', 'max:120'],
        ], [
            'farm_free_count.required'  => 'Say how many farms come free. Enter 0 for none.',
            'farm_free_months.required' => 'Say how long the free period lasts. Enter 0 for no expiry.',
            'farm_demo_video_url.url'   => 'The demo video needs a full link, starting with https://',
        ]);

        foreach ($data as $key => $value) {
            AppConfig::setValue($key, (string) ($value ?? ''), 'farm_management');
        }

        // Deliberately NOT applied to farms that already exist.
        //
        // Their free period was set when they were created, and shortening the
        // setting must not retroactively lock farms a farmer is working in
        // today. New farms get the new terms; the old ones keep what they were
        // given.
        return back()->with(
            'success',
            'Farm Management settings saved. They apply to farms created from now on — '
            . 'existing farms keep the terms they were created under.'
        );
    }
}
