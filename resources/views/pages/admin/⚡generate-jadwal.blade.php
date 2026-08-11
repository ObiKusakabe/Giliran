<?php

use App\Models\JadwalAdzanKitab;
use App\Models\JadwalBriefing;
use App\Models\AlokasiRuangan;
use App\Models\PeriodeWfo;
use App\Services\LraScheduler;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Generate Jadwal')] #[Layout('layouts.admin')] class extends Component {

    public string $tanggalMulai   = '';
    public string $tanggalSelesai = '';

    /** Tab aktif: semua | adzan | briefing | ruangan */
    public string $tab = 'semua';

    /** Preview in-memory sebelum disimpan — GEN-07 */
    public array $previewAdzan    = [];
    public array $previewBriefing = [];
    public array $previewRuangan  = [];

    /** Flag apakah preview sudah ada */
    public bool $sudahPreview = false;

    /** Warning kalau ada tanggal dengan personil kurang */
    public array $warnings = [];

    #[Computed]
    public function periodeAktif(): ?PeriodeWfo
    {
        return PeriodeWfo::where('status', 'aktif')->first();
    }

    public function preview(): void
    {
        $this->validate([
            'tanggalMulai'   => 'required|date',
            'tanggalSelesai' => 'required|date|after_or_equal:tanggalMulai',
        ]);

        if (! $this->periodeAktif) {
            Flux::toast(variant: 'danger', text: 'Tidak ada periode WFO aktif. Aktifkan periode terlebih dahulu.');
            return;
        }

        $periode   = $this->periodeAktif;
        $scheduler = new LraScheduler;

        // Validasi rentang ada di dalam periode aktif
        $mulai   = Carbon::parse($this->tanggalMulai);
        $selesai = Carbon::parse($this->tanggalSelesai);

        if ($mulai->lt($periode->tanggal_mulai) || $selesai->gt($periode->tanggal_selesai)) {
            Flux::toast(variant: 'danger', text: 'Rentang tanggal harus berada di dalam periode aktif ('
                .$periode->tanggal_mulai->format('d/m/Y').' – '.$periode->tanggal_selesai->format('d/m/Y').').');
            return;
        }

        $tanggalList = $scheduler->expandTanggal($mulai, $selesai);

        if (empty($tanggalList)) {
            Flux::toast(variant: 'warning', text: 'Tidak ada hari kerja dalam rentang tanggal tersebut.');
            return;
        }

        // Generate preview — belum menyentuh DB
        $this->previewAdzan    = $scheduler->generateAdzanKajian($tanggalList, $periode->id);
        $this->previewBriefing = $scheduler->generateBriefing($tanggalList, $periode->id);
        $this->previewRuangan  = $scheduler->generateAlokasiRuangan($tanggalList, $periode->id);

        // Cek warning: hari yang personilnya 0
        $this->warnings = [];
        foreach ($tanggalList as $tgl) {
            $namaHari  = $scheduler->namaHariIndonesia($tgl);
            $kandidat  = $scheduler->ambilPersonilWfo($periode->id, $namaHari);
            $slots     = $scheduler->tentukanSlotAdzan($tgl);
            $butuh     = count($slots) * 2; // adzan + kajian per slot

            if ($kandidat->count() < $butuh && $butuh > 0) {
                $this->warnings[] = 'Tanggal '.$tgl->format('d/m/Y')
                    .' ('.$namaHari.'): personil tersedia '.$kandidat->count()
                    .', dibutuhkan '.$butuh.' — akan ada duplikasi tugas.';
            }
        }

        $this->sudahPreview = true;
    }

    public function simpan(): void
    {
        if (! $this->sudahPreview) {
            Flux::toast(variant: 'danger', text: 'Jalankan preview terlebih dahulu.');
            return;
        }

        DB::transaction(function () {
            // Bulk insert — GEN-08: tersimpan permanen
            if (! empty($this->previewAdzan)) {
                JadwalAdzanKitab::insert($this->previewAdzan);
            }

            if (! empty($this->previewBriefing)) {
                JadwalBriefing::insert($this->previewBriefing);
            }

            if (! empty($this->previewRuangan)) {
                AlokasiRuangan::insert($this->previewRuangan);
            }
        });

        $totalAdzan    = count($this->previewAdzan);
        $totalBriefing = count($this->previewBriefing);
        $totalRuangan  = count($this->previewRuangan);

        Flux::toast(
            variant: 'success',
            text: "Jadwal berhasil disimpan: {$totalAdzan} adzan/kajian, {$totalBriefing} briefing, {$totalRuangan} alokasi ruangan."
        );

        // Reset state
        $this->reset(['previewAdzan', 'previewBriefing', 'previewRuangan', 'sudahPreview', 'warnings']);
        unset($this->periodeAktif);

        // Redirect ke kalender setelah simpan (GEN setelah simpan → kalender)
        $this->redirect(route('admin.kalender'), navigate: true);
    }

    public function resetPreview(): void
    {
        $this->reset(['previewAdzan', 'previewBriefing', 'previewRuangan', 'sudahPreview', 'warnings']);
    }
}; ?>

