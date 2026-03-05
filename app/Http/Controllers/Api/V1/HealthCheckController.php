<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
            ],
        ];

        $isHealthy = collect($services['services'])
            ->every(fn (array $service) => $service['status'] === 'up');

        $services['status'] = $isHealthy ? 'healthy' : 'degraded';

        return response()->json($services, $isHealthy ? 200 : 503);
    }

    private function checkApp(): array
    {
        return [
            'status' => 'up',
            'version' => config('app.version', '1.0.0'),
            'environment' => app()->environment(),
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
        ];
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection('pgsql')->getPdo();

            return ['status' => 'up', 'driver' => 'pgsql'];
        } catch (\Throwable $e) {
            return ['status' => 'down', 'driver' => 'pgsql', 'error' => $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        try {
            Cache::store('redis')->put('health_check', true, 5);
            Cache::store('redis')->forget('health_check');

            return ['status' => 'up'];
        } catch (\Throwable $e) {
            return ['status' => 'down', 'error' => $e->getMessage()];
        }
    }

    private function checkMongoDB(): array
    {
        try {
            DB::connection('mongodb')->getMongoClient()->listDatabases();

            return ['status' => 'up'];
        } catch (\Throwable $e) {
            return ['status' => 'down', 'error' => $e->getMessage()];
        }
    }
}
