<?php

namespace Database\Seeders;

use App\Models\Tim;
use Illuminate\Database\Seeder;

class TimSeeder extends Seeder
{
    public function run(): void
    {
        $tims = [
            ['nama_tim' => 'Tim Politeknik Negeri Jakarta', 'keterangan' => 'Peserta PKL D3 Teknik Informatika'],
            ['nama_tim' => 'Tim Universitas Bina Nusantara', 'keterangan' => 'Peserta magang jurusan Sistem Informasi'],
            ['nama_tim' => 'Tim SMKN 1 Jakarta', 'keterangan' => 'Peserta PKL jurusan RPL'],
            ['nama_tim' => 'Tim Universitas Gunadarma', 'keterangan' => 'Peserta magang semester 6'],
        ];

        foreach ($tims as $tim) {
            Tim::firstOrCreate(['nama_tim' => $tim['nama_tim']], $tim);
        }
    }
}
