@php
use Livewire\Volt\Component;

new class extends Component {
    public function mount() 
    {
        // Simulate heavier query - dengan joins/relations
        usleep(500000); // 500ms delay for demo (hapus di production)
    }

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeletons.table :columns="4" :rows="5" />
        HTML;
    }

    public function with(): array
    {
        // Query yang lebih berat - ambil recent activity
        $recentAdzan = \App\Models\JadwalAdzanKitab::with('personil.tim')
            ->whereBetween('tanggal', [now()->startOfMonth(), now()->endOfMonth()])
            ->orderBy('tanggal', 'desc')
            ->limit(5)
            ->get();

        return [
            'recentActivity' => $recentAdzan,
        ];
    }
}
@endphp

<div>
    <flux:heading size="lg" class="mb-4">Aktivitas Terbaru</flux:heading>
    
    @if($recentActivity->isEmpty())
        <flux:card>
            <flux:text class="text-zinc-500">Belum ada aktivitas bulan ini.</flux:text>
        </flux:card>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Tanggal</flux:table.column>
                <flux:table.column>Personil</flux:table.column>
                <flux:table.column>Tim</flux:table.column>
                <flux:table.column>Tugas</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach($recentActivity as $activity)
                    <flux:table.row>
                        <flux:table.cell>{{ $activity->tanggal->format('d M Y') }}</flux:table.cell>
                        <flux:table.cell>{{ $activity->personil->nama }}</flux:table.cell>
                        <flux:table.cell>{{ $activity->personil->tim->nama_tim }}</flux:table.cell>
                        <flux:table.cell>{{ ucfirst($activity->waktu_sholat) }} - {{ ucfirst($activity->jenis_tugas) }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
