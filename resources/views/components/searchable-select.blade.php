@props([
    'name' => '',
    'label'       => null,
    'placeholder' => 'Pilih...',
    'options'     => [],   // array [['value' => ..., 'label' => ...]]
    'modelValue'  => '',   // nilai awal (dari wire:model / PHP)
    'required'    => false,
])

@php
    $selectedLabel = collect($options)->firstWhere('value', (string) $modelValue)['label'] ?? '';
    $wireModelName = $attributes->wire('model')->value();
@endphp

<div
    x-data="{
        open: false,
        q: '',
        selected: @js((string) $modelValue),
        selectedLabel: @js($selectedLabel),
        options: @js($options),

        init() {
            this.$watch('selected', val => {
                const opt = this.options.find(o => String(o.value) === String(val));
                this.selectedLabel = opt ? opt.label : '';
            });
        },

        get filtered() {
            if (!this.q) return this.options;
            return this.options.filter(o =>
                o.label.toLowerCase().includes(this.q.toLowerCase())
            );
        },

        pick(option) {
            this.selected = String(option.value);
            this.selectedLabel = option.label;
            this.open = false;
            this.q = '';

            @if($wireModelName)
                if (typeof $wire !== 'undefined') {
                    $wire.set('{{ $wireModelName }}', option.value);
                }
            @endif

            // Trigger native change event
            this.$nextTick(() => {
                const input = this.$el.querySelector('input[data-hidden]');
                if (input) {
                    input.value = option.value;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        }
    }"
    @click.outside="open = false; q = ''"
    class="relative w-full"
>
    @if($label)
        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
            {{ $label }}
            @if($required) <span class="text-red-500">*</span> @endif
        </label>
    @endif

    {{-- Hidden input — Livewire reads this via wire:model --}}
    <input
        type="hidden"
        name="{{ $name }}"
        data-hidden
        x-bind:value="selected"
        {{ $attributes->whereStartsWith('wire:') }}
    />

    {{-- Trigger --}}
    <button
        type="button"
        @click="open = !open"
        :class="open ? 'ring-2 ring-brand border-brand' : 'border-zinc-300 dark:border-zinc-600 hover:border-zinc-400 dark:hover:border-zinc-500'"
        class="w-full flex items-center justify-between gap-2 rounded-lg border bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-left transition-colors focus:outline-none"
    >
        <span
            :class="selectedLabel ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-400'"
            x-text="selectedLabel || '{{ $placeholder }}'"
        ></span>
        <svg class="h-4 w-4 text-zinc-400 flex-shrink-0 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    {{-- Dropdown --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-end="opacity-0"
        class="absolute z-50 mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg"
    >
        <div class="p-2 border-b border-zinc-100 dark:border-zinc-700">
            <input
                x-model="q"
                type="text"
                placeholder="Cari..."
                @click.stop
                class="w-full rounded-md border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 px-3 py-1.5 text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-1 focus:ring-brand"
            />
        </div>
        <ul class="max-h-48 overflow-y-auto py-1">
            <template x-for="option in filtered" :key="option.value">
                <li>
                    <button
                        type="button"
                        @click="pick(option)"
                        :class="selected == option.value
                            ? 'bg-brand/10 text-brand font-medium'
                            : 'text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700'"
                        class="w-full text-left px-3 py-2 text-sm flex items-center justify-between gap-2"
                    >
                        <span x-text="option.label" class="truncate"></span>
                        <svg x-show="selected == option.value" class="h-4 w-4 text-brand flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </button>
                </li>
            </template>
            <li x-show="filtered.length === 0" class="px-3 py-2 text-sm text-zinc-400 text-center">
                Tidak ada hasil.
            </li>
        </ul>
    </div>

    {{ $slot }}
</div>
