<x-layouts::admin :title="'Dashboard'">
    <flux:card>
        <flux:heading size="xl">Dashboard</flux:heading>
        <flux:text class="text-zinc-500">Selamat datang, {{ auth()->user()->name }}. Modul dashboard akan diimplementasi di Batch 10.</flux:text>
    </flux:card>
</x-layouts::admin>
