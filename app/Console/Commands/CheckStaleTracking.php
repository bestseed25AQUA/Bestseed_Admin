<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\Vendors_apis\DriverBookingController;
use Illuminate\Console\Command;

class CheckStaleTracking extends Command
{
    protected $signature = 'tracking:check-stale';
    protected $description = 'Check for active bookings with stale driver location and alert vendor + admin';

    public function handle()
    {
        // Mark that cron ran — visible via /api/cron-check
        \Illuminate\Support\Facades\Cache::put('last_scheduler_run', now()->toDateTimeString(), 3600);

        $alertCount = DriverBookingController::checkAndAlertStaleBookings();

        if ($alertCount > 0) {
            $this->info("Sent {$alertCount} stale tracking alert(s).");
        } else {
            $this->info('No stale tracking detected.');
        }

        return Command::SUCCESS;
    }
}
