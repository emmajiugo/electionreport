<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
        $this->configureRateLimiting();
    }

    /**
     * Limits are keyed per session, not IP, because Nigerian mobile carriers put many voters behind
     * one carrier-grade NAT address. Turnstile on VIN entry is the bot defence: every other limited
     * route needs a session that has already passed it.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('reports', fn (Request $request): Limit => Limit::perMinute(10)->by('reports:'.$request->session()->getId()));

        RateLimiter::for('vin', fn (Request $request): Limit => Limit::perMinute(10)->by('vin:'.$request->session()->getId()));

        RateLimiter::for('locations', fn (Request $request): Limit => Limit::perMinute(60)->by('locations:'.$request->session()->getId()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

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
            : null,
        );
    }
}
