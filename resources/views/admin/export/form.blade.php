<x-layouts::admin :title="'Export Jadwal'">
    <div class="flex flex-col gap-6 max-w-lg">
        <div>
            <flux:heading size="xl">Export Jadwal</flux:heading>
            <flux:text class="text-zinc-500">Download jadwal ke PDF untuk dicetak atau dibagikan.</flux:text>
        </div>

        <flux:card>
            <form
                method="POST"
                action="{{ route('admin.export.pdf') }}"
                class="flex flex-col gap-4"
                x-data="{ from: '', to: '' }"
            >
                @csrf

                <x-date-range-picker
                    name-from="tanggal_mulai"
                    name-to="tanggal_selesai"
                    label-from="Rentang Tanggal"
                    label-to=""
                    :value-from="old('tanggal_mulai', '')"
                    :value-to="old('tanggal_selesai', '')"
                    :required="true"
                />

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
