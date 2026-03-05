<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_health_check_endpoint_returns_json(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'timestamp',
                'services' => [
                    'app' => ['status', 'version', 'environment', 'php', 'laravel'],
                ],
            ]);
    }

    public function test_builtin_health_check_returns_ok(): void
    {
        $response = $this->get('/up');

        $response->assertStatus(200);
    }
}
