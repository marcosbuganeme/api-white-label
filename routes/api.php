<?php

use App\Http\Controllers\Api\V1\HealthCheckController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Rotas versionadas da API. O prefixo /api é aplicado automaticamente
| pelo bootstrap/app.php.
|
*/

// Health check (sem prefixo de versão, throttle dedicado para probes)
Route::get('/health', HealthCheckController::class)
    ->name('health')
    ->withoutMiddleware('throttle:api')
    ->middleware('throttle:health');

// V1
Route::prefix('v1')->name('v1.')->group(function () {
    // Futuras rotas da API v1 aqui
});
