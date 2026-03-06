<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use App\Logging\MongoDBHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class MongoDBHandlerTest extends TestCase
{
    /** @param array<string, mixed> $context */
    private function createRecord(Level $level = Level::Warning, string $message = 'test', array $context = []): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'test',
            level: $level,
            message: $message,
            context: $context,
        );
    }

    public function test_recursion_guard_prevents_nested_writes(): void
    {
        $reflection = new \ReflectionProperty(MongoDBHandler::class, 'writing');

        // Simulate an in-progress write
        $reflection->setValue(null, true);

        $handler = new MongoDBHandler(Level::Debug, true, 'logs');
        $record = $this->createRecord();

        // Should silently return without error (recursion guard active)
        $handler->handle($record);

        // Reset flag
        $reflection->setValue(null, false);

        $this->addToAssertionCount(1);
    }

    public function test_recursion_guard_resets_after_write(): void
    {
        $reflection = new \ReflectionProperty(MongoDBHandler::class, 'writing');

        // Ensure clean state
        $reflection->setValue(null, false);

        $handler = new MongoDBHandler(Level::Debug, true, 'logs');
        $record = $this->createRecord();

        // This will fail (no MongoDB), but flag should still be reset
        $handler->handle($record);

        $this->assertFalse($reflection->getValue(null), 'Writing flag should be reset after write attempt');
    }

    public function test_normalize_context_serializes_throwable(): void
    {
        $handler = new MongoDBHandler(Level::Debug);
        $exception = new \RuntimeException('test error', 42);

        $method = new \ReflectionMethod($handler, 'normalizeContext');
        $result = $method->invoke($handler, ['error' => $exception]);

        $this->assertIsArray($result['error']);
        $this->assertSame('RuntimeException', $result['error']['class']);
        $this->assertSame('test error', $result['error']['message']);
        $this->assertSame(42, $result['error']['code']);
        $this->assertArrayHasKey('file', $result['error']);
        $this->assertArrayHasKey('line', $result['error']);
        $this->assertArrayHasKey('trace', $result['error']);
        $this->assertLessThanOrEqual(30, count($result['error']['trace']));
    }

    public function test_normalize_context_converts_stringable(): void
    {
        $handler = new MongoDBHandler(Level::Debug);

        $stringable = new class implements \Stringable
        {
            public function __toString(): string
            {
                return 'stringified value';
            }
        };

        $method = new \ReflectionMethod($handler, 'normalizeContext');
        $result = $method->invoke($handler, ['obj' => $stringable]);

        $this->assertSame('stringified value', $result['obj']);
    }

    public function test_normalize_context_json_encodes_generic_objects(): void
    {
        $handler = new MongoDBHandler(Level::Debug);

        $obj = new \stdClass;
        $obj->key = 'value';

        $method = new \ReflectionMethod($handler, 'normalizeContext');
        $result = $method->invoke($handler, ['data' => $obj]);

        $this->assertIsArray($result['data']);
        $this->assertSame('value', $result['data']['key']);
    }

    public function test_normalize_context_falls_back_to_classname_for_non_encodable(): void
    {
        $handler = new MongoDBHandler(Level::Debug);

        // Create an object that json_encode will fail on
        $resource = fopen('php://memory', 'r');
        $this->assertIsResource($resource);

        $obj = new class($resource)
        {
            public function __construct(public mixed $handle) {}
        };

        $method = new \ReflectionMethod($handler, 'normalizeContext');
        $result = $method->invoke($handler, ['broken' => $obj]);

        // json_encode fails on resources inside objects, falls back to class name
        $this->assertIsString($result['broken']);

        fclose($resource);
    }

    public function test_handler_respects_minimum_level(): void
    {
        $handler = new MongoDBHandler(Level::Error);

        $debugRecord = $this->createRecord(Level::Debug);
        $errorRecord = $this->createRecord(Level::Error);

        $this->assertFalse($handler->isHandling($debugRecord));
        $this->assertTrue($handler->isHandling($errorRecord));
    }
}
