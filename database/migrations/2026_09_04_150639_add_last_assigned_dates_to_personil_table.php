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
        Schema::table('personil', function (Blueprint $table) {
            $table->date('last_moderator_date')->nullable()->after('last_notulen_date')
                ->comment('Tanggal terakhir kali ditunjuk sebagai moderator (untuk LRA tiebreaker)');
            $table->date('last_doa_date')->nullable()->after('last_moderator_date')
                ->comment('Tanggal terakhir kali ditunjuk sebagai pembaca doa (untuk LRA tiebreaker)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personil', function (Blueprint $table) {
            $table->dropColumn(['last_moderator_date', 'last_doa_date']);
        });
    }
};
