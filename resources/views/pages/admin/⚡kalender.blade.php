<?php

use App\Models\JadwalAdzanKitab;
use App\Models\JadwalBriefing;
use App\Models\Tim;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Kalender')] #[Layout('layouts.admin')] class extends Component {

    public string $filterJenis = '';
    public string $filterTimId = '';
    public string $searchTim = ''; // Search nama tim
    public string $viewMode    = 'kalender'; // 'kalender' | 'tabel'

    // Filter tabel: bulan/tahun
    public string $tabelBulan = '';

    public function mount(): void
    {
        $this->tabelBulan = now()->format('Y-m');
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
        $query = Tim::orderBy('nama_tim');

        // Filter by search text
        if ($this->searchTim) {
            $query->where('nama_tim', 'like', '%' . $this->searchTim . '%');
        }

        return $query->get(['id', 'nama_tim']);
    }

    /**
     * Data tabel adzan format Inovindo:
     * [tanggal => ['dhuhr_adzan' => row, 'dhuhr_kajian' => row, 'asr_adzan' => row, 'asr_kajian' => row]]
     */
    #[Computed]
    public function tabelAdzan(): array
    {
        [$mulai, $selesai] = $this->rentangBulan();

        $query = JadwalAdzanKitab::with('personil.tim')
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

        $query = JadwalBriefing::with('personil', 'tim')
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
        $mulai  = Carbon::parse($bulan . '-01')->startOfMonth();
        $selesai = $mulai->copy()->endOfMonth();

        return [$mulai->toDateString(), $selesai->toDateString()];
    }

    public function bulanLabel(): string
    {
        return Carbon::parse(($this->tabelBulan ?: now()->format('Y-m')) . '-01')
            ->translatedFormat('F Y');
    }
}; ?>

<div
    x-data="{
        calendar: null,
        modalOpen: false,
        selectedEvent: null,
        filterJenis: '',
        filterTimId: '',
        currentView: 'dayGridMonth',
        activeDateLabel: '',
        isSaturday: false,

        initCalendar() {
            this.calendar = new FullCalendar.Calendar(this.$refs.kalender, {
                initialView: 'dayGridMonth',
                locale: FullCalendar.idLocale,
                plugins: [
                    FullCalendar.dayGridPlugin,
                    FullCalendar.timeGridPlugin,
                    FullCalendar.listPlugin,
                    FullCalendar.interactionPlugin,
                ],
                buttonText: {
                    today: 'Hari Ini',
                    month: 'Bulan',
                    week:  'Minggu',
                    day:   'Harian',
                },
                slotMinTime: '09:00:00',
                slotMaxTime: '17:00:00',
                allDaySlot: true,
                allDayText: 'WFO / Ruangan',
                slotLabelFormat: {
                    hour:   '2-digit',
                    minute: '2-digit',
                    hour12: false,
                },
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridDay'
                },
                navLinks: true,
                navLinkDayClick: (date) => {
                    this.calendar.changeView('timeGridDay', date);
                },
                dateClick: (info) => {
                    if (this.currentView === 'dayGridMonth') {
                        this.calendar.changeView('timeGridDay', info.dateStr);
                    }
                },
                datesSet: (info) => {
                    this.currentView = info.view.type;
                    const dateObj = info.view.currentStart;
                    const dayOfWeek = dateObj.getDay(); // 0 = Sun, 6 = Sat
                    this.isSaturday = (dayOfWeek === 6);

                    // Format tanggal Bahasa Indonesia
                    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                    this.activeDateLabel = dateObj.toLocaleDateString('id-ID', options);

                    // Dinamis jam kerja: Sabtu s/d 14:00, Senin-Jumat s/d 17:00
                    if (this.currentView === 'timeGridDay') {
                        if (this.isSaturday) {
                            this.calendar.setOption('slotMaxTime', '14:00:00');
                        } else {
                            this.calendar.setOption('slotMaxTime', '17:00:00');
                        }
                    }
                },
                events: (info, successCb, failureCb) => {
                    const url = `/admin/kalender/events?start=${info.startStr}&end=${info.endStr}&tim_id=${this.filterTimId}`;
                    fetch(url)
                        .then(r => r.json())
                        .then(data => {
                            if (this.filterJenis) {
                                data = data.filter(e => e.extendedProps.jenis === this.filterJenis);
                            }
                            successCb(data);
                        })
                        .catch(failureCb);
                },
                eventClick: (info) => {
                    this.selectedEvent = {
                        title: info.event.title,
                        ...info.event.extendedProps,
                        tanggal: info.event.startStr,
                    };
                    this.modalOpen = true;
                },
                eventDisplay: 'block',
                dayMaxEvents: 4,
                height: 'auto',
            });
            this.calendar.render();
        },

        refetchEvents() {
            if (this.calendar) this.calendar.refetchEvents();
        }
    }"
    x-init="$watch('$wire.viewMode', v => { if (v === 'kalender') $nextTick(() => { initCalendar() }) })"
    @filter-changed.window="filterJenis = $event.detail.jenis; filterTimId = String($event.detail.timId ?? ''); refetchEvents()"
    @sidebar-toggled.window="setTimeout(() => { if (calendar) calendar.updateSize() }, 220)"
    class="flex flex-col gap-6"
