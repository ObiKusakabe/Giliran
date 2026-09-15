<?php

use App\Models\JadwalAdzanKitab;
use App\Models\JadwalBriefing;
use App\Models\AlokasiRuangan;
use App\Models\PeriodeWfo;
use App\Services\LraScheduler;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Generate Jadwal')] #[Layout('layouts.admin')] class extends Component {

    public string $tanggalMulai   = '';
    public string $tanggalSelesai = '';

    /** Tab aktif: semua | adzan | briefing | ruangan */
    public string $tab = 'semua';

    /** Preview in-memory sebelum disimpan — GEN-07 */
    public array $previewAdzan    = [];
    public array $previewBriefing = [];
    public array $previewRuangan  = [];

    /** Flag apakah preview sudah ada */
    public bool $sudahPreview = false;

    /** Warning kalau ada tanggal dengan personil kurang */
    public array $warnings = [];

    /** Modal untuk prompt generate WFO dulu */
    public bool $modalWfoKosong = false;

    /** Modal untuk mode selection */
    public bool $modalModeSelection = false;
    public string $selectedMode = 'periode_baru'; // periode_baru | custom
    public array $selectedJadwal = []; // For custom mode: ['adzan', 'kajian', 'briefing_notulen', 'briefing_moderator', 'briefing_doa', 'ruangan']

    #[Computed]
    public function periodeAktif(): ?PeriodeWfo
    {
        return PeriodeWfo::where('status', 'aktif')->first();
    }

    /**
     * Preview Adzan & Kajian dikelompokkan per tanggal dengan tata letak kolom Zuhur & Ashar
     * Format: [tanggal => ['dhuhr_adzan' => [...], 'dhuhr_kajian' => [...], 'asr_adzan' => [...], 'asr_kajian' => [...]]]
     */
    #[Computed]
    public function previewAdzanGrouped(): array
    {
        if (empty($this->previewAdzan)) {
            return [];
        }

        $personilIds = collect($this->previewAdzan)->pluck('personil_id')->unique()->filter()->values()->all();
        $personilMap = \App\Models\Personil::with('tim')
            ->whereIn('id', $personilIds)
            ->get()
            ->keyBy('id');

        $grouped = [];
        foreach ($this->previewAdzan as $row) {
            $tgl = $row['tanggal'];
            $key = $row['waktu_sholat'] . '_' . $row['jenis_tugas'];
            $personil = $personilMap->get($row['personil_id']);

            $grouped[$tgl][$key] = [
                'personil_id' => $row['personil_id'],
                'nama'        => $personil?->nama ?? '—',
                'nama_tim'    => $personil?->tim?->nama_tim ?? '—',
            ];
        }

        return $grouped;
    }

    public function mount(): void
    {
        if ($this->periodeAktif) {
            $this->tanggalMulai   = $this->periodeAktif->tanggal_mulai->toDateString();
            $this->tanggalSelesai = $this->periodeAktif->tanggal_selesai->toDateString();
        }
        
        // Default custom mode: all selected
        $this->selectedJadwal = ['adzan', 'kajian', 'briefing_notulen', 'briefing_moderator', 'briefing_doa', 'ruangan'];
    }

    public function openModeSelection(): void
    {
        if (! $this->periodeAktif) {
            Flux::toast(variant: 'danger', text: 'Tidak ada periode WFO aktif. Aktifkan periode terlebih dahulu.');
            return;
        }

        // CHECK: Apakah jadwal WFO sudah ada?
        $jadwalWfoAda = \App\Models\JadwalWfo::where('periode_wfo_id', $this->periodeAktif->id)->exists();
        
        if (! $jadwalWfoAda) {
            $this->modalWfoKosong = true;
            return;
        }

        $this->modalModeSelection = true;
    }

    public function processGenerate(): void
    {
        // Validate custom mode
        if ($this->selectedMode === 'custom' && empty($this->selectedJadwal)) {
            Flux::toast(variant: 'warning', text: 'Pilih minimal 1 jadwal untuk di-generate.');
            return;
        }

        // Validate dependencies for custom mode
        if ($this->selectedMode === 'custom') {
            // If Ruangan selected, check if WFO jadwal exists
            if (in_array('ruangan', $this->selectedJadwal)) {
                $jadwalWfoAda = \App\Models\JadwalWfo::where('periode_wfo_id', $this->periodeAktif->id)->exists();
                if (!$jadwalWfoAda) {
                    Flux::toast(variant: 'danger', text: 'Alokasi Ruangan memerlukan Jadwal WFO. Generate Jadwal WFO terlebih dahulu.');
                    return;
                }
            }
        }

        // Close mode selection modal
        $this->modalModeSelection = false;
        
        // Start preview generation (will show processing UI via wire:loading)
        $this->preview();
    }

    public function preview(): void
    {
        if (! $this->periodeAktif) {
            Flux::toast(variant: 'danger', text: 'Tidak ada periode WFO aktif. Aktifkan periode terlebih dahulu.');
            return;
        }

        // ✅ CHECK: Apakah jadwal WFO sudah ada?
        $jadwalWfoAda = \App\Models\JadwalWfo::where('periode_wfo_id', $this->periodeAktif->id)->exists();
        
        if (! $jadwalWfoAda) {
            $this->modalWfoKosong = true;
            return; // Stop preview, show modal
        }

        $periode = $this->periodeAktif;

        // Auto-fill dari periode aktif jika belum terisi
        if (empty($this->tanggalMulai)) {
            $this->tanggalMulai = $periode->tanggal_mulai->toDateString();
        }
        if (empty($this->tanggalSelesai)) {
            $this->tanggalSelesai = $periode->tanggal_selesai->toDateString();
        }

        $scheduler = new LraScheduler;

        $mulai   = Carbon::parse($this->tanggalMulai);
        $selesai = Carbon::parse($this->tanggalSelesai);

        $tanggalList = $scheduler->expandTanggal($mulai, $selesai);

        if (empty($tanggalList)) {
            Flux::toast(variant: 'warning', text: 'Tidak ada hari kerja dalam rentang periode tersebut.');
            return;
        }

        // Generate preview based on selected mode
        if ($this->selectedMode === 'periode_baru') {
            // Mode Periode Baru: Generate ALL
            $this->previewAdzan    = $scheduler->generateAdzanKajian($tanggalList, $periode->id);
            $this->previewBriefing = $scheduler->generateBriefing($tanggalList, $periode->id);
            $this->previewRuangan  = $scheduler->generateAlokasiRuangan($tanggalList, $periode->id);
        } else {
            // Mode Custom: Only generate selected schedules
            $this->previewAdzan = [];
            $this->previewBriefing = [];
            $this->previewRuangan = [];
            
            // Generate Adzan & Kajian (both use same method)
            if (in_array('adzan', $this->selectedJadwal) || in_array('kajian', $this->selectedJadwal)) {
                $this->previewAdzan = $scheduler->generateAdzanKajian($tanggalList, $periode->id);
            }
            
            // Generate Briefing (notulen, moderator, doa)
            if (in_array('briefing_notulen', $this->selectedJadwal) || 
                in_array('briefing_moderator', $this->selectedJadwal) || 
                in_array('briefing_doa', $this->selectedJadwal)) {
                $this->previewBriefing = $scheduler->generateBriefing($tanggalList, $periode->id);
            }
            
            // Generate Ruangan
            if (in_array('ruangan', $this->selectedJadwal)) {
                $this->previewRuangan = $scheduler->generateAlokasiRuangan($tanggalList, $periode->id);
            }
        }

        // 🆕 Tentukan notulensi dari perwakilan briefing (LRA)
        if (! empty($this->previewBriefing)) {
            $notulenIndex = $scheduler->tentukanNotulen($this->previewBriefing);
            
            // FASE 4.3: Tentukan moderator & doa (LRA, no duplicate)
            $roles = $scheduler->tentukanModeratorDoa($this->previewBriefing, $notulenIndex);
            $moderatorIndex = $roles['moderator'];
            $doaIndex = $roles['doa'];
            
            // Set role IDs di preview (untuk save ke DB)
            foreach ($this->previewBriefing as $idx => $row) {
                // Default all false/null
                $this->previewBriefing[$idx]['is_notulen'] = false;
                $this->previewBriefing[$idx]['moderator_id'] = null;
                $this->previewBriefing[$idx]['doa_id'] = null;
                
                // Set notulen flag
                if (isset($notulenIndex[$idx])) {
                    $this->previewBriefing[$idx]['is_notulen'] = true;
                }
                
                // Set moderator_id
                if (isset($moderatorIndex[$idx])) {
                    $this->previewBriefing[$idx]['moderator_id'] = $row['personil_id'];
                }
                
                // Set doa_id
                if (isset($doaIndex[$idx])) {
                    $this->previewBriefing[$idx]['doa_id'] = $row['personil_id'];
                }
            }
        }

        // Cek warning: hari yang personilnya 0
        $this->warnings = [];
        foreach ($tanggalList as $tgl) {
            $namaHari  = $scheduler->namaHariIndonesia($tgl);
            $kandidat  = $scheduler->ambilPersonilWfo($periode->id, $namaHari);
            $slots     = $scheduler->tentukanSlotAdzan($tgl);
            $butuh     = count($slots) * 2; // adzan + kajian per slot

            if ($kandidat->count() < $butuh && $butuh > 0) {
                $this->warnings[] = 'Tanggal '.$tgl->format('d/m/Y')
                    .' ('.$namaHari.'): personil tersedia '.$kandidat->count()
                    .', dibutuhkan '.$butuh.' — akan ada duplikasi tugas.';
            }
        }

        // FASE 4.4 - Task #6: Capacity validation warnings
        $ruanganKapasitasMap = \App\Models\Ruangan::pluck('kapasitas', 'id');
        foreach ($this->previewRuangan as $r) {
            $kapasitas = $r['kapasitas'] ?? $ruanganKapasitasMap[$r['ruangan_id']] ?? 0;
            $expected = $r['expected_attendance'] ?? 0;
            
            if ($expected > $kapasitas) {
                $timNama = \App\Models\Tim::find($r['tim_id'])?->nama_tim ?? 'Tim #'.$r['tim_id'];
                $ruanganNama = \App\Models\Ruangan::find($r['ruangan_id'])?->nama_ruangan ?? 'Ruangan #'.$r['ruangan_id'];
                $this->warnings[] = '⚠️ Overflow: '.$timNama.' ('.$expected.' personil) dialokasikan ke '
                    .$ruanganNama.' (kapasitas '.$kapasitas.') pada '
                    .Carbon::parse($r['tanggal'])->format('d/m/Y').' — ruangan terlalu kecil!';
            } elseif ($expected > 0 && ($expected / $kapasitas) < 0.5) {
                // Underutilized warning (optional, bisa di-comment jika terlalu banyak)
                // $timNama = \App\Models\Tim::find($r['tim_id'])?->nama_tim ?? 'Tim #'.$r['tim_id'];
                // $ruanganNama = \App\Models\Ruangan::find($r['ruangan_id'])?->nama_ruangan ?? 'Ruangan #'.$r['ruangan_id'];
                // $this->warnings[] = 'ℹ️ Underutilized: '.$timNama.' ('.$expected.' personil) di '
                //     .$ruanganNama.' (kapasitas '.$kapasitas.') — utilitas hanya '.round(($expected/$kapasitas)*100).'%';
            }
        }

        $this->sudahPreview = true;
    }

    public function simpan(): void
    {
        if (! $this->sudahPreview) {
            Flux::toast(variant: 'danger', text: 'Jalankan preview terlebih dahulu.');
            return;
        }

        $mulai   = Carbon::parse($this->tanggalMulai)->toDateString();
        $selesai = Carbon::parse($this->tanggalSelesai)->toDateString();

        DB::transaction(function () use ($mulai, $selesai) {
            // Bersihkan jadwal lama pada rentang tanggal tersebut agar tidak terjadi duplikasi saat generate ulang
            JadwalAdzanKitab::whereBetween('tanggal', [$mulai, $selesai])->delete();
            JadwalBriefing::whereBetween('tanggal', [$mulai, $selesai])->delete();
            AlokasiRuangan::whereBetween('tanggal', [$mulai, $selesai])->delete();

            // Bulk insert — GEN-08: tersimpan permanen
            if (! empty($this->previewAdzan)) {
                JadwalAdzanKitab::insert($this->previewAdzan);
            }

            if (! empty($this->previewBriefing)) {
                JadwalBriefing::insert($this->previewBriefing);
            }

            if (! empty($this->previewRuangan)) {
                $cleanRuangan = array_map(function ($row) {
                    unset($row['kapasitas']);
                    return $row;
                }, $this->previewRuangan);
                AlokasiRuangan::insert($cleanRuangan);
            }
        });

        $totalAdzan    = count($this->previewAdzan);
        $totalBriefing = count($this->previewBriefing);
        $totalRuangan  = count($this->previewRuangan);

        Flux::toast(
            variant: 'success',
            text: "Jadwal berhasil diperbarui & disimpan: {$totalAdzan} adzan/kajian, {$totalBriefing} briefing, {$totalRuangan} alokasi ruangan."
        );

        // Reset state
        $this->reset(['previewAdzan', 'previewBriefing', 'previewRuangan', 'sudahPreview', 'warnings']);
        unset($this->periodeAktif);

        // Redirect ke dashboard setelah simpan
        $this->redirect(route('admin.dashboard'), navigate: true);
    }

    public function resetPreview(): void
    {
        $this->reset(['previewAdzan', 'previewBriefing', 'previewRuangan', 'sudahPreview', 'warnings']);
    }

    public function redirectKeJadwalWfo(): void
    {
        $this->redirect(route('admin.jadwal-wfo') . '?highlight=generate', navigate: true);
    }
}; ?>

