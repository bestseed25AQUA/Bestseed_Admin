<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\FarmSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;
use Tests\Concerns\UsesDevelopmentDatabase;
use Tests\TestCase;

/**
 * Editing a subscription rewrites its snapshot from the package catalogue.
 *
 * It used to read `config('subscriptions.plans.*')`, which no longer holds the
 * packages — they live in `subscription_plans`. A package created in the panel
 * had no config entry, so saving an edit wrote the raw key as the label, zeroed
 * the amount, reset the term to one month and never touched the farm limit.
 */
class SubscriptionEditTest extends TestCase
{
    use UsesDevelopmentDatabase;

    private Farmer $farmer;
    private FarmSubscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        $this->beginDevelopmentDatabase();

        $this->farmer = Farmer::create([
            'first_name' => 'Edit',
            'last_name'  => 'Test',
            'mobile'     => (string) random_int(7000000000, 9999999999),
            'role'       => 'farmer',
        ]);

        $monthly = $this->plan('month_1');

        $this->subscription = FarmSubscription::create([
            'farmer_id'  => $this->farmer->id,
            'plan_id'    => $monthly->id,
            'plan_key'   => $monthly->key,
            'plan_label' => $monthly->label,
            'farm_limit' => (int) $monthly->farm_limit,
            'amount'     => $monthly->amount,
            'months'     => (int) $monthly->months,
            'starts_at'  => Carbon::today()->toDateString(),
            'expires_at' => Carbon::today()->addMonth()->subDay()->toDateString(),
        ]);
    }

    protected function tearDown(): void
    {
        $this->rollBackDevelopmentDatabase();
        parent::tearDown();
    }

    private function plan(string $key): SubscriptionPlan
    {
        $plan = SubscriptionPlan::where('key', $key)->first();

        if (!$plan) {
            $this->markTestSkipped("Package [{$key}] is not in the catalogue.");
        }

        return $plan;
    }

    private function admin(): User
    {
        $admin = User::first();

        if (!$admin) {
            $this->markTestSkipped('No admin user to act as.');
        }

        return $admin;
    }

    public function test_changing_the_package_rewrites_every_snapshot_field(): void
    {
        $target = $this->plan('month_6');

        $starts  = Carbon::today()->toDateString();
        $expires = Carbon::today()->addMonths(6)->subDay()->toDateString();

        $this->actingAs($this->admin())
            ->put("/admin/subscriptions/{$this->subscription->id}", [
                'plan_key'   => $target->key,
                'starts_at'  => $starts,
                'expires_at' => $expires,
            ])
            ->assertRedirect();

        $this->subscription->refresh();

        $this->assertSame($target->id, (int) $this->subscription->plan_id);
        $this->assertSame($target->key, $this->subscription->plan_key);
        $this->assertSame($target->label, $this->subscription->plan_label);

        $this->assertSame(
            (int) $target->farm_limit,
            (int) $this->subscription->farm_limit,
            'The farm limit must follow the package — this was never updated at all.'
        );

        $this->assertSame((int) $target->months, (int) $this->subscription->months);
        $this->assertSame(
            round((float) $target->amount, 2),
            round((float) $this->subscription->amount, 2)
        );
        $this->assertSame($expires, $this->subscription->expires_at->toDateString());
    }

    /** A package created in the admin panel has no config entry at all. */
    public function test_a_package_created_in_the_panel_saves_correctly(): void
    {
        $target = SubscriptionPlan::create([
            'key'        => 'edit_test_' . random_int(1000, 9999),
            'label'      => '2 Months',
            'months'     => 2,
            'farm_limit' => 4,
            'amount'     => 349,
            'reminders'  => [15],
            'is_active'  => true,
            'sort_order' => 99,
        ]);

        $this->actingAs($this->admin())
            ->put("/admin/subscriptions/{$this->subscription->id}", [
                'plan_key'   => $target->key,
                'starts_at'  => Carbon::today()->toDateString(),
                'expires_at' => Carbon::today()->addMonths(2)->subDay()->toDateString(),
            ])
            ->assertRedirect();

        $this->subscription->refresh();

        $this->assertSame('2 Months', $this->subscription->plan_label, 'Not the raw key.');
        $this->assertSame(4, (int) $this->subscription->farm_limit);
        $this->assertSame(2, (int) $this->subscription->months);
        $this->assertSame(349.00, round((float) $this->subscription->amount, 2), 'Not zero.');
    }

    public function test_editing_clears_reminders_so_the_farmer_is_warned_again(): void
    {
        $this->subscription->reminders()->create([
            'days_before' => 7,
            'sent_at'     => now(),
        ]);

        $this->actingAs($this->admin())
            ->put("/admin/subscriptions/{$this->subscription->id}", [
                'plan_key'   => $this->subscription->plan_key,
                'starts_at'  => $this->subscription->starts_at->toDateString(),
                'expires_at' => Carbon::today()->addMonths(3)->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame(0, $this->subscription->reminders()->count());
    }

    public function test_an_unknown_package_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->put("/admin/subscriptions/{$this->subscription->id}", [
                'plan_key'   => 'no_such_package',
                'starts_at'  => Carbon::today()->toDateString(),
                'expires_at' => Carbon::today()->addMonth()->toDateString(),
            ])
            ->assertSessionHasErrors('plan_key');
    }
}
