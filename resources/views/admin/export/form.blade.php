<x-layouts::admin :title="'Export Jadwal'">
    <div class="flex flex-col gap-6 max-w-lg">
        <div>
            <flux:heading size="xl">Export Jadwal</flux:heading>
            <flux:text class="text-zinc-500">Download jadwal ke PDF untuk dicetak atau dibagikan.</flux:text>
        </div>

        <flux:card>
            <form method="POST" action="{{ route('admin.export.pdf') }}" class="flex flex-col gap-4">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Tanggal Mulai</label>
                    <input
                        type="date"
                        name="tanggal_mulai"
                        value="{{ old('tanggal_mulai') }}"
                        required
                        class="w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand"
                    />
                    @error('tanggal_mulai') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Tanggal Selesai</label>
                    <input
                        type="date"
                        name="tanggal_selesai"
                        value="{{ old('tanggal_selesai') }}"
                        required
                        class="w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand"
                    />
                    @error('tanggal_selesai') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <flux:select name="jenis" label="Jenis Jadwal">
                    <flux:select.option value="semua">Semua (Adzan + Briefing + Ruangan)</flux:select.option>
                    <flux:select.option value="adzan">Adzan & Kajian saja</flux:select.option>
                    <flux:select.option value="briefing">Briefing saja</flux:select.option>
                    <flux:select.option value="ruangan">Alokasi Ruangan saja</flux:select.option>
                </flux:select>

                <flux:button type="submit" variant="primary" icon="arrow-down-tray" class="w-full">
                    Download PDF
                </flux:button>
            </form>
        </flux:card>
    </div>
</x-layouts::admin>
