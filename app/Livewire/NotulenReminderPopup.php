<?php

namespace App\Livewire;

use App\Models\JadwalBriefing;
use App\Models\NotulenBriefing;
use Livewire\Component;

class NotulenReminderPopup extends Component
{
    public bool $showModal = false;

    public ?array $briefingData = null;

    public function mount(): void
    {
        // Check apakah user adalah tim (bukan admin)
        if (! auth()->user() || ! auth()->user()->isTim()) {
            return;
        }

        // Check apakah ada tugas notulensi hari ini
        $today = now()->toDateString();
        $currentTime = now();
        $currentHour = $currentTime->hour;
        $currentMinute = $currentTime->minute;

        // ========== TESTING MODE ==========
        // Uncomment untuk testing jam tertentu
        // $currentHour = 6;  // ← ubah ini untuk testing jam berapa
        // $currentMinute = 0;
        // ==================================

        // Tentukan sesi based on time
        $sesi = null;
        if ($currentHour >= 8 && ($currentHour < 11 || ($currentHour == 11 && $currentMinute == 0))) {
            // Pagi: 08:50-11:00
            if ($currentHour > 8 || ($currentHour == 8 && $currentMinute >= 50)) {
                $sesi = 'pagi';
            }
        } elseif ($currentHour >= 16 && ($currentHour < 18 || ($currentHour == 18 && $currentMinute == 0))) {
            // Sore: 16:50-18:00
            if ($currentHour > 16 || ($currentHour == 16 && $currentMinute >= 50)) {
                $sesi = 'sore';
            }
        }

        // Kalau di luar jam, tidak perlu popup
        if (! $sesi) {
            return;
        }

        // Cek apakah tim ini dapat tugas notulensi hari ini
        $timId = auth()->user()->tim_id;
        $jadwal = JadwalBriefing::where('tim_id', $timId)
            ->where('tanggal', $today)
            ->where('sesi', $sesi)
            ->where('is_notulen', true)
            ->first();

        if (! $jadwal) {
            return;
        }

        // Cek apakah sudah pernah isi notulensi untuk sesi ini
        $sudahIsi = NotulenBriefing::where('tim_id', $timId)
            ->where('tanggal', $today)
            ->where('sesi', $sesi)
            ->exists();

        if ($sudahIsi) {
            return;
        }

        // Cek apakah sudah pernah dismiss popup hari ini (session)
        $sessionKey = "notulen_reminder_dismissed_{$today}_{$sesi}";
        if (session($sessionKey)) {
            return;
        }

        // Show modal
        $this->showModal = true;
        $this->briefingData = [
            'tanggal' => $jadwal->tanggal->translatedFormat('l, d F Y'),
            'sesi' => ucfirst($sesi),
            'tim' => $jadwal->tim->nama_tim,
        ];
    }

    public function dismiss(): void
    {
        $today = now()->toDateString();
        $currentTime = now();
        $sesi = ($currentTime->hour < 12) ? 'pagi' : 'sore';

        // Set session supaya tidak muncul lagi hari ini
        session(["notulen_reminder_dismissed_{$today}_{$sesi}" => true]);

        $this->showModal = false;
    }

    public function goToNotulen()
    {
        $this->dismiss();

        return redirect()->route('tim.notulen-briefing');
    }

    public function render()
    {
        return view('livewire.notulen-reminder-popup');
    }
}
