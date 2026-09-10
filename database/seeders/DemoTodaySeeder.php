<?php

namespace Database\Seeders;

use App\Models\AlokasiRuangan;
use App\Models\JadwalAdzanKitab;
use App\Models\JadwalBriefing;
use App\Models\Notifikasi;
use App\Models\PeriodeWfo;
use App\Models\Personil;
use App\Models\Ruangan;
use App\Models\Tim;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoTodaySeeder extends Seeder
{
    /**
     * Seed data untuk demo hari ini.
     * Generate jadwal untuk hari ini & besok agar notifikasi H-1 bisa jalan.
     */
    public function run(): void
    {
        $periode = PeriodeWfo::where('status', 'aktif')->first();

        if (! $periode) {
            $this->command->error('❌ Tidak ada periode WFO aktif!');
            $this->command->info('💡 Run: php artisan db:seed --class=PeriodeWfoSeeder');

            return;
        }

        // Cek tim & personil
        $timCount = Tim::where('status', 'active')->count();
        $personilCount = Personil::where('status', 'aktif')->count();

        if ($timCount < 3 || $personilCount < 10) {
            $this->command->error('❌ Data tim/personil kurang!');
            $this->command->info('💡 Run: php artisan db:seed --class=TimSeeder');
            $this->command->info('💡 Run: php artisan db:seed --class=PersonilSeeder');

            return;
        }

        $this->command->info('🚀 Seeding data untuk demo...');

        // Tanggal: Hari ini & besok (untuk notifikasi H-1)
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        $this->command->info("📅 Hari ini: {$today->format('d M Y')}");
        $this->command->info("📅 Besok: {$tomorrow->format('d M Y')}");

        // Hapus jadwal existing untuk hari ini & besok (avoid duplicate)
        JadwalBriefing::whereIn('tanggal', [$today, $tomorrow])->delete();
        JadwalAdzanKitab::whereIn('tanggal', [$today, $tomorrow])->delete();
        AlokasiRuangan::whereIn('tanggal', [$today, $tomorrow])->delete();

        // Ambil tim aktif (max 5 tim untuk demo)
        $tims = Tim::where('status', 'active')->limit(5)->get();

        // Ambil ruangan
        $ruangans = Ruangan::where('status', 'tersedia')->get();

        foreach ([$today, $tomorrow] as $tanggal) {
            $this->command->info("⏰ Generate jadwal untuk: {$tanggal->format('d M Y')}");

            foreach (['pagi', 'sore'] as $sesi) {
                $perwakilanIds = [];

                foreach ($tims as $tim) {
                    // Ambil 1 personil random dari tim ini
                    $personil = Personil::where('tim_id', $tim->id)
                        ->where('status', 'aktif')
                        ->inRandomOrder()
                        ->first();

                    if (! $personil) {
                        continue;
                    }

                    $perwakilanIds[] = $personil->id;

                    // Insert perwakilan briefing
                    JadwalBriefing::create([
                        'tim_id' => $tim->id,
                        'personil_id' => $personil->id,
                        'tanggal' => $tanggal,
                        'sesi' => $sesi,
                        'status_konfirmasi' => 'menunggu',
                        'is_notulen' => false, // Default
                    ]);
                }

                // Pilih 1 notulensi dari perwakilan (random untuk demo)
                if (! empty($perwakilanIds)) {
                    $notulenId = $perwakilanIds[array_rand($perwakilanIds)];

                    JadwalBriefing::where('personil_id', $notulenId)
                        ->where('tanggal', $tanggal)
                        ->where('sesi', $sesi)
                        ->update(['is_notulen' => true]);

                    $this->command->info("  🖊️  Notulensi {$sesi}: Personil ID {$notulenId}");
                }
            }

            // Generate adzan/kajian (untuk personil laki-laki)
            $lakiLaki = Personil::where('status', 'aktif')
                ->where('jenis_kelamin', 'laki-laki')
                ->inRandomOrder()
                ->limit(4)
                ->get();

            if ($lakiLaki->count() >= 4) {
                $slots = $tanggal->dayOfWeekIso === 5 ? ['asr'] : ['dhuhr', 'asr'];

                $idx = 0;
                foreach ($slots as $slot) {
                    foreach (['adzan', 'kajian'] as $jenis) {
                        if ($idx >= $lakiLaki->count()) {
                            break 2;
                        }

                        JadwalAdzanKitab::create([
                            'personil_id' => $lakiLaki[$idx]->id,
                            'tanggal' => $tanggal,
                            'waktu_sholat' => $slot,
                            'jenis_tugas' => $jenis,
                            'status_konfirmasi' => 'menunggu',
                        ]);

                        $idx++;
                    }
                }

                $this->command->info("  📖 Adzan/Kajian: {$lakiLaki->count()} slot");
            }

            // Alokasi ruangan per tim
            if ($ruangans->isNotEmpty()) {
                foreach ($tims as $idx => $tim) {
                    if ($idx >= $ruangans->count()) {
                        break;
                    }

                    AlokasiRuangan::create([
                        'tim_id' => $tim->id,
                        'ruangan_id' => $ruangans[$idx]->id,
                        'tanggal' => $tanggal,
                    ]);
                }

                $this->command->info("  🏢 Alokasi Ruangan: {$tims->count()} tim");
            }
        }

        $this->command->newLine();
        $this->command->info('✅ Demo data seeded successfully!');
        $this->command->newLine();
        $this->command->info('📊 Summary:');
        $this->command->table(
            ['Jenis', 'Hari Ini', 'Besok'],
            [
                [
                    'Briefing',
                    JadwalBriefing::whereDate('tanggal', $today)->count(),
                    JadwalBriefing::whereDate('tanggal', $tomorrow)->count(),
                ],
                [
                    'Adzan/Kajian',
                    JadwalAdzanKitab::whereDate('tanggal', $today)->count(),
                    JadwalAdzanKitab::whereDate('tanggal', $tomorrow)->count(),
                ],
                [
                    'Alokasi Ruangan',
                    AlokasiRuangan::whereDate('tanggal', $today)->count(),
                    AlokasiRuangan::whereDate('tanggal', $tomorrow)->count(),
                ],
            ]
        );

        $this->command->newLine();
        $this->command->info('🔔 Trigger notifikasi H-1:');
        $this->command->warn('   php artisan notifikasi:h1');
    }
}
