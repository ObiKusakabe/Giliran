@blaze(fold: true)

@php
$classes = Flux::classes()
    ->add('z-[65]! fixed inset-0 bg-black/40 hidden')
    ->add('data-flux-sidebar-on-mobile:not-data-flux-sidebar-collapsed-mobile:block')
    ;
@endphp

<ui-sidebar-toggle {{ $attributes->class($classes) }} data-flux-sidebar-backdrop></ui-sidebar-toggle>
