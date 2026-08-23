<?php

use App\Models\FarmAccessGrant;
use App\Models\FarmAccessMember;
use Illuminate\Database\Migrations\Migration;

/**
 * Carry existing redeemed grants into the members table.
 *
 * Access now reads from `farm_access_members`, so anyone who had redeemed a QR
 * before this deploy must be represented there or they would silently lose the
 * farm. Their grant stays as the audit record of how they got in.
 */
return new class extends Migration
{
    public function up(): void
    {
        $grants = FarmAccessGrant::whereNotNull('redeemed_at')
            ->whereNotNull('redeemed_by')
            ->orderBy('id')
            ->get();

        foreach ($grants as $grant) {
            FarmAccessMember::updateOrCreate(
                [
                    'farm_id'   => $grant->farm_id,
                    'farmer_id' => $grant->redeemed_by,
                ],
                [
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
                ]
            );
        }
    }

    public function down(): void
    {
        // The members table is dropped by the migration that created it.
    }
};
