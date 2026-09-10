<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change enum to add 'auto' option and make it default
        DB::statement("ALTER TABLE users MODIFY COLUMN ui_preference ENUM('auto', 'desktop', 'mobile') NOT NULL DEFAULT 'auto'");

        // Update existing users with 'desktop' to 'auto' (better default behavior)
        DB::table('users')
            ->where('ui_preference', 'desktop')
            ->update(['ui_preference' => 'auto']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum
        DB::statement("ALTER TABLE users MODIFY COLUMN ui_preference ENUM('desktop', 'mobile') NOT NULL DEFAULT 'desktop'");

        // Update 'auto' back to 'desktop'
        DB::table('users')
            ->where('ui_preference', 'auto')
            ->update(['ui_preference' => 'desktop']);
    }
};
