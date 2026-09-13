<?php

use App\Models\NotulenBriefing;
use App\Models\Tim;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Notulen Briefing')] #[Layout('layouts.admin')] class extends Component {
    // Modal state
    public bool $modalCatatanOpen = false;
    public ?NotulenBriefing $selectedNotulen = null;

    public function mount(): void
    {
        // Mark all unviewed notulen as viewed when admin opens this page
        NotulenBriefing::unviewed()->get()->each(function ($notulen) {
            $notulen->markAsViewedByAdmin();
        });
    }
    
    public function lihatCatatan(int $notulenId): void
    {
        $this->selectedNotulen = NotulenBriefing::withoutGlobalScope('role_based_visibility')
            ->with(['user', 'tim', 'timYangTerlibat'])
            ->findOrFail($notulenId);
        $this->modalCatatanOpen = true;
    }

    #[Computed]
    public function semuaNotulen()
    {
        return NotulenBriefing::withoutGlobalScope('role_based_visibility')
            ->with(['user', 'tim', 'timYangTerlibat'])
            ->orderByDesc('tanggal')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($n) {
                // Get proper notulen name: penulis_nama (if exists), otherwise user name, otherwise nama_notulen field
                $namaNotulen = $n->penulis_nama ?? $n->user?->name ?? $n->nama_notulen;
                
                return [
                    'id' => $n->id,
                    'tanggal' => $n->tanggal->format('Y-m-d'),
                    'tanggal_display' => $n->tanggal->format('d/m/Y'),
                    'hari' => $n->tanggal->locale('id')->translatedFormat('l'),
                    'sesi' => $n->sesi,
                    'nama_notulen' => $namaNotulen,
                    'catatan' => $n->catatan ?? '',
                    'tim_penulis' => $n->tim?->nama_tim ?? '—',
                    'tim_terlibat' => $n->timYangTerlibat->pluck('nama_tim')->join(', '),
                    'tim_ids' => $n->timYangTerlibat->pluck('id')->toArray(),
                    'has_file' => $n->hasFile(),
                    'file_name' => $n->file_name,
                    'created_at' => $n->created_at->format('H:i'),
                    'bulan' => $n->tanggal->format('Y-m'),
                ];
            })
            ->toArray();
    }

    #[Computed]
    public function timList()
    {
        return Tim::orderBy('nama_tim')->get(['id', 'nama_tim']);
    }

    public function downloadFile(int $notulenId): mixed
    {
        $notulen = NotulenBriefing::withoutGlobalScope('role_based_visibility')->findOrFail($notulenId);

        if (! $notulen->hasFile()) {
            Flux::toast(variant: 'warning', text: 'File tidak ditemukan.');

            return null;
        }

        return response()->download(storage_path('app/public/'.$notulen->file_path), $notulen->file_name);
    }
}; ?>

<div
    x-data="{
        rows: @js($this->semuaNotulen),
        q: '',
        filterBulan: '',
        filterSesi: '',
        filterTim: '',
        sortField: 'tanggal',
        sortDir: 'desc',
        
        toggleSort(field) {
            if (this.sortField === field) {
                this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortField = field;
                this.sortDir = 'asc';
            }
        },
        
        get filtered() {
            let data = [...this.rows];
            
            // Search filter
            if (this.q) {
                data = data.filter(r => 
                    r.nama_notulen.toLowerCase().includes(this.q.toLowerCase()) ||
                    r.catatan.toLowerCase().includes(this.q.toLowerCase())
                );
            }
            
            // Bulan filter
            if (this.filterBulan) {
                data = data.filter(r => r.bulan === this.filterBulan);
            }
            
            // Sesi filter
            if (this.filterSesi) {
                data = data.filter(r => r.sesi === this.filterSesi);
            }
            
            // Tim filter
            if (this.filterTim) {
                data = data.filter(r => r.tim_ids.includes(parseInt(this.filterTim)));
            }
            
            return data;
        },
        
        get totalNotulen() {
            return this.rows.length;
        },
        
        get bulanIni() {
            const now = new Date();
            const bulan = String(now.getMonth() + 1).padStart(2, '0');
            const tahun = now.getFullYear();
            const bulanIni = `${tahun}-${bulan}`;
            return this.rows.filter(r => r.bulan === bulanIni).length;
        },
        
        get denganFile() {
            return this.rows.filter(r => r.has_file).length;
        }
    }"
    class="space-y-6"
