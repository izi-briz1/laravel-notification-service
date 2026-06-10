<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        // Входящий лимит API (Redis-стор кэша): защита от злоупотребления
        // со стороны вызывающих сервисов
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute((int) config('services.api.rate_limit_per_minute'))->by($request->ip());
        });
    }
}
