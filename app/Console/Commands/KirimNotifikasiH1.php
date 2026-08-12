<?php

namespace App\Console\Commands;

use App\Models\JadwalAdzanKitab;
use App\Models\JadwalBriefing;
use App\Models\Notifikasi;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Kirim notifikasi H-1 ke personil yang bertugas besok.
 * Dijadwalkan jalan tiap hari pukul 06:00 via Laravel Scheduler.
 *
 * Setup cron di server:
 *   * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
 */
class KirimNotifikasiH1 extends Command
{
    protected $signature = 'notifikasi:h1';

    protected $description = 'Kirim notifikasi H-1 ke personil yang bertugas besok';

    public function handle(): int
    {
        $besok = Carbon::tomorrow()->toDateString();

        $this->info("Mengirim notifikasi H-1 untuk tanggal: {$besok}");

        $totalDikirim = 0;

        // Notifikasi jadwal adzan/kajian
        $adzanBesok = JadwalAdzanKitab::with('personil.user')
            ->where('tanggal', $besok)
            ->where('status_konfirmasi', 'menunggu')
            ->get();

        foreach ($adzanBesok as $jadwal) {
            $personil = $jadwal->personil;

            if (! $personil) {
                continue;
            }

            // Hindari duplikat notifikasi di hari yang sama
            $sudahAda = Notifikasi::where('personil_id', $personil->id)
                ->where('tipe', 'jadwal')
                ->where('data_id', $jadwal->id)
                ->exists();

            if ($sudahAda) {
                continue;
            }

            Notifikasi::create([
                'personil_id' => $personil->id,
                'user_id' => $personil->user?->id,
                'tipe' => 'jadwal',
                'pesan' => "Kamu bertugas {$jadwal->jenis_tugas} "
                    .strtoupper($jadwal->waktu_sholat)
                    .' besok, '.Carbon::parse($besok)->translatedFormat('l d M Y').'.',
                'data_id' => $jadwal->id,
                'dibaca' => false,
                'terkirim_pada' => now(),
            ]);

            $totalDikirim++;
        }

        // Notifikasi jadwal briefing
        $briefingBesok = JadwalBriefing::with('personil.user', 'tim')
            ->where('tanggal', $besok)
            ->where('status_konfirmasi', 'menunggu')
            ->get();

        foreach ($briefingBesok as $jadwal) {
            $personil = $jadwal->personil;

            if (! $personil) {
                continue;
            }

            $sudahAda = Notifikasi::where('personil_id', $personil->id)
                ->where('tipe', 'jadwal')
                ->where('data_id', $jadwal->id)
                ->exists();

            if ($sudahAda) {
                continue;
            }

            Notifikasi::create([
                'personil_id' => $personil->id,
                'user_id' => $personil->user?->id,
                'tipe' => 'jadwal',
                'pesan' => "Kamu mewakili {$jadwal->tim->nama_tim} untuk briefing "
                    .ucfirst($jadwal->sesi)
                    .' besok, '.Carbon::parse($besok)->translatedFormat('l d M Y').'.',
                'data_id' => $jadwal->id,
                'dibaca' => false,
                'terkirim_pada' => now(),
            ]);

            $totalDikirim++;
        }

        $this->info("Selesai. Total notifikasi terkirim: {$totalDikirim}");

        return self::SUCCESS;
    }
}
