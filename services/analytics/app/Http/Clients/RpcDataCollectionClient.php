<?php

namespace App\Http\Clients;

use Shared\RPC\Clients\UserServiceClient;
use Shared\RPC\Clients\PaymentServiceClient;
use Shared\RPC\Clients\AuctionServiceClient;
use Shared\RPC\Clients\BiddingServiceClient;
use Shared\RPC\Clients\NotificationServiceClient;
use Shared\RPC\Clients\VinOcrServiceClient;
use Illuminate\Support\Facades\Log;

/**
 * RPC-based Data Collection Client
 * 
 * Maintains the same interface as DataCollectionClient but uses RPC calls
 * instead of HTTP requests for improved performance and reliability.
 */
class RpcDataCollectionClient
{
    private UserServiceClient $userRpcClient;
    private PaymentServiceClient $paymentRpcClient;
    private AuctionServiceClient $auctionRpcClient;
    private BiddingServiceClient $biddingRpcClient;
    private NotificationServiceClient $notificationRpcClient;
    private VinOcrServiceClient $vinOcrRpcClient;

    public function __construct()
    {
        $this->userRpcClient = app(UserServiceClient::class);
        $this->paymentRpcClient = app(PaymentServiceClient::class);
        $this->auctionRpcClient = app(AuctionServiceClient::class);
        $this->biddingRpcClient = app(BiddingServiceClient::class);
        $this->notificationRpcClient = app(NotificationServiceClient::class);
        $this->vinOcrRpcClient = app(VinOcrServiceClient::class);
    }

