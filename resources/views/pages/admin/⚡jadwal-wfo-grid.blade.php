<?php

use App\Models\JadwalWfo;
use App\Models\PeriodeWfo;
use App\Models\Tim;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Jadwal WFO')] #[Layout('layouts.admin')] class extends Component {

    public ?int $periodeId = null;

    /** Daftar hari sesuai §5.2 */
    public const HARI = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'];

    public function mount(): void
    {
        // Default ke periode aktif
        $aktif = PeriodeWfo::where('status', 'aktif')->first();
        $this->periodeId = $aktif?->id;
    }

    #[Computed]
    public function periodeOptions()
    {
        return PeriodeWfo::orderByDesc('tanggal_mulai')->get(['id', 'keterangan', 'status', 'tanggal_mulai']);
    }

    #[Computed]
    public function periodeDipilih(): ?PeriodeWfo
    {
        return $this->periodeId ? PeriodeWfo::find($this->periodeId) : null;
    }

    #[Computed]
    public function semuaTim()
    {
        return Tim::orderBy('nama_tim')->get(['id', 'nama_tim']);
    }

    /**
     * Mengambil pola grid: [hari => [tim_id => nama_tim, ...]]
     *
     * @return array<string, array<int, string>>
     */
    #[Computed]
    public function gridData(): array
    {
        if (! $this->periodeId) {
            return array_fill_keys(self::HARI, []);
        }

        $rows = JadwalWfo::with('tim')
            ->where('periode_wfo_id', $this->periodeId)
            ->get();

        $grid = array_fill_keys(self::HARI, []);

        foreach ($rows as $row) {
            $grid[$row->hari][$row->tim_id] = $row->tim->nama_tim;
        }

        return $grid;
    }

    /**
     * Tambah tim ke hari tertentu — auto-save (WFO-01, WFO-05).
     */
    public function tambahTim(string $hari, int $timId): void
    {
        if (! $this->periodeId) {
            Flux::toast(variant: 'danger', text: 'Pilih periode terlebih dahulu.');
            return;
        }

        if (! in_array($hari, self::HARI)) {
            return;
        }

        // WFO-05: validasi duplikat 1 tim hanya boleh 1x per hari per periode
        $sudahAda = JadwalWfo::where('periode_wfo_id', $this->periodeId)
            ->where('tim_id', $timId)
            ->where('hari', $hari)
            ->exists();

        if ($sudahAda) {
            Flux::toast(variant: 'danger', text: 'Tim ini sudah ada di hari tersebut.');
            return;
        }

        JadwalWfo::create([
            'periode_wfo_id' => $this->periodeId,
            'tim_id'         => $timId,
            'hari'           => $hari,
        ]);

        unset($this->gridData);
    }

    /**
     * Hapus tim dari hari tertentu — auto-save.
     */
    public function hapusTim(string $hari, int $timId): void
    {
        if (! $this->periodeId) {
            return;
        }

        JadwalWfo::where('periode_wfo_id', $this->periodeId)
            ->where('tim_id', $timId)
            ->where('hari', $hari)
            ->delete();

        unset($this->gridData);
    }

    public function updatedPeriodeId(): void
    {
        unset($this->gridData, $this->periodeDipilih);
    }
}; ?>

