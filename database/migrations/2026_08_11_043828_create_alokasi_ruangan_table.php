<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alokasi_ruangan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tim_id')->constrained('tim')->restrictOnDelete();
            $table->foreignId('ruangan_id')->constrained('ruangan')->restrictOnDelete();
            $table->date('tanggal');
            $table->timestamps();

            // 1 ruangan hanya untuk 1 tim per hari
            $table->unique(['ruangan_id', 'tanggal']);
            // 1 tim hanya mendapat 1 ruangan per hari
            $table->unique(['tim_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alokasi_ruangan');
    }
};
