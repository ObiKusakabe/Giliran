<?php

use App\Services\OtpService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tambah Email')] #[Layout('layouts.app')] class extends Component {
    public string $email = '';
    public string $otp = '';
    public bool $otpSent = false;
    public ?int $remainingTime = null;

    public function mount(): void
    {
        // Redirect if user already has email
        if (Auth::user()->email) {
            $this->redirect(route('tim.beranda'), navigate: true);
            return;
        }

        // Check if there's an active OTP
        $otpService = app(OtpService::class);
        $this->remainingTime = $otpService->getOtpRemainingTime(Auth::user());
        
        if ($this->remainingTime && $this->remainingTime > 0) {
            $this->otpSent = true;
        }
    }

    public function kirimOtp(): void
    {
        $this->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah digunakan oleh akun lain.',
        ]);

        $otpService = app(OtpService::class);
        $user = Auth::user();

        // Check rate limiting
        if (! $otpService->canRequestOtp($user)) {
            Flux::toast(
                variant: 'warning',
                text: 'Silakan tunggu hingga kode OTP sebelumnya kedaluwarsa.'
            );
            return;
        }

        // Send OTP
        $success = $otpService->sendOtp($user, $this->email);

        if ($success) {
            $this->otpSent = true;
            $this->remainingTime = 600; // 10 minutes in seconds

            Flux::toast(
                variant: 'success',
                text: "Kode OTP telah dikirim ke {$this->email}. Periksa inbox Anda."
            );
        } else {
            Flux::toast(
                variant: 'danger',
                text: 'Gagal mengirim kode OTP. Silakan coba lagi atau hubungi admin.'
            );
        }
    }

    public function updatedOtp($value): void
    {
        $clean = preg_replace('/\D/', '', (string) $value);
        if (strlen($clean) === 6) {
            $this->verifikasiOtp();
        }
    }

    public function verifikasiOtp(): void
    {
        $this->validate([
            'otp' => 'required|digits:6',
        ], [
            'otp.required' => 'Kode OTP wajib diisi.',
            'otp.digits' => 'Kode OTP harus 6 digit angka.',
        ]);

        $otpService = app(OtpService::class);
        $user = Auth::user();

        // Verify OTP
        if ($otpService->verifyOtp($user, $this->otp)) {
            // Update user email and mark as verified
            $user->update([
                'email' => $this->email,
                'email_verified_at' => now(),
            ]);

            // Clear OTP data
            $otpService->clearOtp($user);

            Flux::toast(
                variant: 'success',
                text: 'Email berhasil diverifikasi dan ditambahkan ke akun Anda!'
            );

            // Redirect to dashboard
            $this->redirect(route('tim.beranda'), navigate: true);
        } else {
            Flux::toast(
                variant: 'danger',
                text: 'Kode OTP tidak valid atau sudah kedaluwarsa. Silakan minta kode baru.'
            );
        }
    }

    public function kirimUlang(): void
    {
        $otpService = app(OtpService::class);
        $user = Auth::user();

        if (! $otpService->canRequestOtp($user)) {
            Flux::toast(
                variant: 'warning',
                text: 'Anda hanya bisa meminta kode baru setelah kode sebelumnya kedaluwarsa.'
            );
            return;
        }

        // Clear form and allow new request
        $this->otpSent = false;
        $this->otp = '';
        $this->remainingTime = null;

        Flux::toast(
            variant: 'info',
            text: 'Silakan kirim kode OTP baru.'
        );
    }

    public function batal(): void
    {
        $this->redirect(route('tim.beranda'), navigate: true);
    }
}; ?>

