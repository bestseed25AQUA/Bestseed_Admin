<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmAccessMember;
use App\Models\Farmer;
use App\Models\User;
use Tests\TestCase;

/**
 * Access is given by 10-digit mobile number, to people who may not have the
 * app yet.
 */
class AccessByMobileTest extends TestCase
{
    public function test_search_is_exact_and_unknown_numbers_can_be_added(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Needs the development MySQL database.');
        }

        $owner = Farmer::whereNotNull('mobile')->first();
        $farm  = Farm::create([
            'farm_name' => 'Mobile Access Test Farm',
            'farmer_id' => $owner->id,
            'status'    => 1,
        ]);

        $existing = Farmer::where('id', '!=', $owner->id)->whereNotNull('mobile')->first();
        $unknown  = '9' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);

        // make sure the "unknown" number really is unknown
        Farmer::where('mobile', $unknown)->delete();

        try {
            // --- search: fewer than 10 digits is refused outright
            $this->actingAs($owner, 'sanctum')
                ->getJson('/api/farmer/farmers/search?q=98765')
                ->assertStatus(422);

            // --- search: a name must NOT match anything
            $this->actingAs($owner, 'sanctum')
                ->getJson('/api/farmer/farmers/search?q=' . urlencode('Ramesh'))
                ->assertStatus(422);

            // --- search: an exact 10-digit number returns exactly one person
            $hit = $this->actingAs($owner, 'sanctum')
                ->getJson('/api/farmer/farmers/search?q=' . $existing->mobile)
                ->assertOk()
                ->json();

            $this->assertTrue($hit['found']);
            $this->assertCount(1, $hit['data'], 'Exactly one person, never a list.');
            $this->assertSame($existing->mobile, $hit['data'][0]['mobile']);

            // --- search: an unknown number is not an error, it is "found: false"
            $miss = $this->actingAs($owner, 'sanctum')
                ->getJson('/api/farmer/farmers/search?q=' . $unknown)
                ->assertOk()
                ->json();

            $this->assertFalse($miss['found']);
            $this->assertSame([], $miss['data']);

            // --- adding an unregistered number registers them and grants access
            $this->actingAs($owner, 'sanctum')
                ->postJson("/api/farmer/farm/{$farm->id}/members", [
                    'mobiles'     => [$unknown],
                    'role'        => 'manager',
                    'view_access' => 1,
                ])
                ->assertStatus(201);

            $invited = Farmer::where('mobile', $unknown)->first();
            $this->assertNotNull($invited, 'The number must become a farmer row.');
            $this->assertSame('farmer', $invited->role);

            $this->assertTrue(
                FarmAccessMember::where('farm_id', $farm->id)
                    ->where('farmer_id', $invited->id)->live()->exists()
            );

            // --- the farm is waiting for them the moment they sign in
            $this->assertTrue(
                Farm::accessibleBy($invited->id)->where('id', $farm->id)->exists(),
                'After logging in, the invited person must see this farm.'
            );

            // --- and logging in must reuse that row, not make a second one
            $onLogin = Farmer::firstOrCreate(['mobile' => $unknown], ['role' => 'farmer']);
            $this->assertSame(
                $invited->id,
                $onLogin->id,
                'The login flow must find the invited row, not create a duplicate.'
            );
            $this->assertSame(1, Farmer::where('mobile', $unknown)->count());
        } finally {
            FarmAccessMember::where('farm_id', $farm->id)->delete();
            $farm->forceDelete();
            Farmer::where('mobile', $unknown)->delete();
        }
    }
}
