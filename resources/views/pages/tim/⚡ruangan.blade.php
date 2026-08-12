<?php

use App\Models\AlokasiRuangan;
use App\Models\JadwalWfo;
use App\Models\PeriodeWfo;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ruangan Tim')] #[Layout('layouts.auth')] class extends Component {

    #[Computed]
    public function timId(): ?int
    {
        // Wajib scope ke tim milik user yang login — §4.3.1, §5.2
        return auth()->user()->tim_id;
    }

    #[Computed]
    public function namaTim(): string
    {
        return auth()->user()->tim?->nama_tim ?? 'Tim Anda';
    }

    /** Ruangan yang dialokasikan untuk tim ini hari ini */
    #[Computed]
    public function alokasiHariIni(): ?AlokasiRuangan
    {
        if (! $this->timId) {
            return null;
        }

        return AlokasiRuangan::with('ruangan')
            ->where('tim_id', $this->timId)
            ->where('tanggal', now()->toDateString())
            ->first();
    }

    /** Apakah tim WFO hari ini */
    #[Computed]
    public function isWfoHariIni(): bool
    {
        if (! $this->timId) {
            return false;
        }

        $periode = PeriodeWfo::where('status', 'aktif')->first();

        if (! $periode) {
            return false;
        }

        $namaHari = match (now()->dayOfWeekIso) {
            1 => 'senin', 2 => 'selasa', 3 => 'rabu',
            4 => 'kamis', 5 => 'jumat', 6 => 'sabtu',
            default => null,
        };

        if (! $namaHari) {
            return false;
        }

        return JadwalWfo::where('periode_wfo_id', $periode->id)
            ->where('tim_id', $this->timId)
            ->where('hari', $namaHari)
            ->exists();
    }

    /** Riwayat alokasi ruangan tim ini (tidak termasuk hari ini) */
    #[Computed]
    public function riwayatAlokasi()
    {
        if (! $this->timId) {
            return collect();
        }

        return AlokasiRuangan::with('ruangan')
            ->where('tim_id', $this->timId)
            ->where('tanggal', '<', now()->toDateString())
            ->orderByDesc('tanggal')
            ->limit(30)
            ->get();
    }
}; ?>

<div class="min-h-screen bg-zinc-50 dark:bg-zinc-900">
    {{-- Topbar --}}
    <header class="sticky top-0 z-10 border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 h-14 flex items-center px-4 gap-3">
        <span class="font-semibold text-sm text-zinc-900 dark:text-zinc-100 flex-1">
            {{ $this->namaTim }}
        </span>
        <a href="{{ route('notifikasi.index') }}" wire:navigate class="p-1.5 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-700">
            <flux:icon icon="bell" class="h-5 w-5 text-zinc-500" />
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <flux:button type="submit" variant="ghost" size="sm">Keluar</flux:button>
        </form>
    </header>

    <div class="max-w-3xl mx-auto px-4 py-6 flex flex-col gap-6">
        {{-- Header --}}
        <div>
            <flux:heading size="xl">Ruangan Hari Ini</flux:heading>
            <flux:text class="text-zinc-500">
                {{ now()->translatedFormat('l, d F Y') }}
            </flux:text>
        </div>

        @if (! $this->timId)
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>Akun belum terhubung ke tim</flux:callout.heading>
                <flux:callout.text>Hubungi admin untuk menghubungkan akun kamu ke data tim.</flux:callout.text>
            </flux:callout>

        @elseif (! $this->isWfoHariIni)
            <x-empty-state
                icon="building-office-2"
                title="Tim Anda tidak WFO hari ini"
                description="Tidak ada alokasi ruangan karena tim Anda tidak dijadwalkan WFO hari ini."
            />

        @elseif ($this->alokasiHariIni)
            {{-- Card ruangan hari ini --}}
            <flux:card class="flex flex-col gap-3">
                <div class="flex items-center gap-3">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/30">
                        <flux:icon icon="building-office-2" class="h-6 w-6 text-emerald-600" />
                    </span>
                    <div>
                        <p class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                            {{ $this->alokasiHariIni->ruangan->nama_ruangan }}
                        </p>
                        <p class="text-sm text-zinc-500">
                            Kapasitas: {{ $this->alokasiHariIni->ruangan->kapasitas }} orang
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <x-status-badge status="aktif" />
                    <flux:text class="text-xs text-zinc-400">Dialokasikan otomatis oleh sistem</flux:text>
                </div>
            </flux:card>

        @else
            <x-empty-state
                icon="building-office-2"
                title="Belum ada alokasi ruangan"
                description="Admin belum meng-generate alokasi ruangan untuk hari ini."
            />
        @endif

        {{-- Riwayat alokasi --}}
        @if ($this->riwayatAlokasi->isNotEmpty())
            <div>
                <flux:heading size="sm" class="mb-3">Riwayat Ruangan</flux:heading>
                <flux:card class="p-0 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800 border-b border-zinc-100 dark:border-zinc-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-zinc-500">Tanggal</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-zinc-500">Ruangan</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-zinc-500">Kapasitas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->riwayatAlokasi as $alokasi)
                                <tr>
                                    <td class="px-4 py-2 text-zinc-600 dark:text-zinc-400">
                                        {{ $alokasi->tanggal->translatedFormat('d M Y') }}
                                    </td>
                                    <td class="px-4 py-2 font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $alokasi->ruangan->nama_ruangan }}
                                    </td>
                                    <td class="px-4 py-2 text-zinc-500">
                                        {{ $alokasi->ruangan->kapasitas }} orang
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </flux:card>
            </div>
        @endif
    </div>
</div>
