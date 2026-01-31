# 📢 Real-time Notification Architecture Diagram

```mermaid
graph TB
    %% Event Sources
    AUTH[🔐 Auth Service<br/>Login/Register Events]
    ORDER[📋 Order Service<br/>Order Status Changes]
    BIDDING[🎯 Bidding Service<br/>Bid Events]
    PAYMENT[💳 Payment Service<br/>Payment Events]
    USER[👥 User Service<br/>Profile Updates]
    
    %% Message Queue System
    REDIS_QUEUE[📨 Redis Pub/Sub<br/>Message Queue]
    
    %% Notification Service Core
    NOTIFICATION[📢 Notification Service<br/>Laravel + Queue Workers]
    
    %% Notification Channels
    PUSH_WORKER[🔔 Push Notification Worker<br/>FCM/APNS Handler]
    SMS_WORKER[📱 SMS Worker<br/>Twilio/AWS SNS]
    EMAIL_WORKER[📧 Email Worker<br/>SendGrid/SES]
    INAPP_WORKER[📱 In-App Worker<br/>WebSocket/Database]
    
    %% External Providers
    FCM[🔥 Firebase Cloud Messaging<br/>Android Push]
    APNS[🍎 Apple Push Notification<br/>iOS Push]
    TWILIO[📱 Twilio SMS<br/>SMS Provider]
    AWS_SNS[📱 AWS SNS<br/>SMS Provider]
    SENDGRID[📧 SendGrid<br/>Email Provider]
    AWS_SES[📧 AWS SES<br/>Email Provider]
    
    %% WebSocket for Real-time
    WEBSOCKET[🔄 WebSocket Server<br/>Socket.io/Pusher]
    
    %% Client Applications
    PWA_CLIENT[📱 PWA Client<br/>Customer App]
    ADMIN_CLIENT[🖥️ Admin Dashboard<br/>Merchant App]
    MOBILE_CLIENT[📱 Mobile Apps<br/>iOS/Android]
    
    %% Database
    DB[(🗃️ Database<br/>Notifications + Preferences)]
    REDIS_CACHE[(⚡ Redis Cache<br/>Session + Temp Data)]
    
    %% Event Flow
    AUTH --> REDIS_QUEUE
    ORDER --> REDIS_QUEUE
    BIDDING --> REDIS_QUEUE
    PAYMENT --> REDIS_QUEUE
    USER --> REDIS_QUEUE
    
    %% Queue to Notification Service
    REDIS_QUEUE --> NOTIFICATION
    
    %% Notification Service to Workers
    NOTIFICATION --> PUSH_WORKER
    NOTIFICATION --> SMS_WORKER
    NOTIFICATION --> EMAIL_WORKER
    NOTIFICATION --> INAPP_WORKER
    
    %% Workers to External Providers
    PUSH_WORKER --> FCM
    PUSH_WORKER --> APNS
    SMS_WORKER --> TWILIO
    SMS_WORKER --> AWS_SNS
    EMAIL_WORKER --> SENDGRID
    EMAIL_WORKER --> AWS_SES
    
    %% In-App Notifications
    INAPP_WORKER --> WEBSOCKET
    INAPP_WORKER --> DB
    
    %% WebSocket to Clients
    WEBSOCKET --> PWA_CLIENT
    WEBSOCKET --> ADMIN_CLIENT
    WEBSOCKET --> MOBILE_CLIENT
    
    %% Database Connections
    NOTIFICATION --> DB
    NOTIFICATION --> REDIS_CACHE
    
    %% Styling
    classDef service fill:#e8f5e8,stroke:#1b5e20,stroke-width:2px
    classDef worker fill:#e3f2fd,stroke:#0d47a1,stroke-width:2px
    classDef external fill:#fce4ec,stroke:#880e4f,stroke-width:2px
    classDef client fill:#f3e5f5,stroke:#4a148c,stroke-width:2px
    classDef database fill:#fff3e0,stroke:#e65100,stroke-width:2px
    
    class AUTH,ORDER,BIDDING,PAYMENT,USER,NOTIFICATION service
    class PUSH_WORKER,SMS_WORKER,EMAIL_WORKER,INAPP_WORKER worker
    class FCM,APNS,TWILIO,AWS_SNS,SENDGRID,AWS_SES external
    class PWA_CLIENT,ADMIN_CLIENT,MOBILE_CLIENT client
    class DB,REDIS_CACHE,REDIS_QUEUE database
```

