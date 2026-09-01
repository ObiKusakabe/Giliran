<?php

use App\Livewire\Actions\Logout;
use App\Models\Personil;
use App\Models\Tim;
use App\Services\TimNamingService;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Lengkapi Profil Tim')] #[Layout('layouts.auth.onboarding')] class extends Component {

    public string $nama_tim = '';
    public string $keterangan = '';

    /** @var array<int, array{nama: string, jenis_kelamin: string, no_hp: string}> */
    public array $personil = [
        ['nama' => '', 'jenis_kelamin' => 'laki-laki', 'no_hp' => ''],
        ['nama' => '', 'jenis_kelamin' => 'laki-laki', 'no_hp' => ''],
    ];

    public function mount(): void
    {
        $user = auth()->user();

        // Jika user sudah memiliki tim, redirect langsung ke jadwal
        if ($user && ! $user->needsOnboarding()) {
            $this->redirect(route('tim.jadwal'), navigate: true);
        }
    }

    #[Computed]
    public function step1Complete(): bool
    {
        return strlen(trim($this->nama_tim)) >= 3;
    }

    #[Computed]
    public function step2Complete(): bool
    {
        $filled = collect($this->personil)
            ->filter(fn ($p) => ! empty(trim($p['nama'] ?? '')) && strlen(trim($p['nama'])) >= 2);

        return $filled->count() >= 1;
    }

    public function tambahPersonil(): void
    {
        $this->personil[] = ['nama' => '', 'jenis_kelamin' => 'laki-laki', 'no_hp' => ''];
    }

    public function hapusPersonil(int $index): void
    {
        if (count($this->personil) > 1) {
            unset($this->personil[$index]);
            $this->personil = array_values($this->personil);
        }
    }

    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }

    public function simpan(): void
    {
        $this->validate([
            'nama_tim' => 'required|string|min:3|max:150',
            'keterangan' => 'nullable|string|max:255',
            'personil' => 'required|array|min:1',
            'personil.*.nama' => 'required|string|max:150',
            'personil.*.jenis_kelamin' => 'nullable|in:laki-laki,perempuan',
            'personil.*.no_hp' => 'nullable|string|max:20',
        ], [
            'nama_tim.required' => 'Nama instansi/tim wajib diisi.',
            'personil.*.nama.required' => 'Nama lengkap anggota personil wajib diisi.',
        ]);

        $user = auth()->user();
        if (! $user) {
            return;
        }

        // Auto gelombang naming jika belum ada format tahun
        $namaTim = $this->nama_tim;
        if (! str_contains($this->nama_tim, '-'.now()->year.'-')) {
            $naming = app(TimNamingService::class);
            $namaTim = $naming->generateNamaGelombang($this->nama_tim);
        }

        DB::transaction(function () use ($user, $namaTim) {
            // 1. Buat record Tim baru
            $tim = Tim::create([
                'nama_tim' => $namaTim,
                'keterangan' => $this->keterangan ?: null,
                'status' => 'active',
            ]);

            // 2. Hubungkan User ke Tim ini
            $user->update([
                'tim_id' => $tim->id,
                'name' => $tim->nama_tim,
            ]);

            // 3. Simpan data personil
            foreach ($this->personil as $p) {
                if (! empty(trim($p['nama']))) {
                    Personil::create([
                        'tim_id' => $tim->id,
                        'nama' => trim($p['nama']),
                        'jenis_kelamin' => $p['jenis_kelamin'] ?? 'laki-laki',
                        'no_hp' => ! empty($p['no_hp']) ? trim($p['no_hp']) : null,
                        'status' => 'aktif',
                    ]);
                }
            }
        });

        Flux::toast(
            variant: 'success',
            text: "Selamat datang, {$namaTim}! Profil tim dan anggota berhasil disimpan."
        );

        $this->redirect(route('tim.jadwal'), navigate: true);
    }
}; ?>

