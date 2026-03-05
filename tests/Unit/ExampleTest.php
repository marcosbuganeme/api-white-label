<?php

namespace Tests\Unit;

use App\Logging\MongoDBLogger;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_mongodb_logger_creates_logger_instance(): void
    {
        $logger = new MongoDBLogger;
        $config = [
            'level' => 'debug',
            'collection' => 'test_logs',
        ];

        $result = $logger($config);

        $this->assertInstanceOf(Logger::class, $result);
    }
}
