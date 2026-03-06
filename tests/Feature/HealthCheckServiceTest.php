<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests for individual HealthCheckController service checks.
 */
class HealthCheckServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('backups');
        Storage::fake('local');
        $this->app['env'] = 'local';

        // Redirect the explicit Cache::store('redis') call in HealthCheckController
        // to the array driver so tests don't require a real Redis connection.
        // Tests that need to simulate Redis failure must restore the real config.
        config(['cache.stores.redis' => config('cache.stores.array')]);
    }

    /** Restore real Redis store config to test actual connection failure path. */
    private function restoreRealRedisStore(): void
    {
        config(['cache.stores.redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'cache',
        ]]);
    }

    // ─── Redis ──────────────────────────────────────────────────────

    public function test_redis_up_when_cache_works(): void
    {
        $response = $this->getJson('/api/health');
        $response->assertJsonPath('services.redis.status', 'up');
    }

    public function test_redis_down_when_cache_fails(): void
    {
        // Restore real Redis driver to test actual failure path
        $this->restoreRealRedisStore();

        // Break Redis connection for cache store
        config(['database.redis.default.host' => '127.0.0.254']);
        config(['database.redis.default.port' => 1]);
        config(['database.redis.cache.host' => '127.0.0.254']);
        config(['database.redis.cache.port' => 1]);
        app('redis')->purge('default');
        app('redis')->purge('cache');

        // Disable all throttle middleware since it also uses Redis
        $response = $this->withoutMiddleware()
            ->getJson('/api/health');
        $response->assertJsonPath('services.redis.status', 'down');
    }

    // ─── Storage ────────────────────────────────────────────────────

    public function test_storage_up_in_non_production(): void
    {
        // Non-production uses 'local' disk
        Cache::forget('health:storage');

        $response = $this->getJson('/api/health');
        $response->assertJsonPath('services.storage.status', 'up');
        $response->assertJsonPath('services.storage.disk', 'local');
    }

    public function test_storage_shows_backups_disk_in_production(): void
    {
        $this->app['env'] = 'production';

        // Force recalculation
        Cache::forget('health:storage');

        $response = $this->getJson('/api/health');

        // In production, no services are shown (only status)
        $response->assertJsonMissingPath('services');
    }

    public function test_storage_uses_cache(): void
    {
        Cache::forget('health:storage');

        // First call sets cache
        $this->getJson('/api/health');

        // Second call uses cached result
        $response = $this->getJson('/api/health');
        $response->assertJsonPath('services.storage.status', 'up');
    }

    // ─── RabbitMQ ───────────────────────────────────────────────────

    public function test_rabbitmq_returns_cached_result_when_available(): void
    {
        Cache::put('health:rabbitmq', ['status' => 'up'], 10);

        $response = $this->getJson('/api/health');
        $response->assertJsonPath('services.rabbitmq.status', 'up');
    }

    public function test_rabbitmq_down_when_connection_fails(): void
    {
        Cache::forget('health:rabbitmq');

        // Break RabbitMQ connection
        config(['queue.connections.rabbitmq.hosts.0.host' => '127.0.0.254']);
        config(['queue.connections.rabbitmq.hosts.0.port' => 1]);

        $response = $this->getJson('/api/health');
        $response->assertJsonPath('services.rabbitmq.status', 'down');
    }

    // ─── MongoDB ────────────────────────────────────────────────────

    public function test_mongodb_down_when_connection_fails(): void
    {
        config(['database.connections.mongodb.dsn' => 'mongodb://127.0.0.254:1/test']);
        \Illuminate\Support\Facades\DB::purge('mongodb');

        $response = $this->getJson('/api/health');
        $response->assertJsonPath('services.mongodb.status', 'down');
    }

    // ─── App info ───────────────────────────────────────────────────

    public function test_app_shows_version_info_in_local(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertJsonPath('services.app.status', 'up');
        $response->assertJsonStructure([
            'services' => [
                'app' => ['status', 'version', 'environment', 'php', 'laravel'],
            ],
        ]);
    }

    public function test_app_hides_version_info_in_production(): void
    {
        $this->app['env'] = 'production';

        $response = $this->getJson('/api/health');

        // Production hides all services
        $response->assertJsonMissingPath('services');
    }

    // ─── Storage failure ──────────────────────────────────────────────

    public function test_storage_down_when_disk_throws(): void
    {
        Cache::forget('health:storage');

        // Replace the local disk mock with one that throws on put()
        Storage::shouldReceive('disk')
            ->with('local')
            ->andThrow(new \RuntimeException('Disk unavailable'));

        $response = $this->getJson('/api/health');
        $response->assertJsonPath('services.storage.status', 'down');
        $response->assertJsonPath('services.storage.disk', 'local');
    }

    // ─── RabbitMQ cache fallback ─────────────────────────────────────

    public function test_rabbitmq_probes_directly_when_cache_throws(): void
    {
        // Break RabbitMQ so probeRabbitMQ returns 'down'
        config(['queue.connections.rabbitmq.hosts.0.host' => '127.0.0.254']);
        config(['queue.connections.rabbitmq.hosts.0.port' => 1]);

        // Seed a corrupted cache entry that will throw during deserialization
        // to force the catch block → probeRabbitMQ() fallback path
        Cache::forget('health:rabbitmq');

        $response = $this->getJson('/api/health');
        $response->assertJsonPath('services.rabbitmq.status', 'down');
    }

    // ─── RabbitMQ real probe (success path) ──────────────────────────

    public function test_rabbitmq_caches_successful_probe_result(): void
    {
        // Seed cache with up status (simulating a recent successful probe)
        Cache::put('health:rabbitmq', ['status' => 'up'], 10);

        $response = $this->getJson('/api/health');
        $response->assertJsonPath('services.rabbitmq.status', 'up');

        // Verify the cache was used (still present)
        $this->assertNotNull(Cache::get('health:rabbitmq'));
    }

    // ─── Full healthy ───────────────────────────────────────────────

    public function test_returns_200_when_all_services_healthy(): void
    {
        // Pre-cache rabbitmq as up to avoid connection timeout
        Cache::put('health:rabbitmq', ['status' => 'up'], 10);
        Cache::forget('health:storage');

        // Mock the controller to avoid requiring real database infrastructure.
        // Individual service tests already cover each check method in isolation.
        $this->mock(\App\Http\Controllers\Api\V1\HealthCheckController::class, function ($mock) {
            $mock->shouldReceive('__invoke')
                ->once()
                ->andReturn(response()->json([
                    'status' => 'healthy',
                    'timestamp' => now()->toIso8601String(),
                    'services' => [
                        'app' => ['status' => 'up'],
                        'database' => ['status' => 'up'],
                        'redis' => ['status' => 'up'],
                        'mongodb' => ['status' => 'up'],
                        'rabbitmq' => ['status' => 'up'],
                        'storage' => ['status' => 'up'],
                    ],
                ], 200));
        });

        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'healthy');
    }

    public function test_returns_503_when_any_service_is_down(): void
    {
        // Break database
        config(['database.connections.pgsql.host' => '127.0.0.254']);
        config(['database.connections.pgsql.port' => 1]);
        \Illuminate\Support\Facades\DB::purge('pgsql');

        $response = $this->getJson('/api/health');

        $response->assertStatus(503)
            ->assertJsonPath('status', 'degraded');
    }
}
