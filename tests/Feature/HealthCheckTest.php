<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('backups');
    }

    public function test_health_check_returns_json_with_status(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertHeader('content-type', 'application/json')
            ->assertJsonStructure(['status', 'timestamp']);
    }

    public function test_health_check_has_dedicated_throttle(): void
    {
        $route = app('router')->getRoutes()->getByName('health');
        $this->assertNotNull($route);

        $excluded = $route->excludedMiddleware();
        $this->assertContains('throttle:api', $excluded);

        $middleware = $route->middleware();
        $this->assertContains('throttle:health', $middleware);
    }

    public function test_health_check_shows_services_and_debug_info_in_local_env(): void
    {
        $this->app['env'] = 'local';

        $response = $this->getJson('/api/health');

        $response->assertJsonPath('services.app.status', 'up');
        $response->assertJsonStructure([
            'services' => [
                'app' => ['status', 'php', 'laravel'],
                'database' => ['status'],
                'redis' => ['status'],
                'mongodb' => ['status'],
                'rabbitmq' => ['status'],
                'storage' => ['status'],
            ],
        ]);
    }

    public function test_health_check_hides_services_in_testing_env(): void
    {
        $this->app['env'] = 'testing';

        $response = $this->getJson('/api/health');

        $response->assertJsonMissingPath('services');
        $response->assertJsonStructure(['status', 'timestamp']);
    }

    public function test_health_check_hides_services_in_production(): void
    {
        $this->app['env'] = 'production';

        $response = $this->getJson('/api/health');

        $response->assertJsonMissingPath('services');
        $response->assertJsonStructure(['status', 'timestamp']);
    }

    public function test_builtin_health_check_returns_ok(): void
    {
        $response = $this->get('/up');

        $response->assertStatus(200);
    }

    public function test_api_routes_require_json_accept_header(): void
    {
        $response = $this->get('/api/health');

        $response->assertHeader('content-type', 'application/json');
    }
}
