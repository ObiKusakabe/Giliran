<?php

use App\Models\JadwalWfo;
use App\Models\PeriodeWfo;
use App\Models\Tim;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Jadwal WFO')] #[Layout('layouts.admin')] class extends Component {

    public ?int $periodeId = null;
    public array $gridRowsCache = [];
    public bool $modalConfirmGenerate = false; // Confirmation modal before generate

    public const HARI = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'];

    public function mount(): void
    {
        $aktif           = PeriodeWfo::where('status', 'aktif')->first();
        $this->periodeId = $aktif?->id;
        $this->refreshGrid();
    }

    public function refreshGrid(): void
    {
        unset($this->gridRows);
        
        $this->gridRowsCache = $this->periodeId
            ? JadwalWfo::with('tim')
                ->where('periode_wfo_id', $this->periodeId)
                ->get()
                ->filter(fn ($row) => $row->tim !== null)
                ->map(fn ($row) => [
                    'id'            => $row->id,
                    'hari'          => $row->hari,
                    'tim_id'        => $row->tim_id,
                    'nama_tim'      => $row->tim->nama_tim,
                    'color_classes' => $this->getTimColorClasses($row->tim_id),
                ])
                ->values()
                ->toArray()
            : [];
        
        $this->dispatch('gridRowsUpdated', $this->gridRowsCache);
    }

    #[Computed]
    public function periodeOptions()
    {
        return PeriodeWfo::orderByDesc('tanggal_mulai')->get(['id', 'keterangan', 'status', 'tanggal_mulai']);
    }

    #[Computed]
    public function periodeDipilih(): ?PeriodeWfo
    {
        return $this->periodeId ? PeriodeWfo::find($this->periodeId) : null;
    }

    #[Computed]
    public function semuaTim()
    {
        return Tim::orderBy('nama_tim')->get(['id', 'nama_tim']);
    }

    /**
     * Grid sebagai array flat untuk Alpine:
     * [{ id, hari, tim_id, nama_tim }, ...]
     */
    #[Computed]
    public function gridRows(): array
    {
        if (! $this->periodeId) {
            return [];
        }

        return JadwalWfo::with('tim')
            ->where('periode_wfo_id', $this->periodeId)
            ->get()
            ->filter(fn ($row) => $row->tim !== null)
            ->map(fn ($row) => [
                'id'            => $row->id,
                'hari'          => $row->hari,
                'tim_id'        => $row->tim_id,
                'nama_tim'      => $row->tim->nama_tim,
                'color_classes' => $this->getTimColorClasses($row->tim_id),
            ])
            ->values()
            ->toArray();
    }

    /**
     * NEW: Get count of teams per day for max 7 validation
     */
    #[Computed]
    public function timCountPerHari(): array
    {
        if (! $this->periodeId) {
            return array_fill_keys(self::HARI, 0);
        }

        $counts = JadwalWfo::where('periode_wfo_id', $this->periodeId)
            ->select('hari', DB::raw('count(*) as total'))
            ->groupBy('hari')
            ->pluck('total', 'hari')
            ->toArray();

        // Ensure all days have a count (default 0)
        return array_merge(array_fill_keys(self::HARI, 0), $counts);
    }

    public function tambahTim(string $hari, int $timId): void
    {
        if (! $this->periodeId) {
            Flux::toast(variant: 'danger', text: 'Pilih periode terlebih dahulu.');
            return;
        }

        if (! in_array($hari, self::HARI)) {
            return;
        }

        // ✅ NEW: Check max 7 teams per day
        $countHariIni = JadwalWfo::where('periode_wfo_id', $this->periodeId)
            ->where('hari', $hari)
            ->count();

        if ($countHariIni >= 7) {
            Flux::toast(variant: 'danger', text: 'Maksimal 7 tim per hari sudah tercapai. Tidak bisa menambah tim lagi di hari ini.');
            return;
        }

        $sudahAda = JadwalWfo::where('periode_wfo_id', $this->periodeId)
            ->where('tim_id', $timId)
            ->where('hari', $hari)
            ->exists();

        if ($sudahAda) {
            // Seharusnya tidak sampai sini karena sudah difilter di client
            Flux::toast(variant: 'danger', text: 'Tim ini sudah ada di hari tersebut.');
            return;
        }

        $row = JadwalWfo::create([
            'periode_wfo_id' => $this->periodeId,
            'tim_id'         => $timId,
            'hari'           => $hari,
        ]);

        $tim = Tim::find($timId);

        // Kembalikan row baru ke Alpine supaya state client sync
        $this->dispatch('row-ditambah', row: [
            'id'            => $row->id,
            'hari'          => $hari,
            'tim_id'        => $timId,
            'nama_tim'      => $tim?->nama_tim ?? '',
            'color_classes' => $this->getTimColorClasses($timId),
        ]);

        $this->refreshGrid();
    }

    public function hapusTim(int $rowId): void
    {
        if (! $this->periodeId) {
            return;
        }

        JadwalWfo::where('id', $rowId)
            ->where(fn ($q) => $q->whereHas('periodeWfo', fn ($q2) => $q2->where('id', $this->periodeId)))
            ->delete();

        $this->dispatch('row-dihapus', rowId: $rowId);
        $this->refreshGrid();
    }

    public function hapusBulk(array $rowIds): void
    {
        if (! $this->periodeId || empty($rowIds)) {
            return;
        }

        JadwalWfo::whereIn('id', $rowIds)
            ->where(fn ($q) => $q->whereHas('periodeWfo', fn ($q2) => $q2->where('id', $this->periodeId)))
            ->delete();

        $this->refreshGrid();
    }

    public function hapusBulkWithToast(array $rowIds, int $count): void
    {
        $this->hapusBulk($rowIds);

        // Success toast dengan positioning kanan bawah akan di-trigger oleh Livewire event
        $this->dispatch('bulk-delete-success', count: $count);
    }

    public function pindahTim(int $rowId, string $hariTujuan): void
    {
        if (! $this->periodeId || $hariTujuan === '__trash') {
            if ($hariTujuan === '__trash') {
                $this->hapusTim($rowId);
            }
            return;
        }

        if (! in_array($hariTujuan, self::HARI)) {
            return;
        }

        $row = JadwalWfo::find($rowId);

        if (! $row || $row->hari === $hariTujuan) {
            return;
        }

        // Cek duplikat di hari tujuan
        $sudahAda = JadwalWfo::where('periode_wfo_id', $this->periodeId)
            ->where('tim_id', $row->tim_id)
            ->where('hari', $hariTujuan)
            ->exists();

        if ($sudahAda) {
            // Revert optimistic update di client
            $this->dispatch('revert-pindah', rowId: $rowId, hariAsal: $row->hari);
            Flux::toast(variant: 'danger', text: 'Tim ini sudah ada di hari '.$hariTujuan.'.');
            return;
        }

        $row->update(['hari' => $hariTujuan]);
        $this->refreshGrid();
    }
    
    /**
     * Get color classes for tim chip - warna sangat berbeda, hindari warna yang mirip
     * Urutan: Biru, Merah, Hijau, Kuning, Ungu, Coklat, Pink, Hitam, Orange, Teal
     */
    public function getTimColorClasses(int $timId): string
    {
        $colors = [
            // 1. Inovindo Blue (biru primary)
            'bg-[#3B71CA] dark:bg-[#3B71CA] text-white border-[#2d5db3] dark:border-[#2d5db3]',
            // 2. Red (merah - sangat berbeda dari biru)
            'bg-red-500 dark:bg-red-600 text-white border-red-600 dark:border-red-700',
            // 3. Green (hijau - berbeda dari merah dan biru)
            'bg-green-500 dark:bg-green-600 text-white border-green-600 dark:border-green-700',
            // 4. Yellow/Amber (kuning - berbeda dari hijau)
            'bg-amber-400 dark:bg-amber-500 text-zinc-900 dark:text-zinc-900 border-amber-500 dark:border-amber-600',
            // 5. Purple (ungu - berbeda dari kuning)
            'bg-purple-500 dark:bg-purple-600 text-white border-purple-600 dark:border-purple-700',
            // 6. Brown/Stone (coklat - berbeda dari ungu)
            'bg-stone-600 dark:bg-stone-700 text-white border-stone-700 dark:border-stone-800',
            // 7. Pink (pink cerah - berbeda dari coklat)
            'bg-pink-500 dark:bg-pink-600 text-white border-pink-600 dark:border-pink-700',
            // 8. Slate/Gray (abu gelap/hitam - berbeda dari pink)
            'bg-slate-700 dark:bg-slate-800 text-white border-slate-800 dark:border-slate-900',
            // 9. Orange (orange - berbeda dari abu)
            'bg-orange-500 dark:bg-orange-600 text-white border-orange-600 dark:border-orange-700',
            // 10. Emerald (hijau teal - berbeda dari orange)
            'bg-emerald-500 dark:bg-emerald-600 text-white border-emerald-600 dark:border-emerald-700',
        ];
        
        $index = ($timId - 1) % count($colors);
        return $colors[$index];
    }

    /**
     * Generate jadwal WFO otomatis - 6 tim per hari menggunakan algoritma LRA (Least Recently Allocated)
     */
    public function openGenerateModal(): void
    {
        if (! $this->periodeId) {
            Flux::toast(variant: 'danger', text: 'Pilih periode terlebih dahulu.');
            return;
        }

        $this->modalConfirmGenerate = true;
    }

    public function confirmGenerate(): void
    {
        // Don't close modal yet - let processing animation show inside modal
        $this->generateJadwalWfo();
        
        // Modal will close automatically after toast success (via wire:loading or manual close after success)
    }

    public function generateJadwalWfo(): void
    {
        if (! $this->periodeId) {
            Flux::toast(variant: 'danger', text: 'Pilih periode terlebih dahulu.');
            return;
        }

        $periode = PeriodeWfo::find($this->periodeId);
        if (! $periode) {
            Flux::toast(variant: 'danger', text: 'Periode tidak ditemukan.');
            return;
        }

        // Ambil semua tim aktif
        $semuaTim = Tim::whereNull('deleted_at')
            ->where('status', 'active')
            ->orderBy('nama_tim')
            ->get();

        $jumlahTim = $semuaTim->count();

        if ($jumlahTim < 1) {
            Flux::toast(variant: 'danger', text: 'Tidak ada tim aktif. Tambahkan tim terlebih dahulu.');
            return;
        }

        // Tentukan jumlah tim per hari (max 6, atau semua tim jika kurang dari 6)
        $timPerHari = min(6, $jumlahTim);

        // Hapus jadwal lama untuk periode ini
        JadwalWfo::where('periode_wfo_id', $this->periodeId)->delete();

        // Track frekuensi alokasi per tim (LRA algorithm)
        $timFrequency = [];
        foreach ($semuaTim as $tim) {
            $timFrequency[$tim->id] = 0;
        }

        $created = [];

        // Generate untuk setiap hari
        foreach (self::HARI as $hari) {
            // Sort tim berdasarkan frekuensi (ascending) dengan shuffle untuk randomness saat tie
            // Step 1: Collect dengan preserving keys (tim_id => frequency)
            $collection = collect($timFrequency);
            
            // Step 2: Sort by frequency (ascending) - LRA: yang paling jarang allocated duluan
            // sortBy() preserves keys, jadi keys masih tim IDs
            $sorted = $collection->sortBy(function ($freq, $timId) {
                return $freq;
            });
            
            // Step 3: Get tim IDs (keys) dalam urutan yang sudah di-sort
            $allTimIds = $sorted->keys()->all();
            
            // Step 4: Shuffle untuk tie-breaking
            shuffle($allTimIds);
            
            // Step 5: Sort lagi berdasarkan frequency (stable sort for tie-breaking)
            usort($allTimIds, function ($a, $b) use ($timFrequency) {
                return $timFrequency[$a] <=> $timFrequency[$b];
            });
            
            // Step 6: Ambil tim sesuai timPerHari (yang paling jarang allocated)
            $timHariIni = array_slice($allTimIds, 0, $timPerHari);

            foreach ($timHariIni as $timId) {
                $row = JadwalWfo::create([
                    'periode_wfo_id' => $this->periodeId,
                    'tim_id'         => $timId,
                    'hari'           => $hari,
                ]);

                $created[] = $row;

                // Increment frequency untuk tim ini
                $timFrequency[$timId]++;
            }
        }

        $this->refreshGrid();

        // Close modal after successful generation
        $this->modalConfirmGenerate = false;

        Flux::toast(
            variant: 'success',
            text: 'Jadwal WFO berhasil di-generate! Total: '.count($created).' alokasi ('.$timPerHari.' tim × '.count(self::HARI).' hari).'
        );
    }

    public function updatedPeriodeId(): void
    {
        $this->refreshGrid();
        unset($this->periodeDipilih);
    }
};
?>
<div
    x-data="{
        // State grid di client
        rows: [],
        semuaTim: @js($this->semuaTim->toArray()),

        dragging: null,
        overHari: null,
        overTrash: false,
        loadingRowIds: [],

        // ✨ Multi-select state
        selectedRowIds: [],
        
        // ✨ Drag-to-select box state
        isDrawingBox: false,
        boxStartX: 0,
        boxStartY: 0,
        boxCurrentX: 0,
        boxCurrentY: 0,

        // Helper: tim di hari tertentu
        timDiHari(hari) {
            if (!Array.isArray(this.rows)) return [];
            return this.rows.filter(r => r.hari === hari);
        },

        // Helper: tim yang belum ada di hari tertentu (untuk dropdown filter)
        timBelumDiHari(hari) {
            if (!Array.isArray(this.rows) || !Array.isArray(this.semuaTim)) return [];
            const sudahAda = this.timDiHari(hari).map(r => r.tim_id);
            return this.semuaTim.filter(t => !sudahAda.includes(t.id));
        },

        // ✨ Toggle selection (Shift+Click)
        toggleSelect(rowId, event) {
            if (event?.shiftKey) {
                const idx = this.selectedRowIds.indexOf(rowId);
                if (idx > -1) {
                    this.selectedRowIds.splice(idx, 1);
                } else {
                    this.selectedRowIds.push(rowId);
                }
            } else {
                if (this.selectedRowIds.includes(rowId)) {
                    this.selectedRowIds = [];
                } else {
                    this.selectedRowIds = [rowId];
                }
            }
        },

        isSelected(rowId) {
            return this.selectedRowIds.includes(rowId);
        },

        clearSelection() {
            this.selectedRowIds = [];
        },

        // ✨ Bulk delete
        hapusBulk() {
            if (!Array.isArray(this.selectedRowIds) || this.selectedRowIds.length === 0) {
                console.log('hapusBulk: no selection');
                return;
            }
            
            const ids = [...this.selectedRowIds];
            const count = ids.length;
            console.log('hapusBulk called with ids:', ids);
            
            // Show loading toast (kanan bawah)
            this.showToast('loading', `Menghapus ${count} tim...`);
            
            // Clear selection and update UI immediately
            this.clearSelection();
            
            // Optimistic UI update
            if (Array.isArray(this.rows)) {
                this.rows = this.rows.filter(r => !ids.includes(r.id));
            }
            
            // Call backend
            $wire.hapusBulkWithToast(ids, count);
        },

        // Show toast notification (kanan bawah)
        showToast(type, message) {
            const toastId = 'custom-toast-' + Date.now();
            const toast = document.createElement('div');
            toast.id = toastId;
            toast.className = 'pointer-events-auto w-full max-w-sm overflow-hidden rounded-lg bg-white dark:bg-zinc-900 shadow-lg transform transition-all duration-300 ease-in-out';
            
            const body = document.createElement('div');
            body.className = 'p-4';
            
            const flex = document.createElement('div');
            flex.className = 'flex items-start';
            
            const iconContainer = document.createElement('div');
            iconContainer.className = 'flex-shrink-0';
            
            if (type === 'loading') {
                const spinner = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                spinner.setAttribute('class', 'h-6 w-6 text-blue-500 animate-spin');
                spinner.setAttribute('fill', 'none');
                spinner.setAttribute('viewBox', '0 0 24 24');
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
                spinner.appendChild(circle);
                spinner.appendChild(path);
                iconContainer.appendChild(spinner);
            } else {
                const check = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                check.setAttribute('class', 'h-6 w-6 text-green-500');
                check.setAttribute('fill', 'none');
                check.setAttribute('viewBox', '0 0 24 24');
                check.setAttribute('stroke', 'currentColor');
                check.setAttribute('stroke-width', '2');
                const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                path.setAttribute('stroke-linecap', 'round');
                path.setAttribute('stroke-linejoin', 'round');
                path.setAttribute('d', 'M5 13l4 4L19 7');
                check.appendChild(path);
                iconContainer.appendChild(check);
            }
            
            const textContainer = document.createElement('div');
            textContainer.className = 'ml-3 w-0 flex-1 pt-0.5';
            
            const text = document.createElement('p');
            text.className = 'text-sm font-medium text-zinc-900 dark:text-zinc-100';
            text.textContent = message;
            
            textContainer.appendChild(text);
            flex.appendChild(iconContainer);
            flex.appendChild(textContainer);
            body.appendChild(flex);
            toast.appendChild(body);
            
            let container = document.getElementById('custom-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'custom-toast-container';
                container.className = 'pointer-events-none fixed inset-0 z-[200] flex items-end px-4 py-6 sm:items-end sm:px-6';
                const wrapper = document.createElement('div');
                wrapper.className = 'flex w-full flex-col items-end space-y-4';
                container.appendChild(wrapper);
                document.body.appendChild(container);
            }
            
            const wrapper = container.querySelector('div');
            wrapper.appendChild(toast);
            
            window.currentToastId = toastId;
            
            if (type === 'success') {
                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(100%)';
                    setTimeout(() => toast.remove(), 300);
                }, 3000);
            }
        },

        handleBulkDeleteSuccess(event) {
            const count = event.detail.count;
            const currentToast = document.getElementById(window.currentToastId);
            if (currentToast) {
                currentToast.remove();
            }
            this.showToast('success', `Berhasil menghapus ${count} tim dari jadwal WFO.`);
        },

        // ✨ Select all chips (Ctrl+A)
        selectAll() {
            if (!Array.isArray(this.rows)) return;
            this.selectedRowIds = this.rows.map(r => r.id);
        },

        // ✨ Drag-box selection
        startBoxSelection(event) {
            // Jangan start box selection jika:
            // 1. Click pada chip
            // 2. Click pada floating toolbar
            if (event.target.closest('[data-chip-row]') || event.target.closest('[data-floating-toolbar]')) {
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
            
            const chips = document.querySelectorAll('[data-chip-row]');
            const newSelection = [];
            
            chips.forEach(chip => {
                const chipRect = chip.getBoundingClientRect();
                if (!(chipRect.right < boxRect.left || 
                      chipRect.left > boxRect.right || 
                      chipRect.bottom < boxRect.top || 
                      chipRect.top > boxRect.bottom)) {
                    const rowId = parseInt(chip.dataset.chipRow);
                    if (rowId && !newSelection.includes(rowId)) {
                        newSelection.push(rowId);
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

        // Tambah tim: optimistic update langsung, server di background
        tambahTimOptimistic(hari, timId, namaTim) {
            const tempId = -Date.now(); // ID sementara negatif
            if (Array.isArray(this.rows)) {
                this.rows.push({ id: tempId, hari, tim_id: timId, nama_tim: namaTim });
            }
            $wire.tambahTim(hari, timId);
        },

        // Hapus tim: optimistic — sembunyikan dulu, tunggu server
        hapusTimOptimistic(rowId) {
            if (Array.isArray(this.loadingRowIds)) {
                this.loadingRowIds.push(rowId);
            }
            $wire.hapusTim(rowId);
        },

        // Drag start
        startDrag(row) {
            if (this.selectedRowIds.includes(row.id) && this.selectedRowIds.length > 1) {
                this.dragging = { 
                    isMulti: true,
                    rowIds: [...this.selectedRowIds],
                    hariAsal: row.hari,
                };
            } else {
                this.dragging = { 
                    isMulti: false,
                    rowId: row.id, 
                    hariAsal: row.hari, 
                    timId: row.tim_id, 
                    namaTim: row.nama_tim 
                };
            }
        },

        // Drop ke hari: optimistic pindah dulu
        dropKeHari(hariTujuan) {
            if (!this.dragging || this.dragging.hariAsal === hariTujuan) {
                this.dragging = null; this.overHari = null;
                return;
            }

            if (this.dragging.isMulti && Array.isArray(this.dragging.rowIds)) {
                const rowIds = this.dragging.rowIds;
                rowIds.forEach(rowId => {
                    if (Array.isArray(this.rows)) {
                        const idx = this.rows.findIndex(r => r.id === rowId);
                        if (idx !== -1) this.rows[idx].hari = hariTujuan;
                    }
                    if (Array.isArray(this.loadingRowIds)) {
                        this.loadingRowIds.push(rowId);
                    }
                    $wire.pindahTim(rowId, hariTujuan);
                });
                this.selectedRowIds = [];
            } else {
                const rowId = this.dragging.rowId;
                if (Array.isArray(this.rows)) {
                    const idx = this.rows.findIndex(r => r.id === rowId);
                    if (idx !== -1) this.rows[idx].hari = hariTujuan;
                }
                if (Array.isArray(this.loadingRowIds)) {
                    this.loadingRowIds.push(rowId);
                }
                $wire.pindahTim(rowId, hariTujuan);
            }

            this.dragging = null; this.overHari = null;
        },

        // Drop ke trash: optimistic hapus
        dropKeTrash() {
            if (!this.dragging) return;

            if (this.dragging.isMulti && Array.isArray(this.dragging.rowIds)) {
                const rowIds = this.dragging.rowIds;
                if (Array.isArray(this.rows)) {
                    this.rows = this.rows.filter(r => !rowIds.includes(r.id));
                }
                rowIds.forEach(id => $wire.hapusTim(id));
                this.selectedRowIds = [];
            } else {
                const rowId = this.dragging.rowId;
                if (Array.isArray(this.rows)) {
                    this.rows = this.rows.filter(r => r.id !== rowId);
                }
                $wire.hapusTim(rowId);
            }

            this.dragging = null; this.overTrash = false; this.overHari = null;
        },

        isLoading(rowId) {
            return Array.isArray(this.loadingRowIds) && this.loadingRowIds.includes(rowId);
        },

        // Event handlers
        handleRowDitambah(event) {
            const row = event.detail.row;
            if (!Array.isArray(this.rows)) return;
            const tempIdx = this.rows.findIndex(r => r.tim_id === row.tim_id && r.hari === row.hari && r.id < 0);
            if (tempIdx !== -1) this.rows[tempIdx].id = row.id;
            else this.rows.push(row);
        },

        handleRowDihapus(event) {
            if (Array.isArray(this.rows)) {
                this.rows = this.rows.filter(r => r.id !== event.detail.rowId);
            }
            if (Array.isArray(this.loadingRowIds)) {
                this.loadingRowIds = this.loadingRowIds.filter(id => id !== event.detail.rowId);
            }
        },

        handleRevertPindah(event) {
            const { rowId, hariAsal } = event.detail;
            if (Array.isArray(this.rows)) {
                const idx = this.rows.findIndex(r => r.id === rowId);
                if (idx !== -1) this.rows[idx].hari = hariAsal;
            }
            if (Array.isArray(this.loadingRowIds)) {
                this.loadingRowIds = this.loadingRowIds.filter(id => id !== rowId);
            }
        },

        // ── Touch drag polyfill ──────────────────────────────────────────────
        touchGhost: null,   // elemen visual yang mengikuti jari

        touchStartDrag(event, row) {
            // Cegah scroll saat drag dimulai
            event.preventDefault();
            this.startDrag(row);

            // Buat ghost element
            const chip = event.currentTarget;
            const ghost = chip.cloneNode(true);
            ghost.style.cssText = `
                position: fixed; pointer-events: none; z-index: 99999;
                opacity: 0.85; transform: scale(1.05);
                border-radius: 9999px; transition: none;
                box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            `;
            const rect = chip.getBoundingClientRect();
            const touch = event.touches[0];
            ghost._offsetX = touch.clientX - rect.left;
            ghost._offsetY = touch.clientY - rect.top;
            ghost.style.left = (touch.clientX - ghost._offsetX) + 'px';
            ghost.style.top  = (touch.clientY - ghost._offsetY) + 'px';
            ghost.style.width = rect.width + 'px';
            document.body.appendChild(ghost);
            this.touchGhost = ghost;
        },

        touchMoveDrag(event) {
            if (!this.dragging || !this.touchGhost) return;
            event.preventDefault();
            const touch = event.touches[0];
            this.touchGhost.style.left = (touch.clientX - this.touchGhost._offsetX) + 'px';
            this.touchGhost.style.top  = (touch.clientY - this.touchGhost._offsetY) + 'px';

            // Deteksi target: sembunyikan ghost sementara supaya elementsFromPoint dapat elemen di bawahnya
            this.touchGhost.style.display = 'none';
            const els = document.elementsFromPoint(touch.clientX, touch.clientY);
            this.touchGhost.style.display = '';

            // Cek apakah di atas card hari
            const hariCard = els.find(el => el.dataset.touchHari);
            const trashZone = els.find(el => el.dataset.touchTrash);

            this.overHari  = hariCard ? hariCard.dataset.touchHari : null;
            this.overTrash = !!trashZone;
        },

        touchEndDrag(event) {
            if (!this.dragging) return;
            // Hapus ghost
            if (this.touchGhost) {
                this.touchGhost.remove();
                this.touchGhost = null;
            }
            // Eksekusi drop
            if (this.overTrash) {
                this.dropKeTrash();
            } else if (this.overHari && this.overHari !== this.dragging.hariAsal) {
                this.dropKeHari(this.overHari);
            } else {
                this.dragging = null; this.overHari = null; this.overTrash = false;
            }
        }
        // ────────────────────────────────────────────────────────────────────
    }"
    x-init="
        rows = @js($this->gridRowsCache);
        $wire.on('gridRowsUpdated', (data) => {
            rows = Array.isArray(data[0]) ? data[0] : (Array.isArray(data) ? data : []);
        });
    "
    @row-ditambah.window="handleRowDitambah($event)"
    @row-dihapus.window="handleRowDihapus($event)"
    @revert-pindah.window="handleRevertPindah($event)"
    @bulk-delete-success.window="handleBulkDeleteSuccess($event)"
    @mousedown="startBoxSelection($event)"
    @mousemove="updateBoxSelection($event)"
    @mouseup="endBoxSelection()"
    @mouseleave="endBoxSelection()"
    @keydown.delete.window="selectedRowIds.length > 0 ? hapusBulk() : null"
    @keydown.backspace.window="selectedRowIds.length > 0 ? hapusBulk() : null"
    @keydown.ctrl.a.window.prevent="selectAll()"
    @keydown.escape.window="clearSelection()"
    class="flex flex-col gap-6"
>
    {{-- Header --}}
    <div class="flex flex-col gap-4">
        <div>
            <flux:heading size="xl">Jadwal WFO</flux:heading>
            <flux:text class="text-zinc-500 select-none">
                <strong>Click+Drag area kosong</strong> untuk multi-select, <strong>Shift+Click</strong> chip, atau <strong>Ctrl+A</strong> select semua. 
                Drag chip untuk pindah hari, drop ke
                <svg class="inline h-3.5 w-3.5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                untuk hapus.
            </flux:text>
        </div>
        
        <div class="flex flex-col sm:flex-row items-stretch sm:items-end gap-3">
            <div class="w-full sm:w-72">
                <x-searchable-select
                    name="periodeId"
                    label="Periode"
                    placeholder="— Pilih Periode —"
                    wire:model.live="periodeId"
                    :model-value="$periodeId"
                    :options="$this->periodeOptions->map(fn($p) => [
                        'value' => $p->id,
                        'label' => ($p->keterangan ?? $p->tanggal_mulai->format('M Y')) . ($p->status === 'aktif' ? ' (Aktif)' : ''),
                    ])->toArray()"
                />
            </div>
            @if ($this->periodeId)
                <flux:button
                    variant="primary"
                    wire:click="openGenerateModal"
                    icon="sparkles"
                    class="min-w-[180px]"
                >
                    <span wire:loading.remove wire:target="openGenerateModal">Generate Jadwal</span>
                    <span wire:loading wire:target="openGenerateModal" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Memuat...
                    </span>
                </flux:button>
            @endif
        </div>
    </div>

    @if (! $this->periodeId)
        <x-empty-state icon="calendar-days" title="Pilih periode WFO" description="Pilih periode dari dropdown di atas." />
    @else
        @if ($this->periodeDipilih)
            <flux:callout
                variant="{{ $this->periodeDipilih->isAktif() ? 'success' : 'warning' }}"
                icon="{{ $this->periodeDipilih->isAktif() ? 'check-circle' : 'exclamation-triangle' }}"
            >
                <flux:callout.heading>
                    {{ $this->periodeDipilih->keterangan ?? 'Periode ini' }}
                    — {{ $this->periodeDipilih->tanggal_mulai->translatedFormat('d M Y') }}
                    s/d {{ $this->periodeDipilih->tanggal_selesai->translatedFormat('d M Y') }}
                </flux:callout.heading>
                @if (! $this->periodeDipilih->isAktif())
                    <flux:callout.text>Periode ini tidak aktif. Perubahan pola tidak akan mempengaruhi jadwal yang sudah ter-generate.</flux:callout.text>
                @endif
            </flux:callout>
        @endif

        {{-- Grid 6 kolom --}}
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
            @foreach (['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'] as $hari)
                <flux:card
                    class="flex flex-col gap-3 p-3 min-h-[140px] transition-colors select-none"
                    x-bind:class="overHari === '{{ $hari }}' && dragging && dragging.hariAsal !== '{{ $hari }}' ? 'ring-2 ring-brand ring-offset-1 ring-offset-zinc-900 bg-brand/5' : ''"
                    data-touch-hari="{{ $hari }}"
                    @dragover.prevent="overHari = '{{ $hari }}'"
                    @dragleave="overHari = null"
                    @drop.prevent="dropKeHari('{{ $hari }}')"
                >
                    {{-- Header hari --}}
                    <div class="flex items-center justify-between pointer-events-none">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                {{ ucfirst($hari) }}
                            </span>
                            @php
                                $count = $this->timCountPerHari[$hari] ?? 0;
                                $isFull = $count >= 7;
                            @endphp
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-md {{ $isFull ? 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400' : 'bg-zinc-100 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300' }}">
                                {{ $count }}/7
                            </span>
                        </div>
                        @if ($isFull)
                            <flux:icon icon="lock-closed" variant="micro" class="size-3 text-red-500" />
                        @endif
                    </div>

                    {{-- Chip tim — Alpine rendered, draggable --}}
                    <div class="flex flex-wrap gap-1.5 items-start content-start">
                        <template x-for="row in timDiHari('{{ $hari }}')" :key="row.id">
                            <div style="display:contents">
                                <span
                                    :data-chip-row="row.id"
                                    draggable="true"
                                    @click="toggleSelect(row.id, $event)"
                                    @dragstart="startDrag(row)"
                                    @dragend="dragging = null; overHari = null; overTrash = false"
                                    @touchstart.prevent="touchStartDrag($event, row)"
                                    @touchmove="touchMoveDrag($event)"
                                    @touchend="touchEndDrag($event)"
                                    @mousedown.stop
                                    class="inline-flex items-center gap-1 rounded-full text-xs font-medium px-2 py-0.5 border cursor-grab active:cursor-grabbing touch-none transition-all"
                                    style="flex-shrink:0; width:auto; max-width:100%;"
                                    :class="[
                                        row.color_classes,
                                        isSelected(row.id) && 'ring-2 ring-blue-500',
                                        (dragging && (dragging.rowId === row.id || (dragging.isMulti && dragging.rowIds?.includes(row.id)))) && 'opacity-40 !cursor-grabbing'
                                    ]"
                                >
                                <span class="truncate max-w-[80px]" x-text="row.nama_tim" :title="row.nama_tim"></span>

                                {{-- Tombol X: loading spinner saat server processing --}}
                                <button
                                    @click.stop="hapusTimOptimistic(row.id)"
                                    :disabled="isLoading(row.id)"
                                    class="ml-0.5 flex-shrink-0 transition-colors focus:outline-none rounded-full p-0.5 hover:bg-black/10 dark:hover:bg-white/10"
                                    :class="isLoading(row.id) ? 'opacity-30 cursor-wait' : 'opacity-60 hover:opacity-100'"
                                >
                                    {{-- Spinner saat loading --}}
                                    <svg x-show="isLoading(row.id)" class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    {{-- X normal --}}
                                    <svg x-show="!isLoading(row.id)" class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor">
                                        <path d="M6 4.586L1.707.293A1 1 0 00.293 1.707L4.586 6 .293 10.293a1 1 0 101.414 1.414L6 7.414l4.293 4.293a1 1 0 001.414-1.414L7.414 6l4.293-4.293A1 1 0 0010.293.293L6 4.586z"/>
                                    </svg>
                                </button>
                                </span>
                            </div>
                        </template>
                    </div>

                    {{-- Dropdown tambah tim — hanya tampilkan yang belum ada di hari ini --}}
                    @php
                        $count = $this->timCountPerHari[$hari] ?? 0;
                        $isFull = $count >= 7;
                    @endphp
                    <div
                        x-data="{ open: false, q: '' }"
                        @click.outside="open = false; q = ''"
                        class="relative"
                    >
                        <button
                            @click="open = !open"
                            {{ $isFull ? 'disabled' : '' }}
                            class="flex items-center gap-1 w-full justify-center rounded-md border border-dashed px-2 py-1.5 text-xs transition-colors focus:outline-none {{ $isFull ? 'border-zinc-200 dark:border-zinc-700 text-zinc-300 dark:text-zinc-600 cursor-not-allowed' : 'border-zinc-300 dark:border-zinc-600 text-zinc-400 hover:border-[#3B71CA] hover:text-[#3B71CA] focus:ring-2 focus:ring-[#3B71CA]' }}"
                            {{ $isFull ? 'title="Maksimal 7 tim per hari sudah tercapai"' : '' }}
                        >
                            @if ($isFull)
                                <flux:icon icon="lock-closed" variant="micro" class="size-3.5" />
                                Max 7 Tim
                            @else
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                </svg>
                                Tambah Tim
                            @endif
                        </button>

                        @if (!$isFull)
                            <div
                                x-show="open"
                                x-transition
                                class="absolute bottom-full mb-1 left-0 z-50 w-52 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg"
                            >
                                <div class="p-2 border-b border-zinc-100 dark:border-zinc-700">
                                    <input x-model="q" type="text" placeholder="Cari tim…" @click.stop
                                        class="w-full rounded-md border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-[#3B71CA]" />
                                </div>
                                <ul class="max-h-40 overflow-y-auto py-1">
                                    {{-- Filter: tampilkan hanya tim yang belum ada di hari ini --}}
                                    <template x-for="tim in timBelumDiHari('{{ $hari }}').filter(t => !q || t.nama_tim.toLowerCase().includes(q.toLowerCase()))" :key="tim.id">
                                        <li>
                                            <button
                                                @click="tambahTimOptimistic('{{ $hari }}', tim.id, tim.nama_tim); open = false; q = ''"
                                                class="w-full text-left px-3 py-1.5 text-xs hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors truncate"
                                                x-text="tim.nama_tim"
                                            ></button>
                                    </li>
                                </template>
                                <li
                                    x-show="timBelumDiHari('{{ $hari }}').filter(t => !q || t.nama_tim.toLowerCase().includes(q.toLowerCase())).length === 0"
                                    class="px-3 py-2 text-xs text-zinc-400"
                                >
                                    <span x-show="timBelumDiHari('{{ $hari }}').length === 0">Semua tim sudah ada di hari ini.</span>
                                    <span x-show="timBelumDiHari('{{ $hari }}').length > 0 && q">Tidak ada tim ditemukan.</span>
                                </li>
                            </ul>
                        </div>
                        @endif
                    </div>
                </flux:card>
            @endforeach
        </div>

        <p class="text-xs text-zinc-400 text-center md:hidden">Scroll ke bawah untuk melihat semua hari.</p>
    @endif

    {{-- ✨ Floating Action: Multi-select Toolbar --}}
    <div
        x-cloak
        x-show="selectedRowIds.length > 0 && dragging === null"
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
            <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Tim Terpilih</span>
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
        x-show="dragging !== null"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-6"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-6"
        data-touch-trash="true"
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

    {{-- Modal: Confirmation Before Generate --}}
    <flux:modal wire:model="modalConfirmGenerate" class="max-w-md">
        {{-- Loading State: Show processing animation --}}
        <div wire:loading wire:target="confirmGenerate,generateJadwalWfo" class="flex flex-col items-center justify-center py-12 px-6">
            <svg class="animate-spin h-12 w-12 text-blue-600 dark:text-blue-400 mb-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <flux:heading size="lg" class="mb-2">Sedang Generate...</flux:heading>
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400 text-center">
                Mohon tunggu, sistem sedang membuat jadwal WFO dengan algoritma LRA.
            </flux:text>
            
            {{-- Processing Steps --}}
            <div class="mt-6 space-y-2 w-full max-w-sm">
                <div class="flex items-center gap-3 text-sm text-zinc-600 dark:text-zinc-400">
                    <div class="w-2 h-2 rounded-full bg-blue-600 animate-pulse"></div>
                    <span>Menghapus jadwal lama...</span>
                </div>
                <div class="flex items-center gap-3 text-sm text-zinc-600 dark:text-zinc-400">
                    <div class="w-2 h-2 rounded-full bg-blue-600 animate-pulse" style="animation-delay: 0.2s"></div>
                    <span>Mengambil data tim aktif...</span>
                </div>
                <div class="flex items-center gap-3 text-sm text-zinc-600 dark:text-zinc-400">
                    <div class="w-2 h-2 rounded-full bg-blue-600 animate-pulse" style="animation-delay: 0.4s"></div>
                    <span>Menghitung alokasi LRA...</span>
                </div>
                <div class="flex items-center gap-3 text-sm text-zinc-600 dark:text-zinc-400">
                    <div class="w-2 h-2 rounded-full bg-blue-600 animate-pulse" style="animation-delay: 0.6s"></div>
                    <span>Menyimpan ke database...</span>
                </div>
            </div>
        </div>

        {{-- Idle State: Show confirmation form --}}
        <div wire:loading.remove wire:target="confirmGenerate,generateJadwalWfo" class="flex flex-col gap-4">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                    <flux:icon icon="exclamation-triangle" class="size-5 text-amber-600 dark:text-amber-400" />
                </div>
                <div class="flex-1">
                    <flux:heading size="lg" class="mb-2">Konfirmasi Generate Jadwal WFO</flux:heading>
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                        Anda akan men-generate jadwal WFO untuk periode terpilih. Proses ini akan:
                    </flux:text>
                </div>
            </div>

            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
                <ul class="text-sm text-amber-800 dark:text-amber-200 space-y-1.5 list-disc list-inside">
                    <li><strong>Menghapus</strong> semua jadwal WFO lama di periode ini</li>
                    <li><strong>Membuat</strong> jadwal baru dengan algoritma LRA (Fair)</li>
                    <li>Mengalokasikan <strong>6 tim per hari</strong> secara merata</li>
                    <li>Proses ini <strong>tidak bisa di-undo</strong></li>
                </ul>
            </div>

            @if ($this->periodeDipilih)
                <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-3 space-y-1 text-sm">
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Periode:</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $this->periodeDipilih->keterangan ?? 'Periode ini' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Rentang:</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $this->periodeDipilih->tanggal_mulai->format('d/m/Y') }} – 
                            {{ $this->periodeDipilih->tanggal_selesai->format('d/m/Y') }}
                        </span>
                    </div>
                </div>
            @endif

            <div class="flex justify-end gap-2 pt-2">
                <flux:button 
                    variant="ghost" 
                    @click="$wire.set('modalConfirmGenerate', false)"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50 cursor-not-allowed"
                    wire:target="confirmGenerate,generateJadwalWfo"
                >
                    Batal
                </flux:button>
                <flux:button
                    variant="primary"
                    wire:click="confirmGenerate"
                    icon="sparkles"
                    wire:loading.attr="disabled"
                    wire:target="confirmGenerate,generateJadwalWfo"
                    class="min-w-[180px]"
                >
                    Generate Sekarang
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
