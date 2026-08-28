<?php

namespace App\Services;

use App\Models\Tim;
use Carbon\Carbon;

/**
 * Service untuk generate nama tim dengan suffix gelombang otomatis.
 *
 * Format: <Nama Tim>-<Tahun>-<Roman Numeral>
 * Contoh: "Tim LPKIA-2026-I", "Tim LPKIA-2026-II"
 */
class TimNamingService
{
    /**
     * Generate nama tim dengan suffix gelombang otomatis.
     *
     * @param  string  $namaBase  Nama tim tanpa suffix (misal: "Tim LPKIA")
     * @return string Nama lengkap dengan suffix (misal: "Tim LPKIA-2026-II")
     */
    public function generateNamaGelombang(string $namaBase): string
    {
        $namaBase = trim($namaBase);
        $tahun = Carbon::now()->year;

        // Pattern: <nama>-<tahun>-<roman>
        $pattern = $namaBase.'-'.$tahun.'-';

        // Cek apakah sudah ada tim dengan pattern ini (case-insensitive)
        $existingTims = Tim::where('nama_tim', 'LIKE', $pattern.'%')
            ->pluck('nama_tim')
            ->map(fn ($nama) => strtolower($nama));

        if ($existingTims->isEmpty()) {
            // Belum ada, gelombang I
            return $pattern.'I';
        }

        // Extract roman numerals yang sudah ada
        $romanNumerals = $existingTims->map(function ($nama) use ($pattern) {
            // Ambil suffix setelah pattern
            $suffix = str_replace(strtolower($pattern), '', $nama);

            return $this->romanToInt($suffix);
        })->filter()->sort()->values();

        // Next gelombang = max + 1
        $nextGelombang = $romanNumerals->last() + 1;

        return $pattern.$this->intToRoman($nextGelombang);
    }

    /**
     * Convert Roman numeral to integer.
     *
     * @param  string  $roman  Roman numeral (I, II, III, IV, V, dst)
     * @return int|null Integer value, or null if invalid
     */
    private function romanToInt(string $roman): ?int
    {
        $roman = strtoupper(trim($roman));
        $map = ['I' => 1, 'V' => 5, 'X' => 10, 'L' => 50, 'C' => 100];

        $result = 0;
        $prev = 0;

        for ($i = strlen($roman) - 1; $i >= 0; $i--) {
            $current = $map[$roman[$i]] ?? 0;

            if ($current === 0) {
                return null; // Invalid roman numeral
            }

            if ($current < $prev) {
                $result -= $current;
            } else {
                $result += $current;
            }

            $prev = $current;
        }

        return $result;
    }

    /**
     * Convert integer to Roman numeral (up to 20).
     *
     * @param  int  $num  Integer value (1-20)
     * @return string Roman numeral
     */
    private function intToRoman(int $num): string
    {
        $map = [
            10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I',
        ];

        $result = '';

        foreach ($map as $value => $roman) {
            while ($num >= $value) {
                $result .= $roman;
                $num -= $value;
            }
        }

        return $result;
    }
}
