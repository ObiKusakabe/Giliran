<?php

use App\Models\Ruangan;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ruangan')] #[Layout('layouts.admin')] class extends Component {

    public ?int $editingId        = null;
    public string $nama_ruangan   = '';
    public int|string $kapasitas  = '';
    public string $status         = 'tersedia';
    public ?int $hapusId          = null;

    #[Computed]
    public function semuaRuangan(): array
    {
        return Ruangan::orderBy('nama_ruangan')
            ->get()
            ->map(fn ($r) => [
                'id'           => $r->id,
                'nama_ruangan' => $r->nama_ruangan,
                'kapasitas'    => $r->kapasitas,
                'status'       => $r->status,
            ])
            ->toArray();
    }

    #[Computed]
    public function totalRuangan(): int
    {
        return Ruangan::count();
    }

    #[Computed]
    public function totalRuanganTersedia(): int
    {
        return Ruangan::where('status', 'tersedia')->count();
    }

    #[Computed]
    public function totalRuanganTidakTersedia(): int
    {
        return Ruangan::where('status', 'tidak_tersedia')->count();
    }

    #[Computed]
    public function totalKapasitas(): int
    {
        return (int) Ruangan::where('status', 'tersedia')->sum('kapasitas');
    }

    public function bukaFormTambah(): void
    {
        $this->resetForm();
        $this->modal('form-ruangan')->show();
    }

    public function bukaFormEdit(int $id): void
    {
        $ruangan            = Ruangan::findOrFail($id);
        $this->editingId    = $id;
        $this->nama_ruangan = $ruangan->nama_ruangan;
        $this->kapasitas    = $ruangan->kapasitas;
        $this->status       = $ruangan->status;
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
        unset($this->semuaRuangan, $this->totalRuangan, $this->totalRuanganTersedia, $this->totalRuanganTidakTersedia, $this->totalKapasitas);
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

        Ruangan::findOrFail($this->hapusId)->delete();
        Flux::toast(variant: 'success', text: 'Ruangan berhasil dihapus.');
        $this->modal('hapus-ruangan')->close();
        $this->hapusId = null;
        unset($this->semuaRuangan, $this->totalRuangan, $this->totalRuanganTersedia, $this->totalRuanganTidakTersedia, $this->totalKapasitas);
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

<div
    x-data="{
        rows: @js($this->semuaRuangan),
        q: '',
        filterStatus: '',
        sortField: 'nama_ruangan',
        sortDir: 'asc',
        page: 1,
        perPage: 10,

        get filtered() {
            let data = [...this.rows];
            if (this.q) data = data.filter(r => r.nama_ruangan.toLowerCase().includes(this.q.toLowerCase()));
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
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <flux:heading size="xl">Ruangan</flux:heading>
            <flux:text class="text-zinc-500">Kelola data ruangan meeting dan kelas.</flux:text>
        </div>
        <flux:button variant="primary" wire:click="bukaFormTambah" icon="plus" class="flex-shrink-0">Tambah Ruangan</flux:button>
    </div>

    {{-- Quick Info Cards with Watermark Icons --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="relative z-10 pr-6">
                <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Total Ruangan</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                    {{ $this->totalRuangan }}
                </flux:heading>
            </div>
            <flux:icon icon="home-modern" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-blue-500/10 dark:text-blue-400/10 pointer-events-none" />
        </flux:card>

        <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="relative z-10 pr-6">
                <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Tersedia</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                    {{ $this->totalRuanganTersedia }}
                </flux:heading>
            </div>
            <flux:icon icon="check-circle" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-emerald-500/10 dark:text-emerald-400/10 pointer-events-none" />
        </flux:card>

        <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="relative z-10 pr-6">
                <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Tidak Tersedia</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-zinc-600 dark:text-zinc-400">
                    {{ $this->totalRuanganTidakTersedia }}
                </flux:heading>
            </div>
            <flux:icon icon="x-circle" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-zinc-500/10 dark:text-zinc-400/10 pointer-events-none" />
        </flux:card>

        <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="relative z-10 pr-6">
                <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Kapasitas Kursi</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-purple-600 dark:text-purple-400">
                    {{ $this->totalKapasitas }}
                </flux:heading>
            </div>
            <flux:icon icon="user-group" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-purple-500/10 dark:text-purple-400/10 pointer-events-none" />
        </flux:card>
    </div>

    <div class="flex gap-3">
        <div class="relative flex-1">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input x-model="q" type="text" placeholder="Cari nama ruangan…"
                class="w-full pl-9 pr-4 py-2 rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 text-sm focus:outline-none focus:ring-2 focus:ring-brand dark:text-zinc-100" />
            <button x-show="q" @click="q = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div
            x-data="{ open: false }"
            @click.outside="open = false"
            class="relative w-40 sm:w-44"
        >
            <button type="button" @click="open = !open"
                :class="open ? 'ring-2 ring-brand border-brand' : 'border-zinc-300 dark:border-zinc-600 hover:border-zinc-400 dark:hover:border-zinc-500'"
                class="w-full flex items-center justify-between gap-2 rounded-lg border bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-left transition-colors focus:outline-none cursor-pointer"
            >
                <span x-text="filterStatus === '' ? 'Semua Status' : (filterStatus === 'tersedia' ? 'Tersedia' : 'Tidak Tersedia')"
                      class="text-zinc-900 dark:text-zinc-100"></span>
                <svg class="h-4 w-4 text-zinc-400 flex-shrink-0 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-75" x-transition:leave-end="opacity-0"
                 class="absolute z-50 mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg py-1">
                <template x-for="opt in [{value:'',label:'Semua Status'},{value:'tersedia',label:'Tersedia'},{value:'tidak_tersedia',label:'Tidak Tersedia'}]" :key="opt.value">
                    <button type="button" @click="filterStatus = opt.value; page = 1; open = false"
                        :class="filterStatus === opt.value ? 'bg-brand/10 text-brand font-medium' : 'text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700'"
                        class="w-full text-left px-3 py-2 text-sm flex items-center justify-between cursor-pointer"
                    >
                        <span x-text="opt.label"></span>
                        <svg x-show="filterStatus === opt.value" class="h-4 w-4 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </button>
                </template>
            </div>
        </div>
    </div>

    <flux:card class="p-0 overflow-visible table-sticky-card border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs">
        {{-- Card-list: mobile only (< sm) --}}
        <div class="sm:hidden divide-y divide-zinc-100 dark:divide-zinc-800">
            <template x-if="displayed.length === 0">
                <div class="px-4 py-8 text-center text-zinc-400 text-sm">Tidak ada ruangan yang cocok.</div>
            </template>
            <template x-for="r in displayed" :key="r.id">
                <div class="flex items-center gap-3 px-4 py-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate" x-text="r.nama_ruangan"></p>
                            <span :class="r.status==='tersedia' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium flex-shrink-0"
                                  x-text="r.status==='tersedia' ? 'Tersedia' : 'Tidak Tersedia'"></span>
                        </div>
                        <p class="text-xs text-zinc-400 mt-0.5" x-text="r.kapasitas + ' orang'"></p>
                    </div>
                    <flux:dropdown>
                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                        <flux:menu>
                            <flux:menu.item icon="pencil" @click="$wire.bukaFormEdit(r.id)">Edit</flux:menu.item>
                            <flux:menu.separator />
                            <flux:menu.item icon="trash" variant="danger" @click="$wire.konfirmasiHapus(r.id)">Hapus</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </div>
            </template>
        </div>

        {{-- Tabel: sm dan lebih lebar --}}
        <div class="hidden sm:block px-5">
            <flux:table>
                <flux:table.columns class="bg-white dark:bg-zinc-900">
                    <flux:table.column @click="toggleSort('nama_ruangan')" class="cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 select-none">
                        <span class="inline-flex items-center gap-1">Nama Ruangan
                            <svg x-show="sortField==='nama_ruangan' && sortDir==='asc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                            <svg x-show="sortField==='nama_ruangan' && sortDir==='desc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            <svg x-show="sortField!=='nama_ruangan'" class="h-3.5 w-3.5 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
                        </span>
                    </flux:table.column>
                    <flux:table.column @click="toggleSort('kapasitas')" align="center" class="cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 select-none">
                        <span class="inline-flex items-center justify-center gap-1">Kapasitas
                            <svg x-show="sortField==='kapasitas' && sortDir==='asc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                            <svg x-show="sortField==='kapasitas' && sortDir==='desc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            <svg x-show="sortField!=='kapasitas'" class="h-3.5 w-3.5 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
                        </span>
                    </flux:table.column>
                    <flux:table.column @click="toggleSort('status')" align="center" class="cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 select-none">
                        <span class="inline-flex items-center justify-center gap-1">Status
                            <svg x-show="sortField==='status' && sortDir==='asc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                            <svg x-show="sortField==='status' && sortDir==='desc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            <svg x-show="sortField!=='status'" class="h-3.5 w-3.5 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
                        </span>
                    </flux:table.column>
                    <flux:table.column align="end">Aksi</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    <template x-if="displayed.length === 0">
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="text-center text-zinc-400 text-sm py-8">Tidak ada ruangan yang cocok.</flux:table.cell>
                        </flux:table.row>
                    </template>
                    <template x-for="r in displayed" :key="r.id">
                        <flux:table.row>
                            <flux:table.cell class="font-medium text-zinc-900 dark:text-zinc-100" x-text="r.nama_ruangan"></flux:table.cell>
                            <flux:table.cell align="center" class="text-zinc-500" x-text="r.kapasitas + ' orang'"></flux:table.cell>
                            <flux:table.cell align="center">
                                <span :class="r.status==='tersedia' ? 'bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400' : 'bg-red-100 dark:bg-red-950/40 text-red-700 dark:text-red-400'"
                                      class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                      x-text="r.status==='tersedia' ? 'Tersedia' : 'Tidak Tersedia'"></span>
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil" @click="$wire.bukaFormEdit(r.id)">Edit</flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger" @click="$wire.konfirmasiHapus(r.id)">Hapus</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    </template>
                </flux:table.rows>
            </flux:table>
        </div>
        {{-- Pagination bar --}}
        <div class="px-4 py-3 border-t border-zinc-100 dark:border-zinc-800 flex flex-col sm:flex-row items-center justify-between gap-3">
            <span class="text-xs text-zinc-400" x-text="
                filtered.length === 0 ? 'Tidak ada hasil' :
                'Menampilkan ' + ((page-1)*perPage+1) + '–' + Math.min(page*perPage, filtered.length) + ' dari ' + filtered.length + ' ruangan'
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

    <flux:modal name="form-ruangan" class="max-w-md">
        <div class="flex flex-col gap-5 p-1">
            <flux:heading size="lg">{{ $editingId ? 'Edit Ruangan' : 'Tambah Ruangan' }}</flux:heading>
            <form wire:submit="simpan" class="flex flex-col gap-4">
                <flux:input wire:model.blur="nama_ruangan" label="Nama Ruangan" placeholder="cth. Ruang Meeting A" required />
                <flux:input wire:model.blur="kapasitas" label="Kapasitas (orang)" type="number" min="1" placeholder="cth. 10" required />
                {{-- Status — styled dropdown --}}
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Status <span class="text-red-500">*</span></label>
                    <div x-data="{ open: false, opts: [{value:'tersedia',label:'Tersedia'},{value:'tidak_tersedia',label:'Tidak Tersedia'}] }"
                         @click.outside="open = false" class="relative">
                        <button type="button" @click="open = !open"
                            :class="open ? 'ring-2 ring-brand border-brand' : 'border-zinc-300 dark:border-zinc-600 hover:border-zinc-400 dark:hover:border-zinc-500'"
                            class="w-full flex items-center justify-between gap-2 rounded-lg border bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-left transition-colors focus:outline-none"
                        >
                            <span class="text-zinc-900 dark:text-zinc-100">{{ $status === 'tersedia' ? 'Tersedia' : 'Tidak Tersedia' }}</span>
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

    <flux:modal name="hapus-ruangan" class="max-w-sm">
        <div class="flex flex-col gap-4 p-1">
            <div>
                <flux:heading size="lg">Hapus Ruangan</flux:heading>
                <flux:text class="mt-1 text-zinc-500">Data ruangan akan dihapus. Riwayat alokasi tidak akan terpengaruh.</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="hapus" wire:loading.attr="disabled" wire:target="hapus">Ya, Hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
