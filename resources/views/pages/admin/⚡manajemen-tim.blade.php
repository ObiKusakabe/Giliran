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
    public string $status     = 'active'; // Default status
    public ?int $hapusId      = null;
    public string $filterStatus = 'active'; // 'all', 'active', 'inactive', 'has_account', 'no_account'
    public ?int $toggleStatusId = null; // ID tim yang akan toggle status

    public ?int $generateTimId = null;
    public ?string $generateTimNama = null;

    public int $jumlahGenerate = 1;
    public array $akunBaruGenerated = [];

    #[Computed]
    public function semuaTim(): array
    {
        return Tim::with('user')->withCount('personil')
            ->orderBy('nama_tim')
            ->get()
            ->map(fn ($t) => [
                'id'             => $t->id,
                'nama_tim'       => $t->nama_tim,
                'keterangan'     => $t->keterangan ?? '',
                'status'         => $t->status,
                'personil_count' => $t->personil_count,
                'has_account'    => $t->user !== null,
                'account_user'   => $t->user?->username ?? $t->user?->email,
            ])
            ->toArray();
    }

    #[Computed]
    public function akunMenunggu(): array
    {
        return \App\Models\User::where('role', 'tim')
            ->whereNull('tim_id')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'username' => $u->username ?? $u->email,
                'created_at' => $u->created_at?->translatedFormat('d M Y, H:i') ?? '—',
            ])
            ->toArray();
    }

    #[Computed]
    public function totalTim(): int
    {
        return Tim::count();
    }

    #[Computed]
    public function totalTimAktif(): int
    {
        return Tim::where('status', 'active')->count();
    }

    #[Computed]
    public function totalTimInactive(): int
    {
        return Tim::where('status', 'inactive')->count();
    }

    #[Computed]
    public function totalTimAkun(): int
    {
        return Tim::has('user')->count();
    }

    #[Computed]
    public function totalMenungguOnboarding(): int
    {
        return \App\Models\User::where('role', 'tim')->whereNull('tim_id')->count();
    }

    public function bukaFormTambah(): void
    {
        $this->resetForm();
        $this->modal('form-tim')->show();
    }

    public function bukaModalGenerateAkunBaru(): void
    {
        $this->jumlahGenerate = 1;
        $this->akunBaruGenerated = [];
        $this->modal('modal-generate-standalone')->show();
    }

    public function generateAkunStandalone(): void
    {
        $this->validate([
            'jumlahGenerate' => 'required|integer|min:1|max:10',
        ]);

        $generator = app(\App\Services\TimAccountGenerator::class);
        $createdUsers = $generator->createMultipleStandaloneAccounts($this->jumlahGenerate);

        $this->akunBaruGenerated = array_map(fn ($u) => [
            'username' => $u->username,
            'email' => $u->email,
            'password' => 'inovindojaya',
        ], $createdUsers);

        Flux::toast(
            variant: 'success',
            text: count($createdUsers)." Akun tim berhasil di-generate! Berikan kredensial ke anak magang."
        );

        unset($this->akunMenunggu, $this->totalMenungguOnboarding);
    }

    public function hapusAkunStandalone(int $id): void
    {
        $user = \App\Models\User::where('role', 'tim')->whereNull('tim_id')->find($id);
        if ($user) {
            $user->delete();
            Flux::toast(variant: 'success', text: 'Akun tim yang belum terpakai berhasil dihapus.');
            unset($this->akunMenunggu, $this->totalMenungguOnboarding);
        }
    }

    public function bukaFormEdit(int $id): void
    {
        $tim              = Tim::findOrFail($id);
        $this->editingId  = $id;
        $this->nama_tim   = $tim->nama_tim;
        $this->keterangan = $tim->keterangan ?? '';
        $this->status     = $tim->status;
        $this->modal('form-tim')->show();
    }

    public function simpan(): void
    {
        $this->validate([
            'nama_tim'   => 'required|string|max:100',
            'keterangan' => 'nullable|string',
            'status'     => 'required|in:active,inactive',
        ]);

        // Auto-generate nama dengan suffix gelombang jika create baru
        $namaTim = $this->nama_tim;
        if (!$this->editingId && !str_contains($this->nama_tim, '-' . now()->year . '-')) {
            $naming = app(\App\Services\TimNamingService::class);
            $namaTim = $naming->generateNamaGelombang($this->nama_tim);
        }

        if ($this->editingId) {
            Tim::findOrFail($this->editingId)->update([
                'nama_tim'   => $namaTim,
                'keterangan' => $this->keterangan ?: null,
                'status'     => $this->status,
            ]);
            Flux::toast(variant: 'success', text: 'Tim berhasil diperbarui.');
        } else {
            Tim::create([
                'nama_tim'   => $namaTim,
                'keterangan' => $this->keterangan ?: null,
                'status'     => $this->status,
            ]);
            Flux::toast(variant: 'success', text: 'Tim berhasil ditambahkan dengan nama: ' . $namaTim);
        }

        $this->modal('form-tim')->close();
        $this->resetForm();
        unset($this->semuaTim, $this->totalTim, $this->totalTimAktif, $this->totalTimInactive);
    }

    public function konfirmasiToggleStatus(int $id): void
    {
        $this->toggleStatusId = $id;
        $this->modal('konfirmasi-toggle-status')->show();
    }

    public function toggleStatus(): void
    {
        if (!$this->toggleStatusId) {
            return;
        }

        $tim = Tim::findOrFail($this->toggleStatusId);
        $newStatus = $tim->status === 'active' ? 'inactive' : 'active';

        $tim->update(['status' => $newStatus]);

        $message = $newStatus === 'active'
            ? "Status tim '{$tim->nama_tim}' diubah menjadi Active."
            : "Status tim '{$tim->nama_tim}' diubah menjadi Inactive.";

        Flux::toast(variant: 'success', text: $message);

        $this->modal('konfirmasi-toggle-status')->close();
        $this->toggleStatusId = null;
        unset($this->semuaTim, $this->totalTim, $this->totalTimAktif, $this->totalTimInactive);
    }

    public function konfirmasiGenerateAkun(int $timId): void
    {
        $tim = Tim::findOrFail($timId);
        $this->generateTimId = $tim->id;
        $this->generateTimNama = $tim->nama_tim;
        $this->modal('konfirmasi-generate-akun')->show();
    }

    public function generateAkun(): void
    {
        if (! $this->generateTimId) {
            return;
        }

        $tim = Tim::findOrFail($this->generateTimId);

        if ($tim->user) {
            Flux::toast(variant: 'warning', text: 'Tim ini sudah memiliki akun login.');
            $this->modal('konfirmasi-generate-akun')->close();
            return;
        }

        $generator = app(\App\Services\TimAccountGenerator::class);
        $user = $generator->createAccount($tim);

        Flux::toast(
            variant: 'success',
            text: "Akun tim berhasil dibuat! Username: {$user->username} | Email: {$user->email}"
        );

        $this->modal('konfirmasi-generate-akun')->close();
        $this->generateTimId = null;
        $this->generateTimNama = null;
        unset($this->semuaTim, $this->totalTimAkun);
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
        unset($this->semuaTim, $this->totalTim, $this->totalTimAktif, $this->totalTimInactive, $this->totalTimAkun);
    }

    private function resetForm(): void
    {
        $this->editingId  = null;
        $this->nama_tim   = '';
        $this->keterangan = '';
        $this->status     = 'active'; // Reset to default
        $this->resetValidation();
    }
}; ?>

