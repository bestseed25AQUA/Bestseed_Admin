<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmAccessMember;
use App\Models\Farmer;
use Tests\TestCase;

/**
 * "Give access again" re-uses the grant endpoint rather than needing a restore
 * of its own: granting writes revoked_at = null, so the same call that admits
 * someone new admits someone who was removed.
 */
class RestoreRevokedMemberTest extends TestCase
{
    public function test_granting_again_restores_a_removed_member(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Needs the development MySQL database.');
        }

        $owner  = Farmer::first();
        $person = Farmer::where('id', '!=', $owner->id)->first();

        $farm = Farm::create([
            'farm_name' => 'Restore Access Farm',
            'farmer_id' => $owner->id,
            'status'    => 1,
        ]);

        try {
            // Given access, then removed.
            $this->actingAs($owner, 'sanctum')
                ->postJson("/api/farmer/farm/{$farm->id}/members", [
                    'farmer_ids'  => [$person->id],
                    'role'        => 'manager',
                    'view_access' => 1,
                    'edit_access' => 1,
                ])->assertStatus(201);

            $member = FarmAccessMember::where('farm_id', $farm->id)
                ->where('farmer_id', $person->id)->firstOrFail();

            $this->actingAs($owner, 'sanctum')
                ->postJson("/api/farmer/members/{$member->id}/revoke")
                ->assertOk();

            $this->assertFalse($member->fresh()->isLive());
            $this->assertFalse(
                Farm::accessibleBy($person->id)->where('id', $farm->id)->exists(),
                'A removed person must not see the farm.'
            );

            // "Give access again" — the same grant call the sheet already makes.
            $this->actingAs($owner, 'sanctum')
                ->postJson("/api/farmer/farm/{$farm->id}/members", [
                    'farmer_ids'  => [$person->id],
                    'role'        => 'manager',
                    'view_access' => 1,
                ])->assertStatus(201);

            $this->assertTrue($member->fresh()->isLive(), 'Access must come back.');

            // And it must reuse the row, not stack a second membership.
            $this->assertSame(
                1,
                FarmAccessMember::where('farm_id', $farm->id)
                    ->where('farmer_id', $person->id)->count()
            );

            $this->assertTrue(
                Farm::accessibleBy($person->id)->where('id', $farm->id)->exists(),
                'They must see the farm again.'
            );
        } finally {
            FarmAccessMember::where('farm_id', $farm->id)->delete();
            $farm->forceDelete();
        }
    }
}
