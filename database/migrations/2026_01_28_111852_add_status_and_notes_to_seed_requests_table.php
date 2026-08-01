<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('seed_requests', 'hatchery_name')) {
            Schema::table('seed_requests', function (Blueprint $table) {
                $table->string('hatchery_name')->nullable()->after('hatchery_id');
            });
        }
        if (!Schema::hasColumn('seed_requests', 'status')) {
            Schema::table('seed_requests', function (Blueprint $table) {
                $table->string('status')->default('pending')->after('packing_date');
            });
        }
        if (!Schema::hasColumn('seed_requests', 'admin_notes')) {
            Schema::table('seed_requests', function (Blueprint $table) {
                $table->text('admin_notes')->nullable()->after('status');
            });
        }
        if (!Schema::hasColumn('seed_requests', 'handled_by')) {
            Schema::table('seed_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('handled_by')->nullable()->after('admin_notes');
            });
        }
        if (!Schema::hasColumn('seed_requests', 'handled_at')) {
            Schema::table('seed_requests', function (Blueprint $table) {
                $table->timestamp('handled_at')->nullable()->after('handled_by');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seed_requests', function (Blueprint $table) {
            $columns = ['hatchery_name', 'status', 'admin_notes', 'handled_by', 'handled_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('seed_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
