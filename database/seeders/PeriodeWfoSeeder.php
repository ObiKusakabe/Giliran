<?php

namespace Database\Seeders;

use App\Models\PeriodeWfo;
use Illuminate\Database\Seeder;

class PeriodeWfoSeeder extends Seeder
{
    public function run(): void
    {
        // Periode WFO Aktif sesuai Surat Resmi: 4 Agustus s.d 31 Agustus 2026
        PeriodeWfo::updateOrCreate(
            ['keterangan' => 'Periode WFO Agustus 2026'],
            [
                'tanggal_mulai' => '2026-08-04',
                'tanggal_selesai' => '2026-08-31',
                'keterangan' => 'Periode WFO Agustus 2026',
                'status' => 'aktif',
            ]
        );
    }
}
