# Notification Delegation Interface API Documentation

## Overview

The Notification Delegation Interface provides a clean, protocol-agnostic API for notification operations. This interface acts as a communication bridge between the shared service and notification service, implementing a pure delegation pattern with comprehensive error handling and distributed tracing support.

## Interface Definition

### Base Interface

All notification methods follow a consistent signature pattern:

```php
public function methodName(array $params, array $context = []): array
```

**Parameters**:
- `$params` (array): Method-specific parameters for the notification operation
- `$context` (array, optional): Request context including trace information and metadata

**Returns**:
- `array`: Standardized response structure with success status and data/error information

## Available Methods

### 1. Email Notifications

#### `sendEmail(array $params, array $context = []): array`

Send email notifications through the notification service.

**Parameters (`$params`)**:
```php
[
    'to' => 'recipient@example.com',           // Required: Recipient email
    'subject' => 'Email Subject',              // Required: Email subject
    'template' => 'welcome_email',             // Required: Template identifier
    'data' => [                                // Optional: Template data
        'name' => 'John Doe',
        'verification_url' => 'https://...'
    ],
    'from' => 'sender@example.com',            // Optional: Sender email
    'reply_to' => 'noreply@example.com',       // Optional: Reply-to address
    'attachments' => [                         // Optional: File attachments
        ['path' => '/path/to/file.pdf', 'name' => 'document.pdf']
    ]
]
```

**Response Structure**:
```php
// Success Response
[
    'success' => true,
    'data' => [
        'message_id' => 'msg_123456789',
        'status' => 'sent',
        'timestamp' => '2026-02-14T11:00:00+00:00'
    ]
]

// Error Response
[
    'success' => false,
    'message' => 'Email delivery failed',
    'details' => [
        'error_code' => 'SMTP_ERROR',
        'error_message' => 'Connection timeout'
    ],
    'timestamp' => '2026-02-14T11:00:00+00:00'
]
```

### 2. SMS Notifications

#### `sendSms(array $params, array $context = []): array`

Send SMS notifications through the notification service.

**Parameters (`$params`)**:
```php
[
    'to' => '+1234567890',                     // Required: Recipient phone number
    'message' => 'Your verification code is 123456', // Required: SMS content
    'template' => 'verification_sms',          // Optional: Template identifier
    'data' => [                                // Optional: Template data
        'code' => '123456',
        'expires_in' => '10 minutes'
    ],
    'priority' => 'high',                      // Optional: Priority level
    'sender_id' => 'YourApp'                   // Optional: Sender identifier
]
```

### 3. Push Notifications

#### `sendPushNotification(array $params, array $context = []): array`

Send push notifications to mobile devices.

**Parameters (`$params`)**:
```php
[
    'device_tokens' => ['token1', 'token2'],   // Required: Device tokens
    'title' => 'Notification Title',           // Required: Notification title
    'body' => 'Notification message',          // Required: Notification body
    'data' => [                                // Optional: Custom data payload
        'action' => 'open_screen',
        'screen_id' => 'profile'
    ],
    'badge' => 1,                              // Optional: Badge count
    'sound' => 'default',                      // Optional: Sound file
    'platform' => 'ios'                       // Optional: Target platform
]
```

### 4. Notification Status

#### `getNotificationStatus(array $params, array $context = []): array`

Retrieve the status of sent notifications.

**Parameters (`$params`)**:
```php
[
    'message_id' => 'msg_123456789',           // Required: Message identifier
    'type' => 'email'                          // Optional: Notification type filter
]
```

**Response Structure**:
```php
[
    'success' => true,
    'data' => [
        'message_id' => 'msg_123456789',
        'status' => 'delivered',               // sent, delivered, failed, pending
        'sent_at' => '2026-02-14T11:00:00+00:00',
        'delivered_at' => '2026-02-14T11:01:30+00:00',
        'attempts' => 1,
        'channel' => 'email',
        'recipient' => 'user@example.com'
    ]
]
```

### 5. Subscription Management

#### `manageSubscriptions(array $params, array $context = []): array`

Manage notification subscriptions and preferences.

**Parameters (`$params`)**:
```php
[
    'user_id' => 'user_123',                   // Required: User identifier
    'action' => 'subscribe',                   // Required: subscribe, unsubscribe, update
    'channels' => ['email', 'sms', 'push'],   // Required: Notification channels
    'preferences' => [                         // Optional: Preference settings
        'frequency' => 'daily',
        'categories' => ['marketing', 'security']
    ]
]
```

### 6. WhatsApp Notifications

#### `sendWhatsApp(array $params, array $context = []): array`

Send WhatsApp messages through the notification service.

