<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmAccessMember;
use App\Models\Farmer;
use App\Services\FarmAccessService;
use App\Support\FarmPermission;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\UsesDevelopmentDatabase;
use Tests\TestCase;

/**
 * Who may hand a farm to somebody else.
 *
 * Owners and partners. A partner co-owns the farm and may bring people in; a
 * manager is staff and may not. Before this rule, ANYONE holding any
 * permission could pass it on, so a manager given nothing but view access
 * could appoint managers and partners of their own and quietly widen who
 * reached the farm.
 *
 * The app hides the two "Set Up Access" options for a manager. These assert the
 * part that actually enforces it.
 */
class FarmAccessSharingTest extends TestCase
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
            'first_name' => 'Access',
            'last_name'  => 'Test',
            'mobile'     => (string) random_int(7000000000, 9999999999),
            'role'       => 'farmer',
        ]);
    }

    private function makeFarm(Farmer $owner): Farm
    {
        return Farm::create([
            'farm_name' => 'Access Test Farm',
            'farmer_id' => $owner->id,
            'status'    => 1,
        ]);
    }

    /** Give someone every permission on a farm, in the named role. */
    private function addMember(
        Farm $farm,
        Farmer $person,
        string $role,
        ?Farmer $grantedBy = null
    ): FarmAccessMember {
        return FarmAccessMember::create([
            'farm_id'            => $farm->id,
            'farmer_id'          => $person->id,
            'granted_by'         => $grantedBy?->id ?? $farm->farmer_id,
            'role'               => $role,
            'view_access'        => 1,
            'edit_access'        => 1,
            'tank_status_access' => 1,
            'total_feed_access'  => 1,
            'create_access'      => 1,
            'delete_access'      => 1,
        ]);
    }

    // ── The rule itself ─────────────────────────────────────────────────────

    public function test_an_owner_may_share(): void
    {
        $this->assertTrue(FarmPermission::owner()->canShareAccess());
    }

    public function test_a_partner_may_share_but_a_manager_may_not(): void
    {
        $owner   = $this->makeFarmer();
        $farm    = $this->makeFarm($owner);
        $access  = app(FarmAccessService::class);

        $partner = $this->makeFarmer();
        $manager = $this->makeFarmer();

        // Identical permissions. Only the role differs, which is the point:
        // the rule is about standing, not about what they can do day to day.
        $this->addMember($farm, $partner, 'partner');
        $this->addMember($farm, $manager, 'manager');

        $this->assertTrue(
            $access->permissionFor($partner->id, $farm)->canShareAccess(),
            'A partner co-owns the farm and may bring people in.'
        );

        $this->assertFalse(
            $access->permissionFor($manager->id, $farm)->canShareAccess(),
            'A manager holding every permission still may not give access away.'
        );
    }

    public function test_someone_with_no_standing_may_not_share(): void
    {
        $this->assertFalse(FarmPermission::none()->canShareAccess());
    }

    public function test_a_partner_holding_nothing_has_nothing_to_give(): void
    {
        $owner   = $this->makeFarmer();
        $farm    = $this->makeFarm($owner);
        $partner = $this->makeFarmer();

        FarmAccessMember::create([
            'farm_id'            => $farm->id,
            'farmer_id'          => $partner->id,
            'granted_by'         => $owner->id,
            'role'               => 'partner',
            'view_access'        => 0,
            'edit_access'        => 0,
            'tank_status_access' => 0,
            'total_feed_access'  => 0,
            'create_access'      => 0,
            'delete_access'      => 0,
        ]);

        $this->assertFalse(
            app(FarmAccessService::class)->permissionFor($partner->id, $farm)->canShareAccess(),
            'Being a partner is not enough — there has to be something to pass on.'
        );
    }

    public function test_the_flag_is_sent_to_the_app(): void
    {
        $this->assertTrue(FarmPermission::owner()->toArray()['can_share_access']);
        $this->assertFalse(FarmPermission::none()->toArray()['can_share_access']);
    }

    // ── Granting over HTTP ──────────────────────────────────────────────────

    /** The body the add-members endpoint expects. */
    private function grantPayload(Farmer $target, string $role = 'manager'): array
    {
        return [
            'farmer_ids'  => [$target->id],
            'role'        => $role,
            'view_access' => true,
        ];
    }

    public function test_an_owner_can_grant_access(): void
    {
        $owner = $this->makeFarmer();
        $farm  = $this->makeFarm($owner);
        $new   = $this->makeFarmer();

        Sanctum::actingAs($owner);

        $this->postJson("/api/farmer/farm/{$farm->id}/members", $this->grantPayload($new))
            ->assertStatus(201);

        $this->assertDatabaseHas('farm_access_members', [
            'farm_id'   => $farm->id,
            'farmer_id' => $new->id,
        ]);
    }

    public function test_a_partner_can_grant_access(): void
    {
        $owner   = $this->makeFarmer();
        $farm    = $this->makeFarm($owner);
        $partner = $this->makeFarmer();
        $new     = $this->makeFarmer();

        $this->addMember($farm, $partner, 'partner');

        Sanctum::actingAs($partner);

        $this->postJson("/api/farmer/farm/{$farm->id}/members", $this->grantPayload($new))
            ->assertStatus(201);

        $this->assertDatabaseHas('farm_access_members', [
            'farm_id'    => $farm->id,
            'farmer_id'  => $new->id,
            'granted_by' => $partner->id,
        ]);
    }

    public function test_a_manager_is_refused_when_granting_access(): void
    {
        $owner   = $this->makeFarmer();
        $farm    = $this->makeFarm($owner);
        $manager = $this->makeFarmer();
        $new     = $this->makeFarmer();

        $this->addMember($farm, $manager, 'manager');

        Sanctum::actingAs($manager);

        $this->postJson("/api/farmer/farm/{$farm->id}/members", $this->grantPayload($new))
            ->assertStatus(403)
            ->assertJsonPath(
                'message',
                'Only the farm owner or a partner can give access to this farm.'
            );

        // And nothing was written on the way to being refused.
        $this->assertDatabaseMissing('farm_access_members', [
            'farm_id'   => $farm->id,
            'farmer_id' => $new->id,
        ]);
    }

    public function test_a_stranger_is_still_refused_as_a_stranger(): void
    {
        $owner    = $this->makeFarmer();
        $farm     = $this->makeFarm($owner);
        $stranger = $this->makeFarmer();

        Sanctum::actingAs($stranger);

        // Not the sharing message: they have no access at all, and saying
        // "only an owner or partner can share" would imply they hold something.
        $this->postJson("/api/farmer/farm/{$farm->id}/members", $this->grantPayload($this->makeFarmer()))
            ->assertStatus(403)
            ->assertJsonPath('message', 'You do not have access to this farm.');
    }

    // ── Revoking ────────────────────────────────────────────────────────────

    public function test_an_owner_can_revoke_anyone(): void
    {
        $owner  = $this->makeFarmer();
        $farm   = $this->makeFarm($owner);
        $member = $this->addMember($farm, $this->makeFarmer(), 'manager');

        Sanctum::actingAs($owner);

        $this->postJson("/api/farmer/members/{$member->id}/revoke")->assertOk();

        $this->assertNotNull($member->fresh()->revoked_at);
    }

    public function test_a_manager_cannot_revoke_even_someone_they_admitted(): void
    {
        $owner   = $this->makeFarmer();
        $farm    = $this->makeFarm($owner);
        $manager = $this->makeFarmer();

        $this->addMember($farm, $manager, 'manager');

        // A row a manager granted before this rule existed. Taking access away
        // is the same authority as giving it, so they are refused now.
        $legacy = $this->addMember($farm, $this->makeFarmer(), 'manager', $manager);

        Sanctum::actingAs($manager);

        $this->postJson("/api/farmer/members/{$legacy->id}/revoke")->assertStatus(403);

        $this->assertNull(
            $legacy->fresh()->revoked_at,
            'A manager must not be able to remove people from the farm.'
        );
    }

    public function test_a_partner_can_revoke_only_people_they_admitted(): void
    {
        $owner   = $this->makeFarmer();
        $farm    = $this->makeFarm($owner);
        $partner = $this->makeFarmer();

        $this->addMember($farm, $partner, 'partner');

        $theirs    = $this->addMember($farm, $this->makeFarmer(), 'manager', $partner);
        $theOwners = $this->addMember($farm, $this->makeFarmer(), 'manager');

        Sanctum::actingAs($partner);

        $this->postJson("/api/farmer/members/{$theirs->id}/revoke")->assertOk();
        $this->assertNotNull($theirs->fresh()->revoked_at);

        // Nobody can lock out the person who let them in.
        $this->postJson("/api/farmer/members/{$theOwners->id}/revoke")->assertStatus(403);
        $this->assertNull($theOwners->fresh()->revoked_at);
    }

    // ── Reading the list is still open to everyone on the farm ──────────────

    public function test_a_manager_can_still_see_who_holds_access(): void
    {
        $owner   = $this->makeFarmer();
        $farm    = $this->makeFarm($owner);
        $manager = $this->makeFarmer();

        $this->addMember($farm, $manager, 'manager');

        Sanctum::actingAs($manager);

        // Losing the ability to CHANGE the list is not a reason to stop them
        // seeing who else works the farm.
        $this->getJson("/api/farmer/farm/{$farm->id}/members")
            ->assertOk()
            ->assertJsonPath('status', true);
    }
}
