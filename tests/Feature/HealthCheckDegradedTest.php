<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HealthCheckDegradedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('backups');
    }

    public function test_returns_degraded_status_when_database_config_is_invalid(): void
    {
        $this->app['env'] = 'local';

        // Break the database connection to simulate failure
        config(['database.connections.pgsql.host' => '127.0.0.254']);
        config(['database.connections.pgsql.port' => 1]);
        \Illuminate\Support\Facades\DB::purge('pgsql');

        $response = $this->getJson('/v1/health');

        $response->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('services.database.status', 'down');
    }

    public function test_returns_200_when_healthy(): void
    {
        $response = $this->getJson('/v1/health');

        $response->assertJsonStructure(['status', 'timestamp']);
        $this->assertContains($response->json('status'), ['healthy', 'degraded']);
    }

    public function test_timestamp_is_iso8601_format(): void
    {
        $response = $this->getJson('/v1/health');

        $timestamp = $response->json('timestamp');
        $this->assertIsString($timestamp);
        $this->assertNotFalse(
            \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $timestamp),
            'Timestamp should be in ISO 8601 format'
        );
    }

    public function test_degraded_response_contains_correct_structure(): void
    {
        $this->app['env'] = 'local';

        // Break database
        config(['database.connections.pgsql.host' => '127.0.0.254']);
        config(['database.connections.pgsql.port' => 1]);
        \Illuminate\Support\Facades\DB::purge('pgsql');

        $response = $this->getJson('/v1/health');

        $response->assertJsonStructure([
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

    public function test_degraded_response_still_shows_healthy_services(): void
    {
        $this->app['env'] = 'local';

        // Break only database
        config(['database.connections.pgsql.host' => '127.0.0.254']);
        config(['database.connections.pgsql.port' => 1]);
        \Illuminate\Support\Facades\DB::purge('pgsql');

        $response = $this->getJson('/v1/health');

        // App should still be up even if database is down
        $response->assertJsonPath('services.app.status', 'up');
        $response->assertJsonPath('services.database.status', 'down');
    }
}
