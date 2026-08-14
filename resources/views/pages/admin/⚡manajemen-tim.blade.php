<?php

use App\Models\Tim;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tim')] #[Layout('layouts.admin')] class extends Component {

    public ?int $editingId    = null;
    public string $nama_tim   = '';
    public string $keterangan = '';
    public ?int $hapusId      = null;

    #[Computed]
    public function semuaTim(): array
    {
        return Tim::withCount('personil')
            ->orderBy('nama_tim')
            ->get()
            ->map(fn ($t) => [
                'id'             => $t->id,
                'nama_tim'       => $t->nama_tim,
                'keterangan'     => $t->keterangan ?? '',
                'personil_count' => $t->personil_count,
            ])
            ->toArray();
    }

    public function bukaFormTambah(): void
    {
        $this->resetForm();
        $this->modal('form-tim')->show();
    }

    public function bukaFormEdit(int $id): void
    {
        $tim              = Tim::findOrFail($id);
        $this->editingId  = $id;
        $this->nama_tim   = $tim->nama_tim;
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
        unset($this->semuaTim);
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

        if ($tim->personil_count > 0) {
            Flux::toast(variant: 'danger', text: 'Tidak bisa menghapus tim yang masih memiliki personil.');
            $this->modal('hapus-tim')->close();
            $this->hapusId = null;

            return;
        }

        $tim->delete();
        Flux::toast(variant: 'success', text: 'Tim berhasil dihapus.');
        $this->modal('hapus-tim')->close();
        $this->hapusId = null;
        unset($this->semuaTim);
    }

    private function resetForm(): void
    {
        $this->editingId  = null;
        $this->nama_tim   = '';
        $this->keterangan = '';
        $this->resetValidation();
    }
}; ?>

<div
    x-data="{
        rows: @js($this->semuaTim),
        q: '',
        page: 1,
        perPage: 10,
        sortField: 'nama_tim',
        sortDir: 'asc',

        get filtered() {
            let data = [...this.rows];
            if (this.q.trim()) {
                const qLow = this.q.toLowerCase();
                data = data.filter(r => r.nama_tim.toLowerCase().includes(qLow));
            }
            data.sort((a, b) => {
                let va = a[this.sortField] ?? ''; let vb = b[this.sortField] ?? '';
                if (typeof va === 'string') va = va.toLowerCase();
                if (typeof vb === 'string') vb = vb.toLowerCase();
                if (va < vb) return this.sortDir === 'asc' ? -1 : 1;
                if (va > vb) return this.sortDir === 'asc' ? 1 : -1;
                return 0;
            });
            return data;
        },
        get totalPages() { return Math.max(1, Math.ceil(this.filtered.length / this.perPage)); },
        get displayed()  { const s = (this.page-1)*this.perPage; return this.filtered.slice(s, s+this.perPage); },
        get pageNumbers() {
            const total = this.totalPages, cur = this.page;
            if (total <= 7) return Array.from({length:total},(_,i)=>i+1);
            if (cur <= 4) return [1,2,3,4,5,'...',total];
            if (cur >= total-3) return [1,'...',total-4,total-3,total-2,total-1,total];
            return [1,'...',cur-1,cur,cur+1,'...',total];
        },
        prevPage() { if (this.page > 1) this.page--; },
        nextPage() { if (this.page < this.totalPages) this.page++; },
        goPage(p)  { if (p !== '...' && p >= 1 && p <= this.totalPages) this.page = p; },
        toggleSort(field) {
            if (this.sortField === field) { this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc'; }
            else { this.sortField = field; this.sortDir = 'asc'; }
            this.page = 1;
        }
    }"
    x-effect="if (q !== undefined) page = 1"
    class="flex flex-col gap-6"
