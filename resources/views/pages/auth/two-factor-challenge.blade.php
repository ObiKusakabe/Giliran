<x-layouts::auth.split :title="__('Verifikasi Dua Langkah')" :skip-splash="true">
    <div class="space-y-6">
        <div
            class="relative w-full h-auto"
            x-cloak
            x-data="{
                showRecoveryInput: @js($errors->has('recovery_code')),
                code: '',
                recovery_code: '',
                isSubmitting: false,
                focusOtp() {
                    this.$nextTick(() => this.$refs.otp?.querySelector('input')?.focus());
                },
                init() {
                    if (! this.showRecoveryInput) {
                        this.focusOtp();
                    }
                    this.$watch('code', (val) => {
                        let clean = String(val || '').replace(/\D/g, '');
                        if (clean.length === 6 && !this.isSubmitting) {
                            this.isSubmitting = true;
                            this.$nextTick(() => {
                                this.$refs.challengeForm?.submit();
                            });
                        }
                    });
                },
                toggleInput() {
                    this.showRecoveryInput = !this.showRecoveryInput;
                    this.code = '';
                    this.recovery_code = '';

                    $nextTick(() => {
                        this.showRecoveryInput
                            ? this.$refs.recovery_code?.focus()
                            : this.focusOtp();
                    });
                },
            }"
        >
            <div x-show="!showRecoveryInput" class="text-center space-y-2">
                <flux:heading size="xl">{{ __('Verifikasi Dua Langkah') }}</flux:heading>
                <flux:text class="text-zinc-500 dark:text-zinc-400 text-sm">
                    {{ __('Masukkan 6 digit kode keamanan dari aplikasi autentikator (Google Authenticator / Authy) di ponsel Anda.') }}
                </flux:text>
            </div>

            <div x-show="showRecoveryInput" class="text-center space-y-2">
                <flux:heading size="xl">{{ __('Kode Pemulihan Darurat') }}</flux:heading>
                <flux:text class="text-zinc-500 dark:text-zinc-400 text-sm">
                    {{ __('Konfirmasi akses masuk ke akun Anda menggunakan salah satu kode pemulihan darurat.') }}
                </flux:text>
            </div>

            <!-- Session Status -->
            <x-auth-session-status class="text-center mt-4" :status="session('status')" />

            <form 
                x-ref="challengeForm"
                method="POST" 
                action="{{ route('two-factor.login.store') }}" 
                class="mt-6 space-y-5"
                @submit="isSubmitting = true"
                @input="
                    $nextTick(() => {
                        let clean = String(code || '').replace(/\D/g, '');
                        if (clean.length === 6 && !isSubmitting) {
                            isSubmitting = true;
                            $refs.challengeForm?.submit();
                        }
                    });
                "
            >
                @csrf

                <div x-show="!showRecoveryInput">
                    <div class="flex items-center justify-center my-4" x-ref="otp">
                        <flux:otp
                            x-model="code"
                            length="6"
                            name="code"
                            label="Kode OTP"
                            label:sr-only
                            class="mx-auto"
                        />
                    </div>
                    @error('code')
                        <p class="text-center text-xs text-red-600 dark:text-red-400 mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <div x-show="showRecoveryInput">
                    <div class="my-4">
                        <flux:input
                            type="text"
                            name="recovery_code"
                            x-ref="recovery_code"
                            x-bind:required="showRecoveryInput"
                            autocomplete="one-time-code"
                            placeholder="cth. a1b2-c3d4-e5f6"
                            label="Kode Pemulihan"
                            x-model="recovery_code"
                        />
                    </div>

                    @error('recovery_code')
                        <p class="text-center text-xs text-red-600 dark:text-red-400 mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <button 
                    type="submit"
                    x-bind:disabled="isSubmitting"
                    class="w-full px-4 py-2.5 text-sm font-semibold text-white bg-[#3B71CA] hover:bg-[#2d5db3] disabled:bg-[#3B71CA]/60 disabled:cursor-not-allowed rounded-lg transition-all focus:outline-none focus:ring-2 focus:ring-[#3B71CA] focus:ring-offset-2"
                >
                    <span x-show="!isSubmitting" class="inline-flex items-center justify-center gap-2">
                        <span>{{ __('Verifikasi & Masuk') }}</span>
                    </span>
                    <span x-show="isSubmitting" x-cloak class="inline-flex items-center justify-center gap-2">
                        <span style="animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;">{{ __('Memverifikasi...') }}</span>
                        <svg class="animate-spin size-4 shrink-0 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </span>
                </button>

                <div class="mt-4 text-sm text-center">
                    <button 
                        type="button" 
                        class="text-[#3B71CA] hover:underline cursor-pointer font-medium"
                        @click="toggleInput()"
                    >
                        <span x-show="!showRecoveryInput">{{ __('Gunakan kode pemulihan darurat') }}</span>
                        <span x-show="showRecoveryInput">{{ __('Gunakan kode autentikator') }}</span>
                    </button>
                </div>
            </form>

            <div class="text-center text-sm text-zinc-500 dark:text-zinc-400 pt-2">
                <flux:link :href="route('login')" class="font-medium text-zinc-600 dark:text-zinc-400 hover:underline" wire:navigate>
                    &larr; {{ __('Kembali ke halaman masuk') }}
                </flux:link>
            </div>
        </div>
    </div>
</x-layouts::auth.split>
