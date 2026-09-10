<?php

namespace App\Services;

use App\Models\JadwalAdzanKitab;
use App\Models\JadwalBriefing;
use App\Models\Notifikasi;
use App\Models\PeriodeWfo;
use App\Models\Personil;
use App\Models\User;
use Carbon\Carbon;

/**
 * Auto-cari pengganti saat personil klik "Berhalangan" (§4.2.1).
 * Berlaku untuk SEMUA jenis tugas: Adzan/Kajian DAN Briefing.
 */
class AutoSwapService
{
    public function __construct(private readonly LraScheduler $lra) {}

    /**
     * Proses "berhalangan" untuk jadwal adzan/kajian.
     * Return: pesan hasil (untuk toast).
     */
    public function berhalanganAdzan(int $jadwalId, int $personilYangBerhalanganId, ?string $alasan = null): string
    {
        $jadwal = JadwalAdzanKitab::findOrFail($jadwalId);

        // Note: Berhalangan counter check sudah dilakukan di konfirmasiBerhalangan() (FASE 3.2)

        $jadwal->update(['status_konfirmasi' => 'berhalangan']);

        $pengganti = $this->cariPenggantiAdzan($jadwal, $personilYangBerhalanganId);

        if ($pengganti) {
            // Buat jadwal baru untuk pengganti dengan switched tracking
            $jadwalBaru = JadwalAdzanKitab::create([
                'personil_id' => $pengganti->id,
                'tanggal' => $jadwal->tanggal,
                'waktu_sholat' => $jadwal->waktu_sholat,
                'jenis_tugas' => $jadwal->jenis_tugas,
                'status_konfirmasi' => 'menunggu',
                'is_switched' => true,
                'original_personil_id' => $personilYangBerhalanganId,
                'switch_reason' => $alasan ?? 'Berhalangan',
                'switched_at' => now(),
            ]);

            $this->kirimNotifikasiPengganti($pengganti, $jadwalBaru->id,
                "{$pengganti->nama} ditunjuk menggantikan tugas {$jadwal->jenis_tugas} "
                .strtoupper($jadwal->waktu_sholat)
                .' pada '.Carbon::parse($jadwal->tanggal)->locale('id')->translatedFormat('l d M Y').'.'
            );

            $this->kirimNotifikasiAdmin(
                "Personil berhalangan: tugas {$jadwal->jenis_tugas} ".strtoupper($jadwal->waktu_sholat)
                .' tgl '.Carbon::parse($jadwal->tanggal)->format('d/m/Y')
                ." sudah diganti ke {$pengganti->nama}."
            );

            return "Pengganti ditemukan: {$pengganti->nama}.";
        }

        // Tidak ada pengganti — notifikasi admin
        $this->kirimNotifikasiAdmin(
            "PERLU TINDAKAN: Tidak ada pengganti untuk tugas {$jadwal->jenis_tugas} "
            .strtoupper($jadwal->waktu_sholat)
            .' tgl '.Carbon::parse($jadwal->tanggal)->format('d/m/Y').'. Assign manual diperlukan.'
        );

        return 'Tidak ada pengganti tersedia. Admin sudah diberitahu untuk assign manual.';
    }

    /**
     * Detect apakah personil yang berhalangan punya role khusus.
     *
     * @return string|null 'notulen', 'moderator', 'doa', atau null
     */
    private function detectRole(JadwalBriefing $jadwal, int $personilId): ?string
    {
        // Check is_notulen (column di row personil ini)
        if ($jadwal->is_notulen && $jadwal->personil_id === $personilId) {
            return 'notulen';
        }

        // Check moderator_id (global untuk sesi ini)
        if ($jadwal->moderator_id === $personilId) {
            return 'moderator';
        }

        // Check doa_id (global untuk sesi ini)
        if ($jadwal->doa_id === $personilId) {
            return 'doa';
        }

        return null; // No special role
    }

