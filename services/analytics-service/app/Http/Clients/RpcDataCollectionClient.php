<?php

namespace App\Http\Clients;

use App\RPC\Clients\UserServiceRpcClient;
use App\RPC\Clients\PaymentServiceRpcClient;
use App\RPC\Clients\AuctionServiceRpcClient;
use App\RPC\Clients\BiddingServiceRpcClient;
use Illuminate\Support\Facades\Log;

/**
 * RPC-based Data Collection Client
 * 
 * Maintains the same interface as DataCollectionClient but uses RPC calls
 * instead of HTTP requests for improved performance and reliability.
 */
class RpcDataCollectionClient
{
    private UserServiceRpcClient $userRpcClient;
    private PaymentServiceRpcClient $paymentRpcClient;
    private AuctionServiceRpcClient $auctionRpcClient;
    private BiddingServiceRpcClient $biddingRpcClient;

    public function __construct()
    {
        $this->userRpcClient = app(UserServiceRpcClient::class);
        $this->paymentRpcClient = app(PaymentServiceRpcClient::class);
        $this->auctionRpcClient = app(AuctionServiceRpcClient::class);
        $this->biddingRpcClient = app(BiddingServiceRpcClient::class);
    }

    /**
     * Collect data from multiple services for analytics.
     */
    public function collectUserData(): array
    {
        try {
            $result = $this->userRpcClient->getUserAnalyticsData();
            return $result['success'] ?? false ? ($result['data'] ?? []) : [];
        } catch (\Exception $e) {
            Log::error('RPC User data collection failed', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    public function collectPaymentData(): array
    {
        try {
            $result = $this->paymentRpcClient->getPaymentAnalyticsData();
            return $result['success'] ?? false ? ($result['data'] ?? []) : [];
        } catch (\Exception $e) {
            Log::error('RPC Payment data collection failed', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    public function collectOrderData(): array
    {
        try {
            // Use auction service for order-related data since orders are part of auctions
            $result = $this->auctionRpcClient->getAuctionAnalyticsData();
            return $result['success'] ?? false ? ($result['data'] ?? []) : [];
        } catch (\Exception $e) {
            Log::error('RPC Order data collection failed', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    public function collectBiddingData(): array
    {
        try {
            $result = $this->biddingRpcClient->getBiddingAnalyticsData();
            return $result['success'] ?? false ? ($result['data'] ?? []) : [];
        } catch (\Exception $e) {
            Log::error('RPC Bidding data collection failed', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    public function collectNotificationData(): array
    {
        try {
            // For now, return empty array as notification service RPC client is not available in analytics service
            // This would need to be added to the RPC service provider if notification analytics are needed
            Log::info('Notification data collection not implemented via RPC yet');
            return [];
        } catch (\Exception $e) {
            Log::error('RPC Notification data collection failed', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    public function collectVinOcrData(): array
    {
        try {
            // For now, return empty array as VIN OCR service RPC client is not available in analytics service
            // This would need to be added to the RPC service provider if VIN OCR analytics are needed
            Log::info('VIN OCR data collection not implemented via RPC yet');
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

        // Placeholder for services not yet available via RPC
        $metrics['auth'] = ['status' => 'rpc_not_implemented'];
        $metrics['notification'] = ['status' => 'rpc_not_implemented'];
        $metrics['vin_ocr'] = ['status' => 'rpc_not_implemented'];

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

        // Placeholder for services not yet available via RPC
        $info['auth'] = ['status' => 'rpc_not_implemented'];
        $info['notification'] = ['status' => 'rpc_not_implemented'];
        $info['vin_ocr'] = ['status' => 'rpc_not_implemented'];

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
