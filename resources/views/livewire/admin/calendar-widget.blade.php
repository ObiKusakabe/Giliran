<?php

use Livewire\Attributes\Lazy;
use Livewire\Component;

new #[Lazy] class extends Component {
    public string $filterJenis = '';
    public string $filterTimId = '';
    
    public function mount(string $filterJenis = '', string $filterTimId = ''): void
    {
        $this->filterJenis = $filterJenis;
        $this->filterTimId = $filterTimId;
    }
    
    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="p-4">
            <flux:skeleton.group animate="shimmer" class="space-y-4">
                <!-- Skeleton header toolbar -->
                <div class="flex justify-between items-center gap-4">
                    <div class="flex gap-2">
                        <flux:skeleton class="h-8 w-16 rounded-md" />
                        <flux:skeleton class="h-8 w-16 rounded-md" />
                        <flux:skeleton class="h-8 w-20 rounded-md" />
                    </div>
                    <flux:skeleton class="h-8 w-32 rounded-md" />
                    <div class="flex gap-2">
                        <flux:skeleton class="h-8 w-24 rounded-md" />
                        <flux:skeleton class="h-8 w-24 rounded-md" />
                    </div>
                </div>
                
                <!-- Skeleton calendar grid with Flux -->
                <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                    <!-- Week header -->
                    <div class="grid grid-cols-7 bg-zinc-50 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700">
                        <div class="h-10 flex items-center justify-center text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Sen</div>
                        <div class="h-10 flex items-center justify-center text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Sel</div>
                        <div class="h-10 flex items-center justify-center text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Rab</div>
                        <div class="h-10 flex items-center justify-center text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Kam</div>
                        <div class="h-10 flex items-center justify-center text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Jum</div>
                        <div class="h-10 flex items-center justify-center text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Sab</div>
                        <div class="h-10 flex items-center justify-center text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Min</div>
                    </div>
                    
                    <!-- Calendar days skeleton -->
                    <div class="grid grid-cols-7">
                        @for ($i = 0; $i < 35; $i++)
                            <div class="min-h-[120px] border-r border-b border-zinc-200 dark:border-zinc-700 p-3 space-y-3">
                                <flux:skeleton class="h-4 w-8 rounded" />
                                <div class="space-y-2">
                                    <flux:skeleton.line />
                                    @if ($i % 3 === 0)
                                        <flux:skeleton.line style="width: 80%" />
                                    @endif
                                    @if ($i % 5 === 0)
                                        <flux:skeleton.line style="width: 60%" />
                                    @endif
                                </div>
                            </div>
                        @endfor
                    </div>
                </div>
                
                <!-- Loading text with icon -->
                <div class="text-center py-4">
                    <div class="flex items-center justify-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                        <flux:icon icon="calendar" class="size-5 animate-spin" />
                        <span>Memuat kalender...</span>
                    </div>
                </div>
            </flux:skeleton.group>
        </div>
        HTML;
    }
}; ?>

<div
    x-data="{
        calendar: null,
        modalOpen: false,
        selectedEvent: null,
        filterJenis: @entangle('filterJenis'),
        filterTimId: @entangle('filterTimId'),
        currentView: 'dayGridMonth',
        activeDateLabel: '',
        isSaturday: false,

        initCalendar() {
            this.calendar = new FullCalendar.Calendar(this.\$refs.kalender, {
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
                    const dayOfWeek = dateObj.getDay();
                    this.isSaturday = (dayOfWeek === 6);

                    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                    this.activeDateLabel = dateObj.toLocaleDateString('id-ID', options);

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
    x-init="\$nextTick(() => initCalendar())"
    @filter-changed.window="filterJenis = \$event.detail.jenis; filterTimId = String(\$event.detail.timId ?? ''); refetchEvents()"
    @sidebar-toggled.window="setTimeout(() => { if (calendar) calendar.updateSize() }, 220)"
>
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

        <div wire:ignore>
            <div x-ref="kalender"></div>
        </div>
    </div>

    {{-- Event Detail Modal --}}
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
                                <p class="text-sm" x-text="selectedEvent?.waktu_sholat ?? '-'"></p>
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
                        
                        <div x-show="selectedEvent?.keterangan">
                            <p class="text-xs text-zinc-500 font-medium mb-1">Keterangan</p>
                            <p class="text-sm" x-text="selectedEvent?.keterangan ?? '-'"></p>
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
