<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Farmer;
use App\Models\FarmSubscription;
use App\Models\SubscriptionPlan;
use App\Services\FarmAccessService;
use App\Services\SubscriptionService;
use Tests\Concerns\UsesDevelopmentDatabase;
use Tests\TestCase;

/**
 * How many farms a package actually buys.
 *
 * A package grants the right to create N farms DURING ITS TERM, spent once.
 * Buying another package always buys new farms rather than re-buying the ones
 * already standing. Holding any live package — even one that grants no farms
 * at all — keeps every existing farm usable.
 */
class FarmCreationSlotsTest extends TestCase
{
    use UsesDevelopmentDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->beginDevelopmentDatabase();
    }

    protected function tearDown(): void
    {
        $this->rollBackDevelopmentDatabase();
        parent::tearDown();
    }

    private function makeFarmer(): Farmer
    {
        return Farmer::create([
            'first_name' => 'Slots',
            'last_name'  => 'Test',
            'mobile'     => (string) random_int(7000000000, 9999999999),
            'role'       => 'farmer',
        ]);
    }

    private function makeFarm(Farmer $owner, string $name = 'Slots Test Farm'): Farm
    {
        return Farm::create([
            'farm_name' => $name,
            'farmer_id' => $owner->id,
            'status'    => 1,
        ]);
    }

    private function package(
        Farmer $owner,
        int $farmLimit,
        string $startsAt,
        string $expiresAt
    ): FarmSubscription {
        $plan = SubscriptionPlan::active()->ordered()->first();
        $this->assertNotNull($plan, 'A subscription plan is needed for this test.');

        return FarmSubscription::create([
            'farmer_id'  => $owner->id,
            'plan_id'    => $plan->id,
            'plan_key'   => $plan->key,
            'plan_label' => $plan->label,
            'farm_limit' => $farmLimit,
            'amount'     => $plan->amount,
            'months'     => $plan->months,
            'starts_at'  => $startsAt,
            'expires_at' => $expiresAt,
        ]);
    }

    private function service(): SubscriptionService
    {
        return app(SubscriptionService::class);
    }

    public function test_the_first_two_farms_need_no_package(): void
    {
        $owner = $this->makeFarmer();

        $this->assertTrue($this->service()->canCreateFarm($owner->id));

        $this->makeFarm($owner);
        $this->assertTrue(app(SubscriptionService::class)->canCreateFarm($owner->id));

        $this->makeFarm($owner);
        $this->assertFalse(app(SubscriptionService::class)->canCreateFarm($owner->id));
    }

    public function test_a_one_farm_package_buys_exactly_one_farm(): void
    {
        $owner = $this->makeFarmer();
        $this->makeFarm($owner);
        $this->makeFarm($owner);

        $package = $this->package(
            $owner,
            1,
            now()->subDay()->toDateString(),
            now()->addMonth()->toDateString()
        );

        $this->assertTrue(app(SubscriptionService::class)->canCreateFarm($owner->id));

        $third = $this->makeFarm($owner, 'Paid Farm');

        // Charged to the package, so the grant is visibly spent.
        $this->assertSame($package->id, $third->subscription_id);
        $this->assertFalse(app(SubscriptionService::class)->canCreateFarm($owner->id));
    }

    public function test_a_three_farm_package_buys_three(): void
    {
        $owner = $this->makeFarmer();
        $this->makeFarm($owner);
        $this->makeFarm($owner);

        $this->package(
            $owner,
            3,
            now()->subDay()->toDateString(),
            now()->addMonth()->toDateString()
        );

        for ($i = 1; $i <= 3; $i++) {
            $this->assertTrue(
                app(SubscriptionService::class)->canCreateFarm($owner->id),
                "Farm {$i} of the package should be allowed."
            );
            $this->makeFarm($owner, "Paid Farm {$i}");
        }

        $this->assertFalse(app(SubscriptionService::class)->canCreateFarm($owner->id));
        $this->assertSame(5, app(SubscriptionService::class)->ownedFarmCount($owner->id));
    }

    /** The case that started this: two one-farm packages, one after the other. */
    public function test_a_second_package_buys_a_further_farm_rather_than_re_buying_the_first(): void
    {
        $owner = $this->makeFarmer();
        $this->makeFarm($owner);
        $this->makeFarm($owner);

        $first = $this->package(
            $owner,
            1,
            now()->subMonths(2)->toDateString(),
            now()->subMonth()->toDateString()
        );

        $paid = $this->makeFarm($owner, 'Paid Farm');
        $paid->subscription_id = $first->id;
        $paid->save();

        // Lapsed: three farms, nothing live, so the paid one is read-only.
        $this->assertFalse(app(SubscriptionService::class)->canCreateFarm($owner->id));
        $this->assertTrue(app(SubscriptionService::class)->isFarmLocked($paid->fresh()));

        $this->package(
            $owner,
            1,
            now()->toDateString(),
            now()->addMonth()->toDateString()
        );

        $service = app(SubscriptionService::class);

        // The new package does not pay again for the farm already standing.
        $this->assertFalse($service->isFarmLocked($paid->fresh()));
        $this->assertTrue($service->canCreateFarm($owner->id), 'The new package must buy a NEW farm.');
        $this->assertSame(1, $service->remainingFarms($owner->id));
    }

    public function test_a_zero_farm_package_unlocks_what_they_have_without_adding_more(): void
    {
        $owner = $this->makeFarmer();
        $this->makeFarm($owner);
        $this->makeFarm($owner);
        $paid = $this->makeFarm($owner, 'Paid Farm');

        $this->assertTrue(app(SubscriptionService::class)->isFarmLocked($paid->fresh()));

        $this->package(
            $owner,
            0,
            now()->toDateString(),
            now()->addMonth()->toDateString()
        );

        $service = app(SubscriptionService::class);

        $this->assertFalse(
            $service->isFarmLocked($paid->fresh()),
            'An access-only package must make existing farms usable again.'
        );
        $this->assertFalse(
            $service->canCreateFarm($owner->id),
            'An access-only package grants no new farms.'
        );
    }

    public function test_a_zero_farm_package_restores_every_write_on_an_existing_farm(): void
    {
        $owner = $this->makeFarmer();
        $this->makeFarm($owner);
        $this->makeFarm($owner);
        $paid = $this->makeFarm($owner, 'Paid Farm');

        $this->package(
            $owner,
            0,
            now()->toDateString(),
            now()->addMonth()->toDateString()
        );

        $permission = app(FarmAccessService::class)->permissionFor($owner->id, $paid->fresh());

        $this->assertFalse($permission->locked);
        $this->assertTrue($permission->edit);
        $this->assertTrue($permission->create);
        $this->assertTrue($permission->delete);
        $this->assertTrue($permission->totalFeed);
        $this->assertTrue($permission->canShareAccess());
    }

    public function test_two_packages_running_together_stack_their_slots(): void
    {
        $owner = $this->makeFarmer();
        $this->makeFarm($owner);
        $this->makeFarm($owner);

        $this->package($owner, 1, now()->toDateString(), now()->addMonth()->toDateString());
        $this->package($owner, 2, now()->toDateString(), now()->addMonths(3)->toDateString());

        $this->assertSame(3, app(SubscriptionService::class)->remainingFarms($owner->id));
    }

    public function test_a_new_farm_is_charged_to_the_package_expiring_soonest(): void
    {
        $owner = $this->makeFarmer();
        $this->makeFarm($owner);
        $this->makeFarm($owner);

        $soonest = $this->package($owner, 1, now()->toDateString(), now()->addMonth()->toDateString());
        $this->package($owner, 1, now()->toDateString(), now()->addMonths(6)->toDateString());

        $farm = $this->makeFarm($owner, 'Paid Farm');

        $this->assertSame(
            $soonest->id,
            $farm->subscription_id,
            'The grant most likely to be wasted should be spent first.'
        );
    }

    public function test_deleting_a_paid_farm_gives_the_slot_back(): void
    {
        $owner = $this->makeFarmer();
        $this->makeFarm($owner);
        $this->makeFarm($owner);

        $this->package($owner, 1, now()->toDateString(), now()->addMonth()->toDateString());

        $paid = $this->makeFarm($owner, 'Paid Farm');
        $this->assertFalse(app(SubscriptionService::class)->canCreateFarm($owner->id));

        $paid->delete();

        $this->assertTrue(
            app(SubscriptionService::class)->canCreateFarm($owner->id),
            'A deleted farm must release the slot it was charged to.'
        );
    }

    /**
     * The exact shape that was still being refused: two free farms, a third
     * created under a package that has since lapsed, and a fresh package of
     * the same size bought today.
     *
     * Asserted through [statusFor] because that — not [canCreateFarm] — is
     * what the app asks before it opens the form, and the two had drifted:
     * statusFor recomputed the rule inline, so the app was shown the packages
     * sheet for a farm the server would have let them create.
     */
    public function test_the_app_is_told_it_may_create_after_renewing_a_used_up_package(): void
    {
        $owner = $this->makeFarmer();
        $this->makeFarm($owner);
        $this->makeFarm($owner);

        $lapsed = $this->package(
            $owner,
            1,
            now()->subMonths(2)->toDateString(),
            now()->subDay()->toDateString()
        );

        $paid = $this->makeFarm($owner, 'Paid Farm');
        $paid->subscription_id = $lapsed->id;
        $paid->save();

        $before = app(SubscriptionService::class)->statusFor($owner->id);
        $this->assertFalse($before['can_create_farm']);
        $this->assertTrue($before['needs_subscription']);

        $this->package($owner, 1, now()->toDateString(), now()->addMonth()->toDateString());

        $after = app(SubscriptionService::class)->statusFor($owner->id);

        $this->assertTrue($after['can_create_farm'], 'The app must open the form, not the packages sheet.');
        $this->assertFalse($after['needs_subscription']);
        $this->assertNull($after['message']);
        $this->assertSame(1, $after['farms_remaining']);
        $this->assertSame(4, $after['farm_allowance']);
    }

    /** statusFor and canCreateFarm must never disagree. */
    public function test_the_status_payload_agrees_with_the_create_check(): void
    {
        $owner = $this->makeFarmer();
        $service = app(SubscriptionService::class);

        $assertAgrees = function (string $stage) use ($owner) {
            $service = app(SubscriptionService::class);

            $this->assertSame(
                $service->canCreateFarm($owner->id),
                $service->statusFor($owner->id)['can_create_farm'],
                "The two answers differ at: {$stage}"
            );
        };

        $assertAgrees('no farms');

        $this->makeFarm($owner);
        $assertAgrees('one free farm');

        $this->makeFarm($owner);
        $assertAgrees('free allowance used');

        $this->package($owner, 1, now()->toDateString(), now()->addMonth()->toDateString());
        $assertAgrees('package bought');

        $this->makeFarm($owner, 'Paid Farm');
        $assertAgrees('package spent');

        $this->package($owner, 2, now()->toDateString(), now()->addMonths(2)->toDateString());
        $assertAgrees('second package bought');

        unset($service);
    }

    public function test_the_status_payload_reports_the_new_figures(): void
    {
        $owner = $this->makeFarmer();
        $this->makeFarm($owner);
        $this->makeFarm($owner);
        $this->makeFarm($owner, 'Paid Farm');

        $expired = $this->service()->statusFor($owner->id);

        $this->assertFalse($expired['has_live_package']);
        $this->assertSame(0, $expired['package_slots_remaining']);
        $this->assertFalse($expired['can_create_farm']);
        $this->assertCount(1, $expired['locked_farm_ids']);

        $this->package($owner, 2, now()->toDateString(), now()->addMonth()->toDateString());

        $live = app(SubscriptionService::class)->statusFor($owner->id);

        $this->assertTrue($live['has_live_package']);
        $this->assertSame(2, $live['package_slots_remaining']);
        $this->assertTrue($live['can_create_farm']);
        $this->assertSame([], $live['locked_farm_ids']);
    }
}
