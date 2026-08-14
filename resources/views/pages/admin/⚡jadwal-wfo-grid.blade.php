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

    public const HARI = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'];

    public function mount(): void
    {
        $aktif           = PeriodeWfo::where('status', 'aktif')->first();
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
     * Grid sebagai array flat untuk Alpine:
     * [{ id, hari, tim_id, nama_tim }, ...]
     */
    #[Computed]
    public function gridRows(): array
    {
        if (! $this->periodeId) {
            return [];
        }

        return JadwalWfo::with('tim')
            ->where('periode_wfo_id', $this->periodeId)
            ->get()
            ->map(fn ($row) => [
                'id'       => $row->id,
                'hari'     => $row->hari,
                'tim_id'   => $row->tim_id,
                'nama_tim' => $row->tim->nama_tim,
            ])
            ->toArray();
    }

    public function tambahTim(string $hari, int $timId): void
    {
        if (! $this->periodeId) {
            Flux::toast(variant: 'danger', text: 'Pilih periode terlebih dahulu.');
            return;
        }

        if (! in_array($hari, self::HARI)) {
            return;
        }

        $sudahAda = JadwalWfo::where('periode_wfo_id', $this->periodeId)
            ->where('tim_id', $timId)
            ->where('hari', $hari)
            ->exists();

        if ($sudahAda) {
            // Seharusnya tidak sampai sini karena sudah difilter di client
            Flux::toast(variant: 'danger', text: 'Tim ini sudah ada di hari tersebut.');
            return;
        }

        $row = JadwalWfo::create([
            'periode_wfo_id' => $this->periodeId,
            'tim_id'         => $timId,
            'hari'           => $hari,
        ]);

        $tim = Tim::find($timId);

        // Kembalikan row baru ke Alpine supaya state client sync
        $this->dispatch('row-ditambah', row: [
            'id'       => $row->id,
            'hari'     => $hari,
            'tim_id'   => $timId,
            'nama_tim' => $tim?->nama_tim ?? '',
        ]);

        unset($this->gridRows);
    }

    public function hapusTim(int $rowId): void
    {
        if (! $this->periodeId) {
            return;
        }

        JadwalWfo::where('id', $rowId)
            ->where(fn ($q) => $q->whereHas('periodeWfo', fn ($q2) => $q2->where('id', $this->periodeId)))
            ->delete();

        $this->dispatch('row-dihapus', rowId: $rowId);
        unset($this->gridRows);
    }

    public function pindahTim(int $rowId, string $hariTujuan): void
    {
        if (! $this->periodeId || $hariTujuan === '__trash') {
            if ($hariTujuan === '__trash') {
                $this->hapusTim($rowId);
            }
            return;
        }

        if (! in_array($hariTujuan, self::HARI)) {
            return;
        }

        $row = JadwalWfo::find($rowId);

        if (! $row || $row->hari === $hariTujuan) {
            return;
        }

        // Cek duplikat di hari tujuan
        $sudahAda = JadwalWfo::where('periode_wfo_id', $this->periodeId)
            ->where('tim_id', $row->tim_id)
            ->where('hari', $hariTujuan)
            ->exists();

        if ($sudahAda) {
            // Revert optimistic update di client
            $this->dispatch('revert-pindah', rowId: $rowId, hariAsal: $row->hari);
            Flux::toast(variant: 'danger', text: 'Tim ini sudah ada di hari '.$hariTujuan.'.');
            return;
        }

        $row->update(['hari' => $hariTujuan]);
        unset($this->gridRows);
    }

    public function updatedPeriodeId(): void
    {
        unset($this->gridRows, $this->periodeDipilih);
    }
}; ?>

