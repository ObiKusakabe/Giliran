<?php

namespace App\Support;

use Carbon\Carbon;

class GreetingHelper
{
    /**
     * Get greeting based on current time (e.g., "Selamat Pagi", "Selamat Sore").
     */
    public static function sapaanHari(): string
    {
        $range = self::getCurrentTimeRange();

        return 'Selamat '.$range['greeting'];
    }

    /**
     * Get random message based on current time (e.g., "Waktunya ngopi dulu?").
     */
    public static function sapaanWaktu(): string
    {
        $range = self::getCurrentTimeRange();

        return collect($range['messages'])->random();
    }

    /**
     * Get full greeting with typing animation data for Alpine.js.
     * Returns array with greeting and message for client-side animation.
     */
    public static function getTypingGreeting(): array
    {
        return [
            'greeting' => self::sapaanHari(),
            'message' => self::sapaanWaktu(),
        ];
    }

    /**
     * Get current time range configuration based on now().
     */
    private static function getCurrentTimeRange(): array
    {
        $now = Carbon::now('Asia/Jakarta');
        $currentTime = $now->format('H:i');

        $ranges = config('greeting.time_ranges', []);

        foreach ($ranges as $range) {
            if ($currentTime >= $range['start'] && $currentTime <= $range['end']) {
                return $range;
            }
        }

        // Fallback jika tidak ada range yang cocok
        return [
            'greeting' => 'Hari Ini',
            'messages' => ['Selamat datang!'],
        ];
    }
}
