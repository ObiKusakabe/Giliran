<?php

use App\Models\JadwalAdzanKitab;
use App\Models\JadwalBriefing;
use App\Models\Notifikasi;
use App\Models\Personil;
use App\Services\AutoSwapService;
use App\Services\LraScheduler;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Jadwal Tim')] #[Layout('layouts.auth')] class extends Component {

    #[Computed]
    public function tim()
    {
        return auth()->user()->tim;
    }

    #[Computed]
    public function timId(): ?int
    {
        return auth()->user()->tim_id;
    }

    /** Semua personil aktif dalam tim ini */
    #[Computed]
    public function personilList()
    {
        if (! $this->timId) {
            return collect();
        }

        return Personil::where('tim_id', $this->timId)
            ->where('status', 'aktif')
            ->orderBy('nama')
            ->get();
    }

    /**
     * Tugas mendatang dikelompokkan per personil.
     * Format: [personil_id => ['personil' => Personil, 'tugas' => [...]]]
     */
    #[Computed]
    public function tugasPerPersonil(): array
    {
        if (! $this->timId) {
            return [];
        }

        $sekarang    = now()->toDateString();
        $personilIds = $this->personilList->pluck('id');

        // Adzan/kajian mendatang
        $adzan = JadwalAdzanKitab::whereIn('personil_id', $personilIds)
            ->where('tanggal', '>=', $sekarang)
            ->orderBy('tanggal')->orderBy('waktu_sholat')
            ->get()
            ->map(fn ($j) => [
                'id'                => $j->id,
                'personil_id'       => $j->personil_id,
                'tipe'              => 'adzan',
                'tanggal'           => $j->tanggal,
                'label'             => ucfirst($j->jenis_tugas).' '.strtoupper($j->waktu_sholat),
                'keterangan'        => 'Adzan & Kajian',
                'status_konfirmasi' => $j->status_konfirmasi,
            ]);

        // Briefing mendatang (hanya tim ini)
        $briefing = JadwalBriefing::where('tim_id', $this->timId)
            ->whereIn('personil_id', $personilIds)
            ->where('tanggal', '>=', $sekarang)
            ->orderBy('tanggal')->orderBy('sesi')
            ->get()
            ->map(fn ($j) => [
                'id'                => $j->id,
                'personil_id'       => $j->personil_id,
                'tipe'              => 'briefing',
                'tanggal'           => $j->tanggal,
                'label'             => 'Briefing '.ucfirst($j->sesi),
                'keterangan'        => $j->tim?->nama_tim ?? '—',
                'status_konfirmasi' => $j->status_konfirmasi,
            ]);

        // Gabungkan & group per personil
        $semuaTugas = collect(array_merge($adzan->toArray(), $briefing->toArray()))
            ->sortBy('tanggal');

        $result = [];
        foreach ($this->personilList as $personil) {
            $tugas = $semuaTugas->filter(fn ($t) => $t['personil_id'] === $personil->id)->values();
            $result[$personil->id] = [
                'personil' => $personil,
                'tugas'    => $tugas->toArray(),
            ];
        }

        return $result;
    }

    #[Computed]
    public function jumlahBelumDibaca(): int
    {
        if (! $this->timId) {
            return 0;
        }

        return Notifikasi::where('user_id', auth()->id())
            ->where('dibaca', false)
            ->count();
    }

    public function konfirmasiSiap(int $id, string $tipe): void
    {
        $this->updateStatus($id, $tipe, 'siap');
        Flux::toast(variant: 'success', text: 'Konfirmasi kehadiran berhasil disimpan.');
        unset($this->tugasPerPersonil);
    }

    public function konfirmasiBerhalangan(int $id, string $tipe, int $personilId): void
    {
        $autoSwap = app(AutoSwapService::class);

        $pesan = $tipe === 'adzan'
            ? $autoSwap->berhalanganAdzan($id, $personilId)
            : $autoSwap->berhalanganBriefing($id, $personilId);

        Flux::toast(variant: 'warning', text: "Berhalangan dicatat. {$pesan}");
        unset($this->tugasPerPersonil);
    }

    private function updateStatus(int $id, string $tipe, string $status): void
    {
        if ($tipe === 'adzan') {
            JadwalAdzanKitab::where('id', $id)->update(['status_konfirmasi' => $status]);
        } else {
            JadwalBriefing::where('id', $id)->update(['status_konfirmasi' => $status]);
        }
    }
}; ?>

