<?php

namespace Database\Seeders;

use App\Models\Tim;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder akun demo — 2 role: admin dan tim.
 *
 * Kredensial:
 *   Admin — admin@giliran.test   / password
 *   Tim 1 — tim.pnj@giliran.test / password  (Tim Politeknik Negeri Jakarta)
 *   Tim 2 — tim@giliran.test     / password  (Tim SMKN 1 Jakarta)
 */
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

        // Tim PNJ
        $timPnj = Tim::where('nama_tim', 'Tim Politeknik Negeri Jakarta')->first();
        User::updateOrCreate(
            ['email' => 'tim.pnj@giliran.test'],
            [
                'name' => 'Tim Politeknik Negeri Jakarta',
                'username' => 'tim_pnj',
                'password' => Hash::make('password'),
                'role' => 'tim',
                'tim_id' => $timPnj?->id,
                'email_verified_at' => now(),
            ]
        );

        // Tim SMKN 1
        $timSmkn = Tim::where('nama_tim', 'Tim SMKN 1 Jakarta')->first();
        User::updateOrCreate(
            ['email' => 'tim@giliran.test'],
            [
                'name' => 'Tim SMKN 1 Jakarta',
                'username' => 'tim_smkn1',
                'password' => Hash::make('password'),
                'role' => 'tim',
                'tim_id' => $timSmkn?->id,
                'email_verified_at' => now(),
            ]
        );
    }
}
