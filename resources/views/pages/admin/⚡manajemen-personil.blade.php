<?php

use App\Models\Personil;
use App\Models\Tim;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Personil')] #[Layout('layouts.admin')] #[Lazy] class extends Component {

    public ?int $editingId       = null;
    public int|string $tim_id    = '';
    public string $nama          = '';
    public string $jenis_kelamin = 'laki-laki';
    public string $no_hp         = '';
    public string $status        = 'aktif';
    public ?int $hapusId         = null;

    // Filter by tim dari URL query parameter - using #[Url] to sync with query string
    #[Url(as: 'tim')]
    public ?int $filterTimId = null;

    #[Computed]
    public function semuaPersonil(): array
    {
        $query = Personil::with('tim');

        // Jika ada filter tim dari URL, prioritaskan tim tersebut di atas
        if ($this->filterTimId) {
            $query->orderByRaw('CASE WHEN tim_id = ? THEN 0 ELSE 1 END', [$this->filterTimId]);
        }
        
        $query->orderBy('nama');

        return $query->get()
            ->map(fn ($p) => [
                'id'            => $p->id,
                'nama'          => $p->nama,
                'jenis_kelamin' => $p->jenis_kelamin ?? 'laki-laki',
                'tim_id'        => $p->tim_id,
                'tim'           => $p->tim?->nama_tim ?? '-',
                'no_hp'         => $p->no_hp ?? '',
                'status'        => $p->status,
                'is_highlighted' => $this->filterTimId && $p->tim_id === $this->filterTimId,
            ])
            ->toArray();
    }

    #[Computed]
    public function timOptions()
    {
        return Tim::orderBy('nama_tim')->get(['id', 'nama_tim']);
    }

    #[Computed]
    public function filteredTim(): ?Tim
    {
        return $this->filterTimId ? Tim::find($this->filterTimId) : null;
    }

    public function clearFilter(): void
    {
        $this->filterTimId = null;
        $this->redirect(route('admin.personil'), navigate: true);
    }

    #[Computed]
    public function totalPersonil(): int
    {
        return Personil::count();
    }

    #[Computed]
    public function totalPersonilAktif(): int
    {
        return Personil::where('status', 'aktif')->count();
    }

    #[Computed]
    public function totalPersonilNonaktif(): int
    {
        return Personil::where('status', 'nonaktif')->count();
    }

    #[Computed]
    public function totalTim(): int
    {
        return Tim::count();
    }

    public function bukaFormTambah(): void
    {
        $this->resetForm();
        $this->modal('form-personil')->show();
    }

    public function bukaFormEdit(int $id): void
    {
        $personil            = Personil::findOrFail($id);
        $this->editingId     = $id;
        $this->tim_id        = $personil->tim_id;
        $this->nama          = $personil->nama;
        $this->jenis_kelamin = $personil->jenis_kelamin ?? 'laki-laki';
        $this->no_hp         = $personil->no_hp ?? '';
        $this->status        = $personil->status;
        $this->modal('form-personil')->show();
    }

    public function updatedNoHp(string $value): void
    {
        $this->no_hp = preg_replace('/[^0-9]/', '', $value);
    }

    public function simpan(): void
    {
        $this->validate([
            'tim_id'        => 'required|exists:tim,id',
            'nama'          => 'required|string|max:150',
            'jenis_kelamin' => 'required|in:laki-laki,perempuan',
            'no_hp'         => 'nullable|string|regex:/^[0-9]+$/|max:20',
            'status'        => 'required|in:aktif,nonaktif',
        ], [
            'tim_id.required'        => 'Tim wajib dipilih.',
            'tim_id.exists'          => 'Tim yang dipilih tidak valid.',
            'nama.required'          => 'Nama lengkap wajib diisi.',
            'jenis_kelamin.required' => 'Jenis kelamin wajib dipilih.',
            'no_hp.regex'            => 'No. HP hanya boleh berisi angka.',
            'status.required'        => 'Status wajib dipilih.',
        ]);

        if ($this->editingId) {
            Personil::findOrFail($this->editingId)->update([
                'tim_id'        => (int) $this->tim_id,
                'nama'          => trim($this->nama),
                'jenis_kelamin' => $this->jenis_kelamin,
                'no_hp'         => $this->no_hp ?: null,
                'status'        => $this->status,
            ]);
            Flux::toast(variant: 'success', text: 'Personil berhasil diperbarui.');
        } else {
            Personil::create([
                'tim_id'        => (int) $this->tim_id,
                'nama'          => trim($this->nama),
                'jenis_kelamin' => $this->jenis_kelamin,
                'no_hp'         => $this->no_hp ?: null,
                'status'        => $this->status,
            ]);
            Flux::toast(variant: 'success', text: 'Personil baru berhasil ditambahkan.');
        }

        $this->modal('form-personil')->close();
        $this->resetForm();
        unset($this->semuaPersonil, $this->totalPersonil, $this->totalPersonilAktif, $this->totalPersonilNonaktif);
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
        unset($this->semuaPersonil, $this->totalPersonil, $this->totalPersonilAktif, $this->totalPersonilNonaktif);
    }

    public function resetForm(): void
    {
        $this->editingId     = null;
        $this->tim_id        = '';
        $this->nama          = '';
        $this->jenis_kelamin = 'laki-laki';
        $this->no_hp         = '';
        $this->status        = 'aktif';
        $this->resetValidation();
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="p-6 space-y-6">
            <x-skeletons.page-header />
            <x-skeletons.stat-cards />
            <x-skeletons.table :columns="6" :rows="8" />
        </div>
        HTML;
    }
}; ?>

