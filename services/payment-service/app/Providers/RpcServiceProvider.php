<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Shared\Core\ProcedureEngine;
use App\RPC\Procedures\PaymentProcedure;
use App\Services\PaymentService;
use App\Services\ReservationService;
use App\Services\InvoiceService;
use App\Services\EscrowService;

/**
 * RPC Service Provider for Payment Service
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
        $this->app->singleton(PaymentProcedure::class, function ($app) {
            return new PaymentProcedure(
                $app->make(PaymentService::class),
                $app->make(ReservationService::class),
                $app->make(InvoiceService::class),
                $app->make(EscrowService::class)
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
        
        // Register payment-related procedures
        $paymentProcedure = $this->app->make(PaymentProcedure::class);
        $procedureEngine->register('payment.reserveFunds', [$paymentProcedure, 'reserveFunds']);
        $procedureEngine->register('payment.releaseFunds', [$paymentProcedure, 'releaseFunds']);
        $procedureEngine->register('payment.captureFunds', [$paymentProcedure, 'captureFunds']);
        $procedureEngine->register('payment.processPayment', [$paymentProcedure, 'processPayment']);
        $procedureEngine->register('payment.issueRefund', [$paymentProcedure, 'issueRefund']);
        $procedureEngine->register('payment.getStatus', [$paymentProcedure, 'getStatus']);
        $procedureEngine->register('payment.getReservationStatus', [$paymentProcedure, 'getReservationStatus']);
        $procedureEngine->register('payment.getUserPaymentMethods', [$paymentProcedure, 'getUserPaymentMethods']);
        $procedureEngine->register('payment.calculateFees', [$paymentProcedure, 'calculateFees']);
        
        // Register invoice-related procedures
        $procedureEngine->register('payment.createInvoice', [$paymentProcedure, 'createInvoice']);
        $procedureEngine->register('payment.sendInvoice', [$paymentProcedure, 'sendInvoice']);
        $procedureEngine->register('payment.getInvoice', [$paymentProcedure, 'getInvoice']);
        
        // Register escrow-related procedures
        $procedureEngine->register('payment.createEscrow', [$paymentProcedure, 'createEscrow']);
        $procedureEngine->register('payment.fundEscrow', [$paymentProcedure, 'fundEscrow']);
        $procedureEngine->register('payment.releaseEscrow', [$paymentProcedure, 'releaseEscrow']);
        $procedureEngine->register('payment.getEscrowByOrderId', [$paymentProcedure, 'getEscrowByOrderId']);
        
        // Register system procedures for health checks and monitoring
        $procedureEngine->register('health.check', function () {
            return [
                'success' => true,
                'service' => 'payment-service',
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'version' => config('app.version', '1.0.0'),
            ];
        });
        
        $procedureEngine->register('system.info', function () {
            return [
                'success' => true,
                'service' => 'payment-service',
                'description' => 'Handles payment processing, fund reservations, and financial operations',
                'version' => config('app.version', '1.0.0'),
                'environment' => config('app.env'),
                'procedures' => [
                    'payment.reserveFunds',
                    'payment.releaseFunds',
                    'payment.captureFunds',
                    'payment.processPayment',
                    'payment.issueRefund',
                    'payment.getStatus',
                    'payment.getReservationStatus',
                    'payment.getUserPaymentMethods',
                    'payment.calculateFees',
                    'payment.createInvoice',
                    'payment.sendInvoice',
                    'payment.getInvoice',
                    'payment.createEscrow',
                    'payment.fundEscrow',
                    'payment.releaseEscrow',
                    'payment.getEscrowByOrderId',
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
                'service' => 'payment-service',
                'metrics' => [
                    'total_payments' => \App\Models\Payment::count(),
                    'successful_payments' => \App\Models\Payment::where('status', 'completed')->count(),
                    'pending_payments' => \App\Models\Payment::where('status', 'pending')->count(),
                    'failed_payments' => \App\Models\Payment::where('status', 'failed')->count(),
                    'total_reservations' => \App\Models\Reservation::count(),
                    'active_reservations' => \App\Models\Reservation::where('status', 'active')->count(),
                    'memory_usage' => memory_get_usage(true),
                    'memory_peak' => memory_get_peak_usage(true),
                ],
                'timestamp' => now()->toISOString(),
            ];
        });
        
        $procedureEngine->register('system.ping', function () {
            return [
                'success' => true,
                'service' => 'payment-service',
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
        // Auction Service RPC Client
        $this->app->singleton('AuctionRpc', function () {
            return new \App\RPC\Clients\AuctionServiceRpcClient();
        });

        // Notification Service RPC Client
        $this->app->singleton('NotificationRpc', function () {
            return new \App\RPC\Clients\NotificationServiceRpcClient();
        });

        // Register RPC clients with interface bindings for dependency injection
        $this->app->bind(\App\RPC\Clients\AuctionServiceRpcClient::class, function () {
            return app('AuctionRpc');
        });

        $this->app->bind(\App\RPC\Clients\NotificationServiceRpcClient::class, function () {
            return app('NotificationRpc');
        });

        // Order Service RPC Client
        $this->app->singleton('OrderRpc', function () {
            return new \Sajya\Client\Client(
                \Illuminate\Support\Facades\Http::baseUrl(config('rpc.services.order.url'))
                    ->withToken(config('rpc.services.order.token'))
                    ->withHeaders([
                        'X-Service-Name' => 'payment-service',
                        'X-Correlation-ID' => request()->header('X-Correlation-ID', uniqid('rpc_', true)),
                    ])
                    ->timeout(config('rpc.client.timeout', 5))
            );
        });

        // Register RPC adapters
        $this->registerRpcAdapters();
    }

    /**
     * Register RPC adapters as singletons
     */
    private function registerRpcAdapters(): void
    {
        $this->app->singleton(\App\RPC\Adapters\AuthServiceAdapter::class);
        $this->app->singleton(\App\RPC\Adapters\UserServiceAdapter::class);
        $this->app->singleton(\App\RPC\Adapters\NotificationServiceAdapter::class);
        $this->app->singleton(\App\RPC\Adapters\OrderServiceAdapter::class);
    }
}
