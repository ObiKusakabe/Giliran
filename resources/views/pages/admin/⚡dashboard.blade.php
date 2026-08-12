<?php

use App\Models\AlokasiRuangan;
use App\Models\JadwalAdzanKitab;
use App\Models\JadwalBriefing;
use App\Models\JadwalWfo;
use App\Models\PeriodeWfo;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] #[Layout('layouts.admin')] class extends Component {

    #[Computed]
    public function periodeAktif(): ?PeriodeWfo
    {
        return PeriodeWfo::where('status', 'aktif')->first();
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
}; ?>

<div class="flex flex-col gap-6" wire:poll="60s">
    {{-- Header --}}
    <div>
        <flux:heading size="xl">Dashboard</flux:heading>
        <flux:text class="text-zinc-500">
            Ringkasan aktivitas hari ini — {{ now()->translatedFormat('l, d F Y') }}
        </flux:text>
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

    {{-- DSB-01/02/03 — 4 stat cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="flex flex-col gap-1">
            <flux:text class="text-xs text-zinc-500 uppercase tracking-wide">Personil Terjadwal</flux:text>
            <p class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                {{ $this->personilTerjadwalHariIni }}
            </p>
            <flux:text class="text-xs text-zinc-400">hari ini</flux:text>
        </flux:card>

        <flux:card class="flex flex-col gap-1">
            <flux:text class="text-xs text-zinc-500 uppercase tracking-wide">Ruang Teralokasi</flux:text>
            <p class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                {{ $this->ruanganTeralokasHariIni }}
            </p>
            <flux:text class="text-xs text-zinc-400">hari ini</flux:text>
        </flux:card>

        <flux:card class="flex flex-col gap-1">
            <flux:text class="text-xs text-zinc-500 uppercase tracking-wide">Konfirmasi Tertunda</flux:text>
            <p class="text-3xl font-bold {{ $this->konfirmasiTertunda > 0 ? 'text-amber-500' : 'text-zinc-900 dark:text-zinc-100' }}">
                {{ $this->konfirmasiTertunda }}
            </p>
            <flux:text class="text-xs text-zinc-400">belum konfirmasi</flux:text>
        </flux:card>

        <flux:card class="flex flex-col gap-1">
            <flux:text class="text-xs text-zinc-500 uppercase tracking-wide">Tim WFO</flux:text>
            <p class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                {{ $this->timWfoHariIni }}
            </p>
            <flux:text class="text-xs text-zinc-400">hari ini</flux:text>
        </flux:card>
    </div>

    {{-- Tabel konfirmasi tertunda --}}
    @if ($this->daftarKonfirmasiTertunda->isNotEmpty())
        <flux:card class="p-0 overflow-hidden">
            <div class="px-4 py-3 border-b border-zinc-100 dark:border-zinc-800">
                <flux:heading size="sm">Konfirmasi Tertunda</flux:heading>
                <flux:text class="text-xs text-zinc-400">10 terdekat</flux:text>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-800">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-zinc-500">Personil</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-zinc-500">Tugas</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-zinc-500">Tim / Jenis</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-zinc-500">Tanggal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->daftarKonfirmasiTertunda as $item)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="px-4 py-2 font-medium text-zinc-900 dark:text-zinc-100">{{ $item['nama'] }}</td>
                                <td class="px-4 py-2 text-zinc-600 dark:text-zinc-400">{{ $item['jenis'] }}</td>
                                <td class="px-4 py-2 text-zinc-500">{{ $item['tipe'] }}</td>
                                <td class="px-4 py-2 text-zinc-500">
                                    {{ \Carbon\Carbon::parse($item['tanggal'])->translatedFormat('d M Y') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </flux:card>
    @endif
</div>
