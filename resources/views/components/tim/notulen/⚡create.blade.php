<?php

use App\Models\JadwalBriefing;
use App\Models\JadwalWfo;
use App\Models\NotulenBriefing;
use App\Models\PeriodeWfo;
use App\Models\Personil;
use App\Services\NotulenBriefingService;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Isi Notulen Briefing')] #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;

    public string $tanggal;
    public string $sesi = 'pagi';
    public string $nama_notulen;
    public array $peserta_selected = [];
    public string $catatan = '';
    public $file = null;

    // Data personil untuk autocomplete
    public string $search_query = '';
    public array $search_results = [];
    public bool $show_dropdown = false;
    
    // Preloaded tim members for instant search
    public array $timMembers = []; // All tim members loaded once

    // FASE 4.2: WFH properties
    public bool $isTimWfh = false;
    public ?NotulenBriefing $existingNotulenWfh = null;
    
    // FASE 4.2 - Task #4: WFH-specific form fields
    // Note: penulisPersonilId is now auto-filled from assignedPenulisId (from JadwalBriefing)
    
    public array $temanWfhIds = []; // Optional teammates IDs for WFH (multi-select)
    public ?int $selectedTemanToAdd = null; // Temp storage for searchable-select
    
    // Per-person notulen notes (NEW structure)
    public string $penulisNotes = ''; // Notes from the creator (current user)
    public array $rekanNotes = []; // Notes from each teammate [personil_id => note_text]
    
    // Auto-filled personil penulis from jadwal briefing
    public ?string $assignedPenulisNama = null;
    public ?int $assignedPenulisId = null;

    public function mount(): void
    {
        // Auto-fill tanggal (today) - use dev mode aware time
        $this->tanggal = \App\Helpers\DevModeHelper::now()->toDateString();

        // Auto-fill nama notulen with tim name
        $this->nama_notulen = Auth::user()->tim?->nama_tim ?? Auth::user()->name;

        // Auto-detect sesi based on current time - use dev mode aware time
        // Pagi: 08:30 - 11:00
        // Sore: 13:00 - 18:00
        $currentTime = \App\Helpers\DevModeHelper::now();
        $hour = $currentTime->hour;
        $minute = $currentTime->minute;
        
        // Convert to minutes since midnight for easier comparison
        $currentMinutes = ($hour * 60) + $minute;
        $pagiStart = (8 * 60) + 30;  // 08:30 = 510 minutes
        $pagiEnd = 11 * 60;           // 11:00 = 660 minutes
        $soreStart = 13 * 60;         // 13:00 = 780 minutes
        $soreEnd = 18 * 60;           // 18:00 = 1080 minutes
        
        if ($currentMinutes >= $pagiStart && $currentMinutes < $pagiEnd) {
            $this->sesi = 'pagi';
        } elseif ($currentMinutes >= $soreStart && $currentMinutes < $soreEnd) {
            $this->sesi = 'sore';
        } else {
            // Default to pagi if outside working hours
            $this->sesi = 'pagi';
        }

        // FASE 4.2 - Task #3: Check if tim is WFH on selected date
        $this->checkIfTimWfh();
        
        // Load all tim members once for instant autocomplete
        $this->loadTimMembers();
        
        // Load assigned personil penulis from jadwal briefing
        $this->loadAssignedPenulis();
    }
    
    /**
     * Load all tim members upfront for instant autocomplete (no debounce needed).
     */
    private function loadTimMembers(): void
    {
        $timId = Auth::user()->tim_id;
        
        if ($timId) {
            $this->timMembers = Personil::where('tim_id', $timId)
                ->orderBy('nama')
                ->get(['id', 'nama'])
                ->toArray();
        }
    }
    
    /**
     * Load assigned personil penulis from JadwalBriefing for selected date & sesi.
     */
    private function loadAssignedPenulis(): void
    {
        $timId = Auth::user()->tim_id;
        
        if (!$timId || !$this->tanggal) {
            $this->assignedPenulisNama = null;
            $this->assignedPenulisId = null;
            return;
        }
        
        $jadwal = JadwalBriefing::where('tim_id', $timId)
            ->where('tanggal', $this->tanggal)
            ->where('sesi', $this->sesi)
            ->with('personil')
            ->first();
        
        if ($jadwal && $jadwal->personil) {
            $this->assignedPenulisNama = $jadwal->personil->nama;
            $this->assignedPenulisId = $jadwal->personil->id;
        } else {
            $this->assignedPenulisNama = null;
            $this->assignedPenulisId = null;
        }
    }

    /**
     * FASE 4.2 - Task #3: Re-check WFH status when tanggal changes.
     */
    public function updatedTanggal(): void
    {
        $this->checkIfTimWfh();
        $this->loadAssignedPenulis();
    }
    
    /**
     * Build combined catatan from per-person notes (WFH mode).
     */
    private function buildCombinedCatatan(): string
    {
        $parts = [];
        
        // Penulis section
        $penulisName = $this->penulisName ?: 'Penulis';
        $parts[] = "=== {$penulisName} (Penulis) ===\n{$this->penulisNotes}";
        
        // Rekan sections
        foreach ($this->temanWfhIds as $personilId) {
            $rekanName = $this->selectedTemanNames[$personilId] ?? "Rekan #{$personilId}";
            $rekanNote = $this->rekanNotes[$personilId] ?? '';
            
            if (!empty($rekanNote)) {
                $parts[] = "=== {$rekanName} ===\n{$rekanNote}";
            }
        }
        
        return implode("\n\n", $parts);
    }
    
    /**
     * FASE 4.2 - Task #3: Re-check existing notulen when sesi changes.
     * Also reload assigned penulis from jadwal.
     */
    public function updatedSesi(): void
    {
        $this->loadAssignedPenulis();
        
        if ($this->isTimWfh) {
            $this->checkExistingNotulenWfh();
        }
    }

    /**
     * Check if current time is within allowed time window for the selected session.
     * Pagi: 08:50 - 11:00
     * Sore: 16:50 - 18:00
     */
    #[Computed]
    public function isPastDeadline(): bool
    {
        if (! $this->tanggal) {
            return false;
        }

        $currentTime = \App\Helpers\DevModeHelper::now();
        $tanggalNotulen = Carbon::parse($this->tanggal);
        
        // Check if selected date is in the past (not today)
        if ($currentTime->toDateString() !== $tanggalNotulen->toDateString()) {
            // If tanggal is past date, it's definitely past deadline
            if ($currentTime->greaterThan($tanggalNotulen->endOfDay())) {
                return true;
            }
            // If tanggal is future date, not past deadline yet
            return false;
        }

        // If tanggal is today, check time window based on sesi
        if ($this->sesi === 'pagi') {
            // Pagi window: 08:50 - 11:00
            $windowStart = $tanggalNotulen->copy()->setTime(8, 50);
            $windowEnd = $tanggalNotulen->copy()->setTime(11, 0);
        } else {
            // Sore window: 16:50 - 18:00
            $windowStart = $tanggalNotulen->copy()->setTime(16, 50);
            $windowEnd = $tanggalNotulen->copy()->setTime(18, 0);
        }

        // Past deadline if current time is outside window
        return $currentTime->lessThan($windowStart) || $currentTime->greaterThan($windowEnd);
    }

    /**
     * Get deadline formatted string for display.
     */
    #[Computed]
    public function deadlineFormatted(): string
    {
        if (! $this->tanggal) {
            return '';
        }

        $tanggalNotulen = Carbon::parse($this->tanggal);
        
        if ($this->sesi === 'pagi') {
            // Pagi deadline: 11:00
            $deadline = $tanggalNotulen->copy()->setTime(11, 0);
        } else {
            // Sore deadline: 18:00
            $deadline = $tanggalNotulen->copy()->setTime(18, 0);
        }

        return $deadline->translatedFormat('l, d F Y \p\u\k\u\l H:i');
    }

    /**
     * FASE 4.2 - Task #1: Check if current tim is WFH on selected date.
     * 
     * Logic: Tim is WFH if NOT in JadwalWfo for that day.
     */
    private function checkIfTimWfh(): void
    {
        if (! $this->tanggal) {
            $this->isTimWfh = false;
            return;
        }

        $tanggalCarbon = Carbon::parse($this->tanggal);
        $namaHari = match ($tanggalCarbon->dayOfWeekIso) {
            1 => 'senin', 2 => 'selasa', 3 => 'rabu',
            4 => 'kamis', 5 => 'jumat', 6 => 'sabtu',
            7 => 'minggu',
        };
        
        $periode = PeriodeWfo::where('status', 'aktif')->first();
        if (! $periode) {
            $this->isTimWfh = false;
            return;
        }

        // Check if tim is IN jadwal WFO for this date
        $timWfoOnDate = JadwalWfo::where('periode_wfo_id', $periode->id)
            ->where('hari', $namaHari)
            ->where('tim_id', Auth::user()->tim_id)
            ->exists();

        // If NOT in WFO jadwal → means WFH
        $this->isTimWfh = ! $timWfoOnDate;

        // If WFH, check if notulen WFH already exists (for race condition)
        if ($this->isTimWfh) {
            $this->checkExistingNotulenWfh();
        }
    }

    /**
     * FASE 4.2 - Task #2: Check if notulen WFH already exists for selected date+sesi.
     * 
     * This prevents race condition (2 users creating notulen simultaneously).
     */
    private function checkExistingNotulenWfh(): void
    {
        if (! $this->tanggal || ! $this->sesi) {
            $this->existingNotulenWfh = null;
            return;
        }

        $this->existingNotulenWfh = NotulenBriefing::where('tanggal', $this->tanggal)
            ->where('sesi', $this->sesi)
            ->where('jenis_kehadiran', 'wfh')
            ->whereHas('timYangTerlibat', fn ($q) => $q->where('tim_id', Auth::user()->tim_id))
            ->with(['user', 'pesertaWfh'])
            ->first();
    }

    /**
     * FASE 4.2 - Task #4: Get teammates dari tim yang sama untuk WFH multi-select.
     * 
     * Returns personil aktif dari tim yang sama (exclude self).
     */
    #[Computed]
    public function temanSeTimOptions()
    {
        return Personil::where('tim_id', Auth::user()->tim_id)
            ->where('status', 'aktif')
            ->where('id', '!=', Auth::id()) // Exclude self (creator always included)
            ->orderBy('nama')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'nama' => $p->nama,
            ]);
    }

    /**
     * FASE 4.2 - Task #4: Search teman WFH with autocomplete.
     */
    public function updatedSearchTemanWfh(): void
    {
        if (trim($this->searchTemanWfh) === '') {
            $this->searchTemanResults = [];
            $this->showTemanDropdown = false;
            return;
        }

        $query = strtolower($this->searchTemanWfh);
        
        $this->searchTemanResults = $this->temanSeTimOptions
            ->filter(function ($p) use ($query) {
                // Exclude already selected
                if (in_array($p['id'], $this->temanWfhIds)) {
                    return false;
                }
                
                return str_contains(strtolower($p['nama']), $query);
            })
            ->take(10)
            ->values()
            ->toArray();

        $this->showTemanDropdown = count($this->searchTemanResults) > 0;
    }

    /**
     * FASE 4.2 - Task #4: Add teman to WFH participants.
     */
    /**
     * FASE 4.2 - Task #4: Select penulis personil.
     */
    public function selectPenulis(int $id): void
    {
        // No-op: penulis is now auto-filled from assignedPenulisId
    }
    
    /**
     * FASE 4.2 - Task #4: Clear penulis selection. (DEPRECATED)
     */
    public function clearPenulis(): void
    {
        // No-op: penulis is now auto-filled from assignedPenulisId
    }

    /**
     * FASE 4.2 - Task #4: Add teman to WFH participants.
     */
    public function addTemanWfh(int $id): void
    {
        if (! in_array($id, $this->temanWfhIds)) {
            $this->temanWfhIds[] = $id;
        }
    }

    /**
     * FASE 4.2 - Task #4: Remove teman from WFH participants.
     */
    public function removeTemanWfh(int $id): void
    {
        $this->temanWfhIds = array_values(
            array_filter($this->temanWfhIds, fn ($tid) => $tid !== $id)
        );
    }

    /**
     * FASE 4.2 - Task #4: Get selected teman names for display.
     */
    #[Computed]
    public function selectedTemanNames(): array
    {
        if (empty($this->temanWfhIds)) {
            return [];
        }

        return Personil::whereIn('id', $this->temanWfhIds)
            ->orderBy('nama')
            ->pluck('nama', 'id')
            ->toArray();
    }
    
    /**
     * Available rekan options (excludes penulis and already selected).
     */
    #[Computed]
    public function availableRekanOptions(): array
    {
        return collect($this->timMembers)
            ->filter(function ($member) {
                return $member['id'] !== $this->assignedPenulisId 
                    && !in_array($member['id'], $this->temanWfhIds);
            })
            ->map(fn ($m) => ['value' => $m['id'], 'label' => $m['nama']])
            ->values()
            ->toArray();
    }
    
    /**
     * Get penulis name for display.
     */
    #[Computed]
    public function penulisName(): string
    {
        if (!$this->assignedPenulisId) {
            return '';
        }
        
        $personil = collect($this->timMembers)->firstWhere('id', $this->assignedPenulisId);
        return $personil['nama'] ?? $this->assignedPenulisNama ?? '';
    }
    
    /**
     * Watch for rekan selection and add to array.
     */
    public function updatedSelectedTemanToAdd($value): void
    {
        if ($value && !in_array($value, $this->temanWfhIds)) {
            $this->temanWfhIds[] = $value;
        }
        
        // Reset selection
        $this->selectedTemanToAdd = null;
    }

    /**
     * Get list of personil from ALL tim yang WFO on selected date.
     */
    #[Computed]
    public function personilOptions()
    {
        $tanggal = $this->tanggal;
        $tanggalCarbon = Carbon::parse($tanggal);
        $namaHari = match ($tanggalCarbon->dayOfWeekIso) {
            1 => 'senin', 2 => 'selasa', 3 => 'rabu',
            4 => 'kamis', 5 => 'jumat', 6 => 'sabtu',
            7 => 'minggu',
        };

        // Get periode aktif
        $periode = PeriodeWfo::where('status', 'aktif')->first();
        if (! $periode) {
            return collect();
        }

        // Get all tim WFO on that day
        $timWfoIds = JadwalWfo::where('periode_wfo_id', $periode->id)
            ->where('hari', $namaHari)
            ->pluck('tim_id');

        // Get all personil from those tim
        return Personil::whereIn('tim_id', $timWfoIds)
            ->where('status', 'aktif')
            ->with('tim')
            ->orderBy('tim_id')
            ->orderBy('nama')
            ->get();
    }

    public function updatedSearchQuery(): void
    {
        if (trim($this->search_query) === '') {
            $this->search_results = [];
            $this->show_dropdown = false;

            return;
        }

        // Filter personil based on search query
        $query = strtolower($this->search_query);
        $this->search_results = $this->personilOptions
            ->filter(function ($p) use ($query) {
                // Exclude already selected personil
                if (in_array($p->id, array_column($this->peserta_selected, 'id'))) {
                    return false;
                }

                return str_contains(strtolower($p->nama), $query) ||
                       str_contains(strtolower($p->tim->nama_tim ?? ''), $query);
            })
            ->take(10)
            ->map(fn ($p) => [
                'id' => $p->id,
                'nama' => $p->nama,
                'tim' => $p->tim->nama_tim ?? '',
            ])
            ->toArray();

        $this->show_dropdown = count($this->search_results) > 0;
    }

    public function selectPersonil(int $id, string $nama, string $tim = ''): void
    {
        // Add to selected list
        if (! in_array($id, array_column($this->peserta_selected, 'id'))) {
            $this->peserta_selected[] = ['id' => $id, 'nama' => $nama, 'tim' => $tim];
        }

        // Clear search
        $this->search_query = '';
        $this->search_results = [];
        $this->show_dropdown = false;
    }

    public function removePersonil(int $id): void
    {
        $this->peserta_selected = array_filter(
            $this->peserta_selected,
            fn ($p) => $p['id'] !== $id
        );
        $this->peserta_selected = array_values($this->peserta_selected); // Re-index
    }

    /**
     * FASE 4.2 - Task #5: Simpan notulen WFH dengan pessimistic lock untuk handle race condition.
     * 
     * First-come-first-serve: Only 1 user can create notulen WFH for tim+date+sesi.
     * Uses DB transaction + lockForUpdate() untuk prevent duplicate submissions.
     */
    public function simpanNotulenWfh()
    {
        // Build combined catatan from all per-person notes
        $combinedCatatan = $this->buildCombinedCatatan();
        
        // Validation
        $this->validate([
            'tanggal' => 'required|date',
            'sesi' => 'required|in:pagi,sore',
            'nama_notulen' => 'required|string|max:255',
            'penulisNotes' => 'required|string|min:50|max:5000',
            'temanWfhIds' => 'nullable|array',
            'temanWfhIds.*' => 'exists:personil,id',
        ], [
            'penulisNotes.required' => 'Catatan penulis wajib diisi.',
            'penulisNotes.min' => 'Catatan penulis minimal 50 karakter.',
            'penulisNotes.max' => 'Catatan penulis maksimal 5000 karakter.',
        ]);
        
        // Validate assigned penulis exists
        if (!$this->assignedPenulisId) {
            Flux::toast(
                variant: 'danger',
                heading: 'Tidak Dapat Menyimpan',
                text: 'Tidak ada penulis notulen yang ditugaskan untuk sesi ini. Hubungi admin.'
            );
            return;
        }
        
        // Validate each rekan note if they exist
        foreach ($this->temanWfhIds as $personilId) {
            $note = $this->rekanNotes[$personilId] ?? '';
            if (!empty($note) && strlen($note) > 5000) {
                Flux::toast(
                    variant: 'danger',
                    text: 'Catatan rekan tidak boleh lebih dari 5000 karakter.'
                );
                return;
            }
        }

        try {
            DB::transaction(function () use ($combinedCatatan) {
                // CRITICAL: Pessimistic lock to prevent race condition
                // Check if notulen WFH already exists (with lock)
                $exists = NotulenBriefing::lockForUpdate()
                    ->where('tanggal', $this->tanggal)
                    ->where('sesi', $this->sesi)
                    ->where('jenis_kehadiran', 'wfh')
                    ->whereHas('timYangTerlibat', fn ($q) => $q->where('tim_id', Auth::user()->tim_id))
                    ->exists();

                if ($exists) {
                    throw new \Exception('Notulen WFH untuk sesi ini sudah dibuat oleh rekan tim Anda. Silakan refresh halaman.');
                }

                // Create notulen WFH
                $notulen = NotulenBriefing::create([
                    'tanggal' => $this->tanggal,
                    'sesi' => $this->sesi,
                    'user_id' => Auth::id(),
                    'nama_notulen' => $this->nama_notulen,
                    'jumlah_peserta' => count($this->temanWfhIds) + 1, // +1 for creator
                    'catatan' => $combinedCatatan, // Combined from all per-person notes
                    'jenis_kehadiran' => 'wfh',
                    'file_path' => null,
                    'file_name' => null,
                    'file_type' => null,
                    'file_size' => null,
                ]);

                // Attach tim sebagai creator
                $notulen->timYangTerlibat()->attach(Auth::user()->tim_id, ['is_creator' => true]);

                // Attach creator ke peserta WFH
                $notulen->pesertaWfh()->attach(Auth::id(), ['is_creator' => true]);

                // Attach teammates (optional)
                if (! empty($this->temanWfhIds)) {
                    foreach ($this->temanWfhIds as $personilId) {
                        $notulen->pesertaWfh()->attach($personilId, ['is_creator' => false]);
                    }
                }
            }, 5); // 5 attempts, timeout if deadlock

            Flux::toast(
                variant: 'success',
                text: 'Notulen WFH berhasil disimpan! Anda adalah yang pertama membuat notulen untuk sesi ini.'
            );

            return redirect()->route('tim.notulen.history');

        } catch (\Exception $e) {
            // Handle race condition or other errors
            if (str_contains($e->getMessage(), 'sudah dibuat')) {
                Flux::toast(
                    variant: 'warning',
                    heading: 'Terlambat!',
                    text: $e->getMessage(),
                    duration: 8000
                );
            } else {
                Flux::toast(
                    variant: 'danger',
                    heading: 'Gagal Menyimpan',
                    text: 'Terjadi kesalahan saat menyimpan notulen. Silakan coba lagi.',
                    duration: 5000
                );
            }

            // Refresh to show existing notulen
            $this->checkExistingNotulenWfh();
        }
    }

    public function simpan()
    {
        $this->validate([
            'tanggal' => 'required|date',
            'sesi' => 'required|in:pagi,sore',
            'nama_notulen' => 'required|string|max:255',
            'catatan' => 'nullable|string|max:5000',
            'file' => [
                'nullable',
                'file',
                'max:10240', // 10MB max
                'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
                'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,image/jpeg,image/png',
            ],
        ], [
            'tanggal.required' => 'Tanggal wajib diisi.',
            'sesi.required' => 'Sesi wajib dipilih.',
            'nama_notulen.required' => 'Nama notulen wajib diisi.',
            'file.mimes' => 'File harus berformat PDF, Word, Excel, JPG, JPEG, atau PNG.',
            'file.mimetypes' => 'Tipe file tidak valid.',
            'file.max' => 'Ukuran file maksimal 10MB.',
        ]);

        // Check time window: must be within allowed time for the sesi
        $tanggalNotulen = Carbon::parse($this->tanggal);
        $now = \App\Helpers\DevModeHelper::now();

        // Determine time window based on sesi
        if ($this->sesi === 'pagi') {
            $windowStart = $tanggalNotulen->copy()->setTime(8, 50);
            $windowEnd = $tanggalNotulen->copy()->setTime(11, 0);
            $sesiLabel = 'Pagi (08:50 - 11:00)';
        } else {
            $windowStart = $tanggalNotulen->copy()->setTime(16, 50);
            $windowEnd = $tanggalNotulen->copy()->setTime(18, 0);
            $sesiLabel = 'Sore (16:50 - 18:00)';
        }

        // Check if current time is within window
        if ($now->lessThan($windowStart) || $now->greaterThan($windowEnd)) {
            $deadlineFormatted = $windowEnd->translatedFormat('l, d F Y \p\u\k\u\l H:i');
            
            Flux::toast(
                variant: 'danger',
                heading: 'Tidak Dapat Menyimpan',
                text: "Notulen hanya dapat diisi pada {$sesiLabel}. Batas waktu input: {$deadlineFormatted}."
            );

            return;
        }

        $service = app(NotulenBriefingService::class);

        // Check if notulen for this date and sesi already exists (any tim)
        if ($service->notulenExists($this->tanggal, $this->sesi)) {
            $existing = $service->getNotulenForDateSesi($this->tanggal, $this->sesi);
            $creatorTim = $existing?->tim?->nama_tim ?? 'Tim lain';

            Flux::toast(
                variant: 'warning',
                text: "Notulen untuk sesi {$this->sesi} pada tanggal ini sudah diisi oleh {$creatorTim}."
            );

            return;
        }

        // Handle file upload
        $filePath = null;
        $fileName = null;
        $fileType = null;
        $fileSize = null;

        if ($this->file) {
            // Additional MIME type security check
            $mime = $this->file->getMimeType();
            $allowedMimes = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'image/jpeg',
                'image/png',
            ];

            if (! in_array($mime, $allowedMimes)) {
                Flux::toast(
                    variant: 'danger',
                    text: 'Tipe file tidak valid. Hanya PDF, Word, Excel, dan gambar yang diperbolehkan.'
                );

                return;
            }

            // Sanitize filename
            $originalName = $this->file->getClientOriginalName();
            $extension = $this->file->getClientOriginalExtension();
            $baseName = pathinfo($originalName, PATHINFO_FILENAME);
            $safeName = Str::slug($baseName).'_'.time().'.'.$extension;

            $fileName = $originalName; // Keep original for display
            $fileType = $mime;
            $fileSize = $this->file->getSize();

            // Store with safe filename
            $filePath = $this->file->storeAs('notulen', $safeName, 'public');
        }

        // Create notulen record using service (auto-links all tim WFO)
        $service->createNotulen([
            'tanggal' => $this->tanggal,
            'sesi' => $this->sesi,
            'user_id' => Auth::id(),
            'nama_notulen' => $this->nama_notulen,
            'peserta' => $this->peserta_selected,
            'catatan' => $this->catatan ?: null,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_type' => $fileType,
            'file_size' => $fileSize,
        ], Auth::user()->tim_id);

        Flux::toast(
            variant: 'success',
            text: 'Notulen briefing berhasil disimpan dan dibagikan ke semua tim WFO!'
        );

        // Redirect to history
        return redirect()->route('tim.notulen.history');
    }

    public function batal()
    {
        return redirect()->route('tim.jadwal');
    }
}; ?>

