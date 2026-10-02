<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class ForgotPasswordController extends Controller
{
    /**
     * Send a password reset link to the given user (supporting email or username).
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string'],
        ], [
            'email.required' => 'Email atau username wajib diisi.',
        ]);

        $credential = trim($request->input('email'));

        // Find user by email OR username
        $user = User::where('email', $credential)
            ->orWhere('username', $credential)
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['Akun dengan email atau username tersebut tidak ditemukan.'],
            ]);
        }

        if (empty($user->email)) {
            throw ValidationException::withMessages([
                'email' => ['Akun "'.$user->username.'" belum memiliki alamat email terdaftar. Silakan hubungi Admin untuk mereset password akun Anda.'],
            ]);
        }

        $otpService = app(OtpService::class);

        // Cek rate-limit cooldown (60 detik)
        if (! $otpService->canRequestPasswordResetOtp($user)) {
            $cooldown = $otpService->getPasswordResetOtpCooldownRemaining($user);
            $maskedEmail = $this->maskEmail($user->email);

            return redirect()->route('password.reset', [
                'token' => 'otp',
                'email' => $user->email,
            ])->with('status', "Kode OTP baru saja dikirim ke {$maskedEmail}. Silakan tunggu {$cooldown} detik sebelum meminta kode baru.");
        }

        // Generate dan simpan kode OTP pada user
        $otp = $otpService->generateOtp();
        $user->update([
            'password_reset_otp' => $otp,
            'password_reset_otp_expires_at' => now()->addMinutes(15),
            'password_reset_otp_attempts' => 0,
        ]);

        // Send reset link using password broker (memicu ResetPassword notification ber-OTP)
        try {
            $status = Password::broker(config('fortify.passwords'))->sendResetLink([
                'email' => $user->email,
            ]);
        } catch (\Throwable $e) {
            logger()->error('Failed to send password reset email', [
                'user' => $user->username,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'email' => ['Gagal mengirim email reset password: '.$e->getMessage()],
            ]);
        }

        if ($status === Password::RESET_LINK_SENT) {
            $maskedEmail = $this->maskEmail($user->email);
            $message = 'Kode OTP reset password telah dikirim ke alamat email terdaftar ('.$maskedEmail.'). Silakan masukkan kode OTP di bawah ini.';

            return redirect()->route('password.reset', [
                'token' => 'otp',
                'email' => $user->email,
            ])->with('status', $message)->with('reset_email', $user->email);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    /**
     * Resend OTP code for password reset.
     */
    public function resendOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
        ]);

        $user = User::where('email', trim($request->input('email')))->first();
        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['Akun dengan email tersebut tidak ditemukan.'],
            ]);
        }

        $otpService = app(OtpService::class);

        if (! $otpService->canRequestPasswordResetOtp($user)) {
            $cooldown = $otpService->getPasswordResetOtpCooldownRemaining($user);

            return back()->withErrors([
                'otp' => "Harap tunggu {$cooldown} detik sebelum meminta kode OTP baru.",
            ]);
        }

        $otp = $otpService->generateOtp();
        $user->update([
            'password_reset_otp' => $otp,
            'password_reset_otp_expires_at' => now()->addMinutes(15),
            'password_reset_otp_attempts' => 0,
        ]);

        try {
            Password::broker(config('fortify.passwords'))->sendResetLink([
                'email' => $user->email,
            ]);
        } catch (\Throwable $e) {
            return back()->withErrors([
                'otp' => 'Gagal mengirim ulang kode OTP: '.$e->getMessage(),
            ]);
        }

        $maskedEmail = $this->maskEmail($user->email);

        return back()->with('status', "Kode OTP baru telah dikirim ke {$maskedEmail}.");
    }

    /**
     * Mask an email address for privacy display.
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }

        $name = $parts[0];
        $domain = $parts[1];

        $len = strlen($name);
        if ($len <= 2) {
            $maskedName = substr($name, 0, 1).'*';
        } else {
            $maskedName = substr($name, 0, 1).str_repeat('*', min(4, $len - 2)).substr($name, -1);
        }

        return $maskedName.'@'.$domain;
    }
}
