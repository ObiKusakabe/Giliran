<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ResetPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        $email = $request->input('email', session('reset_email', ''));
        $token = $request->route('token', 'otp');

        $activeOtp = null;
        if ($email && (config('mail.default') === 'log' || app()->isLocal())) {
            $user = User::where('email', $email)->first();
            if ($user && $user->password_reset_otp && now()->isBefore($user->password_reset_otp_expires_at)) {
                $activeOtp = $user->password_reset_otp;
            }
        }

        return view('pages.auth.reset-password', [
            'request' => $request,
            'token' => $token,
            'email' => $email,
            'activeOtp' => $activeOtp,
        ]);
    }

    /**
     * Reset the user's password using either OTP code or reset token.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', Rules\Password::defaults(), 'confirmed'],
            'token' => ['nullable', 'string'],
            'otp' => ['nullable', 'string'],
        ], [
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $email = trim($request->input('email'));
        $otp = trim((string) $request->input('otp', ''));
        $token = trim((string) $request->input('token', ''));

        $user = User::where('email', $email)->first();
        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['Pengguna dengan alamat email tersebut tidak ditemukan.'],
            ]);
        }

        $otpService = app(OtpService::class);

        // 1. Prioritaskan verifikasi OTP jika kode OTP diisi
        if (! empty($otp)) {
            $verification = $otpService->verifyPasswordResetOtp($user, $otp);

            if (! $verification['valid']) {
                throw ValidationException::withMessages([
                    'otp' => [$verification['message']],
                ]);
            }

            // Update user's password
            $user->forceFill([
                'password' => Hash::make($request->input('password')),
                'remember_token' => Str::random(60),
            ])->save();

            // Bersihkan OTP dan token broker jika ada
            $otpService->clearPasswordResetOtp($user);
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            event(new PasswordReset($user));

            return redirect()->route('login')->with('status', 'Password berhasil diatur ulang! Silakan masuk menggunakan password baru Anda.');
        }

        // 2. Jika OTP kosong tapi token disertakan dan bukan placeholder 'otp'
        if (! empty($token) && $token !== 'otp') {
            $status = Password::broker(config('fortify.passwords'))->reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function (User $user, string $password) use ($otpService) {
                    $user->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ])->save();

                    $otpService->clearPasswordResetOtp($user);
                    event(new PasswordReset($user));
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return redirect()->route('login')->with('status', 'Password berhasil diatur ulang! Silakan masuk menggunakan password baru Anda.');
            }

            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        // 3. Jika tidak ada OTP maupun token valid
        throw ValidationException::withMessages([
            'otp' => ['Silakan masukkan kode OTP 6 digit yang telah dikirim ke email Anda.'],
        ]);
    }
}
