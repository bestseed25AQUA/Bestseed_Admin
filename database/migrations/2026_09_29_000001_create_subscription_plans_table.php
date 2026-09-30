<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The plan catalogue, moved out of config and into the database.
 *
 * It lived in `config/subscriptions.php` because the prices were fixed
 * commercial terms. They are not: admin now writes the packages — any number of
 * months, any number of farms, any price — so a new offer is a form, not a
 * deploy.
 *
 * `farm_limit` is the point of the change. A plan used to grant TIME, and while
 * it ran a farmer could create as many farms as they liked. Now it grants a
 * number of farms for a period, and a farmer who needs more buys another plan
 * alongside it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();

            // Stable identifier carried onto every subscription sold, so a row
            // still says which package it came from after the plan is renamed.
            $table->string('key')->unique();
            $table->string('label');

            $table->unsignedSmallInteger('months');

            // How many farms this package allows. Added to the free allowance,
            // and to any other plan the farmer holds at the same time.
            $table->unsignedSmallInteger('farm_limit');

            $table->decimal('amount', 10, 2)->default(0);

            // Days before expiry to warn, largest first, as JSON. A long plan
            // warns earlier: a 30-day warning on a one-month plan would fire
            // the day it was sold.
            $table->json('reminders')->nullable();

            // Retired rather than deleted: subscriptions already sold point at
            // it, and their history must keep reading correctly.
            $table->boolean('is_active')->default(true);

            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // Carry the existing catalogue over, so nothing that is already sold
        // loses the plan it points at.
        //
        // farm_limit is seeded to match the months — one farm per month, the
        // shape asked for — and can be edited in admin afterwards.
        $now = now();

        foreach ((array) config('subscriptions.plans', []) as $key => $plan) {
            $months = (int) ($plan['months'] ?? 1);

            DB::table('subscription_plans')->insert([
                'key'        => $key,
                'label'      => $plan['label'] ?? $key,
                'months'     => $months,
                'farm_limit' => max(1, $months),
                'amount'     => $plan['amount'] ?? 0,
                'reminders'  => json_encode($plan['reminders'] ?? [7, 3, 1]),
                'is_active'  => true,
                'sort_order' => $months,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