>
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Kalender Jadwal</flux:heading>
            <flux:text class="text-zinc-500">Adzan/kajian, briefing, dan alokasi ruangan.</flux:text>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            {{-- Toggle Kalender / Tabel --}}
            <div class="flex rounded-lg border border-zinc-700 overflow-hidden">
                <button
                    wire:click="$set('viewMode', 'kalender')"
                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium transition-colors
                           {{ $viewMode === 'kalender' ? 'bg-zinc-700 text-white' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800' }}"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
                    </svg>
                    Kalender
                </button>
                <button
                    wire:click="$set('viewMode', 'tabel')"
                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium transition-colors border-l border-zinc-700
                           {{ $viewMode === 'tabel' ? 'bg-zinc-700 text-white' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800' }}"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path d="M3 10h18M3 14h18M10 4v16M3 4h18v16H3z"/>
                    </svg>
                    Tabel
                </button>
            </div>

            {{-- Filter jenis (kalender saja) --}}
            @if ($viewMode === 'kalender')
                <flux:select wire:model.live="filterJenis" class="w-40">
                    <flux:select.option value="">Semua Jenis</flux:select.option>
                    <flux:select.option value="adzan">Adzan/Kajian</flux:select.option>
                    <flux:select.option value="briefing">Briefing</flux:select.option>
                    <flux:select.option value="ruangan">Alokasi Ruangan</flux:select.option>
                </flux:select>
            @endif

            {{-- Filter tim --}}
            <x-simple-select
                wire:model.live="filterTimId"
                name="filterTimId"
                placeholder="Semua Tim"
                :options="$this->timList->map(fn($t) => ['value' => (string)$t->id, 'label' => $t->nama_tim])->prepend(['value' => '', 'label' => 'Semua Tim'])->toArray()"
                :modelValue="$filterTimId"
                class="w-48"
            />

            {{-- Search nama tim --}}
            <flux:input 
                wire:model.live.debounce.300ms="searchTim" 
                placeholder="Cari nama tim..."
                icon="magnifying-glass"
                class="w-64"
            />
        </div>
    </div>

    {{-- â•â• VIEW: KALENDER â•â• --}}
    @if ($viewMode === 'kalender')
        {{-- Legend --}}
        <div class="flex gap-4 flex-wrap text-xs">
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

        <flux:card class="p-4 overflow-hidden">
            <style>
                /* ── Header hari ── */
                .fc .fc-col-header-cell-cushion {
                    color: #71717a !important;
                    font-size: 0.75rem; font-weight: 500;
                    text-transform: uppercase; letter-spacing: 0.05em;
                    text-decoration: none !important;
                }
                /* ── Nomor tanggal ── */
                .fc .fc-daygrid-day-number {
                    color: #71717a;
                    text-decoration: none !important;
                    font-size: 0.75rem;
                }
                /* ── Border grid — ikut tema ── */
                .fc-theme-standard td,
                .fc-theme-standard th,
                .fc-theme-standard .fc-scrollgrid {
                    border-color: var(--color-zinc-200, #e5e5e5) !important;
                }
                .dark .fc-theme-standard td,
                .dark .fc-theme-standard th,
                .dark .fc-theme-standard .fc-scrollgrid {
                    border-color: #3f3f46 !important;
                }
                /* ── Header row ── */
                .fc .fc-col-header-cell {
                    background-color: var(--color-zinc-50, #fafafa) !important;
                }
                .dark .fc .fc-col-header-cell {
                    background-color: #18181b !important;
                }
                /* ── Background hari ── */
                .fc .fc-daygrid-day,
                .fc .fc-daygrid-body,
                .fc .fc-timegrid-slot,
                .fc .fc-timegrid-col {
                    background-color: transparent !important;
                }

                /* Hari Minggu - Tanda Libur */
                .fc .fc-daygrid-day.fc-day-sun {
                    background-color: rgba(254, 242, 242, 0.45) !important;
                }
                .dark .fc .fc-daygrid-day.fc-day-sun {
                    background-color: rgba(239, 68, 68, 0.04) !important;
                }
                .fc .fc-daygrid-day.fc-day-sun .fc-daygrid-day-number {
                    color: #ef4444 !important;
                    font-weight: 600;
                }
                .fc .fc-daygrid-day.fc-day-sun .fc-daygrid-day-top::after {
                    content: 'Libur';
                    font-size: 9px;
                    font-weight: 600;
                    color: #dc2626;
                    background: rgba(239, 68, 68, 0.12);
                    padding: 1px 4px;
                    border-radius: 4px;
                    margin-right: 4px;
                }

                /* Pointer hover on Month Grid */
                .fc-daygrid-day-frame {
                    cursor: pointer;
                    transition: background-color 0.15s ease;
                }
                .fc-daygrid-day-frame:hover {
                    background-color: rgba(59, 113, 202, 0.06);
                }
                .dark .fc-daygrid-day-frame:hover {
                    background-color: rgba(59, 113, 202, 0.12);
                }

                /* ── Hari ini ── */
                .fc .fc-daygrid-day.fc-day-today {
                    background-color: rgba(59,113,202,0.08) !important;
                }
                .fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
                    color: #3B71CA !important;
                    font-weight: 600;
                }
                /* ── Hari luar bulan ── */
                .fc .fc-day-other .fc-daygrid-day-number { color: #a3a3a3 !important; }
                /* ── Toolbar buttons ── */
                .fc .fc-button, .fc .fc-button-primary {
                    background-color: var(--color-zinc-100, #f5f5f5) !important;
                    border-color: var(--color-zinc-200, #e5e5e5) !important;
                    color: var(--color-zinc-700, #3f3f46) !important;
                    font-size: 0.75rem; padding: 0.3rem 0.6rem; box-shadow: none !important;
                    border-radius: 0.5rem !important;
                }
                .dark .fc .fc-button, .dark .fc .fc-button-primary {
                    background-color: #27272a !important;
                    border-color: #3f3f46 !important;
                    color: #d4d4d8 !important;
                }
                .fc .fc-button:hover, .fc .fc-button-primary:hover {
                    background-color: var(--color-zinc-200, #e5e5e5) !important;
                    color: #111 !important;
                }
                .dark .fc .fc-button:hover, .dark .fc .fc-button-primary:hover {
                    background-color: #3f3f46 !important;
                    color: #fff !important;
                }
                .fc .fc-button-active,
                .fc .fc-button-primary:not(:disabled).fc-button-active {
                    background-color: #3B71CA !important;
                    border-color: #3B71CA !important;
                    color: #ffffff !important;
                    font-weight: 600;
                }
                /* ── Judul bulan ── */
                .fc .fc-toolbar-title {
                    color: var(--color-zinc-900, #171717) !important;
                    font-size: 1.1rem !important; font-weight: 600;
                }
                .dark .fc .fc-toolbar-title { color: #f4f4f5 !important; }
                /* ── "+N lebih" ── */
                .fc .fc-daygrid-more-link { color: #3B71CA !important; font-size: 0.7rem; }
                /* ── Slot label time grid ── */
                .fc .fc-timegrid-slot-label { color: #71717a; font-size: 0.7rem; }
                .fc .fc-timegrid-now-indicator-line { border-color: #3B71CA; }
                .fc .fc-all-day-text { color: #71717a; font-size: 0.7rem; }
                /* ── List empty ── */
                .fc .fc-list-empty { color: #71717a; }
            </style>

            {{-- Breadcrumb Navigation saat di Mode Harian --}}
            <div x-show="currentView === 'timeGridDay'" x-transition class="mb-4 flex flex-wrap items-center justify-between gap-3 p-3 rounded-xl bg-blue-50/70 dark:bg-blue-950/30 border border-blue-200/70 dark:border-blue-800/50">
                <div class="flex items-center gap-2.5">
                    <button
                        type="button"
                        @click="calendar.changeView('dayGridMonth')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-800 dark:text-zinc-200 transition-all border border-zinc-200 dark:border-zinc-700 shadow-xs cursor-pointer"
                    >
                        <flux:icon icon="arrow-left" class="size-3.5 text-blue-600 dark:text-blue-400" />
                        <span>← Kembali ke Tampilan Bulan</span>
                    </button>
                    <span class="text-zinc-300 dark:text-zinc-600">/</span>
                    <span class="text-xs font-bold text-blue-700 dark:text-blue-300 capitalize" x-text="activeDateLabel"></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-medium bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300">
                        <flux:icon icon="clock" class="size-3" />
                        <span x-text="isSaturday ? 'Jam Operasional: 09:00 - 14:00 (Sabtu)' : 'Jam Operasional: 09:00 - 17:00 (Weekday)'"></span>
                    </span>
                </div>
            </div>

            <div wire:ignore x-init="initCalendar()">
                <div x-ref="kalender"></div>
            </div>
        </flux:card>
    @endif

    {{-- â•â• VIEW: TABEL FORMAT INOVINDO â•â• --}}
    @if ($viewMode === 'tabel')
        {{-- Navigasi bulan --}}
        <div class="flex items-center gap-3">
            <button
                wire:click="$set('tabelBulan', '{{ \Carbon\Carbon::parse(($tabelBulan ?: now()->format('Y-m')) . '-01')->subMonth()->format('Y-m') }}')"
                class="p-1.5 rounded-md text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
            <span class="text-sm font-semibold text-zinc-100 min-w-[120px] text-center">
                {{ $this->bulanLabel() }}
            </span>
            <button
                wire:click="$set('tabelBulan', '{{ \Carbon\Carbon::parse(($tabelBulan ?: now()->format('Y-m')) . '-01')->addMonth()->format('Y-m') }}')"
                class="p-1.5 rounded-md text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </div>

        {{-- Tabel Adzan & Kajian format Inovindo --}}
        @if (count($this->tabelAdzan) > 0)
            <flux:card class="p-0 overflow-hidden">
                <div class="px-4 py-3 border-b border-zinc-800">
                    <flux:heading size="sm">Jadwal Petugas Adzan & Pembacaan Kitab</flux:heading>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs border-collapse">
                        <thead>
                            {{-- Level 1: group header --}}
                            <tr>
                                <th rowspan="2" class="border border-zinc-700 bg-zinc-800 px-3 py-2 text-left text-zinc-200 font-semibold w-28">
                                    Hari, Tanggal
                                </th>
                                <th rowspan="2" class="border border-zinc-700 bg-zinc-800 px-3 py-2 text-center text-zinc-200 font-semibold w-16">
                                    Hari
                                </th>
                                <th colspan="2" class="border border-zinc-700 bg-zinc-800 px-3 py-2 text-center text-zinc-200 font-semibold">
                                    Zuhur
                                </th>
                                <th colspan="2" class="border border-zinc-700 bg-zinc-800 px-3 py-2 text-center text-zinc-200 font-semibold">
                                    Ashar
                                </th>
                            </tr>
                            {{-- Level 2: sub header --}}
                            <tr>
                                <th class="border border-zinc-700 bg-zinc-700/60 px-3 py-1.5 text-center text-zinc-300 font-medium">Adzan</th>
                                <th class="border border-zinc-700 bg-zinc-700/60 px-3 py-1.5 text-center text-zinc-300 font-medium">Pembacaan Kitab</th>
                                <th class="border border-zinc-700 bg-zinc-700/60 px-3 py-1.5 text-center text-zinc-300 font-medium">Adzan</th>
                                <th class="border border-zinc-700 bg-zinc-700/60 px-3 py-1.5 text-center text-zinc-300 font-medium">Pembacaan Kitab</th>
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

                                    $fmtPersonil = fn($row) => $row
                                        ? ($row->personil?->nama ?? 'â€”') . "\n(" . ($row->personil?->tim?->nama_tim ?? 'â€”') . ")"
                                        : 'â€”';
                                @endphp
                                <tr class="{{ $isEven ? 'bg-zinc-800/40' : 'bg-zinc-900/60' }} hover:bg-zinc-800/70 transition-colors">
                                    <td class="border border-zinc-700 px-3 py-2 text-zinc-200">
                                        {{ $carbon->locale('id')->translatedFormat('d F Y') }}
                                    </td>
                                    <td class="border border-zinc-700 px-3 py-2 text-center font-bold text-zinc-100 uppercase">
                                        {{ strtoupper(substr($carbon->locale('id')->translatedFormat('l'), 0, 4)) }}
                                    </td>
                                    <td class="border border-zinc-700 px-3 py-2 whitespace-pre-line text-zinc-300">{{ $fmtPersonil($dhuhrAdzan) }}</td>
                                    <td class="border border-zinc-700 px-3 py-2 whitespace-pre-line text-zinc-300">{{ $fmtPersonil($dhuhrKajian) }}</td>
                                    <td class="border border-zinc-700 px-3 py-2 whitespace-pre-line text-zinc-300">{{ $fmtPersonil($asrAdzan) }}</td>
                                    <td class="border border-zinc-700 px-3 py-2 whitespace-pre-line text-zinc-300">{{ $fmtPersonil($asrKajian) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </flux:card>
        @else
            <x-empty-state icon="book-open" title="Tidak ada jadwal adzan/kajian" description="Belum ada data untuk bulan {{ $this->bulanLabel() }}." />
        @endif

        {{-- Tabel Briefing --}}
        @if ($this->tabelBriefing->isNotEmpty())
            <flux:card class="p-0 overflow-hidden">
                <div class="px-4 py-3 border-b border-zinc-800">
                    <flux:heading size="sm">Jadwal Briefing</flux:heading>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr>
                                <th class="border border-zinc-700 bg-zinc-800 px-3 py-2 text-left text-zinc-200 font-semibold">Tanggal</th>
                                <th class="border border-zinc-700 bg-zinc-800 px-3 py-2 text-center text-zinc-200 font-semibold">Hari</th>
                                <th class="border border-zinc-700 bg-zinc-800 px-3 py-2 text-center text-zinc-200 font-semibold">Sesi</th>
                                <th class="border border-zinc-700 bg-zinc-800 px-3 py-2 text-left text-zinc-200 font-semibold">Perwakilan</th>
                                <th class="border border-zinc-700 bg-zinc-800 px-3 py-2 text-left text-zinc-200 font-semibold">Tim</th>
                                <th class="border border-zinc-700 bg-zinc-800 px-3 py-2 text-center text-zinc-200 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->tabelBriefing as $j)
                                <tr class="{{ $loop->even ? 'bg-zinc-800/40' : 'bg-zinc-900/60' }} hover:bg-zinc-800/70 transition-colors">
                                    <td class="border border-zinc-700 px-3 py-2 text-zinc-200">{{ $j->tanggal->format('d/m/Y') }}</td>
                                    <td class="border border-zinc-700 px-3 py-2 text-center font-bold text-zinc-100 uppercase">
                                        {{ strtoupper(substr($j->tanggal->locale('id')->translatedFormat('l'), 0, 4)) }}
                                    </td>
                                    <td class="border border-zinc-700 px-3 py-2 text-center text-zinc-300">{{ ucfirst($j->sesi) }}</td>
                                    <td class="border border-zinc-700 px-3 py-2 text-zinc-300">{{ $j->personil?->nama ?? 'â€”' }}</td>
                                    <td class="border border-zinc-700 px-3 py-2 text-zinc-400">{{ $j->tim?->nama_tim ?? 'â€”' }}</td>
                                    <td class="border border-zinc-700 px-3 py-2 text-center">
                                        <x-status-badge :status="$j->status_konfirmasi" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </flux:card>
        @endif
    @endif

    {{-- Modal detail event (kalender) --}}
    <template x-if="modalOpen">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @keydown.escape.window="modalOpen = false">
            {{-- Backdrop --}}
            <div class="fixed inset-0 bg-black/50" 
                 style="backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);"
                 @click="modalOpen = false"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0">
            </div>

            {{-- Modal Content --}}
            <div class="relative bg-white dark:bg-zinc-900 rounded-xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto"
                 @click.stop
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95">
                
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="font-semibold text-zinc-900 dark:text-zinc-100" x-text="selectedEvent?.title"></h3>
                            <p class="text-sm text-zinc-500 mt-0.5" x-text="selectedEvent?.tanggal"></p>
                        </div>
                        <button @click="modalOpen = false" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-3">
                        <template x-if="selectedEvent?.jenis === 'adzan'">
                            <div>
                                <p class="text-xs text-zinc-500 font-medium mb-1">Waktu Sholat</p>
                                <p class="text-sm" x-text="selectedEvent?.waktu_sholat?.toUpperCase() ?? '-'"></p>
                            </div>
                        </template>

                        <template x-if="selectedEvent?.jenis === 'briefing'">
                            <div>
                                <p class="text-xs text-zinc-500 font-medium mb-1">Sesi</p>
                                <p class="text-sm" x-text="selectedEvent?.sesi ?? '-'"></p>
                            </div>
                        </template>

                        <template x-if="selectedEvent?.jenis === 'ruangan'">
                            <div>
                                <p class="text-xs text-zinc-500 font-medium mb-1">Ruangan</p>
                                <p class="text-sm" x-text="selectedEvent?.ruangan ?? '-'"></p>
                            </div>
                        </template>

                        <div x-show="selectedEvent?.personil">
                            <p class="text-xs text-zinc-500 font-medium mb-1">Personil</p>
                            <p class="text-sm" x-text="selectedEvent?.personil ?? '-'"></p>
                        </div>
                        
                        <div x-show="selectedEvent?.tim">
                            <p class="text-xs text-zinc-500 font-medium mb-1">Tim</p>
                            <p class="text-sm" x-text="selectedEvent?.tim ?? '-'"></p>
                        </div>
                        
                        <div x-show="selectedEvent?.status_konfirmasi">
                            <p class="text-xs text-zinc-500 font-medium mb-1">Status</p>
                            <p class="text-sm" x-text="selectedEvent?.status_konfirmasi ?? '-'"></p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button @click="modalOpen = false" 
                                class="px-4 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition-colors">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>



