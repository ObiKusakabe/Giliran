<?php

use App\Models\AlokasiRuangan;
use App\Models\JadwalWfo;
use App\Models\PeriodeWfo;
use App\Models\Ruangan;
use App\Models\Tim;
use App\Services\LraScheduler;
use Carbon\Carbon;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Alokasi Ruangan')] #[Layout('layouts.admin')] class extends Component {

    public string $tanggalMulaiMinggu = '';
    public ?int $periodeId = null;

    public function mount(): void
    {
        // Default: Senin minggu ini
        $this->tanggalMulaiMinggu = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();

        $aktif = PeriodeWfo::where('status', 'aktif')->first();
        $this->periodeId = $aktif?->id;
    }

    #[Computed]
    public function periodeAktif(): ?PeriodeWfo
    {
        return $this->periodeId ? PeriodeWfo::find($this->periodeId) : PeriodeWfo::where('status', 'aktif')->first();
    }

    #[Computed]
    public function daftarHariMingguIni(): array
    {
        $start = Carbon::parse($this->tanggalMulaiMinggu);
        $hariList = [];
        $namaHariIndo = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'];

        for ($i = 0; $i < 6; $i++) {
            $tgl = $start->copy()->addDays($i);
            $hariList[] = [
                'kode' => $namaHariIndo[$i],
                'nama' => ucfirst($namaHariIndo[$i]),
                'tanggal' => $tgl->toDateString(),
                'label_tanggal' => $tgl->translatedFormat('d M'),
                'is_today' => $tgl->isToday(),
            ];
        }

        return $hariList;
    }

    #[Computed]
    public function daftarRuangan()
    {
        return Ruangan::where('status', 'tersedia')->orderBy('nama_ruangan')->get();
    }

    #[Computed]
    public function daftarTim()
    {
        return Tim::where('status', 'active')->withCount('personil')->orderBy('nama_tim')->get();
    }

    /**
     * Data alokasi untuk minggu yang dipilih:
     * [{ id, tim_id, nama_tim, personil_count, ruangan_id, tanggal, hari }, ...]
     */
    #[Computed]
    public function alokasiRows(): array
    {
        $start = Carbon::parse($this->tanggalMulaiMinggu);
        $end = $start->copy()->addDays(5);

        return AlokasiRuangan::with(['tim.personil', 'ruangan'])
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'tim_id' => $a->tim_id,
                'nama_tim' => $a->tim?->nama_tim ?? '—',
                'personil_count' => $a->tim?->personil?->count() ?? 0,
                'ruangan_id' => $a->ruangan_id,
                'tanggal' => $a->tanggal->toDateString(),
            ])
            ->toArray();
    }

    /**
     * Peta tim WFO per tanggal
     */
    #[Computed]
    public function timWfoPerTanggal(): array
    {
        if (! $this->periodeAktif) {
            return [];
        }

        $wfo = JadwalWfo::with('tim')
            ->where('periode_wfo_id', $this->periodeAktif->id)
            ->whereHas('tim', fn ($q) => $q->where('status', 'active'))
            ->get();

        $map = [];
        foreach ($this->daftarHariMingguIni as $h) {
            $timHariIni = $wfo->where('hari', $h['kode'])->pluck('tim')->filter();
            $map[$h['tanggal']] = $timHariIni->map(fn ($t) => [
                'id' => $t->id,
                'nama_tim' => $t->nama_tim,
            ])->values()->toArray();
        }

        return $map;
    }

    public function prevWeek(): void
    {
        $this->tanggalMulaiMinggu = Carbon::parse($this->tanggalMulaiMinggu)->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->tanggalMulaiMinggu = Carbon::parse($this->tanggalMulaiMinggu)->addWeek()->toDateString();
    }

    public function todayWeek(): void
    {
        $this->tanggalMulaiMinggu = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    public function pindahRuangan(int $alokasiId, int $targetRuanganId, string $targetTanggal): void
    {
        $alokasi = AlokasiRuangan::find($alokasiId);
        if (! $alokasi) {
            Flux::toast(variant: 'danger', text: 'Data alokasi tidak ditemukan.');
            return;
        }

        // Cek apakah ruangan target sudah dipakai oleh tim lain di tanggal tersebut
        $bentrok = AlokasiRuangan::where('ruangan_id', $targetRuanganId)
            ->where('tanggal', $targetTanggal)
            ->where('id', '!=', $alokasiId)
            ->first();

        if ($bentrok) {
            // Swap ruangan jika target sudah terisi
            $oldRuanganId = $alokasi->ruangan_id;
            $oldTanggal = $alokasi->tanggal->toDateString();

            $bentrok->update([
                'ruangan_id' => $oldRuanganId,
                'tanggal' => $oldTanggal,
            ]);
        }

        $alokasi->update([
            'ruangan_id' => $targetRuanganId,
            'tanggal' => $targetTanggal,
        ]);

        $ruangan = Ruangan::find($targetRuanganId);
        Flux::toast(
            variant: 'success',
            text: "Tim {$alokasi->tim?->nama_tim} berhasil dialokasikan ke {$ruangan?->nama_ruangan} ({$targetTanggal})."
        );

        unset($this->alokasiRows);
    }

    public function tambahAlokasi(int $timId, int $ruanganId, string $tanggal): void
    {
        // Cek bentrok tim di tanggal sama
        $sudahAdaTim = AlokasiRuangan::where('tim_id', $timId)->where('tanggal', $tanggal)->first();
        if ($sudahAdaTim) {
            Flux::toast(variant: 'warning', text: 'Tim ini sudah memiliki ruangan pada tanggal tersebut.');
            return;
        }

        // Cek bentrok ruangan di tanggal sama
        $sudahAdaRuangan = AlokasiRuangan::where('ruangan_id', $ruanganId)->where('tanggal', $tanggal)->first();
        if ($sudahAdaRuangan) {
            Flux::toast(variant: 'danger', text: 'Ruangan ini sudah dialokasikan ke tim lain pada tanggal tersebut.');
            return;
        }

        $alokasi = AlokasiRuangan::create([
            'tim_id' => $timId,
            'ruangan_id' => $ruanganId,
            'tanggal' => $tanggal,
        ]);

        $tim = Tim::find($timId);
        $ruangan = Ruangan::find($ruanganId);

        Flux::toast(
            variant: 'success',
            text: "Alokasi berhasil: {$tim?->nama_tim} → {$ruangan?->nama_ruangan}."
        );

        unset($this->alokasiRows);
    }

    public function hapusAlokasi(int $alokasiId): void
    {
        $alokasi = AlokasiRuangan::find($alokasiId);
        if ($alokasi) {
            $nama = $alokasi->tim?->nama_tim;
            $alokasi->delete();
            Flux::toast(variant: 'success', text: "Alokasi ruangan tim {$nama} berhasil dihapus.");
        }

        unset($this->alokasiRows);
    }

    public function autoAlokasiMingguIni(): void
    {
        if (! $this->periodeAktif) {
            Flux::toast(variant: 'danger', text: 'Tidak ada periode WFO yang aktif.');
            return;
        }

        $start = Carbon::parse($this->tanggalMulaiMinggu);
        $tanggalList = [];
        for ($i = 0; $i < 6; $i++) {
            $tanggalList[] = $start->copy()->addDays($i);
        }

        // Hapus alokasi lama untuk rentang minggu ini supaya bersih
        AlokasiRuangan::whereBetween('tanggal', [
            $tanggalList[0]->toDateString(),
            $tanggalList[5]->toDateString(),
        ])->delete();

        $scheduler = app(LraScheduler::class);
        $hasil = $scheduler->generateAlokasiRuangan($tanggalList, $this->periodeAktif->id);

        if (empty($hasil)) {
            Flux::toast(variant: 'warning', text: 'Tidak ada jadwal WFO tim yang ditemukan untuk dialokasikan.');
            return;
        }

        AlokasiRuangan::insert(array_map(fn ($r) => [
            'tim_id' => $r['tim_id'],
            'ruangan_id' => $r['ruangan_id'],
            'tanggal' => $r['tanggal'],
            'created_at' => now(),
            'updated_at' => now(),
        ], $hasil));

        Flux::toast(
            variant: 'success',
            text: 'Berhasil meng-generate ' . count($hasil) . ' alokasi ruangan mingguan secara otomatis.'
        );

        unset($this->alokasiRows);
    }
}; ?>

