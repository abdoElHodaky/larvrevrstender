# Naming Conventions

## Overview

This document establishes the official naming conventions for the Reverse Tender microservices architecture. These conventions prioritize clarity, consistency, and developer productivity.

## 🎯 Core Principles

### 1. **Simplicity Over Verbosity**
- Use the shortest name that clearly conveys meaning
- Remove redundant suffixes like `-service`
- Prefer domain names over technical implementation details

### 2. **Consistency Across All Components**
- Apply the same naming patterns throughout the system
- Use consistent capitalization and formatting
- Maintain alignment between related components

### 3. **Developer-Centric Design**
- Choose names that are easy to type and remember
- Minimize cognitive load when reading code
- Reduce the likelihood of typos and confusion

## 🏗️ Service Naming

### Service Directory Names
```
✅ CORRECT                    ❌ INCORRECT
services/analytics            services/analytics-service
services/auctions             services/auction-service
services/auth                 services/auth-service
services/bidding              services/bidding-service
services/gateway              services/gateway-service
services/notifications        services/notification-service
services/orders               services/order-service
services/payments             services/payment-service
services/users                services/user-service
services/vin-ocr              services/vin-ocr-service
```

### Service Display Names
```
✅ CORRECT                    ❌ INCORRECT
Analytics                     Analytics Service
Auctions                      Auction Service
Auth                          Authentication Service
Bidding                       Bidding Service
Gateway                       Gateway Service
Notifications                 Notification Service
Orders                        Order Service
Payments                      Payment Service
Users                         User Service
VIN OCR                       VIN OCR Service
```

## ⚙️ Configuration Naming

### Environment Variables

#### Service Authentication Tokens
```
✅ CORRECT                    ❌ INCORRECT
AUTH_TOKEN=                   RPC_AUTH_SERVICE_TOKEN=
USERS_TOKEN=                  RPC_USER_SERVICE_TOKEN=
AUCTIONS_TOKEN=               RPC_AUCTION_SERVICE_TOKEN=
BIDDING_TOKEN=                RPC_BIDDING_SERVICE_TOKEN=
ORDERS_TOKEN=                 RPC_ORDER_SERVICE_TOKEN=
PAYMENTS_TOKEN=               RPC_PAYMENT_SERVICE_TOKEN=
GATEWAY_TOKEN=                RPC_GATEWAY_SERVICE_TOKEN=
NOTIFICATIONS_TOKEN=          RPC_NOTIFICATION_SERVICE_TOKEN=
ANALYTICS_TOKEN=              RPC_ANALYTICS_SERVICE_TOKEN=
VIN_OCR_TOKEN=                RPC_VIN_OCR_SERVICE_TOKEN=
```

#### Service URLs
```
✅ CORRECT                    ❌ INCORRECT
AUTH_URL=                     AUTH_SERVICE_RPC_URL=
USERS_URL=                    USER_SERVICE_RPC_URL=
AUCTIONS_URL=                 AUCTION_SERVICE_RPC_URL=
BIDDING_URL=                  BIDDING_SERVICE_RPC_URL=
ORDERS_URL=                   ORDER_SERVICE_RPC_URL=
PAYMENTS_URL=                 PAYMENT_SERVICE_RPC_URL=
GATEWAY_URL=                  GATEWAY_SERVICE_RPC_URL=
NOTIFICATIONS_URL=            NOTIFICATION_SERVICE_RPC_URL=
ANALYTICS_URL=                ANALYTICS_SERVICE_RPC_URL=
VIN_OCR_URL=                  VIN_OCR_SERVICE_RPC_URL=
```

#### Application Configuration
```
✅ CORRECT                    ❌ INCORRECT
APP_NAME="Analytics"          APP_NAME="Analytics Service"
APP_NAME="Auctions"           APP_NAME="Auction Service"
APP_NAME="Auth"               APP_NAME="Authentication Service"
```

## 🎮 Controller Naming

