<?php

use App\Models\NotulenBriefing;
use App\Models\Tim;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Notulen Briefing')] #[Layout('layouts.admin')] class extends Component {
    public string $filterBulan = '';
    public string $filterSesi = '';
    public string $filterTim = '';
    public string $search = '';
    
    // Modal state
    public bool $modalCatatanOpen = false;
    public ?NotulenBriefing $selectedNotulen = null;

    public function mount(): void
    {
        $this->filterBulan = \App\Helpers\DevModeHelper::now()->format('Y-m');
        
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
    public function notulenList()
    {
        $query = NotulenBriefing::withoutGlobalScope('role_based_visibility')
            ->with(['user', 'tim', 'timYangTerlibat'])
            ->orderByDesc('tanggal')
            ->orderByDesc('created_at');

        // Filter by bulan
        if ($this->filterBulan) {
            $query->whereYear('tanggal', substr($this->filterBulan, 0, 4))
                ->whereMonth('tanggal', substr($this->filterBulan, 5, 2));
        }

        // Filter by sesi
        if ($this->filterSesi) {
            $query->where('sesi', $this->filterSesi);
        }

        // Filter by tim (check pivot)
        if ($this->filterTim) {
            $query->whereHas('timYangTerlibat', fn ($q) => $q->where('tim_id', $this->filterTim));
        }

        // Search by nama_notulen or catatan
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('nama_notulen', 'like', "%{$this->search}%")
                    ->orWhere('catatan', 'like', "%{$this->search}%");
            });
        }

        return $query->get();
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

<div>
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
                <flux:heading size="xl" class="mt-2 font-bold text-blue-600 dark:text-blue-400">
                    {{ $this->notulenList->count() }}
                </flux:heading>
            </div>
            <flux:icon icon="clipboard-document-list" class="absolute -bottom-3 -right-3 size-20 text-blue-500/10 pointer-events-none" />
        </flux:card>

        <flux:card variant="soft" class="relative overflow-hidden p-5 border border-zinc-200 dark:border-zinc-700">
            <div class="relative z-10">
                <flux:text class="font-medium text-xs text-zinc-500 dark:text-zinc-400">Bulan Ini</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold text-emerald-600 dark:text-emerald-400">
                    @php
                        $currentMonth = \App\Helpers\DevModeHelper::now()->month;
                        $currentYear = \App\Helpers\DevModeHelper::now()->year;
                    @endphp
                    {{ $this->notulenList->filter(fn($n) => $n->tanggal->month === $currentMonth && $n->tanggal->year === $currentYear)->count() }}
                </flux:heading>
            </div>
            <flux:icon icon="calendar" class="absolute -bottom-3 -right-3 size-20 text-emerald-500/10 pointer-events-none" />
        </flux:card>

        <flux:card variant="soft" class="relative overflow-hidden p-5 border border-zinc-200 dark:border-zinc-700">
            <div class="relative z-10">
                <flux:text class="font-medium text-xs text-zinc-500 dark:text-zinc-400">Dengan File</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold text-purple-600 dark:text-purple-400">
                    {{ $this->notulenList->filter(fn($n) => $n->hasFile())->count() }}
                </flux:heading>
            </div>
            <flux:icon icon="document" class="absolute -bottom-3 -right-3 size-20 text-purple-500/10 pointer-events-none" />
        </flux:card>
    </div>

    {{-- Filters --}}
    <flux:card class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            {{-- Search --}}
            <flux:input
                wire:model.live="search"
                placeholder="Cari nama notulen atau catatan..."
                icon="magnifying-glass"
            />

            {{-- Filter Bulan --}}
            <x-month-picker 
                wire:model.live="filterBulan" 
                placeholder="April 2026"
            />

            {{-- Filter Sesi --}}
            <flux:select wire:model.live="filterSesi">
                <flux:select.option value="">Semua Sesi</flux:select.option>
                <flux:select.option value="pagi">Pagi</flux:select.option>
                <flux:select.option value="sore">Sore</flux:select.option>
            </flux:select>

            {{-- Filter Tim --}}
            <x-searchable-select
                wire:model.live="filterTim"
                name="filterTim"
                placeholder="Semua Tim"
                :options="$this->timList->map(fn($t) => ['value' => (string)$t->id, 'label' => $t->nama_tim])->prepend(['value' => '', 'label' => 'Semua Tim'])->toArray()"
                :modelValue="$filterTim"
            />
        </div>
    </flux:card>

    {{-- Table --}}
    <flux:card class="p-0 overflow-visible table-sticky-card border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs">
        @if ($this->notulenList->count() > 0)
            <div class="px-5">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Tanggal</flux:table.column>
                        <flux:table.column>Sesi</flux:table.column>
                        <flux:table.column>Tim Penulis</flux:table.column>
                        <flux:table.column>Nama Notulen</flux:table.column>
                        <flux:table.column>File</flux:table.column>
                        <flux:table.column align="center">Aksi</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->notulenList as $n)
                            <flux:table.row>
                                <flux:table.cell class="font-medium">
                                    {{ $n->tanggal->format('d/m/Y') }}
                                    <div class="text-xs text-zinc-500">{{ $n->tanggal->locale('id')->translatedFormat('l') }}</div>
                                    <div class="flex items-center gap-1 text-xs text-zinc-400 mt-0.5">
                                        <flux:icon icon="clock" variant="micro" class="size-3" />
                                        <span>Dibuat: {{ $n->created_at->format('H:i') }} WIB</span>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <flux:badge :color="$n->sesi === 'pagi' ? 'amber' : 'indigo'" size="sm">
                                        {{ ucfirst($n->sesi) }}
                                    </flux:badge>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($n->timYangTerlibat as $tim)
                                            <flux:badge size="sm" color="zinc" variant="outline">
                                                {{ $tim->nama_tim }}
                                                @if ($tim->pivot->is_creator)
                                                    <flux:icon icon="pencil" class="size-3 ml-1" />
                                                @endif
                                            </flux:badge>
                                        @endforeach
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>{{ $n->penulis_nama ?? $n->nama_notulen }}</flux:table.cell>

                                <flux:table.cell>
                                    @if ($n->hasFile())
                                        <div class="flex items-center gap-2">
                                            <flux:icon icon="document" class="size-4 text-blue-500" />
                                            <span class="text-xs truncate max-w-[150px]">{{ $n->file_name }}</span>
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
                                        wire:click="lihatCatatan({{ $n->id }})"
                                        title="Lihat Catatan"
                                    />
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @else
            <div class="text-center py-12">
                <flux:icon icon="document-text" class="size-12 text-zinc-300 mx-auto mb-3" />
                <flux:heading size="lg" class="mb-2">Tidak ada notulen</flux:heading>
                <flux:text class="text-zinc-500">
                    @if ($search || $filterSesi || $filterTim)
                        Tidak ada notulen untuk filter yang dipilih.
                    @else
                        Belum ada notulen briefing yang dibuat.
                    @endif
                </flux:text>
            </div>
        @endif
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
                    <div class="flex flex-wrap gap-2">
                        @foreach ($selectedNotulen->timYangTerlibat as $tim)
                            <flux:badge size="sm" color="zinc">
                                {{ $tim->nama_tim }}
                                @if ($tim->pivot->is_creator)
                                    <flux:icon icon="pencil" class="size-3 ml-1" />
                                @endif
                            </flux:badge>
                        @endforeach
                    </div>
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