>
    {{-- Header --}}
    <div class="mb-6">
        <flux:heading size="xl">Notulen Briefing</flux:heading>
        <flux:text class="text-zinc-500">Semua notulen briefing dari seluruh tim</flux:text>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <flux:card variant="soft" class="relative overflow-hidden p-5 border border-zinc-200 dark:border-zinc-700">
            <div class="relative z-10">
                <flux:text class="font-medium text-xs text-zinc-500 dark:text-zinc-400">Total Notulen</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold text-blue-600 dark:text-blue-400" x-text="totalNotulen"></flux:heading>
            </div>
            <flux:icon icon="clipboard-document-list" class="absolute -bottom-3 -right-3 size-20 text-blue-500/10 pointer-events-none" />
        </flux:card>

        <flux:card variant="soft" class="relative overflow-hidden p-5 border border-zinc-200 dark:border-zinc-700">
            <div class="relative z-10">
                <flux:text class="font-medium text-xs text-zinc-500 dark:text-zinc-400">Bulan Ini</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold text-emerald-600 dark:text-emerald-400" x-text="bulanIni"></flux:heading>
            </div>
            <flux:icon icon="calendar" class="absolute -bottom-3 -right-3 size-20 text-emerald-500/10 pointer-events-none" />
        </flux:card>

        <flux:card variant="soft" class="relative overflow-hidden p-5 border border-zinc-200 dark:border-zinc-700">
            <div class="relative z-10">
                <flux:text class="font-medium text-xs text-zinc-500 dark:text-zinc-400">Dengan File</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold text-purple-600 dark:text-purple-400" x-text="denganFile"></flux:heading>
            </div>
            <flux:icon icon="document" class="absolute -bottom-3 -right-3 size-20 text-purple-500/10 pointer-events-none" />
        </flux:card>
    </div>

    {{-- Filters --}}
    <flux:card class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            {{-- Search --}}
            <flux:input
                x-model="q"
                placeholder="Cari nama notulen atau catatan..."
                icon="magnifying-glass"
            />

            {{-- Filter Bulan dengan Flatpickr --}}
            <div class="relative">
                <input
                    type="text"
                    x-init="
                        flatpickr($el, {
                            plugins: [new monthSelectPlugin({ shorthand: false, dateFormat: 'Y-m', altFormat: 'F Y' })],
                            altInput: true,
                            altFormat: 'F Y',
                            dateFormat: 'Y-m',
                            defaultDate: null,
                            onChange: function(selectedDates, dateStr) {
                                filterBulan = dateStr;
                            }
                        });
                    "
                    placeholder="Pilih Bulan"
                    class="w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent"
                />
            </div>

            {{-- Filter Sesi --}}
            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button type="button" @click="open = !open"
                    :class="open ? 'ring-2 ring-brand border-brand' : 'border-zinc-300 dark:border-zinc-600 hover:border-zinc-400'"
                    class="w-full flex items-center justify-between gap-2 rounded-lg border bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-left transition-colors">
                    <span :class="filterSesi ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-400'" 
                        x-text="filterSesi === '' ? 'Semua Sesi' : (filterSesi === 'pagi' ? 'Pagi' : 'Sore')"></span>
                    <svg class="h-4 w-4 text-zinc-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-transition class="absolute z-50 mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg py-1">
                    <template x-for="opt in [{value:'',label:'Semua Sesi'},{value:'pagi',label:'Pagi'},{value:'sore',label:'Sore'}]" :key="opt.value">
                        <button type="button" @click="filterSesi = opt.value; open = false"
                            :class="filterSesi === opt.value ? 'bg-brand/10 text-brand font-medium' : 'text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700'"
                            class="w-full text-left px-3 py-2 text-sm flex items-center justify-between">
                            <span x-text="opt.label"></span>
                            <svg x-show="filterSesi === opt.value" class="h-4 w-4 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                    </template>
                </div>
            </div>

            {{-- Filter Tim dengan Search --}}
            <div class="relative" x-data="{ open: false, qTim: '' }" @click.outside="open = false; qTim = ''">
                <button type="button" @click="open = !open"
                    :class="open ? 'ring-2 ring-brand border-brand' : 'border-zinc-300 dark:border-zinc-600 hover:border-zinc-400'"
                    class="w-full flex items-center justify-between gap-2 rounded-lg border bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-left transition-colors">
                    <span :class="filterTim ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-400'"
                        x-text="filterTim === '' ? 'Semua Tim' : (@js($this->timList->pluck('nama_tim', 'id')->toArray())[filterTim] || 'Semua Tim')"></span>
                    <svg class="h-4 w-4 text-zinc-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-transition class="absolute z-50 mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg">
                    <div class="p-2 border-b border-zinc-100 dark:border-zinc-700">
                        <input x-model="qTim" type="text" placeholder="Cari tim..." @click.stop
                            class="w-full rounded-md border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 px-3 py-1.5 text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-1 focus:ring-brand"/>
                    </div>
                    <div class="max-h-48 overflow-y-auto py-1">
                        <button type="button" @click="filterTim = ''; open = false"
                            :class="filterTim === '' ? 'bg-brand/10 text-brand font-medium' : 'text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700'"
                            class="w-full text-left px-3 py-2 text-sm flex items-center justify-between">
                            <span>Semua Tim</span>
                            <svg x-show="filterTim === ''" class="h-4 w-4 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                        <template x-for="tim in @js($this->timList->toArray()).filter(t => !qTim || t.nama_tim.toLowerCase().includes(qTim.toLowerCase()))" :key="tim.id">
                            <button type="button" @click="filterTim = String(tim.id); open = false"
                                :class="filterTim == tim.id ? 'bg-brand/10 text-brand font-medium' : 'text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700'"
                                class="w-full text-left px-3 py-2 text-sm flex items-center justify-between">
                                <span x-text="tim.nama_tim" class="truncate"></span>
                                <svg x-show="filterTim == tim.id" class="h-4 w-4 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            </button>
                        </template>
                        <div x-show="@js($this->timList->toArray()).filter(t => !qTim || t.nama_tim.toLowerCase().includes(qTim.toLowerCase())).length === 0" 
                            class="px-3 py-2 text-sm text-zinc-400 text-center">
                            Tidak ada tim ditemukan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </flux:card>

    {{-- Table --}}
    <flux:card class="p-0 overflow-visible table-sticky-card border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs">
        <div class="hidden sm:block px-5">
            <flux:table>
                <flux:table.columns class="sticky top-0 z-10 bg-white/95 dark:bg-zinc-800/95 backdrop-blur-md border-b border-zinc-200 dark:border-zinc-700">
                    <flux:table.column @click="toggleSort('tanggal')" class="cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 select-none">
                        <span class="inline-flex items-center gap-1">Tanggal
                            <svg x-show="sortField === 'tanggal' && sortDir === 'asc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                            <svg x-show="sortField === 'tanggal' && sortDir === 'desc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            <svg x-show="sortField !== 'tanggal'" class="h-3.5 w-3.5 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
                        </span>
                    </flux:table.column>
                    <flux:table.column @click="toggleSort('sesi')" class="cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 select-none">
                        <span class="inline-flex items-center gap-1">Sesi
                            <svg x-show="sortField === 'sesi' && sortDir === 'asc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                            <svg x-show="sortField === 'sesi' && sortDir === 'desc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            <svg x-show="sortField !== 'sesi'" class="h-3.5 w-3.5 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
                        </span>
                    </flux:table.column>
                    <flux:table.column @click="toggleSort('tim_terlibat')" class="cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 select-none">
                        <span class="inline-flex items-center gap-1">Tim Penulis
                            <svg x-show="sortField === 'tim_terlibat' && sortDir === 'asc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                            <svg x-show="sortField === 'tim_terlibat' && sortDir === 'desc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            <svg x-show="sortField !== 'tim_terlibat'" class="h-3.5 w-3.5 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
                        </span>
                    </flux:table.column>
                    <flux:table.column @click="toggleSort('nama_notulen')" class="cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 select-none">
                        <span class="inline-flex items-center gap-1">Nama Notulen
                            <svg x-show="sortField === 'nama_notulen' && sortDir === 'asc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                            <svg x-show="sortField === 'nama_notulen' && sortDir === 'desc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            <svg x-show="sortField !== 'nama_notulen'" class="h-3.5 w-3.5 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
                        </span>
                    </flux:table.column>
                    <flux:table.column>File</flux:table.column>
                    <flux:table.column align="center">Aksi</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    <template x-if="filtered.length === 0">
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center text-zinc-400 text-sm py-8">
                                <div class="flex flex-col items-center gap-3">
                                    <flux:icon icon="clipboard-document-list" class="size-12 text-zinc-300 dark:text-zinc-600" />
                                    <div>
                                        <div class="font-medium text-zinc-400 dark:text-zinc-500">Tidak ada notulen</div>
                                        <div class="text-xs text-zinc-400 dark:text-zinc-500 mt-1" x-text="q || filterBulan || filterSesi || filterTim ? 'Coba ubah filter pencarian' : 'Belum ada notulen briefing yang dibuat'"></div>
                                    </div>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    </template>

                    @foreach ($this->semuaNotulen as $n)
                        <flux:table.row
                            x-data="{ n: @js($n) }"
                            x-show="(() => {
                                let match = true;
                                
                                // Search filter
                                if (q) {
                                    match = match && (
                                        n.nama_notulen.toLowerCase().includes(q.toLowerCase()) ||
                                        n.catatan.toLowerCase().includes(q.toLowerCase())
                                    );
                                }
                                
                                // Bulan filter
                                if (filterBulan) {
                                    match = match && n.bulan === filterBulan;
                                }
                                
                                // Sesi filter
                                if (filterSesi) {
                                    match = match && n.sesi === filterSesi;
                                }
                                
                                // Tim filter
                                if (filterTim) {
                                    match = match && n.tim_ids.includes(parseInt(filterTim));
                                }
                                
                                return match;
                            })()"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 transform scale-95"
                            x-transition:enter-end="opacity-100 transform scale-100"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 transform scale-100"
                            x-transition:leave-end="opacity-0 transform scale-95"
                        >
                            <flux:table.cell>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $n['tanggal_display'] }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $n['hari'] }}</div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge :color="$n['sesi'] === 'pagi' ? 'amber' : 'indigo'" size="sm">
                                    {{ ucfirst($n['sesi']) }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="text-zinc-900 dark:text-zinc-100">{{ $n['tim_terlibat'] ?: '—' }}</div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $n['nama_notulen'] }}</div>
                            </flux:table.cell>

                            <flux:table.cell>
                                @if($n['has_file'])
                                    <div class="flex items-center gap-1.5 text-blue-600 dark:text-blue-400">
                                        <flux:icon icon="document" variant="micro" class="size-4" />
                                        <span class="text-xs font-medium">Ada file</span>
                                    </div>
                                @else
                                    <span class="text-xs text-zinc-400">—</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="center">
                                <flux:button 
                                    size="sm" 
                                    variant="ghost" 
                                    icon="eye"
                                    wire:click="lihatCatatan({{ $n['id'] }})"
                                    title="Lihat Detail"
                                />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    </flux:card>

    {{-- Modal: Lihat Catatan --}}
    <flux:modal wire:model="modalCatatanOpen" class="max-w-2xl">
        @if ($selectedNotulen)
            <div class="space-y-4">
                <flux:heading size="lg">Detail Notulen</flux:heading>
                
                {{-- Info Header --}}
                <div class="space-y-3 p-4 bg-zinc-50 dark:bg-zinc-800 rounded-lg">
                    <div>
                        <flux:text class="text-xs text-zinc-500 mb-1">Tanggal</flux:text>
                        <flux:text class="font-medium">{{ $selectedNotulen->tanggal->translatedFormat('l, d F Y') }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-xs text-zinc-500 mb-1">Sesi</flux:text>
                        <flux:badge :color="$selectedNotulen->sesi === 'pagi' ? 'amber' : 'indigo'">
                            {{ ucfirst($selectedNotulen->sesi) }}
                        </flux:badge>
                    </div>
                    <div>
                        <flux:text class="text-xs text-zinc-500 mb-1">Waktu Submit</flux:text>
                        <div class="flex items-center gap-1.5 text-sm">
                            <flux:icon icon="clock" variant="micro" class="size-4 text-zinc-400" />
                            <flux:text class="font-medium">{{ $selectedNotulen->created_at->format('H:i') }} WIB</flux:text>
                            <span class="text-xs text-zinc-400">({{ $selectedNotulen->created_at->translatedFormat('d M Y') }})</span>
                        </div>
                    </div>
                    <div>
                        <flux:text class="text-xs text-zinc-500 mb-1">Nama Notulen</flux:text>
                        <flux:text class="font-medium">{{ $selectedNotulen->penulis_nama ?? $selectedNotulen->nama_notulen }}</flux:text>
                    </div>
                </div>

                {{-- Tim Penulis --}}
                <div>
                    <flux:text class="text-xs text-zinc-500 mb-2">Tim Penulis</flux:text>
                    <flux:text class="font-medium">
                        {{ $selectedNotulen->timYangTerlibat->pluck('nama_tim')->join(', ') ?: '—' }}
                    </flux:text>
                </div>

                {{-- Catatan --}}
                <div>
                    <flux:text class="text-xs text-zinc-500 mb-2">Catatan</flux:text>
                    <div class="p-4 bg-zinc-50 dark:bg-zinc-800 rounded-lg max-h-[400px] overflow-y-auto">
                        <div class="prose prose-sm dark:prose-invert max-w-none whitespace-pre-wrap">{{ $selectedNotulen->catatan }}</div>
                    </div>
                </div>

                {{-- File (if exists) --}}
                @if ($selectedNotulen->hasFile())
                    <div>
                        <flux:text class="text-xs text-zinc-500 mb-2">File Lampiran</flux:text>
                        <div class="flex items-center gap-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                            <flux:icon icon="document" class="size-8 text-blue-500" />
                            <div class="flex-1 min-w-0">
                                <flux:text class="font-medium text-sm truncate">{{ $selectedNotulen->file_name }}</flux:text>
                                <flux:text class="text-xs text-zinc-500">Lampiran notulen</flux:text>
                            </div>
                            <flux:button 
                                size="sm" 
                                icon="arrow-down-tray"
                                wire:click="downloadFile({{ $selectedNotulen->id }})"
                            >
                                Download
                            </flux:button>
                        </div>
                    </div>
                @endif

                {{-- Actions --}}
                <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button variant="ghost" wire:click="$set('modalCatatanOpen', false)">
                        Tutup
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>

@push('scripts')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>
@endpush
