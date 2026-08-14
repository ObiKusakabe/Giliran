{{--
  Styled native-like select untuk filter dengan sedikit opsi (tidak perlu search).
  Usage: <x-styled-select x-model="filterStatus">
             <option value="">Semua Status</option>
             <option value="aktif">Aktif</option>
         </x-styled-select>
--}}
@props(['label' => null])

<div class="relative" x-data="{ open: false }" @click.outside="open = false">
    @if($label)
        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ $label }}</label>
    @endif

    {{-- Hidden native select untuk value binding --}}
    <select
        {{ $attributes->whereStartsWith(['x-model', 'wire:', 'name', '@']) }}
        class="sr-only"
    >
        {{ $slot }}
    </select>

    {{-- Custom trigger --}}
    <button
        type="button"
        @click="open = !open"
        :class="open ? 'ring-2 ring-brand border-brand' : 'border-zinc-300 dark:border-zinc-600 hover:border-zinc-400'"
        class="w-full flex items-center justify-between gap-2 rounded-lg border bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-left transition-colors focus:outline-none"
        x-text="$el.previousElementSibling.selectedOptions[0]?.text || 'Pilih…'"
    ></button>

    {{-- Dropdown --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-end="opacity-0"
        class="absolute z-50 mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg py-1"
    >
        <template x-for="opt in Array.from($el.parentElement.querySelector('select').options)" :key="opt.value">
            <button
                type="button"
                @click="
                    $el.closest('[x-data]').querySelector('select').value = opt.value;
                    $el.closest('[x-data]').querySelector('select').dispatchEvent(new Event('change', {bubbles:true}));
                    $el.closest('[x-data]').querySelector('select').dispatchEvent(new Event('input', {bubbles:true}));
                    open = false;
                "
                :class="$el.closest('[x-data]').querySelector('select').value === opt.value
                    ? 'bg-brand/10 text-brand font-medium'
                    : 'text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700'"
                class="w-full text-left px-3 py-2 text-sm flex items-center justify-between"
                x-text="opt.text"
            ></button>
        </template>
    </div>
</div>
