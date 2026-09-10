<x-layouts::auth.split :title="__('Masuk')">
    <div class="space-y-6">
        <flux:heading class="text-center" size="xl">{{ __('Masuk') }}</flux:heading>

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

        <form 
            method="POST" 
            action="{{ route('login.store') }}" 
            class="flex flex-col gap-5"
            x-data="{ isSubmitting: false }"
            @submit="isSubmitting = true"
        >
            @csrf

            <!-- Email or Username -->
            <flux:input
                name="email"
                :label="__('Email atau Username')"
                :value="old('email')"
                type="text"
                required
                autofocus
                autocomplete="email"
                placeholder="email@inovindo.co.id atau username"
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

            <button 
                type="submit"
                x-bind:disabled="isSubmitting"
                class="w-full px-4 py-2.5 text-sm font-semibold text-white bg-[#3B71CA] hover:bg-[#2d5db3] disabled:bg-[#3B71CA]/60 disabled:cursor-not-allowed rounded-lg transition-all focus:outline-none focus:ring-2 focus:ring-[#3B71CA] focus:ring-offset-2"
                style="min-width: 180px;"
            >
                {{-- State 1: Idle --}}
                <span x-show="!isSubmitting" class="inline-flex items-center justify-center gap-2">
                    <span>{{ __('Masuk') }}</span>
                </span>
                
                {{-- State 2: Processing (memverifikasi... + spinner) --}}
                <span x-show="isSubmitting" x-cloak class="inline-flex items-center justify-center gap-2">
                    <span style="animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;">{{ __('Memverifikasi...') }}</span>
                    <svg class="animate-spin size-4 shrink-0 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </span>
            </button>
        </form>
    </div>
</x-layouts::auth.split>
