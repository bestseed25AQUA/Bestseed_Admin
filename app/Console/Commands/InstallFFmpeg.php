<?php

namespace App\Console\Commands;

use App\Helpers\VideoCompressor;
use Illuminate\Console\Command;

class InstallFFmpeg extends Command
{
    protected $signature = 'setup:ffmpeg';
    protected $description = 'Download and install static FFmpeg binary for video compression';

    public function handle()
    {
        // Check if already available
        $existing = VideoCompressor::getFFmpegPath();
        if ($existing) {
            $this->info("FFmpeg is already available at: {$existing}");
            return 0;
        }

        if (PHP_OS_FAMILY !== 'Linux') {
            $this->error('Auto-download only works on Linux servers.');
            $this->line('On Windows/Mac, install FFmpeg manually and add it to your PATH.');
            return 1;
        }

        $this->info('Downloading static FFmpeg binary...');
        $this->line('This may take a few minutes depending on your server speed.');

        $result = VideoCompressor::downloadStaticFFmpeg();

        if ($result) {
            $this->info('FFmpeg installed successfully at: ' . storage_path('app/bin/ffmpeg'));
            $this->info('Videos uploaded through admin panel will now be auto-compressed to 1080p.');
            return 0;
        }

        $this->error('Failed to download FFmpeg. Check the Laravel log for details.');
        return 1;
    }
}
