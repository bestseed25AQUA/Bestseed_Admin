<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Farm access no longer expires — it lasts until someone revokes it.
 *
 * Rows written before that change still carry an expires_at, and leaving them
 * would mean access quietly ending on a date nobody can see or edit any more,
 * with no way in either app to extend it. Clearing the dates makes the stored
 * data agree with the behaviour.
 *
 * Revoked rows are left alone: revoking is still how access ends, and their
 * expiry is part of the record of what was granted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('farm_access_members')) {
            return;
        }

        DB::table('farm_access_members')
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->update(['expires_at' => null]);
    }

    public function down(): void
    {
        // The dates are gone; there is nothing faithful to restore. Access
        // simply stays live, which is what the column now means.
    }
};
