<?php

namespace Database\Seeders;

use App\Models\Personil;
use App\Models\Tim;
use Illuminate\Database\Seeder;

class PersonilSeeder extends Seeder
{
    public function run(): void
    {
        // Data personil per tim — nama disesuaikan dengan tim dummy
        $data = [
            'Tim Politeknik Negeri Jakarta' => [
                ['nama' => 'Andi Pratama',    'no_hp' => '081111111101', 'status' => 'aktif'],
                ['nama' => 'Budi Santoso',    'no_hp' => '081111111102', 'status' => 'aktif'],
                ['nama' => 'Citra Dewi',      'no_hp' => '081111111103', 'status' => 'aktif'],
                ['nama' => 'Deni Kurniawan',  'no_hp' => null,           'status' => 'aktif'],
                ['nama' => 'Eka Putri',       'no_hp' => '081111111105', 'status' => 'nonaktif'],
            ],
            'Tim Universitas Bina Nusantara' => [
                ['nama' => 'Fajar Ramadhan',  'no_hp' => '082222222201', 'status' => 'aktif'],
                ['nama' => 'Gita Lestari',    'no_hp' => '082222222202', 'status' => 'aktif'],
                ['nama' => 'Hendra Wijaya',   'no_hp' => '082222222203', 'status' => 'aktif'],
                ['nama' => 'Indah Sari',      'no_hp' => null,           'status' => 'aktif'],
            ],
            'Tim SMKN 1 Jakarta' => [
                ['nama' => 'Joko Susanto',    'no_hp' => '083333333301', 'status' => 'aktif'],
                ['nama' => 'Kartika Sari',    'no_hp' => '083333333302', 'status' => 'aktif'],
                ['nama' => 'Luthfi Hakim',    'no_hp' => '083333333303', 'status' => 'aktif'],
            ],
            'Tim Universitas Gunadarma' => [
                ['nama' => 'Maya Anggraini',  'no_hp' => '084444444401', 'status' => 'aktif'],
                ['nama' => 'Nanda Putra',     'no_hp' => '084444444402', 'status' => 'aktif'],
                ['nama' => 'Olivia Tanaka',   'no_hp' => null,           'status' => 'aktif'],
                ['nama' => 'Pandu Wicaksono', 'no_hp' => '084444444404', 'status' => 'aktif'],
            ],
        ];

        foreach ($data as $namaTim => $personils) {
            $tim = Tim::where('nama_tim', $namaTim)->first();

            if (! $tim) {
                continue;
            }

            foreach ($personils as $personil) {
                Personil::firstOrCreate(
                    ['tim_id' => $tim->id, 'nama' => $personil['nama']],
                    array_merge($personil, ['tim_id' => $tim->id])
                );
            }
        }
    }
}
