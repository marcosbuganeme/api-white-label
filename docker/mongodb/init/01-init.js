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

print('MongoDB initialized: collections created (indexes managed by Laravel migrations)');
