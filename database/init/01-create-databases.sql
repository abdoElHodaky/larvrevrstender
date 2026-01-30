-- Create databases for each microservice
-- Based on the microservices architecture diagram

-- Main database for shared data
CREATE DATABASE IF NOT EXISTS reverse_tender;

-- Service-specific databases for data isolation
CREATE DATABASE IF NOT EXISTS auth_service;
CREATE DATABASE IF NOT EXISTS bidding_service;
CREATE DATABASE IF NOT EXISTS user_service;
CREATE DATABASE IF NOT EXISTS order_service;
CREATE DATABASE IF NOT EXISTS payment_service;
CREATE DATABASE IF NOT EXISTS analytics_service;

-- Create users for each service
CREATE USER IF NOT EXISTS 'auth_user'@'%' IDENTIFIED BY 'auth_password';
CREATE USER IF NOT EXISTS 'bidding_user'@'%' IDENTIFIED BY 'bidding_password';
CREATE USER IF NOT EXISTS 'user_user'@'%' IDENTIFIED BY 'user_password';
CREATE USER IF NOT EXISTS 'order_user'@'%' IDENTIFIED BY 'order_password';
CREATE USER IF NOT EXISTS 'payment_user'@'%' IDENTIFIED BY 'payment_password';
CREATE USER IF NOT EXISTS 'analytics_user'@'%' IDENTIFIED BY 'analytics_password';

-- Grant permissions
GRANT ALL PRIVILEGES ON reverse_tender.* TO 'laravel'@'%';
GRANT ALL PRIVILEGES ON auth_service.* TO 'auth_user'@'%';
GRANT ALL PRIVILEGES ON bidding_service.* TO 'bidding_user'@'%';
GRANT ALL PRIVILEGES ON user_service.* TO 'user_user'@'%';
GRANT ALL PRIVILEGES ON order_service.* TO 'order_user'@'%';
GRANT ALL PRIVILEGES ON payment_service.* TO 'payment_user'@'%';
GRANT ALL PRIVILEGES ON analytics_service.* TO 'analytics_user'@'%';

-- Allow cross-service read access for analytics
GRANT SELECT ON auth_service.* TO 'analytics_user'@'%';
GRANT SELECT ON bidding_service.* TO 'analytics_user'@'%';
GRANT SELECT ON user_service.* TO 'analytics_user'@'%';
GRANT SELECT ON order_service.* TO 'analytics_user'@'%';
GRANT SELECT ON payment_service.* TO 'analytics_user'@'%';

FLUSH PRIVILEGES;
