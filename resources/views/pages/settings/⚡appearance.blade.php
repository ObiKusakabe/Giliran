<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pengaturan Tampilan')] #[Layout('layouts.admin', ['breadcrumbs' => [['label' => 'Pengaturan'], ['label' => 'Tampilan']]])] class extends Component {
    //
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

    {{-- Appearance Content --}}
    <div class="max-w-3xl space-y-6">
        <div>
            <flux:heading size="lg">Tampilan</flux:heading>
            <flux:subheading>Atur tampilan aplikasi sesuai preferensi kamu</flux:subheading>
        </div>

        <div
            x-data="{
                inovindoActive: localStorage.getItem('theme-inovindo') === 'true',

                toggleInovindo() {
                    this.inovindoActive = !this.inovindoActive;
                    localStorage.setItem('theme-inovindo', this.inovindoActive);

                    if (this.inovindoActive) {
                        // Inovindo = light mode dengan aksen navy, bukan dark mode
                        document.documentElement.classList.add('inovindo');
                        document.documentElement.classList.remove('dark');
                        localStorage.setItem('flux-appearance', 'light');
                        if (window.$flux) window.$flux.appearance = 'light';
                    } else {
                        document.documentElement.classList.remove('inovindo');
                    }
                },

                // Dipanggil saat user klik Terang/Gelap di radio Flux
                onFluxChange(val) {
                    if (val === 'light' || val === 'dark') {
                        // Matikan Inovindo otomatis
                        this.inovindoActive = false;
                        localStorage.setItem('theme-inovindo', 'false');
                        document.documentElement.classList.remove('inovindo');
                    }
                }
            }"
            class="flex flex-col gap-4"
        >
            {{-- Radio Terang / Gelap — watch perubahan untuk matikan Inovindo --}}
            <flux:radio.group
                x-data
                variant="segmented"
                x-model="$flux.appearance"
                @change="onFluxChange($event.target.value)"
            >
                <flux:radio value="light" icon="sun">Terang</flux:radio>
                <flux:radio value="dark" icon="moon">Gelap</flux:radio>
            </flux:radio.group>

            {{-- Tema Inovindo --}}
            <div class="flex items-center justify-between rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 px-4 py-3">
                <div class="flex items-center gap-3">
                    <div class="flex gap-1">
                        <span class="h-4 w-4 rounded-full" style="background:#12314F"></span>
                        <span class="h-4 w-4 rounded-full" style="background:#3B71CA"></span>
                        <span class="h-4 w-4 rounded-full" style="background:#2FA84F"></span>
                        <span class="h-4 w-4 rounded-full" style="background:#F2A340"></span>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Tema Inovindo</p>
                        <p class="text-xs text-zinc-500">Palet warna PT Inovindo Digital Media</p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="toggleInovindo()"
                    :class="inovindoActive ? 'bg-[#3B71CA]' : 'bg-zinc-200 dark:bg-zinc-700'"
                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#3B71CA] focus:ring-offset-2"
                    role="switch"
                    :aria-checked="inovindoActive"
                >
                    <span
                        :class="inovindoActive ? 'translate-x-5' : 'translate-x-0'"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                </button>
            </div>
        </div>
    </div>
</div>
