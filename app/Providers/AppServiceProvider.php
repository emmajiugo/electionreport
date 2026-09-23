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
     * Limits stay loose per IP because Nigerian mobile carriers put many voters behind one
     * carrier-grade NAT address; duplicate VINs are the primary defence, not IP limits.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('reports', fn (Request $request): array => [
            Limit::perMinute(10)->by('minute:'.$request->ip()),
            Limit::perDay(200)->by('day:'.$request->ip()),
        ]);

        RateLimiter::for('vin', fn (Request $request): array => [
            Limit::perMinute(10)->by('vin-minute:'.$request->ip()),
            Limit::perDay(200)->by('vin-day:'.$request->ip()),
        ]);

        RateLimiter::for('locations', fn (Request $request): Limit => Limit::perMinute(120)->by($request->ip()));
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
