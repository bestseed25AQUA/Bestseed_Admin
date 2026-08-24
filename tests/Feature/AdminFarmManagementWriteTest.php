<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmAccessMember;
use App\Models\Farmer;
use App\Models\Feed;
use App\Models\Tank;
use App\Models\TankFeedHistory;
use App\Models\User;
use Tests\TestCase;

/**
 * Exercises every admin write path added for farm management, against a farm
 * created for the test and torn down afterwards.
 *
 * Rendering a page proves very little; these are the actions that can corrupt
 * data — feed written to one table and not the other, a member row that
 * outlives its revoke, a tank deleted while its feed keeps counting.
 */
class AdminFarmManagementWriteTest extends TestCase
{
    public function test_admin_can_manage_tanks_feed_and_members(): void
    {
        $admin  = User::first();
        $farmer = Farmer::first();
        $this->assertNotNull($admin);
        $this->assertNotNull($farmer);

        $farm = Farm::create([
            'farm_name'     => 'Smoke Test Farm',
            'farmer_id'     => $farmer->id,
            'status'        => 1,
            'stocking_date' => now()->subDays(10)->toDateString(),
            'no_of_tanks'   => 1,
            'store'         => 1000,
        ]);

        try {
            // ---------------------------------------------------------- tanks
            $this->actingAs($admin)
                ->post("/admin/farm-management/farms/{$farm->id}/tanks", [
                    'tank_name' => 'Smoke Tank',
                    'status'    => 1,
                    'meals'     => 3,
                    'store'     => 500,
                ])->assertRedirect();

            $tank = Tank::where('farm_id', $farm->id)->firstOrFail();
            $this->assertSame('Smoke Tank', $tank->tank_name);
            $this->assertSame(
                $farm->stocking_date,
                (string) $tank->stocking_date,
                'A new tank should inherit the farm stocking date.'
            );

            $this->actingAs($admin)
                ->put("/admin/farm-management/farms/{$farm->id}/tanks/{$tank->id}", [
                    'tank_name' => 'Smoke Tank Renamed',
                    'status'    => 1,
                    'meals'     => 4,
                ])->assertRedirect();
            $this->assertSame('Smoke Tank Renamed', $tank->fresh()->tank_name);

            $this->actingAs($admin)
                ->post("/admin/farm-management/farms/{$farm->id}/tanks/{$tank->id}/toggle-status")
                ->assertRedirect();
            $this->assertSame(0, (int) $tank->fresh()->status);

            // ----------------------------------------------------------- feed
            $this->actingAs($admin)
                ->post("/admin/farm-management/farms/{$farm->id}/tanks/{$tank->id}/feed", [
                    'feed_date'     => now()->toDateString(),
                    'meals'         => 3,
                    'feed_quantity' => 12.5,
                ])->assertRedirect();

            $entry = TankFeedHistory::where('tank_id', $tank->id)->firstOrFail();

            // Both feed tables must move together — they have drifted before.
            $this->assertSame(
                1,
                Feed::where('tank_id', $tank->id)->count(),
                'Recording feed must write to `feeds` as well as `tank_feed_histories`.'
            );
            $this->assertEqualsWithDelta(12.5, (float) $tank->fresh()->total_feed_used, 0.001);

            $this->actingAs($admin)
                ->put("/admin/farm-management/farms/{$farm->id}/tanks/{$tank->id}/feed/{$entry->id}", [
                    'meals'         => 4,
                    'feed_quantity' => 20,
                ])->assertRedirect();

            $this->assertEqualsWithDelta(20.0, (float) $entry->fresh()->feed_quantity, 0.001);
            $this->assertEqualsWithDelta(
                20.0,
                (float) Feed::where('tank_id', $tank->id)->sum('feed_quantity'),
                0.001,
                'Editing an entry must move its `feeds` twin, not leave the old value behind.'
            );
            $this->assertEqualsWithDelta(20.0, (float) $tank->fresh()->total_feed_used, 0.001);

            $this->actingAs($admin)
                ->delete("/admin/farm-management/farms/{$farm->id}/tanks/{$tank->id}/feed/{$entry->id}")
                ->assertRedirect();

            $this->assertSame(0, TankFeedHistory::where('tank_id', $tank->id)->count());
            $this->assertSame(0, Feed::where('tank_id', $tank->id)->count());
            $this->assertEqualsWithDelta(0.0, (float) $tank->fresh()->total_feed_used, 0.001);

            // -------------------------------------------------------- members
            $other = Farmer::where('id', '!=', $farmer->id)->firstOrFail();

            $this->actingAs($admin)
                ->post("/admin/farm-management/farms/{$farm->id}/members", [
                    'farmer_ids'  => [$other->id],
                    'role'        => 'manager',
                    'view_access' => 1,
                    'edit_access' => 1,
                ])->assertRedirect();

            $member = FarmAccessMember::where('farm_id', $farm->id)->firstOrFail();
            $this->assertTrue($member->isLive());
            $this->assertSame(1, (int) $member->edit_access);

            // The owner must never get a membership row for their own farm.
            $this->actingAs($admin)
                ->post("/admin/farm-management/farms/{$farm->id}/members", [
                    'farmer_ids'  => [$farm->farmer_id],
                    'role'        => 'manager',
                    'view_access' => 1,
                ])->assertRedirect();
            $this->assertSame(
                1,
                FarmAccessMember::where('farm_id', $farm->id)->count(),
                'The farm owner should be skipped, not given a membership row.'
            );

            $this->actingAs($admin)
                ->put("/admin/farm-management/members/{$member->id}", [
                    'role'        => 'partner',
                    'view_access' => 1,
                ])->assertRedirect();
            $member->refresh();
            $this->assertSame('partner', $member->role);
            $this->assertSame(0, (int) $member->edit_access, 'Unticked permissions must be cleared.');

            $this->actingAs($admin)
                ->post("/admin/farm-management/members/{$member->id}/revoke")
                ->assertRedirect();
            $this->assertFalse($member->fresh()->isLive());

            // A revoked member must drop out of the farmer's visible farms.
            $this->assertFalse(
                Farm::accessibleBy($other->id)->where('id', $farm->id)->exists(),
                'A revoked member should no longer see the farm in the app.'
            );

            $this->actingAs($admin)
                ->post("/admin/farm-management/members/{$member->id}/restore")
                ->assertRedirect();
            $this->assertTrue($member->fresh()->isLive());
            $this->assertTrue(
                Farm::accessibleBy($other->id)->where('id', $farm->id)->exists(),
                'A restored member should see the farm again.'
            );

            $this->actingAs($admin)
                ->delete("/admin/farm-management/members/{$member->id}")
                ->assertRedirect();
            $this->assertSame(0, FarmAccessMember::where('farm_id', $farm->id)->count());

            // ------------------------------------------------- tank teardown
            $this->actingAs($admin)
                ->delete("/admin/farm-management/farms/{$farm->id}/tanks/{$tank->id}")
                ->assertRedirect();
            $this->assertNull(Tank::find($tank->id));
        } finally {
            Tank::where('farm_id', $farm->id)->delete();
            Feed::where('farm_id', $farm->id)->delete();
            TankFeedHistory::where('farm_id', $farm->id)->delete();
            FarmAccessMember::where('farm_id', $farm->id)->delete();
            $farm->forceDelete();
        }
    }
}
