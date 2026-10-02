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

new #[Title('Akun')] #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;

    // Segmented Control State
    public string $activeSection = 'info'; // 'info' or 'settings'

    // Edit Tim Form State (Section: Info Tim)
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

    // Ubah Password State (Section: Pengaturan)
    public string $password_baru = '';
    public string $password_baru_confirmation = '';

    // Email Akun State (Section: Pengaturan)
    public string $email = '';

    public function mount(): void
    {
        $user = auth()->user();
        if ($user) {
            $this->email = $user->email ?? '';
            if ($user->tim) {
                $this->nama_tim = $user->tim->nama_tim;
                $this->keterangan = $user->tim->keterangan ?? '';
            }
        }
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

    public function updateProfilTim(): void
    {
        $this->validate([
            'nama_tim' => 'required|string|max:100',
            'keterangan' => 'nullable|string|max:500',
            'foto_bersama' => 'nullable|image|max:5120',
        ], [
            'nama_tim.required' => 'Nama tim wajib diisi.',
            'foto_bersama.image' => 'File harus berupa gambar (JPG, PNG, atau WebP).',
            'foto_bersama.max' => 'Ukuran foto maksimal 5MB.',
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

        // Sinkronisasi nama akun user dengan nama tim
        $user = auth()->user();
        if ($user && $user->name !== $this->nama_tim) {
            $user->update(['name' => $this->nama_tim]);
        }

        $this->dispatch('photo-saved');

        Flux::toast(variant: 'success', text: 'Informasi & foto tim berhasil diperbarui.');
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

        $this->dispatch('photo-deleted');
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

            $this->password_baru = '';
            $this->password_baru_confirmation = '';

            Flux::toast(variant: 'success', text: 'Password akun tim berhasil diperbarui!');
        }
    }

    public function updateEmail(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $validated = $this->validate([
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ], [
            'email.email' => 'Format alamat email tidak valid.',
            'email.unique' => 'Email ini sudah digunakan oleh akun lain.',
        ]);

        $emailValue = ! empty($validated['email']) ? $validated['email'] : null;

        $user->update([
            'email' => $emailValue,
        ]);

        $this->email = $emailValue ?? '';

        Flux::toast(variant: 'success', text: 'Alamat email akun tim berhasil diperbarui!');
    }
}; ?>

<div class="space-y-6">
    {{-- Segmented Control --}}
    <div class="flex gap-2 p-1 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
        <button 
            wire:click="$set('activeSection', 'info')"
            class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-all {{ $activeSection === 'info' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
        >
            Info Tim
        </button>
        <button 
            wire:click="$set('activeSection', 'settings')"
            class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-all {{ $activeSection === 'settings' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
        >
            Pengaturan
        </button>
    </div>

    {{-- Section Content --}}
    <div>
        @if ($activeSection === 'info')
            {{-- SECTION: INFO TIM --}}
            <div class="space-y-6">
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

                    {{-- Right Column: Form Edit Info Tim & Foto Bersama (Span 1) --}}
                    <div class="space-y-6">
                        {{-- Form Edit Info Tim --}}
                        <flux:card class="border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs space-y-4">
                            <div>
                                <flux:heading size="md">Informasi & Foto Tim</flux:heading>
                                <flux:subheading class="text-xs">Ubah nama, keterangan, dan unggah foto bersama tim</flux:subheading>
                            </div>

                            {{-- Foto Bersama Preview & Upload with Instant Client-Side Preview & Lightbox Modal --}}
                            <div 
                                x-data="{
                                    clientPreviewUrl: null,
                                    previewModalOpen: false,
                                    previewModalSrc: '',
                                    previewModalTitle: 'Foto Bersama Tim',
                                    
                                    handleFileChange(event) {
                                        const file = event.target.files[0];
                                        if (!file) return;
                                        
                                        // Client-side validation
                                        if (!file.type.match(/^image\/(jpeg|png|webp|jpg)$/i)) {
                                            alert('Format file tidak didukung. Harap pilih gambar JPG, PNG, atau WebP.');
                                            event.target.value = '';
                                            return;
                                        }
                                        if (file.size > 5120 * 1024) {
                                            alert('Ukuran file maksimal adalah 5MB.');
                                            event.target.value = '';
                                            return;
                                        }
                                        
                                        if (this.clientPreviewUrl) {
                                            URL.revokeObjectURL(this.clientPreviewUrl);
                                        }
                                        this.clientPreviewUrl = URL.createObjectURL(file);
                                    },
                                    
                                    clearFile(inputId) {
                                        if (this.clientPreviewUrl) {
                                            URL.revokeObjectURL(this.clientPreviewUrl);
                                            this.clientPreviewUrl = null;
                                        }
                                        const input = document.getElementById(inputId);
                                        if (input) input.value = '';
                                    },
                                    
                                    openLightbox(src, title) {
                                        if (!src) return;
                                        this.previewModalSrc = src;
                                        this.previewModalTitle = title || 'Foto Bersama Tim';
                                        this.previewModalOpen = true;
                                    }
                                }"
                                @photo-saved.window="clearFile('foto-bersama-input-akun')"
                                class="space-y-3"
                            >
                                <div class="flex items-center justify-between">
                                    <flux:label>Foto Bersama Tim</flux:label>
                                    <span class="text-[11px] text-zinc-400">JPG, PNG, WebP &bull; Maks 5MB</span>
                                </div>
                                
                                <div class="relative overflow-hidden rounded-xl border-2 border-dashed border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 p-3 text-center transition-all hover:border-zinc-300 dark:hover:border-zinc-600">
                                    {{-- 1. Client-Side Instant Preview (immediately upon file selection) --}}
                                    <template x-if="clientPreviewUrl">
                                        <div class="relative group cursor-pointer" @click="openLightbox(clientPreviewUrl, 'Pratinjau Foto Baru')">
                                            <img 
                                                :src="clientPreviewUrl" 
                                                alt="Pratinjau Foto Baru" 
                                                class="w-full aspect-video sm:h-48 object-cover rounded-lg shadow-xs transition-transform duration-200 group-hover:scale-[1.01]" 
                                            />
                                            
                                            {{-- Hover overlay for zoom preview --}}
                                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex flex-col items-center justify-center text-white gap-1.5 p-2">
                                                <flux:icon icon="magnifying-glass-plus" class="size-6 text-white drop-shadow" />
                                                <span class="text-xs font-semibold bg-black/60 px-2.5 py-1 rounded-full drop-shadow">Klik untuk perbesar</span>
                                            </div>
                                            
                                            {{-- Badge: Siap Disimpan --}}
                                            <div class="absolute top-2 left-2 pointer-events-none">
                                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 bg-emerald-100 dark:bg-emerald-950/80 dark:text-emerald-300 px-2.5 py-0.5 rounded-full shadow-xs">
                                                    <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                                    Foto Baru Siap Disimpan
                                                </span>
                                            </div>
                                        </div>
                                    </template>

                                    {{-- 2. Fallback when no client blob: Livewire temporary OR existing saved photo OR placeholder --}}
                                    <div x-show="!clientPreviewUrl">
                                        @if ($foto_bersama)
                                            {{-- Livewire temporary uploaded preview --}}
                                            <div class="relative group cursor-pointer" @click="openLightbox('{{ $foto_bersama->temporaryUrl() }}', 'Pratinjau Foto Bersama')">
                                                <img src="{{ $foto_bersama->temporaryUrl() }}" alt="Preview Foto Bersama" class="w-full aspect-video sm:h-48 object-cover rounded-lg shadow-xs" />
                                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex flex-col items-center justify-center text-white gap-1.5">
                                                    <flux:icon icon="magnifying-glass-plus" class="size-6 text-white drop-shadow" />
                                                    <span class="text-xs font-semibold bg-black/60 px-2.5 py-1 rounded-full">Klik untuk perbesar</span>
                                                </div>
                                            </div>
                                        @elseif ($this->tim?->foto_bersama)
                                            {{-- Existing saved photo --}}
                                            <div class="relative group cursor-pointer" @click="openLightbox('{{ \Illuminate\Support\Facades\Storage::url($this->tim->foto_bersama) }}', 'Foto Bersama {{ addslashes($this->tim->nama_tim) }}')">
                                                <img src="{{ \Illuminate\Support\Facades\Storage::url($this->tim->foto_bersama) }}" alt="Foto Bersama {{ $this->tim->nama_tim }}" class="w-full aspect-video sm:h-48 object-cover rounded-lg shadow-xs transition-transform duration-200 group-hover:scale-[1.01]" />
                                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex flex-col items-center justify-center text-white gap-1.5">
                                                    <flux:icon icon="magnifying-glass-plus" class="size-6 text-white drop-shadow" />
                                                    <span class="text-xs font-semibold bg-black/60 px-2.5 py-1 rounded-full">Klik untuk perbesar</span>
                                                </div>
                                                <div class="absolute top-2 right-2 z-10" @click.stop>
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
                                            <div class="py-6 flex flex-col items-center justify-center space-y-2 text-zinc-400">
                                                <div class="size-12 rounded-full bg-zinc-200/70 dark:bg-zinc-700/60 flex items-center justify-center text-zinc-500 dark:text-zinc-400">
                                                    <flux:icon icon="camera" class="size-6" />
                                                </div>
                                                <div class="text-xs font-medium text-zinc-600 dark:text-zinc-300">Belum ada foto bersama</div>
                                                <div class="text-[11px] text-zinc-400 max-w-[220px] leading-tight">Foto ini akan tampil di profil tim dan identitas tim di sistem.</div>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Livewire Uploading Overlay --}}
                                    <div wire:loading wire:target="foto_bersama" class="absolute inset-0 bg-white/85 dark:bg-zinc-900/85 backdrop-blur-2xs flex flex-col items-center justify-center gap-2 z-20">
                                        <svg class="animate-spin size-6 text-[#3B71CA]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span class="text-xs font-medium text-[#3B71CA] dark:text-blue-400">Mengunggah pratinjau foto...</span>
                                    </div>

                                    {{-- Upload & Action Controls --}}
                                    <div class="mt-3 flex items-center justify-center gap-2">
                                        <label for="foto-bersama-input-akun" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 border border-zinc-300 dark:border-zinc-600 hover:bg-zinc-50 dark:hover:bg-zinc-700 cursor-pointer shadow-2xs transition-all">
                                            <flux:icon icon="arrow-up-tray" class="size-3.5 text-zinc-500" />
                                            <span x-text="(clientPreviewUrl || '{{ $this->tim?->foto_bersama }}' || '{{ $foto_bersama }}') ? 'Ganti Foto' : 'Unggah Foto'"></span>
                                        </label>
                                        <input 
                                            id="foto-bersama-input-akun" 
                                            type="file" 
                                            wire:model="foto_bersama" 
                                            @change="handleFileChange($event)"
                                            accept="image/jpeg,image/png,image/webp,image/jpg" 
                                            class="hidden" 
                                        />
                                        
                                        <template x-if="clientPreviewUrl">
                                            <button 
                                                type="button" 
                                                @click="clearFile('foto-bersama-input-akun'); $wire.set('foto_bersama', null)" 
                                                class="px-2.5 py-1.5 text-xs font-medium text-red-600 hover:text-red-700 dark:text-red-400 transition-colors"
                                            >
                                                Batal
                                            </button>
                                        </template>
                                        <div x-show="!clientPreviewUrl">
                                            @if ($foto_bersama)
                                                <button 
                                                    type="button" 
                                                    wire:click="$set('foto_bersama', null)" 
                                                    class="px-2.5 py-1.5 text-xs font-medium text-red-600 hover:text-red-700 dark:text-red-400 transition-colors"
                                                >
                                                    Batal
                                                </button>
                                            @endif
                                        </div>
                                    </div>

                                    @error('foto_bersama')
                                        <div class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Lightbox / Fullscreen Preview Image Modal --}}
                                <template x-teleport="body">
                                    <div 
                                        x-show="previewModalOpen" 
                                        x-cloak
                                        @keydown.escape.window="previewModalOpen = false"
                                        class="fixed inset-0 z-[999] flex items-center justify-center p-3 sm:p-6 bg-black/80 backdrop-blur-sm transition-opacity"
                                        x-transition:enter="ease-out duration-200"
                                        x-transition:enter-start="opacity-0"
                                        x-transition:enter-end="opacity-100"
                                        x-transition:leave="ease-in duration-150"
                                        x-transition:leave-start="opacity-100"
                                        x-transition:leave-end="opacity-0"
                                    >
                                        <div 
                                            @click.away="previewModalOpen = false"
                                            class="relative max-w-4xl w-full max-h-[92vh] bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl overflow-hidden border border-zinc-200 dark:border-zinc-800 flex flex-col"
                                            x-transition:enter="ease-out duration-200"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            x-transition:leave="ease-in duration-150"
                                            x-transition:leave-start="opacity-100 scale-100"
                                            x-transition:leave-end="opacity-0 scale-95"
                                        >
                                            {{-- Header --}}
                                            <div class="flex items-center justify-between px-5 py-3.5 border-b border-zinc-200 dark:border-zinc-800 shrink-0">
                                                <div class="flex items-center gap-3">
                                                    <div class="size-8 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-[#3B71CA] flex items-center justify-center">
                                                        <flux:icon icon="photo" class="size-4" />
                                                    </div>
                                                    <div>
                                                        <h3 class="font-semibold text-sm text-zinc-900 dark:text-zinc-100" x-text="previewModalTitle">Pratinjau Foto Tim</h3>
                                                        <p class="text-[11px] text-zinc-500">Pratinjau gambar bersama tim ukuran penuh</p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <a 
                                                        :href="previewModalSrc" 
                                                        target="_blank" 
                                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                                                        title="Buka gambar di tab baru"
                                                    >
                                                        <flux:icon icon="arrow-top-right-on-square" class="size-3.5" />
                                                        <span>Tab Baru</span>
                                                    </a>
                                                    <button 
                                                        type="button" 
                                                        @click="previewModalOpen = false"
                                                        class="p-1.5 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                                                    >
                                                        <flux:icon icon="x-mark" class="size-5" />
                                                    </button>
                                                </div>
                                            </div>

                                            {{-- Image Display Area --}}
                                            <div class="p-4 sm:p-6 bg-zinc-950/5 dark:bg-black/40 flex-1 overflow-auto flex items-center justify-center min-h-[250px]">
                                                <img 
                                                    :src="previewModalSrc" 
                                                    alt="Pratinjau Foto Resolusi Penuh" 
                                                    class="max-h-[70vh] w-auto max-w-full rounded-xl object-contain shadow-md mx-auto"
                                                />
                                            </div>

                                            {{-- Footer --}}
                                            <div class="flex items-center justify-between px-5 py-3 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/20 text-xs text-zinc-500 shrink-0">
                                                <span class="hidden sm:inline">Tekan <kbd class="px-1.5 py-0.5 rounded bg-zinc-200 dark:bg-zinc-700 font-mono text-[10px]">ESC</kbd> untuk menutup</span>
                                                <span class="sm:hidden">Sentuh tombol untuk keluar</span>
                                                <flux:button size="sm" variant="ghost" @click="previewModalOpen = false">
                                                    Tutup
                                                </flux:button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
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
                    </div>
                </div>
            </div>
        @else
            {{-- SECTION: PENGATURAN --}}
            <div class="space-y-6">
                {{-- Card: Alamat Email Akun Tim --}}
                <flux:card class="border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs space-y-4">
                    <div>
                        <flux:heading size="md">Alamat Email Akun</flux:heading>
                        <flux:subheading class="text-xs">Digunakan untuk notifikasi keamanan, pemulihan akun, dan reset password saat lupa</flux:subheading>
                    </div>

                    <form wire:submit="updateEmail" class="space-y-4">
                        <div class="space-y-1">
                            <flux:input
                                wire:model="email"
                                type="email"
                                label="Email Tim"
                                placeholder="cth. tim@instansi.sch.id atau email ketua"
                            />
                            @if (!auth()->user()->email)
                                <p class="text-xs text-amber-600 dark:text-amber-400 mt-1 flex items-center gap-1">
                                    <flux:icon icon="exclamation-circle" class="size-3.5 shrink-0" />
                                    <span>Akun ini belum memiliki email. Masukkan email agar fitur Lupa Password dapat digunakan.</span>
                                </p>
                            @else
                                <p class="text-xs text-green-600 dark:text-green-400 mt-1 flex items-center gap-1">
                                    <flux:icon icon="check-circle" class="size-3.5 shrink-0" />
                                    <span>Email terdaftar dan aktif.</span>
                                </p>
                            @endif
                        </div>

                        <div class="flex items-center justify-between gap-3 pt-1">
                            @if (!auth()->user()->email)
                                <flux:link href="{{ route('tim.tambah-email') }}" wire:navigate class="text-xs text-[#3B71CA] hover:underline">
                                    Verifikasi via OTP &rarr;
                                </flux:link>
                            @else
                                <span></span>
                            @endif

                            <flux:button type="submit" variant="primary" size="sm">
                                Simpan Email
                            </flux:button>
                        </div>
                    </form>
                </flux:card>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {{-- Left Column: Password --}}
                    <div class="space-y-6">
                        {{-- Form Ubah Password Akun --}}
                        <flux:card class="border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs space-y-4">
                            <div>
                                <flux:heading size="md">Keamanan & Password</flux:heading>
                                <flux:subheading class="text-xs">Ganti password akun tim agar lebih aman</flux:subheading>
                            </div>

                            <form wire:submit="ubahPassword" class="space-y-4" x-data="{
                                pw: '',
                                pwConfirm: '',
                                get isMatch() {
                                    return this.pw.length > 0 && this.pwConfirm.length > 0 && this.pw === this.pwConfirm;
                                },
                                get isMismatch() {
                                    return this.pw.length > 0 && this.pwConfirm.length > 0 && this.pw !== this.pwConfirm;
                                }
                            }">
                                <flux:input
                                    wire:model="password_baru"
                                    x-model="pw"
                                    type="password"
                                    label="Password Baru"
                                    required
                                    placeholder="Minimal 8 karakter"
                                    viewable
                                />

                                <div>
                                    <flux:input
                                        wire:model="password_baru_confirmation"
                                        x-model="pwConfirm"
                                        type="password"
                                        label="Ulangi Password Baru"
                                        required
                                        placeholder="Konfirmasi password"
                                        viewable
                                    />

                                    {{-- Client-side Password Match Feedback --}}
                                    <div x-show="isMatch" x-cloak class="flex items-center gap-1.5 text-xs text-green-600 dark:text-green-400 font-medium mt-1.5">
                                        <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        <span>Password cocok</span>
                                    </div>
                                    <div x-show="isMismatch" x-cloak class="flex items-center gap-1.5 text-xs text-red-600 dark:text-red-400 font-medium mt-1.5">
                                        <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        <span>Konfirmasi password belum sama</span>
                                    </div>
                                </div>

                                <div class="pt-1">
                                    <flux:button type="submit" variant="filled" class="w-full" x-bind:disabled="isMismatch">
                                        Perbarui Password
                                    </flux:button>
                                </div>
                            </form>
                        </flux:card>
                    </div>

                    {{-- Right Column: 2FA/Passkey & Logout --}}
                    <div class="space-y-6">
                        {{-- 2FA & Passkey Card --}}
                        <flux:card class="border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs space-y-4">
                            <div>
                                <flux:heading size="md">Autentikasi 2 Langkah & Passkey</flux:heading>
                                <flux:subheading class="text-xs">Tingkatkan proteksi akun dengan Google Authenticator / Authy atau login instan dengan sensor sidik jari / Face ID</flux:subheading>
                            </div>

                            <div class="space-y-2">
                                <flux:button href="{{ route('profile.edit') }}" wire:navigate variant="outline" class="w-full" icon="finger-print">
                                    Kelola 2FA & Passkey
                                </flux:button>
                            </div>
                        </flux:card>

                        {{-- Logout Card --}}
                        <flux:card class="border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs space-y-4">
                            <div>
                                <flux:heading size="md">Keluar dari Akun</flux:heading>
                                <flux:subheading class="text-xs">Keluar dari sesi login saat ini. Anda perlu login kembali untuk mengakses sistem.</flux:subheading>
                            </div>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <flux:button type="submit" variant="danger" class="w-full" icon="arrow-right-start-on-rectangle">
                                    Logout
                                </flux:button>
                            </form>
                        </flux:card>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Modals (shared between sections) --}}
    <flux:modal name="modal-personil" class="max-w-md">
        <form wire:submit="simpanPersonil" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editingPersonilId ? 'Edit Anggota Personil' : 'Tambah Anggota Baru' }}</flux:heading>
                <flux:subheading>Masukkan data personil tim untuk penjadwalan WFO & tugas.</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="personil_nama" label="Nama Lengkap" placeholder="Nama anggota tim" required autofocus />
                <flux:select wire:model="personil_jenis_kelamin" label="Jenis Kelamin" required>
                    <flux:select.option value="laki-laki">Laki-laki (Dapat ditugaskan adzan/kitab)</flux:select.option>
                    <flux:select.option value="perempuan">Perempuan</flux:select.option>
                </flux:select>
                <flux:input wire:model="personil_no_hp" label="Nomor WhatsApp / HP" placeholder="cth. 08123456789" />
                <flux:select wire:model="personil_status" label="Status Anggota">
                    <flux:select.option value="aktif">Aktif (Ikut Penjadwalan)</flux:select.option>
                    <flux:select.option value="nonaktif">Nonaktif (Tidak Ikut Penjadwalan)</flux:select.option>
                </flux:select>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ $editingPersonilId ? 'Simpan Perubahan' : 'Tambahkan' }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="modal-hapus-personil" class="max-w-sm">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Hapus Anggota?</flux:heading>
                <flux:subheading>Anggota ini akan dihapus dari daftar tim. Tugas masa lalu yang terkait tetap tersimpan di riwayat.</flux:subheading>
            </div>
            <div class="flex items-center justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="hapusPersonil">Hapus Anggota</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