<div class="min-h-screen bg-zinc-50 dark:bg-zinc-900">
    {{-- Topbar --}}
    <header class="sticky top-0 z-10 border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 h-14 flex items-center px-4 gap-3">
        <span class="font-semibold text-sm text-zinc-900 dark:text-zinc-100 flex-1">
            {{ $this->tim?->nama_tim ?? 'Jadwal Tim' }}
        </span>

        <a href="{{ route('tim.ruangan') }}" wire:navigate
           class="p-1.5 rounded-md text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-700 text-xs text-zinc-500">
            Ruangan
        </a>

        <a href="{{ route('notifikasi.index') }}" wire:navigate class="relative p-1.5 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-700">
            <flux:icon icon="bell" class="h-5 w-5 text-zinc-500" />
            @if ($this->jumlahBelumDibaca > 0)
                <span class="absolute top-0.5 right-0.5 h-4 w-4 rounded-full bg-red-500 text-white text-[10px] flex items-center justify-center font-bold">
                    {{ $this->jumlahBelumDibaca > 9 ? '9+' : $this->jumlahBelumDibaca }}
                </span>
            @endif
        </a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <flux:button type="submit" variant="ghost" size="sm">Keluar</flux:button>
        </form>
    </header>

    <div class="max-w-3xl mx-auto px-4 py-6 flex flex-col gap-6">
        {{-- Header --}}
        <div>
            <flux:heading size="xl">Jadwal Tim</flux:heading>
            <flux:text class="text-zinc-500">
                Jadwal tugas seluruh anggota {{ $this->tim?->nama_tim ?? 'tim' }} ke depan.
                Konfirmasi kehadiran masing-masing anggota.
            </flux:text>
        </div>

        @if (! $this->timId)
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>Akun belum terhubung ke tim</flux:callout.heading>
                <flux:callout.text>Hubungi admin untuk menghubungkan akun ini ke data tim.</flux:callout.text>
            </flux:callout>

        @elseif (empty($this->tugasPerPersonil))
            <x-empty-state
                icon="calendar-days"
                title="Tidak ada tugas mendatang"
                description="Tim kamu belum memiliki jadwal tugas ke depan."
            />

        @else
            @foreach ($this->tugasPerPersonil as $personilId => $data)
                @php
                    $personil     = $data['personil'];
                    $tugas        = $data['tugas'];
                    $hasTugas     = count($tugas) > 0;
                    $pending      = collect($tugas)->where('status_konfirmasi', 'menunggu')->count();
                @endphp

                <div class="flex flex-col gap-2">
                    {{-- Header personil --}}
                    <div class="flex items-center flex-wrap gap-2 px-1">
                        <flux:avatar :name="$personil->nama" size="xs" />
                        <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ $personil->nama }}
                        </span>
                        @if ($pending > 0)
                            <span class="inline-flex items-center rounded-full bg-amber-100 text-amber-700 px-2 py-0.5 text-xs font-medium">
                                {{ $pending }} belum konfirmasi
                            </span>
                        @elseif ($hasTugas)
                            <span class="inline-flex items-center rounded-full bg-emerald-100 text-emerald-700 px-2 py-0.5 text-xs font-medium">
                                Semua terkonfirmasi
                            </span>
                        @endif
                    </div>

                    @if (! $hasTugas)
                        <div class="rounded-lg border border-dashed border-zinc-200 dark:border-zinc-700 px-4 py-3 text-sm text-zinc-400">
                            Tidak ada tugas mendatang untuk {{ $personil->nama }}.
                        </div>
                    @else
                        @foreach ($tugas as $t)
                            @php
                                $tanggal   = \Carbon\Carbon::parse($t['tanggal']);
                                $isMenunggu = $t['status_konfirmasi'] === 'menunggu';
                                $isHariIni  = $tanggal->isToday();
                                $isBesok    = $tanggal->isTomorrow();
                            @endphp

                            <flux:card class="flex flex-col sm:flex-row sm:items-center gap-3 {{ $isHariIni ? 'ring-2 ring-brand' : '' }}">
                                {{-- Tanggal --}}
                                <div class="flex sm:flex-col items-center sm:items-center gap-3 sm:gap-0 flex-shrink-0 sm:w-14">
                                    <div class="flex items-baseline gap-1 sm:block sm:text-center">
                                        <p class="text-xl font-bold text-zinc-900 dark:text-zinc-100 leading-none">
                                            {{ $tanggal->format('d') }}
                                        </p>
                                        <p class="text-xs text-zinc-400 uppercase sm:mt-0">
                                            {{ $tanggal->translatedFormat('M Y') }}
                                        </p>
                                    </div>
                                    @if ($isHariIni)
                                        <span class="text-xs font-semibold text-brand">Hari ini</span>
                                    @elseif ($isBesok)
                                        <span class="text-xs font-semibold text-amber-500">Besok</span>
                                    @endif
                                </div>

                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $t['label'] }}</p>
                                    <p class="text-sm text-zinc-500">
                                        {{ $t['keterangan'] }} · {{ $tanggal->translatedFormat('l') }}
                                    </p>
                                </div>

                                {{-- Konfirmasi --}}
                                <div class="flex items-center gap-2 flex-shrink-0 flex-wrap">
                                    @if ($isMenunggu)
                                        <flux:button
                                            size="sm"
                                            variant="primary"
                                            wire:click="konfirmasiSiap({{ $t['id'] }}, '{{ $t['tipe'] }}')"
                                            wire:loading.attr="disabled"
                                            icon="check"
                                        >
                                            Siap
                                        </flux:button>
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            wire:click="konfirmasiBerhalangan({{ $t['id'] }}, '{{ $t['tipe'] }}', {{ $personilId }})"
                                            wire:loading.attr="disabled"
                                            icon="x-mark"
                                        >
                                            Berhalangan
                                        </flux:button>
                                    @else
                                        <x-status-badge :status="$t['status_konfirmasi']" />
                                    @endif
                                </div>
                            </flux:card>
                        @endforeach
                    @endif
                </div>
            @endforeach
        @endif
    </div>
</div>