<div
    x-data="{
        rows: @js($this->semuaTim),
        q: '',
        filterStatus: 'active',
        page: 1,
        perPage: 10,
        sortField: 'nama_tim',
        sortDir: 'asc',

        get filtered() {
            let data = [...this.rows];
            if (this.q.trim()) {
                const qLow = this.q.toLowerCase();
                data = data.filter(r => r.nama_tim.toLowerCase().includes(qLow) || (r.keterangan && r.keterangan.toLowerCase().includes(qLow)));
            }
            if (this.filterStatus === 'active') {
                data = data.filter(r => r.status === 'active');
            } else if (this.filterStatus === 'inactive') {
                data = data.filter(r => r.status === 'inactive');
            } else if (this.filterStatus === 'has_account') {
                data = data.filter(r => r.has_account);
            } else if (this.filterStatus === 'no_account') {
                data = data.filter(r => !r.has_account);
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
    x-effect="if (q !== undefined || filterStatus !== undefined) page = 1"
    class="flex flex-col gap-6"
>
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Tim</flux:heading>
            <flux:text class="text-zinc-500">Kelola data tim peserta PKL/magang dan akun login mandiri.</flux:text>
        </div>
        <div class="flex items-center gap-2.5">
            <flux:button variant="subtle" wire:click="bukaModalGenerateAkunBaru" icon="sparkles">
                Generate Akun Baru
            </flux:button>
            <flux:button variant="primary" wire:click="bukaFormTambah" icon="plus" class="flex-shrink-0">
                Tambah Tim
            </flux:button>
        </div>
    </div>

    {{-- Quick Info Cards with Watermark Icons --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="relative z-10 pr-6">
                <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Total Tim</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                    {{ $this->totalTim }}
                </flux:heading>
            </div>
            <flux:icon icon="user-group" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-blue-500/10 dark:text-blue-400/10 pointer-events-none" />
        </flux:card>

        <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="relative z-10 pr-6">
                <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Tim Aktif</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                    {{ $this->totalTimAktif }}
                </flux:heading>
            </div>
            <flux:icon icon="check-circle" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-emerald-500/10 dark:text-emerald-400/10 pointer-events-none" />
        </flux:card>

        <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="relative z-10 pr-6">
                <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Memiliki Akun</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-purple-600 dark:text-purple-400">
                    {{ $this->totalTimAkun }}
                </flux:heading>
            </div>
            <flux:icon icon="key" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-purple-500/10 dark:text-purple-400/10 pointer-events-none" />
        </flux:card>

        <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="relative z-10 pr-6">
                <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Menunggu Onboarding</flux:text>
                <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-amber-600 dark:text-amber-400">
                    {{ $this->totalMenungguOnboarding }}
                </flux:heading>
            </div>
            <flux:icon icon="clock" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-amber-500/10 dark:text-amber-400/10 pointer-events-none" />
        </flux:card>
    </div>

    {{-- Akun Siap / Menunggu Onboarding Section --}}
    @if ($this->totalMenungguOnboarding > 0)
        <flux:card class="border-amber-200 dark:border-amber-900/50 bg-amber-50/30 dark:bg-amber-950/10 p-5 space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div class="flex items-center gap-2.5">
                    <div class="flex size-7 items-center justify-center rounded-lg bg-amber-500 text-white font-bold text-xs">
                        <flux:icon icon="key" class="size-4" />
                    </div>
                    <div>
                        <h4 class="font-semibold text-sm text-zinc-900 dark:text-white">Akun Tim Baru (Menunggu Onboarding Mandiri)</h4>
                        <p class="text-xs text-zinc-500">Kredensial ini siap diberikan ke anak magang. Mereka akan mengisi profil tim saat pertama kali login.</p>
                    </div>
                </div>
                <flux:badge color="amber" size="sm">{{ $this->totalMenungguOnboarding }} Akun Siap</flux:badge>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5 pt-2">
                @foreach ($this->akunMenunggu as $akun)
                    <div class="flex items-center justify-between p-3 rounded-xl bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 shadow-sm text-xs" wire:key="akun-stand-{{ $akun['id'] }}">
                        <div class="space-y-0.5 min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="font-mono font-bold text-purple-700 dark:text-purple-300">{{ $akun['username'] }}</span>
                                <span class="text-[10px] text-zinc-400">• {{ $akun['created_at'] }}</span>
                            </div>
                            <div class="text-zinc-500">
                                Password: <code class="font-mono bg-zinc-100 dark:bg-zinc-700 px-1 py-0.5 rounded text-zinc-800 dark:text-zinc-200">inovindojaya</code>
                            </div>
                        </div>

                        <div class="flex items-center gap-1">
                            <flux:button
                                size="xs"
                                variant="ghost"
                                icon="trash"
                                class="text-red-500 hover:text-red-600 cursor-pointer"
                                wire:click="hapusAkunStandalone({{ $akun['id'] }})"
                                title="Hapus akun ini"
                            />
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif

    {{-- Search & Filter Bar (Search kiri, Filter kanan matching Personil & Ruangan) --}}
    <div class="flex gap-3">
        <div class="relative flex-1">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input x-model="q" type="text" placeholder="Cari nama tim atau keterangan…"
                class="w-full pl-9 pr-4 py-2 rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 text-sm focus:outline-none focus:ring-2 focus:ring-brand dark:text-zinc-100" />
            <button x-show="q" @click="q = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div
            x-data="{ open: false }"
            @click.outside="open = false"
            class="relative w-44 sm:w-48"
        >
            <button type="button" @click="open = !open"
                :class="open ? 'ring-2 ring-brand border-brand' : 'border-zinc-300 dark:border-zinc-600 hover:border-zinc-400 dark:hover:border-zinc-500'"
                class="w-full flex items-center justify-between gap-2 rounded-lg border bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-left transition-colors focus:outline-none cursor-pointer"
            >
                <span x-text="filterStatus === '' ? 'Semua Tim' : (filterStatus === 'active' ? 'Tim Aktif' : (filterStatus === 'inactive' ? 'Inactive' : (filterStatus === 'has_account' ? 'Punya Akun' : 'Belum Ada Akun')))"
                      class="text-zinc-900 dark:text-zinc-100 truncate"></span>
                <svg class="h-4 w-4 text-zinc-400 flex-shrink-0 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-75" x-transition:leave-end="opacity-0"
                 class="absolute right-0 z-50 mt-1 w-52 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg py-1">
                <template x-for="opt in [
                    {value:'active', label:'Tim Aktif'},
                    {value:'', label:'Semua Tim'},
                    {value:'inactive', label:'Tim Inactive'},
                    {value:'has_account', label:'Memiliki Akun'},
                    {value:'no_account', label:'Belum Ada Akun'}
                ]" :key="opt.value">
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

    <flux:card class="p-0 overflow-hidden border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs">
        {{-- Card-list: mobile only (< sm) --}}
        <div class="sm:hidden divide-y divide-zinc-100 dark:divide-zinc-800">
            <template x-if="displayed.length === 0">
                <div class="px-4 py-8 text-center text-zinc-400 text-sm" x-text="q ? 'Tidak ada tim yang cocok.' : 'Belum ada tim.'"></div>
            </template>
            <template x-for="tim in displayed" :key="tim.id">
                <div class="flex items-center gap-3 px-4 py-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate" x-text="tim.nama_tim"></p>
                            <span x-show="tim.status === 'active'" class="inline-flex items-center rounded-full bg-green-100 dark:bg-green-900/30 px-2 py-0.5 text-xs font-medium text-green-700 dark:text-green-400">Active</span>
                            <span x-show="tim.status === 'inactive'" class="inline-flex items-center rounded-full bg-zinc-100 dark:bg-zinc-700 px-2 py-0.5 text-xs font-medium text-zinc-600 dark:text-zinc-400">Inactive</span>
                        </div>
                        <p class="text-xs text-zinc-500 truncate mt-0.5" x-text="tim.keterangan || '—'"></p>
                        <div class="flex items-center gap-3 mt-1.5 text-xs">
                            <span class="text-zinc-400">Personil: <strong class="text-zinc-700 dark:text-zinc-300" x-text="tim.personil_count"></strong></span>
                            <template x-if="tim.has_account">
                                <span class="inline-flex items-center gap-1 text-purple-600 dark:text-purple-400 font-medium">
                                    <flux:icon icon="key" class="size-3" />
                                    <span x-text="tim.account_user"></span>
                                </span>
                            </template>
                            <template x-if="!tim.has_account">
                                <span class="text-zinc-400 italic">Belum ada akun</span>
                            </template>
                        </div>
                    </div>
                    <flux:dropdown>
                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                        <flux:menu>
                            <template x-if="!tim.has_account">
                                <flux:menu.item icon="key" @click="$wire.konfirmasiGenerateAkun(tim.id)">Generate Akun</flux:menu.item>
                            </template>
                            <flux:menu.item icon="pencil" @click="$wire.bukaFormEdit(tim.id)">Edit</flux:menu.item>
                            <template x-if="tim.status === 'active'">
                                <flux:menu.item icon="x-circle" @click="$wire.konfirmasiToggleStatus(tim.id)">Set Inactive</flux:menu.item>
                            </template>
                            <template x-if="tim.status === 'inactive'">
                                <flux:menu.item icon="check-circle" @click="$wire.konfirmasiToggleStatus(tim.id)">Set Active</flux:menu.item>
                            </template>
                            <flux:menu.separator />
                            <flux:menu.item icon="trash" variant="danger" @click="$wire.konfirmasiHapus(tim.id)">Hapus</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </div>
            </template>
        </div>

        {{-- Tabel: sm dan lebih lebar --}}
        <div class="hidden sm:block px-5">
            <flux:table>
                <flux:table.columns class="sticky top-0 z-10 bg-white/95 dark:bg-zinc-800/95 backdrop-blur-md border-b border-zinc-200 dark:border-zinc-700">
                    <flux:table.column @click="toggleSort('nama_tim')" class="cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 select-none">
                        <span class="inline-flex items-center gap-1">Nama Tim
                            <svg x-show="sortField==='nama_tim' && sortDir==='asc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                            <svg x-show="sortField==='nama_tim' && sortDir==='desc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            <svg x-show="sortField!=='nama_tim'" class="h-3.5 w-3.5 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
                        </span>
                    </flux:table.column>
                    <flux:table.column @click="toggleSort('status')" class="cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 select-none">
                        <span class="inline-flex items-center gap-1">Status
                            <svg x-show="sortField==='status' && sortDir==='asc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                            <svg x-show="sortField==='status' && sortDir==='desc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            <svg x-show="sortField!=='status'" class="h-3.5 w-3.5 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
                        </span>
                    </flux:table.column>
                    <flux:table.column @click="toggleSort('has_account')" class="cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 select-none">
                        <span class="inline-flex items-center gap-1">Akun Login
                            <svg x-show="sortField==='has_account' && sortDir==='asc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                            <svg x-show="sortField==='has_account' && sortDir==='desc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            <svg x-show="sortField!=='has_account'" class="h-3.5 w-3.5 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
                        </span>
                    </flux:table.column>
                    <flux:table.column>Keterangan</flux:table.column>
                    <flux:table.column @click="toggleSort('personil_count')" align="center" class="cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 select-none">
                        <span class="inline-flex items-center justify-center gap-1">Personil
                            <svg x-show="sortField==='personil_count' && sortDir==='asc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                            <svg x-show="sortField==='personil_count' && sortDir==='desc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            <svg x-show="sortField!=='personil_count'" class="h-3.5 w-3.5 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
                        </span>
                    </flux:table.column>
                    <flux:table.column align="end">Aksi</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    <template x-if="displayed.length === 0">
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center text-zinc-400 text-sm py-8" x-text="q ? 'Tidak ada tim yang cocok.' : 'Belum ada tim.'"></flux:table.cell>
                        </flux:table.row>
                    </template>
                    <template x-for="tim in displayed" :key="tim.id">
                        <flux:table.row>
                            <flux:table.cell class="font-medium text-zinc-900 dark:text-zinc-100" x-text="tim.nama_tim"></flux:table.cell>
                            <flux:table.cell>
                                <span x-show="tim.status === 'active'" class="inline-flex items-center rounded-full bg-green-100 dark:bg-green-900/30 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:text-green-400">Active</span>
                                <span x-show="tim.status === 'inactive'" class="inline-flex items-center rounded-full bg-zinc-100 dark:bg-zinc-700 px-2.5 py-0.5 text-xs font-medium text-zinc-600 dark:text-zinc-400">Inactive</span>
                            </flux:table.cell>
                            <flux:table.cell>
                                <template x-if="tim.has_account">
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-purple-50 dark:bg-purple-950/40 border border-purple-200 dark:border-purple-800/50 px-2.5 py-1 text-xs font-medium text-purple-700 dark:text-purple-300">
                                        <flux:icon icon="key" class="size-3.5 text-purple-500" />
                                        <span x-text="tim.account_user"></span>
                                    </span>
                                </template>
                                <template x-if="!tim.has_account">
                                    <flux:button size="xs" variant="subtle" icon="key" @click="$wire.konfirmasiGenerateAkun(tim.id)">
                                        Generate Akun
                                    </flux:button>
                                </template>
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-500 max-w-xs truncate" x-text="tim.keterangan || '—'"></flux:table.cell>
                            <flux:table.cell align="center">
                                <span class="inline-flex items-center rounded-full bg-zinc-100 dark:bg-zinc-700 px-2.5 py-0.5 text-xs font-medium text-zinc-700 dark:text-zinc-300" x-text="tim.personil_count"></span>
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                    <flux:menu>
                                        <template x-if="!tim.has_account">
                                            <flux:menu.item icon="key" @click="$wire.konfirmasiGenerateAkun(tim.id)">Generate Akun</flux:menu.item>
                                        </template>
                                        <flux:menu.item icon="pencil" @click="$wire.bukaFormEdit(tim.id)">Edit</flux:menu.item>
                                        <template x-if="tim.status === 'active'">
                                            <flux:menu.item icon="x-circle" @click="$wire.konfirmasiToggleStatus(tim.id)">Set Inactive</flux:menu.item>
                                        </template>
                                        <template x-if="tim.status === 'inactive'">
                                            <flux:menu.item icon="check-circle" @click="$wire.konfirmasiToggleStatus(tim.id)">Set Active</flux:menu.item>
                                        </template>
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger" @click="$wire.konfirmasiHapus(tim.id)">Hapus</flux:menu.item>
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

    {{-- Modal Form Tim --}}
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

    {{-- Modal Hapus Tim --}}
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

    {{-- Modal Konfirmasi Toggle Status --}}
    <flux:modal name="konfirmasi-toggle-status" class="max-w-md">
        <div class="flex flex-col gap-4 p-1">
            <div>
                <flux:heading size="lg">Konfirmasi Ubah Status Tim</flux:heading>
                <flux:text class="mt-1 text-zinc-500">
                    Tim yang dinonaktifkan tidak akan masuk jadwal scheduling dan tidak bisa login. 
                    Tim yang diaktifkan kembali akan masuk jadwal scheduling dan bisa login.
                </flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button 
                    variant="primary"
                    wire:click="toggleStatus" 
                    wire:loading.attr="disabled" 
                    wire:target="toggleStatus"
                >
                    Ya, Ubah Status
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal Konfirmasi Generate Akun untuk Tim Tertentu --}}
    <flux:modal name="konfirmasi-generate-akun" class="max-w-md">
        <div class="flex flex-col gap-4 p-1">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-300">
                    <flux:icon icon="key" class="size-5" />
                </div>
                <div>
                    <flux:heading size="lg">Generate Akun Tim</flux:heading>
                    <flux:text class="text-xs text-zinc-500">Buat akun login otomatis untuk tim magang</flux:text>
                </div>
            </div>

            <div class="rounded-xl bg-zinc-50 dark:bg-zinc-800/60 p-4 border border-zinc-200 dark:border-zinc-700 space-y-2.5 text-xs">
                <div class="flex justify-between">
                    <span class="text-zinc-500">Nama Tim:</span>
                    <span class="font-medium text-zinc-900 dark:text-white">{{ $generateTimNama }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-zinc-500">Format Username:</span>
                    <span class="font-mono text-purple-600 dark:text-purple-400 font-semibold">{{ now()->format('Y_m') }}_XXX</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-zinc-500">Password Bawaan:</span>
                    <span class="font-mono text-zinc-900 dark:text-zinc-100 font-medium">inovindojaya</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-zinc-500">Role Akses:</span>
                    <span class="font-medium text-emerald-600 dark:text-emerald-400">Tim (Portal Tim)</span>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button 
                    variant="primary"
                    wire:click="generateAkun" 
                    wire:loading.attr="disabled" 
                    wire:target="generateAkun"
                    icon="key"
                >
                    <span wire:loading.remove wire:target="generateAkun">Buat Akun Sekarang</span>
                    <span wire:loading wire:target="generateAkun">Membuat Akun…</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal Generate Akun Standalone / Batch --}}
    <flux:modal name="modal-generate-standalone" class="max-w-lg">
        <div class="flex flex-col gap-5 p-1" x-data="{ copied: false }">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-2xl bg-gradient-to-br from-purple-500 to-indigo-600 text-white shadow-md">
                    <flux:icon icon="sparkles" class="size-5" />
                </div>
                <div>
                    <flux:heading size="lg">Generate Akun Tim Magang</flux:heading>
                    <flux:text class="text-xs text-zinc-500">Buat kredensial akun kosong yang akan diisi sendiri oleh anak magang saat login.</flux:text>
                </div>
            </div>

            @if (empty($akunBaruGenerated))
                <form wire:submit="generateAkunStandalone" class="space-y-4">
                    <div class="rounded-xl bg-zinc-50 dark:bg-zinc-800/60 p-4 border border-zinc-200 dark:border-zinc-700 space-y-2 text-xs text-zinc-600 dark:text-zinc-300">
                        <div class="font-medium text-zinc-900 dark:text-white flex items-center gap-1.5">
                            <flux:icon icon="information-circle" class="size-4 text-blue-500" />
                            Alur Onboarding Otomatis:
                        </div>
                        <ul class="list-disc list-inside space-y-1 text-zinc-500 dark:text-zinc-400 pl-1">
                            <li>Format username otomatis: <code class="font-mono text-purple-600 dark:text-purple-400 font-semibold">{{ now()->format('Y_m') }}_XXX</code></li>
                            <li>Password awal seragam: <code class="font-mono text-zinc-900 dark:text-zinc-100 font-semibold">inovindojaya</code></li>
                            <li>Saat login pertama kali, anak magang langsung diarahkan mengisi nama kampus & anggota tim.</li>
                        </ul>
                    </div>

                    <flux:field>
                        <flux:label>Jumlah Akun yang Ingin Dibuat</flux:label>
                        <flux:select wire:model="jumlahGenerate">
                            @for ($i = 1; $i <= 10; $i++)
                                <option value="{{ $i }}">{{ $i }} Akun Tim</option>
                            @endfor
                        </flux:select>
                    </flux:field>

                    <div class="flex justify-end gap-2 pt-2">
                        <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="generateAkunStandalone" icon="sparkles">
                            <span wire:loading.remove wire:target="generateAkunStandalone">Generate Sekarang</span>
                            <span wire:loading wire:target="generateAkunStandalone">Memproses…</span>
                        </flux:button>
                    </div>
                </form>
            @else
                <div class="space-y-4">
                    <div class="rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 p-3.5 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <flux:icon icon="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400" />
                            <span class="text-xs font-semibold text-emerald-800 dark:text-emerald-200">
                                {{ count($akunBaruGenerated) }} Akun Berhasil Dibuat!
                            </span>
                        </div>

                        <button
                            type="button"
                            @click="
                                let text = @js(collect($akunBaruGenerated)->map(fn($a) => 'Username: ' . $a['username'] . ' | Password: ' . $a['password'] . ' (Login di: ' . url('/login') . ')')->implode(PHP_EOL));
                                navigator.clipboard.writeText(text);
                                copied = true;
                                setTimeout(() => copied = false, 2500);
                            "
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium transition cursor-pointer"
                        >
                            <flux:icon icon="clipboard-document-check" class="size-3.5" />
                            <span x-text="copied ? 'Tersalin ke Clipboard!' : 'Salin Semua Kredensial'"></span>
                        </button>
                    </div>

                    <div class="max-h-60 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800">
                        @foreach ($akunBaruGenerated as $acc)
                            <div class="p-3 flex items-center justify-between text-xs">
                                <div>
                                    <div class="font-mono font-bold text-purple-600 dark:text-purple-400">{{ $acc['username'] }}</div>
                                    <div class="text-zinc-400 text-[11px]">Email: {{ $acc['email'] }}</div>
                                </div>
                                <div class="text-right">
                                    <span class="font-mono bg-zinc-100 dark:bg-zinc-700 px-2 py-0.5 rounded text-zinc-800 dark:text-zinc-200 font-medium">
                                        {{ $acc['password'] }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex justify-end pt-2">
                        <flux:modal.close><flux:button variant="primary">Selesai</flux:button></flux:modal.close>
                    </div>
                </div>
            @endif
        </div>
    </flux:modal>
</div>
