<?php

use App\Models\JadwalAdzanKitab;
use App\Models\JadwalBriefing;
use App\Models\Notifikasi;
use App\Models\Personil;
use App\Services\AutoSwapService;
use App\Services\LraScheduler;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Jadwal Tim')] #[Layout('layouts.app')] class extends Component {

    // Modal berhalangan
    public bool $modalBerhalangan = false;
    public int $selectedJadwalId = 0;
    public string $selectedJadwalTipe = '';
    public int $selectedPersonilId = 0;
    public string $alasanBerhalangan = '';
    
    // PHASE 3: Berhalangan scope (pagi/sore/both)
    public string $berhalanganScope = 'single'; // 'single' or 'both'
    public ?int $relatedJadwalId = null; // For "both" scenario

    #[Computed]
    public function tim()
    {
        return auth()->user()->tim;
    }

    #[Computed]
    public function timId(): ?int
    {
        return auth()->user()->tim_id;
    }

    /** Semua personil aktif dalam tim ini */
    #[Computed]
    public function personilList()
    {
        if (! $this->timId) {
            return collect();
        }

        return Personil::where('tim_id', $this->timId)
            ->where('status', 'aktif')
            ->orderBy('nama')
            ->get();
    }

    /**
     * Tugas mendatang dikelompokkan per personil.
     * Format: [personil_id => ['personil' => Personil, 'tugas' => [...]]]
     */
    #[Computed]
    public function tugasPerPersonil(): array
    {
        if (! $this->timId) {
            return [];
        }

        $sekarang    = now()->toDateString();
        $personilIds = $this->personilList->pluck('id');

        // Adzan/kajian mendatang
        $adzan = JadwalAdzanKitab::whereIn('personil_id', $personilIds)
            ->where('tanggal', '>=', $sekarang)
            ->with('originalPersonil')  // Eager load untuk badge pengganti
            ->orderBy('tanggal')->orderBy('waktu_sholat')
            ->get()
            ->map(fn ($j) => [
                'id'                => $j->id,
                'personil_id'       => $j->personil_id,
                'tipe'              => 'adzan',
                'tanggal'           => $j->tanggal,
                'label'             => ucfirst($j->jenis_tugas).' '.match($j->waktu_sholat) {
                    'dhuhr' => 'Zuhur',
                    'asr' => 'Ashar',
                    'fajr' => 'Subuh',
                    'maghrib' => 'Maghrib',
                    'isha' => 'Isya',
                    default => strtoupper($j->waktu_sholat),
                },
                'keterangan'        => ucfirst($j->jenis_tugas),  // "Adzan" atau "Kajian" saja, bukan gabungan
                'status_konfirmasi' => $j->status_konfirmasi,
                'roles'             => [], // Empty untuk adzan
                // Pengganti tracking
                'is_switched'       => $j->is_switched ?? false,
                'original_personil_nama' => $j->originalPersonil?->nama,
            ]);

        // Briefing mendatang (hanya tim ini)
        // Query semua jadwal briefing untuk tim ini
        $allBriefingRows = JadwalBriefing::where('tim_id', $this->timId)
            ->where('tanggal', '>=', $sekarang)
            ->with(['tim', 'originalPersonil'])  // Eager load originalPersonil untuk badge pengganti
            ->orderBy('tanggal')->orderBy('sesi')
            ->get();

        // Group by tanggal + sesi untuk identifikasi siapa saja yang terlibat
        $briefing = $allBriefingRows
            ->groupBy(fn ($j) => $j->tanggal->format('Y-m-d').'_'.$j->sesi)
            ->flatMap(function ($group) {
                // Untuk setiap sesi, kita perlu tahu:
                // 1. Siapa notulensi (yang is_notulen = true)
                // 2. Siapa moderator (cari row dimana moderator_id === personil_id)
                // 3. Siapa doa (cari row dimana doa_id === personil_id)
                
                $sampleRow = $group->first();
                $tanggal = $sampleRow->tanggal;
                $sesi = $sampleRow->sesi;
                $timNama = $sampleRow->tim?->nama_tim ?? '—';
                
                // Ambil IDs dari row pertama (moderator_id & doa_id sama untuk semua row di sesi ini)
                $moderatorId = $sampleRow->moderator_id;
                $doaId = $sampleRow->doa_id;
                
                // Cari siapa notulensi
                $notulenRow = $group->firstWhere('is_notulen', true);
                $notulenId = $notulenRow?->personil_id;
                
                // Kumpulkan semua personil yang terlibat
                $involvedPersonilIds = collect([$notulenId, $moderatorId, $doaId])
                    ->filter()
                    ->unique()
                    ->values();
                
                // Buat 1 row per personil yang terlibat
                $results = [];
                foreach ($involvedPersonilIds as $pid) {
                    // Cari row asli untuk personil ini (untuk ambil status_konfirmasi & id)
                    $personilRow = $group->firstWhere('personil_id', $pid);
                    
                    // Jika row tidak ditemukan, gunakan row pertama sebagai template
                    // (untuk moderator/doa yang tidak punya dedicated row)
                    if (! $personilRow) {
                        $personilRow = $sampleRow;
                    }
                    
                    $results[] = [
                        'id'                => $personilRow->id,
                        'personil_id'       => (int)$pid,
                        'tipe'              => 'briefing',
                        'tanggal'           => $tanggal,
                        'label'             => 'Briefing '.ucfirst($sesi),
                        'keterangan'        => $timNama,
                        'status_konfirmasi' => $personilRow->status_konfirmasi,
                        // Role flags
                        'is_notulen'        => (int)$pid === (int)$notulenId,
                        'is_moderator'      => (int)$pid === (int)$moderatorId,
                        'is_doa'            => (int)$pid === (int)$doaId,
                        // Pengganti tracking
                        'is_switched'       => $personilRow->is_switched ?? false,
                        'original_personil_nama' => $personilRow->originalPersonil?->nama,
                    ];
                }
                
                return $results;
            });

        // Gabungkan & group per personil
        $semuaTugas = collect(array_merge($adzan->toArray(), $briefing->toArray()))
            ->sortBy('tanggal');

        $result = [];
        foreach ($this->personilList as $personil) {
            $tugas = $semuaTugas->filter(fn ($t) => (int)$t['personil_id'] === (int)$personil->id)->values();
            $result[$personil->id] = [
                'personil' => $personil,
                'tugas'    => $tugas->toArray(),
            ];
        }

        return $result;
    }

    #[Computed]
    public function jumlahBelumDibaca(): int
    {
        if (! $this->timId) {
            return 0;
        }

        return Notifikasi::where('user_id', auth()->id())
            ->where('dibaca', false)
            ->count();
    }

    public function openModalBerhalangan(int $id, string $tipe, int $personilId): void
    {
        $this->selectedJadwalId = $id;
        $this->selectedJadwalTipe = $tipe;
        $this->selectedPersonilId = $personilId;
        $this->alasanBerhalangan = '';
        $this->berhalanganScope = 'single';
        $this->relatedJadwalId = null;
        
        // PHASE 3: Check if personil punya jadwal di sesi lain di hari yang sama (only for briefing)
        if ($tipe === 'briefing') {
            $jadwal = JadwalBriefing::findOrFail($id);
            $sesiLain = $jadwal->sesi === 'pagi' ? 'sore' : 'pagi';
            
            $relatedJadwal = JadwalBriefing::where('personil_id', $personilId)
                ->where('tanggal', $jadwal->tanggal)
                ->where('sesi', $sesiLain)
                ->where('status_konfirmasi', '!=', 'berhalangan')
                ->first();
            
            $this->relatedJadwalId = $relatedJadwal?->id;
        }
        
        $this->modalBerhalangan = true;
    }

    public function konfirmasiBerhalangan(): void
    {
        $this->validate([
            'alasanBerhalangan' => 'required|min:10|max:500',
            'berhalanganScope' => 'required|in:single,both',
        ], [
            'alasanBerhalangan.required' => 'Alasan berhalangan wajib diisi.',
            'alasanBerhalangan.min' => 'Alasan minimal 10 karakter.',
            'alasanBerhalangan.max' => 'Alasan maksimal 500 karakter.',
        ]);

        // PHASE 3 - PART 2: Check berhalangan counter PER-TIM (bukan per-personil)
        // Formula: COUNT(DISTINCT tanggal) per tim per jenis per bulan
        $personil = Personil::findOrFail($this->selectedPersonilId);
        $jadwal = $this->selectedJadwalTipe === 'adzan' 
            ? JadwalAdzanKitab::findOrFail($this->selectedJadwalId)
            : JadwalBriefing::findOrFail($this->selectedJadwalId);
        
        $year = $jadwal->tanggal->year;
        $month = $jadwal->tanggal->month;
        
        // Count berhalangan per TIM per jenis (adzan/briefing)
        // IMPORTANT: COUNT(DISTINCT tanggal) untuk avoid double-count (pagi + sore = 1 quota)
        if ($this->selectedJadwalTipe === 'adzan') {
            // Count adzan/kitab berhalangan untuk TIM ini
            $countBerhalangan = JadwalAdzanKitab::whereHas('personil', fn($q) => $q->where('tim_id', $personil->tim_id))
                ->where('status_konfirmasi', 'berhalangan')
                ->whereYear('tanggal', $year)
                ->whereMonth('tanggal', $month)
                ->distinct('tanggal')
                ->count('tanggal');
            
            $maxQuota = 2;
            $jenisLabel = 'Adzan/Kitab';
        } else {
            // Count briefing berhalangan untuk TIM ini
            // NOTE: Count DISTINCT tanggal (pagi + sore di hari yang sama = 1 quota)
            $countBerhalangan = JadwalBriefing::where('tim_id', $personil->tim_id)
                ->where('status_konfirmasi', 'berhalangan')
                ->whereYear('tanggal', $year)
                ->whereMonth('tanggal', $month)
                ->distinct('tanggal')
                ->count('tanggal');
            
            $maxQuota = 2;
            $jenisLabel = 'Briefing';
        }
        
        // Check quota
        if ($countBerhalangan >= $maxQuota) {
            $bulanLabel = $jadwal->tanggal->translatedFormat('F Y');
            
            Flux::toast(
                variant: 'danger',
                heading: 'Kuota Tim Habis',
                text: "Tim {$personil->tim->nama_tim} sudah berhalangan {$maxQuota}x untuk {$jenisLabel} di bulan {$bulanLabel}. Hubungi admin jika kondisi darurat.",
                duration: 8000
            );
            
            return;
        }

        // Update status + alasan untuk jadwal selected
        $this->updateStatus($this->selectedJadwalId, $this->selectedJadwalTipe, 'berhalangan', $this->alasanBerhalangan);

        // Auto swap logic untuk jadwal selected
        $autoSwap = app(AutoSwapService::class);
        $pesan = $this->selectedJadwalTipe === 'adzan'
            ? $autoSwap->berhalanganAdzan($this->selectedJadwalId, $this->selectedPersonilId, $this->alasanBerhalangan)
            : $autoSwap->berhalanganBriefing($this->selectedJadwalId, $this->selectedPersonilId, $this->alasanBerhalangan);

        // PHASE 3: If berhalangan "both", update related jadwal juga
        if ($this->berhalanganScope === 'both' && $this->relatedJadwalId) {
            $this->updateStatus($this->relatedJadwalId, 'briefing', 'berhalangan', $this->alasanBerhalangan);
            
            // Auto swap untuk related jadwal
            $pesanRelated = $autoSwap->berhalanganBriefing($this->relatedJadwalId, $this->selectedPersonilId, $this->alasanBerhalangan);
            $pesan .= " & " . $pesanRelated;
        }

        Flux::toast(variant: 'warning', text: "Berhalangan dicatat. {$pesan}");
        
        // Reset & close
        $this->modalBerhalangan = false;
        $this->alasanBerhalangan = '';
        $this->berhalanganScope = 'single';
        $this->relatedJadwalId = null;
        unset($this->tugasPerPersonil);
    }

    private function updateStatus(int $id, string $tipe, string $status, ?string $alasan = null): void
    {
        $data = ['status_konfirmasi' => $status];
        
        if ($alasan !== null) {
            $data['alasan_berhalangan'] = $alasan;
        }

        if ($tipe === 'adzan') {
            JadwalAdzanKitab::where('id', $id)->update($data);
        } else {
            JadwalBriefing::where('id', $id)->update($data);
        }
    }
}; ?>

