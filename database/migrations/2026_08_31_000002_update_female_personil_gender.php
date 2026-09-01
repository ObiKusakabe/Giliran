<?php

use App\Models\Personil;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $femaleNames = [
            'Halua Sherra Al-Khalifi',
            'Ainun Siva Salsabilah',
            'Salwa Mutiara Hikmah',
            'Nadilla Fristiani',
            'Yanuar Andina Rahayu',
            'Natasya Ariyani Nazhirah',
            'Nazwa Wahdatul Aisya',
            'Azkiya',
            'Azkila',
            'Cindy Artika Devi',
            'Salira Restu Gusti',
            'Andieni Putri',
            'Tika Adela',
            'Salymah Allawiyah',
            'Sindi Putri',
            'Natya Kivany',
            'Anandhita Putri Yoan',
            'Raisa Yaumil Fauziah',
            'Nazwa Aulia Khaira',
        ];

        foreach ($femaleNames as $nama) {
            Personil::where('nama', 'like', "%{$nama}%")->update(['jenis_kelamin' => 'perempuan']);
        }
    }

    public function down(): void
    {
        // No reverse needed
    }
};
