<?php

use App\Models\AlokasiRuangan;
use App\Models\JadwalAdzanKitab;
use App\Models\JadwalBriefing;
use App\Models\JadwalWfo;
use App\Models\Notifikasi;
use App\Models\PeriodeWfo;
use App\Models\Personil;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Beranda')] #[Layout('layouts.app')] class extends Component {

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

    /** Status WFO hari ini */
    #[Computed]
    public function isWfoHariIni(): bool
    {
        if (! $this->timId) {
            return false;
        }

        $periode = PeriodeWfo::where('status', 'aktif')->first();

        if (! $periode) {
            return false;
        }

        // Use dev mode aware time
        $currentTime = \App\Helpers\DevModeHelper::now();
        $namaHari = strtolower($currentTime->locale('id')->dayName);

        return JadwalWfo::where('periode_wfo_id', $periode->id)
            ->where('tim_id', $this->timId)
            ->where('hari', $namaHari)
            ->exists();
    }

    /** Ruangan yang dialokasikan untuk tim ini hari ini */
    #[Computed]
    public function alokasiHariIni(): ?AlokasiRuangan
    {
        if (! $this->timId) {
            return null;
        }

        // Use dev mode aware time
        $currentTime = \App\Helpers\DevModeHelper::now();

        return AlokasiRuangan::with('ruangan')
            ->where('tim_id', $this->timId)
            ->where('tanggal', $currentTime->toDateString())
            ->first();
    }

    /** Jadwal terdekat (hari ini atau besok) */
    #[Computed]
    public function jadwalTerdekat(): ?array
    {
        if (! $this->timId) {
            return null;
        }

        // Get personil IDs dari tim ini
        $personilIds = Personil::where('tim_id', $this->timId)
            ->where('status', 'aktif')
            ->pluck('id')
            ->toArray();

        if (empty($personilIds)) {
            return null;
        }

        // Use dev mode aware time
        $currentTime = \App\Helpers\DevModeHelper::now();
        $today = $currentTime->toDateString();

        // Cari briefing hari ini (pagi & sore)
        $briefingsHariIni = JadwalBriefing::with('personil')
            ->where('tim_id', $this->timId)
            ->where('tanggal', $today)
            ->orderBy('sesi')
            ->get();

        foreach ($briefingsHariIni as $briefing) {
            $briefingTime = \Carbon\Carbon::parse($briefing->tanggal);
            if ($briefing->sesi === 'pagi') {
                $briefingTime->setTime(9, 0); // 09:00
            } else {
                $briefingTime->setTime(17, 0); // 17:00
            }

            // Skip if briefing already passed +10 minutes
            $endTime = $briefingTime->copy()->addMinutes(10);
            if ($currentTime->greaterThan($endTime)) {
                continue; // Skip this, find next
            }

            // Check if currently happening (within 10 minutes after start)
            $isHappening = $currentTime->between($briefingTime, $endTime);

            return [
                'tipe' => 'briefing',
                'label' => 'Briefing ' . ucfirst($briefing->sesi),
                'personil' => $briefing->personil->nama ?? 'Tidak ada',
                'tanggal' => $briefingTime,
                'is_today' => true,
                'is_happening' => $isHappening,
                'icon' => 'users',
            ];
        }

        // Cari adzan hari ini (jika briefing tidak ada)
        $adzanHariIni = JadwalAdzanKitab::with('personil')
            ->whereIn('personil_id', $personilIds)
            ->where('tanggal', $today)
            ->orderBy('waktu_sholat')
            ->first();

        if ($adzanHariIni) {
            return [
                'tipe' => 'adzan',
                'label' => 'Adzan ' . match($adzanHariIni->waktu_sholat) {
                    'dhuhr' => 'Zuhur',
                    'asr' => 'Ashar',
                    'fajr' => 'Subuh',
                    'maghrib' => 'Maghrib',
                    'isha' => 'Isya',
                    default => ucfirst($adzanHariIni->waktu_sholat),
                },
                'personil' => $adzanHariIni->personil->nama ?? 'Tidak ada',
                'tanggal' => \Carbon\Carbon::parse($adzanHariIni->tanggal),
                'is_today' => true,
                'is_happening' => false,
                'icon' => 'speaker-wave',
            ];
        }

        // Jika hari ini sudah lewat semua, cari jadwal berikutnya (besok, lusa, dst)
        // Cari max 7 hari ke depan
        for ($i = 1; $i <= 7; $i++) {
            $nextDate = $currentTime->copy()->addDays($i)->toDateString();

            $briefingNext = JadwalBriefing::with('personil')
                ->where('tim_id', $this->timId)
                ->where('tanggal', $nextDate)
                ->orderBy('sesi')
                ->first();

            if ($briefingNext) {
                $briefingTime = \Carbon\Carbon::parse($briefingNext->tanggal);
                if ($briefingNext->sesi === 'pagi') {
                    $briefingTime->setTime(9, 0);
                } else {
                    $briefingTime->setTime(17, 0);
                }

                return [
                    'tipe' => 'briefing',
                    'label' => 'Briefing ' . ucfirst($briefingNext->sesi),
                    'personil' => $briefingNext->personil->nama ?? 'Tidak ada',
                    'tanggal' => $briefingTime,
                    'is_today' => false,
                    'is_happening' => false,
                    'icon' => 'users',
                ];
            }

            $adzanNext = JadwalAdzanKitab::with('personil')
                ->whereIn('personil_id', $personilIds)
                ->where('tanggal', $nextDate)
                ->orderBy('waktu_sholat')
                ->first();

            if ($adzanNext) {
                return [
                    'tipe' => 'adzan',
                    'label' => 'Adzan ' . match($adzanNext->waktu_sholat) {
                        'dhuhr' => 'Zuhur',
                        'asr' => 'Ashar',
                        'fajr' => 'Subuh',
                        'maghrib' => 'Maghrib',
                        'isha' => 'Isya',
                        default => ucfirst($adzanNext->waktu_sholat),
                    },
                    'personil' => $adzanNext->personil->nama ?? 'Tidak ada',
                    'tanggal' => \Carbon\Carbon::parse($adzanNext->tanggal),
                    'is_today' => false,
                    'is_happening' => false,
                    'icon' => 'speaker-wave',
                ];
            }
        }

        return null;
    }

    /** Jadwal hari ini (timeline lengkap untuk display) */
    #[Computed]
    public function jadwalHariIniTimeline(): array
    {
        if (! $this->timId) {
            return [];
        }

        // Get personil IDs dari tim ini
        $personilIds = Personil::where('tim_id', $this->timId)
            ->where('status', 'aktif')
            ->pluck('id')
            ->toArray();

        if (empty($personilIds)) {
            return [];
        }

        // Use dev mode aware time
        $currentTime = \App\Helpers\DevModeHelper::now();
        $today = $currentTime->toDateString();

        $timeline = [];

        // Get all briefings hari ini
        $briefings = JadwalBriefing::with('personil')
            ->where('tim_id', $this->timId)
            ->where('tanggal', $today)
            ->orderBy('sesi')
            ->get();

        foreach ($briefings as $briefing) {
            $time = \Carbon\Carbon::parse($briefing->tanggal);
            if ($briefing->sesi === 'pagi') {
                $time->setTime(9, 0);
                $sortOrder = 1;
            } else {
                $time->setTime(17, 0);
                $sortOrder = 6;
            }

            $timeline[] = [
                'type' => 'briefing',
                'label' => 'Briefing ' . ucfirst($briefing->sesi),
                'personil' => $briefing->personil->nama ?? 'Tidak ada',
                'time' => $time,
                'sort_order' => $sortOrder,
                'icon' => 'users',
            ];
        }

        // Get all adzan hari ini
        $adzans = JadwalAdzanKitab::with('personil')
            ->whereIn('personil_id', $personilIds)
            ->where('tanggal', $today)
            ->get();

        foreach ($adzans as $adzan) {
            $time = \Carbon\Carbon::parse($adzan->tanggal);
            
            // Hardcoded prayer times for Bandung
            // TODO: Replace with Aladhan API in the future
            $sortOrder = match($adzan->waktu_sholat) {
                'fajr' => 0,
                'dhuhr' => 2,
                'asr' => 4,
                'maghrib' => 5,
                'isha' => 7,
                default => 99,
            };
            
            // Set prayer times
            match($adzan->waktu_sholat) {
                'fajr' => $time->setTime(4, 30),
                'dhuhr' => $time->setTime(12, 0),    // 12:00 - 12:10
                'asr' => $time->setTime(15, 20),     // 15:20 - 15:30
                'maghrib' => $time->setTime(18, 0),
                'isha' => $time->setTime(19, 15),
                default => $time->setTime(12, 0),
            };

            $timeline[] = [
                'type' => 'adzan',
                'label' => 'Adzan ' . match($adzan->waktu_sholat) {
                    'fajr' => 'Subuh',
                    'dhuhr' => 'Zuhur',
                    'asr' => 'Ashar',
                    'maghrib' => 'Maghrib',
                    'isha' => 'Isya',
                    default => ucfirst($adzan->waktu_sholat),
                },
                'personil' => $adzan->personil->nama ?? 'Tidak ada',
                'time' => $time,
                'sort_order' => $sortOrder,
                'icon' => 'speaker-wave',
            ];
        }

        // Get all kajian hari ini (if exists in your system)
        // You can add similar logic for kajian here

        // Sort by sort_order
        usort($timeline, fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);

        return $timeline;
    }

    /** Notifikasi belum dibaca */
    #[Computed]
    public function notifikasiBelumDibaca()
    {
        return Notifikasi::where('user_id', auth()->id())
            ->where('dibaca', false)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();
    }

    #[Computed]
    public function jumlahNotifikasiBelumDibaca(): int
    {
        return Notifikasi::where('user_id', auth()->id())
            ->where('dibaca', false)
            ->count();
    }

    /**
     * FASE 3.2: Berhalangan stats per personil untuk bulan ini.
     * Returns array: [personil_id => ['nama' => ..., 'count' => ..., 'max' => 2]]
     */
    /**
     * PHASE 3 - PART 2: Berhalangan stats per TIM (bukan per personil).
     * Returns: ['adzan' => [...], 'briefing' => [...]]
     */
    #[Computed]
    public function berhalanganStatsPerTim(): array
    {
        if (! $this->timId) {
            return [
                'adzan' => ['count' => 0, 'max' => 2, 'is_full' => false],
                'briefing' => ['count' => 0, 'max' => 2, 'is_full' => false],
            ];
        }
        
        $year = now()->year;
        $month = now()->month;
        
        // Count DISTINCT tanggal untuk adzan berhalangan
        $countAdzan = JadwalAdzanKitab::whereHas('personil', fn($q) => $q->where('tim_id', $this->timId))
            ->where('status_konfirmasi', 'berhalangan')
            ->whereYear('tanggal', $year)
            ->whereMonth('tanggal', $month)
            ->distinct('tanggal')
            ->count('tanggal');
        
        // Count DISTINCT tanggal untuk briefing berhalangan
        $countBriefing = JadwalBriefing::where('tim_id', $this->timId)
            ->where('status_konfirmasi', 'berhalangan')
            ->whereYear('tanggal', $year)
            ->whereMonth('tanggal', $month)
            ->distinct('tanggal')
            ->count('tanggal');
        
        return [
            'adzan' => [
                'count' => $countAdzan,
                'max' => 2,
                'is_full' => $countAdzan >= 2,
            ],
            'briefing' => [
                'count' => $countBriefing,
                'max' => 2,
                'is_full' => $countBriefing >= 2,
            ],
        ];
    }
}; ?>

