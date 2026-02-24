-- 🗄️ PostgreSQL Database Initialization Script
-- Creates separate databases for each microservice

-- Create databases for each service
CREATE DATABASE auth_service;
CREATE DATABASE user_service;
CREATE DATABASE auction_service;
CREATE DATABASE bidding_service;
CREATE DATABASE payment_service;
CREATE DATABASE order_service;
CREATE DATABASE notification_service;
CREATE DATABASE analytics_service;
CREATE DATABASE gateway_service;
CREATE DATABASE vin_ocr_service;
CREATE DATABASE shared_service;

-- Create service-specific users with appropriate permissions
CREATE USER auth_service_user WITH PASSWORD 'auth_service_password_123';
CREATE USER user_service_user WITH PASSWORD 'user_service_password_123';
CREATE USER auction_service_user WITH PASSWORD 'auction_service_password_123';
CREATE USER bidding_service_user WITH PASSWORD 'bidding_service_password_123';
CREATE USER payment_service_user WITH PASSWORD 'payment_service_password_123';
CREATE USER order_service_user WITH PASSWORD 'order_service_password_123';
CREATE USER notification_service_user WITH PASSWORD 'notification_service_password_123';
CREATE USER analytics_service_user WITH PASSWORD 'analytics_service_password_123';
CREATE USER gateway_service_user WITH PASSWORD 'gateway_service_password_123';
CREATE USER vin_ocr_service_user WITH PASSWORD 'vin_ocr_service_password_123';
CREATE USER shared_service_user WITH PASSWORD 'shared_service_password_123';

-- Grant permissions to service users
GRANT ALL PRIVILEGES ON DATABASE auth_service TO auth_service_user;
GRANT ALL PRIVILEGES ON DATABASE user_service TO user_service_user;
GRANT ALL PRIVILEGES ON DATABASE auction_service TO auction_service_user;
GRANT ALL PRIVILEGES ON DATABASE bidding_service TO bidding_service_user;
GRANT ALL PRIVILEGES ON DATABASE payment_service TO payment_service_user;
GRANT ALL PRIVILEGES ON DATABASE order_service TO order_service_user;
GRANT ALL PRIVILEGES ON DATABASE notification_service TO notification_service_user;
GRANT ALL PRIVILEGES ON DATABASE analytics_service TO analytics_service_user;
GRANT ALL PRIVILEGES ON DATABASE gateway_service TO gateway_service_user;
GRANT ALL PRIVILEGES ON DATABASE vin_ocr_service TO vin_ocr_service_user;
GRANT ALL PRIVILEGES ON DATABASE shared_service TO shared_service_user;

-- Create read-only user for analytics and reporting
CREATE USER analytics_readonly WITH PASSWORD 'analytics_readonly_password_123';
GRANT CONNECT ON DATABASE auth_service TO analytics_readonly;
GRANT CONNECT ON DATABASE user_service TO analytics_readonly;
GRANT CONNECT ON DATABASE auction_service TO analytics_readonly;
GRANT CONNECT ON DATABASE bidding_service TO analytics_readonly;
GRANT CONNECT ON DATABASE payment_service TO analytics_readonly;
GRANT CONNECT ON DATABASE order_service TO analytics_readonly;
GRANT CONNECT ON DATABASE notification_service TO analytics_readonly;
GRANT CONNECT ON DATABASE analytics_service TO analytics_readonly;

-- Enable necessary PostgreSQL extensions
\c auth_service;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

\c user_service;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

\c auction_service;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

\c bidding_service;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

\c payment_service;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

\c order_service;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

\c notification_service;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

\c analytics_service;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

\c gateway_service;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

\c vin_ocr_service;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

\c shared_service;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";
