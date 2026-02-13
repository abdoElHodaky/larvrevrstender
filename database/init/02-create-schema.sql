-- ========================================
-- NEON POSTGRESQL SCHEMA - PART 1
-- ========================================
-- REVERSE TENDER PLATFORM - COMPLETE DATABASE SCHEMA
-- Based on Enhanced ERD with 13 Business Domains
-- ZATCA Compliance | VIN OCR | Real-time Bidding
-- Optimized for Neon PostgreSQL compatibility
-- ========================================

-- Connect to the main database
\c reverse_tender;

-- ========================================
-- POSTGRESQL ENUM TYPE DEFINITIONS
-- ========================================
-- Create all ENUM types before table creation

-- User and Authentication ENUMs
CREATE TYPE user_type_enum AS ENUM ('customer', 'merchant', 'admin');
CREATE TYPE oauth_provider_enum AS ENUM ('google', 'apple', 'facebook', 'twitter');
CREATE TYPE otp_type_enum AS ENUM ('registration', 'login', 'password_reset');

-- Document and Verification ENUMs
CREATE TYPE document_type_enum AS ENUM ('license', 'tax_certificate', 'insurance', 'cr');
CREATE TYPE verification_status_enum AS ENUM ('pending', 'approved', 'rejected', 'expired');

-- Vehicle ENUMs
CREATE TYPE vehicle_category_enum AS ENUM ('sedan', 'suv', 'hatchback', 'coupe', 'truck', 'convertible', 'wagon');
CREATE TYPE fuel_type_enum AS ENUM ('gasoline', 'diesel', 'hybrid', 'electric', 'cng');
CREATE TYPE body_style_enum AS ENUM ('sedan', 'suv', 'hatchback', 'coupe');
CREATE TYPE vehicle_condition_enum AS ENUM ('excellent', 'good', 'fair', 'poor');
CREATE TYPE processing_status_enum AS ENUM ('processing', 'completed', 'failed');

-- ========================================
-- 🔐 AUTHENTICATION & USER MANAGEMENT DOMAIN
-- ========================================

