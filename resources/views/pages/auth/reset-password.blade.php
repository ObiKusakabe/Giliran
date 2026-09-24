<x-layouts::auth.split :title="__('Reset Password')">
    <div class="space-y-6">
        <div class="text-center space-y-2">
            <flux:heading size="xl">{{ __('Buat Password Baru') }}</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400 text-sm">
                {{ __('Silakan tentukan password baru yang aman untuk akun Anda.') }}
            </flux:text>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form 
            method="POST" 
            action="{{ route('password.update') }}" 
            class="flex flex-col gap-5"
            x-data="{
                pw: '',
                pwConfirm: '',
                isSubmitting: false,
                get isMatch() {
                    return this.pw.length > 0 && this.pwConfirm.length > 0 && this.pw === this.pwConfirm;
                },
                get isMismatch() {
                    return this.pw.length > 0 && this.pwConfirm.length > 0 && this.pw !== this.pwConfirm;
                }
            }"
            @submit="isSubmitting = true"
        >
            @csrf

            <!-- Token -->
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <!-- Email Address -->
            <flux:input
                name="email"
                value="{{ request('email') }}"
                :label="__('Alamat Email')"
                type="email"
                required
                autocomplete="email"
                placeholder="nama@email.com"
            />

            <!-- Password -->
            <flux:input
                name="password"
                x-model="pw"
                :label="__('Password Baru')"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Minimal 8 karakter"
                viewable
            />

            <!-- Confirm Password -->
            <div>
                <flux:input
                    name="password_confirmation"
                    x-model="pwConfirm"
                    :label="__('Konfirmasi Password Baru')"
                    type="password"
                    required
                    autocomplete="new-password"
                    placeholder="Ulangi password baru"
                    viewable
                />

                {{-- Client-side Password Match Feedback --}}
                <div x-show="isMatch" x-cloak class="flex items-center gap-1.5 text-xs text-green-600 dark:text-green-400 font-medium mt-1.5">
                    <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>{{ __('Password cocok') }}</span>
                </div>
                <div x-show="isMismatch" x-cloak class="flex items-center gap-1.5 text-xs text-red-600 dark:text-red-400 font-medium mt-1.5">
                    <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    <span>{{ __('Konfirmasi password belum sama') }}</span>
                </div>
            </div>

            <button 
                type="submit"
                x-bind:disabled="isMismatch || isSubmitting"
                class="w-full px-4 py-2.5 text-sm font-semibold text-white bg-[#3B71CA] hover:bg-[#2d5db3] disabled:bg-[#3B71CA]/60 disabled:cursor-not-allowed rounded-lg transition-all focus:outline-none focus:ring-2 focus:ring-[#3B71CA] focus:ring-offset-2"
                data-test="reset-password-button"
            >
                <span x-show="!isSubmitting" class="inline-flex items-center justify-center gap-2">
                    <span>{{ __('Simpan Password Baru') }}</span>
                </span>
                <span x-show="isSubmitting" x-cloak class="inline-flex items-center justify-center gap-2">
                    <span style="animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;">{{ __('Memperbarui...') }}</span>
                    <svg class="animate-spin size-4 shrink-0 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </span>
            </button>
        </form>

        <div class="text-center text-sm text-zinc-500 dark:text-zinc-400">
            <span>{{ __('Batal mengubah?') }}</span>
            <flux:link :href="route('login')" class="font-medium text-[#3B71CA] hover:underline ml-1" wire:navigate>
                {{ __('Kembali ke halaman masuk') }}
            </flux:link>
        </div>
    </div>
</x-layouts::auth.split>
