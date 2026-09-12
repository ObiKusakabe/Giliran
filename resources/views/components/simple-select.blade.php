@props([
    'name',
    'label'       => null,
    'placeholder' => 'Pilih…',
    'options'     => [],   // array [['value' => ..., 'label' => ...]]
    'modelValue'  => '',   // nilai awal
    'required'    => false,
])

@php
    $selectedLabel = collect($options)->firstWhere('value', (string) $modelValue)['label'] ?? '';
    $wireModelName = $attributes->wire('model')->value();
@endphp

<div
    x-data="{
        open: false,
        selected: @js((string) $modelValue),
        selectedLabel: @js($selectedLabel),
        options: @js($options),

        pick(option) {
            this.selected = String(option.value);
            this.selectedLabel = option.label;
            this.open = false;
            
            // Direct Livewire update if wire:model is present
            if ('{{ $wireModelName }}' && typeof $wire !== 'undefined') {
                $wire.set('{{ $wireModelName }}', option.value);
            }

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
    @click.outside="open = false"
    {{ $attributes->except(['wire:model', 'wire:model.live']) }}
    class="relative w-full"
>
    @if($label)
        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
            {{ $label }}
            @if($required) <span class="text-red-500">*</span> @endif
        </label>
    @endif

    {{-- Hidden input for Livewire --}}
    <input
        type="hidden"
        name="{{ $name }}"
        data-hidden
        x-bind:value="selected"
        {{ $attributes->whereStartsWith('wire:') }}
    />

    {{-- Trigger Button --}}
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

    {{-- Dropdown Menu --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-end="opacity-0"
        class="absolute z-50 mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg py-1"
    >
        <template x-for="option in options" :key="option.value">
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
        </template>
    </div>
</div>
