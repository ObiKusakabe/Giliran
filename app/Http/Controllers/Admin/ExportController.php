<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AlokasiRuangan;
use App\Models\JadwalAdzanKitab;
use App\Models\JadwalBriefing;
use App\Models\JadwalWfo;
use App\Models\PeriodeWfo;
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
        return view('admin.export.form');
    }

    public function pdf(Request $request): Response
    {
        $periodeAktif = PeriodeWfo::where('status', 'aktif')->first();

        $tglMulai = $request->input('tanggal_mulai')
            ?? $periodeAktif?->tanggal_mulai?->format('Y-m-d')
            ?? now()->startOfMonth()->format('Y-m-d');

        $tglSelesai = $request->input('tanggal_selesai')
            ?? $periodeAktif?->tanggal_selesai?->format('Y-m-d')
            ?? now()->endOfMonth()->format('Y-m-d');

        $jenis = $request->input('jenis', 'semua');

        $mulai = Carbon::parse($tglMulai);
        $selesai = Carbon::parse($tglSelesai);

        $periode = PeriodeWfo::where('tanggal_mulai', '<=', $selesai)
            ->where('tanggal_selesai', '>=', $mulai)
            ->first() ?? $periodeAktif;

        // 1. Data WFO Mingguan (Lampiran 1)
        $jadwalWfo = JadwalWfo::with('tim')
            ->when($periode, fn ($q) => $q->where('periode_wfo_id', $periode->id))
            ->get()
            ->groupBy('hari');

        // 2. Data Kelompok Tim & Personil (Lampiran 2)
        $semuaTim = Tim::with(['personil' => fn ($q) => $q->where('status', 'aktif')])
            ->where('status', 'active')
            ->orderBy('nama_tim')
            ->get();

        // 3. Data Adzan & Kitab
        $adzan = ($jenis === 'semua' || $jenis === 'adzan')
            ? JadwalAdzanKitab::with('personil.tim')
                ->whereBetween('tanggal', [$mulai, $selesai])
                ->orderBy('tanggal')
                ->get()
            : collect();

        // 4. Data Briefing
        $briefing = ($jenis === 'semua' || $jenis === 'briefing')
            ? JadwalBriefing::with('personil', 'tim')
                ->whereBetween('tanggal', [$mulai, $selesai])
                ->orderBy('tanggal')->orderBy('sesi')
                ->get()
            : collect();

        // 5. Data Ruangan
        $ruangan = ($jenis === 'semua' || $jenis === 'ruangan')
            ? AlokasiRuangan::with('tim', 'ruangan')
                ->whereBetween('tanggal', [$mulai, $selesai])
                ->orderBy('tanggal')
                ->get()
            : collect();

        $pdf = Pdf::loadView('admin.export.pdf', compact(
            'adzan', 'briefing', 'ruangan', 'jadwalWfo', 'semuaTim', 'periode', 'mulai', 'selesai', 'jenis'
        ))->setPaper('a4', 'portrait');

        $filename = 'jadwal-inovindo-'.$mulai->format('Ymd').'-'.$selesai->format('Ymd').'.pdf';

        return $pdf->download($filename);
    }
}
