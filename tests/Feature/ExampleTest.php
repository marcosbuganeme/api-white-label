<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
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
                ],
            ]);
    }

    public function test_health_check_is_not_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $response = $this->getJson('/api/health');
            $this->assertNotEquals(429, $response->getStatusCode(), 'Health check should not be rate limited');
            $response->assertJsonPath('services.app.status', 'up');
        }
    }

    public function test_health_check_shows_debug_info_only_in_local(): void
    {
        $response = $this->getJson('/api/health');

        if (app()->environment('local')) {
            $response->assertJsonPath('services.app.status', 'up');
            $response->assertJsonStructure(['services' => ['app' => ['php', 'laravel']]]);
        } else {
            $response->assertJsonMissingPath('services.app.php');
            $response->assertJsonMissingPath('services.app.laravel');
        }
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
