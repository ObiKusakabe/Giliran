<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_adzan_kitab', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personil_id')->constrained('personil')->restrictOnDelete();
            $table->date('tanggal');
            // MVP: dhuhr & asr saja (GEN-04), enum lengkap disimpan untuk fleksibilitas
            $table->enum('waktu_sholat', ['fajr', 'dhuhr', 'asr', 'maghrib', 'isha']);
            $table->enum('jenis_tugas', ['adzan', 'kajian']);
            $table->enum('status_konfirmasi', ['menunggu', 'siap', 'berhalangan'])->default('menunggu');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_adzan_kitab');
    }
};
