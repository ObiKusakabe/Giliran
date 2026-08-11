<?php

namespace Database\Seeders;

use App\Models\JadwalWfo;
use App\Models\PeriodeWfo;
use App\Models\Tim;
use Illuminate\Database\Seeder;

/**
 * Seed pola WFO mingguan untuk periode aktif.
 * Format: tim X WFO di hari Y.
 * Setiap tim boleh WFO di beberapa hari, tapi UNIQUE(periode, tim, hari).
 */
class JadwalWfoSeeder extends Seeder
{
    public function run(): void
    {
        $periode = PeriodeWfo::where('status', 'aktif')->first();

        if (! $periode) {
            $this->command->warn('Tidak ada periode aktif. Lewati JadwalWfoSeeder.');

            return;
        }

        // Ambil ID tim berdasarkan nama
        $timIds = Tim::pluck('id', 'nama_tim');

        // Pola WFO: [nama_tim => [hari, ...]]
        $pola = [
            'Tim Politeknik Negeri Jakarta' => ['senin', 'rabu', 'jumat'],
            'Tim Universitas Bina Nusantara' => ['selasa', 'kamis'],
            'Tim SMKN 1 Jakarta' => ['senin', 'rabu', 'sabtu'],
            'Tim Universitas Gunadarma' => ['selasa', 'kamis', 'sabtu'],
        ];

        foreach ($pola as $namaTim => $hariList) {
            $timId = $timIds[$namaTim] ?? null;

            if (! $timId) {
                continue;
            }

            foreach ($hariList as $hari) {
                JadwalWfo::firstOrCreate([
                    'periode_wfo_id' => $periode->id,
                    'tim_id' => $timId,
                    'hari' => $hari,
                ]);
            }
        }
    }
}
