-- Create databases for each microservice
CREATE DATABASE IF NOT EXISTS auth_service CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS user_service CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS bidding_service CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS order_service CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS payment_service CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS analytics_service CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS vin_ocr_service CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS notification_service CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Grant privileges to root user for all service databases
GRANT ALL PRIVILEGES ON auth_service.* TO 'root'@'%';
GRANT ALL PRIVILEGES ON user_service.* TO 'root'@'%';
GRANT ALL PRIVILEGES ON bidding_service.* TO 'root'@'%';
GRANT ALL PRIVILEGES ON order_service.* TO 'root'@'%';
GRANT ALL PRIVILEGES ON payment_service.* TO 'root'@'%';
GRANT ALL PRIVILEGES ON analytics_service.* TO 'root'@'%';
GRANT ALL PRIVILEGES ON vin_ocr_service.* TO 'root'@'%';
GRANT ALL PRIVILEGES ON notification_service.* TO 'root'@'%';

-- Create service-specific users for better security (optional)
CREATE USER IF NOT EXISTS 'auth_user'@'%' IDENTIFIED BY 'auth_password';
CREATE USER IF NOT EXISTS 'user_user'@'%' IDENTIFIED BY 'user_password';
CREATE USER IF NOT EXISTS 'bidding_user'@'%' IDENTIFIED BY 'bidding_password';
CREATE USER IF NOT EXISTS 'order_user'@'%' IDENTIFIED BY 'order_password';
CREATE USER IF NOT EXISTS 'payment_user'@'%' IDENTIFIED BY 'payment_password';
CREATE USER IF NOT EXISTS 'analytics_user'@'%' IDENTIFIED BY 'analytics_password';
CREATE USER IF NOT EXISTS 'vin_ocr_user'@'%' IDENTIFIED BY 'vin_ocr_password';
CREATE USER IF NOT EXISTS 'notification_user'@'%' IDENTIFIED BY 'notification_password';

-- Grant specific privileges to service users
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER ON auth_service.* TO 'auth_user'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER ON user_service.* TO 'user_user'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER ON bidding_service.* TO 'bidding_user'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER ON order_service.* TO 'order_user'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER ON payment_service.* TO 'payment_user'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER ON analytics_service.* TO 'analytics_user'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER ON vin_ocr_service.* TO 'vin_ocr_user'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER ON notification_service.* TO 'notification_user'@'%';

-- Flush privileges to apply changes
FLUSH PRIVILEGES;

-- Create initial tables for Laravel framework requirements
USE auth_service;
CREATE TABLE IF NOT EXISTS migrations (
    id int unsigned NOT NULL AUTO_INCREMENT,
    migration varchar(255) NOT NULL,
    batch int NOT NULL,
    PRIMARY KEY (id)
);

USE user_service;
CREATE TABLE IF NOT EXISTS migrations (
    id int unsigned NOT NULL AUTO_INCREMENT,
    migration varchar(255) NOT NULL,
    batch int NOT NULL,
    PRIMARY KEY (id)
);

USE bidding_service;
CREATE TABLE IF NOT EXISTS migrations (
    id int unsigned NOT NULL AUTO_INCREMENT,
    migration varchar(255) NOT NULL,
    batch int NOT NULL,
    PRIMARY KEY (id)
);

USE order_service;
CREATE TABLE IF NOT EXISTS migrations (
    id int unsigned NOT NULL AUTO_INCREMENT,
    migration varchar(255) NOT NULL,
    batch int NOT NULL,
    PRIMARY KEY (id)
);

USE payment_service;
CREATE TABLE IF NOT EXISTS migrations (
    id int unsigned NOT NULL AUTO_INCREMENT,
    migration varchar(255) NOT NULL,
    batch int NOT NULL,
    PRIMARY KEY (id)
);

USE analytics_service;
CREATE TABLE IF NOT EXISTS migrations (
    id int unsigned NOT NULL AUTO_INCREMENT,
    migration varchar(255) NOT NULL,
    batch int NOT NULL,
    PRIMARY KEY (id)
);

USE vin_ocr_service;
CREATE TABLE IF NOT EXISTS migrations (
    id int unsigned NOT NULL AUTO_INCREMENT,
    migration varchar(255) NOT NULL,
    batch int NOT NULL,
    PRIMARY KEY (id)
);

USE notification_service;
CREATE TABLE IF NOT EXISTS migrations (
    id int unsigned NOT NULL AUTO_INCREMENT,
    migration varchar(255) NOT NULL,
    batch int NOT NULL,
    PRIMARY KEY (id)
);
