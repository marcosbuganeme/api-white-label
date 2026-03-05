<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /** @var \MongoDB\Laravel\Connection $mongo */
        $mongo = DB::connection('mongodb');

        // Logs collection indexes
        $logs = $mongo->getMongoDB()->selectCollection('logs');
        $logs->createIndex(['level' => 1, 'logged_at' => -1], ['name' => 'level_logged_at']);
        $logs->createIndex(['channel' => 1, 'logged_at' => -1], ['name' => 'channel_logged_at']);
        $logs->createIndex(['logged_at' => 1], ['name' => 'logged_at_ttl', 'expireAfterSeconds' => 30 * 86400]); // 30 days

        // Metrics collection indexes
        $metrics = $mongo->getMongoDB()->selectCollection('metrics');
        $metrics->createIndex(['name' => 1, 'recorded_at' => -1], ['name' => 'name_recorded_at']);
        $metrics->createIndex(['recorded_at' => 1], ['name' => 'recorded_at_ttl', 'expireAfterSeconds' => 90 * 86400]); // 90 days

        // Processed data collection indexes
        $processed = $mongo->getMongoDB()->selectCollection('processed_data');
        $processed->createIndex(['type' => 1, 'processed_at' => -1], ['name' => 'type_processed_at']);
        $processed->createIndex(['source_id' => 1], ['name' => 'source_id']);
    }

    public function down(): void
    {
        /** @var \MongoDB\Laravel\Connection $mongo */
        $mongo = DB::connection('mongodb');

        $mongo->getMongoDB()->selectCollection('logs')->dropIndexes();
        $mongo->getMongoDB()->selectCollection('metrics')->dropIndexes();
        $mongo->getMongoDB()->selectCollection('processed_data')->dropIndexes();
    }
};
