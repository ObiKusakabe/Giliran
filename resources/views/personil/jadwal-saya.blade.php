<x-layouts::auth :title="'Jadwal Saya'">
    <flux:card class="w-full max-w-3xl mx-auto">
        <flux:heading size="xl">Jadwal Saya</flux:heading>
        <flux:text class="text-zinc-500">Selamat datang, {{ auth()->user()->name }}. Modul jadwal akan diimplementasi di batch selanjutnya.</flux:text>
        <form method="POST" action="{{ route('logout') }}" class="mt-4">
            @csrf
            <flux:button type="submit" variant="ghost" size="sm">Keluar</flux:button>
        </form>
    </flux:card>
</x-layouts::auth>
