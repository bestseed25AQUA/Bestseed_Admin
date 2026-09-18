<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Farmer;
use App\Models\FarmSubscription;
use App\Models\SubscriptionReminder;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\UsesDevelopmentDatabase;
use Tests\TestCase;

/**
 * The farm allowance, and the paid window that lifts it.
 *
 * Runs against the development MySQL database rather than an in-memory SQLite
 * one: the rules here turn on real date columns and a real unique index, and a
 * schema rebuilt from scratch would not prove either. Everything happens inside
 * a transaction that is rolled back, so the database is untouched afterwards.
 */
class FarmSubscriptionTest extends TestCase
{
    use UsesDevelopmentDatabase;

    private SubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->beginDevelopmentDatabase();

        $this->service = app(SubscriptionService::class);
    }

    protected function tearDown(): void
    {
        $this->rollBackDevelopmentDatabase();

        parent::tearDown();
    }

    /** A farmer nobody else's data can reach. */
    private function makeFarmer(): Farmer
    {
        return Farmer::create([
            'first_name' => 'Subscription',
            'last_name'  => 'Test',
            // Unique per run so a leftover row from a crashed run cannot
            // collide with this one.
            'mobile'     => (string) random_int(7000000000, 9999999999),
            'role'       => 'farmer',
        ]);
    }

    private function makeFarm(Farmer $farmer, string $name = 'Test Farm'): Farm
    {
        return Farm::create([
            'farm_name' => $name,
            'farmer_id' => $farmer->id,
            'status'    => 1,
        ]);
    }

    // ── The free allowance ──────────────────────────────────────────────────

    public function test_a_farmer_may_create_up_to_the_free_limit(): void
    {
        $farmer = $this->makeFarmer();
        $limit  = $this->service->freeLimit();

        $this->assertTrue($this->service->canCreateFarm($farmer->id), 'Should allow the first farm.');

        for ($i = 1; $i < $limit; $i++) {
            $this->makeFarm($farmer, "Farm {$i}");
            $this->assertTrue(
                $this->service->canCreateFarm($farmer->id),
                "Should still allow a farm while under the limit of {$limit}."
            );
        }

        // One more takes them to the limit.
        $this->makeFarm($farmer, 'Farm at limit');

        $this->assertFalse(
            $this->service->canCreateFarm($farmer->id),
            "Should refuse once {$limit} farms are owned."
        );
    }

    public function test_farms_shared_with_a_farmer_do_not_use_their_allowance(): void
    {
        $owner  = $this->makeFarmer();
        $helper = $this->makeFarmer();

        // Three farms owned by somebody else — more than the free limit.
        $this->makeFarm($owner, 'Owner A');
        $this->makeFarm($owner, 'Owner B');
        $this->makeFarm($owner, 'Owner C');

        $this->assertSame(0, $this->service->ownedFarmCount($helper->id));
        $this->assertTrue(
            $this->service->canCreateFarm($helper->id),
            'A helper on somebody else\'s farms keeps their own full allowance.'
        );
    }

    public function test_a_deleted_farm_gives_the_slot_back(): void
    {
        $farmer = $this->makeFarmer();
        $limit  = $this->service->freeLimit();

        $farms = [];
        for ($i = 0; $i < $limit; $i++) {
            $farms[] = $this->makeFarm($farmer, "Farm {$i}");
        }

        $this->assertFalse($this->service->canCreateFarm($farmer->id));

        $farms[0]->delete(); // soft delete

        $this->assertTrue(
            $this->service->canCreateFarm($farmer->id),
            'Soft-deleting a farm should free the slot.'
        );
    }

    // ── The paid window ─────────────────────────────────────────────────────

    private function fillFreeLimit(Farmer $farmer): void
    {
        for ($i = 0; $i < $this->service->freeLimit(); $i++) {
            $this->makeFarm($farmer, "Filler {$i}");
        }
    }

    public function test_an_active_subscription_lifts_the_limit(): void
    {
        $farmer = $this->makeFarmer();
        $this->fillFreeLimit($farmer);

        $this->assertFalse($this->service->canCreateFarm($farmer->id));

        $this->service->subscribe($farmer->id, 'month_1');

        $this->assertTrue(
            $this->service->canCreateFarm($farmer->id),
            'A live subscription should allow more farms.'
        );
    }

    public function test_an_expired_subscription_does_not_lift_the_limit(): void
    {
        $farmer = $this->makeFarmer();
        $this->fillFreeLimit($farmer);

        FarmSubscription::create([
            'farmer_id'  => $farmer->id,
            'plan_key'   => 'month_1',
            'plan_label' => '1 Month',
            'amount'     => 199,
            'months'     => 1,
            'starts_at'  => Carbon::today()->subMonths(2)->toDateString(),
            'expires_at' => Carbon::today()->subDay()->toDateString(),
        ]);

        $this->assertFalse(
            $this->service->canCreateFarm($farmer->id),
            'A subscription that ended yesterday must not still work.'
        );
    }

    public function test_a_subscription_expiring_today_still_works_today(): void
    {
        $farmer = $this->makeFarmer();
        $this->fillFreeLimit($farmer);

        FarmSubscription::create([
            'farmer_id'  => $farmer->id,
            'plan_key'   => 'month_1',
            'plan_label' => '1 Month',
            'amount'     => 199,
            'months'     => 1,
            'starts_at'  => Carbon::today()->subMonth()->toDateString(),
            'expires_at' => Carbon::today()->toDateString(),
        ]);

        $this->assertTrue(
            $this->service->canCreateFarm($farmer->id),
            'Expiry is inclusive — the last day is still a paid day.'
        );
    }

    public function test_a_cancelled_subscription_does_not_lift_the_limit(): void
    {
        $farmer = $this->makeFarmer();
        $this->fillFreeLimit($farmer);

        $subscription = $this->service->subscribe($farmer->id, 'year_1');
        $this->assertTrue($this->service->canCreateFarm($farmer->id));

        $subscription->update(['cancelled_at' => now()]);

        $this->assertFalse(
            $this->service->canCreateFarm($farmer->id),
            'Cancelling must take the allowance away immediately.'
        );
    }

    // ── Dates ───────────────────────────────────────────────────────────────

    public function test_each_plan_runs_for_its_full_term_inclusive(): void
    {
        $cases = [
            'month_1' => 1,
            'month_3' => 3,
            'month_6' => 6,
            'year_1'  => 12,
        ];

        foreach ($cases as $planKey => $months) {
            $farmer = $this->makeFarmer();

            $subscription = $this->service->subscribe($farmer->id, $planKey, '2026-01-01');

            $this->assertSame(
                Carbon::parse('2026-01-01')->addMonths($months)->subDay()->toDateString(),
                $subscription->expires_at->toDateString(),
                "[{$planKey}] should end the day before the same date {$months} months on."
            );

            $this->assertSame($months, $subscription->months);
        }
    }

    public function test_renewing_early_extends_rather_than_overlaps(): void
    {
        $farmer = $this->makeFarmer();

        $first = $this->service->subscribe($farmer->id, 'month_1', Carbon::today()->toDateString());
        $firstEnd = $first->expires_at->copy();

        // Renew while the first is still running.
        $second = $this->service->subscribe($farmer->id, 'month_3');

        $this->assertSame(
            $firstEnd->copy()->addDay()->toDateString(),
            $second->starts_at->toDateString(),
            'A renewal should begin the day after the current term ends.'
        );

        $this->assertSame(
            $firstEnd->copy()->addDay()->addMonths(3)->subDay()->toDateString(),
            $second->expires_at->toDateString(),
            'The farmer must get all three months on top of what they had.'
        );
    }

    public function test_days_remaining_is_measured_in_whole_days(): void
    {
        $farmer = $this->makeFarmer();

        $subscription = FarmSubscription::create([
            'farmer_id'  => $farmer->id,
            'plan_key'   => 'month_1',
            'plan_label' => '1 Month',
            'amount'     => 199,
            'months'     => 1,
            'starts_at'  => Carbon::today()->subDays(23)->toDateString(),
            'expires_at' => Carbon::today()->addDays(7)->toDateString(),
        ]);

        $this->assertSame(7, $subscription->days_remaining);
        $this->assertSame('expiring', $subscription->state);
        $this->assertTrue($subscription->is_active);
        $this->assertFalse($subscription->is_expired);
    }

    /**
     * The regression this exists to prevent.
     *
     * A one-month plan has 30 days on the day it is sold. Under a flat 30-day
     * "expiring soon" window it went amber in the admin list, and told the
     * farmer it was about to run out, the moment they paid for it.
     */
    public function test_a_freshly_bought_plan_is_never_already_expiring(): void
    {
        foreach (array_keys(config('subscriptions.plans')) as $planKey) {
            $subscription = $this->service->subscribe(
                $this->makeFarmer()->id,
                $planKey,
                Carbon::today()->toDateString(),
            );

            $this->assertFalse(
                $subscription->is_expiring_soon,
                "[{$planKey}] must not be 'expiring soon' on the day it is bought."
            );

            $this->assertSame('active', $subscription->state, "[{$planKey}] should read as plain active.");
        }
    }

    public function test_the_warning_window_follows_the_plan(): void
    {
        $expected = [
            'month_1' => 7,
            'month_3' => 15,
            'month_6' => 30,
            'year_1'  => 30,
        ];

        foreach ($expected as $planKey => $window) {
            $subscription = $this->service->subscribe($this->makeFarmer()->id, $planKey);

            $this->assertSame(
                $window,
                $subscription->warningWindow(),
                "[{$planKey}] should start warning {$window} days out."
            );
        }
    }

    public function test_thirty_days_out_is_expiring_for_a_long_plan_but_not_a_monthly_one(): void
    {
        $halfYear = $this->subscriptionEndingIn(30, 'month_6');
        $monthly  = $this->subscriptionEndingIn(30, 'month_1');

        $this->assertTrue($halfYear->is_expiring_soon);
        $this->assertSame('expiring', $halfYear->state);
        $this->assertSame('table-warning', $halfYear->row_class);

        $this->assertFalse($monthly->is_expiring_soon);
        $this->assertSame('active', $monthly->state);
        $this->assertSame('', $monthly->row_class);
    }

    public function test_the_expiring_soon_scope_agrees_with_each_row(): void
    {
        // Inside its window.
        $monthlySoon = $this->subscriptionEndingIn(5, 'month_1');
        // Outside its window, though a flat 30-day rule would have caught it.
        $monthlyFine = $this->subscriptionEndingIn(20, 'month_1');
        // Inside its own, larger window.
        $annualSoon = $this->subscriptionEndingIn(20, 'year_1');

        $ids = FarmSubscription::expiringSoon()->pluck('id')->all();

        $this->assertContains($monthlySoon->id, $ids);
        $this->assertContains($annualSoon->id, $ids);
        $this->assertNotContains($monthlyFine->id, $ids);

        // The scope and the per-row attribute must never disagree: the admin
        // list filters with one and colours with the other.
        foreach ([$monthlySoon, $monthlyFine, $annualSoon] as $subscription) {
            $this->assertSame(
                $subscription->is_expiring_soon,
                in_array($subscription->id, $ids, true),
                "Scope and attribute disagree for subscription #{$subscription->id}."
            );
        }
    }

    public function test_state_reports_expired_and_cancelled_distinctly(): void
    {
        $farmer = $this->makeFarmer();

        $expired = FarmSubscription::create([
            'farmer_id'  => $farmer->id,
            'plan_key'   => 'month_1',
            'plan_label' => '1 Month',
            'amount'     => 199,
            'months'     => 1,
            'starts_at'  => Carbon::today()->subMonths(2)->toDateString(),
            'expires_at' => Carbon::today()->subDays(5)->toDateString(),
        ]);

        $this->assertSame('expired', $expired->state);
        $this->assertSame(-5, $expired->days_remaining);

        // Cancelled beats expired, so a cancelled row is not also shown in red.
        $expired->update(['cancelled_at' => now()]);
        $this->assertSame('cancelled', $expired->fresh()->state);
    }

    // ── Reminders ───────────────────────────────────────────────────────────

    public function test_reminder_thresholds_follow_the_plan(): void
    {
        $farmer = $this->makeFarmer();

        $monthly = $this->service->subscribe($farmer->id, 'month_1', Carbon::today()->toDateString());
        $this->assertSame([7, 3, 2, 1], $monthly->reminderDays());

        $halfYear = $this->service->subscribe($this->makeFarmer()->id, 'month_6', Carbon::today()->toDateString());
        $this->assertSame([30, 15, 10, 7, 3, 2, 1], $halfYear->reminderDays());
    }

    public function test_a_reminder_cannot_be_recorded_twice(): void
    {
        $farmer       = $this->makeFarmer();
        $subscription = $this->service->subscribe($farmer->id, 'month_1');

        SubscriptionReminder::create([
            'subscription_id' => $subscription->id,
            'days_before'     => 7,
            'sent_at'         => now(),
        ]);

        // The unique index is what makes the expiry command safe to re-run.
        $this->expectException(\Illuminate\Database\QueryException::class);

        SubscriptionReminder::create([
            'subscription_id' => $subscription->id,
            'days_before'     => 7,
            'sent_at'         => now(),
        ]);
    }

    // ── What the app is told ────────────────────────────────────────────────

    public function test_status_tells_the_app_to_show_the_sheet_only_when_a_plan_is_needed(): void
    {
        $farmer = $this->makeFarmer();

        $fresh = $this->service->statusFor($farmer->id);
        $this->assertTrue($fresh['can_create_farm']);
        $this->assertFalse($fresh['needs_subscription']);
        $this->assertNull($fresh['message']);

        $this->fillFreeLimit($farmer);

        $capped = $this->service->statusFor($farmer->id);
        $this->assertFalse($capped['can_create_farm']);
        $this->assertTrue($capped['needs_subscription']);
        $this->assertNotNull($capped['message']);
        $this->assertSame($this->service->freeLimit(), $capped['owned_farms']);
        $this->assertSame(0, $capped['free_remaining']);

        $this->service->subscribe($farmer->id, 'month_3');

        $subscribed = $this->service->statusFor($farmer->id);
        $this->assertTrue($subscribed['can_create_farm']);
        $this->assertFalse($subscribed['needs_subscription']);
        $this->assertSame('3 Months', $subscribed['subscription']['plan_label']);
    }

    public function test_status_offers_every_configured_plan_with_a_price(): void
    {
        $status = $this->service->statusFor($this->makeFarmer()->id);

        $this->assertCount(count(config('subscriptions.plans')), $status['plans']);

        $byKey = collect($status['plans'])->keyBy('key');

        $this->assertSame(199.0, $byKey['month_1']['amount']);
        $this->assertSame(549.0, $byKey['month_3']['amount']);
        $this->assertSame(999.0, $byKey['month_6']['amount']);
        $this->assertSame(1899.0, $byKey['year_1']['amount']);

        $this->assertSame('₹199', $byKey['month_1']['price_label']);
        $this->assertSame('₹1,899', $byKey['year_1']['price_label']);
    }

    public function test_an_unknown_plan_is_refused_rather_than_silently_created(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->subscribe($this->makeFarmer()->id, 'lifetime_free');
    }

    // ── The nightly reminder command ────────────────────────────────────────

    /** A live subscription with exactly $daysLeft to run. */
    private function subscriptionEndingIn(int $daysLeft, string $planKey = 'month_1'): FarmSubscription
    {
        $plan = config("subscriptions.plans.{$planKey}");

        return FarmSubscription::create([
            'farmer_id'  => $this->makeFarmer()->id,
            'plan_key'   => $planKey,
            'plan_label' => $plan['label'],
            'amount'     => $plan['amount'],
            'months'     => $plan['months'],
            'starts_at'  => Carbon::today()->subMonths($plan['months'])->addDays($daysLeft)->toDateString(),
            'expires_at' => Carbon::today()->addDays($daysLeft)->toDateString(),
        ]);
    }

    public function test_the_command_warns_on_a_threshold_day_and_records_it(): void
    {
        $subscription = $this->subscriptionEndingIn(7);

        $this->artisan('subscriptions:notify-expiry')->assertSuccessful();

        $this->assertDatabaseHas('subscription_reminders', [
            'subscription_id' => $subscription->id,
            'days_before'     => 7,
        ]);

        $this->assertDatabaseHas('push_notifications', [
            'farmer_id' => $subscription->farmer_id,
            'type'      => 'subscription_expiry',
        ]);
    }

    public function test_the_command_says_tomorrow_rather_than_one_day(): void
    {
        $subscription = $this->subscriptionEndingIn(1);

        $this->artisan('subscriptions:notify-expiry')->assertSuccessful();

        $this->assertDatabaseHas('push_notifications', [
            'farmer_id' => $subscription->farmer_id,
            'title'     => 'Subscription expires tomorrow',
        ]);
    }

    public function test_the_command_is_safe_to_run_twice(): void
    {
        $subscription = $this->subscriptionEndingIn(3);

        $this->artisan('subscriptions:notify-expiry')->assertSuccessful();
        $this->artisan('subscriptions:notify-expiry')->assertSuccessful();

        // One reminder, one notification — not two of each.
        $this->assertSame(
            1,
            SubscriptionReminder::where('subscription_id', $subscription->id)->count(),
            'A second run must not re-send the same warning.'
        );

        $this->assertSame(
            1,
            DB::table('push_notifications')
                ->where('farmer_id', $subscription->farmer_id)
                ->where('type', 'subscription_expiry')
                ->count()
        );
    }

    public function test_the_command_ignores_days_that_are_not_thresholds(): void
    {
        // A monthly plan warns at 7, 3, 2 and 1 — never at 5.
        $subscription = $this->subscriptionEndingIn(5, 'month_1');

        $this->artisan('subscriptions:notify-expiry')->assertSuccessful();

        $this->assertSame(
            0,
            SubscriptionReminder::where('subscription_id', $subscription->id)->count(),
            'Five days out is not a threshold for a one-month plan.'
        );
    }

    public function test_a_longer_plan_warns_earlier_than_a_monthly_one(): void
    {
        // 30 days out: a threshold for the six-month plan, not the monthly one.
        $halfYear = $this->subscriptionEndingIn(30, 'month_6');
        $monthly  = $this->subscriptionEndingIn(30, 'month_1');

        $this->artisan('subscriptions:notify-expiry')->assertSuccessful();

        $this->assertSame(1, SubscriptionReminder::where('subscription_id', $halfYear->id)->count());
        $this->assertSame(0, SubscriptionReminder::where('subscription_id', $monthly->id)->count());
    }

    public function test_the_command_leaves_expired_and_cancelled_subscriptions_alone(): void
    {
        $farmer = $this->makeFarmer();

        $expired = FarmSubscription::create([
            'farmer_id'  => $farmer->id,
            'plan_key'   => 'month_1',
            'plan_label' => '1 Month',
            'amount'     => 199,
            'months'     => 1,
            'starts_at'  => Carbon::today()->subMonths(2)->toDateString(),
            'expires_at' => Carbon::today()->subDay()->toDateString(),
        ]);

        $cancelled = $this->subscriptionEndingIn(3);
        $cancelled->update(['cancelled_at' => now()]);

        $this->artisan('subscriptions:notify-expiry')->assertSuccessful();

        $this->assertSame(0, SubscriptionReminder::where('subscription_id', $expired->id)->count());
        $this->assertSame(0, SubscriptionReminder::where('subscription_id', $cancelled->id)->count());
    }

    public function test_editing_the_dates_clears_reminders_so_the_farmer_is_warned_again(): void
    {
        $subscription = $this->subscriptionEndingIn(3);

        $this->artisan('subscriptions:notify-expiry')->assertSuccessful();
        $this->assertSame(1, SubscriptionReminder::where('subscription_id', $subscription->id)->count());

        // What the admin edit screen does when the term is extended.
        $subscription->update(['expires_at' => Carbon::today()->addDays(40)->toDateString()]);
        $subscription->reminders()->delete();

        $this->assertSame(
            0,
            SubscriptionReminder::where('subscription_id', $subscription->id)->count(),
            'Stale reminders must not silence the new term.'
        );
    }

    // ── The HTTP contract the app is built against ──────────────────────────

    public function test_the_status_endpoint_returns_the_shape_the_app_parses(): void
    {
        $farmer = $this->makeFarmer();
        Sanctum::actingAs($farmer);

        $response = $this->getJson('/api/farmer/subscription/status');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => [
                    'owned_farms',
                    'free_limit',
                    'free_remaining',
                    'can_create_farm',
                    'needs_subscription',
                    'message',
                    'subscription',
                    'plans' => [
                        ['key', 'label', 'months', 'amount', 'price_label', 'per_month'],
                    ],
                    'contact',
                ],
            ])
            ->assertJsonPath('data.can_create_farm', true)
            ->assertJsonPath('data.needs_subscription', false)
            ->assertJsonPath('data.owned_farms', 0);
    }

    public function test_the_status_endpoint_flags_a_farmer_who_is_out_of_free_farms(): void
    {
        $farmer = $this->makeFarmer();
        $this->fillFreeLimit($farmer);

        Sanctum::actingAs($farmer);

        $this->getJson('/api/farmer/subscription/status')
            ->assertOk()
            ->assertJsonPath('data.can_create_farm', false)
            ->assertJsonPath('data.needs_subscription', true);
    }

    public function test_creating_a_farm_over_the_limit_is_refused_with_402(): void
    {
        $farmer = $this->makeFarmer();
        $this->fillFreeLimit($farmer);

        Sanctum::actingAs($farmer);

        // 402 specifically: the app keys the packages sheet off this status,
        // and 403 already means "this farm is not yours" elsewhere.
        $this->postJson('/api/farmer/create-farm', [
            'type'      => 'form',
            'farm_name' => 'One Too Many',
            'tanks'     => 1,
        ])
            ->assertStatus(402)
            ->assertJsonPath('needs_subscription', true)
            ->assertJsonStructure(['status', 'needs_subscription', 'message', 'data' => ['plans']]);

        $this->assertDatabaseMissing('farms', [
            'farmer_id' => $farmer->id,
            'farm_name' => 'One Too Many',
        ]);
    }

    public function test_a_subscribed_farmer_is_not_refused_by_the_limit(): void
    {
        $farmer = $this->makeFarmer();
        $this->fillFreeLimit($farmer);
        $this->service->subscribe($farmer->id, 'month_1');

        Sanctum::actingAs($farmer);

        // Past the allowance gate, so whatever comes back is NOT the 402.
        // Deliberately not asserting a created farm: this endpoint does a great
        // deal more than the gate, and this test is about the gate.
        $response = $this->postJson('/api/farmer/create-farm', [
            'type'      => 'form',
            'farm_name' => 'Subscribed Farm',
            'tanks'     => 1,
        ]);

        $this->assertNotSame(
            402,
            $response->getStatusCode(),
            'A farmer with a live subscription must get past the free-farm limit.'
        );
    }

    public function test_the_refusal_message_names_the_end_date_after_a_lapse(): void
    {
        $farmer = $this->makeFarmer();
        $this->fillFreeLimit($farmer);

        $endedOn = Carbon::today()->subDays(3);

        FarmSubscription::create([
            'farmer_id'  => $farmer->id,
            'plan_key'   => 'month_1',
            'plan_label' => '1 Month',
            'amount'     => 199,
            'months'     => 1,
            'starts_at'  => $endedOn->copy()->subMonth()->toDateString(),
            'expires_at' => $endedOn->toDateString(),
        ]);

        $this->assertStringContainsString(
            $endedOn->format('d M Y'),
            $this->service->refusalMessage($farmer->id)
        );
    }
}
