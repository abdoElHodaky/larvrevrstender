# Enhanced Shared Notification Procedures

## Overview

The Enhanced Shared Notification Procedures provide a comprehensive, unified notification system for all microservices in the platform. This system leverages the existing shared procedures infrastructure to deliver notifications across multiple channels with advanced features like scheduling, bulk processing, and multi-channel fallback.

## Key Features

### 🚀 **Multi-Channel Support**
- **Email** - Rich HTML emails with templates
- **SMS** - Text messages with MENA provider support
- **Push Notifications** - Web and mobile push notifications
- **WhatsApp** - WhatsApp Business API integration
- **Telegram** - Telegram Bot API integration
- **Multi-Channel** - Send across multiple channels simultaneously

### 📋 **Template Management**
- Centralized template system with service-specific customization
- Multi-language support (English, Arabic, French)
- Template inheritance and versioning
- A/B testing capabilities

### ⚡ **Advanced Processing**
- Queue-based async processing
- Retry mechanisms with exponential backoff
- Circuit breaker integration for fault tolerance
- Bulk notification processing
- Scheduled notifications

### 📊 **Analytics & Monitoring**
- Delivery tracking and status monitoring
- Performance metrics and analytics
- Error tracking and alerting
- User engagement metrics

## Quick Start

### Basic Usage

```php
use Shared\Procedures\Micro\NotificationProcedure;

class MyService {
    use NotificationProcedure;
    
    public function sendWelcomeEmail($user) {
        return $this->sendEmail([
            'to' => $user->email,
            'subject' => 'Welcome to our platform!',
            'template' => 'welcome',
            'data' => [
                'name' => $user->name,
                'activation_link' => $user->activation_link
            ]
        ]);
    }
}
```

### Service-Specific Traits

```php
use Shared\Procedures\Micro\NotificationProcedure;
use Shared\Traits\OrderNotificationTrait;

class OrderService {
    use NotificationProcedure, OrderNotificationTrait;
    
    public function createOrder($data) {
        $order = Order::create($data);
        
        // Send order created notification using the trait
        $this->sendOrderCreatedNotification($order, [
            'channels' => ['email', 'push', 'sms']
        ]);
        
        return $order;
    }
}
```

## Available Methods

### Core Notification Methods

#### `sendEmail(array $params)`
Send email notifications with template support.

```php
$result = $this->sendEmail([
    'to' => 'user@example.com',
    'subject' => 'Order Confirmation',
    'template' => 'order.created',
    'data' => [
        'order_number' => '12345',
        'customer_name' => 'John Doe',
        'total_amount' => '$99.99'
    ],
    'priority' => 'high'
]);
```

#### `sendSms(array $params)`
Send SMS notifications with MENA provider support.

```php
$result = $this->sendSms([
    'to' => '+1234567890',
    'message' => 'Your order #12345 has been confirmed!',
    'template' => 'order.created',
    'data' => ['order_number' => '12345']
]);
```

#### `sendPushNotification(array $params)`
Send push notifications to mobile devices.

```php
$result = $this->sendPushNotification([
    'device_tokens' => ['token1', 'token2'],
    'title' => 'Order Update',
    'body' => 'Your order has been shipped!',
    'data' => ['order_id' => 123],
    'badge' => 1
]);
```

#### `sendWhatsApp(array $params)`
Send WhatsApp messages via Business API.

```php
$result = $this->sendWhatsApp([
    'to' => '+1234567890',
    'message' => 'Your order is ready for pickup!',
    'template' => 'order.ready',
    'data' => ['order_number' => '12345'],
    'media_url' => 'https://example.com/qr-code.png'
]);
```

#### `sendTelegram(array $params)`
Send Telegram messages via Bot API.

```php
$result = $this->sendTelegram([
    'chat_id' => '123456789',
    'message' => 'Your auction bid was successful!',
    'template' => 'bid.success',
    'data' => ['auction_title' => 'Vintage Watch'],
    'parse_mode' => 'HTML'
]);
```

### Advanced Methods

#### `sendMultiChannel(array $params)`
Send notifications across multiple channels with fallback support.

```php
$result = $this->sendMultiChannel([
    'channels' => ['email', 'push', 'sms'],
    'recipient' => [
        'email' => 'user@example.com',
        'device_tokens' => ['push_token'],
        'phone' => '+1234567890'
    ],
    'template' => 'order.created',
    'data' => ['order_number' => '12345'],
    'priority' => 'high',
    'fallback_order' => ['email', 'sms', 'push']
]);
```

