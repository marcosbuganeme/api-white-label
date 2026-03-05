<?php

namespace App\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class MongoDBHandler extends AbstractProcessingHandler
{
    private const MAX_TRACE_DEPTH = 30;

    private static bool $writing = false;

    public function __construct(
        Level $level = Level::Debug,
        bool $bubble = true,
        private readonly string $collection = 'logs',
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        // Prevent infinite recursion (MongoDB operations may trigger logging).
        // The flag is reset in finally{} to avoid persisting across PHP-FPM requests.
        if (self::$writing) {
            return;
        }

        self::$writing = true;

        try {
            /** @var \MongoDB\Laravel\Connection $connection */
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
        } finally {
            self::$writing = false;
        }
    }

    /**
     * @param  array<mixed>  $context
     * @return array<mixed>
     */
    private function normalizeContext(array $context): array
    {
        array_walk_recursive($context, function (mixed &$value): void {
            if ($value instanceof \Throwable) {
                $trace = $value->getTrace();
                $value = [
                    'class' => $value::class,
                    'message' => $value->getMessage(),
                    'code' => $value->getCode(),
                    'file' => $value->getFile(),
                    'line' => $value->getLine(),
                    'trace' => array_slice($trace, 0, self::MAX_TRACE_DEPTH),
                ];
            } elseif (is_object($value)) {
                if ($value instanceof \MongoDB\BSON\Serializable) {
                    return;
                }
                if ($value instanceof \Stringable) {
                    $value = (string) $value;
                } else {
                    try {
                        $encoded = json_encode($value, JSON_THROW_ON_ERROR);
                        $value = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
                    } catch (\Throwable) {
                        $value = $value::class;
                    }
                }
            }
        });

        return $context;
    }
}
