# 📊 Enhanced Database Schema Diagram

```mermaid
erDiagram
    %% Users and Authentication
    users {
        bigint id PK
        string name
        string email UK
        string phone UK
        string password
        enum type "customer, merchant, admin"
        boolean verified
        timestamp email_verified_at
        timestamp phone_verified_at
        timestamp created_at
        timestamp updated_at
    }
    
    user_sessions {
        bigint id PK
        bigint user_id FK
        string session_token UK
        string device_info
        string ip_address
        timestamp expires_at
        timestamp created_at
    }
    
    oauth_providers {
        bigint id PK
        bigint user_id FK
        string provider "google, apple, facebook"
        string provider_id
        string provider_token
        timestamp created_at
    }
    
    otp_verifications {
        bigint id PK
        bigint user_id FK
        string phone_or_email
        string otp_code
        enum type "registration, login, password_reset"
        boolean verified
        timestamp expires_at
        timestamp created_at
    }
    
    %% Customer Profiles
    customer_profiles {
        bigint id PK
        bigint user_id FK
        string national_id "Saudi National ID for ZATCA"
        text national_address
        json default_location
        json preferences
        timestamp created_at
        timestamp updated_at
    }
    
    %% Merchant Profiles
    merchant_profiles {
        bigint id PK
        bigint user_id FK
        string business_name
        string business_license
        string tax_number "For ZATCA integration"
        json specializations
        decimal rating "3,2"
        int total_reviews
        boolean verified
        json verification_documents
        json business_hours
        json service_areas
        timestamp created_at
        timestamp updated_at
    }
    
    merchant_verifications {
        bigint id PK
        bigint merchant_id FK
        enum document_type "license, tax_certificate, insurance"
        string document_path
        enum status "pending, approved, rejected"
        text rejection_reason
        timestamp verified_at
        timestamp created_at
    }
    
    %% Vehicle Management
    vehicle_brands {
        bigint id PK
        string name
        string logo_url
        boolean active
        timestamp created_at
    }
    
    vehicle_models {
        bigint id PK
        bigint brand_id FK
        string name
        int year_start
        int year_end
        boolean active
        timestamp created_at
    }
    
    vehicle_trims {
        bigint id PK
        bigint model_id FK
        string name
        string engine_type
        string transmission_type
        string fuel_type
        string body_style
        timestamp created_at
    }
    
    vehicles {
        bigint id PK
        bigint customer_id FK
        bigint brand_id FK
        bigint model_id FK
        bigint trim_id FK
        int year
        string vin UK "17 characters"
        boolean is_primary
        string custom_name
        int mileage
        string engine_type
        string transmission_type
        string fuel_type
        string body_style
        decimal vin_confidence "3,2" "OCR confidence score"
        timestamp created_at
        timestamp updated_at
    }
    
    vin_ocr_logs {
        bigint id PK
        bigint vehicle_id FK
        string original_image_path
        string processed_image_path
        string extracted_vin
        decimal confidence_score "3,2"
        json ocr_metadata
        enum status "processing, completed, failed"
        timestamp created_at
    }
    
    %% Part Categories and Management
    part_categories {
        bigint id PK
        bigint parent_id FK
        string name
        string description
        string icon_url
        boolean active
        int sort_order
        timestamp created_at
    }
    
    parts {
        bigint id PK
        bigint category_id FK
        string name
        string part_number
        text description
        json specifications
        boolean active
        timestamp created_at
    }
    
    vehicle_parts {
        bigint id PK
        bigint vehicle_id FK
        bigint part_id FK
        boolean compatible
        text notes
        timestamp created_at
    }
    
    %% Orders and Requests
    orders {
        bigint id PK
        bigint customer_id FK
        bigint vehicle_id FK
        string order_number UK
        enum status "draft, published, bidding, awarded, completed, cancelled"
        string title
        text description
        json part_details
        decimal budget_min "10,2"
        decimal budget_max "10,2"
        json delivery_location
        boolean urgent
        timestamp deadline
        timestamp published_at
        timestamp completed_at
        timestamp created_at
        timestamp updated_at
    }
    
    order_images {
        bigint id PK
        bigint order_id FK
        string image_path
        string image_type "part_photo, damage_photo, reference"
        text description
        int sort_order
        timestamp created_at
    }
    
    order_status_history {
        bigint id PK
        bigint order_id FK
        bigint user_id FK
        enum old_status
        enum new_status
        text reason
        json metadata
        timestamp created_at
    }
    
    %% Bidding System
    bids {
        bigint id PK
        bigint order_id FK
        bigint merchant_id FK
        decimal amount "10,2"
        text description
        json part_details
        int delivery_days
        enum status "active, withdrawn, awarded, rejected"
        boolean auto_bid
        decimal max_amount "10,2"
        timestamp expires_at
        timestamp created_at
        timestamp updated_at
    }
    
    bid_messages {
        bigint id PK
        bigint bid_id FK
        bigint user_id FK
        text message
        json attachments
        timestamp created_at
    }
    
    bid_history {
        bigint id PK
        bigint bid_id FK
        decimal old_amount "10,2"
        decimal new_amount "10,2"
        text reason
        timestamp created_at
    }
    
    %% Awards and Contracts
    awards {
        bigint id PK
        bigint order_id FK
        bigint bid_id FK
        bigint merchant_id FK
        decimal final_amount "10,2"
        text terms
        timestamp awarded_at
        timestamp expected_delivery
        enum status "active, completed, disputed, cancelled"
        timestamp created_at
    }
    
    %% Notifications
    notifications {
        bigint id PK
        bigint user_id FK
        string type "bid_received, order_update, payment_due"
        string title
        text message
        json data
        enum channel "push, sms, email, in_app"
        boolean read
        timestamp sent_at
        timestamp read_at
        timestamp created_at
    }
    
    notification_preferences {
        bigint id PK
        bigint user_id FK
        string notification_type
        boolean push_enabled
        boolean sms_enabled
        boolean email_enabled
        json schedule_settings
        timestamp created_at
        timestamp updated_at
    }
    
    %% Payment and ZATCA Integration
    payments {
        bigint id PK
        bigint order_id FK
        bigint award_id FK
        bigint payer_id FK
        bigint payee_id FK
        string payment_number UK
        decimal amount "10,2"
        decimal tax_amount "10,2"
        string currency "SAR"
        enum status "pending, processing, completed, failed, refunded"
        enum payment_method "card, bank_transfer, wallet"
        json payment_details
        string gateway_transaction_id
        timestamp processed_at
        timestamp created_at
        timestamp updated_at
    }
    
    zatca_invoices {
        bigint id PK
        bigint payment_id FK
        string invoice_number UK
        string zatca_uuid UK
        string qr_code
        json invoice_data
        enum status "draft, submitted, approved, rejected"
        string zatca_response
        timestamp submitted_at
        timestamp approved_at
        timestamp created_at
    }
    
    %% Reviews and Ratings
    reviews {
        bigint id PK
        bigint order_id FK
        bigint reviewer_id FK
        bigint reviewee_id FK
        int rating "1-5"
        text comment
        json criteria_ratings
        boolean verified_purchase
        timestamp created_at
        timestamp updated_at
    }
    
    %% Analytics and Reporting
    user_analytics {
        bigint id PK
        bigint user_id FK
        string event_type
        json event_data
        string session_id
        string ip_address
        string user_agent
        timestamp created_at
    }
    
    business_metrics {
        bigint id PK
        date metric_date
        string metric_type "orders, bids, revenue, users"
        decimal value "15,2"
        json breakdown
        timestamp created_at
    }
    
    %% System Configuration
    system_settings {
        bigint id PK
        string key UK
        text value
        string type "string, number, boolean, json"
        text description
        timestamp updated_at
        timestamp created_at
    }
    
    %% Relationships
    users ||--o{ customer_profiles : "has"
    users ||--o{ merchant_profiles : "has"
    users ||--o{ user_sessions : "has"
    users ||--o{ oauth_providers : "has"
    users ||--o{ otp_verifications : "has"
    
    customer_profiles ||--o{ vehicles : "owns"
    merchant_profiles ||--o{ merchant_verifications : "has"
    
    vehicle_brands ||--o{ vehicle_models : "has"
    vehicle_models ||--o{ vehicle_trims : "has"
    vehicle_brands ||--o{ vehicles : "belongs_to"
    vehicle_models ||--o{ vehicles : "belongs_to"
    vehicle_trims ||--o{ vehicles : "belongs_to"
    vehicles ||--o{ vin_ocr_logs : "has"
    
    part_categories ||--o{ part_categories : "parent"
    part_categories ||--o{ parts : "contains"
    vehicles ||--o{ vehicle_parts : "compatible_with"
    parts ||--o{ vehicle_parts : "compatible_with"
    
    customer_profiles ||--o{ orders : "creates"
    vehicles ||--o{ orders : "for"
    orders ||--o{ order_images : "has"
    orders ||--o{ order_status_history : "tracks"
    
    orders ||--o{ bids : "receives"
    merchant_profiles ||--o{ bids : "submits"
    bids ||--o{ bid_messages : "has"
    bids ||--o{ bid_history : "tracks"
    
    orders ||--o{ awards : "awarded_to"
    bids ||--o{ awards : "wins"
    merchant_profiles ||--o{ awards : "receives"
    
    users ||--o{ notifications : "receives"
    users ||--o{ notification_preferences : "has"
    
    orders ||--o{ payments : "for"
    awards ||--o{ payments : "for"
    users ||--o{ payments : "pays"
    users ||--o{ payments : "receives"
    payments ||--o{ zatca_invoices : "generates"
    
    orders ||--o{ reviews : "about"
    users ||--o{ reviews : "writes"
    users ||--o{ reviews : "receives"
    
    users ||--o{ user_analytics : "generates"
```

