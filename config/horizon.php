<?php

use Illuminate\Support\Str;

return [

    'name' => env('HORIZON_NAME', 'API MaisVendas'),

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => 'horizon',

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    'middleware' => ['api', 'auth'],

    'allowed_emails' => env('HORIZON_ALLOWED_EMAILS', ''),

    'waits' => [
        'redis:default' => 60,
        'redis:logs' => 120,
        'redis:metrics' => 120,
    ],

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [],
    'silenced_tags' => [],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => true,
    'memory_limit' => (int) env('HORIZON_MEMORY_LIMIT', 128),

    'defaults' => [
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-default' => [
                'maxProcesses' => 5,
                'balanceMaxShift' => 2,
                'balanceCooldown' => 3,
                'queue' => ['default'],
            ],
            'supervisor-logs' => [
                'connection' => 'redis',
                'queue' => ['logs'],
                'balance' => 'auto',
                'minProcesses' => 1,
                'maxProcesses' => 2,
                'tries' => 3,
                'timeout' => 30,
                'memory' => 64,
            ],
            'supervisor-metrics' => [
                'connection' => 'redis',
                'queue' => ['metrics'],
                'balance' => 'auto',
                'minProcesses' => 1,
                'maxProcesses' => 2,
                'tries' => 3,
                'timeout' => 30,
                'memory' => 64,
            ],
        ],

        'local' => [
            'supervisor-default' => [
                'maxProcesses' => 3,
                'queue' => ['default', 'logs', 'metrics'],
            ],
        ],
    ],

    'watch' => [
        'app',
        'bootstrap',
        'config',
        'database/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
    ],
];
