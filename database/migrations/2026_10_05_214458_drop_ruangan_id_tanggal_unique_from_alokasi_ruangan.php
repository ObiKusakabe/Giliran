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
        Schema::table('alokasi_ruangan', function (Blueprint $table) {
            // Add regular index to support the foreign key first
            $table->index('ruangan_id');
            // Drop unique constraint that prevents multiple teams in one room per day
            $table->dropUnique('alokasi_ruangan_ruangan_id_tanggal_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alokasi_ruangan', function (Blueprint $table) {
            // Restore constraint
            $table->unique(['ruangan_id', 'tanggal']);
            // Drop the regular index
            $table->dropIndex(['ruangan_id']);
        });
    }
};
