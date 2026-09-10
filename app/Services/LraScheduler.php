<?php

namespace App\Services;

use App\Models\AlokasiRuangan;
use App\Models\JadwalAdzanKitab;
use App\Models\JadwalBriefing;
use App\Models\JadwalWfo;
use App\Models\Personil;
use App\Models\Ruangan;
use App\Models\Tim;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * LRA (Least-Recently/Rarely Assigned) Scheduler.
 *
 * Algoritma frequency-based: personil/ruangan dengan COUNT historis
 * paling sedikit diprioritaskan. Bukan timestamp-based.
 *
 * Pola eksekusi (§6.3):
 * 1. Query hitungan awal SEKALI sebelum loop
 * 2. Simpan in-memory counter [id => count]
 * 3. Loop per tanggal/slot → sort by counter → assign → increment counter
 * 4. Return array hasil (preview) — caller yang insert ke DB
 */
class LraScheduler
{
    // ── Slot adzan/kajian per hari kerja (GEN-04, §2.5) ──────────────────────

    /** @return array<string> slot waktu aktif untuk tanggal tertentu */
    public function tentukanSlotAdzan(Carbon $tanggal): array
    {
        return match ($tanggal->dayOfWeekIso) {
            // Senin–Kamis: Zuhur + Ashar
            1, 2, 3, 4 => ['dhuhr', 'asr'],
            // Jumat: Ashar saja
            5 => ['asr'],
            // Sabtu: Zuhur saja
            6 => ['dhuhr'],
            default => [],
        };
    }

    // ── Generate Jadwal Adzan & Kajian ────────────────────────────────────────

    /**
     * Generate jadwal adzan & kajian untuk array tanggal.
     *
     * @param  Carbon[]  $tanggalList
     * @return array<int, array{personil_id: int, tanggal: string, waktu_sholat: string, jenis_tugas: string, status_konfirmasi: string}>
     */
    public function generateAdzanKajian(array $tanggalList, int $periodeWfoId): array
    {
        // 1. Hitungan historis awal — sekali query
        $counter = JadwalAdzanKitab::selectRaw('personil_id, COUNT(*) as total')
            ->groupBy('personil_id')
            ->pluck('total', 'personil_id')
            ->toArray();

        $hasil = [];

        foreach ($tanggalList as $tanggal) {
            $namaHari = $this->namaHariIndonesia($tanggal);
            $kandidat = $this->ambilPersonilWfo($periodeWfoId, $namaHari, 'laki-laki');

            if ($kandidat->isEmpty()) {
                continue;
            }

            $slots = $this->tentukanSlotAdzan($tanggal);

            foreach ($slots as $slot) {
                // Pool per-slot: reset untuk tiap slot baru
                $poolSlot = $kandidat->values();

                // Tiap slot = 2 tugas: adzan + kajian
                foreach (['adzan', 'kajian'] as $jenis) {
                    // Kalau pool slot habis, isi ulang (GEN-06: duplikasi terbatas)
                    if ($poolSlot->isEmpty()) {
                        $poolSlot = $kandidat->values();
                    }

                    $terpilih = $this->pilihKandidat($poolSlot, $counter);

                    if (! $terpilih) {
                        continue;
                    }

                    $hasil[] = [
                        'personil_id' => $terpilih->id,
                        'tanggal' => $tanggal->toDateString(),
                        'waktu_sholat' => $slot,
                        'jenis_tugas' => $jenis,
                        'status_konfirmasi' => 'menunggu',
                    ];

                    // Increment in-memory supaya rotasi adil dalam 1 batch
                    $counter[$terpilih->id] = ($counter[$terpilih->id] ?? 0) + 1;

                    // GEN-05: hapus dari pool slot ini supaya tidak dapat adzan+kajian di slot sama
                    $poolSlot = $poolSlot->reject(fn ($p) => $p->id === $terpilih->id)->values();
                }
            }
        }

        return $hasil;
    }

    // ── Generate Jadwal Briefing ──────────────────────────────────────────────

