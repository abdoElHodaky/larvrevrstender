<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Shared\Procedures\ProcedureEngine;
use App\RPC\Procedures\NotificationProcedure;
use App\Services\NotificationService;
use App\Services\EmailService;
use App\Services\SmsService;
use App\Services\PushNotificationService;

/**
 * RPC Service Provider for Notification Service
 * 
 * Registers RPC procedures with the ProcedureEngine for handling
 * incoming RPC calls from other services.
 */
class RpcServiceProvider extends ServiceProvider
{
    /**
     * Register RPC procedures
     *
     * @return void
     */
    public function register(): void
    {
        // Register procedure classes in the container
        $this->app->singleton(NotificationProcedure::class, function ($app) {
            return new NotificationProcedure(
                $app->make(NotificationService::class),
                $app->make(EmailService::class),
                $app->make(SmsService::class),
                $app->make(PushNotificationService::class)
            );
        });
    }
    
    /**
     * Bootstrap RPC procedures
     *
     * @return void
     */
    public function boot(): void
    {
        // Register RPC clients for inter-service communication
        $this->registerRpcClients();
        
        $procedureEngine = $this->app->make(ProcedureEngine::class);
        
        // Register notification-related procedures
        $notificationProcedure = $this->app->make(NotificationProcedure::class);
        $procedureEngine->register('notification.sendEmail', [$notificationProcedure, 'sendEmail']);
        $procedureEngine->register('notification.sendSms', [$notificationProcedure, 'sendSms']);
        $procedureEngine->register('notification.sendPushNotification', [$notificationProcedure, 'sendPushNotification']);
        $procedureEngine->register('notification.sendMultiChannel', [$notificationProcedure, 'sendMultiChannel']);
        $procedureEngine->register('notification.sendBidConfirmation', [$notificationProcedure, 'sendBidConfirmation']);
        $procedureEngine->register('notification.sendOutbidNotification', [$notificationProcedure, 'sendOutbidNotification']);
        $procedureEngine->register('notification.sendAuctionWonNotification', [$notificationProcedure, 'sendAuctionWonNotification']);
        $procedureEngine->register('notification.sendPaymentReminder', [$notificationProcedure, 'sendPaymentReminder']);
        $procedureEngine->register('notification.getStatus', [$notificationProcedure, 'getStatus']);
        
        // Register system procedures for health checks and monitoring
        $procedureEngine->register('health.check', function () {
            return [
                'success' => true,
                'service' => 'notification-service',
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'version' => config('app.version', '1.0.0'),
            ];
        });
        
        $procedureEngine->register('system.info', function () {
            return [
                'success' => true,
                'service' => 'notification-service',
                'description' => 'Handles multi-channel notifications including email, SMS, and push notifications',
                'version' => config('app.version', '1.0.0'),
                'environment' => config('app.env'),
                'procedures' => [
                    'notification.sendEmail',
                    'notification.sendSms',
                    'notification.sendPushNotification',
                    'notification.sendMultiChannel',
                    'notification.sendBidConfirmation',
                    'notification.sendOutbidNotification',
                    'notification.sendAuctionWonNotification',
                    'notification.sendPaymentReminder',
                    'notification.getStatus',
                    'health.check',
                    'system.info',
                    'system.metrics',
                    'system.ping',
                ],
            ];
        });
        
        $procedureEngine->register('system.metrics', function () {
            // Get basic metrics - in production this would include more detailed metrics
            return [
                'success' => true,
                'service' => 'notification-service',
                'metrics' => [
                    'total_notifications' => \App\Models\Notification::count(),
                    'sent_notifications' => \App\Models\Notification::where('status', 'sent')->count(),
                    'pending_notifications' => \App\Models\Notification::where('status', 'pending')->count(),
                    'failed_notifications' => \App\Models\Notification::where('status', 'failed')->count(),
                    'email_notifications' => \App\Models\Notification::where('channel', 'email')->count(),
                    'sms_notifications' => \App\Models\Notification::where('channel', 'sms')->count(),
                    'push_notifications' => \App\Models\Notification::where('channel', 'push')->count(),
                    'memory_usage' => memory_get_usage(true),
                    'memory_peak' => memory_get_peak_usage(true),
                ],
                'timestamp' => now()->toISOString(),
            ];
        });
        
        $procedureEngine->register('system.ping', function () {
            return [
                'success' => true,
                'service' => 'notification-service',
                'message' => 'pong',
                'timestamp' => now()->toISOString(),
            ];
        });
    }

    /**
     * Register RPC clients for inter-service communication
     */
    private function registerRpcClients(): void
    {
        // User Service RPC Client
        $this->app->singleton('UserRpc', function () {
            return new \App\RPC\Clients\UserServiceRpcClient();
        });

        // Register RPC clients with interface bindings for dependency injection
        $this->app->bind(\App\RPC\Clients\UserServiceRpcClient::class, function () {
            return app('UserRpc');
        });
    }

    /**
     * Register RPC adapters as singletons
     */
    private function registerRpcAdapters(): void
    {
        $this->app->singleton(\App\RPC\Adapters\AuthServiceAdapter::class);
    }
}
