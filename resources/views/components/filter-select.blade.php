{{--
  Styled filter dropdown — Alpine only, no server roundtrip.
  Untuk filter dengan sedikit opsi (tidak butuh search).

  Usage:
    <x-filter-select
        x-model="filterStatus"
        :options="[
            ['value' => '', 'label' => 'Semua Status'],
            ['value' => 'aktif', 'label' => 'Aktif'],
            ['value' => 'nonaktif', 'label' => 'Nonaktif'],
        ]"
        class="w-40"
    />
--}}
@props([
    'options' => [],
    'placeholder' => 'Semua',
])

<div
    x-data="{
        open: false,
        options: @js($options),
        get selectedLabel() {
            const found = this.options.find(o => o.value === (this.\$el.closest('[x-data]')?.__x?.\$data?.filterStatus ?? this.\$el.closest('[x-data]')?.__x?.\$data?.filterVal ?? ''));
            return found ? found.label : '{{ $placeholder }}';
        }
    }"
    @click.outside="open = false"
    {{ $attributes->only('class') }}
    class="{{ $attributes->get('class', '') }} relative"
>
    <button
        type="button"
        @click="open = !open"
        :class="open ? 'ring-2 ring-brand border-brand' : 'border-zinc-300 dark:border-zinc-600 hover:border-zinc-400 dark:hover:border-zinc-500'"
        class="w-full flex items-center justify-between gap-2 rounded-lg border bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-left transition-colors focus:outline-none"
    >
        <span x-text="selectedLabel" class="text-zinc-900 dark:text-zinc-100"></span>
        <svg class="h-4 w-4 text-zinc-400 flex-shrink-0 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-end="opacity-0"
        class="absolute z-50 mt-1 w-full min-w-[10rem] rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg py-1"
    >
        <template x-for="opt in options" :key="opt.value">
            <button
                type="button"
                {{ $attributes->whereStartsWith('@click') }}
                @click.stop="
                    open = false;
                "
                :class="opt.value === ({{ $attributes->get('x-model', "''") }})
                    ? 'bg-brand/10 text-brand font-medium'
                    : 'text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700'"
                class="w-full text-left px-3 py-2 text-sm flex items-center justify-between gap-2"
            >
                <span x-text="opt.label"></span>
                <svg x-show="opt.value === ({{ $attributes->get('x-model', "''") }})"
                     class="h-4 w-4 text-brand flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
            </button>
        </template>
    </div>
</div>
