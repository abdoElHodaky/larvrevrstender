<?php

namespace App\Http\Clients;

class DataCollectionClient extends BaseServiceClient
{
    /**
     * Collect data from multiple services for analytics.
     */
    public function collectUserData(): array
    {
        return $this->collectFromService(config('services.user_service.url'), '/analytics/users');
    }

    public function collectPaymentData(): array
    {
        return $this->collectFromService(config('services.payment_service.url'), '/analytics/payments');
    }

    public function collectOrderData(): array
    {
        return $this->collectFromService(config('services.order_service.url'), '/analytics/orders');
    }

    public function collectBiddingData(): array
    {
        return $this->collectFromService(config('services.bidding_service.url'), '/analytics/bids');
    }

    public function collectNotificationData(): array
    {
        return $this->collectFromService(config('services.notification_service.url'), '/analytics/notifications');
    }

    public function collectVinOcrData(): array
    {
        return $this->collectFromService(config('services.vin_ocr_service.url'), '/analytics/ocr');
    }

    /**
     * Collect service health metrics.
     */
    public function collectHealthMetrics(): array
    {
        $services = [
            'auth' => config('services.auth_service.url'),
            'user' => config('services.user_service.url'),
            'payment' => config('services.payment_service.url'),
            'order' => config('services.order_service.url'),
            'bidding' => config('services.bidding_service.url'),
            'notification' => config('services.notification_service.url'),
            'vin_ocr' => config('services.vin_ocr_service.url'),
        ];

        return collect($services)->mapWithKeys(fn($url, $name) => [
            $name => $this->collectFromService($url, '/health')
        ])->toArray();
    }

    /**
     * Collect service information.
     */
    public function collectServiceInfo(): array
    {
        $services = [
            'auth' => config('services.auth_service.url'),
            'user' => config('services.user_service.url'),
            'payment' => config('services.payment_service.url'),
            'order' => config('services.order_service.url'),
            'bidding' => config('services.bidding_service.url'),
            'notification' => config('services.notification_service.url'),
            'vin_ocr' => config('services.vin_ocr_service.url'),
        ];

        return collect($services)->mapWithKeys(fn($url, $name) => [
            $name => $this->collectFromService($url, '/info')
        ])->toArray();
    }

    /**
     * Generic method to collect data from any service.
     */
    private function collectFromService(string $baseUrl, string $endpoint): array
    {
        try {
            $client = new class($baseUrl) extends BaseServiceClient {};
            $response = $client->get($endpoint);

            return $response->successful() ? $response->json() : [];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}

