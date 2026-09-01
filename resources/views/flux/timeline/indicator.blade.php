@props([
    'variant' => 'default',
    'color' => null,
    'status' => null,
    'size' => 'md',
])

@php
if ($variant === 'bare') {
    $classes = 'relative z-10 flex shrink-0 items-center justify-center';
} else {
    $colorClass = match ($color) {
        'green', 'emerald' => 'bg-emerald-600 text-white shadow-emerald-500/20 ring-4 ring-emerald-500/10',
        'blue'            => 'bg-blue-600 text-white shadow-blue-500/20 ring-4 ring-blue-500/10',
        'purple'          => 'bg-purple-600 text-white shadow-purple-500/20 ring-4 ring-purple-500/10',
        'amber', 'yellow' => 'bg-amber-500 text-white shadow-amber-500/20 ring-4 ring-amber-500/10',
        'red', 'rose'     => 'bg-red-600 text-white shadow-red-500/20 ring-4 ring-red-500/10',
        default           => match ($status) {
            'complete' => 'bg-emerald-600 text-white ring-4 ring-emerald-500/10',
            'current'  => 'bg-blue-600 text-white shadow-md ring-4 ring-blue-500/20',
            default    => 'bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 border border-zinc-300 dark:border-zinc-700',
        },
    };

    $sizeClass = match ($size) {
        'sm' => 'size-6 text-xs',
        'lg' => 'size-10 text-base',
        default => 'size-8 text-xs',
    };

    $classes = "relative z-10 flex {$sizeClass} shrink-0 items-center justify-center rounded-full font-semibold shadow-xs {$colorClass} transition-all";
}
@endphp

<div
    data-flux-timeline-indicator
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</div>
