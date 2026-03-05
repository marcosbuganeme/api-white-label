<?php

namespace App\Models\Mongo;

use MongoDB\Laravel\Eloquent\Model;

class Metric extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'metrics';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'metadata' => 'array',
            'value' => 'float',
            'recorded_at' => 'datetime',
        ];
    }
}
