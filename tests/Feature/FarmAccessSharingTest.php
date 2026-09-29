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
 * The owner, plus anyone given CREATE access — role does not decide it.
 * Revoking needs create AND delete.
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

    /**
     * Put someone on a farm.
     *
     * [$abilities] overrides individual permissions. Everything is granted by
     * default, because most of these tests are about the two that decide
     * access sharing and the rest are noise.
     */
    private function addMember(
        Farm $farm,
        Farmer $person,
        string $role,
        ?Farmer $grantedBy = null,
        array $abilities = []
    ): FarmAccessMember {
        return FarmAccessMember::create(array_merge([
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
        ], $abilities));
    }

    // ── The rule itself ─────────────────────────────────────────────────────

    public function test_an_owner_may_share(): void
    {
        $this->assertTrue(FarmPermission::owner()->canShareAccess());
    }

    /** Create access decides it, for either role. */
    public function test_create_access_decides_who_may_share_not_the_role(): void
    {
        $owner  = $this->makeFarmer();
        $farm   = $this->makeFarm($owner);
        $access = app(FarmAccessService::class);

        $managerWithCreate    = $this->makeFarmer();
        $partnerWithoutCreate = $this->makeFarmer();

        $this->addMember($farm, $managerWithCreate, 'manager');
        $this->addMember($farm, $partnerWithoutCreate, 'partner', null, [
            'create_access' => 0,
        ]);

        $this->assertTrue(
            $access->permissionFor($managerWithCreate->id, $farm)->canShareAccess(),
            'A manager given create access may bring people in.'
        );

        $this->assertFalse(
            $access->permissionFor($partnerWithoutCreate->id, $farm)->canShareAccess(),
            'Being a partner is not enough without create access.'
        );
    }

    public function test_someone_with_no_standing_may_not_share(): void
    {
        $this->assertFalse(FarmPermission::none()->canShareAccess());
    }

    public function test_a_member_holding_nothing_has_nothing_to_give(): void
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
            'No create access, nothing to pass on.'
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

    public function test_someone_without_create_access_is_refused_when_granting(): void
    {
        $owner  = $this->makeFarmer();
        $farm   = $this->makeFarm($owner);
        $helper = $this->makeFarmer();
        $new    = $this->makeFarmer();

        // Everything except create.
        $this->addMember($farm, $helper, 'partner', null, ['create_access' => 0]);

        Sanctum::actingAs($helper);

        $this->postJson("/api/farmer/farm/{$farm->id}/members", $this->grantPayload($new))
            ->assertStatus(403)
            ->assertJsonPath(
                'message',
                'You need create access on this farm to give someone access.'
            );

        // And nothing was written on the way to being refused.
        $this->assertDatabaseMissing('farm_access_members', [
            'farm_id'   => $farm->id,
            'farmer_id' => $new->id,
        ]);
    }

    public function test_a_manager_with_create_access_may_grant(): void
    {
        $owner   = $this->makeFarmer();
        $farm    = $this->makeFarm($owner);
        $manager = $this->makeFarmer();
        $new     = $this->makeFarmer();

        $this->addMember($farm, $manager, 'manager');

        Sanctum::actingAs($manager);

        $this->postJson("/api/farmer/farm/{$farm->id}/members", $this->grantPayload($new))
            ->assertStatus(201);

        $this->assertDatabaseHas('farm_access_members', [
            'farm_id'    => $farm->id,
            'farmer_id'  => $new->id,
            'granted_by' => $manager->id,
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

    /** Revoking needs create AND delete; create alone is not enough. */
    public function test_create_without_delete_cannot_revoke(): void
    {
        $owner  = $this->makeFarmer();
        $farm   = $this->makeFarm($owner);
        $helper = $this->makeFarmer();

        $this->addMember($farm, $helper, 'manager', null, ['delete_access' => 0]);

        $theirs = $this->addMember($farm, $this->makeFarmer(), 'manager', $helper);

        Sanctum::actingAs($helper);

        $this->postJson("/api/farmer/members/{$theirs->id}/revoke")->assertStatus(403);

        $this->assertNull(
            $theirs->fresh()->revoked_at,
            'Delete is what turns "may bring people in" into "may also remove them".'
        );
    }

    /** Delete is held for the farm, not only for one's own appointees. */
    public function test_someone_with_create_and_delete_can_revoke_anyone_but_themselves(): void
    {
        $owner  = $this->makeFarmer();
        $farm   = $this->makeFarm($owner);
        $helper = $this->makeFarmer();

        $self      = $this->addMember($farm, $helper, 'partner');
        $theirs    = $this->addMember($farm, $this->makeFarmer(), 'manager', $helper);
        $theOwners = $this->addMember($farm, $this->makeFarmer(), 'manager');

        Sanctum::actingAs($helper);

        $this->postJson("/api/farmer/members/{$theirs->id}/revoke")->assertOk();
        $this->assertNotNull($theirs->fresh()->revoked_at);

        $this->postJson("/api/farmer/members/{$theOwners->id}/revoke")->assertOk();
        $this->assertNotNull($theOwners->fresh()->revoked_at);

        // But not themselves, or a farm can be left with nobody able to
        // manage access.
        $this->postJson("/api/farmer/members/{$self->id}/revoke")->assertStatus(403);
        $this->assertNull($self->fresh()->revoked_at);
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
