<?php

use App\Models\JadwalAdzanKitab;
use App\Models\JadwalBriefing;
use App\Models\Notifikasi;
use App\Services\AutoSwapService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Jadwal Saya')] #[Layout('layouts.auth')] class extends Component {

    public ?int $konfirmasiId   = null;
    public string $konfirmasiTipe = ''; // 'adzan' | 'briefing'

    // Modal berhalangan
    public bool $modalBerhalangan = false;
    public int $selectedJadwalId = 0;
    public string $selectedJadwalTipe = '';
    public string $alasanBerhalangan = '';
    
    // PHASE 3: Berhalangan scope (pagi/sore/both)
    public string $berhalanganScope = 'single'; // 'single' or 'both'
    public ?int $relatedJadwalId = null; // For "both" scenario

    #[Computed]
    public function personil()
    {
        return auth()->user()->personil;
    }

    #[Computed]
    public function tugasMendatang(): array
    {
        if (! $this->personil) {
            return [];
        }

        $personilId = $this->personil->id;
        $sekarang   = now()->toDateString();

        $adzan = JadwalAdzanKitab::where('personil_id', $personilId)
            ->where('tanggal', '>=', $sekarang)
            ->orderBy('tanggal')
            ->orderBy('waktu_sholat')
            ->get()
            ->map(fn ($j) => [
                'id'                => $j->id,
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
            ])
            ->toArray();

        $briefing = JadwalBriefing::with('tim')
            ->where(function ($query) use ($personilId) {
                $query->where('personil_id', $personilId)
                    ->orWhere('moderator_id', $personilId)
                    ->orWhere('doa_id', $personilId);
            })
            ->where('tanggal', '>=', $sekarang)
            ->orderBy('tanggal')
            ->get()
            ->map(fn ($j) => [
                'id'                => $j->id,
                'tipe'              => 'briefing',
                'tanggal'           => $j->tanggal,
                'label'             => 'Briefing '.ucfirst($j->sesi),
                'keterangan'        => $j->tim->nama_tim ?? '—',
                'status_konfirmasi' => $j->status_konfirmasi,
                // PHASE 4: Role labels - personil_id adalah notulensi
                'roles'             => collect([
                    $j->personil_id === $personilId ? 'Notulensi' : null,
                    $j->moderator_id === $personilId ? 'Moderator' : null,
                    $j->doa_id === $personilId ? 'Doa' : null,
                ])->filter()->values()->toArray(),
            ])
            ->toArray();

        // Gabung dan urutkan by tanggal
        return collect(array_merge($adzan, $briefing))
            ->sortBy('tanggal')
            ->values()
            ->toArray();
    }

    #[Computed]
    public function jumlahBelumDibaca(): int
    {
        if (! $this->personil) {
            return 0;
        }

        return Notifikasi::where('personil_id', $this->personil->id)
            ->where('dibaca', false)
            ->count();
    }

    public function openModalBerhalangan(int $id, string $tipe): void
    {
        $this->selectedJadwalId = $id;
        $this->selectedJadwalTipe = $tipe;
        $this->alasanBerhalangan = '';
        $this->berhalanganScope = 'single';
        $this->relatedJadwalId = null;
        
        // PHASE 3: Check if personil punya jadwal di sesi lain di hari yang sama (only for briefing)
        if ($tipe === 'briefing') {
            $jadwal = JadwalBriefing::findOrFail($id);
            $sesiLain = $jadwal->sesi === 'pagi' ? 'sore' : 'pagi';
            
            $relatedJadwal = JadwalBriefing::where('personil_id', $this->personil->id)
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

        if (! $this->personil) {
            return;
        }

        // PHASE 3 - PART 2: Check berhalangan counter PER-TIM (bukan per-personil)
        // Formula: COUNT(DISTINCT tanggal) per tim per jenis per bulan
        $jadwal = $this->selectedJadwalTipe === 'adzan' 
            ? JadwalAdzanKitab::findOrFail($this->selectedJadwalId)
            : JadwalBriefing::findOrFail($this->selectedJadwalId);
        
        $year = $jadwal->tanggal->year;
        $month = $jadwal->tanggal->month;
        
        // Count berhalangan per TIM per jenis (adzan/briefing)
        // IMPORTANT: COUNT(DISTINCT tanggal) untuk avoid double-count (pagi + sore = 1 quota)
        if ($this->selectedJadwalTipe === 'adzan') {
            // Count adzan/kitab berhalangan untuk TIM ini
            $countBerhalangan = JadwalAdzanKitab::whereHas('personil', fn($q) => $q->where('tim_id', $this->personil->tim_id))
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
            $countBerhalangan = JadwalBriefing::where('tim_id', $this->personil->tim_id)
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
                text: "Tim {$this->personil->tim->nama_tim} sudah berhalangan {$maxQuota}x untuk {$jenisLabel} di bulan {$bulanLabel}. Hubungi admin jika kondisi darurat.",
                duration: 8000
            );
            
            return;
        }

        // Update status + alasan untuk jadwal selected
        $this->updateStatusKonfirmasi($this->selectedJadwalId, $this->selectedJadwalTipe, 'berhalangan', $this->alasanBerhalangan);

        // Auto swap logic untuk jadwal selected
        $autoSwap = app(AutoSwapService::class);
        $pesan = $this->selectedJadwalTipe === 'adzan'
            ? $autoSwap->berhalanganAdzan($this->selectedJadwalId, $this->personil->id, $this->alasanBerhalangan)
            : $autoSwap->berhalanganBriefing($this->selectedJadwalId, $this->personil->id, $this->alasanBerhalangan);

        // PHASE 3: If berhalangan "both", update related jadwal juga
        if ($this->berhalanganScope === 'both' && $this->relatedJadwalId) {
            $this->updateStatusKonfirmasi($this->relatedJadwalId, 'briefing', 'berhalangan', $this->alasanBerhalangan);
            
            // Auto swap untuk related jadwal
            $pesanRelated = $autoSwap->berhalanganBriefing($this->relatedJadwalId, $this->personil->id, $this->alasanBerhalangan);
            $pesan .= " & " . $pesanRelated;
        }

        Flux::toast(variant: 'warning', text: "Berhalangan dicatat. {$pesan}");
        
        // Reset & close
        $this->modalBerhalangan = false;
        $this->alasanBerhalangan = '';
        $this->berhalanganScope = 'single';
        $this->relatedJadwalId = null;
        unset($this->tugasMendatang);
    }

    private function updateStatusKonfirmasi(int $id, string $tipe, string $status, ?string $alasan = null): void
    {
        $data = ['status_konfirmasi' => $status];
        
        if ($alasan !== null) {
            $data['alasan_berhalangan'] = $alasan;
        }

        if ($tipe === 'adzan') {
            JadwalAdzanKitab::where('id', $id)
                ->where('personil_id', $this->personil->id)
                ->update($data);
        } else {
            JadwalBriefing::where('id', $id)
                ->where('personil_id', $this->personil->id)
                ->update($data);
        }
    }
}; ?>

