<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use App\Logging\MongoDBHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\TestCase;

/**
 * Tests for MongoDBHandler::write() that require the Laravel container.
 */
class MongoDBHandlerWriteTest extends TestCase
{
    private function createRecord(Level $level = Level::Warning, string $message = 'test'): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'test',
            level: $level,
            message: $message,
            context: [],
        );
    }

    public function test_write_attempts_mongodb_insert_and_catches_failure(): void
    {
        $reflection = new \ReflectionProperty(MongoDBHandler::class, 'writing');
        $reflection->setValue(null, false);

        $handler = new MongoDBHandler(Level::Debug, true, 'logs');
        $record = $this->createRecord();

        // write() will try app('db')->connection('mongodb') which may fail,
        // but the catch block silently handles it
        $handler->handle($record);

        // The writing flag must be reset in the finally block
        $this->assertFalse($reflection->getValue(null));
    }

    public function test_write_sets_and_resets_writing_flag(): void
    {
        $reflection = new \ReflectionProperty(MongoDBHandler::class, 'writing');
        $reflection->setValue(null, false);

        $handler = new MongoDBHandler(Level::Debug, true, 'test_collection');
        $record = $this->createRecord(Level::Error, 'test write flag lifecycle');

        $handler->handle($record);

        $this->assertFalse(
            $reflection->getValue(null),
            'Writing flag should be false after handle() completes'
        );
    }

    public function test_write_with_context_normalizes_before_insert(): void
    {
        $reflection = new \ReflectionProperty(MongoDBHandler::class, 'writing');
        $reflection->setValue(null, false);

        $handler = new MongoDBHandler(Level::Debug, true, 'logs');

        $record = new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'test',
            level: Level::Error,
            message: 'error with context',
            context: [
                'user_id' => 123,
                'error' => new \RuntimeException('test'),
                'nested' => ['key' => 'value'],
            ],
        );

        // Should not throw even though MongoDB is likely unavailable
        $handler->handle($record);

        $this->assertFalse($reflection->getValue(null));
    }

    public function test_normalize_context_preserves_bson_serializable(): void
    {
        $handler = new MongoDBHandler(Level::Debug);
        $method = new \ReflectionMethod($handler, 'normalizeContext');

        $bsonObj = new class implements \MongoDB\BSON\Serializable
        {
            /** @return array<string, string> */
            public function bsonSerialize(): array
            {
                return ['type' => 'test'];
            }
        };

        $result = $method->invoke($handler, ['data' => $bsonObj]);

        // BSON Serializable objects are returned as-is (not converted)
        $this->assertInstanceOf(\MongoDB\BSON\Serializable::class, $result['data']);
    }

    public function test_constructor_stores_collection_name(): void
    {
        $handler = new MongoDBHandler(Level::Info, false, 'custom_collection');

        $reflection = new \ReflectionProperty($handler, 'collection');
        $this->assertSame('custom_collection', $reflection->getValue($handler));
    }

    public function test_constructor_defaults(): void
    {
        $handler = new MongoDBHandler;

        $this->assertSame(Level::Debug, $handler->getLevel());

        $collectionProp = new \ReflectionProperty($handler, 'collection');
        $this->assertSame('logs', $collectionProp->getValue($handler));

        $bubbleProp = new \ReflectionProperty($handler, 'bubble');
        $this->assertTrue($bubbleProp->getValue($handler));
    }
}