<div
    x-data="{
        rows: @js($this->semuaPersonil),
        q: '',
        filterStatus: '',
        filterGender: '',
        sortField: 'nama',
        sortDir: 'asc',
        page: 1,
        perPage: 20,
        hasScrolledToHighlight: false,

        get filtered() {
            let data = [...this.rows];
            if (this.q) data = data.filter(r => r.nama.toLowerCase().includes(this.q.toLowerCase()) || r.tim.toLowerCase().includes(this.q.toLowerCase()));
            if (this.filterStatus) data = data.filter(r => r.status === this.filterStatus);
            if (this.filterGender) data = data.filter(r => r.jenis_kelamin === this.filterGender);
            
            // Sort logic
            data.sort((a, b) => {
                // Priority 1: If any row is highlighted, show highlighted first
                if (a.is_highlighted !== b.is_highlighted) {
                    return a.is_highlighted ? -1 : 1;
                }
                
                // Priority 2: Then sort by user-selected field
                let va = a[this.sortField] ?? ''; 
                let vb = b[this.sortField] ?? '';
                if (typeof va === 'string') va = va.toLowerCase();
                if (typeof vb === 'string') vb = vb.toLowerCase();
                if (va < vb) return this.sortDir === 'asc' ? -1 : 1;
                if (va > vb) return this.sortDir === 'asc' ? 1 : -1;
                return 0;
            });
            return data;
        },
        get highlightedCount() {
            return this.rows.filter(r => r.is_highlighted).length;
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
    x-effect="if (q !== undefined || filterStatus !== undefined || filterGender !== undefined) page = 1"
    class="flex flex-col gap-6"
>
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <flux:heading size="xl">Personil</flux:heading>
            <flux:text class="text-zinc-500">Kelola data personil peserta PKL/magang.</flux:text>
            
            {{-- Filter Badge - Show when filtering by tim --}}
            @if ($filterTimId && $this->filteredTim)
                <div 
                    class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-blue-100 dark:bg-blue-900/30 border border-blue-300 dark:border-blue-700 shadow-sm"
                    x-data
                    x-init="$nextTick(() => { 
                        const firstHighlighted = document.querySelector('.highlight-row');
                        if (firstHighlighted) {
                            setTimeout(() => {
                                const yOffset = -150; // Offset 150px dari top (agar tidak terlalu bawah)
                                const y = firstHighlighted.getBoundingClientRect().top + window.pageYOffset + yOffset;
                                window.scrollTo({ top: y, behavior: 'smooth' });
                            }, 200);
                        }
                    })"
                >
                    <flux:icon icon="funnel" class="size-4 text-blue-600 dark:text-blue-400" />
                    <span class="text-sm font-medium text-blue-700 dark:text-blue-300">
                        <strong>{{ $this->filteredTim->nama_tim }}</strong>
                        <span x-data x-text="`(${highlightedCount} personil)`" class="opacity-80"></span>
                    </span>
                    <button 
                        @click="$wire.set('filterTimId', null); window.history.pushState({}, '', '/admin/personil');"
                        class="ml-2 p-0.5 text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 hover:bg-blue-200 dark:hover:bg-blue-800 rounded transition-colors"
                        title="Hapus filter & tampilkan semua"
                    >
                        <flux:icon icon="x-mark" class="size-4" />
                    </button>
                </div>
            @endif
        </div>
        <flux:modal.trigger name="form-personil">
            <flux:button variant="primary" icon="plus" class="flex-shrink-0" wire:click="bukaFormTambah">Tambah Personil</flux:button>
        </flux:modal.trigger>
    </div>

    {{-- Quick Info Cards with Watermark Icons --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="relative z-10 pr-6">
                <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Total Personil</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                    {{ $this->totalPersonil }}
                </flux:heading>
            </div>
            <flux:icon icon="users" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-blue-500/10 dark:text-blue-400/10 pointer-events-none" />
        </flux:card>

        <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="relative z-10 pr-6">
                <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Personil Aktif</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                    {{ $this->totalPersonilAktif }}
                </flux:heading>
            </div>
            <flux:icon icon="check-circle" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-emerald-500/10 dark:text-emerald-400/10 pointer-events-none" />
        </flux:card>

        <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="relative z-10 pr-6">
                <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Nonaktif</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-zinc-600 dark:text-zinc-400">
                    {{ $this->totalPersonilNonaktif }}
                </flux:heading>
            </div>
            <flux:icon icon="x-circle" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-zinc-500/10 dark:text-zinc-400/10 pointer-events-none" />
        </flux:card>

        <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="relative z-10 pr-6">
                <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Total Tim</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-purple-600 dark:text-purple-400">
                    {{ $this->totalTim }}
                </flux:heading>
            </div>
            <flux:icon icon="user-group" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-purple-500/10 dark:text-purple-400/10 pointer-events-none" />
        </flux:card>
    </div>

    {{-- Search & Filter Bar --}}
    {{-- Search & Filter Bar --}}
    <div class="flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input x-model="q" type="text" placeholder="Cari nama, tim, atau no HP..."
                class="w-full pl-9 pr-4 py-2 rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 text-sm focus:outline-none focus:ring-2 focus:ring-brand dark:text-zinc-100" />
            <button x-show="q" @click="q = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Filter Jenis Kelamin --}}
        <div
            x-data="{ open: false }"
            @click.outside="open = false"
            class="relative w-full sm:w-44"
        >
            <button type="button" @click="open = !open"
                :class="open ? 'ring-2 ring-brand border-brand' : (filterGender ? 'border-brand text-brand font-medium' : 'border-zinc-300 dark:border-zinc-600 hover:border-zinc-400 dark:hover:border-zinc-500')"
                class="w-full flex items-center justify-between gap-2 rounded-lg border bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-left transition-colors focus:outline-none cursor-pointer"
            >
                <span x-text="filterGender === '' ? 'Semua Gender' : (filterGender === 'laki-laki' ? '? Laki-laki' : '? Perempuan')"
                      class="truncate"></span>
                <svg class="h-4 w-4 text-zinc-400 flex-shrink-0 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-75" x-transition:leave-end="opacity-0"
                 class="absolute z-50 mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg py-1">
                <template x-for="opt in [{value:'',label:'Semua Gender'},{value:'laki-laki',label:'? Laki-laki'},{value:'perempuan',label:'? Perempuan'}]" :key="opt.value">
                    <button type="button" @click="filterGender = opt.value; page = 1; open = false"
                        :class="filterGender === opt.value ? 'bg-brand/10 text-brand font-medium' : 'text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700'"
                        class="w-full text-left px-3 py-2 text-sm flex items-center justify-between cursor-pointer"
                    >
                        <span x-text="opt.label"></span>
                        <svg x-show="filterGender === opt.value" class="h-4 w-4 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </button>
                </template>
            </div>
        </div>

        {{-- Filter Status --}}
        <div
            x-data="{ open: false }"
            @click.outside="open = false"
            class="relative w-full sm:w-40"
        >
            <button type="button" @click="open = !open"
                :class="open ? 'ring-2 ring-brand border-brand' : (filterStatus ? 'border-brand text-brand font-medium' : 'border-zinc-300 dark:border-zinc-600 hover:border-zinc-400 dark:hover:border-zinc-500')"
                class="w-full flex items-center justify-between gap-2 rounded-lg border bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-left transition-colors focus:outline-none cursor-pointer"
            >
                <span x-text="filterStatus === '' ? 'Semua Status' : (filterStatus === 'aktif' ? 'Aktif' : 'Nonaktif')"
                      class="truncate"></span>
                <svg class="h-4 w-4 text-zinc-400 flex-shrink-0 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-75" x-transition:leave-end="opacity-0"
                 class="absolute z-50 mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg py-1">
                <template x-for="opt in [{value:'',label:'Semua Status'},{value:'aktif',label:'Aktif'},{value:'nonaktif',label:'Nonaktif'}]" :key="opt.value">
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
                <div class="px-4 py-8 text-center text-zinc-400 text-sm">Tidak ada personil yang cocok.</div>
            </template>
            <template x-for="p in displayed" :key="p.id">
                <div class="flex items-center gap-3 px-4 py-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate" x-text="p.nama"></p>
                            <span :class="p.status==='aktif' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium flex-shrink-0"
                                  x-text="p.status==='aktif' ? 'Aktif' : 'Nonaktif'"></span>
                        </div>
                        <p class="text-xs text-zinc-500 mt-0.5" x-text="p.tim"></p>
                        <p class="text-xs text-zinc-400 mt-0.5" x-show="p.no_hp" x-text="p.no_hp"></p>
                    </div>
                    <flux:dropdown>
                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                        <flux:menu>
                            <flux:menu.item icon="pencil" @click="$wire.bukaFormEdit(p.id)">Edit</flux:menu.item>
                            <flux:menu.separator />
                            <flux:menu.item icon="trash" variant="danger" @click="$wire.konfirmasiHapus(p.id)">Hapus</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </div>
            </template>
        </div>

        {{-- Tabel: sm dan lebih lebar --}}
        <div class="hidden sm:block px-5">
            <flux:table>
                <flux:table.columns class="bg-white dark:bg-zinc-900">
                    <flux:table.column @click="toggleSort('nama')" class="cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 select-none">
                        <span class="inline-flex items-center gap-1">Nama
                            <svg x-show="sortField==='nama' && sortDir==='asc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                            <svg x-show="sortField==='nama' && sortDir==='desc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            <svg x-show="sortField!=='nama'" class="h-3.5 w-3.5 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
                        </span>
                    </flux:table.column>
                    <flux:table.column @click="toggleSort('tim')" class="cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 select-none">
                        <span class="inline-flex items-center gap-1">Tim
                            <svg x-show="sortField==='tim' && sortDir==='asc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                            <svg x-show="sortField==='tim' && sortDir==='desc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            <svg x-show="sortField!=='tim'" class="h-3.5 w-3.5 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
                        </span>
                    </flux:table.column>

                    {{-- Kolom Jenis Kelamin dengan Filter Dropdown di Header --}}
                    <flux:table.column class="select-none">
                        <div x-data="{ openColGender: false }" @click.outside="openColGender = false" class="relative inline-block">
                            <button
                                type="button"
                                @click="openColGender = !openColGender"
                                class="inline-flex items-center gap-1.5 cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors"
                                :class="filterGender ? 'text-brand font-semibold' : ''"
                            >
                                <span>Jenis Kelamin</span>
                                <svg class="h-3.5 w-3.5" :class="filterGender ? 'text-brand' : 'text-zinc-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                </svg>
                                <span x-show="filterGender" class="size-1.5 rounded-full bg-brand"></span>
                            </button>
                            <div
                                x-show="openColGender"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-end="opacity-0"
                                class="absolute left-0 z-50 mt-1.5 w-36 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-xl py-1 text-xs font-normal"
                            >
                                <button type="button" @click="filterGender = ''; page = 1; openColGender = false"
                                    :class="filterGender === '' ? 'bg-brand/10 text-brand font-semibold' : 'text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700'"
                                    class="w-full text-left px-3 py-1.5 flex items-center justify-between cursor-pointer"
                                >
                                    <span>Semua</span>
                                    <svg x-show="filterGender === ''" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </button>
                                <button type="button" @click="filterGender = 'laki-laki'; page = 1; openColGender = false"
                                    :class="filterGender === 'laki-laki' ? 'bg-brand/10 text-brand font-semibold' : 'text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700'"
                                    class="w-full text-left px-3 py-1.5 flex items-center justify-between cursor-pointer"
                                >
                                    <span>? Laki-laki</span>
                                    <svg x-show="filterGender === 'laki-laki'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </button>
                                <button type="button" @click="filterGender = 'perempuan'; page = 1; openColGender = false"
                                    :class="filterGender === 'perempuan' ? 'bg-brand/10 text-brand font-semibold' : 'text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700'"
                                    class="w-full text-left px-3 py-1.5 flex items-center justify-between cursor-pointer"
                                >
                                    <span>? Perempuan</span>
                                    <svg x-show="filterGender === 'perempuan'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </button>
                            </div>
                        </div>
                    </flux:table.column>

                    <flux:table.column @click="toggleSort('status')" align="center" class="cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 select-none">
                        <span class="inline-flex items-center justify-center gap-1">Status
                            <svg x-show="sortField==='status' && sortDir==='asc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                            <svg x-show="sortField==='status' && sortDir==='desc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            <svg x-show="sortField!=='status'" class="h-3.5 w-3.5 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
                        </span>
                    </flux:table.column>
                    <flux:table.column>No. HP</flux:table.column>
                    <flux:table.column align="end">Aksi</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    <template x-if="displayed.length === 0">
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center text-zinc-400 text-sm py-8">Tidak ada personil yang cocok.</flux:table.cell>
                        </flux:table.row>
                    </template>
                    <template x-for="p in displayed" :key="p.id">
                        <flux:table.row 
                            x-bind:class="p.is_highlighted ? 'highlight-row animate-highlight bg-blue-50 dark:bg-blue-950/30' : ''"
                        >
                            <flux:table.cell class="font-medium text-zinc-900 dark:text-zinc-100" x-text="p.nama"></flux:table.cell>
                            <flux:table.cell class="text-zinc-500" x-text="p.tim"></flux:table.cell>
                            <flux:table.cell>
                                <span x-show="p.jenis_kelamin === 'laki-laki'" class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/50 px-2 py-0.5 rounded-md">
                                    <svg class="h-3.5 w-3.5" viewBox="-5 -5 45 115" fill="currentColor">
                                        <path d="M30.39,35.84v66.43a4.87,4.87,0,0,1-4.85,4.86h0a4.88,4.88,0,0,1-4.86-4.86V63.21H18.77v39.06a4.87,4.87,0,0,1-4.85,4.86h0a4.88,4.88,0,0,1-4.86-4.86V35.84H7.37V60.25a3.7,3.7,0,0,1-3.69,3.68h0A3.7,3.7,0,0,1,0,60.25V34c0-4.27,1.44-7.27,4.05-9.24,4.5-3.39,26.72-3.39,31.22,0,2.62,2,4.07,5,4.06,9.24V60.25a3.7,3.7,0,0,1-3.68,3.68h0A3.7,3.7,0,0,1,32,60.25V35.84Z M19.66,5.56a8.78,8.78,0,1,1-8.78,8.78,8.78,8.78,0,0,1,8.78-8.78Z"/>
                                    </svg>
                                    Laki-laki
                                </span>
                                <span x-show="p.jenis_kelamin === 'perempuan'" class="inline-flex items-center gap-1 text-xs font-medium text-pink-600 dark:text-pink-400 bg-pink-50 dark:bg-pink-950/40 border border-pink-200 dark:border-pink-800/50 px-2 py-0.5 rounded-md">
                                    <svg class="h-3.5 w-3.5" viewBox="66 -6 60 116" fill="currentColor">
                                        <path d="M115,33c.06.15.11.3.16.46l7.59,27a3.77,3.77,0,1,1-7.27,2L108,35.81l-.11,0H106.3v1.69l10,39.74h-10v25a4.87,4.87,0,0,1-4.85,4.86h0a4.88,4.88,0,0,1-4.85-4.86v-25H94.68v25a4.88,4.88,0,0,1-4.86,4.86h0A4.87,4.87,0,0,1,85,102.27v-25H74.38L85,36.48v-.64h-1.7l-.14,0L75.64,62.46a3.77,3.77,0,1,1-7.27-2L75.8,34l-.08,0c1.14-4.25,2.09-7.27,4.71-9.24,4.5-3.39,26.24-3.39,30.75,0C113.6,26.56,114,29,115,33Z M95.57,2.78a8.78,8.78,0,1,1-8.78,8.78,8.78,8.78,0,0,1,8.78-8.78Z"/>
                                    </svg>
                                    Perempuan
                                </span>
                            </flux:table.cell>
                            <flux:table.cell align="center">
                                <span :class="p.status==='aktif' ? 'bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400' : 'bg-red-100 dark:bg-red-950/40 text-red-700 dark:text-red-400'"
                                      class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                      x-text="p.status==='aktif' ? 'Aktif' : 'Nonaktif'"></span>
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-500" x-text="p.no_hp || '-'"></flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil" @click="$wire.bukaFormEdit(p.id)">Edit</flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger" @click="$wire.konfirmasiHapus(p.id)">Hapus</flux:menu.item>
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

    <flux:modal name="form-personil" class="w-full sm:w-[480px] max-w-md" 
        x-on:close="$wire.editingId = null; $wire.tim_id = ''; $wire.nama = ''; $wire.jenis_kelamin = 'laki-laki'; $wire.no_hp = ''; $wire.status = 'aktif';">
        <div class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editingId ? 'Edit Personil' : 'Tambah Personil' }}</flux:heading>
            <form wire:submit="simpan" class="flex flex-col gap-4">
                <div>
                    <x-searchable-select
                        name="tim_id"
                        label="Tim"
                        placeholder="Pilih tim..."
                        wire:model="tim_id"
                        :model-value="$tim_id"
                        :required="true"
                        :options="$this->timOptions->map(fn($t) => ['value' => $t->id, 'label' => $t->nama_tim])->toArray()"
                    />
                    <flux:error name="tim_id" />
                </div>
                <div>
                    <flux:input wire:model="nama" label="Nama Lengkap" placeholder="cth. Budi Santoso" required />
                    <flux:error name="nama" />
                </div>
                <div>
                    <label class="text-sm font-medium text-zinc-950 dark:text-white">Jenis Kelamin <span class="text-red-500">*</span></label>
                    <div class="relative mt-1.5" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open"
                            :class="open ? 'ring-2 ring-brand border-brand' : 'border-zinc-300 dark:border-zinc-600 hover:border-zinc-400'"
                            class="w-full flex items-center justify-between gap-2 rounded-lg border bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-left transition-colors">
                            <span class="truncate" :class="$wire.jenis_kelamin ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-400'" 
                                x-text="$wire.jenis_kelamin === 'laki-laki' ? 'Laki-laki (Dapat ditugaskan adzan/kitab)' : ($wire.jenis_kelamin === 'perempuan' ? 'Perempuan' : 'Pilih jenis kelamin')"></span>
                            <svg class="h-4 w-4 text-zinc-400 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" x-transition class="absolute z-50 mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg py-1">
                            <template x-for="opt in [{value:'laki-laki',label:'Laki-laki (Dapat ditugaskan adzan/kitab)'},{value:'perempuan',label:'Perempuan'}]" :key="opt.value">
                                <button type="button" @click="$wire.jenis_kelamin = opt.value; open = false"
                                    :class="$wire.jenis_kelamin === opt.value ? 'bg-brand/10 text-brand font-medium' : 'text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700'"
                                    class="w-full text-left px-3 py-2 text-sm flex items-center justify-between gap-2">
                                    <span x-text="opt.label" class="truncate"></span>
                                    <svg x-show="$wire.jenis_kelamin === opt.value" class="h-4 w-4 text-brand shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </button>
                            </template>
                        </div>
                    </div>
                    <flux:error name="jenis_kelamin" />
                </div>
                <div>
                    <flux:input 
                        wire:model="no_hp" 
                        label="No. HP" 
                        placeholder="cth. 08123456789" 
                        type="tel"
                        inputmode="numeric"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                    />
                    <flux:error name="no_hp" />
                </div>
                <div>
                    <label class="text-sm font-medium text-zinc-950 dark:text-white">Status <span class="text-red-500">*</span></label>
                    <div class="relative mt-1.5" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open"
                            :class="open ? 'ring-2 ring-brand border-brand' : 'border-zinc-300 dark:border-zinc-600 hover:border-zinc-400'"
                            class="w-full flex items-center justify-between gap-2 rounded-lg border bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-left transition-colors">
                            <span class="truncate" :class="$wire.status ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-400'" 
                                x-text="$wire.status === 'aktif' ? 'Aktif' : ($wire.status === 'nonaktif' ? 'Nonaktif' : 'Pilih status')"></span>
                            <svg class="h-4 w-4 text-zinc-400 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" x-transition class="absolute z-50 mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg py-1">
                            <template x-for="opt in [{value:'aktif',label:'Aktif'},{value:'nonaktif',label:'Nonaktif'}]" :key="opt.value">
                                <button type="button" @click="$wire.status = opt.value; open = false"
                                    :class="$wire.status === opt.value ? 'bg-brand/10 text-brand font-medium' : 'text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700'"
                                    class="w-full text-left px-3 py-2 text-sm flex items-center justify-between gap-2">
                                    <span x-text="opt.label" class="truncate"></span>
                                    <svg x-show="$wire.status === opt.value" class="h-4 w-4 text-brand shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </button>
                            </template>
                        </div>
                    </div>
                    <flux:error name="status" />
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="simpan">
                        <span wire:loading.remove wire:target="simpan">Simpan</span>
                        <span wire:loading wire:target="simpan">Menyimpan...</span>
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

<style>
@keyframes highlight {
    0%, 100% { background-color: transparent; }
    50% { background-color: rgb(239 246 255 / 0.8); }
}

@media (prefers-color-scheme: dark) {
    @keyframes highlight {
        0%, 100% { background-color: transparent; }
        50% { background-color: rgb(23 37 84 / 0.3); }
    }
}

.animate-highlight {
    animation: highlight 2s ease-in-out 3;
}
</style>
