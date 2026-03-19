# RPC Communication Guide

## 🔗 **Inter-Service Communication Overview**

This guide documents the Remote Procedure Call (RPC) communication patterns used throughout the Reverse Tender microservices ecosystem. All services implement standardized RPC communication for reliable, secure, and efficient inter-service communication.

---

## ⚙️ **RPC Configuration Standards**

### **Global RPC Settings**
All services must implement these standardized RPC configuration variables:

```bash
# Global RPC Configuration (3 variables)
RPC_TIMEOUT=30                    # Request timeout in seconds
RPC_RETRY_ATTEMPTS=3              # Number of retry attempts
RPC_RETRY_DELAY=1000             # Delay between retries in milliseconds
```

### **Service URL Configuration**
Each service must configure URLs for all other services:

```bash
# Service URLs (10 variables)
AUTH_SERVICE_URL=http://auth-service:8001
USER_SERVICE_URL=http://user-service:8002
AUCTION_SERVICE_URL=http://auction-service:8003
BIDDING_SERVICE_URL=http://bidding-service:8004
ORDER_SERVICE_URL=http://order-service:8005
PAYMENT_SERVICE_URL=http://payment-service:8006
GATEWAY_SERVICE_URL=http://gateway-service:8007
NOTIFICATION_SERVICE_URL=http://notification-service:8008
ANALYTICS_SERVICE_URL=http://analytics-service:8009
VIN_OCR_SERVICE_URL=http://vin-ocr-service:8010
```

### **Authentication Token Configuration**
Each service must configure authentication tokens for secure communication:

```bash
# RPC Authentication Tokens (10+ variables)
RPC_AUTH_SERVICE_TOKEN=auth_service_token_placeholder
RPC_USER_SERVICE_TOKEN=user_service_token_placeholder
RPC_AUCTION_SERVICE_TOKEN=auction_service_token_placeholder
RPC_BIDDING_SERVICE_TOKEN=bidding_service_token_placeholder
RPC_ORDER_SERVICE_TOKEN=order_service_token_placeholder
RPC_PAYMENT_SERVICE_TOKEN=payment_service_token_placeholder
RPC_GATEWAY_SERVICE_TOKEN=gateway_service_token_placeholder
RPC_NOTIFICATION_SERVICE_TOKEN=notification_service_token_placeholder
RPC_ANALYTICS_SERVICE_TOKEN=analytics_service_token_placeholder
RPC_VIN_OCR_SERVICE_TOKEN=vin_ocr_service_token_placeholder
```

---

## 🏗️ **RPC Client Architecture**

### **Shared RPC Clients**
All RPC client implementations are centralized in the `shared` library:

```
services/shared/src/RPC/Clients/
├── AnalyticsServiceClient.php
├── AuctionServiceClient.php
├── AuthServiceClient.php
├── BiddingServiceClient.php
├── NotificationServiceClient.php
├── OrderServiceClient.php
├── PaymentServiceClient.php
├── UserServiceClient.php
└── VinOcrServiceClient.php
```

### **Base RPC Client Features**
All RPC clients inherit from a base client that provides:
- **Timeout Handling**: Configurable request timeouts
- **Retry Logic**: Automatic retry with exponential backoff
- **Authentication**: Token-based service authentication
- **Error Handling**: Standardized error responses
- **Logging**: Request/response logging for debugging
- **Circuit Breaker**: Fault tolerance for failing services

---

## 🔐 **Authentication & Security**

### **Service-to-Service Authentication**
1. **Token-Based**: Each service has unique authentication tokens
2. **Header Authentication**: Tokens sent in `Authorization` header
3. **Token Rotation**: Regular token rotation for security
4. **Scope Limitation**: Tokens limited to specific service interactions

### **Security Headers**
All RPC requests include standardized security headers:
```http
Authorization: Bearer {RPC_SERVICE_TOKEN}
Content-Type: application/json
X-Service-Name: {calling_service_name}
X-Request-ID: {unique_request_id}
X-Correlation-ID: {correlation_id}
```

### **Request Validation**
- **Input Sanitization**: All inputs validated and sanitized
- **Schema Validation**: Request/response schema validation
- **Rate Limiting**: Per-service rate limiting
- **IP Whitelisting**: Service IP address validation

---

## 📡 **Communication Patterns**

### **Synchronous RPC Calls**
Used for immediate data requirements:

```php
// Example: Get user profile from user-service
$userClient = new UserServiceClient();
$userProfile = $userClient->getUserProfile($userId);

// Example: Validate auction from auction-service
$auctionClient = new AuctionServiceClient();
$isValid = $auctionClient->validateAuction($auctionId);
```

