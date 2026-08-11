<?php

namespace Database\Seeders;

use App\Models\PeriodeWfo;
use Illuminate\Database\Seeder;

class PeriodeWfoSeeder extends Seeder
{
    public function run(): void
    {
        // Periode lama (sudah selesai) — nonaktif
        PeriodeWfo::firstOrCreate(
            ['keterangan' => 'PKL Juni–Juli 2026'],
            [
                'tanggal_mulai' => '2026-06-01',
                'tanggal_selesai' => '2026-07-31',
                'keterangan' => 'PKL Juni–Juli 2026',
                'status' => 'nonaktif',
            ]
        );

        // Periode aktif saat ini
        PeriodeWfo::firstOrCreate(
            ['keterangan' => 'PKL Agustus–September 2026'],
            [
                'tanggal_mulai' => '2026-08-01',
                'tanggal_selesai' => '2026-09-30',
                'keterangan' => 'PKL Agustus–September 2026',
                'status' => 'aktif',
            ]
        );
    }
}
