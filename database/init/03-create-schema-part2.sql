-- ========================================
-- NEON POSTGRESQL SCHEMA - PART 2
-- ========================================
-- REVERSE TENDER PLATFORM - COMPLETE DATABASE SCHEMA (PART 2)
-- Orders, Bidding, Payments, Notifications, Reviews, Analytics
-- Optimized for Neon PostgreSQL compatibility
-- ========================================

-- Connect to the main database
\c reverse_tender;

-- ========================================
-- ADDITIONAL POSTGRESQL ENUM TYPE DEFINITIONS
-- ========================================
-- Create additional ENUM types for Part 2 tables

-- Order and Bidding ENUMs
CREATE TYPE order_status_enum AS ENUM ('draft', 'published', 'bidding', 'awarded', 'completed', 'cancelled');
CREATE TYPE image_type_enum AS ENUM ('part_photo', 'damage_photo', 'reference', 'vin_photo');
CREATE TYPE bid_status_enum AS ENUM ('active', 'withdrawn', 'awarded', 'rejected', 'expired');
CREATE TYPE contract_status_enum AS ENUM ('active', 'completed', 'disputed', 'cancelled');

-- Notification ENUMs
CREATE TYPE notification_type_enum AS ENUM ('bid_received', 'order_update', 'payment_due', 'award_notification', 'system_alert');
CREATE TYPE notification_channel_enum AS ENUM ('push', 'sms', 'email', 'in_app', 'whatsapp');
CREATE TYPE priority_level_enum AS ENUM ('low', 'medium', 'high', 'urgent');

-- Payment ENUMs
CREATE TYPE payment_status_enum AS ENUM ('pending', 'processing', 'completed', 'failed', 'refunded');
CREATE TYPE payment_method_enum AS ENUM ('card', 'bank_transfer', 'wallet', 'stc_pay');
CREATE TYPE invoice_status_enum AS ENUM ('draft', 'submitted', 'approved', 'rejected', 'cancelled');

-- Review and Analytics ENUMs
CREATE TYPE review_status_enum AS ENUM ('active', 'hidden', 'flagged', 'deleted');
CREATE TYPE metric_type_enum AS ENUM ('orders', 'bids', 'revenue', 'users', 'conversion');
CREATE TYPE aggregation_level_enum AS ENUM ('daily', 'weekly', 'monthly');
CREATE TYPE value_type_enum AS ENUM ('string', 'number', 'boolean', 'json');

-- ========================================
-- 📋 ORDERS & PART REQUESTS DOMAIN
-- ========================================

CREATE TABLE orders (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    customer_id BIGINT NOT NULL,
    vehicle_id BIGINT NOT NULL,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    status order_status_enum DEFAULT 'draft',
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    part_details JSONB,
    budget_min NUMERIC(10,2) DEFAULT 0.00,
    budget_max NUMERIC(10,2) DEFAULT 0.00,
    delivery_location JSONB,
    urgent BOOLEAN DEFAULT FALSE,
    priority_score INTEGER DEFAULT 5,
    deadline TIMESTAMP WITH TIME ZONE NULL,
    published_at TIMESTAMP WITH TIME ZONE NULL,
    completed_at TIMESTAMP WITH TIME ZONE NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_orders_customer_id FOREIGN KEY (customer_id) REFERENCES customer_profiles(id) ON DELETE CASCADE,
    CONSTRAINT fk_orders_vehicle_id FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
);

-- Comments for orders table
COMMENT ON TABLE orders IS 'Customer part requests and orders';
COMMENT ON COLUMN orders.customer_id IS '👤 Customer Reference';
COMMENT ON COLUMN orders.vehicle_id IS '🚗 Vehicle Reference';
COMMENT ON COLUMN orders.order_number IS '🔢 Unique Order Number';
COMMENT ON COLUMN orders.status IS '📊 Order Status';
COMMENT ON COLUMN orders.title IS '📝 Request Title';
COMMENT ON COLUMN orders.description IS '📄 Detailed Description';
COMMENT ON COLUMN orders.part_details IS '🔧 Required Parts Specification';
COMMENT ON COLUMN orders.budget_min IS '💰 Minimum Budget';
COMMENT ON COLUMN orders.budget_max IS '💰 Maximum Budget';
COMMENT ON COLUMN orders.delivery_location IS '📍 Delivery Address';
COMMENT ON COLUMN orders.urgent IS '🚨 Urgent Request';
COMMENT ON COLUMN orders.priority_score IS '⭐ Priority Score (1-10)';
COMMENT ON COLUMN orders.deadline IS '⏰ Response Deadline';
COMMENT ON COLUMN orders.published_at IS '📅 Published Time';
COMMENT ON COLUMN orders.completed_at IS '✅ Completion Time';
COMMENT ON COLUMN orders.created_at IS '⏰ Created At';
COMMENT ON COLUMN orders.updated_at IS '🔄 Updated At';

