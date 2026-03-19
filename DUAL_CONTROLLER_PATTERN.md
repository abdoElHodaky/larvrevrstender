# Dual Controller Pattern Documentation

## Overview

The **Dual Controller Pattern** is an intentional architectural design implemented in the Reverse Tender microservices ecosystem. This pattern provides two distinct controller implementations for the same domain logic, each serving different architectural purposes and use cases.

## Pattern Implementation

### Services Using This Pattern

- **auctions**: `AuctionController` + `Api\AuctionController`
- **bidding**: `BiddingController` + `Api\BiddingController`

## Architecture Explanation

### Root Controllers (Direct Service Operations)

**Location**: `app/Http/Controllers/{DomainController}.php`

**Purpose**: Handle direct service operations with single-service responsibility

**Characteristics**:
- Direct database operations within the service boundary
- Service-specific business logic
- Minimal cross-service dependencies
- Optimized for performance and simplicity
- Direct model interactions

**Example**: `services/auctions/app/Http/Controllers/AuctionController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Services\AuctionService;

class AuctionController extends Controller
{
    public function __construct(
        protected AuctionService $auctionService
    ) {}

    /**
     * Direct service operation - handles auction creation
     * within the auction service boundary only
     */
    public function store(Request $request): JsonResponse
    {
        // Direct model operations
        $auction = Auction::create($validated);
        
        // Service-specific business logic
        return response()->json($auction, 201);
    }
}
```

### API Controllers (Cross-Service Orchestration)

**Location**: `app/Http/Controllers/Api/{DomainController}.php`

**Purpose**: Handle complex workflows requiring cross-service orchestration

**Characteristics**:
- Uses shared procedures for cross-service coordination
- Implements complex business workflows
- Handles distributed transactions
- Manages inter-service communication
- Provides comprehensive error handling across services

**Example**: `services/auctions/app/Http/Controllers/Api/AuctionController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Shared\Procedures\Micro\AuctionLifecycleProcedure;

class AuctionController extends Controller
{
    public function __construct(
        private AuctionLifecycleProcedure $auctionProcedure
    ) {}

    /**
     * Cross-service orchestration - handles complete auction lifecycle
     * involving multiple services (auth, user, notification, etc.)
     */
    public function createAuction(Request $request): JsonResponse
    {
        // Uses shared procedures for cross-service orchestration
        $result = $this->auctionProcedure->createCompleteAuction([
            'auction_data' => $validated,
            'user_id' => $request->user()->id,
            'notify_users' => true,
            'update_analytics' => true
        ]);
        
        return response()->json($result);
    }
}
```

## When to Use Each Controller

### Use Root Controllers When:

✅ **Single Service Operations**
- Creating, reading, updating, or deleting records within the service boundary
- Simple CRUD operations
- Performance-critical operations
- Internal service endpoints

✅ **Direct Database Operations**
- Queries that don't require cross-service data
- Bulk operations within the service
- Reporting endpoints specific to the service

✅ **Service-Specific Logic**
- Business rules that apply only to the current service
- Validation that doesn't require external data
- Internal administrative operations

### Use API Controllers When:

✅ **Cross-Service Workflows**
- Operations that span multiple services
- Complex business processes
- Distributed transactions

✅ **External API Endpoints**
- Public API endpoints
- Client-facing operations
- Third-party integrations

✅ **Orchestrated Operations**
- User registration with profile creation, notification sending, and analytics tracking
- Auction creation with user validation, image processing, and notification broadcasting
- Bid placement with auction validation, user verification, and real-time updates

## Shared Procedures Integration

The API controllers leverage the **Shared Procedures** library located in `services/shared/src/Procedures/Micro/` to handle cross-service orchestration:

### Available Procedures

- `AuctionLifecycleProcedure`: Complete auction management across services
- `BiddingLifecycleProcedure`: Bidding workflows with validation and notifications
- `UserManagementProcedure`: User operations spanning auth, profile, and analytics
- `OrderProcessingProcedure`: Order lifecycle from creation to fulfillment

### Example Procedure Usage

