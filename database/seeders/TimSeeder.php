<?php

namespace Database\Seeders;

use App\Models\Tim;
use Illuminate\Database\Seeder;

class TimSeeder extends Seeder
{
    public function run(): void
    {
        $tims = [
            ['nama_tim' => 'SMK Yapiim Indramayu', 'keterangan' => 'Peserta PKL SMK Yapiim Indramayu', 'status' => 'active'],
            ['nama_tim' => 'SMKN 2 Kota Sukabumi', 'keterangan' => 'Peserta PKL SMKN 2 Kota Sukabumi', 'status' => 'active'],
            ['nama_tim' => 'Univ Telkom Purwakerto', 'keterangan' => 'Peserta Magang Universitas Telkom Purwakerto', 'status' => 'active'],
            ['nama_tim' => 'SMKN 3 Banjar', 'keterangan' => 'Peserta PKL SMKN 3 Banjar', 'status' => 'active'],
            ['nama_tim' => 'Univ PGRI Madiun', 'keterangan' => 'Peserta Magang Universitas PGRI Madiun', 'status' => 'active'],
            ['nama_tim' => 'SMKN 1 Binong', 'keterangan' => 'Peserta PKL SMKN 1 Binong', 'status' => 'active'],
            ['nama_tim' => 'STMIK Mardira Indonesia', 'keterangan' => 'Peserta PKL STMIK Mardira Indonesia Kelompok 1', 'status' => 'active'],
            ['nama_tim' => 'SMK LPPM', 'keterangan' => 'Peserta PKL SMK LPPM', 'status' => 'active'],
            ['nama_tim' => 'LPKIA', 'keterangan' => 'Peserta Magang LPKIA', 'status' => 'active'],
            ['nama_tim' => 'SMKN 3 PARIAMAN', 'keterangan' => 'Peserta PKL SMKN 3 Pariaman', 'status' => 'active'],
            ['nama_tim' => 'STMIK Mardira (Kel 2)', 'keterangan' => 'Peserta PKL STMIK Mardira Indonesia Kelompok 2', 'status' => 'active'],
            ['nama_tim' => 'Politeknik Negri Padang', 'keterangan' => 'Peserta PKL Politeknik Negeri Padang', 'status' => 'active'],
        ];

        foreach ($tims as $tim) {
            Tim::updateOrCreate(['nama_tim' => $tim['nama_tim']], $tim);
        }
    }
}
