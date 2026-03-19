<?php

namespace App\Jobs;

use App\Models\VinScan;
use App\Models\OcrResult;
use App\Services\VinOcrService;
use App\Services\VinValidationService;
use Shared\Jobs\BaseQueueJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * VIN OCR Batch Processing Job with Laravel Fuse Circuit Breaker Protection
 * 
 * Processes batches of VIN images for OCR recognition. This is critical for user onboarding
 * workflows where users upload vehicle documents that need VIN extraction. Handles batch
 * processing to improve efficiency and provides circuit breaker protection against OCR
 * service outages.
 */
class ProcessVinOcrBatchJob extends BaseQueueJob
{
    public array $vinScanIds;
    public array $processingOptions;
    public int $batchSize;
    public int $tries = 3;
    public int $timeout = 1800; // 30 minutes for batch processing

    /**
     * Create a new job instance.
     */
    public function __construct(
        array $vinScanIds,
        array $processingOptions = [],
        int $batchSize = 10
    ) {
        // Initialize parent with circuit breaker configuration
        parent::__construct();
        
        $this->vinScanIds = $vinScanIds;
        $this->processingOptions = array_merge([
            'enhance_image' => true,
            'confidence_threshold' => 0.8,
            'validate_vin' => true,
            'retry_failed' => true,
            'cleanup_temp_files' => true
        ], $processingOptions);
        $this->batchSize = $batchSize;
        
        // Set queue based on batch size priority
        $this->onQueue($this->getQueueForBatchSize($batchSize));
        
        // Configure circuit breaker for VIN OCR batch processing
        $this->configureCircuitBreaker([
            'service_name' => 'vin_ocr_batch_processing',
            'failure_threshold' => 40, // 40% failure rate triggers circuit breaker
            'timeout' => 180, // 3 minutes timeout for batch OCR operations
            'recovery_timeout' => 600, // 10 minutes before attempting recovery
            'tags' => [
                'service' => 'vin-ocr-service',
                'job_type' => 'batch_processing',
                'batch_size' => $batchSize,
                'priority' => 'high'
            ]
        ]);
    }

    /**
     * Execute the job with circuit breaker protection.
     */
    public function handle(VinOcrService $vinOcrService, VinValidationService $vinValidationService): void
    {
        Log::info('Starting VIN OCR batch processing with circuit breaker protection', [
            'batch_size' => count($this->vinScanIds),
            'vin_scan_ids' => $this->vinScanIds,
            'processing_options' => $this->processingOptions,
            'job_id' => $this->job?->getJobId(),
            'circuit_breaker_service' => 'vin_ocr_batch_processing'
        ]);

        // Execute with circuit breaker protection
        $this->executeWithCircuitBreaker(function() use ($vinOcrService, $vinValidationService) {
            $results = [
                'processed' => 0,
                'successful' => 0,
                'failed' => 0,
                'skipped' => 0,
                'errors' => []
            ];

            // Process VIN scans in chunks to manage memory
            $chunks = array_chunk($this->vinScanIds, $this->batchSize);
            
            foreach ($chunks as $chunkIndex => $chunk) {
                Log::debug('Processing VIN OCR chunk', [
                    'chunk_index' => $chunkIndex + 1,
                    'chunk_size' => count($chunk),
                    'total_chunks' => count($chunks)
                ]);

                $chunkResults = $this->processVinScanChunk($chunk, $vinOcrService, $vinValidationService);
                
                // Aggregate results
                $results['processed'] += $chunkResults['processed'];
                $results['successful'] += $chunkResults['successful'];
                $results['failed'] += $chunkResults['failed'];
                $results['skipped'] += $chunkResults['skipped'];
                $results['errors'] = array_merge($results['errors'], $chunkResults['errors']);
            }

            // Cleanup temporary files if requested
            if ($this->processingOptions['cleanup_temp_files']) {
                $this->cleanupTemporaryFiles();
            }

            Log::info('VIN OCR batch processing completed successfully', [
                'total_processed' => $results['processed'],
                'successful' => $results['successful'],
                'failed' => $results['failed'],
                'skipped' => $results['skipped'],
                'success_rate' => $results['processed'] > 0 ? 
                    round(($results['successful'] / $results['processed']) * 100, 2) : 0,
                'job_id' => $this->job?->getJobId()
            ]);

            return $results;
        }, function(\Exception $e) {
            // Circuit breaker failure handler
            Log::error('VIN OCR batch processing failed with circuit breaker protection', [
                'batch_size' => count($this->vinScanIds),
                'vin_scan_ids' => $this->vinScanIds,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_id' => $this->job?->getJobId(),
            ]);

            // Mark all scans as failed
            $this->markBatchAsFailed($e->getMessage());

            throw $e;
        });
    }

