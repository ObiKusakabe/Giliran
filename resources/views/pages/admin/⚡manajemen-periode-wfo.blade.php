<?php

use App\Models\PeriodeWfo;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Periode WFO')] #[Layout('layouts.admin')] class extends Component {

    public ?int $editId            = null;
    public string $tanggal_mulai   = '';
    public string $tanggal_selesai = '';
    public string $keterangan      = '';
    public ?int $aktifkanId        = null;

    public string $sortField = 'tanggal_mulai';
    public string $sortDir   = 'desc';

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDir   = 'asc';
        }
        unset($this->periodeList);
    }

    #[Computed]
    public function periodeList()
    {
        return PeriodeWfo::orderBy($this->sortField, $this->sortDir)->get();
    }

    #[Computed]
    public function totalPeriode(): int
    {
        return PeriodeWfo::count();
    }

    #[Computed]
    public function periodeAktif(): ?PeriodeWfo
    {
        return PeriodeWfo::where('status', 'aktif')->first();
    }

    #[Computed]
    public function totalNonaktif(): int
    {
        return PeriodeWfo::where('status', 'nonaktif')->count();
    }

    public function bukaEdit(int $id): void
    {
        $this->resetValidation();
        $periode = PeriodeWfo::findOrFail($id);
        $this->editId          = $periode->id;
        $this->tanggal_mulai   = $periode->tanggal_mulai->format('Y-m-d');
        $this->tanggal_selesai = $periode->tanggal_selesai->format('Y-m-d');
        $this->keterangan      = $periode->keterangan ?? '';
        $this->modal('form-periode')->show();
    }

    public function simpan(): void
    {
        $this->validate([
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'keterangan'      => 'nullable|string|max:150',
        ]);

        if ($this->editId) {
            $periode = PeriodeWfo::findOrFail($this->editId);
            $periode->update([
                'tanggal_mulai'   => $this->tanggal_mulai,
                'tanggal_selesai' => $this->tanggal_selesai,
                'keterangan'      => $this->keterangan ?: null,
            ]);

            Flux::toast(variant: 'success', text: 'Periode WFO berhasil diperbarui.');
        } else {
            PeriodeWfo::create([
                'tanggal_mulai'   => $this->tanggal_mulai,
                'tanggal_selesai' => $this->tanggal_selesai,
                'keterangan'      => $this->keterangan ?: null,
                'status'          => 'nonaktif',
            ]);

            Flux::toast(variant: 'success', text: 'Periode WFO berhasil dibuat.');
        }

        $this->modal('form-periode')->close();
        $this->resetForm();
        unset($this->periodeList, $this->totalPeriode, $this->totalNonaktif, $this->periodeAktif);
    }

    public function konfirmasiAktifkan(int $id): void
    {
        $this->aktifkanId = $id;
        $this->modal('konfirmasi-aktifkan')->show();
    }

    public function aktifkan(): void
    {
        if (! $this->aktifkanId) {
            return;
        }

        PeriodeWfo::where('id', '!=', $this->aktifkanId)
            ->where('status', 'aktif')
            ->update(['status' => 'nonaktif']);

        PeriodeWfo::findOrFail($this->aktifkanId)->update(['status' => 'aktif']);

        Flux::toast(variant: 'success', text: 'Periode WFO berhasil diaktifkan.');
        $this->modal('konfirmasi-aktifkan')->close();
        $this->aktifkanId = null;
        unset($this->periodeList, $this->periodeAktif, $this->totalNonaktif);
    }

    private function resetForm(): void
    {
        $this->editId          = null;
        $this->tanggal_mulai   = '';
        $this->tanggal_selesai = '';
        $this->keterangan      = '';
        $this->resetValidation();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Periode WFO</flux:heading>
            <flux:text class="text-zinc-500">Kelola periode rotasi WFO - 1 periode aktif menjadi acuan penjadwalan.</flux:text>
        </div>
        <flux:modal.trigger name="form-periode">
            <flux:button variant="primary" icon="plus">Buat Periode Baru</flux:button>
        </flux:modal.trigger>
    </div>

    {{-- Quick Info Cards with Watermark Icons --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="relative z-10 pr-6">
                <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Total Periode</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                    {{ $this->totalPeriode }}
                </flux:heading>
            </div>
            <flux:icon icon="calendar-days" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-blue-500/10 dark:text-blue-400/10 pointer-events-none" />
        </flux:card>

        <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="relative z-10 pr-6">
                <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Periode Aktif</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-emerald-600 dark:text-emerald-400" title="{{ $this->periodeAktif ? $this->periodeAktif->keterangan : 'Tidak Ada' }}">
                    {{ $this->periodeAktif ? $this->periodeAktif->keterangan : 'Tidak Ada' }}
                </flux:heading>
            </div>
            <flux:icon icon="check-circle" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-emerald-500/10 dark:text-emerald-400/10 pointer-events-none" />
        </flux:card>

        <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="relative z-10 pr-6">
                <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Nonaktif</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-zinc-600 dark:text-zinc-400">
                    {{ $this->totalNonaktif }}
                </flux:heading>
            </div>
            <flux:icon icon="clock" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-zinc-500/10 dark:text-zinc-400/10 pointer-events-none" />
        </flux:card>

        <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="relative z-10 pr-6">
                <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Durasi Aktif</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-purple-600 dark:text-purple-400">
                    @if ($this->periodeAktif)
                        {{ $this->periodeAktif->tanggal_mulai->diffInDays($this->periodeAktif->tanggal_selesai) + 1 }} Hari
                    @else
                        —
                    @endif
                </flux:heading>
            </div>
            <flux:icon icon="sparkles" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-purple-500/10 dark:text-purple-400/10 pointer-events-none" />
        </flux:card>
    </div>

    <flux:card class="p-0 overflow-visible table-sticky-card border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs">
        <div class="px-5">
            <flux:table>
                <flux:table.columns class="bg-white dark:bg-zinc-900">
                    <flux:table.column>Keterangan</flux:table.column>
                    <flux:table.column class="cursor-pointer select-none" wire:click="sortBy('tanggal_mulai')">
                        <span class="inline-flex items-center gap-1">Tanggal Mulai
                            @if ($sortField === 'tanggal_mulai')
                                <flux:icon icon="{{ $sortDir === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-3.5 text-brand" />
                            @endif
                        </span>
                    </flux:table.column>
                    <flux:table.column class="cursor-pointer select-none" wire:click="sortBy('tanggal_selesai')">
                        <span class="inline-flex items-center gap-1">Tanggal Selesai
                            @if ($sortField === 'tanggal_selesai')
                                <flux:icon icon="{{ $sortDir === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-3.5 text-brand" />
                            @endif
                        </span>
                    </flux:table.column>
                    <flux:table.column align="center" class="cursor-pointer select-none" wire:click="sortBy('status')">
                        <span class="inline-flex items-center justify-center gap-1">Status
                            @if ($sortField === 'status')
                                <flux:icon icon="{{ $sortDir === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-3.5 text-brand" />
                            @endif
                        </span>
                    </flux:table.column>
                    <flux:table.column align="end">Aksi</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->periodeList as $periode)
                        <flux:table.row class="{{ $periode->isAktif() ? 'bg-emerald-50/50 dark:bg-emerald-950/20' : '' }}">
                            <flux:table.cell class="font-medium text-zinc-900 dark:text-zinc-100">{{ $periode->keterangan ?? '—' }}</flux:table.cell>
                            <flux:table.cell class="text-zinc-600 dark:text-zinc-400">{{ $periode->tanggal_mulai->translatedFormat('d M Y') }}</flux:table.cell>
                            <flux:table.cell class="text-zinc-600 dark:text-zinc-400">{{ $periode->tanggal_selesai->translatedFormat('d M Y') }}</flux:table.cell>
                            <flux:table.cell align="center"><x-status-badge :status="$periode->status" /></flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="flex items-center justify-end gap-1.5">
                                    <flux:button 
                                        size="xs" 
                                        variant="ghost" 
                                        icon="pencil" 
                                        wire:click="bukaEdit({{ $periode->id }})" 
                                        wire:loading.attr="disabled" 
                                        wire:target="bukaEdit({{ $periode->id }})"
                                        title="Edit Periode"
                                    >
                                        <span wire:loading.remove wire:target="bukaEdit({{ $periode->id }})">Edit</span>
                                        <span wire:loading wire:target="bukaEdit({{ $periode->id }})">
                                            <flux:icon icon="arrow-path" class="size-4 animate-spin" />
                                        </span>
                                    </flux:button>
                                    @if (! $periode->isAktif())
                                        <flux:button 
                                            size="xs" 
                                            variant="primary" 
                                            wire:click="konfirmasiAktifkan({{ $periode->id }})"
                                            wire:loading.attr="disabled" 
                                            wire:target="konfirmasiAktifkan({{ $periode->id }})"
                                        >
                                            <span wire:loading.remove wire:target="konfirmasiAktifkan({{ $periode->id }})">Aktifkan</span>
                                            <span wire:loading wire:target="konfirmasiAktifkan({{ $periode->id }})">
                                                <flux:icon icon="arrow-path" class="size-4 animate-spin" />
                                            </span>
                                        </flux:button>
                                    @else
                                        <span class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold px-2">Sedang Aktif</span>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="py-4">
                                <x-empty-state icon="calendar-days" title="Belum ada periode WFO" description="Buat periode WFO pertama untuk mulai mengatur jadwal." action-label="Buat Periode Baru" action-wire="bukaTambah" />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </flux:card>

    <flux:modal name="form-periode" class="max-w-md" 
        x-on:close="$wire.editId = null; $wire.tanggal_mulai = ''; $wire.tanggal_selesai = ''; $wire.keterangan = '';">
        <div class="flex flex-col gap-5 p-1">
            <div>
                <flux:heading size="lg">{{ $editId ? 'Edit Periode WFO' : 'Buat Periode WFO Baru' }}</flux:heading>
                <flux:text class="mt-1 text-zinc-500">
                    {{ $editId ? 'Perbarui tanggal atau keterangan periode WFO ini.' : 'Periode baru akan berstatus nonaktif. Aktifkan secara manual setelah dibuat.' }}
                </flux:text>
            </div>
            <form wire:submit="simpan" class="flex flex-col gap-4">
                <x-date-range-picker
                    name-from="tanggal_mulai"
                    name-to="tanggal_selesai"
                    label-from="Rentang Periode"
                    label-to=""
                    wire-from="tanggal_mulai"
                    wire-to="tanggal_selesai"
                    :value-from="$tanggal_mulai"
                    :value-to="$tanggal_selesai"
                    :required="true"
                />
                <flux:input wire:model="keterangan" label="Keterangan" placeholder="cth. PKL Agustus–September 2026" />
                <div class="flex justify-end gap-2 pt-2">
                    <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="simpan">
                        <span wire:loading.remove wire:target="simpan">{{ $editId ? 'Perbarui' : 'Simpan' }}</span>
                        <span wire:loading wire:target="simpan">Menyimpan…</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal name="konfirmasi-aktifkan" class="max-w-sm">
        <div class="flex flex-col gap-4 p-1">
            <div>
                <flux:heading size="lg">Aktifkan Periode Ini?</flux:heading>
                <flux:text class="mt-1 text-zinc-500">Periode aktif saat ini akan dinonaktifkan otomatis. Jadwal WFO yang sudah ter-generate tidak berubah.</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button variant="primary" wire:click="aktifkan" wire:loading.attr="disabled" wire:target="aktifkan">Ya, Aktifkan</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
