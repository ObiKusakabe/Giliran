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
        return Tim::orderBy('nama_tim')->get(['id', 'nama_tim']);
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
                    today: 'hari ini',
                    month: 'Bulan',
                    week:  'Minggu',
                    day:   'Hari',
                },
                slotMinTime: '09:00:00',
                slotMaxTime: '17:00:00',
                allDaySlot: true,
                allDayText: 'Ruangan',
                slotLabelFormat: {
                    hour:   'numeric',
                    minute: '2-digit',
                    hour12: true,
                },
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
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

            {{-- Filter tim (keduanya) --}}
            <flux:select wire:model.live="filterTimId" class="w-48">
                <flux:select.option value="">Semua Tim</flux:select.option>
                @foreach ($this->timList as $tim)
                    <flux:select.option :value="$tim->id">{{ $tim->nama_tim }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    {{-- â•â• VIEW: KALENDER â•â• --}}
    @if ($viewMode === 'kalender')
        {{-- Legend --}}
        <div class="flex gap-4 flex-wrap text-xs">
            <span class="flex items-center gap-1.5">
                <span class="h-3 w-3 rounded-sm bg-[#1591D8]"></span> Adzan & Kajian
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
                .fc .fc-col-header-cell-cushion { color: #a1a1aa !important; font-size: 0.75rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; text-decoration: none !important; }
                .fc .fc-daygrid-day-number { color: #71717a; text-decoration: none !important; font-size: 0.75rem; }
                .fc-theme-standard td, .fc-theme-standard th, .fc-theme-standard .fc-scrollgrid { border-color: #3f3f46 !important; }
                .fc .fc-col-header-cell { background-color: #18181b !important; border-color: #3f3f46 !important; }
                .fc .fc-daygrid-day { background-color: #09090b; }
                .fc .fc-day-sun { background-color: #09090b !important; }
                .fc .fc-daygrid-day.fc-day-today { background-color: rgba(25,118,210,0.10) !important; }
                .fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number { color: #60a5fa !important; font-weight: 600; }
                .fc .fc-day-other .fc-daygrid-day-number { color: #52525b !important; }
                .fc .fc-button, .fc .fc-button-primary { background-color: #27272a !important; border-color: #3f3f46 !important; color: #d4d4d8 !important; font-size: 0.75rem; padding: 0.3rem 0.6rem; box-shadow: none !important; }
                .fc .fc-button:hover, .fc .fc-button-primary:hover { background-color: #3f3f46 !important; border-color: #52525b !important; color: #fff !important; }
                .fc .fc-button-active, .fc .fc-button-primary:not(:disabled).fc-button-active { background-color: #f4f4f5 !important; border-color: #e4e4e7 !important; color: #18181b !important; font-weight: 600; }
                .fc .fc-toolbar-title { color: #f4f4f5 !important; font-size: 1.1rem !important; font-weight: 600; }
                .fc .fc-daygrid-more-link { color: #60a5fa !important; font-size: 0.7rem; }
                .fc-theme-standard .fc-list { border-color: #3f3f46 !important; }
                .fc .fc-list-empty { background-color: #09090b; color: #71717a; }
                .fc .fc-timegrid-slot { background-color: #09090b; }
                .fc .fc-timegrid-slot-label { color: #71717a; font-size: 0.7rem; }
                .fc .fc-timegrid-col { background-color: #09090b; }
                .fc .fc-timegrid-now-indicator-line { border-color: #60a5fa; }
                .fc .fc-daygrid-body { background-color: #09090b; }
                .fc .fc-all-day-text { color: #71717a; font-size: 0.7rem; }
            </style>
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
                                        {{ $carbon->translatedFormat('d F Y') }}
                                    </td>
                                    <td class="border border-zinc-700 px-3 py-2 text-center font-bold text-zinc-100 uppercase">
                                        {{ strtoupper(substr($carbon->translatedFormat('l'), 0, 4)) }}
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
                                        {{ strtoupper(substr($j->tanggal->translatedFormat('l'), 0, 4)) }}
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
    <div
        x-show="modalOpen"
        x-transition
        @keydown.escape.window="modalOpen = false"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40"
    >
        <div
            @click.outside="modalOpen = false"
            class="bg-white dark:bg-zinc-800 rounded-xl shadow-xl w-full max-w-sm p-6 flex flex-col gap-4"
        >
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="font-semibold text-zinc-900 dark:text-zinc-100" x-text="selectedEvent?.title"></h3>
                    <p class="text-sm text-zinc-500 mt-0.5" x-text="selectedEvent?.tanggal"></p>
                </div>
                <button @click="modalOpen = false" class="text-zinc-400 hover:text-zinc-600 p-1">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <dl class="text-sm flex flex-col gap-2">
                <template x-if="selectedEvent?.jenis === 'adzan'">
                    <div class="flex flex-col gap-1">
                        <div class="flex gap-2"><dt class="text-zinc-400 w-24">Personil</dt><dd x-text="selectedEvent?.personil"></dd></div>
                        <div class="flex gap-2"><dt class="text-zinc-400 w-24">Tim</dt><dd x-text="selectedEvent?.tim"></dd></div>
                        <div class="flex gap-2"><dt class="text-zinc-400 w-24">Waktu</dt><dd x-text="selectedEvent?.waktu_sholat?.toUpperCase()"></dd></div>
                        <div class="flex gap-2"><dt class="text-zinc-400 w-24">Tugas</dt><dd x-text="selectedEvent?.jenis_tugas"></dd></div>
                        <div class="flex gap-2"><dt class="text-zinc-400 w-24">Status</dt><dd x-text="selectedEvent?.status_konfirmasi"></dd></div>
                    </div>
                </template>
                <template x-if="selectedEvent?.jenis === 'briefing'">
                    <div class="flex flex-col gap-1">
                        <div class="flex gap-2"><dt class="text-zinc-400 w-24">Personil</dt><dd x-text="selectedEvent?.personil"></dd></div>
                        <div class="flex gap-2"><dt class="text-zinc-400 w-24">Tim</dt><dd x-text="selectedEvent?.tim"></dd></div>
                        <div class="flex gap-2"><dt class="text-zinc-400 w-24">Sesi</dt><dd x-text="selectedEvent?.sesi"></dd></div>
                        <div class="flex gap-2"><dt class="text-zinc-400 w-24">Status</dt><dd x-text="selectedEvent?.status_konfirmasi"></dd></div>
                    </div>
                </template>
                <template x-if="selectedEvent?.jenis === 'ruangan'">
                    <div class="flex flex-col gap-1">
                        <div class="flex gap-2"><dt class="text-zinc-400 w-24">Tim</dt><dd x-text="selectedEvent?.tim"></dd></div>
                        <div class="flex gap-2"><dt class="text-zinc-400 w-24">Ruangan</dt><dd x-text="selectedEvent?.ruangan"></dd></div>
                        <div class="flex gap-2"><dt class="text-zinc-400 w-24">Kapasitas</dt><dd x-text="selectedEvent?.kapasitas + ' orang'"></dd></div>
                    </div>
                </template>
            </dl>
        </div>
    </div>
</div>


