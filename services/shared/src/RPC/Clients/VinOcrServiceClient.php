<?php

declare(strict_types=1);

namespace Shared\RPC\Clients;

use Shared\RPC\AbstractRpcClient;
use Shared\RPC\Enums\ServiceType;
use Shared\RPC\ValueObjects\RpcRequest;
use Shared\RPC\ValueObjects\RpcResponse;

/**
 * VIN OCR Service RPC Client - PHP 8.3 & Laravel 12 Implementation
 * 
 * Provides RPC communication interface for VIN OCR operations including:
 * - Image-based VIN processing
 * - Text-based VIN processing  
 * - VIN validation and extraction
 * - OCR statistics and analytics
 */
class VinOcrServiceClient extends AbstractRpcClient
{
    public function __construct($httpClient, string $environment = 'local')
    {
        parent::__construct($httpClient, ServiceType::VIN_OCR, $environment);
    }

    /**
     * Process VIN from uploaded image
     */
    public function processVinImage(array $imageData): RpcResponse
    {
        $request = RpcRequest::post('/rpc/process-vin-image', $imageData);
        return $this->call($request);
    }

    /**
     * Process VIN from text input
     */
    public function processVinText(string $vin, int $userId, ?int $vehicleId = null): RpcResponse
    {
        $request = RpcRequest::post('/rpc/process-vin-text', [
            'vin' => $vin,
            'user_id' => $userId,
            'vehicle_id' => $vehicleId,
        ]);
        return $this->call($request);
    }

    /**
     * Reprocess VIN with manual corrections
     */
    public function reprocessVin(int $vehicleId, string $correctedVin): RpcResponse
    {
        $request = RpcRequest::post('/rpc/reprocess-vin', [
            'vehicle_id' => $vehicleId,
            'corrected_vin' => $correctedVin,
        ]);
        return $this->call($request);
    }

    /**
     * Get OCR processing statistics
     */
    public function getOcrStats(?int $userId = null): RpcResponse
    {
        $params = $userId ? ['user_id' => $userId] : [];
        $request = RpcRequest::get('/rpc/ocr-stats', $params);
        return $this->call($request);
    }

    /**
     * Validate VIN format and structure
     */
    public function validateVin(string $vin): RpcResponse
    {
        $request = RpcRequest::post('/rpc/validate-vin', ['vin' => $vin]);
        return $this->call($request);
    }

    /**
     * Extract vehicle data from VIN
     */
    public function extractVinData(string $vin): RpcResponse
    {
        $request = RpcRequest::post('/rpc/extract-vin-data', ['vin' => $vin]);
        return $this->call($request);
    }

    /**
     * Get VIN processing history for a user
     */
    public function getProcessingHistory(int $userId, array $filters = []): RpcResponse
    {
        $params = array_merge(['user_id' => $userId], $filters);
        $request = RpcRequest::get('/rpc/processing-history', $params);
        return $this->call($request);
    }

    /**
     * Health check for VIN OCR service
     */
    public function healthCheck(): RpcResponse
    {
        $request = RpcRequest::get('/health');
        return $this->call($request);
    }

    /**
     * Get service information and capabilities
     */
    public function getServiceInfo(): RpcResponse
    {
        $request = RpcRequest::get('/info');
        return $this->call($request);
    }
}