<div
    class="space-y-6"
    x-data="{
        draggingItem: null,
        dragOverRuanganId: null,
        dragOverTanggal: null,
        rows: @js($this->alokasiRows),
        timWfoMap: @js($this->timWfoPerTanggal),

        init() {
            $wire.$watch('alokasiRows', val => {
                this.rows = val;
            });
            $wire.$watch('timWfoPerTanggal', val => {
                this.timWfoMap = val;
            });
        },

        getAllocation(ruanganId, tanggal) {
            return this.rows.find(r => r.ruangan_id == ruanganId && r.tanggal == tanggal);
        },

        getAvailableTeams(tanggal) {
            const assignedTimIds = this.rows.filter(r => r.tanggal == tanggal).map(r => r.tim_id);
            const wfoTeams = this.timWfoMap[tanggal] || [];
            return wfoTeams.filter(t => !assignedTimIds.includes(t.id));
        },

        dragStart(event, item) {
            this.draggingItem = item;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', JSON.stringify(item));
        },

        dragOver(event, ruanganId, tanggal) {
            event.preventDefault();
            this.dragOverRuanganId = ruanganId;
            this.dragOverTanggal = tanggal;
        },

        dragLeave(event, ruanganId, tanggal) {
            if (this.dragOverRuanganId == ruanganId && this.dragOverTanggal == tanggal) {
                this.dragOverRuanganId = null;
                this.dragOverTanggal = null;
            }
        },

        dropItem(event, targetRuanganId, targetTanggal) {
            event.preventDefault();
            if (!this.draggingItem) return;

            const alokasiId = this.draggingItem.id;
            const sourceRuanganId = this.draggingItem.ruangan_id;
            const sourceTanggal = this.draggingItem.tanggal;

            if (sourceRuanganId == targetRuanganId && sourceTanggal == targetTanggal) {
                this.draggingItem = null;
                this.dragOverRuanganId = null;
                this.dragOverTanggal = null;
                return;
            }

            // Optimistic update
            const targetAlloc = this.getAllocation(targetRuanganId, targetTanggal);
            if (targetAlloc) {
                targetAlloc.ruangan_id = sourceRuanganId;
                targetAlloc.tanggal = sourceTanggal;
            }

            this.draggingItem.ruangan_id = targetRuanganId;
            this.draggingItem.tanggal = targetTanggal;

            $wire.pindahRuangan(alokasiId, targetRuanganId, targetTanggal);

            this.draggingItem = null;
            this.dragOverRuanganId = null;
            this.dragOverTanggal = null;
        }
    }"
