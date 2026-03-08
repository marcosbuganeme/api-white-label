<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        if (env('APP_ENV') !== 'testing') {
            $middleware->throttleWithRedis('api');
        }

        // env() is intentional here: bootstrap/app.php runs before the config
        // service is available. This file is NOT subject to config:cache.
        /** @var string $proxies */
        $proxies = env('TRUSTED_PROXIES', '172.16.0.0/12');
        $middleware->trustProxies(
            at: $proxies === '*' ? '*' : array_filter(explode(',', $proxies)),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return true;
        });
    })->create();
