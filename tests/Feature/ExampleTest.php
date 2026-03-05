<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('backups');
    }

    public function test_health_check_returns_json_structure(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertHeader('content-type', 'application/json')
            ->assertJsonStructure([
                'status',
                'timestamp',
                'services' => [
                    'app' => ['status'],
                    'database' => ['status'],
                    'redis' => ['status'],
                    'mongodb' => ['status'],
                    'rabbitmq' => ['status'],
                    'storage' => ['status'],
                ],
            ]);
    }

    public function test_health_check_is_not_rate_limited(): void
    {
        for ($i = 0; $i < 65; $i++) {
            $response = $this->getJson('/api/health');
            $this->assertNotEquals(429, $response->getStatusCode(), "Request #{$i} was rate limited");
        }
    }

    public function test_health_check_shows_debug_info_in_local_env(): void
    {
        $this->app['env'] = 'local';

        $response = $this->getJson('/api/health');

        $response->assertJsonPath('services.app.status', 'up');
        $response->assertJsonStructure([
            'services' => [
                'app' => ['php', 'laravel'],
            ],
        ]);
    }

    public function test_health_check_hides_debug_info_outside_local_env(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertJsonMissingPath('services.app.php');
        $response->assertJsonMissingPath('services.app.laravel');
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
