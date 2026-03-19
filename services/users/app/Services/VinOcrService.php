<?php

declare(strict_types=1);

namespace App\Services;

use Shared\RPC\Clients\VinOcrServiceClient;
use Shared\RPC\ValueObjects\RpcResponse;
use Shared\RPC\Exceptions\RpcException;

/**
 * VIN OCR Service - PHP 8.3 & Laravel 12 Implementation
 * 
 * Handles VIN OCR operations via RPC communication with vin-ocr-service.
 * Acts as a facade layer for user-specific VIN processing operations.
 */
final readonly class VinOcrService
{
    public function __construct(
        private VinOcrServiceClient $vinOcrClient,
    ) {}

    /**
     * Process VIN from uploaded image via RPC
     */
    public function processVinFromImage(array $imageData): RpcResponse
    {
        try {
            return $this->vinOcrClient->processVinImage($imageData);
        } catch (RpcException $e) {
            throw new RpcException(
                "Failed to process VIN from image: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Process VIN from text input via RPC
     */
    public function processVinFromText(string $vin, int $userId, ?int $vehicleId = null): RpcResponse
    {
        try {
            return $this->vinOcrClient->processVinText($vin, $userId, $vehicleId);
        } catch (RpcException $e) {
            throw new RpcException(
                "Failed to process VIN from text: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Validate VIN format via RPC
     */
    public function validateVin(string $vin): RpcResponse
    {
        try {
            return $this->vinOcrClient->validateVin($vin);
        } catch (RpcException $e) {
            throw new RpcException(
                "Failed to validate VIN: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Reprocess VIN with manual corrections via RPC
     */
    public function reprocessVin(int $vehicleId, string $correctedVin): RpcResponse
    {
        try {
            return $this->vinOcrClient->reprocessVin($vehicleId, $correctedVin);
        } catch (RpcException $e) {
            throw new RpcException(
                "Failed to reprocess VIN: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get OCR processing statistics via RPC
     */
    public function getOcrStats(?int $userId = null): RpcResponse
    {
        try {
            return $this->vinOcrClient->getOcrStats($userId);
        } catch (RpcException $e) {
            throw new RpcException(
                "Failed to get OCR stats: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Extract vehicle data from VIN via RPC
     */
    public function extractVinData(string $vin): RpcResponse
    {
        try {
            return $this->vinOcrClient->extractVinData($vin);
        } catch (RpcException $e) {
            throw new RpcException(
                "Failed to extract VIN data: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get VIN processing history for a user via RPC
     */
    public function getProcessingHistory(int $userId, array $filters = []): RpcResponse
    {
        try {
            return $this->vinOcrClient->getProcessingHistory($userId, $filters);
        } catch (RpcException $e) {
            throw new RpcException(
                "Failed to get processing history: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
