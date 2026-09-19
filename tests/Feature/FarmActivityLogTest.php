<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmAccessMember;
use App\Models\FarmActivity;
use App\Models\Farmer;
use App\Models\Tank;
use App\Models\TankBatch;
use App\Services\TankBatchService;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\UsesDevelopmentDatabase;
use Tests\TestCase;

/**
 * Who changed what on a farm, and who is allowed to look.
 */
class FarmActivityLogTest extends TestCase
{
    use UsesDevelopmentDatabase;

    private Farmer $owner;
    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->beginDevelopmentDatabase();

        $this->owner = $this->makeFarmer('Owner');

        $this->farm = Farm::create([
            'farm_name'     => 'Activity Test Farm',
            'farmer_id'     => $this->owner->id,
            'status'        => 1,
            'stocking_date' => Carbon::today()->subDays(30)->toDateString(),
            'store'         => 5000,
        ]);
    }

    protected function tearDown(): void
    {
        $this->rollBackDevelopmentDatabase();
        parent::tearDown();
    }

    private function makeFarmer(string $name): Farmer
    {
        return Farmer::create([
            'first_name' => $name,
            'last_name'  => 'Test',
            'mobile'     => (string) random_int(7000000000, 9999999999),
            'role'       => 'farmer',
        ]);
    }

    private function addMember(Farmer $person, string $role): FarmAccessMember
    {
        return FarmAccessMember::create([
            'farm_id'            => $this->farm->id,
            'farmer_id'          => $person->id,
            'granted_by'         => $this->owner->id,
            'role'               => $role,
            'view_access'        => 1,
            'edit_access'        => 1,
            'tank_status_access' => 1,
            'total_feed_access'  => 1,
            'create_access'      => 1,
            'delete_access'      => 1,
        ]);
    }

    private function makeTank(string $name = 'Tank1', int $status = 1): Tank
    {
        $tank = Tank::create([
            'farm_id'       => $this->farm->id,
            'tank_name'     => $name,
            'status'        => $status,
            'stocking_date' => Carbon::today()->subDays(30)->toDateString(),
        ]);

        if ($status === 1) {
            TankBatch::create([
                'tank_id'       => $tank->id,
                'farm_id'       => $this->farm->id,
                'batch_no'      => 1,
                'stocking_date' => Carbon::today()->subDays(30)->toDateString(),
                'started_at'    => now(),
            ]);
        }

        return $tank;
    }

    private function entries(): array
    {
        return FarmActivity::forFarm($this->farm->id)
            ->orderBy('id')
            ->get()
            ->all();
    }

    // ── What gets recorded ──────────────────────────────────────────────────

    public function test_recording_feed_names_the_person_the_tank_and_the_amount(): void
    {
        $tank = $this->makeTank('Tank3');

        Sanctum::actingAs($this->owner);

        $this->postJson('/api/farmer/tanks/add-todays-tanks-quantity', [
            'tank_id'       => $tank->id,
            'meals'         => 2,
            'feed_quantity' => 12.5,
        ])->assertStatus(201);

        $entry = collect($this->entries())
            ->firstWhere('category', FarmActivity::CATEGORY_FEED);

        $this->assertNotNull($entry, 'Recording feed should leave a trace.');

        // Everything the client asked each row to carry.
        $this->assertSame('Owner Test', $entry->actor_name);
        $this->assertSame($this->owner->mobile, $entry->actor_mobile);
        $this->assertSame('owner', $entry->actor_role);
        $this->assertSame('Tank3', $entry->tank_name);
        $this->assertSame($tank->id, (int) $entry->tank_id);
        $this->assertSame('Feed', $entry->category_label);
        $this->assertNotNull($entry->created_at);

        $this->assertStringContainsString('12.5 kg', $entry->description);
        $this->assertStringContainsString('Tank3', $entry->description);
    }

    public function test_the_actor_role_reflects_standing_on_this_farm(): void
    {
        $tank    = $this->makeTank();
        $manager = $this->makeFarmer('Manager');
        $this->addMember($manager, 'manager');

        Sanctum::actingAs($manager);

        $this->postJson('/api/farmer/tanks/add-todays-tanks-quantity', [
            'tank_id'       => $tank->id,
            'meals'         => 1,
            'feed_quantity' => 4,
        ])->assertStatus(201);

        $entry = collect($this->entries())->firstWhere('category', FarmActivity::CATEGORY_FEED);

        $this->assertSame('Manager Test', $entry->actor_name);
        $this->assertSame('manager', $entry->actor_role, 'The log should say what they were when they acted.');
    }

    public function test_starting_and_harvesting_a_crop_are_both_recorded(): void
    {
        $tank = $this->makeTank('Tank7', 0);

        app(TankBatchService::class)->setStatus(
            $tank,
            1,
            Carbon::today()->subDays(4)->toDateString(),
            8
        );

        app(TankBatchService::class)->setStatus($tank, 0, null, 0, 310.0);

        $tankEntries = collect($this->entries())
            ->where('category', FarmActivity::CATEGORY_TANK)
            ->values();

        $this->assertSame(
            FarmActivity::ACTION_ACTIVATED,
            $tankEntries[0]->action
        );
        $this->assertStringContainsString('8 kg already fed', $tankEntries[0]->description);

        $this->assertSame(
            FarmActivity::ACTION_HARVESTED,
            $tankEntries[1]->action
        );
        $this->assertStringContainsString('310 kg harvested', $tankEntries[1]->description);
    }

    public function test_granting_and_revoking_access_name_the_person(): void
    {
        $helper = $this->makeFarmer('Helper');

        Sanctum::actingAs($this->owner);

        $this->postJson("/api/farmer/farm/{$this->farm->id}/members", [
            'farmer_ids'  => [$helper->id],
            'role'        => 'manager',
            'view_access' => true,
            'edit_access' => true,
        ])->assertStatus(201);

        $granted = collect($this->entries())->firstWhere('action', FarmActivity::ACTION_GRANTED);

        $this->assertNotNull($granted);
        $this->assertStringContainsString('Helper Test', $granted->description);
        $this->assertStringContainsString($helper->mobile, $granted->description);
        $this->assertStringContainsString('view', $granted->description);
        $this->assertStringContainsString('edit', $granted->description);

        $member = FarmAccessMember::where('farm_id', $this->farm->id)
            ->where('farmer_id', $helper->id)
            ->first();

        $this->postJson("/api/farmer/members/{$member->id}/revoke")->assertOk();

        $revoked = collect($this->entries())->firstWhere('action', FarmActivity::ACTION_REVOKED);

        $this->assertNotNull($revoked);
        $this->assertStringContainsString('Helper Test', $revoked->description);
    }

    public function test_updating_the_store_records_what_the_farmer_typed(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson("/api/farmer/farm/{$this->farm->id}/update-total-feed", [
            'store'          => 4200,
            'low_feed_limit' => 300,
        ])->assertOk();

        $entry = collect($this->entries())->firstWhere('category', FarmActivity::CATEGORY_STORE);

        $this->assertNotNull($entry);
        // The figure they typed, not the column behind it.
        $this->assertStringContainsString('4200 kg', $entry->description);
        $this->assertStringContainsString('300 kg', $entry->description);
    }

    public function test_deleting_a_farm_is_recorded_before_it_goes(): void
    {
        Sanctum::actingAs($this->owner);

        $this->getJson("/api/farmer/farm/delete/{$this->farm->id}")->assertOk();

        $entry = collect($this->entries())->firstWhere('action', FarmActivity::ACTION_DELETED);

        $this->assertNotNull($entry, 'The deletion must be recorded while the farm still has a name.');
        $this->assertStringContainsString('Activity Test Farm', $entry->description);
    }

    // ── Who may read it ─────────────────────────────────────────────────────

    private function activity(): \Illuminate\Testing\TestResponse
    {
        return $this->getJson("/api/farmer/farm/{$this->farm->id}/activity");
    }

    public function test_the_owner_can_read_the_history(): void
    {
        $this->makeTank();
        Sanctum::actingAs($this->owner);

        $this->activity()
            ->assertOk()
            ->assertJsonPath('data.window_days', 15)
            ->assertJsonStructure(['data' => ['window_days', 'categories', 'entries']]);
    }

    public function test_a_partner_can_read_the_history(): void
    {
        $partner = $this->makeFarmer('Partner');
        $this->addMember($partner, 'partner');

        Sanctum::actingAs($partner);

        $this->activity()->assertOk();
    }

    public function test_a_manager_cannot_read_the_history(): void
    {
        $manager = $this->makeFarmer('Manager');
        $this->addMember($manager, 'manager');

        Sanctum::actingAs($manager);

        // Their own entries are IN the log; seeing the log is a different
        // thing from appearing in it.
        $this->activity()
            ->assertStatus(403)
            ->assertJsonPath('message', 'Only the farm owner or a partner can view the farm history.');
    }

    public function test_a_stranger_cannot_read_the_history(): void
    {
        Sanctum::actingAs($this->makeFarmer('Stranger'));

        $this->activity()
            ->assertStatus(403)
            ->assertJsonPath('message', 'You do not have access to this farm.');
    }

    // ── The window ──────────────────────────────────────────────────────────

    public function test_the_app_only_sees_the_last_fifteen_days(): void
    {
        $recent = FarmActivity::create([
            'farm_id'     => $this->farm->id,
            'category'    => FarmActivity::CATEGORY_FARM,
            'action'      => FarmActivity::ACTION_UPDATED,
            'description' => 'Recent change.',
            'actor_name'  => 'Owner Test',
        ]);

        $old = FarmActivity::create([
            'farm_id'     => $this->farm->id,
            'category'    => FarmActivity::CATEGORY_FARM,
            'action'      => FarmActivity::ACTION_UPDATED,
            'description' => 'Ancient change.',
            'actor_name'  => 'Owner Test',
        ]);

        // Twenty days back: inside the admin's 30-day window, outside the
        // app's 15-day one.
        $old->forceFill(['created_at' => Carbon::now()->subDays(20)])->saveQuietly();

        Sanctum::actingAs($this->owner);

        $ids = collect($this->activity()->assertOk()->json('data.entries'))
            ->pluck('id')
            ->all();

        $this->assertContains($recent->id, $ids);
        $this->assertNotContains($old->id, $ids, 'The app window is 15 days.');

        // The same entry is still within reach of the admin panel.
        $this->assertTrue(
            FarmActivity::forFarm($this->farm->id)
                ->recent(FarmActivity::ADMIN_WINDOW_DAYS)
                ->pluck('id')
                ->contains($old->id),
            'The admin window is 30 days.'
        );
    }

    public function test_the_history_can_be_filtered_by_category_and_tank(): void
    {
        $tank = $this->makeTank('Tank9');

        Sanctum::actingAs($this->owner);

        $this->postJson('/api/farmer/tanks/add-todays-tanks-quantity', [
            'tank_id'       => $tank->id,
            'meals'         => 1,
            'feed_quantity' => 5,
        ])->assertStatus(201);

        FarmActivity::create([
            'farm_id'     => $this->farm->id,
            'category'    => FarmActivity::CATEGORY_FARM,
            'action'      => FarmActivity::ACTION_UPDATED,
            'description' => 'Something about the farm.',
            'actor_name'  => 'Owner Test',
        ]);

        $feedOnly = $this->getJson(
            "/api/farmer/farm/{$this->farm->id}/activity?category=feed"
        )->assertOk()->json('data.entries');

        $this->assertNotEmpty($feedOnly);
        foreach ($feedOnly as $entry) {
            $this->assertSame('feed', $entry['category']);
        }

        $tankOnly = $this->getJson(
            "/api/farmer/farm/{$this->farm->id}/activity?tank_id={$tank->id}"
        )->assertOk()->json('data.entries');

        $this->assertNotEmpty($tankOnly);
        foreach ($tankOnly as $entry) {
            $this->assertSame($tank->id, $entry['tank_id']);
        }
    }

    public function test_generating_back_history_does_not_flood_the_log(): void
    {
        $tank = $this->makeTank('Tank11', 0);

        // 20 kg across 10 days is dozens of feed rows. The log should carry
        // ONE entry for the action a person actually took.
        app(TankBatchService::class)->setStatus(
            $tank,
            1,
            Carbon::today()->subDays(10)->toDateString(),
            20
        );

        $this->assertCount(
            1,
            collect($this->entries())->where('tank_id', $tank->id)->all(),
            'One human action is one entry, however many rows it wrote.'
        );
    }
}
