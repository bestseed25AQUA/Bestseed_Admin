<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class VideoCompressor
{
    private static $videoExtensions = ['mp4', 'mov', 'avi', 'webm', 'wmv'];

    // Max dimension (width or height) for 1080p compatibility
    private static $maxDimension = 1920;

    /**
     * Check if the file is a video based on extension.
     */
    public static function isVideo(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($extension, self::$videoExtensions);
    }

    /**
     * Compress video to max 1080p resolution if needed.
     * Auto-downloads FFmpeg if not found on the server.
     */
    public static function compress(string $relativePath): string
    {
        if (!self::isVideo($relativePath)) {
            return $relativePath;
        }

        $fullPath = public_path($relativePath);
        if (!file_exists($fullPath)) {
            Log::warning('VideoCompressor: File not found', ['path' => $fullPath]);
            return $relativePath;
        }

        // exec() is disabled on some managed hosts (disable_functions in php.ini).
        // Without it we cannot locate or run FFmpeg, so skip compression and keep
        // the original upload rather than fataling with a 500.
        if (!function_exists('exec')) {
            Log::warning('VideoCompressor: exec() is disabled on this server, skipping compression', ['path' => $relativePath]);
            return $relativePath;
        }

        // Find FFmpeg binary (system or auto-downloaded local)
        $ffmpeg = self::getFFmpegPath();
        $ffprobe = self::getFFprobePath();

        if (!$ffmpeg) {
            Log::warning('VideoCompressor: FFmpeg not available, skipping compression');
            return $relativePath;
        }

        // Check if compression is needed
        $dimensions = self::getVideoDimensions($fullPath, $ffprobe);
        if (!$dimensions) {
            Log::warning('VideoCompressor: Could not read video dimensions, attempting compression', ['path' => $relativePath]);
        } elseif ($dimensions['width'] <= self::$maxDimension && $dimensions['height'] <= self::$maxDimension) {
            Log::info('VideoCompressor: Video already within limits, skipping', [
                'path' => $relativePath,
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
            ]);
            return $relativePath;
        }

        // Calculate target dimensions
        if ($dimensions) {
            $scale = min(self::$maxDimension / $dimensions['width'], self::$maxDimension / $dimensions['height'], 1.0);
            $targetWidth = (int)(floor($dimensions['width'] * $scale / 2) * 2);
            $targetHeight = (int)(floor($dimensions['height'] * $scale / 2) * 2);
        } else {
            $targetWidth = -2;
            $targetHeight = self::$maxDimension;
        }

        $pathInfo = pathinfo($fullPath);
        $outputPath = $pathInfo['dirname'] . DIRECTORY_SEPARATOR . $pathInfo['filename'] . '_compressed.mp4';

        if ($targetWidth > 0 && $targetHeight > 0) {
            $scaleFilter = "scale={$targetWidth}:{$targetHeight}";
        } else {
            $scaleFilter = "scale=-2:{$targetHeight}";
        }

        $command = sprintf(
            '%s -i %s -vf "%s" -c:v libx264 -preset fast -crf 23 -c:a aac -movflags +faststart -y %s 2>&1',
            escapeshellarg($ffmpeg),
            escapeshellarg($fullPath),
            $scaleFilter,
            escapeshellarg($outputPath)
        );

        Log::info('VideoCompressor: Starting compression', [
            'input' => $relativePath,
            'dimensions' => $dimensions,
            'target' => "{$targetWidth}x{$targetHeight}",
        ]);

        $previousTimeLimit = ini_get('max_execution_time');
        set_time_limit(300);

        exec($command, $output, $returnCode);

        set_time_limit((int)$previousTimeLimit);

        if ($returnCode === 0 && file_exists($outputPath)) {
            @unlink($fullPath);

            $newFileName = $pathInfo['filename'] . '.mp4';
            $newFullPath = $pathInfo['dirname'] . DIRECTORY_SEPARATOR . $newFileName;
            rename($outputPath, $newFullPath);

            $relativeDir = str_replace('\\', '/', pathinfo($relativePath, PATHINFO_DIRNAME));
            $newRelativePath = $relativeDir . '/' . $newFileName;

            Log::info('VideoCompressor: Compression successful', [
                'original' => $relativePath,
                'compressed' => $newRelativePath,
            ]);

            return $newRelativePath;
        }

        Log::warning('VideoCompressor: Compression failed', [
            'path' => $relativePath,
            'return_code' => $returnCode,
            'output' => implode("\n", array_slice($output, -5)),
        ]);

        if (file_exists($outputPath)) {
            @unlink($outputPath);
        }

        return $relativePath;
    }

    /**
     * Get FFmpeg path. Checks system, then local, then auto-downloads.
     */
    public static function getFFmpegPath(): ?string
    {
        // 1. System PATH
        $system = self::findBinary('ffmpeg');
        if ($system) return $system;

        // 2. Local binary
        $local = storage_path('app/bin/ffmpeg');
        if (file_exists($local)) {
            if (PHP_OS_FAMILY !== 'Windows') @chmod($local, 0755);
            return $local;
        }

        // 3. Auto-download on Linux
        if (PHP_OS_FAMILY === 'Linux') {
            Log::info('VideoCompressor: FFmpeg not found, auto-downloading...');
            if (self::downloadStaticFFmpeg()) {
                return file_exists($local) ? $local : null;
            }
        }

        return null;
    }

    /**
     * Get FFprobe path.
     */
    public static function getFFprobePath(): ?string
    {
        $system = self::findBinary('ffprobe');
        if ($system) return $system;

        $local = storage_path('app/bin/ffprobe');
        if (file_exists($local)) {
            if (PHP_OS_FAMILY !== 'Windows') @chmod($local, 0755);
            return $local;
        }

        return null;
    }

    /**
     * Find binary in system PATH.
     */
    private static function findBinary(string $name): ?string
    {
        if (!function_exists('exec')) {
            return null;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            exec("where {$name} 2>NUL", $output, $code);
        } else {
            exec("which {$name} 2>/dev/null", $output, $code);
        }

        return ($code === 0 && !empty($output[0])) ? trim($output[0]) : null;
    }

    /**
     * Download static FFmpeg + FFprobe binaries to storage/app/bin/.
     * Works on Linux servers (amd64 and arm64).
     */
    public static function downloadStaticFFmpeg(): bool
    {
        $binDir = storage_path('app/bin');
        if (!is_dir($binDir)) {
            mkdir($binDir, 0755, true);
        }

        // Prevent concurrent downloads
        $lockFile = $binDir . '/download.lock';
        if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 600) {
            Log::info('VideoCompressor: Download already in progress');
            return false;
        }
        file_put_contents($lockFile, time());

        try {
            $arch = strtolower(trim(php_uname('m')));

            $url = match (true) {
                in_array($arch, ['x86_64', 'amd64']) => 'https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-amd64-static.tar.xz',
                in_array($arch, ['aarch64', 'arm64']) => 'https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-arm64-static.tar.xz',
                default => null,
            };

            if (!$url) {
                Log::warning('VideoCompressor: Unsupported architecture: ' . $arch);
                return false;
            }

            Log::info('VideoCompressor: Downloading FFmpeg', ['arch' => $arch]);

            $tarFile = $binDir . '/ffmpeg-static.tar.xz';

            // Download the archive
            $downloaded = self::downloadFile($url, $tarFile);
            if (!$downloaded) {
                Log::warning('VideoCompressor: Download failed');
                @unlink($tarFile);
                return false;
            }

            Log::info('VideoCompressor: Download complete, extracting...');

            // Extract ffmpeg and ffprobe
            $tempDir = $binDir . '/extract-temp';
            if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);

            exec(sprintf('tar xf %s -C %s 2>&1', escapeshellarg($tarFile), escapeshellarg($tempDir)), $out, $code);

            if ($code !== 0) {
                Log::warning('VideoCompressor: Extraction failed', ['output' => implode("\n", $out)]);
                @unlink($tarFile);
                self::removeDir($tempDir);
                return false;
            }

            // Find and copy binaries from extracted directory
            $found = false;
            $dirs = @scandir($tempDir);
            if ($dirs) {
                foreach ($dirs as $dir) {
                    if ($dir === '.' || $dir === '..') continue;
                    $srcDir = $tempDir . '/' . $dir;

                    if (file_exists($srcDir . '/ffmpeg')) {
                        copy($srcDir . '/ffmpeg', $binDir . '/ffmpeg');
                        chmod($binDir . '/ffmpeg', 0755);
                        $found = true;
                    }
                    if (file_exists($srcDir . '/ffprobe')) {
                        copy($srcDir . '/ffprobe', $binDir . '/ffprobe');
                        chmod($binDir . '/ffprobe', 0755);
                    }
                    break;
                }
            }

            // Cleanup
            @unlink($tarFile);
            self::removeDir($tempDir);

            if ($found) {
                Log::info('VideoCompressor: FFmpeg installed successfully');
            }

            return $found;
        } finally {
            @unlink($lockFile);
        }
    }

    /**
     * Download file using curl, wget, or PHP stream.
     */
    private static function downloadFile(string $url, string $dest): bool
    {
        $prev = ini_get('max_execution_time');
        set_time_limit(600); // 10 min for download

        // Try curl
        if (self::findBinary('curl')) {
            exec(sprintf('curl -L -f -o %s %s 2>&1', escapeshellarg($dest), escapeshellarg($url)), $out, $code);
            set_time_limit((int)$prev);
            return ($code === 0 && file_exists($dest) && filesize($dest) > 1000000);
        }

        // Try wget
        if (self::findBinary('wget')) {
            exec(sprintf('wget -q -O %s %s 2>&1', escapeshellarg($dest), escapeshellarg($url)), $out, $code);
            set_time_limit((int)$prev);
            return ($code === 0 && file_exists($dest) && filesize($dest) > 1000000);
        }

        // PHP fallback
        $ctx = stream_context_create(['http' => ['timeout' => 300]]);
        $content = @file_get_contents($url, false, $ctx);
        set_time_limit((int)$prev);

        if ($content && strlen($content) > 1000000) {
            file_put_contents($dest, $content);
            return true;
        }

        return false;
    }

    /**
     * Recursively remove a directory.
     */
    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) ? self::removeDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    /**
     * Get video dimensions using ffprobe.
     */
    private static function getVideoDimensions(string $fullPath, ?string $ffprobe = null): ?array
    {
        $ffprobe = $ffprobe ?: self::getFFprobePath();
        if (!$ffprobe) return null;

        $command = sprintf(
            '%s -v error -select_streams v:0 -show_entries stream=width,height -of csv=s=x:p=0 %s 2>&1',
            escapeshellarg($ffprobe),
            escapeshellarg($fullPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || empty($output[0])) {
            return null;
        }

        $parts = explode('x', trim($output[0]));
        if (count($parts) !== 2) {
            return null;
        }

        return [
            'width' => (int)$parts[0],
            'height' => (int)$parts[1],
        ];
    }
}
