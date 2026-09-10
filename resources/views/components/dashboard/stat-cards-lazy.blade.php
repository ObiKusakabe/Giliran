@php
use Livewire\Volt\Component;

new class extends Component {
    public function mount() 
    {
        // Simulate fast query - hanya count()
        usleep(100000); // 100ms delay for demo (hapus di production)
    }

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeletons.stat-cards />
        HTML;
    }

    public function with(): array
    {
        return [
            'totalPersonil' => \App\Models\Personil::count(),
            'totalTim' => \App\Models\Tim::count(),
            'totalRuangan' => \App\Models\Ruangan::count(),
            'periodeAktif' => \App\Models\PeriodeWfo::where('status', 'aktif')->exists(),
        ];
    }
}
@endphp

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    {{-- Card 1: Total Personil --}}
    <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5">
        <div class="relative z-10">
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Total Personil</flux:text>
            <flux:heading size="xl" class="mt-2 font-bold text-zinc-900 dark:text-zinc-100">
                {{ $totalPersonil }}
            </flux:heading>
        </div>
        <div class="absolute bottom-0 right-0 opacity-10">
            <flux:icon.user-group class="size-24 text-zinc-900 dark:text-zinc-100" />
        </div>
    </flux:card>

    {{-- Card 2: Total Tim --}}
    <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5">
        <div class="relative z-10">
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Total Tim</flux:text>
            <flux:heading size="xl" class="mt-2 font-bold text-emerald-600 dark:text-emerald-400">
                {{ $totalTim }}
            </flux:heading>
        </div>
        <div class="absolute bottom-0 right-0 opacity-10">
            <flux:icon.users class="size-24 text-emerald-600" />
        </div>
    </flux:card>

    {{-- Card 3: Total Ruangan --}}
    <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5">
        <div class="relative z-10">
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Ruang Tersedia</flux:text>
            <flux:heading size="xl" class="mt-2 font-bold text-blue-600 dark:text-blue-400">
                {{ $totalRuangan }}
            </flux:heading>
        </div>
        <div class="absolute bottom-0 right-0 opacity-10">
            <flux:icon.home-modern class="size-24 text-blue-600" />
        </div>
    </flux:card>

    {{-- Card 4: Periode Status --}}
    <flux:card variant="soft" class="relative overflow-hidden p-4 sm:p-5">
        <div class="relative z-10">
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Periode WFO</flux:text>
            <flux:heading size="xl" class="mt-2 font-bold {{ $periodeAktif ? 'text-green-600 dark:text-green-400' : 'text-zinc-400' }}">
                {{ $periodeAktif ? 'Aktif' : 'Tidak Aktif' }}
            </flux:heading>
        </div>
        <div class="absolute bottom-0 right-0 opacity-10">
            <flux:icon.calendar-days class="size-24 text-green-600" />
        </div>
    </flux:card>
</div>
