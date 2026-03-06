<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use App\Logging\MongoDBHandler;
use App\Logging\MongoDBLogger;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class MongoDBLoggerTest extends TestCase
{
    public function test_mongodb_logger_creates_logger_instance(): void
    {
        $logger = new MongoDBLogger;
        $config = ['level' => 'debug', 'collection' => 'test_logs'];

        $result = $logger($config);

        $this->assertInstanceOf(Logger::class, $result);
    }

    public function test_mongodb_logger_sets_handler_level(): void
    {
        $logger = new MongoDBLogger;
        $result = $logger(['level' => 'error', 'collection' => 'errors']);

        $this->assertInstanceOf(Logger::class, $result);
        $this->assertCount(1, $result->getHandlers());

        $handler = $result->getHandlers()[0];
        $this->assertInstanceOf(MongoDBHandler::class, $handler);
        $this->assertSame(\Monolog\Level::Error, $handler->getLevel());
    }

    public function test_mongodb_logger_defaults_to_debug_level(): void
    {
        $logger = new MongoDBLogger;
        $result = $logger(['collection' => 'test_logs']);

        $handler = $result->getHandlers()[0];
        $this->assertInstanceOf(MongoDBHandler::class, $handler);
        /** @var MongoDBHandler $handler */
        $this->assertSame(\Monolog\Level::Debug, $handler->getLevel());
    }
}