### **Asynchronous Event Publishing**
Used for non-blocking notifications:

```php
// Example: Notify about bid placement
$notificationClient = new NotificationServiceClient();
$notificationClient->sendBidNotificationAsync($bidData);

// Example: Track analytics event
$analyticsClient = new AnalyticsServiceClient();
$analyticsClient->trackEventAsync('bid_placed', $eventData);
```

### **Batch Operations**
Used for bulk data processing:

```php
// Example: Batch user validation
$userClient = new UserServiceClient();
$validationResults = $userClient->validateUsersBatch($userIds);

// Example: Bulk notification sending
$notificationClient = new NotificationServiceClient();
$notificationClient->sendBulkNotifications($notifications);
```

---

## 🔄 **Error Handling & Resilience**

### **Error Response Format**
Standardized error response structure:

```json
{
    "success": false,
    "error": {
        "code": "SERVICE_UNAVAILABLE",
        "message": "User service is temporarily unavailable",
        "details": {
            "service": "user-service",
            "timestamp": "2024-03-19T07:12:40Z",
            "request_id": "req_123456789"
        }
    },
    "retry_after": 30
}
```

### **Retry Strategy**
Exponential backoff with jitter:
1. **First Retry**: 1 second delay
2. **Second Retry**: 2 seconds delay
3. **Third Retry**: 4 seconds delay
4. **Circuit Breaker**: Open circuit after 3 consecutive failures

### **Circuit Breaker States**
- **Closed**: Normal operation, requests pass through
- **Open**: Service unavailable, requests fail fast
- **Half-Open**: Testing service recovery, limited requests

### **Fallback Mechanisms**
- **Cached Data**: Return cached data when service unavailable
- **Default Values**: Use sensible defaults for non-critical data
- **Graceful Degradation**: Reduce functionality instead of complete failure
- **Queue for Later**: Queue requests for later processing

---

## 📊 **Monitoring & Observability**

### **RPC Metrics**
Track key performance indicators:
- **Request Rate**: Requests per second per service
- **Response Time**: Average, P95, P99 response times
- **Error Rate**: Percentage of failed requests
- **Circuit Breaker State**: Open/closed state per service

### **Distributed Tracing**
- **Correlation IDs**: Track requests across services
- **Span Creation**: Create spans for each RPC call
- **Context Propagation**: Pass trace context between services
- **Performance Analysis**: Identify bottlenecks in service chains

### **Logging Standards**
Structured logging for all RPC interactions:

```json
{
    "timestamp": "2024-03-19T07:12:40Z",
    "level": "INFO",
    "service": "bidding-service",
    "event": "rpc_call",
    "target_service": "auction-service",
    "method": "validateAuction",
    "request_id": "req_123456789",
    "correlation_id": "corr_987654321",
    "duration_ms": 150,
    "status": "success"
}
```

---

## 🚀 **Performance Optimization**

### **Connection Pooling**
- **HTTP Connection Reuse**: Reuse connections for multiple requests
- **Pool Size Configuration**: Configurable connection pool sizes
- **Connection Timeout**: Automatic connection cleanup
- **Load Balancing**: Distribute connections across service instances

### **Caching Strategies**
- **Response Caching**: Cache frequently requested data
- **Cache Invalidation**: Invalidate cache on data updates
- **Cache TTL**: Configurable time-to-live for cached data
- **Distributed Caching**: Redis for cross-service caching

### **Request Optimization**
- **Batch Requests**: Combine multiple requests into batches
- **Selective Fields**: Request only needed data fields
- **Compression**: Gzip compression for large payloads
- **Async Processing**: Non-blocking requests where possible

---

## 🔧 **Development Guidelines**

### **RPC Client Usage**
Best practices for using RPC clients:

```php
<?php

namespace App\Services;

use Shared\RPC\Clients\UserServiceClient;
use Shared\RPC\Clients\NotificationServiceClient;

class BiddingService
{
    private UserServiceClient $userClient;
    private NotificationServiceClient $notificationClient;
    
    public function __construct(
        UserServiceClient $userClient,
        NotificationServiceClient $notificationClient
    ) {
        $this->userClient = $userClient;
        $this->notificationClient = $notificationClient;
    }
    
    public function placeBid(int $userId, int $auctionId, float $amount): array
    {
        try {
            // Validate user exists and is active
            $user = $this->userClient->getUserProfile($userId);
            if (!$user['active']) {
                throw new InvalidUserException('User is not active');
            }
            
            // Process bid logic here...
            $bid = $this->processBid($userId, $auctionId, $amount);
            
            // Send notification asynchronously
            $this->notificationClient->sendBidNotificationAsync([
                'user_id' => $userId,
                'auction_id' => $auctionId,
                'bid_amount' => $amount
            ]);
            
            return $bid;
            
        } catch (ServiceUnavailableException $e) {
            // Handle service unavailability
            Log::warning('User service unavailable', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            // Use fallback or cached data
            return $this->processBidWithFallback($userId, $auctionId, $amount);
        }
    }
}
```

