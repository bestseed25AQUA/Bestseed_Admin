<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Carry existing redeemed grants into the members table.
 *
 * Access reads from `farm_access_members`, so anyone who had redeemed a QR
 * before that table existed had to be represented there or they would silently
 * lose the farm.
 *
 * Rewritten to use the query builder rather than the FarmAccessGrant and
 * FarmAccessMember models. Those models were deleted along with the QR flow,
 * and an old migration that imports a class which no longer exists is a fatal
 * error on any fresh `migrate` — this still has to run for databases that have
 * not applied it yet, long after the code around it is gone.
 *
 * A later migration drops `farm_access_grants`, so on a fresh install this
 * finds an empty table, copies nothing, and the drop follows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('farm_access_grants') || !Schema::hasTable('farm_access_members')) {
            return;
        }

        $grants = DB::table('farm_access_grants')
            ->whereNotNull('redeemed_at')
            ->whereNotNull('redeemed_by')
            ->orderBy('id')
            ->get();

        foreach ($grants as $grant) {
            $values = [
                'grant_id'      => $grant->id,
                'granted_by'    => $grant->issued_by,
                'manager_id'    => $grant->manager_id,
                'role'          => $grant->role,
                'view_access'   => $grant->view_access,
                'edit_access'   => $grant->edit_access,
                'create_access' => $grant->create_access,
                'delete_access' => $grant->delete_access,
                'expires_at'    => $grant->expires_at,
                'revoked_at'    => $grant->revoked_at,
                'updated_at'    => now(),
            ];

            $existing = DB::table('farm_access_members')
                ->where('farm_id', $grant->farm_id)
                ->where('farmer_id', $grant->redeemed_by)
                ->first();

            if ($existing) {
                DB::table('farm_access_members')->where('id', $existing->id)->update($values);
                continue;
            }

            DB::table('farm_access_members')->insert($values + [
                'farm_id'    => $grant->farm_id,
                'farmer_id'  => $grant->redeemed_by,
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // The members table is dropped by the migration that created it.
    }
};
