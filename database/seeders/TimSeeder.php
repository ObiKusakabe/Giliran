<?php

namespace Database\Seeders;

use App\Models\Tim;
use Illuminate\Database\Seeder;

class TimSeeder extends Seeder
{
    public function run(): void
    {
        $tims = [
            ['nama_tim' => 'Tim Politeknik Negeri Jakarta', 'keterangan' => 'Peserta PKL D3 Teknik Informatika', 'status' => 'active'],
            ['nama_tim' => 'Tim Universitas Bina Nusantara', 'keterangan' => 'Peserta magang jurusan Sistem Informasi', 'status' => 'active'],
            ['nama_tim' => 'Tim SMKN 1 Jakarta', 'keterangan' => 'Peserta PKL jurusan RPL', 'status' => 'active'],
            ['nama_tim' => 'Tim Universitas Gunadarma', 'keterangan' => 'Peserta magang semester 6', 'status' => 'active'],
        ];

        foreach ($tims as $tim) {
            Tim::firstOrCreate(['nama_tim' => $tim['nama_tim']], $tim);
        }
    }
}
