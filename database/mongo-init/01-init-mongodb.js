// 🗄️ MongoDB Initialization Script for Fallback Database
// Creates collections and indexes for each microservice

// Switch to the main database
db = db.getSiblingDB('larvrevrstender_fallback');

// Create collections for each service with appropriate indexes

// Auth Service Collections
db.createCollection('auth_users');
db.createCollection('auth_sessions');
db.createCollection('auth_activity_logs');
db.createCollection('auth_failed_logins');

// User Service Collections
db.createCollection('user_profiles');
db.createCollection('user_documents');
db.createCollection('user_preferences');
db.createCollection('user_verification_logs');

// Auction Service Collections
db.createCollection('auction_data');
db.createCollection('auction_requirements');
db.createCollection('auction_configurations');
db.createCollection('auction_invitations');

// Bidding Service Collections
db.createCollection('bid_data');
db.createCollection('bid_evaluations');
db.createCollection('bid_attachments');
db.createCollection('automatic_bid_history');

// Payment Service Collections
db.createCollection('payment_transactions');
db.createCollection('payment_webhooks');
db.createCollection('payment_retry_attempts');
db.createCollection('refund_requests');

// Order Service Collections
db.createCollection('order_data');
db.createCollection('order_tracking_events');
db.createCollection('order_status_history');
db.createCollection('return_requests');

// Notification Service Collections
db.createCollection('notification_queue');
db.createCollection('notification_templates');
db.createCollection('notification_deliveries');
db.createCollection('notification_preferences');

// Analytics Service Collections
db.createCollection('analytics_events');
db.createCollection('analytics_metrics');
db.createCollection('analytics_reports');
db.createCollection('analytics_dashboards');

// Gateway Service Collections
db.createCollection('gateway_logs');
db.createCollection('gateway_rate_limits');
db.createCollection('gateway_health_checks');

// VIN OCR Service Collections
db.createCollection('ocr_jobs');
db.createCollection('vin_validations');
db.createCollection('vehicle_compatibility');

// Shared Service Collections
db.createCollection('system_settings');
db.createCollection('lookup_data');
db.createCollection('audit_logs');

// Create indexes for performance

// Auth Service Indexes
db.auth_users.createIndex({ "email": 1 }, { unique: true });
db.auth_users.createIndex({ "phone": 1 }, { unique: true });
db.auth_sessions.createIndex({ "user_id": 1 });
db.auth_sessions.createIndex({ "expires_at": 1 });
db.auth_activity_logs.createIndex({ "user_id": 1, "created_at": -1 });
db.auth_failed_logins.createIndex({ "ip_address": 1, "created_at": -1 });

// User Service Indexes
db.user_profiles.createIndex({ "user_id": 1 }, { unique: true });
db.user_profiles.createIndex({ "verification_status": 1 });
db.user_documents.createIndex({ "user_id": 1 });
db.user_preferences.createIndex({ "user_id": 1 });

// Auction Service Indexes
db.auction_data.createIndex({ "status": 1, "starts_at": 1, "ends_at": 1 });
db.auction_data.createIndex({ "created_by": 1 });
db.auction_requirements.createIndex({ "auction_id": 1 });
db.auction_invitations.createIndex({ "auction_id": 1, "supplier_id": 1 });

// Bidding Service Indexes
db.bid_data.createIndex({ "auction_id": 1, "amount": -1 });
db.bid_data.createIndex({ "user_id": 1, "status": 1 });
db.bid_evaluations.createIndex({ "bid_id": 1 });
db.bid_evaluations.createIndex({ "composite_score": -1 });

// Payment Service Indexes
db.payment_transactions.createIndex({ "order_id": 1 });
db.payment_transactions.createIndex({ "status": 1, "created_at": -1 });
db.payment_webhooks.createIndex({ "external_id": 1 });
db.refund_requests.createIndex({ "payment_id": 1 });

// Order Service Indexes
db.order_data.createIndex({ "order_number": 1 }, { unique: true });
db.order_data.createIndex({ "customer_id": 1, "status": 1 });
db.order_data.createIndex({ "merchant_id": 1, "status": 1 });
db.order_tracking_events.createIndex({ "order_id": 1, "timestamp": -1 });

// Notification Service Indexes
db.notification_queue.createIndex({ "status": 1, "scheduled_at": 1 });
db.notification_deliveries.createIndex({ "notification_id": 1 });
db.notification_preferences.createIndex({ "user_id": 1, "notification_type": 1 });

// Analytics Service Indexes
db.analytics_events.createIndex({ "event_type": 1, "timestamp": -1 });
db.analytics_events.createIndex({ "user_id": 1, "timestamp": -1 });
db.analytics_metrics.createIndex({ "metric_name": 1, "period_start": -1 });

// Gateway Service Indexes
db.gateway_logs.createIndex({ "timestamp": -1 });
db.gateway_rate_limits.createIndex({ "identifier": 1, "endpoint": 1, "window_start": 1 });
db.gateway_health_checks.createIndex({ "service_name": 1, "checked_at": -1 });

// VIN OCR Service Indexes
db.ocr_jobs.createIndex({ "user_id": 1, "status": 1 });
db.vin_validations.createIndex({ "vin": 1 }, { unique: true });

// Shared Service Indexes
db.system_settings.createIndex({ "key": 1 }, { unique: true });
db.audit_logs.createIndex({ "entity_type": 1, "entity_id": 1, "created_at": -1 });

// Create admin user
db.createUser({
  user: "larvrevrstender_admin",
  pwd: "admin_password_123",
  roles: [
    { role: "readWrite", db: "larvrevrstender_fallback" },
    { role: "dbAdmin", db: "larvrevrstender_fallback" }
  ]
});

// Create read-only user for analytics
db.createUser({
  user: "analytics_readonly",
  pwd: "analytics_readonly_password_123",
  roles: [
    { role: "read", db: "larvrevrstender_fallback" }
  ]
});

print("MongoDB initialization completed successfully!");
print("Created collections and indexes for all microservices");
print("Created admin and analytics users");
