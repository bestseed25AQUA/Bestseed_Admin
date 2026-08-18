<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scope managers/partners to a farm.
     *
     * `phone` was globally unique, which prevented the same person from being a
     * manager on two different farms. Uniqueness becomes (farm_id, phone).
     * Existing rows keep farm_id = null, so they stay global until backfilled.
     */
    public function up(): void
    {
        Schema::table('managers', function (Blueprint $table) {
            if (!Schema::hasColumn('managers', 'farm_id')) {
                $table->unsignedBigInteger('farm_id')->nullable()->after('id');
                $table->index('farm_id');
            }
        });

        // Swap the global phone unique index for a per-farm one.
        try {
            Schema::table('managers', function (Blueprint $table) {
                $table->dropUnique('managers_phone_unique');
            });
        } catch (\Throwable $e) {
            // Index already dropped or named differently — safe to continue.
        }

        try {
            Schema::table('managers', function (Blueprint $table) {
                $table->unique(['farm_id', 'phone'], 'managers_farm_phone_unique');
            });
        } catch (\Throwable $e) {
            // Composite index already present.
        }
    }

    public function down(): void
    {
        try {
            Schema::table('managers', function (Blueprint $table) {
                $table->dropUnique('managers_farm_phone_unique');
            });
        } catch (\Throwable $e) {
            // Nothing to drop.
        }

        Schema::table('managers', function (Blueprint $table) {
            if (Schema::hasColumn('managers', 'farm_id')) {
                $table->dropIndex(['farm_id']);
                $table->dropColumn('farm_id');
            }
        });

        try {
            Schema::table('managers', function (Blueprint $table) {
                $table->unique('phone', 'managers_phone_unique');
            });
        } catch (\Throwable $e) {
            // Duplicate phones may exist by now; leave unconstrained.
        }
    }
};
