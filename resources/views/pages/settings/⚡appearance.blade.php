<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tampilan')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout heading="Tampilan" subheading="Atur tampilan aplikasi sesuai preferensi kamu">

        {{-- Flux default: light / dark --}}
        <div class="flex flex-col gap-4">
            <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
                <flux:radio value="light" icon="sun">Terang</flux:radio>
                <flux:radio value="dark" icon="moon">Gelap</flux:radio>
            </flux:radio.group>

            {{-- Tema Inovindo — toggle terpisah --}}
            <div
                x-data="{
                    active: localStorage.getItem('theme-inovindo') === 'true',
                    toggle() {
                        this.active = !this.active;
                        localStorage.setItem('theme-inovindo', this.active);
                        if (this.active) {
                            document.documentElement.classList.add('inovindo', 'dark');
                            document.documentElement.classList.remove('light');
                            // Sync Flux ke dark supaya base component gelap
                            localStorage.setItem('flux-appearance', 'dark');
                        } else {
                            document.documentElement.classList.remove('inovindo');
                        }
                    },
                    init() {
                        if (this.active) {
                            document.documentElement.classList.add('inovindo', 'dark');
                        }
                    }
                }"
                x-init="init()"
                class="flex items-center justify-between rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 px-4 py-3"
            >
                <div class="flex items-center gap-3">
                    {{-- Preview swatch --}}
                    <div class="flex gap-1">
                        <span class="h-4 w-4 rounded-full" style="background:#12314F"></span>
                        <span class="h-4 w-4 rounded-full" style="background:#1591D8"></span>
                        <span class="h-4 w-4 rounded-full" style="background:#2FA84F"></span>
                        <span class="h-4 w-4 rounded-full" style="background:#F2A340"></span>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Tema Inovindo</p>
                        <p class="text-xs text-zinc-500">Palet warna PT Inovindo Digital Media</p>
                    </div>
                </div>
                {{-- Toggle switch --}}
                <button
                    type="button"
                    @click="toggle()"
                    :class="active ? 'bg-[#1591D8]' : 'bg-zinc-200 dark:bg-zinc-700'"
                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#1591D8] focus:ring-offset-2"
                    role="switch"
                    :aria-checked="active"
                >
                    <span
                        :class="active ? 'translate-x-5' : 'translate-x-0'"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                </button>
            </div>
        </div>

    </x-pages::settings.layout>
</section>
