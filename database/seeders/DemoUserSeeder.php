<?php

namespace Database\Seeders;

use App\Models\Personil;
use App\Models\Tim;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder akun demo per role.
 *
 * Kredensial:
 *   Admin    — admin@giliran.test    / password
 *   Personil — personil@giliran.test / password  (terhubung ke Andi Pratama, Tim PNJ)
 *   Tim      — tim@giliran.test      / password  (terhubung ke Tim SMKN 1 Jakarta)
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin — tidak perlu link personil/tim
        User::updateOrCreate(
            ['email' => 'admin@giliran.test'],
            [
                'name' => 'Admin Demo',
                'username' => 'admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        // Personil — link ke Andi Pratama dari Tim PNJ
        $personil = Personil::whereHas('tim', fn ($q) => $q->where('nama_tim', 'Tim Politeknik Negeri Jakarta'))
            ->where('nama', 'Andi Pratama')
            ->first();

        User::updateOrCreate(
            ['email' => 'personil@giliran.test'],
            [
                'name' => 'Andi Pratama',
                'username' => 'andi.pratama',
                'password' => Hash::make('password'),
                'role' => 'personil',
                'personil_id' => $personil?->id,
                'email_verified_at' => now(),
            ]
        );

        // Tim — link ke Tim SMKN 1 Jakarta (role tim, read-only)
        $tim = Tim::where('nama_tim', 'Tim SMKN 1 Jakarta')->first();

        User::updateOrCreate(
            ['email' => 'tim@giliran.test'],
            [
                'name' => 'Tim SMKN 1 Jakarta',
                'username' => 'tim_smkn1',
                'password' => Hash::make('password'),
                'role' => 'tim',
                'tim_id' => $tim?->id,
                'email_verified_at' => now(),
            ]
        );
    }
}
