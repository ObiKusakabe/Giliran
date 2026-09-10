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
        Schema::table('notulen_briefing', function (Blueprint $table) {
            $table->enum('jenis_kehadiran', ['wfo', 'wfh'])->default('wfo')->after('sesi')
                ->comment('WFO = created by notulen saat briefing fisik, WFH = created by any personil saat tim WFH (race condition)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notulen_briefing', function (Blueprint $table) {
            $table->dropColumn('jenis_kehadiran');
        });
    }
};