## 🔍 Schema Design Principles

### **1. Service-Oriented Design**
- **Auth Service**: `users`, `user_sessions`, `oauth_providers`, `otp_verifications`
- **User Service**: `customer_profiles`, `merchant_profiles`, `merchant_verifications`
- **Vehicle Service**: `vehicles`, `vehicle_brands`, `vehicle_models`, `vehicle_trims`, `vin_ocr_logs`
- **Order Service**: `orders`, `order_images`, `order_status_history`, `parts`, `part_categories`
- **Bidding Service**: `bids`, `bid_messages`, `bid_history`, `awards`
- **Notification Service**: `notifications`, `notification_preferences`
- **Payment Service**: `payments`, `zatca_invoices`
- **Analytics Service**: `user_analytics`, `business_metrics`

### **2. ZATCA Compliance Features**
- **Tax Numbers**: Stored in `merchant_profiles.tax_number`
- **National IDs**: Stored in `customer_profiles.national_id`
- **E-Invoicing**: Complete `zatca_invoices` table with UUID and QR codes
- **Tax Calculations**: `payments.tax_amount` for VAT handling

### **3. VIN OCR Integration**
- **VIN Storage**: `vehicles.vin` with uniqueness constraint
- **OCR Confidence**: `vehicles.vin_confidence` for accuracy tracking
- **OCR Logs**: `vin_ocr_logs` for processing history and debugging
- **Image Processing**: Paths for original and processed images

