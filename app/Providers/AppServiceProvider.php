<?php

namespace App\Providers;

use App\Models\NotulenBriefing;
use App\Policies\NotulenBriefingPolicy;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureDefaults();
        $this->configurePolicies();
        $this->configureRateLimiting();
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

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : Password::min(8)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols(),
        );
    }
}
