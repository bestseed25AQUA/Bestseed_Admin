<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Farmer;
use App\Models\Feed;
use App\Models\Tank;
use App\Models\TankBatch;
use App\Models\User;
use App\Services\TankBatchService;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\UsesDevelopmentDatabase;
use Tests\TestCase;

/**
 * Activating a tank must do the same thing wherever it is done.
 *
 * Reported from the admin panel: a tank was activated there and it never asked
 * for a stocking date, so the new crop inherited whatever date the tank was
 * already carrying and got no history for the days that had already passed.
 * The identical action in the app asked for both and generated both.
 *
 * The cause was two implementations: the app wrote the whole sequence out in
 * its controller, admin called [TankBatchService], and the service only did
 * the first step. It now does the whole job and both callers go through it.
 */
class TankActivationParityTest extends TestCase
{
    use UsesDevelopmentDatabase;

    private Farmer $farmer;
    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->beginDevelopmentDatabase();

        $this->farmer = Farmer::create([
            'first_name' => 'Activation',
            'last_name'  => 'Test',
            'mobile'     => (string) random_int(7000000000, 9999999999),
            'role'       => 'farmer',
        ]);

        $this->farm = Farm::create([
            'farm_name'     => 'Activation Test Farm',
            'farmer_id'     => $this->farmer->id,
            'status'        => 1,
            'stocking_date' => Carbon::today()->subDays(200)->toDateString(),
            'store'         => 5000,
        ]);
    }

    protected function tearDown(): void
    {
        $this->rollBackDevelopmentDatabase();
        parent::tearDown();
    }

    /**
     * An inactive tank carrying a stale date from a crop that finished long
     * ago — exactly what a tank being re-activated looks like.
     */
    private function dormantTank(): Tank
    {
        $tank = Tank::create([
            'farm_id'       => $this->farm->id,
            'tank_name'     => 'Tank' . random_int(100, 999),
            'status'        => 0,
            'stocking_date' => Carbon::today()->subDays(200)->toDateString(),
        ]);

        // A finished crop behind it.
        TankBatch::create([
            'tank_id'       => $tank->id,
            'farm_id'       => $this->farm->id,
            'batch_no'      => 1,
            'stocking_date' => Carbon::today()->subDays(200)->toDateString(),
            'started_at'    => Carbon::today()->subDays(200),
            'ended_at'      => Carbon::today()->subDays(30),
        ]);

        return $tank;
    }

    /** What a freshly started crop should look like, whoever started it. */
    private function assertCropStarted(Tank $tank, string $stockedOn, float $usedBefore): void
    {
        $tank->refresh();

        $this->assertSame(1, (int) $tank->status, 'Tank should be active.');

        $this->assertSame(
            $stockedOn,
            Carbon::parse($tank->stocking_date)->toDateString(),
            'The tank must take the new crop\'s date, not keep the old one.'
        );

        $batch = TankBatch::openFor($tank->id);

        $this->assertNotNull($batch, 'An open batch is what makes the tank feedable.');
        $this->assertSame(2, (int) $batch->batch_no, 'It is the tank\'s second crop.');
        $this->assertSame(
            $stockedOn,
            $batch->stocking_date->toDateString(),
            'The batch carries the date it was stocked on.'
        );
        $this->assertSame(
            $usedBefore,
            (float) $batch->feed_used_before,
            'The figure entered is stored so the edit form can offer it back.'
        );

        // The history for the days already passed.
        $backfilled = (float) Feed::where('tank_id', $tank->id)
            ->where('batch_id', $batch->id)
            ->where('is_backfill', 1)
            ->sum('feed_quantity');

        $this->assertSame(
            $usedBefore,
            round($backfilled, 2),
            'The whole "already used" figure must reach the tank as history.'
        );
    }

    public function test_the_app_asks_and_generates_history(): void
    {
        $tank      = $this->dormantTank();
        $stockedOn = Carbon::today()->subDays(10)->toDateString();

        Sanctum::actingAs($this->farmer);

        $this->postJson('/api/farmer/tank/status', [
            'tank_id'          => $tank->id,
            'status'           => 1,
            'stocking_date'    => $stockedOn,
            'feed_used_before' => 20,
        ])->assertOk();

        $this->assertCropStarted($tank, $stockedOn, 20.0);
    }

    /**
     * The reported bug. Before the fix this left the tank on its 200-day-old
     * date with no backfilled rows at all.
     */
    public function test_the_admin_panel_does_exactly_the_same(): void
    {
        $tank      = $this->dormantTank();
        $stockedOn = Carbon::today()->subDays(10)->toDateString();

        $admin = User::first();

        if (!$admin) {
            $this->markTestSkipped('No admin user to act as.');
        }

        $this->actingAs($admin)
            ->post(
                "/admin/farm-management/farms/{$this->farm->id}/tanks/{$tank->id}/toggle-status",
                ['stocking_date' => $stockedOn, 'feed_used_before' => 20]
            )
            ->assertRedirect();

        $this->assertCropStarted($tank, $stockedOn, 20.0);
    }

    public function test_the_admin_panel_refuses_to_start_a_crop_with_no_date(): void
    {
        $tank  = $this->dormantTank();
        $admin = User::first();

        if (!$admin) {
            $this->markTestSkipped('No admin user to act as.');
        }

        $this->actingAs($admin)
            ->post("/admin/farm-management/farms/{$this->farm->id}/tanks/{$tank->id}/toggle-status", [])
            ->assertSessionHasErrors('stocking_date');

        $this->assertSame(0, (int) $tank->refresh()->status, 'The tank must stay inactive.');
        $this->assertNull(TankBatch::openFor($tank->id), 'No crop should have been opened.');
    }

    public function test_a_future_stocking_date_is_refused(): void
    {
        $tank  = $this->dormantTank();
        $admin = User::first();

        if (!$admin) {
            $this->markTestSkipped('No admin user to act as.');
        }

        $this->actingAs($admin)
            ->post(
                "/admin/farm-management/farms/{$this->farm->id}/tanks/{$tank->id}/toggle-status",
                ['stocking_date' => Carbon::tomorrow()->toDateString()]
            )
            ->assertSessionHasErrors('stocking_date');
    }

    public function test_starting_a_crop_today_generates_no_history(): void
    {
        $tank = $this->dormantTank();

        app(TankBatchService::class)->setStatus(
            $tank,
            1,
            Carbon::today()->toDateString(),
            0
        );

        $batch = TankBatch::openFor($tank->id);

        $this->assertNotNull($batch);
        $this->assertSame(
            0,
            Feed::where('tank_id', $tank->id)->where('batch_id', $batch->id)->count(),
            'A crop stocked today has no past to fill in.'
        );
    }

    /**
     * Harvesting is the other end of the same action and must keep working
     * through the shared service, including the weight when one is given.
     */
    public function test_harvesting_closes_the_crop_and_records_the_weight(): void
    {
        $tank = $this->dormantTank();

        app(TankBatchService::class)->setStatus($tank, 1, Carbon::today()->subDays(5)->toDateString());

        $batch = TankBatch::openFor($tank->id);
        $this->assertNotNull($batch);

        app(TankBatchService::class)->setStatus($tank, 0, null, 0, 450.5);

        $this->assertSame(0, (int) $tank->refresh()->status);
        $this->assertNull(TankBatch::openFor($tank->id), 'The crop should be closed.');

        $batch->refresh();
        $this->assertNotNull($batch->ended_at);
        $this->assertSame(450.5, (float) $batch->harvest_quantity);
    }

    public function test_harvesting_without_a_weight_leaves_an_earlier_one_alone(): void
    {
        $tank = $this->dormantTank();

        app(TankBatchService::class)->setStatus($tank, 1, Carbon::today()->subDays(5)->toDateString());
        $batch = TankBatch::openFor($tank->id);

        app(TankBatchService::class)->setStatus($tank, 0, null, 0, 300.0);
        $this->assertSame(300.0, (float) $batch->refresh()->harvest_quantity);

        // Reopen and close again with no figure: null means "not weighed",
        // which must not wipe what was recorded before.
        app(TankBatchService::class)->setStatus($tank, 1, Carbon::today()->subDays(2)->toDateString());
        app(TankBatchService::class)->setStatus($tank, 0);

        $this->assertSame(
            300.0,
            (float) $batch->refresh()->harvest_quantity,
            'A blank weight must not erase a recorded one.'
        );
    }
}
