<?php

namespace App\Models\Mongo;

use MongoDB\Laravel\Eloquent\Model;

class Log extends Model
{
    protected $connection = 'mongodb';
    /** @var string */
    protected $collection = 'logs';

    protected $fillable = [
        'channel',
        'level',
        'level_code',
        'message',
        'context',
        'extra',
        'logged_at',
        'environment',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'extra' => 'array',
            'logged_at' => 'datetime',
        ];
    }
}
