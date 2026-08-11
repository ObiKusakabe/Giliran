@props([
    'name'        => 'confirm',
    'title'       => 'Konfirmasi',
    'description' => 'Apakah kamu yakin ingin melanjutkan aksi ini?',
    'confirmText' => 'Ya, Lanjutkan',
    'cancelText'  => 'Batal',
    'action'      => null,   // wire:click action string, contoh: "hapus({{ $id }})"
    'variant'     => 'danger',
])

<flux:modal :name="$name" class="max-w-sm">
    <div class="flex flex-col gap-4 p-2">
        <div>
            <flux:heading size="lg">{{ $title }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ $description }}</flux:text>
        </div>

        {{ $slot }}

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ $cancelText }}</flux:button>
            </flux:modal.close>

            @if ($action)
                <flux:modal.close>
                    <flux:button
                        :variant="$variant"
                        wire:click="{{ $action }}"
                    >
                        {{ $confirmText }}
                    </flux:button>
                </flux:modal.close>
            @else
                {{ $actions ?? '' }}
            @endif
        </div>
    </div>
</flux:modal>
