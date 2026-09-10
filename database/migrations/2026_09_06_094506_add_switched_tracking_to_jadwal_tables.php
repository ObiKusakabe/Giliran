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
        // Add switched tracking to jadwal_adzan_kitab
        Schema::table('jadwal_adzan_kitab', function (Blueprint $table) {
            $table->boolean('is_switched')->default(false)->after('status_konfirmasi');
            $table->unsignedBigInteger('original_personil_id')->nullable()->after('is_switched');
            $table->string('switch_reason')->nullable()->after('original_personil_id');
            $table->timestamp('switched_at')->nullable()->after('switch_reason');

            $table->foreign('original_personil_id')
                ->references('id')
                ->on('personil')
                ->nullOnDelete();
        });

        // Add switched tracking to jadwal_briefing
        Schema::table('jadwal_briefing', function (Blueprint $table) {
            $table->boolean('is_switched')->default(false)->after('status_konfirmasi');
            $table->unsignedBigInteger('original_personil_id')->nullable()->after('is_switched');
            $table->string('switch_reason')->nullable()->after('original_personil_id');
            $table->timestamp('switched_at')->nullable()->after('switch_reason');

            $table->foreign('original_personil_id')
                ->references('id')
                ->on('personil')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jadwal_adzan_kitab', function (Blueprint $table) {
            $table->dropForeign(['original_personil_id']);
            $table->dropColumn(['is_switched', 'original_personil_id', 'switch_reason', 'switched_at']);
        });

        Schema::table('jadwal_briefing', function (Blueprint $table) {
            $table->dropForeign(['original_personil_id']);
            $table->dropColumn(['is_switched', 'original_personil_id', 'switch_reason', 'switched_at']);
        });
    }
};
