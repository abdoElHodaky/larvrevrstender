# 📚 API Documentation

Complete API documentation for the Reverse Tender Platform microservices.

## 🔗 Base URLs

| Service | Development | Production |
|---------|-------------|------------|
| API Gateway | `http://localhost:8000` | `https://api.reversetender.com` |
| Auth Service | `http://localhost:8001` | `https://auth.reversetender.com` |
| Bidding Service | `http://localhost:8002` | `https://bidding.reversetender.com` |
| User Service | `http://localhost:8003` | `https://users.reversetender.com` |
| Order Service | `http://localhost:8004` | `https://orders.reversetender.com` |
| Notification Service | `http://localhost:8005` | `https://notifications.reversetender.com` |
| Payment Service | `http://localhost:8006` | `https://payments.reversetender.com` |
| Analytics Service | `http://localhost:8007` | `https://analytics.reversetender.com` |
| VIN OCR Service | `http://localhost:8008` | `https://vin.reversetender.com` |

## 🔐 Authentication

The platform uses multiple authentication methods:

### JWT Token Authentication

```bash
# Login to get JWT token
curl -X POST http://localhost:8001/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password"
  }'

# Response
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "token_type": "bearer",
  "expires_in": 3600,
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "user@example.com",
    "type": "customer"
  }
}
```

### Using the Token

```bash
# Include token in Authorization header
curl -X GET http://localhost:8001/api/v1/auth/me \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
```

### Laravel Sanctum (Alternative)

```bash
# Login with Sanctum
curl -X POST http://localhost:8001/api/v1/auth/sanctum/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password"
  }'

# Use token
curl -X GET http://localhost:8001/api/v1/auth/me \
  -H "Authorization: Bearer 1|sanctum_token_here"
```

## 🔐 Auth Service API

### User Registration

```http
POST /api/v1/auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+966501234567",
  "password": "password123",
  "password_confirmation": "password123",
  "type": "customer"
}
```

**Response:**
```json
{
  "message": "User registered successfully",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+966501234567",
    "type": "customer",
    "status": "active",
    "email_verified_at": null,
    "phone_verified_at": null
  },
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
}
```

### User Login

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "password123"
}
```

### Email Verification

```http
POST /api/v1/auth/email/verify
Content-Type: application/json

{
  "user_id": 1,
  "verification_code": "123456"
}
```

### Phone Verification

```http
POST /api/v1/auth/phone/verify
Content-Type: application/json

{
  "user_id": 1,
  "otp_code": "123456"
}
```

### OTP Management

```http
POST /api/v1/auth/otp/send
Content-Type: application/json

{
  "phone": "+966501234567",
  "type": "login"
}
```

### Social Authentication

```http
GET /api/v1/auth/social/google/redirect
```

```http
POST /api/v1/auth/social/google/callback
Content-Type: application/json

{
  "code": "google_auth_code",
  "state": "csrf_token"
}
```

### Two-Factor Authentication

```http
POST /api/v1/auth/2fa/enable
Authorization: Bearer {token}
Content-Type: application/json

{
  "password": "current_password"
}
```

```http
POST /api/v1/auth/2fa/verify
Authorization: Bearer {token}
Content-Type: application/json

{
  "code": "123456"
}
```

## ⚡ Bidding Service API

### Get Bids for Order

```http
GET /api/v1/bids?order_id=123
Authorization: Bearer {token}
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "order_id": 123,
      "merchant_id": 5,
      "amount": 1500.00,
      "currency": "SAR",
      "message": "I can deliver this within 2 days",
      "status": "active",
      "created_at": "2024-01-15T10:30:00Z",
      "merchant": {
        "id": 5,
        "name": "ABC Trading",
        "rating": 4.8
      }
    }
  ],
  "meta": {
    "total": 15,
    "per_page": 10,
    "current_page": 1
  }
}
```

### Place a Bid

```http
POST /api/v1/bids
Authorization: Bearer {token}
Content-Type: application/json

{
  "order_id": 123,
  "amount": 1500.00,
  "currency": "SAR",
  "message": "I can deliver this within 2 days",
  "delivery_time": "2024-01-17T10:00:00Z"
}
```

### Update Bid

```http
PUT /api/v1/bids/1
Authorization: Bearer {token}
Content-Type: application/json

