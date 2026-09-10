@php
    $logoPath = public_path('images/inovindo-logo.png');
    $logoBase64 = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal WFO & Petugas Internal — PT Inovindo Digital Media</title>
    <style>
        @page {
            margin: 18mm 18mm 30mm 18mm; /* Increased bottom margin for footer */
            size: a4 portrait;
        }

        body {
            font-family: 'Arial MT', 'Arial', 'Helvetica', sans-serif;
            font-size: 10pt;
            color: #000000;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }

        /* Footer Watermark */
        .pdf-footer {
            position: fixed;
            bottom: 5mm;
            left: 0;
            right: 0;
            height: 18mm;
            text-align: center;
            font-size: 8pt;
            color: #666666;
            border-top: 1px solid #cccccc;
            padding-top: 6px;
            font-family: 'Times New Roman', Times, Georgia, serif;
        }

        .pdf-footer .watermark-text {
            font-weight: bold;
            color: #333333;
            font-family: 'Times New Roman', Times, Georgia, serif;
        }

        .pdf-footer .page-number:before {
            content: counter(page);
        }

        .page-break {
            page-break-after: always;
        }

        /* Kop Surat Header (Times New Roman) */
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2.5px solid #000000;
            padding-bottom: 8px;
            margin-bottom: 22px;
            font-family: 'Times New Roman', Times, Georgia, serif !important;
        }

        .kop-table td {
            font-family: 'Times New Roman', Times, Georgia, serif !important;
        }

        .kop-logo {
            width: 38%;
            vertical-align: middle;
            text-align: left;
        }

        .kop-logo-img {
            max-height: 42px;
            width: auto;
        }

        .kop-brand-text {
            font-size: 16pt;
            font-weight: 900;
            color: #3B71CA;
            letter-spacing: -0.5px;
            font-family: 'Times New Roman', Times, serif;
        }
        .kop-brand-sub {
            font-size: 8pt;
            font-weight: bold;
            color: #2FA84F;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            font-family: 'Times New Roman', Times, serif;
        }

        .kop-address {
            width: 62%;
            vertical-align: middle;
            text-align: right;
            font-size: 8.5pt;
            color: #111111;
            line-height: 1.35;
            font-family: 'Times New Roman', Times, Georgia, serif !important;
        }

        .kop-company {
            font-size: 10.5pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 2px;
            color: #000000;
            font-family: 'Times New Roman', Times, Georgia, serif !important;
        }

        /* Typography & Paragraphs (Arial MT) */
        p {
            margin: 0 0 12px 0;
            text-align: justify;
            font-family: 'Arial MT', 'Arial', 'Helvetica', sans-serif;
        }

        .title-lampiran {
            text-align: center;
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 8px;
            margin-bottom: 4px;
            font-family: 'Arial MT', 'Arial', 'Helvetica', sans-serif;
        }

        .subtitle-lampiran {
            text-align: center;
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 4px;
            font-family: 'Arial MT', 'Arial', 'Helvetica', sans-serif;
        }

        .periode-lampiran {
            text-align: center;
            font-size: 9pt;
            margin-bottom: 20px;
            font-family: 'Arial MT', 'Arial', 'Helvetica', sans-serif;
        }

        /* Tabel WFO & Kelompok (Blue Headers) */
        .table-cyan {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            font-family: 'Arial MT', 'Arial', 'Helvetica', sans-serif;
        }

        .table-cyan th {
            background-color: #0000FF;
            color: #ffffff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9pt;
            padding: 7px 8px;
            border: 1px solid #000000;
            text-align: left;
            font-family: 'Arial MT', 'Arial', 'Helvetica', sans-serif;
        }

        .table-cyan td {
            border: 1px solid #000000;
            padding: 5.5px 8px;
            font-size: 8.5pt;
            vertical-align: middle;
            font-family: 'Arial MT', 'Arial', 'Helvetica', sans-serif;
        }

        /* Tabel Adzan (Blue Headers) */
        .table-blue {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            font-family: 'Arial MT', 'Arial', 'Helvetica', sans-serif;
        }

        .table-blue th {
            background-color: #0000FF;
            color: #ffffff;
            font-weight: bold;
            font-size: 8.5pt;
            padding: 6px 4px;
            border: 1px solid #000000;
            text-align: center;
            font-family: 'Arial MT', 'Arial', 'Helvetica', sans-serif;
        }

        .table-blue td {
            border: 1px solid #000000;
            padding: 5px 6px;
            font-size: 8pt;
            vertical-align: middle;
            font-family: 'Arial MT', 'Arial', 'Helvetica', sans-serif;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }

        .keterangan-box {
            font-size: 8.5pt;
            line-height: 1.5;
            margin-top: 14px;
            font-family: 'Arial MT', 'Arial', 'Helvetica', sans-serif;
        }
    </style>
