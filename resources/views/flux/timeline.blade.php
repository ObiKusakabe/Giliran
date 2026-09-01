@props([
    'horizontal' => false,
    'align' => 'start',
    'size' => 'md',
])

<flux:timeline.index :horizontal="$horizontal" :align="$align" :size="$size" {{ $attributes }}>
    {{ $slot }}
</flux:timeline.index>