<div
    x-data="{ tab: $wire.entangle('tab') }"
    class="flex flex-col gap-6"
>
    {{-- Header --}}
    <div>
        <flux:heading size="xl">Generate Jadwal</flux:heading>
        <flux:text class="text-zinc-500">
            Otomatisasi pembuatan jadwal adzan/kajian, briefing, dan alokasi ruangan berdasarkan periode WFO aktif.
        </flux:text>
    </div>

    {{-- Control Card: Periode Aktif --}}
    @if ($this->periodeAktif)
        <flux:card class="bg-gradient-to-r from-blue-500/5 via-emerald-500/5 to-transparent border-zinc-200 dark:border-zinc-700">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <flux:heading size="lg">{{ $this->periodeAktif->keterangan }}</flux:heading>
                        <flux:badge color="green" size="sm">Periode Aktif</flux:badge>
                    </div>
                    <flux:text class="text-zinc-600 dark:text-zinc-300">
                        <span class="font-medium text-zinc-900 dark:text-white">
                            {{ $this->periodeAktif->tanggal_mulai->locale('id')->translatedFormat('l, d F Y') }}
                        </span>
                        s/d
                        <span class="font-medium text-zinc-900 dark:text-white">
                            {{ $this->periodeAktif->tanggal_selesai->locale('id')->translatedFormat('l, d F Y') }}
                        </span>
                    </flux:text>
                </div>

                <div class="flex items-center gap-3">
                    <flux:button
                        variant="primary"
                        @click="$wire.set('modalModeSelection', true)"
                        icon="sparkles"
                    >
                        Pilih Mode Generate
                    </flux:button>
                </div>
            </div>
        </flux:card>
    @else
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.heading>Tidak ada periode WFO aktif</flux:callout.heading>
            <flux:callout.text>
                Aktifkan periode di halaman
                <a href="{{ route('admin.periode-wfo') }}" wire:navigate class="underline font-medium">Periode WFO</a>
                terlebih dahulu untuk men-generate jadwal.
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- Warnings --}}
    @if (! empty($warnings))
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>Perhatian: personil kurang di beberapa tanggal</flux:callout.heading>
            <flux:callout.text>
                <ul class="list-disc list-inside mt-1 space-y-0.5">
                    @foreach ($warnings as $w)
                        <li>{{ $w }}</li>
                    @endforeach
                </ul>
                Jadwal tetap di-generate dengan duplikasi terbatas (GEN-06).
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- Preview area --}}
    @if ($sudahPreview)
        {{-- Tab navigator (Alpine.js — pengganti flux:tabs Pro §3.6) --}}
        <flux:card class="p-0 overflow-hidden">
            {{-- Tab buttons --}}
            <div class="flex border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-4">
                @foreach ([
                    'semua'    => 'Semua ('.( count($previewAdzan) + count($previewBriefing) + count($previewRuangan) ).')',
                    'adzan'    => 'Adzan & Kajian ('.count($previewAdzan).')',
                    'briefing' => 'Briefing ('.count($previewBriefing).')',
                    'ruangan'  => 'Alokasi Ruangan ('.count($previewRuangan).')',
                ] as $key => $label)
                    <button
                        @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}'
                            ? 'border-b-2 border-brand text-brand font-medium'
                            : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                        class="px-4 py-3 text-sm transition-colors focus:outline-none"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="p-4">
                {{-- Badge preview --}}
                <div class="mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center rounded-full bg-amber-100 text-amber-700 px-2.5 py-0.5 text-xs font-medium">
                        Preview — belum tersimpan
                    </span>
                </div>

                {{-- Tab: Adzan & Kajian --}}
                <div x-show="tab === 'semua' || tab === 'adzan'">
                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Jadwal Adzan & Kajian</p>
                    @if (empty($this->previewAdzanGrouped))
                        <p class="text-sm text-zinc-400">Tidak ada data adzan/kajian untuk rentang ini.</p>
                    @else
                        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <table class="w-full text-xs border-separate border-spacing-0 table-auto">
                                <thead>
                                    {{-- Level 1: group header --}}
                                    <tr>
                                        <th rowspan="2" class="border-b border-r border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800 px-4 py-2.5 text-left text-zinc-700 dark:text-zinc-200 font-semibold align-middle">
                                            Hari, Tanggal
                                        </th>
                                        <th rowspan="2" class="border-b border-r border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800 px-4 py-2.5 text-center text-zinc-700 dark:text-zinc-200 font-semibold w-24 align-middle">
                                            Hari
                                        </th>
                                        <th colspan="2" class="border-b border-r border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800 px-4 py-2 text-center text-zinc-700 dark:text-zinc-200 font-semibold">
                                            Zuhur
                                        </th>
                                        <th colspan="2" class="border-b border-r border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800 px-4 py-2 text-center text-zinc-700 dark:text-zinc-200 font-semibold">
                                            Ashar
                                        </th>
                                    </tr>
                                    {{-- Level 2: sub header --}}
                                    <tr>
                                        <th class="border-b border-r border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-700/60 px-4 py-2 text-center text-zinc-600 dark:text-zinc-300 font-medium">Adzan</th>
                                        <th class="border-b border-r border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-700/60 px-4 py-2 text-center text-zinc-600 dark:text-zinc-300 font-medium">Pembacaan Kitab</th>
                                        <th class="border-b border-r border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-700/60 px-4 py-2 text-center text-zinc-600 dark:text-zinc-300 font-medium">Adzan</th>
                                        <th class="border-b border-r border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-700/60 px-4 py-2 text-center text-zinc-600 dark:text-zinc-300 font-medium">Pembacaan Kitab</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($this->previewAdzanGrouped as $tgl => $slots)
                                        @php
                                            $carbon      = \Carbon\Carbon::parse($tgl);
                                            $dhuhrAdzan  = $slots['dhuhr_adzan']  ?? null;
                                            $dhuhrKajian = $slots['dhuhr_kajian'] ?? null;
                                            $asrAdzan    = $slots['asr_adzan']    ?? null;
                                            $asrKajian   = $slots['asr_kajian']   ?? null;
                                            $isEven      = $loop->even;
                                            $namaHari    = match($carbon->dayOfWeekIso) {
                                                1 => 'SENIN', 2 => 'SELASA', 3 => 'RABU',
                                                4 => 'KAMIS', 5 => 'JUMAT', 6 => 'SABTU',
                                                7 => 'MINGGU',
                                            };
                                        @endphp
                                        <tr class="{{ $isEven ? 'bg-zinc-50 dark:bg-zinc-800/40' : 'bg-white dark:bg-zinc-900/60' }} hover:bg-zinc-100 dark:hover:bg-zinc-800/70 transition-colors">
                                            <td class="border-b border-r border-zinc-200 dark:border-zinc-700 px-4 py-3 text-zinc-900 dark:text-zinc-200 whitespace-nowrap">
                                                {{ $carbon->locale('id')->translatedFormat('d F Y') }}
                                            </td>
                                            <td class="border-b border-r border-zinc-200 dark:border-zinc-700 px-4 py-3 text-center font-bold text-zinc-900 dark:text-zinc-100 uppercase">
                                                {{ $namaHari }}
                                            </td>
                                            <td class="border-b border-r border-zinc-200 dark:border-zinc-700 px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                                @if ($dhuhrAdzan)
                                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $dhuhrAdzan['nama'] }}</div>
                                                    <div class="text-zinc-500 text-[11px]">({{ $dhuhrAdzan['nama_tim'] }})</div>
                                                @else
                                                    <div class="text-zinc-400">—</div>
                                                    <div class="text-zinc-400 text-[11px]">(-)</div>
                                                @endif
                                            </td>
                                            <td class="border-b border-r border-zinc-200 dark:border-zinc-700 px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                                @if ($dhuhrKajian)
                                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $dhuhrKajian['nama'] }}</div>
                                                    <div class="text-zinc-500 text-[11px]">({{ $dhuhrKajian['nama_tim'] }})</div>
                                                @else
                                                    <div class="text-zinc-400">—</div>
                                                    <div class="text-zinc-400 text-[11px]">(-)</div>
                                                @endif
                                            </td>
                                            <td class="border-b border-r border-zinc-200 dark:border-zinc-700 px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                                @if ($asrAdzan)
                                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $asrAdzan['nama'] }}</div>
                                                    <div class="text-zinc-500 text-[11px]">({{ $asrAdzan['nama_tim'] }})</div>
                                                @else
                                                    <div class="text-zinc-400">—</div>
                                                    <div class="text-zinc-400 text-[11px]">(-)</div>
                                                @endif
                                            </td>
                                            <td class="border-b border-r border-zinc-200 dark:border-zinc-700 px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                                @if ($asrKajian)
                                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $asrKajian['nama'] }}</div>
                                                    <div class="text-zinc-500 text-[11px]">({{ $asrKajian['nama_tim'] }})</div>
                                                @else
                                                    <div class="text-zinc-400">—</div>
                                                    <div class="text-zinc-400 text-[11px]">(-)</div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Tab: Briefing --}}
                <div x-show="tab === 'semua' || tab === 'briefing'" :class="(tab === 'semua') ? 'mt-6' : ''">
                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Jadwal Briefing</p>
                    @if (empty($previewBriefing))
                        <p class="text-sm text-zinc-400">Tidak ada data briefing untuk rentang ini.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead class="bg-zinc-50 dark:bg-zinc-800">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-zinc-500">Tanggal</th>
                                        <th class="px-3 py-2 text-left font-medium text-zinc-500">Sesi</th>
                                        <th class="px-3 py-2 text-left font-medium text-zinc-500">Tim</th>
                                        <th class="px-3 py-2 text-left font-medium text-zinc-500">Perwakilan</th>
                                        <th class="px-3 py-2 text-left font-medium text-zinc-500">Peran</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                    @foreach ($previewBriefing as $row)
                                        @php
                                            $personil  = \App\Models\Personil::find($row['personil_id']);
                                            $tim       = \App\Models\Tim::find($row['tim_id']);
                                            $isNotulen = $row['is_notulen'] ?? false;
                                            $isMod     = ! empty($row['moderator_id']) && $row['moderator_id'] === $row['personil_id'];
                                            $isDoa     = ! empty($row['doa_id']) && $row['doa_id'] === $row['personil_id'];
                                        @endphp
                                        <tr>
                                            <td class="px-3 py-1.5">{{ \Carbon\Carbon::parse($row['tanggal'])->translatedFormat('d M Y') }}</td>
                                            <td class="px-3 py-1.5 capitalize">{{ $row['sesi'] }}</td>
                                            <td class="px-3 py-1.5">{{ $tim?->nama_tim ?? '—' }}</td>
                                            <td class="px-3 py-1.5">{{ $personil?->nama ?? '—' }}</td>
                                            <td class="px-3 py-1.5">
                                                <div class="inline-flex items-center gap-1 flex-wrap">
                                                    @if ($isNotulen)
                                                        <flux:badge color="amber" size="sm" icon="pencil">Notulen</flux:badge>
                                                    @endif
                                                    @if ($isMod)
                                                        <flux:badge color="indigo" size="sm" icon="user">Moderator</flux:badge>
                                                    @endif
                                                    @if ($isDoa)
                                                        <flux:badge color="emerald" size="sm" icon="sparkles">Doa</flux:badge>
                                                    @endif
                                                    @if (! $isNotulen && ! $isMod && ! $isDoa)
                                                        <span class="text-zinc-400 text-xs">—</span>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Tab: Alokasi Ruangan --}}
                <div x-show="tab === 'semua' || tab === 'ruangan'" :class="(tab === 'semua') ? 'mt-6' : ''">
                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Alokasi Ruangan</p>
                    @if (empty($previewRuangan))
                        <p class="text-sm text-zinc-400">Tidak ada alokasi ruangan untuk rentang ini.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead class="bg-zinc-50 dark:bg-zinc-800">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-zinc-500">Tanggal</th>
                                        <th class="px-3 py-2 text-left font-medium text-zinc-500">Tim</th>
                                        <th class="px-3 py-2 text-left font-medium text-zinc-500">Ruangan</th>
                                        <th class="px-3 py-2 text-left font-medium text-zinc-500">Kapasitas</th>
                                        <th class="px-3 py-2 text-left font-medium text-zinc-500">Personil</th>
                                        <th class="px-3 py-2 text-left font-medium text-zinc-500">Utilitas</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                    @foreach ($previewRuangan as $row)
                                        @php
                                            $tim     = \App\Models\Tim::find($row['tim_id']);
                                            $ruangan = \App\Models\Ruangan::find($row['ruangan_id']);
                                            $kapasitas = $row['kapasitas'] ?? $ruangan?->kapasitas ?? 0;
                                            $expected = $row['expected_attendance'] ?? 0;
                                            $utilization = $kapasitas > 0 ? round(($expected / $kapasitas) * 100, 1) : 0;
                                            $isOverCapacity = $expected > $kapasitas;
                                        @endphp
                                        <tr class="{{ $isOverCapacity ? 'bg-red-50 dark:bg-red-950/20' : '' }}">
                                            <td class="px-3 py-1.5">{{ \Carbon\Carbon::parse($row['tanggal'])->translatedFormat('d M Y') }}</td>
                                            <td class="px-3 py-1.5">{{ $tim?->nama_tim ?? '—' }}</td>
                                            <td class="px-3 py-1.5">{{ $ruangan?->nama_ruangan ?? '—' }}</td>
                                            <td class="px-3 py-1.5 text-zinc-600 dark:text-zinc-400">{{ $kapasitas }}</td>
                                            <td class="px-3 py-1.5 {{ $isOverCapacity ? 'font-semibold text-red-600 dark:text-red-400' : 'text-zinc-600 dark:text-zinc-400' }}">
                                                {{ $expected }}
                                            </td>
                                            <td class="px-3 py-1.5">
                                                @if ($isOverCapacity)
                                                    <flux:badge color="red" size="sm">{{ $utilization }}% ⚠️</flux:badge>
                                                @elseif ($utilization >= 50 && $utilization <= 100)
                                                    <flux:badge color="green" size="sm">{{ $utilization }}%</flux:badge>
                                                @elseif ($utilization > 0)
                                                    <flux:badge color="zinc" size="sm">{{ $utilization }}%</flux:badge>
                                                @else
                                                    <span class="text-zinc-400">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Tombol aksi --}}
            <div class="flex justify-between items-center px-4 py-3 border-t border-zinc-100 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-800/50">
                <flux:button
                    variant="ghost"
                    wire:click="resetPreview"
                    size="sm"
                >
                    Ulangi
                </flux:button>

                <x-processing-button
                    variant="primary"
                    wire:click="simpan"
                    wireTarget="simpan"
                    icon="check"
                    idle-text="Simpan ke Database"
                    :steps="[
                        ['label' => 'Memvalidasi data...', 'duration' => 500],
                        ['label' => 'Menyimpan ke server...', 'duration' => 1000],
                        ['label' => 'Menyelesaikan...', 'duration' => 99999],
                    ]"
                    min-width="200px"
                />
            </div>
        </flux:card>
    @endif

    {{-- Modal: Jadwal WFO Belum Ada --}}
    <flux:modal wire:model="modalWfoKosong" class="max-w-lg" :closable="false">
        <div class="space-y-4">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 w-12 h-12 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                    <flux:icon.exclamation-triangle class="w-6 h-6 text-amber-600 dark:text-amber-400" />
                </div>
                <div class="flex-1">
                    <flux:heading size="lg" class="font-bold">Jadwal WFO Belum Ada</flux:heading>
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">
                        Jadwal <strong>adzan/kajian</strong>, <strong>briefing</strong>, dan <strong>alokasi ruangan</strong> 
                        membutuhkan jadwal WFO sebagai dasar untuk menentukan tim dan personil yang tersedia.
                    </flux:text>
                </div>
            </div>

            <div class="p-4 bg-zinc-50 dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
                <flux:text class="text-sm font-medium text-zinc-900 dark:text-zinc-100 mb-2">
                    ℹ️ Yang perlu dilakukan:
                </flux:text>
                <ol class="text-sm text-zinc-600 dark:text-zinc-400 space-y-1.5 list-decimal list-inside">
                    <li>Generate jadwal WFO terlebih dahulu (tentukan tim mana yang WFO di hari apa)</li>
                    <li>Kembali ke halaman ini untuk generate jadwal adzan, briefing, dan ruangan</li>
                </ol>
            </div>

            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                Ingin membuat jadwal WFO sekarang?
            </flux:text>

            <div class="flex justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button 
                    variant="ghost" 
                    @click="$wire.set('modalWfoKosong', false)"
                >
                    Nanti Saja
                </flux:button>
                <flux:button 
                    variant="primary" 
                    icon="calendar-days"
                    wire:click="redirectKeJadwalWfo"
                >
                    Ya, Buat Jadwal WFO
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal: Mode Selection --}}
    <flux:modal wire:model="modalModeSelection" class="max-w-2xl max-h-[90vh] flex flex-col">
        {{-- Content: Mode Selection (default) --}}
        <div wire:loading.remove wire:target="processGenerate,preview" class="flex flex-col h-full">
        {{-- Sticky Header --}}
        <div class="sticky top-0 z-10 bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700 px-6 py-4">
            <flux:heading size="lg">Pilih Mode Generate Jadwal</flux:heading>
            <flux:text class="text-zinc-500 mt-1">
                Tentukan cara generate jadwal: semua sekaligus atau pilih jadwal tertentu saja.
            </flux:text>
        </div>

        {{-- Scrollable Content --}}
        <div class="overflow-y-auto flex-1 px-6 py-6 space-y-4">
                {{-- Mode: Periode Baru (Recommended) --}}
                <label class="flex items-start gap-4 p-4 border-2 rounded-lg cursor-pointer transition-all"
                    :class="$wire.selectedMode === 'periode_baru' ? 'border-[#3B71CA] bg-[#3B71CA]/5' : 'border-zinc-200 dark:border-zinc-700 hover:border-zinc-300 dark:hover:border-zinc-600'">
                    <input type="radio" wire:model.live="selectedMode" value="periode_baru" class="mt-1">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <flux:heading size="sm">Mode Periode Baru</flux:heading>
                            <flux:badge size="xs" class="bg-[#3B71CA] text-white">Recommended</flux:badge>
                        </div>
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400 mb-3">
                            Generate semua jadwal sekaligus dalam 1 klik. Cocok untuk periode baru.
                        </flux:text>
                        
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                                <flux:icon icon="check-circle" variant="micro" class="size-4 text-green-500" />
                                <span>Jadwal Adzan</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                                <flux:icon icon="check-circle" variant="micro" class="size-4 text-green-500" />
                                <span>Jadwal Kajian</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                                <flux:icon icon="check-circle" variant="micro" class="size-4 text-green-500" />
                                <span>Briefing (Notulen)</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                                <flux:icon icon="check-circle" variant="micro" class="size-4 text-green-500" />
                                <span>Briefing (Moderator)</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                                <flux:icon icon="check-circle" variant="micro" class="size-4 text-green-500" />
                                <span>Briefing (Doa)</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                                <flux:icon icon="check-circle" variant="micro" class="size-4 text-green-500" />
                                <span>Alokasi Ruangan</span>
                            </div>
                        </div>
                    </div>
                </label>

                {{-- Mode: Custom --}}
                <label class="flex items-start gap-4 p-4 border-2 rounded-lg cursor-pointer transition-all"
                    :class="$wire.selectedMode === 'custom' ? 'border-[#3B71CA] bg-[#3B71CA]/5' : 'border-zinc-200 dark:border-zinc-700 hover:border-zinc-300 dark:hover:border-zinc-600'">
                    <input type="radio" wire:model.live="selectedMode" value="custom" class="mt-1">
                    <div class="flex-1">
                        <flux:heading size="sm" class="mb-1">Mode Custom</flux:heading>
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                            Pilih jadwal mana saja yang ingin di-generate.
                        </flux:text>
                    </div>
                </label>

                {{-- Custom Mode: Checkbox Group --}}
                @if ($selectedMode === 'custom')
                    <div class="ml-10 space-y-2 animate-in fade-in duration-200">
                        <flux:checkbox.group variant="cards" class="flex-col">
                            <flux:checkbox 
                                wire:model="selectedJadwal" 
                                value="adzan"
                                icon="speaker-wave"
                                label="Jadwal Adzan"
                                description="Generate jadwal adzan Zuhur & Ashar"
                            />
                            <flux:checkbox 
                                wire:model="selectedJadwal" 
                                value="kajian"
                                icon="book-open"
                                label="Jadwal Kajian"
                                description="Generate jadwal kajian/kultum setelah adzan"
                            />
                            <flux:checkbox 
                                wire:model="selectedJadwal" 
                                value="briefing_notulen"
                                icon="pencil"
                                label="Briefing (Notulen)"
                                description="Generate jadwal penulis notulen briefing"
                            />
                            <flux:checkbox 
                                wire:model="selectedJadwal" 
                                value="briefing_moderator"
                                icon="user-group"
                                label="Briefing (Moderator)"
                                description="Generate jadwal moderator briefing"
                            />
                            <flux:checkbox 
                                wire:model="selectedJadwal" 
                                value="briefing_doa"
                                icon="hand-raised"
                                label="Briefing (Doa)"
                                description="Generate jadwal pembuka/penutup doa"
                            />
                            <flux:checkbox 
                                wire:model="selectedJadwal" 
                                value="ruangan"
                                icon="building-office-2"
                                label="Alokasi Ruangan"
                                description="Generate alokasi ruangan untuk tim WFO"
                            />
                        </flux:checkbox.group>

                        @if (empty($selectedJadwal))
                            <flux:text class="text-sm text-red-600 dark:text-red-400">
                                ⚠️ Pilih minimal 1 jadwal untuk di-generate
                            </flux:text>
                        @endif
                    </div>
                @endif
        </div>

        {{-- Sticky Footer Actions --}}
        <div class="sticky bottom-0 z-10 flex items-center justify-between gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700 px-6 pb-6 bg-white dark:bg-zinc-900 rounded-b-xl">
            <flux:button variant="ghost" @click="$wire.set('modalModeSelection', false)">
                Batal
            </flux:button>
            <flux:button variant="primary" wire:click="processGenerate" :disabled="$selectedMode === 'custom' && empty($selectedJadwal)">
                Generate Jadwal
            </flux:button>
        </div>
        </div>

        {{-- Content: Processing Steps (during loading) --}}
        <div 
            wire:loading 
            wire:target="processGenerate,preview" 
            class="flex flex-col items-center justify-center py-16 px-6"
            x-data="{ 
                currentStep: 0,
                steps: [
                    @if ($this->selectedMode === 'periode_baru' || in_array('adzan', $this->selectedJadwal) || in_array('kajian', $this->selectedJadwal))
                        { label: 'Generate Adzan & Kajian...', duration: 800 },
                    @endif
                    @if ($this->selectedMode === 'periode_baru' || in_array('briefing_notulen', $this->selectedJadwal) || in_array('briefing_moderator', $this->selectedJadwal) || in_array('briefing_doa', $this->selectedJadwal))
                        { label: 'Generate Briefing...', duration: 600 },
                        { label: 'Tentukan Notulen...', duration: 400, isSubStep: true },
                        { label: 'Tentukan Moderator...', duration: 400, isSubStep: true },
                        { label: 'Tentukan Doa...', duration: 400, isSubStep: true },
                    @endif
                    @if ($this->selectedMode === 'periode_baru' || in_array('ruangan', $this->selectedJadwal))
                        { label: 'Generate Alokasi Ruangan...', duration: 800 },
                    @endif
                    { label: 'Menyiapkan preview...', duration: 600 }
                ],
                progressNextStep() {
                    if (this.currentStep < this.steps.length) {
                        const currentDuration = this.steps[this.currentStep]?.duration || 500;
                        setTimeout(() => {
                            this.currentStep++;
                            this.progressNextStep();
                        }, currentDuration);
                    }
                }
            }"
            x-init="progressNextStep()"
        >
            <svg class="animate-spin h-12 w-12 text-blue-600 dark:text-blue-400 mb-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <flux:heading size="lg" class="mb-2">Sedang Generate...</flux:heading>
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400 text-center mb-8">
                Mohon tunggu, sistem sedang membuat jadwal dengan algoritma LRA.
            </flux:text>
            
            {{-- Processing Steps with Alpine.js progress tracking --}}
            <div class="space-y-3 w-full max-w-sm">
                <template x-for="(step, index) in steps" :key="index">
                    <div 
                        class="flex items-center gap-3 text-sm"
                        :class="step.isSubStep ? 'pl-5' : ''"
                        x-show="index <= currentStep"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 transform translate-y-2"
                        x-transition:enter-end="opacity-100 transform translate-y-0"
                    >
                        {{-- Icon: Checklist if done, dot if current --}}
                        <template x-if="index < currentStep">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" 
                                :class="step.isSubStep ? 'w-3.5 h-3.5 text-green-600 dark:text-green-400' : 'w-4 h-4 text-green-600 dark:text-green-400'">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                            </svg>
                        </template>
                        <template x-if="index === currentStep">
                            <div 
                                :class="step.isSubStep 
                                    ? 'w-2 h-2 rounded-full bg-purple-500 animate-pulse' 
                                    : 'w-2.5 h-2.5 rounded-full bg-blue-600 animate-pulse'"
                            ></div>
                        </template>
                        
                        <span 
                            :class="index < currentStep 
                                ? 'text-zinc-500 dark:text-zinc-400' 
                                : 'text-zinc-700 dark:text-zinc-300'"
                            x-text="step.label"
                            :style="step.isSubStep ? 'font-size: 0.75rem' : ''"
                        ></span>
                    </div>
                </template>
            </div>
        </div>
    </flux:modal>
</div>

