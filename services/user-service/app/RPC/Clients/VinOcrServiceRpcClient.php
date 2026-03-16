<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * VIN OCR Service RPC Client for User Service
 *
 * Handles RPC communication with the VIN OCR Service for vehicle
 * identification number processing, image analysis, and vehicle
 * data extraction operations.
 *
 * This client provides comprehensive VIN OCR operations needed for
 * user vehicle management workflows including VIN extraction,
 * validation, and vehicle data retrieval.
 */
class VinOcrServiceRpcClient extends BaseRpcClient
{
    public function __construct()
    {
        parent::__construct('vin-ocr-service', [
            'timeout' => 60, // Longer timeout for OCR processing
            'retries' => 3,
            'circuit_breaker' => true,
            'trace_requests' => true,
        ]);
    }

    /**
     * Process VIN from uploaded image
     *
     * @param int $customerId Customer ID
     * @param string $imagePath Path to uploaded image
     * @return array VIN processing result
     */
    public function processVinFromImage(int $customerId, string $imagePath): array
    {
        return $this->call('vin_ocr.process_vin_from_image', [
            'customer_id' => $customerId,
            'image_path' => $imagePath,
        ]);
    }

    /**
     * Extract VIN from image using OCR
     *
     * @param string $imagePath Path to image file
     * @return array OCR extraction result
     */
    public function extractVinFromImage(string $imagePath): array
    {
        return $this->call('vin_ocr.extract_vin_from_image', [
            'image_path' => $imagePath,
        ]);
    }

    /**
     * Validate extracted VIN
     *
     * @param string $vin VIN to validate
     * @return array Validation result
     */
    public function validateExtractedVin(string $vin): array
    {
        return $this->call('vin_ocr.validate_extracted_vin', [
            'vin' => $vin,
        ]);
    }

    /**
     * Get vehicle data from VIN
     *
     * @param string $vin Vehicle identification number
     * @return array Vehicle data
     */
    public function getVehicleDataFromVin(string $vin): array
    {
        return $this->call('vin_ocr.get_vehicle_data_from_vin', [
            'vin' => $vin,
        ]);
    }

    /**
     * Process batch VIN images
     *
     * @param int $customerId Customer ID
     * @param array $imagePaths Array of image paths
     * @return array Batch processing results
     */
    public function processBatchVinImages(int $customerId, array $imagePaths): array
    {
        return $this->call('vin_ocr.process_batch_vin_images', [
            'customer_id' => $customerId,
            'image_paths' => $imagePaths,
        ]);
    }

    /**
     * Get VIN processing history for customer
     *
     * @param int $customerId Customer ID
     * @return array Processing history
     */
    public function getVinProcessingHistory(int $customerId): array
    {
        return $this->call('vin_ocr.get_vin_processing_history', [
            'customer_id' => $customerId,
        ]);
    }

    /**
     * Reprocess failed VIN extraction
     *
     * @param int $processingId Processing ID
     * @return array Reprocessing result
     */
    public function reprocessFailedVinExtraction(int $processingId): array
    {
        return $this->call('vin_ocr.reprocess_failed_vin_extraction', [
            'processing_id' => $processingId,
        ]);
    }

    /**
     * Get OCR confidence threshold settings
     *
     * @return array Confidence threshold settings
     */
    public function getOcrConfidenceThresholds(): array
    {
        return $this->call('vin_ocr.get_ocr_confidence_thresholds', []);
    }

    /**
     * Update OCR confidence thresholds
     *
     * @param array $thresholds New threshold settings
     * @return array Update result
     */
    public function updateOcrConfidenceThresholds(array $thresholds): array
    {
        return $this->call('vin_ocr.update_ocr_confidence_thresholds', [
            'thresholds' => $thresholds,
        ]);
    }

