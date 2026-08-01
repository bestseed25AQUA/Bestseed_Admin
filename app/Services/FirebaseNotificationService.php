<?php

namespace App\Services;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Illuminate\Support\Facades\Log;

class FirebaseNotificationService
{
    protected Messaging $messaging;

    public function __construct()
    {
        $this->messaging = app('firebase.messaging');
    }

    /**
     * Send notification to a topic (e.g. 'all_users' for broadcast).
     *
     * Data-only for Android, APNs alert for iOS.  See sendToDevice() for the
     * why — the top-level `notification` block used to trigger duplicate
     * notifications on MIUI / OnePlus / Realme (FCM SDK auto-displayed AND
     * the app's onMessage handler also fired).  Making Android data-only
     * hands full display control to the Flutter side; iOS keeps its native
     * APNs display path.
     */
    public function sendToTopic(string $topic, string $title, string $body, ?string $imageUrl = null, array $data = []): bool
    {
        try {
            $payload = array_merge(
                array_map('strval', $data),
                [
                    'title' => $title,
                    'body'  => $body,
                    'image' => (string) ($imageUrl ?? ''),
                ]
            );

            $apns = [
                'headers' => [
                    'apns-priority'  => '10',
                    'apns-push-type' => 'alert',
                ],
                'payload' => [
                    'aps' => [
                        'alert' => [
                            'title' => $title,
                            'body'  => $body,
                        ],
                        'sound'           => 'default',
                        'mutable-content' => 1,
                    ],
                ],
            ];
            if ($imageUrl) {
                $apns['fcm_options'] = ['image' => $imageUrl];
            }

            $message = CloudMessage::fromArray([
                'topic'   => $topic,
                'data'    => $payload,
                'android' => [
                    'priority' => 'high',
                ],
                'apns' => $apns,
            ]);

            $this->messaging->send($message);

            Log::info("FCM sent to topic '$topic'", ['title' => $title]);
            return true;
        } catch (\Exception $e) {
            Log::error('FCM send to topic failed', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send a DATA-ONLY high-priority message to a specific device.
     *
     * Unlike sendToDevice(), this carries no `notification` block. That matters
     * for silent/control messages like `force_logout`: a message WITH a
     * notification payload is handled by the OS tray when the app is in the
     * background or terminated, and the app's background handler does NOT run
     * until the user taps it. A data-only, high-priority message is delivered
     * straight to the background isolate (Android) / wakes the app for a silent
     * background fetch (iOS `content-available`), so the device can act on it
     * (e.g. clear the session) even when backgrounded or killed.
     */
    public function sendDataToDevice(string $fcmToken, array $data = []): bool
    {
        try {
            $message = CloudMessage::fromArray([
                'token'   => $fcmToken,
                'data'    => array_map('strval', $data),
                'android' => [
                    'priority' => 'high',
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority'  => '5',
                        'apns-push-type' => 'background',
                    ],
                    'payload' => [
                        'aps' => [
                            'content-available' => 1,
                        ],
                    ],
                ],
            ]);

            $this->messaging->send($message);

            Log::info('FCM data-only sent to device', ['type' => $data['type'] ?? '']);
            return true;
        } catch (\Exception $e) {
            Log::error('FCM data-only send to device failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send notification to a specific device token.
     *
     * ANDROID delivery is data-only (no top-level `notification` block) so
     * the FCM SDK never auto-displays a system-tray notification.  The
     * Flutter app's onMessage (foreground) and onBackgroundMessage
     * (background/killed) handlers both call flutter_local_notifications to
     * show exactly one notification.  Before this change, MIUI / OnePlus /
     * Realme were showing TWO — one from FCM auto-display, one from the
     * app's onMessage which sometimes fires even in background on those
     * OEMs.
     *
     * iOS delivery still uses an APNs `alert` payload, so iOS auto-displays
     * natively as before — no iOS duplicate ever existed.
     */
    public function sendToDevice(string $fcmToken, string $title, string $body, ?string $imageUrl = null, array $data = []): bool
    {
        try {
            $payload = array_merge(
                array_map('strval', $data),
                [
                    'title' => $title,
                    'body'  => $body,
                    'image' => (string) ($imageUrl ?? ''),
                ]
            );

            $apns = [
                'headers' => [
                    'apns-priority'  => '10',
                    'apns-push-type' => 'alert',
                ],
                'payload' => [
                    'aps' => [
                        'alert' => [
                            'title' => $title,
                            'body'  => $body,
                        ],
                        'sound'           => 'default',
                        'mutable-content' => 1,
                    ],
                ],
            ];
            if ($imageUrl) {
                $apns['fcm_options'] = ['image' => $imageUrl];
            }

            $message = CloudMessage::fromArray([
                'token'   => $fcmToken,
                'data'    => $payload,
                'android' => [
                    'priority' => 'high',
                ],
                'apns' => $apns,
            ]);

            $this->messaging->send($message);

            Log::info('FCM sent to device', ['title' => $title]);
            return true;
        } catch (\Exception $e) {
            Log::error('FCM send to device failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
