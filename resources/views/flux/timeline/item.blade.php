@props([
    'status' => 'incomplete',
    'align' => null,
    'size' => 'md',
])

@php
$connectorColor = match ($status) {
    'complete' => 'bg-[#3B71CA] dark:bg-[#3B71CA]',
    'current'  => 'bg-[#3B71CA]/40 dark:bg-[#3B71CA]/30',
    default    => 'bg-zinc-200 dark:bg-zinc-800',
};

$linePos = match ($size) {
    'lg'    => 'left-[20px] top-[44px]',
    'sm'    => 'left-[12px] top-[26px]',
    default => 'left-[16px] top-[34px]',
};
@endphp

<div
    data-flux-timeline-item
    data-status="{{ $status }}"
    {{ $attributes->class([
        'group/item relative flex items-start gap-4 sm:gap-6',
        'pb-[var(--flux-timeline-item-gap,2rem)] last:pb-0',
    ]) }}
>
    {{-- Vertical connector line for non-last items --}}
    <div
        class="absolute {{ $linePos }} bottom-0 w-[2px] -translate-x-1/2 group-last/item:hidden {{ $connectorColor }} transition-all duration-300"
        aria-hidden="true"
    ></div>

    {{ $slot }}
</div>
