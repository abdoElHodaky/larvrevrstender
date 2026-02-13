-- ========================================
-- NEON POSTGRESQL DATABASE SETUP
-- ========================================
-- Create databases for each microservice
-- Based on the microservices architecture diagram
-- Optimized for Neon PostgreSQL compatibility
-- ========================================

-- Main database for shared data
-- Note: In Neon PostgreSQL, database creation is typically handled via dashboard
-- These commands are for local development and testing
CREATE DATABASE reverse_tender;

-- Service-specific databases for data isolation
CREATE DATABASE auth_service;
CREATE DATABASE bidding_service;
CREATE DATABASE user_service;
CREATE DATABASE order_service;
CREATE DATABASE payment_service;
CREATE DATABASE analytics_service;

-- ========================================
-- POSTGRESQL USER MANAGEMENT
-- ========================================
-- Create roles for each service (PostgreSQL uses roles instead of users)
-- Note: In Neon PostgreSQL, user management is handled via dashboard
-- These are for local development environments

DO $$
BEGIN
    -- Create service users with login capability
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'auth_user') THEN
        CREATE ROLE auth_user WITH LOGIN PASSWORD 'auth_password';
    END IF;
    
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'bidding_user') THEN
        CREATE ROLE bidding_user WITH LOGIN PASSWORD 'bidding_password';
    END IF;
    
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'user_user') THEN
        CREATE ROLE user_user WITH LOGIN PASSWORD 'user_password';
    END IF;
    
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'order_user') THEN
        CREATE ROLE order_user WITH LOGIN PASSWORD 'order_password';
    END IF;
    
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'payment_user') THEN
        CREATE ROLE payment_user WITH LOGIN PASSWORD 'payment_password';
    END IF;
    
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'analytics_user') THEN
        CREATE ROLE analytics_user WITH LOGIN PASSWORD 'analytics_password';
    END IF;
    
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'laravel') THEN
        CREATE ROLE laravel WITH LOGIN PASSWORD 'laravel_password';
    END IF;
END
$$;

-- ========================================
-- POSTGRESQL PERMISSIONS
-- ========================================
-- Grant database-level permissions
GRANT ALL PRIVILEGES ON DATABASE reverse_tender TO laravel;
GRANT ALL PRIVILEGES ON DATABASE auth_service TO auth_user;
GRANT ALL PRIVILEGES ON DATABASE bidding_service TO bidding_user;
GRANT ALL PRIVILEGES ON DATABASE user_service TO user_user;
GRANT ALL PRIVILEGES ON DATABASE order_service TO order_user;
GRANT ALL PRIVILEGES ON DATABASE payment_service TO payment_user;
GRANT ALL PRIVILEGES ON DATABASE analytics_service TO analytics_user;

-- Grant connection privileges
GRANT CONNECT ON DATABASE reverse_tender TO laravel;
GRANT CONNECT ON DATABASE auth_service TO auth_user;
GRANT CONNECT ON DATABASE bidding_service TO bidding_user;
GRANT CONNECT ON DATABASE user_service TO user_user;
GRANT CONNECT ON DATABASE order_service TO order_user;
GRANT CONNECT ON DATABASE payment_service TO payment_user;
GRANT CONNECT ON DATABASE analytics_service TO analytics_user;

-- Allow cross-service read access for analytics
GRANT CONNECT ON DATABASE auth_service TO analytics_user;
GRANT CONNECT ON DATABASE bidding_service TO analytics_user;
GRANT CONNECT ON DATABASE user_service TO analytics_user;
GRANT CONNECT ON DATABASE order_service TO analytics_user;
GRANT CONNECT ON DATABASE payment_service TO analytics_user;

-- Note: Schema-level permissions will be granted after schema creation
-- PostgreSQL requires explicit schema permissions unlike MySQL's database.* syntax