<div
    x-data="{
        // State grid di client — array flat [{ id, hari, tim_id, nama_tim }]
        rows: @js($this->gridRows),
        semuaTim: @js($this->semuaTim->toArray()),

        dragging: null,    // { rowId, hariAsal, timId, namaTim }
        overHari: null,
        overTrash: false,
        loadingRowIds: [], // row yang sedang menunggu server (loading X)

        // Helper: tim di hari tertentu
        timDiHari(hari) {
            return this.rows.filter(r => r.hari === hari);
        },

        // Helper: tim yang belum ada di hari tertentu (untuk dropdown filter)
        timBelumDiHari(hari) {
            const sudahAda = this.timDiHari(hari).map(r => r.tim_id);
            return this.semuaTim.filter(t => !sudahAda.includes(t.id));
        },

        // Tambah tim: optimistic update langsung, server di background
        tambahTimOptimistic(hari, timId, namaTim) {
            const tempId = -Date.now(); // ID sementara negatif
            this.rows.push({ id: tempId, hari, tim_id: timId, nama_tim: namaTim });
            $wire.tambahTim(hari, timId);
        },

        // Hapus tim: optimistic — sembunyikan dulu, tunggu server
        hapusTimOptimistic(rowId) {
            this.loadingRowIds.push(rowId);
            $wire.hapusTim(rowId);
        },

        // Drag start
        startDrag(row) {
            this.dragging = { rowId: row.id, hariAsal: row.hari, timId: row.tim_id, namaTim: row.nama_tim };
        },

        // Drop ke hari: optimistic pindah dulu
        dropKeHari(hariTujuan) {
            if (!this.dragging || this.dragging.hariAsal === hariTujuan) {
                this.dragging = null; this.overHari = null;
                return;
            }
            const rowId = this.dragging.rowId;
            const hariAsal = this.dragging.hariAsal;

            // Optimistic: update hari di client state
            const idx = this.rows.findIndex(r => r.id === rowId);
            if (idx !== -1) this.rows[idx].hari = hariTujuan;

            this.loadingRowIds.push(rowId);
            $wire.pindahTim(rowId, hariTujuan);

            this.dragging = null; this.overHari = null;
        },

        // Drop ke trash: optimistic hapus
        dropKeTrash() {
            if (!this.dragging) return;
            const rowId = this.dragging.rowId;
            this.rows = this.rows.filter(r => r.id !== rowId);
            $wire.hapusTim(rowId);
            this.dragging = null; this.overTrash = false; this.overHari = null;
        },

        isLoading(rowId) {
            return this.loadingRowIds.includes(rowId);
        }
    }"
    @row-ditambah.window="
        // Ganti tempId negatif dengan ID asli dari server
        const row = $event.detail.row;
        const tempIdx = rows.findIndex(r => r.tim_id === row.tim_id && r.hari === row.hari && r.id < 0);
        if (tempIdx !== -1) rows[tempIdx].id = row.id;
        else rows.push(row);
    "
    @row-dihapus.window="
        rows = rows.filter(r => r.id !== $event.detail.rowId);
        loadingRowIds = loadingRowIds.filter(id => id !== $event.detail.rowId);
    "
    @revert-pindah.window="
        // Server tolak pindah — kembalikan ke hari asal
        const { rowId, hariAsal } = $event.detail;
        const idx = rows.findIndex(r => r.id === rowId);
        if (idx !== -1) rows[idx].hari = hariAsal;
        loadingRowIds = loadingRowIds.filter(id => id !== rowId);
    "
    class="flex flex-col gap-6"
