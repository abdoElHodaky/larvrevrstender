<?php

namespace App\RPC\Clients;

use Shared\RPC\BaseRpcClient;

/**
 * Analytics Service RPC Client for VIN OCR Service
 *
 * Handles RPC communication with the Analytics Service for VIN OCR-related
 * event tracking, metrics collection, performance monitoring, and
 * business intelligence operations.
 *
 * This client provides comprehensive analytics operations needed for
 * VIN OCR processing workflows including OCR performance tracking,
 * accuracy metrics, and processing analytics.
 */
class AnalyticsServiceRpcClient extends BaseRpcClient
{
    public function __construct()
    {
        parent::__construct('analytics-service', [
            'timeout' => 30,
            'retries' => 3,
            'circuit_breaker' => true,
            'trace_requests' => true,
        ]);
    }

    /**
     * Track VIN OCR processing event
     *
     * @param int $customerId Customer ID
     * @param string $vin VIN number
     * @param array $ocrData OCR processing details
     * @return array Event tracking result
     */
    public function trackVinOcrProcessing(int $customerId, string $vin, array $ocrData): array
    {
        return $this->call('analytics.track_vin_ocr_processing', [
            'customer_id' => $customerId,
            'vin' => $vin,
            'ocr_data' => $ocrData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Track OCR accuracy metrics
     *
     * @param string $vin VIN number
     * @param float $confidence OCR confidence score
     * @param bool $validationResult Validation result
     * @param array $accuracyData Accuracy details
     * @return array Event tracking result
     */
    public function trackOcrAccuracyMetrics(string $vin, float $confidence, bool $validationResult, array $accuracyData): array
    {
        return $this->call('analytics.track_ocr_accuracy_metrics', [
            'vin' => $vin,
            'confidence' => $confidence,
            'validation_result' => $validationResult,
            'accuracy_data' => $accuracyData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Track VIN processing performance
     *
     * @param string $vin VIN number
     * @param float $processingTime Processing time in seconds
     * @param array $performanceData Performance metrics
     * @return array Event tracking result
     */
    public function trackVinProcessingPerformance(string $vin, float $processingTime, array $performanceData): array
    {
        return $this->call('analytics.track_vin_processing_performance', [
            'vin' => $vin,
            'processing_time' => $processingTime,
            'performance_data' => $performanceData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Track OCR error event
     *
     * @param string $vin VIN number
     * @param string $errorType Error type
     * @param array $errorData Error details
     * @return array Event tracking result
     */
    public function trackOcrError(string $vin, string $errorType, array $errorData): array
    {
        return $this->call('analytics.track_ocr_error', [
            'vin' => $vin,
            'error_type' => $errorType,
            'error_data' => $errorData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get VIN OCR analytics data
     *
     * @param array $filters Analytics filters
     * @return array VIN OCR analytics data
     */
    public function getVinOcrAnalytics(array $filters = []): array
    {
        return $this->call('analytics.get_vin_ocr_analytics', [
            'filters' => $filters,
        ]);
    }

    /**
     * Get OCR accuracy statistics
     *
     * @param array $dateRange Date range filter
     * @param array $filters Additional filters
     * @return array OCR accuracy statistics
     */
    public function getOcrAccuracyStatistics(array $dateRange = [], array $filters = []): array
    {
        return $this->call('analytics.get_ocr_accuracy_statistics', [
            'date_range' => $dateRange,
            'filters' => $filters,
        ]);
    }

    /**
     * Get VIN processing performance analytics
     *
     * @param array $dateRange Date range filter
     * @param array $filters Additional filters
     * @return array Performance analytics data
     */
    public function getVinProcessingPerformanceAnalytics(array $dateRange = [], array $filters = []): array
    {
        return $this->call('analytics.get_vin_processing_performance_analytics', [
            'date_range' => $dateRange,
            'filters' => $filters,
        ]);
    }

    /**
     * Track image quality metrics
     *
     * @param string $imagePath Image path
     * @param array $qualityMetrics Image quality metrics
     * @return array Event tracking result
     */
    public function trackImageQualityMetrics(string $imagePath, array $qualityMetrics): array
    {
        return $this->call('analytics.track_image_quality_metrics', [
            'image_path' => $imagePath,
            'quality_metrics' => $qualityMetrics,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get image quality analytics
     *
     * @param array $filters Analytics filters
     * @return array Image quality analytics
     */
    public function getImageQualityAnalytics(array $filters = []): array
    {
        return $this->call('analytics.get_image_quality_analytics', [
            'filters' => $filters,
        ]);
    }

    /**
     * Track VIN validation event
     *
     * @param string $vin VIN number
     * @param bool $isValid Validation result
     * @param array $validationData Validation details
     * @return array Event tracking result
     */
    public function trackVinValidation(string $vin, bool $isValid, array $validationData): array
    {
        return $this->call('analytics.track_vin_validation', [
            'vin' => $vin,
            'is_valid' => $isValid,
            'validation_data' => $validationData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get VIN validation statistics
     *
     * @param array $dateRange Date range filter
     * @param array $filters Additional filters
     * @return array VIN validation statistics
     */
    public function getVinValidationStatistics(array $dateRange = [], array $filters = []): array
    {
        return $this->call('analytics.get_vin_validation_statistics', [
            'date_range' => $dateRange,
            'filters' => $filters,
        ]);
    }

    /**
     * Track batch VIN processing event
     *
     * @param array $batchData Batch processing details
     * @return array Event tracking result
     */
    public function trackBatchVinProcessing(array $batchData): array
    {
        return $this->call('analytics.track_batch_vin_processing', [
            'batch_data' => $batchData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get batch processing analytics
     *
     * @param array $filters Analytics filters
     * @return array Batch processing analytics
     */
    public function getBatchProcessingAnalytics(array $filters = []): array
    {
        return $this->call('analytics.get_batch_processing_analytics', [
            'filters' => $filters,
        ]);
    }

    /**
     * Track OCR model performance
     *
     * @param string $modelVersion OCR model version
     * @param array $performanceMetrics Performance metrics
     * @return array Event tracking result
     */
    public function trackOcrModelPerformance(string $modelVersion, array $performanceMetrics): array
    {
        return $this->call('analytics.track_ocr_model_performance', [
            'model_version' => $modelVersion,
            'performance_metrics' => $performanceMetrics,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get OCR model performance analytics
     *
     * @param array $modelVersions Array of model versions
     * @param array $dateRange Date range filter
     * @return array Model performance analytics
     */
    public function getOcrModelPerformanceAnalytics(array $modelVersions = [], array $dateRange = []): array
    {
        return $this->call('analytics.get_ocr_model_performance_analytics', [
            'model_versions' => $modelVersions,
            'date_range' => $dateRange,
        ]);
    }

    /**
     * Generate VIN OCR analytics report
     *
     * @param string $reportType Report type
     * @param array $parameters Report parameters
     * @return array Report generation result
     */
    public function generateVinOcrAnalyticsReport(string $reportType, array $parameters = []): array
    {
        return $this->call('analytics.generate_vin_ocr_analytics_report', [
            'report_type' => $reportType,
            'parameters' => $parameters,
        ]);
    }

    /**
     * Track VIN decoding event
     *
     * @param string $vin VIN number
     * @param array $decodingResult Decoding result
     * @return array Event tracking result
     */
    public function trackVinDecoding(string $vin, array $decodingResult): array
    {
        return $this->call('analytics.track_vin_decoding', [
            'vin' => $vin,
            'decoding_result' => $decodingResult,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get VIN decoding analytics
     *
     * @param array $filters Analytics filters
     * @return array VIN decoding analytics
     */
    public function getVinDecodingAnalytics(array $filters = []): array
    {
        return $this->call('analytics.get_vin_decoding_analytics', [
            'filters' => $filters,
        ]);
    }

    /**
     * Track batch OCR events
     *
     * @param array $ocrEvents Array of OCR events
     * @return array Batch event tracking results
     */
    public function trackBatchOcrEvents(array $ocrEvents): array
    {
        $calls = [];
        foreach ($ocrEvents as $index => $event) {
            $calls[] = [
                'method' => 'analytics.track_vin_ocr_processing',
                'params' => $event,
                'id' => "track_ocr_event_{$index}",
            ];
        }

        return $this->batchCall($calls);
    }

    /**
     * Get VIN OCR dashboard data
     *
     * @param array $dateRange Date range filter
     * @param array $widgets Dashboard widgets to include
     * @return array Dashboard data
     */
    public function getVinOcrDashboard(array $dateRange = [], array $widgets = []): array
    {
        return $this->call('analytics.get_vin_ocr_dashboard', [
            'date_range' => $dateRange,
            'widgets' => $widgets,
        ]);
    }
}

