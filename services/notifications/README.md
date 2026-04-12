# Notifications

## Overview

The Notifications service manages multi-channel communication for the Reverse Tender platform, providing WhatsApp Business, SMS, Telegram, Signal, and Email notifications with intelligent fallback routing and MENA region optimization.

## Features

- **Multi-Channel Orchestration**: WhatsApp, SMS, Telegram, Signal, and Email
- **MENA-Optimized Providers**: Regional SMS and WhatsApp providers for optimal delivery
- **Intelligent Fallback**: Automatic failover between communication channels
- **Template Management**: Dynamic message templates with variable substitution
- **Delivery Tracking**: Real-time delivery status and analytics
- **Rate Limiting**: Provider-specific rate limiting and queue management
- **Bulk Messaging**: Efficient batch message processing
- **Webhook Integration**: Real-time delivery status updates

## API Endpoints

### Single Message Sending
- `POST /api/notifications/send` - Send single notification
- `POST /api/notifications/whatsapp` - Send WhatsApp message
- `POST /api/notifications/sms` - Send SMS message
- `POST /api/notifications/telegram` - Send Telegram message
- `POST /api/notifications/signal` - Send Signal message
- `POST /api/notifications/email` - Send email notification

### Bulk Messaging
- `POST /api/notifications/bulk` - Send bulk notifications
- `POST /api/notifications/bulk/whatsapp` - Bulk WhatsApp messages
- `POST /api/notifications/bulk/sms` - Bulk SMS messages

### Multi-Channel
- `POST /api/notifications/multi-channel` - Send via multiple channels
- `POST /api/notifications/broadcast` - Broadcast to all channels

### Status & Tracking
- `GET /api/notifications/{id}/status` - Get delivery status
- `GET /api/notifications/history` - Get notification history
- `POST /api/notifications/webhook` - Delivery status webhook

### Templates
- `GET /api/templates` - List message templates
- `POST /api/templates` - Create message template
- `PUT /api/templates/{id}` - Update message template
- `DELETE /api/templates/{id}` - Delete message template

## Configuration

### Environment Variables

```bash
# Application Configuration
APP_NAME="Notifications"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8007

# Database Configuration
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=notifications_db
DB_USERNAME=postgres
DB_PASSWORD=password

# Queue Configuration
QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# WhatsApp Providers (5 providers)
WHATSAPP_PRIMARY_PROVIDER=unifonic
UNIFONIC_WHATSAPP_API_KEY=your_unifonic_whatsapp_key
UNIFONIC_WHATSAPP_SENDER=your_sender_id

MSEGAT_WHATSAPP_API_KEY=your_msegat_whatsapp_key
MSEGAT_WHATSAPP_USERNAME=your_msegat_username

OURSMS_WHATSAPP_API_KEY=your_oursms_whatsapp_key
INFOBIP_WHATSAPP_API_KEY=your_infobip_whatsapp_key
TWILIO_WHATSAPP_SID=your_twilio_sid
TWILIO_WHATSAPP_TOKEN=your_twilio_token

# SMS Providers (4 regional providers)
SMS_PRIMARY_PROVIDER=unifonic
UNIFONIC_SMS_API_KEY=your_unifonic_sms_key
UNIFONIC_SMS_SENDER=your_sender_name

MSEGAT_SMS_API_KEY=your_msegat_sms_key
MSEGAT_SMS_USERNAME=your_msegat_username
MSEGAT_SMS_PASSWORD=your_msegat_password

OURSMS_SMS_API_KEY=your_oursms_sms_key
OURSMS_SMS_USERNAME=your_oursms_username

INFOBIP_SMS_API_KEY=your_infobip_sms_key
INFOBIP_SMS_BASE_URL=https://api.infobip.com

# Telegram Configuration
TELEGRAM_BOT_TOKEN=your_telegram_bot_token
TELEGRAM_API_URL=https://api.telegram.org

# Signal Configuration
SIGNAL_CLI_PATH=/usr/local/bin/signal-cli
SIGNAL_PHONE_NUMBER=your_signal_number
SIGNAL_WEBHOOK_URL=your_webhook_url

# Email Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_app_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@reversetender.com
MAIL_FROM_NAME="Reverse Tender"

# Service Authentication Tokens
AUTH_TOKEN=your_auth_token_here
USERS_TOKEN=your_users_token_here
AUCTIONS_TOKEN=your_auctions_token_here
ANALYTICS_TOKEN=your_analytics_token_here
```

## Development Setup

### Prerequisites
- PHP 8.3+
- Composer
- PostgreSQL 15+
- Redis 7+
- Signal CLI (for Signal integration)

### Installation

