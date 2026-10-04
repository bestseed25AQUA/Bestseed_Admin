<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppConfig;
use App\Models\Farm;
use App\Services\VideoCompressor;
use App\Support\UploadLimit;
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
            // What the server will really take, so the form states a figure
            // that is true on THIS machine rather than an aspirational one.
            'videoMaxMb'     => UploadLimit::megabytes(self::VIDEO_MAX_MB),
            'videoMaxBytes'  => UploadLimit::bytes(self::VIDEO_MAX_MB),
            'videoServerCap' => UploadLimit::cappedByServer(self::VIDEO_MAX_MB),
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

    /** Video formats a phone can actually play. */
    public const VIDEO_MIMES = ['mp4', 'mov', 'webm', 'm4v'];

    /** Megabytes. A farmer on mobile data has to download whatever is here. */
    public const VIDEO_MAX_MB = 60;

    public function update(Request $request)
    {
        $data = $request->validate([
            'farm_free_count'  => ['required', 'integer', 'min:0', 'max:50'],
            'farm_free_months' => ['required', 'integer', 'min:0', 'max:120'],
            // Only ever a pasted link. An uploaded file never comes back
            // through this field — see the view — so `url` is safe here.
            'farm_demo_video_url'   => ['nullable', 'url', 'max:500'],
            'farm_demo_video_remove' => ['nullable', 'boolean'],
            'farm_demo_video_title' => ['nullable', 'string', 'max:120'],
            // Uploaded in place of a link. A YouTube URL only opens the
            // browser; a file of our own plays in the app.
            'farm_demo_video_file'  => [
                'nullable', 'file',
                'mimetypes:video/mp4,video/quicktime,video/webm,video/x-m4v',
                'mimes:' . implode(',', self::VIDEO_MIMES),
                // The SERVER's real cap, not our stated one. php.ini may be
                // lower, and a rule promising 60 MB while PHP refuses at 2
                // produces a crash instead of a validation message.
                'max:' . (UploadLimit::megabytes(self::VIDEO_MAX_MB) * 1024),
            ],
        ], [
            'farm_free_count.required'  => 'Say how many farms come free. Enter 0 for none.',
            'farm_free_months.required' => 'Say how long the free period lasts. Enter 0 for no expiry.',
            'farm_demo_video_url.url'   => 'The demo video needs a full link, starting with https://',
            'farm_demo_video_file.mimetypes' => 'That file is not a video. Upload an '
                . strtoupper(implode(', ', self::VIDEO_MIMES)) . ' file.',
            'farm_demo_video_file.mimes'     => 'Upload an '
                . strtoupper(implode(', ', self::VIDEO_MIMES)) . ' file.',
            'farm_demo_video_file.max'       => 'That video is too large. Keep it under '
                . UploadLimit::label(self::VIDEO_MAX_MB)
                . ' — a farmer may be on mobile data.',
        ]);

        // Not columns — they only decide what the one video setting becomes.
        unset($data['farm_demo_video_file'], $data['farm_demo_video_url']);

        $current = (string) AppConfig::getValue('farm_demo_video_url', '');
        $link    = trim((string) $request->input('farm_demo_video_url', ''));

        // One setting, three ways to change it, resolved in a fixed order so
        // two half-filled boxes can never produce a surprise.
        if ($request->hasFile('farm_demo_video_file')) {
            // 1. A new upload always wins.
            $data['farm_demo_video_url'] = $this->storeVideo(
                $request->file('farm_demo_video_file')
            );

            // Re-encoded to 720p once, here, rather than sending a phone's
            // 1080p recording to every farmer who opens the screen on mobile
            // data. Never fatal: on any failure the original is kept and the
            // save still succeeds.
            $compressor = app(VideoCompressor::class);
            $note = $compressor->summarise(
                $compressor->compress(public_path($data['farm_demo_video_url']))
            );
        } elseif ($request->boolean('farm_demo_video_remove')) {
            // 2. Explicitly removed. A tickbox rather than "clear the link
            //    box", because the link box is empty whenever a FILE is in
            //    use — so blank there cannot be read as "delete it".
            $this->deleteVideoIfOurs($current);
            $data['farm_demo_video_url'] = '';
        } elseif ($link !== '') {
            // 3. A pasted link replaces whatever was there, and takes any
            //    uploaded file with it.
            $this->deleteVideoIfOurs($current);
            $data['farm_demo_video_url'] = $link;
        } else {
            // 4. Nothing said about the video: leave it exactly as it was.
            //    Saving the free-plan fields must not wipe the video.
            $data['farm_demo_video_url'] = $current;
        }

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
            // Said out loud, because the file the admin gets back is not the
            // one they chose, and a silent 80% size change looks like a bug.
            . (isset($note) && $note ? ' ' . $note : '')
        );
    }

    /**
     * Save an uploaded video and return its path, RELATIVE to public/.
     *
     * Relative, not absolute, and that distinction matters. `asset()` builds
     * against the host of the CURRENT request, so a URL baked in here would
     * be whatever the admin happened to type — `http://127.0.0.1:8000/...`
     * from a local panel, which on a phone means the phone itself. Storing
     * the path and resolving it when the API answers gives each caller a URL
     * that works from where they are, and survives the server moving.
     */
    private function storeVideo($file): string
    {
        $previous = AppConfig::getValue('farm_demo_video_url', '');

        $name = 'farm_demo_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('uploads/farm/'), $name);

        // Only after the new one is safely written.
        $this->deleteVideoIfOurs($previous);

        return 'uploads/farm/' . $name;
    }

    /**
     * Remove a previously uploaded video.
     *
     * Guarded on the path, so a YouTube link — or anything else we did not put
     * there — is never treated as a file to delete.
     */
    private function deleteVideoIfOurs(?string $url): void
    {
        // No leading slash in the match: the setting holds a RELATIVE path
        // ("uploads/farm/x.mp4") since storeVideo stopped baking in a host.
        // Matching "/uploads/farm/" skipped every delete silently, leaving
        // replaced and removed videos on disk for ever. This also still
        // matches an older absolute URL, so rows written before the change
        // clean up correctly too.
        if (!$url || !str_contains($url, 'uploads/farm/')) {
            return;
        }

        $relative = 'uploads/farm/' . basename(parse_url($url, PHP_URL_PATH) ?? '');
        $path     = public_path($relative);

        if ($relative !== 'uploads/farm/' && is_file($path)) {
            @unlink($path);
        }
    }
}
