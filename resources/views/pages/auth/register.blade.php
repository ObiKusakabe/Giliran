<x-layouts::auth.split :title="__('Daftar Akun')">
    <div class="space-y-6">
        <flux:heading class="text-center" size="xl">{{ __('Buat Akun Baru') }}</flux:heading>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-5" x-data="{ loading: false }" @submit="loading = true">
            @csrf

            <!-- Name -->
            <flux:input
                name="name"
                :label="__('Nama Lengkap')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Nama lengkap Anda')"
            />

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Alamat Email')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@inovindo.co.id"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Minimal 8 karakter')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Konfirmasi Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Ulangi password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button" x-bind:disabled="loading">
                <span x-show="!loading">{{ __('Daftar Sekarang') }}</span>
                <span x-show="loading" x-cloak class="flex items-center justify-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    {{ __('Mendaftarkan...') }}
                </span>
            </flux:button>
        </form>

        <flux:text class="text-center text-sm">
            {{ __('Sudah memiliki akun?') }}
            <flux:link :href="route('login')" wire:navigate class="font-medium ms-1">{{ __('Masuk') }}</flux:link>
        </flux:text>
    </div>
</x-layouts::auth.split>
