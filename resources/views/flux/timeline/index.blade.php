@props([
    'horizontal' => false,
    'align' => 'start',
    'size' => 'md',
])

@php
$alignClasses = match ($align) {
    'baseline' => 'items-baseline',
    'center'   => 'items-center',
    'end'      => 'items-end',
    default    => 'items-start',
};

$directionClasses = $horizontal
    ? 'flex flex-row items-start'
    : 'flex flex-col';
@endphp

<div
    data-flux-timeline
    {{ $attributes->class([
        'relative',
        $directionClasses,
        $alignClasses,
        '[--flux-timeline-item-gap:1.5rem]' => !$horizontal,
        '[--flux-timeline-item-gap:2rem]' => $horizontal,
        '[--flux-timeline-content-gap:0.875rem]',
    ]) }}
>
    {{ $slot }}
</div>
