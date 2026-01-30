<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\OrderService;
use App\Services\NotificationService;
use App\Services\VehicleService;
use App\Services\ImageProcessingService;
use App\Services\AnalyticsService;
use App\Services\Contracts\NotificationServiceInterface;
use App\Services\Contracts\VehicleServiceInterface;
use App\Services\Contracts\ImageProcessingServiceInterface;
use App\Services\Contracts\AnalyticsServiceInterface;

/**
 * Application Service Provider
 * 
 * Handles dependency injection and service binding to prevent circular dependencies
 * and ensure proper separation of concerns
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register service interfaces to prevent circular dependencies
        $this->registerServiceInterfaces();
        
        // Register concrete service implementations
        $this->registerServices();
        
        // Register external service clients
        $this->registerExternalServices();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Boot service configurations
        $this->bootServiceConfigurations();
    }

    /**
     * Register service interfaces
     */
    protected function registerServiceInterfaces(): void
    {
        // Notification Service Interface
        $this->app->bind(NotificationServiceInterface::class, function ($app) {
            return new NotificationService(
                config('services.notification.url', env('NOTIFICATION_SERVICE_URL'))
            );
        });

        // Vehicle Service Interface
        $this->app->bind(VehicleServiceInterface::class, function ($app) {
            return new VehicleService(
                config('services.user.url', env('USER_SERVICE_URL'))
            );
        });

        // Image Processing Service Interface
        $this->app->bind(ImageProcessingServiceInterface::class, function ($app) {
            return new ImageProcessingService(
                config('filesystems.disks.s3'),
                config('image.processing', [
                    'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                    'max_file_size' => 10240, // 10MB
                    'thumbnail_size' => 300,
                    'large_size' => 1200,
                    'quality' => 85
                ])
            );
        });

        // Analytics Service Interface
        $this->app->bind(AnalyticsServiceInterface::class, function ($app) {
            return new AnalyticsService(
                config('services.analytics.url', env('ANALYTICS_SERVICE_URL'))
            );
        });
    }

    /**
     * Register concrete service implementations
     */
    protected function registerServices(): void
    {
        // Order Service - Main business logic service
        $this->app->singleton(OrderService::class, function ($app) {
            return new OrderService(
                $app->make(NotificationServiceInterface::class),
                $app->make(VehicleServiceInterface::class),
                $app->make(ImageProcessingServiceInterface::class),
                $app->make(AnalyticsServiceInterface::class)
            );
        });

        // Register service aliases for easier access
        $this->app->alias(OrderService::class, 'order.service');
        $this->app->alias(NotificationServiceInterface::class, 'notification.service');
        $this->app->alias(VehicleServiceInterface::class, 'vehicle.service');
        $this->app->alias(ImageProcessingServiceInterface::class, 'image.service');
        $this->app->alias(AnalyticsServiceInterface::class, 'analytics.service');
    }

    /**
     * Register external service clients
     */
    protected function registerExternalServices(): void
    {
        // HTTP Client for service communication
        $this->app->singleton('http.client', function ($app) {
            return \Illuminate\Support\Facades\Http::withOptions([
                'timeout' => config('services.http.timeout', 10),
                'retry' => config('services.http.retry', 3),
                'connect_timeout' => config('services.http.connect_timeout', 5),
            ]);
        });

        // Service Discovery Client
        $this->app->singleton('service.discovery', function ($app) {
            return new \App\Services\ServiceDiscoveryClient([
                'auth_service' => config('services.auth.url', env('AUTH_SERVICE_URL')),
                'user_service' => config('services.user.url', env('USER_SERVICE_URL')),
                'bidding_service' => config('services.bidding.url', env('BIDDING_SERVICE_URL')),
                'notification_service' => config('services.notification.url', env('NOTIFICATION_SERVICE_URL')),
                'payment_service' => config('services.payment.url', env('PAYMENT_SERVICE_URL')),
                'vin_ocr_service' => config('services.vin_ocr.url', env('VIN_OCR_SERVICE_URL')),
                'analytics_service' => config('services.analytics.url', env('ANALYTICS_SERVICE_URL')),
            ]);
        });

        // Event Bus for inter-service communication
        $this->app->singleton('event.bus', function ($app) {
            return new \App\Services\EventBus(
                $app->make('redis'),
                config('events.channels', [
                    'orders' => 'order-events',
                    'bids' => 'bid-events',
                    'payments' => 'payment-events',
                    'notifications' => 'notification-events'
                ])
            );
        });
    }

    /**
     * Boot service configurations
     */
    protected function bootServiceConfigurations(): void
    {
        // Configure service timeouts and retry policies
        config([
            'services.http.timeout' => env('SERVICE_HTTP_TIMEOUT', 10),
            'services.http.retry' => env('SERVICE_HTTP_RETRY', 3),
            'services.http.connect_timeout' => env('SERVICE_HTTP_CONNECT_TIMEOUT', 5),
        ]);

        // Configure image processing settings
        config([
            'image.processing.allowed_types' => explode(',', env('ALLOWED_IMAGE_TYPES', 'jpg,jpeg,png,gif,webp')),
            'image.processing.max_file_size' => env('MAX_UPLOAD_SIZE', 10240),
            'image.processing.thumbnail_size' => env('THUMBNAIL_WIDTH', 300),
            'image.processing.large_size' => env('LARGE_IMAGE_WIDTH', 1200),
            'image.processing.quality' => env('IMAGE_QUALITY', 85),
        ]);

        // Configure order business rules
        config([
            'order.default_expiry_hours' => env('DEFAULT_ORDER_EXPIRY_HOURS', 168),
            'order.min_budget_amount' => env('MIN_BUDGET_AMOUNT', 100),
            'order.max_budget_amount' => env('MAX_BUDGET_AMOUNT', 100000),
            'order.max_images_per_order' => env('MAX_IMAGES_PER_ORDER', 10),
            'order.auto_publish_enabled' => env('AUTO_PUBLISH_ENABLED', true),
        ]);

        // Configure notification settings
        config([
            'notifications.channels' => explode(',', env('NOTIFICATION_CHANNELS', 'push,email,sms')),
            'notifications.retry_attempts' => env('NOTIFICATION_RETRY_ATTEMPTS', 3),
            'notifications.retry_delay' => env('NOTIFICATION_RETRY_DELAY', 60),
        ]);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            OrderService::class,
            NotificationServiceInterface::class,
            VehicleServiceInterface::class,
            ImageProcessingServiceInterface::class,
            AnalyticsServiceInterface::class,
            'order.service',
            'notification.service',
            'vehicle.service',
            'image.service',
            'analytics.service',
            'http.client',
            'service.discovery',
            'event.bus',
        ];
    }
}
