<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Kegiatan Internal</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #18181b; }
        h1 { font-size: 16px; font-weight: bold; margin-bottom: 2px; }
        h2 { font-size: 13px; font-weight: bold; margin: 16px 0 6px; border-bottom: 2px solid #1976D2; padding-bottom: 3px; color: #1976D2; }
        p.sub { font-size: 10px; color: #71717a; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th { background: #1976D2; color: #fff; padding: 5px 8px; text-align: left; font-size: 10px; }
        td { padding: 4px 8px; border-bottom: 1px solid #e4e4e7; font-size: 10px; }
        tr:nth-child(even) td { background: #f4f4f5; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 9999px; font-size: 9px; font-weight: bold; }
        .badge-menunggu { background: #f4f4f5; color: #71717a; }
        .badge-siap { background: #d1fae5; color: #065f46; }
        .badge-berhalangan { background: #fee2e2; color: #991b1b; }
        .footer { margin-top: 24px; font-size: 9px; color: #a1a1aa; text-align: right; }
    </style>
</head>
<body>

<h1>Jadwal Kegiatan Internal — PT Inovindo Digital Media</h1>
<p class="sub">
    Periode: {{ $mulai->translatedFormat('d F Y') }} s/d {{ $selesai->translatedFormat('d F Y') }}
    &nbsp;|&nbsp; Diekspor: {{ now()->translatedFormat('d F Y, H:i') }}
</p>

@if ($adzan->isNotEmpty())
    <h2>Jadwal Adzan & Kajian</h2>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Hari</th>
                <th>Waktu</th>
                <th>Tugas</th>
                <th>Personil</th>
                <th>Tim</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($adzan as $j)
                <tr>
                    <td>{{ $j->tanggal->format('d/m/Y') }}</td>
                    <td>{{ $j->tanggal->translatedFormat('l') }}</td>
                    <td>{{ strtoupper($j->waktu_sholat) }}</td>
                    <td>{{ ucfirst($j->jenis_tugas) }}</td>
                    <td>{{ $j->personil?->nama ?? '—' }}</td>
                    <td>{{ $j->personil?->tim?->nama_tim ?? '—' }}</td>
                    <td><span class="badge badge-{{ $j->status_konfirmasi }}">{{ ucfirst($j->status_konfirmasi) }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($briefing->isNotEmpty())
    <h2>Jadwal Briefing</h2>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Hari</th>
                <th>Sesi</th>
                <th>Perwakilan</th>
                <th>Tim</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($briefing as $j)
                <tr>
                    <td>{{ $j->tanggal->format('d/m/Y') }}</td>
                    <td>{{ $j->tanggal->translatedFormat('l') }}</td>
                    <td>{{ ucfirst($j->sesi) }}</td>
                    <td>{{ $j->personil?->nama ?? '—' }}</td>
                    <td>{{ $j->tim?->nama_tim ?? '—' }}</td>
                    <td><span class="badge badge-{{ $j->status_konfirmasi }}">{{ ucfirst($j->status_konfirmasi) }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($ruangan->isNotEmpty())
    <h2>Alokasi Ruangan</h2>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Hari</th>
                <th>Tim</th>
                <th>Ruangan</th>
                <th>Kapasitas</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($ruangan as $j)
                <tr>
                    <td>{{ $j->tanggal->format('d/m/Y') }}</td>
                    <td>{{ $j->tanggal->translatedFormat('l') }}</td>
                    <td>{{ $j->tim?->nama_tim ?? '—' }}</td>
                    <td>{{ $j->ruangan?->nama_ruangan ?? '—' }}</td>
                    <td>{{ $j->ruangan?->kapasitas }} orang</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="footer">
    Sistem Manajemen Jadwal Kegiatan Internal — PT Inovindo Digital Media
</div>

</body>
</html>
