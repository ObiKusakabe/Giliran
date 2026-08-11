<?php

namespace Database\Seeders;

use App\Models\Ruangan;
use Illuminate\Database\Seeder;

class RuanganSeeder extends Seeder
{
    public function run(): void
    {
        $ruangans = [
            ['nama_ruangan' => 'Ruang Meeting A', 'kapasitas' => 10, 'status' => 'tersedia'],
            ['nama_ruangan' => 'Ruang Meeting B', 'kapasitas' => 8,  'status' => 'tersedia'],
            ['nama_ruangan' => 'Ruang Kelas 1',   'kapasitas' => 20, 'status' => 'tersedia'],
            ['nama_ruangan' => 'Ruang Kelas 2',   'kapasitas' => 20, 'status' => 'tersedia'],
            ['nama_ruangan' => 'Ruang Diskusi',   'kapasitas' => 6,  'status' => 'tersedia'],
        ];

        foreach ($ruangans as $ruangan) {
            Ruangan::firstOrCreate(['nama_ruangan' => $ruangan['nama_ruangan']], $ruangan);
        }
    }
}
