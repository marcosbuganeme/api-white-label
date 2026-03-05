<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $services = [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'services' => [
                'app' => $this->checkApp(),
                'database' => $this->checkDatabase(),
                'redis' => $this->checkRedis(),
                'mongodb' => $this->checkMongoDB(),
                'rabbitmq' => $this->checkRabbitMQ(),
            ],
        ];

        $isHealthy = collect($services['services'])
            ->every(fn (array $service) => $service['status'] === 'up');

        $services['status'] = $isHealthy ? 'healthy' : 'degraded';

        return response()->json($services, $isHealthy ? 200 : 503);
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
            Log::warning('Health check failed for pgsql', ['error' => $e->getMessage()]);

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
            Log::warning('Health check failed for redis', ['error' => $e->getMessage()]);

            return ['status' => 'down'];
        }
    }

    /** @return array<string, string> */
    private function checkMongoDB(): array
    {
        try {
            /** @var \MongoDB\Laravel\Connection $connection */
            $connection = DB::connection('mongodb');
            $client = $connection->getClient();

            if ($client === null) {
                return ['status' => 'down'];
            }

            $client->listDatabases();

            return ['status' => 'up'];
        } catch (\Throwable $e) {
            Log::warning('Health check failed for mongodb', ['error' => $e->getMessage()]);

            return ['status' => 'down'];
        }
    }

    /** @return array<string, string> */
    private function checkRabbitMQ(): array
    {
        try {
            /** @var array<string, string> $cached */
            $cached = Cache::remember('health:rabbitmq', 15, fn (): array => $this->probeRabbitMQ());

            return $cached;
        } catch (\Throwable) {
            return $this->probeRabbitMQ();
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
                password: (string) config('queue.connections.rabbitmq.hosts.0.password', 'secret'),
                vhost: (string) config('queue.connections.rabbitmq.hosts.0.vhost', 'maisvendas'),
                connection_timeout: 3,
                read_write_timeout: 3,
            );
            $channel = $connection->channel();
            $channel->close();

            return ['status' => 'up'];
        } catch (\Throwable $e) {
            Log::warning('Health check failed for rabbitmq', ['error' => $e->getMessage()]);

            return ['status' => 'down'];
        } finally {
            try {
                $connection?->close();
            } catch (\Throwable) {
            }
        }
    }
}
