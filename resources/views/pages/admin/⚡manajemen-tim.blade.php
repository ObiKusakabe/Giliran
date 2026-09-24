<?php

use App\Models\Tim;
use App\Models\User;
use App\Services\TimAccountGenerator;
use App\Services\TimNamingService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tim')] #[Layout('layouts.admin')] class extends Component
{
    public ?int $editingId = null;

    public string $nama_tim = '';

    public string $keterangan = '';

    public string $status = 'active'; // Default status

    public ?int $hapusId = null;

    public string $filterStatus = 'active'; // 'all', 'active', 'inactive', 'has_account', 'no_account'

    public ?int $toggleStatusId = null; // ID tim yang akan toggle status

    public ?int $generateTimId = null;

    public ?string $generateTimNama = null;

    public int $jumlahGenerate = 1;

    public array $akunBaruGenerated = [];

    // Foto Modal State
    public bool $showFotoModal = false;

    public ?int $fotoTimId = null;

    // Reset Password State
    public ?int $resetPasswordTimId = null;
    public ?string $resetPasswordTimNama = null;
    public ?string $resetPasswordUsername = null;
    public string $resetPasswordBaru = '';
    public string $resetPasswordKonfirmasi = '';

    public function bukaResetPassword(int $timId): void
    {
        $tim = Tim::with('user')->findOrFail($timId);
        if (! $tim->user) {
            Flux::toast(variant: 'warning', text: "Tim {$tim->nama_tim} belum memiliki akun login.");
            return;
        }

        $this->resetPasswordTimId = $timId;
        $this->resetPasswordTimNama = $tim->nama_tim;
        $this->resetPasswordUsername = $tim->user->username ?? $tim->user->email;
        $this->resetPasswordBaru = '';
        $this->resetPasswordKonfirmasi = '';
        $this->resetValidation();
        $this->modal('modal-reset-password')->show();
    }

    public function simpanResetPassword(): void
    {
        $this->validate([
            'resetPasswordBaru' => 'required|min:8',
            'resetPasswordKonfirmasi' => 'required|same:resetPasswordBaru',
        ], [
            'resetPasswordBaru.required' => 'Password baru wajib diisi.',
            'resetPasswordBaru.min' => 'Password minimal 8 karakter.',
            'resetPasswordKonfirmasi.required' => 'Konfirmasi password wajib diisi.',
            'resetPasswordKonfirmasi.same' => 'Konfirmasi password tidak sama.',
        ]);

        $tim = Tim::with('user')->findOrFail($this->resetPasswordTimId);
        if (! $tim->user) {
            Flux::toast(variant: 'danger', text: 'Akun tim tidak ditemukan.');
            return;
        }

        $tim->user->update([
            'password' => \Illuminate\Support\Facades\Hash::make($this->resetPasswordBaru),
        ]);

        Flux::toast(variant: 'success', text: "Password untuk akun tim {$tim->nama_tim} ({$this->resetPasswordUsername}) berhasil diubah.");
        $this->modal('modal-reset-password')->close();
        $this->resetPasswordTimId = null;
        $this->resetPasswordBaru = '';
        $this->resetPasswordKonfirmasi = '';
    }

    #[Computed]
    public function semuaTim(): array
    {
        // Map English day names to Indonesian lowercase
        $dayMap = [
            'Monday' => 'senin',
            'Tuesday' => 'selasa',
            'Wednesday' => 'rabu',
            'Thursday' => 'kamis',
            'Friday' => 'jumat',
            'Saturday' => 'sabtu',
            'Sunday' => 'minggu',
        ];
        
        $hariIni = $dayMap[now()->format('l')] ?? 'senin';
        
        // Get active periode_wfo_id
        $periodeAktif = \App\Models\PeriodeWfo::where('status', 'aktif')->first();
        
        return Tim::with('user')
            ->withCount('personil')
            ->when($periodeAktif, function ($query) use ($periodeAktif, $hariIni) {
                $query->leftJoin('jadwal_wfo', function ($join) use ($periodeAktif, $hariIni) {
                    $join->on('tim.id', '=', 'jadwal_wfo.tim_id')
                        ->where('jadwal_wfo.periode_wfo_id', $periodeAktif->id)
                        ->where('jadwal_wfo.hari', $hariIni);
                })
                ->selectRaw('tim.*, IF(jadwal_wfo.id IS NOT NULL, 1, 0) as is_wfo_today')
                ->orderByDesc('is_wfo_today');
            })
            ->orderBy('tim.nama_tim')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'nama_tim' => $t->nama_tim,
                'keterangan' => $t->keterangan ?? '',
                'status' => $t->status,
                'personil_count' => $t->personil_count,
                'has_account' => $t->user !== null,
                'account_user' => $t->user?->username ?? $t->user?->email,
                'foto_bersama' => $t->foto_bersama,
                'is_wfo_today' => (bool) ($t->is_wfo_today ?? false),
            ])
            ->toArray();
    }

    public function bukaFotoModal(int $timId): void
    {
        $this->fotoTimId = $timId;
        $this->showFotoModal = true;
    }

    #[Computed]
    public function selectedTim(): ?Tim
    {
        if (! $this->fotoTimId) {
            return null;
        }

        return Tim::find($this->fotoTimId);
    }

    #[Computed]
    public function akunMenunggu(): array
    {
        return User::where('role', 'tim')
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
        return User::where('role', 'tim')->whereNull('tim_id')->count();
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

        $generator = app(TimAccountGenerator::class);
        $createdUsers = $generator->createMultipleStandaloneAccounts($this->jumlahGenerate);

        $this->akunBaruGenerated = array_map(fn ($u) => [
            'username' => $u->username,
            'email' => $u->email,
            'password' => 'inovindojaya',
        ], $createdUsers);

        Flux::toast(
            variant: 'success',
            text: count($createdUsers).' Akun tim berhasil di-generate! Berikan kredensial ke anak magang.'
        );

        unset($this->akunMenunggu, $this->totalMenungguOnboarding);
    }

    public function hapusAkunStandalone(int $id): void
    {
        $user = User::where('role', 'tim')->whereNull('tim_id')->find($id);
        if ($user) {
            $user->delete();
            Flux::toast(variant: 'success', text: 'Akun tim yang belum terpakai berhasil dihapus.');
            unset($this->akunMenunggu, $this->totalMenungguOnboarding);
        }
    }

    public function bukaFormEdit(int $id): void
    {
        $tim = Tim::findOrFail($id);
        $this->editingId = $id;
        $this->nama_tim = $tim->nama_tim;
        $this->keterangan = $tim->keterangan ?? '';
        $this->status = $tim->status;
        $this->modal('form-tim')->show();
    }

    public function simpan(): void
    {
        $this->validate([
            'nama_tim' => 'required|string|max:100',
            'keterangan' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        // Auto-generate nama dengan suffix gelombang jika create baru
        $namaTim = $this->nama_tim;
        if (! $this->editingId && ! str_contains($this->nama_tim, '-'.now()->year.'-')) {
            $naming = app(TimNamingService::class);
            $namaTim = $naming->generateNamaGelombang($this->nama_tim);
        }

        if ($this->editingId) {
            Tim::findOrFail($this->editingId)->update([
                'nama_tim' => $namaTim,
                'keterangan' => $this->keterangan ?: null,
                'status' => $this->status,
            ]);
            Flux::toast(variant: 'success', text: 'Tim berhasil diperbarui.');
        } else {
            Tim::create([
                'nama_tim' => $namaTim,
                'keterangan' => $this->keterangan ?: null,
                'status' => $this->status,
            ]);
            Flux::toast(variant: 'success', text: 'Tim berhasil ditambahkan dengan nama: '.$namaTim);
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
        if (! $this->toggleStatusId) {
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

        $generator = app(TimAccountGenerator::class);
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
        $this->editingId = null;
        $this->nama_tim = '';
        $this->keterangan = '';
        $this->status = 'active'; // Reset to default
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
        sortField: 'default',
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

            if (this.sortField === 'default') {
                // Default: Tim yang WFO hari ini paling atas, lalu A-Z. Sisanya non-WFO juga urut A-Z.
                data.sort((a, b) => {
                    const wfoA = a.is_wfo_today ? 1 : 0;
                    const wfoB = b.is_wfo_today ? 1 : 0;
                    if (wfoA !== wfoB) {
                        return wfoB - wfoA;
                    }
                    return a.nama_tim.localeCompare(b.nama_tim, undefined, { sensitivity: 'base' });
                });
            } else {
                data.sort((a, b) => {
                    let va = a[this.sortField] ?? ''; let vb = b[this.sortField] ?? '';
                    if (typeof va === 'string') va = va.toLowerCase();
                    if (typeof vb === 'string') vb = vb.toLowerCase();
                    if (va < vb) return this.sortDir === 'asc' ? -1 : 1;
                    if (va > vb) return this.sortDir === 'asc' ? 1 : -1;
                    return 0;
                });
            }
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
            if (this.sortField === field) {
                if (this.sortDir === 'asc') {
                    this.sortDir = 'desc';
                } else {
                    this.sortField = 'default';
                    this.sortDir = 'asc';
                }
            } else {
                this.sortField = field;
                this.sortDir = 'asc';
            }
            this.page = 1;
        }
    }"
    x-effect="
        rows = @js($this->semuaTim);
        if (q !== undefined || filterStatus !== undefined) page = 1;
    "
    class="flex flex-col gap-6"
>
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Tim</flux:heading>
            <flux:text class="text-zinc-500">Kelola data tim peserta PKL/magang dan akun login mandiri.</flux:text>
        </div>
        <div class="flex items-center gap-2.5">
            <flux:button variant="subtle" @click="$wire.bukaModalGenerateAkunBaru()" icon="sparkles">
                Generate Akun Baru
            </flux:button>
            <flux:modal.trigger name="form-tim">
                <flux:button variant="primary" icon="plus" class="flex-shrink-0">
                    Tambah Tim
                </flux:button>
            </flux:modal.trigger>
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
                            <a 
                                :href="`/admin/personil?tim=${tim.id}`"
                                wire:navigate
                                class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate hover:text-blue-600 dark:hover:text-blue-400 transition-colors cursor-pointer underline decoration-transparent hover:decoration-current"
                                x-text="tim.nama_tim"
                                title="Lihat personil tim ini"
                            ></a>
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
                            <template x-if="tim.has_account">
                                <flux:menu.item icon="key" @click="$wire.bukaResetPassword(tim.id)">Ubah Password</flux:menu.item>
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
                            <svg x-show="sortField === 'nama_tim' && sortDir === 'asc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                            <svg x-show="sortField === 'nama_tim' && sortDir === 'desc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            <svg x-show="sortField !== 'nama_tim'" class="h-3.5 w-3.5 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
                        </span>
                    </flux:table.column>
                    <flux:table.column @click="toggleSort('status')" class="cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 select-none">
                        <span class="inline-flex items-center gap-1">Status
                            <svg x-show="sortField === 'status' && sortDir === 'asc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                            <svg x-show="sortField === 'status' && sortDir === 'desc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            <svg x-show="sortField !== 'status'" class="h-3.5 w-3.5 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
                        </span>
                    </flux:table.column>
                    <flux:table.column @click="toggleSort('has_account')" class="cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 select-none">
                        <span class="inline-flex items-center gap-1">Akun Login
                            <svg x-show="sortField === 'has_account' && sortDir === 'asc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                            <svg x-show="sortField === 'has_account' && sortDir === 'desc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            <svg x-show="sortField !== 'has_account'" class="h-3.5 w-3.5 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
                        </span>
                    </flux:table.column>
                    <flux:table.column>Keterangan</flux:table.column>
                    <flux:table.column @click="toggleSort('personil_count')" align="center" class="cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 select-none">
                        <span class="inline-flex items-center justify-center gap-1">Personil
                            <svg x-show="sortField === 'personil_count' && sortDir === 'asc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                            <svg x-show="sortField === 'personil_count' && sortDir === 'desc'" class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            <svg x-show="sortField !== 'personil_count'" class="h-3.5 w-3.5 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
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
                            {{-- Nama Tim dengan Foto Thumbnail --}}
                            <flux:table.cell>
                                <div class="flex items-center gap-3">
                                    {{-- Foto/Avatar --}}
                                    <button 
                                        type="button"
                                        @click="$wire.bukaFotoModal(tim.id)" 
                                        class="relative group flex-shrink-0"
                                        x-show="tim.foto_bersama"
                                    >
                                        <img 
                                            :src="tim.foto_bersama ? ('/storage/' + tim.foto_bersama) : ''" 
                                            :alt="'Foto ' + tim.nama_tim"
                                            class="size-10 rounded-lg object-cover border border-zinc-200 dark:border-zinc-700 group-hover:ring-2 group-hover:ring-brand transition-all cursor-pointer"
                                        />
                                        <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center">
                                            <flux:icon icon="magnifying-glass-plus" class="size-4 text-white" />
                                        </div>
                                    </button>
                                    <div x-show="!tim.foto_bersama" class="size-10 rounded-lg bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-sm font-semibold text-zinc-400 flex-shrink-0" x-text="tim.nama_tim.charAt(0)"></div>
                                    
                                    {{-- Nama Tim + Badge WFO --}}
                                    <div class="flex flex-col gap-1">
                                        <a 
                                            :href="`/admin/personil?tim=${tim.id}`"
                                            wire:navigate
                                            class="font-medium text-zinc-900 dark:text-zinc-100 hover:text-blue-600 dark:hover:text-blue-400 transition-colors cursor-pointer underline decoration-transparent hover:decoration-current"
                                            x-text="tim.nama_tim"
                                            title="Lihat personil tim ini"
                                        ></a>
                                        <span x-show="tim.is_wfo_today" class="inline-flex items-center gap-1 rounded-md bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/50 px-1.5 py-0.5 text-[10px] font-medium text-blue-700 dark:text-blue-300 w-fit">
                                            <flux:icon icon="building-office" class="size-3" />
                                            WFO Hari Ini
                                        </span>
                                    </div>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <span x-show="tim.status === 'active'" class="inline-flex items-center rounded-full bg-green-100 dark:bg-green-900/30 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:text-green-400">Active</span>
                                <span x-show="tim.status === 'inactive'" class="inline-flex items-center rounded-full bg-zinc-100 dark:bg-zinc-700 px-2.5 py-0.5 text-xs font-medium text-zinc-600 dark:text-zinc-400">Inactive</span>
                            </flux:table.cell>
                            <flux:table.cell>
                                <template x-if="tim.has_account">
                                    <div class="relative">
                                        <div
                                            x-data="{ hovering: false, copied: false }"
                                            @mouseenter="hovering = true"
                                            @mouseleave="hovering = false; copied = false"
                                            @click="navigator.clipboard.writeText(tim.account_user); copied = true; setTimeout(() => copied = false, 1500);"
                                            :class="copied ? 'bg-green-50 dark:bg-green-950/40 border-green-200 dark:border-green-800/50 text-green-700 dark:text-green-300' : 'bg-purple-50 dark:bg-purple-950/40 border-purple-200 dark:border-purple-800/50 text-purple-700 dark:text-purple-300 hover:bg-purple-100 dark:hover:bg-purple-900/50'"
                                            class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-medium cursor-pointer transition-colors"
                                        >
                                            <svg x-show="! hovering && ! copied" class="size-3.5 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.169.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                                            </svg>
                                            <svg x-show="hovering && ! copied" class="size-3.5 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                                            </svg>
                                            <svg x-show="copied" class="size-3.5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                            <span x-text="copied ? 'Tercopy!' : tim.account_user"></span>
                                        </div>
                                    </div>
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
                                        <template x-if="tim.has_account">
                                            <flux:menu.item icon="key" @click="$wire.bukaResetPassword(tim.id)">Ubah Password</flux:menu.item>
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
    <flux:modal name="form-tim" class="max-w-md" 
        x-on:close="$wire.editingId = null; $wire.nama_tim = ''; $wire.keterangan = ''; $wire.status = 'active';">
        <div class="flex flex-col gap-5 p-1">
            <flux:heading size="lg">{{ $editingId ? 'Edit Tim' : 'Tambah Tim' }}</flux:heading>
            <form wire:submit="simpan" class="flex flex-col gap-4">
                <flux:input wire:model="nama_tim" label="Nama Tim" placeholder="cth. Tim Politeknik Negeri Jakarta" required />
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
        <div class="flex flex-col gap-5 p-1" x-data="{ copied: false, copiedUser: null }">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-purple-100 dark:bg-purple-900/30 border border-purple-200 dark:border-purple-800/50">
                    <flux:icon icon="sparkles" class="size-5 text-purple-600 dark:text-purple-400" />
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
                        @foreach ($akunBaruGenerated as $idx => $acc)
                            <div class="p-3 flex items-center justify-between text-xs gap-3">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <div class="font-mono font-bold text-purple-600 dark:text-purple-400">{{ $acc['username'] }}</div>
                                        <button
                                            type="button"
                                            @click="navigator.clipboard.writeText('{{ $acc['username'] }}'); copiedUser = '{{ $acc['username'] }}'; setTimeout(() => copiedUser = null, 2000);"
                                            class="text-zinc-400 hover:text-purple-600 dark:hover:text-purple-400 transition"
                                        >
                                            <svg x-show="copiedUser !== '{{ $acc['username'] }}'" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                            </svg>
                                            <svg x-show="copiedUser === '{{ $acc['username'] }}'" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </button>
                                    </div>
                                    <div class="text-zinc-400 text-[11px]">Email: {{ $acc['email'] }}</div>
                                    <div class="text-emerald-600 dark:text-emerald-400 text-[10px] font-medium mt-0.5" x-show="copiedUser === '{{ $acc['username'] }}'" x-transition>
                                        ✓ Tercopy ke clipboard!
                                    </div>
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

    {{-- Modal: Foto Bersama Tim (Lightbox) --}}
    <flux:modal wire:model="showFotoModal" class="max-w-3xl">
        @if ($this->selectedTim && $this->selectedTim->foto_bersama)
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">Foto Bersama {{ $this->selectedTim->nama_tim }}</flux:heading>
                    @if ($this->selectedTim->keterangan)
                        <flux:subheading class="mt-1 text-sm">{{ $this->selectedTim->keterangan }}</flux:subheading>
                    @endif
                </div>

                <div class="rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-700">
                    <img 
                        src="{{ Storage::url($this->selectedTim->foto_bersama) }}" 
                        alt="Foto Bersama Tim"
                        class="w-full h-auto object-contain max-h-[70vh]"
                    />
                </div>

                <div class="flex justify-end pt-2">
                    <flux:button variant="ghost" wire:click="$set('showFotoModal', false)">
                        Tutup
                    </flux:button>
                </div>
            </div>
        @else
            <div class="text-center py-8 text-zinc-400">
                <flux:icon icon="photo" class="size-12 mx-auto mb-3" />
                <p class="text-sm">Tidak ada foto bersama untuk tim ini.</p>
            </div>
        @endif
    </flux:modal>

    {{-- Modal Ubah Password Akun Tim --}}
    <flux:modal name="modal-reset-password" class="max-w-md"
        x-on:close="$wire.resetPasswordTimId = null; $wire.resetPasswordBaru = ''; $wire.resetPasswordKonfirmasi = '';">
        <div class="flex flex-col gap-4 p-1">
            <div>
                <flux:heading size="lg">Ubah Password Akun Tim</flux:heading>
                <flux:subheading class="mt-1">
                    Atur ulang kata sandi login untuk <strong>{{ $resetPasswordTimNama }}</strong>
                    @if ($resetPasswordUsername)
                        (Username: <code class="font-mono text-zinc-900 dark:text-zinc-100 font-semibold">{{ $resetPasswordUsername }}</code>)
                    @endif
                </flux:subheading>
            </div>

            <form wire:submit="simpanResetPassword" class="flex flex-col gap-4">
                <flux:input 
                    wire:model="resetPasswordBaru" 
                    label="Password Baru" 
                    type="password" 
                    placeholder="Minimal 8 karakter" 
                    required 
                    viewable 
                />

                <flux:input 
                    wire:model="resetPasswordKonfirmasi" 
                    label="Konfirmasi Password Baru" 
                    type="password" 
                    placeholder="Ulangi password baru" 
                    required 
                    viewable 
                />

                <div class="flex justify-end gap-2 pt-2">
                    <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="simpanResetPassword">
                        <span wire:loading.remove wire:target="simpanResetPassword">Simpan Password</span>
                        <span wire:loading wire:target="simpanResetPassword">Menyimpan…</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
