<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Expand ENUM to include all values temporarily
        DB::statement("ALTER TABLE seed_wanted_listings MODIFY COLUMN species ENUM('vannamei', 'tiger', 'shrimp', 'fish') NOT NULL DEFAULT 'vannamei'");

        // Migrate existing data
        DB::statement("UPDATE seed_wanted_listings SET species = 'shrimp' WHERE species = 'vannamei'");
        DB::statement("UPDATE seed_wanted_listings SET species = 'fish' WHERE species = 'tiger'");

        // Now restrict to new values only
        DB::statement("ALTER TABLE seed_wanted_listings MODIFY COLUMN species ENUM('shrimp', 'fish') NOT NULL DEFAULT 'shrimp'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE seed_wanted_listings MODIFY COLUMN species ENUM('vannamei', 'tiger') NOT NULL DEFAULT 'vannamei'");
    }
};
