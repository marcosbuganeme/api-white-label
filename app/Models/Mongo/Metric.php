<?php

namespace App\Models\Mongo;

use MongoDB\Laravel\Eloquent\Model;

class Metric extends Model
{
    public $timestamps = false;

    protected $connection = 'mongodb';
    /** @var string */
    protected $collection = 'metrics';

    protected $fillable = [
        'name',
        'value',
        'tags',
        'metadata',
        'recorded_at',
    ];

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