    /**
     * Generate jadwal briefing untuk array tanggal.
     * Setiap tim WFO hari itu mengirim 1 perwakilan per sesi (pagi + sore).
     *
     * @param  Carbon[]  $tanggalList
     * @return array<int, array{tim_id: int, personil_id: int, tanggal: string, sesi: string, status_konfirmasi: string}>
     */
    public function generateBriefing(array $tanggalList, int $periodeWfoId): array
    {
        // Counter per personil di dalam tim — scope per tim
        // Format: [tim_id => [personil_id => count]]
        $counterPerTim = [];

        $baseCount = JadwalBriefing::selectRaw('personil_id, COUNT(*) as total')
            ->groupBy('personil_id')
            ->pluck('total', 'personil_id')
            ->toArray();

        $hasil = [];

        foreach ($tanggalList as $tanggal) {
            $namaHari = $this->namaHariIndonesia($tanggal);

            // Tim yang WFO hari ini
            $timIds = JadwalWfo::where('periode_wfo_id', $periodeWfoId)
                ->where('hari', $namaHari)
                ->whereHas('tim', fn ($q) => $q->where('status', 'active')->whereNull('deleted_at')) // Filter tim active dan tidak deleted
                ->pluck('tim_id');

            foreach ($timIds as $timId) {
                // Inisialisasi counter per tim kalau belum ada
                if (! isset($counterPerTim[$timId])) {
                    $counterPerTim[$timId] = $baseCount;
                }

                // Kandidat: personil aktif dari tim ini
                $kandidat = Personil::where('tim_id', $timId)
                    ->where('status', 'aktif')
                    ->get();

                if ($kandidat->isEmpty()) {
                    continue;
                }

                // PERBAIKAN: Pilih perwakilan briefing SEKALI untuk Pagi & Sore
                // (Tim yang sama untuk kedua sesi, sesuai real-life implementation)
                $perwakilanBriefing = $this->pilihKandidat($kandidat, $counterPerTim[$timId]);

                if (! $perwakilanBriefing) {
                    continue;
                }

                // Increment counter untuk perwakilan (hanya 1x, karena dia handle Pagi & Sore)
                $counterPerTim[$timId][$perwakilanBriefing->id] =
                    ($counterPerTim[$timId][$perwakilanBriefing->id] ?? 0) + 1;

                foreach (['pagi', 'sore'] as $sesi) {
                    $hasil[] = [
                        'tim_id' => $timId,
                        'personil_id' => $perwakilanBriefing->id,
                        'tanggal' => $tanggal->toDateString(),
                        'sesi' => $sesi,
                        'status_konfirmasi' => 'siap',
                    ];
                }
            }
        }

        return $hasil;
    }

    // ── Tentukan Notulensi dari Perwakilan Briefing ───────────────────────────

    /**
     * Pilih 1 notulensi per tanggal+sesi dari perwakilan briefing yang sudah ada.
     * LRA-based: personil yang paling jarang jadi notulen diprioritaskan.
     *
     * @param  array  $hasilBriefing  Output dari generateBriefing()
     * @return array<int, int> [index_hasil_briefing => 1 (flag is_notulen)]
     */
    public function tentukanNotulen(array $hasilBriefing): array
    {
        // FASE 4.1: Counter historis last 30 days untuk relevance
        $thirtyDaysAgo = now()->subDays(30);
        $counter = JadwalBriefing::where('is_notulen', true)
            ->where('tanggal', '>=', $thirtyDaysAgo)
            ->selectRaw('personil_id, COUNT(*) as total')
            ->groupBy('personil_id')
            ->pluck('total', 'personil_id')
            ->toArray();

        // Get last_notulen_date for tiebreaker
        $lastNotulenDates = Personil::whereNotNull('last_notulen_date')
            ->pluck('last_notulen_date', 'id')
            ->toArray();

        // Group hasil briefing by tanggal+sesi
        $grouped = collect($hasilBriefing)
            ->groupBy(fn ($row) => $row['tanggal'].'_'.$row['sesi']);

        $notulenIndex = [];

        foreach ($grouped as $key => $rows) {
            // Ambil personil_id dari rows
            $personilIds = collect($rows)->pluck('personil_id')->toArray();

            // Pilih personil dengan LRA: count → last_date → id
            $terpilihId = collect($personilIds)
                ->sortBy(function ($pid) use ($counter, $lastNotulenDates) {
                    $count = $counter[$pid] ?? 0;
                    $lastDate = $lastNotulenDates[$pid] ?? '1970-01-01';

                    return [$count, $lastDate, $pid];
                })
                ->first();

            // Cari index di $hasilBriefing original
            foreach ($hasilBriefing as $idx => $row) {
                if ($row['personil_id'] === $terpilihId && $row['tanggal'] === $rows[0]['tanggal'] && $row['sesi'] === $rows[0]['sesi']) {
                    $notulenIndex[$idx] = 1; // Mark as notulen
                    $counter[$terpilihId] = ($counter[$terpilihId] ?? 0) + 1; // Increment counter

                    // Update last_notulen_date
                    Personil::where('id', $terpilihId)->update([
                        'last_notulen_date' => $rows[0]['tanggal'],
                    ]);

                    break;
                }
            }
        }

        return $notulenIndex;
    }

