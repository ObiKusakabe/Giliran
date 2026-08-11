<?php

use App\Models\Tim;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Tim')] #[Layout('layouts.admin')] class extends Component {
    use WithPagination;

    public string $search = '';

    // Form fields
    public ?int $editingId = null;
    public string $nama_tim = '';
    public string $keterangan = '';

    // ID tim yang mau dihapus
    public ?int $hapusId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function timList()
    {
        return Tim::query()
            ->when($this->search, fn ($q) => $q->where('nama_tim', 'like', "%{$this->search}%"))
            ->withCount('personil')
            ->orderBy('nama_tim')
            ->paginate(10);
    }

    public function bukaFormTambah(): void
    {
        $this->resetForm();
        $this->modal('form-tim')->show();
    }

    public function bukaFormEdit(int $id): void
    {
        $tim = Tim::findOrFail($id);
        $this->editingId = $id;
        $this->nama_tim = $tim->nama_tim;
        $this->keterangan = $tim->keterangan ?? '';
        $this->modal('form-tim')->show();
    }

    public function simpan(): void
    {
        $this->validate([
            'nama_tim'   => 'required|string|max:100',
            'keterangan' => 'nullable|string',
        ]);

        if ($this->editingId) {
            Tim::findOrFail($this->editingId)->update([
                'nama_tim'   => $this->nama_tim,
                'keterangan' => $this->keterangan ?: null,
            ]);
            Flux::toast(variant: 'success', text: 'Tim berhasil diperbarui.');
        } else {
            Tim::create([
                'nama_tim'   => $this->nama_tim,
                'keterangan' => $this->keterangan ?: null,
            ]);
            Flux::toast(variant: 'success', text: 'Tim berhasil ditambahkan.');
        }

        $this->modal('form-tim')->close();
        $this->resetForm();
        unset($this->timList);
    }

    public function konfirmasiHapus(int $id): void
    {
        $this->hapusId = $id;
        $this->modal('hapus-tim')->show();
    }

    public function hapus(): void
    {
        if (! $this->hapusId) {
            return;
        }

        $tim = Tim::withCount('personil')->findOrFail($this->hapusId);

        // TIM-02: cegah hapus kalau masih ada personil
        if ($tim->personil_count > 0) {
            Flux::toast(variant: 'danger', text: 'Tidak bisa menghapus tim yang masih memiliki personil.');
            $this->modal('hapus-tim')->close();
            $this->hapusId = null;

            return;
        }

        $tim->delete(); // soft delete
        Flux::toast(variant: 'success', text: 'Tim berhasil dihapus.');
        $this->modal('hapus-tim')->close();
        $this->hapusId = null;
        unset($this->timList);
    }

    private function resetForm(): void
    {
        $this->editingId  = null;
        $this->nama_tim   = '';
        $this->keterangan = '';
        $this->resetValidation();
    }
}; ?>

<div class="flex flex-col gap-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Tim</flux:heading>
            <flux:text class="text-zinc-500">Kelola data tim peserta PKL/magang.</flux:text>
        </div>
        <flux:button variant="primary" wire:click="bukaFormTambah" icon="plus">
            Tambah Tim
        </flux:button>
    </div>

    {{-- Search --}}
    <flux:input
        wire:model.live.debounce.300ms="search"
        placeholder="Cari nama tim…"
        icon="magnifying-glass"
        clearable
    />

    {{-- Tabel --}}
    <flux:card class="p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">Nama Tim</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">Keterangan</th>
                        <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">Personil</th>
                        <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->timList as $tim)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $tim->nama_tim }}
                            </td>
                            <td class="px-4 py-3 text-zinc-500 max-w-xs truncate">
                                {{ $tim->keterangan ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center rounded-full bg-zinc-100 dark:bg-zinc-700 px-2.5 py-0.5 text-xs font-medium text-zinc-700 dark:text-zinc-300">
                                    {{ $tim->personil_count }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil" wire:click="bukaFormEdit({{ $tim->id }})">
                                            Edit
                                        </flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger" wire:click="konfirmasiHapus({{ $tim->id }})">
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
                                    icon="users"
                                    title="Belum ada tim"
                                    description="Tambahkan tim pertama untuk mulai mengelola jadwal."
                                    action-label="Tambah Tim"
                                    action-wire="bukaFormTambah"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->timList->hasPages())
            <div class="px-4 py-3 border-t border-zinc-100 dark:border-zinc-800">
                {{ $this->timList->links() }}
            </div>
        @endif
    </flux:card>

    {{-- Modal Form Tambah/Edit --}}
    <flux:modal name="form-tim" class="max-w-md">
        <div class="flex flex-col gap-5 p-1">
            <flux:heading size="lg">{{ $editingId ? 'Edit Tim' : 'Tambah Tim' }}</flux:heading>
            <form wire:submit="simpan" class="flex flex-col gap-4">
                <flux:input wire:model.live="nama_tim" label="Nama Tim" placeholder="cth. Tim Politeknik Negeri Jakarta" required />
                <flux:textarea wire:model="keterangan" label="Keterangan" placeholder="Keterangan opsional…" rows="3" />
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
    <flux:modal name="hapus-tim" class="max-w-sm">
        <div class="flex flex-col gap-4 p-1">
            <div>
                <flux:heading size="lg">Hapus Tim</flux:heading>
                <flux:text class="mt-1 text-zinc-500">Tim akan dihapus. Pastikan tim tidak memiliki personil aktif.</flux:text>
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
