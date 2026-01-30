# 🎨 Enhanced Database Schema - Reverse Tender Platform

## 🌟 Visual ERD with Eye-Catching Styling

```mermaid
%%{init: {
  'theme': 'base',
  'themeVariables': {
    'primaryColor': '#ff6b6b',
    'primaryTextColor': '#fff',
    'primaryBorderColor': '#ff4757',
    'lineColor': '#5f27cd',
    'secondaryColor': '#00d2d3',
    'tertiaryColor': '#ff9ff3',
    'background': '#f1f2f6',
    'mainBkg': '#ffffff',
    'secondBkg': '#ecf0f1',
    'tertiaryBkg': '#bdc3c7'
  }
}}%%

erDiagram
    %% 🔐 AUTHENTICATION & USER MANAGEMENT DOMAIN
    users ||--o{ user_sessions : "has_many"
    users ||--o{ oauth_providers : "has_many"
    users ||--o{ otp_verifications : "has_many"
    users ||--|| customer_profiles : "has_one"
    users ||--|| merchant_profiles : "has_one"
    
    users {
        bigint id PK "🔑 Primary Key"
        varchar name "👤 Full Name"
        varchar email UK "📧 Email Address"
        varchar phone UK "📱 Phone Number"
        varchar password "🔒 Encrypted Password"
        enum type "👥 customer|merchant|admin"
        boolean verified "✅ Account Verified"
        timestamp email_verified_at "📧 Email Verification Time"
        timestamp phone_verified_at "📱 Phone Verification Time"
        timestamp created_at "⏰ Created At"
        timestamp updated_at "🔄 Updated At"
    }
    
    user_sessions {
        bigint id PK "🔑 Session ID"
        bigint user_id FK "👤 User Reference"
        varchar session_token UK "🎫 Unique Session Token"
        text device_info "📱 Device Information"
        varchar ip_address "🌐 IP Address"
        timestamp expires_at "⏰ Session Expiry"
        timestamp created_at "⏰ Created At"
    }
    
    oauth_providers {
        bigint id PK "🔑 OAuth ID"
        bigint user_id FK "👤 User Reference"
        enum provider "🔗 google|apple|facebook|twitter"
        varchar provider_id "🆔 Provider User ID"
        text provider_token "🎫 OAuth Token"
        timestamp created_at "⏰ Created At"
    }
    
    otp_verifications {
        bigint id PK "🔑 OTP ID"
        bigint user_id FK "👤 User Reference"
        varchar phone_or_email "📱📧 Contact Method"
        varchar otp_code "🔢 6-Digit Code"
        enum type "📝 registration|login|password_reset"
        boolean verified "✅ Verification Status"
        timestamp expires_at "⏰ Code Expiry"
        timestamp created_at "⏰ Created At"
    }
    
    %% 👥 CUSTOMER PROFILE DOMAIN
    customer_profiles ||--o{ vehicles : "owns_many"
    customer_profiles ||--o{ part_requests : "creates_many"
    customer_profiles ||--o{ reviews : "writes_many"
    
    customer_profiles {
        bigint id PK "🔑 Customer ID"
        bigint user_id FK "👤 User Reference"
        varchar national_id "🇸🇦 Saudi National ID (ZATCA)"
        text national_address "🏠 National Address"
        json default_location "📍 GPS Coordinates"
        json preferences "⚙️ User Preferences"
        decimal loyalty_points "🎯 Loyalty Points"
        timestamp created_at "⏰ Created At"
        timestamp updated_at "🔄 Updated At"
    }
    
    %% 🏪 MERCHANT PROFILE DOMAIN
    merchant_profiles ||--o{ merchant_verifications : "has_many"
    merchant_profiles ||--o{ bids : "submits_many"
    merchant_profiles ||--o{ reviews : "receives_many"
    
    merchant_profiles {
        bigint id PK "🔑 Merchant ID"
        bigint user_id FK "👤 User Reference"
        varchar business_name "🏪 Business Name"
        varchar business_license "📄 License Number"
        varchar tax_number "💰 Tax ID (ZATCA)"
        json specializations "🔧 Service Categories"
        decimal rating "⭐ Average Rating (0-5)"
        int total_reviews "📊 Review Count"
        boolean verified "✅ Verification Status"
        json verification_documents "📋 Document URLs"
        json business_hours "🕒 Operating Hours"
        json service_areas "🗺️ Coverage Areas"
        decimal commission_rate "💸 Platform Commission"
        timestamp created_at "⏰ Created At"
        timestamp updated_at "🔄 Updated At"
    }
    
    merchant_verifications {
        bigint id PK "🔑 Verification ID"
        bigint merchant_id FK "🏪 Merchant Reference"
        enum document_type "📄 license|tax_certificate|insurance|cr"
        varchar document_path "📁 File Path"
        enum status "📊 pending|approved|rejected|expired"
        text rejection_reason "❌ Rejection Details"
        bigint verified_by FK "👤 Admin User ID"
        timestamp verified_at "✅ Verification Time"
        timestamp expires_at "⏰ Document Expiry"
        timestamp created_at "⏰ Created At"
    }
    
    %% 🚗 VEHICLE MANAGEMENT DOMAIN
    vehicle_brands ||--o{ vehicle_models : "has_many"
    vehicle_models ||--o{ vehicle_trims : "has_many"
    vehicle_brands ||--o{ vehicles : "brand_reference"
    vehicle_models ||--o{ vehicles : "model_reference"
    vehicle_trims ||--o{ vehicles : "trim_reference"
    vehicles ||--o{ vin_ocr_logs : "has_processing_logs"
    
    vehicle_brands {
        bigint id PK "🔑 Brand ID"
        varchar name "🚗 Brand Name"
        varchar logo_url "🖼️ Logo Image URL"
        varchar country_origin "🌍 Country of Origin"
        boolean active "✅ Active Status"
        int sort_order "📊 Display Order"
        timestamp created_at "⏰ Created At"
    }
    
    vehicle_models {
        bigint id PK "🔑 Model ID"
        bigint brand_id FK "🚗 Brand Reference"
        varchar name "🚙 Model Name"
        int year_start "📅 Production Start Year"
        int year_end "📅 Production End Year"
        enum category "🏷️ sedan|suv|hatchback|coupe|truck"
        boolean active "✅ Active Status"
        timestamp created_at "⏰ Created At"
    }
    
    vehicle_trims {
        bigint id PK "🔑 Trim ID"
        bigint model_id FK "🚙 Model Reference"
        varchar name "✨ Trim Level"
        varchar engine_type "⚙️ Engine Specification"
        varchar transmission_type "🔧 Transmission Type"
        enum fuel_type "⛽ gasoline|diesel|hybrid|electric"
        enum body_style "🚗 sedan|suv|hatchback|coupe"
        json specifications "📋 Technical Specs"
        timestamp created_at "⏰ Created At"
    }
    
    vehicles {
        bigint id PK "🔑 Vehicle ID"
        bigint customer_id FK "👤 Owner Reference"
        bigint brand_id FK "🚗 Brand Reference"
        bigint model_id FK "🚙 Model Reference"
        bigint trim_id FK "✨ Trim Reference"
        int year "📅 Manufacturing Year"
        varchar vin UK "🔢 17-Character VIN"
        boolean is_primary "⭐ Primary Vehicle"
        varchar custom_name "🏷️ Custom Nickname"
        int mileage "🛣️ Current Mileage"
        varchar color "🎨 Vehicle Color"
        enum condition "📊 excellent|good|fair|poor"
        decimal vin_confidence "🎯 OCR Confidence (0-1)"
        json maintenance_history "🔧 Service Records"
        timestamp created_at "⏰ Created At"
        timestamp updated_at "🔄 Updated At"
    }
    
    vin_ocr_logs {
        bigint id PK "🔑 OCR Log ID"
        bigint vehicle_id FK "🚗 Vehicle Reference"
        varchar original_image_path "📷 Original Image Path"
        varchar processed_image_path "🖼️ Processed Image Path"
        varchar extracted_vin "🔢 Extracted VIN Code"
        decimal confidence_score "🎯 OCR Confidence (0-1)"
        json ocr_metadata "📊 Processing Metadata"
        enum status "⚙️ processing|completed|failed"
        text error_message "❌ Error Details"
        timestamp created_at "⏰ Created At"
    }
    
    %% 🔧 PART CATEGORIES & MANAGEMENT DOMAIN
    part_categories ||--o{ part_categories : "has_subcategories"
    part_categories ||--o{ parts : "contains_parts"
    parts ||--o{ vehicle_parts : "compatible_with"
    vehicles ||--o{ vehicle_parts : "uses_parts"
    
    part_categories {
        bigint id PK "🔑 Category ID"
        bigint parent_id FK "📁 Parent Category"
        varchar name "🏷️ Category Name"
        text description "📝 Category Description"
        varchar icon_url "🖼️ Category Icon"
        boolean active "✅ Active Status"
        int sort_order "📊 Display Order"
        json metadata "📋 Additional Properties"
        timestamp created_at "⏰ Created At"
    }
    
    parts {
        bigint id PK "🔑 Part ID"
        bigint category_id FK "📁 Category Reference"
        varchar name "🔧 Part Name"
        varchar part_number "🔢 Manufacturer Part Number"
        text description "📝 Part Description"
        json specifications "📋 Technical Specifications"
        json compatibility_rules "🚗 Vehicle Compatibility"
        boolean active "✅ Active Status"
        decimal avg_price "💰 Average Market Price"
        timestamp created_at "⏰ Created At"
    }
    
    vehicle_parts {
        bigint id PK "🔑 Compatibility ID"
        bigint vehicle_id FK "🚗 Vehicle Reference"
        bigint part_id FK "🔧 Part Reference"
        boolean compatible "✅ Compatibility Status"
        text compatibility_notes "📝 Compatibility Notes"
        json fitment_details "🔧 Installation Details"
        timestamp created_at "⏰ Created At"
    }
    
    %% 📋 ORDERS & PART REQUESTS DOMAIN
    customer_profiles ||--o{ orders : "creates_requests"
    vehicles ||--o{ orders : "needs_parts"
    orders ||--o{ order_images : "has_images"
    orders ||--o{ order_status_history : "tracks_changes"
    orders ||--o{ bids : "receives_bids"
    
    orders {
        bigint id PK "🔑 Order ID"
        bigint customer_id FK "👤 Customer Reference"
        bigint vehicle_id FK "🚗 Vehicle Reference"
        varchar order_number UK "🔢 Unique Order Number"
        enum status "📊 draft|published|bidding|awarded|completed|cancelled"
        varchar title "📝 Request Title"
        text description "📄 Detailed Description"
        json part_details "🔧 Required Parts Specification"
        decimal budget_min "💰 Minimum Budget"
        decimal budget_max "💰 Maximum Budget"
        json delivery_location "📍 Delivery Address"
        boolean urgent "🚨 Urgent Request"
        int priority_score "⭐ Priority Score (1-10)"
        timestamp deadline "⏰ Response Deadline"
        timestamp published_at "📅 Published Time"
        timestamp completed_at "✅ Completion Time"
        timestamp created_at "⏰ Created At"
        timestamp updated_at "🔄 Updated At"
    }
    
    order_images {
        bigint id PK "🔑 Image ID"
        bigint order_id FK "📋 Order Reference"
        varchar image_path "📷 Image File Path"
        enum image_type "🖼️ part_photo|damage_photo|reference|vin_photo"
        text description "📝 Image Description"
        int sort_order "📊 Display Order"
        json metadata "📋 Image Properties"
        timestamp created_at "⏰ Created At"
    }
    
    order_status_history {
        bigint id PK "🔑 History ID"
        bigint order_id FK "📋 Order Reference"
        bigint user_id FK "👤 User Who Changed"
        enum old_status "📊 Previous Status"
        enum new_status "📊 New Status"
        text reason "📝 Change Reason"
        json metadata "📋 Additional Context"
        timestamp created_at "⏰ Changed At"
    }
    
    %% 🎯 BIDDING & AUCTION SYSTEM DOMAIN
    merchant_profiles ||--o{ bids : "submits_bids"
    orders ||--o{ bids : "receives_bids"
    bids ||--o{ bid_messages : "has_communications"
    bids ||--o{ bid_history : "tracks_changes"
    bids ||--|| awards : "can_be_awarded"
    
    bids {
        bigint id PK "🔑 Bid ID"
        bigint order_id FK "📋 Order Reference"
        bigint merchant_id FK "🏪 Merchant Reference"
        decimal amount "💰 Bid Amount"
        text description "📝 Bid Description"
        json part_details "🔧 Offered Parts Details"
        int delivery_days "📅 Delivery Timeline (Days)"
        enum status "📊 active|withdrawn|awarded|rejected|expired"
        boolean auto_bid "🤖 Automated Bidding"
        decimal max_amount "💰 Auto-bid Maximum"
        json warranty_terms "🛡️ Warranty Information"
        decimal confidence_score "🎯 Merchant Confidence (0-1)"
        timestamp expires_at "⏰ Bid Expiry"
        timestamp created_at "⏰ Created At"
        timestamp updated_at "🔄 Updated At"
    }
    
    bid_messages {
        bigint id PK "🔑 Message ID"
        bigint bid_id FK "🎯 Bid Reference"
        bigint user_id FK "👤 Sender Reference"
        text message "💬 Message Content"
        json attachments "📎 File Attachments"
        boolean is_system_message "🤖 System Generated"
        timestamp created_at "⏰ Created At"
    }
    
    bid_history {
        bigint id PK "🔑 History ID"
        bigint bid_id FK "🎯 Bid Reference"
        decimal old_amount "💰 Previous Amount"
        decimal new_amount "💰 New Amount"
        text reason "📝 Change Reason"
        json metadata "📋 Change Context"
        timestamp created_at "⏰ Changed At"
    }
    
    %% 🏆 AWARDS & CONTRACTS DOMAIN
    orders ||--|| awards : "can_have_award"
    bids ||--|| awards : "winning_bid"
    merchant_profiles ||--o{ awards : "receives_awards"
    
    awards {
        bigint id PK "🔑 Award ID"
        bigint order_id FK "📋 Order Reference"
        bigint bid_id FK "🎯 Winning Bid Reference"
        bigint merchant_id FK "🏪 Merchant Reference"
        decimal final_amount "💰 Final Contract Amount"
        text contract_terms "📄 Contract Terms"
        json delivery_terms "🚚 Delivery Agreement"
        timestamp awarded_at "🏆 Award Time"
        timestamp expected_delivery "📅 Expected Delivery"
        enum status "📊 active|completed|disputed|cancelled"
        json dispute_details "⚖️ Dispute Information"
        timestamp created_at "⏰ Created At"
    }
    
    %% 📢 NOTIFICATION SYSTEM DOMAIN
    users ||--o{ notifications : "receives_notifications"
    users ||--|| notification_preferences : "has_preferences"
    
    notifications {
        bigint id PK "🔑 Notification ID"
        bigint user_id FK "👤 User Reference"
        enum type "📝 bid_received|order_update|payment_due|award_notification"
        varchar title "📋 Notification Title"
        text message "💬 Notification Message"
        json data "📊 Additional Data"
        enum channel "📱 push|sms|email|in_app|whatsapp"
        boolean read "👁️ Read Status"
        enum priority "⭐ low|medium|high|urgent"
        timestamp sent_at "📤 Sent Time"
        timestamp read_at "👁️ Read Time"
        timestamp expires_at "⏰ Expiry Time"
        timestamp created_at "⏰ Created At"
    }
    
    notification_preferences {
        bigint id PK "🔑 Preference ID"
        bigint user_id FK "👤 User Reference"
        varchar notification_type "📝 Notification Category"
        boolean push_enabled "📱 Push Notifications"
        boolean sms_enabled "📞 SMS Notifications"
        boolean email_enabled "📧 Email Notifications"
        boolean whatsapp_enabled "💬 WhatsApp Notifications"
        json schedule_settings "⏰ Delivery Schedule"
        json frequency_limits "🔄 Frequency Controls"
        timestamp created_at "⏰ Created At"
        timestamp updated_at "🔄 Updated At"
    }
    
    %% 💳 PAYMENT & ZATCA INTEGRATION DOMAIN
    orders ||--o{ payments : "has_payments"
    awards ||--o{ payments : "contract_payments"
    users ||--o{ payments : "payer_payments"
    users ||--o{ payments : "payee_payments"
    payments ||--|| zatca_invoices : "generates_invoice"
    
    payments {
        bigint id PK "🔑 Payment ID"
        bigint order_id FK "📋 Order Reference"
        bigint award_id FK "🏆 Award Reference"
        bigint payer_id FK "👤 Payer Reference"
        bigint payee_id FK "🏪 Payee Reference"
        varchar payment_number UK "🔢 Unique Payment Number"
        decimal amount "💰 Payment Amount"
        decimal tax_amount "💰 VAT Amount (15%)"
        varchar currency "💱 SAR (Saudi Riyal)"
        enum status "📊 pending|processing|completed|failed|refunded"
        enum payment_method "💳 card|bank_transfer|wallet|stc_pay"
        json payment_details "📋 Gateway Details"
        varchar gateway_transaction_id "🔗 Gateway Transaction ID"
        json zatca_compliance "🇸🇦 ZATCA Compliance Data"
        timestamp processed_at "✅ Processing Time"
        timestamp created_at "⏰ Created At"
        timestamp updated_at "🔄 Updated At"
    }
    
    zatca_invoices {
        bigint id PK "🔑 Invoice ID"
        bigint payment_id FK "💳 Payment Reference"
        varchar invoice_number UK "🔢 ZATCA Invoice Number"
        varchar zatca_uuid UK "🆔 ZATCA Unique ID"
        text qr_code "📱 QR Code Data"
        json invoice_data "📄 Complete Invoice JSON"
        enum status "📊 draft|submitted|approved|rejected|cancelled"
        text zatca_response "🇸🇦 ZATCA API Response"
        varchar hash_value "🔐 Invoice Hash"
        timestamp submitted_at "📤 Submission Time"
        timestamp approved_at "✅ Approval Time"
        timestamp created_at "⏰ Created At"
    }
    
    %% ⭐ REVIEWS & RATING SYSTEM DOMAIN
    orders ||--o{ reviews : "can_be_reviewed"
    users ||--o{ reviews : "writes_reviews"
    users ||--o{ reviews : "receives_reviews"
    
    reviews {
        bigint id PK "🔑 Review ID"
        bigint order_id FK "📋 Order Reference"
        bigint reviewer_id FK "👤 Reviewer Reference"
        bigint reviewee_id FK "🏪 Reviewee Reference"
        int rating "⭐ Overall Rating (1-5)"
        text comment "💬 Review Comment"
        json criteria_ratings "📊 Detailed Ratings"
        boolean verified_purchase "✅ Verified Transaction"
        boolean helpful "👍 Helpful Review"
        int helpful_count "👍 Helpful Votes"
        enum status "📊 active|hidden|flagged|deleted"
        timestamp created_at "⏰ Created At"
        timestamp updated_at "🔄 Updated At"
    }
    
    %% 📊 ANALYTICS & BUSINESS INTELLIGENCE DOMAIN
    users ||--o{ user_analytics : "generates_events"
    
    user_analytics {
        bigint id PK "🔑 Analytics ID"
        bigint user_id FK "👤 User Reference"
        varchar event_type "📝 Event Category"
        json event_data "📊 Event Details"
        varchar session_id "🔗 Session Identifier"
        varchar ip_address "🌐 IP Address"
        varchar user_agent "🖥️ Browser/Device Info"
        json geo_location "📍 Geographic Data"
        varchar referrer "🔗 Traffic Source"
        timestamp created_at "⏰ Event Time"
    }
    
    business_metrics {
        bigint id PK "🔑 Metric ID"
        date metric_date "📅 Metric Date"
        enum metric_type "📊 orders|bids|revenue|users|conversion"
        decimal value "📈 Metric Value"
        json breakdown "📊 Detailed Breakdown"
        json dimensions "🏷️ Metric Dimensions"
        varchar aggregation_level "📊 daily|weekly|monthly"
        timestamp created_at "⏰ Created At"
    }
    
    %% ⚙️ SYSTEM CONFIGURATION DOMAIN
    system_settings {
        bigint id PK "🔑 Setting ID"
        varchar key UK "🏷️ Configuration Key"
        text value "📝 Configuration Value"
        enum type "📊 string|number|boolean|json"
        text description "📄 Setting Description"
        boolean encrypted "🔒 Encrypted Value"
        varchar category "📁 Setting Category"
        timestamp updated_at "🔄 Updated At"
        timestamp created_at "⏰ Created At"
    }
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
