<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Farmer;
use App\Models\Feed;
use App\Models\Tank;
use App\Models\TankBatch;
use App\Models\TankFeedHistory;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\UsesDevelopmentDatabase;
use Tests\TestCase;

/**
 * Adding a tank with a past date and a "feed already used" figure.
 *
 * The reported complaint, in the client's own terms: a farm holding 5,000 kg
 * with 500 kg used. A ninth tank is added, stocked in the past, with 20 kg of
 * feed already given to it.
 *
 *   Total feed used  500 → 520.  The 20 kg WAS fed; it belongs in the total.
 *   Store            5,000 → 5,000.  That 20 kg never came out of this shed —
 *                    it was fed before the farm was being tracked here.
 *
 * The store only falls when the farmer records real feed by hand.
 */
class FarmStoreOnTankAddTest extends TestCase
{
    use UsesDevelopmentDatabase;

    private Farmer $farmer;
    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->beginDevelopmentDatabase();

        $this->farmer = Farmer::create([
            'first_name' => 'Store',
            'last_name'  => 'Test',
            'mobile'     => (string) random_int(7000000000, 9999999999),
            'role'       => 'farmer',
        ]);

        // A farm stocked a while back so a "past date" is genuinely in range.
        $this->farm = Farm::create([
            'farm_name'     => 'Store Test Farm',
            'farmer_id'     => $this->farmer->id,
            'status'        => 1,
            'stocking_date' => Carbon::today()->subDays(60)->toDateString(),
            // The COLUMN is total-ever-stocked. Remaining is this minus feed
            // actually recorded, so 5,500 − 500 shows the farmer 5,000.
            'store'         => 5500,
            'no_of_tanks'   => 8,
        ]);

        // Eight tanks, each on an open batch.
        for ($i = 1; $i <= 8; $i++) {
            $tank = Tank::create([
                'farm_id'       => $this->farm->id,
                'tank_name'     => "Tank{$i}",
                'status'        => 1,
                'stocking_date' => Carbon::today()->subDays(60)->toDateString(),
            ]);

            TankBatch::create([
                'tank_id'       => $tank->id,
                'farm_id'       => $this->farm->id,
                'batch_no'      => 1,
                'stocking_date' => Carbon::today()->subDays(60)->toDateString(),
                'started_at'    => now(),
            ]);
        }

        // 500 kg the farmer actually recorded: 8 tanks that HAVE drawn on the
        // shed. is_backfill = 0, which is what makes it count against store.
        $first = Tank::where('farm_id', $this->farm->id)->orderBy('id')->first();
        $batch = TankBatch::where('tank_id', $first->id)->first();

        $row = [
            'tank_id'       => $first->id,
            'farm_id'       => $this->farm->id,
            'batch_id'      => $batch->id,
            'feed_date'     => Carbon::today()->subDays(5)->toDateString(),
            'meals'         => 1,
            'feed_quantity' => 500,
            'is_backfill'   => 0,
        ];