{
  "amount": 1400.00,
  "message": "Updated offer with better price"
}
```

### Cancel Bid

```http
DELETE /api/v1/bids/1
Authorization: Bearer {token}
```

### Award Bid

```http
POST /api/v1/bids/1/award
Authorization: Bearer {token}
Content-Type: application/json

{
  "reason": "Best price and delivery time"
}
```

## 👥 User Service API

### Get User Profile

```http
GET /api/v1/users/profile
Authorization: Bearer {token}
```

### Update Profile

```http
PUT /api/v1/users/profile
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "John Doe Updated",
  "phone": "+966501234567",
  "address": {
    "street": "King Fahd Road",
    "city": "Riyadh",
    "postal_code": "12345",
    "country": "SA"
  }
}
```

### Customer Profile

```http
GET /api/v1/users/customer-profile
Authorization: Bearer {token}
```

```http
PUT /api/v1/users/customer-profile
Authorization: Bearer {token}
Content-Type: application/json

{
  "company_name": "ABC Company",
  "industry": "Technology",
  "preferences": {
    "notification_email": true,
    "notification_sms": false
  }
}
```

### Merchant Profile

```http
GET /api/v1/users/merchant-profile
Authorization: Bearer {token}
```

```http
PUT /api/v1/users/merchant-profile
Authorization: Bearer {token}
Content-Type: application/json

{
  "business_name": "XYZ Trading",
  "business_license": "123456789",
  "tax_number": "300123456789003",
  "categories": ["electronics", "automotive"],
  "service_areas": ["riyadh", "jeddah", "dammam"]
}
```

## 📦 Order Service API

### Get Orders

```http
GET /api/v1/orders
Authorization: Bearer {token}
```

**Query Parameters:**
- `status`: Filter by status (draft, published, in_progress, completed, cancelled)
- `category`: Filter by category
- `page`: Page number
- `per_page`: Items per page

### Create Order

```http
POST /api/v1/orders
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Need 100 laptops for office",
  "description": "Looking for Dell or HP laptops with specific requirements",
  "category": "electronics",
  "budget_min": 50000,
  "budget_max": 80000,
  "currency": "SAR",
  "delivery_location": {
    "address": "King Fahd Road, Riyadh",
    "latitude": 24.7136,
    "longitude": 46.6753
  },
  "delivery_date": "2024-02-15T10:00:00Z",
  "requirements": [
    {
      "item": "Laptop",
      "specifications": {
        "brand": "Dell or HP",
        "ram": "16GB",
        "storage": "512GB SSD",
        "processor": "Intel i7"
      },
      "quantity": 100
    }
  ]
}
```

### Update Order

```http
PUT /api/v1/orders/123
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Updated: Need 120 laptops for office",
  "budget_max": 90000
}
```

### Publish Order

```http
POST /api/v1/orders/123/publish
Authorization: Bearer {token}
```

### Cancel Order

```http
POST /api/v1/orders/123/cancel
Authorization: Bearer {token}
Content-Type: application/json

{
  "reason": "Requirements changed"
}
```

## 📢 Notification Service API

### Send Notification

```http
POST /api/v1/notifications
Authorization: Bearer {token}
Content-Type: application/json

{
  "user_id": 123,
  "type": "bid_placed",
  "title": "New bid received",
  "message": "You have received a new bid for your order",
  "channels": ["push", "email"],
  "data": {
    "order_id": 456,
    "bid_id": 789
  }
}
```

### Get User Notifications

```http
GET /api/v1/notifications
Authorization: Bearer {token}
```

### Mark as Read

```http
PUT /api/v1/notifications/123/read
Authorization: Bearer {token}
```

### Update Notification Preferences

```http
PUT /api/v1/notifications/preferences
Authorization: Bearer {token}
Content-Type: application/json

{
  "email_notifications": true,
  "push_notifications": true,
  "sms_notifications": false,
  "notification_types": {
    "bid_placed": true,
    "bid_awarded": true,
    "order_updates": true,
    "payment_updates": false
  }
}
```

## 💳 Payment Service API

### Create Payment

```http
POST /api/v1/payments
Authorization: Bearer {token}
Content-Type: application/json

