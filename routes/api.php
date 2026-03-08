<?php

use App\Http\Controllers\Api\V1\HealthCheckController;
use App\Http\Controllers\Api\V1\MetricsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Rotas versionadas da API. O prefixo /v1 é aplicado automaticamente
| pelo bootstrap/app.php (apiPrefix: 'v1').
|
*/

// Health check (throttle dedicado para probes)
Route::get('/health', HealthCheckController::class)
    ->name('health')
    ->withoutMiddleware('throttle:api')
    ->middleware('throttle:health');

// Liveness probe: app alive? (no dependency checks)
Route::get('/health/live', [HealthCheckController::class, 'live'])
    ->name('health.live')
    ->withoutMiddleware('throttle:api')
    ->middleware('throttle:health');

// Readiness probe: app ready to accept traffic? (checks all dependencies)
Route::get('/health/ready', [HealthCheckController::class, 'ready'])
    ->name('health.ready')
    ->withoutMiddleware('throttle:api')
    ->middleware('throttle:health');

// Prometheus metrics
Route::get('/metrics', MetricsController::class)
    ->name('metrics')
    ->withoutMiddleware('throttle:api')
    ->middleware('throttle:health');

// Futuras rotas da API v1 aqui
