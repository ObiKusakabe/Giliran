<?php

namespace Database\Seeders;

use App\Models\Tim;
use App\Models\User;
use App\Services\TimAccountGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $generator = new TimAccountGenerator;

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

        // Hapus akun demo lama dengan username format lama
        User::whereIn('username', ['tim_yapiim', 'tim_smkn2sukabumi', 'tim_telkom'])->delete();

        // Generate akun untuk tim yang sudah ada menggunakan format YYYY_MM_XXX
        $demoTimNames = [
            'SMK Yapiim Indramayu',
            'SMKN 2 Kota Sukabumi',
            'Univ Telkom Purwakerto',
        ];

        foreach ($demoTimNames as $timName) {
            $tim = Tim::where('nama_tim', $timName)->first();
            if ($tim) {
                // Cek apakah tim sudah punya akun
                $existingUser = User::where('tim_id', $tim->id)->where('role', 'tim')->first();

                if (! $existingUser) {
                    // Generate akun baru dengan format YYYY_MM_XXX
                    $generator->createAccount($tim);
                }
            }
        }
    }
}
