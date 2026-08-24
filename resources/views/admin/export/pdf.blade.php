<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Kegiatan Internal</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #18181b; }
        h1 { font-size: 15px; font-weight: bold; margin-bottom: 2px; }
        h2 { font-size: 12px; font-weight: bold; margin: 16px 0 5px; border-bottom: 2px solid #3B71CA; padding-bottom: 3px; color: #3B71CA; }
        p.sub { font-size: 9px; color: #71717a; margin-bottom: 14px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #d4d4d8; padding: 4px 6px; font-size: 9px; }
        th { background: #3B71CA; color: #fff; text-align: center; font-weight: bold; }
        th.left { text-align: left; }
        td { text-align: left; }
        td.center { text-align: center; }

        /* Zebra striping */
        tbody tr:nth-child(even) td { background: #f4f4f5; }

        /* Group header Zuhur/Ashar */
        .th-group { background: #2d5db3; font-size: 10px; }
        .th-sub { background: #3B71CA; font-size: 9px; }

        .badge { display: inline-block; padding: 1px 5px; border-radius: 9999px; font-size: 8px; font-weight: bold; }
        .badge-menunggu { background: #f4f4f5; color: #71717a; }
        .badge-siap { background: #d1fae5; color: #065f46; }
        .badge-berhalangan { background: #fee2e2; color: #991b1b; }

        .footer { margin-top: 24px; font-size: 8px; color: #a1a1aa; text-align: right; }
    </style>
</head>
<body>

<h1>Jadwal Kegiatan Internal â€” PT Inovindo Digital Media</h1>
<p class="sub">
    Periode: {{ $mulai->translatedFormat('d F Y') }} s/d {{ $selesai->translatedFormat('d F Y') }}
    &nbsp;|&nbsp; Diekspor: {{ now()->translatedFormat('d F Y, H:i') }}
</p>

{{-- â”€â”€ JADWAL ADZAN & KAJIAN â€” Format Inovindo: 2-level header, 1 baris = 1 tanggal â”€â”€ --}}
@if ($adzan->isNotEmpty())
    <h2>Jadwal Petugas Adzan & Pembacaan Kitab Zuhur dan Ashar</h2>

    @php
        /**
         * Group data adzan per tanggal.
         * Tiap tanggal bisa punya: dhuhr_adzan, dhuhr_kajian, asr_adzan, asr_kajian
         */
        $grouped = $adzan->groupBy(fn($j) => $j->tanggal->toDateString())
            ->map(function ($rows) {
                $map = [];
                foreach ($rows as $r) {
                    $key = $r->waktu_sholat . '_' . $r->jenis_tugas; // mis. dhuhr_adzan
                    $map[$key] = $r;
                }
                return $map;
            });
    @endphp

    <table>
        <thead>
            <tr>
                <th class="th-group left" rowspan="2" style="width:12%">Hari, Tanggal</th>
                <th class="th-group left" rowspan="2" style="width:8%">Hari</th>
                <th class="th-group" colspan="2" style="width:30%">Zuhur</th>
                <th class="th-group" colspan="2" style="width:30%">Ashar</th>
            </tr>
            <tr>
                <th class="th-sub" style="width:15%">Adzan</th>
                <th class="th-sub" style="width:15%">Pembacaan Kitab</th>
                <th class="th-sub" style="width:15%">Adzan</th>
                <th class="th-sub" style="width:15%">Pembacaan Kitab</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($grouped as $tgl => $slots)
                @php
                    $carbon       = \Carbon\Carbon::parse($tgl);
                    $dhuhrAdzan   = $slots['dhuhr_adzan']   ?? null;
                    $dhuhrKajian  = $slots['dhuhr_kajian']  ?? null;
                    $asrAdzan     = $slots['asr_adzan']     ?? null;
                    $asrKajian    = $slots['asr_kajian']    ?? null;

                    // Helper untuk format nama + tim
                    $fmt = fn($row) => $row
                        ? ($row->personil?->nama ?? 'â€”') . "\n(" . ($row->personil?->tim?->nama_tim ?? 'â€”') . ")"
                        : 'â€”';
                @endphp
                <tr>
                    <td>{{ $carbon->format('d F Y') }}</td>
                    <td class="center" style="font-weight:bold;text-transform:uppercase">
                        {{ $carbon->translatedFormat('l') }}
                    </td>
                    <td style="white-space:pre-line">{{ $fmt($dhuhrAdzan) }}</td>
                    <td style="white-space:pre-line">{{ $fmt($dhuhrKajian) }}</td>
                    <td style="white-space:pre-line">{{ $fmt($asrAdzan) }}</td>
                    <td style="white-space:pre-line">{{ $fmt($asrKajian) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- â”€â”€ JADWAL BRIEFING â”€â”€ --}}
@if ($briefing->isNotEmpty())
    <h2>Jadwal Briefing</h2>
    <table>
        <thead>
            <tr>
                <th class="left" style="width:12%">Tanggal</th>
                <th class="left" style="width:10%">Hari</th>
                <th class="left" style="width:8%">Sesi</th>
                <th class="left" style="width:25%">Perwakilan</th>
                <th class="left" style="width:25%">Tim</th>
                <th class="center" style="width:10%">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($briefing as $j)
                <tr>
                    <td>{{ $j->tanggal->format('d/m/Y') }}</td>
                    <td style="font-weight:bold;text-transform:uppercase">{{ $j->tanggal->translatedFormat('l') }}</td>
                    <td>{{ ucfirst($j->sesi) }}</td>
                    <td>{{ $j->personil?->nama ?? 'â€”' }}</td>
                    <td>{{ $j->tim?->nama_tim ?? 'â€”' }}</td>
                    <td class="center">
                        <span class="badge badge-{{ $j->status_konfirmasi }}">{{ ucfirst($j->status_konfirmasi) }}</span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- â”€â”€ ALOKASI RUANGAN â”€â”€ --}}
@if ($ruangan->isNotEmpty())
    <h2>Alokasi Ruangan</h2>
    <table>
        <thead>
            <tr>
                <th class="left" style="width:12%">Tanggal</th>
                <th class="left" style="width:10%">Hari</th>
                <th class="left" style="width:30%">Tim</th>
                <th class="left" style="width:25%">Ruangan</th>
                <th class="center" style="width:13%">Kapasitas</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($ruangan as $j)
                <tr>
                    <td>{{ $j->tanggal->format('d/m/Y') }}</td>
                    <td style="font-weight:bold;text-transform:uppercase">{{ $j->tanggal->translatedFormat('l') }}</td>
                    <td>{{ $j->tim?->nama_tim ?? 'â€”' }}</td>
                    <td>{{ $j->ruangan?->nama_ruangan ?? 'â€”' }}</td>
                    <td class="center">{{ $j->ruangan?->kapasitas }} orang</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="footer">
    Sistem Manajemen Jadwal Kegiatan Internal â€” PT Inovindo Digital Media
</div>

</body>
</html>


