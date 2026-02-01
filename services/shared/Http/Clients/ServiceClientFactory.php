<?php

namespace Shared\Http\Clients;

use Shared\Config\ServiceRegistry;

class ServiceClientFactory
{
    /**
     * Create a service client instance.
     */
    public static function create(string $serviceName, string $clientClass): BaseServiceClient
    {
        $config = ServiceRegistry::getServiceConfig($serviceName);
        
        return new $clientClass(
            $config['url'],
            $config['timeout'],
            $config['retry_attempts'],
            $config['retry_delay']
        );
    }

    /**
     * Create an Auth service client.
     */
    public static function createAuthClient(): AuthServiceClient
    {
        return self::create('auth', AuthServiceClient::class);
    }

    /**
     * Create a User service client.
     */
    public static function createUserClient(): UserServiceClient
    {
        return self::create('user', UserServiceClient::class);
    }

    /**
     * Create a Payment service client.
     */
    public static function createPaymentClient(): PaymentServiceClient
    {
        return self::create('payment', PaymentServiceClient::class);
    }

    /**
     * Create an Order service client.
     */
    public static function createOrderClient(): OrderServiceClient
    {
        return self::create('order', OrderServiceClient::class);
    }

    /**
     * Create a Bidding service client.
     */
    public static function createBiddingClient(): BiddingServiceClient
    {
        return self::create('bidding', BiddingServiceClient::class);
    }

    /**
     * Create an Analytics service client.
     */
    public static function createAnalyticsClient(): AnalyticsServiceClient
    {
        return self::create('analytics', AnalyticsServiceClient::class);
    }

    /**
     * Create a Notification service client.
     */
    public static function createNotificationClient(): NotificationServiceClient
    {
        return self::create('notification', NotificationServiceClient::class);
    }

    /**
     * Create a VIN OCR service client.
     */
    public static function createVinOcrClient(): VinOcrServiceClient
    {
        return self::create('vin-ocr', VinOcrServiceClient::class);
    }
}
