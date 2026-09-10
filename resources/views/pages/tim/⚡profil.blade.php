<?php

use App\Models\Notifikasi;
use App\Models\Personil;
use App\Models\Tim;
use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Profil & Anggota Tim')] #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;

    // Edit Tim Form State
    public string $nama_tim = '';
    public string $keterangan = '';
    public $foto_bersama = null;

    // Personil Modal Form State
    public ?int $editingPersonilId = null;
    public string $personil_nama = '';
    public string $personil_jenis_kelamin = 'laki-laki';
    public string $personil_no_hp = '';
    public string $personil_status = 'aktif';
    public ?int $hapusPersonilId = null;

    // Ubah Password State
    public string $password_saat_ini = '';
    public string $password_baru = '';
    public string $password_baru_confirmation = '';

    // UI Preference State
    public string $ui_preference = 'auto';

    public function mount(): void
    {
        $user = auth()->user();
        if ($user && $user->tim) {
            $this->nama_tim = $user->tim->nama_tim;
            $this->keterangan = $user->tim->keterangan ?? '';
        }
        
        // Load current UI preference
        $this->ui_preference = auth()->user()->ui_preference ?? 'auto';
    }

    #[Computed]
    public function tim(): ?Tim
    {
        return auth()->user()?->tim;
    }

    #[Computed]
    public function personilList()
    {
        if (! $this->tim) {
            return collect();
        }

        return Personil::where('tim_id', $this->tim->id)
            ->orderBy('nama')
            ->get();
    }

    #[Computed]
    public function jumlahBelumDibaca(): int
    {
        $personilIds = $this->personilList->pluck('id')->toArray();

        if (empty($personilIds)) {
            return 0;
        }

        return Notifikasi::whereIn('personil_id', $personilIds)
            ->where('dibaca', false)
            ->count();
    }

    public function updateProfilTim(): void
    {
        $this->validate([
            'nama_tim' => 'required|string|max:100',
            'keterangan' => 'nullable|string|max:500',
            'foto_bersama' => 'nullable|image|max:5120',
        ]);

        if (! $this->tim) {
            Flux::toast(variant: 'danger', text: 'Data tim tidak ditemukan.');

            return;
        }

        $data = [
            'nama_tim' => $this->nama_tim,
            'keterangan' => $this->keterangan ?: null,
        ];

        if ($this->foto_bersama) {
            if ($this->tim->foto_bersama && Storage::disk('public')->exists($this->tim->foto_bersama)) {
                Storage::disk('public')->delete($this->tim->foto_bersama);
            }
            $path = $this->foto_bersama->store('tim-photos', 'public');
            $data['foto_bersama'] = $path;
            $this->foto_bersama = null;
        }

        $this->tim->update($data);

        Flux::toast(variant: 'success', text: 'Informasi tim berhasil diperbarui.');
        unset($this->tim);
    }

    public function hapusFotoBersama(): void
    {
        if (! $this->tim) {
            return;
        }

        if ($this->tim->foto_bersama && Storage::disk('public')->exists($this->tim->foto_bersama)) {
            Storage::disk('public')->delete($this->tim->foto_bersama);
        }

        $this->tim->update(['foto_bersama' => null]);
        $this->foto_bersama = null;
        unset($this->tim);

        Flux::toast(variant: 'success', text: 'Foto bersama berhasil dihapus.');
    }

    public function resetFormPersonil(): void
    {
        $this->editingPersonilId = null;
        $this->personil_nama = '';
        $this->personil_jenis_kelamin = 'laki-laki';
        $this->personil_no_hp = '';
        $this->personil_status = 'aktif';
        $this->resetValidation();
    }

    public function loadPersonilData(int $id): void
    {
        $personil = Personil::where('tim_id', $this->tim?->id)->findOrFail($id);
        $this->editingPersonilId = $personil->id;
        $this->personil_nama = $personil->nama;
        $this->personil_jenis_kelamin = $personil->jenis_kelamin ?? 'laki-laki';
        $this->personil_no_hp = $personil->no_hp ?? '';
        $this->personil_status = $personil->status;
        $this->resetValidation();
    }

    public function bukaModalEditPersonil(int $id): void
    {
        $this->loadPersonilData($id);
        $this->modal('modal-personil')->show();
    }

    public function simpanPersonil(): void
    {
        $this->validate([
            'personil_nama' => 'required|string|max:100',
            'personil_jenis_kelamin' => 'required|in:laki-laki,perempuan',
            'personil_no_hp' => 'nullable|string|max:20',
            'personil_status' => 'required|in:aktif,nonaktif',
        ]);

        if (! $this->tim) {
            return;
        }

        if ($this->editingPersonilId) {
            $personil = Personil::where('tim_id', $this->tim->id)->findOrFail($this->editingPersonilId);
            $personil->update([
                'nama' => $this->personil_nama,
                'jenis_kelamin' => $this->personil_jenis_kelamin,
                'no_hp' => $this->personil_no_hp ?: null,
                'status' => $this->personil_status,
            ]);
            Flux::toast(variant: 'success', text: "Data anggota '{$personil->nama}' berhasil diperbarui.");
        } else {
            Personil::create([
                'tim_id' => $this->tim->id,
                'nama' => $this->personil_nama,
                'jenis_kelamin' => $this->personil_jenis_kelamin,
                'no_hp' => $this->personil_no_hp ?: null,
                'status' => $this->personil_status,
            ]);
            Flux::toast(variant: 'success', text: "Anggota '{$this->personil_nama}' berhasil ditambahkan ke tim.");
        }

        $this->modal('modal-personil')->close();
        unset($this->personilList);
    }

    public function konfirmasiHapusPersonil(int $id): void
    {
        $this->hapusPersonilId = $id;
        $this->modal('modal-hapus-personil')->show();
    }

    public function hapusPersonil(): void
    {
        if (! $this->hapusPersonilId || ! $this->tim) {
            return;
        }

        $personil = Personil::where('tim_id', $this->tim->id)->findOrFail($this->hapusPersonilId);
        $nama = $personil->nama;
        $personil->delete();

        Flux::toast(variant: 'success', text: "Anggota '{$nama}' berhasil dihapus dari tim.");
        $this->modal('modal-hapus-personil')->close();
        $this->hapusPersonilId = null;
        unset($this->personilList);
    }

    public function ubahPassword(): void
    {
        $this->validate([
            'password_baru' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = auth()->user();
        if ($user) {
            $user->update([
                'password' => Hash::make($this->password_baru),
            ]);

            $this->password_saat_ini = '';
            $this->password_baru = '';
            $this->password_baru_confirmation = '';

            Flux::toast(variant: 'success', text: 'Password akun tim berhasil diperbarui!');
        }
    }

    public function updateUiPreference(): void
    {
        $this->validate([
            'ui_preference' => 'required|in:auto,desktop,mobile',
        ]);

        $user = auth()->user();
        if (!$user) {
            return;
        }

        // Direct DB update to ensure it saves
        \DB::table('users')
            ->where('id', $user->id)
            ->update(['ui_preference' => $this->ui_preference]);

        // Log untuk debug
        logger()->info('UI Preference updated via direct query', [
            'user_id' => $user->id,
            'new_preference' => $this->ui_preference,
        ]);

        Flux::toast(variant: 'success', text: 'Preferensi tampilan berhasil disimpan! Halaman akan di-reload...');
        
        // Force redirect with full URL to bypass any cache
        $this->js("window.location.href = window.location.href.split('?')[0] + '?t=' + Date.now()");
    }
}; ?>

<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">
                {{ $this->tim?->nama_tim ?? 'Profil & Anggota Tim' }}
            </flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400 mt-0.5">
                Kelola informasi tim, anggota personil yang terdaftar, dan keamanan akun login.
            </flux:text>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left Column: Personil Management (Span 2) --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Quick Stats Anggota with Watermark Icons --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-xs">
                    <div class="relative z-10 pr-6">
                        <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Total Anggota</flux:text>
                        <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-zinc-900 dark:text-zinc-100">{{ $this->personilList->count() }}</flux:heading>
                    </div>
                    <flux:icon icon="users" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-blue-500/10 dark:text-blue-400/10 pointer-events-none" />
                </flux:card>

                <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-xs">
                    <div class="relative z-10 pr-6">
                        <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Anggota Aktif</flux:text>
                        <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                            {{ $this->personilList->where('status', 'aktif')->count() }}
                        </flux:heading>
                    </div>
                    <flux:icon icon="check-circle" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-emerald-500/10 dark:text-emerald-400/10 pointer-events-none" />
                </flux:card>

                <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 max-sm:col-span-2 shadow-xs">
                    <div class="relative z-10 pr-6">
                        <flux:text class="truncate font-medium text-xs text-zinc-500 dark:text-zinc-400">Status Tim</flux:text>
                        <flux:heading size="xl" class="mt-2 font-bold tracking-tight text-purple-600 dark:text-purple-400">
                            {{ ucfirst($this->tim?->status ?? 'Active') }}
                        </flux:heading>
                    </div>
                    <flux:icon icon="academic-cap" class="absolute -bottom-3 -right-3 size-20 sm:size-24 text-purple-500/10 dark:text-purple-400/10 pointer-events-none" />
                </flux:card>
            </div>

            {{-- Personil Table Card --}}
            <flux:card class="p-0 overflow-hidden border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs">
                <div class="px-5 py-4 border-b border-zinc-100 dark:border-zinc-800 flex items-center justify-between">
                    <div>
                        <flux:heading size="md">Daftar Anggota Personil</flux:heading>
                        <flux:text class="text-xs text-zinc-500">Anggota ini yang akan dirotasikan ke dalam jadwal WFO, briefing, dan adzan.</flux:text>
                    </div>
                    @if ($this->personilList->isNotEmpty())
                        <flux:modal.trigger name="modal-personil" wire:click="resetFormPersonil">
                            <flux:button size="xs" variant="primary" icon="plus">
                                Tambah
                            </flux:button>
                        </flux:modal.trigger>
                    @endif
                </div>

                @if ($this->personilList->isEmpty())
                    <div class="p-8 text-center space-y-3">
                        <div class="inline-flex size-12 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-zinc-800 text-zinc-400">
                            <flux:icon icon="users" class="size-6" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Belum ada anggota personil</p>
                            <p class="text-xs text-zinc-400">Tambahkan anggota tim agar dapat terjadwal secara otomatis.</p>
                        </div>
                        <flux:modal.trigger name="modal-personil" wire:click="resetFormPersonil">
                            <flux:button size="sm" variant="primary">
                                Tambah Anggota Sekarang
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                @else
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->personilList as $p)
                            <div class="p-4 flex items-center justify-between gap-4 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors" wire:key="personil-{{ $p->id }}">
                                <div class="flex items-center gap-3.5 min-w-0">
                                    <div class="flex size-10 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-700 text-zinc-500 dark:text-zinc-400 flex-shrink-0">
                                        <flux:icon icon="user" class="size-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="font-medium text-sm text-zinc-900 dark:text-zinc-100 truncate">{{ $p->nama }}</p>
                                            
                                            {{-- Gender Badge --}}
                                            @if (($p->jenis_kelamin ?? 'laki-laki') === 'laki-laki')
                                                <span class="inline-flex items-center gap-1 text-[10px] font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/50 px-1.5 py-0.5 rounded-md">
                                                    <span>♂</span> Laki-laki
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 text-[10px] font-medium text-pink-600 dark:text-pink-400 bg-pink-50 dark:bg-pink-950/40 border border-pink-200 dark:border-pink-800/50 px-1.5 py-0.5 rounded-md">
                                                    <span>♀</span> Perempuan
                                                </span>
                                            @endif

                                            @if ($p->status === 'aktif')
                                                <span class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-950/60 px-2 py-0.5 text-[10px] font-medium text-emerald-700 dark:text-emerald-400">
                                                    Aktif
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 text-[10px] font-medium text-zinc-600 dark:text-zinc-400">
                                                    Nonaktif
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-zinc-400 mt-0.5 flex items-center gap-1">
                                            <flux:icon icon="phone" class="size-3" />
                                            <span>{{ $p->no_hp ?? 'Tidak ada nomor WhatsApp' }}</span>
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1">
                                    <flux:modal.trigger name="modal-personil" wire:click="loadPersonilData({{ $p->id }})">
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="pencil"
                                            title="Edit anggota"
                                        />
                                    </flux:modal.trigger>
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="trash"
                                        class="text-red-500 hover:text-red-600"
                                        wire:click="konfirmasiHapusPersonil({{ $p->id }})"
                                        title="Hapus anggota"
                                    />
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </flux:card>
        </div>

        {{-- Right Column: Profil Tim & Password (Span 1) --}}
        <div class="space-y-6">
            {{-- Form Edit Info Tim & Foto Bersama --}}
            <flux:card class="border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs space-y-4">
                <div>
                    <flux:heading size="md">Informasi & Foto Tim</flux:heading>
                    <flux:subheading class="text-xs">Ubah nama, keterangan, dan unggah foto bersama tim</flux:subheading>
                </div>

                {{-- Foto Bersama Preview & Upload --}}
                <div class="space-y-3">
                    <flux:label>Foto Bersama Tim</flux:label>
                    
                    <div class="relative overflow-hidden rounded-xl border-2 border-dashed border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 p-3 text-center transition-all hover:border-zinc-300 dark:hover:border-zinc-600">
                        @if ($foto_bersama)
                            {{-- Temporary uploaded preview --}}
                            <div class="relative group">
                                <img src="{{ $foto_bersama->temporaryUrl() }}" alt="Preview Foto Bersama" class="w-full h-44 object-cover rounded-lg shadow-xs" />
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center">
                                    <span class="text-xs font-semibold text-white bg-black/60 px-2.5 py-1 rounded-full">Foto Siap Disimpan</span>
                                </div>
                            </div>
                        @elseif ($this->tim?->foto_bersama)
                            {{-- Existing saved photo --}}
                            <div class="relative group">
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($this->tim->foto_bersama) }}" alt="Foto Bersama {{ $this->tim->nama_tim }}" class="w-full h-44 object-cover rounded-lg shadow-xs" />
                                <div class="absolute top-2 right-2">
                                    <button 
                                        type="button" 
                                        wire:click="hapusFotoBersama" 
                                        wire:confirm="Yakin ingin menghapus foto bersama tim?" 
                                        class="p-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg shadow-md transition-colors"
                                        title="Hapus foto"
                                    >
                                        <flux:icon icon="trash" class="size-4" />
                                    </button>
                                </div>
                            </div>
                        @else
                            {{-- Placeholder when no photo is uploaded --}}
                            <div class="py-5 flex flex-col items-center justify-center space-y-2 text-zinc-400">
                                <div class="size-12 rounded-full bg-zinc-200/70 dark:bg-zinc-700/60 flex items-center justify-center text-zinc-500 dark:text-zinc-400">
                                    <flux:icon icon="camera" class="size-6" />
                                </div>
                                <div class="text-xs font-medium text-zinc-600 dark:text-zinc-300">Belum ada foto bersama</div>
                                <div class="text-[11px] text-zinc-400 max-w-[200px] leading-tight">Foto ini akan tampil di daftar tim admin sebagai identitas & kenang-kenangan.</div>
                            </div>
                        @endif

                        {{-- Upload Control --}}
                        <div class="mt-3 flex items-center justify-center gap-2">
                            <label for="foto-bersama-input" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 border border-zinc-300 dark:border-zinc-600 hover:bg-zinc-50 dark:hover:bg-zinc-700 cursor-pointer shadow-2xs transition-all">
                                <flux:icon icon="arrow-up-tray" class="size-3.5 text-zinc-500" />
                                <span>{{ $this->tim?->foto_bersama || $foto_bersama ? 'Ganti Foto' : 'Unggah Foto' }}</span>
                            </label>
                            <input 
                                id="foto-bersama-input" 
                                type="file" 
                                wire:model="foto_bersama" 
                                accept="image/jpeg,image/png,image/webp,image/jpg" 
                                class="hidden" 
                            />
                            
                            @if ($foto_bersama)
                                <button 
                                    type="button" 
                                    wire:click="$set('foto_bersama', null)" 
                                    class="px-2.5 py-1.5 text-xs text-zinc-500 hover:text-red-600 transition-colors"
                                >
                                    Batal
                                </button>
                            @endif
                        </div>

                        {{-- Loading Indicator --}}
                        <div wire:loading wire:target="foto_bersama" class="text-xs text-blue-500 dark:text-blue-400 mt-2 font-medium">
                            Mengunggah pratinjau foto...
                        </div>
                        @error('foto_bersama')
                            <div class="text-xs text-red-500 mt-1.5">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <form wire:submit="updateProfilTim" class="space-y-4">
                    <flux:input
                        wire:model="nama_tim"
                        label="Nama Tim / Sekolah / Kampus"
                        required
                        placeholder="cth. SMK Negeri 1 Cimahi"
                    />

                    <flux:textarea
                        wire:model="keterangan"
                        label="Keterangan / Jurusan"
                        rows="2"
                        placeholder="cth. Peserta PKL Jurusan RPL - Gelombang 1"
                    />

                    <div class="pt-1">
                        <flux:button type="submit" variant="primary" class="w-full">
                            Simpan Perubahan Tim
                        </flux:button>
                    </div>
                </form>
            </flux:card>

            {{-- Form Ubah Password Akun --}}
            <flux:card class="border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs space-y-4">
                <div>
                    <flux:heading size="md">Keamanan & Password</flux:heading>
                    <flux:subheading class="text-xs">Ganti password akun tim agar lebih aman</flux:subheading>
                </div>

                <form wire:submit="ubahPassword" class="space-y-4">
                    <flux:input
                        wire:model="password_baru"
                        type="password"
                        label="Password Baru"
                        required
                        placeholder="Minimal 8 karakter"
                        viewable
                    />

                    <flux:input
                        wire:model="password_baru_confirmation"
                        type="password"
                        label="Ulangi Password Baru"
                        required
                        placeholder="Konfirmasi password"
                        viewable
                    />

                    <div class="pt-1">
                        <flux:button type="submit" variant="filled" class="w-full">
                            Perbarui Password
                        </flux:button>
                    </div>
                </form>
            </flux:card>

            {{-- UI Preference Settings --}}
            <flux:card class="border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs space-y-4">
                <div>
                    <flux:heading size="md">Pengaturan Tampilan</flux:heading>
                    <flux:subheading class="text-xs">Pilih mode tampilan yang sesuai untuk perangkat</flux:subheading>
                </div>

                <form wire:submit="updateUiPreference" class="space-y-4">
                    <flux:radio.group wire:model.live="ui_preference" label="Mode Tampilan" variant="cards">
                        <flux:radio value="auto" icon="device-tablet">
                            <div>
                                <div class="font-medium">Auto (Recommended)</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">Otomatis sesuai ukuran layar perangkat</div>
                            </div>
                        </flux:radio>
                        
                        <flux:radio value="desktop" icon="computer-desktop">
                            <div>
                                <div class="font-medium">Desktop Look</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">Sidebar navigasi di samping (klasik)</div>
                            </div>
                        </flux:radio>
                        
                        <flux:radio value="mobile" icon="device-phone-mobile">
                            <div>
                                <div class="font-medium">Mobile Look</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">Bottom navigation bar (app-style)</div>
                            </div>
                        </flux:radio>
                    </flux:radio.group>

                    <div class="pt-1">
                        <flux:button type="submit" variant="primary" class="w-full">
                            Simpan Pengaturan
                        </flux:button>
                    </div>
                </form>
            </flux:card>
        </div>
    </div>

    {{-- Modal Tambah / Edit Personil --}}
    <flux:modal name="modal-personil" class="max-w-md">
        <form wire:submit="simpanPersonil" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editingPersonilId ? 'Edit Anggota Personil' : 'Tambah Anggota Baru' }}</flux:heading>
                <flux:subheading>Masukkan data personil tim untuk penjadwalan WFO & tugas.</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input
                    wire:model="personil_nama"
                    label="Nama Lengkap"
                    placeholder="Nama anggota tim"
                    required
                    autofocus
                />

                <flux:select wire:model="personil_jenis_kelamin" label="Jenis Kelamin" required>
                    <flux:select.option value="laki-laki">Laki-laki (Dapat ditugaskan adzan/kitab)</flux:select.option>
                    <flux:select.option value="perempuan">Perempuan</flux:select.option>
                </flux:select>

                <flux:input
                    wire:model="personil_no_hp"
                    label="Nomor WhatsApp / HP"
                    placeholder="cth. 08123456789"
                />

                <flux:select wire:model="personil_status" label="Status Anggota">
                    <flux:select.option value="aktif">Aktif (Ikut Penjadwalan)</flux:select.option>
                    <flux:select.option value="nonaktif">Nonaktif (Tidak Ikut Penjadwalan)</flux:select.option>
                </flux:select>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">
                    {{ $editingPersonilId ? 'Simpan Perubahan' : 'Tambahkan' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal Konfirmasi Hapus Personil --}}
    <flux:modal name="modal-hapus-personil" class="max-w-sm">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Hapus Anggota?</flux:heading>
                <flux:subheading>Anggota ini akan dihapus dari daftar tim. Tugas masa lalu yang terkait tetap tersimpan di riwayat.</flux:subheading>
            </div>

            <div class="flex items-center justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="hapusPersonil">
                    Hapus Anggota
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
