// MongoDB Initialization Script
// Creates collections with schema validators only.
// Indexes are managed by Laravel migrations (database/migrations/*_create_mongodb_indexes.php).

db = db.getSiblingDB('maisvendas_logs');

// -- Logs Collection --
db.createCollection('logs', {
  capped: false,
  validator: {
    $jsonSchema: {
      bsonType: 'object',
      required: ['channel', 'level', 'message', 'logged_at'],
      properties: {
        channel: { bsonType: 'string' },
        level: { bsonType: 'string' },
        message: { bsonType: 'string' },
        logged_at: { bsonType: 'date' }
      }
    }
  }
});

// -- Metrics Collection --
db.createCollection('metrics');

// -- Processed Data Collection --
db.createCollection('processed_data');

// Fallback TTL index: auto-remove logs older than 90 days.
// Laravel migrations may override with more specific indexes, but this ensures
// logs don't grow unbounded even if migrations haven't run yet.
db.logs.createIndex(
  { "logged_at": 1 },
  { expireAfterSeconds: 7776000, background: true }
);

print('MongoDB initialized: collections created, fallback TTL index on logs (indexes managed by Laravel migrations)');
