<?php

namespace App\Services;

use App\Models\Tim;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TimAccountGenerator
{
    /**
     * Generate username dengan format YYYY_MM_XXX (reset urutan per tahun).
     */
    public function generateUsername(): string
    {
        $year = now()->format('Y');
        $month = now()->format('m');

        // Cari nomor urut tertinggi untuk tahun berjalan
        $usersTahunIni = User::where('username', 'LIKE', "{$year}_%")
            ->orWhere('email', 'LIKE', "{$year}_%")
            ->get();

        $maxUrut = 0;
        foreach ($usersTahunIni as $user) {
            $identifier = $user->username ?: strstr($user->email, '@', true);
            if (preg_match('/^\d{4}_\d{2}_(\d{3})$/', $identifier, $matches)) {
                $urut = (int) $matches[1];
                if ($urut > $maxUrut) {
                    $maxUrut = $urut;
                }
            }
        }

        $nextUrut = $maxUrut + 1;

        return sprintf('%s_%s_%03d', $year, $month, $nextUrut);
    }

    /**
     * Buat akun login untuk tim (username-only, no email).
     */
    public function createAccount(Tim $tim): User
    {
        $username = $this->generateUsername();

        return User::create([
            'name' => $tim->nama_tim,
            'username' => $username,
            'email' => null, // No email initially
            'password' => Hash::make('inovindojaya'),
            'role' => 'tim',
            'tim_id' => $tim->id,
            'email_verified_at' => null,
        ]);
    }

    /**
     * Buat akun login mandiri (tanpa tim awal) untuk onboarding tim magang.
     */
    public function createStandaloneAccount(): User
    {
        $username = $this->generateUsername();

        return User::create([
            'name' => "Akun Tim ({$username})",
            'username' => $username,
            'email' => null, // No email initially
            'password' => Hash::make('inovindojaya'),
            'role' => 'tim',
            'tim_id' => null,
            'email_verified_at' => null,
        ]);
    }

    /**
     * Buat beberapa akun tim mandiri sekaligus.
     *
     * @return array<User>
     */
    public function createMultipleStandaloneAccounts(int $count = 1): array
    {
        $created = [];
        for ($i = 0; $i < $count; $i++) {
            $created[] = $this->createStandaloneAccount();
        }

        return $created;
    }
}