```bash
# Navigate to notifications service
cd services/notifications

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run database migrations
php artisan migrate

# Start queue workers
php artisan queue:work

# Start the service
php artisan serve --port=8007
```

### Testing

```bash
# Run all tests
./vendor/bin/phpunit

# Test specific provider
./vendor/bin/phpunit --filter WhatsAppTest

# Test with coverage
./vendor/bin/phpunit --coverage-html coverage
```

## Provider Configuration

### WhatsApp Business Providers

#### 1. Unifonic (Primary)
- **Region**: GCC, MENA
- **Features**: Rich media, templates, delivery reports
- **Rate Limit**: 1000 messages/minute

#### 2. Msegat
- **Region**: Saudi Arabia, UAE
- **Features**: Bulk messaging, scheduling
- **Rate Limit**: 500 messages/minute

#### 3. OurSMS
- **Region**: North Africa
- **Features**: Template management, analytics
- **Rate Limit**: 300 messages/minute

#### 4. Infobip
- **Region**: Global, MENA
- **Features**: Advanced analytics, A2P messaging
- **Rate Limit**: 2000 messages/minute

#### 5. Twilio
- **Region**: Global backup
- **Features**: Programmable messaging, webhooks
- **Rate Limit**: 1500 messages/minute

### SMS Providers

#### Regional Optimization
- **GCC**: Unifonic, Infobip
- **North Africa**: OurSMS, Infobip
- **Levant**: Msegat, Infobip
- **Global Backup**: Twilio (via WhatsApp fallback)

## Message Templates

### Template Structure
```json
{
  "id": "auction_bid_placed",
  "name": "Auction Bid Placed",
  "channels": ["whatsapp", "sms", "email"],
  "variables": ["bidder_name", "auction_title", "bid_amount"],
  "templates": {
    "whatsapp": "🎯 New bid placed by {{bidder_name}} on {{auction_title}} for {{bid_amount}}",
    "sms": "New bid: {{bidder_name}} bid {{bid_amount}} on {{auction_title}}",
    "email": {
      "subject": "New Bid on {{auction_title}}",
      "body": "Hello,\n\n{{bidder_name}} has placed a bid of {{bid_amount}} on {{auction_title}}.\n\nBest regards,\nReverse Tender Team"
    }
  }
}
```

### Available Templates
- `auction_created` - New auction notification
- `bid_placed` - Bid placement notification
- `bid_outbid` - Outbid notification
- `auction_won` - Auction winner notification
- `payment_reminder` - Payment reminder
- `order_status_update` - Order status change
- `account_verification` - Account verification code
- `password_reset` - Password reset link

## Integration with Other Services

### Auth Service
- User authentication for notification preferences
- JWT token validation for API access

### Users Service
- User contact information retrieval
- Notification preferences management

### Auctions Service
- Auction event notifications
- Bidding activity alerts

### Orders Service
- Order status notifications
- Delivery updates

### Payments Service
- Payment confirmation messages
- Transaction alerts

## Monitoring & Analytics

### Delivery Metrics
- Success/failure rates by provider
- Delivery time analytics
- Cost optimization tracking
- Regional performance metrics

### Provider Health Monitoring
- Real-time provider status
- Automatic failover triggers
- Rate limit monitoring
- Cost tracking per provider

## Error Handling & Fallback

### Fallback Chain
1. **Primary Provider** (e.g., Unifonic WhatsApp)
2. **Secondary Provider** (e.g., Msegat WhatsApp)
3. **Alternative Channel** (e.g., SMS via Unifonic)
4. **Backup Channel** (e.g., Email)

### Error Types
- **Provider Unavailable**: Switch to next provider
- **Rate Limit Exceeded**: Queue for later or switch provider
- **Invalid Recipient**: Log error and notify sender
- **Template Error**: Use fallback template

## Security Features

- **API Authentication**: JWT token validation
- **Rate Limiting**: Prevent spam and abuse
- **Input Validation**: Sanitize message content
- **Webhook Verification**: Verify provider webhooks
- **Data Encryption**: Encrypt sensitive notification data

## Performance Optimization

- **Queue Management**: Redis-based message queuing
- **Batch Processing**: Efficient bulk message handling
- **Connection Pooling**: Reuse HTTP connections
- **Caching**: Template and configuration caching
- **Async Processing**: Non-blocking message delivery

## Deployment

### Docker
```bash
# Build image
docker build -t notifications:latest .

# Run container
docker run -p 8007:8007 notifications:latest
```

### Production Considerations
- Configure webhook endpoints for delivery status
- Set up monitoring for provider health
- Implement proper logging and alerting
- Configure queue workers for high throughput
- Set up backup providers for redundancy

---

The Notifications service ensures reliable, multi-channel communication across the MENA region with intelligent failover and comprehensive delivery tracking.
