<?php

namespace App\Helpers;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Session;

/**
 * Dev Mode Helper - Time Simulation for Testing
 *
 * Usage in your code:
 * Instead of: Carbon::now()
 * Use: DevModeHelper::now()
 *
 * Example:
 * $today = DevModeHelper::now()->toDateString();
 * $currentHour = DevModeHelper::now()->hour;
 *
 * When dev mode is OFF: returns real system time
 * When dev mode is ON: returns simulated time from sidebar toggle
 */
class DevModeHelper
{
    /**
     * Get current time - returns dev mode time if active, otherwise real time
     */
    public static function now(): Carbon
    {
        $devTime = Session::get('dev_mode_time');

        if ($devTime) {
            try {
                return Carbon::parse($devTime);
            } catch (\Exception $e) {
                // If parsing fails, return real time
                return Carbon::now();
            }
        }

        return Carbon::now();
    }

    /**
     * Check if dev mode is currently active
     */
    public static function isActive(): bool
    {
        return Session::has('dev_mode_time');
    }

    /**
     * Get the dev mode time string (or null if not active)
     */
    public static function getTime(): ?string
    {
        return Session::get('dev_mode_time');
    }

    /**
     * Get human-readable time difference from now (dev mode aware)
     *
     * Usage: DevModeHelper::diffForHumans($tanggal)
     *
     * @param  Carbon  $date  The date to compare
     * @param  bool  $showSekarang  If true, returns "sekarang" when within a time window
     * @return string Human-readable difference (e.g., "2 jam yang lalu", "dalam 1 hari", "sekarang")
     */
    public static function diffForHumans(Carbon $date, bool $showSekarang = false): string
    {
        $now = self::now();

        // If showSekarang is enabled, check if we're within the same minute
        if ($showSekarang && abs($now->diffInMinutes($date, false)) <= 10) {
            return 'sekarang';
        }

        return $date->diffForHumans($now, [
            'syntax' => CarbonInterface::DIFF_RELATIVE_TO_NOW,
        ]);
    }
}
