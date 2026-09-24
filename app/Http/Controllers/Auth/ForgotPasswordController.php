<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
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

        // Send reset link using password broker
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
            $message = 'Tautan reset password telah dikirim ke alamat email terdaftar ('.$maskedEmail.'). Silakan periksa kotak masuk atau spam email Anda.';

            return back()->with('status', $message);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
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
