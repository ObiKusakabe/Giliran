<x-layouts::auth.split :title="__('Masuk')">
    <div class="space-y-6">
        <flux:heading class="text-center" size="xl">{{ __('Selamat Datang') }}</flux:heading>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <!-- Passkey Login -->
        <x-passkey-verify
            options-route="passkey.login-options"
            submit-route="passkey.login"
            :label="__('Masuk dengan Passkey')"
            :loading-label="__('Mengautentikasi...')"
            :separator="__('atau')"
        />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5" x-data="{ loading: false }" @submit="loading = true">
            @csrf

            <!-- Email -->
            <flux:input
                name="email"
                :label="__('Email')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@inovindo.co.id"
            />

            <!-- Password -->
            <flux:field>
                <div class="mb-2 flex justify-between">
                    <flux:label>{{ __('Password') }}</flux:label>

                    @if (Route::has('password.request'))
                        <flux:link
                            :href="route('password.request')"
                            variant="subtle"
                            class="text-sm"
                            wire:navigate
                        >
                            {{ __('Lupa password?') }}
                        </flux:link>
                    @endif
                </div>

                <flux:input
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password Anda')"
                    viewable
                />
            </flux:field>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Ingat saya')" :checked="old('remember')" />

            <flux:button variant="primary" type="submit" class="w-full" x-bind:disabled="loading">
                <span x-show="!loading">{{ __('Masuk') }}</span>
                <span x-show="loading" x-cloak class="flex items-center justify-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    {{ __('Memproses...') }}
                </span>
            </flux:button>
        </form>

        @if (Route::has('register'))
            <flux:text class="text-center text-sm">
                {{ __('Belum memiliki akun?') }}
                <flux:link :href="route('register')" wire:navigate class="font-medium ms-1">{{ __('Daftar') }}</flux:link>
            </flux:text>
        @endif
    </div>
</x-layouts::auth.split>