    /**
     * Get VIN OCR statistics
     *
     * @param array $filters Statistics filters
     * @return array OCR statistics
     */
    public function getVinOcrStatistics(array $filters = []): array
    {
        return $this->call('vin_ocr.get_vin_ocr_statistics', [
            'filters' => $filters,
        ]);
    }

    /**
     * Validate VIN format
     *
     * @param string $vin VIN to validate
     * @return array Format validation result
     */
    public function validateVinFormat(string $vin): array
    {
        return $this->call('vin_ocr.validate_vin_format', [
            'vin' => $vin,
        ]);
    }

    /**
     * Get supported image formats for OCR
     *
     * @return array Supported image formats
     */
    public function getSupportedImageFormats(): array
    {
        return $this->call('vin_ocr.get_supported_image_formats', []);
    }

    /**
     * Enhance image quality for better OCR
     *
     * @param string $imagePath Path to image file
     * @return array Image enhancement result
     */
    public function enhanceImageForOcr(string $imagePath): array
    {
        return $this->call('vin_ocr.enhance_image_for_ocr', [
            'image_path' => $imagePath,
        ]);
    }

    /**
     * Get VIN decoding information
     *
     * @param string $vin VIN to decode
     * @return array VIN decoding result
     */
    public function decodeVin(string $vin): array
    {
        return $this->call('vin_ocr.decode_vin', [
            'vin' => $vin,
        ]);
    }

    /**
     * Check VIN against recall database
     *
     * @param string $vin VIN to check
     * @return array Recall check result
     */
    public function checkVinRecalls(string $vin): array
    {
        return $this->call('vin_ocr.check_vin_recalls', [
            'vin' => $vin,
        ]);
    }

    /**
     * Get vehicle specifications from VIN
     *
     * @param string $vin VIN to lookup
     * @return array Vehicle specifications
     */
    public function getVehicleSpecifications(string $vin): array
    {
        return $this->call('vin_ocr.get_vehicle_specifications', [
            'vin' => $vin,
        ]);
    }

    /**
     * Store processed VIN result
     *
     * @param int $customerId Customer ID
     * @param array $vinData VIN processing data
     * @return array Storage result
     */
    public function storeProcessedVinResult(int $customerId, array $vinData): array
    {
        return $this->call('vin_ocr.store_processed_vin_result', [
            'customer_id' => $customerId,
            'vin_data' => $vinData,
        ]);
    }

    /**
     * Get VIN processing queue status
     *
     * @return array Queue status information
     */
    public function getVinProcessingQueueStatus(): array
    {
        return $this->call('vin_ocr.get_vin_processing_queue_status', []);
    }

    /**
     * Cancel VIN processing job
     *
     * @param int $jobId Processing job ID
     * @return array Cancellation result
     */
    public function cancelVinProcessingJob(int $jobId): array
    {
        return $this->call('vin_ocr.cancel_vin_processing_job', [
            'job_id' => $jobId,
        ]);
    }

    /**
     * Get batch VIN processing results
     *
     * @param array $vinNumbers Array of VIN numbers
     * @return array Batch processing results
     */
    public function getBatchVinProcessingResults(array $vinNumbers): array
    {
        $calls = [];
        foreach ($vinNumbers as $vin) {
            $calls[] = [
                'method' => 'vin_ocr.get_vehicle_data_from_vin',
                'params' => ['vin' => $vin],
                'id' => "vehicle_data_{$vin}",
            ];
        }

        return $this->batchCall($calls);
    }

    /**
     * Validate batch VINs
     *
     * @param array $vinNumbers Array of VIN numbers
     * @return array Batch validation results
     */
    public function validateBatchVins(array $vinNumbers): array
    {
        $calls = [];
        foreach ($vinNumbers as $index => $vin) {
            $calls[] = [
                'method' => 'vin_ocr.validate_extracted_vin',
                'params' => ['vin' => $vin],
                'id' => "validate_vin_{$index}",
            ];
        }

        return $this->batchCall($calls);
    }
}
