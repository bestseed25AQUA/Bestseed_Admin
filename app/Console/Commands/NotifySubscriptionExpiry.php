<?php

namespace App\Console\Commands;

use App\Models\Farmer;
use App\Models\FarmSubscription;
use App\Models\PushNotification;
use App\Models\SubscriptionReminder;
use App\Services\FirebaseNotificationService;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Warn farmers before their Farm Management subscription lapses.
 *
 * Runs once a day. For each live subscription it works out how many days are
 * left, and if that number is one of the plan's thresholds — 7, 3, 2 and 1 for
 * a monthly plan; 30 down to 1 for a longer one — it sends a notification.
 *
 * Every send is written to `subscription_reminders` first, under a unique key.
 * That row, not a timestamp comparison, is what stops a second run of the day
 * sending the same warning twice.
 *
 * Idempotent by design: safe to run by hand, safe to run twice, and safe after
 * the scheduler has been down for a week — a missed threshold is picked up at
 * the next one still ahead rather than firing a backlog all at once.
 */
class NotifySubscriptionExpiry extends Command
{
    protected $signature = 'subscriptions:notify-expiry
                            {--dry-run : Show what would be sent without sending it}';

    protected $description = 'Notify farmers whose Farm Management subscription is close to expiring';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sent = 0;
        $skipped = 0;

        // Only subscriptions that are live today. An expired one has nothing
        // left to warn about, and a cancelled one was deliberately stopped.
        $subscriptions = FarmSubscription::with('farmer')->active()->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No active subscriptions.');
            return self::SUCCESS;
        }

        foreach ($subscriptions as $subscription) {
            $daysLeft = $subscription->days_remaining;

            // Not a threshold day for this plan: nothing to do.
            if (!in_array($daysLeft, $subscription->reminderDays(), true)) {
                continue;
            }

            if ($dryRun) {
                $this->line(sprintf(
                    '[dry-run] #%d %s — %d day(s) left',
                    $subscription->id,
                    $this->farmerName($subscription->farmer),
                    $daysLeft
                ));
                $sent++;
                continue;
            }

            // Claim the reminder BEFORE sending.
            //
            // The unique index is the lock: two overlapping runs both reach
            // here, one insert wins and the other throws, so exactly one push
            // goes out. Claiming after sending would let both send first.
            try {
                SubscriptionReminder::create([
                    'subscription_id' => $subscription->id,
                    'days_before'     => $daysLeft,
                    'sent_at'         => now(),
                ]);
            } catch (QueryException $e) {
                // Already claimed — by an earlier run today, or by the other
                // half of a race. Either way this warning is handled.
                $skipped++;
                continue;
            }

            $this->notify($subscription, $daysLeft);
            $sent++;
        }

        $this->info(($dryRun ? '[dry-run] ' : '') . "Reminders sent: {$sent}. Already sent: {$skipped}.");

        return self::SUCCESS;
    }

    /**
     * Tell one farmer, in the notification centre and on their device.
     *
     * The stored row comes first and is the one that matters: it is what the
     * app's notification list reads, and it survives a farmer who had no FCM
     * token registered or whose device was off. The push is best effort on top.
     */
    private function notify(FarmSubscription $subscription, int $daysLeft): void
    {
        $title = $daysLeft === 1
            ? 'Subscription expires tomorrow'
            : "Subscription expires in {$daysLeft} days";

        $body = sprintf(
            'Your %s Farm Management plan ends on %s. Renew it to keep adding farms. Your existing farms stay as they are.',
            $subscription->plan_label,
            $subscription->expires_at->format('d M Y')
        );

        $data = [
            'subscription_id' => $subscription->id,
            'plan_key'        => $subscription->plan_key,
            'plan_label'      => $subscription->plan_label,
            'expires_at'      => $subscription->expires_at->toDateString(),
            'days_remaining'  => $daysLeft,
        ];

        // Recording the alert must never be lost because the push failed, and
        // the push must never be skipped because recording failed — hence two
        // separate guards rather than one try around both.
        try {
            PushNotification::create([
                'title'          => $title,
                'body'           => $body,
                'module'         => 'farm_management',
                'recipient_type' => 'farmer',
                'type'           => 'subscription_expiry',
                'farmer_id'      => $subscription->farmer_id,
                'data'           => $data,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Subscription reminder could not be stored', [
                'subscription_id' => $subscription->id,
                'error'           => $e->getMessage(),
            ]);
        }

        $token = $subscription->farmer?->fcm_token;

        if (!$token) {
            return;
        }

        try {
            app(FirebaseNotificationService::class)->sendToDevice(
                $token,
                $title,
                $body,
                null,
                // FCM requires every data value to be a string; an int here is
                // rejected by the API and the whole push is dropped.
                array_map('strval', $data) + ['type' => 'subscription_expiry']
            );
        } catch (\Throwable $e) {
            Log::warning('Subscription reminder push failed', [
                'subscription_id' => $subscription->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }

    private function farmerName(?Farmer $farmer): string
    {
        if (!$farmer) {
            return 'Unknown farmer';
        }

        return trim($farmer->first_name . ' ' . $farmer->last_name) ?: ($farmer->mobile ?? 'Unnamed');
    }
}