<div
    x-data="{ tab: $wire.entangle('tab') }"
    class="flex flex-col gap-6"
>
    {{-- Header --}}
    <div>
        <flux:heading size="xl">Generate Jadwal</flux:heading>
        <flux:text class="text-zinc-500">
            Generate jadwal adzan/kajian, briefing, dan alokasi ruangan dari data WFO.
            Cek preview sebelum menyimpan.
        </flux:text>
    </div>

    {{-- Info periode aktif --}}
    @if ($this->periodeAktif)
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.heading>Periode Aktif: {{ $this->periodeAktif->keterangan }}</flux:callout.heading>
            <flux:callout.text>
                {{ $this->periodeAktif->tanggal_mulai->translatedFormat('d M Y') }}
                s/d {{ $this->periodeAktif->tanggal_selesai->translatedFormat('d M Y') }}
            </flux:callout.text>
        </flux:callout>
    @else
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.heading>Tidak ada periode WFO aktif</flux:callout.heading>
            <flux:callout.text>
                Aktifkan periode di halaman
                <a href="{{ route('admin.periode-wfo') }}" wire:navigate class="underline">Periode WFO</a>
                terlebih dahulu.
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- Form rentang tanggal --}}
    <flux:card>
        <div class="flex flex-col sm:flex-row gap-4 items-end">
            <div class="flex-1">
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                    Tanggal Mulai
                </label>
                <input
                    type="date"
                    wire:model="tanggalMulai"
                    class="w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand"
                    @if($this->periodeAktif)
                        min="{{ $this->periodeAktif->tanggal_mulai->toDateString() }}"
                        max="{{ $this->periodeAktif->tanggal_selesai->toDateString() }}"
                    @endif
                />
                @error('tanggalMulai') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex-1">
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                    Tanggal Selesai
                </label>
                <input
                    type="date"
                    wire:model="tanggalSelesai"
                    class="w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand"
                    @if($this->periodeAktif)
                        min="{{ $this->periodeAktif->tanggal_mulai->toDateString() }}"
                        max="{{ $this->periodeAktif->tanggal_selesai->toDateString() }}"
                    @endif
                />
                @error('tanggalSelesai') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <flux:button
                variant="primary"
                wire:click="preview"
                wire:loading.attr="disabled"
                wire:target="preview"
                icon="sparkles"
            >
                <span wire:loading.remove wire:target="preview">Preview</span>
                <span wire:loading wire:target="preview">Menghitung…</span>
            </flux:button>
        </div>
    </flux:card>

    {{-- Warnings --}}
    @if (! empty($warnings))
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>Perhatian: personil kurang di beberapa tanggal</flux:callout.heading>
            <flux:callout.text>
                <ul class="list-disc list-inside mt-1 space-y-0.5">
                    @foreach ($warnings as $w)
                        <li>{{ $w }}</li>
                    @endforeach
                </ul>
                Jadwal tetap di-generate dengan duplikasi terbatas (GEN-06).
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- Preview area --}}
    @if ($sudahPreview)
        {{-- Tab navigator (Alpine.js — pengganti flux:tabs Pro §3.6) --}}
        <flux:card class="p-0 overflow-hidden">
            {{-- Tab buttons --}}
            <div class="flex border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-4">
                @foreach ([
                    'semua'    => 'Semua ('.( count($previewAdzan) + count($previewBriefing) + count($previewRuangan) ).')',
                    'adzan'    => 'Adzan & Kajian ('.count($previewAdzan).')',
                    'briefing' => 'Briefing ('.count($previewBriefing).')',
                    'ruangan'  => 'Alokasi Ruangan ('.count($previewRuangan).')',
                ] as $key => $label)
                    <button
                        @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}'
                            ? 'border-b-2 border-brand text-brand font-medium'
                            : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                        class="px-4 py-3 text-sm transition-colors focus:outline-none"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="p-4">
                {{-- Badge preview --}}
                <div class="mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center rounded-full bg-amber-100 text-amber-700 px-2.5 py-0.5 text-xs font-medium">
                        Preview — belum tersimpan
                    </span>
                </div>

                {{-- Tab: Adzan & Kajian --}}
                <div x-show="tab === 'semua' || tab === 'adzan'">
                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Jadwal Adzan & Kajian</p>
                    @if (empty($previewAdzan))
                        <p class="text-sm text-zinc-400">Tidak ada data adzan/kajian untuk rentang ini.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead class="bg-zinc-50 dark:bg-zinc-800">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-zinc-500">Tanggal</th>
                                        <th class="px-3 py-2 text-left font-medium text-zinc-500">Waktu</th>
                                        <th class="px-3 py-2 text-left font-medium text-zinc-500">Tugas</th>
                                        <th class="px-3 py-2 text-left font-medium text-zinc-500">Personil</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                    @foreach ($previewAdzan as $row)
                                        @php $personil = \App\Models\Personil::find($row['personil_id']); @endphp
                                        <tr>
                                            <td class="px-3 py-1.5">{{ \Carbon\Carbon::parse($row['tanggal'])->translatedFormat('d M Y') }}</td>
                                            <td class="px-3 py-1.5 capitalize">{{ $row['waktu_sholat'] }}</td>
                                            <td class="px-3 py-1.5 capitalize">{{ $row['jenis_tugas'] }}</td>
                                            <td class="px-3 py-1.5">{{ $personil?->nama ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Tab: Briefing --}}
                <div x-show="tab === 'semua' || tab === 'briefing'" :class="(tab === 'semua') ? 'mt-6' : ''">
                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Jadwal Briefing</p>
                    @if (empty($previewBriefing))
                        <p class="text-sm text-zinc-400">Tidak ada data briefing untuk rentang ini.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead class="bg-zinc-50 dark:bg-zinc-800">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-zinc-500">Tanggal</th>
                                        <th class="px-3 py-2 text-left font-medium text-zinc-500">Sesi</th>
                                        <th class="px-3 py-2 text-left font-medium text-zinc-500">Tim</th>
                                        <th class="px-3 py-2 text-left font-medium text-zinc-500">Perwakilan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                    @foreach ($previewBriefing as $row)
                                        @php
                                            $personil = \App\Models\Personil::find($row['personil_id']);
                                            $tim      = \App\Models\Tim::find($row['tim_id']);
                                        @endphp
                                        <tr>
                                            <td class="px-3 py-1.5">{{ \Carbon\Carbon::parse($row['tanggal'])->translatedFormat('d M Y') }}</td>
                                            <td class="px-3 py-1.5 capitalize">{{ $row['sesi'] }}</td>
                                            <td class="px-3 py-1.5">{{ $tim?->nama_tim ?? '—' }}</td>
                                            <td class="px-3 py-1.5">{{ $personil?->nama ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Tab: Alokasi Ruangan --}}
                <div x-show="tab === 'semua' || tab === 'ruangan'" :class="(tab === 'semua') ? 'mt-6' : ''">
                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Alokasi Ruangan</p>
                    @if (empty($previewRuangan))
                        <p class="text-sm text-zinc-400">Tidak ada alokasi ruangan untuk rentang ini.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead class="bg-zinc-50 dark:bg-zinc-800">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-zinc-500">Tanggal</th>
                                        <th class="px-3 py-2 text-left font-medium text-zinc-500">Tim</th>
                                        <th class="px-3 py-2 text-left font-medium text-zinc-500">Ruangan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                    @foreach ($previewRuangan as $row)
                                        @php
                                            $tim     = \App\Models\Tim::find($row['tim_id']);
                                            $ruangan = \App\Models\Ruangan::find($row['ruangan_id']);
                                        @endphp
                                        <tr>
                                            <td class="px-3 py-1.5">{{ \Carbon\Carbon::parse($row['tanggal'])->translatedFormat('d M Y') }}</td>
                                            <td class="px-3 py-1.5">{{ $tim?->nama_tim ?? '—' }}</td>
                                            <td class="px-3 py-1.5">{{ $ruangan?->nama_ruangan ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Tombol aksi --}}
            <div class="flex justify-between items-center px-4 py-3 border-t border-zinc-100 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-800/50">
                <flux:button
                    variant="ghost"
                    wire:click="resetPreview"
                    size="sm"
                >
                    Ulangi
                </flux:button>

                <flux:button
                    variant="primary"
                    wire:click="simpan"
                    wire:loading.attr="disabled"
                    wire:target="simpan"
                    icon="check"
                >
                    <span wire:loading.remove wire:target="simpan">Simpan ke Database</span>
                    <span wire:loading wire:target="simpan">Menyimpan…</span>
                </flux:button>
            </div>
        </flux:card>
    @endif
</div>
