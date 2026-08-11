<?php

use App\Models\Ruangan;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Ruangan')] #[Layout('layouts.admin')] class extends Component {
    use WithPagination;

    public string $search       = '';
    public string $filterStatus = '';

    // Form fields
    public ?int $editingId      = null;
    public string $nama_ruangan = '';
    public int|string $kapasitas = '';
    public string $status       = 'tersedia';

    // ID ruangan yang mau dihapus
    public ?int $hapusId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function ruanganList()
    {
        return Ruangan::query()
            ->when($this->search, fn ($q) => $q->where('nama_ruangan', 'like', "%{$this->search}%"))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->orderBy('nama_ruangan')
            ->paginate(10);
    }

    public function bukaFormTambah(): void
    {
        $this->resetForm();
        $this->modal('form-ruangan')->show();
    }

    public function bukaFormEdit(int $id): void
    {
        $ruangan              = Ruangan::findOrFail($id);
        $this->editingId      = $id;
        $this->nama_ruangan   = $ruangan->nama_ruangan;
        $this->kapasitas      = $ruangan->kapasitas;
        $this->status         = $ruangan->status;
        $this->modal('form-ruangan')->show();
    }

    public function simpan(): void
    {
        $this->validate([
            'nama_ruangan' => 'required|string|max:100',
            'kapasitas'    => 'required|integer|min:1|max:9999',
            'status'       => 'required|in:tersedia,tidak_tersedia',
        ]);

        $data = [
            'nama_ruangan' => $this->nama_ruangan,
            'kapasitas'    => (int) $this->kapasitas,
            'status'       => $this->status,
        ];

        if ($this->editingId) {
            Ruangan::findOrFail($this->editingId)->update($data);
            Flux::toast(variant: 'success', text: 'Ruangan berhasil diperbarui.');
        } else {
            Ruangan::create($data);
            Flux::toast(variant: 'success', text: 'Ruangan berhasil ditambahkan.');
        }

        $this->modal('form-ruangan')->close();
        $this->resetForm();
        unset($this->ruanganList);
    }

    public function konfirmasiHapus(int $id): void
    {
        $this->hapusId = $id;
        $this->modal('hapus-ruangan')->show();
    }

    public function hapus(): void
    {
        if (! $this->hapusId) {
            return;
        }

        Ruangan::findOrFail($this->hapusId)->delete(); // soft delete
        Flux::toast(variant: 'success', text: 'Ruangan berhasil dihapus.');
        $this->modal('hapus-ruangan')->close();
        $this->hapusId = null;
        unset($this->ruanganList);
    }

    private function resetForm(): void
    {
        $this->editingId    = null;
        $this->nama_ruangan = '';
        $this->kapasitas    = '';
        $this->status       = 'tersedia';
        $this->resetValidation();
    }
}; ?>

<div class="flex flex-col gap-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Ruangan</flux:heading>
            <flux:text class="text-zinc-500">Kelola data ruangan meeting dan kelas.</flux:text>
        </div>
        <flux:button variant="primary" wire:click="bukaFormTambah" icon="plus">
            Tambah Ruangan
        </flux:button>
    </div>

    {{-- Search + Filter --}}
    <div class="flex gap-3">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Cari nama ruangan…"
                icon="magnifying-glass"
                clearable
            />
        </div>
        <flux:select wire:model.live="filterStatus" class="w-44">
            <flux:select.option value="">Semua Status</flux:select.option>
            <flux:select.option value="tersedia">Tersedia</flux:select.option>
            <flux:select.option value="tidak_tersedia">Tidak Tersedia</flux:select.option>
        </flux:select>
    </div>

    {{-- Tabel --}}
    <flux:card class="p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">Nama Ruangan</th>
                        <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">Kapasitas</th>
                        <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">Status</th>
                        <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->ruanganList as $ruangan)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $ruangan->nama_ruangan }}
                            </td>
                            <td class="px-4 py-3 text-center text-zinc-500">
                                {{ $ruangan->kapasitas }} orang
                            </td>
                            <td class="px-4 py-3 text-center">
                                <x-status-badge :status="$ruangan->status" />
                            </td>
                            <td class="px-4 py-3 text-right">
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil" wire:click="bukaFormEdit({{ $ruangan->id }})">
                                            Edit
                                        </flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger" wire:click="konfirmasiHapus({{ $ruangan->id }})">
                                            Hapus
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-2">
                                <x-empty-state
                                    icon="home-modern"
                                    title="Belum ada ruangan"
                                    description="Tambahkan ruangan pertama untuk bisa dialokasikan ke tim WFO."
                                    action-label="Tambah Ruangan"
                                    action-wire="bukaFormTambah"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->ruanganList->hasPages())
            <div class="px-4 py-3 border-t border-zinc-100 dark:border-zinc-800">
                {{ $this->ruanganList->links() }}
            </div>
        @endif
    </flux:card>

    {{-- Modal Form Tambah/Edit --}}
    <flux:modal name="form-ruangan" class="max-w-md">
        <div class="flex flex-col gap-5 p-1">
            <flux:heading size="lg">{{ $editingId ? 'Edit Ruangan' : 'Tambah Ruangan' }}</flux:heading>
            <form wire:submit="simpan" class="flex flex-col gap-4">
                <flux:input
                    wire:model.live="nama_ruangan"
                    label="Nama Ruangan"
                    placeholder="cth. Ruang Meeting A"
                    required
                />
                <flux:input
                    wire:model.live="kapasitas"
                    label="Kapasitas (orang)"
                    type="number"
                    min="1"
                    placeholder="cth. 10"
                    required
                />
                <flux:select wire:model="status" label="Status" required>
                    <flux:select.option value="tersedia">Tersedia</flux:select.option>
                    <flux:select.option value="tidak_tersedia">Tidak Tersedia</flux:select.option>
                </flux:select>
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

    {{-- Modal Konfirmasi Hapus --}}
    <flux:modal name="hapus-ruangan" class="max-w-sm">
        <div class="flex flex-col gap-4 p-1">
            <div>
                <flux:heading size="lg">Hapus Ruangan</flux:heading>
                <flux:text class="mt-1 text-zinc-500">
                    Data ruangan akan dihapus. Riwayat alokasi yang sudah ada tidak akan terpengaruh.
                </flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button
                    variant="danger"
                    wire:click="hapus"
                    wire:loading.attr="disabled"
                    wire:target="hapus"
                >
                    Ya, Hapus
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
