<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Urutan seeding wajib diikuti — ada dependency antar seeder.
     *
     * Tim → Ruangan → Personil (butuh Tim) → DemoUserSeeder (butuh Personil + Tim)
     */
    public function run(): void
    {
        $this->call([
            TimSeeder::class,
            RuanganSeeder::class,
            PersonilSeeder::class,
            DemoUserSeeder::class,
            PeriodeWfoSeeder::class,
            JadwalWfoSeeder::class,
        ]);
    }
}
