<?php

use App\Models\AlokasiRuangan;
use App\Models\JadwalWfo;
use App\Models\PeriodeWfo;
use App\Models\Ruangan;
use App\Models\Tim;
use App\Services\LraScheduler;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Alokasi Ruangan')] #[Layout('layouts.admin')] class extends Component {

    public string $tanggalMulaiMinggu = '';
    public ?int $periodeId = null;
    public array $alokasiRowsCache = [];
    
    // Modal confirmations
    public bool $showBestFitConfirmModal = false;
    public bool $showFairConfirmModal = false;
    public bool $isGenerating = false;

    public function mount(): void
    {
        $aktif = PeriodeWfo::where('status', 'aktif')->first();
        $this->periodeId = $aktif?->id;

        if ($aktif && $aktif->tanggal_mulai) {
            $this->tanggalMulaiMinggu = Carbon::parse($aktif->tanggal_mulai)->startOfWeek(Carbon::MONDAY)->toDateString();
        } else {
            $this->tanggalMulaiMinggu = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
        }
        
        // Cleanup orphaned allocations (tim yang sudah dihapus & tim tidak WFO)
        $this->cleanupOrphanedAllocations();
        $this->syncWithJadwalWfo();
        
        $this->refreshAlokasiCache();
    }

    /**
     * Cleanup alokasi yang timnya sudah dihapus
     */
    private function cleanupOrphanedAllocations(): void
    {
        $deleted = AlokasiRuangan::whereDoesntHave('tim')->delete();
        
        if ($deleted > 0) {
            \Log::info("Cleaned up {$deleted} orphaned room allocations");
        }
    }

    public function refreshAlokasiCache(): void
    {
        unset($this->alokasiRows);
        
        $start = Carbon::parse($this->tanggalMulaiMinggu);
        $end = $start->copy()->addDays(4);

        $this->alokasiRowsCache = AlokasiRuangan::with(['tim.personil', 'ruangan'])
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->filter(fn ($a) => $a->tim !== null) // Filter out deleted teams
            ->map(fn ($a) => [
                'id' => $a->id,
                'tim_id' => $a->tim_id,
                'nama_tim' => $a->tim->nama_tim,
                'personil_count' => $a->tim->personil?->count() ?? 0,
                'ruangan_id' => $a->ruangan_id,
                'tanggal' => $a->tanggal->toDateString(),
                'expected_attendance' => $a->expected_attendance ?? 0,
                'kapasitas' => $a->ruangan?->kapasitas ?? 0,
                'utilization' => $a->expected_attendance > 0 ? $a->utilizationPercentage() : 0,
                'is_over_capacity' => $a->isOverCapacity(),
                'is_underutilized' => $a->isUnderutilized(),
                'color_classes' => $this->getTimColorClasses($a->tim_id),
            ])
            ->values() // Re-index array after filter
            ->toArray();
            
        $this->dispatch('alokasiRowsUpdated', $this->alokasiRowsCache);
    }

    /**
     * Get color classes for tim chip - warna sangat berbeda
     * SAMA seperti Jadwal WFO untuk konsistensi
     */
    public function getTimColorClasses(int $timId): string
    {
        $colors = [
            'bg-[#3B71CA] dark:bg-[#3B71CA] text-white border-[#2d5db3] dark:border-[#2d5db3]',
            'bg-red-500 dark:bg-red-600 text-white border-red-600 dark:border-red-700',
            'bg-green-500 dark:bg-green-600 text-white border-green-600 dark:border-green-700',
            'bg-amber-400 dark:bg-amber-500 text-zinc-900 dark:text-zinc-900 border-amber-500 dark:border-amber-600',
            'bg-purple-500 dark:bg-purple-600 text-white border-purple-600 dark:border-purple-700',
            'bg-stone-600 dark:bg-stone-700 text-white border-stone-700 dark:border-stone-800',
            'bg-pink-500 dark:bg-pink-600 text-white border-pink-600 dark:border-pink-700',
            'bg-slate-700 dark:bg-slate-800 text-white border-slate-800 dark:border-slate-900',
            'bg-orange-500 dark:bg-orange-600 text-white border-orange-600 dark:border-orange-700',
            'bg-emerald-500 dark:bg-emerald-600 text-white border-emerald-600 dark:border-emerald-700',
        ];
        
        $index = ($timId - 1) % count($colors);
        return $colors[$index];
    }

    public function badgeTim(?\App\Models\Tim $tim): string
    {
        if (!$tim) {
            return 'Tim Tidak Diketahui';
        }
        $index = ($tim->id - 1) % 10;
        return "[[TIM:{$tim->nama_tim}:{$index}]]";
    }

    #[Computed]
    public function periodeAktif(): ?PeriodeWfo
    {
        return $this->periodeId ? PeriodeWfo::find($this->periodeId) : PeriodeWfo::where('status', 'aktif')->first();
    }

    #[Computed]
    public function daftarPeriode()
    {
        return PeriodeWfo::orderByDesc('tanggal_mulai')->get();
    }

    #[Computed]
    public function daftarHariMingguIni(): array
    {
        $start = Carbon::parse($this->tanggalMulaiMinggu);
        $hariList = [];
        $namaHariIndo = ['senin', 'selasa', 'rabu', 'kamis', 'jumat'];

        for ($i = 0; $i < 5; $i++) {
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
        return Tim::where('status', 'active')
            ->whereNull('deleted_at')
            ->withCount('personil')
            ->orderBy('nama_tim')
            ->get();
    }

    /**
     * Data alokasi untuk minggu yang dipilih:
     * [{ id, tim_id, nama_tim, personil_count, ruangan_id, tanggal, hari, expected_attendance, kapasitas, utilization }, ...]
     * 
     * FASE 4.4: Added capacity utilization info
     */
    #[Computed]
    public function alokasiRows(): array
    {
        $start = Carbon::parse($this->tanggalMulaiMinggu);
        $end = $start->copy()->addDays(4);

        return AlokasiRuangan::with(['tim.personil', 'ruangan'])
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->filter(fn ($a) => $a->tim !== null) // Filter out deleted teams
            ->map(fn ($a) => [
                'id' => $a->id,
                'tim_id' => $a->tim_id,
                'nama_tim' => $a->tim->nama_tim,
                'personil_count' => $a->tim->personil?->count() ?? 0,
                'ruangan_id' => $a->ruangan_id,
                'tanggal' => $a->tanggal->toDateString(),
                // FASE 4.4: Capacity info
                'expected_attendance' => $a->expected_attendance ?? 0,
                'kapasitas' => $a->ruangan?->kapasitas ?? 0,
                'utilization' => $a->expected_attendance > 0 ? $a->utilizationPercentage() : 0,
                'is_over_capacity' => $a->isOverCapacity(),
                'is_underutilized' => $a->isUnderutilized(),
            ])
            ->values() // Re-index after filter
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
            ->whereHas('tim', fn ($q) => $q->where('status', 'active')->whereNull('deleted_at'))
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

    public function pindahRuangan(int $alokasiId, int $targetRuanganId, string $targetTanggal, ?int $targetAlokasiId = null): void
    {
        $alokasi = AlokasiRuangan::with('tim')->find($alokasiId);
        if (! $alokasi) {
            Flux::toast(variant: 'danger', text: 'Data alokasi tidak ditemukan.');
            $this->dispatch('alokasiActionFeedback', success: false, targetRuanganId: $targetRuanganId, targetTanggal: $targetTanggal);
            $this->refreshAlokasiCache();
            return;
        }

        $oldRuanganId = $alokasi->ruangan_id;
        $oldTanggal = $alokasi->tanggal->toDateString();
        $targetTanggal = Carbon::parse($targetTanggal)->toDateString();

        // 1. Jika ruangan dan tanggal sama persis, tidak ada perubahan
        if ($oldRuanganId === $targetRuanganId && $oldTanggal === $targetTanggal) {
            return;
        }

        $namaHariIndo = ['senin', 'selasa', 'rabu', 'kamis', 'jumat'];

        // 2. Validasi Jadwal WFO untuk hari target
        $targetDayOfWeek = Carbon::parse($targetTanggal)->dayOfWeekIso;
        $targetHariKode = $namaHariIndo[$targetDayOfWeek - 1] ?? null;

        if ($this->periodeAktif && $targetHariKode) {
            $isWfo = JadwalWfo::where('periode_wfo_id', $this->periodeAktif->id)
                ->where('tim_id', $alokasi->tim_id)
                ->where('hari', $targetHariKode)
                ->exists();

            if (! $isWfo) {
                Flux::toast(variant: 'danger', text: "Tim " . $this->badgeTim($alokasi->tim) . " tidak memiliki jadwal WFO pada hari ".ucfirst($targetHariKode).'.');
                $this->dispatch('alokasiActionFeedback', success: false, targetRuanganId: $targetRuanganId, targetTanggal: $targetTanggal, oldRuanganId: $oldRuanganId, oldTanggal: $oldTanggal);
                $this->refreshAlokasiCache();
                return;
            }
        }

        // 3. Cari tim yang akan diswap (jika ada targetAlokasiId)
        $bentrok = null;
        if ($targetAlokasiId) {
            $bentrok = AlokasiRuangan::with('tim')->find($targetAlokasiId);
        }

        // Jika ruangan target sudah berisi tim yang sama
        if ($bentrok && $bentrok->tim_id === $alokasi->tim_id) {
            Flux::toast(variant: 'warning', text: "Tim " . $this->badgeTim($alokasi->tim) . " sudah berada di ruangan tersebut.");
            $this->dispatch('alokasiActionFeedback', success: false, targetRuanganId: $targetRuanganId, targetTanggal: $targetTanggal, oldRuanganId: $oldRuanganId, oldTanggal: $oldTanggal);
            return;
        }

        // 4. Jika pindah hari (bukan hanya pindah ruangan di hari yang sama):
        if ($oldTanggal !== $targetTanggal) {
            // Cek apakah tim asal sudah punya alokasi ruangan lain di tanggal target
            $timSudahAdaDiTargetTanggal = AlokasiRuangan::where('tim_id', $alokasi->tim_id)
                ->whereIn('tanggal', $this->expandDateQueryFormats([$targetTanggal]))
                ->where('id', '!=', $alokasiId)
                ->exists();

            if ($timSudahAdaDiTargetTanggal) {
                Flux::toast(variant: 'warning', text: "Tim " . $this->badgeTim($alokasi->tim) . " sudah dialokasikan di ruangan lain pada tanggal {$targetTanggal}.");
                $this->dispatch('alokasiActionFeedback', success: false, targetRuanganId: $targetRuanganId, targetTanggal: $targetTanggal, oldRuanganId: $oldRuanganId, oldTanggal: $oldTanggal);
                $this->refreshAlokasiCache();
                return;
            }

            // Jika ada tim bentrok di tanggal target dan kita swap:
            if ($bentrok) {
                $oldDayOfWeek = Carbon::parse($oldTanggal)->dayOfWeekIso;
                $oldHariKode = $namaHariIndo[$oldDayOfWeek - 1] ?? null;

                if ($this->periodeAktif && $oldHariKode) {
                    $bentrokIsWfo = JadwalWfo::where('periode_wfo_id', $this->periodeAktif->id)
                        ->where('tim_id', $bentrok->tim_id)
                        ->where('hari', $oldHariKode)
                        ->exists();

                    if (! $bentrokIsWfo) {
                        Flux::toast(variant: 'danger', text: "Tim " . $this->badgeTim($bentrok->tim) . " tidak memiliki jadwal WFO pada hari ".ucfirst($oldHariKode).' untuk ditukar.');
                        $this->dispatch('alokasiActionFeedback', success: false, targetRuanganId: $targetRuanganId, targetTanggal: $targetTanggal, oldRuanganId: $oldRuanganId, oldTanggal: $oldTanggal);
                        $this->refreshAlokasiCache();
                        return;
                    }
                }

                $bentrokSudahAdaDiOldTanggal = AlokasiRuangan::where('tim_id', $bentrok->tim_id)
                    ->whereIn('tanggal', $this->expandDateQueryFormats([$oldTanggal]))
                    ->where('id', '!=', $bentrok->id)
                    ->exists();

                if ($bentrokSudahAdaDiOldTanggal) {
                    Flux::toast(variant: 'warning', text: "Tim " . $this->badgeTim($bentrok->tim) . " sudah dialokasikan di ruangan lain pada tanggal asal ({$oldTanggal}).");
                    $this->dispatch('alokasiActionFeedback', success: false, targetRuanganId: $targetRuanganId, targetTanggal: $targetTanggal, oldRuanganId: $oldRuanganId, oldTanggal: $oldTanggal);
                    $this->refreshAlokasiCache();
                    return;
                }
            }
        }

        try {
            DB::transaction(function () use ($alokasi, $bentrok, $targetRuanganId, $targetTanggal, $oldRuanganId, $oldTanggal) {
                $timIdA = $alokasi->tim_id;
                $attendanceA = $alokasi->expected_attendance;

                // Jika ada periode aktif dan perpindahan terjadi pada hari yang sama (misal Senin Ruang A ⇄ Senin Ruang B sepanjang periode):
                if ($this->periodeAktif && $this->periodeAktif->tanggal_mulai && $this->periodeAktif->tanggal_selesai && $oldTanggal === $targetTanggal) {
                    $dayOfWeek = Carbon::parse($targetTanggal)->dayOfWeekIso;
                    $allDates = $this->getDatesForDayOfWeek($this->periodeAktif, $dayOfWeek);
                    $expandedDates = $this->expandDateQueryFormats($allDates);

                    if ($bentrok) {
                        $timIdB = $bentrok->tim_id;
                        $attendanceB = $bentrok->expected_attendance;

                        // SWAP DUA TIM SEPANJANG PERIODE:
                        // Hapus alokasi kedua tim di tanggal-tanggal periode ini untuk mengosongkan kedua ruangan
                        AlokasiRuangan::whereIn('tim_id', [$timIdA, $timIdB])
                            ->whereIn('tanggal', $expandedDates)
                            ->delete();

                        // Masukkan kembali dengan ruangan yang sudah bertukar
                        foreach ($allDates as $d) {
                            AlokasiRuangan::create([
                                'tim_id' => $timIdA,
                                'ruangan_id' => $targetRuanganId,
                                'tanggal' => $d,
                                'expected_attendance' => $attendanceA,
                            ]);
                            AlokasiRuangan::create([
                                'tim_id' => $timIdB,
                                'ruangan_id' => $oldRuanganId,
                                'tanggal' => $d,
                                'expected_attendance' => $attendanceB,
                            ]);
                        }
                    } else {
                        // PINDAH KE RUANGAN (JADI TAMBAHAN TIM) SEPANJANG PERIODE
                        AlokasiRuangan::where('tim_id', $timIdA)
                            ->whereIn('tanggal', $expandedDates)
                            ->delete();

                        foreach ($allDates as $d) {
                            AlokasiRuangan::create([
                                'tim_id' => $timIdA,
                                'ruangan_id' => $targetRuanganId,
                                'tanggal' => $d,
                                'expected_attendance' => $attendanceA,
                            ]);
                        }
                    }
                } else {
                    // Single date swap/move atau beda tanggal
                    if ($bentrok) {
                        $timIdB = $bentrok->tim_id;
                        $attendanceB = $bentrok->expected_attendance;

                        $alokasi->delete();
                        $bentrok->delete();

                        AlokasiRuangan::create([
                            'tim_id' => $timIdA,
                            'ruangan_id' => $targetRuanganId,
                            'tanggal' => $targetTanggal,
                            'expected_attendance' => $attendanceA,
                        ]);

                        AlokasiRuangan::create([
                            'tim_id' => $timIdB,
                            'ruangan_id' => $oldRuanganId,
                            'tanggal' => $oldTanggal,
                            'expected_attendance' => $attendanceB,
                        ]);
                    } else {
                        $alokasi->delete();

                        AlokasiRuangan::create([
                            'tim_id' => $timIdA,
                            'ruangan_id' => $targetRuanganId,
                            'tanggal' => $targetTanggal,
                            'expected_attendance' => $attendanceA,
                        ]);
                    }
                }
            });

            $targetRuangan = Ruangan::find($targetRuanganId);
            if ($bentrok) {
                $oldRuangan = Ruangan::find($oldRuanganId);
                Flux::toast(
                    variant: 'success',
                    text: "Berhasil menukar: Tim " . $this->badgeTim($alokasi->tim) . " ({$targetRuangan?->nama_ruangan}) ⇄ Tim " . $this->badgeTim($bentrok->tim) . " ({$oldRuangan?->nama_ruangan})."
                );
            } else {
                Flux::toast(
                    variant: 'success',
                    text: "Tim " . $this->badgeTim($alokasi->tim) . " berhasil dialokasikan ke {$targetRuangan?->nama_ruangan}."
                );
            }

            $this->dispatch('alokasiActionFeedback', success: true, targetRuanganId: $targetRuanganId, targetTanggal: $targetTanggal, oldRuanganId: $oldRuanganId, oldTanggal: $oldTanggal);
        } catch (\Throwable $e) {
            \Log::error('Error pindahRuangan: '.$e->getMessage(), ['exception' => $e]);
            Flux::toast(variant: 'danger', text: 'Gagal memindahkan ruangan: '.$e->getMessage());
            $this->dispatch('alokasiActionFeedback', success: false, targetRuanganId: $targetRuanganId, targetTanggal: $targetTanggal, oldRuanganId: $oldRuanganId, oldTanggal: $oldTanggal);
        }

        $this->refreshAlokasiCache();
    }

    public function getDatesForDayOfWeek(PeriodeWfo $periode, int $dayOfWeekIso): array
    {
        $dates = [];
        $cur = Carbon::parse($periode->tanggal_mulai)->copy();
        $end = Carbon::parse($periode->tanggal_selesai)->copy();
        while ($cur->lte($end)) {
            if ($cur->dayOfWeekIso === $dayOfWeekIso) {
                $dates[] = $cur->toDateString();
            }
            $cur = $cur->copy()->addDay();
        }

        return $dates;
    }

    public function expandDateQueryFormats(array $dates): array
    {
        $expanded = [];
        foreach ($dates as $d) {
            $dStr = is_string($d) ? substr($d, 0, 10) : Carbon::parse($d)->toDateString();
            $expanded[] = $dStr;
            $expanded[] = $dStr.' 00:00:00';
        }

        return array_values(array_unique($expanded));
    }

    /**
     * Sinkronkan alokasi ruangan dengan Jadwal WFO:
     * Hapus alokasi tim di tanggal yang bukan hari WFO-nya dalam periode aktif.
     */
    public function syncWithJadwalWfo(): int
    {
        if (! $this->periodeAktif || ! $this->periodeAktif->tanggal_mulai || ! $this->periodeAktif->tanggal_selesai) {
            return 0;
        }

        // Ambil semua jadwal WFO untuk periode aktif: [tim_id => [hari1, hari2, ...]]
        $wfoMap = JadwalWfo::where('periode_wfo_id', $this->periodeAktif->id)
            ->get()
            ->groupBy('tim_id')
            ->map(fn ($rows) => $rows->pluck('hari')->toArray())
            ->toArray();

        $namaHariIndo = ['senin', 'selasa', 'rabu', 'kamis', 'jumat'];

        // Ambil semua alokasi dalam rentang periode
        $start = Carbon::parse($this->periodeAktif->tanggal_mulai)->startOfDay();
        $end = Carbon::parse($this->periodeAktif->tanggal_selesai)->endOfDay();

        $alokasiList = AlokasiRuangan::whereBetween('tanggal', [
            $start->toDateTimeString(),
            $end->toDateTimeString(),
        ])->get();

        $invalidIds = [];
        foreach ($alokasiList as $alokasi) {
            $dayOfWeek = $alokasi->tanggal->dayOfWeekIso; // 1..7
            if ($dayOfWeek > 6) {
                // Hari Minggu tidak ada WFO
                $invalidIds[] = $alokasi->id;
                continue;
            }

            $hariKode = $namaHariIndo[$dayOfWeek - 1] ?? null;
            $timWfoDays = $wfoMap[$alokasi->tim_id] ?? [];

            // Jika tim tidak terjadwal WFO pada hari ini
            if (! in_array($hariKode, $timWfoDays)) {
                $invalidIds[] = $alokasi->id;
            }
        }

        if (! empty($invalidIds)) {
            AlokasiRuangan::whereIn('id', $invalidIds)->delete();
        }

        return count($invalidIds);
    }

    public function sinkronkanJadwalWfo(): void
    {
        $cleaned = $this->syncWithJadwalWfo();
        $this->replicateWeek1ToPeriod();
        $this->refreshAlokasiCache();

        if ($cleaned > 0) {
            Flux::toast(variant: 'success', text: "Sinkronisasi selesai. {$cleaned} alokasi ruangan dibersihkan & pola 1 minggu diseragamkan sepanjang periode.");
        } else {
            Flux::toast(variant: 'success', text: 'Pola alokasi ruangan 1 minggu berhasil diseragamkan ke seluruh periode aktif.');
        }
    }

    /**
     * Replikasi pola alokasi Minggu ke-1 ke seluruh minggu dalam periode aktif
     */
    public function replicateWeek1ToPeriod(): void
    {
        if (! $this->periodeAktif || ! $this->periodeAktif->tanggal_mulai || ! $this->periodeAktif->tanggal_selesai) {
            return;
        }

        $scheduler = app(LraScheduler::class);
        $mulai = Carbon::parse($this->periodeAktif->tanggal_mulai);
        $selesai = Carbon::parse($this->periodeAktif->tanggal_selesai);
        $tanggalList = $scheduler->expandTanggal($mulai, $selesai);

        $startWeek1 = Carbon::parse($this->tanggalMulaiMinggu);
        $endWeek1 = $startWeek1->copy()->addDays(4);

        $week1Allocs = AlokasiRuangan::whereBetween('tanggal', [
            $startWeek1->toDateString(),
            $endWeek1->toDateString(),
        ])->get();

        $patternPerDay = [];
        foreach ($week1Allocs as $alloc) {
            $dow = $alloc->tanggal->dayOfWeekIso;
            $patternPerDay[$dow][] = [
                'tim_id' => $alloc->tim_id,
                'ruangan_id' => $alloc->ruangan_id,
                'expected_attendance' => $alloc->expected_attendance,
            ];
        }

        AlokasiRuangan::whereBetween('tanggal', [
            $mulai->toDateString(),
            $selesai->toDateString(),
        ])->delete();

        $rowsToInsert = [];
        foreach ($tanggalList as $tgl) {
            $dow = $tgl->dayOfWeekIso;
            if (! isset($patternPerDay[$dow])) {
                continue;
            }

            foreach ($patternPerDay[$dow] as $item) {
                $rowsToInsert[] = [
                    'tim_id' => $item['tim_id'],
                    'ruangan_id' => $item['ruangan_id'],
                    'tanggal' => $tgl->toDateString(),
                    'expected_attendance' => $item['expected_attendance'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (! empty($rowsToInsert)) {
            AlokasiRuangan::insert($rowsToInsert);
        }
    }

    public function tambahAlokasi(int $timId, int $ruanganId, string $tanggal): void
    {
        $dayOfWeek = Carbon::parse($tanggal)->dayOfWeekIso;
        $namaHariIndo = ['senin', 'selasa', 'rabu', 'kamis', 'jumat'];
        $hariKode = $namaHariIndo[$dayOfWeek - 1] ?? null;

        // Validasi: Apakah tim terjadwal WFO pada hari ini di periode aktif?
        if ($this->periodeAktif && $hariKode) {
            $isWfo = JadwalWfo::where('periode_wfo_id', $this->periodeAktif->id)
                ->where('tim_id', $timId)
                ->where('hari', $hariKode)
                ->exists();

            if (! $isWfo) {
                Flux::toast(variant: 'danger', text: 'Tim tidak memiliki jadwal WFO pada hari '.ucfirst($hariKode).'.');
                return;
            }
        }

        // Cek bentrok tim di tanggal sama
        $sudahAdaTim = AlokasiRuangan::where('tim_id', $timId)->whereIn('tanggal', $this->expandDateQueryFormats([$tanggal]))->first();
        if ($sudahAdaTim) {
            Flux::toast(variant: 'warning', text: 'Tim ini sudah memiliki ruangan pada tanggal tersebut.');
            return;
        }

        // Cek bentrok ruangan di tanggal sama dihilangkan untuk mengizinkan multiple tim per ruangan (sesuai kapasitas)

        $tim = Tim::withCount('personil')->find($timId);
        $ruangan = Ruangan::find($ruanganId);
        $expectedAttendance = $tim?->personil_count ?? 0;

        // Jika ada periode aktif, sinkronkan ke seluruh minggu dalam periode untuk hari yang sama
        if ($this->periodeAktif && $this->periodeAktif->tanggal_mulai && $this->periodeAktif->tanggal_selesai) {
            $allDates = $this->getDatesForDayOfWeek($this->periodeAktif, $dayOfWeek);

            foreach ($allDates as $tgl) {
                // Kita bisa cek apakah melebihi kapasitas dan memberi warning, tapi tetap masukkan
                // $occupied check dihilangkan agar bisa berbagi ruangan
                AlokasiRuangan::updateOrCreate(
                    ['tim_id' => $timId, 'tanggal' => $tgl],
                    ['ruangan_id' => $ruanganId, 'expected_attendance' => $expectedAttendance]
                );
            }

            Flux::toast(
                variant: 'success',
                text: "Alokasi berhasil: " . $this->badgeTim($tim) . " → {$ruangan?->nama_ruangan} (berlaku sepanjang periode)."
            );
        } else {
            AlokasiRuangan::create([
                'tim_id' => $timId,
                'ruangan_id' => $ruanganId,
                'tanggal' => $tanggal,
                'expected_attendance' => $expectedAttendance,
            ]);

            Flux::toast(
                variant: 'success',
                text: "Alokasi berhasil: " . $this->badgeTim($tim) . " → {$ruangan?->nama_ruangan}."
            );
        }

        $this->dispatch('alokasiActionFeedback', success: true, targetRuanganId: $ruanganId, targetTanggal: $tanggal);
        $this->refreshAlokasiCache();
    }

    public function hapusAlokasi(int $alokasiId): void
    {
        $alokasi = AlokasiRuangan::with('tim')->find($alokasiId);
        if ($alokasi) {
            $nama = $alokasi->tim?->nama_tim;
            $timId = $alokasi->tim_id;
            $dayOfWeek = $alokasi->tanggal->dayOfWeekIso;

            // Jika ada periode aktif, hapus alokasi ruangan tim ini untuk hari yang sama sepanjang periode
            if ($this->periodeAktif && $this->periodeAktif->tanggal_mulai && $this->periodeAktif->tanggal_selesai) {
                $allDates = $this->getDatesForDayOfWeek($this->periodeAktif, $dayOfWeek);
                AlokasiRuangan::where('tim_id', $timId)
                    ->whereIn('tanggal', $this->expandDateQueryFormats($allDates))
                    ->delete();

                Flux::toast(variant: 'success', text: "Alokasi ruangan tim " . $this->badgeTim($alokasi->tim) . " berhasil dihapus sepanjang periode.");
            } else {
                $alokasi->delete();
                Flux::toast(variant: 'success', text: "Alokasi ruangan tim " . $this->badgeTim($alokasi->tim) . " berhasil dihapus.");
            }
        }

        $this->refreshAlokasiCache();
    }

    public function hapusBulk(array $alokasiIds): void
    {
        if (empty($alokasiIds)) {
            return;
        }

        if ($this->periodeAktif && $this->periodeAktif->tanggal_mulai && $this->periodeAktif->tanggal_selesai) {
            $alokasis = AlokasiRuangan::whereIn('id', $alokasiIds)->get();
            foreach ($alokasis as $alokasi) {
                $dayOfWeek = $alokasi->tanggal->dayOfWeekIso;
                $allDates = $this->getDatesForDayOfWeek($this->periodeAktif, $dayOfWeek);
                AlokasiRuangan::where('tim_id', $alokasi->tim_id)
                    ->whereIn('tanggal', $this->expandDateQueryFormats($allDates))
                    ->delete();
            }
        } else {
            AlokasiRuangan::whereIn('id', $alokasiIds)->delete();
        }

        $this->refreshAlokasiCache();
    }

    public function hapusBulkWithToast(array $alokasiIds, int $count): void
    {
        $this->hapusBulk($alokasiIds);

        // Show success toast setelah selesai
        Flux::toast(
            variant: 'success',
            text: "Berhasil menghapus {$count} alokasi ruangan.",
            duration: 3000,
        );
    }

    /**
     * Generate alokasi dengan strategi FAIR (LRA - Least Recently Allocated)
     * Fokus: Distribusi adil penggunaan ruangan, setiap ruangan dipakai merata
     */
    public function generateFairAllocation(): void
    {
        $this->isGenerating = true;
        $this->showFairConfirmModal = false;
        
        if (! $this->periodeAktif) {
            $this->isGenerating = false;
            Flux::toast(variant: 'danger', text: 'Tidak ada periode WFO yang aktif.');
            return;
        }

        $scheduler = app(LraScheduler::class);
        $mulai = $this->periodeAktif->tanggal_mulai ? Carbon::parse($this->periodeAktif->tanggal_mulai) : Carbon::parse($this->tanggalMulaiMinggu);
        $selesai = $this->periodeAktif->tanggal_selesai ? Carbon::parse($this->periodeAktif->tanggal_selesai) : $mulai->copy()->addDays(27);
        $tanggalList = $scheduler->expandTanggal($mulai, $selesai);

        // Hapus alokasi lama untuk seluruh periode ini
        AlokasiRuangan::whereBetween('tanggal', [
            $mulai->toDateString(),
            $selesai->toDateString(),
        ])->delete();

        $hasil = $scheduler->generateAlokasiRuangan($tanggalList, $this->periodeAktif->id);

        if (empty($hasil)) {
            $this->isGenerating = false;
            Flux::toast(variant: 'warning', text: 'Tidak ada jadwal WFO tim yang ditemukan untuk dialokasikan.');
            return;
        }

        AlokasiRuangan::insert(array_map(fn ($r) => [
            'tim_id' => $r['tim_id'],
            'ruangan_id' => $r['ruangan_id'],
            'tanggal' => $r['tanggal'],
            'expected_attendance' => $r['expected_attendance'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $hasil));

        Flux::toast(
            variant: 'success',
            text: 'Berhasil generate alokasi ruangan (Fair/LRA) 1 minggu dan diulang ke seluruh periode.',
            heading: 'Generate Fair/LRA Selesai'
        );

        $this->refreshAlokasiCache();
        $this->isGenerating = false;
    }

    /**
     * Generate alokasi dengan strategi BEST FIT
     * Fokus: Maksimalkan utilisasi kapasitas ruangan, minimal waste space
     */
    public function generateBestFitAllocation(): void
    {
        $this->isGenerating = true;
        $this->showBestFitConfirmModal = false;
        
        if (! $this->periodeAktif) {
            $this->isGenerating = false;
            Flux::toast(variant: 'danger', text: 'Tidak ada periode WFO yang aktif.');
            return;
        }

        $start = Carbon::parse($this->tanggalMulaiMinggu);
        $week1Tanggal = [];
        for ($i = 0; $i < 5; $i++) {
            $week1Tanggal[] = $start->copy()->addDays($i);
        }

        $namaHariIndo = ['senin', 'selasa', 'rabu', 'kamis', 'jumat'];
        $timWfoPerHari = [];
        
        foreach ($week1Tanggal as $idx => $tgl) {
            $hari = $namaHariIndo[$idx];
            $tims = JadwalWfo::with('tim.personil')
                ->where('periode_wfo_id', $this->periodeAktif->id)
                ->where('hari', $hari)
                ->whereHas('tim', fn($q) => $q->where('status', 'active')->whereNull('deleted_at'))
                ->get()
                ->pluck('tim')
                ->filter()
                ->sortByDesc(fn($t) => $t->personil->count());
            
            $timWfoPerHari[$hari] = $tims;
        }

        $ruanganList = Ruangan::where('status', 'tersedia')
            ->orderBy('kapasitas', 'asc')
            ->get();

        if ($ruanganList->isEmpty()) {
            Flux::toast(variant: 'danger', text: 'Tidak ada ruangan tersedia.');
            $this->isGenerating = false;
            return;
        }

        $dayPatternMap = [];
        foreach ($timWfoPerHari as $hari => $tims) {
            $allocatedRuangan = [];
            foreach ($tims as $tim) {
                $teamSize = $tim->personil->count();
                $bestRoom = null;
                $minWaste = PHP_INT_MAX;
                
                foreach ($ruanganList as $ruangan) {
                    if (in_array($ruangan->id, $allocatedRuangan)) continue;
                    
                    if ($ruangan->kapasitas >= $teamSize) {
                        $waste = $ruangan->kapasitas - $teamSize;
                        if ($waste < $minWaste) {
                            $minWaste = $waste;
                            $bestRoom = $ruangan;
                        }
                    }
                }
                
                if ($bestRoom) {
                    $dayPatternMap[$hari][] = [
                        'tim_id' => $tim->id,
                        'ruangan_id' => $bestRoom->id,
                        'expected_attendance' => $teamSize,
                    ];
                    $allocatedRuangan[] = $bestRoom->id;
                }
            }
        }

        if (empty($dayPatternMap)) {
            Flux::toast(variant: 'warning', text: 'Tidak dapat mengalokasikan tim. Kapasitas ruangan tidak mencukupi.');
            $this->isGenerating = false;
            return;
        }

        $scheduler = app(LraScheduler::class);
        $mulai = $this->periodeAktif->tanggal_mulai ? Carbon::parse($this->periodeAktif->tanggal_mulai) : Carbon::parse($this->tanggalMulaiMinggu);
        $selesai = $this->periodeAktif->tanggal_selesai ? Carbon::parse($this->periodeAktif->tanggal_selesai) : $mulai->copy()->addDays(27);
        $tanggalList = $scheduler->expandTanggal($mulai, $selesai);

        AlokasiRuangan::whereBetween('tanggal', [
            $mulai->toDateString(),
            $selesai->toDateString(),
        ])->delete();

        $hasil = [];
        foreach ($tanggalList as $tanggal) {
            $namaHari = $scheduler->namaHariIndonesia($tanggal);
            if (! isset($dayPatternMap[$namaHari])) continue;

            foreach ($dayPatternMap[$namaHari] as $item) {
                $hasil[] = [
                    'tim_id' => $item['tim_id'],
                    'ruangan_id' => $item['ruangan_id'],
                    'tanggal' => $tanggal->toDateString(),
                    'expected_attendance' => $item['expected_attendance'],
                ];
            }
        }

        AlokasiRuangan::insert(array_map(fn ($r) => [
            'tim_id' => $r['tim_id'],
            'ruangan_id' => $r['ruangan_id'],
            'tanggal' => $r['tanggal'],
            'expected_attendance' => $r['expected_attendance'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $hasil));

        Flux::toast(
            variant: 'success',
            text: 'Berhasil generate alokasi ruangan Best Fit 1 minggu dan diulang ke seluruh periode.',
            heading: 'Generate Best Fit Selesai'
        );

        $this->refreshAlokasiCache();
        $this->isGenerating = false;
    }
}; ?>

<div
    class="space-y-6 select-none"
    x-data="{
        draggingItem: null,
        dragOverRuanganId: null,
        dragOverTanggal: null,
        overTrash: false,
        rows: [],
        pendingRows: null,
        timWfoMap: @js($this->timWfoPerTanggal),
        isDragging: false,
        scrollSpeedX: 0,
        scrollSpeedY: 0,
        autoScrollTimer: null,
        
        // ✨ Multi-select state
        selectedRowIds: [],
        swappingIds: [],
        feedbackCells: {},
        previewSwapTargetId: null,
        previewSwapTransform: '',
        isDrawingBox: false,
        boxStartX: 0,
        boxStartY: 0,
        boxCurrentX: 0,
        boxCurrentY: 0,

        init() {
            // Initialize rows
            this.rows = @js($this->alokasiRowsCache);
            
            // Listen for cache updates
            $wire.on('alokasiRowsUpdated', (data) => {
                let newRows = [];
                if (Array.isArray(data)) {
                    newRows = Array.isArray(data[0]) ? data[0] : (data[0]?.rows || data);
                } else if (data && typeof data === 'object') {
                    newRows = data.rows || (Array.isArray(data[0]) ? data[0] : []);
                }
                const cleanRows = Array.isArray(newRows) ? newRows : [];

                // Jika sedang ada drag aktif, jangan ganti rows sekarang karena akan merusak DOM node kartu yang sedang ditarik!
                // Tunda update sampai drag selesai.
                if (this.isDragging) {
                    this.pendingRows = cleanRows;
                    return;
                }

                this.rows = cleanRows;
                this.cleanupHeaderStyles();
            });

            // Listen for feedback events (success / failure highlight)
            $wire.on('alokasiActionFeedback', (data) => {
                if (!this.isDragging) {
                    this.resetDrag();
                }
                const payload = Array.isArray(data) ? (data[0] || {}) : (data || {});
                const type = payload.success ? 'success' : 'error';
                if (payload.targetRuanganId && payload.targetTanggal) {
                    this.setCellFeedback(payload.targetRuanganId, payload.targetTanggal, type);
                }
                if (payload.oldRuanganId && payload.oldTanggal) {
                    this.setCellFeedback(payload.oldRuanganId, payload.oldTanggal, type);
                }
            });
            
            $wire.$watch('timWfoPerTanggal', val => {
                this.timWfoMap = val;
            });
            $wire.$watch('tanggalMulaiMinggu', () => {
                this.resetScroll();
            });

            // Edge auto-scroll on window dragover
            window.addEventListener('dragover', e => {
                if (this.isDragging) {
                    this.handleAutoScroll(e);
                }
            });

            // Global drag termination listeners on window and document
            const handleGlobalDragEnd = () => {
                this.resetDrag();
            };

            window.addEventListener('dragend', handleGlobalDragEnd);
            document.addEventListener('dragend', handleGlobalDragEnd);

            // Window resize hook if needed (removed JS scroll listener)
            window.addEventListener('resize', () => {}, { passive: true });
        },

        updateStickyHeader() {
            // Deprecated: Using native CSS position: sticky instead for better performance
        },

        handleAutoScroll(event) {
            if (!this.isDragging || !this.$refs.gridScroll) {
                this.stopAutoScrollLoop();
                return;
            }

            const mouseX = event.clientX;
            const mouseY = event.clientY;
            const rect = this.$refs.gridScroll.getBoundingClientRect();

            let speedX = 0;
            let speedY = 0;

            // 1. Horizontal Auto-scroll (Ujung tabel kiri & kanan)
            const edgeThresholdX = 120;
            const leftBoundary = rect.left + 220; // 220px kolom Ruangan (sticky)
            const rightBoundary = Math.min(rect.right, window.innerWidth);

            if (mouseX > rightBoundary - edgeThresholdX && mouseX <= rightBoundary + 60) {
                // Dragging mendekati ujung kanan tabel -> scroll kanan
                const intensity = Math.min(1, Math.max(0.1, (mouseX - (rightBoundary - edgeThresholdX)) / edgeThresholdX));
                speedX = intensity * 22;
            } else if (mouseX < leftBoundary + edgeThresholdX && mouseX >= rect.left - 40) {
                // Dragging mendekati batas kolom Ruangan di kiri -> scroll kiri
                const intensity = Math.min(1, Math.max(0.1, ((leftBoundary + edgeThresholdX) - mouseX) / edgeThresholdX));
                speedX = -intensity * 22;
            }

            // 2. Vertical Auto-scroll (Ujung tabel atas & bawah)
            const edgeThresholdY = 90;
            const topBoundary = Math.max(rect.top, 56); // Ujung atas tabel (atau batas bawah navbar jika tabel terscroll)
            const bottomBoundary = Math.min(rect.bottom, window.innerHeight); // Ujung bawah tabel (atau batas bawah layar jika tabel melebihi layar)

            if (mouseY < topBoundary + edgeThresholdY && mouseY >= topBoundary - 50) {
                // Dragging mendekati ujung atas tabel -> scroll ke atas
                const intensity = Math.min(1, Math.max(0.1, ((topBoundary + edgeThresholdY) - mouseY) / edgeThresholdY));
                speedY = -intensity * 22;
            } else if (mouseY > bottomBoundary - edgeThresholdY && mouseY <= bottomBoundary + 50) {
                // Dragging mendekati ujung bawah tabel -> scroll ke bawah
                const intensity = Math.min(1, Math.max(0.1, (mouseY - (bottomBoundary - edgeThresholdY)) / edgeThresholdY));
                speedY = intensity * 22;
            }

            this.scrollSpeedX = speedX;
            this.scrollSpeedY = speedY;

            if (speedX !== 0 || speedY !== 0) {
                this.startAutoScrollLoop();
            } else {
                this.stopAutoScrollLoop();
            }
        },

        startAutoScrollLoop() {
            if (this.autoScrollTimer) return;
            const step = () => {
                if (!this.isDragging || (this.scrollSpeedX === 0 && this.scrollSpeedY === 0)) {
                    this.stopAutoScrollLoop();
                    return;
                }

                // Horizontal scroll on table container
                if (this.scrollSpeedX !== 0 && this.$refs.gridScroll) {
                    this.$refs.gridScroll.scrollLeft += this.scrollSpeedX;
                }

                // Vertical scroll on window & table container
                if (this.scrollSpeedY !== 0) {
                    window.scrollBy(0, this.scrollSpeedY);
                    if (this.$refs.gridScroll && this.$refs.gridScroll.scrollHeight > this.$refs.gridScroll.clientHeight) {
                        this.$refs.gridScroll.scrollTop += this.scrollSpeedY;
                    }
                }

                this.autoScrollTimer = requestAnimationFrame(step);
            };
            this.autoScrollTimer = requestAnimationFrame(step);
        },

        stopAutoScrollLoop() {
            if (this.autoScrollTimer) {
                cancelAnimationFrame(this.autoScrollTimer);
                this.autoScrollTimer = null;
            }
            this.scrollSpeedX = 0;
            this.scrollSpeedY = 0;
        },

        resetScroll() {
            if (this.$refs.gridScroll) {
                this.$refs.gridScroll.scrollTo({ left: 0, behavior: 'smooth' });
            }
        },

        getAllocation(ruanganId, tanggal) {
            if (!Array.isArray(this.rows)) return null;
            return this.rows.find(r => r.ruangan_id == ruanganId && r.tanggal == tanggal);
        },

        getAllocations(ruanganId, tanggal) {
            if (!Array.isArray(this.rows)) return [];
            return this.rows.filter(r => r.ruangan_id == ruanganId && r.tanggal == tanggal);
        },

        getTotalAttendance(ruanganId, tanggal) {
            const allocs = this.getAllocations(ruanganId, tanggal);
            return allocs.reduce((sum, a) => sum + (a.personil_count || 0), 0);
        },

        setCellFeedback(ruanganId, tanggal, type) {
            if (!ruanganId || !tanggal) return;
            const key = `${ruanganId}_${tanggal}`;
            this.feedbackCells = { ...this.feedbackCells, [key]: type };
            setTimeout(() => {
                if (this.feedbackCells[key] === type) {
                    const updated = { ...this.feedbackCells };
                    delete updated[key];
                    this.feedbackCells = updated;
                }
            }, 2000);
        },

        isTeamWfoOnDate(timId, tanggal) {
            if (!timId || !tanggal || !this.timWfoMap) return false;
            const teams = this.timWfoMap[tanggal];
            if (!Array.isArray(teams)) return false;
            return teams.some(t => t.id == timId);
        },

        cleanupHeaderStyles() {
            const dragClasses = [
                'bg-emerald-500/20', '!bg-emerald-500/20', 'dark:bg-emerald-500/30', 'dark:!bg-emerald-500/30',
                'text-emerald-800', '!text-emerald-800', 'dark:text-emerald-200', 'dark:!text-emerald-200',
                '!border-b-4', '!border-b-emerald-500', '!border-b-rose-500', 'shadow-sm',
                'bg-rose-500/15', '!bg-rose-500/15', 'dark:bg-rose-500/25', 'dark:!bg-rose-500/25',
                'text-rose-800', '!text-rose-800', 'dark:text-rose-200', 'dark:!text-rose-200'
            ];
            document.querySelectorAll('[data-day-header]').forEach(el => {
                dragClasses.forEach(c => el.classList.remove(c));
                el.style.backgroundColor = '';
                el.style.borderColor = '';
                el.style.color = '';
            });
        },

        resetDrag() {
            this.clearSwapPreview();
            this.stopAutoScrollLoop();
            this.isDragging = false;
            this.draggingItem = null;
            this.dragOverRuanganId = null;
            this.dragOverTanggal = null;
            this.overTrash = false;
            this.cleanupHeaderStyles();
            requestAnimationFrame(() => this.cleanupHeaderStyles());
            setTimeout(() => this.cleanupHeaderStyles(), 50);
            setTimeout(() => this.cleanupHeaderStyles(), 200);

            if (this.pendingRows) {
                this.rows = this.pendingRows;
                this.pendingRows = null;
            }
        },

        getHeaderClass(tanggal) {
            if (!this.isDragging || !this.draggingItem) {
                return '';
            }
            const timId = this.draggingItem.isMulti ? this.draggingItem.items?.[0]?.tim_id : this.draggingItem.tim_id;
            if (this.isTeamWfoOnDate(timId, tanggal)) {
                return '!bg-emerald-500/20 dark:!bg-emerald-500/30 !text-emerald-800 dark:!text-emerald-200 !border-b-4 !border-b-emerald-500 shadow-sm';
            } else {
                return '!bg-rose-500/15 dark:!bg-rose-500/25 !text-rose-800 dark:!text-rose-200 !border-b-4 !border-b-rose-500';
            }
        },

        isHeaderAllowed(tanggal) {
            if (!this.isDragging || !this.draggingItem) return false;
            const timId = this.draggingItem.isMulti ? this.draggingItem.items?.[0]?.tim_id : this.draggingItem.tim_id;
            return this.isTeamWfoOnDate(timId, tanggal);
        },

        isHeaderDisallowed(tanggal) {
            if (!this.isDragging || !this.draggingItem) return false;
            const timId = this.draggingItem.isMulti ? this.draggingItem.items?.[0]?.tim_id : this.draggingItem.tim_id;
            return !this.isTeamWfoOnDate(timId, tanggal);
        },

        getAvailableTeams(tanggal) {
            const assignedTimIds = Array.isArray(this.rows) ? this.rows.filter(r => r.tanggal == tanggal).map(r => r.tim_id) : [];
            const wfoTeams = this.timWfoMap[tanggal] || [];
            return wfoTeams.filter(t => !assignedTimIds.includes(t.id));
        },

        // ✨ Multi-select methods
        toggleSelect(alokasiId, event) {
            if (event?.shiftKey) {
                const idx = this.selectedRowIds.indexOf(alokasiId);
                if (idx > -1) {
                    this.selectedRowIds.splice(idx, 1);
                } else {
                    this.selectedRowIds.push(alokasiId);
                }
            } else {
                if (this.selectedRowIds.includes(alokasiId)) {
                    this.selectedRowIds = [];
                } else {
                    this.selectedRowIds = [alokasiId];
                }
            }
        },

        isSelected(alokasiId) {
            return this.selectedRowIds.includes(alokasiId);
        },

        clearSelection() {
            this.selectedRowIds = [];
        },

        hapusBulk() {
            if (!Array.isArray(this.selectedRowIds) || this.selectedRowIds.length === 0) return;
            const ids = [...this.selectedRowIds];
            const count = ids.length;
            
            // Create loading toast using DOM API (avoid HTML strings for Livewire parser)
            const toast = document.createElement('div');
            toast.id = 'bulk-delete-loading-toast';
            toast.className = 'pointer-events-auto w-full max-w-sm overflow-hidden rounded-lg bg-white dark:bg-zinc-900 shadow-lg ring-1 ring-black/5 dark:ring-white/10';
            
            const toastBody = document.createElement('div');
            toastBody.className = 'p-4';
            
            const flexContainer = document.createElement('div');
            flexContainer.className = 'flex items-center gap-3';
            
            const spinnerContainer = document.createElement('div');
            spinnerContainer.className = 'flex-shrink-0';
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('class', 'h-5 w-5 text-blue-500 animate-spin');
            svg.setAttribute('fill', 'none');
            svg.setAttribute('viewBox', '0 0 24 24');
            const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            circle.setAttribute('class', 'opacity-25');
            circle.setAttribute('cx', '12');
            circle.setAttribute('cy', '12');
            circle.setAttribute('r', '10');
            circle.setAttribute('stroke', 'currentColor');
            circle.setAttribute('stroke-width', '4');
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('class', 'opacity-75');
            path.setAttribute('fill', 'currentColor');
            path.setAttribute('d', 'M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z');
            svg.appendChild(circle);
            svg.appendChild(path);
            spinnerContainer.appendChild(svg);
            
            const textContainer = document.createElement('div');
            textContainer.className = 'flex-1';
            
            const text = document.createElement('p');
            text.className = 'text-sm font-medium text-zinc-900 dark:text-zinc-100';
            text.textContent = `Menghapus ${count} alokasi...`;
            
            textContainer.appendChild(text);
            flexContainer.appendChild(spinnerContainer);
            flexContainer.appendChild(textContainer);
            toastBody.appendChild(flexContainer);
            toast.appendChild(toastBody);
            
            // Find or create toast container
            let toastContainer = document.querySelector('[data-flux-toast-container]');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.setAttribute('data-flux-toast-container', '');
                toastContainer.className = 'pointer-events-none fixed inset-0 z-[200] flex items-end px-4 py-6 sm:items-start sm:p-6';
                const wrapper = document.createElement('div');
                wrapper.className = 'flex w-full flex-col items-center space-y-4 sm:items-end';
                toastContainer.appendChild(wrapper);
                document.body.appendChild(toastContainer);
            }
            
            const wrapper = toastContainer.querySelector('div');
            wrapper.appendChild(toast);
            
            // Clear selection first
            this.clearSelection();
            
            // Optimistic: remove from rows
            if (Array.isArray(this.rows)) {
                this.rows = this.rows.filter(r => !ids.includes(r.id));
            }
            
            // Call backend bulk delete
            $wire.hapusBulkWithToast(ids, count).then(() => {
                // Remove loading toast
                const loadingToast = document.getElementById('bulk-delete-loading-toast');
                if (loadingToast) {
                    loadingToast.remove();
                }
            });
        },

        // ✨ Select all chips (Ctrl+A)
        selectAll() {
            if (!Array.isArray(this.rows)) return;
            this.selectedRowIds = this.rows.map(r => r.id);
        },

        hapusSingle(alokasiId) {
            $wire.hapusAlokasi(alokasiId);
        },

        // ✨ Drag-box selection
        startBoxSelection(event) {
            // Jangan start box selection jika:
            // 1. Click pada card alokasi
            // 2. Click pada floating toolbar
            if (event.target.closest('[data-alokasi-card]') || event.target.closest('[data-floating-toolbar]')) {
                return;
            }
            
            this.isDrawingBox = true;
            this.boxStartX = event.clientX;
            this.boxStartY = event.clientY;
            this.boxCurrentX = event.clientX;
            this.boxCurrentY = event.clientY;
            this.selectedRowIds = [];
        },

        updateBoxSelection(event) {
            if (!this.isDrawingBox) return;
            this.boxCurrentX = event.clientX;
            this.boxCurrentY = event.clientY;
            
            const boxRect = {
                left: Math.min(this.boxStartX, this.boxCurrentX),
                right: Math.max(this.boxStartX, this.boxCurrentX),
                top: Math.min(this.boxStartY, this.boxCurrentY),
                bottom: Math.max(this.boxStartY, this.boxCurrentY),
            };
            
            const cards = document.querySelectorAll('[data-alokasi-card]');
            const newSelection = [];
            
            cards.forEach(card => {
                const cardRect = card.getBoundingClientRect();
                if (!(cardRect.right < boxRect.left || 
                      cardRect.left > boxRect.right || 
                      cardRect.bottom < boxRect.top || 
                      cardRect.top > boxRect.bottom)) {
                    const alokasiId = parseInt(card.dataset.alokasiCard);
                    if (alokasiId && !newSelection.includes(alokasiId)) {
                        newSelection.push(alokasiId);
                    }
                }
            });
            
            this.selectedRowIds = newSelection;
        },

        endBoxSelection() {
            this.isDrawingBox = false;
        },

        getBoxStyle() {
            const left = Math.min(this.boxStartX, this.boxCurrentX);
            const top = Math.min(this.boxStartY, this.boxCurrentY);
            const width = Math.abs(this.boxCurrentX - this.boxStartX);
            const height = Math.abs(this.boxCurrentY - this.boxStartY);
            return `position: fixed; left: ${left}px; top: ${top}px; width: ${width}px; height: ${height}px; pointer-events: none; z-index: 9999;`;
        },

        dragStart(event, item) {
            // Check if item is selected and we have multiple selections
            if (this.selectedRowIds.includes(item.id) && this.selectedRowIds.length > 1) {
                this.draggingItem = { 
                    isMulti: true,
                    ids: [...this.selectedRowIds],
                    items: this.rows.filter(r => this.selectedRowIds.includes(r.id))
                };
            } else {
                this.draggingItem = { 
                    isMulti: false,
                    ...item 
                };
            }
            this.isDragging = true;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', JSON.stringify(item));
        },

        dragEnd(event) {
            this.resetDrag();
        },

        dropKeTrash() {
            if (!this.draggingItem) {
                this.resetDrag();
                return;
            }

            if (this.draggingItem.isMulti) {
                const ids = this.draggingItem.ids;
                // Optimistic: remove from rows
                if (Array.isArray(this.rows)) {
                    this.rows = this.rows.filter(r => !ids.includes(r.id));
                }
                ids.forEach(id => $wire.hapusAlokasi(id));
                this.selectedRowIds = [];
            } else {
                // Optimistic: remove from rows
                if (Array.isArray(this.rows)) {
                    this.rows = this.rows.filter(r => r.id !== this.draggingItem.id);
                }
                $wire.hapusAlokasi(this.draggingItem.id);
            }

            this.resetDrag();
        },

        updateSwapPreview() {
            if (!this.isDragging || !this.draggingItem || this.draggingItem.isMulti) {
                this.clearSwapPreview();
                return;
            }

            if (!this.dragOverRuanganId || !this.dragOverTanggal) {
                this.clearSwapPreview();
                return;
            }

            const sourceRuanganId = this.draggingItem.ruangan_id;
            const sourceTanggal = this.draggingItem.tanggal;
            const targetRuanganId = this.dragOverRuanganId;
            const targetTanggal = this.dragOverTanggal;

            if (sourceRuanganId == targetRuanganId && sourceTanggal == targetTanggal) {
                this.clearSwapPreview();
                return;
            }

            let targetAlloc = null;
            if (this.dragOverAlokasiId) {
                targetAlloc = this.rows.find(r => r.id === this.dragOverAlokasiId);
            }

            if (!targetAlloc || targetAlloc.id === this.draggingItem.id || targetAlloc.tim_id === this.draggingItem.tim_id) {
                this.clearSwapPreview();
                return;
            }

            // Validasi: Tim asal harus WFO di tanggal target
            if (!this.isTeamWfoOnDate(this.draggingItem.tim_id, targetTanggal)) {
                this.clearSwapPreview();
                return;
            }

            // Validasi: Jika beda hari, tim target juga harus WFO di tanggal asal
            if (sourceTanggal !== targetTanggal && !this.isTeamWfoOnDate(targetAlloc.tim_id, sourceTanggal)) {
                this.clearSwapPreview();
                return;
            }

            // Keduanya valid WFO! Hitung selisih koordinat antar cell
            const sourceCell = document.querySelector(`[data-cell-id='${sourceRuanganId}_${sourceTanggal}']`);
            const targetCell = document.querySelector(`[data-cell-id='${targetRuanganId}_${targetTanggal}']`);

            if (sourceCell && targetCell) {
                const sourceRect = sourceCell.getBoundingClientRect();
                const targetRect = targetCell.getBoundingClientRect();
                const deltaX = Math.round(sourceRect.left - targetRect.left);
                const deltaY = Math.round(sourceRect.top - targetRect.top);

                this.previewSwapTargetId = targetAlloc.id;
                this.previewSwapTransform = `translate3d(${deltaX}px, ${deltaY}px, 0)`;
            } else {
                this.clearSwapPreview();
            }
        },

        clearSwapPreview() {
            if (this.previewSwapTargetId) {
                const el = document.querySelector(`[data-alokasi-card='${this.previewSwapTargetId}']`);
                if (el) {
                    el.style.transition = 'none';
                    el.style.transform = '';
                }
            }
            this.previewSwapTargetId = null;
            this.previewSwapTransform = '';
        },

        dragLeaveContainer(event) {
            if (event.currentTarget && event.relatedTarget && !event.currentTarget.contains(event.relatedTarget)) {
                this.clearSwapPreview();
                this.dragOverRuanganId = null;
                this.dragOverTanggal = null;
                this.dragOverAlokasiId = null;
            }
        },

        dragOver(event, ruanganId, tanggal) {
            event.preventDefault();
            const overCard = event.target.closest('[data-alokasi-card]');
            const overAlokasiId = overCard ? parseInt(overCard.dataset.alokasiCard) : null;
            
            if (this.dragOverRuanganId == ruanganId && this.dragOverTanggal == tanggal && this.dragOverAlokasiId === overAlokasiId) {
                return;
            }
            this.dragOverRuanganId = ruanganId;
            this.dragOverTanggal = tanggal;
            this.dragOverAlokasiId = overAlokasiId;
            this.updateSwapPreview();
        },

        dropItem(event, targetRuanganId, targetTanggal) {
            event.preventDefault();
            this.stopAutoScrollLoop();
            if (!this.draggingItem) {
                this.resetDrag();
                return;
            }

            const alokasiId = this.draggingItem.id;
            const sourceRuanganId = this.draggingItem.ruangan_id;
            const sourceTanggal = this.draggingItem.tanggal;
            
            const overCard = event.target.closest('[data-alokasi-card]');
            const targetAlokasiId = overCard ? parseInt(overCard.dataset.alokasiCard) : null;
            
            this.resetDrag();

            if (sourceRuanganId == targetRuanganId && sourceTanggal == targetTanggal) {
                return;
            }

            // Temukan item asal di dalam array rows Alpine
            const sourceAlloc = Array.isArray(this.rows) ? this.rows.find(r => r.id === alokasiId) : null;
            if (!sourceAlloc) {
                return;
            }

            // Validasi apakah tim asal memiliki jadwal WFO pada tanggal target
            if (!this.isTeamWfoOnDate(sourceAlloc.tim_id, targetTanggal)) {
                this.setCellFeedback(targetRuanganId, targetTanggal, 'error');
                this.setCellFeedback(sourceRuanganId, sourceTanggal, 'error');
                $wire.pindahRuangan(alokasiId, targetRuanganId, targetTanggal);
                return;
            }

            // Jika didrop ke atas chip (targetAlokasiId), lakukan swap dengan chip tersebut
            let targetAlloc = null;
            if (targetAlokasiId) {
                targetAlloc = this.rows.find(r => r.id === targetAlokasiId);
            }

            if (targetAlloc) {
                // Jangan lakukan apa-apa jika di drag ke tim yang sama
                if (targetAlloc.tim_id === sourceAlloc.tim_id) {
                    return;
                }

                // Jika beda hari, validasi apakah tim target juga memiliki jadwal WFO pada hari asal
                if (sourceTanggal !== targetTanggal && !this.isTeamWfoOnDate(targetAlloc.tim_id, sourceTanggal)) {
                    this.setCellFeedback(targetRuanganId, targetTanggal, 'error');
                    this.setCellFeedback(sourceRuanganId, sourceTanggal, 'error');
                    $wire.pindahRuangan(alokasiId, targetRuanganId, targetTanggal, targetAlokasiId);
                    return;
                }

                // SWAP KEDUA TIM: tukar ruangan & tanggal secara langsung di memory
                sourceAlloc.ruangan_id = targetRuanganId;
                sourceAlloc.tanggal = targetTanggal;

                targetAlloc.ruangan_id = sourceRuanganId;
                targetAlloc.tanggal = sourceTanggal;

                // Animasi visual kartu yang bertukar tempat
                this.swappingIds = [sourceAlloc.id, targetAlloc.id];

                // ✨ Highlight kedua cell hijau setelah sukses ditukar
                this.setCellFeedback(targetRuanganId, targetTanggal, 'success');
                this.setCellFeedback(sourceRuanganId, sourceTanggal, 'success');
            } else {
                // Geser ke ruangan kosong atau slot kosong di ruangan yang sama
                sourceAlloc.ruangan_id = targetRuanganId;
                sourceAlloc.tanggal = targetTanggal;

                this.swappingIds = [sourceAlloc.id];

                // ✨ Highlight cell tujuan & asal hijau setelah sukses dipindah
                this.setCellFeedback(targetRuanganId, targetTanggal, 'success');
                this.setCellFeedback(sourceRuanganId, sourceTanggal, 'success');
            }

            // Trigger reaktifitas Alpine dengan array baru
            this.rows = [...this.rows];

            setTimeout(() => {
                this.swappingIds = [];
            }, 600);

            // Jalankan atomic swap / pindah di backend
            $wire.pindahRuangan(alokasiId, targetRuanganId, targetTanggal, targetAlokasiId);
        }
    }"
    @mousedown="startBoxSelection($event)"
    @mousemove="updateBoxSelection($event)"
    @mouseup="endBoxSelection()"
    @mouseleave="endBoxSelection()"
    @dragend.window="resetDrag()"
    @dragend.document="resetDrag()"
    @keydown.delete.window="selectedRowIds.length > 0 ? hapusBulk() : null"
    @keydown.backspace.window="selectedRowIds.length > 0 ? hapusBulk() : null"
    @keydown.ctrl.a.window.prevent="selectAll()"
    @keydown.escape.window="clearSelection(); resetDrag()"
>
    {{-- Top Header Section --}}
    <div class="flex flex-col xl:flex-row xl:items-end justify-between gap-4">
        <div class="flex-1">
            <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">
                Alokasi Ruangan Periode
            </flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400 mt-0.5">
                Pola alokasi ruangan 1 minggu berulang otomatis sepanjang periode. 
                Drag card untuk swap ruangan, drop ke
                <svg class="inline h-3.5 w-3.5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                untuk hapus.
            </flux:text>
        </div>

        {{-- Week Navigator & Auto-Generate Button --}}
        <div class="flex items-center gap-2 flex-wrap">
            @if ($this->periodeAktif && $this->periodeAktif->tanggal_mulai && $this->periodeAktif->tanggal_selesai)
                <div class="inline-flex items-center gap-2 rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50/80 dark:bg-blue-950/40 px-3 py-1.5 text-xs font-semibold text-blue-700 dark:text-blue-300 shadow-2xs">
                    <flux:icon icon="calendar-days" class="size-4 text-blue-600 dark:text-blue-400 shrink-0" />
                    <span>Periode: {{ Carbon::parse($this->periodeAktif->tanggal_mulai)->translatedFormat('d M Y') }} – {{ Carbon::parse($this->periodeAktif->tanggal_selesai)->translatedFormat('d M Y') }}</span>
                </div>
            @else
                <div class="inline-flex items-center rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-1 shadow-xs">
                    <button
                        wire:click="prevWeek"
                        @click="resetScroll()"
                        type="button"
                        wire:loading.attr="disabled"
                        wire:target="prevWeek,nextWeek"
                        class="p-1.5 rounded-md text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors disabled:opacity-50 disabled:cursor-wait"
                        title="Minggu Sebelumnya"
                    >
                        <span wire:loading.remove wire:target="prevWeek,nextWeek">
                            <flux:icon icon="chevron-left" class="size-4" />
                        </span>
                        <span wire:loading wire:target="prevWeek,nextWeek">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </span>
                    </button>
                    <button
                        wire:click="todayWeek"
                        @click="resetScroll()"
                        type="button"
                        wire:loading.attr="disabled"
                        wire:target="prevWeek,nextWeek"
                        class="px-2.5 py-1 text-xs font-semibold text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded-md transition-colors disabled:opacity-50 disabled:cursor-wait"
                    >
                        Minggu Ini
                    </button>
                    <button
                        wire:click="nextWeek"
                        @click="resetScroll()"
                        type="button"
                        wire:loading.attr="disabled"
                        wire:target="prevWeek,nextWeek"
                        class="p-1.5 rounded-md text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors disabled:opacity-50 disabled:cursor-wait"
                        title="Minggu Berikutnya"
                    >
                        <span wire:loading.remove wire:target="prevWeek,nextWeek">
                            <flux:icon icon="chevron-right" class="size-4" />
                        </span>
                        <span wire:loading wire:target="prevWeek,nextWeek">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </span>
                    </button>
                </div>
            @endif

            {{-- Dual Generate Buttons --}}
            <div class="flex items-center gap-2" x-data="{ showStrategyInfo: false }">
                <flux:button 
                    variant="primary" 
                    icon="chart-bar"
                    @click="$wire.set('showBestFitConfirmModal', true)"
                    @mouseenter="showStrategyInfo = 'bestfit'"
                    @mouseleave="showStrategyInfo = false"
                >
                    <span class="hidden sm:inline">Generate</span> Best Fit
                </flux:button>
                
                <flux:button 
                    variant="outline" 
                    icon="scale"
                    @click="$wire.set('showFairConfirmModal', true)"
                    @mouseenter="showStrategyInfo = 'fair'"
                    @mouseleave="showStrategyInfo = false"
                >
                    <span class="hidden sm:inline">Generate</span> Fair/LRA
                </flux:button>

                <flux:button 
                    variant="ghost" 
                    icon="arrow-path"
                    wire:click="sinkronkanJadwalWfo"
                    wire:loading.attr="disabled"
                    title="Sinkronkan alokasi ruangan dengan Jadwal WFO aktif"
                >
                    <span class="hidden sm:inline">Sinkronkan WFO</span>
                </flux:button>

                <flux:modal.trigger name="modal-export-ruangan-pdf">
                    <flux:button variant="filled" icon="arrow-down-tray">
                        Export PDF
                    </flux:button>
                </flux:modal.trigger>
                
                {{-- Strategy Info Tooltip --}}
                <div 
                    x-show="showStrategyInfo"
                    x-transition
                    class="absolute top-full mt-2 right-0 z-50 w-80 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-xl text-xs"
                >
                    <template x-if="showStrategyInfo === 'bestfit'">
                        <div>
                            <div class="font-semibold text-blue-600 dark:text-blue-400 mb-1">📊 Best Fit (Capacity-Optimized)</div>
                            <p class="text-zinc-600 dark:text-zinc-400">Maksimalkan utilisasi kapasitas ruangan. Algoritma memilih ruangan yang paling pas dengan ukuran tim untuk meminimalkan ruang kosong terbuang.</p>
                        </div>
                    </template>
                    <template x-if="showStrategyInfo === 'fair'">
                        <div>
                            <div class="font-semibold text-green-600 dark:text-green-400 mb-1">⚖️ Fair/LRA (Fairness-Optimized)</div>
                            <p class="text-zinc-600 dark:text-zinc-400">Distribusi adil penggunaan ruangan. Algoritma memastikan setiap ruangan dipakai merata dengan track frekuensi pemakaian (Least Recently Allocated).</p>
                        </div>
                    </template>
                </div>
            </div>
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
    <flux:card class="p-0 overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs">
        <div 
            class="px-5 py-3.5 border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-900/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3 transition-all duration-300"
        >
            <div class="flex items-center gap-2">
                <span class="size-2.5 rounded-full bg-[#3B71CA] animate-pulse"></span>
                <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                    @if ($this->periodeAktif && $this->periodeAktif->tanggal_mulai && $this->periodeAktif->tanggal_selesai)
                        Rentang Periode: {{ Carbon::parse($this->periodeAktif->tanggal_mulai)->translatedFormat('d F Y') }} – {{ Carbon::parse($this->periodeAktif->tanggal_selesai)->translatedFormat('d F Y') }}
                        <span class="ml-2 px-2 py-0.5 rounded-md bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-[11px] font-medium border border-blue-200 dark:border-blue-800/50">
                            1 Minggu Diulang
                        </span>
                    @else
                        Rentang: {{ Carbon::parse($tanggalMulaiMinggu)->translatedFormat('d F Y') }} – {{ Carbon::parse($tanggalMulaiMinggu)->addDays(5)->translatedFormat('d F Y') }}
                    @endif
                </span>
            </div>
            <div class="flex items-center gap-3 text-xs text-zinc-500">
                <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-[#3B71CA]"></span> Drag & drop kartu tim untuk swap ruangan</span>
            </div>
        </div>

        {{-- Single Table Container (Horizontal & vertical scrollable for native sticky header) --}}
        <div
            x-ref="gridScroll"
            @dragover="handleAutoScroll($event)"
            @dragleave="dragLeaveContainer($event)"
            class="overflow-auto relative min-h-[400px]"
            style="max-height: calc(100vh - 210px);"
        >
            {{-- Loading Overlay --}}
            <div 
                wire:loading 
                wire:target="prevWeek,nextWeek"
                class="absolute inset-0 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-sm z-50 flex items-center justify-center pt-24"
            >
                <div class="flex flex-col items-center gap-3">
                    <svg class="animate-spin h-8 w-8 text-[#3B71CA]" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Memuat data alokasi...</span>
                </div>
            </div>
            
            <table
                x-ref="tableRef"
                class="w-full text-left border-separate border-spacing-0"
                style="min-width: {{ 220 + (count($this->daftarHariMingguIni) * 146) }}px;"
            >
                <colgroup>
                    <col style="width: 220px; min-width: 220px;">
                    @foreach ($this->daftarHariMingguIni as $h)
                        <col style="width: {{ 100 / max(1, count($this->daftarHariMingguIni)) }}%; min-width: 146px;">
                    @endforeach
                </colgroup>
                <thead
                    x-ref="tableThead"
                    class="z-20 bg-zinc-100 dark:bg-zinc-900"
                >
                    <tr class="bg-zinc-100 dark:bg-zinc-900 text-xs font-semibold text-zinc-600 dark:text-zinc-300">
                        {{-- Kolom Ruangan: Sticky Top & Left --}}
                        <th class="p-3.5 ps-5 border-b border-r border-zinc-200 dark:border-zinc-800 select-none sticky left-0 top-0 z-30 bg-zinc-100 dark:bg-zinc-900 shadow-[2px_2px_5px_-2px_rgba(0,0,0,0.08)]">
                            <div class="flex items-center gap-2 font-semibold text-xs uppercase tracking-wider text-zinc-600 dark:text-zinc-400 whitespace-nowrap">
                                <flux:icon icon="building-office-2" class="size-4 text-zinc-400 shrink-0" />
                                <span>Ruangan</span>
                            </div>
                        </th>
                        {{-- Kolom Hari --}}
                        @foreach ($this->daftarHariMingguIni as $h)
                            <th
                                data-day-header="{{ $h['tanggal'] }}"
                                class="p-3.5 text-center border-b border-r border-zinc-200 dark:border-zinc-800 select-none sticky top-0 z-20 bg-zinc-100 dark:bg-zinc-900 transition-colors duration-150 shadow-[0_2px_5px_-2px_rgba(0,0,0,0.08)]"
                                :class="(isDragging && draggingItem) ? getHeaderClass('{{ $h['tanggal'] }}') : ({{ $h['is_today'] ? 'true' : 'false' }} ? '!bg-blue-50/90 dark:!bg-blue-950/50 text-blue-600 dark:text-blue-400' : '!bg-zinc-100 dark:!bg-zinc-900 text-zinc-600 dark:text-zinc-300')"
                                @dragover.prevent
                                @drop="resetDrag()"
                            >
                                <div class="flex items-center justify-center gap-1.5 font-bold text-sm">
                                    <span>{{ $h['nama'] }}</span>
                                    <template x-if="isDragging && draggingItem && isHeaderAllowed('{{ $h['tanggal'] }}')">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-500 text-white shadow-xs animate-pulse">
                                            ✓ Boleh
                                        </span>
                                    </template>
                                    <template x-if="isDragging && draggingItem && isHeaderDisallowed('{{ $h['tanggal'] }}')">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-500 text-white shadow-xs">
                                            ✕ Bukan Hari WFO
                                        </span>
                                    </template>
                                </div>
                                <div class="text-[11px] font-normal opacity-75 mt-0.5">
                                    {{ $this->periodeAktif ? 'Setiap ' . $h['nama'] : $h['label_tanggal'] }}
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse ($this->daftarRuangan as $ruangan)
                        <tr class="hover:bg-zinc-50/40 dark:hover:bg-zinc-900/20 transition-colors">
                            {{-- Ruangan Info Cell (Sticky Left) --}}
                            <td
                                class="p-4 ps-5 sticky left-0 z-10 bg-white dark:bg-zinc-800 border-b border-r border-zinc-200 dark:border-zinc-800 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.06)]"
                            >
                                <div class="font-semibold text-sm text-zinc-900 dark:text-zinc-100 leading-snug break-words">
                                    {{ $ruangan->nama_ruangan }}
                                </div>
                                <div class="flex items-center gap-1.5 mt-2">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-zinc-100 dark:bg-zinc-700/60 text-zinc-600 dark:text-zinc-300 text-xs font-medium border border-zinc-200/80 dark:border-zinc-700/80 whitespace-nowrap shadow-2xs">
                                        <flux:icon icon="users" class="size-3.5 text-zinc-400 shrink-0" />
                                        <span>{{ $ruangan->kapasitas }} orang</span>
                                    </span>
                                </div>
                            </td>

                            {{-- Daily Cells (Drop targets) --}}
                            @foreach ($this->daftarHariMingguIni as $h)
                                <td
                                    data-cell-id="{{ $ruangan->id }}_{{ $h['tanggal'] }}"
                                    class="p-2 border-b border-r border-zinc-200 dark:border-zinc-800 align-top transition-all duration-300 relative"
                                    :class="{
                                        'ring-2 !ring-red-500 !bg-red-500/20 dark:!bg-red-500/30 rounded-lg !border-red-500 shadow-md animate-pulse': feedbackCells['{{ $ruangan->id }}_{{ $h['tanggal'] }}'] === 'error',
                                        'ring-2 !ring-emerald-500 !bg-emerald-500/20 dark:!bg-emerald-500/30 rounded-lg !border-emerald-500 shadow-md animate-pulse': feedbackCells['{{ $ruangan->id }}_{{ $h['tanggal'] }}'] === 'success',
                                        'bg-emerald-500/10 dark:bg-emerald-500/15 !border-emerald-300/60 dark:!border-emerald-700/50': isDragging && draggingItem && isTeamWfoOnDate(draggingItem.tim_id, '{{ $h['tanggal'] }}') && (dragOverRuanganId != {{ $ruangan->id }} || dragOverTanggal != '{{ $h['tanggal'] }}'),
                                        'opacity-40 bg-zinc-100/70 dark:bg-zinc-900/70 cursor-not-allowed': isDragging && draggingItem && !isTeamWfoOnDate(draggingItem.tim_id, '{{ $h['tanggal'] }}'),
                                        'bg-[#3B71CA]/10 ring-2 ring-[#3B71CA] ring-inset rounded-lg': isDragging && dragOverRuanganId == {{ $ruangan->id }} && dragOverTanggal == '{{ $h['tanggal'] }}' && !dragOverAlokasiId && (!draggingItem || isTeamWfoOnDate(draggingItem.tim_id, '{{ $h['tanggal'] }}')),
                                        'bg-amber-500/15 ring-2 ring-amber-500 ring-inset rounded-lg': isDragging && dragOverRuanganId == {{ $ruangan->id }} && dragOverTanggal == '{{ $h['tanggal'] }}' && dragOverAlokasiId && (!draggingItem || isTeamWfoOnDate(draggingItem.tim_id, '{{ $h['tanggal'] }}')),
                                        'bg-red-500/15 ring-2 ring-red-500 ring-inset rounded-lg cursor-not-allowed': isDragging && dragOverRuanganId == {{ $ruangan->id }} && dragOverTanggal == '{{ $h['tanggal'] }}' && draggingItem && !isTeamWfoOnDate(draggingItem.tim_id, '{{ $h['tanggal'] }}'),
                                        '!z-40': previewSwapTargetId && getAllocation({{ $ruangan->id }}, '{{ $h['tanggal'] }}')?.id === previewSwapTargetId,
                                        'bg-[#3B71CA]/5 dark:bg-[#3B71CA]/5': !isDragging && {{ $h['is_today'] ? 'true' : 'false' }}
                                    }"
                                    @dragover.prevent="dragOver($event, {{ $ruangan->id }}, '{{ $h['tanggal'] }}')"
                                    @drop="dropItem($event, {{ $ruangan->id }}, '{{ $h['tanggal'] }}')"
                                >
                                    {{-- Swap Indicator Badge on Hover when allowed --}}
                                    <div
                                        x-show="isDragging && dragOverRuanganId == {{ $ruangan->id }} && dragOverTanggal == '{{ $h['tanggal'] }}' && dragOverAlokasiId && draggingItem && draggingItem.id !== dragOverAlokasiId && isTeamWfoOnDate(draggingItem.tim_id, '{{ $h['tanggal'] }}')"
                                        x-cloak
                                        class="absolute inset-x-2 -top-2.5 z-30 flex items-center justify-center pointer-events-none"
                                    >
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500 text-white shadow-md flex items-center gap-1 animate-pulse">
                                            <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                            </svg>
                                            Tukar Ruangan
                                        </span>
                                    </div>

                                    {{-- Ghost Dropzone in target cell while target card is preview-swapped --}}
                                    <div
                                        x-show="isDragging && dragOverRuanganId == {{ $ruangan->id }} && dragOverTanggal == '{{ $h['tanggal'] }}' && previewSwapTargetId"
                                        x-cloak
                                        class="absolute inset-2 border-2 border-dashed border-amber-400 dark:border-amber-500 bg-amber-500/10 dark:bg-amber-500/15 rounded-xl flex items-center justify-center pointer-events-none z-10 transition-all duration-200"
                                    >
                                        <span class="text-xs font-bold text-amber-600 dark:text-amber-400 flex items-center gap-1.5 animate-pulse">
                                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                            </svg>
                                            Lepas untuk Tukar
                                        </span>
                                    </div>

                                    <div class="min-h-[72px] flex flex-col justify-center gap-2">
                                        {{-- Over-capacity Warning Badge --}}
                                        <template x-if="getAllocations({{ $ruangan->id }}, '{{ $h['tanggal'] }}').length > 0 && getTotalAttendance({{ $ruangan->id }}, '{{ $h['tanggal'] }}') > {{ $ruangan->kapasitas }}">
                                            <div class="flex items-center gap-1 text-[10px] font-bold text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-900/30 px-2 py-1 rounded-md mb-1 animate-pulse border border-red-200 dark:border-red-800/50">
                                                <flux:icon icon="exclamation-triangle" class="size-3" />
                                                <span>Over Capacity (<span x-text="getTotalAttendance({{ $ruangan->id }}, '{{ $h['tanggal'] }}')"></span>/{{ $ruangan->kapasitas }})</span>
                                            </div>
                                        </template>

                                        {{-- Assigned Team Cards --}}
                                        <template x-for="alokasi in getAllocations({{ $ruangan->id }}, '{{ $h['tanggal'] }}')" :key="alokasi.id">
                                            <div :data-alokasi-card="alokasi.id" class="relative">
                                                <div
                                                    draggable="true"
                                                    @click="toggleSelect(alokasi.id, $event)"
                                                    @dragstart="dragStart($event, alokasi)"
                                                    @dragend="resetDrag()"
                                                    @mousedown.stop
                                                    class="group/card p-2.5 rounded-xl border shadow-xs transition-all duration-150 cursor-grab active:cursor-grabbing select-none hover:-translate-y-px hover:shadow-[0_0_12px_currentColor]"
                                                    :class="[
                                                        alokasi.color_classes || 'bg-blue-50 dark:bg-blue-950/50 border-blue-200 dark:border-blue-800/60 hover:border-blue-400 dark:hover:border-blue-600',
                                                        isSelected(alokasi.id) ? 'ring-2 ring-blue-500 hover:ring-2 hover:ring-blue-500' : 'hover:ring-1 hover:ring-current',
                                                        (draggingItem && ((draggingItem.id === alokasi.id) || (draggingItem.isMulti && draggingItem.ids?.includes(alokasi.id)))) && 'opacity-40',
                                                        swappingIds.includes(alokasi.id) && 'ring-2 ring-emerald-500 dark:ring-emerald-400 shadow-md',
                                                        (previewSwapTargetId === alokasi.id) && 'z-50 shadow-2xl ring-2 ring-amber-400 dark:ring-amber-500 opacity-90'
                                                    ]"
                                                    :style="(previewSwapTargetId && previewSwapTargetId === alokasi.id) ? ('transform: ' + previewSwapTransform + '; transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease; pointer-events: none;') : ''"
                                                >
                                                    <div class="flex items-start justify-between gap-1.5">
                                                        <div class="min-w-0 flex-1">
                                                            <div class="font-semibold text-xs truncate leading-tight" x-text="alokasi.nama_tim"></div>
                                                            
                                                            <div class="flex items-center gap-1.5 text-[10px] font-medium mt-1.5">
                                                                <span class="inline-block size-1.5 rounded-full bg-current"></span>
                                                                <span x-text="alokasi.personil_count + ' orang'"></span>
                                                            </div>
                                                        </div>

                                                        <button
                                                            type="button"
                                                            @click.stop="hapusSingle(alokasi.id)"
                                                            class="opacity-0 group-hover/card:opacity-100 p-1 hover:bg-black/10 dark:hover:bg-white/10 rounded-md transition-all"
                                                            title="Hapus alokasi ruangan"
                                                        >
                                                            <flux:icon icon="x-mark" class="size-3.5" />
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>

                                        {{-- Empty Slot / Add Button (Tampil di bawah tim atau sendirian jika kosong) --}}
                                        <div class="h-full flex flex-col items-center justify-center p-2 rounded-lg border border-dashed border-zinc-200 dark:border-zinc-700/60 hover:border-blue-400 dark:hover:border-blue-600 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all mt-1">
                                            <div x-data="{ openMenu: false }" class="relative w-full">
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
                                        </div>
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

    {{-- ✨ Floating Action: Multi-select Toolbar --}}
    <div
        x-cloak
        x-show="selectedRowIds.length > 0 && !isDragging"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-4"
        data-floating-toolbar
        class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 px-5 py-3 rounded-2xl bg-white/95 dark:bg-zinc-900/95 border border-zinc-200 dark:border-zinc-700 shadow-2xl backdrop-blur-md select-none"
    >
        <div class="flex items-center gap-2.5">
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400 text-xs font-bold">
                <span x-text="selectedRowIds.length"></span>
            </span>
            <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Alokasi Terpilih</span>
        </div>

        <div class="h-5 w-px bg-zinc-200 dark:bg-zinc-700 mx-1"></div>

        <div class="flex items-center gap-2">
            <button 
                x-on:click.stop="clearSelection()" 
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                Batal
            </button>
            <button 
                x-on:click.stop="hapusBulk()" 
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border border-red-600 bg-red-600 text-white hover:bg-red-700 hover:border-red-700 transition-colors"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Hapus
            </button>
        </div>
    </div>

    {{-- ✨ Floating Trash Drop Zone --}}
    <div
        x-cloak
        x-show="isDragging"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-6"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-6"
        @dragover.prevent="overTrash = true"
        @dragleave="overTrash = false"
        @drop.prevent="dropKeTrash()"
        :class="overTrash
            ? 'border-red-500 bg-red-600 text-white scale-105 shadow-red-500/40'
            : 'border-red-400/70 bg-zinc-900/90 text-white shadow-2xl border-dashed'"
        class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex items-center justify-center gap-2.5 px-8 py-3.5 rounded-2xl border-2 backdrop-blur-md transition-all cursor-pointer select-none"
    >
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
        </svg>
        <span class="text-sm font-semibold">Drop di sini untuk hapus</span>
    </div>

    {{-- ✨ Drag-to-select Box Visual --}}
    <div 
        x-show="isDrawingBox" 
        :style="getBoxStyle()"
        class="border-2 border-[#3B71CA] bg-[#3B71CA]/10 rounded"
    ></div>

    {{-- Modal: Best Fit Confirmation --}}
    <flux:modal wire:model="showBestFitConfirmModal" class="max-w-lg">
        <div class="space-y-4">
            <div class="flex items-start gap-4">
                <flux:icon icon="chart-bar" variant="solid" class="size-8 text-blue-500 flex-shrink-0 mt-1" />
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <flux:heading size="lg" class="font-bold">Generate Best Fit (Capacity-Optimized)</flux:heading>
                        <flux:badge size="sm" color="amber" class="font-semibold">BETA</flux:badge>
                    </div>
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                        Algoritma <strong>Best Fit</strong> akan memilih ruangan yang paling sesuai dengan ukuran tim untuk memaksimalkan utilisasi kapasitas ruangan dan meminimalkan ruang kosong terbuang.
                    </flux:text>
                </div>
            </div>

            <div class="p-4 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-700">
                <div class="flex items-center gap-2 mb-2">
                    <flux:icon.exclamation-triangle class="size-5 text-amber-600 dark:text-amber-400" />
                    <flux:text class="text-sm font-medium text-amber-900 dark:text-amber-100">
                        Konsekuensi:
                    </flux:text>
                </div>
                <ul class="text-sm text-amber-800 dark:text-amber-200 space-y-1.5 list-disc list-inside">
                    <li>Semua alokasi ruangan untuk <strong>minggu ini</strong> akan dihapus</li>
                    <li>Sistem akan generate alokasi baru berdasarkan jadwal WFO yang ada</li>
                    <li>Proses ini tidak bisa di-undo</li>
                </ul>
            </div>

            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                Yakin ingin melanjutkan generate dengan strategi Best Fit?
            </flux:text>

            <div class="flex justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button 
                    variant="ghost" 
                    @click="$wire.set('showBestFitConfirmModal', false)"
                    wire:loading.attr="disabled"
                >
                    Batal
                </flux:button>
                <flux:button 
                    variant="primary" 
                    icon="chart-bar"
                    wire:click="generateBestFitAllocation"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="generateBestFitAllocation">Ya, Generate Best Fit</span>
                    <span wire:loading wire:target="generateBestFitAllocation" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Processing...
                    </span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal: Fair/LRA Confirmation --}}
    <flux:modal wire:model="showFairConfirmModal" class="max-w-lg">
        <div class="space-y-4">
            <div class="flex items-start gap-4">
                <flux:icon icon="scale" variant="solid" class="size-8 text-green-500 flex-shrink-0 mt-1" />
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <flux:heading size="lg" class="font-bold">Generate Fair/LRA (Fairness-Optimized)</flux:heading>
                        <flux:badge size="sm" color="amber" class="font-semibold">BETA</flux:badge>
                    </div>
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                        Algoritma <strong>Fair/LRA</strong> (Least Recently Allocated) akan memastikan setiap ruangan dipakai secara merata dengan tracking frekuensi pemakaian.
                    </flux:text>
                </div>
            </div>

            <div class="p-4 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-700">
                <div class="flex items-center gap-2 mb-2">
                    <flux:icon.exclamation-triangle class="size-5 text-amber-600 dark:text-amber-400" />
                    <flux:text class="text-sm font-medium text-amber-900 dark:text-amber-100">
                        Konsekuensi:
                    </flux:text>
                </div>
                <ul class="text-sm text-amber-800 dark:text-amber-200 space-y-1.5 list-disc list-inside">
                    <li>Semua alokasi ruangan untuk <strong>minggu ini</strong> akan dihapus</li>
                    <li>Sistem akan generate alokasi baru dengan distribusi merata antar ruangan</li>
                    <li>Proses ini tidak bisa di-undo</li>
                </ul>
            </div>

            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                Yakin ingin melanjutkan generate dengan strategi Fair/LRA?
            </flux:text>

            <div class="flex justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button 
                    variant="ghost" 
                    @click="$wire.set('showFairConfirmModal', false)"
                    wire:loading.attr="disabled"
                >
                    Batal
                </flux:button>
                <flux:button 
                    variant="primary" 
                    icon="scale"
                    wire:click="generateFairAllocation"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="generateFairAllocation">Ya, Generate Fair/LRA</span>
                    <span wire:loading wire:target="generateFairAllocation" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Processing...
                    </span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal Export PDF Alokasi Ruangan --}}
    <flux:modal name="modal-export-ruangan-pdf" class="max-w-lg">
        <form method="POST" action="{{ route('admin.export.pdf') }}" target="_blank" class="space-y-6">
            @csrf
            <input type="hidden" name="from_modal" value="1">
            <div>
                <flux:heading size="lg">Export Alokasi Ruangan ke PDF</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    Download dokumen PDF alokasi ruangan mingguan dan komponen jadwal lainnya.
                </flux:text>
            </div>

            {{-- Pemilihan Periode WFO / Rentang Tanggal --}}
            <div x-data="{ mode: 'periode' }" class="space-y-3">
                <div class="flex items-center justify-between">
                    <flux:label class="font-medium text-sm">Rentang Jadwal</flux:label>
                    <div class="flex items-center gap-2">
                        <button 
                            type="button" 
                            @click="mode = (mode === 'periode' ? 'minggu' : 'periode')" 
                            class="text-xs text-blue-600 dark:text-blue-400 hover:underline"
                        >
                            <span x-text="mode === 'periode' ? 'Hanya Minggu Ini' : 'Pilih Periode Lengkap'"></span>
                        </button>
                    </div>
                </div>

                <div x-show="mode === 'periode'" x-data="{
                    open: false,
                    selectedId: '{{ $this->periodeAktif?->id ?? ($this->daftarPeriode->first()?->id ?? '') }}',
                    selectedLabel: '{{ $this->periodeAktif ? ($this->periodeAktif->nama . ' (' . $this->periodeAktif->tanggal_mulai?->format('d M Y') . ' - ' . $this->periodeAktif->tanggal_selesai?->format('d M Y') . ')' . ($this->periodeAktif->status === 'aktif' ? ' • [Aktif]' : '')) : ($this->daftarPeriode->first() ? ($this->daftarPeriode->first()->nama . ' (' . $this->daftarPeriode->first()->tanggal_mulai?->format('d M Y') . ' - ' . $this->daftarPeriode->first()->tanggal_selesai?->format('d M Y') . ')') : 'Pilih Periode') }}'
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
                        @foreach ($this->daftarPeriode as $p)
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
                        Mengekspor jadwal seluruh minggu dalam satu periode ini.
                    </flux:description>
                </div>

                <div x-show="mode === 'minggu'" x-cloak class="p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-800 text-sm">
                    <div class="font-medium text-zinc-800 dark:text-zinc-200">
                        Minggu yang sedang dilihat:
                    </div>
                    <div class="text-xs text-zinc-500 mt-0.5">
                        {{ \Carbon\Carbon::parse($this->tanggalMulaiMinggu)->isoFormat('D MMMM Y') }} s.d. {{ \Carbon\Carbon::parse($this->tanggalMulaiMinggu)->addDays(5)->isoFormat('D MMMM Y') }}
                    </div>
                    <input type="hidden" name="custom_tanggal" :value="mode === 'minggu' ? '1' : '0'">
                    <input type="hidden" name="tanggal_mulai" value="{{ $this->tanggalMulaiMinggu }}">
                    <input type="hidden" name="tanggal_selesai" value="{{ \Carbon\Carbon::parse($this->tanggalMulaiMinggu)->addDays(5)->toDateString() }}">
                </div>
            </div>

            {{-- Komponen Konten dengan Alpine.js --}}
            <div class="space-y-3" x-data="{
                allSelected: false,
                surat: false,
                wfo: false,
                kelompok: false,
                ruangan: true,
                adzan: false,
                briefing: false,
                toggleAll() {
                    this.allSelected = !this.allSelected;
                    this.surat = this.allSelected;
                    this.wfo = this.allSelected;
                    this.kelompok = this.allSelected;
                    this.ruangan = this.allSelected;
                    this.adzan = this.allSelected;
                    this.briefing = false;
                },
                selectOnly(type) {
                    this.surat = (type === 'wfo');
                    this.wfo = (type === 'wfo');
                    this.kelompok = (type === 'wfo');
                    this.ruangan = (type === 'ruangan');
                    this.adzan = (type === 'adzan');
                    this.briefing = false;
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
                    <button type="button" @click="selectOnly('ruangan')" class="px-2.5 py-1 text-xs rounded-md bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition">
                        Hanya Ruangan
                    </button>
                    <button type="button" @click="selectOnly('wfo')" class="px-2.5 py-1 text-xs rounded-md bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition">
                        Hanya WFO
                    </button>
                    <button type="button" @click="toggleAll()" class="px-2.5 py-1 text-xs rounded-md bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition">
                        Semua Jadwal
                    </button>
                </div>

                <div class="space-y-2 border border-zinc-200 dark:border-zinc-800 rounded-lg p-3 bg-zinc-50/50 dark:bg-zinc-900/50">
                    <label class="flex items-start gap-3 p-2 rounded hover:bg-white dark:hover:bg-zinc-800/80 cursor-pointer transition">
                        <input type="checkbox" name="include_ruangan" value="1" x-model="ruangan" class="mt-0.5 rounded border-zinc-300 dark:border-zinc-600 text-blue-600 shadow-sm focus:ring-blue-500">
                        <div class="text-sm">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">Jadwal Alokasi Ruangan Mingguan</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">Matriks pembagian ruangan tim WFO per hari & kapasitas</div>
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
                        <input type="checkbox" name="include_surat" value="1" x-model="surat" class="mt-0.5 rounded border-zinc-300 dark:border-zinc-600 text-blue-600 shadow-sm focus:ring-blue-500">
                        <div class="text-sm">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">Surat Resmi Pemberitahuan WFO</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">Surat pengantar resmi Inovindo dengan tanda tangan direktur</div>
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

            <div class="flex justify-end gap-3 pt-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="arrow-down-tray">
                    Download PDF
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <script>
        if (!window.badgeHTMLFixer) {
            window.badgeHTMLFixer = true;
            const timColors = [
                'bg-[#3B71CA] dark:bg-[#3B71CA] text-white border-[#2d5db3] dark:border-[#2d5db3]',
                'bg-red-500 dark:bg-red-600 text-white border-red-600 dark:border-red-700',
                'bg-green-500 dark:bg-green-600 text-white border-green-600 dark:border-green-700',
                'bg-amber-400 dark:bg-amber-500 text-zinc-900 dark:text-zinc-900 border-amber-500 dark:border-amber-600',
                'bg-purple-500 dark:bg-purple-600 text-white border-purple-600 dark:border-purple-700',
                'bg-stone-600 dark:bg-stone-700 text-white border-stone-700 dark:border-stone-800',
                'bg-pink-500 dark:bg-pink-600 text-white border-pink-600 dark:border-pink-700',
                'bg-slate-700 dark:bg-slate-800 text-white border-slate-800 dark:border-slate-900',
                'bg-orange-500 dark:bg-orange-600 text-white border-orange-600 dark:border-orange-700',
                'bg-emerald-500 dark:bg-emerald-600 text-white border-emerald-600 dark:border-emerald-700',
            ];

            const observer = new MutationObserver((mutations) => {
                mutations.forEach(mutation => {
                    const processNode = (node) => {
                        if (node.nodeValue && node.nodeValue.includes('[[TIM:')) {
                            let replaced = false;
                            let newValue = node.nodeValue.replace(/\[\[TIM:(.*?):(\d+)\]\]/g, (match, text, index) => {
                                replaced = true;
                                let classes = timColors[parseInt(index)] || timColors[0];
                                return `<strong class="px-1.5 py-0.5 rounded text-[11px] font-bold mx-0.5 shadow-sm inline-block ${classes}">${text}</strong>`;
                            });
                            if (replaced) {
                                const span = document.createElement('span');
                                span.innerHTML = newValue;
                                node.parentNode.replaceChild(span, node);
                            }
                        }
                    };

                    if (mutation.type === 'characterData') {
                        processNode(mutation.target);
                    } else if (mutation.type === 'childList') {
                        mutation.addedNodes.forEach(node => {
                            if (node.nodeType === Node.TEXT_NODE) {
                                processNode(node);
                            } else if (node.nodeType === Node.ELEMENT_NODE) {
                                const walker = document.createTreeWalker(node, NodeFilter.SHOW_TEXT, null, false);
                                let n;
                                const toReplace = [];
                                while ((n = walker.nextNode())) {
                                    if (n.nodeValue && n.nodeValue.includes('[[TIM:')) {
                                        toReplace.push(n);
                                    }
                                }
                                toReplace.forEach(n => processNode(n));
                            }
                        });
                    }
                });
            });
            observer.observe(document.body, { childList: true, subtree: true, characterData: true });
        }
    </script>
</div>



    