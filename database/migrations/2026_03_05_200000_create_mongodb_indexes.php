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
        $logs = $mongo->getDatabase()->selectCollection('logs');
        $this->dropConflictingIndexes($logs, ['logged_at_1', 'logged_at_ttl']);
        $logs->createIndex(['level' => 1, 'logged_at' => -1], ['name' => 'level_logged_at']);
        $logs->createIndex(['channel' => 1, 'logged_at' => -1], ['name' => 'channel_logged_at']);
        $logs->createIndex(['logged_at' => 1], ['name' => 'logged_at_ttl', 'expireAfterSeconds' => 30 * 86400]); // 30 days

        // Metrics collection indexes
        $metrics = $mongo->getDatabase()->selectCollection('metrics');
        $this->dropConflictingIndexes($metrics, ['recorded_at_1', 'recorded_at_ttl']);
        $metrics->createIndex(['name' => 1, 'recorded_at' => -1], ['name' => 'name_recorded_at']);
        $metrics->createIndex(['recorded_at' => 1], ['name' => 'recorded_at_ttl', 'expireAfterSeconds' => 90 * 86400]); // 90 days

        // Processed data collection indexes
        $processed = $mongo->getDatabase()->selectCollection('processed_data');
        $this->dropConflictingIndexes($processed, ['processed_at_1', 'processed_at_ttl']);
        $processed->createIndex(['type' => 1, 'processed_at' => -1], ['name' => 'type_processed_at']);
        $processed->createIndex(['source_id' => 1], ['name' => 'source_id']);
        $processed->createIndex(['processed_at' => 1], ['name' => 'processed_at_ttl', 'expireAfterSeconds' => 180 * 86400]); // 180 days
    }

    private function dropConflictingIndexes(\MongoDB\Collection $collection, array $indexNames): void
    {
        foreach ($indexNames as $indexName) {
            try {
                $collection->dropIndex($indexName);
            } catch (\Throwable) {
                // Index may not exist
            }
        }
    }

    public function down(): void
    {
        /** @var \MongoDB\Laravel\Connection $mongo */
        $mongo = DB::connection('mongodb');

        $indexes = [
            'logs' => ['level_logged_at', 'channel_logged_at', 'logged_at_ttl'],
            'metrics' => ['name_recorded_at', 'recorded_at_ttl'],
            'processed_data' => ['type_processed_at', 'source_id', 'processed_at_ttl'],
        ];

        foreach ($indexes as $collectionName => $indexNames) {
            $collection = $mongo->getDatabase()->selectCollection($collectionName);

            foreach ($indexNames as $indexName) {
                try {
                    $collection->dropIndex($indexName);
                } catch (\Throwable) {
                    // Index may not exist (first run or partial migration)
                }
            }
        }
    }
};
