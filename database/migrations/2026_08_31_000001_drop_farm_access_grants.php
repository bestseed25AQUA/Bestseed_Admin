<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the QR + PIN access codes.
 *
 * Access is now given by picking a person directly, which writes a row to
 * `farm_access_members`. The grants table held the codes and the (recoverable,
 * encrypted) PINs behind them; with the flow gone it is dead weight, and the
 * stored PINs are a secret we no longer have any reason to keep.
 *
 * SAFE FOR EXISTING MEMBERS. `farm_access_members.grant_id` was only ever a
 * label — FarmAccessService decides access from `revoked_at` and `expires_at`
 * on the membership row and never consults the grant. There is no foreign key
 * on the column either, so nothing cascades. People who originally arrived by
 * scanning a QR keep exactly the access they hold.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Clear the link first, so the column is meaningless before the table
        // it pointed at disappears rather than after.
        if (Schema::hasColumn('farm_access_members', 'grant_id')) {
            DB::table('farm_access_members')
                ->whereNotNull('grant_id')
                ->update(['grant_id' => null]);

            Schema::table('farm_access_members', function (Blueprint $table) {
                $table->dropIndex('farm_access_members_grant_id_index');
                $table->dropColumn('grant_id');
            });
        }

        Schema::dropIfExists('farm_access_grants');
    }

    /**
     * Recreates the table's shape so a rollback leaves a working schema.
     *
     * The codes themselves are NOT recoverable — they are deleted, along with
     * their PINs. Rolling back gives you an empty table and a null grant_id on
     * every membership, which is the honest outcome: the data is gone.
     */
    public function down(): void
    {
        if (!Schema::hasTable('farm_access_grants')) {
            Schema::create('farm_access_grants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('farm_id');
                $table->unsignedBigInteger('issued_by')->nullable();
                $table->unsignedBigInteger('manager_id')->nullable();
                $table->string('token', 64)->unique();
                $table->text('pin_secret');
                $table->enum('role', ['manager', 'partner'])->default('manager');
                $table->tinyInteger('view_access')->default(0);
                $table->tinyInteger('edit_access')->default(0);
                $table->tinyInteger('create_access')->default(0);
                $table->tinyInteger('delete_access')->default(0);
                $table->unsignedInteger('duration_days')->default(30);
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('redeemed_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->unsignedTinyInteger('pin_attempts')->default(0);
                $table->timestamps();

                $table->index('farm_id');
                $table->index('manager_id');
            });
        }

        if (!Schema::hasColumn('farm_access_members', 'grant_id')) {
            Schema::table('farm_access_members', function (Blueprint $table) {
                $table->unsignedBigInteger('grant_id')->nullable()->after('farmer_id');
                $table->index('grant_id');
            });
        }
    }
};
