<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * RPC Client for VIN OCR Service (Analytics Context)
 * 
 * Provides RPC communication with the VIN OCR service for analytics data collection
 */
class VinOcrServiceRpcClient extends BaseRpcClient
{
    public function __construct()
    {
        parent::__construct('vin-ocr-service', [
            'timeout' => config('rpc.timeout', 30),
            'retries' => config('rpc.retry_attempts', 3),
            'retry_delay' => config('rpc.retry_delay', 1000),
        ]);
    }

    /**
     * Get VIN OCR analytics data
     */
    public function getVinOcrAnalyticsData(): array
    {
        return $this->call('vin_ocr.getAnalyticsData', [], [
            'context' => 'analytics_collection',
            'service' => 'analytics-service'
        ]);
    }

    /**
     * Get OCR processing statistics
     */
    public function getProcessingStatistics(array $filters = []): array
    {
        return $this->call('vin_ocr.getProcessingStats', [
            'filters' => $filters
        ], [
            'context' => 'analytics_collection',
            'service' => 'analytics-service'
        ]);
    }

    /**
     * Get OCR accuracy metrics
     */
    public function getAccuracyMetrics(array $filters = []): array
    {
        return $this->call('vin_ocr.getAccuracyMetrics', [
            'filters' => $filters
        ], [
            'context' => 'analytics_collection',
            'service' => 'analytics-service'
        ]);
    }

    /**
     * Get VIN validation statistics
     */
    public function getValidationStatistics(array $filters = []): array
    {
        return $this->call('vin_ocr.getValidationStats', [
            'filters' => $filters
        ], [
            'context' => 'analytics_collection',
            'service' => 'analytics-service'
        ]);
    }

    /**
     * Get processing time metrics
     */
    public function getProcessingTimeMetrics(array $filters = []): array
    {
        return $this->call('vin_ocr.getProcessingTimeMetrics', [
            'filters' => $filters
        ], [
            'context' => 'analytics_collection',
            'service' => 'analytics-service'
        ]);
    }

    /**
     * Get error analysis data
     */
    public function getErrorAnalysis(array $filters = []): array
    {
        return $this->call('vin_ocr.getErrorAnalysis', [
            'filters' => $filters
        ], [
            'context' => 'analytics_collection',
            'service' => 'analytics-service'
        ]);
    }

    /**
     * Health check for VIN OCR service
     */
    public function healthCheck(): array
    {
        return $this->call('vin_ocr.healthCheck', [], [
            'context' => 'health_check',
            'service' => 'analytics-service'
        ]);
    }

    /**
     * Get service information
     */
    public function getServiceInfo(): array
    {
        return $this->call('vin_ocr.getServiceInfo', [], [
            'context' => 'service_info',
            'service' => 'analytics-service'
        ]);
    }
}
