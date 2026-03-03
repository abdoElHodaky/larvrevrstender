# Database Failover Events - Cross-Service Listening

This document explains how to listen to database failover events across all services in the microservices architecture.

## Overview

The shared service now provides comprehensive database failover event listening capabilities that can be used across all services. The infrastructure includes:

- ✅ **DatabaseFailoverEvent** - Individual failover events
- ✅ **DatabaseFailoverSystemEvent** - System-wide failover events  
- ✅ **DatabaseFailoverEmailNotificationService** - Comprehensive email notifications
- ✅ **EventServiceProvider** - Event listener registration
- ✅ **DatabaseFailoverNotificationListener** - Event processing

## How to Listen to Database Failover Events in Any Service

### 1. Create an EventServiceProvider in Your Service

```php
<?php
// services/your-service/app/Providers/EventServiceProvider.php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Shared\Events\DatabaseFailoverEvent;
use Shared\Events\DatabaseFailoverSystemEvent;
use App\Listeners\YourCustomFailoverListener;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        DatabaseFailoverEvent::class => [
            YourCustomFailoverListener::class,
        ],
        DatabaseFailoverSystemEvent::class => [
            YourCustomFailoverListener::class . '@handleSystemEvent',
        ],
    ];
}
```

### 2. Create a Custom Listener in Your Service

```php
<?php
// services/your-service/app/Listeners/YourCustomFailoverListener.php

namespace App\Listeners;

use Shared\Events\DatabaseFailoverEvent;
use Shared\Events\DatabaseFailoverSystemEvent;
use Illuminate\Contracts\Queue\ShouldQueue;

class YourCustomFailoverListener implements ShouldQueue
{
    public function handle(DatabaseFailoverEvent $event): void
    {
        // Your custom logic for handling database failover
        // Access event properties:
        // - $event->fromConnection
        // - $event->toConnection  
        // - $event->getSeverity()
        // - $event->reason
        // - $event->duration
        // - $event->getImpact()
        // - $event->isCriticalFailover()
        
        // Example: Log to service-specific logs
        logger()->warning('Database failover detected in ' . config('app.name'), [
            'from' => $event->fromConnection,
            'to' => $event->toConnection,
            'severity' => $event->getSeverity(),
        ]);
        
        // Example: Trigger service-specific actions
        if ($event->isCriticalFailover()) {
            // Handle critical failover in your service
            $this->handleCriticalFailover($event);
        }
    }
    
    public function handleSystemEvent(DatabaseFailoverSystemEvent $event): void
    {
        // Handle system-wide failover events
        logger()->critical('System-wide database failover', [
            'event_type' => $event->eventType,
            'affected_services' => $event->affectedServices,
        ]);
    }
    
    private function handleCriticalFailover(DatabaseFailoverEvent $event): void
    {
        // Your service-specific critical failover handling
        // Examples:
        // - Switch to read-only mode
        // - Activate circuit breakers
        // - Send service-specific notifications
        // - Update service health status
    }
}
```

### 3. Register Your EventServiceProvider

Add to your service's `bootstrap/app.php` or `config/app.php`:

```php
// bootstrap/app.php (Laravel 11+)
return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        App\Providers\EventServiceProvider::class,
        // ... other providers
    ]);

// OR config/app.php (Laravel 10)
'providers' => [
    App\Providers\EventServiceProvider::class,
    // ... other providers
],
```

## Available Event Data

### DatabaseFailoverEvent Properties
- `fromConnection` - Source database connection
- `toConnection` - Target database connection  
- `reason` - Reason for failover
- `duration` - Failover duration in milliseconds
- `healthStatus` - Current health status
- `requestId` - Request ID for tracing
- `context` - Additional context data

### DatabaseFailoverEvent Methods
- `getSeverity()` - Get severity level (critical, high, medium, low, info)
- `getImpact()` - Get impact description
- `getDescription()` - Get human-readable description
- `isFailback()` - Check if this is a failback operation
- `isCriticalFailover()` - Check if this is a critical failover

### DatabaseFailoverSystemEvent Properties
- `eventType` - Type of system event
- `affectedServices` - Array of affected service names
- `timestamp` - Event timestamp
- `systemContext` - System-level context data

## Email Notifications

The shared service automatically handles email notifications through the `DatabaseFailoverEmailNotificationService`. This includes:

- **Severity-based routing** (ops-team, engineering-leads, on-call)
- **Rate limiting** to prevent alert fatigue
- **Rich email templates** for different event types
- **SMTP2Go integration** for reliable delivery

## Event Types Handled

The system recognizes these specific event types for targeted notifications:

- `split_brain_detected` (CRITICAL)
- `graceful_degradation_unavailable` (CRITICAL)  
- `failover_attempt_failed` (CRITICAL)
- `transaction_circuit_breaker_open` (CRITICAL)
- `query_circuit_breaker_open` (HIGH)
- `connection_health_check_failed` (HIGH)
- `data_consistency_issues_detected` (HIGH)
- `database_topology_mapping_completed` (INFO)

## Queue Configuration

Database failover events are processed through the `database-failover-notifications` queue. Make sure your queue workers are configured to process this queue:

```bash
php artisan queue:work --queue=database-failover-notifications,default
```

## Testing Event Listening

You can test event listening by dispatching events manually:

```php
use Shared\Events\DatabaseFailoverEvent;

// Dispatch a test failover event
event(new DatabaseFailoverEvent(
    fromConnection: 'primary',
    toConnection: 'secondary', 
    reason: 'Connection timeout',
    duration: 1500,
    healthStatus: 'degraded',
    requestId: 'test-123'
));
```

## Cross-Service Architecture

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Auth Service  │    │  Order Service  │    │  User Service   │
│                 │    │                 │    │                 │
│ EventProvider   │    │ EventProvider   │    │ EventProvider   │
│ CustomListener  │    │ CustomListener  │    │ CustomListener  │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
                    ┌─────────────────┐
                    │ Shared Service  │
                    │                 │
                    │ DatabaseFailover│
                    │ Event Dispatch  │
                    │                 │
                    │ Email Service   │
                    │ SMTP2Go        │
                    └─────────────────┘
```

Each service can implement its own custom listeners while benefiting from the centralized email notification system provided by the shared service.
