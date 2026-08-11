<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personil', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tim_id')->constrained('tim')->restrictOnDelete();
            $table->string('nama', 150);
            $table->string('no_hp', 20)->nullable();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personil');
    }
};
