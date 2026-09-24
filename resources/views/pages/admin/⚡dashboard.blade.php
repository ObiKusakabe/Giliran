<?php

use App\Models\AlokasiRuangan;
use App\Models\JadwalAdzanKitab;
use App\Models\JadwalBriefing;
use App\Models\JadwalWfo;
use App\Models\PeriodeWfo;
use App\Models\Tim;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('')] #[Layout('layouts.admin')] #[Lazy] class extends Component {

    // Calendar filters
    public string $filterJenis = '';
    public string $filterTimId = '';
    
    // View mode: kalender | tabel (default: tabel)
    public string $viewMode = 'tabel';
    
    // Tabel bulan filter
    public string $tabelBulan = '';

    // Modal states
    public bool $modalExport = false;
    
    public function mount(): void
    {
        $currentMonth = now()->format('Y-m');

        // Cek apakah ada jadwal di bulan sekarang (Adzan atau Briefing)
        $adaJadwalBulanIni = JadwalAdzanKitab::whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$currentMonth])->exists()
            || JadwalBriefing::whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$currentMonth])->exists();

        if ($adaJadwalBulanIni) {
            $this->tabelBulan = $currentMonth;
        } else {
            // Jika bulan sekarang belum ada jadwal, arahkan ke bulan dari periode aktif
            $aktif = PeriodeWfo::where('status', 'aktif')->first();
            $this->tabelBulan = $aktif?->tanggal_mulai ? $aktif->tanggal_mulai->format('Y-m') : $currentMonth;
        }
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="flex flex-col gap-6 p-6">
            <x-skeletons.page-header />
            <x-skeletons.stat-cards />
            <x-skeletons.calendar />
        </div>
        HTML;
    }

    public function updatedFilterJenis(): void
    {
        $this->dispatch('filter-changed', jenis: $this->filterJenis, timId: $this->filterTimId);
    }

    public function updatedFilterTimId(): void
    {
        $this->dispatch('filter-changed', jenis: $this->filterJenis, timId: $this->filterTimId);
    }

    #[Computed]
    public function timList()
    {
        return Tim::orderBy('nama_tim')->get(['id', 'nama_tim']);
    }

    #[Computed]
    public function periodeAktif(): ?PeriodeWfo
    {
        return PeriodeWfo::where('status', 'aktif')->first();
    }

    #[Computed]
    public function daftarPeriode()
    {
        return PeriodeWfo::orderByDesc('tanggal_mulai')->get();
    }

    /**
     * Data tabel adzan format Inovindo:
     * [tanggal => ['dhuhr_adzan' => row, 'dhuhr_kajian' => row, 'asr_adzan' => row, 'asr_kajian' => row]]
     */
    #[Computed]
    public function tabelAdzan(): array
    {
        [$mulai, $selesai] = $this->rentangBulan();

        $query = JadwalAdzanKitab::with(['personil.tim', 'originalPersonil'])
            ->whereBetween('tanggal', [$mulai, $selesai])
            ->orderBy('tanggal')
            ->orderBy('waktu_sholat');

        if ($this->filterTimId) {
            $query->whereHas('personil', fn ($q) => $q->where('tim_id', $this->filterTimId));
        }

        $grouped = [];
        foreach ($query->get() as $row) {
            $tgl = $row->tanggal->toDateString();
            $key = $row->waktu_sholat . '_' . $row->jenis_tugas;
            $grouped[$tgl][$key] = $row;
        }

        return $grouped;
    }

    /** Data tabel briefing */
    #[Computed]
    public function tabelBriefing()
    {
        [$mulai, $selesai] = $this->rentangBulan();

        $query = JadwalBriefing::with('personil', 'tim', 'originalPersonil')
            ->whereBetween('tanggal', [$mulai, $selesai])
            ->orderBy('tanggal')
            ->orderBy('sesi');

        if ($this->filterTimId) {
            $query->where('tim_id', $this->filterTimId);
        }

        return $query->get();
    }

    private function rentangBulan(): array
    {
        $bulan  = $this->tabelBulan ?: now()->format('Y-m');
        $mulai  = \Carbon\Carbon::parse($bulan . '-01')->startOfMonth();
        $selesai = $mulai->copy()->endOfMonth();

        return [$mulai->toDateString(), $selesai->toDateString()];
    }

    public function bulanLabel(): string
    {
        return \Carbon\Carbon::parse(($this->tabelBulan ?: now()->format('Y-m')) . '-01')
            ->translatedFormat('F Y');
    }

    /** DSB-01: Jumlah personil terjadwal (adzan/briefing) hari ini */
    #[Computed]
    public function personilTerjadwalHariIni(): int
    {
        $hari = now()->toDateString();

        $adzan = JadwalAdzanKitab::where('tanggal', $hari)
            ->distinct('personil_id')
            ->count('personil_id');

        $briefing = JadwalBriefing::where('tanggal', $hari)
            ->distinct('personil_id')
            ->count('personil_id');

        return $adzan + $briefing;
    }

    /** DSB-02: Ruangan teralokasi hari ini */
    #[Computed]
    public function ruanganTeralokasHariIni(): int
    {
        return AlokasiRuangan::where('tanggal', now()->toDateString())->count();
    }

    /** Total tim terdaftar di sistem */
    #[Computed]
    public function totalTim(): int
    {
        return Tim::count();
    }

    /** DSB-03: Konfirmasi tertunda (deprecated/fallback) */
    #[Computed]
    public function konfirmasiTertunda(): int
    {
        $hari = now()->toDateString();

        $adzan = JadwalAdzanKitab::where('tanggal', '>=', $hari)
            ->where('status_konfirmasi', 'menunggu')
            ->count();

        $briefing = JadwalBriefing::where('tanggal', '>=', $hari)
            ->where('status_konfirmasi', 'menunggu')
            ->count();

        return $adzan + $briefing;
    }

    /** Tim yang WFO hari ini */
    #[Computed]
    public function timWfoHariIni(): int
    {
        if (! $this->periodeAktif) {
            return 0;
        }

        $namaHari = match (now()->dayOfWeekIso) {
            1 => 'senin', 2 => 'selasa', 3 => 'rabu',
            4 => 'kamis', 5 => 'jumat', 6 => 'sabtu',
            default => null,
        };

        if (! $namaHari) {
            return 0;
        }

        return JadwalWfo::where('periode_wfo_id', $this->periodeAktif->id)
            ->where('hari', $namaHari)
            ->count();
    }

    /** List konfirmasi tertunda untuk tabel, limit 10 */
    #[Computed]
    public function daftarKonfirmasiTertunda()
    {
        $hari = now()->toDateString();

        $adzan = JadwalAdzanKitab::with('personil')
            ->where('tanggal', '>=', $hari)
            ->where('status_konfirmasi', 'menunggu')
            ->orderBy('tanggal')
            ->limit(10)
            ->get()
            ->map(fn ($j) => [
                'nama'    => $j->personil?->nama ?? '—',
                'jenis'   => ucfirst($j->jenis_tugas).' '.match($j->waktu_sholat) {
                    'dhuhr' => 'Zuhur',
                    'asr' => 'Ashar',
                    'fajr' => 'Subuh',
                    'maghrib' => 'Maghrib',
                    'isha' => 'Isya',
                    default => strtoupper($j->waktu_sholat),
                },
                'tanggal' => $j->tanggal,
                'tipe'    => 'Adzan/Kajian',
            ]);

        $briefing = JadwalBriefing::with('personil', 'tim')
            ->where('tanggal', '>=', $hari)
            ->where('status_konfirmasi', 'menunggu')
            ->orderBy('tanggal')
            ->limit(10)
            ->get()
            ->map(fn ($j) => [
                'nama'    => $j->personil?->nama ?? '—',
                'jenis'   => 'Briefing '.ucfirst($j->sesi),
                'tanggal' => $j->tanggal,
                'tipe'    => $j->tim?->nama_tim ?? '—',
            ]);

        return $adzan->merge($briefing)->sortBy('tanggal')->take(10)->values();
    }

    public function exportPDF(): void
    {
        if (! $this->periodeAktif) {
            $this->dispatch('notify', type: 'error', message: 'Tidak ada periode WFO aktif.');
            return;
        }

        // Redirect to export with auto-filled periode dates
        $this->redirect(route('admin.export.pdf', [
            'tanggal_mulai' => $this->periodeAktif->tanggal_mulai->format('Y-m-d'),
            'tanggal_selesai' => $this->periodeAktif->tanggal_selesai->format('Y-m-d'),
            'jenis' => 'semua',
        ]));
    }
}; ?>