    /**
     * Process a chunk of VIN scans
     */
    private function processVinScanChunk(
        array $vinScanIds, 
        VinOcrService $vinOcrService, 
        VinValidationService $vinValidationService
    ): array {
        $results = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        // Load VIN scans for this chunk
        $vinScans = VinScan::whereIn('id', $vinScanIds)
            ->where('status', '!=', 'completed')
            ->get();

        foreach ($vinScans as $vinScan) {
            try {
                $result = $this->processSingleVinScan($vinScan, $vinOcrService, $vinValidationService);
                
                $results['processed']++;
                
                if ($result['success']) {
                    $results['successful']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = [
                        'vin_scan_id' => $vinScan->id,
                        'error' => $result['error'] ?? 'Unknown error'
                    ];
                }

            } catch (\Exception $e) {
                $results['processed']++;
                $results['failed']++;
                $results['errors'][] = [
                    'vin_scan_id' => $vinScan->id,
                    'error' => $e->getMessage()
                ];

                Log::error('Failed to process VIN scan', [
                    'vin_scan_id' => $vinScan->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                // Mark scan as failed
                $vinScan->update([
                    'status' => 'failed',
                    'processed_at' => now()
                ]);
            }
        }

        return $results;
    }

    /**
     * Process a single VIN scan
     */
    private function processSingleVinScan(
        VinScan $vinScan, 
        VinOcrService $vinOcrService, 
        VinValidationService $vinValidationService
    ): array {
        $startTime = microtime(true);

        Log::debug('Processing single VIN scan', [
            'vin_scan_id' => $vinScan->id,
            'image_path' => $vinScan->image_path,
            'current_status' => $vinScan->status
        ]);

        // Mark as processing
        $vinScan->update(['status' => 'processing']);

        try {
            // Get image URL or path
            $imageUrl = $this->getImageUrl($vinScan);
            
            if (!$imageUrl) {
                throw new \Exception('No valid image URL found for VIN scan');
            }

            // Process VIN image using OCR service
            $ocrResult = $vinOcrService->processVinImage(
                $imageUrl,
                $vinScan->user_id,
                $this->processingOptions['enhance_image'],
                $this->processingOptions['confidence_threshold']
            );

            if (!$ocrResult['success'] || empty($ocrResult['data']['vin'])) {
                throw new \Exception('OCR failed to extract VIN from image');
            }

            $extractedVin = $ocrResult['data']['vin'];
            $confidence = $ocrResult['data']['confidence'];

            // Validate VIN if requested
            $vinData = null;
            if ($this->processingOptions['validate_vin']) {
                $vinData = $vinValidationService->validateVin($extractedVin);
            }

            $processingTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            // Update VIN scan with results
            $vinScan->update([
                'vin_number' => $extractedVin,
                'confidence_score' => $confidence,
                'status' => 'completed',
                'processed_at' => now(),
                'processing_time_ms' => round($processingTime)
            ]);

            // Create OCR result record
            OcrResult::create([
                'vin_scan_id' => $vinScan->id,
                'extracted_text' => $extractedVin,
                'confidence_score' => $confidence,
                'processing_time_ms' => round($processingTime),
                'ocr_engine' => 'tesseract', // or whatever engine is used
                'image_enhanced' => $this->processingOptions['enhance_image'],
                'validation_data' => $vinData ? json_encode($vinData) : null
            ]);

            Log::info('VIN scan processed successfully', [
                'vin_scan_id' => $vinScan->id,
                'extracted_vin' => $extractedVin,
                'confidence' => $confidence,
                'processing_time_ms' => round($processingTime)
            ]);

            return [
                'success' => true,
                'vin' => $extractedVin,
                'confidence' => $confidence,
                'processing_time_ms' => round($processingTime),
                'vin_data' => $vinData
            ];

        } catch (\Exception $e) {
            $processingTime = (microtime(true) - $startTime) * 1000;

            // Update scan as failed
            $vinScan->update([
                'status' => 'failed',
                'processed_at' => now(),
                'processing_time_ms' => round($processingTime)
            ]);

            Log::error('VIN scan processing failed', [
                'vin_scan_id' => $vinScan->id,
                'error' => $e->getMessage(),
                'processing_time_ms' => round($processingTime)
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'processing_time_ms' => round($processingTime)
            ];
        }
    }

    /**
     * Get image URL for VIN scan
     */
    private function getImageUrl(VinScan $vinScan): ?string
    {
        // Priority order: CDN URL > URL > file path > image path
        if ($vinScan->cdn_url) {
            return $vinScan->cdn_url;
        }

        if ($vinScan->url) {
            return $vinScan->url;
        }

        if ($vinScan->file_path && Storage::exists($vinScan->file_path)) {
            return Storage::url($vinScan->file_path);
        }

        if ($vinScan->image_path && Storage::exists($vinScan->image_path)) {
            return Storage::url($vinScan->image_path);
        }

        return null;
    }

    /**
     * Mark entire batch as failed
     */
    private function markBatchAsFailed(string $errorMessage): void
    {
        try {
            VinScan::whereIn('id', $this->vinScanIds)
                ->where('status', '!=', 'completed')
                ->update([
                    'status' => 'failed',
                    'processed_at' => now()
                ]);

            Log::warning('Marked VIN scan batch as failed', [
                'vin_scan_ids' => $this->vinScanIds,
                'error' => $errorMessage
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark VIN scan batch as failed', [
                'vin_scan_ids' => $this->vinScanIds,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Cleanup temporary files
     */
    private function cleanupTemporaryFiles(): void
    {
        try {
            // Clean up any temporary files created during processing
            $tempPath = storage_path('app/temp/vin-ocr');
            
            if (is_dir($tempPath)) {
                $files = glob($tempPath . '/*');
                $cleanedCount = 0;
                
                foreach ($files as $file) {
                    if (is_file($file) && filemtime($file) < (time() - 3600)) { // Files older than 1 hour
                        unlink($file);
                        $cleanedCount++;
                    }
                }

                Log::debug('Cleaned up temporary VIN OCR files', [
                    'files_cleaned' => $cleanedCount,
                    'temp_path' => $tempPath
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to cleanup temporary VIN OCR files', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get queue name based on batch size
     */
    private function getQueueForBatchSize(int $batchSize): string
    {
        return match (true) {
            $batchSize >= 100 => 'vin-ocr-large',
            $batchSize >= 50 => 'vin-ocr-medium',
            $batchSize >= 10 => 'vin-ocr-small',
            default => 'vin-ocr-default',
        };
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('VIN OCR batch processing job failed permanently', [
            'batch_size' => count($this->vinScanIds),
            'vin_scan_ids' => $this->vinScanIds,
            'processing_options' => $this->processingOptions,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'job_id' => $this->job?->getJobId(),
        ]);

        // Mark all scans as failed
        $this->markBatchAsFailed('Job failed permanently: ' . $exception->getMessage());

        // Could broadcast failure event for monitoring
        // broadcast(new \App\Events\VinOcr\BatchProcessingFailed(...));
    }
}