### **Error Handling Best Practices**
1. **Catch Specific Exceptions**: Handle different error types appropriately
2. **Log Errors**: Log all RPC errors for debugging
3. **Provide Fallbacks**: Implement fallback mechanisms
4. **User-Friendly Messages**: Return meaningful error messages
5. **Retry Logic**: Implement appropriate retry strategies

### **Testing RPC Clients**
Mock RPC clients for unit testing:

```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Shared\RPC\Clients\UserServiceClient;
use App\Services\BiddingService;

class BiddingServiceTest extends TestCase
{
    public function testPlaceBidWithValidUser()
    {
        // Mock the user service client
        $userClient = $this->createMock(UserServiceClient::class);
        $userClient->method('getUserProfile')
                   ->willReturn(['id' => 1, 'active' => true]);
        
        $notificationClient = $this->createMock(NotificationServiceClient::class);
        
        $biddingService = new BiddingService($userClient, $notificationClient);
        
        $result = $biddingService->placeBid(1, 100, 50.00);
        
        $this->assertIsArray($result);
        $this->assertEquals(1, $result['user_id']);
    }
}
```

---

## 📋 **Service-Specific RPC Endpoints**

### **auth-service RPC Endpoints**
```
POST /rpc/validateToken      # Validate JWT token
POST /rpc/getUserPermissions # Get user permissions
POST /rpc/refreshToken       # Refresh JWT token
```

### **user-service RPC Endpoints**
```
GET  /rpc/users/{id}         # Get user profile
POST /rpc/users/validate     # Validate user exists
POST /rpc/users/batch        # Get multiple users
```

### **auction-service RPC Endpoints**
```
GET  /rpc/auctions/{id}      # Get auction details
POST /rpc/auctions/validate  # Validate auction active
POST /rpc/auctions/batch     # Get multiple auctions
```

### **bidding-service RPC Endpoints**
```
POST /rpc/bids/validate      # Validate bid amount
GET  /rpc/bids/highest/{auction} # Get highest bid
POST /rpc/bids/place         # Place bid via RPC
```

### **order-service RPC Endpoints**
```
POST /rpc/orders/create      # Create order from bid
GET  /rpc/orders/{id}        # Get order details
POST /rpc/orders/update      # Update order status
```

### **payment-service RPC Endpoints**
```
POST /rpc/payments/process   # Process payment
POST /rpc/payments/validate  # Validate payment method
GET  /rpc/payments/{id}      # Get payment status
```

### **notification-service RPC Endpoints**
```
POST /rpc/notifications/send # Send notification
POST /rpc/notifications/bulk # Send bulk notifications
GET  /rpc/templates/{id}     # Get notification template
```

### **analytics-service RPC Endpoints**
```
POST /rpc/events/track       # Track analytics event
POST /rpc/events/batch       # Track multiple events
GET  /rpc/metrics/{type}     # Get metrics data
```

### **vin-ocr-service RPC Endpoints**
```
POST /rpc/vin/extract        # Extract VIN from image
POST /rpc/vin/validate       # Validate VIN format
POST /rpc/vin/decode         # Decode VIN information
```

---

## 🔍 **Troubleshooting Guide**

### **Common Issues**
1. **Connection Timeouts**: Check network connectivity and service health
2. **Authentication Failures**: Verify RPC tokens are correct and not expired
3. **Rate Limiting**: Check if service is being rate limited
4. **Circuit Breaker Open**: Wait for circuit breaker to reset or check service health

### **Debugging Tools**
- **RPC Logs**: Check service logs for RPC call details
- **Health Endpoints**: Verify service health status
- **Metrics Dashboard**: Monitor RPC performance metrics
- **Distributed Tracing**: Follow request traces across services

### **Performance Issues**
- **Slow Responses**: Check service performance metrics
- **High Error Rates**: Investigate service logs and health
- **Memory Leaks**: Monitor connection pool usage
- **Network Issues**: Check network latency and connectivity

---

*This RPC communication guide ensures consistent, reliable, and secure inter-service communication across the Reverse Tender microservices ecosystem.*

