<?php

namespace App\Services;

use App\Models\AlokasiRuangan;
use App\Models\JadwalAdzanKitab;
use App\Models\JadwalBriefing;
use App\Models\JadwalWfo;
use App\Models\Personil;
use App\Models\Ruangan;
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
                ->whereHas('tim', fn ($q) => $q->where('status', 'active')) // Filter tim active
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

                foreach (['pagi', 'sore'] as $sesi) {
                    $terpilih = $this->pilihKandidat($kandidat, $counterPerTim[$timId]);

                    if (! $terpilih) {
                        continue;
                    }

                    $hasil[] = [
                        'tim_id' => $timId,
                        'personil_id' => $terpilih->id,
                        'tanggal' => $tanggal->toDateString(),
                        'sesi' => $sesi,
                        'status_konfirmasi' => 'menunggu',
                    ];

                    $counterPerTim[$timId][$terpilih->id] =
                        ($counterPerTim[$timId][$terpilih->id] ?? 0) + 1;
                }
            }
        }

        return $hasil;
    }

    // ── Generate Alokasi Ruangan ──────────────────────────────────────────────

    /**
     * Generate alokasi ruangan otomatis untuk array tanggal.
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

            $timIds = JadwalWfo::where('periode_wfo_id', $periodeWfoId)
                ->where('hari', $namaHari)
                ->pluck('tim_id');

            // Track ruangan yang sudah dipakai hari ini (GEN-15: cegah bentrok)
            $ruanganDipakai = [];

            foreach ($timIds as $timId) {
                if (! isset($counterPerTim[$timId])) {
                    $counterPerTim[$timId] = $baseCount[$timId] ?? [];
                }

                // Cari ruangan LRA yang belum dipakai hari ini
                $ruanganTerpilih = $this->pilihRuangan(
                    $ruangans,
                    $counterPerTim[$timId],
                    $ruanganDipakai
                );

                if (! $ruanganTerpilih) {
                    // Tidak ada ruangan tersedia — skip tim ini
                    continue;
                }

                $hasil[] = [
                    'tim_id' => $timId,
                    'ruangan_id' => $ruanganTerpilih->id,
                    'tanggal' => $tanggal->toDateString(),
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
            ->whereHas('tim', fn ($q) => $q->where('status', 'active')) // Filter tim active
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
     * @param  Collection<int, Ruangan>  $ruangans
     * @param  array<int, int>  $counter  [ruangan_id => count]
     * @param  int[]  $sudahDipakai
     */
    private function pilihRuangan(
        Collection $ruangans,
        array $counter,
        array $sudahDipakai
    ): ?Ruangan {
        return $ruangans
            ->reject(fn ($r) => in_array($r->id, $sudahDipakai))
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
