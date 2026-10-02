<x-layouts::auth.split :title="__('Reset Password')">
    <div class="space-y-6">
        <div class="text-center space-y-2">
            <flux:heading size="xl">{{ __('Buat Password Baru') }}</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400 text-sm">
                {{ __('Masukkan kode OTP yang telah dikirim ke email Anda dan buat kata sandi baru.') }}
            </flux:text>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        @if (!empty($activeOtp))
            <div class="p-3 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-lg text-xs text-amber-800 dark:text-amber-300 flex items-center justify-between">
                <span>Mode Pengujian (Log Mailer): Kode OTP</span>
                <code class="font-bold font-mono text-sm px-2 py-0.5 bg-amber-200/60 dark:bg-amber-900/60 rounded text-amber-900 dark:text-amber-100">{{ $activeOtp }}</code>
            </div>
        @endif

        <div
            x-data="{
                email: '{{ old('email', $email ?? request('email', '')) }}',
                otp: '{{ old('otp', request('otp', '')) }}',
                pw: '',
                pwConfirm: '',
                isSubmitting: false,
                cooldown: 60,
                hasTokenLink: {{ (isset($token) && $token !== 'otp' && !empty($token)) ? 'true' : 'false' }},
                init() {
                    let timer = setInterval(() => {
                        if (this.cooldown > 0) {
                            this.cooldown--;
                        } else {
                            clearInterval(timer);
                        }
                    }, 1000);
                },
                get isMatch() {
                    return this.pw.length > 0 && this.pwConfirm.length > 0 && this.pw === this.pwConfirm;
                },
                get isMismatch() {
                    return this.pw.length > 0 && this.pwConfirm.length > 0 && this.pw !== this.pwConfirm;
                }
            }"
            class="space-y-5"
        >
            <form 
                method="POST" 
                action="{{ route('password.update') }}" 
                class="flex flex-col gap-4"
                @submit="isSubmitting = true"
            >
                @csrf

                <!-- Token -->
                <input type="hidden" name="token" value="{{ $token ?? request()->route('token', 'otp') }}">

                <!-- Email Address -->
                <flux:input
                    name="email"
                    x-model="email"
                    :label="__('Alamat Email')"
                    type="email"
                    required
                    autocomplete="email"
                    placeholder="nama@email.com"
                />

                <!-- Kode OTP -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="otp" class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            {{ __('Kode OTP 6 Digit') }}
                            <template x-if="hasTokenLink">
                                <span class="text-xs font-normal text-zinc-500">({{ __('Opsional jika via tautan email') }})</span>
                            </template>
                            <template x-if="!hasTokenLink">
                                <span class="text-red-500">*</span>
                            </template>
                        </label>
                    </div>

                    <div class="relative">
                        <flux:input
                            id="otp"
                            name="otp"
                            x-model="otp"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="6"
                            placeholder="123456"
                            class="text-center font-mono tracking-widest text-lg"
                            autocomplete="one-time-code"
                            x-bind:required="!hasTokenLink"
                        />
                        <button
                            type="button"
                            x-show="!otp"
                            x-cloak
                            @click="if (navigator.clipboard && navigator.clipboard.readText) { navigator.clipboard.readText().then(text => { otp = text.replace(/[^0-9]/g, '').slice(0, 6); }).catch(() => {}) }"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-xs bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 px-2 py-1 rounded border border-zinc-300 dark:border-zinc-700 flex items-center gap-1 cursor-pointer transition shadow-2xs"
                            title="Tempel kode OTP dari clipboard"
                        >
                            <flux:icon icon="clipboard-document-list" class="size-3.5 text-zinc-500" />
                            <span>Tempel</span>
                        </button>
                    </div>
                    @error('otp')
                        <p class="text-xs text-red-600 dark:text-red-400 mt-1 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Resend OTP Action -->
                <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400 -mt-1">
                    <span>
                        <span x-show="cooldown > 0">
                            {{ __('Kirim ulang kode dalam') }} <strong class="font-mono text-zinc-800 dark:text-zinc-200" x-text="cooldown + 's'"></strong>
                        </span>
                        <span x-show="cooldown <= 0">
                            {{ __('Belum menerima kode OTP?') }}
                        </span>
                    </span>
                    <button 
                        type="button"
                        x-show="cooldown <= 0" 
                        class="font-medium text-[#3B71CA] hover:underline focus:outline-none cursor-pointer"
                        @click="$refs.resendForm.submit()"
                    >
                        {{ __('Kirim Ulang Kode OTP') }}
                    </button>
                </div>

                <!-- Password Baru -->
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

            {{-- Hidden Form for Resend OTP --}}
            <form x-ref="resendForm" method="POST" action="{{ route('password.resend-otp') }}" class="hidden">
                @csrf
                <input type="hidden" name="email" :value="email">
            </form>
        </div>

        <div class="text-center text-sm text-zinc-500 dark:text-zinc-400">
            <span>{{ __('Batal mengubah?') }}</span>
            <flux:link :href="route('login')" class="font-medium text-[#3B71CA] hover:underline ml-1" wire:navigate>
                {{ __('Kembali ke halaman masuk') }}
            </flux:link>
        </div>
    </div>
</x-layouts::auth.split>