### **4. Real-time Bidding Support**
- **Bid Tracking**: Complete bid lifecycle with status management
- **Auto-bidding**: `bids.auto_bid` and `bids.max_amount` for automated bidding
- **Bid History**: `bid_history` for audit trail
- **Real-time Messages**: `bid_messages` for communication

### **5. Multi-channel Notifications**
- **Notification Types**: Flexible `notifications.type` for different events
- **Channel Support**: `notifications.channel` for push, SMS, email, in-app
- **User Preferences**: `notification_preferences` for granular control
- **Scheduling**: JSON settings for notification timing

### **6. Comprehensive Analytics**
- **User Behavior**: `user_analytics` for detailed event tracking
- **Business Metrics**: `business_metrics` for KPI monitoring
- **Session Tracking**: Session IDs and user agent information
- **Performance Data**: Breakdown JSON for detailed analysis

### **7. Security and Audit**
- **Status History**: Complete audit trail for orders and bids
- **Verification Tracking**: Document verification with timestamps
- **Session Management**: Secure session handling with expiration
- **Payment Security**: Gateway transaction IDs and status tracking

### **8. Scalability Considerations**
- **Indexing Strategy**: Primary keys, foreign keys, and business logic indexes
- **JSON Fields**: Flexible schema for evolving requirements
- **Partitioning Ready**: Date-based fields for time-series partitioning
- **Caching Support**: Optimized queries for Redis caching

## 📈 Performance Optimizations

### **Database Indexes**
```sql
-- User lookup indexes
CREATE INDEX idx_users_phone ON users(phone);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_type ON users(type);

-- Order performance indexes
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_customer_id ON orders(customer_id);
CREATE INDEX idx_orders_published_at ON orders(published_at);

-- Bidding system indexes
CREATE INDEX idx_bids_order_id ON bids(order_id);
CREATE INDEX idx_bids_merchant_id ON bids(merchant_id);
CREATE INDEX idx_bids_status ON bids(status);

-- Payment and ZATCA indexes
CREATE INDEX idx_payments_status ON payments(status);
CREATE INDEX idx_zatca_invoices_status ON zatca_invoices(status);

-- Analytics indexes
CREATE INDEX idx_user_analytics_user_id ON user_analytics(user_id);
CREATE INDEX idx_user_analytics_created_at ON user_analytics(created_at);
```

### **Partitioning Strategy**
```sql
-- Partition analytics tables by month
ALTER TABLE user_analytics PARTITION BY RANGE (YEAR(created_at) * 100 + MONTH(created_at));

-- Partition notifications by month
ALTER TABLE notifications PARTITION BY RANGE (YEAR(created_at) * 100 + MONTH(created_at));
```

This schema provides a robust foundation for the Reverse Tender Platform with full support for ZATCA compliance, VIN OCR integration, real-time bidding, and comprehensive analytics.

