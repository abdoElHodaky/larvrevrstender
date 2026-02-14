-- Laravel Forge Database Setup for Notification System
-- Execute this script on your MySQL database server

-- Create database
CREATE DATABASE IF NOT EXISTS notification_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the database
USE notification_system;

-- Create shared tables
CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    email_verified_at TIMESTAMP NULL DEFAULT NULL,
    password VARCHAR(255) NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_email (email),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create notification service tables
CREATE TABLE IF NOT EXISTS notification_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    channel VARCHAR(50) NOT NULL,
    language VARCHAR(10) DEFAULT 'en',
    subject VARCHAR(255) NULL,
    content TEXT NOT NULL,
    variables JSON NULL,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_name_channel (name, channel),
    INDEX idx_channel (channel),
    INDEX idx_active (active),
    INDEX idx_language (language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    template_id BIGINT UNSIGNED NULL,
    channel VARCHAR(50) NOT NULL,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NULL,
    content TEXT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    sent_at TIMESTAMP NULL DEFAULT NULL,
    delivered_at TIMESTAMP NULL DEFAULT NULL,
    failed_at TIMESTAMP NULL DEFAULT NULL,
    error_message TEXT NULL,
    attempts INT DEFAULT 0,
    metadata JSON NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (template_id) REFERENCES notification_templates(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_template_id (template_id),
    INDEX idx_channel (channel),
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at),
    INDEX idx_recipient (recipient),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    channel VARCHAR(50) NOT NULL,
    category VARCHAR(100) DEFAULT 'general',
    subscribed BOOLEAN DEFAULT TRUE,
    preferences JSON NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_channel_category (user_id, channel, category),
    INDEX idx_user_id (user_id),
    INDEX idx_channel (channel),
    INDEX idx_subscribed (subscribed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create shared service tables
CREATE TABLE IF NOT EXISTS api_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service VARCHAR(50) NOT NULL,
    method VARCHAR(100) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    trace_id VARCHAR(255) NULL,
    user_id BIGINT UNSIGNED NULL,
    request_data JSON NULL,
    response_data JSON NULL,
    status_code INT NULL,
    duration_ms INT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_service (service),
    INDEX idx_method (method),
    INDEX idx_trace_id (trace_id),
    INDEX idx_status_code (status_code),
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_health_checks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL,
    response_time_ms INT NULL,
    error_message TEXT NULL,
    checked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    metadata JSON NULL,
    INDEX idx_service_name (service_name),
    INDEX idx_status (status),
    INDEX idx_checked_at (checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default notification templates
INSERT INTO notification_templates (name, channel, language, subject, content, variables, active, created_at, updated_at) VALUES
('welcome_email', 'email', 'en', 'Welcome to {{app_name}}!', 
 '<h1>Welcome {{name}}!</h1><p>Thank you for joining {{app_name}}. Please verify your email by clicking the link below:</p><p><a href="{{verification_url}}">Verify Email</a></p>', 
 JSON_ARRAY('name', 'app_name', 'verification_url'), TRUE, NOW(), NOW()),

('password_reset', 'email', 'en', 'Reset Your Password', 
 '<h1>Password Reset Request</h1><p>Hello {{name}},</p><p>You requested a password reset. Click the link below to reset your password:</p><p><a href="{{reset_url}}">Reset Password</a></p><p>This link expires in {{expires_in}} minutes.</p>', 
 JSON_ARRAY('name', 'reset_url', 'expires_in'), TRUE, NOW(), NOW()),

('verification_sms', 'sms', 'en', NULL, 
 'Your verification code is {{code}}. This code expires in {{expires_in}} minutes.', 
 JSON_ARRAY('code', 'expires_in'), TRUE, NOW(), NOW()),

('order_confirmation', 'email', 'en', 'Order Confirmation - {{order_id}}', 
 '<h1>Order Confirmed!</h1><p>Hello {{name}},</p><p>Your order {{order_id}} has been confirmed.</p><p><strong>Total: {{amount}}</strong></p><p>Estimated delivery: {{delivery_date}}</p>', 
 JSON_ARRAY('name', 'order_id', 'amount', 'delivery_date'), TRUE, NOW(), NOW()),

('push_notification_general', 'push', 'en', NULL, 
 '{{title}}: {{message}}', 
 JSON_ARRAY('title', 'message'), TRUE, NOW(), NOW());

-- Insert sample user for testing
INSERT INTO users (email, name, email_verified_at, created_at, updated_at) VALUES
('test@example.com', 'Test User', NOW(), NOW(), NOW());

-- Create database user for Laravel applications (adjust credentials as needed)
-- Note: Run these commands separately with appropriate privileges
-- CREATE USER 'forge'@'%' IDENTIFIED BY 'your-secure-password';
-- GRANT ALL PRIVILEGES ON notification_system.* TO 'forge'@'%';
-- FLUSH PRIVILEGES;

-- Show table status
SELECT 
    TABLE_NAME as 'Table',
    TABLE_ROWS as 'Rows',
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) as 'Size (MB)'
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'notification_system'
ORDER BY TABLE_NAME;