<div class="flex flex-col gap-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Jadwal WFO</flux:heading>
            <flux:text class="text-zinc-500">
                Atur pola WFO per tim per hari. Klik <strong>+</strong> untuk tambah tim, <strong>×</strong> untuk hapus. Perubahan tersimpan otomatis.
            </flux:text>
        </div>
        {{-- Pilih Periode --}}
        <div class="w-full sm:w-72">
            <flux:select wire:model.live="periodeId" label="Periode">
                <flux:select.option value="">— Pilih Periode —</flux:select.option>
                @foreach ($this->periodeOptions as $periode)
                    <flux:select.option :value="$periode->id">
                        {{ $periode->keterangan ?? $periode->tanggal_mulai->format('M Y') }}
                        {{ $periode->status === 'aktif' ? '(Aktif)' : '' }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    @if (! $this->periodeId)
        {{-- Belum pilih periode --}}
        <x-empty-state
            icon="calendar-days"
            title="Pilih periode WFO"
            description="Pilih periode dari dropdown di atas untuk melihat atau mengisi grid jadwal."
        />
    @else
        {{-- Info periode --}}
        @if ($this->periodeDipilih)
            <flux:callout
                variant="{{ $this->periodeDipilih->isAktif() ? 'success' : 'warning' }}"
                icon="{{ $this->periodeDipilih->isAktif() ? 'check-circle' : 'exclamation-triangle' }}"
            >
                <flux:callout.heading>
                    {{ $this->periodeDipilih->keterangan ?? 'Periode ini' }}
                    — {{ $this->periodeDipilih->tanggal_mulai->translatedFormat('d M Y') }}
                    s/d {{ $this->periodeDipilih->tanggal_selesai->translatedFormat('d M Y') }}
                </flux:callout.heading>
                @if (! $this->periodeDipilih->isAktif())
                    <flux:callout.text>Periode ini tidak aktif. Perubahan pola tidak akan mempengaruhi jadwal yang sudah ter-generate.</flux:callout.text>
                @endif
            </flux:callout>
        @endif

        {{-- GRID JADWAL WFO — Signature Element §3.4 --}}
        {{-- Desktop: 6 kolom side-by-side | Mobile: 1 kolom per hari (scroll vertikal) --}}
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
            @foreach (['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'] as $hari)
                @php $timDiHari = $this->gridData[$hari] ?? []; @endphp

                <flux:card class="flex flex-col gap-3 p-3 min-h-[140px]">
                    {{-- Header hari --}}
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            {{ ucfirst($hari) }}
                        </span>
                        <span class="text-xs text-zinc-400">{{ count($timDiHari) }} tim</span>
                    </div>

                    {{-- Chip-chip tim --}}
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($timDiHari as $tid => $namaTim)
                            <span class="inline-flex items-center gap-1 rounded-full bg-brand/10 text-brand text-xs font-medium px-2 py-0.5 border border-brand/20 shrink-0">
                                <span class="truncate max-w-[80px]" title="{{ $namaTim }}">{{ $namaTim }}</span>
                                <button
                                    wire:click="hapusTim('{{ $hari }}', {{ $tid }})"
                                    wire:loading.attr="disabled"
                                    wire:target="hapusTim('{{ $hari }}', {{ $tid }})"
                                    class="ml-0.5 text-brand/60 hover:text-red-500 transition-colors focus:outline-none flex-shrink-0"
                                    aria-label="Hapus {{ $namaTim }} dari {{ $hari }}"
                                >
                                    <svg class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor">
                                        <path d="M6 4.586L1.707.293A1 1 0 00.293 1.707L4.586 6 .293 10.293a1 1 0 101.414 1.414L6 7.414l4.293 4.293a1 1 0 001.414-1.414L7.414 6l4.293-4.293A1 1 0 0010.293.293L6 4.586z"/>
                                    </svg>
                                </button>
                            </span>
                        @endforeach
                    </div>

                    {{-- Tombol + dengan searchable dropdown (Alpine.js, pengganti Searchable Select Pro §3.6) --}}
                    <div
                        x-data="{
                            open: false,
                            q: '',
                            get filtered() {
                                if (!this.q) return @js($this->semuaTim->toArray());
                                return @js($this->semuaTim->toArray()).filter(t =>
                                    t.nama_tim.toLowerCase().includes(this.q.toLowerCase())
                                );
                            }
                        }"
                        @click.outside="open = false; q = ''"
                        class="relative"
                    >
                        <button
                            @click="open = !open"
                            class="flex items-center gap-1 w-full justify-center rounded-md border border-dashed border-zinc-300 dark:border-zinc-600 px-2 py-1.5 text-xs text-zinc-400 hover:border-brand hover:text-brand transition-colors focus:outline-none focus:ring-2 focus:ring-brand"
                            aria-label="Tambah tim ke {{ $hari }}"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            Tambah Tim
                        </button>

                        <div
                            x-show="open"
                            x-transition
                            class="absolute bottom-full mb-1 left-0 z-50 w-52 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg"
                        >
                            <div class="p-2 border-b border-zinc-100 dark:border-zinc-700">
                                <input
                                    x-model="q"
                                    type="text"
                                    placeholder="Cari tim…"
                                    class="w-full rounded-md border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-brand"
                                    @click.stop
                                />
                            </div>
                            <ul class="max-h-40 overflow-y-auto py-1">
                                <template x-for="tim in filtered" :key="tim.id">
                                    <li>
                                        <button
                                            @click="$wire.tambahTim('{{ $hari }}', tim.id); open = false; q = ''"
                                            class="w-full text-left px-3 py-1.5 text-xs hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors truncate"
                                            x-text="tim.nama_tim"
                                        ></button>
                                    </li>
                                </template>
                                <li x-show="filtered.length === 0" class="px-3 py-2 text-xs text-zinc-400">
                                    Tidak ada tim ditemukan.
                                </li>
                            </ul>
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>

        {{-- Hint responsif mobile --}}
        <p class="text-xs text-zinc-400 text-center md:hidden">
            Scroll ke bawah untuk melihat semua hari.
        </p>
    @endif
</div>
