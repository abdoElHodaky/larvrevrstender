<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * RPC Client for VIN OCR Service
 * 
 * Provides RPC-based communication with the VIN OCR service for
 * VIN recognition, processing, and vehicle data extraction.
 */
class VinOcrServiceRpcClient extends BaseRpcClient
{
    public function __construct()
    {
        parent::__construct('vin-ocr-service', [
            'timeout' => 60, // Longer timeout for OCR processing
            'retries' => 2, // Fewer retries for expensive operations
            'circuit_breaker' => true,
            'trace_requests' => true,
        ]);
    }
    
    /**
     * Process VIN from image
     *
     * @param array $imageData Image data for VIN extraction
     * @return array RPC response with VIN processing result
     */
    public function processVinImage(array $imageData): array
    {
        return $this->call('vin.processImage', $imageData);
    }
    
    /**
     * Process VIN from text
     *
     * @param string $vinText VIN text to process
     * @return array RPC response with VIN processing result
     */
    public function processVinText(string $vinText): array
    {
        return $this->call('vin.processText', [
            'vin_text' => $vinText,
        ]);
    }
    
    /**
     * Validate VIN format
     *
     * @param string $vin VIN to validate
     * @return array RPC response with validation result
     */
    public function validateVin(string $vin): array
    {
        return $this->call('vin.validate', [
            'vin' => $vin,
        ]);
    }
    
    /**
     * Decode VIN information
     *
     * @param string $vin VIN to decode
     * @return array RPC response with decoded VIN information
     */
    public function decodeVin(string $vin): array
    {
        return $this->call('vin.decode', [
            'vin' => $vin,
        ]);
    }
    
    /**
     * Get vehicle information by VIN
     *
     * @param string $vin VIN number
     * @return array RPC response with vehicle information
     */
    public function getVehicleInfo(string $vin): array
    {
        return $this->call('vin.getVehicleInfo', [
            'vin' => $vin,
        ]);
    }
    
    /**
     * Process batch VIN images
     *
     * @param array $images Array of image data
     * @return array RPC response with batch processing results
     */
    public function processBatchImages(array $images): array
    {
        return $this->call('vin.processBatchImages', [
            'images' => $images,
        ]);
    }
    
    /**
     * Get VIN processing history
     *
     * @param array $filters Optional filters
     * @param int $limit Number of records
     * @param int $offset Pagination offset
     * @return array RPC response with processing history
     */
    public function getProcessingHistory(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        return $this->call('vin.getProcessingHistory', [
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
    
    /**
     * Get VIN processing status
     *
     * @param string $processId Processing ID
     * @return array RPC response with processing status
     */
    public function getProcessingStatus(string $processId): array
    {
        return $this->call('vin.getProcessingStatus', [
            'process_id' => $processId,
        ]);
    }
    
    /**
     * Get supported image formats
     *
     * @return array RPC response with supported formats
     */
    public function getSupportedFormats(): array
    {
        return $this->call('vin.getSupportedFormats');
    }
    
    /**
     * Get OCR confidence threshold settings
     *
     * @return array RPC response with threshold settings
     */
    public function getConfidenceThresholds(): array
    {
        return $this->call('vin.getConfidenceThresholds');
    }
    
    /**
     * Update OCR confidence threshold settings
     *
     * @param array $thresholds New threshold settings
     * @return array RPC response
     */
    public function updateConfidenceThresholds(array $thresholds): array
    {
        return $this->call('vin.updateConfidenceThresholds', [
            'thresholds' => $thresholds,
        ]);
    }
    
    /**
     * Get VIN processing statistics
     *
     * @param array $filters Optional filters
     * @return array RPC response with processing statistics
     */
    public function getProcessingStatistics(array $filters = []): array
    {
        return $this->call('vin.getProcessingStatistics', [
            'filters' => $filters,
        ]);
    }
    
    /**
     * Enhance image quality for better OCR
     *
     * @param array $imageData Image data to enhance
     * @param array $enhancementOptions Enhancement options
     * @return array RPC response with enhanced image
     */
    public function enhanceImage(array $imageData, array $enhancementOptions = []): array
    {
        return $this->call('vin.enhanceImage', [
            'image_data' => $imageData,
            'enhancement_options' => $enhancementOptions,
        ]);
    }
    
    /**
     * Extract text regions from image
     *
     * @param array $imageData Image data
     * @return array RPC response with text regions
     */
    public function extractTextRegions(array $imageData): array
    {
        return $this->call('vin.extractTextRegions', $imageData);
    }
    
    /**
     * Verify VIN against database
     *
     * @param string $vin VIN to verify
     * @param array $verificationOptions Verification options
     * @return array RPC response with verification result
     */
    public function verifyVinDatabase(string $vin, array $verificationOptions = []): array
    {
        return $this->call('vin.verifyDatabase', [
            'vin' => $vin,
            'verification_options' => $verificationOptions,
        ]);
    }
    
    /**
     * Get VIN recall information
     *
     * @param string $vin VIN number
     * @return array RPC response with recall information
     */
    public function getRecallInfo(string $vin): array
    {
        return $this->call('vin.getRecallInfo', [
            'vin' => $vin,
        ]);
    }
    
    /**
     * Get vehicle specifications by VIN
     *
     * @param string $vin VIN number
     * @return array RPC response with vehicle specifications
     */
    public function getVehicleSpecs(string $vin): array
    {
        return $this->call('vin.getVehicleSpecs', [
            'vin' => $vin,
        ]);
    }
    
    /**
     * Export VIN processing results
     *
     * @param array $exportData Export parameters
     * @return array RPC response with export result
     */
    public function exportProcessingResults(array $exportData): array
    {
        return $this->call('vin.exportResults', $exportData);
    }
    
    /**
     * Batch operation: Process multiple VIN texts
     *
     * @param array $vins Array of VIN texts
     * @return array Array of RPC responses
     */
    public function processBatchVinTexts(array $vins): array
    {
        $calls = [];
        foreach ($vins as $vin) {
            $calls[] = [
                'method' => 'vin.processText',
                'params' => ['vin_text' => $vin],
            ];
        }
        
        return $this->batchCall($calls);
    }
    
    /**
     * Batch operation: Decode multiple VINs
     *
     * @param array $vins Array of VIN numbers
     * @return array Array of RPC responses
     */
    public function decodeBatchVins(array $vins): array
    {
        $calls = [];
        foreach ($vins as $vin) {
            $calls[] = [
                'method' => 'vin.decode',
                'params' => ['vin' => $vin],
            ];
        }
        
        return $this->batchCall($calls);
    }
}