    /**
     * Re-calculate LRA untuk assign role ke personil yang paling fair.
     *
     * @param  string  $roleType  'notulen', 'moderator', 'doa'
     * @return int|null personil_id yang terpilih, atau null jika tidak ada kandidat
     */
    private function reassignRoleWithLRA(JadwalBriefing $jadwalBerhalangan, string $roleType): ?int
    {
        // Step 1: Get all perwakilan briefing di sesi yang sama
        $perwakilanBriefing = JadwalBriefing::where('tanggal', $jadwalBerhalangan->tanggal)
            ->where('sesi', $jadwalBerhalangan->sesi)
            ->whereNotNull('tim_id')
            ->where('status_konfirmasi', '!=', 'berhalangan')
            ->get();

        if ($perwakilanBriefing->isEmpty()) {
            return null;
        }

        // Step 2: Get current role assignments di sesi ini
        $currentModerator = $perwakilanBriefing->first()->moderator_id;
        $currentDoa = $perwakilanBriefing->first()->doa_id;

        // Get personil_ids yang is_notulen
        $notulenIds = $perwakilanBriefing->where('is_notulen', true)->pluck('personil_id')->toArray();

        // Step 3: Filter kandidat (exclude yang sudah punya role lain)
        $kandidat = $perwakilanBriefing->filter(function ($jadwal) use ($roleType, $currentModerator, $currentDoa, $notulenIds) {
            $personilId = $jadwal->personil_id;

            if ($roleType === 'notulen') {
                // Exclude jika personil ini adalah moderator atau doa
                return $personilId !== $currentModerator && $personilId !== $currentDoa;
            } elseif ($roleType === 'moderator') {
                // Exclude jika personil ini adalah notulen atau doa
                return ! in_array($personilId, $notulenIds) && $personilId !== $currentDoa;
            } else { // doa
                // Exclude jika personil ini adalah notulen atau moderator
                return ! in_array($personilId, $notulenIds) && $personilId !== $currentModerator;
            }
        });

        if ($kandidat->isEmpty()) {
            return null;
        }

        $personilIds = $kandidat->pluck('personil_id')->unique()->toArray();

        // Step 4: Count LRA untuk role ini (last 30 days)
        $thirtyDaysAgo = now()->subDays(30);

        $counter = [];
        if ($roleType === 'notulen') {
            $counter = JadwalBriefing::where('is_notulen', true)
                ->where('tanggal', '>=', $thirtyDaysAgo)
                ->whereIn('personil_id', $personilIds)
                ->selectRaw('personil_id, COUNT(*) as total')
                ->groupBy('personil_id')
                ->pluck('total', 'personil_id')
                ->toArray();
        } elseif ($roleType === 'moderator') {
            $counter = JadwalBriefing::whereNotNull('moderator_id')
                ->where('tanggal', '>=', $thirtyDaysAgo)
                ->whereIn('moderator_id', $personilIds)
                ->selectRaw('moderator_id as personil_id, COUNT(*) as total')
                ->groupBy('moderator_id')
                ->pluck('total', 'personil_id')
                ->toArray();
        } else { // doa
            $counter = JadwalBriefing::whereNotNull('doa_id')
                ->where('tanggal', '>=', $thirtyDaysAgo)
                ->whereIn('doa_id', $personilIds)
                ->selectRaw('doa_id as personil_id, COUNT(*) as total')
                ->groupBy('doa_id')
                ->pluck('total', 'personil_id')
                ->toArray();
        }

        // Step 5: Get last assigned dates
        $lastDates = Personil::whereIn('id', $personilIds)
            ->get()
            ->pluck('last_'.$roleType.'_date', 'id')
            ->toArray();

        // Step 6: Sort by LRA (count → last_date → id)
        $terpilih = collect($personilIds)
            ->sortBy(function ($pid) use ($counter, $lastDates) {
                return [
                    $counter[$pid] ?? 0,
                    $lastDates[$pid] ?? '1970-01-01',
                    $pid,
                ];
            })
            ->first();

        return $terpilih;
    }

