<?php

namespace App\Services;

use App\Models\JadwalWfo;
use App\Models\NotulenBriefing;
use App\Models\PeriodeWfo;
use Carbon\Carbon;

/**
 * Service untuk manage notulen briefing dengan auto-link tim WFO.
 */
class NotulenBriefingService
{
    /**
     * Create notulen and auto-link all tim yang WFO di hari tersebut.
     */
    public function createNotulen(array $data, int $creatorTimId): NotulenBriefing
    {
        // 1. Create notulen record
        $notulen = NotulenBriefing::create([
            'tanggal' => $data['tanggal'],
            'sesi' => $data['sesi'],
            'tim_id' => $creatorTimId, // Keep for backward compatibility
            'user_id' => $data['user_id'],
            'nama_notulen' => $data['nama_notulen'],
            'peserta' => $data['peserta'],
            'catatan' => $data['catatan'] ?? null,
            'file_path' => $data['file_path'] ?? null,
            'file_name' => $data['file_name'] ?? null,
            'file_type' => $data['file_type'] ?? null,
            'file_size' => $data['file_size'] ?? null,
        ]);

        // 2. Link creator tim (with is_creator = true)
        $notulen->timYangTerlibat()->attach($creatorTimId, ['is_creator' => true]);

        // 3. Auto-link other tim yang WFO di hari yang sama
        $timWfoIds = $this->getTimWfoOnDate($data['tanggal']);

        foreach ($timWfoIds as $timId) {
            if ($timId !== $creatorTimId) {
                $notulen->timYangTerlibat()->attach($timId, ['is_creator' => false]);
            }
        }

        return $notulen;
    }

    /**
     * Get list of tim IDs yang WFO pada tanggal tertentu.
     */
    public function getTimWfoOnDate(string $tanggal): array
    {
        $tanggalCarbon = Carbon::parse($tanggal);
        $namaHari = match ($tanggalCarbon->dayOfWeekIso) {
            1 => 'senin', 2 => 'selasa', 3 => 'rabu',
            4 => 'kamis', 5 => 'jumat', 6 => 'sabtu',
            7 => 'minggu',
        };

        // Find periode aktif for that date
        $periode = PeriodeWfo::where('tanggal_mulai', '<=', $tanggal)
            ->where('tanggal_selesai', '>=', $tanggal)
            ->where('status', 'aktif')
            ->first();

        if (! $periode) {
            return [];
        }

        // Get all tim yang WFO di hari tersebut
        return JadwalWfo::where('periode_wfo_id', $periode->id)
            ->where('hari', $namaHari)
            ->pluck('tim_id')
            ->toArray();
    }

    /**
     * Check if notulen already exists for this date + sesi (any tim).
     */
    public function notulenExists(string $tanggal, string $sesi): bool
    {
        return NotulenBriefing::withoutGlobalScope('role_based_visibility')
            ->where('tanggal', $tanggal)
            ->where('sesi', $sesi)
            ->exists();
    }

    /**
     * Get notulen for specific date + sesi (bypasses global scope).
     */
    public function getNotulenForDateSesi(string $tanggal, string $sesi): ?NotulenBriefing
    {
        return NotulenBriefing::withoutGlobalScope('role_based_visibility')
            ->where('tanggal', $tanggal)
            ->where('sesi', $sesi)
            ->first();
    }
}
