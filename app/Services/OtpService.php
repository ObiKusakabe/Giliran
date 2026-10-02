<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    /**
     * Generate a 6-digit OTP code.
     */
    public function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Send OTP to user's email and store it in database.
     */
    public function sendOtp(User $user, string $email): bool
    {
        $otp = $this->generateOtp();
        $expiresAt = now()->addMinutes(10);

        // Update user with OTP and expiration
        $user->update([
            'email_verification_otp' => $otp,
            'email_verification_otp_expires_at' => $expiresAt,
        ]);

        // Send email with OTP
        try {
            Mail::send('emails.otp-verification', [
                'otp' => $otp,
                'username' => $user->username,
                'expiresInMinutes' => 10,
            ], function ($message) use ($email) {
                $message->to($email)
                    ->subject('Kode OTP Verifikasi Email - Sistem Giliran WFO');
            });

            return true;
        } catch (\Exception $e) {
            // Log error but don't expose it to user
            logger()->error('Failed to send OTP email', [
                'user_id' => $user->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Verify OTP code against user's stored OTP.
     */
    public function verifyOtp(User $user, string $otp): bool
    {
        // Check if OTP exists and not expired
        if (! $user->email_verification_otp || ! $user->email_verification_otp_expires_at) {
            return false;
        }

        // Check if OTP is expired
        if (now()->isAfter($user->email_verification_otp_expires_at)) {
            return false;
        }

        // Check if OTP matches
        if ($user->email_verification_otp !== $otp) {
            return false;
        }

        return true;
    }

    /**
     * Clear OTP data after successful verification.
     */
    public function clearOtp(User $user): void
    {
        $user->update([
            'email_verification_otp' => null,
            'email_verification_otp_expires_at' => null,
        ]);
    }

    /**
     * Check if user can request a new OTP (rate limiting).
     */
    public function canRequestOtp(User $user): bool
    {
        // If no OTP exists, allow request
        if (! $user->email_verification_otp_expires_at) {
            return true;
        }

        // Allow new request if OTP expired
        if (now()->isAfter($user->email_verification_otp_expires_at)) {
            return true;
        }

        // Otherwise, user must wait (OTP still valid)
        return false;
    }

    /**
     * Get remaining time until OTP expires (in seconds).
     */
    public function getOtpRemainingTime(User $user): ?int
    {
        if (! $user->email_verification_otp_expires_at) {
            return null;
        }

        $remaining = now()->diffInSeconds($user->email_verification_otp_expires_at, false);

        return $remaining > 0 ? (int) $remaining : 0;
    }

    /**
     * Send Password Reset OTP to user's registered email.
     */
    public function sendPasswordResetOtp(User $user, ?string $token = null): bool
    {
        if (empty($user->email)) {
            return false;
        }

        $otp = $this->generateOtp();
        $expiresAt = now()->addMinutes(15);

        $user->update([
            'password_reset_otp' => $otp,
            'password_reset_otp_expires_at' => $expiresAt,
            'password_reset_otp_attempts' => 0,
        ]);

        $url = $token ? url(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ], false)) : null;

        $username = $user->name ?: ($user->username ?: 'Pengguna');

        try {
            Mail::send('emails.reset-password', [
                'otp' => $otp,
                'username' => $username,
                'expiresInMinutes' => 15,
                'url' => $url,
            ], function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Kode OTP Reset Password - Sistem Giliran WFO');
            });

            return true;
        } catch (\Throwable $e) {
            logger()->error('Failed to send password reset OTP email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Verify Password Reset OTP code.
     *
     * @return array{valid: bool, message: string}
     */
    public function verifyPasswordResetOtp(User $user, string $otp): array
    {
        if (! $user->password_reset_otp || ! $user->password_reset_otp_expires_at) {
            return [
                'valid' => false,
                'message' => 'Kode OTP tidak ditemukan atau telah kedaluwarsa. Silakan minta kode OTP baru.',
            ];
        }

        if (now()->isAfter($user->password_reset_otp_expires_at)) {
            $this->clearPasswordResetOtp($user);

            return [
                'valid' => false,
                'message' => 'Kode OTP telah kedaluwarsa. Silakan minta kode OTP baru.',
            ];
        }

        if ($user->password_reset_otp_attempts >= 5) {
            $this->clearPasswordResetOtp($user);

            return [
                'valid' => false,
                'message' => 'Terlalu banyak percobaan kode OTP yang salah. Silakan minta kode OTP baru.',
            ];
        }

        if ($user->password_reset_otp !== trim($otp)) {
            $user->increment('password_reset_otp_attempts');
            $remaining = max(0, 5 - $user->password_reset_otp_attempts);

            return [
                'valid' => false,
                'message' => "Kode OTP salah. Sisa kesempatan mencoba: {$remaining} kali.",
            ];
        }

        return [
            'valid' => true,
            'message' => 'Kode OTP valid.',
        ];
    }

    /**
     * Clear Password Reset OTP after successful reset.
     */
    public function clearPasswordResetOtp(User $user): void
    {
        $user->update([
            'password_reset_otp' => null,
            'password_reset_otp_expires_at' => null,
            'password_reset_otp_attempts' => 0,
        ]);
    }

    /**
     * Check if user can request a new Password Reset OTP (60 seconds cooldown).
     */
    public function canRequestPasswordResetOtp(User $user): bool
    {
        return $this->getPasswordResetOtpCooldownRemaining($user) <= 0;
    }

    /**
     * Get remaining cooldown seconds before user can request a new Password Reset OTP.
     */
    public function getPasswordResetOtpCooldownRemaining(User $user): int
    {
        if (! $user->password_reset_otp_expires_at) {
            return 0;
        }

        // OTP expires in 15 minutes (900 seconds). Cooldown is 60 seconds from generation.
        // That means if remaining expiration > 840 seconds (900 - 60), cooldown is active.
        $remainingSeconds = now()->diffInSeconds($user->password_reset_otp_expires_at, false);
        $cooldown = $remainingSeconds - (15 * 60 - 60);

        return $cooldown > 0 ? (int) $cooldown : 0;
    }
}
