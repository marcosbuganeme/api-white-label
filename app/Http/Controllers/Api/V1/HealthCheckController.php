<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class HealthCheckController extends Controller
{
    /**
     * Full health check (backward-compatible: checks all services).
     */
    public function __invoke(): JsonResponse
    {
        return $this->ready();
    }

    /**
     * Liveness probe: is the application process alive and able to serve requests?
     * Does NOT check external dependencies. Used by orchestrators to decide restarts.
     */
    public function live(): JsonResponse
    {
        return response()->json([
            'status' => 'alive',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Readiness probe: is the application ready to accept traffic?
     * Checks all external dependencies. Used by load balancers for routing decisions.
     */
    public function ready(): JsonResponse
    {
        $checks = [
            'app' => $this->checkApp(),
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'mongodb' => $this->checkMongoDB(),
            'rabbitmq' => $this->checkRabbitMQ(),
            'storage' => $this->checkStorage(),
        ];

        $isHealthy = collect($checks)
            ->every(fn (array $service) => $service['status'] === 'up');

        $response = [
            'status' => $isHealthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
        ];

        if (app()->environment('local')) {
            $response['services'] = $checks;
        }

        return response()->json($response, $isHealthy ? 200 : 503);
    }

    /** @return array<string, string> */
    private function checkApp(): array
    {
        $base = ['status' => 'up'];

        if (app()->environment('local')) {
            return array_merge($base, [
                'version' => (string) config('app.version', '1.0.0'),
                'environment' => (string) app()->environment(),
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
            ]);
        }

        return $base;
    }

    /** @return array<string, string> */
    private function checkDatabase(): array
    {
        try {
            DB::connection('pgsql')->getPdo();

            return ['status' => 'up', 'driver' => 'pgsql'];
        } catch (\Throwable $e) {
            Log::warning('Health check failed for pgsql', ['error' => class_basename($e).': '.$e->getCode()]);

            return ['status' => 'down', 'driver' => 'pgsql'];
        }
    }

    /** @return array<string, string> */
    private function checkRedis(): array
    {
        try {
            Cache::store('redis')->put('health_check', true, 5);
            Cache::store('redis')->forget('health_check');

            return ['status' => 'up'];
        } catch (\Throwable $e) {
            Log::warning('Health check failed for redis', ['error' => class_basename($e).': '.$e->getCode()]);

            return ['status' => 'down'];
        }
    }

    /** @return array<string, string> */
    private function checkMongoDB(): array
    {
        try {
            /** @var \MongoDB\Laravel\Connection $connection */
            $connection = DB::connection('mongodb');

            $connection->getDatabase()->command(['ping' => 1]);

            return ['status' => 'up'];
        } catch (\Throwable $e) {
            Log::warning('Health check failed for mongodb', ['error' => class_basename($e).': '.$e->getCode()]);

            return ['status' => 'down'];
        }
    }

    /** @return array<string, string> */
    private function checkRabbitMQ(): array
    {
        try {
            /** @var array<string, string>|null $cached */
            $cached = Cache::get('health:rabbitmq');

            if ($cached !== null) {
                return $cached;
            }

            $result = $this->probeRabbitMQ();
            $ttl = $result['status'] === 'up' ? 10 : 30;
            Cache::put('health:rabbitmq', $result, $ttl);

            return $result;
        } catch (\Throwable $e) {
            Log::debug('Health check: RabbitMQ cache read failed, falling back to probe', [
                'error' => class_basename($e).': '.$e->getMessage(),
            ]);

            return $this->probeRabbitMQ();
        }
    }

    /** @return array<string, string> */
    private function checkStorage(): array
    {
        $diskName = app()->isProduction() ? 'backups' : 'local';

        try {
            /** @var array<string, string> $result */
            $result = Cache::remember('health:storage', 60, function () use ($diskName): array {
                $disk = Storage::disk($diskName);
                $testKey = '.health-check';

                $disk->put($testKey, 'ok');
                $disk->delete($testKey);

                return ['status' => 'up', 'disk' => $diskName];
            });

            return $result;
        } catch (\Throwable $e) {
            Cache::forget('health:storage');
            Log::warning('Health check storage failed', ['error' => $e->getMessage()]);

            return ['status' => 'down', 'disk' => $diskName];
        }
    }

    /** @return array<string, string> */
    private function probeRabbitMQ(): array
    {
        $connection = null;

        try {
            $connection = new \PhpAmqpLib\Connection\AMQPStreamConnection(
                host: (string) config('queue.connections.rabbitmq.hosts.0.host', 'rabbitmq'),
                port: (int) config('queue.connections.rabbitmq.hosts.0.port', 5672),
                user: (string) config('queue.connections.rabbitmq.hosts.0.user', 'maisvendas'),
                password: (string) config('queue.connections.rabbitmq.hosts.0.password', ''),
                vhost: (string) config('queue.connections.rabbitmq.hosts.0.vhost', 'maisvendas'),
                connection_timeout: 3,
                read_write_timeout: 3,
            );
            $channel = $connection->channel();
            $channel->close();

            return ['status' => 'up'];
        } catch (\Throwable $e) {
            Log::warning('Health check failed for rabbitmq', ['error' => class_basename($e).': '.$e->getCode()]);

            return ['status' => 'down'];
        } finally {
            try {
                $connection?->close();
            } catch (\Throwable) {
            }
        }
    }
}
