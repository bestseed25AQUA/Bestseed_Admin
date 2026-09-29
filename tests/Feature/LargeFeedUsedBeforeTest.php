<?php

namespace Tests\Feature;

use App\Exceptions\FeedBackfillException;
use App\Http\Controllers\Api\User_apis\FarmController;
use App\Models\Farm;
use App\Models\Farmer;
use App\Models\Feed;
use App\Models\Tank;
use App\Models\TankBatch;
use App\Services\FeedBackfillService;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\UsesDevelopmentDatabase;
use Tests\TestCase;

/**
 * A nine-digit "feed already used" produced a tank with no history and a
 * total of zero.
 *
 * `tanks.total_feed_used` was decimal(10,2), capped at 99,999,999.99. Writing
 * more threw inside the backfill's transaction, rolling every generated row
 * back, and the exception was swallowed — so the farm reported success and the
 * tank showed 0 kgs.
 */
class LargeFeedUsedBeforeTest extends TestCase
{
    use UsesDevelopmentDatabase;

    private Farmer $farmer;
    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->beginDevelopmentDatabase();

        $this->farmer = Farmer::create([
            'first_name' => 'BigFeed',
            'last_name'  => 'Test',
            'mobile'     => (string) random_int(7000000000, 9999999999),
            'role'       => 'farmer',
        ]);

        $this->farm = Farm::create([
            'farm_name'     => 'Big Feed Farm',
            'farmer_id'     => $this->farmer->id,
            'status'        => 1,
            'stocking_date' => '2026-04-01',
            'store'         => 1000,
        ]);
    }

    protected function tearDown(): void
    {
        $this->rollBackDevelopmentDatabase();
        parent::tearDown();
    }

    private function makeTank(string $name = 'Tank4'): array
    {
        $tank = Tank::create([
            'farm_id'       => $this->farm->id,
            'tank_name'     => $name,
            'status'        => 1,
            'stocking_date' => '2026-04-01',
        ]);

        $batch = TankBatch::create([
            'tank_id'       => $tank->id,
            'farm_id'       => $this->farm->id,
            'batch_no'      => 1,
            'stocking_date' => '2026-04-01',
            'started_at'    => now(),
        ]);

        return [$tank, $batch];
    }

    /** The reported case, to the digit. */
    public function test_a_nine_digit_figure_is_recorded_in_full(): void
    {
        [$tank, $batch] = $this->makeTank();

        app(FeedBackfillService::class)
            ->applyForTank($this->farm, $tank->id, '2026-04-01', 500000000.0, $batch->id);

        $this->assertGreaterThan(
            0,
            Feed::where('tank_id', $tank->id)->count(),
            'The tank must end up with history, not an empty list.'
        );

        // The rows add up to EXACTLY what was entered — the split distributes
        // a remainder rather than losing it.
        $this->assertSame(
            500000000.00,
            round((float) Feed::where('tank_id', $tank->id)->sum('feed_quantity'), 2)
        );

        $this->assertSame(
            500000000.00,
            round((float) Tank::find($tank->id)->total_feed_used, 2),
            'This is the column that used to overflow.'
        );
    }

    /**
     * The old ceiling, one either side. decimal(10,2) stopped at
     * 99,999,999.99, which is what made eight digits work and nine fail.
     */
    public function test_figures_around_the_old_ceiling_all_work(): void
    {
        foreach ([99999999.0, 100000000.0, 123456789.0] as $amount) {
            [$tank, $batch] = $this->makeTank('Tank' . random_int(100, 999));

            app(FeedBackfillService::class)
                ->applyForTank($this->farm, $tank->id, '2026-04-01', $amount, $batch->id);

            $this->assertSame(
                round($amount, 2),
                round((float) Feed::where('tank_id', $tank->id)->sum('feed_quantity'), 2),
                "Failed at {$amount}."
            );
        }
    }

    /** Beyond the accepted range it now says so instead of going quiet. */
    public function test_an_impossible_figure_raises_rather_than_silently_doing_nothing(): void
    {
        [$tank, $batch] = $this->makeTank();

        // Past decimal(14,2) entirely.
        $this->expectException(FeedBackfillException::class);

        app(FeedBackfillService::class)
            ->applyForTank($this->farm, $tank->id, '2026-04-01', 9.9e13, $batch->id);
    }

    public function test_the_api_refuses_an_over_limit_figure_with_a_message(): void
    {
        Sanctum::actingAs($this->farmer);

        $response = $this->postJson('/api/farmer/create-farm', [
            'type'       => 'form',
            'farm_name'  => 'Rejected Farm ' . random_int(1000, 9999),
            'tanks'      => 1,
            'tanks_meta' => json_encode([[
                'stocking_date'    => '2026-04-01',
                'feed_used_before' => FarmController::MAX_FEED_USED_BEFORE + 1000,
            ]]),
        ]);

        $response->assertStatus(422);

        $this->assertStringContainsString(
            'too large',
            (string) $response->json('message'),
            'The farmer must be told the figure was rejected.'
        );
    }

    public function test_the_api_accepts_a_figure_at_the_limit(): void
    {
        Sanctum::actingAs($this->farmer);

        $name = 'Limit Farm ' . random_int(1000, 9999);

        $this->postJson('/api/farmer/create-farm', [
            'type'       => 'form',
            'farm_name'  => $name,
            'tanks'      => 1,
            'tanks_meta' => json_encode([[
                'stocking_date'    => Carbon::today()->subDays(30)->toDateString(),
                'feed_used_before' => FarmController::MAX_FEED_USED_BEFORE,
            ]]),
        ])->assertStatus(201);

        $created = Farm::where('farm_name', $name)->firstOrFail();
        $tank    = Tank::where('farm_id', $created->id)->firstOrFail();

        $this->assertSame(
            round(FarmController::MAX_FEED_USED_BEFORE, 2),
            round((float) Feed::where('tank_id', $tank->id)->sum('feed_quantity'), 2),
            'A figure at the stated limit must actually go in.'
        );
    }
}
