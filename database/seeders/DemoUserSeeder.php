<?php

namespace Database\Seeders;

use App\Models\Tim;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        User::updateOrCreate(
            ['email' => 'admin@giliran.test'],
            [
                'name' => 'Admin Demo',
                'username' => 'admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'tim_id' => null,
                'email_verified_at' => now(),
            ]
        );

        // Akun untuk tim SMK Yapiim Indramayu
        $tim1 = Tim::where('nama_tim', 'SMK Yapiim Indramayu')->first();
        if ($tim1) {
            User::updateOrCreate(
                ['email' => 'yapiim@giliran.test'],
                [
                    'name' => 'SMK Yapiim Indramayu',
                    'username' => 'tim_yapiim',
                    'password' => Hash::make('inovindojaya'),
                    'role' => 'tim',
                    'tim_id' => $tim1->id,
                    'email_verified_at' => now(),
                ]
            );
        }

        // Akun untuk SMKN 2 Kota Sukabumi
        $tim2 = Tim::where('nama_tim', 'SMKN 2 Kota Sukabumi')->first();
        if ($tim2) {
            User::updateOrCreate(
                ['email' => 'smkn2sukabumi@giliran.test'],
                [
                    'name' => 'SMKN 2 Kota Sukabumi',
                    'username' => 'tim_smkn2sukabumi',
                    'password' => Hash::make('inovindojaya'),
                    'role' => 'tim',
                    'tim_id' => $tim2->id,
                    'email_verified_at' => now(),
                ]
            );
        }

        // Akun untuk Univ Telkom Purwakerto
        $tim3 = Tim::where('nama_tim', 'Univ Telkom Purwakerto')->first();
        if ($tim3) {
            User::updateOrCreate(
                ['email' => 'telkom@giliran.test'],
                [
                    'name' => 'Univ Telkom Purwakerto',
                    'username' => 'tim_telkom',
                    'password' => Hash::make('inovindojaya'),
                    'role' => 'tim',
                    'tim_id' => $tim3->id,
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
