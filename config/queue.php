<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection
    |--------------------------------------------------------------------------
    |
    | Redis: filas leves (notifications, emails, cache warming)
    | RabbitMQ: processamento pesado (data processing, crossref, reports)
    |
    | Jobs que precisam de throughput alto devem usar:
    |   dispatch()->onConnection('rabbitmq')->onQueue('processing')
    |
    */

    'default' => env('QUEUE_CONNECTION', 'redis'),

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        // ── Redis: filas leves, gerenciadas pelo Horizon ──
        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 180),
            'block_for' => null,
            'after_commit' => true,
        ],

        // ── RabbitMQ: processamento pesado com message broker real ──
        'rabbitmq' => [
            'driver' => 'rabbitmq',
            'queue' => env('RABBITMQ_QUEUE', 'processing'),

            'hosts' => [
                [
                    'host' => env('RABBITMQ_HOST', 'rabbitmq'),
                    'port' => (int) env('RABBITMQ_PORT', 5672),
                    'user' => env('RABBITMQ_USER', 'maisvendas'),
                    'password' => env('RABBITMQ_PASSWORD', 'secret'),
                    'vhost' => env('RABBITMQ_VHOST', 'maisvendas'),
                ],
            ],

            'options' => [
                'ssl_options' => [
                    'cafile' => env('RABBITMQ_SSL_CAFILE'),
                    'local_cert' => env('RABBITMQ_SSL_LOCALCERT'),
                    'local_key' => env('RABBITMQ_SSL_LOCALKEY'),
                    'verify_peer' => env('RABBITMQ_SSL_VERIFY_PEER', true),
                    'passphrase' => env('RABBITMQ_SSL_PASSPHRASE'),
                ],
                'queue' => [
                    'job' => VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob::class,
                    'exchange' => env('RABBITMQ_EXCHANGE', 'maisvendas.processing'),
                    'exchange_type' => env('RABBITMQ_EXCHANGE_TYPE', 'topic'),
                    'prioritize_delayed' => false,
                    'queue_max_priority' => 10,
                ],
            ],

            'after_commit' => true,
        ],

        // ── Database: fallback se Redis/RabbitMQ indisponíveis ──
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION', 'pgsql'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => true,
        ],

        'deferred' => [
            'driver' => 'deferred',
        ],

        // ── Failover: fallback automático se Redis cair ──
        'failover' => [
            'driver' => 'failover',
            'connections' => [
                'redis',
                'database',
                'sync',
            ],
        ],

    ],

    'batching' => [
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table' => 'job_batches',
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table' => 'failed_jobs',
    ],

];