    /**
     * FASE 4.3: Tentukan Moderator & Doa dari perwakilan briefing (LRA).
     * Ensure no duplicate assignment across roles.
     *
     * @param  array  $hasilBriefing  From generateBriefing()
     * @param  array  $notulenIndex  Already assigned notulen (to exclude)
     * @return array{moderator: array<int, int>, doa: array<int, int>}
     */
    /**
     * Tentukan moderator & doa dengan LRA + cross-sesi diversity.
     *
     * PHASE 3: Ensure same personil tidak dapat role yang sama di pagi & sore.
     * Example: Budi Notulensi Pagi → Budi cannot be Notulensi Sore (but can be Moderator/Doa Sore).
     */
    public function tentukanModeratorDoa(array $hasilBriefing, array $notulenIndex): array
    {
        // Group by tanggal (untuk enforce cross-sesi diversity)
        $grouped = collect($hasilBriefing)->groupBy('tanggal');

        $moderatorIndex = [];
        $doaIndex = [];

        foreach ($grouped as $tanggal => $rowsPerTanggal) {
            // Separate by sesi
            $rowsPagi = $rowsPerTanggal->where('sesi', 'pagi')->values();
            $rowsSore = $rowsPerTanggal->where('sesi', 'sore')->values();

            // Assign roles untuk PAGI first (standard LRA)
            $rolesPagi = $this->assignRolesForSesi($rowsPagi->toArray(), $hasilBriefing, $notulenIndex, []);

            // Merge rolesPagi ke index
            foreach ($rolesPagi['moderator'] as $idx => $flag) {
                $moderatorIndex[$idx] = $flag;
            }
            foreach ($rolesPagi['doa'] as $idx => $flag) {
                $doaIndex[$idx] = $flag;
            }

            // Build exclude list dari Pagi (personil_id yang sudah punya role)
            $excludeRoles = ['notulen' => [], 'moderator' => [], 'doa' => []];

            foreach ($rowsPagi as $row) {
                $personilId = $row['personil_id'];
                $originalIdx = array_search($row, $hasilBriefing, true);

                // Check if personil ini punya role di Pagi
                if (isset($notulenIndex[$originalIdx])) {
                    $excludeRoles['notulen'][] = $personilId;
                }
                if (isset($rolesPagi['moderator'][$originalIdx])) {
                    $excludeRoles['moderator'][] = $personilId;
                }
                if (isset($rolesPagi['doa'][$originalIdx])) {
                    $excludeRoles['doa'][] = $personilId;
                }
            }

            // Assign roles untuk SORE (exclude roles dari Pagi)
            $rolesSore = $this->assignRolesForSesi($rowsSore->toArray(), $hasilBriefing, $notulenIndex, $excludeRoles);

            // Merge rolesSore ke index
            foreach ($rolesSore['moderator'] as $idx => $flag) {
                $moderatorIndex[$idx] = $flag;
            }
            foreach ($rolesSore['doa'] as $idx => $flag) {
                $doaIndex[$idx] = $flag;
            }
        }

        return [
            'moderator' => $moderatorIndex,
            'doa' => $doaIndex,
        ];
    }