-- Indexes for orders table
CREATE INDEX idx_order_customer ON orders(customer_id);
CREATE INDEX idx_order_vehicle ON orders(vehicle_id);
CREATE INDEX idx_order_status ON orders(status);
CREATE INDEX idx_order_published ON orders(published_at);
CREATE INDEX idx_order_deadline ON orders(deadline);
CREATE INDEX idx_order_urgent ON orders(urgent);

-- Trigger for updated_at
CREATE TRIGGER update_orders_updated_at BEFORE UPDATE ON orders
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TABLE order_images (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    order_id BIGINT NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    image_type image_type_enum NOT NULL,
    description TEXT,
    sort_order INTEGER DEFAULT 0,
    metadata JSONB,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_order_images_order_id FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- Comments for order_images table
COMMENT ON TABLE order_images IS 'Order-related images and photos';
COMMENT ON COLUMN order_images.order_id IS '📋 Order Reference';
COMMENT ON COLUMN order_images.image_path IS '📷 Image File Path';
COMMENT ON COLUMN order_images.image_type IS '🖼️ Image Type';
COMMENT ON COLUMN order_images.description IS '📝 Image Description';
COMMENT ON COLUMN order_images.sort_order IS '📊 Display Order';
COMMENT ON COLUMN order_images.metadata IS '📋 Image Properties';
COMMENT ON COLUMN order_images.created_at IS '⏰ Created At';

-- Indexes for order_images table
CREATE INDEX idx_order_images_order_id ON order_images(order_id);
CREATE INDEX idx_order_images_type ON order_images(image_type);

CREATE TABLE order_status_history (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    order_id BIGINT NOT NULL,
    old_status order_status_enum,
    new_status order_status_enum NOT NULL,
    changed_by BIGINT,
    reason TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_order_history_order_id FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_history_changed_by FOREIGN KEY (changed_by) REFERENCES users(id)
);

-- Comments for order_status_history table
COMMENT ON TABLE order_status_history IS 'Order status change tracking';
COMMENT ON COLUMN order_status_history.order_id IS '📋 Order Reference';
COMMENT ON COLUMN order_status_history.old_status IS '📊 Previous Status';
COMMENT ON COLUMN order_status_history.new_status IS '📊 New Status';
COMMENT ON COLUMN order_status_history.changed_by IS '👤 User Who Changed';
COMMENT ON COLUMN order_status_history.reason IS '📝 Change Reason';
COMMENT ON COLUMN order_status_history.created_at IS '⏰ Created At';

-- Indexes for order_status_history table
CREATE INDEX idx_order_history_order_id ON order_status_history(order_id);
CREATE INDEX idx_order_history_changed_by ON order_status_history(changed_by);

-- ========================================
-- 💰 BIDDING SYSTEM DOMAIN
-- ========================================

CREATE TABLE bids (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    order_id BIGINT NOT NULL,
    merchant_id BIGINT NOT NULL,
    amount NUMERIC(10,2) NOT NULL,
    delivery_time INTEGER,
    warranty_period INTEGER,
    notes TEXT,
    status bid_status_enum DEFAULT 'active',
    expires_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_bids_order_id FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_bids_merchant_id FOREIGN KEY (merchant_id) REFERENCES merchant_profiles(id) ON DELETE CASCADE
);

-- Comments for bids table
COMMENT ON TABLE bids IS 'Merchant bids on customer orders';
COMMENT ON COLUMN bids.order_id IS '📋 Order Reference';
COMMENT ON COLUMN bids.merchant_id IS '🏪 Merchant Reference';
COMMENT ON COLUMN bids.amount IS '💰 Bid Amount';
COMMENT ON COLUMN bids.delivery_time IS '🚚 Delivery Time (days)';
COMMENT ON COLUMN bids.warranty_period IS '🛡️ Warranty Period (months)';
COMMENT ON COLUMN bids.notes IS '📝 Additional Notes';
COMMENT ON COLUMN bids.status IS '📊 Bid Status';
COMMENT ON COLUMN bids.expires_at IS '⏰ Bid Expiry';
COMMENT ON COLUMN bids.created_at IS '⏰ Created At';
COMMENT ON COLUMN bids.updated_at IS '🔄 Updated At';

-- Indexes for bids table
CREATE INDEX idx_bids_order_id ON bids(order_id);
CREATE INDEX idx_bids_merchant_id ON bids(merchant_id);
CREATE INDEX idx_bids_status ON bids(status);
CREATE INDEX idx_bids_amount ON bids(amount);

-- Trigger for updated_at
CREATE TRIGGER update_bids_updated_at BEFORE UPDATE ON bids
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
