<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Farmer;
use App\Models\Tank;
use App\Models\TankBatch;
use App\Support\FeedLimits;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\UsesDevelopmentDatabase;
use Tests\TestCase;

/**
 * Errors reach the app as a plain message, never as a stack trace.
 */
class ApiErrorResponseTest extends TestCase
{
    use UsesDevelopmentDatabase;

    private Farmer $farmer;
    private Farm $farm;
    private Tank $tank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->beginDevelopmentDatabase();

        // The renderer only differs from Laravel's default when debug is on,
        // which is exactly the configuration that leaked the trace.
        config(['app.debug' => true]);

        $this->farmer = Farmer::create([
            'first_name' => 'Api',
            'last_name'  => 'Error',
            'mobile'     => (string) random_int(7000000000, 9999999999),
            'role'       => 'farmer',
        ]);

        $this->farm = Farm::create([
            'farm_name'     => 'Api Error Farm',
            'farmer_id'     => $this->farmer->id,
            'status'        => 1,
            'stocking_date' => '2026-04-01',
            'store'         => 5000,
        ]);

        $this->tank = Tank::create([
            'farm_id'       => $this->farm->id,
            'tank_name'     => 'Tank4',
            'status'        => 1,
            'stocking_date' => '2026-04-01',
        ]);

        TankBatch::create([
            'tank_id'       => $this->tank->id,
            'farm_id'       => $this->farm->id,
            'batch_no'      => 1,
            'stocking_date' => '2026-04-01',
            'started_at'    => now(),
        ]);
    }

    protected function tearDown(): void
    {
        $this->rollBackDevelopmentDatabase();
        parent::tearDown();
    }

    /** The exact payload from the report. */
    public function test_an_oversized_figure_on_an_existing_tank_is_refused_cleanly(): void
    {
        Sanctum::actingAs($this->farmer);

        $response = $this->postJson("/api/farmer/farms/{$this->farm->id}", [
            'farm_name'           => 'testing',
            'existing_tanks_meta' => json_encode([[
                'id'               => (string) $this->tank->id,
                'stocking_date'    => '2026-04-01',
                'feed_used_before' => '50000000000000',
            ]]),
        ]);

        $response->assertStatus(422)->assertJsonPath('status', false);

        $body = $response->getContent();

        foreach (['SQLSTATE', 'QueryException', 'C:\\\\Xamp', 'vendor\\\\laravel', '"trace"'] as $leak) {
            $this->assertStringNotContainsString(
                $leak,
                $body,
                "Response leaked internals: {$leak}"
            );
        }

        $this->assertStringContainsString('Feed already used', (string) $response->json('message'));
    }

    public function test_a_validation_failure_returns_message_and_errors(): void
    {
        Sanctum::actingAs($this->farmer);

        $this->postJson('/api/farmer/create-farm', ['type' => 'form'])
            ->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonStructure(['status', 'message', 'errors']);
    }

    public function test_an_unauthenticated_call_gets_401_json_not_a_redirect(): void
    {
        $this->getJson('/api/farmer/profile')
            ->assertStatus(401)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Your session has ended. Please sign in again.');
    }

    public function test_an_unknown_api_route_returns_json(): void
    {
        $this->getJson('/api/farmer/no-such-endpoint')
            ->assertStatus(404)
            ->assertJsonPath('status', false)
            ->assertJsonStructure(['status', 'message']);
    }

    public function test_the_limit_is_shared_rather_than_repeated(): void
    {
        Sanctum::actingAs($this->farmer);

        // One over the shared constant, on the NEW tank path this time.
        $this->postJson('/api/farmer/create-farm', [
            'type'       => 'form',
            'farm_name'  => 'Over Limit ' . random_int(1000, 9999),
            'tanks'      => 1,
            'tanks_meta' => json_encode([[
                'stocking_date'    => '2026-04-01',
                'feed_used_before' => FeedLimits::MAX_FEED_USED_BEFORE + 1,
            ]]),
        ])
            ->assertStatus(422)
            ->assertJsonPath('status', false);
    }
}
