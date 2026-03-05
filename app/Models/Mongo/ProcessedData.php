<?php

namespace App\Models\Mongo;

use MongoDB\Laravel\Eloquent\Model;

class ProcessedData extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'processed_data';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'metadata' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
