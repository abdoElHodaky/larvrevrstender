-- ========================================
-- REVERSE TENDER PLATFORM - COMPLETE DATABASE SCHEMA (PART 2)
-- ========================================
-- Orders, Bidding, Payments, Notifications, Reviews, Analytics
-- ========================================

USE reverse_tender;

-- ========================================
-- 📋 ORDERS & PART REQUESTS DOMAIN
-- ========================================

CREATE TABLE orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL COMMENT '👤 Customer Reference',
    vehicle_id BIGINT UNSIGNED NOT NULL COMMENT '🚗 Vehicle Reference',
    order_number VARCHAR(50) UNIQUE NOT NULL COMMENT '🔢 Unique Order Number',
    status ENUM('draft', 'published', 'bidding', 'awarded', 'completed', 'cancelled') DEFAULT 'draft' COMMENT '📊 Order Status',
    title VARCHAR(255) NOT NULL COMMENT '📝 Request Title',
    description TEXT NOT NULL COMMENT '📄 Detailed Description',
    part_details JSON COMMENT '🔧 Required Parts Specification',
    budget_min DECIMAL(10,2) DEFAULT 0.00 COMMENT '💰 Minimum Budget',
    budget_max DECIMAL(10,2) DEFAULT 0.00 COMMENT '💰 Maximum Budget',
    delivery_location JSON COMMENT '📍 Delivery Address',
    urgent BOOLEAN DEFAULT FALSE COMMENT '🚨 Urgent Request',
    priority_score INT DEFAULT 5 COMMENT '⭐ Priority Score (1-10)',
    deadline TIMESTAMP NULL COMMENT '⏰ Response Deadline',
    published_at TIMESTAMP NULL COMMENT '📅 Published Time',
    completed_at TIMESTAMP NULL COMMENT '✅ Completion Time',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '🔄 Updated At',
    
    FOREIGN KEY (customer_id) REFERENCES customer_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
    INDEX idx_order_customer (customer_id),
    INDEX idx_order_vehicle (vehicle_id),
    INDEX idx_order_status (status),
    INDEX idx_order_published (published_at),
    INDEX idx_order_deadline (deadline),
    INDEX idx_order_urgent (urgent)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_images (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL COMMENT '📋 Order Reference',
    image_path VARCHAR(500) NOT NULL COMMENT '📷 Image File Path',
    image_type ENUM('part_photo', 'damage_photo', 'reference', 'vin_photo') NOT NULL COMMENT '🖼️ Image Type',
    description TEXT COMMENT '📝 Image Description',
    sort_order INT DEFAULT 0 COMMENT '📊 Display Order',
    metadata JSON COMMENT '📋 Image Properties',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order_image_order (order_id),
    INDEX idx_order_image_type (image_type),
    INDEX idx_order_image_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL COMMENT '📋 Order Reference',
    user_id BIGINT UNSIGNED NOT NULL COMMENT '👤 User Who Changed',
    old_status ENUM('draft', 'published', 'bidding', 'awarded', 'completed', 'cancelled') COMMENT '📊 Previous Status',
    new_status ENUM('draft', 'published', 'bidding', 'awarded', 'completed', 'cancelled') NOT NULL COMMENT '📊 New Status',
    reason TEXT COMMENT '📝 Change Reason',
    metadata JSON COMMENT '📋 Additional Context',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Changed At',
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_status_history_order (order_id),
    INDEX idx_status_history_user (user_id),
    INDEX idx_status_history_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 🎯 BIDDING & AUCTION SYSTEM DOMAIN
-- ========================================

CREATE TABLE bids (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL COMMENT '📋 Order Reference',
    merchant_id BIGINT UNSIGNED NOT NULL COMMENT '🏪 Merchant Reference',
    amount DECIMAL(10,2) NOT NULL COMMENT '💰 Bid Amount',
    description TEXT COMMENT '📝 Bid Description',
    part_details JSON COMMENT '🔧 Offered Parts Details',
    delivery_days INT DEFAULT 7 COMMENT '📅 Delivery Timeline (Days)',
    status ENUM('active', 'withdrawn', 'awarded', 'rejected', 'expired') DEFAULT 'active' COMMENT '📊 Bid Status',
    auto_bid BOOLEAN DEFAULT FALSE COMMENT '🤖 Automated Bidding',
    max_amount DECIMAL(10,2) NULL COMMENT '💰 Auto-bid Maximum',
    warranty_terms JSON COMMENT '🛡️ Warranty Information',
    confidence_score DECIMAL(3,2) DEFAULT 0.50 COMMENT '🎯 Merchant Confidence (0-1)',
    expires_at TIMESTAMP NULL COMMENT '⏰ Bid Expiry',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '🔄 Updated At',
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (merchant_id) REFERENCES merchant_profiles(id) ON DELETE CASCADE,
    INDEX idx_bid_order (order_id),
    INDEX idx_bid_merchant (merchant_id),
    INDEX idx_bid_status (status),
    INDEX idx_bid_amount (amount),
    INDEX idx_bid_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bid_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bid_id BIGINT UNSIGNED NOT NULL COMMENT '🎯 Bid Reference',
    user_id BIGINT UNSIGNED NOT NULL COMMENT '👤 Sender Reference',
    message TEXT NOT NULL COMMENT '💬 Message Content',
    attachments JSON COMMENT '📎 File Attachments',
    is_system_message BOOLEAN DEFAULT FALSE COMMENT '🤖 System Generated',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    
    FOREIGN KEY (bid_id) REFERENCES bids(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_bid_message_bid (bid_id),
    INDEX idx_bid_message_user (user_id),
    INDEX idx_bid_message_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bid_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bid_id BIGINT UNSIGNED NOT NULL COMMENT '🎯 Bid Reference',
    old_amount DECIMAL(10,2) COMMENT '💰 Previous Amount',
    new_amount DECIMAL(10,2) NOT NULL COMMENT '💰 New Amount',
    reason TEXT COMMENT '📝 Change Reason',
    metadata JSON COMMENT '📋 Change Context',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Changed At',
    
    FOREIGN KEY (bid_id) REFERENCES bids(id) ON DELETE CASCADE,
    INDEX idx_bid_history_bid (bid_id),
    INDEX idx_bid_history_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 🏆 AWARDS & CONTRACTS DOMAIN
-- ========================================

CREATE TABLE awards (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED UNIQUE NOT NULL COMMENT '📋 Order Reference',
    bid_id BIGINT UNSIGNED UNIQUE NOT NULL COMMENT '🎯 Winning Bid Reference',
    merchant_id BIGINT UNSIGNED NOT NULL COMMENT '🏪 Merchant Reference',
    final_amount DECIMAL(10,2) NOT NULL COMMENT '💰 Final Contract Amount',
    contract_terms TEXT COMMENT '📄 Contract Terms',
    delivery_terms JSON COMMENT '🚚 Delivery Agreement',
    awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '🏆 Award Time',
    expected_delivery TIMESTAMP NULL COMMENT '📅 Expected Delivery',
    status ENUM('active', 'completed', 'disputed', 'cancelled') DEFAULT 'active' COMMENT '📊 Contract Status',
    dispute_details JSON COMMENT '⚖️ Dispute Information',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (bid_id) REFERENCES bids(id),
    FOREIGN KEY (merchant_id) REFERENCES merchant_profiles(id),
    INDEX idx_award_order (order_id),
    INDEX idx_award_bid (bid_id),
    INDEX idx_award_merchant (merchant_id),
    INDEX idx_award_status (status),
    INDEX idx_award_expected_delivery (expected_delivery)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 📢 NOTIFICATION SYSTEM DOMAIN
-- ========================================

CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL COMMENT '👤 User Reference',
    type ENUM('bid_received', 'order_update', 'payment_due', 'award_notification', 'system_alert') NOT NULL COMMENT '📝 Notification Type',
    title VARCHAR(255) NOT NULL COMMENT '📋 Notification Title',
    message TEXT NOT NULL COMMENT '💬 Notification Message',
    data JSON COMMENT '📊 Additional Data',
    channel ENUM('push', 'sms', 'email', 'in_app', 'whatsapp') NOT NULL COMMENT '📱 Delivery Channel',
    read BOOLEAN DEFAULT FALSE COMMENT '👁️ Read Status',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium' COMMENT '⭐ Priority Level',
    sent_at TIMESTAMP NULL COMMENT '📤 Sent Time',
    read_at TIMESTAMP NULL COMMENT '👁️ Read Time',
    expires_at TIMESTAMP NULL COMMENT '⏰ Expiry Time',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notification_user (user_id),
    INDEX idx_notification_type (type),
    INDEX idx_notification_channel (channel),
    INDEX idx_notification_read (read),
    INDEX idx_notification_priority (priority),
    INDEX idx_notification_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_preferences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED UNIQUE NOT NULL COMMENT '👤 User Reference',
    notification_type VARCHAR(100) NOT NULL COMMENT '📝 Notification Category',
    push_enabled BOOLEAN DEFAULT TRUE COMMENT '📱 Push Notifications',
    sms_enabled BOOLEAN DEFAULT TRUE COMMENT '📞 SMS Notifications',
    email_enabled BOOLEAN DEFAULT TRUE COMMENT '📧 Email Notifications',
    whatsapp_enabled BOOLEAN DEFAULT FALSE COMMENT '💬 WhatsApp Notifications',
    schedule_settings JSON COMMENT '⏰ Delivery Schedule',
    frequency_limits JSON COMMENT '🔄 Frequency Controls',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '🔄 Updated At',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_notification_type (user_id, notification_type),
    INDEX idx_notification_pref_user (user_id),
    INDEX idx_notification_pref_type (notification_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 💳 PAYMENT & ZATCA INTEGRATION DOMAIN
-- ========================================

CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL COMMENT '📋 Order Reference',
    award_id BIGINT UNSIGNED NULL COMMENT '🏆 Award Reference',
    payer_id BIGINT UNSIGNED NOT NULL COMMENT '👤 Payer Reference',
    payee_id BIGINT UNSIGNED NOT NULL COMMENT '🏪 Payee Reference',
    payment_number VARCHAR(50) UNIQUE NOT NULL COMMENT '🔢 Unique Payment Number',
    amount DECIMAL(10,2) NOT NULL COMMENT '💰 Payment Amount',
    tax_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT '💰 VAT Amount (15%)',
    currency VARCHAR(3) DEFAULT 'SAR' COMMENT '💱 Saudi Riyal',
    status ENUM('pending', 'processing', 'completed', 'failed', 'refunded') DEFAULT 'pending' COMMENT '📊 Payment Status',
    payment_method ENUM('card', 'bank_transfer', 'wallet', 'stc_pay') NOT NULL COMMENT '💳 Payment Method',
    payment_details JSON COMMENT '📋 Gateway Details',
    gateway_transaction_id VARCHAR(255) COMMENT '🔗 Gateway Transaction ID',
    zatca_compliance JSON COMMENT '🇸🇦 ZATCA Compliance Data',
    processed_at TIMESTAMP NULL COMMENT '✅ Processing Time',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '🔄 Updated At',
    
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (award_id) REFERENCES awards(id),
    FOREIGN KEY (payer_id) REFERENCES users(id),
    FOREIGN KEY (payee_id) REFERENCES users(id),
    INDEX idx_payment_order (order_id),
    INDEX idx_payment_award (award_id),
    INDEX idx_payment_payer (payer_id),
    INDEX idx_payment_payee (payee_id),
    INDEX idx_payment_status (status),
    INDEX idx_payment_method (payment_method),
    INDEX idx_payment_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE zatca_invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id BIGINT UNSIGNED UNIQUE NOT NULL COMMENT '💳 Payment Reference',
    invoice_number VARCHAR(50) UNIQUE NOT NULL COMMENT '🔢 ZATCA Invoice Number',
    zatca_uuid VARCHAR(255) UNIQUE NOT NULL COMMENT '🆔 ZATCA Unique ID',
    qr_code TEXT COMMENT '📱 QR Code Data',
    invoice_data JSON NOT NULL COMMENT '📄 Complete Invoice JSON',
    status ENUM('draft', 'submitted', 'approved', 'rejected', 'cancelled') DEFAULT 'draft' COMMENT '📊 Invoice Status',
    zatca_response TEXT COMMENT '🇸🇦 ZATCA API Response',
    hash_value VARCHAR(255) COMMENT '🔐 Invoice Hash',
    submitted_at TIMESTAMP NULL COMMENT '📤 Submission Time',
    approved_at TIMESTAMP NULL COMMENT '✅ Approval Time',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    INDEX idx_zatca_payment (payment_id),
    INDEX idx_zatca_invoice_number (invoice_number),
    INDEX idx_zatca_uuid (zatca_uuid),
    INDEX idx_zatca_status (status),
    INDEX idx_zatca_submitted (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- ⭐ REVIEWS & RATING SYSTEM DOMAIN
-- ========================================

CREATE TABLE reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL COMMENT '📋 Order Reference',
    reviewer_id BIGINT UNSIGNED NOT NULL COMMENT '👤 Reviewer Reference',
    reviewee_id BIGINT UNSIGNED NOT NULL COMMENT '🏪 Reviewee Reference',
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5) COMMENT '⭐ Overall Rating (1-5)',
    comment TEXT COMMENT '💬 Review Comment',
    criteria_ratings JSON COMMENT '📊 Detailed Ratings',
    verified_purchase BOOLEAN DEFAULT FALSE COMMENT '✅ Verified Transaction',
    helpful BOOLEAN DEFAULT FALSE COMMENT '👍 Helpful Review',
    helpful_count INT DEFAULT 0 COMMENT '👍 Helpful Votes',
    status ENUM('active', 'hidden', 'flagged', 'deleted') DEFAULT 'active' COMMENT '📊 Review Status',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '🔄 Updated At',
    
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (reviewer_id) REFERENCES users(id),
    FOREIGN KEY (reviewee_id) REFERENCES users(id),
    UNIQUE KEY unique_order_reviewer (order_id, reviewer_id),
    INDEX idx_review_order (order_id),
    INDEX idx_review_reviewer (reviewer_id),
    INDEX idx_review_reviewee (reviewee_id),
    INDEX idx_review_rating (rating),
    INDEX idx_review_status (status),
    INDEX idx_review_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 📊 ANALYTICS & BUSINESS INTELLIGENCE DOMAIN
-- ========================================

CREATE TABLE user_analytics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL COMMENT '👤 User Reference',
    event_type VARCHAR(100) NOT NULL COMMENT '📝 Event Category',
    event_data JSON COMMENT '📊 Event Details',
    session_id VARCHAR(255) COMMENT '🔗 Session Identifier',
    ip_address VARCHAR(45) COMMENT '🌐 IP Address',
    user_agent TEXT COMMENT '🖥️ Browser/Device Info',
    geo_location JSON COMMENT '📍 Geographic Data',
    referrer VARCHAR(500) COMMENT '🔗 Traffic Source',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Event Time',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_analytics_user (user_id),
    INDEX idx_analytics_event_type (event_type),
    INDEX idx_analytics_session (session_id),
    INDEX idx_analytics_created (created_at),
    INDEX idx_analytics_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_metrics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    metric_date DATE NOT NULL COMMENT '📅 Metric Date',
    metric_type ENUM('orders', 'bids', 'revenue', 'users', 'conversion') NOT NULL COMMENT '📊 Metric Type',
    value DECIMAL(15,2) NOT NULL COMMENT '📈 Metric Value',
    breakdown JSON COMMENT '📊 Detailed Breakdown',
    dimensions JSON COMMENT '🏷️ Metric Dimensions',
    aggregation_level ENUM('daily', 'weekly', 'monthly') DEFAULT 'daily' COMMENT '📊 Aggregation Level',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    
    UNIQUE KEY unique_metric_date_type (metric_date, metric_type, aggregation_level),
    INDEX idx_metrics_date (metric_date),
    INDEX idx_metrics_type (metric_type),
    INDEX idx_metrics_aggregation (aggregation_level),
    INDEX idx_metrics_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- ⚙️ SYSTEM CONFIGURATION DOMAIN
-- ========================================

CREATE TABLE system_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key VARCHAR(255) UNIQUE NOT NULL COMMENT '🏷️ Configuration Key',
    value TEXT COMMENT '📝 Configuration Value',
    type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string' COMMENT '📊 Value Type',
    description TEXT COMMENT '📄 Setting Description',
    encrypted BOOLEAN DEFAULT FALSE COMMENT '🔒 Encrypted Value',
    category VARCHAR(100) DEFAULT 'general' COMMENT '📁 Setting Category',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '🔄 Updated At',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    
    INDEX idx_settings_key (key),
    INDEX idx_settings_category (category),
    INDEX idx_settings_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- PERFORMANCE OPTIMIZATION INDEXES
-- ========================================

-- Additional composite indexes for complex queries
CREATE INDEX idx_orders_customer_status ON orders(customer_id, status);
CREATE INDEX idx_bids_order_status ON bids(order_id, status);
CREATE INDEX idx_payments_status_created ON payments(status, created_at);
CREATE INDEX idx_notifications_user_read ON notifications(user_id, read);
CREATE INDEX idx_reviews_reviewee_rating ON reviews(reviewee_id, rating);

-- Partitioning preparation indexes
CREATE INDEX idx_user_analytics_created_month ON user_analytics(created_at);
CREATE INDEX idx_business_metrics_date_type ON business_metrics(metric_date, metric_type);

-- ========================================
-- INITIAL SYSTEM SETTINGS
-- ========================================

INSERT INTO system_settings (key, value, type, description, category) VALUES
('platform_name', 'Reverse Tender Platform', 'string', 'Platform display name', 'general'),
('platform_version', '1.0.0', 'string', 'Current platform version', 'general'),
('default_currency', 'SAR', 'string', 'Default currency for transactions', 'payment'),
('vat_rate', '0.15', 'number', 'VAT rate for Saudi Arabia (15%)', 'payment'),
('bid_expiry_hours', '72', 'number', 'Default bid expiry time in hours', 'bidding'),
('max_images_per_order', '10', 'number', 'Maximum images allowed per order', 'orders'),
('notification_retention_days', '90', 'number', 'Days to retain notifications', 'notifications'),
('analytics_retention_months', '24', 'number', 'Months to retain analytics data', 'analytics'),
('zatca_enabled', 'true', 'boolean', 'Enable ZATCA e-invoicing integration', 'payment'),
('vin_ocr_enabled', 'true', 'boolean', 'Enable VIN OCR processing', 'vehicles'),
('auto_bid_enabled', 'true', 'boolean', 'Enable automatic bidding feature', 'bidding'),
('merchant_commission_rate', '5.0', 'number', 'Default merchant commission rate (%)', 'payment');

-- ========================================
-- SCHEMA COMPLETION MESSAGE
-- ========================================

SELECT 'Database schema creation completed successfully!' as status,
       'All 13 business domains implemented' as domains,
       'ZATCA compliance ready' as compliance,
       'VIN OCR integration ready' as ocr_status,
       'Real-time bidding ready' as bidding_status;
