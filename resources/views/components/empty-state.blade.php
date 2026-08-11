@props([
    'icon'        => 'inbox',
    'title'       => 'Belum ada data',
    'description' => null,
    'actionLabel' => null,
    'actionWire'  => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center py-12 text-center']) }}>
    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800 mb-4">
        <flux:icon :icon="$icon" class="h-6 w-6 text-zinc-400" />
    </div>

    <flux:heading size="sm" class="text-zinc-600 dark:text-zinc-400">{{ $title }}</flux:heading>

    @if ($description)
        <flux:text class="mt-1 text-sm text-zinc-400">{{ $description }}</flux:text>
    @endif

    @if ($actionLabel && $actionWire)
        <div class="mt-4">
            <flux:button variant="primary" size="sm" wire:click="{{ $actionWire }}">
                {{ $actionLabel }}
            </flux:button>
        </div>
    @endif

    {{ $slot }}
</div>