    /**
     * Update role assignment di jadwal briefing untuk sesi tertentu.
     *
     * @param  JadwalBriefing  $jadwal  Reference jadwal (untuk tanggal & sesi)
     * @param  string  $roleType  'notulen', 'moderator', 'doa'
     * @param  int  $personilId  Personil yang akan di-assign role
     */
    private function updateRoleAssignment(JadwalBriefing $jadwal, string $roleType, int $personilId): void
    {
        $tanggal = $jadwal->tanggal;
        $sesi = $jadwal->sesi;

        if ($roleType === 'notulen') {
            // Clear existing notulen first
            JadwalBriefing::where('tanggal', $tanggal)
                ->where('sesi', $sesi)
                ->update(['is_notulen' => false]);

            // Set is_notulen = true untuk personil terpilih
            // PERBAIKAN: Juga set is_switched jika row ini belum ada atau baru di-assign
            $existingRow = JadwalBriefing::where('tanggal', $tanggal)
                ->where('sesi', $sesi)
                ->where('personil_id', $personilId)
                ->first();

            if ($existingRow) {
                $existingRow->update([
                    'is_notulen' => true,
                    'is_switched' => true,  // Mark sebagai pengganti
                    'original_personil_id' => $jadwal->personil_id,  // Personil yang berhalangan
                    'switch_reason' => 'Role reassignment - Notulensi',
                    'switched_at' => now(),
                ]);
            }

            // Update last_notulen_date
            Personil::where('id', $personilId)->update([
                'last_notulen_date' => $tanggal,
            ]);
        } elseif ($roleType === 'moderator') {
            // Set moderator_id untuk SEMUA jadwal di sesi ini
            JadwalBriefing::where('tanggal', $tanggal)
                ->where('sesi', $sesi)
                ->update(['moderator_id' => $personilId]);

            // PERBAIKAN: Mark row personil yang dapat role moderator sebagai pengganti
            $moderatorRow = JadwalBriefing::where('tanggal', $tanggal)
                ->where('sesi', $sesi)
                ->where('personil_id', $personilId)
                ->first();

            if ($moderatorRow) {
                $moderatorRow->update([
                    'is_switched' => true,
                    'original_personil_id' => $jadwal->personil_id,
                    'switch_reason' => 'Role reassignment - Moderator',
                    'switched_at' => now(),
                ]);
            }

            // Update last_moderator_date
            Personil::where('id', $personilId)->update([
                'last_moderator_date' => $tanggal,
            ]);
        } else { // doa
            // Set doa_id untuk SEMUA jadwal di sesi ini
            JadwalBriefing::where('tanggal', $tanggal)
                ->where('sesi', $sesi)
                ->update(['doa_id' => $personilId]);

            // PERBAIKAN: Mark row personil yang dapat role doa sebagai pengganti
            $doaRow = JadwalBriefing::where('tanggal', $tanggal)
                ->where('sesi', $sesi)
                ->where('personil_id', $personilId)
                ->first();

            if ($doaRow) {
                $doaRow->update([
                    'is_switched' => true,
                    'original_personil_id' => $jadwal->personil_id,
                    'switch_reason' => 'Role reassignment - Doa',
                    'switched_at' => now(),
                ]);
            }

            // Update last_doa_date
            Personil::where('id', $personilId)->update([
                'last_doa_date' => $tanggal,
            ]);
        }
    }

    /**
     * Notify pengganti perwakilan briefing.
     */
    private function notifyReplacementPerwakilan(Personil $pengganti, JadwalBriefing $jadwal): void
    {
        $tanggal = Carbon::parse($jadwal->tanggal)->locale('id')->translatedFormat('l, d F Y');
        $sesi = ucfirst($jadwal->sesi);

        // In-app notification (persistent)
        Notifikasi::create([
            'personil_id' => $pengganti->id,
            'user_id' => $pengganti->user?->id,
            'tipe' => 'pengganti',
            'pesan' => "{$pengganti->nama} menggantikan perwakilan briefing {$sesi} pada {$tanggal}. Harap hadir tepat waktu.",
            'data_id' => $jadwal->id,
            'dibaca' => false,
            'terkirim_pada' => now(),
        ]);
    }