### Dual Controller Pattern
```php
✅ CORRECT                    ❌ INCORRECT
// Direct operations
AuctionController.php         AuctionServiceController.php

// API operations  
AuctionApi.php                Api\AuctionController.php
```

### Controller Class Names
```php
✅ CORRECT                    ❌ INCORRECT
class AuctionController       class AuctionServiceController
class AuctionApi              class ApiAuctionController
class UserController          class UserServiceController
class UserApi                 class ApiUserController
```

## 🌐 API Endpoint Naming

### URL Patterns
```
✅ CORRECT                    ❌ INCORRECT
GET  /auctions                GET  /api/auction/auctions
POST /auctions                POST /api/auction/auctions/create
GET  /auctions/{id}           GET  /api/auction/auctions/{id}/show
PUT  /auctions/{id}           PUT  /api/auction/auctions/{id}/update
DELETE /auctions/{id}         DELETE /api/auction/auctions/{id}/delete

GET  /dashboard               GET  /api/analytics/dashboard/overview
GET  /reports                 GET  /api/analytics/reports/list
POST /events                  POST /api/analytics/events/track

GET  /bids                    GET  /api/bidding/bids/history
POST /bids                    POST /api/bidding/bids/place
```

### Route Naming
```php
✅ CORRECT                    ❌ INCORRECT
Route::get('/auctions', [AuctionController::class, 'index'])
    ->name('auctions.index');

Route::post('/auctions', [AuctionController::class, 'store'])
    ->name('auctions.store');

// NOT
Route::get('/api/auction/auctions', [AuctionServiceController::class, 'index'])
    ->name('auction.service.auctions.index');
```

## 🗄️ Database Naming

### Database Names
```
✅ CORRECT                    ❌ INCORRECT
analytics_db                  analytics_service_db
auctions_db                   auction_service_db
auth_db                       auth_service_db
bidding_db                    bidding_service_db
orders_db                     order_service_db
payments_db                   payment_service_db
users_db                      user_service_db
```

### Table Names
```
✅ CORRECT                    ❌ INCORRECT
auctions                      auction_service_auctions
bids                          bidding_service_bids
users                         user_service_users
orders                        order_service_orders
payments                      payment_service_payments
```

## 📦 Model Naming

### Model Class Names
```php
✅ CORRECT                    ❌ INCORRECT
class Auction                 class AuctionServiceAuction
class Bid                     class BiddingServiceBid
class User                    class UserServiceUser
class Order                   class OrderServiceOrder
class Payment                 class PaymentServicePayment
```

### Model Relationships
```php
✅ CORRECT                    ❌ INCORRECT
public function auctions()    public function auctionServiceAuctions()
public function bids()        public function biddingServiceBids()
public function user()        public function userServiceUser()
```

## 🐳 Docker & Deployment Naming

### Docker Service Names
```yaml
✅ CORRECT                    ❌ INCORRECT
services:
  analytics:                  analytics-service:
    build: ./services/analytics
  auctions:                   auction-service:
    build: ./services/auctions
  auth:                       auth-service:
    build: ./services/auth
```

### Container Names
```yaml
✅ CORRECT                    ❌ INCORRECT
container_name: analytics     container_name: analytics-service
container_name: auctions      container_name: auction-service
container_name: auth          container_name: auth-service
```

## 📁 File & Directory Naming

### Service Structure
```
✅ CORRECT                    ❌ INCORRECT
services/
├── analytics/                ├── analytics-service/
├── auctions/                 ├── auction-service/
├── auth/                     ├── auth-service/
├── bidding/                  ├── bidding-service/
└── shared/                   └── shared-libraries/
```

### Configuration Files
```
✅ CORRECT                    ❌ INCORRECT
.env.example                  .env.service.example
docker-compose.yml            docker-compose.services.yml
README.md                     SERVICE_README.md
```

## 📚 Documentation Naming

