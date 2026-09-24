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
            margin: 16mm 16mm 30mm 16mm; /* Safe bottom margin so content never overlaps footer */
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

        /* Prevent awkward table row breaks */
        table {
            page-break-inside: auto;
        }
        tr {
            page-break-inside: avoid;
        }

        /* Footer Formal (Times New Roman - Konsisten dengan Kop Surat Header) */
        .pdf-footer {
            position: fixed;
            bottom: -22mm;
            left: 0;
            right: 0;
            height: 14mm;
            text-align: center;
            border-top: 1.5px solid #000000;
            padding-top: 4px;
            font-family: 'Times New Roman', Times, Georgia, serif;
        }

        .pdf-footer .footer-title {
            font-size: 8.5pt;
            font-weight: bold;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
            font-family: 'Times New Roman', Times, Georgia, serif;
        }

        .pdf-footer .footer-sub {
            font-size: 8pt;
            color: #111111;
            line-height: 1.3;
            margin-bottom: 2px;
            font-family: 'Times New Roman', Times, Georgia, serif;
        }

        .pdf-footer .footer-meta {
            font-size: 7.5pt;
            color: #444444;
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

@php
    $includeSurat = $includeSurat ?? true;
    $includeWfo = $includeWfo ?? true;
    $includeKelompok = $includeKelompok ?? true;
    $includeAdzan = $includeAdzan ?? true;
    $includeBriefing = $includeBriefing ?? true;
    $includeRuangan = $includeRuangan ?? true;
@endphp

<!-- Footer Formal - Appears on every page -->
<div class="pdf-footer">
    <div class="footer-title">PT INOVINDO DIGITAL MEDIA &bull; WEBSITE GILIRAN</div>
    <div class="footer-sub">Sistem Manajemen Jadwal WFO & Petugas Internal</div>
    <div class="footer-meta">Halaman <span class="page-number"></span> | Dokumen Resmi &bull; Generated: {{ now()->translatedFormat('d F Y, H:i') }} WIB</div>
</div>

{{-- ========================================================================= --}}
{{-- HALAMAN 1: SURAT RESMI PEMBERITAHUAN JADWAL WFO                            --}}
{{-- ========================================================================= --}}
@if ($includeSurat)
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

    @if ($includeWfo || $includeKelompok || ($includeAdzan && $adzan->isNotEmpty()) || ($includeBriefing && $briefing->isNotEmpty()) || ($includeRuangan && !empty($dataRuangan) && $dataRuangan->isNotEmpty()))
        <div class="page-break"></div>
    @endif
@endif

    {{-- ========================================================================= --}}
    {{-- HALAMAN 2: LAMPIRAN 1 — JADWAL WFO PESERTA PKL/MAGANG                      --}}
    {{-- ========================================================================= --}}
@if ($includeWfo)
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

    @if ($includeKelompok || ($includeAdzan && $adzan->isNotEmpty()) || ($includeBriefing && $briefing->isNotEmpty()) || ($includeRuangan && !empty($dataRuangan) && $dataRuangan->isNotEmpty()))
        <div class="page-break"></div>
    @endif
@endif

    {{-- ========================================================================= --}}
    {{-- HALAMAN 3 & 4: LAMPIRAN 2 — DAFTAR KELOMPOK PESERTA PKL/MAGANG             --}}
    {{-- ========================================================================= --}}
@if ($includeKelompok)
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

    @if (($includeAdzan && $adzan->isNotEmpty()) || ($includeBriefing && $briefing->isNotEmpty()) || ($includeRuangan && !empty($dataRuangan) && $dataRuangan->isNotEmpty()))
        <div class="page-break"></div>
    @endif
@endif

{{-- ========================================================================= --}}
{{-- HALAMAN ADZAN: JADWAL PETUGAS ADZAN & PEMBACAAN KITAB ZUHUR DAN ASHAR     --}}
{{-- ========================================================================= --}}
@if ($includeAdzan && $adzan->isNotEmpty())
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

    @if (($includeBriefing && $briefing->isNotEmpty()) || ($includeRuangan && !empty($dataRuangan) && $dataRuangan->isNotEmpty()))
        <div class="page-break"></div>
    @endif
@endif

{{-- ========================================================================= --}}
{{-- HALAMAN BRIEFING: JADWAL PETUGAS BRIEFING PAGI & SORE (3 ROLES)           --}}
{{-- ========================================================================= --}}
@if ($includeBriefing && $briefing->isNotEmpty())
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

    <div class="subtitle-lampiran" style="font-size: 12pt; margin-top: 10px;">Jadwal Petugas Briefing Pagi & Sore</div>
    <div class="periode-lampiran" style="font-size: 10pt; font-weight: bold; margin-bottom: 18px;">
        Tanggal {{ $mulai->translatedFormat('d F Y') }} s.d {{ $selesai->translatedFormat('d F Y') }}
    </div>

    @php
        $groupedBriefing = $briefing->groupBy(function($item) {
            return \Carbon\Carbon::parse($item->tanggal)->toDateString();
        })->map(function($dateItems) {
            return $dateItems->groupBy('sesi');
        });
        $no = 1;
    @endphp

    <table class="table-blue">
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 17%;">Hari, Tanggal</th>
                <th style="width: 9%;">Sesi</th>
                <th style="width: 26%;">Nama Personil</th>
                <th style="width: 17%;">Tim</th>
                <th style="width: 13%;">Peran</th>
                <th style="width: 14%;">Keterangan</th>
            </tr>
        </thead>
        @foreach ($groupedBriefing as $dateStr => $sessions)
            @php
                $dateCarbon = \Carbon\Carbon::parse($dateStr);
                $dateRowCount = $sessions->sum(fn($s) => $s->count());
                $isFirstRowOfDate = true;
            @endphp
            <tbody style="page-break-inside: avoid;">
                @foreach ($sessions as $sesiName => $items)
                    @php
                        $sessionRowCount = $items->count();
                        $isFirstRowOfSession = true;
                    @endphp

                    @foreach ($items as $j)
                        @php
                            $pName = $j->personil?->nama ?? '—';
                            $tName = $j->tim?->nama_tim ?? '—';
                            $isMod = ($j->moderator_id && $j->moderator_id === $j->personil_id);
                            $isDoa = ($j->doa_id && $j->doa_id === $j->personil_id);

                            $roleLabels = [];
                            if ($j->is_notulen) {
                                $roleLabels[] = 'Notulen';
                            }
                            if ($isMod) {
                                $roleLabels[] = 'Moderator';
                            }
                            if ($isDoa) {
                                $roleLabels[] = 'Doa';
                            }
                            $roleText = !empty($roleLabels) ? implode(', ', $roleLabels) : 'Peserta';
                        @endphp
                        <tr>
                            <td class="text-center">{{ $no++ }}</td>

                            @if ($isFirstRowOfDate)
                                <td rowspan="{{ $dateRowCount }}" class="text-center font-bold" style="vertical-align: middle;">
                                    {{ $dateCarbon->translatedFormat('l') }}<br>
                                    <span style="font-weight: normal; font-size: 7.5pt; color: #444;">{{ $dateCarbon->translatedFormat('d F Y') }}</span>
                                </td>
                                @php $isFirstRowOfDate = false; @endphp
                            @endif

                            @if ($isFirstRowOfSession)
                                <td rowspan="{{ $sessionRowCount }}" class="text-center font-bold" style="vertical-align: middle; text-transform: capitalize;">
                                    {{ $sesiName }}
                                </td>
                                @php $isFirstRowOfSession = false; @endphp
                            @endif

                            <td style="padding-left: 8px;">
                                <strong>{{ $pName }}</strong>
                            </td>
                            <td class="text-center">{{ $tName }}</td>
                            <td class="text-center font-bold" style="color: #1e3a8a;">
                                {{ $roleText }}
                            </td>
                            <td class="text-center" style="font-size: 8pt;">
                                @if ($j->is_switched)
                                    <span style="color: #c2410c; font-weight: bold;">Pengganti</span>
                                    @if ($j->originalPersonil)
                                        <br><span style="color: #555; font-size: 7pt;">(Gantikan {{ $j->originalPersonil->nama }})</span>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        @endforeach
    </table>

    @if ($includeRuangan && !empty($dataRuangan) && $dataRuangan->isNotEmpty())
        <div class="page-break"></div>
    @endif
@endif

{{-- ========================================================================= --}}
{{-- HALAMAN RUANGAN: JADWAL ALOKASI RUANGAN KERJA                             --}}
{{-- ========================================================================= --}}
@if ($includeRuangan && !empty($dataRuangan) && $dataRuangan->isNotEmpty())
    @php
        $alokasiByWeek = $dataRuangan->groupBy(function($a) {
            return \Carbon\Carbon::parse($a->tanggal)->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
        });
        $daftarRuanganList = (!empty($semuaRuangan) && $semuaRuangan->isNotEmpty())
            ? $semuaRuangan 
            : $dataRuangan->pluck('ruangan')->filter()->unique('id')->values();
    @endphp

    @foreach ($alokasiByWeek as $startOfWeekStr => $weekAllocations)
        @php
            $weekStart = \Carbon\Carbon::parse($startOfWeekStr);
            $weekEnd = $weekStart->copy()->addDays(5);
            $daysInWeek = [];
            for ($d = 0; $d < 6; $d++) {
                $daysInWeek[] = $weekStart->copy()->addDays($d);
            }

            // Map: [ruangan_id][tanggal] => alloc
            $matrixAlloc = [];
            foreach ($weekAllocations as $alloc) {
                $tglStr = \Carbon\Carbon::parse($alloc->tanggal)->toDateString();
                $matrixAlloc[$alloc->ruangan_id][$tglStr] = $alloc;
            }
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

        <div class="subtitle-lampiran" style="font-size: 12pt; margin-top: 10px;">Jadwal Alokasi Ruangan Kerja</div>
        <div class="periode-lampiran" style="font-size: 9.5pt; font-weight: bold; margin-bottom: 18px;">
            Rentang: {{ $weekStart->translatedFormat('d F Y') }} s.d {{ $weekEnd->translatedFormat('d F Y') }}
        </div>

        <table class="table-cyan">
            <thead>
                <tr>
                    <th style="width: 22%;">Ruangan</th>
                    @foreach ($daysInWeek as $day)
                        <th style="width: 13%; text-align: center;">
                            {{ $day->translatedFormat('l') }}<br>
                            <span style="font-size: 7.5pt; font-weight: normal; opacity: 0.9;">{{ $day->translatedFormat('d M') }}</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($daftarRuanganList as $ruang)
                    <tr>
                        <td style="font-weight: bold; vertical-align: middle; background: #f8fafc;">
                            {{ $ruang->nama_ruangan }}<br>
                            <span style="font-size: 7.5pt; font-weight: normal; color: #555;">Kapasitas: {{ $ruang->kapasitas }} org</span>
                        </td>
                        @foreach ($daysInWeek as $day)
                            @php
                                $dayStr = $day->toDateString();
                                $item = $matrixAlloc[$ruang->id][$dayStr] ?? null;
                            @endphp
                            <td style="text-align: center; vertical-align: middle; font-size: 8pt; padding: 4px;">
                                @if ($item && $item->tim)
                                    <div style="font-weight: bold; color: #1e3a8a;">
                                        {{ $item->tim->nama_tim }}
                                    </div>
                                    @if ($item->expected_attendance)
                                        <div style="font-size: 7pt; color: #555;">
                                            {{ $item->expected_attendance }} org
                                        </div>
                                    @endif
                                @else
                                    <span style="color: #bbb;">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if (! $loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
@endif

</body>
</html>
