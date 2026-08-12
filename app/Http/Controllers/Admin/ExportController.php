<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AlokasiRuangan;
use App\Models\JadwalAdzanKitab;
use App\Models\JadwalBriefing;
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
        $request->validate([
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'jenis' => 'required|in:semua,adzan,briefing,ruangan',
        ]);

        $mulai = Carbon::parse($request->tanggal_mulai);
        $selesai = Carbon::parse($request->tanggal_selesai);
        $jenis = $request->jenis;

        $adzan = $jenis === 'semua' || $jenis === 'adzan'
            ? JadwalAdzanKitab::with('personil.tim')
                ->whereBetween('tanggal', [$mulai, $selesai])
                ->orderBy('tanggal')->orderBy('waktu_sholat')
                ->get()
            : collect();

        $briefing = $jenis === 'semua' || $jenis === 'briefing'
            ? JadwalBriefing::with('personil', 'tim')
                ->whereBetween('tanggal', [$mulai, $selesai])
                ->orderBy('tanggal')->orderBy('sesi')
                ->get()
            : collect();

        $ruangan = $jenis === 'semua' || $jenis === 'ruangan'
            ? AlokasiRuangan::with('tim', 'ruangan')
                ->whereBetween('tanggal', [$mulai, $selesai])
                ->orderBy('tanggal')
                ->get()
            : collect();

        $pdf = Pdf::loadView('admin.export.pdf', compact(
            'adzan', 'briefing', 'ruangan', 'mulai', 'selesai', 'jenis'
        ))->setPaper('a4', 'portrait');

        $filename = 'jadwal-'.$mulai->format('Ymd').'-'.$selesai->format('Ymd').'.pdf';

        return $pdf->download($filename);
    }
}