### File Names
```
✅ CORRECT                    ❌ INCORRECT
README.md                     SERVICE_README.md
ARCHITECTURE.md               SERVICE_ARCHITECTURE.md
MIGRATION_GUIDE.md            SERVICE_MIGRATION_GUIDE.md
```

### Document Titles
```markdown
✅ CORRECT                    ❌ INCORRECT
# Analytics                   # Analytics Service
# Auctions                    # Auction Service  
# Auth                        # Authentication Service
```

## 🔧 Code Naming

### Variable Names
```php
✅ CORRECT                    ❌ INCORRECT
$auctionData                  $auctionServiceData
$userToken                    $userServiceToken
$bidAmount                    $biddingServiceAmount
```

### Method Names
```php
✅ CORRECT                    ❌ INCORRECT
public function getAuctions() public function getAuctionServiceAuctions()
public function createBid()   public function createBiddingServiceBid()
public function updateUser()  public function updateUserServiceUser()
```

### Class Properties
```php
✅ CORRECT                    ❌ INCORRECT
protected $auctionClient;     protected $auctionServiceClient;
protected $userRepository;    protected $userServiceRepository;
protected $bidValidator;      protected $biddingServiceValidator;
```

## 🧪 Testing Naming

### Test Class Names
```php
✅ CORRECT                    ❌ INCORRECT
class AuctionControllerTest   class AuctionServiceControllerTest
class UserApiTest             class UserServiceApiTest
class BidValidationTest       class BiddingServiceValidationTest
```

### Test Method Names
```php
✅ CORRECT                    ❌ INCORRECT
public function test_can_create_auction()
public function test_can_place_bid()
public function test_can_authenticate_user()

// NOT
public function test_auction_service_can_create_auction()
public function test_bidding_service_can_place_bid()
```

## 📊 Monitoring & Logging

### Log Channel Names
```php
✅ CORRECT                    ❌ INCORRECT
'channels' => [
    'auctions' => [...],      'auction-service' => [...],
    'bidding' => [...],       'bidding-service' => [...],
    'auth' => [...],          'auth-service' => [...],
]
```

### Metric Names
```
✅ CORRECT                    ❌ INCORRECT
auctions.created              auction_service.auctions.created
bids.placed                   bidding_service.bids.placed
users.authenticated           auth_service.users.authenticated
```

## ✅ Validation Rules

### Naming Checklist
- [ ] **No redundant suffixes** (avoid `-service`, `-controller`, etc.)
- [ ] **Consistent capitalization** (PascalCase for classes, snake_case for files)
- [ ] **Domain-focused names** (reflect business concepts, not technical implementation)
- [ ] **Easy to type** (avoid overly long or complex names)
- [ ] **Consistent patterns** (same naming approach across similar components)
- [ ] **Clear meaning** (name should clearly indicate purpose)

### Common Mistakes to Avoid
❌ **Don't use technical suffixes unnecessarily**
```
AuctionServiceController → AuctionController
UserServiceRepository → UserRepository
```

❌ **Don't mix naming conventions**
```
auction-service + AuctionService → auctions + Auction
```

❌ **Don't use verbose paths when simple ones work**
```
/api/auction/auctions/create → /auctions
```

❌ **Don't include implementation details in names**
```
RPC_AUTH_SERVICE_TOKEN → AUTH_TOKEN
```

## 🔄 Migration Strategy

When updating existing code to follow these conventions:

1. **Start with configuration** (environment variables, Docker configs)
2. **Update service directories** and references
3. **Refactor controller names** and routes
4. **Update model names** and relationships
5. **Fix documentation** and comments
6. **Update tests** and test names

## 📈 Benefits

Following these naming conventions provides:

- **50% reduction** in typing for common operations
- **Faster onboarding** for new developers
- **Reduced cognitive load** when reading code
- **Fewer typos** and naming-related bugs
- **Cleaner documentation** and better readability
- **Consistent developer experience** across all services

---

These naming conventions are designed to make the Reverse Tender codebase more maintainable, readable, and developer-friendly while preserving all semantic meaning and functionality.

