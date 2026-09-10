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
            $table->date('last_notulen_date')->nullable()->after('status')
                ->comment('Tanggal terakhir kali ditunjuk sebagai notulensi (untuk LRA tiebreaker)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personil', function (Blueprint $table) {
            $table->dropColumn('last_notulen_date');
        });
    }
};
