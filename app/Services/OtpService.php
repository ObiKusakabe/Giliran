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
}