    /**
     * Notify personil yang dapat role assignment.
     */
    private function notifyRoleAssignment(
        Personil $personil,
        JadwalBriefing $jadwal,
        string $roleType,
        string $namaBerhalangan
    ): void {
        $tanggal = Carbon::parse($jadwal->tanggal)->locale('id')->translatedFormat('l, d F Y');
        $sesi = ucfirst($jadwal->sesi);
        $roleLabel = ucfirst($roleType);

        // In-app notification (persistent, tipe warning karena assignment penting)
        Notifikasi::create([
            'personil_id' => $personil->id,
            'user_id' => $personil->user?->id,
            'tipe' => 'pengganti',
            'pesan' => "{$personil->nama} ditunjuk sebagai {$roleLabel} pengganti {$namaBerhalangan} di briefing {$sesi} pada {$tanggal}. Harap siapkan materi yang diperlukan.",
            'data_id' => $jadwal->id,
            'dibaca' => false,
            'terkirim_pada' => now(),
        ]);
    }

    /**
     * Proses "berhalangan" untuk jadwal briefing.
     *
     * Flow PHASE 3:
     * 1. Detect role yang berhalangan (if any)
     * 2. Mark jadwal berhalangan
     * 3. Cari pengganti perwakilan dari tim yang sama
     * 4. Create jadwal baru untuk pengganti
     * 5. If ada role, re-assign role dengan LRA
     * 6. Notify pengganti perwakilan & pengganti role
     */
    public function berhalanganBriefing(int $jadwalId, int $personilYangBerhalanganId, ?string $alasan = null): string
    {
        $jadwal = JadwalBriefing::with(['tim', 'personil'])->findOrFail($jadwalId);

        // Note: Berhalangan counter check sudah dilakukan di konfirmasiBerhalangan() (FASE 3.2)

        // Step 1: Detect role yang berhalangan
        $roleYangBerhalangan = $this->detectRole($jadwal, $personilYangBerhalanganId);

        // Step 2: Mark berhalangan
        $jadwal->update(['status_konfirmasi' => 'berhalangan']);

        // Step 3: Cari pengganti perwakilan
        $pengganti = $this->cariPenggantiDalamTim($jadwal, $personilYangBerhalanganId);

        if (! $pengganti) {
            // No replacement available - notify admin
            $this->kirimNotifikasiAdmin(
                'PERLU TINDAKAN: Tidak ada pengganti briefing '.ucfirst($jadwal->sesi)
                .' tgl '.Carbon::parse($jadwal->tanggal)->format('d/m/Y')
                ." ({$jadwal->tim->nama_tim}). Assign manual diperlukan."
            );

            return 'Tidak ada pengganti tersedia. Admin sudah diberitahu untuk assign manual.';
        }

        // Step 4: Create jadwal baru untuk pengganti dengan switched tracking
        $jadwalBaru = JadwalBriefing::create([
            'tim_id' => $jadwal->tim_id,
            'personil_id' => $pengganti->id,
            'tanggal' => $jadwal->tanggal,
            'sesi' => $jadwal->sesi,
            'status_konfirmasi' => 'siap', // ✅ FIXED: Auto-siap untuk konsistensi dengan briefing normal
            'is_switched' => true,
            'original_personil_id' => $personilYangBerhalanganId,
            'switch_reason' => $alasan ?? 'Berhalangan',
            'switched_at' => now(),
            'is_notulen' => false,
            // moderator_id & doa_id will be set globally if assigned
        ]);

        // Step 5: Notify pengganti perwakilan
        $this->notifyReplacementPerwakilan($pengganti, $jadwal);

        $message = "Pengganti ditemukan (perwakilan: {$pengganti->nama})";

        // Step 6: If ada role, re-assign dengan LRA
        if ($roleYangBerhalangan) {
            $personilUntukRole = $this->reassignRoleWithLRA($jadwal, $roleYangBerhalangan);

            if ($personilUntukRole) {
                // Update role assignment
                $this->updateRoleAssignment($jadwal, $roleYangBerhalangan, $personilUntukRole);

                // Notify personil yang dapat role
                $personilRole = Personil::find($personilUntukRole);
                $this->notifyRoleAssignment(
                    $personilRole,
                    $jadwal,
                    $roleYangBerhalangan,
                    $jadwal->personil->nama
                );

                $message .= ". Role {$roleYangBerhalangan} → {$personilRole->nama} (LRA).";
            } else {
                $message .= ". Role {$roleYangBerhalangan} tidak ada kandidat, admin perlu assign manual.";
            }
        }

        // Notify admin tentang swap success
        $this->kirimNotifikasiAdmin(
            'Personil berhalangan briefing: '.ucfirst($jadwal->sesi)
            .' tgl '.Carbon::parse($jadwal->tanggal)->format('d/m/Y')
            ." ({$jadwal->tim->nama_tim}) sudah diganti ke {$pengganti->nama}."
            .($roleYangBerhalangan ? " Role {$roleYangBerhalangan} juga sudah di-reassign." : '')
        );

        return $message;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function cariPenggantiAdzan(JadwalAdzanKitab $jadwal, int $kecualiPersonilId): ?Personil
    {
        // Ambil personil WFO hari itu dari periode aktif
        $periodeId = PeriodeWfo::where('status', 'aktif')->value('id');

        if (! $periodeId) {
            return null;
        }

        $namaHari = $this->lra->namaHariIndonesia(Carbon::parse($jadwal->tanggal));

        // PERBAIKAN: Filter by gender 'laki-laki' untuk adzan/kajian
        $kandidat = $this->lra->ambilPersonilWfo($periodeId, $namaHari, 'laki-laki')
            ->reject(fn ($p) => $p->id === $kecualiPersonilId);

        // Juga exclude personil yang sudah bertugas di slot yang sama
        $sudahBertugas = JadwalAdzanKitab::where('tanggal', $jadwal->tanggal)
            ->where('waktu_sholat', $jadwal->waktu_sholat)
            ->where('status_konfirmasi', '!=', 'berhalangan')
            ->pluck('personil_id');

        $kandidat = $kandidat->reject(fn ($p) => $sudahBertugas->contains($p->id));

        if ($kandidat->isEmpty()) {
            return null;
        }

        // LRA: counter dari riwayat
        $counter = JadwalAdzanKitab::selectRaw('personil_id, COUNT(*) as total')
            ->groupBy('personil_id')
            ->pluck('total', 'personil_id')
            ->toArray();

        return $this->lra->pilihKandidat($kandidat, $counter);
    }

    private function cariPenggantiDalamTim(JadwalBriefing $jadwal, int $kecualiPersonilId): ?Personil
    {
        // Kandidat: personil aktif tim yang sama, bukan yang berhalangan
        $kandidat = Personil::where('tim_id', $jadwal->tim_id)
            ->where('status', 'aktif')
            ->where('id', '!=', $kecualiPersonilId)
            ->get();

        // Exclude yang sudah bertugas di sesi + tanggal yang sama
        $sudahBertugas = JadwalBriefing::where('tanggal', $jadwal->tanggal)
            ->where('sesi', $jadwal->sesi)
            ->where('tim_id', $jadwal->tim_id)
            ->where('status_konfirmasi', '!=', 'berhalangan')
            ->pluck('personil_id');

        $kandidat = $kandidat->reject(fn ($p) => $sudahBertugas->contains($p->id));

        if ($kandidat->isEmpty()) {
            return null;
        }

        $counter = JadwalBriefing::selectRaw('personil_id, COUNT(*) as total')
            ->groupBy('personil_id')
            ->pluck('total', 'personil_id')
            ->toArray();

        return $this->lra->pilihKandidat($kandidat, $counter);
    }

    private function kirimNotifikasiPengganti(Personil $pengganti, int $jadwalBaruId, string $pesan): void
    {
        Notifikasi::create([
            'personil_id' => $pengganti->id,
            'user_id' => $pengganti->user?->id,
            'tipe' => 'pengganti',
            'pesan' => $pesan,
            'data_id' => $jadwalBaruId,
            'dibaca' => false,
            'terkirim_pada' => now(),
        ]);
    }

    private function kirimNotifikasiAdmin(string $pesan): void
    {
        // NOT-07: kirim ke semua akun admin
        $adminIds = User::where('role', 'admin')->pluck('id');

        foreach ($adminIds as $adminId) {
            Notifikasi::create([
                'personil_id' => null,
                'user_id' => $adminId,
                'tipe' => 'pengganti',
                'pesan' => $pesan,
                'data_id' => null,
                'dibaca' => false,
                'terkirim_pada' => now(),
            ]);
        }
    }
}
