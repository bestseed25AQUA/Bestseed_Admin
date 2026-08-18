<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per QR/PIN access grant issued by a farmer.
     *
     * The QR encodes only `token`; every permission, the expiry and the PIN are
     * looked up server-side so a scanned code can be revoked or expired without
     * the holder being able to tamper with it.
     */
    public function up(): void
    {
        if (Schema::hasTable('farm_access_grants')) {
            return;
        }

        Schema::create('farm_access_grants', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('farm_id');
            $table->unsignedBigInteger('issued_by')->nullable(); // farmer_id
            $table->unsignedBigInteger('manager_id')->nullable(); // filled on redeem

            // Opaque value embedded in the QR image.
            $table->string('token', 64)->unique();

            // 4-digit PIN, encrypted at rest (not hashed): the issuing farmer
            // re-reads it on the QR list screen to re-share, so it has to be
            // recoverable. Only ever decrypted for that farmer.
            $table->text('pin_secret');

            $table->enum('role', ['manager', 'partner'])->default('manager');

            $table->tinyInteger('view_access')->default(0);
            $table->tinyInteger('edit_access')->default(0);
            $table->tinyInteger('create_access')->default(0);
            $table->tinyInteger('delete_access')->default(0);

            $table->unsignedInteger('duration_days')->default(30);
            $table->timestamp('expires_at')->nullable();

            // Redemption + revocation lifecycle.
            $table->timestamp('redeemed_at')->nullable();
            $table->unsignedBigInteger('redeemed_by')->nullable(); // farmer_id of scanner
            $table->timestamp('revoked_at')->nullable();

            // Throttles brute-forcing the 4-digit PIN.
            $table->unsignedInteger('pin_attempts')->default(0);

            $table->timestamps();

            $table->index('farm_id');
            $table->index('manager_id');
            $table->index('redeemed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_access_grants');
    }
};
