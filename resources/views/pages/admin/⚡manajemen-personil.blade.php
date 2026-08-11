<?php

use App\Models\Personil;
use App\Models\Tim;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Personil')] #[Layout('layouts.admin')] class extends Component {
    use WithPagination;

    public string $search       = '';
    public string $filterStatus = '';

    // Form fields
    public ?int $editingId    = null;
    public int|string $tim_id = '';
    public string $nama       = '';
    public string $no_hp      = '';
    public string $status     = 'aktif';

    // ID personil yang mau dihapus
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
    public function personilList()
    {
        return Personil::query()
            ->with('tim')
            ->when($this->search, fn ($q) => $q->where('nama', 'like', "%{$this->search}%"))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->orderBy('nama')
            ->paginate(10);
    }

    #[Computed]
    public function timOptions()
    {
        return Tim::orderBy('nama_tim')->get(['id', 'nama_tim']);
    }

    public function bukaFormTambah(): void
    {
        $this->resetForm();
        $this->modal('form-personil')->show();
    }

    public function bukaFormEdit(int $id): void
    {
        $personil    = Personil::findOrFail($id);
        $this->editingId = $id;
        $this->tim_id    = $personil->tim_id;
        $this->nama      = $personil->nama;
        $this->no_hp     = $personil->no_hp ?? '';
        $this->status    = $personil->status;
        $this->modal('form-personil')->show();
    }

    public function simpan(): void
    {
        $this->validate([
            'tim_id' => 'required|exists:tim,id',
            'nama'   => 'required|string|max:150',
            'no_hp'  => 'nullable|string|max:20',
            'status' => 'required|in:aktif,nonaktif',
        ]);

        if ($this->editingId) {
            Personil::findOrFail($this->editingId)->update([
                'tim_id' => $this->tim_id,
                'nama'   => $this->nama,
                'no_hp'  => $this->no_hp ?: null,
                'status' => $this->status,
            ]);
            Flux::toast(variant: 'success', text: 'Personil berhasil diperbarui.');
        } else {
            Personil::create([
                'tim_id' => $this->tim_id,
                'nama'   => $this->nama,
                'no_hp'  => $this->no_hp ?: null,
                'status' => $this->status,
            ]);
            Flux::toast(variant: 'success', text: 'Personil berhasil ditambahkan.');
        }

        $this->modal('form-personil')->close();
        $this->resetForm();
        unset($this->personilList);
    }

    public function konfirmasiHapus(int $id): void
    {
        $this->hapusId = $id;
        $this->modal('hapus-personil')->show();
    }

    public function hapus(): void
    {
        if (! $this->hapusId) {
            return;
        }

        Personil::findOrFail($this->hapusId)->delete(); // soft delete
        Flux::toast(variant: 'success', text: 'Personil berhasil dihapus.');
        $this->modal('hapus-personil')->close();
        $this->hapusId = null;
        unset($this->personilList);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->tim_id    = '';
        $this->nama      = '';
        $this->no_hp     = '';
        $this->status    = 'aktif';
        $this->resetValidation();
    }
}; ?>

<div class="flex flex-col gap-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Personil</flux:heading>
            <flux:text class="text-zinc-500">Kelola data personil peserta PKL/magang.</flux:text>
        </div>
        <flux:button variant="primary" wire:click="bukaFormTambah" icon="plus">
            Tambah Personil
        </flux:button>
    </div>

    {{-- Search + Filter --}}
    <div class="flex gap-3">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Cari nama personil…"
                icon="magnifying-glass"
                clearable
            />
        </div>
        <flux:select wire:model.live="filterStatus" class="w-40">
            <flux:select.option value="">Semua Status</flux:select.option>
            <flux:select.option value="aktif">Aktif</flux:select.option>
            <flux:select.option value="nonaktif">Nonaktif</flux:select.option>
        </flux:select>
    </div>

    {{-- Tabel --}}
    <flux:card class="p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">Nama</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">Tim</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">No. HP</th>
                        <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">Status</th>
                        <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->personilList as $personil)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $personil->nama }}</td>
                            <td class="px-4 py-3 text-zinc-500">{{ $personil->tim->nama_tim ?? '—' }}</td>
                            <td class="px-4 py-3 text-zinc-500">{{ $personil->no_hp ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <x-status-badge :status="$personil->status" />
                            </td>
                            <td class="px-4 py-3 text-right">
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil" wire:click="bukaFormEdit({{ $personil->id }})">
                                            Edit
                                        </flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger" wire:click="konfirmasiHapus({{ $personil->id }})">
                                            Hapus
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-2">
                                <x-empty-state
                                    icon="user"
                                    title="Belum ada personil"
                                    description="Tambahkan personil, atau pastikan ada tim yang tersedia."
                                    action-label="Tambah Personil"
                                    action-wire="bukaFormTambah"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->personilList->hasPages())
            <div class="px-4 py-3 border-t border-zinc-100 dark:border-zinc-800">
                {{ $this->personilList->links() }}
            </div>
        @endif
    </flux:card>

    {{-- Modal Form Tambah/Edit --}}
    <flux:modal name="form-personil" class="max-w-md">
        <div class="flex flex-col gap-5 p-1">
            <flux:heading size="lg">{{ $editingId ? 'Edit Personil' : 'Tambah Personil' }}</flux:heading>
            <form wire:submit="simpan" class="flex flex-col gap-4">
                <flux:select wire:model.live="tim_id" label="Tim" placeholder="Pilih tim…" required>
                    @foreach ($this->timOptions as $tim)
                        <flux:select.option :value="$tim->id">{{ $tim->nama_tim }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model.live="nama" label="Nama Lengkap" placeholder="cth. Budi Santoso" required />
                <flux:input wire:model="no_hp" label="No. HP" placeholder="cth. 08123456789" type="tel" />
                <flux:select wire:model="status" label="Status" required>
                    <flux:select.option value="aktif">Aktif</flux:select.option>
                    <flux:select.option value="nonaktif">Nonaktif</flux:select.option>
                </flux:select>
                <div class="flex justify-end gap-2 pt-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="simpan">
                        <span wire:loading.remove wire:target="simpan">Simpan</span>
                        <span wire:loading wire:target="simpan">Menyimpan…</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Modal Konfirmasi Hapus --}}
    <flux:modal name="hapus-personil" class="max-w-sm">
        <div class="flex flex-col gap-4 p-1">
            <div>
                <flux:heading size="lg">Hapus Personil</flux:heading>
                <flux:text class="mt-1 text-zinc-500">Data personil akan dihapus. Riwayat jadwal tidak akan terpengaruh.</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="hapus" wire:loading.attr="disabled" wire:target="hapus">
                    Ya, Hapus
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
