<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pengaturan Profil')] #[Layout('layouts.admin', ['breadcrumbs' => [['label' => 'Pengaturan'], ['label' => 'Profil']]])] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
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

<div class="w-full space-y-6">
    {{-- Settings Navigation Tabs - Horizontal --}}
    <div class="flex gap-1 border-b border-zinc-200 dark:border-zinc-700">
        <a 
            href="{{ route('profile.edit') }}" 
            wire:navigate.hover
            @class([
                'px-4 py-2 text-sm font-medium rounded-t-lg transition-colors',
                'border-b-2 text-blue-600 dark:text-blue-400 border-blue-600 dark:border-blue-400' => request()->routeIs('profile.edit'),
                'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 border-b-2 border-transparent' => !request()->routeIs('profile.edit'),
            ])
        >
            Profil
        </a>
        <a 
            href="{{ route('security.edit') }}" 
            wire:navigate.hover
            @class([
                'px-4 py-2 text-sm font-medium rounded-t-lg transition-colors',
                'border-b-2 text-blue-600 dark:text-blue-400 border-blue-600 dark:border-blue-400' => request()->routeIs('security.edit'),
                'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 border-b-2 border-transparent' => !request()->routeIs('security.edit'),
            ])
        >
            Keamanan
        </a>
        <a 
            href="{{ route('appearance.edit') }}" 
            wire:navigate.hover
            @class([
                'px-4 py-2 text-sm font-medium rounded-t-lg transition-colors',
                'border-b-2 text-blue-600 dark:text-blue-400 border-blue-600 dark:border-blue-400' => request()->routeIs('appearance.edit'),
                'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 border-b-2 border-transparent' => !request()->routeIs('appearance.edit'),
            ])
        >
            Tampilan
        </a>
    </div>

    {{-- Profile Content --}}
    <div class="max-w-3xl space-y-6">
        <div>
            <flux:heading size="lg">Profil</flux:heading>
            <flux:subheading>Perbarui nama dan alamat email kamu</flux:subheading>
        </div>

        <form wire:submit="updateProfileInformation" class="space-y-6">
            <flux:input wire:model="name" :label="'Nama'" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="'Email'" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ 'Alamat email kamu belum diverifikasi.' }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ 'Klik di sini untuk kirim ulang email verifikasi.' }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ 'Tautan verifikasi baru telah dikirim ke email kamu.' }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit" data-test="update-profile-button">
                    Simpan
                </flux:button>
            </div>
        </form>

        @if ($this->showDeleteUser)
            <div class="pt-6 border-t border-zinc-200 dark:border-zinc-700">
                <livewire:pages::settings.delete-user-form />
            </div>
        @endif
    </div>
</div>
