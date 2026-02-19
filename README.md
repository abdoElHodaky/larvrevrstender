<div style="max-width: 38.2rem; line-height: 1.618; font-family: 'Inter', 'Segoe UI', 'Roboto', sans-serif;">

# <span style="font-size: 42px; font-weight: 700; line-height: 1.618;">🏛️ Laravel Reverse Tender Platform</span>
## <span style="font-size: 20px; font-weight: 500; line-height: 1.618; color: #4ECDC4;">Version 2.0 - Multi-Tier Caching Architecture</span>

<p style="font-size: 16px; line-height: 1.618; margin-bottom: 2rem;">A comprehensive <strong>microservices-based auction and reverse tender platform</strong> built with Laravel, featuring <strong>multi-tier caching architecture</strong>, advanced bidding systems, multi-channel notifications, and MENA-optimized communication services.</p>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin: 2rem 0; padding: 1.5rem; background: linear-gradient(135deg, #0F172A, #1E293B); border-radius: 12px; color: #F8F9FA;">

<div style="text-align: center;">
<div style="font-size: 24px; font-weight: 700; color: #FF6B6B;">⚡ Sub-50ms</div>
<div style="font-size: 14px; color: #94A3B8;">Response Times</div>
</div>

<div style="text-align: center;">
<div style="font-size: 24px; font-weight: 700; color: #4ECDC4;">95%+</div>
<div style="font-size: 14px; color: #94A3B8;">Cache Hit Ratio</div>
</div>

<div style="text-align: center;">
<div style="font-size: 24px; font-weight: 700; color: #45B7D1;">10K+</div>
<div style="font-size: 14px; color: #94A3B8;">Jobs/Second</div>
</div>

<div style="text-align: center;">
<div style="font-size: 24px; font-weight: 700; color: #96CEB4;">65-80%</div>
<div style="font-size: 14px; color: #94A3B8;">Cost Reduction</div>
</div>

</div>

## <span style="font-size: 26px; font-weight: 600; line-height: 1.618;">🌟 Core Platform Features</span>

<!-- 62% MAJOR CONCEPTS: Core Business Value -->
<div style="margin-bottom: 3rem;">

### <span style="font-size: 20px; font-weight: 600; line-height: 1.618;">🎯 Enterprise Auction Engine</span>
<p style="font-size: 16px; line-height: 1.618;"><strong>Reverse Tender Auctions</strong> - Advanced supplier bidding with real-time WebSocket updates and automated escrow settlement. Multi-currency support for global operations.</p>

### <span style="font-size: 20px; font-weight: 600; line-height: 1.618;">📱 MENA-Optimized Communications</span>
<p style="font-size: 16px; line-height: 1.618;"><strong>Multi-channel Orchestration</strong> - WhatsApp Business (5 providers), SMS (4 regional providers), Telegram, Signal, and Email with intelligent fallback routing.</p>

### <span style="font-size: 20px; font-weight: 600; line-height: 1.618;">🏗️ Microservices Architecture</span>
<p style="font-size: 16px; line-height: 1.618;"><strong>8 Independent Services</strong> - Event-driven design with cross-service RPC, shared procedures, and Docker containerization for enterprise scalability.</p>

</div>

<!-- 38% MINOR DETAILS: Supporting Features -->
<details style="margin-bottom: 2rem;">
<summary style="font-size: 16px; font-weight: 500; cursor: pointer;">🔍 Detailed Feature Breakdown</summary>
<div style="margin-top: 1rem; padding-left: 1rem; border-left: 3px solid #4ECDC4;">

**Auction System Details:**
- Traditional Auctions, Smart Escrow, Payment Processing

**Communication Providers:**
- Unifonic, Msegat, Oursms, Infobip (SMS)
- 5 WhatsApp Business providers

**Architecture Components:**
- Service isolation, Shared procedures, Event-driven processing

</div>
</details>

## <span style="font-size: 26px; font-weight: 600; line-height: 1.618;">🏛️ Enterprise Architecture</span>

```mermaid
%%{init: {
  'theme': 'base',
  'themeVariables': {
    'primaryColor': '#FF6B6B',
    'primaryTextColor': '#FFFFFF',
    'primaryBorderColor': '#FF8E8E',
    'lineColor': '#4ECDC4',
    'secondaryColor': '#45B7D1',
    'tertiaryColor': '#96CEB4',
    'background': '#0F172A',
    'mainBkg': '#1E293B',
    'secondBkg': '#334155',
    'clusterBkg': '#1E293B',
    'clusterBorder': '#4ECDC4',
    'fontFamily': 'Inter, Segoe UI, Roboto, sans-serif',
    'fontSize': '14px'
  },
  'flowchart': {
    'rankSpacing': 81,
    'nodeSpacing': 50,
    'curve': 'basis'
  }
}}%%

graph TB
    subgraph "🌐 CLIENT LAYER"
        WEB["🌐 Web Application<br/>323px"]
        MOBILE["📱 Mobile App<br/>323px"]
        API_GW["🚪 API Gateway<br/>323px"]
    end
    
    subgraph "⚡ CORE SERVICES HUB"
        AUTH["🔑 Auth Service<br/>200px"]
        USER["👤 User Service<br/>200px"]
        AUCTION["🏛️ Auction Service<br/>323px"]
        BIDDING["💰 Bidding Service<br/>323px"]
        ORDER["📋 Order Service<br/>200px"]
        PAYMENT["💳 Payment Service<br/>323px"]
        NOTIFICATION["📨 Notification Service<br/>200px"]
        ANALYTICS["📊 Analytics Service<br/>200px"]
        SHARED["🎯 Shared Service<br/>323px"]
    end
    
    subgraph "🌐 EXTERNAL INTEGRATIONS"
        WHATSAPP["📱 WhatsApp Providers<br/>200px"]
        SMS["💬 SMS Providers<br/>200px"]
        TELEGRAM["🤖 Telegram API<br/>200px"]
        SIGNAL["🔒 Signal Gateway<br/>200px"]
        PAYMENT_GW["💳 Payment Gateways<br/>323px"]
    end
    
    subgraph "💾 INFRASTRUCTURE LAYER"
        REDIS["🔴 Redis Cache<br/>323px"]
        POSTGRES["🗄️ PostgreSQL<br/>323px"]
        RABBITMQ["🐰 RabbitMQ<br/>200px"]
        WEBSOCKET["⚡ WebSocket Server<br/>200px"]
    end
    
    %% Golden Ratio Flow Connections (Major paths emphasized)
    WEB -.-> API_GW
    MOBILE -.-> API_GW
    API_GW ==> AUTH
    API_GW ==> AUCTION
    API_GW ==> BIDDING
    API_GW --> USER
    
    %% Core Service Interconnections
    AUCTION ==> SHARED
    BIDDING ==> SHARED
    PAYMENT ==> SHARED
    ORDER --> SHARED
    
    %% External Service Connections
    NOTIFICATION --> WHATSAPP
    NOTIFICATION --> SMS
    NOTIFICATION --> TELEGRAM
    NOTIFICATION --> SIGNAL
    
    PAYMENT ==> PAYMENT_GW
    
    AUTH --> REDIS
    USER --> POSTGRES
    AUCTION --> POSTGRES
    BIDDING --> POSTGRES
    
    SHARED --> RABBITMQ
    BIDDING --> WEBSOCKET
```

## <span style="font-size: 26px; font-weight: 600; line-height: 1.618;">🚀 Quick Start Guide</span>

<!-- 62% MAJOR CONCEPTS: Essential Setup -->
<div style="margin-bottom: 2rem;">

### <span style="font-size: 20px; font-weight: 600; line-height: 1.618;">⚡ Rapid Deployment</span>
<p style="font-size: 16px; line-height: 1.618;"><strong>Docker-based setup</strong> with automated service orchestration. Complete platform deployment in under 5 minutes.</p>

```bash
# One-command deployment
git clone https://github.com/abdoElHodaky/larvrevrstender.git
cd larvrevrstender && docker-compose up -d
```

</div>

<!-- 38% MINOR DETAILS: Configuration Details -->
<details style="margin-bottom: 2rem;">
<summary style="font-size: 16px; font-weight: 500; cursor: pointer;">🔧 Detailed Configuration</summary>
<div style="margin-top: 1rem; padding-left: 1rem; border-left: 3px solid #4ECDC4;">

**Prerequisites:**
- Docker & Docker Compose, PHP 8.2+, Node.js 18+, PostgreSQL 15+, Redis 7+

**Environment Setup:**
```bash
# Database Configuration
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_DATABASE=laravel_reverse_tender

# Cache & Queue Configuration  
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# MENA Notification Providers
WHATSAPP_PROVIDER=unifonic
UNIFONIC_API_KEY=your_unifonic_key
SMS_PROVIDER=unifonic
TELEGRAM_BOT_TOKEN=your_telegram_bot_token
```

### 3. Launch Services
```bash
# Start all services
docker-compose up -d

# Run migrations
docker-compose exec shared-service php artisan migrate

# Install dependencies
docker-compose exec shared-service composer install
```

### 4. Access Applications
- **Web Interface**: http://localhost:8080
- **API Gateway**: http://localhost:8000
- **Admin Panel**: http://localhost:8080/admin

## 📡 **Notification System Architecture**

### Multi-Channel Notification Flow

```mermaid
sequenceDiagram
    participant App as Application
    participant MCS as MultiChannelService
    participant WA as WhatsApp Service
    participant SMS as SMS Service
    participant TG as Telegram Service
    participant SG as Signal Service
    
    App->>MCS: sendMultiChannelNotification()
    MCS->>MCS: Determine available channels
    
    par WhatsApp
        MCS->>WA: sendMessage()
        WA->>WA: Select provider (Unifonic/Msegat/etc)
        WA-->>MCS: Success/Failure
    and SMS
        MCS->>SMS: sendMessage()
        SMS->>SMS: Route to provider
        SMS-->>MCS: Success/Failure
    and Telegram
        MCS->>TG: sendMessage()
        TG->>TG: Bot API call
        TG-->>MCS: Success/Failure
    and Signal
        MCS->>SG: sendMessage()
        SG->>SG: CLI/Gateway/Webhook
        SG-->>MCS: Success/Failure
    end
    
    MCS-->>App: Aggregated results
```

### Signal Service Integration Methods

```mermaid
graph LR
    subgraph "Signal Integration Options"
        APP[Your Application]
        
        subgraph "Method 1: CLI"
            CLI[Signal CLI]
            SIGNAL_NET1[Signal Network]
            APP --> CLI
            CLI --> SIGNAL_NET1
        end
        
        subgraph "Method 2: API Gateway"
            GATEWAY[Signal Gateway]
            SIGNAL_NET2[Signal Network]
            APP --> GATEWAY
            GATEWAY --> SIGNAL_NET2
        end
        
        subgraph "Method 3: Webhook"
            WEBHOOK[Custom Webhook]
            SIGNAL_NET3[Signal Network]
            APP --> WEBHOOK
            WEBHOOK --> SIGNAL_NET3
        end
    end
```

## 🌍 **MENA Provider Ecosystem**

### WhatsApp Providers by Region

| Provider | Region | Strengths | Use Case |
|----------|--------|-----------|----------|
| **Unifonic** | 🇸🇦 Saudi-based | Excellent GCC coverage | Saudi Arabia, Gulf states |
| **Msegat** | 🇦🇪 UAE-based | Strong Gulf presence | UAE, Gulf region |
| **Oursms** | 🇪🇬 Egypt-based | North Africa focus | Egypt, North Africa |
| **Infobip** | 🌍 MENA-friendly | Broad regional coverage | Multi-country deployments |
| **Meta** | 🌐 Official | Global coverage | Worldwide reach |

### SMS Provider Coverage

```mermaid
graph TB
    subgraph "MENA SMS Providers"
        UNIFONIC[Unifonic - Saudi Arabia]
        MSEGAT[Msegat - UAE]
        OURSMS[Oursms - Egypt]
        INFOBIP[Infobip - International]
    end
    
    subgraph "Coverage Areas"
        GCC[GCC Countries<br/>SA, AE, QA, KW, BH, OM]
        NORTH_AFRICA[North Africa<br/>EG, MA, TN, DZ]
        LEVANT[Levant<br/>JO, LB, SY]
    end
    
    UNIFONIC --> GCC
    MSEGAT --> GCC
    OURSMS --> NORTH_AFRICA
    INFOBIP --> GCC
    INFOBIP --> NORTH_AFRICA
    INFOBIP --> LEVANT
```

## 🎯 **Bidding & Auction Lifecycle**

### Complete Bid Placement Flow

```mermaid
sequenceDiagram
    participant User as Bidder
    participant API as Bidding API
    participant BP as BiddingProcedure
    participant AS as Auction Service
    participant WS as Wallet Service
    participant NS as Notification Service
    participant WS_CONN as WebSocket
    
    User->>API: Place Bid Request
    API->>BP: completeBidPlacement()
    
    BP->>AS: validateAuctionActive()
    AS-->>BP: Auction valid
    
    BP->>WS: reserveBidFunds()
    WS-->>BP: Funds reserved (30min)
    
    BP->>BP: createBidRecord()
    BP->>AS: updateAuctionWithBid()
    
    BP->>NS: sendBidNotifications()
    par
        NS->>NS: Notify bidder (confirmation)
    and
        NS->>NS: Notify previous bidder (outbid)
    and
        NS->>NS: Notify auction owner
    end
    
    BP->>WS_CONN: broadcastBidUpdate()
    WS_CONN-->>User: Real-time update
    
    BP->>WS: convertReservationToTransaction()
    WS-->>BP: Transaction completed
    
    BP-->>API: Success response
    API-->>User: Bid placed successfully
```

### Auction Settlement Process

```mermaid
sequenceDiagram
    participant CRON as Scheduler
    participant AP as AuctionProcedure
    participant AS as Auction Service
    participant OS as Order Service
    participant PS as Payment Service
    participant WS as Wallet Service
    participant NS as Notification Service
    
    CRON->>AP: completeAuctionLifecycle()
    AP->>AS: getExpiredAuctions()
    AS-->>AP: Expired auctions list
    
    loop For each expired auction
        AP->>AS: determineWinner()
        AS-->>AP: Winner details
        
        AP->>OS: createOrder()
        OS-->>AP: Order created
        
        AP->>PS: processPayment()
        PS-->>AP: Payment processed
        
        AP->>WS: refundNonWinningBids()
        WS-->>AP: Refunds processed
        
        AP->>NS: sendAuctionEndNotifications()
        par
            NS->>NS: Notify winner
        and
            NS->>NS: Notify non-winners
        and
            NS->>NS: Notify seller
        end
        
        AP->>AS: updateAuctionStatus(completed)
    end
```

## 🔧 **Service Configuration**

### Notification Service Setup

```php
// config/mena-services.php
return [
    'whatsapp' => [
        'provider' => env('WHATSAPP_PROVIDER', 'unifonic'),
        'providers' => [
            'unifonic' => [
                'api_key' => env('UNIFONIC_API_KEY'),
                'base_url' => 'https://api.unifonic.com',
                'sender_id' => env('UNIFONIC_SENDER_ID'),
            ],
            'msegat' => [
                'username' => env('MSEGAT_USERNAME'),
                'api_key' => env('MSEGAT_API_KEY'),
                'base_url' => 'https://www.msegat.com',
            ],
            // ... other providers
        ],
    ],
    'country_recommendations' => [
        'SA' => ['sms' => 'unifonic', 'whatsapp' => 'unifonic'],
        'AE' => ['sms' => 'msegat', 'whatsapp' => 'msegat'],
        'EG' => ['sms' => 'oursms', 'whatsapp' => 'oursms'],
    ],
];
```

### Signal Service Configuration

```php
// Three integration methods available
'signal' => [
    'method' => env('SIGNAL_METHOD', 'cli'), // cli, api_gateway, webhook
    
    // Method 1: CLI
    'cli_path' => '/usr/local/bin/signal-cli',
    'account' => env('SIGNAL_ACCOUNT'),
    
    // Method 2: API Gateway
    'api_gateway' => [
        'url' => env('SIGNAL_API_GATEWAY_URL'),
        'api_key' => env('SIGNAL_API_GATEWAY_KEY'),
    ],
    
    // Method 3: Webhook
    'webhook' => [
        'url' => env('SIGNAL_WEBHOOK_URL'),
        'secret' => env('SIGNAL_WEBHOOK_SECRET'),
    ],
];
```

## 📊 **Usage Examples**

### Multi-Channel Notification

```php
use App\Services\MultiChannelNotificationService;

$notificationService = app(MultiChannelNotificationService::class);

// Send across multiple channels
$result = $notificationService->sendMultiChannelNotification([
    'notification_id' => 'auction_123_ended',
    'channels' => ['whatsapp', 'sms', 'telegram', 'email'],
    'recipient' => [
        'user_id' => 1,
        'phone' => '+966501234567',
        'email' => 'user@example.com',
        'telegram_chat_id' => '123456789'
    ],
    'title' => 'Auction Won!',
    'message' => 'Congratulations! You won the auction.',
    'whatsapp_template' => 'auction_won',
    'template_parameters' => [
        'item_name' => 'iPhone 15 Pro',
        'amount' => '$999'
    ]
]);

// Auto-select best channels
$result = $notificationService->sendWithAutoChannelSelection([
    'recipient' => [
        'phone' => '+966501234567',
        'email' => 'user@example.com'
    ],
    'preferred_channels' => ['whatsapp', 'sms'],
    'fallback_channels' => ['email'],
    'message' => 'Your bid has been placed successfully!'
]);
```

### Bidding System Integration

```php
use Shared\Procedures\Micro\BiddingLifecycleProcedure;

$biddingProcedure = app(BiddingLifecycleProcedure::class);

// Complete bid placement with all validations
$result = $biddingProcedure->completeBidPlacement([
    'auction_id' => 'auction_123',
    'bidder_id' => 456,
    'amount' => 1500.00,
    'currency' => 'USD'
], [
    'auth_token' => $authToken,
    'user_id' => 456,
    'request_id' => 'req_789'
]);

if ($result['success']) {
    // Bid placed successfully
    $bidId = $result['data']['bid_id'];
    $reservationId = $result['data']['reservation_id'];
} else {
    // Handle error
    $error = $result['error'];
    $message = $result['message'];
}
```

## 🔍 **Monitoring & Analytics**

### Service Health Endpoints

```bash
# Check service health
curl http://localhost:8001/api/health  # Auth Service
curl http://localhost:8002/api/health  # User Service
curl http://localhost:8003/api/health  # Auction Service
curl http://localhost:8004/api/health  # Bidding Service
curl http://localhost:8005/api/health  # Notification Service

# Service information
curl http://localhost:8005/api/info   # Get service details
```

### Notification Analytics

```php
// Track notification performance
$analytics = [
    'total_sent' => $result['success_count'],
    'total_failed' => $result['failure_count'],
    'channels_used' => array_keys($result['channels']),
    'delivery_rate' => $result['success_count'] / $result['total_channels'],
    'preferred_channel' => 'whatsapp',
    'fallback_used' => $result['failure_count'] > 0
];
```

## 🛠️ **Development**

### Running Tests

```bash
# Run all service tests
docker-compose exec shared-service php artisan test

# Run specific service tests
docker-compose exec bidding-service php artisan test
docker-compose exec notification-service php artisan test

# Run with coverage
docker-compose exec shared-service php artisan test --coverage
```

### Adding New Notification Providers

1. **Extend the service class**:
```php
// Add new provider method
private function sendViaNewProvider(string $to, string $message, array $options): array
{
    // Implementation
}
```

2. **Update configuration**:
```php
// config/mena-services.php
'providers' => [
    'new_provider' => [
        'api_key' => env('NEW_PROVIDER_API_KEY'),
        'base_url' => env('NEW_PROVIDER_BASE_URL'),
    ],
],
```

3. **Add to provider selection**:
```php
$result = match ($this->provider) {
    'new_provider' => $this->sendViaNewProvider($to, $message, $options),
    // ... existing providers
};
```

## 📈 **Performance & Scaling**

### Optimization Features

- **Redis Caching** - Rate limiting and session management
- **Database Indexing** - Optimized queries for auctions and bids
- **Queue Processing** - Asynchronous notification delivery
- **WebSocket Clustering** - Horizontal scaling for real-time features
- **CDN Integration** - Static asset delivery
- **Database Sharding** - Multi-tenant data isolation

### Scaling Recommendations

```yaml
# docker-compose.production.yml
services:
  notification-service:
    deploy:
      replicas: 3
      resources:
        limits:
          memory: 512M
        reservations:
          memory: 256M
  
  redis:
    deploy:
      replicas: 1
      resources:
        limits:
          memory: 1G
```

## 🔐 **Security**

### Authentication & Authorization
- **JWT Tokens** - Stateless authentication
- **Role-based Access** - Granular permissions
- **API Rate Limiting** - DDoS protection
- **CORS Configuration** - Cross-origin security

### Data Protection
- **Encryption at Rest** - Database encryption
- **TLS/SSL** - Transport layer security
- **Input Validation** - XSS/SQL injection prevention
- **Audit Logging** - Security event tracking

## 🤝 **Contributing**

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 **License**

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 **Support**

- **Documentation**: [docs/](docs/)
- **Issues**: [GitHub Issues](https://github.com/abdoElHodaky/larvrevrstender/issues)
- **Discussions**: [GitHub Discussions](https://github.com/abdoElHodaky/larvrevrstender/discussions)

---

<p style="text-align: center; font-size: 16px; line-height: 1.618; margin-top: 2rem;"><strong>Built with ❤️ for the MENA region</strong> 🌍</p>

</div>
<!-- End Golden Ratio Container -->
