<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AlokasiRuangan;
use App\Models\JadwalAdzanKitab;
use App\Models\JadwalBriefing;
use App\Models\JadwalWfo;
use App\Models\PeriodeWfo;
use App\Models\Ruangan;
use App\Models\Tim;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ExportController extends Controller
{
    public function form(): View
    {
        $daftarPeriode = PeriodeWfo::orderByDesc('tanggal_mulai')->get();
        $periodeAktif = PeriodeWfo::where('status', 'aktif')->first();

        return view('admin.export.form', compact('daftarPeriode', 'periodeAktif'));
    }

    public function pdf(Request $request): Response
    {
        $periodeAktif = PeriodeWfo::where('status', 'aktif')->first();

        $periode = null;
        if ($request->filled('periode_wfo_id')) {
            $periode = PeriodeWfo::find($request->input('periode_wfo_id'));
        }

        $isCustomTanggal = $request->boolean('custom_tanggal');

        if ($periode && ! $isCustomTanggal && ! ($request->filled('tanggal_mulai') && ! $request->filled('periode_wfo_id'))) {
            $mulai = $periode->tanggal_mulai ? Carbon::parse($periode->tanggal_mulai) : now()->startOfMonth();
            $selesai = $periode->tanggal_selesai ? Carbon::parse($periode->tanggal_selesai) : now()->endOfMonth();
        } else {
            $tglMulai = $request->input('tanggal_mulai')
                ?? $periode?->tanggal_mulai?->format('Y-m-d')
                ?? $periodeAktif?->tanggal_mulai?->format('Y-m-d')
                ?? now()->startOfMonth()->format('Y-m-d');

            $tglSelesai = $request->input('tanggal_selesai')
                ?? $periode?->tanggal_selesai?->format('Y-m-d')
                ?? $periodeAktif?->tanggal_selesai?->format('Y-m-d')
                ?? now()->endOfMonth()->format('Y-m-d');

            $mulai = Carbon::parse($tglMulai);
            $selesai = Carbon::parse($tglSelesai);

            $periode = $periode ?? PeriodeWfo::where('tanggal_mulai', '<=', $selesai)
                ->where('tanggal_selesai', '>=', $mulai)
                ->first() ?? $periodeAktif;
        }

        $jenis = $request->input('jenis');

        if ($jenis === 'wfo') {
            $includeSurat = $request->boolean('include_surat', true);
            $includeWfo = true;
            $includeKelompok = $request->boolean('include_kelompok', true);
            $includeAdzan = false;
            $includeBriefing = false;
            $includeRuangan = false;
        } elseif ($jenis === 'adzan') {
            $includeSurat = false;
            $includeWfo = false;
            $includeKelompok = false;
            $includeAdzan = true;
            $includeBriefing = false;
            $includeRuangan = false;
        } elseif ($jenis === 'briefing') {
            $includeSurat = false;
            $includeWfo = false;
            $includeKelompok = false;
            $includeAdzan = false;
            $includeBriefing = true;
            $includeRuangan = false;
        } elseif ($jenis === 'ruangan') {
            $includeSurat = false;
            $includeWfo = false;
            $includeKelompok = false;
            $includeAdzan = false;
            $includeBriefing = false;
            $includeRuangan = true;
        } elseif ($jenis === 'semua' && $request->isMethod('get')) {
            $includeSurat = true;
            $includeWfo = true;
            $includeKelompok = true;
            $includeAdzan = true;
            $includeBriefing = true;
            $includeRuangan = true;
        } else {
            // POST request or form submission with custom checkboxes
            $hasAnyInclude = $request->hasAny(['include_surat', 'include_wfo', 'include_kelompok', 'include_adzan', 'include_briefing', 'include_ruangan']);

            if ($request->isMethod('post') || $hasAnyInclude || $request->boolean('from_modal')) {
                $includeSurat = $request->boolean('include_surat');
                $includeWfo = $request->boolean('include_wfo');
                $includeKelompok = $request->boolean('include_kelompok');
                $includeAdzan = $request->boolean('include_adzan');
                $includeBriefing = $request->boolean('include_briefing');
                $includeRuangan = $request->boolean('include_ruangan');
            } else {
                $includeSurat = true;
                $includeWfo = true;
                $includeKelompok = true;
                $includeAdzan = true;
                $includeBriefing = true;
                $includeRuangan = true;
            }
        }

        // 1. Data WFO Mingguan (Lampiran 1)
        $jadwalWfo = $includeWfo
            ? JadwalWfo::with('tim')
                ->when($periode, fn ($q) => $q->where('periode_wfo_id', $periode->id))
                ->get()
                ->groupBy('hari')
            : collect();

        // 2. Data Kelompok Tim & Personil (Lampiran 2)
        $semuaTim = $includeKelompok
            ? Tim::with(['personil' => fn ($q) => $q->where('status', 'aktif')])
                ->where('status', 'active')
                ->orderBy('nama_tim')
                ->get()
            : collect();

        // 3. Data Adzan & Kitab
        $adzan = $includeAdzan
            ? JadwalAdzanKitab::with('personil.tim')
                ->whereBetween('tanggal', [$mulai, $selesai])
                ->orderBy('tanggal')
                ->get()
            : collect();

        // 4. Data Briefing
        $briefing = $includeBriefing
            ? JadwalBriefing::with(['personil', 'tim', 'moderator', 'doa', 'originalPersonil'])
                ->whereBetween('tanggal', [$mulai, $selesai])
                ->orderBy('tanggal')->orderBy('sesi')
                ->get()
            : collect();

        // 5. Data Ruangan
        $dataRuangan = $includeRuangan
            ? AlokasiRuangan::with(['tim', 'ruangan'])
                ->whereBetween('tanggal', [$mulai, $selesai])
                ->orderBy('tanggal')
                ->get()
            : collect();

        $semuaRuangan = $includeRuangan
            ? Ruangan::where('status', 'tersedia')->orderBy('nama_ruangan')->get()
            : collect();

        $pdf = Pdf::loadView('admin.export.pdf', compact(
            'adzan', 'briefing', 'dataRuangan', 'semuaRuangan', 'jadwalWfo', 'semuaTim', 'periode', 'mulai', 'selesai', 'jenis',
            'includeSurat', 'includeWfo', 'includeKelompok', 'includeAdzan', 'includeBriefing', 'includeRuangan'
        ))->setPaper('a4', 'portrait');

        $filename = 'jadwal-inovindo-'.$mulai->format('Ymd').'-'.$selesai->format('Ymd').'.pdf';

        return $pdf->download($filename);
    }
}
