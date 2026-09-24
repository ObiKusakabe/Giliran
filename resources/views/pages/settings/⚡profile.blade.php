<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use Flux\Flux;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pengaturan')] #[Layout('layouts.admin', ['breadcrumbs' => [['label' => 'Pengaturan']]])] class extends Component {
    use ProfileValidationRules;
    use PasswordValidationRules;

    // Profile fields
    public string $name = '';
    public ?string $email = '';

    // Password fields
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    // Security features
    public bool $canManageTwoFactor;
    public bool $twoFactorEnabled;
    public bool $requiresConfirmation;

    #[Locked]
    public bool $canManagePasskeys;

    #[Locked]
    public array $passkeys = [];

    public bool $showDeleteModal = false;

    #[Locked]
    public ?int $deletingPasskeyId = null;

    #[Locked]
    public string $deletingPasskeyName = '';

    /**
     * Mount the component.
     */
    public function mount(\Laravel\Fortify\Actions\DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        // Profile
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email ?? '';

        // Two-Factor
        $this->canManageTwoFactor = \Laravel\Fortify\Features::canManageTwoFactorAuthentication();

        if ($this->canManageTwoFactor) {
            if (\Laravel\Fortify\Fortify::confirmsTwoFactorAuthentication() && is_null(auth()->user()->two_factor_confirmed_at)) {
                $disableTwoFactorAuthentication(auth()->user());
            }

            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
            $this->requiresConfirmation = \Laravel\Fortify\Features::optionEnabled(\Laravel\Fortify\Features::twoFactorAuthentication(), 'confirm');
        }

        // Passkeys
        $this->canManagePasskeys = \Laravel\Fortify\Features::canManagePasskeys();

        if ($this->canManagePasskeys) {
            $this->loadPasskeys();
        }
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        if (array_key_exists('email', $validated) && empty($validated['email'])) {
            $validated['email'] = null;
        }

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        Flux::toast(variant: 'success', text: __('Password updated.'));
    }

    /**
     * Load the user's passkeys.
     */
    public function loadPasskeys(): void
    {
        $this->passkeys = auth()->user()->passkeys()
            ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
            ->latest()
            ->get()
            ->map(fn ($passkey) => [
                'id' => $passkey->id,
                'name' => $passkey->name,
                'authenticator' => $passkey->authenticator,
                'created_at_diff' => $passkey->created_at->diffForHumans(),
                'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
            ])
            ->toArray();
    }

    /**
     * Show the delete confirmation modal.
     */
    public function confirmDelete(int $passkeyId): void
    {
        $passkey = auth()->user()->passkeys()->findOrFail($passkeyId);

        $this->deletingPasskeyId = $passkey->id;
        $this->deletingPasskeyName = $passkey->name;
        $this->showDeleteModal = true;
    }

    /**
     * Delete the passkey.
     */
    public function deletePasskey(\Laravel\Passkeys\Actions\DeletePasskey $deletePasskey): void
    {
        if (! $this->deletingPasskeyId) {
            return;
        }

        $passkey = auth()->user()->passkeys()->findOrFail($this->deletingPasskeyId);

        $deletePasskey(auth()->user(), $passkey);

        $this->closeDeleteModal();
        $this->loadPasskeys();
    }

    /**
     * Close the delete confirmation modal.
     */
    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingPasskeyId = null;
        $this->deletingPasskeyName = '';
    }

    /**
     * Handle the two-factor authentication enabled event.
     */
    #[On('two-factor-enabled')]
    public function onTwoFactorEnabled(): void
    {
        $this->twoFactorEnabled = true;
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disable(\Laravel\Fortify\Actions\DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication(auth()->user());

        $this->twoFactorEnabled = false;
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<div class="max-w-4xl mx-auto">
    <div>
        <flux:heading size="xl">Pengaturan</flux:heading>
        <flux:text class="text-zinc-500 mt-1">Kelola informasi profil akun, keamanan & password, serta preferensi tema antarmuka.</flux:text>
    </div>

    <flux:separator variant="subtle" class="my-8" />

    {{-- SECTION 1: PROFIL --}}
    <div class="flex flex-col lg:flex-row gap-6 lg:gap-12">
        <div class="lg:w-80 shrink-0">
            <flux:heading size="lg">Profil</flux:heading>
            <flux:text class="mt-2 text-zinc-500">Perbarui nama lengkap dan alamat email akun Anda.</flux:text>
        </div>

        <div class="flex-1 space-y-6">
            <form wire:submit="updateProfileInformation" class="space-y-6">
                <flux:input wire:model="name" label="Nama" type="text" required autofocus autocomplete="name" />

                <div>
                    <flux:input wire:model="email" label="Email (Opsional)" placeholder="cth. nama@email.com" type="email" autocomplete="email" />

                    @if ($this->hasUnverifiedEmail)
                        <div class="mt-4">
                            <flux:text>
                                Alamat email kamu belum diverifikasi.
                                <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                    Klik di sini untuk kirim ulang email verifikasi.
                                </flux:link>
                            </flux:text>

                            @if (session('status') === 'verification-link-sent')
                                <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                    Tautan verifikasi baru telah dikirim ke email kamu.
                                </flux:text>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="flex justify-end">
                    <flux:button variant="primary" type="submit" data-test="update-profile-button">
                        Simpan Profil
                    </flux:button>
                </div>
            </form>

            @if ($this->showDeleteUser)
                <flux:separator variant="subtle" class="my-6" />
                <livewire:pages::settings.delete-user-form />
            @endif
        </div>
    </div>

    <flux:separator variant="subtle" class="my-8" />

    {{-- SECTION 2: KEAMANAN & PASSWORD --}}
    <div class="flex flex-col lg:flex-row gap-6 lg:gap-12">
        <div class="lg:w-80 shrink-0">
            <flux:heading size="lg">Keamanan & Password</flux:heading>
            <flux:text class="mt-2 text-zinc-500">Pastikan akun kamu menggunakan password yang kuat serta kelola autentikasi tambahan.</flux:text>
        </div>

        <div class="flex-1 space-y-8">
            {{-- Update Password Form --}}
            <form method="POST" wire:submit="updatePassword" class="space-y-6"
                @submit.capture="if (isMismatch) { $event.preventDefault(); $event.stopImmediatePropagation(); }"
                @keydown.enter="if (isMismatch) { $event.preventDefault(); }"
                x-data="{
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
                    wire:model="current_password"
                    label="Password Saat Ini"
                    type="password"
                    required
                    autocomplete="current-password"
                    viewable
                />
                <flux:input
                    wire:model="password"
                    x-model="pw"
                    label="Password Baru"
                    type="password"
                    required
                    autocomplete="new-password"
                    placeholder="Minimal 8 karakter"
                    viewable
                />
                <div>
                    <flux:input
                        wire:model="password_confirmation"
                        x-model="pwConfirm"
                        label="Konfirmasi Password"
                        type="password"
                        required
                        autocomplete="new-password"
                        placeholder="Ulangi password baru"
                        viewable
                    />

                    {{-- Client-side Password Match Feedback --}}
                    <div x-show="isMatch" x-cloak class="flex items-center gap-1.5 text-xs text-green-600 dark:text-green-400 font-medium mt-2">
                        <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Password cocok</span>
                    </div>
                    <div x-show="isMismatch" x-cloak class="flex items-center gap-1.5 text-xs text-red-600 dark:text-red-400 font-medium mt-2">
                        <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <span>Konfirmasi password belum sama</span>
                    </div>
                </div>

                <div class="flex justify-end">
                    <flux:button
                        variant="primary"
                        type="submit"
                        class="no-disabled-spinner"
                        data-no-disabled-spinner
                        data-test="update-password-button"
                        x-bind:disabled="isMismatch"
                        x-bind:class="isMismatch ? 'opacity-40 cursor-not-allowed pointer-events-none' : ''"
                    >
                        Perbarui Password
                    </flux:button>
                </div>
            </form>

            {{-- Two-Factor Authentication --}}
            @if ($canManageTwoFactor)
                <flux:separator variant="subtle" class="my-6" />

                <div class="space-y-4">
                    <div>
                        <flux:heading size="md">{{ __('Two-factor authentication') }} (2FA)</flux:heading>
                        <flux:text class="mt-1 text-zinc-500">Amankan akun Anda dengan verifikasi kode tambahan saat proses masuk</flux:text>
                    </div>

                    <div class="flex flex-col w-full space-y-4 text-sm" wire:cloak>
                        @if ($twoFactorEnabled)
                            <div class="space-y-4">
                                <flux:text>
                                    Anda akan diminta memasukkan PIN acak dari aplikasi autentikator di ponsel Anda (seperti Google Authenticator atau Authy) setiap kali login.
                                </flux:text>

                                <div class="flex justify-start">
                                    <flux:button
                                        variant="danger"
                                        wire:click="disable"
                                    >
                                        Nonaktifkan 2FA
                                    </flux:button>
                                </div>

                                <livewire:pages::settings.two-factor.recovery-codes :$requiresConfirmation />
                            </div>
                        @else
                            <div class="space-y-4">
                                <flux:text variant="subtle">
                                    Saat Anda mengaktifkan autentikasi dua langkah, Anda akan diminta memasukkan PIN keamanan saat masuk. PIN ini dapat diambil dari aplikasi pendukung TOTP di ponsel Anda.
                                </flux:text>

                                <flux:modal.trigger name="two-factor-setup-modal">
                                    <flux:button
                                        variant="primary"
                                        wire:click="$dispatch('start-two-factor-setup')"
                                    >
                                        Aktifkan 2FA
                                    </flux:button>
                                </flux:modal.trigger>

                                <livewire:pages::settings.two-factor-setup-modal :requires-confirmation="$requiresConfirmation" />
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Passkeys --}}
            @if ($canManagePasskeys)
                <flux:separator variant="subtle" class="my-6" />

                <div class="space-y-4">
                    <div>
                        <flux:heading size="md">Passkeys (Masuk Tanpa Password)</flux:heading>
                        <flux:text class="mt-1 text-zinc-500">Kelola passkey perangkat Anda untuk login cepat menggunakan sensor sidik jari, Face ID, atau PIN perangkat</flux:text>
                    </div>

                    <div class="flex flex-col w-full space-y-4 text-sm" wire:cloak>
                        <div class="border rounded-lg border-zinc-200 dark:border-zinc-700 overflow-hidden">
                            @forelse ($passkeys as $passkey)
                                <div class="flex items-center justify-between p-4 {{ ! $loop->last ? 'border-b border-zinc-200 dark:border-zinc-700' : '' }}">
                                    <div class="flex items-center gap-4">
                                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800">
                                            <flux:icon.key class="size-5 text-zinc-500 dark:text-zinc-400" />
                                        </div>
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-2.5">
                                                <p class="font-medium tracking-tight">{{ $passkey['name'] }}</p>
                                                @if ($passkey['authenticator'])
                                                    <flux:badge size="sm">{{ $passkey['authenticator'] }}</flux:badge>
                                                @endif
                                            </div>
                                            <p class="text-zinc-500 dark:text-zinc-400 text-xs">
                                                Ditambahkan {{ $passkey['created_at_diff'] }}
                                                @if ($passkey['last_used_at_diff'])
                                                    <span class="opacity-50 mx-1">/</span>
                                                    Terakhir digunakan {{ $passkey['last_used_at_diff'] }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>

                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="trash"
                                        icon:variant="outline"
                                        wire:click="confirmDelete({{ $passkey['id'] }})"
                                        class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/50"
                                    />
                                </div>
                            @empty
                                <div class="p-8 text-center">
                                    <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-zinc-800">
                                        <flux:icon.key class="size-7 text-zinc-400 dark:text-zinc-500" />
                                    </div>
                                    <p class="font-medium">{{ __('No passkeys yet') }}</p>
                                    <flux:text class="mt-1">{{ __('Add a passkey to sign in without a password') }}</flux:text>
                                </div>
                            @endforelse
                        </div>

                        <x-passkey-registration />
                    </div>
                </div>
            @endif
        </div>
    </div>

    <flux:separator variant="subtle" class="my-8" />

    {{-- SECTION 3: TAMPILAN & TEMA --}}
    <div class="flex flex-col lg:flex-row gap-6 lg:gap-12 pb-12">
        <div class="lg:w-80 shrink-0">
            <flux:heading size="lg">Tampilan</flux:heading>
            <flux:text class="mt-2 text-zinc-500">Atur preferensi mode terang/gelap dan tema warna antarmuka aplikasi.</flux:text>
        </div>

        <div class="flex-1 space-y-6">
            <div
                x-data="{
                    inovindoActive: localStorage.getItem('theme-inovindo') === 'true',

                    toggleInovindo() {
                        this.inovindoActive = !this.inovindoActive;
                        localStorage.setItem('theme-inovindo', this.inovindoActive);

                        if (this.inovindoActive) {
                            document.documentElement.classList.add('inovindo');
                            document.documentElement.classList.remove('dark');
                            localStorage.setItem('flux-appearance', 'light');
                            if (window.$flux) window.$flux.appearance = 'light';
                        } else {
                            document.documentElement.classList.remove('inovindo');
                        }
                    },

                    onFluxChange(val) {
                        if (val === 'light' || val === 'dark') {
                            this.inovindoActive = false;
                            localStorage.setItem('theme-inovindo', 'false');
                            document.documentElement.classList.remove('inovindo');
                        }
                    }
                }"
                class="flex flex-col gap-5"
            >
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Mode Tampilan</label>
                    <flux:radio.group
                        x-data
                        variant="segmented"
                        x-model="$flux.appearance"
                        @change="onFluxChange($event.target.value)"
                    >
                        <flux:radio value="light" icon="sun">Terang</flux:radio>
                        <flux:radio value="dark" icon="moon">Gelap</flux:radio>
                    </flux:radio.group>
                </div>

                {{-- Tema Inovindo --}}
                <div class="flex items-center justify-between rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 px-4 py-3.5">
                    <div class="flex items-center gap-3">
                        <div class="flex gap-1">
                            <span class="h-4 w-4 rounded-full" style="background:#12314F"></span>
                            <span class="h-4 w-4 rounded-full" style="background:#3B71CA"></span>
                            <span class="h-4 w-4 rounded-full" style="background:#2FA84F"></span>
                            <span class="h-4 w-4 rounded-full" style="background:#F2A340"></span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Tema Inovindo</p>
                            <p class="text-xs text-zinc-500">Palet warna resmi PT Inovindo Digital Media</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="toggleInovindo()"
                        :class="inovindoActive ? 'bg-[#3B71CA]' : 'bg-zinc-200 dark:bg-zinc-700'"
                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#3B71CA] focus:ring-offset-2"
                        role="switch"
                        :aria-checked="inovindoActive"
                    >
                        <span
                            :class="inovindoActive ? 'translate-x-5' : 'translate-x-0'"
                            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                        ></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Delete Passkey Modal --}}
    <flux:modal
        name="delete-passkey-modal"
        class="max-w-md md:min-w-md"
        @close="closeDeleteModal"
        wire:model="showDeleteModal"
    >
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">Hapus Passkey</flux:heading>
                <flux:text>
                    Apakah Anda yakin ingin menghapus passkey "<strong>{{ $deletingPasskeyName }}</strong>"? Anda tidak dapat lagi menggunakannya untuk masuk ke akun ini.
                </flux:text>
            </div>

            <div class="flex gap-3 justify-end">
                <flux:button
                    variant="outline"
                    wire:click="closeDeleteModal"
                >
                    Batal
                </flux:button>
                <flux:button
                    variant="danger"
                    wire:click="deletePasskey"
                >
                    Hapus Passkey
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
