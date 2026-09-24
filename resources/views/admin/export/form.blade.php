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

                {{-- Pemilihan Periode WFO --}}
                <div x-data="{ useCustomDates: false }" class="space-y-3">
                    <flux:field>
                        <div class="flex items-center justify-between mb-1">
                            <flux:label class="font-medium text-sm">Pilih Periode WFO</flux:label>
                            <button 
                                type="button" 
                                @click="useCustomDates = !useCustomDates" 
                                class="text-xs text-blue-600 dark:text-blue-400 hover:underline"
                            >
                                <span x-text="useCustomDates ? 'Kembali ke Pilih Periode' : 'Kustom Rentang Tanggal'"></span>
                            </button>
                        </div>

                        <div x-show="!useCustomDates" x-data="{
                            open: false,
                            selectedId: '{{ $periodeAktif?->id ?? ($daftarPeriode->first()?->id ?? '') }}',
                            selectedLabel: '{{ $periodeAktif ? ($periodeAktif->nama . ' (' . $periodeAktif->tanggal_mulai?->format('d M Y') . ' - ' . $periodeAktif->tanggal_selesai?->format('d M Y') . ')' . ($periodeAktif->status === 'aktif' ? ' • [Aktif]' : '')) : ($daftarPeriode->first() ? ($daftarPeriode->first()->nama . ' (' . $daftarPeriode->first()->tanggal_mulai?->format('d M Y') . ' - ' . $daftarPeriode->first()->tanggal_selesai?->format('d M Y') . ')') : 'Pilih Periode') }}'
                        }" @click.outside="open = false" class="relative">
                            <input type="hidden" name="periode_wfo_id" :value="selectedId">
                            
                            <button type="button" @click="open = !open"
                                :class="open ? 'ring-2 ring-blue-500 border-blue-500' : 'border-zinc-300 dark:border-zinc-600 hover:border-zinc-400'"
                                class="w-full flex items-center justify-between gap-2 rounded-lg border bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-left transition-colors">
                                <span class="truncate text-zinc-900 dark:text-zinc-100 font-medium" x-text="selectedLabel"></span>
                                <svg class="h-4 w-4 text-zinc-400 shrink-0 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>

                            <div x-show="open" x-transition class="absolute z-50 mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-xl py-1 max-h-60 overflow-y-auto">
                                @foreach ($daftarPeriode as $p)
                                    @php
                                        $pLabel = $p->nama . ' (' . $p->tanggal_mulai?->format('d M Y') . ' - ' . $p->tanggal_selesai?->format('d M Y') . ')' . ($p->status === 'aktif' ? ' • [Aktif]' : '');
                                    @endphp
                                    <button type="button" 
                                        @click="selectedId = '{{ $p->id }}'; selectedLabel = '{{ addslashes($pLabel) }}'; open = false"
                                        :class="selectedId == '{{ $p->id }}' ? 'bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 font-medium' : 'text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700/60'"
                                        class="w-full text-left px-3 py-2 text-sm flex items-center justify-between gap-2 transition-colors">
                                        <span class="truncate">{{ $pLabel }}</span>
                                        <svg x-show="selectedId == '{{ $p->id }}'" class="h-4 w-4 text-blue-600 dark:text-blue-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </button>
                                @endforeach
                            </div>
                            <flux:description class="text-xs mt-1.5 text-zinc-500">
                                Sistem akan mengekspor seluruh jadwal pada rentang tanggal periode yang dipilih.
                            </flux:description>
                        </div>
                    </flux:field>

                    <input type="hidden" name="custom_tanggal" :value="useCustomDates ? '1' : '0'">

                    {{-- Rentang Tanggal Kustom (opsional) --}}
                    <div x-show="useCustomDates" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-800">
                        <flux:field>
                            <flux:label>Tanggal Mulai</flux:label>
                            <flux:input 
                                type="date" 
                                name="tanggal_mulai" 
                                value="{{ $periodeAktif?->tanggal_mulai?->format('Y-m-d') ?? now()->startOfMonth()->format('Y-m-d') }}" 
                            />
                        </flux:field>
                        <flux:field>
                            <flux:label>Tanggal Selesai</flux:label>
                            <flux:input 
                                type="date" 
                                name="tanggal_selesai" 
                                value="{{ $periodeAktif?->tanggal_selesai?->format('Y-m-d') ?? now()->endOfMonth()->format('Y-m-d') }}" 
                            />
                        </flux:field>
                    </div>
                </div>

                {{-- Komponen Konten dengan Alpine.js untuk kontrol instan --}}
                <div class="space-y-3" x-data="{
                    allSelected: true,
                    surat: true,
                    wfo: true,
                    kelompok: true,
                    adzan: true,
                    briefing: false,
                    ruangan: true,
                    toggleAll() {
                        this.allSelected = !this.allSelected;
                        this.surat = this.allSelected;
                        this.wfo = this.allSelected;
                        this.kelompok = this.allSelected;
                        this.adzan = this.allSelected;
                        this.briefing = false;
                        this.ruangan = this.allSelected;
                    },
                    selectOnly(type) {
                        this.surat = (type === 'wfo');
                        this.wfo = (type === 'wfo');
                        this.kelompok = (type === 'wfo');
                        this.adzan = (type === 'adzan');
                        this.briefing = false;
                        this.ruangan = (type === 'ruangan');
                        this.allSelected = false;
                    }
                }">
                    <div class="flex items-center justify-between">
                        <flux:label class="font-medium text-sm">Pilih Jadwal yang Di-include</flux:label>
                        <button type="button" @click="toggleAll()" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">
                            <span x-text="allSelected ? 'Batal Pilih Semua' : 'Pilih Semua'"></span>
                        </button>
                    </div>

                    {{-- Preset Cepat --}}
                    <div class="flex flex-wrap gap-1.5 pb-1">
                        <button type="button" @click="selectOnly('wfo')" class="px-2.5 py-1 text-xs rounded-md bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition">
                            Hanya WFO
                        </button>
                        <button type="button" @click="selectOnly('ruangan')" class="px-2.5 py-1 text-xs rounded-md bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition">
                            Hanya Ruangan
                        </button>
                        <button type="button" @click="selectOnly('adzan')" class="px-2.5 py-1 text-xs rounded-md bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition">
                            Hanya Adzan & Kitab
                        </button>
                    </div>

                    <div class="space-y-2 border border-zinc-200 dark:border-zinc-800 rounded-lg p-3 bg-zinc-50/50 dark:bg-zinc-900/50">
                        <label class="flex items-start gap-3 p-2 rounded hover:bg-white dark:hover:bg-zinc-800/80 cursor-pointer transition">
                            <input type="checkbox" name="include_surat" value="1" x-model="surat" class="mt-0.5 rounded border-zinc-300 dark:border-zinc-600 text-blue-600 shadow-sm focus:ring-blue-500">
                            <div class="text-sm">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">Surat Resmi Pemberitahuan WFO</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">Surat pengantar resmi Inovindo dengan tanda tangan direktur</div>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-2 rounded hover:bg-white dark:hover:bg-zinc-800/80 cursor-pointer transition">
                            <input type="checkbox" name="include_wfo" value="1" x-model="wfo" class="mt-0.5 rounded border-zinc-300 dark:border-zinc-600 text-blue-600 shadow-sm focus:ring-blue-500">
                            <div class="text-sm">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">Jadwal WFO Mingguan (Lampiran 1)</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">Matriks pembagian hari WFO tim Senin s.d. Sabtu</div>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-2 rounded hover:bg-white dark:hover:bg-zinc-800/80 cursor-pointer transition">
                            <input type="checkbox" name="include_kelompok" value="1" x-model="kelompok" class="mt-0.5 rounded border-zinc-300 dark:border-zinc-600 text-blue-600 shadow-sm focus:ring-blue-500">
                            <div class="text-sm">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">Daftar Kelompok Peserta PKL (Lampiran 2)</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">Daftar tim asal sekolah dan seluruh anggota personil</div>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-2 rounded hover:bg-white dark:hover:bg-zinc-800/80 cursor-pointer transition">
                            <input type="checkbox" name="include_ruangan" value="1" x-model="ruangan" class="mt-0.5 rounded border-zinc-300 dark:border-zinc-600 text-blue-600 shadow-sm focus:ring-blue-500">
                            <div class="text-sm">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">Jadwal Alokasi Ruangan Mingguan</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">Matriks pembagian ruangan tim WFO per hari & kapasitas</div>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-2 rounded hover:bg-white dark:hover:bg-zinc-800/80 cursor-pointer transition">
                            <input type="checkbox" name="include_adzan" value="1" x-model="adzan" class="mt-0.5 rounded border-zinc-300 dark:border-zinc-600 text-blue-600 shadow-sm focus:ring-blue-500">
                            <div class="text-sm">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">Jadwal Petugas Adzan & Pembacaan Kitab</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">Petugas sholat Zuhur dan Ashar per tanggal</div>
                            </div>
                        </label>

                        <div class="flex items-start gap-3 p-2 rounded border border-dashed border-zinc-200 dark:border-zinc-800 bg-zinc-100/60 dark:bg-zinc-800/40 opacity-60 cursor-not-allowed">
                            <input type="checkbox" name="include_briefing" value="1" disabled class="mt-0.5 rounded border-zinc-300 dark:border-zinc-600 text-zinc-400 cursor-not-allowed">
                            <div class="text-sm">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-zinc-500 dark:text-zinc-400">Jadwal Petugas Briefing</span>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-400 border border-amber-300 dark:border-amber-800/60">
                                        In Development
                                    </span>
                                </div>
                                <div class="text-xs text-zinc-400 dark:text-zinc-500">Jadwal penugasan notulis & pemateri sesi Pagi dan Sore (Segera Hadir)</div>
                            </div>
                        </div>
                    </div>
                </div>

                <flux:button type="submit" variant="primary" icon="arrow-down-tray" class="w-full">
                    Download PDF
                </flux:button>
            </form>
        </flux:card>
    </div>
</x-layouts::admin>