#### `sendBulkNotification(array $params)`
Send notifications to multiple recipients efficiently.

```php
$result = $this->sendBulkNotification([
    'recipients' => [
        ['email' => 'user1@example.com'],
        ['email' => 'user2@example.com'],
        ['email' => 'user3@example.com']
    ],
    'channel' => 'email',
    'template' => 'newsletter.weekly',
    'data' => ['week' => '2024-W08'],
    'batch_size' => 100
]);
```

#### `scheduleNotification(array $params)`
Schedule notifications for future delivery.

```php
$result = $this->scheduleNotification([
    'scheduled_at' => '2024-02-15 10:00:00',
    'channel' => 'email',
    'recipient' => ['email' => 'user@example.com'],
    'template' => 'appointment.reminder',
    'data' => ['appointment_time' => '2024-02-15 14:00:00'],
    'timezone' => 'America/New_York'
]);
```

#### `cancelNotification(array $params)`
Cancel scheduled notifications.

```php
$result = $this->cancelNotification([
    'notification_id' => 'scheduled_abc123'
]);
```

#### `getNotificationStatus(array $params)`
Check notification delivery status.

```php
$result = $this->getNotificationStatus([
    'notification_id' => 'email_abc123'
]);
```

#### `manageSubscriptions(array $params)`
Manage user notification preferences.

```php
$result = $this->manageSubscriptions([
    'user_id' => 123,
    'action' => 'update',
    'preferences' => [
        'email_frequency' => 'daily',
        'marketing' => false,
        'order_updates' => true
    ]
]);
```

## Service-Specific Traits

### OrderNotificationTrait

Provides order-specific notification methods:

- `sendOrderCreatedNotification($order, $options = [])`
- `sendOrderStatusUpdateNotification($order, $newStatus, $options = [])`
- `sendOrderCompletedNotification($order, $options = [])`
- `sendOrderCancelledNotification($order, $reason = '', $options = [])`
- `sendBulkOrderNotifications($orders, $template, $channel = 'email', $options = [])`
- `scheduleOrderReminderNotification($order, $scheduledAt, $reminderType = 'payment', $options = [])`

### Usage Example

```php
use Shared\Procedures\Micro\NotificationProcedure;
use Shared\Traits\OrderNotificationTrait;

class OrderService {
    use NotificationProcedure, OrderNotificationTrait;
    
    public function updateOrderStatus($orderId, $newStatus) {
        $order = Order::find($orderId);
        $order->update(['status' => $newStatus]);
        
        // Automatically sends appropriate notification based on status
        $this->sendOrderStatusUpdateNotification($order, $newStatus);
        
        return $order;
    }
}
```

## Templates

### Built-in Templates

The system includes pre-built templates for common scenarios:

#### Email Templates
- `order.created` - Order creation confirmation
- `auction.created` - Auction creation notification
- `bid.placed` - New bid notification
- `payment.success` - Payment confirmation

#### SMS Templates
- `order.created` - Order creation SMS
- `auction.ending` - Auction ending reminder
- `payment.failed` - Payment failure alert

#### WhatsApp Templates
- `order.created` - Rich order confirmation with emojis
- `auction.created` - Auction creation with details
- `bid.placed` - Bid notification with auction info

#### Telegram Templates
- `order.created` - HTML-formatted order confirmation
- `auction.created` - Rich auction notification
- `payment.success` - Payment confirmation with details

### Template Variables

Templates support variable substitution using `{{variable_name}}` syntax:

```php
// Template: "Hi {{customer_name}}, your order #{{order_number}} is {{status}}"
// Data: ['customer_name' => 'John', 'order_number' => '12345', 'status' => 'confirmed']
// Result: "Hi John, your order #12345 is confirmed"
```

## Configuration

### Service Configuration

Add notification service configuration to your service's `config/services.php`:

```php
return [
    'notification_service' => [
        'url' => env('NOTIFICATION_SERVICE_URL', 'http://notification-service:8000'),
    ],
];
```

### Environment Variables

```env
NOTIFICATION_SERVICE_URL=http://notification-service:8000
```

## Error Handling

All notification methods return a standardized response format:

### Success Response
```php
[
    'success' => true,
    'data' => [
        'notification_id' => 'email_abc123',
        'type' => 'email',
        'status' => 'sent',
        'sent_at' => '2024-02-13T10:30:00Z'
    ],
    'message' => 'Email sent successfully'
]
```