## 📢 Notification Event Flow

```mermaid
%%{init: {
  'theme': 'dark',
  'themeVariables': {
    'primaryColor': '#FF4757',
    'primaryTextColor': '#FFFFFF',
    'primaryBorderColor': '#FF6B81',
    'lineColor': '#2ED573',
    'secondaryColor': '#1E90FF',
    'tertiaryColor': '#FFA502',
    'background': '#0F172A',
    'mainBkg': '#1E293B',
    'secondBkg': '#334155',
    'tertiaryBkg': '#1E293B',
    'actorBkg': '#FF4757',
    'actorBorder': '#FF6B81',
    'actorTextColor': '#FFFFFF',
    'activationBkgColor': '#2ED573',
    'activationBorderColor': '#FFFFFF',
    'noteBkgColor': '#FFA502',
    'noteTextColor': '#000000'
  }
}}%%

sequenceDiagram
    participant Service as 🔧 Any Service
    participant Queue as 📨 Redis Queue
    participant NotificationService as 📢 Notification Service
    participant DB as 🗃️ Database
    participant PushWorker as 🔔 Push Worker
    participant SMSWorker as 📱 SMS Worker
    participant EmailWorker as 📧 Email Worker
    participant WebSocketWorker as 🔄 WebSocket Worker
    participant Client as 📱 Client App
    
    Note over Service,Client: Event-Driven Notification Flow
    
    Service->>Queue: Publish event (order_created, bid_received, etc.)
    Queue->>NotificationService: Event received
    
    NotificationService->>DB: Get user notification preferences
    NotificationService->>DB: Get notification template
    NotificationService->>NotificationService: Build notification content
    
    alt Push notification enabled
        NotificationService->>PushWorker: Queue push notification
        PushWorker->>PushWorker: Send to FCM/APNS
        PushWorker->>Client: Push notification delivered
    end
    
    alt SMS notification enabled
        NotificationService->>SMSWorker: Queue SMS notification
        SMSWorker->>SMSWorker: Send via Twilio/AWS SNS
        SMSWorker->>Client: SMS delivered
    end
    
    alt Email notification enabled
        NotificationService->>EmailWorker: Queue email notification
        EmailWorker->>EmailWorker: Send via SendGrid/SES
        EmailWorker->>Client: Email delivered
    end
    
    alt In-app notification enabled
        NotificationService->>WebSocketWorker: Queue in-app notification
        WebSocketWorker->>DB: Save notification record
        WebSocketWorker->>Client: Real-time in-app notification
    end
    
    NotificationService->>DB: Log notification delivery status
```

## 🔔 Notification Types & Templates

### **1. Authentication Events**
```yaml
user_registered:
  title: "Welcome to Reverse Tender! 🎉"
  message: "Your account has been created successfully. Please verify your phone number."
  channels: [sms, email, push]
  
user_verified:
  title: "Account Verified ✅"
  message: "Your account is now verified and ready to use!"
  channels: [push, email]
  
login_success:
  title: "Login Successful 🔐"
  message: "You have successfully logged in from {device} at {time}"
  channels: [push]
  
suspicious_login:
  title: "Suspicious Login Detected ⚠️"
  message: "A login attempt was made from {location}. If this wasn't you, please secure your account."
  channels: [sms, email, push]
```

### **2. Order Management Events**
```yaml
order_created:
  title: "Order Created 📋"
  message: "Your part request '{order_title}' has been created successfully."
  channels: [push, email]
  
order_published:
  title: "Order Published 🚀"
  message: "Your order is now live! Merchants will start bidding soon."
  channels: [push, sms]
  
order_expired:
  title: "Order Expired ⏰"
  message: "Your order '{order_title}' has expired. You can republish it anytime."
  channels: [push, email]
```

### **3. Bidding Events**
```yaml
bid_received:
  title: "New Bid Received! 🎯"
  message: "{merchant_name} placed a bid of {amount} SAR on your order."
  channels: [push, sms]
  
bid_updated:
  title: "Bid Updated 📈"
  message: "{merchant_name} updated their bid to {amount} SAR."
  channels: [push]
  
bid_awarded:
  title: "Congratulations! You Won! 🏆"
  message: "Your bid of {amount} SAR has been selected for '{order_title}'"
  channels: [push, sms, email]
  
bid_rejected:
  title: "Bid Not Selected 😔"
  message: "Your bid for '{order_title}' was not selected. Keep trying!"
  channels: [push]
```