CREATE TABLE users (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    type user_type_enum NOT NULL DEFAULT 'customer',
    verified BOOLEAN DEFAULT FALSE,
    email_verified_at TIMESTAMP WITH TIME ZONE NULL,
    phone_verified_at TIMESTAMP WITH TIME ZONE NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Comments for users table
COMMENT ON TABLE users IS 'User accounts and authentication data';
COMMENT ON COLUMN users.name IS '👤 Full Name';
COMMENT ON COLUMN users.email IS '📧 Email Address';
COMMENT ON COLUMN users.phone IS '📱 Phone Number';
COMMENT ON COLUMN users.password IS '🔒 Encrypted Password';
COMMENT ON COLUMN users.type IS '👥 User Type';
COMMENT ON COLUMN users.verified IS '✅ Account Verified';
COMMENT ON COLUMN users.email_verified_at IS '📧 Email Verification Time';
COMMENT ON COLUMN users.phone_verified_at IS '📱 Phone Verification Time';
COMMENT ON COLUMN users.created_at IS '⏰ Created At';
COMMENT ON COLUMN users.updated_at IS '🔄 Updated At';

-- Indexes for users table
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_phone ON users(phone);
CREATE INDEX idx_users_type ON users(type);
CREATE INDEX idx_users_verified ON users(verified);

-- Trigger for updated_at
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TABLE user_sessions (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    device_info TEXT,
    ip_address VARCHAR(45),
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_sessions_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Comments for user_sessions table
COMMENT ON TABLE user_sessions IS 'User session management';
COMMENT ON COLUMN user_sessions.user_id IS '👤 User Reference';
COMMENT ON COLUMN user_sessions.session_token IS '🎫 Unique Session Token';
COMMENT ON COLUMN user_sessions.device_info IS '📱 Device Information';
COMMENT ON COLUMN user_sessions.ip_address IS '🌐 IP Address';
COMMENT ON COLUMN user_sessions.expires_at IS '⏰ Session Expiry';
COMMENT ON COLUMN user_sessions.created_at IS '⏰ Created At';

-- Indexes for user_sessions table
CREATE INDEX idx_sessions_user_id ON user_sessions(user_id);
CREATE INDEX idx_sessions_token ON user_sessions(session_token);
CREATE INDEX idx_sessions_expires ON user_sessions(expires_at);

CREATE TABLE oauth_providers (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NOT NULL,
    provider oauth_provider_enum NOT NULL,
    provider_id VARCHAR(255) NOT NULL,
    provider_token TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_oauth_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT unique_provider_user UNIQUE (user_id, provider)
);

-- Comments for oauth_providers table
COMMENT ON TABLE oauth_providers IS 'OAuth provider integrations';
COMMENT ON COLUMN oauth_providers.user_id IS '👤 User Reference';
COMMENT ON COLUMN oauth_providers.provider IS '🔗 OAuth Provider';
COMMENT ON COLUMN oauth_providers.provider_id IS '🆔 Provider User ID';
COMMENT ON COLUMN oauth_providers.provider_token IS '🎫 OAuth Token';
COMMENT ON COLUMN oauth_providers.created_at IS '⏰ Created At';

-- Indexes for oauth_providers table
CREATE INDEX idx_oauth_user_id ON oauth_providers(user_id);
CREATE INDEX idx_oauth_provider ON oauth_providers(provider);

CREATE TABLE otp_verifications (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NULL,
    phone_or_email VARCHAR(255) NOT NULL,
    otp_code VARCHAR(10) NOT NULL,
    type otp_type_enum NOT NULL,
    verified BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_otp_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Comments for otp_verifications table
COMMENT ON TABLE otp_verifications IS 'OTP verification codes';
COMMENT ON COLUMN otp_verifications.user_id IS '👤 User Reference';
COMMENT ON COLUMN otp_verifications.phone_or_email IS '📱📧 Contact Method';
COMMENT ON COLUMN otp_verifications.otp_code IS '🔢 6-Digit Code';
COMMENT ON COLUMN otp_verifications.type IS '📝 OTP Purpose';
COMMENT ON COLUMN otp_verifications.verified IS '✅ Verification Status';
COMMENT ON COLUMN otp_verifications.expires_at IS '⏰ Code Expiry';
COMMENT ON COLUMN otp_verifications.created_at IS '⏰ Created At';

-- Indexes for otp_verifications table
CREATE INDEX idx_otp_contact ON otp_verifications(phone_or_email);
CREATE INDEX idx_otp_code ON otp_verifications(otp_code);
CREATE INDEX idx_otp_expires ON otp_verifications(expires_at);

-- ========================================
-- 👥 CUSTOMER PROFILE DOMAIN
-- ========================================

CREATE TABLE customer_profiles (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT UNIQUE NOT NULL,
    national_id VARCHAR(20) UNIQUE,
    national_address TEXT,
    default_location JSONB,
    preferences JSONB,
    loyalty_points NUMERIC(10,2) DEFAULT 0.00,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_customer_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Comments for customer_profiles table
COMMENT ON TABLE customer_profiles IS 'Customer profile information';
COMMENT ON COLUMN customer_profiles.user_id IS '👤 User Reference';
COMMENT ON COLUMN customer_profiles.national_id IS '🇸🇦 Saudi National ID (ZATCA)';
COMMENT ON COLUMN customer_profiles.national_address IS '🏠 National Address';
COMMENT ON COLUMN customer_profiles.default_location IS '📍 GPS Coordinates';
COMMENT ON COLUMN customer_profiles.preferences IS '⚙️ User Preferences';
COMMENT ON COLUMN customer_profiles.loyalty_points IS '🎯 Loyalty Points';
COMMENT ON COLUMN customer_profiles.created_at IS '⏰ Created At';
COMMENT ON COLUMN customer_profiles.updated_at IS '🔄 Updated At';

-- Indexes for customer_profiles table
CREATE INDEX idx_customer_national_id ON customer_profiles(national_id);
CREATE INDEX idx_customer_loyalty ON customer_profiles(loyalty_points);

-- Trigger for updated_at
CREATE TRIGGER update_customer_profiles_updated_at BEFORE UPDATE ON customer_profiles
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ========================================
-- 🏪 MERCHANT PROFILE DOMAIN
-- ========================================

CREATE TABLE merchant_profiles (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT UNIQUE NOT NULL,
    business_name VARCHAR(255) NOT NULL,
    business_type VARCHAR(100),
    commercial_registration VARCHAR(50) UNIQUE,
    tax_number VARCHAR(50) UNIQUE,
    business_address TEXT,
    business_location JSONB,
    operating_hours JSONB,
    service_areas JSONB,
    specializations JSONB,
    rating NUMERIC(3,2) DEFAULT 0.00,
    total_orders INTEGER DEFAULT 0,
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_merchant_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Comments for merchant_profiles table
COMMENT ON TABLE merchant_profiles IS 'Merchant business profiles';
COMMENT ON COLUMN merchant_profiles.user_id IS '👤 User Reference';
COMMENT ON COLUMN merchant_profiles.business_name IS '🏪 Business Name';
COMMENT ON COLUMN merchant_profiles.business_type IS '🏢 Business Type';
COMMENT ON COLUMN merchant_profiles.commercial_registration IS '📋 CR Number';
COMMENT ON COLUMN merchant_profiles.tax_number IS '🧾 Tax Number';
COMMENT ON COLUMN merchant_profiles.business_address IS '🏠 Business Address';
COMMENT ON COLUMN merchant_profiles.business_location IS '📍 GPS Coordinates';
COMMENT ON COLUMN merchant_profiles.operating_hours IS '🕐 Operating Hours';
COMMENT ON COLUMN merchant_profiles.service_areas IS '🗺️ Service Areas';
COMMENT ON COLUMN merchant_profiles.specializations IS '🔧 Specializations';
COMMENT ON COLUMN merchant_profiles.rating IS '⭐ Average Rating';
COMMENT ON COLUMN merchant_profiles.total_orders IS '📊 Total Orders';
COMMENT ON COLUMN merchant_profiles.verified IS '✅ Business Verified';
COMMENT ON COLUMN merchant_profiles.created_at IS '⏰ Created At';
COMMENT ON COLUMN merchant_profiles.updated_at IS '🔄 Updated At';

-- Indexes for merchant_profiles table
CREATE INDEX idx_merchant_business_name ON merchant_profiles(business_name);
CREATE INDEX idx_merchant_cr ON merchant_profiles(commercial_registration);
CREATE INDEX idx_merchant_tax ON merchant_profiles(tax_number);
CREATE INDEX idx_merchant_rating ON merchant_profiles(rating);
CREATE INDEX idx_merchant_verified ON merchant_profiles(verified);

-- Trigger for updated_at
CREATE TRIGGER update_merchant_profiles_updated_at BEFORE UPDATE ON merchant_profiles
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TABLE merchant_documents (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    merchant_id BIGINT NOT NULL,
    document_type document_type_enum NOT NULL,
    document_path VARCHAR(500) NOT NULL,
    status verification_status_enum DEFAULT 'pending',
    verified_at TIMESTAMP WITH TIME ZONE NULL,
    expires_at TIMESTAMP WITH TIME ZONE NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_merchant_docs_merchant_id FOREIGN KEY (merchant_id) REFERENCES merchant_profiles(id) ON DELETE CASCADE
);

-- Comments for merchant_documents table
COMMENT ON TABLE merchant_documents IS 'Merchant verification documents';
COMMENT ON COLUMN merchant_documents.merchant_id IS '🏪 Merchant Reference';
COMMENT ON COLUMN merchant_documents.document_type IS '📄 Document Type';
COMMENT ON COLUMN merchant_documents.document_path IS '📁 File Path';
COMMENT ON COLUMN merchant_documents.status IS '📊 Verification Status';
COMMENT ON COLUMN merchant_documents.verified_at IS '✅ Verification Time';
COMMENT ON COLUMN merchant_documents.expires_at IS '⏰ Document Expiry';
COMMENT ON COLUMN merchant_documents.created_at IS '⏰ Created At';

-- Indexes for merchant_documents table
CREATE INDEX idx_merchant_docs_merchant_id ON merchant_documents(merchant_id);
CREATE INDEX idx_merchant_docs_type ON merchant_documents(document_type);
CREATE INDEX idx_merchant_docs_status ON merchant_documents(status);

-- ========================================
-- 🚗 VEHICLE MANAGEMENT DOMAIN
-- ========================================

CREATE TABLE vehicles (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    customer_id BIGINT NOT NULL,
    vin VARCHAR(17) UNIQUE,
    make VARCHAR(100) NOT NULL,
    model VARCHAR(100) NOT NULL,
    year INTEGER NOT NULL,
    category vehicle_category_enum,
    license_plate VARCHAR(20) UNIQUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_vehicles_customer_id FOREIGN KEY (customer_id) REFERENCES customer_profiles(id) ON DELETE CASCADE
);

-- Comments for vehicles table
COMMENT ON TABLE vehicles IS 'Customer vehicle information';
COMMENT ON COLUMN vehicles.customer_id IS '👤 Customer Reference';
COMMENT ON COLUMN vehicles.vin IS '🔢 Vehicle Identification Number';
COMMENT ON COLUMN vehicles.make IS '🏭 Vehicle Make';
COMMENT ON COLUMN vehicles.model IS '🚗 Vehicle Model';
COMMENT ON COLUMN vehicles.year IS '📅 Manufacturing Year';
COMMENT ON COLUMN vehicles.category IS '🏷️ Vehicle Category';
COMMENT ON COLUMN vehicles.license_plate IS '🔢 License Plate';
COMMENT ON COLUMN vehicles.created_at IS '⏰ Created At';
COMMENT ON COLUMN vehicles.updated_at IS '🔄 Updated At';

-- Indexes for vehicles table
CREATE INDEX idx_vehicles_customer_id ON vehicles(customer_id);
CREATE INDEX idx_vehicles_vin ON vehicles(vin);
CREATE INDEX idx_vehicles_make_model ON vehicles(make, model);
CREATE INDEX idx_vehicles_year ON vehicles(year);

-- Trigger for updated_at
CREATE TRIGGER update_vehicles_updated_at BEFORE UPDATE ON vehicles
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TABLE vehicle_specifications (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    vehicle_id BIGINT UNIQUE NOT NULL,
    engine_size VARCHAR(20),
    fuel_type fuel_type_enum,
    body_style body_style_enum,
    transmission VARCHAR(50),
    drivetrain VARCHAR(50),
    color VARCHAR(50),
    mileage INTEGER,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_vehicle_specs_vehicle_id FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
);

-- Comments for vehicle_specifications table
COMMENT ON TABLE vehicle_specifications IS 'Detailed vehicle specifications';
COMMENT ON COLUMN vehicle_specifications.vehicle_id IS '🚗 Vehicle Reference';
COMMENT ON COLUMN vehicle_specifications.engine_size IS '🔧 Engine Size';
COMMENT ON COLUMN vehicle_specifications.fuel_type IS '⛽ Fuel Type';
COMMENT ON COLUMN vehicle_specifications.body_style IS '🚗 Body Style';
COMMENT ON COLUMN vehicle_specifications.transmission IS '⚙️ Transmission';
COMMENT ON COLUMN vehicle_specifications.drivetrain IS '🔄 Drivetrain';
COMMENT ON COLUMN vehicle_specifications.color IS '🎨 Vehicle Color';
COMMENT ON COLUMN vehicle_specifications.mileage IS '📏 Mileage';
COMMENT ON COLUMN vehicle_specifications.created_at IS '⏰ Created At';
COMMENT ON COLUMN vehicle_specifications.updated_at IS '🔄 Updated At';

-- Indexes for vehicle_specifications table
CREATE INDEX idx_vehicle_specs_vehicle_id ON vehicle_specifications(vehicle_id);
CREATE INDEX idx_vehicle_specs_fuel_type ON vehicle_specifications(fuel_type);
CREATE INDEX idx_vehicle_specs_body_style ON vehicle_specifications(body_style);

-- Trigger for updated_at
CREATE TRIGGER update_vehicle_specifications_updated_at BEFORE UPDATE ON vehicle_specifications
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TABLE vehicle_inspections (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    vehicle_id BIGINT NOT NULL,
    inspector_id BIGINT,
    condition vehicle_condition_enum DEFAULT 'good',
    inspection_date TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    inspection_report JSONB,
    photos JSONB,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_vehicle_inspections_vehicle_id FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    CONSTRAINT fk_vehicle_inspections_inspector_id FOREIGN KEY (inspector_id) REFERENCES users(id)
);

-- Comments for vehicle_inspections table
COMMENT ON TABLE vehicle_inspections IS 'Vehicle condition inspections';
COMMENT ON COLUMN vehicle_inspections.vehicle_id IS '🚗 Vehicle Reference';
COMMENT ON COLUMN vehicle_inspections.inspector_id IS '👤 Inspector Reference';
COMMENT ON COLUMN vehicle_inspections.condition IS '📊 Vehicle Condition';
COMMENT ON COLUMN vehicle_inspections.inspection_date IS '📅 Inspection Date';
COMMENT ON COLUMN vehicle_inspections.inspection_report IS '📋 Inspection Report';
COMMENT ON COLUMN vehicle_inspections.photos IS '📷 Inspection Photos';
COMMENT ON COLUMN vehicle_inspections.created_at IS '⏰ Created At';

-- Indexes for vehicle_inspections table
CREATE INDEX idx_vehicle_inspections_vehicle_id ON vehicle_inspections(vehicle_id);
CREATE INDEX idx_vehicle_inspections_inspector_id ON vehicle_inspections(inspector_id);
CREATE INDEX idx_vehicle_inspections_date ON vehicle_inspections(inspection_date);

-- ========================================
-- 🔍 VIN OCR PROCESSING DOMAIN
-- ========================================

CREATE TABLE vin_ocr_requests (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    extracted_vin VARCHAR(17),
    confidence_score NUMERIC(5,4),
    processing_time INTEGER,
    status processing_status_enum DEFAULT 'processing',
    error_message TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP WITH TIME ZONE,
    
    CONSTRAINT fk_vin_ocr_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Comments for vin_ocr_requests table
COMMENT ON TABLE vin_ocr_requests IS 'VIN OCR processing requests';
COMMENT ON COLUMN vin_ocr_requests.user_id IS '👤 User Reference';
COMMENT ON COLUMN vin_ocr_requests.image_path IS '📷 Image File Path';
COMMENT ON COLUMN vin_ocr_requests.extracted_vin IS '🔢 Extracted VIN';
COMMENT ON COLUMN vin_ocr_requests.confidence_score IS '📊 OCR Confidence';
COMMENT ON COLUMN vin_ocr_requests.processing_time IS '⏱️ Processing Time (ms)';
COMMENT ON COLUMN vin_ocr_requests.status IS '⚙️ Processing Status';
COMMENT ON COLUMN vin_ocr_requests.error_message IS '❌ Error Details';
COMMENT ON COLUMN vin_ocr_requests.created_at IS '⏰ Created At';
COMMENT ON COLUMN vin_ocr_requests.completed_at IS '✅ Completed At';

-- Indexes for vin_ocr_requests table
CREATE INDEX idx_vin_ocr_user_id ON vin_ocr_requests(user_id);
CREATE INDEX idx_vin_ocr_status ON vin_ocr_requests(status);
CREATE INDEX idx_vin_ocr_created_at ON vin_ocr_requests(created_at);

-- Grant schema permissions to service users
GRANT USAGE ON SCHEMA public TO laravel;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO laravel;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO laravel;
