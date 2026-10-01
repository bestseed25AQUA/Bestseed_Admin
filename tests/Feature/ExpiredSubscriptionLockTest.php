<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Farmer;
use App\Models\FarmSubscription;
use App\Models\SubscriptionPlan;
use App\Models\Tank;
use App\Services\FarmAccessService;
use App\Services\SubscriptionService;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\UsesDevelopmentDatabase;
use Tests\TestCase;

/**
 * What a farmer may still do on a farm their package no longer covers.
 *
 * Read it, harvest the crop already in it, and pull its reports. Nothing that
 * adds to it. The free allowance is never affected, whatever happens to a
 * subscription.
 */
class ExpiredSubscriptionLockTest extends TestCase
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
            'first_name' => 'Lock',
            'last_name'  => 'Test',
            'mobile'     => (string) random_int(7000000000, 9999999999),
            'role'       => 'farmer',
        ]);
    }

    /** @return array<int, Farm> */
    private function makeFarms(Farmer $owner, int $count): array
    {
        $farms = [];

        for ($i = 1; $i <= $count; $i++) {
            $farms[] = Farm::create([
                'farm_name' => "Lock Test Farm {$i}",
                'farmer_id' => $owner->id,
                'status'    => 1,
            ]);
        }

        return $farms;
    }

    private function subscriptions(): SubscriptionService
    {
        return app(SubscriptionService::class);
    }

    private function givePackage(Farmer $owner, string $startsAt, string $expiresAt): void
    {
        $plan = SubscriptionPlan::active()->ordered()->first();
        $this->assertNotNull($plan, 'A subscription plan is needed for this test.');

        FarmSubscription::create([
            'farmer_id'  => $owner->id,
            'plan_id'    => $plan->id,
            'plan_key'   => $plan->key,
            'plan_label' => $plan->label,
            'farm_limit' => 1,
            'amount'     => $plan->amount,
            'months'     => $plan->months,
            'starts_at'  => $startsAt,
            'expires_at' => $expiresAt,
        ]);
    }

    public function test_free_farms_stay_writable_and_the_rest_lock(): void
    {
        $owner = $this->makeFarmer();
        [$first, $second, $third] = $this->makeFarms($owner, 3);

        $service = $this->subscriptions();

        $this->assertFalse($service->isFarmLocked($first));
        $this->assertFalse($service->isFarmLocked($second));
        $this->assertTrue($service->isFarmLocked($third));
    }

    public function test_a_live_package_unlocks_the_farms_it_paid_for(): void
    {
        $owner = $this->makeFarmer();
        [, , $third] = $this->makeFarms($owner, 3);

        $this->givePackage(
            $owner,
            now()->subDay()->toDateString(),
            now()->addMonth()->toDateString()
        );

        $this->assertFalse(app(SubscriptionService::class)->isFarmLocked($third));
    }

    public function test_an_expired_package_relocks_the_farm_it_paid_for(): void
    {
        $owner = $this->makeFarmer();
        [, , $third] = $this->makeFarms($owner, 3);

        $this->givePackage(
            $owner,
            now()->subMonths(2)->toDateString(),
            now()->subDay()->toDateString()
        );

        $this->assertTrue(app(SubscriptionService::class)->isFarmLocked($third));
    }

    public function test_the_owners_permission_on_a_locked_farm_is_view_and_harvest_only(): void
    {
        $owner = $this->makeFarmer();
        [, , $third] = $this->makeFarms($owner, 3);

        $permission = app(FarmAccessService::class)->permissionFor($owner->id, $third);

        $this->assertTrue($permission->locked);
        $this->assertTrue($permission->view, 'Reports and history must stay readable.');
        $this->assertTrue($permission->tankStatus, 'A crop in the water must be harvestable.');

        $this->assertFalse($permission->edit);
        $this->assertFalse($permission->create);
        $this->assertFalse($permission->delete);
        $this->assertFalse($permission->totalFeed);
        $this->assertFalse($permission->canShareAccess());
        $this->assertFalse($permission->canRevokeAccess());
    }

    public function test_adding_a_tank_to_a_locked_farm_is_refused(): void
    {
        $owner = $this->makeFarmer();
        [, , $third] = $this->makeFarms($owner, 3);

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/farmer/farm/create-tank', [
            'farm_id'   => $third->id,
            'tank_name' => 'Locked Tank',
            'tank_size' => 100,
        ]);

        $response->assertStatus(403);
        $this->assertTrue($response->json('locked'));
    }

    public function test_recording_feed_on_a_locked_farm_is_refused(): void
    {
        $owner = $this->makeFarmer();
        [, , $third] = $this->makeFarms($owner, 3);

        $tank = Tank::create([
            'farm_id'   => $third->id,
            'tank_name' => 'Locked Tank',
            'status'    => 1,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/farmer/tanks/add-todays-tanks-quantity', [
            'tank_id'       => $tank->id,
            'meals'         => 1,
            'feed_quantity' => 5,
        ]);

        $response->assertStatus(403);
        $this->assertTrue($response->json('locked'));
    }

    public function test_the_free_farms_keep_working_while_another_is_locked(): void
    {
        $owner = $this->makeFarmer();
        [$first, , $third] = $this->makeFarms($owner, 3);

        $access = app(FarmAccessService::class);

        $this->assertFalse($access->permissionFor($owner->id, $first)->locked);
        $this->assertTrue($access->permissionFor($owner->id, $third)->locked);

        $tank = Tank::create([
            'farm_id'   => $first->id,
            'tank_name' => 'Free Tank',
            'status'    => 1,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/farmer/tanks/add-todays-tanks-quantity', [
            'tank_id'       => $tank->id,
            'meals'         => 1,
            'feed_quantity' => 5,
        ]);

        $this->assertNotSame(403, $response->status(), 'A free farm must never be locked.');
    }

    public function test_harvesting_is_allowed_on_a_locked_farm_but_starting_a_crop_is_not(): void
    {
        $owner = $this->makeFarmer();
        [, , $third] = $this->makeFarms($owner, 3);

        $tank = Tank::create([
            'farm_id'   => $third->id,
            'tank_name' => 'Locked Tank',
            'status'    => 1,
        ]);

        Sanctum::actingAs($owner);

        $activate = $this->postJson('/api/farmer/tank/status', [
            'tank_id' => $tank->id,
            'status'  => 1,
        ]);

        $activate->assertStatus(403);
        $this->assertTrue($activate->json('locked'));

        $harvest = $this->postJson('/api/farmer/tank/status', [
            'tank_id' => $tank->id,
            'status'  => 0,
        ]);

        $this->assertNotSame(403, $harvest->status(), 'Harvesting must survive a lapsed package.');
    }

    public function test_reports_and_history_still_work_on_a_locked_farm(): void
    {
        $owner = $this->makeFarmer();
        [, , $third] = $this->makeFarms($owner, 3);

        Sanctum::actingAs($owner);

        $tanks = $this->getJson("/api/farmer/farms/{$third->id}/tanks");

        $this->assertNotSame(403, $tanks->status(), 'A locked farm must stay readable.');
    }

    public function test_the_history_is_still_readable_on_a_locked_farm(): void
    {
        $owner = $this->makeFarmer();
        [, , $third] = $this->makeFarms($owner, 3);

        $this->assertTrue(app(SubscriptionService::class)->isFarmLocked($third));

        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/farmer/farm/{$third->id}/activity");

        $this->assertNotSame(
            403,
            $response->status(),
            'Reading the history is a view, like reports — a lapsed package must not hide it.'
        );
    }

    public function test_sharing_access_to_a_locked_farm_is_refused(): void
    {
        $owner = $this->makeFarmer();
        $other = $this->makeFarmer();
        [, , $third] = $this->makeFarms($owner, 3);

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/farmer/farm/{$third->id}/members", [
            'members' => [
                ['farmer_id' => $other->id, 'role' => 'manager'],
            ],
        ]);

        $response->assertStatus(403);
    }

    public function test_status_endpoint_names_the_locked_farms(): void
    {
        $owner = $this->makeFarmer();
        [, , $third] = $this->makeFarms($owner, 3);

        $status = $this->subscriptions()->statusFor($owner->id);

        $this->assertSame([$third->id], $status['locked_farm_ids']);
    }
}
