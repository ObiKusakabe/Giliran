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
        // Add alasan_berhalangan column to jadwal_adzan_kitab table
        Schema::table('jadwal_adzan_kitab', function (Blueprint $table) {
            $table->text('alasan_berhalangan')->nullable()->after('status_konfirmasi');
        });

        // Add alasan_berhalangan column to jadwal_briefing table
        Schema::table('jadwal_briefing', function (Blueprint $table) {
            $table->text('alasan_berhalangan')->nullable()->after('status_konfirmasi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jadwal_adzan_kitab', function (Blueprint $table) {
            $table->dropColumn('alasan_berhalangan');
        });

        Schema::table('jadwal_briefing', function (Blueprint $table) {
            $table->dropColumn('alasan_berhalangan');
        });
    }
};