<div class="min-h-screen bg-zinc-50 dark:bg-zinc-900">
    {{-- Topbar --}}
    <header class="sticky top-0 z-20 border-b border-zinc-200/60 dark:border-zinc-700/60 bg-white/80 dark:bg-zinc-800/80 backdrop-blur-md h-14 flex items-center px-4 gap-3">
        <span class="font-semibold text-sm text-zinc-900 dark:text-zinc-100 flex-1">
            Jadwal Saya
        </span>

        {{-- Bell notifikasi --}}
        <a href="{{ route('notifikasi.index') }}" wire:navigate class="relative p-1.5 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-700">
            <flux:icon icon="bell" class="h-5 w-5 text-zinc-500" />
            @if ($this->jumlahBelumDibaca > 0)
                <span class="absolute top-0.5 right-0.5 h-4 w-4 rounded-full bg-red-500 text-white text-[10px] flex items-center justify-center font-bold">
                    {{ $this->jumlahBelumDibaca > 9 ? '9+' : $this->jumlahBelumDibaca }}
                </span>
            @endif
        </a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <flux:button type="submit" variant="ghost" size="sm">Keluar</flux:button>
        </form>
    </header>

    <div class="max-w-3xl mx-auto px-4 py-6 flex flex-col gap-6">
        {{-- Greeting --}}
        <div>
            <flux:heading size="xl">Halo, {{ auth()->user()->name }} 👋</flux:heading>
            <flux:text class="text-zinc-500">
                Berikut jadwal tugasmu ke depan. Konfirmasi kehadiranmu sebelum hari H.
            </flux:text>
        </div>

        @if (! $this->personil)
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>Akun belum terhubung ke data personil</flux:callout.heading>
                <flux:callout.text>Hubungi admin untuk menghubungkan akun ke data personil.</flux:callout.text>
            </flux:callout>
        @elseif (empty($this->tugasMendatang))
            <x-empty-state
                icon="calendar-days"
                title="Tidak ada tugas mendatang"
                description="Belum ada jadwal tugas ke depan."
            />
        @else
            <div class="flex flex-col gap-3">
                @foreach ($this->tugasMendatang as $tugas)
                    @php
                        $isMenunggu = $tugas['status_konfirmasi'] === 'menunggu';
                        $tanggal    = \Carbon\Carbon::parse($tugas['tanggal']);
                        $isHariIni  = $tanggal->isToday();
                        $isBesok    = $tanggal->isTomorrow();
                    @endphp

                    <flux:card class="flex flex-col sm:flex-row sm:items-center gap-3 {{ $isHariIni ? 'ring-2 ring-brand' : '' }}">
                        {{-- Info tanggal --}}
                        <div class="flex-shrink-0 text-center w-16">
                            <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 leading-none">
                                {{ $tanggal->format('d') }}
                            </p>
                            <p class="text-xs text-zinc-400 uppercase">
                                {{ $tanggal->translatedFormat('M Y') }}
                            </p>
                            @if ($isHariIni)
                                <span class="text-[10px] font-semibold text-brand">Hari ini</span>
                            @elseif ($isBesok)
                                <span class="text-[10px] font-semibold text-amber-500">Besok</span>
                            @endif
                        </div>

                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $tugas['label'] }}</p>
                            <p class="text-sm text-zinc-500">
                                {{ $tugas['keterangan'] }} · {{ $tanggal->locale('id')->translatedFormat('l') }}
                            </p>
                            
                            {{-- Role badges untuk briefing --}}
                            @if ($tugas['tipe'] === 'briefing' && !empty($tugas['roles']))
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    @foreach ($tugas['roles'] as $role)
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
                        </div>

                        {{-- Status & tombol konfirmasi --}}
                        <div class="flex items-center gap-2 flex-shrink-0">
                            @if ($isMenunggu)
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    wire:click="openModalBerhalangan({{ $tugas['id'] }}, '{{ $tugas['tipe'] }}')"
                                    wire:loading.attr="disabled"
                                    icon="x-mark"
                                >
                                    Berhalangan
                                </flux:button>
                            @else
                                <x-status-badge :status="$tugas['status_konfirmasi']" />
                            @endif
                        </div>
                    </flux:card>
                @endforeach
            </div>
        @endif
    </div>
</div>


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
