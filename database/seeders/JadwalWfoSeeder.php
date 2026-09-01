<?php

namespace Database\Seeders;

use App\Models\JadwalAdzanKitab;
use App\Models\JadwalWfo;
use App\Models\PeriodeWfo;
use App\Models\Personil;
use App\Models\Tim;
use Illuminate\Database\Seeder;

class JadwalWfoSeeder extends Seeder
{
    public function run(): void
    {
        $periode = PeriodeWfo::where('status', 'aktif')->first();
        if (! $periode) {
            return;
        }

        // 1. Pola WFO Mingguan (Lampiran 1 PDF)
        $polaWfo = [
            'senin' => ['SMK Yapiim Indramayu', 'SMKN 2 Kota Sukabumi', 'STMIK Mardira Indonesia', 'SMKN 3 Banjar', 'Univ PGRI Madiun', 'SMKN 1 Binong'],
            'selasa' => ['Univ Telkom Purwakerto', 'SMK LPPM', 'LPKIA', 'SMKN 3 PARIAMAN', 'STMIK Mardira (Kel 2)', 'Politeknik Negri Padang'],
            'rabu' => ['SMK Yapiim Indramayu', 'Univ Telkom Purwakerto', 'Univ PGRI Madiun', 'STMIK Mardira Indonesia', 'LPKIA', 'STMIK Mardira (Kel 2)'],
            'kamis' => ['SMKN 2 Kota Sukabumi', 'SMKN 3 Banjar', 'SMKN 1 Binong', 'SMK LPPM', 'SMKN 3 PARIAMAN', 'Politeknik Negri Padang'],
            'jumat' => ['SMK Yapiim Indramayu', 'SMKN 2 Kota Sukabumi', 'Univ Telkom Purwakerto', 'STMIK Mardira Indonesia', 'SMKN 3 PARIAMAN', 'LPKIA'],
            'sabtu' => ['SMKN 3 Banjar', 'Univ PGRI Madiun', 'SMKN 1 Binong', 'SMK LPPM', 'STMIK Mardira (Kel 2)', 'Politeknik Negri Padang'],
        ];

        foreach ($polaWfo as $hari => $timNames) {
            foreach ($timNames as $namaTim) {
                $tim = Tim::where('nama_tim', $namaTim)->first();
                if ($tim) {
                    JadwalWfo::updateOrCreate([
                        'periode_wfo_id' => $periode->id,
                        'tim_id' => $tim->id,
                        'hari' => $hari,
                    ]);
                }
            }
        }

        // 2. Jadwal Petugas Adzan & Pembacaan Kitab (Bulan Agustus 2026 - PDF 2)
        $adzanData = [
            '2026-08-03' => [
                'dhuhr_adzan' => ['Ananda Alif Syahputra', 'SMK Yapiim Indramayu'],
                'dhuhr_kajian' => ['Ari Sigit Firdaus', 'SMKN 2 Kota Sukabumi'],
                'asr_adzan' => ['Tegar Kang Ageng Gilang', 'Univ Telkom Purwakerto'],
                'asr_kajian' => ['Andhika Bintang Raya', 'SMKN 3 Banjar'],
            ],
            '2026-08-04' => [
                'dhuhr_adzan' => ['Roby Rachmat Firdaus', 'LPKIA'],
                'dhuhr_kajian' => ['Erwa Muzaky', 'SMKN 3 PARIAMAN'],
                'asr_adzan' => ['Cahya Rizqon', 'SMK Yapiim Indramayu'],
                'asr_kajian' => ['Kahfi Ilham Firmansyah', 'SMKN 2 Kota Sukabumi'],
            ],
            '2026-08-05' => [
                'dhuhr_adzan' => ['Rizal Hidayatuloh', 'STMIK Mardira Indonesia'],
                'dhuhr_kajian' => ['Andhika Bintang Raya', 'SMKN 3 Banjar'],
                'asr_adzan' => ['Dimas sakirin', 'SMKN 1 Binong'],
                'asr_kajian' => ['Roby Rachmat Firdaus', 'LPKIA'],
            ],
            '2026-08-06' => [
                'dhuhr_adzan' => ['Fadhil Khairil', 'SMKN 3 Banjar'],
                'dhuhr_kajian' => ['Muhammad Abduh', 'SMK Yapiim Indramayu'],
                'asr_adzan' => ['Erwa Muzaky', 'SMKN 3 PARIAMAN'],
                'asr_kajian' => ['Tegar Kang Ageng Gilang', 'Univ Telkom Purwakerto'],
            ],
            '2026-08-07' => [
                'asr_adzan' => ['Aldy shayreja', 'SMKN 1 Binong'],
                'asr_kajian' => ['Rizal Hidayatuloh', 'STMIK Mardira Indonesia'],
            ],
            '2026-08-10' => [
                'dhuhr_adzan' => ['Muhammad Faiz Ilham', 'SMKN 2 Kota Sukabumi'],
                'dhuhr_kajian' => ['Andy setya wardana', 'SMKN 1 Binong'],
                'asr_adzan' => ['Muhammad Abduh', 'SMK Yapiim Indramayu'],
                'asr_kajian' => ['Reza Irawan', 'Univ Telkom Purwakerto'],
            ],
            '2026-08-11' => [
                'dhuhr_adzan' => ['Muhammad Hanif Herman', 'SMKN 3 PARIAMAN'],
                'dhuhr_kajian' => ['Randie Syaeful Azahli', 'LPKIA'],
                'asr_adzan' => ['Ari Sigit Firdaus', 'SMKN 2 Kota Sukabumi'],
                'asr_kajian' => ['Ananda Alif Syahputra', 'SMK Yapiim Indramayu'],
            ],
            '2026-08-12' => [
                'dhuhr_adzan' => ['Muhammad Daniel Anugrah Pratama', 'Univ Telkom Purwakerto'],
                'dhuhr_kajian' => ['Randie Syaeful Azahli', 'LPKIA'],
                'asr_adzan' => ['Muhammad Raihan Setiaman', 'STMIK Mardira Indonesia'],
                'asr_kajian' => ['Ikhsan Ardyansyah', 'SMKN 3 Banjar'],
            ],
            '2026-08-13' => [
                'dhuhr_adzan' => ['Raden Ridho Pratama Kushartanto', 'SMKN 2 Kota Sukabumi'],
                'dhuhr_kajian' => ['Kevin Jonson', 'Univ Telkom Purwakerto'],
                'asr_adzan' => ['Ananda Alif Syahputra', 'SMK Yapiim Indramayu'],
                'asr_kajian' => ['Muhammad Hanif Herman', 'SMKN 3 PARIAMAN'],
            ],
            '2026-08-14' => [
                'asr_adzan' => ['Roby Rachmat Firdaus', 'LPKIA'],
                'asr_kajian' => ['Muhammad Hanif Herman', 'SMKN 3 PARIAMAN'],
            ],
            '2026-08-17' => [
                'dhuhr_adzan' => ['Ikhsan Ardyansyah', 'SMKN 3 Banjar'],
                'dhuhr_kajian' => ['Ananda Alif Syahputra', 'SMK Yapiim Indramayu'],
                'asr_adzan' => ['Raden Ridho Pratama Kushartanto', 'SMKN 2 Kota Sukabumi'],
                'asr_kajian' => ['Alfi muheimin', 'SMKN 1 Binong'],
            ],
            '2026-08-18' => [
                'dhuhr_adzan' => ['Muhammad Faiz Ilham', 'SMKN 2 Kota Sukabumi'],
                'dhuhr_kajian' => ['Salira Restu Gusti', 'LPKIA'],
                'asr_adzan' => ['Cahya Rizqon', 'SMK Yapiim Indramayu'],
                'asr_kajian' => ['Erwa Muzaky', 'SMKN 3 PARIAMAN'],
            ],
            '2026-08-19' => [
                'dhuhr_adzan' => ['Andy setya wardana', 'SMKN 1 Binong'],
                'dhuhr_kajian' => ['Fajar Budiawan', 'Univ Telkom Purwakerto'],
                'asr_adzan' => ['Salira Restu Gusti', 'LPKIA'],
                'asr_kajian' => ['Nugie Kurniawan', 'STMIK Mardira Indonesia'],
            ],
            '2026-08-20' => [
                'dhuhr_adzan' => ['Andhika Bintang Raya', 'SMKN 3 Banjar'],
                'dhuhr_kajian' => ['Ari Sigit Firdaus', 'SMKN 2 Kota Sukabumi'],
                'asr_adzan' => ['Muhammad Daniel Anugrah Pratama', 'Univ Telkom Purwakerto'],
                'asr_kajian' => ['Muhammad Abduh', 'SMK Yapiim Indramayu'],
            ],
            '2026-08-21' => [
                'asr_adzan' => ['Muhammad Raihan Setiaman', 'STMIK Mardira Indonesia'],
                'asr_kajian' => ['Dimas sakirin', 'SMKN 1 Binong'],
            ],
            '2026-08-24' => [
                'dhuhr_adzan' => ['Fajar Budiawan', 'Univ Telkom Purwakerto'],
                'dhuhr_kajian' => ['Cahya Rizqon', 'SMK Yapiim Indramayu'],
                'asr_adzan' => ['Aditya Pratama', 'SMKN 3 Banjar'],
                'asr_kajian' => ['Kahfi Ilham Firmansyah', 'SMKN 2 Kota Sukabumi'],
            ],
            '2026-08-25' => [
                'dhuhr_adzan' => ['Ryan Adryan Kusmana', 'LPKIA'],
                'dhuhr_kajian' => ['Raden Ridho Pratama Kushartanto', 'SMKN 2 Kota Sukabumi'],
                'asr_adzan' => ['Muhammad Hanif Herman', 'SMKN 3 PARIAMAN'],
                'asr_kajian' => ['Cahya Rizqon', 'SMK Yapiim Indramayu'],
            ],
            '2026-08-26' => [
                'dhuhr_adzan' => ['Aditya Pratama', 'SMKN 3 Banjar'],
                'dhuhr_kajian' => ['Ryan Adryan Kusmana', 'LPKIA'],
                'asr_adzan' => ['Reza Irawan', 'Univ Telkom Purwakerto'],
                'asr_kajian' => ['Alfi muheimin', 'SMKN 1 Binong'],
            ],
            '2026-08-27' => [
                'dhuhr_adzan' => ['Erwa Muzaky', 'SMKN 3 PARIAMAN'],
                'dhuhr_kajian' => ['Muhammad Faiz Ilham', 'SMKN 2 Kota Sukabumi'],
                'asr_adzan' => ['Ikhsan Ardyansyah', 'SMKN 3 Banjar'],
                'asr_kajian' => ['Reza Irawan', 'Univ Telkom Purwakerto'],
            ],
            '2026-08-28' => [
                'asr_adzan' => ['Randie Syaeful Azahli', 'LPKIA'],
                'asr_kajian' => ['Erwa Muzaky', 'SMKN 3 PARIAMAN'],
            ],
            '2026-08-31' => [
                'dhuhr_adzan' => ['Aldy shayreja', 'SMKN 1 Binong'],
                'dhuhr_kajian' => ['Fadhil Khairil', 'SMKN 3 Banjar'],
                'asr_adzan' => ['Kevin Jonson', 'Univ Telkom Purwakerto'],
                'asr_kajian' => ['Ananda Alif Syahputra', 'SMK Yapiim Indramayu'],
            ],
        ];

        foreach ($adzanData as $tgl => $tasks) {
            foreach ($tasks as $key => $info) {
                [$namaPersonil, $namaTim] = $info;
                $personil = Personil::where('nama', $namaPersonil)->first();
                if (! $personil) {
                    continue;
                }

                [$waktuSholat, $jenisTugas] = explode('_', $key);

                JadwalAdzanKitab::updateOrCreate(
                    [
                        'tanggal' => $tgl,
                        'waktu_sholat' => $waktuSholat,
                        'jenis_tugas' => $jenisTugas,
                    ],
                    [
                        'personil_id' => $personil->id,
                        'status_konfirmasi' => 'menunggu',
                    ]
                );
            }
        }
    }
}