<div class="max-w-2xl mx-auto py-8 px-4">
    <flux:card class="p-8">
        <div class="mb-6">
            <flux:heading size="lg" class="mb-2">Tambah Email untuk Keamanan Akun</flux:heading>
            <flux:text class="text-zinc-500">
                Tambahkan email Anda untuk meningkatkan keamanan akun dan memudahkan pemulihan akun jika lupa password.
            </flux:text>
        </div>

        @if (!$otpSent)
            {{-- Step 1: Enter Email --}}
            <form wire:submit="kirimOtp" class="space-y-5">
                <flux:field>
                    <flux:label>Email Anda</flux:label>
                    <flux:input
                        wire:model="email"
                        type="email"
                        placeholder="email@inovindo.co.id"
                        autocomplete="email"
                    />
                    <flux:description>
                        Masukkan alamat email yang valid. Kode OTP akan dikirim ke email ini.
                    </flux:description>
                    <flux:error name="email" />
                </flux:field>

                <div class="bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <div class="flex gap-3">
                        <flux:icon icon="information-circle" class="size-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                        <div class="text-sm text-blue-900 dark:text-blue-100">
                            <p class="font-medium mb-1">Cara Kerja Verifikasi Email:</p>
                            <ol class="list-decimal list-inside space-y-1 text-blue-700 dark:text-blue-300">
                                <li>Masukkan email Anda dan klik "Kirim Kode OTP"</li>
                                <li>Periksa inbox email Anda untuk kode 6 digit</li>
                                <li>Masukkan kode OTP untuk verifikasi</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <flux:button
                        type="button"
                        variant="ghost"
                        wire:click="batal"
                    >
                        Nanti Saja
                    </flux:button>
                    <flux:button
                        type="submit"
                        variant="primary"
                        wire:loading.attr="disabled"
                        wire:target="kirimOtp"
                    >
                        <span wire:loading.remove wire:target="kirimOtp">Kirim Kode OTP</span>
                        <span wire:loading wire:target="kirimOtp">Mengirim...</span>
                    </flux:button>
                </div>
            </form>
        @else
            {{-- Step 2: Enter OTP --}}
            <form wire:submit="verifikasiOtp" class="space-y-5">
                <div class="bg-green-50 dark:bg-green-950/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <flux:icon icon="check-circle" class="size-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" />
                        <div class="text-sm">
                            <p class="font-medium text-green-900 dark:text-green-100 mb-1">
                                Kode OTP telah dikirim!
                            </p>
                            <p class="text-green-700 dark:text-green-300">
                                Periksa inbox <strong>{{ $email }}</strong> untuk kode verifikasi 6 digit.
                            </p>
                        </div>
                    </div>
                </div>

                <flux:field>
                    <flux:label>Kode OTP</flux:label>
                    <flux:input
                        wire:model.live="otp"
                        type="text"
                        placeholder="000000"
                        maxlength="6"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        autofocus
                    />
                    <flux:description>
                        Masukkan kode 6 digit yang dikirim ke email Anda.
                        @if ($remainingTime && $remainingTime > 0)
                            <span class="text-amber-600 dark:text-amber-400 font-medium">
                                Kode berlaku selama {{ floor($remainingTime / 60) }} menit {{ $remainingTime % 60 }} detik.
                            </span>
                        @endif
                    </flux:description>
                    @if (config('mail.default') === 'log' && auth()->user()?->email_verification_otp)
                        <div class="mt-2 text-xs bg-blue-50 dark:bg-blue-950/30 p-2.5 rounded-lg border border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-200 flex items-center justify-between">
                            <span>Mode Log Mailer aktif (kode OTP):</span>
                            <span class="font-mono font-bold tracking-wider text-sm bg-white dark:bg-zinc-900 px-2 py-0.5 rounded border border-blue-300 dark:border-blue-700">{{ auth()->user()->email_verification_otp }}</span>
                        </div>
                    @endif
                    <flux:error name="otp" />
                </flux:field>

                <div class="bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
                    <div class="flex gap-3">
                        <flux:icon icon="exclamation-triangle" class="size-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
                        <div class="text-sm text-amber-900 dark:text-amber-100">
                            <p class="font-medium mb-1">Tips:</p>
                            <ul class="list-disc list-inside space-y-1 text-amber-700 dark:text-amber-300">
                                <li>Periksa folder Spam/Junk jika tidak menerima email</li>
                                <li>Kode OTP berlaku selama 10 menit</li>
                                <li>Jangan bagikan kode OTP kepada siapa pun</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <flux:button
                        type="button"
                        variant="ghost"
                        wire:click="kirimUlang"
                        wire:loading.attr="disabled"
                        wire:target="kirimUlang"
                    >
                        Kirim Ulang Kode
                    </flux:button>
                    <flux:button
                        type="submit"
                        variant="primary"
                        wire:loading.attr="disabled"
                        wire:target="verifikasiOtp"
                    >
                        <span wire:loading.remove wire:target="verifikasiOtp">Verifikasi & Simpan</span>
                        <span wire:loading wire:target="verifikasiOtp">Memverifikasi...</span>
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:card>
</div>
