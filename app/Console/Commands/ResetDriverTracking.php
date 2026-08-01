<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Models\VehicleTracking;
use App\Models\VehicleTrackingStop;

class ResetDriverTracking extends Command
{
    protected $signature = 'tracking:reset {driver_id} {--bookings= : Comma-separated booking IDs to reset (optional, defaults to all active)}';
    protected $description = 'Delete all tracking data (breadcrumbs, stops, location) for a driver\'s active bookings';

    public function handle()
    {
        $driverId = $this->argument('driver_id');
        $bookingIdsOption = $this->option('bookings');

        if ($bookingIdsOption) {
            $bookingIds = collect(explode(',', $bookingIdsOption))->map(fn($id) => (int) trim($id))->toArray();
            $bookings = Booking::where('driver_id', $driverId)->whereIn('id', $bookingIds)->get();
        } else {
            $bookings = Booking::where('driver_id', $driverId)->where('status', 4)->get();
        }

        if ($bookings->isEmpty()) {
            $this->error("No bookings found for driver {$driverId}");
            return;
        }

        $this->info("Resetting tracking for driver {$driverId}, bookings: " . $bookings->pluck('id')->join(', '));

        foreach ($bookings as $booking) {
            // Delete tracking points (breadcrumbs/timeline)
            $trackingCount = VehicleTracking::where('booking_id', $booking->id)->count();
            VehicleTracking::where('booking_id', $booking->id)->delete();

            // Delete stops and substops
            $stopCount = VehicleTrackingStop::where('booking_id', $booking->id)->count();
            VehicleTrackingStop::where('booking_id', $booking->id)->delete();

            // Reset driver location on booking
            $booking->update([
                'driver_lat' => $booking->pickup_lat,
                'driver_lng' => $booking->pickup_lng,
                'driver_location_name' => null,
                'driver_location_updated_at' => now(),
                'tracking_path' => null,
                'tracking_path_last_id' => null,
                'tracking_path_at' => null,
            ]);

            $this->info("  Booking #{$booking->id}: deleted {$trackingCount} tracking points, {$stopCount} stops. Location reset to pickup.");
        }

        $this->info("Done! Driver is back at pickup. Run simulation to start fresh.");
    }
}
