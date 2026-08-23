<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who currently has access to a farm, however they got it.
 *
 * Until now access lived on `farm_access_grants.redeemed_by` — one QR, one
 * person. That cannot express what the farm actually needs:
 *
 *   - an owner picking people by name or number and granting them directly,
 *     with no QR involved;
 *   - several people redeeming the SAME QR and all keeping access;
 *   - a manager passing access on to someone else, and so on down the chain.
 *
 * One row per person per farm. `grant_id` records the QR they came in through
 * when there was one; `granted_by` records who let them in, so a chain of
 * delegation can be followed back to the owner.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('farm_access_members')) {
            return;
        }

        Schema::create('farm_access_members', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('farm_id');
            $table->unsignedBigInteger('farmer_id');

            // The QR they redeemed, when they arrived that way.
            $table->unsignedBigInteger('grant_id')->nullable();

            // Who admitted them: the owner, or another member passing it on.
            $table->unsignedBigInteger('granted_by')->nullable();

            // The manager row carrying their name/phone, when one exists.
            $table->unsignedBigInteger('manager_id')->nullable();

            $table->enum('role', ['manager', 'partner'])->default('manager');

            $table->tinyInteger('view_access')->default(1);
            $table->tinyInteger('edit_access')->default(0);
            $table->tinyInteger('create_access')->default(0);
            $table->tinyInteger('delete_access')->default(0);

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            // One membership per person per farm; re-granting updates it.
            $table->unique(['farm_id', 'farmer_id'], 'farm_member_unique');
            $table->index('farmer_id');
            $table->index('grant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_access_members');
    }
};
