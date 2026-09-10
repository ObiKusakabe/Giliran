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
        Schema::table('jadwal_briefing', function (Blueprint $table) {
            $table->boolean('is_notulen')->default(false)->after('status_konfirmasi')
                ->comment('Flag apakah personil ini ditunjuk sebagai notulensi (LRA dari perwakilan briefing)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jadwal_briefing', function (Blueprint $table) {
            $table->dropColumn('is_notulen');
        });
    }
};
