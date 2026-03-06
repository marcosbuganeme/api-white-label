<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Mongo;

use App\Models\Mongo\ProcessedData;
use PHPUnit\Framework\TestCase;

class ProcessedDataTest extends TestCase
{
    public function test_model_uses_mongodb_connection(): void
    {
        $model = new ProcessedData;

        $this->assertSame('mongodb', $model->getConnectionName());
    }

    public function test_model_uses_processed_data_collection(): void
    {
        $model = new ProcessedData;

        $this->assertSame('processed_data', $model->getTable());
    }

    public function test_model_has_timestamps_disabled(): void
    {
        $model = new ProcessedData;

        $this->assertFalse($model->usesTimestamps());
    }

    public function test_model_has_expected_fillable_fields(): void
    {
        $model = new ProcessedData;
        $expected = ['type', 'source_id', 'payload', 'metadata', 'processed_at'];

        $this->assertSame($expected, $model->getFillable());
    }

    public function test_model_casts_payload_and_metadata_as_array(): void
    {
        $model = new ProcessedData;
        $casts = $model->getCasts();

        $this->assertSame('array', $casts['payload']);
        $this->assertSame('array', $casts['metadata']);
    }

    public function test_model_casts_processed_at_as_datetime(): void
    {
        $model = new ProcessedData;
        $casts = $model->getCasts();

        $this->assertSame('datetime', $casts['processed_at']);
    }
}
