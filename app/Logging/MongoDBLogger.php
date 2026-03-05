<?php

namespace App\Logging;

use MongoDB\Laravel\Connection;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;

class MongoDBLogger
{
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

class MongoDBHandler extends AbstractProcessingHandler
{
    public function __construct(
        Level $level = Level::Debug,
        bool $bubble = true,
        private readonly string $collection = 'logs',
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        try {
            /** @var Connection $connection */
            $connection = app('db')->connection('mongodb');

            $connection
                ->getCollection($this->collection)
                ->insertOne([
                    'channel' => $record->channel,
                    'level' => $record->level->name,
                    'level_code' => $record->level->value,
                    'message' => $record->message,
                    'context' => $this->normalizeContext($record->context),
                    'extra' => $record->extra,
                    'logged_at' => new \MongoDB\BSON\UTCDateTime(
                        $record->datetime->getTimestamp() * 1000
                    ),
                    'environment' => app()->environment(),
                ]);
        } catch (\Throwable) {
            // Silently fail - logging should never break the application.
            // If MongoDB is down, daily file log still captures everything via stack channel.
        }
    }

    private function normalizeContext(array $context): array
    {
        array_walk_recursive($context, function (&$value) {
            if ($value instanceof \Throwable) {
                $value = [
                    'class' => get_class($value),
                    'message' => $value->getMessage(),
                    'code' => $value->getCode(),
                    'file' => $value->getFile(),
                    'line' => $value->getLine(),
                    'trace' => $value->getTraceAsString(),
                ];
            } elseif (is_object($value) && ! ($value instanceof \MongoDB\BSON\Serializable)) {
                $value = (string) $value;
            }
        });

        return $context;
    }
}
