<?php

namespace Database\Seeders;

use App\Models\Tim;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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

        // Counter untuk generate username berdasarkan bulan
        $currentYear = now()->year;
        $currentMonth = now()->format('m');

        // Get existing usernames untuk bulan ini untuk increment counter
        $existingCount = User::where('username', 'like', "{$currentYear}_{$currentMonth}_%")->count();
        $counter = $existingCount + 1;

        foreach ($tims as $timData) {
            $tim = Tim::updateOrCreate(['nama_tim' => $timData['nama_tim']], $timData);

            // Auto-generate user account jika tim belum punya user
            if (! $tim->user) {
                $username = sprintf('%s_%s_%03d', $currentYear, $currentMonth, $counter);

                $user = User::create([
                    'username' => $username,
                    'name' => $timData['nama_tim'],
                    'password' => Hash::make('inovindojaya'), // Default password
                    'role' => 'tim',
                    'tim_id' => $tim->id,
                ]);

                $this->command->info("✅ Created user account: {$username} for tim: {$timData['nama_tim']}");

                $counter++;
            } else {
                $this->command->info("⏭️  Tim {$timData['nama_tim']} already has user: {$tim->user->username}");
            }
        }
    }
}
