<x-layouts::auth :title="__('Masuk')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Sistem Manajemen Jadwal')"
            :description="__('Masuk dengan akun yang diberikan oleh admin')"
        />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
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
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link
                        class="absolute top-0 end-0 text-sm"
                        :href="route('password.request')"
                        wire:navigate
                    >
                        {{ __('Lupa password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Ingat saya')" :checked="old('remember')" />

            <flux:button variant="primary" type="submit" class="w-full">
                {{ __('Masuk') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
