<?php

use App\Models\Tim;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Kalender')] #[Layout('layouts.admin')] class extends Component {

    public string $filterJenis = '';
    public string $filterTimId = '';

    /** Dispatch event ke Alpine setiap kali filter berubah */
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
                // View Hari: mulai 09:00, selesai 17:00, hapus all-day row
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
    x-init="initCalendar()"
    {{-- Listen ke event Livewire untuk update filter Alpine lalu refetch --}}
    @filter-changed.window="filterJenis = $event.detail.jenis; filterTimId = String($event.detail.timId ?? ''); refetchEvents()"
    {{-- Resize kalender setelah transisi sidebar (200ms) selesai --}}
    @sidebar-toggled.window="setTimeout(() => { if (calendar) calendar.updateSize() }, 220)"
    class="flex flex-col gap-6"
>
    {{-- Header + Filter --}}
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Kalender Jadwal</flux:heading>
            <flux:text class="text-zinc-500">Semua jadwal adzan/kajian, briefing, dan alokasi ruangan.</flux:text>
        </div>

        <div class="flex gap-2 flex-wrap">
            <flux:select wire:model.live="filterJenis" class="w-40">
                <flux:select.option value="">Semua Jenis</flux:select.option>
                <flux:select.option value="adzan">Adzan/Kajian</flux:select.option>
                <flux:select.option value="briefing">Briefing</flux:select.option>
                <flux:select.option value="ruangan">Alokasi Ruangan</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="filterTimId" class="w-48">
                <flux:select.option value="">Semua Tim</flux:select.option>
                @foreach ($this->timList as $tim)
                    <flux:select.option :value="$tim->id">{{ $tim->nama_tim }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    {{-- Legend --}}
    <div class="flex gap-4 flex-wrap text-xs">
        <span class="flex items-center gap-1.5">
            <span class="h-3 w-3 rounded-sm bg-[#1976D2]"></span> Adzan & Kajian
        </span>
        <span class="flex items-center gap-1.5">
            <span class="h-3 w-3 rounded-sm bg-[#7C3AED]"></span> Briefing
        </span>
        <span class="flex items-center gap-1.5">
            <span class="h-3 w-3 rounded-sm bg-[#059669]"></span> Alokasi Ruangan
        </span>
    </div>

    {{-- FullCalendar --}}
    <flux:card class="p-4 overflow-hidden">
        {{-- Override FullCalendar supaya cocok dengan dark theme zinc --}}
        <style>
            /* Header hari (Sen, Sel, ...) */
            .fc .fc-col-header-cell-cushion {
                color: #a1a1aa !important; /* zinc-400 */
                font-size: 0.75rem;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                text-decoration: none !important;
            }
            /* Nomor tanggal */
            .fc .fc-daygrid-day-number {
                color: #71717a; /* zinc-500 */
                text-decoration: none !important;
                font-size: 0.75rem;
            }
            /* Grid borders */
            .fc-theme-standard td,
            .fc-theme-standard th,
            .fc-theme-standard .fc-scrollgrid {
                border-color: #3f3f46 !important; /* zinc-700 */
            }
            /* Background header row */
            .fc .fc-col-header-cell {
                background-color: #18181b !important; /* zinc-900 */
                border-color: #3f3f46 !important;
            }
            /* Background hari kosong */
            .fc .fc-daygrid-day {
                background-color: #09090b; /* zinc-950 */
            }
            /* Kolom Minggu (day-sun) — pastikan tidak putih */
            .fc .fc-day-sun {
                background-color: #09090b !important;
            }
            /* Hari ini highlight */
            .fc .fc-daygrid-day.fc-day-today {
                background-color: rgba(25, 118, 210, 0.10) !important;
            }
            .fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
                color: #60a5fa !important; /* blue-400 */
                font-weight: 600;
            }
            /* Hari Minggu / luar bulan */
            .fc .fc-day-other .fc-daygrid-day-number {
                color: #52525b !important; /* zinc-600 */
            }
            /* Toolbar: tombol prev/next/today */
            .fc .fc-button,
            .fc .fc-button-primary {
                background-color: #27272a !important; /* zinc-800 */
                border-color: #3f3f46 !important; /* zinc-700 */
                color: #d4d4d8 !important; /* zinc-300 */
                font-size: 0.75rem;
                padding: 0.3rem 0.6rem;
                box-shadow: none !important;
            }
            .fc .fc-button:hover,
            .fc .fc-button-primary:hover {
                background-color: #3f3f46 !important;
                border-color: #52525b !important;
                color: #fff !important;
            }
            .fc .fc-button-active,
            .fc .fc-button-primary:not(:disabled).fc-button-active {
                background-color: #f4f4f5 !important; /* zinc-100 */
                border-color: #e4e4e7 !important;     /* zinc-200 */
                color: #18181b !important;             /* zinc-900 */
                font-weight: 600;
            }
            /* Judul bulan */
            .fc .fc-toolbar-title {
                color: #f4f4f5 !important; /* zinc-100 */
                font-size: 1.1rem !important;
                font-weight: 600;
            }
            /* List view header */
            .fc .fc-list-day-cushion,
            .fc .fc-list-day-text,
            .fc .fc-list-day-side-text {
                color: #d4d4d8 !important;
                background-color: #18181b !important;
            }
            .fc .fc-list-event:hover td {
                background-color: #27272a !important;
            }
            .fc .fc-list-event-title a,
            .fc .fc-list-event-time {
                color: #f4f4f5 !important;
            }
            /* "+N lebih" link */
            .fc .fc-daygrid-more-link {
                color: #60a5fa !important;
                font-size: 0.7rem;
            }
            /* Agenda/List: strip zebra */
            .fc-theme-standard .fc-list {
                border-color: #3f3f46 !important;
            }
            .fc .fc-list-empty {
                background-color: #09090b;
                color: #71717a;
            }
        </style>
        <div wire:ignore>
            <div x-ref="kalender"></div>
        </div>
    </flux:card>

    {{-- Modal detail event --}}
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
