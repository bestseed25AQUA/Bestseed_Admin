<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Farms become individually licensed.
 *
 * Until now a farmer had an ALLOWANCE — free slots plus whatever packages they
 * held — and any farm counted against the pool. That cannot express what the
 * business actually sells: a one-month, one-farm package covers THAT farm for
 * THAT month. Renewing it must bring that farm back and no other, and a farm
 * whose cover has lapsed is locked rather than deleted.
 *
 * So each farm now carries what covers it:
 *
 *   covered_by_subscription_id  the package paying for it, or
 *   free_until                  the day its free period ends
 *
 * `free_until` NULL means "never expires". Every farm that exists today is set
 * that way on purpose: they were created under the old unlimited free plan, and
 * a migration must not lock a farmer out of work they have already done.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            $table->unsignedBigInteger('covered_by_subscription_id')->nullable()->after('farmer_id');

            // NULL = no expiry. See the note above on grandfathering.
            $table->date('free_until')->nullable()->after('covered_by_subscription_id');

            $table->index('covered_by_subscription_id');
            $table->index('free_until');
        });

        // Requests a farmer sends from the subscription screen when nobody
        // answers the phone. Admin works them from a tab of their own.
        Schema::create('subscription_requests', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('farmer_id');

            // Which farm it is about, when the farmer asked from a locked one.
            // Nullable: a request to create their first extra farm has none.
            $table->unsignedBigInteger('farm_id')->nullable();

            // The package they asked for, if they picked one.
            $table->unsignedBigInteger('plan_id')->nullable();

            $table->string('plan_label')->nullable();
            $table->text('message')->nullable();

            // pending → contacted → done | declined
            $table->string('status')->default('pending');

            $table->text('admin_note')->nullable();
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->timestamp('handled_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('farmer_id');
        });

        // Announcements can target one screen, the way banners already do — so
        // the farm management popup is an announcement rather than a banner
        // pretending to be one.
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('screen')->nullable()->after('audience');
            $table->index('screen');
        });

        // Settings admin edits. Seeded with what the app did before, so nothing
        // changes until somebody chooses otherwise.
        $now = now();

        $settings = [
            // How many farms a farmer may create without paying. 0 is allowed:
            // a deployment that sells from the first farm sets it there.
            ['farm_free_count', '2'],

            // How long those free farms last, in months. 0 = never expires,
            // which is what every existing farm was created under.
            ['farm_free_months', '3'],

            // Shown on the farm management screen when the farmer has no farms
            // yet. Blank hides it.
            ['farm_demo_video_url', ''],
            ['farm_demo_video_title', 'How Farm Management works'],
        ];

        foreach ($settings as [$key, $value]) {
            if (!DB::table('app_configs')->where('config_key', $key)->exists()) {
                DB::table('app_configs')->insert([
                    'config_key'   => $key,
                    'config_value' => $value,
                    'config_group' => 'farm_management',
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            $table->dropIndex(['covered_by_subscription_id']);
            $table->dropIndex(['free_until']);
            $table->dropColumn(['covered_by_subscription_id', 'free_until']);
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex(['screen']);
            $table->dropColumn('screen');
        });

        Schema::dropIfExists('subscription_requests');

        DB::table('app_configs')->whereIn('config_key', [
            'farm_free_count', 'farm_free_months',
            'farm_demo_video_url', 'farm_demo_video_title',
        ])->delete();
    }
};
