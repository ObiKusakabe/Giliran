<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_wfo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_wfo_id')->constrained('periode_wfo')->cascadeOnDelete();
            $table->foreignId('tim_id')->constrained('tim')->restrictOnDelete();
            $table->enum('hari', ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu']);
            $table->timestamps();

            // Cegah 1 tim dobel di hari yang sama dalam 1 periode
            $table->unique(['periode_wfo_id', 'tim_id', 'hari']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_wfo');
    }
};