    /**
     * Assign roles untuk 1 sesi (Pagi atau Sore).
     *
     * @param  array  $rows  Array of rows untuk sesi ini
     * @param  array  $hasilBriefing  Original full array (for index lookup)
     * @param  array  $notulenIndex  Index of assigned notulen
     * @param  array  $excludeRoles  ['notulen' => [personil_ids], 'moderator' => [...], 'doa' => [...]]
     * @return array ['moderator' => [idx => 1], 'doa' => [idx => 1]]
     */
    private function assignRolesForSesi(array $rows, array $hasilBriefing, array $notulenIndex, array $excludeRoles = []): array
    {
        if (empty($rows)) {
            return ['moderator' => [], 'doa' => []];
        }

        $thirtyDaysAgo = now()->subDays(30);

        // Counter untuk moderator
        $counterModerator = JadwalBriefing::whereNotNull('moderator_id')
            ->where('tanggal', '>=', $thirtyDaysAgo)
            ->selectRaw('moderator_id as personil_id, COUNT(*) as total')
            ->groupBy('moderator_id')
            ->pluck('total', 'personil_id')
            ->toArray();

        // Counter untuk doa
        $counterDoa = JadwalBriefing::whereNotNull('doa_id')
            ->where('tanggal', '>=', $thirtyDaysAgo)
            ->selectRaw('doa_id as personil_id, COUNT(*) as total')
            ->groupBy('doa_id')
            ->pluck('total', 'personil_id')
            ->toArray();

        // Get last assigned dates
        $lastModeratorDates = Personil::whereNotNull('last_moderator_date')
            ->pluck('last_moderator_date', 'id')
            ->toArray();

        $lastDoaDates = Personil::whereNotNull('last_doa_date')
            ->pluck('last_doa_date', 'id')
            ->toArray();

        $personilIds = collect($rows)->pluck('personil_id')->toArray();

        // Find notulen yang assigned di sesi ini
        $assignedNotulen = null;
        foreach ($rows as $row) {
            $originalIdx = array_search($row, $hasilBriefing);
            if ($originalIdx !== false && isset($notulenIndex[$originalIdx])) {
                $assignedNotulen = $row['personil_id'];
                break;
            }
        }

        $moderatorIndex = [];
        $doaIndex = [];

        // Assign moderator (exclude notulen + exclude dari excludeRoles)
        $excludedForModerator = array_merge(
            [$assignedNotulen],
            $excludeRoles['notulen'] ?? [],
            $excludeRoles['moderator'] ?? []
        );
        $availableForModerator = array_diff($personilIds, $excludedForModerator);

        if (! empty($availableForModerator)) {
            $terpilihModerator = collect($availableForModerator)
                ->sortBy(function ($pid) use ($counterModerator, $lastModeratorDates) {
                    return [
                        $counterModerator[$pid] ?? 0,
                        $lastModeratorDates[$pid] ?? '1970-01-01',
                        $pid,
                    ];
                })
                ->first();

            // Find index in original array
            foreach ($hasilBriefing as $idx => $row) {
                if ($row['personil_id'] === $terpilihModerator
                    && $row['tanggal'] === $rows[0]['tanggal']
                    && $row['sesi'] === $rows[0]['sesi']) {
                    $moderatorIndex[$idx] = 1;
                    $counterModerator[$terpilihModerator] = ($counterModerator[$terpilihModerator] ?? 0) + 1;

                    // Update last_moderator_date
                    Personil::where('id', $terpilihModerator)->update([
                        'last_moderator_date' => $rows[0]['tanggal'],
                    ]);

                    break;
                }
            }

            // Assign doa (exclude notulen + moderator + exclude dari excludeRoles)
            $excludedForDoa = array_merge(
                [$assignedNotulen, $terpilihModerator],
                $excludeRoles['notulen'] ?? [],
                $excludeRoles['moderator'] ?? [],
                $excludeRoles['doa'] ?? []
            );
            $availableForDoa = array_diff($personilIds, $excludedForDoa);

            if (! empty($availableForDoa)) {
                $terpilihDoa = collect($availableForDoa)
                    ->sortBy(function ($pid) use ($counterDoa, $lastDoaDates) {
                        return [
                            $counterDoa[$pid] ?? 0,
                            $lastDoaDates[$pid] ?? '1970-01-01',
                            $pid,
                        ];
                    })
                    ->first();

                // Find index in original array
                foreach ($hasilBriefing as $idx => $row) {
                    if ($row['personil_id'] === $terpilihDoa
                        && $row['tanggal'] === $rows[0]['tanggal']
                        && $row['sesi'] === $rows[0]['sesi']) {
                        $doaIndex[$idx] = 1;
                        $counterDoa[$terpilihDoa] = ($counterDoa[$terpilihDoa] ?? 0) + 1;

                        // Update last_doa_date
                        Personil::where('id', $terpilihDoa)->update([
                            'last_doa_date' => $rows[0]['tanggal'],
                        ]);

                        break;
                    }
                }
            }
        }

        return [
            'moderator' => $moderatorIndex,
            'doa' => $doaIndex,
        ];
    }

