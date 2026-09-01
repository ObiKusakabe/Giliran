<?php

namespace Database\Seeders;

use App\Models\Personil;
use App\Models\Tim;
use Illuminate\Database\Seeder;

class PersonilSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'SMK Yapiim Indramayu' => [
                ['nama' => 'Ananda Alif Syahputra', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081211110001', 'status' => 'aktif'],
                ['nama' => 'Halua Sherra Al-Khalifi', 'jenis_kelamin' => 'perempuan', 'no_hp' => '081211110002', 'status' => 'aktif'],
                ['nama' => 'Muhammad Abduh', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081211110003', 'status' => 'aktif'],
                ['nama' => 'Ainun Siva Salsabilah', 'jenis_kelamin' => 'perempuan', 'no_hp' => '081211110004', 'status' => 'aktif'],
                ['nama' => 'Cahya Rizqon', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081211110005', 'status' => 'aktif'],
            ],
            'SMKN 2 Kota Sukabumi' => [
                ['nama' => 'Ari Sigit Firdaus', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081222220001', 'status' => 'aktif'],
                ['nama' => 'Salwa Mutiara Hikmah', 'jenis_kelamin' => 'perempuan', 'no_hp' => '081222220002', 'status' => 'aktif'],
                ['nama' => 'Muhammad Faiz Ilham', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081222220003', 'status' => 'aktif'],
                ['nama' => 'Nadilla Fristiani', 'jenis_kelamin' => 'perempuan', 'no_hp' => '081222220004', 'status' => 'aktif'],
                ['nama' => 'Raden Ridho Pratama Kushartanto', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081222220005', 'status' => 'aktif'],
                ['nama' => 'Kahfi Ilham Firmansyah', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081222220006', 'status' => 'aktif'],
            ],
            'Univ Telkom Purwakerto' => [
                ['nama' => 'Tegar Kang Ageng Gilang', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081233330001', 'status' => 'aktif'],
                ['nama' => 'Reza Irawan', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081233330002', 'status' => 'aktif'],
                ['nama' => 'Fajar Budiawan', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081233330003', 'status' => 'aktif'],
                ['nama' => 'Kevin Jonson', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081233330004', 'status' => 'aktif'],
                ['nama' => 'Muhammad Daniel Anugrah Pratama', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081233330005', 'status' => 'aktif'],
            ],
            'SMKN 3 Banjar' => [
                ['nama' => 'Andhika Bintang Raya', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081244440001', 'status' => 'aktif'],
                ['nama' => 'Ikhsan Ardyansyah', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081244440002', 'status' => 'aktif'],
                ['nama' => 'Aditya Pratama', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081244440003', 'status' => 'aktif'],
                ['nama' => 'Fadhil Khairil', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081244440004', 'status' => 'aktif'],
            ],
            'Univ PGRI Madiun' => [
                ['nama' => 'Yanuar Andina Rahayu', 'jenis_kelamin' => 'perempuan', 'no_hp' => '081255550001', 'status' => 'aktif'],
            ],
            'SMKN 1 Binong' => [
                ['nama' => 'Andy setya wardana', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081266660001', 'status' => 'aktif'],
                ['nama' => 'Alfi muheimin', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081266660002', 'status' => 'aktif'],
                ['nama' => 'Aldy shayreja', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081266660003', 'status' => 'aktif'],
                ['nama' => 'Dimas sakirin', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081266660004', 'status' => 'aktif'],
            ],
            'STMIK Mardira Indonesia' => [
                ['nama' => 'Rizal Hidayatuloh', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081277770001', 'status' => 'aktif'],
                ['nama' => 'Natasya Ariyani Nazhirah', 'jenis_kelamin' => 'perempuan', 'no_hp' => '081277770002', 'status' => 'aktif'],
                ['nama' => 'Nazwa Wahdatul Aisya', 'jenis_kelamin' => 'perempuan', 'no_hp' => '081277770003', 'status' => 'aktif'],
                ['nama' => 'Muhammad Raihan Setiaman', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081277770004', 'status' => 'aktif'],
                ['nama' => 'Nugie Kurniawan', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081277770005', 'status' => 'aktif'],
            ],
            'SMK LPPM' => [
                ['nama' => 'Azkiya', 'jenis_kelamin' => 'perempuan', 'no_hp' => '081288880001', 'status' => 'aktif'],
                ['nama' => 'Azkila', 'jenis_kelamin' => 'perempuan', 'no_hp' => '081288880002', 'status' => 'aktif'],
            ],
            'LPKIA' => [
                ['nama' => 'Roby Rachmat Firdaus', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081299990001', 'status' => 'aktif'],
                ['nama' => 'Cindy Artika Devi', 'jenis_kelamin' => 'perempuan', 'no_hp' => '081299990002', 'status' => 'aktif'],
                ['nama' => 'Randie Syaeful Azahli', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081299990003', 'status' => 'aktif'],
                ['nama' => 'Salira Restu Gusti', 'jenis_kelamin' => 'perempuan', 'no_hp' => '081299990004', 'status' => 'aktif'],
                ['nama' => 'Ryan Adryan Kusmana', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081299990005', 'status' => 'aktif'],
            ],
            'SMKN 3 PARIAMAN' => [
                ['nama' => 'Erwa Muzaky', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081311110001', 'status' => 'aktif'],
                ['nama' => 'Muhammad Hanif Herman', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '081311110002', 'status' => 'aktif'],
            ],
            'STMIK Mardira (Kel 2)' => [
                ['nama' => 'Andieni Putri', 'jenis_kelamin' => 'perempuan', 'no_hp' => '081322220001', 'status' => 'aktif'],
                ['nama' => 'Tika Adela', 'jenis_kelamin' => 'perempuan', 'no_hp' => '081322220002', 'status' => 'aktif'],
                ['nama' => 'Salymah Allawiyah', 'jenis_kelamin' => 'perempuan', 'no_hp' => '081322220003', 'status' => 'aktif'],
                ['nama' => 'Sindi Putri', 'jenis_kelamin' => 'perempuan', 'no_hp' => '081322220004', 'status' => 'aktif'],
            ],
            'Politeknik Negri Padang' => [
                ['nama' => 'Natya Kivany', 'jenis_kelamin' => 'perempuan', 'no_hp' => '081333330001', 'status' => 'aktif'],
                ['nama' => 'Anandhita Putri Yoan', 'jenis_kelamin' => 'perempuan', 'no_hp' => '081333330002', 'status' => 'aktif'],
                ['nama' => 'Raisa Yaumil Fauziah', 'jenis_kelamin' => 'perempuan', 'no_hp' => '081333330003', 'status' => 'aktif'],
                ['nama' => 'Nazwa Aulia Khaira', 'jenis_kelamin' => 'perempuan', 'no_hp' => '081333330004', 'status' => 'aktif'],
            ],
        ];

        foreach ($data as $namaTim => $personils) {
            $tim = Tim::where('nama_tim', $namaTim)->first();
            if (! $tim) {
                continue;
            }

            foreach ($personils as $personil) {
                Personil::updateOrCreate(
                    ['tim_id' => $tim->id, 'nama' => $personil['nama']],
                    array_merge($personil, ['tim_id' => $tim->id])
                );
            }
        }
    }
}
