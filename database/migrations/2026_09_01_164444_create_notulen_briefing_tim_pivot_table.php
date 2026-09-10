<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Create pivot table
        Schema::create('notulen_briefing_tim', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notulen_briefing_id')->constrained('notulen_briefing')->cascadeOnDelete();
            $table->foreignId('tim_id')->constrained('tim')->cascadeOnDelete();
            $table->boolean('is_creator')->default(false); // Track which tim created the notulen
            $table->timestamps();

            // Unique constraint: 1 tim per notulen
            $table->unique(['notulen_briefing_id', 'tim_id']);
            // Index for faster lookup
            $table->index(['tim_id', 'notulen_briefing_id']);
        });

        // Step 2: Migrate existing data
        // For each existing notulen, add its tim_id to pivot table AND auto-link other tim WFO
        DB::table('notulen_briefing')->orderBy('id')->chunk(100, function ($notulens) {
            foreach ($notulens as $notulen) {
                // Add creator tim to pivot
                DB::table('notulen_briefing_tim')->insert([
                    'notulen_briefing_id' => $notulen->id,
                    'tim_id' => $notulen->tim_id,
                    'is_creator' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Auto-add other tim yang WFO di hari yang sama
                $tanggal = Carbon::parse($notulen->tanggal);
                $namaHari = match ($tanggal->dayOfWeekIso) {
                    1 => 'senin', 2 => 'selasa', 3 => 'rabu',
                    4 => 'kamis', 5 => 'jumat', 6 => 'sabtu',
                    7 => 'minggu',
                };

                // Find periode aktif for that date
                $periode = DB::table('periode_wfo')
                    ->where('tanggal_mulai', '<=', $notulen->tanggal)
                    ->where('tanggal_selesai', '>=', $notulen->tanggal)
                    ->first();

                if ($periode) {
                    // Find all tim WFO on that day
                    $timWfoIds = DB::table('jadwal_wfo')
                        ->where('periode_wfo_id', $periode->id)
                        ->where('hari', $namaHari)
                        ->where('tim_id', '!=', $notulen->tim_id) // Exclude creator
                        ->pluck('tim_id');

                    foreach ($timWfoIds as $timId) {
                        // Check if not already exists (avoid duplicates)
                        $exists = DB::table('notulen_briefing_tim')
                            ->where('notulen_briefing_id', $notulen->id)
                            ->where('tim_id', $timId)
                            ->exists();

                        if (! $exists) {
                            DB::table('notulen_briefing_tim')->insert([
                                'notulen_briefing_id' => $notulen->id,
                                'tim_id' => $timId,
                                'is_creator' => false,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }
        });

        // Step 3: Keep tim_id column for now (don't drop - as backup)
        // We'll keep it for rollback safety
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notulen_briefing_tim');
    }
};
