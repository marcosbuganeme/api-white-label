-- PostgreSQL Initialization Script
-- Creates the test database and essential extensions

-- Extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";

-- Create test database (for CI/tests)
SELECT 'CREATE DATABASE maisvendas_test'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'maisvendas_test')\gexec

-- Grant permissions
GRANT ALL PRIVILEGES ON DATABASE maisvendas_test TO CURRENT_USER;