**Parameters (`$params`)**:
```php
[
    'to' => '+1234567890',                     // Required: Recipient phone number
    'template' => 'order_confirmation',        // Required: WhatsApp template name
    'language' => 'en',                        // Required: Template language
    'parameters' => [                          // Required: Template parameters
        ['type' => 'text', 'text' => 'John'],
        ['type' => 'text', 'text' => 'ORD123']
    ],
    'media' => [                               // Optional: Media attachments
        'type' => 'image',
        'url' => 'https://example.com/image.jpg'
    ]
]
```

### 7. Telegram Notifications

#### `sendTelegram(array $params, array $context = []): array`

Send Telegram messages through the notification service.

**Parameters (`$params`)**:
```php
[
    'chat_id' => '123456789',                  // Required: Telegram chat ID
    'message' => 'Hello from our app!',       // Required: Message content
    'parse_mode' => 'Markdown',               // Optional: Message formatting
    'reply_markup' => [                       // Optional: Inline keyboard
        'inline_keyboard' => [
            [['text' => 'Visit Website', 'url' => 'https://example.com']]
        ]
    ],
    'disable_notification' => false           // Optional: Silent notification
]
```

### 8. Multi-Channel Notifications

#### `sendMultiChannel(array $params, array $context = []): array`

Send notifications across multiple channels simultaneously.

**Parameters (`$params`)**:
```php
[
    'user_id' => 'user_123',                   // Required: Target user
    'channels' => ['email', 'sms', 'push'],   // Required: Channels to use
    'template' => 'order_confirmation',        // Required: Base template
    'data' => [                                // Required: Template data
        'order_id' => 'ORD123',
        'amount' => '$99.99'
    ],
    'priority' => 'high',                      // Optional: Priority level
    'fallback_strategy' => 'sequential'       // Optional: Fallback behavior
]
```

### 9. Bulk Notifications

#### `sendBulkNotification(array $params, array $context = []): array`

Send notifications to multiple recipients efficiently.

**Parameters (`$params`)**:
```php
[
    'recipients' => [                          // Required: Recipient list
        ['type' => 'email', 'address' => 'user1@example.com', 'data' => ['name' => 'John']],
        ['type' => 'sms', 'address' => '+1234567890', 'data' => ['name' => 'Jane']]
    ],
    'template' => 'newsletter',                // Required: Template identifier
    'batch_size' => 100,                       // Optional: Processing batch size
    'delay' => 1000,                           // Optional: Delay between batches (ms)
    'priority' => 'normal'                     // Optional: Processing priority
]
```

**Response Structure**:
```php
[
    'success' => true,
    'data' => [
        'batch_id' => 'batch_123456789',
        'total_recipients' => 1000,
        'queued' => 1000,
        'estimated_completion' => '2026-02-14T12:00:00+00:00'
    ]
]
```

### 10. Scheduled Notifications

#### `scheduleNotification(array $params, array $context = []): array`

Schedule notifications for future delivery.

**Parameters (`$params`)**:
```php
[
    'notification' => [                        // Required: Notification details
        'type' => 'email',
        'to' => 'user@example.com',
        'template' => 'reminder',
        'data' => ['event' => 'Meeting Tomorrow']
    ],
    'schedule' => [                            // Required: Schedule configuration
        'send_at' => '2026-02-15T09:00:00+00:00', // Specific time
        // OR
        'delay' => 3600,                       // Delay in seconds
        // OR
        'cron' => '0 9 * * 1'                 // Cron expression for recurring
    ],
    'timezone' => 'America/New_York'           // Optional: Timezone for scheduling
]
```

### 11. Cancel Notifications

#### `cancelNotification(array $params, array $context = []): array`

Cancel scheduled or queued notifications.

**Parameters (`$params`)**:
```php
[
    'message_id' => 'msg_123456789',           // Required: Message identifier
    'reason' => 'User requested cancellation' // Optional: Cancellation reason
]
```

## Context Parameters

The `$context` parameter provides additional metadata and tracing information:

```php
[
    'trace_id' => 'trace_123456789',           // Optional: Distributed trace ID
    'user_id' => 'user_123',                   // Optional: Requesting user
    'request_id' => 'req_987654321',           // Optional: Request identifier
    'source' => 'web_app',                     // Optional: Request source
    'priority' => 'high',                      // Optional: Processing priority
    'metadata' => [                            // Optional: Additional metadata
        'campaign_id' => 'camp_123',
        'ab_test_variant' => 'A'
    ]
]
```

## Response Standards

### Success Response Format

```php
[
    'success' => true,
    'data' => [
        // Method-specific response data
    ],
    'metadata' => [                            // Optional: Response metadata
        'processing_time' => 150,              // Processing time in milliseconds
        'service_version' => '1.2.3',
        'trace_id' => 'trace_123456789'
    ]
]
```

### Error Response Format

