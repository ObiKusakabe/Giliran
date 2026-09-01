<?php

use App\Models\JadwalAdzanKitab;
use App\Models\JadwalBriefing;
use App\Models\Notifikasi;
use App\Services\AutoSwapService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Jadwal Saya')] #[Layout('layouts.auth')] class extends Component {

    public ?int $konfirmasiId   = null;
    public string $konfirmasiTipe = ''; // 'adzan' | 'briefing'

    #[Computed]
    public function personil()
    {
        return auth()->user()->personil;
    }

    #[Computed]
    public function tugasMendatang(): array
    {
        if (! $this->personil) {
            return [];
        }

        $personilId = $this->personil->id;
        $sekarang   = now()->toDateString();

        $adzan = JadwalAdzanKitab::where('personil_id', $personilId)
            ->where('tanggal', '>=', $sekarang)
            ->orderBy('tanggal')
            ->orderBy('waktu_sholat')
            ->get()
            ->map(fn ($j) => [
                'id'                => $j->id,
                'tipe'              => 'adzan',
                'tanggal'           => $j->tanggal,
                'label'             => ucfirst($j->jenis_tugas).' '.strtoupper($j->waktu_sholat),
                'keterangan'        => 'Adzan & Kajian',
                'status_konfirmasi' => $j->status_konfirmasi,
            ])
            ->toArray();

        $briefing = JadwalBriefing::with('tim')
            ->where('personil_id', $personilId)
            ->where('tanggal', '>=', $sekarang)
            ->orderBy('tanggal')
            ->get()
            ->map(fn ($j) => [
                'id'                => $j->id,
                'tipe'              => 'briefing',
                'tanggal'           => $j->tanggal,
                'label'             => 'Briefing '.ucfirst($j->sesi),
                'keterangan'        => $j->tim->nama_tim ?? '—',
                'status_konfirmasi' => $j->status_konfirmasi,
            ])
            ->toArray();

        // Gabung dan urutkan by tanggal
        return collect(array_merge($adzan, $briefing))
            ->sortBy('tanggal')
            ->values()
            ->toArray();
    }

    #[Computed]
    public function jumlahBelumDibaca(): int
    {
        if (! $this->personil) {
            return 0;
        }

        return Notifikasi::where('personil_id', $this->personil->id)
            ->where('dibaca', false)
            ->count();
    }

    public function konfirmasiSiap(int $id, string $tipe): void
    {
        $this->updateStatusKonfirmasi($id, $tipe, 'siap');
        Flux::toast(variant: 'success', text: 'Konfirmasi kehadiran berhasil disimpan.');
        unset($this->tugasMendatang);
    }

    public function konfirmasiBerhalangan(int $id, string $tipe): void
    {
        if (! $this->personil) {
            return;
        }

        $autoSwap = app(AutoSwapService::class);

        $pesan = $tipe === 'adzan'
            ? $autoSwap->berhalanganAdzan($id, $this->personil->id)
            : $autoSwap->berhalanganBriefing($id, $this->personil->id);

        Flux::toast(variant: 'warning', text: "Berhalangan dicatat. {$pesan}");
        unset($this->tugasMendatang);
    }

    private function updateStatusKonfirmasi(int $id, string $tipe, string $status): void
    {
        if ($tipe === 'adzan') {
            JadwalAdzanKitab::where('id', $id)
                ->where('personil_id', $this->personil->id)
                ->update(['status_konfirmasi' => $status]);
        } else {
            JadwalBriefing::where('id', $id)
                ->where('personil_id', $this->personil->id)
                ->update(['status_konfirmasi' => $status]);
        }
    }
}; ?>

<div class="min-h-screen bg-zinc-50 dark:bg-zinc-900">
    {{-- Topbar --}}
    <header class="sticky top-0 z-20 border-b border-zinc-200/60 dark:border-zinc-700/60 bg-white/80 dark:bg-zinc-800/80 backdrop-blur-md h-14 flex items-center px-4 gap-3">
        <span class="font-semibold text-sm text-zinc-900 dark:text-zinc-100 flex-1">
            Jadwal Saya
        </span>

        {{-- Bell notifikasi --}}
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
        {{-- Greeting --}}
        <div>
            <flux:heading size="xl">Halo, {{ auth()->user()->name }} 👋</flux:heading>
            <flux:text class="text-zinc-500">
                Berikut jadwal tugasmu ke depan. Konfirmasi kehadiranmu sebelum hari H.
            </flux:text>
        </div>

        @if (! $this->personil)
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>Akun belum terhubung ke data personil</flux:callout.heading>
                <flux:callout.text>Hubungi admin untuk menghubungkan akun kamu ke data personil.</flux:callout.text>
            </flux:callout>
        @elseif (empty($this->tugasMendatang))
            <x-empty-state
                icon="calendar-days"
                title="Tidak ada tugas mendatang"
                description="Kamu belum memiliki jadwal tugas ke depan."
            />
        @else
            <div class="flex flex-col gap-3">
                @foreach ($this->tugasMendatang as $tugas)
                    @php
                        $isMenunggu = $tugas['status_konfirmasi'] === 'menunggu';
                        $tanggal    = \Carbon\Carbon::parse($tugas['tanggal']);
                        $isHariIni  = $tanggal->isToday();
                        $isBesok    = $tanggal->isTomorrow();
                    @endphp

                    <flux:card class="flex flex-col sm:flex-row sm:items-center gap-3 {{ $isHariIni ? 'ring-2 ring-brand' : '' }}">
                        {{-- Info tanggal --}}
                        <div class="flex-shrink-0 text-center w-16">
                            <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 leading-none">
                                {{ $tanggal->format('d') }}
                            </p>
                            <p class="text-xs text-zinc-400 uppercase">
                                {{ $tanggal->translatedFormat('M Y') }}
                            </p>
                            @if ($isHariIni)
                                <span class="text-[10px] font-semibold text-brand">Hari ini</span>
                            @elseif ($isBesok)
                                <span class="text-[10px] font-semibold text-amber-500">Besok</span>
                            @endif
                        </div>

                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $tugas['label'] }}</p>
                            <p class="text-sm text-zinc-500">
                                {{ $tugas['keterangan'] }} · {{ $tanggal->translatedFormat('l') }}
                            </p>
                        </div>

                        {{-- Status & tombol konfirmasi --}}
                        <div class="flex items-center gap-2 flex-shrink-0">
                            @if ($isMenunggu)
                                <flux:button
                                    size="sm"
                                    variant="primary"
                                    wire:click="konfirmasiSiap({{ $tugas['id'] }}, '{{ $tugas['tipe'] }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="konfirmasiSiap({{ $tugas['id'] }}, '{{ $tugas['tipe'] }}')"
                                    icon="check"
                                >
                                    Siap
                                </flux:button>
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    wire:click="konfirmasiBerhalangan({{ $tugas['id'] }}, '{{ $tugas['tipe'] }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="konfirmasiBerhalangan({{ $tugas['id'] }}, '{{ $tugas['tipe'] }}')"
                                    icon="x-mark"
                                >
                                    Berhalangan
                                </flux:button>
                            @else
                                <x-status-badge :status="$tugas['status_konfirmasi']" />
                            @endif
                        </div>
                    </flux:card>
                @endforeach
            </div>
        @endif
    </div>
</div>
