<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a sold subscription grants, recorded ON the subscription.
 *
 * `farm_limit` is a SNAPSHOT, not a lookup. Editing a plan afterwards — raising
 * the 3-month package from 3 farms to 5, say — must not silently change what
 * somebody already bought, and must not take farms away from a farmer who is
 * using them. Every row carries the terms it was sold under, exactly as
 * plan_label, amount and months already do.
 *
 * `plan_id` is kept alongside for reporting ("how many 3-month packages have we
 * sold"), and is nullable because rows predating the catalogue have no plan to
 * point at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id')->nullable()->after('farmer_id');

            // Default 1 rather than 0: a row written before this column existed
            // was sold under "unlimited while active", and 0 would silently
            // revoke a farmer's allowance at the moment of migrating.
            $table->unsignedSmallInteger('farm_limit')->default(1)->after('plan_key');

            $table->index('plan_id');
        });

        // Point existing rows at their plan, and give them the farm allowance
        // that plan now carries.
        foreach (DB::table('subscription_plans')->get() as $plan) {
            DB::table('farm_subscriptions')
                ->where('plan_key', $plan->key)
                ->update([
                    'plan_id'    => $plan->id,
                    'farm_limit' => $plan->farm_limit,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('farm_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['plan_id']);
            $table->dropColumn(['plan_id', 'farm_limit']);
        });
    }
};
