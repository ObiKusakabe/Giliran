<?php

use App\Models\AlokasiRuangan;
use App\Models\JadwalAdzanKitab;
use App\Models\JadwalBriefing;
use App\Models\JadwalWfo;
use App\Models\PeriodeWfo;
use App\Models\Tim;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('')] #[Layout('layouts.admin')] class extends Component {

    // Calendar filters
    public string $filterJenis = '';
    public string $filterTimId = '';
    
    // View mode: kalender | tabel (default: tabel)
    public string $viewMode = 'tabel';
    
    // Tabel bulan filter
    public string $tabelBulan = '';

    // Modal states
    public bool $modalGenerate = false;
    public bool $modalExport = false;
    
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

    #[Computed]
    public function periodeAktif(): ?PeriodeWfo
    {
        return PeriodeWfo::where('status', 'aktif')->first();
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

    /** DSB-03: Konfirmasi tertunda (status masih menunggu, jadwal mendatang) */
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
                'jenis'   => ucfirst($j->jenis_tugas).' '.strtoupper($j->waktu_sholat),
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

    public function bukaGenerate(): void
    {
        if (! $this->periodeAktif) {
            $this->dispatch('notify', type: 'error', message: 'Tidak ada periode WFO aktif.');
            return;
        }
        $this->modalGenerate = true;
    }

    public function generateJadwal(): void
    {
        if (! $this->periodeAktif) {
            $this->dispatch('notify', type: 'error', message: 'Tidak ada periode WFO aktif.');
            return;
        }

        // Redirect to generate page with periode context
        $this->redirect(route('admin.generate-jadwal'));
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

<div class="flex flex-col gap-6" 
    wire:poll="60000"
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
                dayHeaderFormat: { weekday: 'long' },
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
    x-init="$nextTick(() => { if ($wire.viewMode === 'kalender') initCalendar() }); $watch('$wire.viewMode', v => { if (v === 'kalender') $nextTick(() => initCalendar()) })"
    @filter-changed.window="filterJenis = $event.detail.jenis; filterTimId = String($event.detail.timId ?? ''); refetchEvents()"
    @sidebar-toggled.window="setTimeout(() => { if (calendar) calendar.updateSize() }, 220)"
>
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
                Kelola rotasi kerja, jadwal WFO, petugas adzan & briefing hari ini — <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ now()->translatedFormat('l, d F Y') }}</span>
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
            <flux:button variant="primary" icon="sparkles" wire:click="bukaGenerate">
                Generate Jadwal
            </flux:button>
            <flux:button variant="filled" icon="arrow-down-tray" wire:click="exportPDF">
                Export PDF
            </flux:button>
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

    {{-- 4 Stat Cards with Watermark Background Icons --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="relative z-10 pr-6">
                <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Personil Terjadwal</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                    {{ $this->personilTerjadwalHariIni }}
                </flux:heading>
                <div class="mt-2 flex items-center gap-1 text-[11px] text-blue-600 dark:text-blue-400 font-medium">
                    <span class="size-1.5 rounded-full bg-blue-500"></span>
                    <span>Hari ini</span>
                </div>
            </div>
            <flux:icon icon="user-group" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-blue-500/10 dark:text-blue-400/10 pointer-events-none" />
        </flux:card>

        <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="relative z-10 pr-6">
                <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Ruang Teralokasi</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                    {{ $this->ruanganTeralokasHariIni }}
                </flux:heading>
                <div class="mt-2 flex items-center gap-1 text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">
                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                    <span>Terisi</span>
                </div>
            </div>
            <flux:icon icon="building-office-2" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-emerald-500/10 dark:text-emerald-400/10 pointer-events-none" />
        </flux:card>

        <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="relative z-10 pr-6">
                <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Konfirmasi Tertunda</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold tracking-tight {{ $this->konfirmasiTertunda > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                    {{ $this->konfirmasiTertunda }}
                </flux:heading>
                <div class="mt-2 flex items-center gap-1 text-[11px] {{ $this->konfirmasiTertunda > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-400' }} font-medium">
                    <span class="size-1.5 rounded-full {{ $this->konfirmasiTertunda > 0 ? 'bg-amber-500' : 'bg-zinc-400' }}"></span>
                    <span>Menunggu respon</span>
                </div>
            </div>
            <flux:icon icon="clock" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-amber-500/10 dark:text-amber-400/10 pointer-events-none" />
        </flux:card>

        <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="relative z-10 pr-6">
                <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Tim WFO Hari Ini</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-purple-600 dark:text-purple-400">
                    {{ $this->timWfoHariIni }}
                </flux:heading>
                <div class="mt-2 flex items-center gap-1 text-[11px] text-purple-600 dark:text-purple-400 font-medium">
                    <span class="size-1.5 rounded-full bg-purple-500"></span>
                    <span>Aktif di kantor</span>
                </div>
            </div>
            <flux:icon icon="calendar-days" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-purple-500/10 dark:text-purple-400/10 pointer-events-none" />
        </flux:card>
    </div>

    {{-- Kalender Section --}}
    <flux:card class="p-0 overflow-hidden">
        <div class="px-4 py-3 border-b border-zinc-100 dark:border-zinc-800 flex items-center justify-between">
            <div>
                <flux:heading size="sm">Kalender Jadwal</flux:heading>
                <flux:text class="text-xs text-zinc-400">Adzan/kajian, briefing, dan alokasi ruangan</flux:text>
            </div>

            {{-- Toggle & Filters --}}
            <div class="flex items-center gap-3">
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

                {{-- Filter tim (both views) --}}
                <flux:select wire:model.live="filterTimId" class="w-48">
                    <flux:select.option value="">Semua Tim</flux:select.option>
                    @foreach ($this->timList as $tim)
                        <flux:select.option :value="$tim->id">{{ $tim->nama_tim }}</flux:select.option>
                    @endforeach
                </flux:select>
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
            <div class="p-4">
            <style>
                /* FullCalendar styling - matching kalender page */
                .fc .fc-col-header-cell-cushion {
                    color: #71717a !important;
                    font-size: 0.75rem; font-weight: 500;
                    text-transform: uppercase; letter-spacing: 0.05em;
                    text-decoration: none !important;
                }
                .fc .fc-daygrid-day-number {
                    color: #71717a;
                    text-decoration: none !important;
                    font-size: 0.75rem;
                }
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
                .fc .fc-col-header-cell {
                    background-color: var(--color-zinc-50, #fafafa) !important;
                }
                .dark .fc .fc-col-header-cell {
                    background-color: #18181b !important;
                }
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

                .fc .fc-daygrid-day.fc-day-today {
                    background-color: rgba(59,113,202,0.08) !important;
                }
                .fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
                    color: #3B71CA !important;
                    font-weight: 600;
                }
                .fc .fc-day-other .fc-daygrid-day-number { color: #a3a3a3 !important; }
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
                .fc .fc-toolbar-title {
                    color: var(--color-zinc-900, #171717) !important;
                    font-size: 1.1rem !important; font-weight: 600;
                }
                .dark .fc .fc-toolbar-title { color: #f4f4f5 !important; }
                .fc .fc-daygrid-more-link { color: #3B71CA !important; font-size: 0.7rem; }
                .fc .fc-timegrid-slot-label { color: #71717a; font-size: 0.7rem; }
                .fc .fc-timegrid-now-indicator-line { border-color: #3B71CA; }
                .fc .fc-all-day-text { color: #71717a; font-size: 0.7rem; }
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
        </div>
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
                <div class="overflow-x-auto">
                    <table class="w-full text-xs border-collapse">
                        <thead>
                            {{-- Level 1: group header --}}
                            <tr>
                                <th rowspan="2" class="border border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800 px-3 py-2 text-left text-zinc-700 dark:text-zinc-200 font-semibold w-28">
                                    Hari, Tanggal
                                </th>
                                <th rowspan="2" class="border border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800 px-3 py-2 text-center text-zinc-700 dark:text-zinc-200 font-semibold w-16">
                                    Hari
                                </th>
                                <th colspan="2" class="border border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800 px-3 py-2 text-center text-zinc-700 dark:text-zinc-200 font-semibold">
                                    Zuhur
                                </th>
                                <th colspan="2" class="border border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800 px-3 py-2 text-center text-zinc-700 dark:text-zinc-200 font-semibold">
                                    Ashar
                                </th>
                            </tr>
                            {{-- Level 2: sub header --}}
                            <tr>
                                <th class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-700/60 px-3 py-1.5 text-center text-zinc-600 dark:text-zinc-300 font-medium">Adzan</th>
                                <th class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-700/60 px-3 py-1.5 text-center text-zinc-600 dark:text-zinc-300 font-medium">Pembacaan Kitab</th>
                                <th class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-700/60 px-3 py-1.5 text-center text-zinc-600 dark:text-zinc-300 font-medium">Adzan</th>
                                <th class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-700/60 px-3 py-1.5 text-center text-zinc-600 dark:text-zinc-300 font-medium">Pembacaan Kitab</th>
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
                                        ? ($row->personil?->nama ?? '—') . "\n(" . ($row->personil?->tim?->nama_tim ?? '—') . ")"
                                        : '—';
                                @endphp
                                <tr class="{{ $isEven ? 'bg-zinc-50 dark:bg-zinc-800/40' : 'bg-white dark:bg-zinc-900/60' }} hover:bg-zinc-100 dark:hover:bg-zinc-800/70 transition-colors">
                                    <td class="border border-zinc-200 dark:border-zinc-700 px-3 py-2 text-zinc-900 dark:text-zinc-200">
                                        {{ $carbon->translatedFormat('d F Y') }}
                                    </td>
                                    <td class="border border-zinc-200 dark:border-zinc-700 px-3 py-2 text-center font-bold text-zinc-900 dark:text-zinc-100 uppercase">
                                        {{ strtoupper(substr($carbon->translatedFormat('l'), 0, 4)) }}
                                    </td>
                                    <td class="border border-zinc-200 dark:border-zinc-700 px-3 py-2 whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $fmtPersonil($dhuhrAdzan) }}</td>
                                    <td class="border border-zinc-200 dark:border-zinc-700 px-3 py-2 whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $fmtPersonil($dhuhrKajian) }}</td>
                                    <td class="border border-zinc-200 dark:border-zinc-700 px-3 py-2 whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $fmtPersonil($asrAdzan) }}</td>
                                    <td class="border border-zinc-200 dark:border-zinc-700 px-3 py-2 whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $fmtPersonil($asrKajian) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-4 py-8">
                    <x-empty-state icon="book-open" title="Tidak ada jadwal adzan/kajian" description="Belum ada data untuk bulan {{ $this->bulanLabel() }}." />
                </div>
            @endif

            {{-- Divider --}}
            <div class="border-t-8 border-zinc-100 dark:border-zinc-800"></div>

            {{-- Tabel Briefing --}}
            @if ($this->tabelBriefing->isNotEmpty())
                <div class="p-4 border-b border-zinc-100 dark:border-zinc-800 flex items-center justify-between">
                    <flux:heading size="sm">Jadwal Briefing</flux:heading>
                    <flux:badge size="sm" color="zinc">{{ $this->tabelBriefing->count() }} Jadwal</flux:badge>
                </div>
                <div>
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Tanggal</flux:table.column>
                            <flux:table.column align="center">Hari</flux:table.column>
                            <flux:table.column align="center">Sesi</flux:table.column>
                            <flux:table.column>Perwakilan</flux:table.column>
                            <flux:table.column>Tim</flux:table.column>
                            <flux:table.column align="center">Status</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->tabelBriefing as $j)
                                <flux:table.row>
                                    <flux:table.cell class="font-medium text-zinc-900 dark:text-zinc-200">{{ $j->tanggal->format('d/m/Y') }}</flux:table.cell>
                                    <flux:table.cell align="center" class="font-bold uppercase text-xs">{{ strtoupper(substr($j->tanggal->translatedFormat('l'), 0, 4)) }}</flux:table.cell>
                                    <flux:table.cell align="center">
                                        <flux:badge size="sm" color="{{ $j->sesi === 'pagi' ? 'amber' : 'indigo' }}">{{ ucfirst($j->sesi) }}</flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell class="text-zinc-900 dark:text-zinc-200">{{ $j->personil?->nama ?? '—' }}</flux:table.cell>
                                    <flux:table.cell class="text-zinc-500">{{ $j->tim?->nama_tim ?? '—' }}</flux:table.cell>
                                    <flux:table.cell align="center">
                                        <x-status-badge :status="$j->status_konfirmasi" />
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            @else
                <div class="px-4 py-8">
                    <x-empty-state icon="user-group" title="Tidak ada jadwal briefing" description="Belum ada data untuk bulan {{ $this->bulanLabel() }}." />
                </div>
            @endif
        @endif
    </flux:card>

    {{-- Tabel konfirmasi tertunda --}}
    @if ($this->daftarKonfirmasiTertunda->isNotEmpty())
        <flux:card class="p-0 overflow-hidden">
            <div class="px-4 py-3 border-b border-zinc-100 dark:border-zinc-800 flex items-center justify-between">
                <div>
                    <flux:heading size="sm">Konfirmasi Tertunda</flux:heading>
                    <flux:text class="text-xs text-zinc-400">10 terdekat</flux:text>
                </div>
                <flux:badge size="sm" color="amber">{{ $this->daftarKonfirmasiTertunda->count() }} Menunggu</flux:badge>
            </div>
            <div>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Personil</flux:table.column>
                        <flux:table.column>Tugas</flux:table.column>
                        <flux:table.column>Tim / Jenis</flux:table.column>
                        <flux:table.column>Tanggal</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->daftarKonfirmasiTertunda as $item)
                            <flux:table.row>
                                <flux:table.cell class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item['nama'] }}</flux:table.cell>
                                <flux:table.cell class="text-zinc-600 dark:text-zinc-400">{{ $item['jenis'] }}</flux:table.cell>
                                <flux:table.cell class="text-zinc-500">{{ $item['tipe'] }}</flux:table.cell>
                                <flux:table.cell class="text-zinc-500">
                                    {{ \Carbon\Carbon::parse($item['tanggal'])->translatedFormat('d M Y') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        </flux:card>
    @endif

    {{-- Modal: Calendar Event Detail --}}
    <div
        x-show="modalOpen"
        x-transition
        @keydown.escape.window="modalOpen = false"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40"
        style="display: none;"
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

    {{-- Modal: Generate Jadwal Confirmation --}}
    <flux:modal wire:model="modalGenerate" class="max-w-md">
        <div class="flex flex-col gap-4">
            <flux:heading size="lg">Generate Jadwal</flux:heading>
            
            @if ($this->periodeAktif)
                <div class="flex flex-col gap-2 text-sm">
                    <flux:text>Akan membuat jadwal untuk periode aktif:</flux:text>
                    <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-3 space-y-1">
                        <div class="flex justify-between">
                            <span class="text-zinc-500">Periode:</span>
                            <span class="font-medium">{{ $this->periodeAktif->nama_periode }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-zinc-500">Tanggal:</span>
                            <span class="font-medium">
                                {{ $this->periodeAktif->tanggal_mulai->translatedFormat('d M') }} – 
                                {{ $this->periodeAktif->tanggal_selesai->translatedFormat('d M Y') }}
                            </span>
                        </div>
                    </div>
                    <flux:text class="text-xs text-zinc-500">
                        Sistem akan generate jadwal Adzan, Briefing, dan Alokasi Ruangan untuk semua hari kerja (Senin–Sabtu).
                    </flux:text>
                </div>
            @endif

            <div class="flex justify-end gap-2 pt-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="generateJadwal">
                    Lanjut Generate
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
