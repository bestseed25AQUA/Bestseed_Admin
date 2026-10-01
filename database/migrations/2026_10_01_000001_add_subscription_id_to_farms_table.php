<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which package paid for each farm's CREATION.
 *
 * A package grants the right to create a number of farms during its term, and
 * that right is spent once. Without recording which farm spent it, a farmer
 * who buys a one-farm package and uses it would still be shown a free slot,
 * because the count would have nothing to subtract.
 *
 * Null means the free allowance, or a farm created under a package that has
 * since gone. Deliberately NOT re-attributed to a later package: buying a new
 * package has to grant a new farm, not pay again for one already standing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('farms', 'subscription_id')) {
            return;
        }

        Schema::table('farms', function (Blueprint $table) {
            $table->unsignedBigInteger('subscription_id')->nullable()->after('farmer_id');
            $table->index('subscription_id');
        });

        $this->backfill();
    }

    /**
     * Attribute every existing farm to the package that was live when it was
     * created, so capacity is right from the first request after deploy.
     */
    private function backfill(): void
    {
        $freeLimit = max(0, (int) config('subscriptions.free_farm_limit', 2));

        $subscriptions = DB::table('farm_subscriptions')
            ->select('id', 'farmer_id', 'starts_at', 'expires_at', 'farm_limit')
            ->orderBy('starts_at')
            ->get()
            ->groupBy('farmer_id');

        // Per farmer rather than in chunks of farms: the "how much of this
        // package is spent" tally has to span a whole farmer's history, and a
        // chunk boundary falling mid-farmer would reset it.
        $byFarmer = DB::table('farms')
            ->select('id', 'farmer_id', 'created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('farmer_id');

        foreach ($byFarmer as $farmerId => $farms) {
            $held = $subscriptions->get($farmerId);

            if (!$held) {
                continue;
            }

            $used = [];

            foreach ($farms->values() as $index => $farm) {
                // Inside the free allowance: nothing paid for it.
                if ($index < $freeLimit || !$farm->created_at) {
                    continue;
                }

                $createdOn = substr((string) $farm->created_at, 0, 10);

                foreach ($held as $subscription) {
                    if (($used[$subscription->id] ?? 0) >= (int) $subscription->farm_limit) {
                        continue;
                    }

                    $starts  = substr((string) $subscription->starts_at, 0, 10);
                    $expires = substr((string) $subscription->expires_at, 0, 10);

                    if ($createdOn >= $starts && $createdOn <= $expires) {
                        DB::table('farms')
                            ->where('id', $farm->id)
                            ->update(['subscription_id' => $subscription->id]);

                        $used[$subscription->id] = ($used[$subscription->id] ?? 0) + 1;
                        break;
                    }
                }
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('farms', 'subscription_id')) {
            return;
        }

        Schema::table('farms', function (Blueprint $table) {
            $table->dropIndex(['subscription_id']);
            $table->dropColumn('subscription_id');
        });
    }
};