<div class="flex flex-col gap-6" wire:poll="60000">
    {{-- DreamsPOS-style Welcome Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            @php
                $hour = now()->setTimezone('Asia/Jakarta')->hour;
                $salam = match(true) {
                    $hour >= 4 && $hour < 11 => 'Selamat Pagi',
                    $hour >= 11 && $hour < 15 => 'Selamat Siang',
                    $hour >= 15 && $hour < 18 => 'Selamat Sore',
                    default => 'Selamat Malam',
                };
            @endphp
            <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">
                {{ $salam }}, {{ auth()->user()->name ?? 'Admin' }}
            </flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400 mt-0.5">
                Kelola rotasi kerja, jadwal WFO, petugas adzan & briefing hari ini - <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span>
            </flux:text>
        </div>

        {{-- Action buttons & Active Period Badge --}}
        <div class="flex items-center gap-2.5 flex-wrap">
            @if ($this->periodeAktif)
                <div class="hidden xl:flex items-center gap-2 px-3 py-1.5 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-xs text-zinc-600 dark:text-zinc-300">
                    <flux:icon icon="calendar-days" class="size-4 text-brand" />
                    <span>{{ $this->periodeAktif->tanggal_mulai->format('d/m/Y') }} – {{ $this->periodeAktif->tanggal_selesai->format('d/m/Y') }}</span>
                </div>
            @endif
            <flux:button variant="primary" icon="sparkles" :href="route('admin.generate-jadwal')" wire:navigate>
                Generate Jadwal
            </flux:button>
            <flux:modal.trigger name="modal-export-pdf">
                <flux:button variant="filled" icon="arrow-down-tray">
                    Export PDF
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    @if (! $this->periodeAktif)
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>Tidak ada periode WFO aktif</flux:callout.heading>
            <flux:callout.text>
                Aktifkan periode di
                <a href="{{ route('admin.periode-wfo') }}" wire:navigate class="underline">Periode WFO</a>
                untuk mulai mengelola jadwal.
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- 4 Clickable Stat Shortcut Cards with 2-Layer 3D Lift Animation --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 pt-8">
        {{-- Card 1: Personil Terjadwal -> Admin Personil --}}
        <a href="{{ route('admin.personil') }}" wire:navigate.hover class="relative block group cursor-pointer select-none">
            {{-- Layer Belakang (Base layer - stays static, reveals shortcut text on hover) --}}
            <div class="absolute inset-0 rounded-2xl bg-blue-100/80 dark:bg-blue-950/60 border border-blue-200 dark:border-blue-800/60 flex items-end justify-between px-4 pb-2.5 text-xs font-semibold text-blue-700 dark:text-blue-300 shadow-xs">
                <span>Pergi ke Personil</span>
                <span class="inline-flex items-center gap-1">
                    <flux:icon icon="arrow-right" class="size-3.5 transition-transform duration-200 group-hover:translate-x-1" />
                </span>
            </div>

            {{-- Layer Utama (Top card - lifts up on hover) --}}
            <div class="relative z-10 overflow-hidden rounded-2xl p-4 sm:p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs transition-all duration-300 ease-out group-hover:-translate-y-8 group-hover:shadow-xl group-hover:border-blue-400 dark:group-hover:border-blue-500">
                <div class="relative z-10 pr-6">
                    <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Personil Terjadwal</flux:text>
                    <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                        {{ $this->personilTerjadwalHariIni }}
                    </flux:heading>
                    <div class="mt-2 flex items-center gap-1.5 text-[11px] text-[#3B71CA] dark:text-[#3B71CA] font-medium">
                        <span class="size-1.5 rounded-full bg-[#3B71CA]"></span>
                        <span>Hari ini</span>
                    </div>
                </div>
                <flux:icon icon="user-group" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-blue-500/10 dark:text-blue-400/10 pointer-events-none group-hover:scale-105 transition-transform duration-300" />
            </div>
        </a>

        {{-- Card 2: Ruang Teralokasi -> Alokasi Ruangan --}}
        <a href="{{ route('admin.alokasi-ruangan') }}" wire:navigate.hover class="relative block group cursor-pointer select-none">
            {{-- Layer Belakang (Base layer) --}}
            <div class="absolute inset-0 rounded-2xl bg-emerald-100/80 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/60 flex items-end justify-between px-4 pb-2.5 text-xs font-semibold text-emerald-700 dark:text-emerald-300 shadow-xs">
                <span>Pergi ke Ruangan</span>
                <span class="inline-flex items-center gap-1">
                    <flux:icon icon="arrow-right" class="size-3.5 transition-transform duration-200 group-hover:translate-x-1" />
                </span>
            </div>

            {{-- Layer Utama (Top card) --}}
            <div class="relative z-10 overflow-hidden rounded-2xl p-4 sm:p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs transition-all duration-300 ease-out group-hover:-translate-y-8 group-hover:shadow-xl group-hover:border-emerald-400 dark:group-hover:border-emerald-500">
                <div class="relative z-10 pr-6">
                    <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Ruang Teralokasi</flux:text>
                    <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                        {{ $this->ruanganTeralokasHariIni }}
                    </flux:heading>
                    <div class="mt-2 flex items-center gap-1.5 text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">
                        <span class="size-1.5 rounded-full bg-emerald-500"></span>
                        <span>Terisi hari ini</span>
                    </div>
                </div>
                <flux:icon icon="building-office-2" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-emerald-500/10 dark:text-emerald-400/10 pointer-events-none group-hover:scale-105 transition-transform duration-300" />
            </div>
        </a>

        {{-- Card 3: Total Tim -> Manajemen Tim --}}
        <a href="{{ route('admin.tim') }}" wire:navigate.hover class="relative block group cursor-pointer select-none">
            {{-- Layer Belakang (Base layer) --}}
            <div class="absolute inset-0 rounded-2xl bg-amber-100/80 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800/60 flex items-end justify-between px-4 pb-2.5 text-xs font-semibold text-amber-700 dark:text-amber-300 shadow-xs">
                <span>Pergi ke Tim</span>
                <span class="inline-flex items-center gap-1">
                    <flux:icon icon="arrow-right" class="size-3.5 transition-transform duration-200 group-hover:translate-x-1" />
                </span>
            </div>

            {{-- Layer Utama (Top card) --}}
            <div class="relative z-10 overflow-hidden rounded-2xl p-4 sm:p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs transition-all duration-300 ease-out group-hover:-translate-y-8 group-hover:shadow-xl group-hover:border-amber-400 dark:group-hover:border-amber-500">
                <div class="relative z-10 pr-6">
                    <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Total Tim</flux:text>
                    <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-amber-600 dark:text-amber-400">
                        {{ $this->totalTim }}
                    </flux:heading>
                    <div class="mt-2 flex items-center gap-1.5 text-[11px] text-amber-600 dark:text-amber-400 font-medium">
                        <span class="size-1.5 rounded-full bg-amber-500"></span>
                        <span>Tim terdaftar</span>
                    </div>
                </div>
                <flux:icon icon="users" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-amber-500/10 dark:text-amber-400/10 pointer-events-none group-hover:scale-105 transition-transform duration-300" />
            </div>
        </a>

        {{-- Card 4: Tim WFO Hari Ini -> Jadwal WFO --}}
        <a href="{{ route('admin.jadwal-wfo') }}" wire:navigate.hover class="relative block group cursor-pointer select-none">
            {{-- Layer Belakang (Base layer) --}}
            <div class="absolute inset-0 rounded-2xl bg-purple-100/80 dark:bg-purple-950/60 border border-purple-200 dark:border-purple-800/60 flex items-end justify-between px-4 pb-2.5 text-xs font-semibold text-purple-700 dark:text-purple-300 shadow-xs">
                <span>Pergi ke Jadwal WFO</span>
                <span class="inline-flex items-center gap-1">
                    <flux:icon icon="arrow-right" class="size-3.5 transition-transform duration-200 group-hover:translate-x-1" />
                </span>
            </div>

            {{-- Layer Utama (Top card) --}}
            <div class="relative z-10 overflow-hidden rounded-2xl p-4 sm:p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs transition-all duration-300 ease-out group-hover:-translate-y-8 group-hover:shadow-xl group-hover:border-purple-400 dark:group-hover:border-purple-500">
                <div class="relative z-10 pr-6">
                    <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Tim WFO Hari Ini</flux:text>
                    <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-purple-600 dark:text-purple-400">
                        {{ $this->timWfoHariIni }}
                    </flux:heading>
                    <div class="mt-2 flex items-center gap-1.5 text-[11px] text-purple-600 dark:text-purple-400 font-medium">
                        <span class="size-1.5 rounded-full bg-purple-500"></span>
                        <span>Aktif di kantor</span>
                    </div>
                </div>
                <flux:icon icon="calendar-days" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-purple-500/10 dark:text-purple-400/10 pointer-events-none group-hover:scale-105 transition-transform duration-300" />
            </div>
        </a>
    </div>

    {{-- Kalender Section --}}
    <flux:card class="p-0 overflow-visible table-sticky-card">
        <div class="px-4 py-3 border-b border-zinc-100 dark:border-zinc-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <flux:heading size="sm">
                    @if ($viewMode === 'kalender')
                        Kalender Jadwal
                    @else
                        Jadwal Adzan, Kajian & Briefing
                    @endif
                </flux:heading>
                <flux:text class="text-xs text-zinc-400">
                    @if ($viewMode === 'kalender')
                        Adzan/kajian, briefing, dan alokasi ruangan
                    @else
                        Bulan {{ $this->bulanLabel() }}
                    @endif
                </flux:text>
            </div>

            {{-- Toggle & Filters --}}
            <div class="flex items-center gap-3 flex-wrap">
                {{-- Toggle Kalender / Tabel Segmented Control (Matching Settings Appearance) --}}
                <flux:radio.group
                    variant="segmented"
                    wire:model.live="viewMode"
                    size="sm"
                >
                    <flux:radio value="tabel" icon="table-cells">Tabel</flux:radio>
                    <flux:radio value="kalender" icon="calendar-days">Kalender</flux:radio>
                </flux:radio.group>

                {{-- Filter jenis (kalender only) --}}
                @if ($viewMode === 'kalender')
                    <flux:select wire:model.live="filterJenis" class="w-40">
                        <flux:select.option value="">Semua Jenis</flux:select.option>
                        <flux:select.option value="adzan">Adzan/Kajian</flux:select.option>
                        <flux:select.option value="briefing">Briefing</flux:select.option>
                        <flux:select.option value="ruangan">Alokasi Ruangan</flux:select.option>
                    </flux:select>
                @endif

                {{-- Filter tim --}}
                <x-searchable-select
                    wire:model.live="filterTimId"
                    name="filterTimId"
                    placeholder="Semua Tim"
                    :options="$this->timList->map(fn($t) => ['value' => (string)$t->id, 'label' => $t->nama_tim])->prepend(['value' => '', 'label' => 'Semua Tim'])->toArray()"
                    :modelValue="$filterTimId"
                    class="w-48"
                />
            </div>
        </div>

        {{-- Legend (kalender only) --}}
        @if ($viewMode === 'kalender')
            <div class="px-4 py-2 bg-zinc-50 dark:bg-zinc-800/50 flex gap-4 flex-wrap text-xs border-b border-zinc-100 dark:border-zinc-800">
                <span class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded-sm bg-[#3B71CA]"></span> Adzan & Kajian
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded-sm bg-[#7C3AED]"></span> Briefing
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded-sm bg-[#059669]"></span> Alokasi Ruangan
                </span>
            </div>
        @endif

        {{-- VIEW: KALENDER --}}
        @if ($viewMode === 'kalender')
            <livewire:admin.calendar-widget 
                :filterJenis="$filterJenis" 
                :filterTimId="$filterTimId" 
                :key="'calendar-'.$filterJenis.'-'.$filterTimId"
            />
        @endif

        {{-- VIEW: TABEL --}}
        @if ($viewMode === 'tabel')
            {{-- Month navigation --}}
            <div class="px-4 py-3 flex items-center justify-center gap-3 border-b border-zinc-100 dark:border-zinc-800">
                <button
                    wire:click="$set('tabelBulan', '{{ \Carbon\Carbon::parse(($tabelBulan ?: now()->format('Y-m')) . '-01')->subMonth()->format('Y-m') }}')"
                    class="p-1.5 rounded-md text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>
                <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 min-w-[120px] text-center">
                    {{ $this->bulanLabel() }}
                </span>
                <button
                    wire:click="$set('tabelBulan', '{{ \Carbon\Carbon::parse(($tabelBulan ?: now()->format('Y-m')) . '-01')->addMonth()->format('Y-m') }}')"
                    class="p-1.5 rounded-md text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>

            {{-- Tabel Adzan & Kajian --}}
            @if (count($this->tabelAdzan) > 0)
                <div x-data="{
                    searchQuery: '',
                    highlightMatch(text, query) {
                        if (!query || !text) return text;
                        const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
                        return text.replace(regex, '<mark class=\'search-highlight\'>$1</mark>');
                    },
                    matchesSearch(row) {
                        if (!this.searchQuery) return true;
                        const query = this.searchQuery.toLowerCase();
                        const text = row.textContent.toLowerCase();
                        return text.includes(query);
                    }
                }">
                    {{-- Search input --}}
                    <div class="px-4 py-3 border-b border-zinc-100 dark:border-zinc-800">
                        <div class="flex items-center gap-3">
                            <div class="relative flex-1 max-w-md">
                                <input 
                                    type="text"
                                    x-model="searchQuery"
                                    placeholder="Cari nama tim atau personil..."
                                    class="w-full px-3 py-2 pl-10 text-sm border border-zinc-300 dark:border-zinc-600 rounded-lg bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 transition-all"
                                />
                                <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <button 
                                x-show="searchQuery"
                                x-transition
                                @click="searchQuery = ''"
                                class="text-sm px-3 py-1.5 rounded-md bg-zinc-100 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-600 transition-colors"
                            >
                                Clear
                            </button>
                        </div>
                    </div>

                    <div class="overflow-visible">
                    <table class="w-full text-xs border-separate border-spacing-0 table-auto border-l border-t border-zinc-200 dark:border-zinc-700">
                        <thead class="sticky top-[56px] z-15 shadow-xs">
                            {{-- Level 1: group header --}}
                            <tr>
                                <th rowspan="2" class="border-b border-r border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800 px-4 py-2.5 text-left text-zinc-700 dark:text-zinc-200 font-semibold align-middle">
                                    Hari, Tanggal
                                </th>
                                <th rowspan="2" class="border-b border-r border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800 px-4 py-2.5 text-center text-zinc-700 dark:text-zinc-200 font-semibold w-20 align-middle">
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
                            @foreach ($this->tabelAdzan as $tgl => $slots)
                                @php
                                    $carbon      = \Carbon\Carbon::parse($tgl);
                                    $dhuhrAdzan  = $slots['dhuhr_adzan']  ?? null;
                                    $dhuhrKajian = $slots['dhuhr_kajian'] ?? null;
                                    $asrAdzan    = $slots['asr_adzan']    ?? null;
                                    $asrKajian   = $slots['asr_kajian']   ?? null;
                                    $isEven      = $loop->even;

                                    $fmtPersonilText = fn($row) => $row
                                        ? e($row->personil?->nama ?? '—') . ' (' . e($row->personil?->tim?->nama_tim ?? '—') . ')'
                                        : '—';
                                @endphp
                                <tr 
                                    class="{{ $isEven ? 'bg-zinc-50 dark:bg-zinc-800/40' : 'bg-white dark:bg-zinc-900/60' }} hover:bg-zinc-100 dark:hover:bg-zinc-800/70 transition-colors"
                                    x-show="matchesSearch($el)"
                                    x-transition
                                >
                                    <td class="border-b border-r border-zinc-200 dark:border-zinc-700 px-4 py-3 text-zinc-900 dark:text-zinc-200">
                                        {{ $carbon->locale('id')->translatedFormat('d F Y') }}
                                    </td>
                                    <td class="border-b border-r border-zinc-200 dark:border-zinc-700 px-4 py-3 text-center font-bold text-zinc-900 dark:text-zinc-100 uppercase">
                                        @php
                                            $namaHari = match($carbon->dayOfWeekIso) {
                                                1 => 'SENIN', 2 => 'SELASA', 3 => 'RABU',
                                                4 => 'KAMIS', 5 => 'JUMAT', 6 => 'SABTU',
                                                7 => 'MINGGU',
                                            };
                                        @endphp
                                        {{ $namaHari }}
                                    </td>
                                    <td class="border-b border-r border-zinc-200 dark:border-zinc-700 px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                        @if ($dhuhrAdzan)
                                            <div class="flex items-start gap-2">
                                                <div class="flex-1">
                                                    <div x-html="highlightMatch('{{ e($dhuhrAdzan->personil?->nama ?? '—') }}', searchQuery)"></div>
                                                    <div class="text-zinc-500 text-[11px]" x-html="'(' + highlightMatch('{{ e($dhuhrAdzan->personil?->tim?->nama_tim ?? '—') }}', searchQuery) + ')'"></div>
                                                    @if ($dhuhrAdzan->is_switched)
                                                        <button 
                                                            x-data="{ showDetail: false }"
                                                            @click="showDetail = !showDetail"
                                                            @click.outside="showDetail = false"
                                                            class="relative mt-1 text-left"
                                                        >
                                                            <flux:badge color="amber" size="xs" icon="arrow-path" class="cursor-pointer hover:bg-amber-200 dark:hover:bg-amber-800 transition-colors">
                                                                Switched
                                                            </flux:badge>
                                                            
                                                            {{-- Tooltip Detail --}}
                                                            <div 
                                                                x-show="showDetail" 
                                                                x-cloak 
                                                                x-transition
                                                                class="absolute z-20 left-0 top-full mt-1 p-3 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-lg text-xs w-64"
                                                            >
                                                                <div class="font-semibold text-amber-600 dark:text-amber-400 mb-2 flex items-center gap-1">
                                                                    <flux:icon.exclamation-triangle class="size-4" />
                                                                    Switched Assignment
                                                                </div>
                                                                <div class="space-y-1.5 text-zinc-600 dark:text-zinc-300">
                                                                    <div><strong>Original:</strong> {{ $dhuhrAdzan->originalPersonil?->nama ?? '—' }}</div>
                                                                    <div><strong>Reason:</strong> {{ $dhuhrAdzan->switch_reason ?? '—' }}</div>
                                                                    <div><strong>Date:</strong> {{ $dhuhrAdzan->switched_at ? \Carbon\Carbon::parse($dhuhrAdzan->switched_at)->format('d M Y, H:i') : '—' }}</div>
                                                                </div>
                                                            </div>
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            <div>—</div>
                                        @endif
                                    </td>
                                    <td class="border-b border-r border-zinc-200 dark:border-zinc-700 px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                        @if ($dhuhrKajian)
                                            <div class="flex items-start gap-2">
                                                <div class="flex-1">
                                                    <div x-html="highlightMatch('{{ e($dhuhrKajian->personil?->nama ?? '—') }}', searchQuery)"></div>
                                                    <div class="text-zinc-500 text-[11px]" x-html="'(' + highlightMatch('{{ e($dhuhrKajian->personil?->tim?->nama_tim ?? '—') }}', searchQuery) + ')'"></div>
                                                    @if ($dhuhrKajian->is_switched)
                                                        <button 
                                                            x-data="{ showDetail: false }"
                                                            @click="showDetail = !showDetail"
                                                            @click.outside="showDetail = false"
                                                            class="relative mt-1 text-left"
                                                        >
                                                            <flux:badge color="amber" size="xs" icon="arrow-path" class="cursor-pointer hover:bg-amber-200 dark:hover:bg-amber-800 transition-colors">
                                                                Switched
                                                            </flux:badge>
                                                            
                                                            <div 
                                                                x-show="showDetail" 
                                                                x-cloak 
                                                                x-transition
                                                                class="absolute z-20 left-0 top-full mt-1 p-3 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-lg text-xs w-64"
                                                            >
                                                                <div class="font-semibold text-amber-600 dark:text-amber-400 mb-2 flex items-center gap-1">
                                                                    <flux:icon.exclamation-triangle class="size-4" />
                                                                    Switched Assignment
                                                                </div>
                                                                <div class="space-y-1.5 text-zinc-600 dark:text-zinc-300">
                                                                    <div><strong>Original:</strong> {{ $dhuhrKajian->originalPersonil?->nama ?? '—' }}</div>
                                                                    <div><strong>Reason:</strong> {{ $dhuhrKajian->switch_reason ?? '—' }}</div>
                                                                    <div><strong>Date:</strong> {{ $dhuhrKajian->switched_at ? \Carbon\Carbon::parse($dhuhrKajian->switched_at)->format('d M Y, H:i') : '—' }}</div>
                                                                </div>
                                                            </div>
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            <div>—</div>
                                        @endif
                                    </td>
                                    <td class="border-b border-r border-zinc-200 dark:border-zinc-700 px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                        @if ($asrAdzan)
                                            <div class="flex items-start gap-2">
                                                <div class="flex-1">
                                                    <div x-html="highlightMatch('{{ e($asrAdzan->personil?->nama ?? '—') }}', searchQuery)"></div>
                                                    <div class="text-zinc-500 text-[11px]" x-html="'(' + highlightMatch('{{ e($asrAdzan->personil?->tim?->nama_tim ?? '—') }}', searchQuery) + ')'"></div>
                                                    @if ($asrAdzan->is_switched)
                                                        <button 
                                                            x-data="{ showDetail: false }"
                                                            @click="showDetail = !showDetail"
                                                            @click.outside="showDetail = false"
                                                            class="relative mt-1 text-left"
                                                        >
                                                            <flux:badge color="amber" size="xs" icon="arrow-path" class="cursor-pointer hover:bg-amber-200 dark:hover:bg-amber-800 transition-colors">
                                                                Switched
                                                            </flux:badge>
                                                            
                                                            <div 
                                                                x-show="showDetail" 
                                                                x-cloak 
                                                                x-transition
                                                                class="absolute z-20 left-0 top-full mt-1 p-3 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-lg text-xs w-64"
                                                            >
                                                                <div class="font-semibold text-amber-600 dark:text-amber-400 mb-2 flex items-center gap-1">
                                                                    <flux:icon.exclamation-triangle class="size-4" />
                                                                    Switched Assignment
                                                                </div>
                                                                <div class="space-y-1.5 text-zinc-600 dark:text-zinc-300">
                                                                    <div><strong>Original:</strong> {{ $asrAdzan->originalPersonil?->nama ?? '—' }}</div>
                                                                    <div><strong>Reason:</strong> {{ $asrAdzan->switch_reason ?? '—' }}</div>
                                                                    <div><strong>Date:</strong> {{ $asrAdzan->switched_at ? \Carbon\Carbon::parse($asrAdzan->switched_at)->format('d M Y, H:i') : '—' }}</div>
                                                                </div>
                                                            </div>
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            <div>—</div>
                                        @endif
                                    </td>
                                    <td class="border-b border-r border-zinc-200 dark:border-zinc-700 px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                        @if ($asrKajian)
                                            <div class="flex items-start gap-2">
                                                <div class="flex-1">
                                                    <div x-html="highlightMatch('{{ e($asrKajian->personil?->nama ?? '—') }}', searchQuery)"></div>
                                                    <div class="text-zinc-500 text-[11px]" x-html="'(' + highlightMatch('{{ e($asrKajian->personil?->tim?->nama_tim ?? '—') }}', searchQuery) + ')'"></div>
                                                    @if ($asrKajian->is_switched)
                                                        <button 
                                                            x-data="{ showDetail: false }"
                                                            @click="showDetail = !showDetail"
                                                            @click.outside="showDetail = false"
                                                            class="relative mt-1 text-left"
                                                        >
                                                            <flux:badge color="amber" size="xs" icon="arrow-path" class="cursor-pointer hover:bg-amber-200 dark:hover:bg-amber-800 transition-colors">
                                                                Switched
                                                            </flux:badge>
                                                            
                                                            <div 
                                                                x-show="showDetail" 
                                                                x-cloak 
                                                                x-transition
                                                                class="absolute z-20 left-0 top-full mt-1 p-3 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-lg text-xs w-64"
                                                            >
                                                                <div class="font-semibold text-amber-600 dark:text-amber-400 mb-2 flex items-center gap-1">
                                                                    <flux:icon.exclamation-triangle class="size-4" />
                                                                    Switched Assignment
                                                                </div>
                                                                <div class="space-y-1.5 text-zinc-600 dark:text-zinc-300">
                                                                    <div><strong>Original:</strong> {{ $asrKajian->originalPersonil?->nama ?? '—' }}</div>
                                                                    <div><strong>Reason:</strong> {{ $asrKajian->switch_reason ?? '—' }}</div>
                                                                    <div><strong>Date:</strong> {{ $asrKajian->switched_at ? \Carbon\Carbon::parse($asrKajian->switched_at)->format('d M Y, H:i') : '—' }}</div>
                                                                </div>
                                                            </div>
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            <div>—</div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                </div>
            @else
                <div class="px-4 py-8">
                    <x-empty-state icon="book-open" title="Tidak ada jadwal adzan/kajian" description="Belum ada data untuk bulan {{ $this->bulanLabel() }}." />
                </div>
            @endif

            {{-- Spacing between tables --}}
            <div class="h-6 bg-zinc-50 dark:bg-zinc-900/50"></div>

            {{-- Tabel Briefing - Accordion per Tanggal + Compact Table --}}
            @if ($this->tabelBriefing->isNotEmpty())
                @php
                    // Group briefing by date
                    $groupedBriefing = $this->tabelBriefing->groupBy(fn($j) => $j->tanggal->format('Y-m-d'));
                    $allDateKeys = $groupedBriefing->keys()->values()->toArray();
                    $todayKey = now()->format('Y-m-d');
                    $tomorrowKey = now()->addDay()->format('Y-m-d');
                    $initialExpanded = array_values(array_filter([$todayKey, $tomorrowKey], fn($k) => in_array($k, $allDateKeys)));
                    if (empty($initialExpanded) && !empty($allDateKeys)) {
                        $initialExpanded = [$allDateKeys[0]];
                    }
                    $searchDataMap = [];
                    foreach ($groupedBriefing as $tglKey => $items) {
                        $searchDataMap[$tglKey] = $items->map(function ($j) {
                            $roles = ($j->is_notulen ? 'notulen notulensi ' : '')
                                . ($j->moderator_id && $j->moderator_id === $j->personil_id ? 'moderator ' : '')
                                . ($j->doa_id && $j->doa_id === $j->personil_id ? 'doa ' : '');

                            return ($j->personil?->nama ?? '') . ' ' . ($j->tim?->nama_tim ?? '') . ' ' . $roles;
                        })->implode(' ');
                    }
                @endphp
                <div
                    :key="'briefing-accordion-'.$tabelBulan"
                    x-data="{
                        searchQueryBriefing: '',
                        allDateKeys: @js($allDateKeys),
                        initialExpanded: @js($initialExpanded),
                        searchData: @js($searchDataMap),
                        expandedDates: [],

                        init() {
                            this.expandedDates = [...this.initialExpanded];
                            this.$watch('searchQueryBriefing', (query) => {
                                const q = (query || '').toLowerCase().trim();
                                if (!q) {
                                    this.expandedDates = [...this.initialExpanded];
                                    return;
                                }
                                this.expandedDates = this.allDateKeys.filter(key => {
                                    const text = (this.searchData[key] || '').toLowerCase();
                                    return text.includes(q);
                                });
                            });
                        },

                        isExpanded(key) {
                            return this.expandedDates.includes(key);
                        },

                        toggleDate(key) {
                            if (this.isExpanded(key)) {
                                this.expandedDates = this.expandedDates.filter(k => k !== key);
                            } else {
                                this.expandedDates.push(key);
                            }
                        },

                        expandAll() {
                            this.expandedDates = [...this.allDateKeys];
                        },

                        collapseAll() {
                            this.expandedDates = [];
                        },

                        hasMatch(key) {
                            const q = (this.searchQueryBriefing || '').toLowerCase().trim();
                            if (!q) return true;
                            return (this.searchData[key] || '').toLowerCase().includes(q);
                        },

                        highlightMatchBriefing(text, query) {
                            if (!query || !text) return text;
                            const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\$&')})`, 'gi');
                            return text.replace(regex, '<mark class=\'search-highlight\'>$1</mark>');
                        }
                    }"
                >
                    <div class="p-4 border-b border-zinc-100 dark:border-zinc-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                        <div>
                            <flux:heading size="sm">Jadwal Briefing</flux:heading>
                            <flux:text class="text-xs text-zinc-400">{{ $this->tabelBriefing->count() }} Jadwal ({{ count($groupedBriefing) }} Tanggal)</flux:text>
                        </div>

                        {{-- Action Controls & Search --}}
                        <div class="flex items-center gap-2.5 w-full sm:w-auto flex-wrap sm:flex-nowrap">
                            {{-- Expand Semua / Collapse Semua --}}
                            <div class="inline-flex items-center rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-0.5 shadow-2xs text-xs">
                                <button
                                    type="button"
                                    @click="expandAll()"
                                    class="px-2.5 py-1 rounded-md text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors font-medium flex items-center gap-1"
                                    title="Buka semua tanggal"
                                >
                                    <svg class="size-3.5 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                    <span>Expand Semua</span>
                                </button>
                                <div class="h-3.5 w-px bg-zinc-200 dark:border-zinc-700"></div>
                                <button
                                    type="button"
                                    @click="collapseAll()"
                                    class="px-2.5 py-1 rounded-md text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors font-medium flex items-center gap-1"
                                    title="Tutup semua tanggal"
                                >
                                    <svg class="size-3.5 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                                    </svg>
                                    <span>Collapse Semua</span>
                                </button>
                            </div>

                            {{-- Search input --}}
                            <div class="relative flex-1 sm:flex-none sm:w-64">
                                <input
                                    type="text"
                                    x-model="searchQueryBriefing"
                                    placeholder="Cari personil atau tim..."
                                    class="w-full px-3 py-1.5 pl-9 text-sm border border-zinc-300 dark:border-zinc-600 rounded-lg bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 transition-all"
                                />
                                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <button
                                x-show="searchQueryBriefing"
                                x-transition
                                @click="searchQueryBriefing = ''"
                                class="text-xs px-2.5 py-1.5 rounded-md bg-zinc-100 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-600 transition-colors"
                            >
                                Clear
                            </button>
                        </div>
                    </div>

                    {{-- Accordion List Layout --}}
                    <div class="p-4 space-y-2.5">
                        @foreach ($groupedBriefing as $tanggalKey => $jadwals)
                            @php
                                $firstJadwal = $jadwals->first();
                                $carbonDate = $firstJadwal->tanggal;
                                $isToday = $carbonDate->isToday();
                                $isTomorrow = $carbonDate->isTomorrow();
                                $namaHariBriefing = match($carbonDate->dayOfWeekIso) {
                                    1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu',
                                    4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu',
                                    7 => 'Minggu',
                                };

                                $siapCount = $jadwals->where('status_konfirmasi', 'siap')->count();
                                $menungguCount = $jadwals->where('status_konfirmasi', 'menunggu')->count();
                                $berhalanganCount = $jadwals->where('status_konfirmasi', 'berhalangan')->count();
                                $totalCount = $jadwals->count();
                            @endphp

                            <div
                                x-show="hasMatch('{{ $tanggalKey }}')"
                                x-transition
                                class="border border-zinc-200 dark:border-zinc-700/80 rounded-xl overflow-hidden bg-white dark:bg-zinc-800/90 shadow-2xs transition-all"
                                :class="isExpanded('{{ $tanggalKey }}') ? 'ring-1 ring-zinc-300 dark:ring-zinc-600' : 'hover:border-zinc-300 dark:hover:border-zinc-600'"
                            >
                                {{-- Accordion Header Bar --}}
                                <button
                                    type="button"
                                    @click="toggleDate('{{ $tanggalKey }}')"
                                    class="w-full px-4 py-2.5 text-left flex items-center justify-between gap-3 transition-colors select-none"
                                    :class="isExpanded('{{ $tanggalKey }}')
                                        ? 'bg-zinc-50/90 dark:bg-zinc-750/90 border-b border-zinc-200 dark:border-zinc-700'
                                        : 'bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-750/50'"
                                    :aria-expanded="isExpanded('{{ $tanggalKey }}')"
                                >
                                    <div class="flex items-center gap-3 min-w-0">
                                        {{-- Chevron Icon --}}
                                        <div
                                            class="size-5 rounded flex items-center justify-center text-zinc-400 transition-transform duration-200 shrink-0"
                                            :class="isExpanded('{{ $tanggalKey }}') ? 'rotate-90 text-zinc-700 dark:text-zinc-200' : ''"
                                        >
                                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </div>

                                        {{-- Mini Date Badge --}}
                                        <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg {{ $isToday ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 font-bold border border-blue-200 dark:border-blue-800' : 'bg-zinc-100 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-200 font-semibold' }} text-xs shrink-0">
                                            <span class="text-sm font-bold leading-none">{{ $carbonDate->format('d') }}</span>
                                            <span class="text-[10px] uppercase tracking-wider opacity-80 leading-none">{{ $carbonDate->format('M') }}</span>
                                        </div>

                                        {{-- Day & Full Date --}}
                                        <div class="flex items-center gap-2 min-w-0 truncate">
                                            <span class="font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ $namaHariBriefing }}</span>
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400 hidden sm:inline">• {{ $carbonDate->translatedFormat('d F Y') }}</span>
                                            @if ($isToday)
                                                <flux:badge color="blue" size="xs">Hari Ini</flux:badge>
                                            @elseif ($isTomorrow)
                                                <flux:badge color="zinc" size="xs">Besok</flux:badge>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Right Summary Indicators --}}
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="text-xs text-zinc-500 font-medium mr-1 hidden md:inline">{{ $totalCount }} Sesi</span>

                                        <div class="flex items-center gap-1">
                                            @if ($siapCount > 0)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60" title="{{ $siapCount }} Personil Siap">
                                                    <span>{{ $siapCount }}</span>
                                                    <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                </span>
                                            @endif
                                            @if ($menungguCount > 0)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60" title="{{ $menungguCount }} Personil Menunggu">
                                                    <span>{{ $menungguCount }}</span>
                                                    <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                </span>
                                            @endif
                                            @if ($berhalanganCount > 0)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-100 dark:bg-red-950/60 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-800/60" title="{{ $berhalanganCount }} Personil Berhalangan">
                                                    <span>{{ $berhalanganCount }}</span>
                                                    <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </button>

                                {{-- Compact Session Table --}}
                                <div
                                    x-show="isExpanded('{{ $tanggalKey }}')"
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 -translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                >
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-xs text-left border-collapse">
                                            <thead>
                                                <tr class="bg-zinc-50/80 dark:bg-zinc-800/80 border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 dark:text-zinc-400 font-semibold uppercase tracking-wider text-[10px]">
                                                    <th class="py-2.5 px-4">Nama Personil</th>
                                                    <th class="py-2.5 px-4">Tim</th>
                                                    <th class="py-2.5 px-4 w-28 text-center">Waktu</th>
                                                    <th class="py-2.5 px-4 w-28 text-center">Peran</th>
                                                    <th class="py-2.5 px-4 w-28 text-right">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/60">
                                                @foreach ($jadwals as $j)
                                                    @php
                                                        $pName = $j->personil?->nama ?? '—';
                                                        $tName = $j->tim?->nama_tim ?? '—';
                                                        $isMod = $j->moderator_id && $j->moderator_id === $j->personil_id;
                                                        $isDoa = $j->doa_id && $j->doa_id === $j->personil_id;
                                                        $rolesStr = ($j->is_notulen ? 'notulen notulensi ' : '') . ($isMod ? 'moderator ' : '') . ($isDoa ? 'doa ' : '') . ($j->is_switched ? 'pengganti ' : '');
                                                        $rowSearch = strtolower(e($pName . ' ' . $tName . ' ' . $rolesStr));
                                                    @endphp
                                                    <tr
                                                        data-briefing-row
                                                        x-show="!searchQueryBriefing.trim() || '{{ $rowSearch }}'.includes(searchQueryBriefing.toLowerCase().trim())"
                                                        class="hover:bg-zinc-50/70 dark:hover:bg-zinc-750/50 transition-colors"
                                                    >
                                                        <td class="py-2.5 px-4 font-medium text-zinc-900 dark:text-zinc-100">
                                                            <span x-html="highlightMatchBriefing('{{ e($pName) }}', searchQueryBriefing)"></span>
                                                        </td>
                                                        <td class="py-2.5 px-4 text-zinc-600 dark:text-zinc-300">
                                                            <span title="{{ $tName }}" class="truncate max-w-[240px] inline-block align-middle" x-html="highlightMatchBriefing('{{ e($tName) }}', searchQueryBriefing)"></span>
                                                        </td>
                                                        <td class="py-2.5 px-4 text-center">
                                                            <flux:badge size="xs" color="{{ $j->sesi === 'pagi' ? 'amber' : 'indigo' }}" class="w-16 justify-center">
                                                                {{ ucfirst($j->sesi) }}
                                                            </flux:badge>
                                                        </td>
                                                        <td class="py-2.5 px-4 text-center">
                                                            <div class="inline-flex items-center justify-center gap-1 flex-wrap">
                                                                @if ($j->is_notulen)
                                                                    <flux:badge color="amber" size="xs" icon="pencil">Notulen</flux:badge>
                                                                @endif
                                                                @if ($isMod)
                                                                    <flux:badge color="indigo" size="xs" icon="user">Moderator</flux:badge>
                                                                @endif
                                                                @if ($isDoa)
                                                                    <flux:badge color="emerald" size="xs" icon="sparkles">Doa</flux:badge>
                                                                @endif
                                                                @if (! $j->is_notulen && ! $isMod && ! $isDoa)
                                                                    <span class="text-zinc-400 text-xs">—</span>
                                                                @endif
                                                            </div>
                                                        </td>
                                                        <td class="py-2.5 px-4 text-right">
                                                            @if ($j->is_switched)
                                                                {{-- Badge Pengganti (clickable) --}}
                                                                <div x-data="{ showDetail: false }" class="inline-block">
                                                                    <button 
                                                                        @click="showDetail = !showDetail"
                                                                        @click.outside="showDetail = false"
                                                                        class="relative"
                                                                    >
                                                                        <flux:badge 
                                                                            color="orange" 
                                                                            size="xs" 
                                                                            icon="arrow-path" 
                                                                            class="cursor-pointer hover:bg-orange-200 dark:hover:bg-orange-800 transition-colors"
                                                                        />
                                                                        
                                                                        {{-- Tooltip Detail --}}
                                                                        <div 
                                                                            x-show="showDetail" 
                                                                            x-cloak 
                                                                            x-transition
                                                                            class="absolute z-20 right-0 top-full mt-1 p-3 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-lg text-xs w-64"
                                                                        >
                                                                            <div class="font-semibold text-orange-600 dark:text-orange-400 mb-2 flex items-center gap-1">
                                                                                <flux:icon.arrow-path class="size-4" />
                                                                                Pengganti
                                                                            </div>
                                                                            <div class="space-y-1.5 text-zinc-600 dark:text-zinc-300">
                                                                                <div><strong>Original:</strong> {{ $j->originalPersonil?->nama ?? '—' }}</div>
                                                                                <div><strong>Alasan:</strong> {{ $j->switch_reason ?? '—' }}</div>
                                                                                <div><strong>Tanggal:</strong> {{ $j->switched_at ? \Carbon\Carbon::parse($j->switched_at)->format('d M Y, H:i') : '—' }}</div>
                                                                            </div>
                                                                        </div>
                                                                    </button>
                                                                </div>
                                                            @elseif ($j->status_konfirmasi === 'berhalangan')
                                                                <x-status-badge status="berhalangan" />
                                                            @else
                                                                {{-- Siap: tampilkan — atau kosong --}}
                                                                <span class="text-zinc-400 text-xs">—</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        {{-- Empty Search Result Indicator --}}
                        <div x-show="searchQueryBriefing.trim() && allDateKeys.every(k => !hasMatch(k))" class="py-8 text-center text-zinc-400 text-sm">
                            Tidak ada jadwal briefing yang cocok dengan "<span class="font-medium text-zinc-600 dark:text-zinc-300" x-text="searchQueryBriefing"></span>"
                        </div>
                    </div>
                </div>
            @else
                <div class="px-4 py-8">
                    <x-empty-state icon="user-group" title="Tidak ada jadwal briefing" description="Belum ada data untuk bulan {{ $this->bulanLabel() }}." />
                </div>
            @endif
        @endif
    </flux:card>
    {{-- Modal Export PDF dengan opsi pemilihan komponen jadwal --}}
    <flux:modal name="modal-export-pdf" class="max-w-lg">
        <form method="POST" action="{{ route('admin.export.pdf') }}" target="_blank" class="space-y-6">
            @csrf
            <input type="hidden" name="from_modal" value="1">
            <div>
                <flux:heading size="lg">Export Jadwal ke PDF</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    Pilih periode dan komponen jadwal yang ingin disertakan ke dalam dokumen PDF.
                </flux:text>
            </div>

            {{-- Pemilihan Periode WFO --}}
            <div x-data="{ useCustomDates: false }" class="space-y-3">
                <flux:field>
                    <div class="flex items-center justify-between mb-1">
                        <flux:label class="font-medium text-sm">Pilih Periode WFO</flux:label>
                        <button 
                            type="button" 
                            @click="useCustomDates = !useCustomDates" 
                            class="text-xs text-blue-600 dark:text-blue-400 hover:underline"
                        >
                            <span x-text="useCustomDates ? 'Kembali ke Pilih Periode' : 'Kustom Rentang Tanggal'"></span>
                        </button>
                    </div>

                    <div x-show="!useCustomDates" x-data="{
                        open: false,
                        selectedId: '{{ $this->periodeAktif?->id ?? ($this->daftarPeriode->first()?->id ?? '') }}',
                        selectedLabel: '{{ $this->periodeAktif ? ($this->periodeAktif->nama . ' (' . $this->periodeAktif->tanggal_mulai?->format('d M Y') . ' - ' . $this->periodeAktif->tanggal_selesai?->format('d M Y') . ')' . ($this->periodeAktif->status === 'aktif' ? ' • [Aktif]' : '')) : ($this->daftarPeriode->first() ? ($this->daftarPeriode->first()->nama . ' (' . $this->daftarPeriode->first()->tanggal_mulai?->format('d M Y') . ' - ' . $this->daftarPeriode->first()->tanggal_selesai?->format('d M Y') . ')') : 'Pilih Periode') }}'
                    }" @click.outside="open = false" class="relative">
                        <input type="hidden" name="periode_wfo_id" :value="selectedId">
                        
                        <button type="button" @click="open = !open"
                            :class="open ? 'ring-2 ring-blue-500 border-blue-500' : 'border-zinc-300 dark:border-zinc-600 hover:border-zinc-400'"
                            class="w-full flex items-center justify-between gap-2 rounded-lg border bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-left transition-colors">
                            <span class="truncate text-zinc-900 dark:text-zinc-100 font-medium" x-text="selectedLabel"></span>
                            <svg class="h-4 w-4 text-zinc-400 shrink-0 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div x-show="open" x-transition class="absolute z-50 mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-xl py-1 max-h-60 overflow-y-auto">
                            @foreach ($this->daftarPeriode as $p)
                                @php
                                    $pLabel = $p->nama . ' (' . $p->tanggal_mulai?->format('d M Y') . ' - ' . $p->tanggal_selesai?->format('d M Y') . ')' . ($p->status === 'aktif' ? ' • [Aktif]' : '');
                                @endphp
                                <button type="button" 
                                    @click="selectedId = '{{ $p->id }}'; selectedLabel = '{{ addslashes($pLabel) }}'; open = false"
                                    :class="selectedId == '{{ $p->id }}' ? 'bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 font-medium' : 'text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700/60'"
                                    class="w-full text-left px-3 py-2 text-sm flex items-center justify-between gap-2 transition-colors">
                                    <span class="truncate">{{ $pLabel }}</span>
                                    <svg x-show="selectedId == '{{ $p->id }}'" class="h-4 w-4 text-blue-600 dark:text-blue-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </button>
                            @endforeach
                        </div>
                        <flux:description class="text-xs mt-1.5 text-zinc-500">
                            Sistem akan mengekspor seluruh jadwal pada rentang tanggal periode yang dipilih.
                        </flux:description>
                    </div>
                </flux:field>

                <input type="hidden" name="custom_tanggal" :value="useCustomDates ? '1' : '0'">

                {{-- Rentang Tanggal Kustom (opsional) --}}
                <div x-show="useCustomDates" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-800">
                    <flux:field>
                        <flux:label>Tanggal Mulai</flux:label>
                        <flux:input 
                            type="date" 
                            name="tanggal_mulai" 
                            value="{{ $this->periodeAktif?->tanggal_mulai?->format('Y-m-d') ?? now()->startOfMonth()->format('Y-m-d') }}" 
                        />
                    </flux:field>
                    <flux:field>
                        <flux:label>Tanggal Selesai</flux:label>
                        <flux:input 
                            type="date" 
                            name="tanggal_selesai" 
                            value="{{ $this->periodeAktif?->tanggal_selesai?->format('Y-m-d') ?? now()->endOfMonth()->format('Y-m-d') }}" 
                        />
                    </flux:field>
                </div>
            </div>

            {{-- Komponen Konten dengan Alpine.js untuk kontrol instan --}}
            <div class="space-y-3" x-data="{
                allSelected: true,
                surat: true,
                wfo: true,
                kelompok: true,
                adzan: true,
                briefing: false,
                ruangan: true,
                toggleAll() {
                    this.allSelected = !this.allSelected;
                    this.surat = this.allSelected;
                    this.wfo = this.allSelected;
                    this.kelompok = this.allSelected;
                    this.adzan = this.allSelected;
                    this.briefing = false;
                    this.ruangan = this.allSelected;
                },
                selectOnly(type) {
                    this.surat = (type === 'wfo');
                    this.wfo = (type === 'wfo');
                    this.kelompok = (type === 'wfo');
                    this.adzan = (type === 'adzan');
                    this.briefing = false;
                    this.ruangan = (type === 'ruangan');
                    this.allSelected = false;
                }
            }">
                <div class="flex items-center justify-between">
                    <flux:label class="font-medium text-sm">Pilih Jadwal yang Di-include</flux:label>
                    <button type="button" @click="toggleAll()" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">
                        <span x-text="allSelected ? 'Batal Pilih Semua' : 'Pilih Semua'"></span>
                    </button>
                </div>

                {{-- Preset Cepat: Hanya 1 Jadwal Tertentu --}}
                <div class="flex flex-wrap gap-1.5 pb-1">
                    <button type="button" @click="selectOnly('wfo')" class="px-2.5 py-1 text-xs rounded-md bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition">
                        Hanya WFO
                    </button>
                    <button type="button" @click="selectOnly('ruangan')" class="px-2.5 py-1 text-xs rounded-md bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition">
                        Hanya Ruangan
                    </button>
                    <button type="button" @click="selectOnly('adzan')" class="px-2.5 py-1 text-xs rounded-md bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition">
                        Hanya Adzan & Kitab
                    </button>
                </div>

                <div class="space-y-2 border border-zinc-200 dark:border-zinc-800 rounded-lg p-3 bg-zinc-50/50 dark:bg-zinc-900/50">
                    <label class="flex items-start gap-3 p-2 rounded hover:bg-white dark:hover:bg-zinc-800/80 cursor-pointer transition">
                        <input type="checkbox" name="include_surat" value="1" x-model="surat" class="mt-0.5 rounded border-zinc-300 dark:border-zinc-600 text-blue-600 shadow-sm focus:ring-blue-500">
                        <div class="text-sm">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">Surat Resmi Pemberitahuan WFO</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">Surat pengantar resmi Inovindo dengan tanda tangan direktur</div>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-2 rounded hover:bg-white dark:hover:bg-zinc-800/80 cursor-pointer transition">
                        <input type="checkbox" name="include_wfo" value="1" x-model="wfo" class="mt-0.5 rounded border-zinc-300 dark:border-zinc-600 text-blue-600 shadow-sm focus:ring-blue-500">
                        <div class="text-sm">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">Jadwal WFO Mingguan (Lampiran 1)</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">Matriks pembagian hari WFO tim Senin s.d. Sabtu</div>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-2 rounded hover:bg-white dark:hover:bg-zinc-800/80 cursor-pointer transition">
                        <input type="checkbox" name="include_kelompok" value="1" x-model="kelompok" class="mt-0.5 rounded border-zinc-300 dark:border-zinc-600 text-blue-600 shadow-sm focus:ring-blue-500">
                        <div class="text-sm">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">Daftar Kelompok Peserta PKL (Lampiran 2)</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">Daftar tim asal sekolah dan seluruh anggota personil</div>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-2 rounded hover:bg-white dark:hover:bg-zinc-800/80 cursor-pointer transition">
                        <input type="checkbox" name="include_ruangan" value="1" x-model="ruangan" class="mt-0.5 rounded border-zinc-300 dark:border-zinc-600 text-blue-600 shadow-sm focus:ring-blue-500">
                        <div class="text-sm">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">Jadwal Alokasi Ruangan Mingguan</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">Matriks pembagian ruangan tim WFO per hari & kapasitas</div>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-2 rounded hover:bg-white dark:hover:bg-zinc-800/80 cursor-pointer transition">
                        <input type="checkbox" name="include_adzan" value="1" x-model="adzan" class="mt-0.5 rounded border-zinc-300 dark:border-zinc-600 text-blue-600 shadow-sm focus:ring-blue-500">
                        <div class="text-sm">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">Jadwal Petugas Adzan & Pembacaan Kitab</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">Petugas sholat Zuhur dan Ashar per tanggal</div>
                        </div>
                    </label>

                    <div class="flex items-start gap-3 p-2 rounded border border-dashed border-zinc-200 dark:border-zinc-800 bg-zinc-100/60 dark:bg-zinc-800/40 opacity-60 cursor-not-allowed">
                        <input type="checkbox" name="include_briefing" value="1" disabled class="mt-0.5 rounded border-zinc-300 dark:border-zinc-600 text-zinc-400 cursor-not-allowed">
                        <div class="text-sm">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-zinc-500 dark:text-zinc-400">Jadwal Petugas Briefing</span>
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-400 border border-amber-300 dark:border-amber-800/60">
                                    In Development
                                </span>
                            </div>
                            <div class="text-xs text-zinc-400 dark:text-zinc-500">Jadwal penugasan notulis & pemateri sesi Pagi dan Sore (Segera Hadir)</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="arrow-down-tray">
                    Download PDF
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