{
  "order_id": 123,
  "bid_id": 456,
  "amount": 1500.00,
  "currency": "SAR",
  "payment_method": "card",
  "card_token": "card_token_from_frontend"
}
```

### Get Payment Status

```http
GET /api/v1/payments/789
Authorization: Bearer {token}
```

### ZATCA Invoice Generation

```http
POST /api/v1/payments/789/zatca-invoice
Authorization: Bearer {token}
Content-Type: application/json

{
  "customer_details": {
    "name": "John Doe",
    "tax_number": "123456789",
    "address": "King Fahd Road, Riyadh"
  },
  "items": [
    {
      "description": "Laptop Dell Inspiron",
      "quantity": 1,
      "unit_price": 1500.00,
      "tax_rate": 0.15
    }
  ]
}
```

### Get ZATCA Invoice

```http
GET /api/v1/zatca/invoices/INV-2024-001
Authorization: Bearer {token}
```

**Response:**
```json
{
  "invoice_number": "INV-2024-001",
  "zatca_uuid": "550e8400-e29b-41d4-a716-446655440000",
  "qr_code": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...",
  "status": "approved",
  "total_amount": 1725.00,
  "tax_amount": 225.00,
  "created_at": "2024-01-15T10:30:00Z"
}
```

## 📊 Analytics Service API

### Track Event

```http
POST /api/v1/analytics/events
Authorization: Bearer {token}
Content-Type: application/json

{
  "event_type": "order_created",
  "user_id": 123,
  "properties": {
    "order_id": 456,
    "category": "electronics",
    "budget": 5000
  }
}
```

### Get User Analytics

```http
GET /api/v1/analytics/users/123
Authorization: Bearer {token}
```

**Response:**
```json
{
  "user_id": 123,
  "total_events": 45,
  "last_activity": "2024-01-15T10:30:00Z",
  "event_breakdown": {
    "page_view": 20,
    "order_created": 5,
    "bid_placed": 15,
    "payment_completed": 5
  },
  "conversion_rate": 0.25
}
```

### Get Business Metrics

```http
GET /api/v1/analytics/metrics
Authorization: Bearer {token}
```

**Query Parameters:**
- `period`: daily, weekly, monthly, yearly
- `start_date`: Start date (YYYY-MM-DD)
- `end_date`: End date (YYYY-MM-DD)

### Dashboard Overview

```http
GET /api/v1/analytics/dashboard
Authorization: Bearer {token}
```

### Generate Custom Report

```http
POST /api/v1/analytics/reports
Authorization: Bearer {token}
Content-Type: application/json

{
  "report_type": "user_activity",
  "date_range": {
    "start": "2024-01-01",
    "end": "2024-01-31"
  },
  "filters": {
    "user_type": "merchant",
    "status": "active"
  },
  "format": "pdf"
}
```

## 🔍 VIN OCR Service API

### Upload VIN Image

```http
POST /api/v1/vin/upload
Authorization: Bearer {token}
Content-Type: multipart/form-data

{
  "image": [binary_image_data],
  "preprocessing": "auto",
  "confidence_threshold": 0.8
}
```

**Response:**
```json
{
  "processing_id": "proc_123456",
  "status": "processing",
  "estimated_completion": "2024-01-15T10:32:00Z"
}
```

### Get Processing Status

```http
GET /api/v1/vin/process/proc_123456
Authorization: Bearer {token}
```

**Response:**
```json
{
  "processing_id": "proc_123456",
  "status": "completed",
  "result": {
    "vin": "1HGBH41JXMN109186",
    "confidence": 0.95,
    "vehicle_info": {
      "make": "Honda",
      "model": "Civic",
      "year": 2021,
      "engine": "1.5L Turbo"
    }
  },
  "processing_time": 2.5
}
```

### Get Processing History

```http
GET /api/v1/vin/history
Authorization: Bearer {token}
```

### Reprocess Image

```http
POST /api/v1/vin/reprocess/proc_123456
Authorization: Bearer {token}
Content-Type: application/json

