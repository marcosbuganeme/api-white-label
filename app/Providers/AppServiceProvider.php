<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Telescope: somente em ambiente local (TELESCOPE_ENABLED é ignorado fora de 'local')
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(600)->by('user:'.$request->user()->id)
                : Limit::perMinute(60)->by('ip:'.$request->ip());
        });

        RateLimiter::for('health', function (Request $request) {
            return Limit::perMinute(60)->by('health:'.$request->ip());
        });

        if ($this->app->isProduction()
            && empty(config('cors.allowed_origins'))
            && empty(config('cors.allowed_origins_patterns'))
        ) {
            Log::warning('CORS_ALLOWED_ORIGINS não configurado. Requisições cross-origin de browsers serão rejeitadas.');
        }
    }
}