>
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Tim</flux:heading>
            <flux:text class="text-zinc-500">Kelola data tim peserta PKL/magang.</flux:text>
        </div>
        <flux:button variant="primary" wire:click="bukaFormTambah" icon="plus">Tambah Tim</flux:button>
    </div>

    <div class="relative">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input x-model="q" @input="page = 1" type="text" placeholder="Cari nama tim…"
            class="w-full pl-9 pr-9 py-2 rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 text-sm focus:outline-none focus:ring-2 focus:ring-brand dark:text-zinc-100" />
        <button x-show="q" @click="q = ''; page = 1" class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <flux:card class="p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th @click="toggleSort('nama_tim')" class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400 cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 select-none">
                            <span class="inline-flex items-center gap-1">Nama Tim
                                <svg x-show="sortField==='nama_tim' && sortDir==='asc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                <svg x-show="sortField==='nama_tim' && sortDir==='desc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                <svg x-show="sortField!=='nama_tim'" class="h-3.5 w-3.5 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
                            </span>
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">Keterangan</th>
                        <th @click="toggleSort('personil_count')" class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400 cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 select-none">
                            <span class="inline-flex items-center justify-center gap-1">Personil
                                <svg x-show="sortField==='personil_count' && sortDir==='asc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                <svg x-show="sortField==='personil_count' && sortDir==='desc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                <svg x-show="sortField!=='personil_count'" class="h-3.5 w-3.5 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
                            </span>
                        </th>
                        <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    <template x-if="displayed.length === 0">
                        <tr><td colspan="4" class="px-4 py-8 text-center text-zinc-400 text-sm" x-text="q ? 'Tidak ada tim yang cocok.' : 'Belum ada tim.'"></td></tr>
                    </template>
                    <template x-for="tim in displayed" :key="tim.id">
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100" x-text="tim.nama_tim"></td>
                            <td class="px-4 py-3 text-zinc-500 max-w-xs truncate" x-text="tim.keterangan || '—'"></td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center rounded-full bg-zinc-100 dark:bg-zinc-700 px-2.5 py-0.5 text-xs font-medium text-zinc-700 dark:text-zinc-300" x-text="tim.personil_count"></span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil" @click="$wire.bukaFormEdit(tim.id)">Edit</flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger" @click="$wire.konfirmasiHapus(tim.id)">Hapus</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        {{-- Pagination bar --}}
        <div class="px-4 py-3 border-t border-zinc-100 dark:border-zinc-800 flex flex-col sm:flex-row items-center justify-between gap-3">
            <span class="text-xs text-zinc-400" x-text="
                filtered.length === 0 ? 'Tidak ada hasil' :
                'Menampilkan ' + ((page-1)*perPage+1) + '–' + Math.min(page*perPage, filtered.length) + ' dari ' + filtered.length + ' tim'
            "></span>
            <div x-show="totalPages > 1" class="flex items-center gap-1">
                <button @click="prevPage()" :disabled="page===1" class="h-7 w-7 flex items-center justify-center rounded-md text-zinc-400 hover:text-zinc-100 hover:bg-zinc-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <template x-for="(p,idx) in pageNumbers" :key="idx">
                    <button @click="goPage(p)" :disabled="p==='...'" :class="p===page?'bg-brand text-white font-semibold':p==='...'?'text-zinc-500 cursor-default':'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-700'" class="h-7 min-w-[28px] px-1.5 flex items-center justify-center rounded-md text-xs transition-colors" x-text="p"></button>
                </template>
                <button @click="nextPage()" :disabled="page===totalPages" class="h-7 w-7 flex items-center justify-center rounded-md text-zinc-400 hover:text-zinc-100 hover:bg-zinc-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
        </div>
    </flux:card>

    <flux:modal name="form-tim" class="max-w-md">
        <div class="flex flex-col gap-5 p-1">
            <flux:heading size="lg">{{ $editingId ? 'Edit Tim' : 'Tambah Tim' }}</flux:heading>
            <form wire:submit="simpan" class="flex flex-col gap-4">
                <flux:input wire:model.live="nama_tim" label="Nama Tim" placeholder="cth. Tim Politeknik Negeri Jakarta" required />
                <flux:textarea wire:model="keterangan" label="Keterangan" placeholder="Keterangan opsional…" rows="3" />
                <div class="flex justify-end gap-2 pt-2">
                    <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="simpan">
                        <span wire:loading.remove wire:target="simpan">Simpan</span>
                        <span wire:loading wire:target="simpan">Menyimpan…</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal name="hapus-tim" class="max-w-sm">
        <div class="flex flex-col gap-4 p-1">
            <div>
                <flux:heading size="lg">Hapus Tim</flux:heading>
                <flux:text class="mt-1 text-zinc-500">Tim akan dihapus. Pastikan tim tidak memiliki personil aktif.</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="hapus" wire:loading.attr="disabled" wire:target="hapus">Ya, Hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
