<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AlokasiRuangan;
use App\Models\JadwalAdzanKitab;
use App\Models\JadwalBriefing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint JSON untuk FullCalendar events (DSB-07).
 * Adzan/kajian punya start/end time sesuai waktu sholat riil (Â§2.5).
 * Briefing dan alokasi ruangan tetap all-day (tanpa jam spesifik).
 */
class KalenderController extends Controller
{
    /** Mapping waktu sholat â†’ [start, end] dalam format H:i:s */
    private const JAM_SHOLAT = [
        'dhuhr' => ['12:00:00', '12:30:00'],
        'asr' => ['15:00:00', '15:30:00'],
        'fajr' => ['05:00:00', '05:30:00'],
        'maghrib' => ['18:00:00', '18:30:00'],
        'isha' => ['19:30:00', '20:00:00'],
    ];

    /** Mapping sesi briefing â†’ [start, end] */
    private const JAM_BRIEFING = [
        'pagi' => ['09:00:00', '09:30:00'],
        'sore' => ['16:00:00', '16:30:00'],
    ];

    public function events(Request $request): JsonResponse
    {
        $mulai = $request->input('start');
        $selesai = $request->input('end');
        $timId = $request->integer('tim_id', 0) ?: null;

        $events = collect();

        // â”€â”€ Adzan & Kajian â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $adzanQuery = JadwalAdzanKitab::with('personil.tim')
            ->whereBetween('tanggal', [$mulai, $selesai]);

        if ($timId) {
            $adzanQuery->whereHas('personil', fn ($q) => $q->where('tim_id', $timId));
        }

        $adzanQuery->get()->each(function ($j) use ($events) {
            [$jamMulai, $jamSelesai] = self::JAM_SHOLAT[$j->waktu_sholat] ?? ['12:00:00', '12:30:00'];
            $tanggal = $j->tanggal->toDateString();

            $events->push([
                'id' => 'adzan_'.$j->id,
                'title' => ucfirst($j->jenis_tugas).' '.strtoupper($j->waktu_sholat)
                    .' â€” '.($j->personil?->nama ?? '?'),
                'start' => $tanggal.'T'.$jamMulai,
                'end' => $tanggal.'T'.$jamSelesai,
                'backgroundColor' => '#1591D8',
                'borderColor' => '#0D77B3',
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'jenis' => 'adzan',
                    'personil' => $j->personil?->nama,
                    'tim' => $j->personil?->tim?->nama_tim,
                    'waktu_sholat' => $j->waktu_sholat,
                    'jenis_tugas' => $j->jenis_tugas,
                    'status_konfirmasi' => $j->status_konfirmasi,
                    'jadwal_id' => $j->id,
                ],
            ]);
        });

        // â”€â”€ Briefing â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $briefingQuery = JadwalBriefing::with('personil', 'tim')
            ->whereBetween('tanggal', [$mulai, $selesai]);

        if ($timId) {
            $briefingQuery->where('tim_id', $timId);
        }

        $briefingQuery->get()->each(function ($j) use ($events) {
            [$jamMulai, $jamSelesai] = self::JAM_BRIEFING[$j->sesi] ?? ['09:00:00', '09:30:00'];
            $tanggal = $j->tanggal->toDateString();

            $events->push([
                'id' => 'briefing_'.$j->id,
                'title' => 'Briefing '.ucfirst($j->sesi)
                    .' â€” '.($j->personil?->nama ?? '?'),
                'start' => $tanggal.'T'.$jamMulai,
                'end' => $tanggal.'T'.$jamSelesai,
                'backgroundColor' => '#7C3AED',
                'borderColor' => '#6D28D9',
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'jenis' => 'briefing',
                    'personil' => $j->personil?->nama,
                    'tim' => $j->tim?->nama_tim,
                    'sesi' => $j->sesi,
                    'status_konfirmasi' => $j->status_konfirmasi,
                    'jadwal_id' => $j->id,
                ],
            ]);
        });

        // â”€â”€ Alokasi Ruangan â€” tetap all-day â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $ruanganQuery = AlokasiRuangan::with('tim', 'ruangan')
            ->whereBetween('tanggal', [$mulai, $selesai]);

        if ($timId) {
            $ruanganQuery->where('tim_id', $timId);
        }

        $ruanganQuery->get()->each(function ($j) use ($events) {
            $events->push([
                'id' => 'ruangan_'.$j->id,
                'title' => ($j->ruangan?->nama_ruangan ?? '?')
                    .' â€” '.($j->tim?->nama_tim ?? '?'),
                'start' => $j->tanggal->toDateString(),
                'allDay' => true,
                'backgroundColor' => '#059669',
                'borderColor' => '#047857',
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'jenis' => 'ruangan',
                    'ruangan' => $j->ruangan?->nama_ruangan,
                    'tim' => $j->tim?->nama_tim,
                    'kapasitas' => $j->ruangan?->kapasitas,
                    'jadwal_id' => $j->id,
                ],
            ]);
        });

        return response()->json($events->values());
    }
}
