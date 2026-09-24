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
        if (! $this->periodeAktif) {
            Flux::toast(variant: 'danger', text: 'Tidak ada periode WFO aktif.');
            return;
        }

        // Cek ketersediaan Jadwal WFO sebelum loading & preview
        $jadwalWfoAda = \App\Models\JadwalWfo::where('periode_wfo_id', $this->periodeAktif->id)->exists();
        if (! $jadwalWfoAda) {
            $this->modalModeSelection = false;
            $this->modalWfoKosong = true;
            return;
        }

        // Validate custom mode
        if ($this->selectedMode === 'custom' && empty($this->selectedJadwal)) {
            Flux::toast(variant: 'warning', text: 'Pilih minimal 1 jadwal untuk di-generate.');
            return;
        }

        // Validate dependencies for custom mode
        if ($this->selectedMode === 'custom') {
            // If Ruangan selected, check if WFO jadwal exists
            if (in_array('ruangan', $this->selectedJadwal)) {
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
                $ruanganDates = collect($this->previewRuangan)->pluck('tanggal')->unique()->values()->all();
                AlokasiRuangan::whereIn('tanggal', $ruanganDates)->delete();

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
    x-data="{ tab: $wire.entangle('tab'), briefingExpanded: '' }"
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
                        wire:click="openModeSelection"
                        icon="sparkles"
                    >
                        <span wire:loading.remove wire:target="openModeSelection">Pilih Mode Generate</span>
                        <span wire:loading wire:target="openModeSelection" class="flex items-center gap-1.5">
                            <svg class="animate-spin size-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Memeriksa...
                        </span>
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
                        @php
                            // Group by tanggal
                            $groupedBriefing = collect($previewBriefing)->groupBy('tanggal')->sortKeys();
                        @endphp

                        <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                            @foreach ($groupedBriefing as $tanggal => $jadwals)
                                @php
                                    $tanggalKey = $tanggal;
                                    $carbonDate = \Carbon\Carbon::parse($tanggal);
                                    $dayName = $carbonDate->translatedFormat('l');
                                    $dateFormat = $carbonDate->translatedFormat('d M Y');
                                @endphp

                                {{-- Accordion Header --}}
                                <div class="border-b border-zinc-200 dark:border-zinc-700 last:border-b-0">
                                    <button
                                        type="button"
                                        @click="briefingExpanded = briefingExpanded === '{{ $tanggalKey }}' ? '' : '{{ $tanggalKey }}'"
                                        class="w-full p-4 flex items-center justify-between hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors"
                                    >
                                        <div class="flex items-center gap-3">
                                            <svg 
                                                class="w-4 h-4 text-zinc-400 transition-transform"
                                                :class="briefingExpanded === '{{ $tanggalKey }}' ? 'rotate-90' : ''"
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                            </svg>
                                            <div class="text-left">
                                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $dayName }}</div>
                                                <div class="text-xs text-zinc-500">{{ $dateFormat }}</div>
                                            </div>
                                        </div>
                                        <div class="text-xs text-zinc-500">
                                            {{ $jadwals->count() }} Sesi
                                        </div>
                                    </button>

                                    {{-- Accordion Content --}}
                                    <div 
                                        x-show="briefingExpanded === '{{ $tanggalKey }}'"
                                        x-transition:enter="transition ease-out duration-150"
                                        x-transition:enter-start="opacity-0 -translate-y-1"
                                        x-transition:enter-end="opacity-100 translate-y-0"
                                        x-cloak
                                    >
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-xs text-left border-collapse">
                                                <thead>
                                                    <tr class="bg-zinc-50/80 dark:bg-zinc-800/80 border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 dark:text-zinc-400 font-semibold uppercase tracking-wider text-[10px]">
                                                        <th class="py-2.5 px-4">Nama Personil</th>
                                                        <th class="py-2.5 px-4">Tim</th>
                                                        <th class="py-2.5 px-4 w-28 text-center">Waktu</th>
                                                        <th class="py-2.5 px-4 w-32 text-center">Peran</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/60">
                                                    @foreach ($jadwals as $row)
                                                        @php
                                                            $personil  = \App\Models\Personil::find($row['personil_id']);
                                                            $tim       = \App\Models\Tim::find($row['tim_id']);
                                                            $isNotulen = $row['is_notulen'] ?? false;
                                                            $isMod     = ! empty($row['moderator_id']) && $row['moderator_id'] === $row['personil_id'];
                                                            $isDoa     = ! empty($row['doa_id']) && $row['doa_id'] === $row['personil_id'];
                                                        @endphp
                                                        <tr class="hover:bg-zinc-50/70 dark:hover:bg-zinc-750/50 transition-colors">
                                                            <td class="py-2.5 px-4 font-medium text-zinc-900 dark:text-zinc-100">
                                                                {{ $personil?->nama ?? '—' }}
                                                            </td>
                                                            <td class="py-2.5 px-4 text-zinc-600 dark:text-zinc-300">
                                                                {{ $tim?->nama_tim ?? '—' }}
                                                            </td>
                                                            <td class="py-2.5 px-4 text-center">
                                                                <flux:badge size="xs" color="{{ $row['sesi'] === 'pagi' ? 'amber' : 'indigo' }}" class="w-16 justify-center">
                                                                    {{ ucfirst($row['sesi']) }}
                                                                </flux:badge>
                                                            </td>
                                                            <td class="py-2.5 px-4 text-center">
                                                                <div class="inline-flex items-center justify-center gap-1 flex-wrap">
                                                                    @if ($isNotulen)
                                                                        <flux:badge color="amber" size="xs" icon="pencil">Notulen</flux:badge>
                                                                    @endif
                                                                    @if ($isMod)
                                                                        <flux:badge color="indigo" size="xs" icon="user">Moderator</flux:badge>
                                                                    @endif
                                                                    @if ($isDoa)
                                                                        <flux:badge color="emerald" size="xs" icon="sparkles">Doa</flux:badge>
                                                                    @endif
                                                                    @if (! $isNotulen && ! $isMod && ! $isDoa)
                                                                        <span class="text-zinc-400">—</span>
                                                                    @endif
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Tab: Alokasi Ruangan --}}
                <div x-show="tab === 'semua' || tab === 'ruangan'" :class="(tab === 'semua') ? 'mt-6' : ''">
                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Alokasi Ruangan</p>
                    @if (empty($previewRuangan))
                        <p class="text-sm text-zinc-400">Tidak ada alokasi ruangan untuk rentang ini.</p>
                    @else
                        @php
                            // Group by ruangan_id, then by tanggal
                            $groupedByRuangan = collect($previewRuangan)->groupBy('ruangan_id');
                            
                            // Generate full week range (Senin-Sabtu) dari tanggal pertama yang ada di data
                            $firstDate = collect($previewRuangan)->pluck('tanggal')->sort()->first();
                            $startOfWeek = \Carbon\Carbon::parse($firstDate)->startOfWeek(\Carbon\Carbon::MONDAY);
                            
                            // Generate 6 hari (Senin-Sabtu)
                            $allDates = collect();
                            for ($i = 0; $i < 6; $i++) {
                                $allDates->push($startOfWeek->copy()->addDays($i)->toDateString());
                            }
                            
                            // Helper untuk nama hari Indonesia
                            $namaHariIndo = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'];
                        @endphp

                        <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700 rounded-lg">
                            <table class="w-full text-left border-separate border-spacing-0" style="min-width: 900px;">
                                <colgroup>
                                    <col style="width: 200px; min-width: 200px;">
                                    @foreach ($allDates as $date)
                                        <col style="width: 140px; min-width: 140px;">
                                    @endforeach
                                </colgroup>
                                
                                <thead>
                                    <tr class="bg-zinc-100 dark:bg-zinc-900 text-xs font-semibold text-zinc-600 dark:text-zinc-300">
                                        {{-- Kolom Ruangan --}}
                                        <th class="p-3.5 ps-5 border-b border-r border-zinc-200 dark:border-zinc-800 select-none sticky left-0 z-50 bg-zinc-100 dark:bg-zinc-900 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.06)]">
                                            <div class="flex items-center gap-2 font-semibold text-xs uppercase tracking-wider text-zinc-600 dark:text-zinc-400 whitespace-nowrap">
                                                <flux:icon icon="building-office-2" class="size-4 text-zinc-400 shrink-0" />
                                                <span>Ruangan</span>
                                            </div>
                                        </th>
                                        {{-- Kolom Tanggal --}}
                                        @foreach ($allDates as $date)
                                            @php
                                                $carbonDate = \Carbon\Carbon::parse($date);
                                                $dayOfWeek = $carbonDate->dayOfWeekIso - 1; // 0 = Senin, 5 = Sabtu
                                                $dayName = ucfirst($namaHariIndo[$dayOfWeek] ?? $carbonDate->translatedFormat('D'));
                                                $dateLabel = $carbonDate->translatedFormat('d M');
                                                $isToday = $carbonDate->isToday();
                                            @endphp
                                            <th class="p-3.5 text-center border-b border-r border-zinc-200 dark:border-zinc-800 select-none relative z-40 bg-zinc-100 dark:bg-zinc-900 {{ $isToday ? '!bg-blue-50/90 dark:!bg-blue-950/50 text-blue-600 dark:text-blue-400' : '' }}">
                                                <div class="font-bold text-sm">{{ $dayName }}</div>
                                                <div class="text-[11px] font-normal opacity-80 mt-0.5">{{ $dateLabel }}</div>
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                
                                <tbody class="text-sm">
                                    @foreach ($groupedByRuangan as $ruanganId => $alokasis)
                                        @php
                                            $ruangan = \App\Models\Ruangan::find($ruanganId);
                                            // Index alokasi by tanggal untuk lookup cepat
                                            $alokasiByDate = $alokasis->keyBy('tanggal');
                                        @endphp
                                        <tr class="hover:bg-zinc-50/40 dark:hover:bg-zinc-900/20 transition-colors">
                                            {{-- Ruangan Info Cell (Sticky Left) --}}
                                            <td class="p-4 ps-5 sticky left-0 z-10 bg-white dark:bg-zinc-800 border-b border-r border-zinc-200 dark:border-zinc-800 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.06)]">
                                                <div class="font-semibold text-sm text-zinc-900 dark:text-zinc-100 leading-snug break-words">
                                                    {{ $ruangan->nama_ruangan ?? '—' }}
                                                </div>
                                                <div class="flex items-center gap-1.5 mt-2">
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-zinc-100 dark:bg-zinc-700/60 text-zinc-600 dark:text-zinc-300 text-xs font-medium border border-zinc-200/80 dark:border-zinc-700/80 whitespace-nowrap shadow-2xs">
                                                        <flux:icon icon="users" class="size-3.5 text-zinc-400 shrink-0" />
                                                        <span>{{ $ruangan->kapasitas ?? 0 }} orang</span>
                                                    </span>
                                                </div>
                                            </td>

                                            {{-- Daily Cells --}}
                                            @foreach ($allDates as $date)
                                                @php
                                                    $carbonDate = \Carbon\Carbon::parse($date);
                                                    $isToday = $carbonDate->isToday();
                                                @endphp
                                                <td class="p-2 border-b border-r border-zinc-200 dark:border-zinc-800 align-top transition-colors {{ $isToday ? 'bg-[#3B71CA]/5 dark:bg-[#3B71CA]/5' : '' }}">
                                                    @if ($alokasiByDate->has($date))
                                                        @php
                                                            $row = $alokasiByDate[$date];
                                                            $tim = \App\Models\Tim::find($row['tim_id']);
                                                            $kapasitas = $row['kapasitas'] ?? $ruangan?->kapasitas ?? 0;
                                                            $expected = $row['expected_attendance'] ?? 0;
                                                            $utilization = $kapasitas > 0 ? round(($expected / $kapasitas) * 100) : 0;
                                                            $isOverCapacity = $expected > $kapasitas;
                                                            
                                                            // Tim color - SAMA dengan alokasi-ruangan page
                                                            $colors = [
                                                                'bg-[#3B71CA] dark:bg-[#3B71CA] text-white border-[#2d5db3] dark:border-[#2d5db3]',
                                                                'bg-red-500 dark:bg-red-600 text-white border-red-600 dark:border-red-700',
                                                                'bg-green-500 dark:bg-green-600 text-white border-green-600 dark:border-green-700',
                                                                'bg-amber-400 dark:bg-amber-500 text-zinc-900 dark:text-zinc-900 border-amber-500 dark:border-amber-600',
                                                                'bg-purple-500 dark:bg-purple-600 text-white border-purple-600 dark:border-purple-700',
                                                                'bg-stone-600 dark:bg-stone-700 text-white border-stone-700 dark:border-stone-800',
                                                                'bg-pink-500 dark:bg-pink-600 text-white border-pink-600 dark:border-pink-700',
                                                                'bg-slate-700 dark:bg-slate-800 text-white border-slate-800 dark:border-slate-900',
                                                                'bg-orange-500 dark:bg-orange-600 text-white border-orange-600 dark:border-orange-700',
                                                                'bg-emerald-500 dark:bg-emerald-600 text-white border-emerald-600 dark:border-emerald-700',
                                                            ];
                                                            $colorClass = $colors[($row['tim_id'] - 1) % count($colors)];
                                                        @endphp
                                                        
                                                        <div class="p-2.5 rounded-xl border shadow-xs hover:shadow-md transition-all {{ $colorClass }}">
                                                            <div class="min-w-0">
                                                                <div class="font-semibold text-xs truncate leading-tight">{{ $tim?->nama_tim ?? '—' }}</div>
                                                                
                                                                {{-- Capacity info --}}
                                                                <div class="flex items-center gap-1.5 text-[10px] font-medium mt-1.5">
                                                                    <span class="inline-block size-1.5 rounded-full bg-current"></span>
                                                                    <span>{{ $expected }}/{{ $kapasitas }} orang</span>
                                                                    <span class="opacity-60">•</span>
                                                                    <span 
                                                                        class="px-1.5 py-0.5 rounded font-semibold {{ $isOverCapacity ? 'bg-red-500/90 text-white' : 'bg-white/90 dark:bg-zinc-900/90 text-zinc-900 dark:text-white' }}"
                                                                    >
                                                                        {{ $utilization }}%
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @else
                                                        <div class="min-h-[72px] flex items-center justify-center text-zinc-300 dark:text-zinc-600 text-xs">
                                                            —
                                                        </div>
                                                    @endif
                                                </td>
                                            @endforeach
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
    <flux:modal wire:model="modalModeSelection" class="max-w-2xl">
        {{-- Content: Mode Selection (default) --}}
        <div wire:loading.remove wire:target="processGenerate,preview" class="space-y-5">
            {{-- Header --}}
            <div class="pr-8">
                <flux:heading size="lg">Pilih Mode Generate Jadwal</flux:heading>
                <flux:subheading class="mt-1">
                    Tentukan cara generate jadwal: semua sekaligus atau pilih jadwal tertentu saja.
                </flux:subheading>
            </div>

            {{-- Scrollable Content --}}
            <div class="max-h-[55vh] overflow-y-auto space-y-4 pr-1.5 -mr-1.5">
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
                    <div class="ml-4 sm:ml-8 space-y-2 animate-in fade-in duration-200">
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

            {{-- Footer Actions --}}
            <div class="flex items-center justify-between gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
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