<div class="max-w-3xl mx-auto py-8 px-4 pb-32 lg:pb-8">
    <flux:card class="p-6 sm:p-8">
        {{-- Header --}}
        <div class="mb-6">
            <flux:heading size="lg" class="mb-2">Isi Notulen Briefing</flux:heading>
            <flux:text class="text-zinc-500">
                Catat hasil briefing tim hari ini untuk dokumentasi internal.
            </flux:text>
        </div>

        <form wire:submit="{{ $isTimWfh ? 'simpanNotulenWfh' : 'simpan' }}" class="space-y-6">
            {{-- Tanggal & Sesi --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <flux:field>
                    <flux:label>Tanggal</flux:label>
                    <flux:input
                        wire:model.live="tanggal"
                        type="date"
                        readonly
                        class="bg-zinc-50 dark:bg-zinc-800 cursor-not-allowed text-zinc-500 dark:text-zinc-400"
                    />
                    <flux:description>Otomatis terisi dengan tanggal hari ini</flux:description>
                    <flux:error name="tanggal" />
                </flux:field>

                <flux:field>
                    <flux:label>Sesi Briefing</flux:label>
                    <flux:select wire:model.live="sesi" disabled class="text-zinc-500 dark:text-zinc-400">
                        <option value="pagi">Pagi (08:30 - 11:00)</option>
                        <option value="sore">Sore (13:00 - 18:00)</option>
                    </flux:select>
                    <flux:description>Otomatis terdeteksi berdasarkan waktu sekarang</flux:description>
                    <flux:error name="sesi" />
                </flux:field>
            </div>

            {{-- FASE 4.2 - Task #6: WFH Detection Banner --}}
            @if ($isTimWfh)
                <div class="flex items-start gap-3 p-4 bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800 rounded-lg">
                    <flux:icon icon="home" class="size-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                    <div class="flex-1">
                        <flux:text class="font-semibold text-blue-900 dark:text-blue-100 text-sm">
                            Mode WFH (Work From Home)
                        </flux:text>
                        <flux:text class="text-blue-700 dark:text-blue-300 text-xs mt-1">
                            Tim Anda sedang WFH untuk tanggal dan sesi ini. Format notulen disesuaikan untuk kebutuhan remote work.
                        </flux:text>
                    </div>
                </div>
            @endif

            {{-- FASE 4.2 - Task #7: Existing WFH Notulen Card --}}
            @if ($existingNotulenWfh)
                <div class="flex items-start gap-3 p-4 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-lg">
                    <flux:icon icon="check-circle" class="size-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
                    <div class="flex-1">
                        <flux:text class="font-semibold text-amber-900 dark:text-amber-100 text-sm mb-2">
                            Notulen WFH Sudah Dibuat
                        </flux:text>
                        <flux:text class="text-amber-700 dark:text-amber-300 text-xs mb-3">
                            Dibuat oleh: <strong>{{ $existingNotulenWfh->creator->nama ?? 'Unknown' }}</strong><br>
                            Tanggal dibuat: {{ $existingNotulenWfh->created_at->translatedFormat('l, d F Y \p\u\k\u\l H:i') }}<br>
                            Peserta: {{ $existingNotulenWfh->jumlah_peserta }} orang
                        </flux:text>
                        <flux:button
                            variant="primary"
                            size="sm"
                            href="{{ route('tim.notulen.show', $existingNotulenWfh->id) }}"
                            icon="eye"
                        >
                            Lihat Notulen
                        </flux:button>
                    </div>
                </div>
            @endif

            {{-- Warning: Past Deadline --}}
            @if ($this->isPastDeadline)
                <div class="flex items-start gap-3 p-4 bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800 rounded-lg">
                    <flux:icon icon="exclamation-triangle" class="size-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
                    <div class="flex-1">
                        <flux:text class="font-semibold text-red-900 dark:text-red-100 text-sm">
                            Tidak Dapat Menyimpan - Melewati Batas Waktu
                        </flux:text>
                        <flux:text class="text-red-700 dark:text-red-300 text-xs mt-1">
                            @if ($sesi === 'pagi')
                                Notulen sesi Pagi hanya dapat diisi pada jam 08:50 - 11:00. Batas waktu input: {{ $this->deadlineFormatted }}.
                            @else
                                Notulen sesi Sore hanya dapat diisi pada jam 16:50 - 18:00. Batas waktu input: {{ $this->deadlineFormatted }}.
                            @endif
                            Silakan hubungi admin untuk bantuan.
                        </flux:text>
                    </div>
                </div>
            @endif

            {{-- FASE 4.2 - Task #6: Conditional Form Rendering --}}
            @if ($isTimWfh && !$existingNotulenWfh)
                {{-- WFH MODE: Different form fields --}}
                
                {{-- Nama Tim (Auto-filled) --}}
                <flux:field>
                    <flux:label>Nama Tim</flux:label>
                    <flux:input
                        wire:model="nama_notulen"
                        type="text"
                        readonly
                        class="bg-zinc-50 dark:bg-zinc-800"
                    />
                    <flux:description>
                        Otomatis terisi dengan nama tim Anda.
                    </flux:description>
                    <flux:error name="nama_notulen" />
                </flux:field>

                {{-- Penulis Notulen (Auto-filled from Jadwal Briefing, Readonly) --}}
                <flux:field>
                    <flux:label>Penulis Notulen</flux:label>
                    @if ($assignedPenulisNama)
                        <flux:input
                            :value="$assignedPenulisNama"
                            type="text"
                            readonly
                            class="bg-zinc-50 dark:bg-zinc-800 cursor-not-allowed"
                        />
                        <flux:description>
                            Penulis notulen ditugaskan berdasarkan jadwal briefing. Otomatis terisi dari sistem.
                        </flux:description>
                    @else
                        <flux:input
                            value="Tidak ada penulis yang ditugaskan"
                            type="text"
                            readonly
                            class="bg-zinc-50 dark:bg-zinc-800 cursor-not-allowed text-zinc-500"
                        />
                        <flux:description>
                            <flux:icon.exclamation-triangle class="inline size-3.5 text-amber-500" />
                            Tidak ada penulis yang ditugaskan untuk sesi ini. Hubungi admin untuk menambahkan jadwal briefing.
                        </flux:description>
                    @endif
                </flux:field>

                {{-- Nama Personil Penulis - REMOVED: Now auto-filled from Jadwal Briefing (readonly field above) --}}

                {{-- Peserta WFH (Multi-select teammates with searchable-select) --}}
                <flux:field>
                    <flux:label>Rekan Tim WFH (Opsional)</flux:label>
                    
                    {{-- Selected Teammates (Tags) --}}
                    @if (count($temanWfhIds) > 0)
                        <div class="flex flex-wrap gap-2 mb-3">
                            @foreach ($this->selectedTemanNames as $id => $nama)
                                <span
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 text-sm font-medium"
                                    wire:key="teman-{{ $id }}"
                                >
                                    {{ $nama }}
                                    <button
                                        type="button"
                                        wire:click="removeTemanWfh({{ $id }})"
                                        class="ml-1 hover:text-green-900 dark:hover:text-green-100"
                                    >
                                        <flux:icon icon="x-mark" class="size-4" />
                                    </button>
                                </span>
                            @endforeach
                        </div>
                    @endif

                    {{-- Searchable Select for adding rekan --}}
                    <x-searchable-select
                        name="addTemanWfh"
                        placeholder="Ketik atau pilih rekan..."
                        wire:model.live="selectedTemanToAdd"
                        :model-value="null"
                        :required="false"
                        :options="collect($this->availableRekanOptions)->toArray()"
                    />

                    <flux:description>
                        Pilih rekan se-tim yang juga WFH. Personil penulis otomatis tercatat sebagai peserta.
                    </flux:description>
                </flux:field>

                {{-- Per-Person Catatan (Styled like onboarding) --}}
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <flux:label class="text-base font-semibold">Catatan Briefing WFH *</flux:label>
                    </div>
                    
                    <flux:description class="!mt-1 mb-3">
                        <flux:icon.light-bulb class="inline size-4" /> Setiap personil mengisi catatan briefing mereka masing-masing (minimal 50 karakter untuk penulis).
                    </flux:description>

                    <div class="space-y-3 bg-zinc-50/70 dark:bg-zinc-800/30 p-4 sm:p-5 rounded-2xl border border-zinc-200/80 dark:border-zinc-700/80">
                        
                        {{-- Penulis (Current User) --}}
                        <div class="flex items-start gap-3 p-3.5 rounded-xl bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 shadow-xs">
                            <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/50 text-xs font-semibold text-blue-600 dark:text-blue-400 mt-1">
                                <flux:icon.pencil class="size-4" />
                            </div>

                            <div class="flex-1 space-y-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold text-zinc-900 dark:text-white">
                                        {{ $this->penulisName ?: 'Pilih penulis di atas' }}
                                    </span>
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">(Penulis)</span>
                                    <span class="text-xs text-red-500">*</span>
                                </div>
                                
                                <flux:textarea
                                    wire:model="penulisNotes"
                                    rows="4"
                                    placeholder="Tulis catatan briefing Anda di sini (minimal 50 karakter)..."
                                    class="w-full"
                                    :disabled="!$assignedPenulisId"
                                />
                                <flux:error name="penulisNotes" />
                                
                                @if (!$assignedPenulisId)
                                    <p class="text-xs text-amber-600 dark:text-amber-400">
                                        <flux:icon.exclamation-triangle class="inline size-3.5" /> Tidak ada penulis yang ditugaskan untuk sesi ini
                                    </p>
                                @endif
                            </div>
                        </div>

                        {{-- Rekan WFH (Dynamic) --}}
                        @foreach ($this->temanWfhIds as $index => $personilId)
                            @php
                                $rekanName = $this->selectedTemanNames[$personilId] ?? "Rekan #{$personilId}";
                            @endphp
                            
                            <div class="flex items-start gap-3 p-3.5 rounded-xl bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 shadow-xs" wire:key="rekan-note-{{ $personilId }}">
                                <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700 text-xs font-semibold text-zinc-600 dark:text-zinc-300 mt-1">
                                    {{ $index + 1 }}
                                </div>

                                <div class="flex-1 space-y-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold text-zinc-900 dark:text-white">
                                            {{ $rekanName }}
                                        </span>
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">(Rekan)</span>
                                    </div>
                                    
                                    <flux:textarea
                                        wire:model="rekanNotes.{{ $personilId }}"
                                        rows="4"
                                        placeholder="Catatan briefing untuk {{ $rekanName }} (opsional)..."
                                        class="w-full"
                                    />
                                </div>
                            </div>
                        @endforeach

                        @if (empty($this->temanWfhIds))
                            <div class="text-center py-3 text-sm text-zinc-500 dark:text-zinc-400">
                                <flux:icon.information-circle class="inline size-4" /> Belum ada rekan yang ditambahkan. Pilih rekan dari dropdown di atas untuk menambahkan catatan mereka.
                            </div>
                        @endif

                    </div>
                </div>

                {{-- Submit Button WFH --}}
                <div class="flex items-center gap-4 pt-4">
                    <flux:button
                        type="submit"
                        variant="primary"
                        icon="clipboard-document-check"
                        wire:loading.attr="disabled"
                    >
                        Simpan Notulen WFH
                    </flux:button>

                    <flux:button
                        type="button"
                        variant="ghost"
                        wire:click="batal"
                    >
                        Batal
                    </flux:button>
                </div>

            @elseif (!$isTimWfh && !$this->isPastDeadline)
                {{-- WFO MODE: Original form fields --}}
                
            {{-- Nama Tim Penulis (Auto-filled, Readonly) --}}
            <flux:field>
                <flux:label>Nama Tim Penulis</flux:label>
                <flux:input
                    wire:model="nama_notulen"
                    type="text"
                    readonly
                    class="bg-zinc-50 dark:bg-zinc-800 cursor-not-allowed text-zinc-500 dark:text-zinc-400"
                />
                <flux:description>
                    Otomatis terisi dengan nama tim Anda.
                </flux:description>
                <flux:error name="nama_notulen" />
            </flux:field>

            {{-- Penulis Notulen (Auto-filled from Jadwal Briefing, Readonly) --}}
            <flux:field>
                <flux:label>Penulis Notulen</flux:label>
                @if ($assignedPenulisNama)
                    <flux:input
                        :value="$assignedPenulisNama"
                        type="text"
                        readonly
                        class="bg-zinc-50 dark:bg-zinc-800 cursor-not-allowed text-zinc-500 dark:text-zinc-400"
                    />
                    <flux:description>
                        Penulis notulen ditugaskan berdasarkan jadwal briefing. Otomatis terisi dari sistem.
                    </flux:description>
                @else
                    <flux:input
                        value="Tidak ada penulis yang ditugaskan"
                        type="text"
                        readonly
                        class="bg-zinc-50 dark:bg-zinc-800 cursor-not-allowed text-zinc-400 dark:text-zinc-500"
                    />
                    <flux:description>
                        <flux:icon.exclamation-triangle class="inline size-3.5 text-amber-500" />
                        Tidak ada penulis yang ditugaskan untuk sesi ini. Hubungi admin untuk menambahkan jadwal briefing.
                    </flux:description>
                @endif
            </flux:field>

            {{-- Peserta Briefing - REMOVED: No longer needed --}}

            {{-- Catatan --}}
            <flux:field>
                <flux:label>Catatan Briefing</flux:label>
                <flux:textarea
                    wire:model="catatan"
                    rows="6"
                    placeholder="Tuliskan catatan hasil briefing: topik yang dibahas, keputusan yang diambil, tugas yang diberikan, dll."
                />
                <flux:description>
                    Opsional. Catatan untuk dokumentasi internal tim.
                </flux:description>
                <flux:error name="catatan" />
            </flux:field>

            {{-- Upload File --}}
            <flux:field>
                <flux:label>Upload Foto/PDF Catatan (Opsional)</flux:label>
                <flux:input
                    wire:model="file"
                    type="file"
                    accept="image/jpeg,image/jpg,image/png,application/pdf"
                />
                <flux:description>
                    Format: PDF, JPG, JPEG, PNG. Maksimal 5MB.
                </flux:description>
                <flux:error name="file" />

                {{-- File Preview --}}
                @if ($file)
                    <div class="mt-3 p-4 bg-zinc-50 dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <div class="flex items-center gap-3">
                            <flux:icon icon="document" class="size-6 text-blue-500" />
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">
                                    {{ $file->getClientOriginalName() }}
                                </p>
                                <p class="text-xs text-zinc-500">
                                    {{ number_format($file->getSize() / 1024, 2) }} KB
                                </p>
                            </div>
                            <button
                                type="button"
                                wire:click="$set('file', null)"
                                class="text-red-500 hover:text-red-700"
                            >
                                <flux:icon icon="trash" class="size-5" />
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Upload Progress --}}
                <div wire:loading wire:target="file" class="mt-3">
                    <div class="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Mengupload file...
                    </div>
                </div>
            </flux:field>

            {{-- Info Box --}}
            <div class="bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <div class="flex gap-3">
                    <flux:icon icon="information-circle" class="size-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                    <div class="text-sm text-blue-900 dark:text-blue-100">
                        <p class="font-medium mb-1">Tips Pengisian Notulen:</p>
                        <ul class="list-disc list-inside space-y-1 text-blue-700 dark:text-blue-300">
                            <li>Tambahkan semua personil yang hadir dalam briefing</li>
                            <li>Tuliskan poin-poin penting yang dibahas</li>
                            <li>Upload foto catatan fisik jika ada (opsional)</li>
                        </ul>
                    </div>
                </div>
            </div>

            @endif
            {{-- End FASE 4.2 Conditional Form Rendering --}}

            {{-- Actions (WFO mode only) --}}
            @if (!$isTimWfh && !$existingNotulenWfh)
            <div class="flex gap-3 pt-4">
                <flux:button
                    type="button"
                    variant="ghost"
                    wire:click="batal"
                >
                    Batal
                </flux:button>
                <flux:button
                    type="submit"
                    variant="primary"
                    wire:loading.attr="disabled"
                    wire:target="simpan"
                    :disabled="$this->isPastDeadline"
                    icon="check"
                >
                    <span wire:loading.remove wire:target="simpan">
                        @if ($this->isPastDeadline)
                            Tidak Dapat Menyimpan (Melewati Deadline)
                        @else
                            Simpan Notulen
                        @endif
                    </span>
                    <span wire:loading wire:target="simpan">Menyimpan...</span>
                </flux:button>
            </div>
            @endif
        </form>
    </flux:card>
</div>
