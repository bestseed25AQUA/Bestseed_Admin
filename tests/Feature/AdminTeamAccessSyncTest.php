<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmAccessMember;
use App\Models\Farmer;
use App\Models\Manager;
use App\Models\User;
use Tests\Concerns\UsesDevelopmentDatabase;
use Tests\TestCase;

/**
 * Access added from the admin panel has to reach the app.
 *
 * `managers` is an address book. `farm_access_members` is what decides whether
 * a farm opens. The admin screen wrote only the first, so a manager added
 * there saw nothing and neither did the owner.
 */
class AdminTeamAccessSyncTest extends TestCase
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

    private function makeFarmer(string $name = 'Team'): Farmer
    {
        return Farmer::create([
            'first_name' => $name,
            'last_name'  => 'Test',
            'mobile'     => (string) random_int(7000000000, 9999999999),
            'role'       => 'farmer',
        ]);
    }

    private function makeFarm(Farmer $owner): Farm
    {
        return Farm::create([
            'farm_name' => 'Team Test Farm',
            'farmer_id' => $owner->id,
            'status'    => 1,
        ]);
    }

    private function admin(): User
    {
        $admin = User::first();
        $this->assertNotNull($admin, 'An admin user is needed for this test.');

        return $admin;
    }

    /** @return array<string, mixed> */
    private function grant(Farm $farm, Farmer $person, array $overrides = []): array
    {
        return array_merge([
            'farm_id'            => $farm->id,
            'name'               => 'Satya',
            'phone'              => $person->mobile,
            'is_partner'         => 0,
            'view_access'        => 1,
            'edit_access'        => 1,
            'tank_status_access' => 0,
            'total_feed_access'  => 1,
            'create_access'      => 0,
            'delete_access'      => 0,
        ], $overrides);
    }

    public function test_adding_someone_from_the_admin_panel_opens_the_farm_in_the_app(): void
    {
        $owner   = $this->makeFarmer('Owner');
        $person  = $this->makeFarmer('Manager');
        $farm    = $this->makeFarm($owner);

        $this->actingAs($this->admin())
            ->post(route('farm-management.team.store'), $this->grant($farm, $person))
            ->assertRedirect();

        $member = FarmAccessMember::where('farm_id', $farm->id)
            ->where('farmer_id', $person->id)
            ->first();

        $this->assertNotNull($member, 'The admin write must reach farm_access_members.');
        $this->assertSame('manager', $member->role);
        $this->assertNull($member->revoked_at);

        // The permissions ticked in the panel, not a blanket grant.
        $this->assertSame(1, (int) $member->view_access);
        $this->assertSame(1, (int) $member->edit_access);
        $this->assertSame(0, (int) $member->tank_status_access);
        $this->assertSame(1, (int) $member->total_feed_access);
        $this->assertSame(0, (int) $member->create_access);
        $this->assertSame(0, (int) $member->delete_access);

        // And the farm now appears in what the app lists for them.
        $this->assertTrue(
            Farm::accessibleBy($person->id)->pluck('id')->contains($farm->id),
            'The farm must show in the manager\'s own list.'
        );
    }

    public function test_a_partner_added_from_the_panel_is_recorded_as_a_partner(): void
    {
        $owner  = $this->makeFarmer('Owner');
        $person = $this->makeFarmer('Partner');
        $farm   = $this->makeFarm($owner);

        $this->actingAs($this->admin())
            ->post(route('farm-management.team.store'), $this->grant($farm, $person, [
                'is_partner'    => 1,
                'create_access' => 1,
                'delete_access' => 1,
            ]))
            ->assertRedirect();

        $member = FarmAccessMember::where('farm_id', $farm->id)
            ->where('farmer_id', $person->id)
            ->first();

        $this->assertNotNull($member);
        $this->assertSame('partner', $member->role);
    }

    public function test_changing_the_permissions_in_the_panel_changes_them_in_the_app(): void
    {
        $owner  = $this->makeFarmer('Owner');
        $person = $this->makeFarmer('Manager');
        $farm   = $this->makeFarm($owner);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('farm-management.team.store'), $this->grant($farm, $person))
            ->assertRedirect();

        $row = Manager::where('farm_id', $farm->id)->where('phone', $person->mobile)->first();
        $this->assertNotNull($row);

        $this->actingAs($admin)
            ->put(route('farm-management.team.update', $row->id), $this->grant($farm, $person, [
                'edit_access'        => 0,
                'tank_status_access' => 1,
            ]))
            ->assertRedirect();

        $member = FarmAccessMember::where('farm_id', $farm->id)
            ->where('farmer_id', $person->id)
            ->first();

        $this->assertSame(0, (int) $member->edit_access);
        $this->assertSame(1, (int) $member->tank_status_access);
    }

    public function test_removing_someone_in_the_panel_takes_their_access_away(): void
    {
        $owner  = $this->makeFarmer('Owner');
        $person = $this->makeFarmer('Manager');
        $farm   = $this->makeFarm($owner);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('farm-management.team.store'), $this->grant($farm, $person))
            ->assertRedirect();

        $row = Manager::where('farm_id', $farm->id)->where('phone', $person->mobile)->first();

        $this->actingAs($admin)
            ->delete(route('farm-management.team.destroy', $row->id))
            ->assertRedirect();

        $member = FarmAccessMember::where('farm_id', $farm->id)
            ->where('farmer_id', $person->id)
            ->first();

        $this->assertNotNull($member->revoked_at, 'Removing in the panel must revoke the access.');

        $this->assertFalse(
            Farm::accessibleBy($person->id)->pluck('id')->contains($farm->id),
            'A revoked member must not keep seeing the farm.'
        );
    }

    public function test_the_owner_is_never_given_a_membership_on_their_own_farm(): void
    {
        $owner = $this->makeFarmer('Owner');
        $farm  = $this->makeFarm($owner);

        $this->actingAs($this->admin())
            ->post(route('farm-management.team.store'), $this->grant($farm, $owner))
            ->assertRedirect();

        $this->assertSame(
            0,
            FarmAccessMember::where('farm_id', $farm->id)->where('farmer_id', $owner->id)->count()
        );
    }
}
