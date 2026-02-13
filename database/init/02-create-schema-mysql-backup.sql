-- ========================================
-- REVERSE TENDER PLATFORM - COMPLETE DATABASE SCHEMA
-- ========================================
-- Based on Enhanced ERD with 13 Business Domains
-- ZATCA Compliance | VIN OCR | Real-time Bidding
-- ========================================

USE reverse_tender;

-- ========================================
-- 🔐 AUTHENTICATION & USER MANAGEMENT DOMAIN
-- ========================================

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COMMENT '👤 Full Name',
    email VARCHAR(255) UNIQUE NOT NULL COMMENT '📧 Email Address',
    phone VARCHAR(20) UNIQUE NOT NULL COMMENT '📱 Phone Number',
    password VARCHAR(255) NOT NULL COMMENT '🔒 Encrypted Password',
    type ENUM('customer', 'merchant', 'admin') NOT NULL DEFAULT 'customer' COMMENT '👥 User Type',
    verified BOOLEAN DEFAULT FALSE COMMENT '✅ Account Verified',
    email_verified_at TIMESTAMP NULL COMMENT '📧 Email Verification Time',
    phone_verified_at TIMESTAMP NULL COMMENT '📱 Phone Verification Time',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '🔄 Updated At',
    
    INDEX idx_users_email (email),
    INDEX idx_users_phone (phone),
    INDEX idx_users_type (type),
    INDEX idx_users_verified (verified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL COMMENT '👤 User Reference',
    session_token VARCHAR(255) UNIQUE NOT NULL COMMENT '🎫 Unique Session Token',
    device_info TEXT COMMENT '📱 Device Information',
    ip_address VARCHAR(45) COMMENT '🌐 IP Address',
    expires_at TIMESTAMP NOT NULL COMMENT '⏰ Session Expiry',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_sessions_user_id (user_id),
    INDEX idx_sessions_token (session_token),
    INDEX idx_sessions_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE oauth_providers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL COMMENT '👤 User Reference',
    provider ENUM('google', 'apple', 'facebook', 'twitter') NOT NULL COMMENT '🔗 OAuth Provider',
    provider_id VARCHAR(255) NOT NULL COMMENT '🆔 Provider User ID',
    provider_token TEXT COMMENT '🎫 OAuth Token',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_provider_user (user_id, provider),
    INDEX idx_oauth_user_id (user_id),
    INDEX idx_oauth_provider (provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE otp_verifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL COMMENT '👤 User Reference',
    phone_or_email VARCHAR(255) NOT NULL COMMENT '📱📧 Contact Method',
    otp_code VARCHAR(10) NOT NULL COMMENT '🔢 6-Digit Code',
    type ENUM('registration', 'login', 'password_reset') NOT NULL COMMENT '📝 OTP Purpose',
    verified BOOLEAN DEFAULT FALSE COMMENT '✅ Verification Status',
    expires_at TIMESTAMP NOT NULL COMMENT '⏰ Code Expiry',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_otp_contact (phone_or_email),
    INDEX idx_otp_code (otp_code),
    INDEX idx_otp_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 👥 CUSTOMER PROFILE DOMAIN
-- ========================================

CREATE TABLE customer_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED UNIQUE NOT NULL COMMENT '👤 User Reference',
    national_id VARCHAR(20) UNIQUE COMMENT '🇸🇦 Saudi National ID (ZATCA)',
    national_address TEXT COMMENT '🏠 National Address',
    default_location JSON COMMENT '📍 GPS Coordinates',
    preferences JSON COMMENT '⚙️ User Preferences',
    loyalty_points DECIMAL(10,2) DEFAULT 0.00 COMMENT '🎯 Loyalty Points',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '🔄 Updated At',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_customer_national_id (national_id),
    INDEX idx_customer_loyalty (loyalty_points)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 🏪 MERCHANT PROFILE DOMAIN
-- ========================================

CREATE TABLE merchant_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED UNIQUE NOT NULL COMMENT '👤 User Reference',
    business_name VARCHAR(255) NOT NULL COMMENT '🏪 Business Name',
    business_license VARCHAR(100) COMMENT '📄 License Number',
    tax_number VARCHAR(50) COMMENT '💰 Tax ID (ZATCA)',
    specializations JSON COMMENT '🔧 Service Categories',
    rating DECIMAL(3,2) DEFAULT 0.00 COMMENT '⭐ Average Rating (0-5)',
    total_reviews INT DEFAULT 0 COMMENT '📊 Review Count',
    verified BOOLEAN DEFAULT FALSE COMMENT '✅ Verification Status',
    verification_documents JSON COMMENT '📋 Document URLs',
    business_hours JSON COMMENT '🕒 Operating Hours',
    service_areas JSON COMMENT '🗺️ Coverage Areas',
    commission_rate DECIMAL(5,2) DEFAULT 5.00 COMMENT '💸 Platform Commission',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '🔄 Updated At',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_merchant_business_name (business_name),
    INDEX idx_merchant_tax_number (tax_number),
    INDEX idx_merchant_rating (rating),
    INDEX idx_merchant_verified (verified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE merchant_verifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    merchant_id BIGINT UNSIGNED NOT NULL COMMENT '🏪 Merchant Reference',
    document_type ENUM('license', 'tax_certificate', 'insurance', 'cr') NOT NULL COMMENT '📄 Document Type',
    document_path VARCHAR(500) NOT NULL COMMENT '📁 File Path',
    status ENUM('pending', 'approved', 'rejected', 'expired') DEFAULT 'pending' COMMENT '📊 Verification Status',
    rejection_reason TEXT COMMENT '❌ Rejection Details',
    verified_by BIGINT UNSIGNED NULL COMMENT '👤 Admin User ID',
    verified_at TIMESTAMP NULL COMMENT '✅ Verification Time',
    expires_at TIMESTAMP NULL COMMENT '⏰ Document Expiry',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    
    FOREIGN KEY (merchant_id) REFERENCES merchant_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_verification_merchant (merchant_id),
    INDEX idx_verification_status (status),
    INDEX idx_verification_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 🚗 VEHICLE MANAGEMENT DOMAIN
-- ========================================

CREATE TABLE vehicle_brands (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT '🚗 Brand Name',
    logo_url VARCHAR(500) COMMENT '🖼️ Logo Image URL',
    country_origin VARCHAR(100) COMMENT '🌍 Country of Origin',
    active BOOLEAN DEFAULT TRUE COMMENT '✅ Active Status',
    sort_order INT DEFAULT 0 COMMENT '📊 Display Order',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    
    UNIQUE KEY unique_brand_name (name),
    INDEX idx_brand_active (active),
    INDEX idx_brand_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vehicle_models (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    brand_id BIGINT UNSIGNED NOT NULL COMMENT '🚗 Brand Reference',
    name VARCHAR(100) NOT NULL COMMENT '🚙 Model Name',
    year_start INT NOT NULL COMMENT '📅 Production Start Year',
    year_end INT NULL COMMENT '📅 Production End Year',
    category ENUM('sedan', 'suv', 'hatchback', 'coupe', 'truck', 'convertible', 'wagon') COMMENT '🏷️ Vehicle Category',
    active BOOLEAN DEFAULT TRUE COMMENT '✅ Active Status',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    
    FOREIGN KEY (brand_id) REFERENCES vehicle_brands(id) ON DELETE CASCADE,
    INDEX idx_model_brand (brand_id),
    INDEX idx_model_year (year_start, year_end),
    INDEX idx_model_category (category),
    INDEX idx_model_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vehicle_trims (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    model_id BIGINT UNSIGNED NOT NULL COMMENT '🚙 Model Reference',
    name VARCHAR(100) NOT NULL COMMENT '✨ Trim Level',
    engine_type VARCHAR(100) COMMENT '⚙️ Engine Specification',
    transmission_type VARCHAR(50) COMMENT '🔧 Transmission Type',
    fuel_type ENUM('gasoline', 'diesel', 'hybrid', 'electric', 'cng') COMMENT '⛽ Fuel Type',
    body_style ENUM('sedan', 'suv', 'hatchback', 'coupe') COMMENT '🚗 Body Style',
    specifications JSON COMMENT '📋 Technical Specs',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    
    FOREIGN KEY (model_id) REFERENCES vehicle_models(id) ON DELETE CASCADE,
    INDEX idx_trim_model (model_id),
    INDEX idx_trim_fuel (fuel_type),
    INDEX idx_trim_body (body_style)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vehicles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL COMMENT '👤 Owner Reference',
    brand_id BIGINT UNSIGNED NOT NULL COMMENT '🚗 Brand Reference',
    model_id BIGINT UNSIGNED NOT NULL COMMENT '🚙 Model Reference',
    trim_id BIGINT UNSIGNED NULL COMMENT '✨ Trim Reference',
    year INT NOT NULL COMMENT '📅 Manufacturing Year',
    vin VARCHAR(17) UNIQUE NOT NULL COMMENT '🔢 17-Character VIN',
    is_primary BOOLEAN DEFAULT FALSE COMMENT '⭐ Primary Vehicle',
    custom_name VARCHAR(100) COMMENT '🏷️ Custom Nickname',
    mileage INT DEFAULT 0 COMMENT '🛣️ Current Mileage',
    color VARCHAR(50) COMMENT '🎨 Vehicle Color',
    condition ENUM('excellent', 'good', 'fair', 'poor') DEFAULT 'good' COMMENT '📊 Vehicle Condition',
    vin_confidence DECIMAL(3,2) DEFAULT 0.00 COMMENT '🎯 OCR Confidence (0-1)',
    maintenance_history JSON COMMENT '🔧 Service Records',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '🔄 Updated At',
    
    FOREIGN KEY (customer_id) REFERENCES customer_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (brand_id) REFERENCES vehicle_brands(id),
    FOREIGN KEY (model_id) REFERENCES vehicle_models(id),
    FOREIGN KEY (trim_id) REFERENCES vehicle_trims(id),
    INDEX idx_vehicle_customer (customer_id),
    INDEX idx_vehicle_brand (brand_id),
    INDEX idx_vehicle_model (model_id),
    INDEX idx_vehicle_vin (vin),
    INDEX idx_vehicle_year (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vin_ocr_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_id BIGINT UNSIGNED NULL COMMENT '🚗 Vehicle Reference',
    original_image_path VARCHAR(500) NOT NULL COMMENT '📷 Original Image Path',
    processed_image_path VARCHAR(500) COMMENT '🖼️ Processed Image Path',
    extracted_vin VARCHAR(17) COMMENT '🔢 Extracted VIN Code',
    confidence_score DECIMAL(3,2) DEFAULT 0.00 COMMENT '🎯 OCR Confidence (0-1)',
    ocr_metadata JSON COMMENT '📊 Processing Metadata',
    status ENUM('processing', 'completed', 'failed') DEFAULT 'processing' COMMENT '⚙️ Processing Status',
    error_message TEXT COMMENT '❌ Error Details',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
    INDEX idx_ocr_vehicle (vehicle_id),
    INDEX idx_ocr_status (status),
    INDEX idx_ocr_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 🔧 PART CATEGORIES & MANAGEMENT DOMAIN
-- ========================================

CREATE TABLE part_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NULL COMMENT '📁 Parent Category',
    name VARCHAR(255) NOT NULL COMMENT '🏷️ Category Name',
    description TEXT COMMENT '📝 Category Description',
    icon_url VARCHAR(500) COMMENT '🖼️ Category Icon',
    active BOOLEAN DEFAULT TRUE COMMENT '✅ Active Status',
    sort_order INT DEFAULT 0 COMMENT '📊 Display Order',
    metadata JSON COMMENT '📋 Additional Properties',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    
    FOREIGN KEY (parent_id) REFERENCES part_categories(id) ON DELETE SET NULL,
    INDEX idx_category_parent (parent_id),
    INDEX idx_category_active (active),
    INDEX idx_category_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE parts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL COMMENT '📁 Category Reference',
    name VARCHAR(255) NOT NULL COMMENT '🔧 Part Name',
    part_number VARCHAR(100) COMMENT '🔢 Manufacturer Part Number',
    description TEXT COMMENT '📝 Part Description',
    specifications JSON COMMENT '📋 Technical Specifications',
    compatibility_rules JSON COMMENT '🚗 Vehicle Compatibility',
    active BOOLEAN DEFAULT TRUE COMMENT '✅ Active Status',
    avg_price DECIMAL(10,2) DEFAULT 0.00 COMMENT '💰 Average Market Price',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    
    FOREIGN KEY (category_id) REFERENCES part_categories(id),
    INDEX idx_part_category (category_id),
    INDEX idx_part_number (part_number),
    INDEX idx_part_active (active),
    INDEX idx_part_price (avg_price)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vehicle_parts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_id BIGINT UNSIGNED NOT NULL COMMENT '🚗 Vehicle Reference',
    part_id BIGINT UNSIGNED NOT NULL COMMENT '🔧 Part Reference',
    compatible BOOLEAN DEFAULT TRUE COMMENT '✅ Compatibility Status',
    compatibility_notes TEXT COMMENT '📝 Compatibility Notes',
    fitment_details JSON COMMENT '🔧 Installation Details',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '⏰ Created At',
    
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE CASCADE,
    UNIQUE KEY unique_vehicle_part (vehicle_id, part_id),
    INDEX idx_vehicle_part_vehicle (vehicle_id),
    INDEX idx_vehicle_part_part (part_id),
    INDEX idx_vehicle_part_compatible (compatible)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Continue with remaining domains...
-- This is Part 1 of the schema. Part 2 will include Orders, Bidding, Payments, etc.