    /**
     * Collect data from multiple services for analytics.
     */
    public function collectUserData(): array
    {
        try {
            // Use searchUsers to get user data for analytics
            $response = $this->userRpcClient->searchUsers([], 1, 100);
            return $response->isSuccess() ? $response->getData() : [];
        } catch (\Exception $e) {
            Log::error('RPC User data collection failed', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    public function collectPaymentData(): array
    {
        try {
            // TODO: Implement payment analytics data collection using available payment methods
            Log::info('Payment analytics data collection not yet implemented in modern RPC clients');
            return [];
        } catch (\Exception $e) {
            Log::error('RPC Payment data collection failed', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    public function collectOrderData(): array
    {
        try {
            // Use auction service to get auction data for analytics
            $response = $this->auctionRpcClient->searchAuctions([], 1, 100);
            return $response->isSuccess() ? $response->getData() : [];
        } catch (\Exception $e) {
            Log::error('RPC Order data collection failed', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    public function collectBiddingData(): array
    {
        try {
            // TODO: Implement bidding analytics data collection using available bidding methods
            Log::info('Bidding analytics data collection not yet implemented in modern RPC clients');
            return [];
        } catch (\Exception $e) {
            Log::error('RPC Bidding data collection failed', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    public function collectNotificationData(): array
    {
        try {
            // TODO: Implement notification analytics data collection using available notification methods
            Log::info('Notification analytics data collection not yet implemented in modern RPC clients');
            return [];
        } catch (\Exception $e) {
            Log::error('RPC Notification data collection failed', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    public function collectVinOcrData(): array
    {
        try {
            // TODO: Implement VIN OCR analytics data collection using available VIN OCR methods
            Log::info('VIN OCR analytics data collection not yet implemented in modern RPC clients');
            return [];
        } catch (\Exception $e) {
            Log::error('RPC VIN OCR data collection failed', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Collect service health metrics.
     */
    public function collectHealthMetrics(): array
    {
        $metrics = [];

        // Collect health metrics from available RPC clients
        try {
            $userHealth = $this->userRpcClient->healthCheck();
            $metrics['user'] = $userHealth;
        } catch (\Exception $e) {
            $metrics['user'] = ['error' => $e->getMessage()];
        }

        try {
            $paymentHealth = $this->paymentRpcClient->healthCheck();
            $metrics['payment'] = $paymentHealth;
        } catch (\Exception $e) {
            $metrics['payment'] = ['error' => $e->getMessage()];
        }

        try {
            $auctionHealth = $this->auctionRpcClient->healthCheck();
            $metrics['auction'] = $auctionHealth;
        } catch (\Exception $e) {
            $metrics['auction'] = ['error' => $e->getMessage()];
        }

        try {
            $biddingHealth = $this->biddingRpcClient->healthCheck();
            $metrics['bidding'] = $biddingHealth;
        } catch (\Exception $e) {
            $metrics['bidding'] = ['error' => $e->getMessage()];
        }

        try {
            $notificationHealth = $this->notificationRpcClient->healthCheck();
            $metrics['notification'] = $notificationHealth;
        } catch (\Exception $e) {
            $metrics['notification'] = ['error' => $e->getMessage()];
        }

        try {
            $vinOcrHealth = $this->vinOcrRpcClient->healthCheck();
            $metrics['vin_ocr'] = $vinOcrHealth;
        } catch (\Exception $e) {
            $metrics['vin_ocr'] = ['error' => $e->getMessage()];
        }

        // Placeholder for services not yet available via RPC
        $metrics['auth'] = ['status' => 'rpc_not_implemented'];

        return $metrics;
    }

    /**
     * Collect service information.
     */
    public function collectServiceInfo(): array
    {
        $info = [];

        // Collect service info from available RPC clients
        try {
            $userInfo = $this->userRpcClient->getServiceInfo();
            $info['user'] = $userInfo['success'] ?? false ? ($userInfo['data'] ?? []) : [];
        } catch (\Exception $e) {
            $info['user'] = ['error' => $e->getMessage()];
        }

        try {
            $paymentInfo = $this->paymentRpcClient->getServiceInfo();
            $info['payment'] = $paymentInfo['success'] ?? false ? ($paymentInfo['data'] ?? []) : [];
        } catch (\Exception $e) {
            $info['payment'] = ['error' => $e->getMessage()];
        }

        try {
            $auctionInfo = $this->auctionRpcClient->getServiceInfo();
            $info['auction'] = $auctionInfo['success'] ?? false ? ($auctionInfo['data'] ?? []) : [];
        } catch (\Exception $e) {
            $info['auction'] = ['error' => $e->getMessage()];
        }

        try {
            $biddingInfo = $this->biddingRpcClient->getServiceInfo();
            $info['bidding'] = $biddingInfo['success'] ?? false ? ($biddingInfo['data'] ?? []) : [];
        } catch (\Exception $e) {
            $info['bidding'] = ['error' => $e->getMessage()];
        }

        try {
            $notificationInfo = $this->notificationRpcClient->getServiceInfo();
            $info['notification'] = $notificationInfo['success'] ?? false ? ($notificationInfo['data'] ?? []) : [];
        } catch (\Exception $e) {
            $info['notification'] = ['error' => $e->getMessage()];
        }

        try {
            $vinOcrInfo = $this->vinOcrRpcClient->getServiceInfo();
            $info['vin_ocr'] = $vinOcrInfo['success'] ?? false ? ($vinOcrInfo['data'] ?? []) : [];
        } catch (\Exception $e) {
            $info['vin_ocr'] = ['error' => $e->getMessage()];
        }

        // Placeholder for services not yet available via RPC
        $info['auth'] = ['status' => 'rpc_not_implemented'];

        return $info;
    }

    /**
     * Health check for compatibility with BaseServiceClient interface
     */
    public function healthCheck(): bool
    {
        try {
            // Check if at least one RPC client is healthy
            $userHealth = $this->userRpcClient->healthCheck();
            return $userHealth['success'] ?? false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get service info for compatibility with BaseServiceClient interface
     */
    public function getServiceInfo(): ?array
    {
        try {
            return [
                'service' => 'analytics-data-collection',
                'version' => '1.0.0',
                'rpc_enabled' => true,
                'available_clients' => ['user', 'payment', 'auction', 'bidding']
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
}