```php
[
    'success' => false,
    'message' => 'Human-readable error message',
    'error_code' => 'ERROR_CODE',              // Machine-readable error code
    'details' => [                             // Additional error context
        'field' => 'validation_error_details',
        'method' => 'sendEmail',
        'service' => 'notification-service'
    ],
    'timestamp' => '2026-02-14T11:00:00+00:00',
    'trace_id' => 'trace_123456789'            // For debugging correlation
]
```

## Error Codes

### Communication Errors
- `RPC_CONNECTION_FAILED`: Unable to connect to notification service
- `RPC_TIMEOUT`: Request timeout during service communication
- `RPC_INVALID_RESPONSE`: Invalid response format from service

### Validation Errors
- `INVALID_PARAMETERS`: Required parameters missing or invalid
- `INVALID_EMAIL_ADDRESS`: Email address format validation failed
- `INVALID_PHONE_NUMBER`: Phone number format validation failed
- `TEMPLATE_NOT_FOUND`: Specified template does not exist

### Service Errors
- `SERVICE_UNAVAILABLE`: Notification service temporarily unavailable
- `RATE_LIMIT_EXCEEDED`: Request rate limit exceeded
- `QUOTA_EXCEEDED`: Notification quota exceeded

### Channel-Specific Errors
- `SMTP_ERROR`: Email delivery service error
- `SMS_PROVIDER_ERROR`: SMS provider service error
- `PUSH_TOKEN_INVALID`: Invalid push notification token
- `WHATSAPP_TEMPLATE_ERROR`: WhatsApp template validation error

## Usage Examples

### Basic Email Notification

```php
use Shared\Procedures\CrossServiceProcedure;

$procedure = new CrossServiceProcedure();

$result = $procedure->sendEmail([
    'to' => 'user@example.com',
    'subject' => 'Welcome to Our Platform',
    'template' => 'welcome_email',
    'data' => [
        'name' => 'John Doe',
        'verification_url' => 'https://app.example.com/verify/abc123'
    ]
], [
    'trace_id' => 'trace_' . uniqid(),
    'user_id' => 'user_123'
]);

if ($result['success']) {
    echo "Email sent successfully: " . $result['data']['message_id'];
} else {
    echo "Email failed: " . $result['message'];
}
```

### Multi-Channel Notification with Error Handling

```php
$result = $procedure->sendMultiChannel([
    'user_id' => 'user_123',
    'channels' => ['email', 'sms', 'push'],
    'template' => 'order_confirmation',
    'data' => [
        'order_id' => 'ORD123',
        'amount' => '$99.99',
        'delivery_date' => '2026-02-16'
    ]
], [
    'trace_id' => 'order_notification_' . uniqid(),
    'priority' => 'high'
]);

if ($result['success']) {
    $channels = $result['data']['channels_sent'];
    echo "Notification sent via: " . implode(', ', $channels);
} else {
    // Handle specific error types
    switch ($result['error_code']) {
        case 'SERVICE_UNAVAILABLE':
            // Retry logic or fallback
            break;
        case 'INVALID_PARAMETERS':
            // Log validation error
            break;
        default:
            // General error handling
            break;
    }
}
```

### Bulk Notification with Status Tracking

```php
// Send bulk notification
$bulkResult = $procedure->sendBulkNotification([
    'recipients' => [
        ['type' => 'email', 'address' => 'user1@example.com', 'data' => ['name' => 'John']],
        ['type' => 'email', 'address' => 'user2@example.com', 'data' => ['name' => 'Jane']],
        // ... more recipients
    ],
    'template' => 'newsletter',
    'batch_size' => 50
]);

if ($bulkResult['success']) {
    $batchId = $bulkResult['data']['batch_id'];
    
    // Check status periodically
    $statusResult = $procedure->getNotificationStatus([
        'batch_id' => $batchId
    ]);
    
    if ($statusResult['success']) {
        $status = $statusResult['data']['status'];
        $progress = $statusResult['data']['progress'];
        echo "Batch {$batchId} status: {$status} ({$progress}% complete)";
    }
}
```

## Best Practices

### Error Handling
1. Always check the `success` field in responses
2. Implement retry logic for transient errors
3. Log error details with trace IDs for debugging
4. Use appropriate fallback strategies for critical notifications

### Performance Optimization
1. Use bulk operations for multiple notifications
2. Include correlation context for distributed tracing
3. Implement client-side caching for template metadata
4. Monitor RPC call latency and success rates

### Security Considerations
1. Validate all input parameters before delegation
2. Include user context for audit logging
3. Use secure communication channels for sensitive data
4. Implement rate limiting for notification operations

### Monitoring and Debugging
1. Include trace IDs in all requests for correlation
2. Monitor success rates and error patterns
3. Set up alerts for service communication failures
4. Use structured logging for better observability

---

**Document Version**: 1.0  
**Last Updated**: February 14, 2026  
**Authors**: Codegen AI, AbdElrhman ElHodaky  
**Status**: Complete - Ready for implementation