```php
// In Api\AuctionController
public function createAuction(Request $request): JsonResponse
{
    $result = $this->auctionProcedure->createCompleteAuction([
        'title' => $request->title,
        'description' => $request->description,
        'starting_price' => $request->starting_price,
        'duration_hours' => $request->duration_hours,
        'user_id' => $request->user()->id,
        'images' => $request->images ?? [],
        'notify_subscribers' => true,
        'update_analytics' => true,
        'validate_user_limits' => true
    ]);

    return response()->json($result);
}
```

## Routing Conventions

### Root Controller Routes
```php
// routes/web.php or routes/api.php
Route::prefix('auctions')->group(function () {
    Route::get('/', [AuctionController::class, 'index']);
    Route::post('/', [AuctionController::class, 'store']);
    Route::get('/{auction}', [AuctionController::class, 'show']);
    Route::put('/{auction}', [AuctionController::class, 'update']);
    Route::delete('/{auction}', [AuctionController::class, 'destroy']);
});
```

### API Controller Routes
```php
// routes/api.php
Route::prefix('api/v1')->group(function () {
    Route::post('/auctions/create', [Api\AuctionController::class, 'createAuction']);
    Route::post('/auctions/{auction}/complete', [Api\AuctionController::class, 'completeAuction']);
    Route::post('/auctions/{auction}/cancel', [Api\AuctionController::class, 'cancelAuction']);
});
```

## Benefits of This Pattern

### 🚀 **Performance Optimization**
- Root controllers provide direct, fast access for simple operations
- API controllers handle complex workflows without impacting simple operations

### 🔧 **Separation of Concerns**
- Clear distinction between service-specific and cross-service operations
- Easier maintenance and testing
- Reduced coupling between services

### 📈 **Scalability**
- Root controllers can be optimized independently
- API controllers can be scaled based on orchestration needs
- Different caching strategies for different use cases

### 🛡️ **Error Isolation**
- Failures in cross-service operations don't affect simple service operations
- Better error handling and recovery strategies
- Improved system resilience

## Best Practices

### ✅ **Do's**

1. **Keep Root Controllers Simple**
   - Focus on single-service operations
   - Minimize external dependencies
   - Optimize for performance

2. **Use API Controllers for Orchestration**
   - Leverage shared procedures
   - Handle cross-service errors gracefully
   - Implement comprehensive logging

3. **Maintain Clear Naming**
   - Root controllers: `{Domain}Controller`
   - API controllers: `Api\{Domain}Controller`

4. **Document Controller Purpose**
   - Add clear docblocks explaining the controller's role
   - Document when to use each controller

### ❌ **Don'ts**

1. **Don't Mix Concerns**
   - Avoid cross-service calls in root controllers
   - Don't put simple CRUD in API controllers

2. **Don't Duplicate Logic**
   - Extract common logic to services or shared libraries
   - Use dependency injection for shared components

3. **Don't Ignore Error Handling**
   - API controllers must handle distributed failures
   - Root controllers should handle service-specific errors

## Migration Guide

### Adding Dual Controllers to a New Service

1. **Create Root Controller**
   ```bash
   php artisan make:controller {Domain}Controller
   ```

2. **Create API Controller**
   ```bash
   php artisan make:controller Api/{Domain}Controller
   ```

3. **Implement Service-Specific Logic** in root controller

4. **Implement Cross-Service Logic** in API controller using shared procedures

5. **Set Up Appropriate Routes** for each controller type

### Converting Single Controller to Dual Pattern

1. **Analyze Existing Controller** - identify service-specific vs cross-service operations
2. **Create API Controller** - move cross-service operations
3. **Refactor Root Controller** - keep only service-specific operations
4. **Update Routes** - separate simple and complex endpoints
5. **Test Both Controllers** - ensure functionality is preserved

## Conclusion

The Dual Controller Pattern provides a clean architectural separation between simple service operations and complex cross-service orchestration. This pattern enhances maintainability, performance, and scalability while providing clear guidelines for developers on where to implement different types of business logic.

By following this pattern, the Reverse Tender platform maintains a clean separation of concerns while supporting both high-performance direct operations and complex distributed workflows.
