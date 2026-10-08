<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What covered a farm, and for how long — every term it has ever had.
 *
 * `farms.covered_by_subscription_id` holds only what covers it TODAY. It is
 * overwritten on renewal, so the term just replaced stops pointing at the farm
 * and the farm's history loses it: a farmer who has renewed three times shows
 * one term. This table is the record the pointer cannot be.
 *
 * One row per period. The free trial is a period like any other, so a farm's
 * whole life reads from one place.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('farm_cover_periods')) {
            return;
        }

        Schema::create('farm_cover_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('farm_id');
            // Null for the free trial, which has no subscription behind it.
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->boolean('is_free')->default(false);
            $table->date('started_on')->nullable();
            // Null while the period is the farm's current cover.
            $table->date('ended_on')->nullable();
            $table->timestamps();

            $table->index(['farm_id', 'started_on']);
            $table->index('subscription_id');
        });

        $this->backfill();
    }

    /**
     * What can be reconstructed: each farm's free trial, and whatever covers
     * it right now.
     *
     * Terms already replaced before this table existed cannot be recovered —
     * the pointer that named them was overwritten, and guessing from dates
     * would invent history rather than record it.
     */
    private function backfill(): void
    {
        $rows = [];
        $now  = now();

        $farms = DB::table('farms')
            ->select('id', 'created_at', 'free_until', 'free_ended_on', 'took_free_slot', 'covered_by_subscription_id')
            ->get();

        $subs = DB::table('farm_subscriptions')
            ->select('id', 'starts_at', 'expires_at')
            ->get()
            ->keyBy('id');

        foreach ($farms as $farm) {
            if ($farm->took_free_slot) {
                $rows[] = [
                    'farm_id'         => $farm->id,
                    'subscription_id' => null,
                    'is_free'         => true,
                    'started_on'      => $farm->created_at
                        ? substr((string) $farm->created_at, 0, 10)
                        : null,
                    'ended_on'        => $farm->free_ended_on ?? $farm->free_until,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }

            if ($farm->covered_by_subscription_id
                && ($sub = $subs->get($farm->covered_by_subscription_id))) {
                $rows[] = [
                    'farm_id'         => $farm->id,
                    'subscription_id' => $sub->id,
                    'is_free'         => false,
                    'started_on'      => $sub->starts_at,
                    // Open: this is what covers the farm today.
                    'ended_on'        => null,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('farm_cover_periods')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_cover_periods');
    }
};
