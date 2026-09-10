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
    public array $alokasiRowsCache = [];
    
    // Modal confirmations
    public bool $showBestFitConfirmModal = false;
    public bool $showFairConfirmModal = false;
    public bool $isGenerating = false;

    public function mount(): void
    {
        // Default: Senin minggu ini
        $this->tanggalMulaiMinggu = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();

        $aktif = PeriodeWfo::where('status', 'aktif')->first();
        $this->periodeId = $aktif?->id;
        
        // Cleanup orphaned allocations (tim yang sudah dihapus)
        $this->cleanupOrphanedAllocations();
        
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
        $end = $start->copy()->addDays(5);

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
        $end = $start->copy()->addDays(5);

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

        $this->refreshAlokasiCache();
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

        $this->refreshAlokasiCache();
    }

    public function hapusAlokasi(int $alokasiId): void
    {
        $alokasi = AlokasiRuangan::find($alokasiId);
        if ($alokasi) {
            $nama = $alokasi->tim?->nama_tim;
            $alokasi->delete();
            Flux::toast(variant: 'success', text: "Alokasi ruangan tim {$nama} berhasil dihapus.");
        }

        $this->refreshAlokasiCache();
    }

    public function hapusBulk(array $alokasiIds): void
    {
        if (empty($alokasiIds)) {
            return;
        }

        AlokasiRuangan::whereIn('id', $alokasiIds)->delete();

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

        $start = Carbon::parse($this->tanggalMulaiMinggu);
        $tanggalList = [];
        for ($i = 0; $i < 6; $i++) {
            $tanggalList[] = $start->copy()->addDays($i);
        }

        // Hapus alokasi lama untuk rentang minggu ini
        AlokasiRuangan::whereBetween('tanggal', [
            $tanggalList[0]->toDateString(),
            $tanggalList[5]->toDateString(),
        ])->delete();

        $scheduler = app(LraScheduler::class);
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
            'created_at' => now(),
            'updated_at' => now(),
        ], $hasil));

        Flux::toast(
            variant: 'success',
            text: 'Berhasil generate ' . count($hasil) . ' alokasi (Fair/LRA): Distribusi merata antar ruangan.',
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
        $tanggalList = [];
        for ($i = 0; $i < 6; $i++) {
            $tanggalList[] = $start->copy()->addDays($i);
        }

        // Hapus alokasi lama
        AlokasiRuangan::whereBetween('tanggal', [
            $tanggalList[0]->toDateString(),
            $tanggalList[5]->toDateString(),
        ])->delete();

        // Get semua tim WFO untuk minggu ini
        $namaHariIndo = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'];
        $timWfoPerHari = [];
        
        foreach ($tanggalList as $idx => $tgl) {
            $hari = $namaHariIndo[$idx];
            $tims = JadwalWfo::with('tim.personil')
                ->where('periode_wfo_id', $this->periodeAktif->id)
                ->where('hari', $hari)
                ->whereHas('tim', fn($q) => $q->where('status', 'active')->whereNull('deleted_at'))
                ->get()
                ->pluck('tim')
                ->filter()
                ->sortByDesc(fn($t) => $t->personil->count()); // Sort descending by team size
            
            $timWfoPerHari[$tgl->toDateString()] = $tims;
        }

        // Get available rooms sorted by capacity (ascending)
        $ruanganList = Ruangan::where('status', 'tersedia')
            ->orderBy('kapasitas', 'asc')
            ->get();

        if ($ruanganList->isEmpty()) {
            Flux::toast(variant: 'danger', text: 'Tidak ada ruangan tersedia.');
            return;
        }

        $hasil = [];

        // Best Fit Algorithm: For each team, find room with minimum waste
        foreach ($timWfoPerHari as $tanggal => $tims) {
            foreach ($tims as $tim) {
                $teamSize = $tim->personil->count();
                
                // Find room with minimum waste (closest fit)
                $bestRoom = null;
                $minWaste = PHP_INT_MAX;
                
                foreach ($ruanganList as $ruangan) {
                    // Check if room is already allocated on this date
                    $sudahDialokasi = collect($hasil)->first(fn($h) => 
                        $h['ruangan_id'] == $ruangan->id && $h['tanggal'] == $tanggal
                    );
                    
                    if ($sudahDialokasi) continue;
                    
                    // Calculate waste (unutilized capacity)
                    if ($ruangan->kapasitas >= $teamSize) {
                        $waste = $ruangan->kapasitas - $teamSize;
                        if ($waste < $minWaste) {
                            $minWaste = $waste;
                            $bestRoom = $ruangan;
                        }
                    }
                }
                
                // If found a suitable room, allocate
                if ($bestRoom) {
                    $hasil[] = [
                        'tim_id' => $tim->id,
                        'ruangan_id' => $bestRoom->id,
                        'tanggal' => $tanggal,
                    ];
                }
            }
        }

        if (empty($hasil)) {
            Flux::toast(variant: 'warning', text: 'Tidak dapat mengalokasikan tim. Kapasitas ruangan tidak mencukupi.');
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
            text: 'Berhasil generate ' . count($hasil) . ' alokasi (Best Fit): Maksimalkan utilisasi kapasitas ruangan.',
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
        timWfoMap: @js($this->timWfoPerTanggal),
        isDragging: false,
        scrollSpeed: 0,
        autoScrollTimer: null,
        
        // ✨ Multi-select state
        selectedRowIds: [],
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
                this.rows = Array.isArray(data[0]) ? data[0] : (Array.isArray(data) ? data : []);
                console.log('✅ Alokasi rows updated:', this.rows.length);
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
            window.addEventListener('dragend', () => {
                if (this.isDragging) {
                    this.stopAutoScrollLoop();
                    this.isDragging = false;
                }
            });

            // Direct synchronous sticky header on window scroll
            window.addEventListener('scroll', () => this.updateStickyHeader(), { passive: true });
            window.addEventListener('resize', () => this.updateStickyHeader(), { passive: true });
        },

        updateStickyHeader() {
            const table = this.$refs.tableRef;
            const thead = this.$refs.tableThead;
            if (!table || !thead) return;

            const navHeight = 56;
            const tableRect = table.getBoundingClientRect();
            const theadHeight = thead.offsetHeight || 48;
            const maxOffset = table.offsetHeight - theadHeight - 20;

            if (tableRect.top < navHeight && tableRect.bottom > navHeight + theadHeight) {
                const offset = Math.min(maxOffset, navHeight - tableRect.top);
                thead.style.transform = `translate3d(0, ${offset}px, 0)`;
                thead.style.zIndex = '40';
                thead.style.boxShadow = '0 4px 6px -1px rgba(0, 0, 0, 0.08)';
            } else {
                thead.style.transform = '';
                thead.style.zIndex = '';
                thead.style.boxShadow = '';
            }
        },

        handleAutoScroll(event) {
            if (!this.isDragging || !this.$refs.gridScroll) return;
            const container = this.$refs.gridScroll;
            const rect = container.getBoundingClientRect();

            // Check if cursor is roughly near container vertically
            if (event.clientY < rect.top - 60 || event.clientY > rect.bottom + 60) {
                this.stopAutoScrollLoop();
                return;
            }

            const mouseX = event.clientX;
            const edgeThreshold = 110;
            const leftBoundary = rect.left + 240; // 240px is sticky Ruangan column
            const rightBoundary = rect.right;

            if (mouseX > rightBoundary - edgeThreshold && mouseX <= rightBoundary + 40) {
                // Dragging near right edge -> auto scroll right
                const intensity = Math.min(1, Math.max(0.1, (mouseX - (rightBoundary - edgeThreshold)) / edgeThreshold));
                this.scrollSpeed = intensity * 18;
                this.startAutoScrollLoop();
            } else if (mouseX < leftBoundary + edgeThreshold && mouseX >= rect.left - 20) {
                // Dragging near left edge (Ruangan boundary) -> auto scroll left
                const intensity = Math.min(1, Math.max(0.1, ((leftBoundary + edgeThreshold) - mouseX) / edgeThreshold));
                this.scrollSpeed = -intensity * 18;
                this.startAutoScrollLoop();
            } else {
                this.stopAutoScrollLoop();
            }
        },

        startAutoScrollLoop() {
            if (this.autoScrollTimer) return;
            const step = () => {
                if (!this.isDragging || this.scrollSpeed === 0 || !this.$refs.gridScroll) {
                    this.stopAutoScrollLoop();
                    return;
                }
                this.$refs.gridScroll.scrollLeft += this.scrollSpeed;
                this.autoScrollTimer = requestAnimationFrame(step);
            };
            this.autoScrollTimer = requestAnimationFrame(step);
        },

        stopAutoScrollLoop() {
            if (this.autoScrollTimer) {
                cancelAnimationFrame(this.autoScrollTimer);
                this.autoScrollTimer = null;
            }
            this.scrollSpeed = 0;
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
            // Optimistic: remove from rows
            if (Array.isArray(this.rows)) {
                this.rows = this.rows.filter(r => r.id !== alokasiId);
            }
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
            this.stopAutoScrollLoop();
            this.isDragging = false;
            this.draggingItem = null;
            this.dragOverRuanganId = null;
            this.dragOverTanggal = null;
            this.overTrash = false;
        },

        dropKeTrash() {
            if (!this.draggingItem) return;

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

            this.draggingItem = null;
            this.overTrash = false;
            this.isDragging = false;
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
            this.stopAutoScrollLoop();
            this.isDragging = false;
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
    @mousedown="startBoxSelection($event)"
    @mousemove="updateBoxSelection($event)"
    @mouseup="endBoxSelection()"
    @mouseleave="endBoxSelection()"
    @keydown.delete.window="selectedRowIds.length > 0 ? hapusBulk() : null"
    @keydown.backspace.window="selectedRowIds.length > 0 ? hapusBulk() : null"
    @keydown.ctrl.a.window.prevent="selectAll()"
    @keydown.escape.window="clearSelection()"
>
    {{-- Top Header Section --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">
                Alokasi Ruangan Mingguan
            </flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400 mt-0.5">
                <strong>Click+Drag area kosong</strong> untuk multi-select, <strong>Shift+Click</strong> card, atau <strong>Ctrl+A</strong> select semua. 
                Drag card untuk pindah ruangan/tanggal, drop ke
                <svg class="inline h-3.5 w-3.5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                untuk hapus.
            </flux:text>
        </div>

        {{-- Week Navigator & Auto-Generate Button --}}
        <div class="flex items-center gap-2 flex-wrap">
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
            x-data="{ justChanged: false }"
            x-init="
                $watch('$wire.tanggalMulaiMinggu', () => {
                    justChanged = true;
                    setTimeout(() => justChanged = false, 800);
                })
            "
            :class="justChanged ? 'bg-blue-100/50 dark:bg-blue-900/20' : ''"
        >
            <div class="flex items-center gap-2">
                <span class="size-2.5 rounded-full bg-[#3B71CA] animate-pulse"></span>
                <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                    <span wire:loading.remove wire:target="prevWeek,nextWeek">
                        Rentang: {{ Carbon::parse($tanggalMulaiMinggu)->translatedFormat('d F Y') }} – {{ Carbon::parse($tanggalMulaiMinggu)->addDays(5)->translatedFormat('d F Y') }}
                    </span>
                    <span wire:loading wire:target="prevWeek,nextWeek" class="flex items-center gap-2">
                        Memuat rentang minggu...
                    </span>
                </span>
            </div>
            <div class="flex items-center gap-3 text-xs text-zinc-500">
                <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-[#3B71CA]"></span> Drag & drop kartu tim untuk swap ruangan</span>
            </div>
        </div>

        {{-- Single Table Container (Horizontal scrollable, natural full height) --}}
        <div
            x-ref="gridScroll"
            @dragover="handleAutoScroll($event)"
            class="overflow-x-auto relative"
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
                style="min-width: 1100px;"
            >
                <colgroup>
                    <col style="width: 220px; min-width: 220px;">
                    <col style="width: 146px; min-width: 146px;">
                    <col style="width: 146px; min-width: 146px;">
                    <col style="width: 146px; min-width: 146px;">
                    <col style="width: 146px; min-width: 146px;">
                    <col style="width: 146px; min-width: 146px;">
                    <col style="width: 146px; min-width: 146px;">
                </colgroup>
                <thead
                    x-ref="tableThead"
                    class="relative z-40 bg-zinc-100 dark:bg-zinc-900 transition-none"
                    style="will-change: transform;"
                >
                    <tr class="bg-zinc-100 dark:bg-zinc-900 text-xs font-semibold text-zinc-600 dark:text-zinc-300">
                        {{-- Kolom Ruangan: Sticky Left --}}
                        <th class="p-3.5 ps-5 border-b border-r border-zinc-200 dark:border-zinc-800 select-none sticky left-0 z-50 bg-zinc-100 dark:bg-zinc-900 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.06)]">
                            <div class="flex items-center gap-2 font-semibold text-xs uppercase tracking-wider text-zinc-600 dark:text-zinc-400 whitespace-nowrap">
                                <flux:icon icon="building-office-2" class="size-4 text-zinc-400 shrink-0" />
                                <span>Ruangan</span>
                            </div>
                        </th>
                        {{-- Kolom Hari --}}
                        @foreach ($this->daftarHariMingguIni as $h)
                            <th class="p-3.5 text-center border-b border-r border-zinc-200 dark:border-zinc-800 select-none relative z-40 bg-zinc-100 dark:bg-zinc-900 {{ $h['is_today'] ? '!bg-blue-50/90 dark:!bg-blue-950/50 text-blue-600 dark:text-blue-400' : '' }}">
                                <div class="font-bold text-sm">{{ $h['nama'] }}</div>
                                <div class="text-[11px] font-normal opacity-80 mt-0.5">{{ $h['label_tanggal'] }}</div>
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
                                    class="p-2 border-b border-r border-zinc-200 dark:border-zinc-800 align-top transition-colors relative"
                                    :class="{
                                        'bg-[#3B71CA]/10 ring-2 ring-[#3B71CA] ring-inset rounded-lg': dragOverRuanganId == {{ $ruangan->id }} && dragOverTanggal == '{{ $h['tanggal'] }}',
                                        'bg-[#3B71CA]/5 dark:bg-[#3B71CA]/5': {{ $h['is_today'] ? 'true' : 'false' }}
                                    }"
                                    @dragover="dragOver($event, {{ $ruangan->id }}, '{{ $h['tanggal'] }}')"
                                    @dragleave="dragLeave($event, {{ $ruangan->id }}, '{{ $h['tanggal'] }}')"
                                    @drop="dropItem($event, {{ $ruangan->id }}, '{{ $h['tanggal'] }}')"
                                >
                                    <div class="min-h-[72px] flex flex-col justify-center">
                                        {{-- Assigned Team Card --}}
                                        <template x-if="getAllocation({{ $ruangan->id }}, '{{ $h['tanggal'] }}')">
                                            <div
                                                :data-alokasi-card="getAllocation({{ $ruangan->id }}, '{{ $h['tanggal'] }}').id"
                                                draggable="true"
                                                @click="toggleSelect(getAllocation({{ $ruangan->id }}, '{{ $h['tanggal'] }}').id, $event)"
                                                @dragstart="dragStart($event, getAllocation({{ $ruangan->id }}, '{{ $h['tanggal'] }}'))"
                                                @dragend="dragEnd($event)"
                                                @mousedown.stop
                                                class="group/card relative p-2.5 rounded-xl border shadow-xs hover:shadow-md transition-all cursor-grab active:cursor-grabbing select-none"
                                                :class="[
                                                    getAllocation({{ $ruangan->id }}, '{{ $h['tanggal'] }}').color_classes || 'bg-blue-50 dark:bg-blue-950/50 border-blue-200 dark:border-blue-800/60 hover:border-blue-400 dark:hover:border-blue-600',
                                                    isSelected(getAllocation({{ $ruangan->id }}, '{{ $h['tanggal'] }}').id) && 'ring-2 ring-blue-500',
                                                    (draggingItem && ((draggingItem.id === getAllocation({{ $ruangan->id }}, '{{ $h['tanggal'] }}').id) || (draggingItem.isMulti && draggingItem.ids?.includes(getAllocation({{ $ruangan->id }}, '{{ $h['tanggal'] }}').id)))) && 'opacity-40'
                                                ]"
                                            >
                                                <div class="flex items-start justify-between gap-1.5">
                                                    <div class="min-w-0 flex-1">
                                                        <div class="font-semibold text-xs truncate leading-tight" x-text="getAllocation({{ $ruangan->id }}, '{{ $h['tanggal'] }}').nama_tim"></div>
                                                        
                                                        {{-- Capacity info with occupancy percentage --}}
                                                        <div class="flex items-center gap-1.5 text-[10px] font-medium mt-1.5">
                                                            <span class="inline-block size-1.5 rounded-full bg-current"></span>
                                                            <span x-text="getAllocation({{ $ruangan->id }}, '{{ $h['tanggal'] }}').personil_count + '/' + getAllocation({{ $ruangan->id }}, '{{ $h['tanggal'] }}').kapasitas + ' orang'"></span>
                                                            <span class="opacity-60">•</span>
                                                            <span 
                                                                class="px-1.5 py-0.5 rounded font-semibold"
                                                                :class="getAllocation({{ $ruangan->id }}, '{{ $h['tanggal'] }}').is_over_capacity 
                                                                    ? 'bg-red-500/90 text-white' 
                                                                    : 'bg-white/90 dark:bg-zinc-900/90 text-zinc-900 dark:text-white'"
                                                                x-text="Math.round((getAllocation({{ $ruangan->id }}, '{{ $h['tanggal'] }}').personil_count / getAllocation({{ $ruangan->id }}, '{{ $h['tanggal'] }}').kapasitas) * 100) + '%'"
                                                            ></span>
                                                        </div>
                                                    </div>

                                                    {{-- Delete allocation button --}}
                                                    <button
                                                        type="button"
                                                        @click.stop="hapusSingle(getAllocation({{ $ruangan->id }}, '{{ $h['tanggal'] }}').id)"
                                                        class="opacity-0 group-hover/card:opacity-100 p-1 hover:bg-black/10 dark:hover:bg-white/10 rounded-md transition-all"
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
</div>


    