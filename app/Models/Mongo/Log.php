<?php

namespace App\Models\Mongo;

use MongoDB\Laravel\Eloquent\Model;

class Log extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'extra' => 'array',
            'logged_at' => 'datetime',
        ];
    }
}
