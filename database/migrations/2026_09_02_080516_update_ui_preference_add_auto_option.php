<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For SQLite compatibility, we need to recreate the column
        Schema::table('users', function (Blueprint $table) {
            $table->enum('ui_preference_new', ['auto', 'desktop', 'mobile'])
                ->default('auto')
                ->after('ui_preference');
        });

        // Copy data from old column to new column (auto-convert desktop -> auto)
        DB::table('users')->update([
            'ui_preference_new' => DB::raw("CASE WHEN ui_preference = 'desktop' THEN 'auto' ELSE ui_preference END"),
        ]);

        // Drop old column and rename new one
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ui_preference');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('ui_preference_new', 'ui_preference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate old column
        Schema::table('users', function (Blueprint $table) {
            $table->enum('ui_preference_old', ['desktop', 'mobile'])
                ->default('desktop')
                ->after('ui_preference');
        });

        // Copy data back (auto -> desktop)
        DB::table('users')->update([
            'ui_preference_old' => DB::raw("CASE WHEN ui_preference = 'auto' THEN 'desktop' ELSE ui_preference END"),
        ]);

        // Drop new column and rename old one back
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ui_preference');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('ui_preference_old', 'ui_preference');
        });
    }
};
