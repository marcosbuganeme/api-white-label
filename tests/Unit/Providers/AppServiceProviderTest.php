<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    public function test_api_rate_limiter_is_registered(): void
    {
        $limiter = RateLimiter::limiter('api');

        $this->assertNotNull($limiter, 'API rate limiter should be registered');
    }

    public function test_health_rate_limiter_is_registered(): void
    {
        $limiter = RateLimiter::limiter('health');

        $this->assertNotNull($limiter, 'Health rate limiter should be registered');
    }

    public function test_api_rate_limiter_returns_limit_for_guest(): void
    {
        $request = Request::create('/api/health');
        $limiter = RateLimiter::limiter('api');
        $this->assertNotNull($limiter);

        $limit = $limiter($request);

        $this->assertInstanceOf(Limit::class, $limit);
    }

    public function test_health_rate_limiter_returns_limit(): void
    {
        $request = Request::create('/api/health');
        $limiter = RateLimiter::limiter('health');
        $this->assertNotNull($limiter);

        $limit = $limiter($request);

        $this->assertInstanceOf(Limit::class, $limit);
    }

    public function test_telescope_is_excluded_from_auto_discovery(): void
    {
        $composerJson = json_decode(
            (string) file_get_contents(base_path('composer.json')),
            true,
        );

        $dontDiscover = $composerJson['extra']['laravel']['dont-discover'] ?? [];

        $this->assertContains(
            'laravel/telescope',
            $dontDiscover,
            'Telescope must be in dont-discover to prevent auto-registration outside local env'
        );
    }

    public function test_telescope_registration_is_gated_by_local_environment(): void
    {
        $source = file_get_contents(app_path('Providers/AppServiceProvider.php'));
        $this->assertIsString($source);

        $this->assertStringContainsString(
            "environment('local')",
            $source,
            'Telescope registration should be gated by local environment check'
        );
    }
}
