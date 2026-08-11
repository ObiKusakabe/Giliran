<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifikasi', function (Blueprint $table) {
            $table->id();
            // Diisi untuk notif ke akun role personil
            $table->foreignId('personil_id')->nullable()->constrained('personil')->nullOnDelete();
            // Diisi untuk notif ke akun role admin ATAU tim
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('tipe', ['jadwal', 'pengganti', 'reminder']);
            $table->text('pesan');
            // ID jadwal terkait (bisa jadwal_adzan_kitab, jadwal_briefing, dll)
            $table->unsignedBigInteger('data_id')->nullable();
            $table->boolean('dibaca')->default(false);
            $table->dateTime('terkirim_pada');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi');
    }
};
