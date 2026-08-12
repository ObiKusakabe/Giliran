<?php

use App\Console\Commands\KirimNotifikasiH1;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// NOT-01: Notifikasi H-1 dikirim otomatis setiap hari pukul 06:00
// Cron di server: * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
Schedule::command(KirimNotifikasiH1::class)->dailyAt('06:00');
