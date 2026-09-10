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
            $table->foreignId('moderator_id')->nullable()->after('personil_id')->constrained('personil')->nullOnDelete()
                ->comment('Moderator briefing (LRA assigned)');
            $table->foreignId('doa_id')->nullable()->after('moderator_id')->constrained('personil')->nullOnDelete()
                ->comment('Pembaca doa briefing (LRA assigned)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jadwal_briefing', function (Blueprint $table) {
            $table->dropForeign(['moderator_id']);
            $table->dropForeign(['doa_id']);
            $table->dropColumn(['moderator_id', 'doa_id']);
        });
    }
};
