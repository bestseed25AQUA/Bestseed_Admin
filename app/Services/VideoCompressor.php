<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Re-encodes an uploaded video to something a farmer can afford to download.
 *
 * A phone records at 1080p or 4K and a two-minute clip can run to 150 MB. The
 * admin uploading it is on office wifi; the farmer watching it is on mobile
 * data in a field, and pays for every megabyte. So the file is re-encoded once
 * here rather than being sent out as recorded, over and over.
 *
 * 720p H.264 at CRF 28 is the target: small enough to stream on a weak
 * connection, sharp enough for a screen recording of the app. Typical saving
 * on a phone recording is 60–85%.
 *
 * Nothing here is required. With no ffmpeg installed, or on any failure, the
 * ORIGINAL file is kept and the upload still succeeds — a demo video is not
 * worth losing an admin's work over.
 */
class VideoCompressor
{
    /** Longest edge of the output. 720p reads well and encodes quickly. */
    private const MAX_HEIGHT = 720;

    /**
     * Constant Rate Factor: lower is better quality and a bigger file.
     * 28 is near the top of the "visually fine for screen content" range.
     */
    private const CRF = 28;

    /** Seconds. A long video on a slow machine must not hang the request. */
    private const TIMEOUT = 600;

    /** Is ffmpeg on this machine? */
    public function available(): bool
    {
        return $this->binary() !== null;
    }

    /**
     * Compress in place, returning what happened.
     *
     * The caller gets a result rather than an exception, because "could not
     * compress" is not an error worth failing an upload over — it only means
     * the original is kept.
     *
     * @return array{compressed: bool, before: int, after: int, reason: ?string}
     */
    public function compress(string $path): array
    {
        $before = is_file($path) ? (int) filesize($path) : 0;

        $result = [
            'compressed' => false,
            'before'     => $before,
            'after'      => $before,
            'reason'     => null,
        ];

        $ffmpeg = $this->binary();

        if ($ffmpeg === null) {
            return ['reason' => 'ffmpeg is not installed'] + $result;
        }

        if ($before === 0) {
            return ['reason' => 'the file is missing or empty'] + $result;
        }

        // Alongside the original, so a half-written encode can never be
        // mistaken for the finished file.
        $temp = $path . '.compressing.mp4';

        $process = new Process([
            $ffmpeg,
            '-y',
            '-i', $path,
            // Scale to at most 720p, keeping the aspect ratio, and only ever
            // DOWN — upscaling a small clip would make it bigger for nothing.
            // -2 keeps the width even, which H.264 requires.
            '-vf', 'scale=-2:min(' . self::MAX_HEIGHT . '\,ih)',
            '-c:v', 'libx264',
            '-preset', 'medium',
            '-crf', (string) self::CRF,
            // Baseline-friendly pixel format: some Android players show a
            // green screen without it.
            '-pix_fmt', 'yuv420p',
            '-c:a', 'aac',
            '-b:a', '96k',
            // Puts the index at the front so playback can start before the
            // whole file has arrived — the difference between a video that
            // streams and one that must download first.
            '-movflags', '+faststart',
            $temp,
        ]);

        $process->setTimeout(self::TIMEOUT);

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            @unlink($temp);
            Log::warning('Video compression timed out', ['path' => $path]);

            return ['reason' => 'it took too long to compress'] + $result;
        }

        if (!$process->isSuccessful() || !is_file($temp)) {
            @unlink($temp);
            Log::warning('Video compression failed', [
                'path'  => $path,
                'error' => substr($process->getErrorOutput(), -500),
            ]);

            return ['reason' => 'the video could not be re-encoded'] + $result;
        }

        $after = (int) filesize($temp);

        // Keep whichever is smaller.
        //
        // An already-compressed upload can come out LARGER after re-encoding,
        // and replacing it then would make the farmer's download worse while
        // reporting success.
        if ($after === 0 || $after >= $before) {
            @unlink($temp);

            return ['reason' => 'the original was already smaller'] + $result;
        }

        if (!@rename($temp, $path)) {
            @unlink($temp);

            return ['reason' => 'the compressed file could not be saved'] + $result;
        }

        return [
            'compressed' => true,
            'before'     => $before,
            'after'      => $after,
            'reason'     => null,
        ];
    }

    /** A human sentence about what happened, or null when nothing did. */
    public function summarise(array $result): ?string
    {
        if (!$result['compressed']) {
            return $result['reason']
                ? 'The video was saved as uploaded — ' . $result['reason'] . '.'
                : null;
        }

        $saved = 100 - (int) round($result['after'] / $result['before'] * 100);

        return sprintf(
            'Video compressed from %s to %s (%d%% smaller).',
            $this->size($result['before']),
            $this->size($result['after']),
            $saved
        );
    }

    private function size(int $bytes): string
    {
        return $bytes >= 1024 * 1024
            ? round($bytes / (1024 * 1024), 1) . ' MB'
            : max(1, (int) round($bytes / 1024)) . ' KB';
    }

    /**
     * Where ffmpeg is, or null.
     *
     * The PATH a web server runs under is often not a shell's, so the usual
     * install locations are checked too rather than trusting `which` alone.
     */
    private function binary(): ?string
    {
        static $resolved = false;
        static $path = null;

        if ($resolved) {
            return $path;
        }

        $resolved = true;

        foreach (['/opt/homebrew/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/usr/bin/ffmpeg'] as $candidate) {
            if (is_executable($candidate)) {
                return $path = $candidate;
            }
        }

        $which = trim((string) @shell_exec('command -v ffmpeg 2>/dev/null'));

        return $path = ($which !== '' && is_executable($which)) ? $which : null;
    }
}
