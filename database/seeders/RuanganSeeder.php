<?php

namespace Database\Seeders;

use App\Models\Ruangan;
use Illuminate\Database\Seeder;

class RuanganSeeder extends Seeder
{
    public function run(): void
    {
        $ruangans = [
            ['nama_ruangan' => 'Front Office', 'lokasi_gedung' => 'Kantor Utama', 'kapasitas' => 10, 'status' => 'tersedia'],
            ['nama_ruangan' => 'Workspace 1', 'lokasi_gedung' => 'Kantor Utama', 'kapasitas' => 15, 'status' => 'tersedia'],
            ['nama_ruangan' => 'Briefing Room', 'lokasi_gedung' => 'Kantor Utama', 'kapasitas' => 20, 'status' => 'tersedia'],
            ['nama_ruangan' => 'Workspace 2', 'lokasi_gedung' => 'Kantor Kedua', 'kapasitas' => 12, 'status' => 'tersedia'],
            ['nama_ruangan' => 'Workspace 3', 'lokasi_gedung' => 'Kantor Kedua', 'kapasitas' => 12, 'status' => 'tersedia'],
            ['nama_ruangan' => 'Back Office', 'lokasi_gedung' => 'Kantor Kedua', 'kapasitas' => 8, 'status' => 'tersedia'],
            ['nama_ruangan' => 'Garasi Mess Karyawan', 'lokasi_gedung' => 'Kantor Kedua', 'kapasitas' => 6, 'status' => 'tersedia'],
        ];

        foreach ($ruangans as $ruangan) {
            Ruangan::firstOrCreate(['nama_ruangan' => $ruangan['nama_ruangan']], $ruangan);
        }
    }
}