### **4. Payment Events**
```yaml
payment_due:
  title: "Payment Due 💳"
  message: "Payment of {amount} SAR is due for order '{order_title}'"
  channels: [push, sms, email]
  
payment_completed:
  title: "Payment Successful ✅"
  message: "Payment of {amount} SAR has been processed successfully."
  channels: [push, email]
  
invoice_generated:
  title: "ZATCA Invoice Generated 🧾"
  message: "Your ZATCA-compliant invoice is ready for download."
  channels: [email]
```

## 🛠️ Technical Implementation

### **1. Queue Workers**
```php
// Push Notification Worker
class PushNotificationWorker
{
    public function handle(PushNotificationJob $job)
    {
        $notification = $job->notification;
        $user = $job->user;
        
        // Get user's device tokens
        $tokens = $user->deviceTokens()->active()->pluck('token');
        
        foreach ($tokens as $token) {
            if ($user->platform === 'ios') {
                $this->sendAPNS($token, $notification);
            } else {
                $this->sendFCM($token, $notification);
            }
        }
        
        // Log delivery status
        $this->logDeliveryStatus($notification, $tokens);
    }
}

// SMS Worker
class SMSWorker
{
    public function handle(SMSNotificationJob $job)
    {
        $notification = $job->notification;
        $user = $job->user;
        
        // Choose provider based on configuration
        $provider = config('notifications.sms.provider'); // twilio or aws_sns
        
        if ($provider === 'twilio') {
            $this->sendTwilioSMS($user->phone, $notification->message);
        } else {
            $this->sendAWSSNS($user->phone, $notification->message);
        }
        
        // Log delivery
        $this->logSMSDelivery($notification, $user);
    }
}
```

### **2. WebSocket Integration**
```javascript
// Client-side WebSocket connection
const socket = io('wss://api.reversetender.com', {
    auth: {
        token: localStorage.getItem('jwt_token')
    }
});

// Listen for real-time notifications
socket.on('notification', (data) => {
    showInAppNotification(data);
    updateNotificationBadge();
});

// Listen for bid updates
socket.on('bid_update', (data) => {
    updateBidList(data);
    showBidAlert(data);
});

// Listen for order updates
socket.on('order_update', (data) => {
    updateOrderStatus(data);
    refreshOrderDetails();
});
```

### **3. Notification Preferences**
```php
// User notification preferences
class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'notification_type',
        'push_enabled',
        'sms_enabled', 
        'email_enabled',
        'schedule_settings'
    ];
    
    protected $casts = [
        'schedule_settings' => 'array',
        'push_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
        'email_enabled' => 'boolean'
    ];
}

// Schedule settings example
{
    "quiet_hours": {
        "enabled": true,
        "start": "22:00",
        "end": "08:00",
        "timezone": "Asia/Riyadh"
    },
    "weekend_notifications": false,
    "urgent_only": false
}
```

## 📱 Multi-Platform Support

### **Push Notifications**
- **iOS**: Apple Push Notification Service (APNS)
- **Android**: Firebase Cloud Messaging (FCM)
- **Web**: Web Push API with service workers
- **Desktop**: Electron app notifications

### **SMS Integration**
- **Primary**: Twilio for international SMS
- **Backup**: AWS SNS for reliability
- **Local Providers**: Saudi-specific SMS providers
- **Delivery Tracking**: Read receipts and delivery confirmations

### **Email Notifications**
- **Transactional**: SendGrid for system emails
- **Marketing**: Mailchimp for promotional emails
- **Templates**: Responsive HTML email templates
- **Tracking**: Open rates, click tracking, unsubscribe management

## 🎯 Notification Strategies

### **Immediate Notifications**
- New bid received (< 30 seconds)
- Order status changes (< 1 minute)
- Payment confirmations (< 30 seconds)
- Security alerts (< 15 seconds)

### **Batched Notifications**
- Daily order summaries
- Weekly performance reports
- Monthly analytics reports
- Promotional campaigns

### **Smart Delivery**
- **Quiet Hours**: Respect user sleep schedules
- **Frequency Limits**: Prevent notification spam
- **Priority Levels**: Critical vs. informational
- **Delivery Optimization**: Best channel selection

This notification architecture ensures users stay informed about all important events while respecting their preferences and providing a seamless real-time experience.

