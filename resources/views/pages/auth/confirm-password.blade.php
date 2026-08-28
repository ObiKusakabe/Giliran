<x-layouts::auth :title="__('Konfirmasi Password')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Konfirmasi Password')"
            :description="__('Ini adalah area aman aplikasi. Silakan konfirmasi password Anda sebelum melanjutkan.')"
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify
            options-route="passkey.confirm-options"
            submit-route="passkey.confirm"
            :label="__('Konfirmasi dengan passkey')"
            :loading-label="__('Mengkonfirmasi...')"
            :separator="__('Atau konfirmasi dengan password')"
        />

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-6" x-data="{ loading: false }" @submit="loading = true">
            @csrf

            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('Password')"
                viewable
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="confirm-password-button" x-bind:disabled="loading">
                <span x-show="!loading">{{ __('Konfirmasi') }}</span>
                <span x-show="loading" x-cloak class="flex items-center justify-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    {{ __('Memproses...') }}
                </span>
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
