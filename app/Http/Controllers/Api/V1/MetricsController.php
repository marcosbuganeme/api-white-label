<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class MetricsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = [];
        $lines[] = '# HELP app_info Application metadata';
        $lines[] = '# TYPE app_info gauge';
        $lines[] = sprintf(
            'app_info{version="%s",php="%s",laravel="%s"} 1',
            config('app.version', '1.0.0'),
            PHP_VERSION,
            app()->version(),
        );

        $this->addDatabaseMetrics($lines);
        $this->addRedisMetrics($lines);
        $this->addHorizonMetrics($lines);

        return response(implode("\n", $lines)."\n", 200)
            ->header('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
    }

    /** @param list<string> $lines */
    private function addDatabaseMetrics(array &$lines): void
    {
        try {
            /** @var object{connections: int}|null $result */
            $result = DB::connection('pgsql')->selectOne(
                'SELECT numbackends as connections FROM pg_stat_database WHERE datname = current_database()'
            );

            $lines[] = '# HELP db_connections_active Active PostgreSQL connections';
            $lines[] = '# TYPE db_connections_active gauge';
            $connections = $result !== null ? $result->connections : 0;
            $lines[] = sprintf('db_connections_active %d', $connections);
        } catch (\Throwable) {
            // Database unavailable - skip metrics
        }
    }

    /** @param list<string> $lines */
    private function addRedisMetrics(array &$lines): void
    {
        try {
            /** @var \Illuminate\Redis\Connections\PhpRedisConnection $redis */
            $redis = Redis::connection();

            /** @var array<string, string> $info */
            $info = $redis->info();

            $lines[] = '# HELP redis_connected_clients Number of connected Redis clients';
            $lines[] = '# TYPE redis_connected_clients gauge';
            $lines[] = sprintf('redis_connected_clients %d', $info['connected_clients'] ?? 0);

            $lines[] = '# HELP redis_used_memory_bytes Redis memory usage in bytes';
            $lines[] = '# TYPE redis_used_memory_bytes gauge';
            $lines[] = sprintf('redis_used_memory_bytes %d', $info['used_memory'] ?? 0);
        } catch (\Throwable) {
            // Redis unavailable - skip metrics
        }
    }

    /** @param list<string> $lines */
    private function addHorizonMetrics(array &$lines): void
    {
        try {
            $prefix = config('horizon.prefix', 'laravel_horizon:');

            /** @var \Illuminate\Redis\Connections\PhpRedisConnection $conn */
            $conn = Redis::connection();

            $lines[] = '# HELP horizon_jobs_pending Pending jobs per queue';
            $lines[] = '# TYPE horizon_jobs_pending gauge';

            foreach (['default', 'logs', 'metrics'] as $queue) {
                /** @var int $size */
                $size = $conn->llen($prefix.'queues:'.$queue);
                $lines[] = sprintf('horizon_jobs_pending{queue="%s"} %d', $queue, $size);
            }

            $lines[] = '# HELP horizon_failed_jobs_total Total failed jobs in Horizon';
            $lines[] = '# TYPE horizon_failed_jobs_total gauge';

            /** @var int $failed */
            $failed = $conn->zcard($prefix.'failed_jobs');
            $lines[] = sprintf('horizon_failed_jobs_total %d', $failed);
        } catch (\Throwable) {
            // Horizon/Redis unavailable - skip metrics
        }
    }
}
