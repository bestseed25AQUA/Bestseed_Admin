<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A farmer's paid allowance to create farms beyond the free limit.
 *
 * The farmer never pays in the app. They call the Farm Management helpline, an
 * admin takes the payment however they take it, and records the plan here. This
 * row IS the subscription: nothing else grants the allowance.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('farm_subscriptions')) {
            return;
        }

        Schema::create('farm_subscriptions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('farmer_id');

            // A key from config('subscriptions.plans'), e.g. 'month_3'.
            $table->string('plan_key', 40);

            // The label and price AS SOLD, copied rather than looked up.
            // Prices change; a receipt must not change with them.
            $table->string('plan_label', 60);
            $table->decimal('amount', 10, 2)->default(0);
            $table->unsignedSmallInteger('months')->default(1);

            $table->date('starts_at');
            $table->date('expires_at');

            // Cancelled by an admin: a refund, or a row entered by mistake.
            // Expiry is NOT a status — it is read from expires_at, so a
            // subscription cannot sit stale as "active" because nothing ran.
            $table->timestamp('cancelled_at')->nullable();

            // Free text: payment reference, who called, anything the admin
            // needs to recognise the row later.
            $table->text('notes')->nullable();

            // The admin who recorded it, for questions afterwards.
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            $table->index('farmer_id');
            $table->index('expires_at');
            $table->index(['farmer_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_subscriptions');
    }
};
