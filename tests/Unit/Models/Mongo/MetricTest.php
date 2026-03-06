<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Mongo;

use App\Models\Mongo\Metric;
use PHPUnit\Framework\TestCase;

class MetricTest extends TestCase
{
    public function test_model_uses_mongodb_connection(): void
    {
        $model = new Metric;

        $this->assertSame('mongodb', $model->getConnectionName());
    }

    public function test_model_uses_metrics_collection(): void
    {
        $model = new Metric;

        $this->assertSame('metrics', $model->getTable());
    }

    public function test_model_has_timestamps_disabled(): void
    {
        $model = new Metric;

        $this->assertFalse($model->usesTimestamps());
    }

    public function test_model_has_expected_fillable_fields(): void
    {
        $model = new Metric;
        $expected = ['name', 'value', 'tags', 'metadata', 'recorded_at'];

        $this->assertSame($expected, $model->getFillable());
    }

    public function test_model_casts_value_as_float(): void
    {
        $model = new Metric;
        $casts = $model->getCasts();

        $this->assertSame('float', $casts['value']);
    }

    public function test_model_casts_tags_and_metadata_as_array(): void
    {
        $model = new Metric;
        $casts = $model->getCasts();

        $this->assertSame('array', $casts['tags']);
        $this->assertSame('array', $casts['metadata']);
    }

    public function test_model_casts_recorded_at_as_datetime(): void
    {
        $model = new Metric;
        $casts = $model->getCasts();

        $this->assertSame('datetime', $casts['recorded_at']);
    }
}
