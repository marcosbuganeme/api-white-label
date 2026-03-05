<?php

namespace App\Logging;

use Monolog\Level;
use Monolog\Logger;

class MongoDBLogger
{
    /** @param array<string, mixed> $config */
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('mongodb');
        $handler = new MongoDBHandler(
            level: Level::fromName($config['level'] ?? 'debug'),
            collection: $config['collection'] ?? 'logs',
        );

        $logger->pushHandler($handler);

        return $logger;
    }
}
