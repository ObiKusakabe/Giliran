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
    public function berhalanganAdzan(int $jadwalId, int $personilYangBerhalanganId): string
    {
        $jadwal = JadwalAdzanKitab::findOrFail($jadwalId);
        $jadwal->update(['status_konfirmasi' => 'berhalangan']);

        $pengganti = $this->cariPenggantiAdzan($jadwal, $personilYangBerhalanganId);

        if ($pengganti) {
            // Buat jadwal baru untuk pengganti
            $jadwalBaru = JadwalAdzanKitab::create([
                'personil_id' => $pengganti->id,
                'tanggal' => $jadwal->tanggal,
                'waktu_sholat' => $jadwal->waktu_sholat,
                'jenis_tugas' => $jadwal->jenis_tugas,
                'status_konfirmasi' => 'menunggu',
            ]);

            $this->kirimNotifikasiPengganti($pengganti, $jadwalBaru->id,
                "Kamu ditunjuk menggantikan tugas {$jadwal->jenis_tugas} "
                .strtoupper($jadwal->waktu_sholat)
                .' pada '.Carbon::parse($jadwal->tanggal)->translatedFormat('l d M Y').'.'
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
     * Proses "berhalangan" untuk jadwal briefing.
     */
    public function berhalanganBriefing(int $jadwalId, int $personilYangBerhalanganId): string
    {
        $jadwal = JadwalBriefing::with('tim')->findOrFail($jadwalId);
        $jadwal->update(['status_konfirmasi' => 'berhalangan']);

        $pengganti = $this->cariPenggantiDalamTim($jadwal, $personilYangBerhalanganId);

        if ($pengganti) {
            $jadwalBaru = JadwalBriefing::create([
                'tim_id' => $jadwal->tim_id,
                'personil_id' => $pengganti->id,
                'tanggal' => $jadwal->tanggal,
                'sesi' => $jadwal->sesi,
                'status_konfirmasi' => 'menunggu',
            ]);

            $this->kirimNotifikasiPengganti($pengganti, $jadwalBaru->id,
                'Kamu ditunjuk menggantikan briefing '.ucfirst($jadwal->sesi)
                ." untuk {$jadwal->tim->nama_tim}"
                .' pada '.Carbon::parse($jadwal->tanggal)->translatedFormat('l d M Y').'.'
            );

            $this->kirimNotifikasiAdmin(
                'Personil berhalangan briefing: '.ucfirst($jadwal->sesi)
                .' tgl '.Carbon::parse($jadwal->tanggal)->format('d/m/Y')
                ." ({$jadwal->tim->nama_tim}) sudah diganti ke {$pengganti->nama}."
            );

            return "Pengganti ditemukan: {$pengganti->nama}.";
        }

        $this->kirimNotifikasiAdmin(
            'PERLU TINDAKAN: Tidak ada pengganti briefing '.ucfirst($jadwal->sesi)
            .' tgl '.Carbon::parse($jadwal->tanggal)->format('d/m/Y')
            ." ({$jadwal->tim->nama_tim}). Assign manual diperlukan."
        );

        return 'Tidak ada pengganti tersedia. Admin sudah diberitahu untuk assign manual.';
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
        $kandidat = $this->lra->ambilPersonilWfo($periodeId, $namaHari)
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
