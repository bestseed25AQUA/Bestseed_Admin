<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmAccessMember;
use App\Models\Farmer;
use App\Models\Tank;
use Tests\TestCase;

/**
 * Tank status and the feed store each carry their own permission now.
 *
 * The UI hiding a button is a courtesy; the server refusing the request is the
 * actual rule. These assert the rule.
 */
class FarmPermissionGateTest extends TestCase
{
    public function test_tank_status_and_total_feed_are_gated_separately(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Needs the development MySQL database.');
        }

        $owner   = Farmer::first();
        $manager = Farmer::where('id', '!=', $owner->id)->first();

        $farm = Farm::create([
            'farm_name' => 'Permission Gate Farm',
            'farmer_id' => $owner->id,
            'status'    => 1,
            'store'     => 1000,
        ]);

        $tank = Tank::create([
            'farm_id'   => $farm->id,
            'tank_name' => 'Gate Tank',
            'status'    => 1,
        ]);

        try {
            // A manager who may edit, and harvest, but NOT touch the store —
            // exactly the default the app now offers.
            $member = FarmAccessMember::create([
                'farm_id'            => $farm->id,
                'farmer_id'          => $manager->id,
                'granted_by'         => $owner->id,
                'role'               => 'manager',
                'view_access'        => 1,
                'edit_access'        => 1,
                'tank_status_access' => 1,
                'total_feed_access'  => 0,
                'create_access'      => 0,
                'delete_access'      => 0,
            ]);

            // Harvesting is allowed.
            $this->actingAs($manager, 'sanctum')
                ->postJson('/api/farmer/tank/status', [
                    'tank_id' => $tank->id,
                    'farm_id' => $farm->id,
                    'status'  => 0,
                ])
                ->assertOk();

            // The store is not — even though they hold edit_access.
            $this->actingAs($manager, 'sanctum')
                ->postJson("/api/farmer/farm/{$farm->id}/update-total-feed", [
                    'store' => 9999,
                ])
                ->assertStatus(403);

            $this->assertEquals(
                1000,
                (float) $farm->fresh()->store,
                'A refused request must not have changed the store.'
            );

            // Grant it, and the same request now works.
            $member->update(['total_feed_access' => 1]);

            $this->actingAs($manager, 'sanctum')
                ->postJson("/api/farmer/farm/{$farm->id}/update-total-feed", [
                    'store' => 9999,
                ])
                ->assertOk();

            $this->assertEquals(9999, (float) $farm->fresh()->store);

            // Take tank status away, and harvesting stops — while edit remains.
            $member->update(['tank_status_access' => 0]);

            $this->actingAs($manager, 'sanctum')
                ->postJson('/api/farmer/tank/status', [
                    'tank_id' => $tank->id,
                    'farm_id' => $farm->id,
                    'status'  => 1,
                ])
                ->assertStatus(403);

            // The owner is never gated by any of this.
            $this->actingAs($owner, 'sanctum')
                ->postJson('/api/farmer/tank/status', [
                    'tank_id' => $tank->id,
                    'farm_id' => $farm->id,
                    'status'  => 1,
                ])
                ->assertOk();
        } finally {
            FarmAccessMember::where('farm_id', $farm->id)->delete();
            Tank::where('farm_id', $farm->id)->delete();
            $farm->forceDelete();
        }
    }
}