<div class="space-y-4 w-full max-w-5xl">
    {{-- Greeting --}}
    <div class="mb-6">
        <flux:heading size="xl" class="!text-2xl font-bold">Halo, {{ $this->tim?->nama_tim ?? auth()->user()->name }}</flux:heading>
        <flux:text class="text-zinc-500 dark:text-zinc-400">{{ now()->locale('id')->isoFormat('dddd, D MMMM YYYY') }}</flux:text>
    </div>

    @if (! $this->timId)
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>Akun belum terhubung ke tim</flux:callout.heading>
            <flux:callout.text>Hubungi admin untuk menghubungkan akun ke data tim.</flux:callout.text>
        </flux:callout>
    @else
        {{-- Card 1: Status WFO Hari Ini --}}
        <flux:card class="!rounded-2xl">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center {{ $this->isWfoHariIni ? 'bg-emerald-100 dark:bg-emerald-900/30' : 'bg-zinc-100 dark:bg-zinc-800' }}">
                        <flux:icon.building-office-2 class="w-6 h-6 {{ $this->isWfoHariIni ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-400' }}" />
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <flux:heading size="lg" class="font-semibold">Status WFO Hari Ini</flux:heading>
                    
                    @if ($this->isWfoHariIni)
                        <div class="mt-2 space-y-2">
                            <flux:badge size="sm" variant="outline" class="!bg-emerald-50 !text-emerald-700 !border-emerald-200 dark:!bg-emerald-900/20 dark:!text-emerald-300 dark:!border-emerald-800">
                                ✓ WFO
                            </flux:badge>
                            
                            @if ($this->alokasiHariIni)
                                <div class="flex items-center gap-2 text-sm">
                                    <flux:icon.map-pin class="w-4 h-4 text-zinc-400" />
                                    <flux:text>{{ $this->alokasiHariIni->ruangan->nama_ruangan ?? 'Belum dialokasikan' }}</flux:text>
                                </div>
                            @else
                                <flux:text class="text-sm text-amber-600 dark:text-amber-400">
                                    Ruangan belum dialokasikan. Hubungi admin.
                                </flux:text>
                            @endif
                        </div>
                    @else
                        <div class="mt-2">
                            <flux:badge size="sm" variant="outline" class="!bg-zinc-50 !text-zinc-600 !border-zinc-200 dark:!bg-zinc-800 dark:!text-zinc-400 dark:!border-zinc-700">
                                Tidak WFO
                            </flux:badge>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400 mt-2">
                                Tim tidak dijadwalkan WFO hari ini.
                            </flux:text>
                        </div>
                    @endif
                </div>
            </div>
        </flux:card>

        {{-- Card 2: Jadwal Terdekat --}}
        @if ($this->jadwalTerdekat)
            @php
                $jadwal = $this->jadwalTerdekat;
                $tanggal = \Carbon\Carbon::parse($jadwal['tanggal']);
                
                // Determine badge for header (not for individual items)
                // Header badge shows overall status, not "SEDANG BERLANGSUNG"
                if ($jadwal['is_today']) {
                    // Hari ini (belum mulai atau sedang berlangsung - tapi badge tetap HARI INI)
                    $jamBadge = '';
                    if ($jadwal['tipe'] === 'briefing') {
                        $sesi = strtolower(str_replace('Briefing ', '', $jadwal['label']));
                        $jamBadge = $sesi === 'pagi' ? '09.00' : '17.00';
                    }
                    $badgeLabel = 'HARI INI' . ($jamBadge ? ' • ' . $jamBadge : '');
                    $badgeColor = 'blue';
                    $bgColor = 'bg-[#3B71CA]/5 dark:bg-[#3B71CA]/10';
                    $borderColor = 'border-l-[#3B71CA]';
                    $iconColor = 'text-[#3B71CA] dark:text-[#3B71CA]';
                    $headerBgColor = 'bg-[#3B71CA]/10 dark:bg-[#3B71CA]/20';
                    $headerIconColor = 'text-[#3B71CA] dark:text-[#3B71CA]';
                } else {
                    // Besok atau hari lain
                    $jamBadge = '';
                    if ($jadwal['tipe'] === 'briefing') {
                        $sesi = strtolower(str_replace('Briefing ', '', $jadwal['label']));
                        $jamBadge = $sesi === 'pagi' ? '09.00' : '17.00';
                    }
                    
                    // Determine label (BESOK, LUSA, atau tanggal)
                    $currentTime = \App\Helpers\DevModeHelper::now();
                    $daysDiff = $currentTime->startOfDay()->diffInDays($tanggal->copy()->startOfDay(), false);
                    
                    if ($daysDiff === 1) {
                        $badgeLabel = 'BESOK' . ($jamBadge ? ' • ' . $jamBadge : '');
                    } elseif ($daysDiff === 2) {
                        $badgeLabel = 'LUSA' . ($jamBadge ? ' • ' . $jamBadge : '');
                    } else {
                        $badgeLabel = strtoupper($tanggal->translatedFormat('D, d M')) . ($jamBadge ? ' • ' . $jamBadge : '');
                    }
                    
                    $badgeColor = 'amber';
                    $bgColor = 'bg-amber-50 dark:bg-amber-950/30';
                    $borderColor = 'border-l-amber-500';
                    $iconColor = 'text-amber-600 dark:text-amber-400';
                    $headerBgColor = 'bg-amber-100 dark:bg-amber-900/30';
                    $headerIconColor = 'text-amber-600 dark:text-amber-400';
                }
            @endphp

            <flux:card class="!rounded-2xl {{ $bgColor }} border-l-4 {{ $borderColor }}">
                {{-- Card Header - Same style as other cards --}}
                <div class="flex items-start gap-4 mb-4">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center {{ $headerBgColor }}">
                            <flux:icon.calendar-days class="w-6 h-6 {{ $headerIconColor }}" />
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <flux:heading size="lg" class="font-semibold">Jadwal Terdekat</flux:heading>
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                            {{ $tanggal->translatedFormat('l, d F Y') }}
                        </flux:text>
                    </div>
                </div>

                {{-- Timeline jadwal hari ini --}}
                @if ($jadwal['is_today'] && count($this->jadwalHariIniTimeline) > 0)
                    @php
                        $currentTime = \App\Helpers\DevModeHelper::now();
                        
                        // Find current and next items
                        $currentItemIndex = null;
                        $nextItemIndex = null;
                        
                        foreach ($this->jadwalHariIniTimeline as $index => $item) {
                            $itemTime = $item['time'];
                            $isHappening = $currentTime->between($itemTime, $itemTime->copy()->addMinutes(10));
                            $isPassed = $currentTime->greaterThan($itemTime->copy()->addMinutes(10));
                            
                            if ($isHappening) {
                                $currentItemIndex = $index;
                                // Next item is the one after current
                                if (isset($this->jadwalHariIniTimeline[$index + 1])) {
                                    $nextItemIndex = $index + 1;
                                }
                                break;
                            } elseif (!$isPassed && $currentItemIndex === null) {
                                // This is upcoming and no current item, so this is "next"
                                $nextItemIndex = $index;
                            }
                        }
                    @endphp
                    
                    <flux:timeline class="[--flux-timeline-item-gap:1rem]" align="center">
                        @foreach ($this->jadwalHariIniTimeline as $index => $item)
                            @php
                                $itemTime = $item['time'];
                                $isHappening = $currentTime->between($itemTime, $itemTime->copy()->addMinutes(10));
                                $isPassed = $currentTime->greaterThan($itemTime->copy()->addMinutes(10));
                                
                                if ($isPassed) {
                                    $status = 'complete';
                                } elseif ($isHappening) {
                                    $status = 'current';
                                } else {
                                    $status = 'incomplete';
                                }
                                
                                // Show badge logic
                                $showBadge = false;
                                $badgeLabel = '';
                                $badgeColor = 'blue';
                                
                                if ($index === $currentItemIndex) {
                                    // Current item: show "SEDANG BERLANGSUNG"
                                    $showBadge = true;
                                    $badgeLabel = 'SEDANG BERLANGSUNG';
                                    $badgeColor = 'green';
                                } elseif ($index === $nextItemIndex) {
                                    // Next item: show "HARI INI • XX.XX"
                                    $showBadge = true;
                                    $jamStr = $itemTime->format('H.i');
                                    $badgeLabel = 'HARI INI • ' . $jamStr;
                                    $badgeColor = 'blue';
                                }
                                
                                // Time difference text
                                $timeDiff = \App\Helpers\DevModeHelper::diffForHumans($itemTime, true);
                            @endphp
                            
                            <flux:timeline.item :status="$status">
                                <flux:timeline.indicator :color="$isPassed ? 'green' : null">
                                    @if ($isPassed)
                                        <flux:icon icon="check" variant="micro" />
                                    @else
                                        <flux:icon :icon="$item['icon']" variant="micro" />
                                    @endif
                                </flux:timeline.indicator>
                                <flux:timeline.content>
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <flux:heading size="sm">{{ $item['label'] }}</flux:heading>
                                                @if ($showBadge)
                                                    <flux:badge size="sm" :color="$badgeColor" class="font-semibold">
                                                        {{ $badgeLabel }}
                                                    </flux:badge>
                                                @endif
                                            </div>
                                            <flux:text class="text-xs">
                                                Penanggung jawab: {{ $item['personil'] }}
                                            </flux:text>
                                        </div>
                                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 flex-shrink-0">
                                            {{ $timeDiff }}
                                        </flux:text>
                                    </div>
                                </flux:timeline.content>
                            </flux:timeline.item>
                        @endforeach
                    </flux:timeline>
                @else
                    {{-- Fallback: Single item display untuk besok/lusa --}}
                    <div class="space-y-3">
                        <flux:badge size="sm" color="{{ $badgeColor }}" class="font-semibold">
                            {{ $badgeLabel }}
                        </flux:badge>
                        
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0">
                                <flux:icon :icon="$jadwal['icon']" class="w-5 h-5 {{ $iconColor }}" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <flux:text class="font-medium text-sm">{{ $jadwal['label'] }}</flux:text>
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                    Penanggung jawab: {{ $jadwal['personil'] }}
                                </flux:text>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button href="{{ route('tim.jadwal') }}" wire:navigate variant="ghost" size="sm" class="w-full">
                        Lihat Semua Jadwal
                        <flux:icon.arrow-right class="w-4 h-4 ml-1" />
                    </flux:button>
                </div>
            </flux:card>
        @else
            <flux:card class="!rounded-2xl">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 rounded-xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center">
                            <flux:icon.calendar-days class="w-6 h-6 text-zinc-400" />
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <flux:heading size="lg" class="font-semibold">Jadwal Terdekat</flux:heading>
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400 mt-2">
                            Tidak ada jadwal terdekat.
                        </flux:text>
                    </div>
                </div>
            </flux:card>
        @endif

        {{-- Card 3: Kuota Berhalangan Tim (PHASE 3) --}}
        <flux:card class="!rounded-2xl">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-orange-100 dark:bg-orange-900/30">
                        <flux:icon icon="exclamation-circle" class="w-6 h-6 text-orange-600 dark:text-orange-400" />
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <flux:heading size="lg" class="font-semibold">Kuota Berhalangan Tim</flux:heading>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                        {{ now()->translatedFormat('F Y') }} • Maksimal 2x per jenis per bulan
                    </flux:text>

                    <div class="mt-4 space-y-3">
                        @php $stats = $this->berhalanganStatsPerTim; @endphp
                        
                        {{-- Adzan/Kitab --}}
                        <div class="flex items-center justify-between p-3 rounded-lg {{ $stats['adzan']['is_full'] ? 'bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800' : 'bg-zinc-50 dark:bg-zinc-800' }}">
                            <div class="flex items-center gap-2">
                                <flux:icon icon="speaker-wave" class="size-5 flex-shrink-0 {{ $stats['adzan']['is_full'] ? 'text-red-600 dark:text-red-400' : 'text-zinc-500' }}" />
                                <span class="font-medium text-sm">Adzan/Kitab</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:badge 
                                    size="sm" 
                                    :color="$stats['adzan']['is_full'] ? 'red' : ($stats['adzan']['count'] > 0 ? 'amber' : 'zinc')"
                                    class="font-mono"
                                >
                                    {{ $stats['adzan']['count'] }}/{{ $stats['adzan']['max'] }}
                                </flux:badge>
                                @if ($stats['adzan']['is_full'])
                                    <flux:icon icon="lock-closed" class="size-4 text-red-600 dark:text-red-400" />
                                @endif
                            </div>
                        </div>
                        
                        {{-- Briefing (Notulensi/Moderator/Doa) --}}
                        <div class="flex items-center justify-between p-3 rounded-lg {{ $stats['briefing']['is_full'] ? 'bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800' : 'bg-zinc-50 dark:bg-zinc-800' }}">
                            <div class="flex items-center gap-2">
                                <flux:icon icon="users" class="size-5 flex-shrink-0 {{ $stats['briefing']['is_full'] ? 'text-red-600 dark:text-red-400' : 'text-zinc-500' }}" />
                                <div class="flex-1">
                                    <span class="font-medium text-sm block">Briefing</span>
                                    <span class="text-xs text-zinc-500">Notulensi/Moderator/Doa</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:badge 
                                    size="sm" 
                                    :color="$stats['briefing']['is_full'] ? 'red' : ($stats['briefing']['count'] > 0 ? 'amber' : 'zinc')"
                                    class="font-mono"
                                >
                                    {{ $stats['briefing']['count'] }}/{{ $stats['briefing']['max'] }}
                                </flux:badge>
                                @if ($stats['briefing']['is_full'])
                                    <flux:icon icon="lock-closed" class="size-4 text-red-600 dark:text-red-400" />
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    {{-- Info text --}}
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 mt-3 flex items-start gap-1.5">
                        <flux:icon.light-bulb class="w-4 h-4 text-amber-500 dark:text-amber-400 flex-shrink-0 mt-0.5" />
                        <span>Catatan: Berhalangan pagi + sore di hari yang sama dihitung 1x quota.</span>
                    </flux:text>
                </div>
            </div>
        </flux:card>

        {{-- Card 4: Shortcut Notulen --}}
        <flux:card class="!rounded-2xl">
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-3 flex-1">
                    <div class="w-12 h-12 rounded-xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center flex-shrink-0">
                        <flux:icon.clipboard-document-list class="w-6 h-6 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div class="flex-1">
                        <flux:heading size="lg" class="font-semibold">Notulen Briefing</flux:heading>
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                            Kelola notulen briefing tim
                        </flux:text>
                    </div>
                </div>
                <flux:icon.chevron-right class="w-5 h-5 text-zinc-400 dark:text-zinc-500 flex-shrink-0" />
            </div>

            <div class="space-y-2">
                <flux:button href="{{ route('tim.notulen.create') }}" wire:navigate variant="primary" size="sm" class="w-full" icon="plus">
                    Buat Notulen Baru
                </flux:button>
                <flux:button href="{{ route('tim.notulen.history') }}" wire:navigate variant="ghost" size="sm" class="w-full" icon="clock">
                    Lihat Riwayat Notulen
                </flux:button>
            </div>
        </flux:card>

        {{-- Card 5: Notifikasi --}}
        @if ($this->jumlahNotifikasiBelumDibaca > 0)
            <flux:card class="!rounded-2xl">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0 relative">
                            <flux:icon.bell class="w-6 h-6 text-red-600 dark:text-red-400" />
                            @if ($this->jumlahNotifikasiBelumDibaca > 0)
                                <div class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 rounded-full flex items-center justify-center">
                                    <span class="text-xs font-bold text-white">{{ $this->jumlahNotifikasiBelumDibaca }}</span>
                                </div>
                            @endif
                        </div>
                        <div>
                            <flux:heading size="lg" class="font-semibold">Notifikasi</flux:heading>
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $this->jumlahNotifikasiBelumDibaca }} belum dibaca</flux:text>
                        </div>
                    </div>
                </div>

                <div class="space-y-2">
                    @foreach ($this->notifikasiBelumDibaca as $notif)
                        <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700">
                            <flux:text class="text-sm font-medium">{{ $notif->judul }}</flux:text>
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                                {{ $notif->created_at->diffForHumans() }}
                            </flux:text>
                        </div>
                    @endforeach
                </div>
            </flux:card>
        @endif
    @endif
</div>
