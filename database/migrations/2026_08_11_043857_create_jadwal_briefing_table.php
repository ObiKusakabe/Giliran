<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_briefing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tim_id')->constrained('tim')->restrictOnDelete();
            $table->foreignId('personil_id')->constrained('personil')->restrictOnDelete();
            $table->date('tanggal');
            $table->enum('sesi', ['pagi', 'sore']);
            $table->enum('status_konfirmasi', ['menunggu', 'siap', 'berhalangan'])->default('menunggu');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_briefing');
    }
};