</head>
<body>

<!-- Footer Watermark - Appears on every page -->
<div class="pdf-footer">
    <div class="watermark-text">Website Giliran</div>
    <div>Sistem Manajemen Jadwal PT Inovindo Digital Media</div>
    <div>Halaman <span class="page-number"></span> | Generated: {{ now()->translatedFormat('d F Y, H:i') }} WIB</div>
</div>

{{-- ========================================================================= --}}
{{-- HALAMAN 1: SURAT RESMI PEMBERITAHUAN JADWAL WFO                            --}}
{{-- ========================================================================= --}}
@if ($jenis === 'semua' || $jenis === 'ruangan' || $jenis === 'wfo')
    {{-- Kop Surat Inovindo (Times New Roman & PNG Logo) --}}
    <table class="kop-table">
        <tr>
            <td class="kop-logo">
                @if ($logoBase64)
                    <img src="{{ $logoBase64 }}" class="kop-logo-img" alt="Inovindo Logo">
                @else
                    <div class="kop-brand-text">Inovindo</div>
                    <div class="kop-brand-sub">digital - media</div>
                @endif
            </td>
            <td class="kop-address">
                <div class="kop-company">PT INOVINDO DIGITAL MEDIA</div>
                <div>Komplek Buana Citra Ciwastra No. D3, Kab. Bandung</div>
                <div>WhatsApp. 08562251196 Website www.inovindo.com</div>
            </td>
        </tr>
    </table>

    <table style="width:100%; margin-bottom: 22px; font-size: 9.5pt;">
        <tr>
            <td style="width: 12%;">Nomor</td>
            <td style="width: 2%;">:</td>
            <td style="width: 46%;">-</td>
            <td style="width: 40%; text-align: right;">Bandung, {{ $mulai->translatedFormat('d F Y') }}</td>
        </tr>
        <tr>
            <td>Lampiran</td>
            <td>:</td>
            <td colspan="2">2 (dua) Berkas</td>
        </tr>
        <tr>
            <td>Perihal</td>
            <td>:</td>
            <td colspan="2"><strong>Pemberitahuan Jadwal WFO</strong></td>
        </tr>
    </table>

    <div style="margin-bottom: 20px; font-size: 9.5pt;">
        <div>Kepada Yth,</div>
        <div style="font-weight: bold;">Seluruh Peserta PKL dan Magang</div>
        <div>PT Inovindo Digital Media</div>
        <div>di Tempat</div>
    </div>

    <div style="font-size: 9.5pt;">
        <p>Dengan hormat,</p>
        <p>
            Sehubungan dengan bertambahnya peserta PKL di PT Inovindo Digital Media, kami bermaksud untuk memberitahukan jadwal kerja WFO (Work From Office) yang akan berlaku mulai tanggal {{ $mulai->translatedFormat('d F') }} s.d {{ $selesai->translatedFormat('d F Y') }}.
        </p>
        <p>
            Perubahan ini dilakukan dengan tujuan untuk memberikan fleksibilitas lebih bagi para peserta PKL/magang dalam menyelesaikan tugas-tugas yang diberikan, serta untuk mendukung upaya perusahaan dalam menjaga produktivitas dan efisiensi kerja selama periode magang. Jadwal telah dibuat dinamis agar setiap institusi dapat bergiliran dan berkesempatan saling bertemu. Bersama ini kami lampirkan jadwal WFO untuk peserta PKL/magang.
        </p>
        <p>
            Kami mengharapkan kerjasama dan pengertian dari seluruh peserta PKL/magang dalam melaksanakan jadwal baru ini. Atas perhatian dan kerjasamanya, kami ucapkan terima kasih.
        </p>
    </div>

    <table style="width: 100%; margin-top: 50px; font-size: 9.5pt;">
        <tr>
            <td style="width: 60%;"></td>
            <td style="width: 40%; text-align: left;">
                <div>Hormat kami,</div>
                <div style="margin-top: 65px; font-weight: bold;">Direktur</div>
                <div>PT Inovindo Digital Media</div>
                <div style="font-weight: bold; margin-top: 4px;">Novi Setia Nurviat</div>
            </td>
        </tr>
    </table>

    <div class="page-break"></div>

    {{-- ========================================================================= --}}
    {{-- HALAMAN 2: LAMPIRAN 1 — JADWAL WFO PESERTA PKL/MAGANG                      --}}
    {{-- ========================================================================= --}}
    <table class="kop-table">
        <tr>
            <td class="kop-logo">
                @if ($logoBase64)
                    <img src="{{ $logoBase64 }}" class="kop-logo-img" alt="Inovindo Logo">
                @else
                    <div class="kop-brand-text">Inovindo</div>
                    <div class="kop-brand-sub">digital - media</div>
                @endif
            </td>
            <td class="kop-address">
                <div class="kop-company">PT INOVINDO DIGITAL MEDIA</div>
                <div>Komplek Buana Citra Ciwastra No. D3, Kab. Bandung</div>
                <div>WhatsApp. 08562251196 Website www.inovindo.com</div>
            </td>
        </tr>
    </table>

    <div class="title-lampiran">LAMPIRAN 1</div>
    <div class="subtitle-lampiran">JADWAL WFO PESERTA PKL/MAGANG</div>
    <div class="periode-lampiran">Tanggal {{ $mulai->translatedFormat('d') }} s.d {{ $selesai->translatedFormat('d F Y') }}</div>

    @php
        $seninList  = $jadwalWfo['senin']  ?? collect();
        $selasaList = $jadwalWfo['selasa'] ?? collect();
        $rabuList   = $jadwalWfo['rabu']   ?? collect();
        $kamisList  = $jadwalWfo['kamis']  ?? collect();
        $jumatList  = $jadwalWfo['jumat']  ?? collect();
        $sabtuList  = $jadwalWfo['sabtu']  ?? collect();

        $maxRow1 = max($seninList->count(), $selasaList->count(), $rabuList->count(), 6);
        $maxRow2 = max($kamisList->count(), $jumatList->count(), $sabtuList->count(), 6);
    @endphp

    {{-- Bagian 1: SENIN, SELASA, RABU --}}
    <table class="table-cyan">
        <thead>
            <tr>
                <th style="width: 33.33%;">SENIN</th>
                <th style="width: 33.33%;">SELASA</th>
                <th style="width: 33.33%;">RABU</th>
            </tr>
        </thead>
        <tbody>
            @for ($i = 0; $i < $maxRow1; $i++)
                <tr>
                    <td>{{ $seninList[$i]->tim->nama_tim ?? '' }}</td>
                    <td>{{ $selasaList[$i]->tim->nama_tim ?? '' }}</td>
                    <td>{{ $rabuList[$i]->tim->nama_tim ?? '' }}</td>
                </tr>
            @endfor
        </tbody>
    </table>

    {{-- Bagian 2: KAMIS, JUM'AT, SABTU --}}
    <table class="table-cyan">
        <thead>
            <tr>
                <th style="width: 33.33%;">KAMIS</th>
                <th style="width: 33.33%;">JUM'AT</th>
                <th style="width: 33.33%;">SABTU</th>
            </tr>
        </thead>
        <tbody>
            @for ($i = 0; $i < $maxRow2; $i++)
                <tr>
                    <td>{{ $kamisList[$i]->tim->nama_tim ?? '' }}</td>
                    <td>{{ $jumatList[$i]->tim->nama_tim ?? '' }}</td>
                    <td>{{ $sabtuList[$i]->tim->nama_tim ?? '' }}</td>
                </tr>
            @endfor
        </tbody>
    </table>

    <div class="keterangan-box">
        <div class="font-bold">Keterangan:</div>
        <div>Daily report: 09.00 - 10.00</div>
        <div>• Absen/presensi pagi: 09.00 - 09.05</div>
        <div>• Absen/presensi sore: 17.00 (weekday) / 14.00 (weekend)</div>
        <div>Kehadiran dibawah 80% nilai default C (tidak mendapatkan sertifikat PKL)</div>
    </div>

    <div class="page-break"></div>

    {{-- ========================================================================= --}}
    {{-- HALAMAN 3 & 4: LAMPIRAN 2 — DAFTAR KELOMPOK PESERTA PKL/MAGANG             --}}
    {{-- ========================================================================= --}}
    <table class="kop-table">
        <tr>
            <td class="kop-logo">
                @if ($logoBase64)
                    <img src="{{ $logoBase64 }}" class="kop-logo-img" alt="Inovindo Logo">
                @else
                    <div class="kop-brand-text">Inovindo</div>
                    <div class="kop-brand-sub">digital - media</div>
                @endif
            </td>
            <td class="kop-address">
                <div class="kop-company">PT INOVINDO DIGITAL MEDIA</div>
                <div>Komplek Buana Citra Ciwastra No. D3, Kab. Bandung</div>
                <div>WhatsApp. 08562251196 Website www.inovindo.com</div>
            </td>
        </tr>
    </table>

    <div class="title-lampiran">LAMPIRAN 2</div>
    <div class="subtitle-lampiran" style="margin-bottom: 22px;">DAFTAR KELOMPOK PESERTA PKL/MAGANG</div>

    <table class="table-cyan">
        <thead>
            <tr>
                <th style="width: 38%;">ASAL SEKOLAH</th>
                <th style="width: 62%;">NAMA PESERTA</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($semuaTim as $tim)
                @php
                    $personilCount = $tim->personil->count();
                @endphp
                @if ($personilCount > 0)
                    @foreach ($tim->personil as $idx => $p)
                        <tr>
                            @if ($idx === 0)
                                <td rowspan="{{ $personilCount }}" style="font-weight: bold; vertical-align: top; background: #ffffff;">
                                    {{ $tim->nama_tim }}
                                </td>
                            @endif
                            <td>{{ $p->nama }}</td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td style="font-weight: bold; vertical-align: top; background: #ffffff;">{{ $tim->nama_tim }}</td>
                        <td style="color: #71717a; font-style: italic;">(Belum ada data anggota)</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <div class="page-break"></div>
@endif

{{-- ========================================================================= --}}
{{-- HALAMAN 5+: JADWAL PETUGAS ADZAN & PEMBACAAN KITAB ZUHUR DAN ASHAR        --}}
{{-- ========================================================================= --}}
@if ($adzan->isNotEmpty())
    @php
        $groupedAdzan = $adzan->groupBy(fn($j) => $j->tanggal->toDateString())
            ->map(function ($rows) {
                $map = [];
                foreach ($rows as $r) {
                    $key = $r->waktu_sholat . '_' . $r->jenis_tugas;
                    $map[$key] = $r;
                }
                return $map;
            });
    @endphp

    <table class="kop-table">
        <tr>
            <td class="kop-logo">
                @if ($logoBase64)
                    <img src="{{ $logoBase64 }}" class="kop-logo-img" alt="Inovindo Logo">
                @else
                    <div class="kop-brand-text">Inovindo</div>
                    <div class="kop-brand-sub">digital - media</div>
                @endif
            </td>
            <td class="kop-address">
                <div class="kop-company">PT INOVINDO DIGITAL MEDIA</div>
                <div>Komplek Buana Citra Ciwastra No. D3, Kab. Bandung</div>
                <div>WhatsApp. 08562251196 Website www.inovindo.com</div>
            </td>
        </tr>
    </table>

    <div class="subtitle-lampiran" style="font-size: 12pt; margin-top: 10px;">Jadwal Petugas Adzan & Pembacaan Kitab Zuhur dan Ashar</div>
    <div class="periode-lampiran" style="font-size: 10pt; font-weight: bold; margin-bottom: 18px;">
        Bulan {{ $mulai->translatedFormat('F Y') }}
    </div>

    <table class="table-blue">
        <thead>
            <tr>
                <th rowspan="2" style="width: 14%;">Hari, Tanggal</th>
                <th rowspan="2" style="width: 10%;">Hari</th>
                <th colspan="2" style="width: 38%;">Zuhur</th>
                <th colspan="2" style="width: 38%;">Ashar</th>
            </tr>
            <tr>
                <th style="width: 19%;">Adzan</th>
                <th style="width: 19%;">Pembacaan Kitab</th>
                <th style="width: 19%;">Adzan</th>
                <th style="width: 19%;">Pembacaan Kitab</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($groupedAdzan as $tgl => $slots)
                @php
                    $carbon = \Carbon\Carbon::parse($tgl);
                    $dhuhrAdzan  = $slots['dhuhr_adzan']  ?? null;
                    $dhuhrKajian = $slots['dhuhr_kajian'] ?? null;
                    $asrAdzan    = $slots['asr_adzan']    ?? null;
                    $asrKajian   = $slots['asr_kajian']   ?? null;

                    $fmt = function($row) {
                        if (! $row || ! $row->personil) {
                            return '-';
                        }
                        return $row->personil->nama . '<br><span style="font-size: 7.5pt; color: #333;">(' . ($row->personil->tim->nama_tim ?? '—') . ')</span>';
                    };
                @endphp
                <tr>
                    <td class="text-center">{{ $carbon->translatedFormat('d F Y') }}</td>
                    <td class="text-center font-bold" style="text-transform: uppercase;">{{ $carbon->translatedFormat('l') }}</td>
                    <td class="text-center">{!! $fmt($dhuhrAdzan) !!}</td>
                    <td class="text-center">{!! $fmt($dhuhrKajian) !!}</td>
                    <td class="text-center">{!! $fmt($asrAdzan) !!}</td>
                    <td class="text-center">{!! $fmt($asrKajian) !!}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

</body>
</html>
