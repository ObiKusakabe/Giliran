<?php

use App\Models\Personil;
use App\Models\Tim;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Personil')] #[Layout('layouts.admin')] class extends Component {

    public ?int $editingId    = null;
    public int|string $tim_id = '';
    public string $nama       = '';
    public string $no_hp      = '';
    public string $status     = 'aktif';
    public ?int $hapusId      = null;

    #[Computed]
    public function semuaPersonil(): array
    {
        return Personil::with('tim')
            ->orderBy('nama')
            ->get()
            ->map(fn ($p) => [
                'id'      => $p->id,
                'nama'    => $p->nama,
                'tim'     => $p->tim?->nama_tim ?? '—',
                'no_hp'   => $p->no_hp ?? '',
                'status'  => $p->status,
            ])
            ->toArray();
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
        $personil        = Personil::findOrFail($id);
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
        unset($this->semuaPersonil);
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

        Personil::findOrFail($this->hapusId)->delete();
        Flux::toast(variant: 'success', text: 'Personil berhasil dihapus.');
        $this->modal('hapus-personil')->close();
        $this->hapusId = null;
        unset($this->semuaPersonil);
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

<div
    x-data="{
        rows: @js($this->semuaPersonil),
        q: '',
        filterStatus: '',
        sortField: 'nama',
        sortDir: 'asc',
        page: 1,
        perPage: 20,

        get filtered() {
            let data = [...this.rows];
            if (this.q) data = data.filter(r => r.nama.toLowerCase().includes(this.q.toLowerCase()) || r.tim.toLowerCase().includes(this.q.toLowerCase()));
            if (this.filterStatus) data = data.filter(r => r.status === this.filterStatus);
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
        get displayed()  { const s=(this.page-1)*this.perPage; return this.filtered.slice(s,s+this.perPage); },
        get pageNumbers() {
            const total=this.totalPages,cur=this.page;
            if(total<=7) return Array.from({length:total},(_,i)=>i+1);
            if(cur<=4) return [1,2,3,4,5,'...',total];
            if(cur>=total-3) return [1,'...',total-4,total-3,total-2,total-1,total];
            return [1,'...',cur-1,cur,cur+1,'...',total];
        },
        prevPage(){ if(this.page>1)this.page--; },
        nextPage(){ if(this.page<this.totalPages)this.page++; },
        goPage(p){ if(p!=='...'&&p>=1&&p<=this.totalPages)this.page=p; },
        toggleSort(field) {
            if (this.sortField === field) { this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc'; }
            else { this.sortField = field; this.sortDir = 'asc'; }
            this.page = 1;
        }
    }"
    x-effect="if (q !== undefined || filterStatus !== undefined) page = 1"
    class="flex flex-col gap-6"
>
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Personil</flux:heading>
            <flux:text class="text-zinc-500">Kelola data personil peserta PKL/magang.</flux:text>
        </div>
        <flux:button variant="primary" wire:click="bukaFormTambah" icon="plus">Tambah Personil</flux:button>
    </div>

    <div class="flex gap-3">
        <div class="relative flex-1">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input x-model="q" type="text" placeholder="Cari nama atau tim…"
                class="w-full pl-9 pr-4 py-2 rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 text-sm focus:outline-none focus:ring-2 focus:ring-brand dark:text-zinc-100" />
            <button x-show="q" @click="q = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div
            x-data="{ open: false }"
            @click.outside="open = false"
            class="relative w-40"
        >
            <button type="button" @click="open = !open"
                :class="open ? 'ring-2 ring-brand border-brand' : 'border-zinc-300 dark:border-zinc-600 hover:border-zinc-400 dark:hover:border-zinc-500'"
                class="w-full flex items-center justify-between gap-2 rounded-lg border bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-left transition-colors focus:outline-none"
            >
                <span x-text="filterStatus === '' ? 'Semua Status' : (filterStatus === 'aktif' ? 'Aktif' : 'Nonaktif')"
                      class="text-zinc-900 dark:text-zinc-100"></span>
                <svg class="h-4 w-4 text-zinc-400 flex-shrink-0 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-75" x-transition:leave-end="opacity-0"
                 class="absolute z-50 mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg py-1">
                <template x-for="opt in [{value:'',label:'Semua Status'},{value:'aktif',label:'Aktif'},{value:'nonaktif',label:'Nonaktif'}]" :key="opt.value">
                    <button type="button" @click="filterStatus = opt.value; page = 1; open = false"
                        :class="filterStatus === opt.value ? 'bg-brand/10 text-brand font-medium' : 'text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700'"
                        class="w-full text-left px-3 py-2 text-sm flex items-center justify-between"
                    >
                        <span x-text="opt.label"></span>
                        <svg x-show="filterStatus === opt.value" class="h-4 w-4 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </button>
                </template>
            </div>
        </div>
    </div>

    <flux:card class="p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        @foreach([['nama','Nama'],['tim','Tim'],['status','Status']] as [$f,$l])
                        <th @click="toggleSort('{{ $f }}')"
                            class="px-4 py-3 {{ $f==='status' ? 'text-center' : 'text-left' }} font-medium text-zinc-600 dark:text-zinc-400 cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 select-none">
                            <span class="inline-flex items-center {{ $f==='status' ? 'justify-center' : '' }} gap-1">
                                {{ $l }}
                                <svg x-show="sortField==='{{ $f }}' && sortDir==='asc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                <svg x-show="sortField==='{{ $f }}' && sortDir==='desc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                <svg x-show="sortField!=='{{ $f }}'" class="h-3.5 w-3.5 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
                            </span>
                        </th>
                        @endforeach
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">No. HP</th>
                        <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    <template x-if="displayed.length === 0">
                        <tr><td colspan="5" class="px-4 py-8 text-center text-zinc-400 text-sm">Tidak ada personil yang cocok.</td></tr>
                    </template>
                    <template x-for="p in displayed" :key="p.id">
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100" x-text="p.nama"></td>
                            <td class="px-4 py-3 text-zinc-500" x-text="p.tim"></td>
                            <td class="px-4 py-3 text-center">
                                <span :class="p.status==='aktif' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                                      class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                      x-text="p.status==='aktif' ? 'Aktif' : 'Nonaktif'"></span>
                            </td>
                            <td class="px-4 py-3 text-zinc-500" x-text="p.no_hp || '—'"></td>
                            <td class="px-4 py-3 text-right">
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil" @click="$wire.bukaFormEdit(p.id)">Edit</flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger" @click="$wire.konfirmasiHapus(p.id)">Hapus</flux:menu.item>
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
                'Menampilkan ' + ((page-1)*perPage+1) + '–' + Math.min(page*perPage, filtered.length) + ' dari ' + filtered.length + ' personil'
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

    <flux:modal name="form-personil" class="max-w-md">
        <div class="flex flex-col gap-5 p-1">
            <flux:heading size="lg">{{ $editingId ? 'Edit Personil' : 'Tambah Personil' }}</flux:heading>
            <form wire:submit="simpan" class="flex flex-col gap-4">
                <x-searchable-select
                    name="tim_id"
                    label="Tim"
                    placeholder="Pilih tim…"
                    wire:model.live="tim_id"
                    :model-value="$tim_id"
                    :required="true"
                    :options="$this->timOptions->map(fn($t) => ['value' => $t->id, 'label' => $t->nama_tim])->toArray()"
                />
                <flux:input wire:model.live="nama" label="Nama Lengkap" placeholder="cth. Budi Santoso" required />
                <flux:input wire:model="no_hp" label="No. HP" placeholder="cth. 08123456789" type="tel" />
                {{-- Status — styled dropdown, konsisten dengan filter di tabel --}}
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Status <span class="text-red-500">*</span></label>
                    <div x-data="{ open: false, opts: [{value:'aktif',label:'Aktif'},{value:'nonaktif',label:'Nonaktif'}] }"
                         @click.outside="open = false" class="relative">
                        <button type="button" @click="open = !open"
                            :class="open ? 'ring-2 ring-brand border-brand' : 'border-zinc-300 dark:border-zinc-600 hover:border-zinc-400 dark:hover:border-zinc-500'"
                            class="w-full flex items-center justify-between gap-2 rounded-lg border bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-left transition-colors focus:outline-none"
                        >
                            <span class="text-zinc-900 dark:text-zinc-100">{{ $status === 'aktif' ? 'Aktif' : 'Nonaktif' }}</span>
                            <svg class="h-4 w-4 text-zinc-400 flex-shrink-0 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-75" x-transition:leave-end="opacity-0"
                             class="absolute z-50 mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg py-1">
                            <template x-for="opt in opts" :key="opt.value">
                                <button type="button"
                                    @click="$wire.set('status', opt.value); open = false"
                                    :class="opt.value === '{{ $status }}' ? 'bg-brand/10 text-brand font-medium' : 'text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700'"
                                    class="w-full text-left px-3 py-2 text-sm flex items-center justify-between"
                                >
                                    <span x-text="opt.label"></span>
                                    <svg x-show="opt.value === '{{ $status }}'" class="h-4 w-4 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
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

    <flux:modal name="hapus-personil" class="max-w-sm">
        <div class="flex flex-col gap-4 p-1">
            <div>
                <flux:heading size="lg">Hapus Personil</flux:heading>
                <flux:text class="mt-1 text-zinc-500">Data personil akan dihapus. Riwayat jadwal tidak akan terpengaruh.</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="hapus" wire:loading.attr="disabled" wire:target="hapus">Ya, Hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
