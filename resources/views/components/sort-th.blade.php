@props(['field', 'sortField', 'sortDir', 'label', 'align' => 'left'])

@php
    $isActive = $sortField === $field;
    $nextDir  = ($isActive && $sortDir === 'asc') ? 'desc' : 'asc';
@endphp

<th
    wire:click="sort('{{ $field }}')"
    class="px-4 py-3 font-medium text-zinc-600 dark:text-zinc-400 cursor-pointer select-none
           hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors
           {{ $align === 'center' ? 'text-center' : ($align === 'right' ? 'text-right' : 'text-left') }}"
>
    <span class="inline-flex items-center gap-1
                 {{ $align === 'center' ? 'justify-center' : ($align === 'right' ? 'justify-end' : '') }}">
        {{ $label }}
        @if ($isActive)
            @if ($sortDir === 'asc')
                <svg class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
                </svg>
            @else
                <svg class="h-3.5 w-3.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            @endif
        @else
            <svg class="h-3.5 w-3.5 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/>
            </svg>
        @endif
    </span>
</th>
