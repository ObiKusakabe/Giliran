<?php

namespace App\Providers;

use App\Models\NotulenBriefing;
use App\Policies\NotulenBriefingPolicy;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS scheme untuk Cloudflare Tunnel
        if (config('app.env') === 'local' && str_contains(config('app.url'), 'trycloudflare.com')) {
            URL::forceScheme('https');
        }

        $this->configureDefaults();
        $this->configurePolicies();
        $this->configureRateLimiting();
        $this->configurePasswordReset();
    }

    /**
     * Configure password reset notification email in Indonesian.
     */
    protected function configurePasswordReset(): void
    {
        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            $username = $notifiable->nama ?? $notifiable->username ?? 'Pengguna';
            $expiresInMinutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

            return (new MailMessage)
                ->subject('Atur Ulang Kata Sandi - '.config('app.name'))
                ->view('emails.reset-password', [
                    'url' => $url,
                    'username' => $username,
                    'expiresInMinutes' => $expiresInMinutes,
                ]);
        });
    }

    /**
     * Configure authorization policies.
     */
    protected function configurePolicies(): void
    {
        Gate::policy(NotulenBriefing::class, NotulenBriefingPolicy::class);
    }

    /**
     * Configure rate limiting for login attempts.
     */
    protected function configureRateLimiting(): void
    {
        // Login rate limiter: max 5 attempts per minute per username+IP
        RateLimiter::for('login', function (Request $request) {
            $username = $request->input('username', $request->input('email', ''));
            $throttleKey = strtolower($username).'|'.$request->ip();

            return Limit::perMinute(5)->by($throttleKey);
        });

        // Two-factor authentication rate limiter
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Set Carbon locale ke Indonesia supaya translatedFormat() output Bahasa Indonesia
        Carbon::setLocale('id');
        CarbonImmutable::setLocale('id');

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => Password::min(8));
    }
}