### Error Response
```php
[
    'success' => false,
    'error' => 'Validation failed',
    'details' => [
        'to' => ['The to field is required']
    ]
]
```

## Best Practices

### 1. Use Service-Specific Traits
Create domain-specific notification methods using traits to encapsulate business logic.

### 2. Handle Failures Gracefully
Always check the response and handle failures appropriately:

```php
$result = $this->sendEmail($params);
if (!$result['success']) {
    Log::error('Email sending failed', $result);
    // Handle failure (retry, fallback, etc.)
}
```

### 3. Use Multi-Channel for Critical Notifications
For important notifications, use multi-channel delivery with fallback:

```php
$this->sendMultiChannel([
    'channels' => ['email', 'sms', 'push'],
    'fallback_order' => ['email', 'sms', 'push'],
    // ... other params
]);
```

### 4. Batch Processing for Bulk Operations
Use bulk notifications for sending to multiple recipients:

```php
$this->sendBulkNotification([
    'recipients' => $users,
    'channel' => 'email',
    'template' => 'newsletter',
    'batch_size' => 100
]);
```

### 5. Schedule Non-Urgent Notifications
Use scheduling for reminders and non-urgent notifications:

```php
$this->scheduleNotification([
    'scheduled_at' => now()->addDays(7)->toISOString(),
    'channel' => 'email',
    'template' => 'review_reminder'
]);
```

## Migration Guide

### From Direct HTTP Calls

**Before:**
```php
class OrderService {
    private $notificationClient;
    
    public function createOrder($data) {
        $order = Order::create($data);
        
        $this->notificationClient->post('/notifications/email', [
            'to' => $order->customer_email,
            'subject' => 'Order Created',
            'template' => 'order_created',
            'data' => $order->toArray()
        ]);
    }
}
```

**After:**
```php
use Shared\Procedures\Micro\NotificationProcedure;
use Shared\Traits\OrderNotificationTrait;

class OrderService {
    use NotificationProcedure, OrderNotificationTrait;
    
    public function createOrder($data) {
        $order = Order::create($data);
        
        $this->sendOrderCreatedNotification($order);
    }
}
```

### Migration Steps

1. **Add use statements** to your service class
2. **Replace direct HTTP calls** with shared procedure methods
3. **Use service-specific traits** for domain logic
4. **Test thoroughly** before removing old code
5. **Remove old notification clients** after migration

## Performance Considerations

### Async Processing
- Use queue-based processing for non-critical notifications
- Implement retry mechanisms for failed deliveries
- Use circuit breakers to handle service failures

### Bulk Operations
- Use bulk methods for sending to multiple recipients
- Implement batching to prevent overwhelming external services
- Add delays between batches when necessary

### Caching
- Cache user preferences and subscription data
- Cache template content for better performance
- Use Redis for notification status tracking

## Monitoring and Analytics

### Metrics Tracked
- Delivery success/failure rates by channel
- Processing latency and throughput
- User engagement metrics (open rates, click rates)
- Cost tracking by channel and volume

### Logging
All notification activities are logged with structured data for monitoring and debugging.

### Alerting
Set up alerts for:
- High failure rates
- Processing delays
- Service unavailability
- Unusual traffic patterns

## Support and Troubleshooting

### Common Issues

1. **Template not found**: Ensure template exists in the template system
2. **Invalid recipient data**: Validate email addresses and phone numbers
3. **Rate limiting**: Implement proper delays and retry mechanisms
4. **Service unavailable**: Use circuit breakers and fallback channels

### Debug Mode
Enable debug logging to troubleshoot notification issues:

```php
$result = $this->sendEmail($params, ['debug' => true]);
```

### Getting Help
- Check the logs for detailed error messages
- Use the notification status endpoint to track delivery
- Review the template system for formatting issues
- Contact the platform team for infrastructure issues

---

## Next Steps

1. **Implement additional service-specific traits** (AuctionNotificationTrait, UserNotificationTrait, etc.)
2. **Add template management UI** for non-technical users
3. **Implement A/B testing framework** for notification optimization
4. **Add real-time analytics dashboard** for monitoring
5. **Integrate with external analytics platforms** for advanced insights

This enhanced notification system provides a solid foundation for scalable, reliable, and feature-rich notification delivery across your microservices platform.