<div class="space-y-6">
    {{-- Header --}}
    <div>
        <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">Jadwal Tim</flux:heading>
        <flux:text class="text-zinc-500 dark:text-zinc-400 mt-0.5">
            Jadwal tugas seluruh anggota {{ $this->tim?->nama_tim ?? 'tim' }} ke depan. Konfirmasi kehadiran masing-masing anggota.
        </flux:text>
    </div>

    {{-- Alert: Tambah Email untuk Keamanan --}}
    @if (!auth()->user()->email)
        <flux:card class="border-amber-200 dark:border-amber-900/50 bg-amber-50/50 dark:bg-amber-950/10 p-4">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="flex items-start gap-3 flex-1">
                    <flux:icon icon="shield-exclamation" class="size-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
                    <div>
                        <h4 class="font-semibold text-sm text-amber-900 dark:text-amber-100">Tingkatkan Keamanan Akun Anda</h4>
                        <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">
                            Tambahkan email untuk keamanan akun dan pemulihan password jika lupa.
                        </p>
                    </div>
                </div>
                <flux:button
                    size="sm"
                    variant="primary"
                    href="{{ route('tim.tambah-email') }}"
                    wire:navigate
                    icon="envelope"
                >
                    Tambah Email
                </flux:button>
            </div>
        </flux:card>
    @endif

    @if (! $this->timId)
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>Akun belum terhubung ke tim</flux:callout.heading>
                <flux:callout.text>Hubungi admin untuk menghubungkan akun ini ke data tim.</flux:callout.text>
            </flux:callout>

        @elseif (empty($this->tugasPerPersonil))
            <x-empty-state
                icon="calendar-days"
                title="Tidak ada tugas mendatang"
                description="Tim belum memiliki jadwal tugas ke depan."
            />

        @else
            @foreach ($this->tugasPerPersonil as $personilId => $data)
                @php
                    $personil     = $data['personil'];
                    $tugas        = $data['tugas'];
                    $hasTugas     = count($tugas) > 0;
                    $pending      = collect($tugas)->where('status_konfirmasi', 'menunggu')->count();
                @endphp

                <div class="flex flex-col gap-2">
                    {{-- Header personil --}}
                    <div class="flex items-center flex-wrap gap-2 px-1">
                        <div class="flex size-6 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-700 text-zinc-500 dark:text-zinc-400">
                            <flux:icon icon="user" class="size-3.5" />
                        </div>
                        <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ $personil->nama }}
                        </span>
                    </div>

                    @if (! $hasTugas)
                        <div class="rounded-lg border border-dashed border-zinc-200 dark:border-zinc-700 px-4 py-3 text-sm text-zinc-400">
                            Tidak ada tugas mendatang untuk {{ $personil->nama }}.
                        </div>
                    @else
                        @foreach ($tugas as $t)
                            @php
                                $tanggal   = \Carbon\Carbon::parse($t['tanggal']);
                                $isMenunggu = $t['status_konfirmasi'] === 'menunggu';
                                $isHariIni  = $tanggal->isToday();
                                $isBesok    = $tanggal->isTomorrow();
                            @endphp

                            <flux:card class="flex flex-col sm:flex-row sm:items-center gap-3 {{ $isHariIni ? 'ring-2 ring-brand' : '' }}">
                                {{-- Tanggal --}}
                                <div class="flex sm:flex-col items-center sm:items-center gap-3 sm:gap-0 flex-shrink-0 sm:w-14">
                                    <div class="flex items-baseline gap-1 sm:block sm:text-center">
                                        <p class="text-xl font-bold text-zinc-900 dark:text-zinc-100 leading-none">
                                            {{ $tanggal->format('d') }}
                                        </p>
                                        <p class="text-xs text-zinc-400 uppercase sm:mt-0">
                                            {{ $tanggal->translatedFormat('M Y') }}
                                        </p>
                                    </div>
                                    @if ($isHariIni)
                                        <span class="text-xs font-semibold text-brand">Hari ini</span>
                                    @elseif ($isBesok)
                                        <span class="text-xs font-semibold text-amber-500">Besok</span>
                                    @endif
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $t['label'] }}</p>
                                        
                                        {{-- Badge Pengganti --}}
                                        @if (!empty($t['is_switched']) && $t['is_switched'])
                                            <flux:badge 
                                                size="sm" 
                                                color="orange"
                                                icon="arrow-path"
                                                class="!text-xs"
                                            >
                                                Pengganti
                                                @if (!empty($t['original_personil_nama']))
                                                    {{ $t['original_personil_nama'] }}
                                                @endif
                                            </flux:badge>
                                        @endif
                                    </div>
                                    
                                    <p class="text-sm text-zinc-500">
                                        {{ $t['keterangan'] }} · {{ $tanggal->locale('id')->translatedFormat('l') }}
                                    </p>
                                    
                                    {{-- Role badges untuk briefing --}}
                                    @if ($t['tipe'] === 'briefing')
                                        @php
                                            // Gunakan flag yang sudah dihitung dari backend
                                            $isNotulen = $t['is_notulen'] ?? false;
                                            $isModerator = $t['is_moderator'] ?? false;
                                            $isDoa = $t['is_doa'] ?? false;
                                            
                                            $roles = collect([
                                                $isNotulen ? 'Notulensi' : null,
                                                $isModerator ? 'Moderator' : null,
                                                $isDoa ? 'Doa' : null,
                                            ])->filter()->values();
                                        @endphp
                                        
                                        @if ($roles->isNotEmpty())
                                            <div class="flex flex-wrap gap-1.5 mt-2">
                                                @foreach ($roles as $role)
                                                    <flux:badge 
                                                        size="sm" 
                                                        :color="match($role) {
                                                            'Notulensi' => 'blue',
                                                            'Moderator' => 'purple',
                                                            'Doa' => 'emerald',
                                                            default => 'zinc',
                                                        }"
                                                        class="!text-xs"
                                                    >
                                                        {{ $role }}
                                                    </flux:badge>
                                                @endforeach
                                            </div>
                                        @endif
                                    @endif
                                </div>

                                {{-- Konfirmasi --}}
                                <div class="flex items-center gap-2 flex-shrink-0 flex-wrap">
                                    @if ($t['tipe'] === 'briefing')
                                        {{-- Briefing: Tidak perlu konfirmasi, cuma ada tombol berhalangan --}}
                                        @if ($t['status_konfirmasi'] === 'berhalangan')
                                            <x-status-badge status="berhalangan" />
                                        @else
                                            <flux:button
                                                size="sm"
                                                variant="danger"
                                                wire:click="openModalBerhalangan({{ $t['id'] }}, '{{ $t['tipe'] }}', {{ $personilId }})"
                                                wire:loading.attr="disabled"
                                                icon="x-mark"
                                            >
                                                Berhalangan
                                            </flux:button>
                                        @endif
                                    @else
                                        {{-- Adzan/Kajian: Pakai logic lama (ada konfirmasi) --}}
                                        @if ($isMenunggu)
                                            <flux:button
                                                size="sm"
                                                variant="danger"
                                                wire:click="openModalBerhalangan({{ $t['id'] }}, '{{ $t['tipe'] }}', {{ $personilId }})"
                                                wire:loading.attr="disabled"
                                                icon="x-mark"
                                            >
                                                Berhalangan
                                            </flux:button>
                                        @else
                                            <x-status-badge :status="$t['status_konfirmasi']" />
                                        @endif
                                    @endif
                                </div>
                            </flux:card>
                        @endforeach
                    @endif
                </div>
            @endforeach
        @endif

    {{-- Modal: Alasan Berhalangan --}}
    <flux:modal wire:model="modalBerhalangan" class="max-w-md">
        <form wire:submit="konfirmasiBerhalangan">
            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                        <flux:icon.exclamation-triangle class="w-5 h-5 text-red-600 dark:text-red-400" />
                    </div>
                    <div class="flex-1">
                        <flux:heading size="lg">Konfirmasi Berhalangan</flux:heading>
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                            Mohon berikan alasan kenapa tidak bisa hadir. Alasan akan dicatat dalam sistem.
                        </flux:text>
                    </div>
                </div>

                {{-- PHASE 3: Scope selection (jika ada related jadwal) --}}
                @if ($relatedJadwalId)
                    @php
                        $jadwalCurrent = \App\Models\JadwalBriefing::find($selectedJadwalId);
                        $sesiCurrent = $jadwalCurrent ? ucfirst($jadwalCurrent->sesi) : 'Pagi/Sore';
                    @endphp
                    <flux:field>
                        <flux:label>Berhalangan untuk</flux:label>
                        <flux:radio.group wire:model.live="berhalanganScope">
                            <flux:radio value="single" label="Hanya sesi ini ({{ $sesiCurrent }})" />
                            <flux:radio value="both" label="Seharian (Pagi & Sore)" />
                        </flux:radio.group>
                        <flux:description class="flex items-start gap-1.5">
                            <flux:icon.light-bulb class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
                            <span>Pilih "Seharian" jika tidak bisa hadir sama sekali hari ini.</span>
                        </flux:description>
                    </flux:field>
                @endif

                <flux:field>
                    <flux:label>Alasan Berhalangan</flux:label>
                    <flux:textarea
                        wire:model="alasanBerhalangan"
                        placeholder="Contoh: Sedang sakit, ada keperluan keluarga, dll..."
                        rows="4"
                        required
                    />
                    <flux:error name="alasanBerhalangan" />
                    <flux:description>Minimal 10 karakter, maksimal 500 karakter</flux:description>
                </flux:field>

                <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button type="button" variant="ghost" wire:click="$set('modalBerhalangan', false)">
                        Batal
                    </flux:button>
                    <flux:button type="submit" variant="danger" icon="check">
                        Konfirmasi Berhalangan
                    </flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</div>
