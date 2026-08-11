<?php

use App\Models\PeriodeWfo;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Periode WFO')] #[Layout('layouts.admin')] class extends Component {

    // Form buat periode baru
    public string $tanggal_mulai   = '';
    public string $tanggal_selesai = '';
    public string $keterangan      = '';

    // ID periode yang mau diaktifkan
    public ?int $aktifkanId = null;

    #[Computed]
    public function periodeList()
    {
        return PeriodeWfo::orderByDesc('tanggal_mulai')->get();
    }

    public function bukaTambah(): void
    {
        $this->resetForm();
        $this->modal('form-periode')->show();
    }

    public function simpan(): void
    {
        $this->validate([
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'keterangan'      => 'nullable|string|max:150',
        ]);

        PeriodeWfo::create([
            'tanggal_mulai'   => $this->tanggal_mulai,
            'tanggal_selesai' => $this->tanggal_selesai,
            'keterangan'      => $this->keterangan ?: null,
            'status'          => 'nonaktif', // baru dibuat selalu nonaktif dulu
        ]);

        Flux::toast(variant: 'success', text: 'Periode WFO berhasil dibuat.');
        $this->modal('form-periode')->close();
        $this->resetForm();
        unset($this->periodeList);
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

        // PWF-02: nonaktifkan semua periode lain terlebih dahulu
        PeriodeWfo::where('id', '!=', $this->aktifkanId)
            ->where('status', 'aktif')
            ->update(['status' => 'nonaktif']);

        PeriodeWfo::findOrFail($this->aktifkanId)->update(['status' => 'aktif']);

        Flux::toast(variant: 'success', text: 'Periode WFO berhasil diaktifkan.');
        $this->modal('konfirmasi-aktifkan')->close();
        $this->aktifkanId = null;
        unset($this->periodeList);
    }

    private function resetForm(): void
    {
        $this->tanggal_mulai   = '';
        $this->tanggal_selesai = '';
        $this->keterangan      = '';
        $this->resetValidation();
    }
}; ?>

<div class="flex flex-col gap-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Periode WFO</flux:heading>
            <flux:text class="text-zinc-500">Kelola periode WFO — hanya 1 periode yang bisa aktif sekaligus.</flux:text>
        </div>
        <flux:button variant="primary" wire:click="bukaTambah" icon="plus">
            Buat Periode Baru
        </flux:button>
    </div>

    {{-- Tabel --}}
    <flux:card class="p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">Keterangan</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">Tanggal Mulai</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">Tanggal Selesai</th>
                        <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">Status</th>
                        <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->periodeList as $periode)
                        {{-- Highlight baris aktif --}}
                        <tr class="transition-colors {{ $periode->isAktif() ? 'bg-emerald-50 dark:bg-emerald-900/10' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}">
                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $periode->keterangan ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">
                                {{ $periode->tanggal_mulai->translatedFormat('d M Y') }}
                            </td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">
                                {{ $periode->tanggal_selesai->translatedFormat('d M Y') }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <x-status-badge :status="$periode->status" />
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if (! $periode->isAktif())
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        wire:click="konfirmasiAktifkan({{ $periode->id }})"
                                    >
                                        Aktifkan
                                    </flux:button>
                                @else
                                    <span class="text-xs text-emerald-600 font-medium px-2">Sedang Aktif</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-2">
                                <x-empty-state
                                    icon="calendar-days"
                                    title="Belum ada periode WFO"
                                    description="Buat periode WFO pertama untuk mulai mengatur jadwal."
                                    action-label="Buat Periode Baru"
                                    action-wire="bukaTambah"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    {{-- Modal Form Buat Periode --}}
    <flux:modal name="form-periode" class="max-w-md">
        <div class="flex flex-col gap-5 p-1">
            <div>
                <flux:heading size="lg">Buat Periode WFO Baru</flux:heading>
                <flux:text class="mt-1 text-zinc-500">Periode baru akan berstatus nonaktif. Aktifkan secara manual setelah dibuat.</flux:text>
            </div>
            <form wire:submit="simpan" class="flex flex-col gap-4">
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                        Tanggal Mulai
                    </label>
                    <input
                        type="date"
                        wire:model="tanggal_mulai"
                        class="w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent"
                        required
                    />
                    @error('tanggal_mulai')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                        Tanggal Selesai
                    </label>
                    <input
                        type="date"
                        wire:model="tanggal_selesai"
                        class="w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent"
                        required
                    />
                    @error('tanggal_selesai')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <flux:input
                    wire:model="keterangan"
                    label="Keterangan"
                    placeholder="cth. PKL Agustus–September 2026"
                />

                <div class="flex justify-end gap-2 pt-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button
                        type="submit"
                        variant="primary"
                        wire:loading.attr="disabled"
                        wire:target="simpan"
                    >
                        <span wire:loading.remove wire:target="simpan">Simpan</span>
                        <span wire:loading wire:target="simpan">Menyimpan…</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Modal Konfirmasi Aktifkan --}}
    <flux:modal name="konfirmasi-aktifkan" class="max-w-sm">
        <div class="flex flex-col gap-4 p-1">
            <div>
                <flux:heading size="lg">Aktifkan Periode Ini?</flux:heading>
                <flux:text class="mt-1 text-zinc-500">
                    Periode aktif saat ini akan dinonaktifkan otomatis. Jadwal WFO yang sudah ter-generate tidak berubah.
                </flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button
                    variant="primary"
                    wire:click="aktifkan"
                    wire:loading.attr="disabled"
                    wire:target="aktifkan"
                >
                    Ya, Aktifkan
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
