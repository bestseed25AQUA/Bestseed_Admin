<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Farmer;
use App\Models\Feed;
use App\Models\Tank;
use App\Models\TankBatch;
use App\Models\TankFeedHistory;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\UsesDevelopmentDatabase;
use Tests\TestCase;

/**
 * Deleting a tank hides it; it does not destroy it.
 *
 * Reported: a farm was created with five tanks and an admin deleted Tank 5.
 * It went permanently, taking every feed row with it, and there was no way to
 * put it back — while a FARM deleted the same way had been restorable all
 * along.
 */
class TankSoftDeleteTest extends TestCase
{
    use UsesDevelopmentDatabase;

    private Farmer $farmer;
    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->beginDevelopmentDatabase();

        $this->farmer = Farmer::create([
            'first_name' => 'SoftDelete',
            'last_name'  => 'Test',
            'mobile'     => (string) random_int(7000000000, 9999999999),
            'role'       => 'farmer',
        ]);

        $this->farm = Farm::create([
            'farm_name'     => 'Devagnya Test',
            'farmer_id'     => $this->farmer->id,
            'status'        => 1,
            'stocking_date' => Carbon::today()->subDays(20)->toDateString(),
            'store'         => 5000,
        ]);
    }

    protected function tearDown(): void
    {
        $this->rollBackDevelopmentDatabase();
        parent::tearDown();
    }

    /** A tank with an open crop and 40 kg recorded against it. */
    private function makeTank(string $name): Tank
    {
        $tank = Tank::create([
            'farm_id'       => $this->farm->id,
            'tank_name'     => $name,
            'status'        => 1,
            'stocking_date' => Carbon::today()->subDays(20)->toDateString(),
        ]);

        $batch = TankBatch::create([
            'tank_id'       => $tank->id,
            'farm_id'       => $this->farm->id,
            'batch_no'      => 1,
            'stocking_date' => Carbon::today()->subDays(20)->toDateString(),
            'started_at'    => now(),
        ]);

        $row = [
            'tank_id'       => $tank->id,
            'farm_id'       => $this->farm->id,
            'batch_id'      => $batch->id,
            'feed_date'     => Carbon::today()->subDays(2)->toDateString(),
            'meals'         => 1,
            'feed_quantity' => 40,
            'is_backfill'   => 0,
        ];

        Feed::create($row);
        TankFeedHistory::create($row);

        return $tank;
    }

    private function admin(): User
    {
        $admin = User::first();

        if (!$admin) {
            $this->markTestSkipped('No admin user to act as.');
        }

        return $admin;
    }

    private function deleteTank(Tank $tank): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin())
            ->delete("/admin/farm-management/farms/{$this->farm->id}/tanks/{$tank->id}");
    }

    /**
     * What the farmer's app shows for this farm.
     *
     * Re-authenticates every time on purpose: acting as the admin to delete a
     * tank leaves the admin as the authenticated user, and the farmer API
     * would then answer 403 for reasons that have nothing to do with the
     * behaviour under test.
     */
    private function farmerSeesTotals(): array
    {
        Sanctum::actingAs($this->farmer);

        $data = $this->getJson("/api/farmer/farm/feed-store/{$this->farm->id}")
            ->assertOk()
            ->json('data');

        return [
            'total'     => (float) $data['total_feed_used'],
            'remaining' => (float) $data['remaining_store'],
        ];
    }

    // ── Deleting ────────────────────────────────────────────────────────────

    public function test_deleting_a_tank_hides_it_and_keeps_its_feed(): void
    {
        $tank = $this->makeTank('Tank5');

        $this->deleteTank($tank)->assertRedirect();

        // Gone from every ordinary query, which is every query the app makes.
        $this->assertNull(Tank::find($tank->id), 'The tank should be hidden.');

        // But still there, with its records.
        $hidden = Tank::withTrashed()->find($tank->id);
        $this->assertNotNull($hidden, 'The row must survive so it can be restored.');
        $this->assertNotNull($hidden->deleted_at);

        $this->assertSame(
            1,
            Feed::where('tank_id', $tank->id)->count(),
            'Feed records are what make restoring worth doing.'
        );
        $this->assertSame(1, TankFeedHistory::where('tank_id', $tank->id)->count());
    }

    public function test_the_app_no_longer_lists_a_deleted_tank(): void
    {
        $kept    = $this->makeTank('Tank1');
        $removed = $this->makeTank('Tank5');

        $this->deleteTank($removed);

        Sanctum::actingAs($this->farmer);

        $names = collect(
            $this->getJson("/api/farmer/farms/{$this->farm->id}/tanks")
                ->assertOk()
                ->json('data')
        )->pluck('tank_name')->all();

        $this->assertContains('Tank1', $names);
        $this->assertNotContains('Tank5', $names, 'A deleted tank must not reach the app.');
        $this->assertNotNull(Tank::find($kept->id));
    }

    /**
     * The crop closes on the way out.
     *
     * The farm's Total Feed Used counts OPEN batches only, so a hidden tank
     * whose batch stayed open would go on adding to the farm's figures while
     * being invisible — the worst of both.
     */
    public function test_deleting_a_tank_closes_its_crop_and_drops_it_from_the_farm_total(): void
    {
        $kept    = $this->makeTank('Tank1');
        $removed = $this->makeTank('Tank5');

        $this->assertSame(
            80.0,
            $this->farmerSeesTotals()['total'],
            'Two tanks at 40 kg each.'
        );

        $this->deleteTank($removed);

        $this->assertNull(
            TankBatch::openFor($removed->id),
            'The crop must close with the tank.'
        );
        $this->assertNotNull(TankBatch::openFor($kept->id), 'The other crop is untouched.');

        $this->assertSame(
            40.0,
            $this->farmerSeesTotals()['total'],
            'Only the surviving tank counts.'
        );
    }

    /**
     * The store is NOT handed back.
     *
     * That feed genuinely left the shed. Deleting the record of a pond does
     * not put food back in the bag, and a store that jumped up on a deletion
     * would be a far more confusing bug than the one being fixed.
     */
    public function test_deleting_a_tank_does_not_return_feed_to_the_store(): void
    {
        $tank = $this->makeTank('Tank5');

        $before = $this->farmerSeesTotals()['remaining'];

        $this->deleteTank($tank);

        $this->assertSame(
            $before,
            $this->farmerSeesTotals()['remaining'],
            'The shed holds what it holds.'
        );
    }

    // ── Restoring ───────────────────────────────────────────────────────────

    public function test_a_deleted_tank_can_be_restored_with_its_history(): void
    {
        $tank = $this->makeTank('Tank5');
        $this->deleteTank($tank);

        $this->actingAs($this->admin())
            ->post("/admin/farm-management/farms/{$this->farm->id}/tanks/{$tank->id}/restore")
            ->assertRedirect();

        $restored = Tank::find($tank->id);

        $this->assertNotNull($restored, 'It should be visible again.');
        $this->assertNull($restored->deleted_at);
        $this->assertSame('Tank5', $restored->tank_name);

        $this->assertSame(
            40.0,
            (float) Feed::where('tank_id', $tank->id)->sum('feed_quantity'),
            'Its feed came back with it.'
        );
    }

    /**
     * It comes back INACTIVE. Reopening the crop would restart something
     * nobody asked to restart and quietly move the farm's totals.
     */
    public function test_a_restored_tank_comes_back_inactive(): void
    {
        $tank = $this->makeTank('Tank5');
        $this->deleteTank($tank);

        $this->actingAs($this->admin())
            ->post("/admin/farm-management/farms/{$this->farm->id}/tanks/{$tank->id}/restore");

        $this->assertSame(0, (int) Tank::find($tank->id)->status);
        $this->assertNull(TankBatch::openFor($tank->id), 'No crop should be running.');
    }

    public function test_restoring_a_tank_that_is_not_deleted_is_refused(): void
    {
        $tank = $this->makeTank('Tank1');

        $this->actingAs($this->admin())
            ->post("/admin/farm-management/farms/{$this->farm->id}/tanks/{$tank->id}/restore")
            ->assertSessionHas('error');
    }

    // ── Permanent removal ───────────────────────────────────────────────────

    public function test_permanent_removal_requires_the_tank_to_be_deleted_first(): void
    {
        $tank = $this->makeTank('Tank1');

        $this->actingAs($this->admin())
            ->delete("/admin/farm-management/farms/{$this->farm->id}/tanks/{$tank->id}/force")
            ->assertSessionHas('error');

        $this->assertNotNull(
            Tank::withTrashed()->find($tank->id),
            'A live tank must not be destroyed by the permanent button.'
        );
    }

    public function test_permanent_removal_takes_the_feed_with_it(): void
    {
        $tank = $this->makeTank('Tank5');
        $this->deleteTank($tank);

        $this->actingAs($this->admin())
            ->delete("/admin/farm-management/farms/{$this->farm->id}/tanks/{$tank->id}/force")
            ->assertRedirect();

        $this->assertNull(Tank::withTrashed()->find($tank->id), 'Gone for good.');
        $this->assertSame(0, Feed::where('tank_id', $tank->id)->count());
        $this->assertSame(0, TankFeedHistory::where('tank_id', $tank->id)->count());
        $this->assertSame(0, TankBatch::where('tank_id', $tank->id)->count());
    }

    // ── The history records it ──────────────────────────────────────────────

    public function test_deleting_and_restoring_are_both_recorded(): void
    {
        $tank = $this->makeTank('Tank5');

        $this->deleteTank($tank);

        $this->actingAs($this->admin())
            ->post("/admin/farm-management/farms/{$this->farm->id}/tanks/{$tank->id}/restore");

        $descriptions = \App\Models\FarmActivity::forFarm($this->farm->id)
            ->orderBy('id')
            ->pluck('description')
            ->implode(' | ');

        $this->assertStringContainsString('Deleted Tank5', $descriptions);
        $this->assertStringContainsString('Restored Tank5', $descriptions);
    }
}
