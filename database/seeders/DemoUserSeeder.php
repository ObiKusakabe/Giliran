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
            ['username' => 'admin'],
            [
                'name' => 'Admin Inovindo',
                'username' => 'admin',
                'email' => null,
                'password' => Hash::make('inovindojaya2010'),
                'role' => 'admin',
                'tim_id' => null,
                'email_verified_at' => null,
            ]
        );

        // Akun untuk tim SMK Yapiim Indramayu
        $tim1 = Tim::where('nama_tim', 'SMK Yapiim Indramayu')->first();
        if ($tim1) {
            User::updateOrCreate(
                ['username' => 'tim_yapiim'],
                [
                    'name' => 'SMK Yapiim Indramayu',
                    'username' => 'tim_yapiim',
                    'email' => null,
                    'password' => Hash::make('inovindojaya'),
                    'role' => 'tim',
                    'tim_id' => $tim1->id,
                    'email_verified_at' => null,
                ]
            );
        }

        // Akun untuk SMKN 2 Kota Sukabumi
        $tim2 = Tim::where('nama_tim', 'SMKN 2 Kota Sukabumi')->first();
        if ($tim2) {
            User::updateOrCreate(
                ['username' => 'tim_smkn2sukabumi'],
                [
                    'name' => 'SMKN 2 Kota Sukabumi',
                    'username' => 'tim_smkn2sukabumi',
                    'email' => null,
                    'password' => Hash::make('inovindojaya'),
                    'role' => 'tim',
                    'tim_id' => $tim2->id,
                    'email_verified_at' => null,
                ]
            );
        }

        // Akun untuk Univ Telkom Purwakerto
        $tim3 = Tim::where('nama_tim', 'Univ Telkom Purwakerto')->first();
        if ($tim3) {
            User::updateOrCreate(
                ['username' => 'tim_telkom'],
                [
                    'name' => 'Univ Telkom Purwakerto',
                    'username' => 'tim_telkom',
                    'email' => null,
                    'password' => Hash::make('inovindojaya'),
                    'role' => 'tim',
                    'tim_id' => $tim3->id,
                    'email_verified_at' => null,
                ]
            );
        }
    }
}
