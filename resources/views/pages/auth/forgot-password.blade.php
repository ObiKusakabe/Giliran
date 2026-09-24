<x-layouts::auth.split :title="__('Lupa Password')">
    <div class="space-y-6">
        <div class="text-center space-y-2">
            <flux:heading size="xl">{{ __('Lupa Password') }}</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400 text-sm">
                {{ __('Masukkan email atau username akun Anda. Kami akan mengirimkan tautan untuk mengatur ulang password.') }}
            </flux:text>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form 
            method="POST" 
            action="{{ route('password.email') }}" 
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
                placeholder="nama@email.com atau username akun"
            />

            <button 
                type="submit"
                x-bind:disabled="isSubmitting"
                class="w-full px-4 py-2.5 text-sm font-semibold text-white bg-[#3B71CA] hover:bg-[#2d5db3] disabled:bg-[#3B71CA]/60 disabled:cursor-not-allowed rounded-lg transition-all focus:outline-none focus:ring-2 focus:ring-[#3B71CA] focus:ring-offset-2"
                data-test="email-password-reset-link-button"
            >
                <span x-show="!isSubmitting" class="inline-flex items-center justify-center gap-2">
                    <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <span>{{ __('Kirim Tautan Reset Password') }}</span>
                </span>
                <span x-show="isSubmitting" x-cloak class="inline-flex items-center justify-center gap-2">
                    <span style="animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;">{{ __('Mengirim Tautan...') }}</span>
                    <svg class="animate-spin size-4 shrink-0 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </span>
            </button>
        </form>

        <div class="text-center text-sm text-zinc-500 dark:text-zinc-400">
            <span>{{ __('Sudah ingat password?') }}</span>
            <flux:link :href="route('login')" class="font-medium text-[#3B71CA] hover:underline ml-1" wire:navigate>
                {{ __('Kembali ke halaman masuk') }}
            </flux:link>
        </div>
    </div>
</x-layouts::auth.split>