    // ── Generate Alokasi Ruangan ──────────────────────────────────────────────

    /**
     * FASE 4.4 - Task #2: Calculate expected attendance for a tim on a specific date.
     *
     * Returns count of active personil in the tim.
     * Used for capacity-based room allocation.
     *
     * @return int Number of expected attendees (active personil)
     */
    private function hitungExpectedAttendance(int $timId): int
    {
        return Personil::where('tim_id', $timId)
            ->where('status', 'aktif')
            ->count();
    }

    /**
     * Generate alokasi ruangan otomatis untuk array tanggal.
     *
     * FASE 4.4 ENHANCEMENT: Considers room capacity vs expected attendance.
     * Allocates rooms where kapasitas >= expected attendance when possible.
     *
     * LRA per ruangan, scope per tim — ruangan paling jarang dipakai tim itu.
     *
     * @param  Carbon[]  $tanggalList
     * @return array<int, array{tim_id: int, ruangan_id: int, tanggal: string}>
     */
    public function generateAlokasiRuangan(array $tanggalList, int $periodeWfoId): array
    {
        // Counter: [tim_id => [ruangan_id => count]]
        $counterPerTim = [];

        // Ambil hitungan awal per tim-ruangan
        $baseCount = AlokasiRuangan::selectRaw('tim_id, ruangan_id, COUNT(*) as total')
            ->groupBy('tim_id', 'ruangan_id')
            ->get()
            ->groupBy('tim_id')
            ->map(fn ($rows) => $rows->pluck('total', 'ruangan_id')->toArray())
            ->toArray();

        $ruangans = Ruangan::where('status', 'tersedia')->orderBy('id')->get();

        $hasil = [];

        foreach ($tanggalList as $tanggal) {
            $namaHari = $this->namaHariIndonesia($tanggal);

            // Fix: Ambil tim IDs yang active dan tidak deleted
            $timIds = JadwalWfo::where('periode_wfo_id', $periodeWfoId)
                ->where('hari', $namaHari)
                ->whereHas('tim', fn ($q) => $q->where('status', 'active')->whereNull('deleted_at'))
                ->pluck('tim_id');

            // Track ruangan yang sudah dipakai hari ini (GEN-15: cegah bentrok)
            $ruanganDipakai = [];

            foreach ($timIds as $timId) {
                if (! isset($counterPerTim[$timId])) {
                    $counterPerTim[$timId] = $baseCount[$timId] ?? [];
                }

                // FASE 4.4 - Task #3: Calculate expected attendance for capacity filtering
                $expectedAttendance = $this->hitungExpectedAttendance($timId);

                // Cari ruangan LRA yang belum dipakai hari ini (dengan capacity filter)
                $ruanganTerpilih = $this->pilihRuangan(
                    $ruangans,
                    $counterPerTim[$timId],
                    $ruanganDipakai,
                    $expectedAttendance
                );

                if (! $ruanganTerpilih) {
                    // Tidak ada ruangan tersedia — skip tim ini
                    continue;
                }

                // FASE 4.4 - Task #4: Store expected attendance for utilization tracking
                $hasil[] = [
                    'tim_id' => $timId,
                    'ruangan_id' => $ruanganTerpilih->id,
                    'tanggal' => $tanggal->toDateString(),
                    'expected_attendance' => $expectedAttendance,
                ];

                $counterPerTim[$timId][$ruanganTerpilih->id] =
                    ($counterPerTim[$timId][$ruanganTerpilih->id] ?? 0) + 1;

                $ruanganDipakai[] = $ruanganTerpilih->id;
            }
        }

        return $hasil;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Ambil personil aktif dari tim yang WFO pada hari tertentu.
     *
     * @return Collection<int, Personil>
     */
    public function ambilPersonilWfo(int $periodeWfoId, string $namaHari, ?string $jenisKelamin = null): Collection
    {
        $timIds = JadwalWfo::where('periode_wfo_id', $periodeWfoId)
            ->where('hari', $namaHari)
            ->whereHas('tim', fn ($q) => $q->where('status', 'active')->whereNull('deleted_at')) // Filter tim active dan tidak deleted
            ->pluck('tim_id');

        return Personil::whereIn('tim_id', $timIds)
            ->where('status', 'aktif')
            ->when($jenisKelamin, fn ($q) => $q->where('jenis_kelamin', $jenisKelamin))
            ->get();
    }

    /**
     * Pilih kandidat dengan counter terkecil.
     * Tie-breaker: shuffle sebelum sort (§6.3).
     *
     * @param  Collection<int, Personil>  $kandidat
     * @param  array<int, int>  $counter  [personil_id => count]
     */
    public function pilihKandidat(Collection $kandidat, array $counter): ?Personil
    {
        if ($kandidat->isEmpty()) {
            return null;
        }

        // Shuffle dulu untuk tie-breaker yang adil
        $shuffled = $kandidat->shuffle();

        return $shuffled
            ->sortBy(fn ($p) => $counter[$p->id] ?? 0)
            ->first();
    }

    /**
     * Pilih ruangan LRA yang belum dipakai hari ini dan kapasitas cukup.
     *
     * FASE 4.4 ENHANCEMENT: Prioritizes rooms where kapasitas >= expectedAttendance.
     * Fallback: If no suitable room, picks largest available room (best effort).
     *
     * Algorithm:
     * 1. Filter out sudahDipakai (same day conflict)
     * 2. Try to find rooms with sufficient capacity
     * 3. If found, apply LRA (shuffle + sort by counter)
     * 4. If not found, fallback to largest room available
     *
     * @param  Collection<int, Ruangan>  $ruangans
     * @param  array<int, int>  $counter  [ruangan_id => count]
     * @param  int[]  $sudahDipakai
     * @param  int  $expectedAttendance  Expected number of attendees
     */
    private function pilihRuangan(
        Collection $ruangans,
        array $counter,
        array $sudahDipakai,
        int $expectedAttendance = 0
    ): ?Ruangan {
        // Step 1: Filter out already used rooms (same day)
        $available = $ruangans->reject(fn ($r) => in_array($r->id, $sudahDipakai));

        if ($available->isEmpty()) {
            return null;
        }

        // Step 2: Try to find rooms with sufficient capacity
        if ($expectedAttendance > 0) {
            $suitableRooms = $available->filter(fn ($r) => $r->kapasitas >= $expectedAttendance);

            if ($suitableRooms->isNotEmpty()) {
                // Apply LRA on suitable rooms
                return $suitableRooms
                    ->shuffle()
                    ->sortBy(fn ($r) => $counter[$r->id] ?? 0)
                    ->first();
            }

            // Step 3: Fallback to largest available room (best effort)
            // This handles overflow scenarios (tim too large for any room)
            return $available
                ->sortByDesc(fn ($r) => $r->kapasitas)
                ->first();
        }

        // Step 4: No capacity constraint (legacy behavior)
        return $available
            ->shuffle()
            ->sortBy(fn ($r) => $counter[$r->id] ?? 0)
            ->first();
    }

    /**
     * Konversi Carbon ke nama hari Indonesia lowercase (sesuai enum jadwal_wfo).
     */
    public function namaHariIndonesia(Carbon $tanggal): string
    {
        return match ($tanggal->dayOfWeekIso) {
            1 => 'senin',
            2 => 'selasa',
            3 => 'rabu',
            4 => 'kamis',
            5 => 'jumat',
            6 => 'sabtu',
            default => '',
        };
    }

    /**
     * Expand rentang tanggal menjadi array Carbon, skip hari Minggu.
     *
     * @return Carbon[]
     */
    public function expandTanggal(Carbon $mulai, Carbon $selesai): array
    {
        $list = [];
        $current = $mulai->copy();

        while ($current->lte($selesai)) {
            if ($current->dayOfWeekIso !== 7) { // bukan Minggu
                $list[] = $current->copy();
            }
            $current->addDay();
        }

        return $list;
    }
}
