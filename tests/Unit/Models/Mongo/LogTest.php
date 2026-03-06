<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Mongo;

use App\Models\Mongo\Log;
use PHPUnit\Framework\TestCase;

class LogTest extends TestCase
{
    public function test_model_uses_mongodb_connection(): void
    {
        $model = new Log;

        $this->assertSame('mongodb', $model->getConnectionName());
    }

    public function test_model_uses_logs_collection(): void
    {
        $model = new Log;

        $this->assertSame('logs', $model->getTable());
    }

    public function test_model_has_timestamps_disabled(): void
    {
        $model = new Log;

        $this->assertFalse($model->usesTimestamps());
    }

    public function test_model_has_expected_fillable_fields(): void
    {
        $model = new Log;
        $expected = ['channel', 'level', 'level_code', 'message', 'context', 'extra', 'logged_at', 'environment'];

        $this->assertSame($expected, $model->getFillable());
    }

    public function test_model_casts_context_and_extra_as_array(): void
    {
        $model = new Log;
        $casts = $model->getCasts();

        $this->assertSame('array', $casts['context']);
        $this->assertSame('array', $casts['extra']);
    }

    public function test_model_casts_logged_at_as_datetime(): void
    {
        $model = new Log;
        $casts = $model->getCasts();

        $this->assertSame('datetime', $casts['logged_at']);
    }
}
