<?php

namespace Tests\Feature;

use App\Services\FirebaseNotificationService;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\Message;
use Mockery;
use Tests\TestCase;

/**
 * The vendor push body is built once, server-side, and handed to BOTH
 * platforms:
 *
 *   Android → the FCM `data` block, rendered by the Flutter app
 *             (BigTextStyleInformation in notification_service.dart)
 *   iOS     → the APNs `aps.alert` block, rendered natively by iOS
 *
 * So removing "booked by" and switching to the short "#<id>" is a pure
 * backend change: iOS shows the corrected text with NO app rebuild, exactly
 * like Android. These tests pin that — if anyone ever gives iOS its own copy
 * of the body, or lets the customer's number back into the APNs alert, they
 * fail.
 */
class VendorPushIosPayloadTest extends TestCase
{
    private const BODY = "booking id : #1014\nhatchery name : Apex Hatchery";
    private const CUSTOMER_MOBILE = '8328537731';
    private const CUSTOMER_NAME = 'Adithya';

    /** Captures the CloudMessage the service hands to Firebase. */
    private function capturePayload(string $title, string $body, array $data = []): array
    {
        $captured = null;

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')
            ->once()
            ->andReturnUsing(function (Message $message) use (&$captured) {
                $captured = $message->jsonSerialize();
                return [];
            });

        // FirebaseNotificationService::__construct() resolves this.
        $this->app->instance('firebase.messaging', $messaging);

        $sent = (new FirebaseNotificationService())
            ->sendToDevice('DEVICE_TOKEN', $title, $body, null, $data);

        $this->assertTrue($sent, 'send must report success');
        $this->assertIsArray($captured, 'no message was captured');

        return $captured;
    }

    public function test_ios_apns_alert_carries_the_same_body_as_android(): void
    {
        $payload = $this->capturePayload('New Booking received', self::BODY);

        $iosBody = $payload['apns']['payload']['aps']['alert']['body'] ?? null;
        $androidBody = $payload['data']['body'] ?? null;

        $this->assertSame(self::BODY, $iosBody,
            'iOS must receive the exact server-built body');
        $this->assertSame($androidBody, $iosBody,
            'both platforms must render the identical string — one source of truth');
    }

    public function test_ios_alert_title_is_correct(): void
    {
        $payload = $this->capturePayload('New Booking received', self::BODY);

        $this->assertSame(
            'New Booking received',
            $payload['apns']['payload']['aps']['alert']['title'] ?? null
        );
    }

    /** The whole point of the change, verified on the iOS path specifically. */
    public function test_ios_alert_has_no_booked_by_line_and_no_customer_number(): void
    {
        $payload = $this->capturePayload(
            'New Booking received',
            self::BODY,
            // The data block still carries the name for the tap handler; it
            // must never reach the visible alert.
            ['type' => 'new_booking', 'customer_name' => self::CUSTOMER_NAME],
        );

        $alert = $payload['apns']['payload']['aps']['alert'];
        $visible = $alert['title'] . "\n" . $alert['body'];

        $this->assertStringNotContainsStringIgnoringCase('booked by', $visible);
        $this->assertStringNotContainsString(self::CUSTOMER_MOBILE, $visible);
        $this->assertStringNotContainsString(self::CUSTOMER_NAME, $visible);
        $this->assertStringNotContainsString('OD-BS-', $visible);
    }

    /** iOS renders the newline, so the two lines stay two lines. */
    public function test_ios_body_is_exactly_two_lines(): void
    {
        $payload = $this->capturePayload('New Booking received', self::BODY);
        $lines = explode("\n", $payload['apns']['payload']['aps']['alert']['body']);

        $this->assertCount(2, $lines);
        $this->assertStringStartsWith('booking id : #', $lines[0]);
        $this->assertStringStartsWith('hatchery name : ', $lines[1]);
    }

    /** iOS needs these to display an alert at all while backgrounded. */
    public function test_apns_is_configured_to_actually_display(): void
    {
        $payload = $this->capturePayload('New Booking received', self::BODY);

        $this->assertSame('alert', $payload['apns']['headers']['apns-push-type'] ?? null);
        $this->assertSame('10', $payload['apns']['headers']['apns-priority'] ?? null);
        $this->assertSame('default', $payload['apns']['payload']['aps']['sound'] ?? null);
    }

    /** The tap payload is identical on both platforms. */
    public function test_data_payload_is_shared_and_untouched(): void
    {
        $payload = $this->capturePayload(
            'New Booking received',
            self::BODY,
            ['type' => 'new_booking', 'booking_id' => '1014', 'booking_uid' => 'OD-BS-TS-07082026-36086'],
        );

        $this->assertSame('new_booking', $payload['data']['type']);
        $this->assertSame('1014', $payload['data']['booking_id']);
        $this->assertSame('OD-BS-TS-07082026-36086', $payload['data']['booking_uid']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