<div class="min-h-screen bg-zinc-50 dark:bg-zinc-950 flex flex-col justify-between">
    {{-- Top Navbar --}}
    <header class="border-b border-zinc-200/80 dark:border-zinc-800/80 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md sticky top-0 z-30">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="/" class="flex items-center gap-3 group" wire:navigate>
                <x-app-logo-icon class="size-8 text-zinc-900 dark:text-zinc-100 transition-transform group-hover:scale-105" />
                <span class="font-bold text-lg tracking-tight text-zinc-900 dark:text-white">Giliran</span>
            </a>

            <div class="flex items-center gap-3">
                @if (auth()->check())
                    <div class="hidden sm:flex items-center gap-2 px-3 py-1 rounded-full bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-xs">
                        <span class="text-zinc-500 dark:text-zinc-400">Akun:</span>
                        <span class="font-mono font-bold text-purple-600 dark:text-purple-400">{{ auth()->user()->username ?? auth()->user()->email }}</span>
                    </div>

                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="arrow-right-start-on-rectangle"
                        wire:click="logout"
                        title="Keluar dari akun"
                        class="text-zinc-600 dark:text-zinc-300 hover:text-red-600 dark:hover:text-red-400"
                    >
                        Keluar
                    </flux:button>
                @endif
            </div>
        </div>
    </header>

    {{-- Main Container --}}
    <main class="flex-1 max-w-4xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-10">
        {{-- Welcoming Header --}}
        <div class="text-center max-w-2xl mx-auto mb-10 space-y-2">
            <flux:heading size="xl" class="font-extrabold tracking-tight sm:text-3xl text-zinc-900 dark:text-white">
                Lengkapi Profil Tim & Anggota
            </flux:heading>
            <flux:text class="text-zinc-600 dark:text-zinc-400 text-sm sm:text-base">
                Ikuti alur timeline di bawah untuk mengisi identitas tim dan personil Anda sebelum mengakses jadwal WFO.
            </flux:text>
        </div>

        {{-- Form dengan Timeline Terintegrasi --}}
        <flux:card class="bg-white dark:bg-zinc-900 shadow-xl border border-zinc-200 dark:border-zinc-800 p-6 sm:p-10 rounded-3xl">
            <form wire:submit="simpan">
                <flux:timeline align="start">
                    {{-- STEP 1: Identitas Tim / Instansi --}}
                    <flux:timeline.item :status="$this->step1Complete ? 'complete' : 'current'" size="lg">
                        <flux:timeline.indicator
                            size="lg"
                            :color="$this->step1Complete ? 'blue' : null"
                            :status="$this->step1Complete ? 'complete' : 'current'"
                            class="transition-all duration-300"
                        >
                            @if ($this->step1Complete)
                                <svg class="size-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            @else
                                <span class="font-bold text-sm">1</span>
                            @endif
                        </flux:timeline.indicator>

                        <flux:timeline.content class="space-y-4 pb-8">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <flux:heading size="lg" class="font-bold text-zinc-900 dark:text-white">
                                            Identitas Tim / Instansi
                                        </flux:heading>
                                        @if ($this->step1Complete)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 dark:bg-blue-950/60 px-2 py-0.5 text-xs font-semibold text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800">
                                                <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                Selesai
                                            </span>
                                        @endif
                                    </div>
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                        Nama institusi kampus, sekolah, atau asal lembaga tim magang Anda.
                                    </flux:text>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 bg-zinc-50/70 dark:bg-zinc-800/30 p-4 sm:p-5 rounded-2xl border border-zinc-200/80 dark:border-zinc-700/80">
                                <flux:field>
                                    <flux:label>Nama Tim / Asal Instansi <span class="text-red-500">*</span></flux:label>
                                    <flux:input
                                        wire:model.live.debounce.200ms="nama_tim"
                                        placeholder="cth. Tim Politeknik Negeri Jakarta, Tim SMKN 1 Cibinong"
                                        required
                                        autofocus
                                    />
                                    <flux:description>Nama instansi tempat asal tim Anda (akan otomatis berakhiran tahun).</flux:description>
                                    <flux:error name="nama_tim" />
                                </flux:field>

                                <flux:field>
                                    <flux:label>Jurusan / Keterangan (Opsional)</flux:label>
                                    <flux:input
                                        wire:model.live.debounce.200ms="keterangan"
                                        placeholder="cth. Teknik Informatika, Multimedia Batch 3"
                                    />
                                    <flux:error name="keterangan" />
                                </flux:field>
                            </div>
                        </flux:timeline.content>
                    </flux:timeline.item>

                    {{-- STEP 2: Daftar Anggota Tim --}}
                    <flux:timeline.item :status="$this->step2Complete ? 'complete' : ($this->step1Complete ? 'current' : 'incomplete')" size="lg">
                        <flux:timeline.indicator
                            size="lg"
                            :color="$this->step2Complete ? 'blue' : ($this->step1Complete ? 'blue' : null)"
                            :status="$this->step2Complete ? 'complete' : ($this->step1Complete ? 'current' : 'incomplete')"
                            class="transition-all duration-300"
                        >
                            @if ($this->step2Complete)
                                <svg class="size-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            @else
                                <span class="font-bold text-sm">2</span>
                            @endif
                        </flux:timeline.indicator>

                        <flux:timeline.content class="space-y-4 pb-8">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <flux:heading size="lg" class="font-bold text-zinc-900 dark:text-white">
                                            Daftar Anggota Tim
                                        </flux:heading>
                                        @if ($this->step2Complete)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 dark:bg-blue-950/60 px-2 py-0.5 text-xs font-semibold text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800">
                                                <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                Selesai
                                            </span>
                                        @endif
                                    </div>
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                        Masukkan nama personil yang akan menjalankan rotasi WFO, briefing, dan jadwal adzan/kitab.
                                    </flux:text>
                                </div>

                                <flux:button
                                    type="button"
                                    size="sm"
                                    variant="subtle"
                                    icon="plus"
                                    wire:click="tambahPersonil"
                                    class="self-start sm:self-auto"
                                >
                                    Tambah Anggota
                                </flux:button>
                            </div>

                            <div class="space-y-3 bg-zinc-50/70 dark:bg-zinc-800/30 p-4 sm:p-5 rounded-2xl border border-zinc-200/80 dark:border-zinc-700/80">
                                @foreach ($personil as $index => $p)
                                    <div class="flex items-start gap-3 p-3.5 rounded-xl bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 shadow-xs" wire:key="personil-row-{{ $index }}">
                                        <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700 text-xs font-semibold text-zinc-600 dark:text-zinc-300 mt-1">
                                            {{ $index + 1 }}
                                        </div>

                                        <div class="flex-1 grid grid-cols-1 sm:grid-cols-12 gap-3">
                                            <div class="sm:col-span-6">
                                                <flux:input
                                                    wire:model.live.debounce.200ms="personil.{{ $index }}.nama"
                                                    placeholder="Nama lengkap anggota *"
                                                    required
                                                />
                                                <flux:error name="personil.{{ $index }}.nama" />
                                            </div>
                                            <div class="sm:col-span-3">
                                                <flux:select wire:model="personil.{{ $index }}.jenis_kelamin" placeholder="Jenis Kelamin">
                                                    <flux:select.option value="laki-laki">Laki-laki</flux:select.option>
                                                    <flux:select.option value="perempuan">Perempuan</flux:select.option>
                                                </flux:select>
                                            </div>
                                            <div class="sm:col-span-3">
                                                <flux:input
                                                    wire:model="personil.{{ $index }}.no_hp"
                                                    placeholder="No. WhatsApp"
                                                />
                                            </div>
                                        </div>

                                        @if (count($personil) > 1)
                                            <flux:button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                icon="trash"
                                                class="text-red-500 hover:text-red-600 mt-1"
                                                wire:click="hapusPersonil({{ $index }})"
                                                title="Hapus baris"
                                            />
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </flux:timeline.content>
                    </flux:timeline.item>

                    {{-- STEP 3: Konfirmasi & Aktivasi --}}
                    <flux:timeline.item :status="($this->step1Complete && $this->step2Complete) ? 'current' : 'incomplete'" size="lg">
                        <flux:timeline.indicator
                            size="lg"
                            :color="($this->step1Complete && $this->step2Complete) ? 'blue' : null"
                            :status="($this->step1Complete && $this->step2Complete) ? 'current' : 'incomplete'"
                            class="transition-all duration-300"
                        >
                            <span class="font-bold text-sm">3</span>
                        </flux:timeline.indicator>

                        <flux:timeline.content class="space-y-4">
                            <div>
                                <flux:heading size="lg" class="font-bold text-zinc-900 dark:text-white">
                                    Aktivasi & Masuk Portal
                                </flux:heading>
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                    Setelah menyimpan, data tim akan langsung aktif dan Anda dapat melihat jadwal WFO & alokasi ruangan.
                                </flux:text>
                            </div>

                            <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 flex flex-col sm:flex-row items-center justify-between gap-4">
                                <flux:text class="text-xs text-zinc-400">
                                    Data tim & personil dapat disesuaikan kembali nantinya oleh Admin.
                                </flux:text>

                                <flux:button
                                    type="submit"
                                    variant="primary"
                                    class="w-full sm:w-auto px-8"
                                    wire:loading.attr="disabled"
                                    wire:target="simpan"
                                    icon="arrow-right"
                                >
                                    <span wire:loading.remove wire:target="simpan">Selesaikan & Masuk Portal</span>
                                    <span wire:loading wire:target="simpan">Menyimpan Data Tim…</span>
                                </flux:button>
                            </div>
                        </flux:timeline.content>
                    </flux:timeline.item>
                </flux:timeline>
            </form>
        </flux:card>
    </main>

    {{-- Footer --}}
    <footer class="py-6 text-center text-xs text-zinc-400 border-t border-zinc-200/50 dark:border-zinc-800/50">
        &copy; {{ date('Y') }} PT Inovindo Digital Mandiri &bull; Giliran Schedule System
    </footer>
</div>