        Feed::create($row);
        TankFeedHistory::create($row);
    }

    protected function tearDown(): void
    {
        $this->rollBackDevelopmentDatabase();
        parent::tearDown();
    }

    /** What the app's feed-store card shows. */
    private function card(): array
    {
        Sanctum::actingAs($this->farmer);

        $response = $this->getJson("/api/farmer/farm/feed-store/{$this->farm->id}")
            ->assertOk();

        $data = $response->json('data');

        return [
            'total'     => round((float) $data['total_feed_used'], 2),
            'remaining' => round((float) $data['remaining_store'], 2),
        ];
    }

    public function test_the_starting_position_is_what_the_client_describes(): void
    {
        $this->assertSame(
            ['total' => 500.0, 'remaining' => 5000.0],
            $this->card()
        );
    }

    public function test_adding_a_tank_with_past_feed_raises_the_total_and_leaves_the_store_alone(): void
    {
        $before = $this->card();

        Sanctum::actingAs($this->farmer);

        // Exactly what the edit screen posts: the store field carries the
        // REMAINING figure it was showing, untouched by the farmer.
        $this->postJson("/api/farmer/farms/{$this->farm->id}", [
            'farm_name'      => $this->farm->farm_name,
            'store'          => (string) $before['remaining'],
            'new_tanks_meta' => json_encode([[
                'stocking_date'    => Carbon::today()->subDays(10)->toDateString(),
                'feed_used_before' => 20,
            ]]),
        ])->assertOk();

        $after = $this->card();

        $this->assertSame(
            520.0,
            $after['total'],
            'The 20 kg was fed, so it belongs in Total Feed Used.'
        );

        $this->assertSame(
            5000.0,
            $after['remaining'],
            'That 20 kg never came out of this shed — the store must not move.'
        );
    }

    public function test_saving_the_farm_again_changes_nothing(): void
    {
        Sanctum::actingAs($this->farmer);

        // Three saves with the farmer touching nothing. A store that drifts on
        // a no-op save is the "why is it increasing" complaint.
        for ($i = 0; $i < 3; $i++) {
            $shown = $this->card();

            $this->postJson("/api/farmer/farms/{$this->farm->id}", [
                'farm_name' => $this->farm->farm_name,
                'store'     => (string) $shown['remaining'],
            ])->assertOk();

            $this->assertSame(
                $shown,
                $this->card(),
                "Save #{$i} moved figures the farmer never edited."
            );
        }
    }

    /**
     * The second half of the complaint: the farmer goes back and records what
     * was ACTUALLY fed on a past day the estimate already covered.
     *
     * The 20 kg estimate was spread across every past day. Recording the real
     * figure for one of those days must REPLACE that day's share, not stack on
     * top of it — otherwise the day is counted twice and Total Feed Used
     * climbs past anything that was ever fed.
     */
    public function test_real_feed_replaces_the_estimate_for_that_day(): void
    {
        Sanctum::actingAs($this->farmer);

        $stockedOn = Carbon::today()->subDays(10);

        $this->postJson("/api/farmer/farms/{$this->farm->id}", [
            'farm_name'      => $this->farm->farm_name,
            'store'          => '5000',
            'new_tanks_meta' => json_encode([[
                'stocking_date'    => $stockedOn->toDateString(),
                'feed_used_before' => 20,
            ]]),
        ])->assertOk();

        $newTank = Tank::where('farm_id', $this->farm->id)->orderByDesc('id')->first();

        $this->assertSame(520.0, $this->card()['total'], 'Estimate should be in the total.');

        // A day the estimate already generated rows for.
        $day = $stockedOn->copy()->addDays(3)->toDateString();

        $estimatedThatDay = (float) Feed::where('tank_id', $newTank->id)
            ->whereDate('feed_date', $day)
            ->sum('feed_quantity');

        $this->assertGreaterThan(0, $estimatedThatDay, 'Fixture assumption: that day was estimated.');

        // The farmer records what really happened on that day.
        $this->postJson('/api/farmer/tanks/add-todays-tanks-quantity', [
            'tank_id'       => $newTank->id,
            'meals'         => 1,
            'feed_quantity' => 6,
            'feed_date'     => $day,
        ])->assertStatus(201);

        $after = $this->card();

        // 500 recorded + 20 estimated, with that day's estimate replaced by 6.
        $this->assertSame(
            round(520 - $estimatedThatDay + 6, 2),
            $after['total'],
            'Recording a real figure must replace that day\'s estimate, not add to it.'
        );

        $this->assertSame(
            4994.0,
            $after['remaining'],
            'Only the 6 kg actually recorded comes off the shed.'
        );
    }

    /**
     * A second meal on a corrected day must not clear anything again.
     *
     * The estimate went with the first entry. If superseding ran per-entry
     * rather than per-day it would be a no-op here, but a bug that made it
     * touch hand-entered rows would show up as the first meal vanishing.
     */
    public function test_a_second_meal_on_a_corrected_day_keeps_the_first(): void
    {
        Sanctum::actingAs($this->farmer);

        $stockedOn = Carbon::today()->subDays(10);

        $this->postJson("/api/farmer/farms/{$this->farm->id}", [
            'farm_name'      => $this->farm->farm_name,
            'store'          => '5000',
            'new_tanks_meta' => json_encode([[
                'stocking_date'    => $stockedOn->toDateString(),
                'feed_used_before' => 20,
            ]]),
        ])->assertOk();

        $newTank = Tank::where('farm_id', $this->farm->id)->orderByDesc('id')->first();
        $day     = $stockedOn->copy()->addDays(3)->toDateString();

        foreach ([['meals' => 1, 'qty' => 6], ['meals' => 2, 'qty' => 4]] as $meal) {
            $this->postJson('/api/farmer/tanks/add-todays-tanks-quantity', [
                'tank_id'       => $newTank->id,
                'meals'         => $meal['meals'],
                'feed_quantity' => $meal['qty'],
                'feed_date'     => $day,
            ])->assertStatus(201);
        }

        $recordedThatDay = (float) Feed::where('tank_id', $newTank->id)
            ->whereDate('feed_date', $day)
            ->sum('feed_quantity');

        $this->assertSame(
            10.0,
            round($recordedThatDay, 2),
            'Both meals must survive, with no estimate left beside them.'
        );

        $this->assertSame(
            4990.0,
            $this->card()['remaining'],
            'Both real meals come off the shed; neither estimate does.'
        );
    }

    /**
     * Correcting past days must not quietly rewrite what the farmer typed.
     *
     * The figure used to be read back by summing the generated rows, which now
     * shrink as each day is corrected — so a farmer who entered 20 kg would
     * reopen the edit form and find a smaller number waiting to be saved over
     * the top of it.
     */
    public function test_the_entered_feed_already_used_figure_survives_corrections(): void
    {
        Sanctum::actingAs($this->farmer);

        $stockedOn = Carbon::today()->subDays(10);

        $this->postJson("/api/farmer/farms/{$this->farm->id}", [
            'farm_name'      => $this->farm->farm_name,
            'store'          => '5000',
            'new_tanks_meta' => json_encode([[
                'stocking_date'    => $stockedOn->toDateString(),
                'feed_used_before' => 20,
            ]]),
        ])->assertOk();

        $newTank = Tank::where('farm_id', $this->farm->id)->orderByDesc('id')->first();

        $shown = fn () => (float) collect(
            $this->getJson("/api/farmer/farms/{$this->farm->id}/tanks")->json('data')
        )->firstWhere('id', $newTank->id)['feed_used_before'];

        $this->assertSame(20.0, $shown(), 'The form should offer back what was entered.');

        // Correct two of the estimated days.
        foreach ([2, 4] as $offset) {
            $this->postJson('/api/farmer/tanks/add-todays-tanks-quantity', [
                'tank_id'       => $newTank->id,
                'meals'         => 1,
                'feed_quantity' => 3,
                'feed_date'     => $stockedOn->copy()->addDays($offset)->toDateString(),
            ])->assertStatus(201);
        }

        $this->assertSame(
            20.0,
            $shown(),
            'Correcting a past day must not change the figure the farmer entered.'
        );
    }

    public function test_recording_real_feed_afterwards_does_draw_on_the_store(): void
    {
        Sanctum::actingAs($this->farmer);

        $this->postJson("/api/farmer/farms/{$this->farm->id}", [
            'farm_name'      => $this->farm->farm_name,
            'store'          => '5000',
            'new_tanks_meta' => json_encode([[
                'stocking_date'    => Carbon::today()->subDays(10)->toDateString(),
                'feed_used_before' => 20,
            ]]),
        ])->assertOk();

        $newTank = Tank::where('farm_id', $this->farm->id)->orderByDesc('id')->first();

        // The farmer now records 30 kg by hand on that tank. THIS is feed that
        // leaves the shed.
        $this->postJson('/api/farmer/tanks/add-todays-tanks-quantity', [
            'tank_id'       => $newTank->id,
            'meals'         => 1,
            'feed_quantity' => 30,
        ])->assertStatus(201);

        $after = $this->card();

        $this->assertSame(550.0, $after['total'], '500 + 20 backfilled + 30 recorded.');
        $this->assertSame(4970.0, $after['remaining'], '5000 − 30 actually fed.');
    }
}