>
    {{-- Top Header Section --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">
                Alokasi Ruangan Mingguan
            </flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400 mt-0.5">
                Kelola distribusi workstation & ruangan tim WFO dengan visual grid interaktif (Drag & Drop).
            </flux:text>
        </div>

        {{-- Week Navigator & Auto-Generate Button --}}
        <div class="flex items-center gap-2 flex-wrap">
            <div class="inline-flex items-center rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-1 shadow-xs">
                <button
                    wire:click="prevWeek"
                    type="button"
                    class="p-1.5 rounded-md text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors"
                    title="Minggu Sebelumnya"
                >
                    <flux:icon icon="chevron-left" class="size-4" />
                </button>
                <button
                    wire:click="todayWeek"
                    type="button"
                    class="px-2.5 py-1 text-xs font-semibold text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded-md transition-colors"
                >
                    Minggu Ini
                </button>
                <button
                    wire:click="nextWeek"
                    type="button"
                    class="p-1.5 rounded-md text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors"
                    title="Minggu Berikutnya"
                >
                    <flux:icon icon="chevron-right" class="size-4" />
                </button>
            </div>

            <flux:button variant="primary" icon="sparkles" wire:click="autoAlokasiMingguIni">
                Auto-Alokasi Ruangan
            </flux:button>
        </div>
    </div>

    @if (! $this->periodeAktif)
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>Tidak Ada Periode WFO Aktif</flux:callout.heading>
            <flux:callout.text>
                Silakan aktifkan periode WFO di menu
                <a href="{{ route('admin.periode-wfo') }}" wire:navigate class="underline font-semibold">Periode WFO</a>
                agar sistem dapat memetakan tim yang bekerja WFO per harinya.
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- Interactive Room Allocation Grid --}}
    <flux:card class="p-0 overflow-hidden border border-zinc-200 dark:border-zinc-700 shadow-sm">
        <div class="px-5 py-3.5 border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-900/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="size-2.5 rounded-full bg-blue-500 animate-pulse"></span>
                <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                    Rentang: {{ Carbon::parse($tanggalMulaiMinggu)->translatedFormat('d F Y') }} – {{ Carbon::parse($tanggalMulaiMinggu)->addDays(5)->translatedFormat('d F Y') }}
                </span>
            </div>
            <div class="flex items-center gap-3 text-xs text-zinc-500">
                <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-blue-500"></span> Drag & drop kartu tim untuk swap ruangan</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[900px]">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-800 bg-zinc-100/60 dark:bg-zinc-900/60 text-xs font-semibold text-zinc-600 dark:text-zinc-300">
                        <th class="p-3.5 ps-5 w-48 sticky left-0 z-10 bg-zinc-100/90 dark:bg-zinc-900/90 backdrop-blur-xs border-r border-zinc-200/80 dark:border-zinc-800/80">
                            Ruangan
                        </th>
                        @foreach ($this->daftarHariMingguIni as $h)
                            <th class="p-3.5 text-center min-w-[150px] border-r border-zinc-200/80 dark:border-zinc-800/80 {{ $h['is_today'] ? 'bg-blue-50/70 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400' : '' }}">
                                <div class="font-bold text-sm">{{ $h['nama'] }}</div>
                                <div class="text-[11px] font-normal opacity-80 mt-0.5">{{ $h['label_tanggal'] }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 text-sm">
                    @forelse ($this->daftarRuangan as $ruangan)
                        <tr class="hover:bg-zinc-50/40 dark:hover:bg-zinc-900/20 transition-colors">
                            {{-- Ruangan Info Cell (Sticky Left) --}}
                            <td class="p-3.5 ps-5 sticky left-0 z-10 bg-white dark:bg-zinc-800 border-r border-zinc-200/80 dark:border-zinc-800/80 shadow-xs">
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $ruangan->nama_ruangan }}</div>
                                <div class="flex items-center gap-1.5 text-xs text-zinc-500 mt-1">
                                    <flux:icon icon="users" class="size-3 text-zinc-400" />
                                    <span>Kapasitas: {{ $ruangan->kapasitas }} orang</span>
                                </div>
                            </td>

                            {{-- Daily Cells (Drop targets) --}}
                            @foreach ($this->daftarHariMingguIni as $h)
                                <td
                                    class="p-2 border-r border-zinc-200/80 dark:border-zinc-800/80 align-top transition-colors relative"
                                    :class="{
                                        'bg-blue-500/10 ring-2 ring-blue-500 ring-inset rounded-lg': dragOverRuanganId == {{ $ruangan->id }} && dragOverTanggal == '{{ $h['tanggal'] }}',
                                        'bg-blue-50/30 dark:bg-blue-950/10': {{ $h['is_today'] ? 'true' : 'false' }}
                                    }"
                                    @dragover="dragOver($event, {{ $ruangan->id }}, '{{ $h['tanggal'] }}')"
                                    @dragleave="dragLeave($event, {{ $ruangan->id }}, '{{ $h['tanggal'] }}')"
                                    @drop="dropItem($event, {{ $ruangan->id }}, '{{ $h['tanggal'] }}')"
                                >
                                    <div class="min-h-[72px] flex flex-col justify-center">
                                        {{-- Assigned Team Card --}}
                                        <template x-if="getAllocation({{ $ruangan->id }}, '{{ $h['tanggal'] }}')">
                                            <div
                                                draggable="true"
                                                @dragstart="dragStart($event, getAllocation({{ $ruangan->id }}, '{{ $h['tanggal'] }}'))"
                                                class="group/card relative p-2.5 rounded-xl bg-blue-50 dark:bg-blue-950/50 border border-blue-200 dark:border-blue-800/60 shadow-xs hover:shadow-md hover:border-blue-400 dark:hover:border-blue-600 transition-all cursor-grab active:cursor-grabbing select-none"
                                            >
                                                <div class="flex items-start justify-between gap-1.5">
                                                    <div class="min-w-0 flex-1">
                                                        <div class="font-semibold text-xs text-blue-900 dark:text-blue-200 truncate leading-tight" x-text="getAllocation({{ $ruangan->id }}, '{{ $h['tanggal'] }}').nama_tim"></div>
                                                        <div class="flex items-center gap-1 text-[10px] text-blue-700/80 dark:text-blue-300/80 mt-1">
                                                            <span class="inline-block size-1.5 rounded-full bg-blue-500"></span>
                                                            <span x-text="getAllocation({{ $ruangan->id }}, '{{ $h['tanggal'] }}').personil_count + ' personil'"></span>
                                                        </div>
                                                    </div>

                                                    {{-- Delete allocation button --}}
                                                    <button
                                                        type="button"
                                                        @click="$wire.hapusAlokasi(getAllocation({{ $ruangan->id }}, '{{ $h['tanggal'] }}').id)"
                                                        class="opacity-0 group-hover/card:opacity-100 p-1 text-zinc-400 hover:text-red-600 dark:hover:text-red-400 rounded-md transition-opacity"
                                                        title="Hapus alokasi ruangan"
                                                    >
                                                        <flux:icon icon="x-mark" class="size-3.5" />
                                                    </button>
                                                </div>
                                            </div>
                                        </template>

                                        {{-- Empty Slot Dropzone with Add Button --}}
                                        <template x-if="!getAllocation({{ $ruangan->id }}, '{{ $h['tanggal'] }}')">
                                            <div class="h-full flex flex-col items-center justify-center p-2 rounded-lg border border-dashed border-zinc-200 dark:border-zinc-700/60 hover:border-blue-400 dark:hover:border-blue-600 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all">
                                                <div x-data="{ openMenu: false }" class="relative">
                                                    <button
                                                        type="button"
                                                        @click="openMenu = !openMenu"
                                                        class="inline-flex items-center gap-1 text-[11px] font-medium text-zinc-400 hover:text-blue-600 dark:hover:text-blue-400 py-1 px-2 rounded-md transition-colors"
                                                    >
                                                        <flux:icon icon="plus" class="size-3" />
                                                        <span>Pilih Tim</span>
                                                    </button>

                                                    {{-- Dropdown options --}}
                                                    <div
                                                        x-show="openMenu"
                                                        @click.outside="openMenu = false"
                                                        x-transition
                                                        class="absolute z-30 mt-1 left-1/2 -translate-x-1/2 w-48 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg py-1.5 text-xs"
                                                    >
                                                        <div class="px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-zinc-400">Tim WFO Hari Ini</div>
                                                        <template x-for="tim in getAvailableTeams('{{ $h['tanggal'] }}')" :key="tim.id">
                                                            <button
                                                                type="button"
                                                                @click="$wire.tambahAlokasi(tim.id, {{ $ruangan->id }}, '{{ $h['tanggal'] }}'); openMenu = false"
                                                                class="w-full text-left px-2.5 py-1.5 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 dark:hover:text-blue-400 transition-colors flex items-center justify-between"
                                                            >
                                                                <span class="truncate font-medium" x-text="tim.nama_tim"></span>
                                                                <flux:icon icon="plus" class="size-3 text-zinc-400" />
                                                            </button>
                                                        </template>
                                                        <div x-show="getAvailableTeams('{{ $h['tanggal'] }}').length === 0" class="px-2.5 py-2 text-zinc-400 italic text-[11px] text-center">
                                                            Semua tim WFO sudah dialokasikan
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-zinc-400">
                                Belum ada data ruangan aktif. Silakan tambahkan ruangan di menu Manajemen Ruangan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>
</div>
