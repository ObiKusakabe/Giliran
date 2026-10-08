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
        // Hapus jadwal wfo untuk hari sabtu
        DB::table('jadwal_wfo')->where('hari', 'sabtu')->delete();
        
        // Hapus alokasi ruangan dan jadwal_adzan_kitab serta jadwal_briefing
        // yang jatuh pada hari sabtu (DAYOFWEEK = 7 di MySQL, isODayOfWeek = 6)
        DB::table('jadwal_adzan_kitab')->whereRaw('DAYOFWEEK(tanggal) = 7')->delete();
        DB::table('jadwal_briefing')->whereRaw('DAYOFWEEK(tanggal) = 7')->delete();
        DB::table('alokasi_ruangan')->whereRaw('DAYOFWEEK(tanggal) = 7')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot revert
    }
};
