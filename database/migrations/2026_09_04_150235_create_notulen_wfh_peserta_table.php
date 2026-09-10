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
        Schema::create('notulen_wfh_peserta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notulen_briefing_id')->constrained('notulen_briefing')->cascadeOnDelete();
            $table->foreignId('personil_id')->constrained('personil')->cascadeOnDelete();
            $table->boolean('is_creator')->default(false)->comment('True = yang buat notulen WFH');
            $table->timestamps();

            // Prevent duplicate
            $table->unique(['notulen_briefing_id', 'personil_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notulen_wfh_peserta');
    }
};
