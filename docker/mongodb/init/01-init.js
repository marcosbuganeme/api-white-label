// MongoDB Initialization Script
// Creates collections with proper indexes for logs, metrics, and processed_data

db = db.getSiblingDB('maisvendas_logs');

// ── Logs Collection ──
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

db.logs.createIndex({ 'logged_at': -1 }, { expireAfterSeconds: 2592000 }); // TTL: 30 days
db.logs.createIndex({ 'level': 1, 'logged_at': -1 });
db.logs.createIndex({ 'channel': 1, 'logged_at': -1 });
db.logs.createIndex({ 'environment': 1 });

// ── Metrics Collection ──
db.createCollection('metrics');

db.metrics.createIndex({ 'recorded_at': -1 }, { expireAfterSeconds: 7776000 }); // TTL: 90 days
db.metrics.createIndex({ 'name': 1, 'recorded_at': -1 });
db.metrics.createIndex({ 'tags': 1 });

// ── Processed Data Collection ──
db.createCollection('processed_data');

db.processed_data.createIndex({ 'processed_at': -1 });
db.processed_data.createIndex({ 'type': 1, 'processed_at': -1 });
db.processed_data.createIndex({ 'source_id': 1 }, { sparse: true });

print('✅ MongoDB initialized: collections and indexes created');
