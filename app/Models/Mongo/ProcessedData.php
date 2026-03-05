<?php

namespace App\Models\Mongo;

use MongoDB\Laravel\Eloquent\Model;

class ProcessedData extends Model
{
    public $timestamps = false;

    protected $connection = 'mongodb';
    /** @var string */
    protected $collection = 'processed_data';

    protected $fillable = [
        'type',
        'source_id',
        'payload',
        'metadata',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'metadata' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
