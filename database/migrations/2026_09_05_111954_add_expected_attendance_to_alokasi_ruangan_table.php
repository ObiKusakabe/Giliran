<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FASE 4.4: Add expected_attendance for capacity utilization tracking.
     */
    public function up(): void
    {
        Schema::table('alokasi_ruangan', function (Blueprint $table) {
            $table->unsignedInteger('expected_attendance')->default(0)->after('tanggal')
                ->comment('Expected number of attendees (active personil count)');
        });
    }

    public function down(): void
    {
        Schema::table('alokasi_ruangan', function (Blueprint $table) {
            $table->dropColumn('expected_attendance');
        });
    }
};
