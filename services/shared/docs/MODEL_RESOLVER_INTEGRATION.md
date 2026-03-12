# Model Resolver Integration Guide

## Overview

The Shared Service library uses a Model Resolver pattern to access Eloquent models from consuming services without creating circular dependencies. This allows shared jobs and procedures to work with service-specific models while maintaining clean architecture.

## How It Works

1. **Shared Library**: Defines `ModelResolverInterface` and provides `ModelResolver` implementation
2. **Consuming Services**: Register their models with the resolver during service boot
3. **Shared Jobs/Procedures**: Use the resolver to access models dynamically

## Integration Steps for Consuming Services

### 1. Register Models in Service Provider

In each service's `AppServiceProvider` or dedicated service provider, register your models:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Shared\Contracts\ModelResolverInterface;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Register models with shared library
        $this->registerModelsWithSharedLibrary();
    }

    private function registerModelsWithSharedLibrary(): void
    {
        $modelResolver = app(ModelResolverInterface::class);
        
        // Register service-specific models
        $modelResolver->registerModels([
            'User' => \App\Models\User::class,
            'Auction' => \App\Models\Auction::class,
            'BusinessMetric' => \App\Models\BusinessMetric::class,
            'NotificationTemplate' => \App\Models\NotificationTemplate::class,
            'PaymentMethod' => \App\Models\PaymentMethod::class,
            'FailedJob' => \App\Models\FailedJob::class,
        ]);
    }
}
```

### 2. Service-Specific Model Registration Examples

#### User Service
```php
$modelResolver->registerModels([
    'User' => \App\Models\User::class,
    'CustomerProfile' => \App\Models\CustomerProfile::class,
    'MerchantProfile' => \App\Models\MerchantProfile::class,
]);
```

#### Auction Service
```php
$modelResolver->registerModels([
    'Auction' => \App\Models\Auction::class,
    'ProductImage' => \App\Models\ProductImage::class,
]);
```

#### Analytics Service
```php
$modelResolver->registerModels([
    'BusinessMetric' => \App\Models\BusinessMetric::class,
    'AnalyticsReport' => \App\Models\AnalyticsReport::class,
    'UserAnalytic' => \App\Models\UserAnalytic::class,
]);
```

#### Payment Service
```php
$modelResolver->registerModels([
    'PaymentMethod' => \App\Models\PaymentMethod::class,
    'Payment' => \App\Models\Payment::class,
    'Transaction' => \App\Models\Transaction::class,
]);
```

#### Notification Service
```php
$modelResolver->registerModels([
    'NotificationTemplate' => \App\Models\NotificationTemplate::class,
    'Job' => \App\Models\Job::class,
    'FailedJob' => \App\Models\FailedJob::class,
]);
```

## Usage in Shared Library

### Cache Warming Job Example
```php
// In WarmCacheDataJob
$userQuery = $this->modelResolver->query('User');
if ($userQuery) {
    $activeUsers = $userQuery
        ->where('status', 'active')
        ->where('last_login_at', '>=', now()->subDays(30))
        ->get();
}
```

### Circuit Breaker Procedure Example
```php
// In QueueCircuitBreakerProcedure
$failedJobQuery = $modelResolver->query('FailedJob');
if ($failedJobQuery) {
    return $failedJobQuery->count();
}
```

## Benefits

1. **No Circular Dependencies**: Shared library doesn't import service models directly
2. **Flexible Architecture**: Services control which models are available to shared components
3. **Graceful Degradation**: Shared components can fallback to DB::table if models unavailable
4. **Type Safety**: Full Eloquent ORM benefits when models are available
5. **Service Isolation**: Each service only exposes models it chooses to share

## Required Models for Current Shared Components

The following models should be registered by their respective services for full functionality:

- **User** (user-service): Required for cache warming user profiles
- **Auction** (auction-service): Required for cache warming auction data
- **BusinessMetric** (analytics-service): Required for cache warming analytics metrics
- **NotificationTemplate** (notification-service): Required for cache warming notification templates
- **PaymentMethod** (payment-service): Required for cache warming payment methods
- **FailedJob** (notification-service): Required for circuit breaker health monitoring

## Error Handling

The shared library includes graceful error handling:

1. **Model Not Available**: Logs warning and returns empty result set
2. **Query Failure**: Falls back to DB::table operations where possible
3. **Service Container Issues**: Returns null/empty results safely

## Testing

When testing shared components, you can mock the model resolver:

```php
$mockResolver = Mockery::mock(ModelResolverInterface::class);
$mockResolver->shouldReceive('query')
    ->with('User')
    ->andReturn(User::query());

app()->instance(ModelResolverInterface::class, $mockResolver);
```

## Migration Path

1. **Phase 1**: Register models in each service (this enables shared components)
2. **Phase 2**: Verify shared jobs work with registered models
3. **Phase 3**: Remove any remaining DB::table fallbacks if desired

This pattern ensures the shared library remains a true library without service dependencies while enabling powerful Eloquent ORM integration.
