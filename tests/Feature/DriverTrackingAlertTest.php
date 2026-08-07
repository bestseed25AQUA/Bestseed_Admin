<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\TrackingAlert;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regression cover for: the admin Bookings bell stopped showing driver-device
 * problems (no internet, phone off, location off...).
 *
 * The driver app's TrackingAlertService emits no_internet / location_off /
 * location_not_sending / battery_low, but this endpoint validated issue_type
 * against gps_off / internet_off / permission_denied / tracking_stopped /
 * tracking_restarted. Only gps_off overlapped, so every other alert was
 * rejected 422 and driver_inactive_reason was never set — leaving the bell
 * permanently empty for those cases.
 */
class DriverTrackingAlertTest extends TestCase
{
    /** Exactly what lib/driver/services/tracking_alert_service.dart sends. */
    public const APP_ISSUE_TYPES = [
        'no_internet',
        'gps_off',
        'location_off',
        'battery_low',
        'location_not_sending',
    ];

    /** The vocabulary this endpoint already accepted. */
    public const LEGACY_ISSUE_TYPES = [
        'gps_off',
        'internet_off',
        'permission_denied',
        'tracking_stopped',
        'tracking_restarted',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Built by hand rather than via migrate:fresh — three tables in this
        // project are created by two migrations each, so a fresh migrate aborts.
        Schema::dropIfExists('bookings');
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_uid')->nullable();
            $table->integer('status')->default(1);
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('driver_location_name')->nullable();
            $table->string('driver_inactive_reason')->nullable();
            $table->timestamp('last_tracking_alert_at')->nullable();
            $table->timestamp('driver_location_updated_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('driver_assigned_at')->nullable();
            $table->timestamp('in_progress_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('drivers');
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('mobile')->nullable();
            $table->string('fcm_token')->nullable();
            // CheckVendorActive rejects when `status == 0`, and a missing column
            // reads as null — which loosely equals 0 and 403s every request.
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::dropIfExists('tracking_alerts');
        Schema::create('tracking_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->string('reason')->nullable();
            $table->string('location_name')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    /** A driver on an active (status 4 = In Journey) booking. */
    private function driverOnActiveBooking(): array
    {
        $driver = Driver::create(['name' => 'Ramu', 'mobile' => '9000000001', 'status' => 1]);
        $booking = Booking::create([
            'booking_uid' => 'BK-1018',
            'status' => 4,
            'driver_id' => $driver->id,
            'driver_name' => 'Ramu',
        ]);
        Sanctum::actingAs($driver, ['*']);

        return [$driver, $booking];
    }

    private function postAlert(string $issueType)
    {
        return $this->postJson('/api/driver/tracking-alert', [
            'issue_type' => $issueType,
        ]);
    }

    /**
     * The core regression: every alert the app can emit must be accepted and
     * must land a reason on the booking for the admin bell.
     *
     * @dataProvider appIssueTypes
     */
    public function test_every_issue_type_the_app_sends_is_accepted(string $issueType): void
    {
        [, $booking] = $this->driverOnActiveBooking();

        $response = $this->postAlert($issueType);

        $response->assertOk()->assertJsonPath('status', true);

        $booking->refresh();
        $this->assertNotNull(
            $booking->driver_inactive_reason,
            "'$issueType' must set driver_inactive_reason so the admin bell shows it"
        );
        $this->assertNotSame('Tracking issue', $booking->driver_inactive_reason,
            "'$issueType' must map to a readable description, not the fallback");
    }

    public static function appIssueTypes(): array
    {
        return array_map(fn ($t) => [$t], array_combine(self::APP_ISSUE_TYPES, self::APP_ISSUE_TYPES));
    }

    /** Older app builds must keep working. */
    public function test_legacy_issue_types_still_accepted(): void
    {
        foreach (self::LEGACY_ISSUE_TYPES as $issueType) {
            [, $booking] = $this->driverOnActiveBooking();
            $this->postAlert($issueType)->assertOk();

            $booking->refresh();
            if ($issueType === 'tracking_restarted') {
                $this->assertNull($booking->driver_inactive_reason,
                    'tracking_restarted must CLEAR the reason');
            } else {
                $this->assertNotNull($booking->driver_inactive_reason, $issueType);
            }
        }
    }

    public function test_alert_is_recorded_in_history(): void
    {
        [$driver, $booking] = $this->driverOnActiveBooking();

        $this->postAlert('no_internet')->assertOk();

        $this->assertDatabaseHas('tracking_alerts', [
            'booking_id' => $booking->id,
            'driver_id' => $driver->id,
            'reason' => 'Driver internet connection is down',
        ]);
        $this->assertSame(1, TrackingAlert::count());
    }

    public function test_each_issue_type_has_its_own_readable_description(): void
    {
        $seen = [];
        foreach (self::APP_ISSUE_TYPES as $issueType) {
            [, $booking] = $this->driverOnActiveBooking();
            $this->postAlert($issueType)->assertOk();
            $seen[$issueType] = $booking->refresh()->driver_inactive_reason;
        }

        // battery_low is a distinct condition and must not reuse another label.
        $this->assertStringContainsString('battery', strtolower($seen['battery_low']));
        $this->assertStringContainsString('internet', strtolower($seen['no_internet']));
        $this->assertStringContainsString('gps', strtolower($seen['gps_off']));
    }

    public function test_unknown_issue_type_is_still_rejected(): void
    {
        $this->driverOnActiveBooking();

        $this->postAlert('totally_made_up')
            ->assertStatus(422)
            ->assertJsonValidationErrors('issue_type');
    }

    public function test_alert_without_an_active_booking_is_a_noop(): void
    {
        $driver = Driver::create(['name' => 'Idle', 'mobile' => '9000000002', 'status' => 1]);
        // status 3 = Driver Assigned, journey not started.
        Booking::create(['status' => 3, 'driver_id' => $driver->id]);
        Sanctum::actingAs($driver, ['*']);

        $this->postAlert('no_internet')
            ->assertOk()
            ->assertJsonPath('status', false);

        $this->assertSame(0, TrackingAlert::count());
    }

    public function test_alert_only_touches_this_drivers_bookings(): void
    {
        [, $mine] = $this->driverOnActiveBooking();

        $other = Driver::create(['name' => 'Someone', 'mobile' => '9000000003', 'status' => 1]);
        $theirs = Booking::create(['status' => 4, 'driver_id' => $other->id]);

        $this->postAlert('gps_off')->assertOk();

        $this->assertNotNull($mine->refresh()->driver_inactive_reason);
        $this->assertNull($theirs->refresh()->driver_inactive_reason,
            'another driver\'s booking must be untouched');
    }
}
