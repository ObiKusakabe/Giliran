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
        Schema::create('notulen_briefing', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->index();
            $table->enum('sesi', ['pagi', 'sore'])->default('pagi');
            $table->foreignId('tim_id')->constrained('tim')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // Notulen (user yang mengisi)
            $table->string('nama_notulen'); // Nama user/personil yang mengisi
            $table->json('peserta')->nullable(); // Array of personil names/IDs yang hadir
            $table->text('catatan')->nullable(); // Catatan briefing
            $table->string('file_path')->nullable(); // Path untuk foto/PDF (optional)
            $table->string('file_name')->nullable(); // Original filename
            $table->string('file_type')->nullable(); // mime type
            $table->unsignedInteger('file_size')->nullable(); // in bytes
            $table->timestamps();

            // Indexes untuk performa query
            $table->index(['tim_id', 'tanggal']);
            $table->index(['user_id', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notulen_briefing');
    }
};
