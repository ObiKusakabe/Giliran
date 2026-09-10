@props(['title' => null, 'breadcrumbs' => []])

<x-layouts::app.sidebar :title="$title" :breadcrumbs="$breadcrumbs">
    {{ $slot }}
</x-layouts::app.sidebar>