{
  "preprocessing": "enhanced",
  "confidence_threshold": 0.7
}
```

## 🔄 Real-time WebSocket API

### Laravel Reverb Connection

```javascript
// Connect to Laravel Reverb
const echo = new Echo({
    broadcaster: 'reverb',
    key: 'reverse-tender-key',
    wsHost: 'localhost',
    wsPort: 8080,
    forceTLS: false,
});
```

### Bidding Events

```javascript
// Listen to bidding events for a specific order
echo.channel('bidding.order.123')
    .listen('BidPlaced', (e) => {
        console.log('New bid:', e.bid);
        // Update UI with new bid
    })
    .listen('BidUpdated', (e) => {
        console.log('Bid updated:', e.bid);
        // Update existing bid in UI
    })
    .listen('BidAwarded', (e) => {
        console.log('Bid awarded:', e.bid);
        // Show award notification
    });
```

### Private User Channel

```javascript
// Listen to private user notifications
echo.private('user.123')
    .listen('OrderStatusChanged', (e) => {
        console.log('Order status changed:', e.order);
    })
    .listen('PaymentProcessed', (e) => {
        console.log('Payment processed:', e.payment);
    });
```

### Presence Channel

```javascript
// Join presence channel to see active bidders
echo.join('presence-bidding.order.123')
    .here((users) => {
        console.log('Currently active bidders:', users);
    })
    .joining((user) => {
        console.log('User joined bidding:', user);
    })
    .leaving((user) => {
        console.log('User left bidding:', user);
    });
```

## 📝 Response Formats

### Success Response

```json
{
  "success": true,
  "data": {
    // Response data
  },
  "message": "Operation completed successfully",
  "timestamp": "2024-01-15T10:30:00Z"
}
```

### Error Response

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "email": ["The email field is required."],
      "password": ["The password must be at least 8 characters."]
    }
  },
  "timestamp": "2024-01-15T10:30:00Z"
}
```

### Pagination Response

```json
{
  "data": [
    // Array of items
  ],
  "links": {
    "first": "http://localhost:8001/api/v1/orders?page=1",
    "last": "http://localhost:8001/api/v1/orders?page=10",
    "prev": null,
    "next": "http://localhost:8001/api/v1/orders?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "per_page": 15,
    "to": 15,
    "total": 150
  }
}
```

## 🚨 Error Codes

| Code | Description |
|------|-------------|
| `VALIDATION_ERROR` | Request validation failed |
| `AUTHENTICATION_ERROR` | Authentication required or failed |
| `AUTHORIZATION_ERROR` | Insufficient permissions |
| `NOT_FOUND` | Resource not found |
| `RATE_LIMIT_EXCEEDED` | Too many requests |
| `SERVER_ERROR` | Internal server error |
| `SERVICE_UNAVAILABLE` | Service temporarily unavailable |
| `PAYMENT_FAILED` | Payment processing failed |
| `ZATCA_ERROR` | ZATCA integration error |
| `OCR_PROCESSING_FAILED` | VIN OCR processing failed |

## 🔒 Rate Limiting

All API endpoints are rate-limited:

- **Authentication endpoints**: 5 requests per minute
- **General API endpoints**: 60 requests per minute
- **File upload endpoints**: 10 requests per minute
- **Real-time WebSocket**: 100 connections per IP

Rate limit headers are included in responses:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1642248000
```

## 🧪 Testing

### Using cURL

```bash
# Test authentication
curl -X POST http://localhost:8001/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "test@example.com", "password": "password"}'

# Test with authentication
curl -X GET http://localhost:8001/api/v1/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Using Postman

Import the Postman collection:
- Download: [Reverse Tender API.postman_collection.json](postman/Reverse%20Tender%20API.postman_collection.json)
- Environment: [Reverse Tender Environment.postman_environment.json](postman/Reverse%20Tender%20Environment.postman_environment.json)

### API Testing Tools

- **Insomnia**: REST client with WebSocket support
- **HTTPie**: Command-line HTTP client
- **Swagger UI**: Interactive API documentation (available at `/docs`)

---

**Need help?** Check our [Installation Guide](INSTALLATION.md) or open an issue on [GitHub](https://github.com/abdoElHodaky/larvrevrstender/issues).