>
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Jadwal WFO</flux:heading>
            <flux:text class="text-zinc-500">
                Drag chip untuk pindah hari, drop ke
                <svg class="inline h-3.5 w-3.5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                untuk hapus. Perubahan tersimpan otomatis.
            </flux:text>
        </div>
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
        <x-empty-state icon="calendar-days" title="Pilih periode WFO" description="Pilih periode dari dropdown di atas." />
    @else
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

        {{-- Grid 6 kolom --}}
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
            @foreach (['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'] as $hari)
                <flux:card
                    class="flex flex-col gap-3 p-3 min-h-[140px] transition-colors"
                    :class="{
                        'ring-2 ring-brand ring-offset-1 ring-offset-zinc-900 bg-brand/5': overHari === '{{ $hari }}' && dragging && dragging.hariAsal !== '{{ $hari }}',
                    }"
                    @dragover.prevent="overHari = '{{ $hari }}'"
                    @dragleave="overHari = null"
                    @drop.prevent="dropKeHari('{{ $hari }}')"
                >
                    {{-- Header hari --}}
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            {{ ucfirst($hari) }}
                        </span>
                        <span class="text-xs text-zinc-400" x-text="timDiHari('{{ $hari }}').length + ' tim'"></span>
                    </div>

                    {{-- Chip tim — Alpine rendered, draggable --}}
                    <div class="flex flex-wrap gap-1.5 items-start content-start">
                        <template x-for="row in timDiHari('{{ $hari }}')" :key="row.id">
                            <div style="display:contents">
                                <span
                                    draggable="true"
                                    @dragstart="startDrag(row)"
                                    @dragend="dragging = null; overHari = null; overTrash = false"
                                    class="inline-flex items-center gap-1 rounded-full bg-brand/10 text-brand text-xs font-medium px-2 py-0.5 border border-brand/20 cursor-grab"
                                    style="flex-shrink:0; width:auto; max-width:100%;"
                                    :class="dragging && dragging.rowId === row.id ? 'opacity-40 !cursor-grabbing' : ''"
                                >
                                <span class="truncate max-w-[80px]" x-text="row.nama_tim" :title="row.nama_tim"></span>

                                {{-- Tombol X: loading spinner saat server processing --}}
                                <button
                                    @click.stop="hapusTimOptimistic(row.id)"
                                    :disabled="isLoading(row.id)"
                                    class="ml-0.5 flex-shrink-0 transition-colors focus:outline-none"
                                    :class="isLoading(row.id) ? 'text-brand/30 cursor-wait' : 'text-brand/60 hover:text-red-500'"
                                >
                                    {{-- Spinner saat loading --}}
                                    <svg x-show="isLoading(row.id)" class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    {{-- X normal --}}
                                    <svg x-show="!isLoading(row.id)" class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor">
                                        <path d="M6 4.586L1.707.293A1 1 0 00.293 1.707L4.586 6 .293 10.293a1 1 0 101.414 1.414L6 7.414l4.293 4.293a1 1 0 001.414-1.414L7.414 6l4.293-4.293A1 1 0 0010.293.293L6 4.586z"/>
                                    </svg>
                                </button>
                                </span>
                            </div>
                        </template>
                    </div>

                    {{-- Dropdown tambah tim — hanya tampilkan yang belum ada di hari ini --}}
                    <div
                        x-data="{ open: false, q: '' }"
                        @click.outside="open = false; q = ''"
                        class="relative"
                    >
                        <button
                            @click="open = !open"
                            class="flex items-center gap-1 w-full justify-center rounded-md border border-dashed border-zinc-300 dark:border-zinc-600 px-2 py-1.5 text-xs text-zinc-400 hover:border-brand hover:text-brand transition-colors focus:outline-none focus:ring-2 focus:ring-brand"
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
                                <input x-model="q" type="text" placeholder="Cari tim…" @click.stop
                                    class="w-full rounded-md border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-brand" />
                            </div>
                            <ul class="max-h-40 overflow-y-auto py-1">
                                {{-- Filter: tampilkan hanya tim yang belum ada di hari ini --}}
                                <template x-for="tim in timBelumDiHari('{{ $hari }}').filter(t => !q || t.nama_tim.toLowerCase().includes(q.toLowerCase()))" :key="tim.id">
                                    <li>
                                        <button
                                            @click="tambahTimOptimistic('{{ $hari }}', tim.id, tim.nama_tim); open = false; q = ''"
                                            class="w-full text-left px-3 py-1.5 text-xs hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors truncate"
                                            x-text="tim.nama_tim"
                                        ></button>
                                    </li>
                                </template>
                                <li
                                    x-show="timBelumDiHari('{{ $hari }}').filter(t => !q || t.nama_tim.toLowerCase().includes(q.toLowerCase())).length === 0"
                                    class="px-3 py-2 text-xs text-zinc-400"
                                >
                                    <span x-show="timBelumDiHari('{{ $hari }}').length === 0">Semua tim sudah ada di hari ini.</span>
                                    <span x-show="timBelumDiHari('{{ $hari }}').length > 0 && q">Tidak ada tim ditemukan.</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>

        {{-- Trash zone --}}
        <div
            x-show="dragging !== null"
            x-transition
            @dragover.prevent="overTrash = true"
            @dragleave="overTrash = false"
            @drop.prevent="dropKeTrash()"
            :class="overTrash
                ? 'border-red-400 bg-red-500/20 text-red-400'
                : 'border-zinc-600 bg-zinc-800/60 text-zinc-500'"
            class="flex items-center justify-center gap-2 rounded-xl border-2 border-dashed py-5 transition-colors"
        >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
            <span class="text-sm font-medium">
                <span x-show="overTrash" x-text="(dragging?.namaTim ?? '') + ' — lepaskan untuk hapus'"></span>
                <span x-show="!overTrash">Drop di sini untuk hapus</span>
            </span>
        </div>

        <p class="text-xs text-zinc-400 text-center md:hidden">Scroll ke bawah untuk melihat semua hari.</p>
    @endif
</div>
