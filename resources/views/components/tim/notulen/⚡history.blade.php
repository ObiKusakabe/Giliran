<?php

use App\Models\NotulenBriefing;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Riwayat Notulen Briefing')] #[Layout('layouts.app')] class extends Component {
    public string $filter_bulan = '';
    public string $filter_sesi = '';
    public string $filter_jenis = ''; // FASE 4.2 - Task #8: Filter WFO/WFH
    public ?int $detailId = null;

    public function mount(): void
    {
        // Default filter to current month
        $this->filter_bulan = now()->format('Y-m');
    }

    #[Computed]
    public function notulenList(): array
    {
        if (! Auth::user()->tim_id) {
            return [];
        }

        // Get ALL notulen (visible to all tim)
        $query = NotulenBriefing::with(['user', 'timYangTerlibat'])
            ->latest();

        // Filter by month
        if ($this->filter_bulan) {
            $query->whereYear('tanggal', substr($this->filter_bulan, 0, 4))
                ->whereMonth('tanggal', substr($this->filter_bulan, 5, 2));
        }

        // Filter by sesi
        if ($this->filter_sesi) {
            $query->where('sesi', $this->filter_sesi);
        }

        // FASE 4.2 - Task #8: Filter by jenis kehadiran
        if ($this->filter_jenis) {
            $query->where('jenis_kehadiran', $this->filter_jenis);
        }

        return $query->get()
            ->map(function ($n) {
                // Get penulis from JadwalBriefing (from creator tim)
                $creatorTim = $n->timYangTerlibat->where('pivot.is_creator', true)->first();
                $jadwal = null;
                
                if ($creatorTim) {
                    $jadwal = \App\Models\JadwalBriefing::withoutGlobalScope('tim_isolation')
                        ->where('tim_id', $creatorTim->id)
                        ->where('tanggal', $n->tanggal)
                        ->where('sesi', $n->sesi)
                        ->where('is_notulen', true)
                        ->with('personil')
                        ->first();
                }
                
                $penulisNama = $jadwal?->personil?->nama ?? 'Tidak ada penulis';
                $timPenulis = $creatorTim?->nama_tim ?? 'Tidak ada tim';
                
                return [
                    'id' => $n->id,
                    'tanggal' => $n->tanggal->translatedFormat('d M Y'),
                    'tanggal_raw' => $n->tanggal->toDateString(),
                    'hari' => $n->tanggal->translatedFormat('l'),
                    'sesi' => ucfirst($n->sesi),
                    'nama_notulen' => $n->nama_notulen,
                    'tim_penulis' => $timPenulis,
                    'penulis_nama' => $penulisNama,
                    'user_name' => $n->user?->name ?? '—',
                    'jumlah_peserta' => $n->jumlah_peserta,
                    'peserta' => $n->peserta ?? [],
                    'catatan' => $n->catatan ?? '—',
                    'has_file' => $n->hasFile(),
                    'file_name' => $n->file_name,
                    'file_url' => $n->file_url,
                    'file_size' => $n->formatted_file_size,
                    'created_at' => $n->created_at->translatedFormat('d M Y, H:i'),
                    'jenis_kehadiran' => $n->jenis_kehadiran, // FASE 4.2 - Task #8
                ];
            })
            ->toArray();
    }

    #[Computed]
    public function totalNotulen(): int
    {
        if (! Auth::user()->tim_id) {
            return 0;
        }

        // Count ALL notulen (visible to all tim)
        return NotulenBriefing::count();
    }

    #[Computed]
    public function notulenBulanIni(): int
    {
        if (! Auth::user()->tim_id) {
            return 0;
        }

        // Count ALL notulen for current month (visible to all tim)
        return NotulenBriefing::whereYear('tanggal', now()->year)
            ->whereMonth('tanggal', now()->month)
            ->count();
    }

    public function bukaDetail(int $id): void
    {
        $this->detailId = $id;
        $this->modal('detail-modal')->show();
    }

    public function tutupDetail(): void
    {
        $this->detailId = null;
        $this->modal('detail-modal')->close();
    }

    public function buatBaru()
    {
        return redirect()->route('tim.notulen.create');
    }

    #[Computed]
    public function detailNotulen(): ?array
    {
        if (! $this->detailId) {
            return null;
        }

        $notulen = NotulenBriefing::with(['user', 'timYangTerlibat'])->find($this->detailId);

        if (! $notulen) {
            return null;
        }

        // Get penulis from JadwalBriefing (from creator tim)
        $creatorTim = $notulen->timYangTerlibat->where('pivot.is_creator', true)->first();
        $jadwal = null;
        
        if ($creatorTim) {
            $jadwal = \App\Models\JadwalBriefing::withoutGlobalScope('tim_isolation')
                ->where('tim_id', $creatorTim->id)
                ->where('tanggal', $notulen->tanggal)
                ->where('sesi', $notulen->sesi)
                ->where('is_notulen', true)
                ->with('personil')
                ->first();
        }
        
        $penulisNama = $jadwal?->personil?->nama ?? 'Tidak ada penulis';
        $timPenulis = $creatorTim?->nama_tim ?? 'Tidak ada tim';

        return [
            'id' => $notulen->id,
            'tanggal' => $notulen->tanggal->translatedFormat('l, d F Y'),
            'sesi' => ucfirst($notulen->sesi),
            'nama_notulen' => $notulen->nama_notulen,
            'tim_penulis' => $timPenulis,
            'penulis_nama' => $penulisNama,
            'user_name' => $notulen->user?->name ?? '—',
            'peserta' => $notulen->peserta ?? [],
            'jumlah_peserta' => $notulen->jumlah_peserta,
            'catatan' => $notulen->catatan ?? 'Tidak ada catatan.',
            'has_file' => $notulen->hasFile(),
            'file_name' => $notulen->file_name,
            'file_url' => $notulen->file_url,
            'file_size' => $notulen->formatted_file_size,
            'file_type' => $notulen->file_type,
            'created_at' => $notulen->created_at->translatedFormat('d M Y, H:i'),
            'jenis_kehadiran' => $notulen->jenis_kehadiran, // FASE 4.2 - Task #8
        ];
    }
}; ?>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">
                Riwayat Notulen Briefing
            </flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400 mt-0.5">
                Lihat dokumentasi notulen briefing tim Anda.
            </flux:text>
        </div>
        {{-- Show button only when there are existing notulen --}}
        @if (count($this->notulenList) > 0)
            <flux:button variant="primary" wire:click="buatBaru" icon="plus">
                Buat Notulen Baru
            </flux:button>
        @endif
    </div>

    {{-- Quick Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <flux:card variant="soft" class="relative overflow-hidden p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="relative z-10">
                <flux:text class="font-medium text-xs text-zinc-500 dark:text-zinc-400">Total Notulen</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                    {{ $this->totalNotulen }}
                </flux:heading>
            </div>
            <flux:icon icon="clipboard-document-list" class="absolute -bottom-3 -right-3 size-24 text-blue-500/10 dark:text-blue-400/10 pointer-events-none" />
        </flux:card>

        <flux:card variant="soft" class="relative overflow-hidden p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="relative z-10">
                <flux:text class="font-medium text-xs text-zinc-500 dark:text-zinc-400">Bulan Ini</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                    {{ $this->notulenBulanIni }}
                </flux:heading>
            </div>
            <flux:icon icon="calendar-days" class="absolute -bottom-3 -right-3 size-24 text-emerald-500/10 dark:text-emerald-400/10 pointer-events-none" />
        </flux:card>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col sm:flex-row gap-3">
        <flux:field class="flex-1">
            <flux:label>Filter Bulan</flux:label>
            <flux:input
                wire:model.live="filter_bulan"
                type="month"
            />
        </flux:field>

        <flux:field class="sm:w-48">
            <flux:label>Filter Sesi</flux:label>
            <flux:select wire:model.live="filter_sesi">
                <option value="">Semua Sesi</option>
                <option value="pagi">Pagi</option>
                <option value="sore">Sore</option>
            </flux:select>
        </flux:field>

        {{-- FASE 4.2 - Task #8: Filter Jenis Kehadiran --}}
        <flux:field class="sm:w-48">
            <flux:label>Filter Jenis</flux:label>
            <flux:select wire:model.live="filter_jenis">
                <option value="">Semua Jenis</option>
                <option value="wfo">WFO</option>
                <option value="wfh">WFH</option>
            </flux:select>
        </flux:field>
    </div>

    {{-- Notulen List --}}
    @if (count($this->notulenList) === 0)
        <flux:card class="p-12">
            <div class="text-center">
                <flux:icon icon="clipboard-document-list" class="size-16 mx-auto text-zinc-300 dark:text-zinc-600 mb-4" />
                <flux:heading size="lg" class="text-zinc-700 dark:text-zinc-300 mb-2">
                    Belum Ada Notulen
                </flux:heading>
                <flux:text class="text-zinc-500 mb-6">
                    @if ($filter_bulan || $filter_sesi)
                        Tidak ada notulen untuk filter yang dipilih.
                    @else
                        Mulai buat notulen briefing untuk tim Anda.
                    @endif
                </flux:text>
                <flux:button variant="primary" wire:click="buatBaru" icon="plus">
                    Buat Notulen Pertama
                </flux:button>
            </div>
        </flux:card>
    @else
        <div class="space-y-3">
            @foreach ($this->notulenList as $notulen)
                <flux:card class="hover:shadow-md transition-shadow" wire:key="notulen-{{ $notulen['id'] }}">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4 p-5">
                        {{-- Date Badge --}}
                        <div class="flex-shrink-0 sm:w-24">
                            <div class="text-center p-3 rounded-lg bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800">
                                <p class="text-2xl font-bold text-blue-700 dark:text-blue-300 leading-none">
                                    {{ \Carbon\Carbon::parse($notulen['tanggal_raw'])->format('d') }}
                                </p>
                                <p class="text-xs text-blue-600 dark:text-blue-400 uppercase mt-1">
                                    {{ \Carbon\Carbon::parse($notulen['tanggal_raw'])->translatedFormat('M Y') }}
                                </p>
                            </div>
                        </div>

                        {{-- Content --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start gap-2 mb-2">
                                <flux:heading size="sm" class="font-semibold text-zinc-900 dark:text-zinc-100">
                                    Briefing {{ $notulen['sesi'] }} - {{ $notulen['hari'] }}
                                </flux:heading>
                                
                                {{-- FASE 4.2 - Task #8: WFH Badge --}}
                                @if ($notulen['jenis_kehadiran'] === 'wfh')
                                    <flux:badge color="blue" size="sm" icon="home">WFH</flux:badge>
                                @endif
                                
                                @if ($notulen['has_file'])
                                    <flux:badge color="zinc" size="sm" icon="paper-clip">File</flux:badge>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-3 text-sm text-zinc-600 dark:text-zinc-400">
                                <span class="flex items-center gap-1.5">
                                    <flux:icon icon="user" class="size-4" />
                                    {{ $notulen['nama_notulen'] }}
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <flux:icon icon="user-group" class="size-4" />
                                    {{ $notulen['jumlah_peserta'] }} peserta
                                </span>
                                <span class="flex items-center gap-1.5 text-xs text-zinc-400">
                                    <flux:icon icon="clock" class="size-3.5" />
                                    Dibuat {{ $notulen['created_at'] }}
                                </span>
                            </div>
                        </div>

                        {{-- Action --}}
                        <div class="flex-shrink-0">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                wire:click="bukaDetail({{ $notulen['id'] }})"
                                icon="eye"
                            >
                                Lihat Detail
                            </flux:button>
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif

    {{-- Detail Modal --}}
    <flux:modal name="detail-modal" class="max-w-3xl">
        @if ($this->detailNotulen)
            <div>
                <flux:heading size="lg" class="mb-1">Detail Notulen Briefing</flux:heading>
                <flux:text class="text-zinc-500 mb-6">
                    {{ $this->detailNotulen['tanggal'] }} - Sesi {{ $this->detailNotulen['sesi'] }}
                </flux:text>

                <div class="space-y-5">
                    {{-- Notulen Info --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
                        <div>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-1">Tim Penulis</p>
                            <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $this->detailNotulen['nama_notulen'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-1">Penulis Notulen</p>
                            <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $this->detailNotulen['penulis_nama'] }}</p>
                        </div>
                        
                        {{-- FASE 4.2 - Task #8: Display Jenis Kehadiran --}}
                        <div>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-1">Jenis Kehadiran</p>
                            <div class="flex items-center gap-2">
                                @if ($this->detailNotulen['jenis_kehadiran'] === 'wfh')
                                    <flux:badge color="blue" icon="home">WFH</flux:badge>
                                @else
                                    <flux:badge color="green" icon="building-office-2">WFO</flux:badge>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Jumlah Peserta & Daftar Peserta - REMOVED per requirement --}}

                    {{-- Catatan --}}
                    <div>
                        <flux:label class="mb-2">Catatan Briefing</flux:label>
                        <div class="p-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                            <p class="text-sm text-zinc-700 dark:text-zinc-300 whitespace-pre-wrap">{{ $this->detailNotulen['catatan'] }}</p>
                        </div>
                    </div>

                    {{-- File Attachment --}}
                    @if ($this->detailNotulen['has_file'])
                        <div>
                            <flux:label class="mb-2">File Lampiran</flux:label>
                            <div class="p-4 bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <flux:icon 
                                            :icon="str_contains($this->detailNotulen['file_type'], 'pdf') ? 'document-text' : 'photo'" 
                                            class="size-8 text-blue-600 dark:text-blue-400 flex-shrink-0" 
                                        />
                                        <div class="min-w-0">
                                            <p class="font-medium text-zinc-900 dark:text-zinc-100 truncate">
                                                {{ $this->detailNotulen['file_name'] }}
                                            </p>
                                            <p class="text-xs text-zinc-500">{{ $this->detailNotulen['file_size'] }}</p>
                                        </div>
                                    </div>
                                    <flux:button
                                        size="sm"
                                        variant="primary"
                                        :href="$this->detailNotulen['file_url']"
                                        target="_blank"
                                        icon="arrow-down-tray"
                                    >
                                        Download
                                    </flux:button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <flux:button variant="ghost" wire:click="tutupDetail">
                        Tutup
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
