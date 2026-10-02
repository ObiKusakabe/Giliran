<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AlokasiRuangan;
use App\Models\JadwalAdzanKitab;
use App\Models\JadwalBriefing;
use App\Models\JadwalWfo;
use App\Models\PeriodeWfo;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint JSON untuk FullCalendar events.
 * Mendukung filter jenis (wfo, adzan, briefing, ruangan) dan tim_id.
 */
class KalenderController extends Controller
{
    /** Mapping waktu sholat -> [start, end] dalam format H:i:s */
    private const JAM_SHOLAT = [
        'dhuhr' => ['12:00:00', '12:30:00'],
        'asr' => ['15:00:00', '15:30:00'],
        'fajr' => ['05:00:00', '05:30:00'],
        'maghrib' => ['18:00:00', '18:30:00'],
        'isha' => ['19:30:00', '20:00:00'],
    ];

    /** Mapping sesi briefing -> [start, end] */
    private const JAM_BRIEFING = [
        'pagi' => ['09:00:00', '09:30:00'],
        'sore' => ['16:00:00', '16:30:00'],
    ];

    public function events(Request $request): JsonResponse
    {
        $mulai = $request->input('start');
        $selesai = $request->input('end');
        $timId = $request->integer('tim_id', 0) ?: null;
        $jenis = strtolower(trim((string) $request->input('jenis', '')));

        $startDate = Carbon::parse($mulai)->startOfDay();
        $endDate = Carbon::parse($selesai)->endOfDay();

        $events = collect();

        // ── 1. Jadwal WFO ───────────────────────────────────────────
        if (empty($jenis) || $jenis === 'all' || $jenis === 'wfo') {
            $periodeList = PeriodeWfo::where('tanggal_mulai', '<=', $endDate->toDateString())
                ->where('tanggal_selesai', '>=', $startDate->toDateString())
                ->get();

            if ($periodeList->isNotEmpty()) {
                $periodeIds = $periodeList->pluck('id')->toArray();
                $wfoQuery = JadwalWfo::with('tim')
                    ->whereIn('periode_wfo_id', $periodeIds)
                    ->whereHas('tim', fn ($q) => $q->where('status', 'active')->whereNull('deleted_at'));

                if ($timId) {
                    $wfoQuery->where('tim_id', $timId);
                }

                $wfoList = $wfoQuery->get()->groupBy('periode_wfo_id');
                $namaHariIndo = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'];

                foreach ($periodeList as $periode) {
                    $jadwalsInPeriode = $wfoList->get($periode->id, collect());
                    if ($jadwalsInPeriode->isEmpty()) {
                        continue;
                    }

                    $pMulai = Carbon::parse($periode->tanggal_mulai)->startOfDay();
                    $pSelesai = Carbon::parse($periode->tanggal_selesai)->endOfDay();

                    $cur = $startDate->gt($pMulai) ? $startDate->copy() : $pMulai->copy();
                    $limit = $endDate->lt($pSelesai) ? $endDate->copy() : $pSelesai->copy();

                    while ($cur->lte($limit)) {
                        $dayOfWeek = $cur->dayOfWeekIso; // 1=Senin .. 7=Minggu
                        if ($dayOfWeek <= 6) {
                            $hariKode = $namaHariIndo[$dayOfWeek - 1] ?? null;
                            $matching = $jadwalsInPeriode->where('hari', $hariKode);

                            foreach ($matching as $wfo) {
                                $tanggal = $cur->toDateString();
                                $events->push([
                                    'id' => 'wfo_'.$wfo->id.'_'.$tanggal,
                                    'title' => 'WFO - '.($wfo->tim?->nama_tim ?? '?'),
                                    'start' => $tanggal,
                                    'allDay' => true,
                                    'backgroundColor' => '#4F46E5',
                                    'borderColor' => '#4338CA',
                                    'textColor' => '#ffffff',
                                    'extendedProps' => [
                                        'jenis' => 'wfo',
                                        'tim' => $wfo->tim?->nama_tim,
                                        'hari' => ucfirst($wfo->hari),
                                        'periode' => $periode->keterangan ?? ($pMulai->format('d M').' - '.$pSelesai->format('d M Y')),
                                        'jadwal_id' => $wfo->id,
                                    ],
                                ]);
                            }
                        }
                        $cur->addDay();
                    }
                }
            }
        }

        // ── 2. Adzan & Kajian ───────────────────────────────────────
        if (empty($jenis) || $jenis === 'all' || $jenis === 'adzan') {
            $adzanQuery = JadwalAdzanKitab::with('personil.tim')
                ->whereBetween('tanggal', [$startDate->toDateString(), $endDate->toDateString()]);

            if ($timId) {
                $adzanQuery->whereHas('personil', fn ($q) => $q->where('tim_id', $timId));
            }

            $adzanQuery->get()->each(function ($j) use ($events) {
                [$jamMulai, $jamSelesai] = self::JAM_SHOLAT[$j->waktu_sholat] ?? ['12:00:00', '12:30:00'];
                $tanggal = $j->tanggal->toDateString();

                $waktuSholatLabel = match ($j->waktu_sholat) {
                    'dhuhr' => 'Zuhur',
                    'asr' => 'Ashar',
                    'fajr' => 'Subuh',
                    'maghrib' => 'Maghrib',
                    'isha' => 'Isya',
                    default => strtoupper($j->waktu_sholat),
                };

                $events->push([
                    'id' => 'adzan_'.$j->id,
                    'title' => ucfirst($j->jenis_tugas).' '.$waktuSholatLabel.' - '.($j->personil?->nama ?? '?'),
                    'start' => $tanggal.'T'.$jamMulai,
                    'end' => $tanggal.'T'.$jamSelesai,
                    'backgroundColor' => '#3B71CA',
                    'borderColor' => '#2d5db3',
                    'textColor' => '#ffffff',
                    'extendedProps' => [
                        'jenis' => 'adzan',
                        'personil' => $j->personil?->nama,
                        'tim' => $j->personil?->tim?->nama_tim,
                        'waktu_sholat' => $waktuSholatLabel,
                        'jenis_tugas' => $j->jenis_tugas,
                        'status_konfirmasi' => $j->status_konfirmasi,
                        'jadwal_id' => $j->id,
                    ],
                ]);
            });
        }

        // ── 3. Briefing ────────────────────────────────────────────
        if (empty($jenis) || $jenis === 'all' || $jenis === 'briefing') {
            $briefingQuery = JadwalBriefing::with('personil', 'tim')
                ->whereBetween('tanggal', [$startDate->toDateString(), $endDate->toDateString()]);

            if ($timId) {
                $briefingQuery->where('tim_id', $timId);
            }

            $briefingQuery->get()->each(function ($j) use ($events) {
                [$jamMulai, $jamSelesai] = self::JAM_BRIEFING[$j->sesi] ?? ['09:00:00', '09:30:00'];
                $tanggal = $j->tanggal->toDateString();

                $events->push([
                    'id' => 'briefing_'.$j->id,
                    'title' => 'Briefing '.ucfirst($j->sesi).' - '.($j->personil?->nama ?? '?'),
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
        }

        // ── 4. Alokasi Ruangan ─────────────────────────────────────
        if (empty($jenis) || $jenis === 'all' || $jenis === 'ruangan') {
            $ruanganQuery = AlokasiRuangan::with('tim', 'ruangan')
                ->whereBetween('tanggal', [$startDate->toDateString(), $endDate->toDateString()]);

            if ($timId) {
                $ruanganQuery->where('tim_id', $timId);
            }

            $ruanganQuery->get()->each(function ($j) use ($events) {
                $events->push([
                    'id' => 'ruangan_'.$j->id,
                    'title' => ($j->ruangan?->nama_ruangan ?? '?').' - '.($j->tim?->nama_tim ?? '?'),
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
        }

        return response()->json($events->values());
    }
}
