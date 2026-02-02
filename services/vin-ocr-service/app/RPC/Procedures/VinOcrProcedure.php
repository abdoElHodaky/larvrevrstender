<?php

namespace App\RPC\Procedures;

use App\RPC\BaseProcedure;
use App\Services\VinOcrService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Sajya\Server\Exceptions\RuntimeException;

class VinOcrProcedure extends BaseProcedure
{
    public function __construct(
        private VinOcrService $vinOcrService
    ) {}

    /**
     * Process VIN from image
     * 
     * @param array $params
     * @return array
     */
    public function processImage(array $params): array
    {
        $this->validate($params, [
            'image_url' => 'required|url',
            'user_id' => 'sometimes|integer|min:1',
            'enhance_image' => 'sometimes|boolean',
            'confidence_threshold' => 'sometimes|numeric|min:0|max:1',
        ]);

        return $this->executeWithLogging('VinOcr@processImage', $this->sanitizeForLogging($params), function () use ($params) {
            // Rate limiting for OCR processing
            $key = 'vin_ocr:' . ($params['user_id'] ?? request()->ip());
            if (RateLimiter::tooManyAttempts($key, 20)) {
                throw new RuntimeException(
                    'Too many OCR processing attempts. Please try again later.',
                    -32007,
                    ['retry_after' => RateLimiter::availableIn($key)]
                );
            }

            try {
                $result = $this->vinOcrService->processImageUrl([
                    'image_url' => $params['image_url'],
                    'user_id' => $params['user_id'] ?? null,
                    'enhance_image' => $params['enhance_image'] ?? true,
                    'confidence_threshold' => $params['confidence_threshold'] ?? 0.8,
                ]);
                
                // Clear rate limiting on successful processing
                RateLimiter::clear($key);
                
                return [
                    'success' => true,
                    'vin' => $result['vin'],
                    'confidence' => $result['confidence'],
                    'vehicle_info' => $result['vehicle_info'] ?? null,
                    'processing_time_ms' => $result['processing_time_ms'],
                    'processed_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                // Increment rate limiting on failed processing
                RateLimiter::hit($key, 60); // 1 minute
                
                throw new RuntimeException(
                    'VIN OCR processing failed: ' . $e->getMessage(),
                    -32001,
                    ['image_url' => $params['image_url']]
                );
            }
        });
    }

    /**
     * Process VIN from base64 image
     * 
     * @param array $params
     * @return array
     */
    public function processBase64(array $params): array
    {
        $this->validate($params, [
            'image_data' => 'required|string',
            'image_format' => 'required|string|in:jpeg,jpg,png,webp',
            'user_id' => 'sometimes|integer|min:1',
            'enhance_image' => 'sometimes|boolean',
            'confidence_threshold' => 'sometimes|numeric|min:0|max:1',
        ]);

        return $this->executeWithLogging('VinOcr@processBase64', $this->sanitizeForLogging($params), function () use ($params) {
            // Rate limiting for OCR processing
            $key = 'vin_ocr:' . ($params['user_id'] ?? request()->ip());
            if (RateLimiter::tooManyAttempts($key, 20)) {
                throw new RuntimeException(
                    'Too many OCR processing attempts. Please try again later.',
                    -32007,
                    ['retry_after' => RateLimiter::availableIn($key)]
                );
            }

            try {
                $result = $this->vinOcrService->processBase64Image([
                    'image_data' => $params['image_data'],
                    'image_format' => $params['image_format'],
                    'user_id' => $params['user_id'] ?? null,
                    'enhance_image' => $params['enhance_image'] ?? true,
                    'confidence_threshold' => $params['confidence_threshold'] ?? 0.8,
                ]);
                
                // Clear rate limiting on successful processing
                RateLimiter::clear($key);
                
                return [
                    'success' => true,
                    'vin' => $result['vin'],
                    'confidence' => $result['confidence'],
                    'vehicle_info' => $result['vehicle_info'] ?? null,
                    'processing_time_ms' => $result['processing_time_ms'],
                    'processed_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                // Increment rate limiting on failed processing
                RateLimiter::hit($key, 60); // 1 minute
                
                throw new RuntimeException(
                    'VIN OCR processing failed: ' . $e->getMessage(),
                    -32001,
                    ['image_format' => $params['image_format']]
                );
            }
        });
    }

    /**
     * Validate VIN number
     * 
     * @param array $params
     * @return array
     */
    public function validateVin(array $params): array
    {
        $this->validate($params, [
            'vin' => 'required|string|size:17',
            'include_details' => 'sometimes|boolean',
        ]);

        return $this->executeWithLogging('VinOcr@validateVin', $params, function () use ($params) {
            // Check cache first
            $cacheKey = 'vin_validation:' . strtoupper($params['vin']) . ':' . 
                       ($params['include_details'] ?? false ? 'with_details' : 'no_details');
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }

            try {
                $validation = $this->vinOcrService->validateVin(
                    $params['vin'],
                    $params['include_details'] ?? false
                );
                
                $result = [
                    'success' => true,
                    'vin' => strtoupper($params['vin']),
                    'valid' => $validation['valid'],
                    'check_digit_valid' => $validation['check_digit_valid'],
                    'format_valid' => $validation['format_valid'],
                    'vehicle_info' => $validation['vehicle_info'] ?? null,
                    'validated_at' => now()->toISOString(),
                ];
                
                // Cache for 24 hours (VIN validation doesn't change)
                Cache::put($cacheKey, $result, 86400);
                
                return $result;
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'VIN validation failed: ' . $e->getMessage(),
                    -32002,
                    ['vin' => $params['vin']]
                );
            }
        });
    }

    /**
     * Decode VIN to vehicle information
     * 
     * @param array $params
     * @return array
     */
    public function decodeVin(array $params): array
    {
        $this->validate($params, [
            'vin' => 'required|string|size:17',
            'include_specifications' => 'sometimes|boolean',
            'include_recalls' => 'sometimes|boolean',
        ]);

        return $this->executeWithLogging('VinOcr@decodeVin', $params, function () use ($params) {
            // Check cache first
            $cacheKey = 'vin_decode:' . strtoupper($params['vin']) . ':' . 
                       ($params['include_specifications'] ?? false ? 'with_specs' : 'no_specs') . ':' .
                       ($params['include_recalls'] ?? false ? 'with_recalls' : 'no_recalls');
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }

            try {
                $decoded = $this->vinOcrService->decodeVin(
                    $params['vin'],
                    $params['include_specifications'] ?? true,
                    $params['include_recalls'] ?? false
                );
                
                $result = [
                    'success' => true,
                    'vin' => strtoupper($params['vin']),
                    'vehicle_info' => $decoded['vehicle_info'],
                    'specifications' => $decoded['specifications'] ?? null,
                    'recalls' => $decoded['recalls'] ?? null,
                    'decoded_at' => now()->toISOString(),
                ];
                
                // Cache for 24 hours
                Cache::put($cacheKey, $result, 86400);
                
                return $result;
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'VIN decoding failed: ' . $e->getMessage(),
                    -32003,
                    ['vin' => $params['vin']]
                );
            }
        });
    }

    /**
     * Get processing history
     * 
     * @param array $params
     * @return array
     */
    public function getHistory(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
            'status' => 'sometimes|string|in:success,failed,pending',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        return $this->executeWithLogging('VinOcr@getHistory', $params, function () use ($params) {
            try {
                $results = $this->vinOcrService->getProcessingHistory([
                    'user_id' => $params['user_id'],
                    'date_from' => $params['date_from'] ?? null,
                    'date_to' => $params['date_to'] ?? null,
                    'status' => $params['status'] ?? null,
                    'page' => $params['page'] ?? 1,
                    'per_page' => $params['per_page'] ?? 20,
                ]);
                
                return [
                    'success' => true,
                    'history' => $results['data'],
                    'pagination' => $results['pagination'],
                    'retrieved_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve processing history: ' . $e->getMessage(),
                    -32004,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Get processing statistics
     * 
     * @param array $params
     * @return array
     */
    public function getStatistics(array $params): array
    {
        $this->validate($params, [
            'period' => 'sometimes|string|in:today,week,month,quarter,year',
            'user_id' => 'sometimes|integer|min:1',
        ]);

        return $this->executeWithLogging('VinOcr@getStatistics', $params, function () use ($params) {
            $period = $params['period'] ?? 'month';
            $userId = $params['user_id'] ?? null;
            
            // Check cache first
            $cacheKey = 'vin_ocr_stats:' . $period . ':' . ($userId ?? 'all');
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }

            try {
                $statistics = $this->vinOcrService->getProcessingStatistics($period, $userId);
                
                $result = [
                    'success' => true,
                    'statistics' => $statistics,
                    'period' => $period,
                    'user_id' => $userId,
                    'generated_at' => now()->toISOString(),
                ];
                
                // Cache for 30 minutes
                Cache::put($cacheKey, $result, 1800);
                
                return $result;
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve OCR statistics: ' . $e->getMessage(),
                    -32005,
                    ['period' => $period]
                );
            }
        });
    }

    /**
     * Batch process multiple images
     * 
     * @param array $params
     * @return array
     */
    public function batchProcess(array $params): array
    {
        $this->validate($params, [
            'images' => 'required|array|min:1|max:10',
            'images.*.image_url' => 'required|url',
            'images.*.reference_id' => 'sometimes|string|max:100',
            'user_id' => 'sometimes|integer|min:1',
            'enhance_images' => 'sometimes|boolean',
            'confidence_threshold' => 'sometimes|numeric|min:0|max:1',
        ]);

        return $this->executeWithLogging('VinOcr@batchProcess', $this->sanitizeForLogging($params), function () use ($params) {
            // Rate limiting for batch processing
            $key = 'vin_ocr_batch:' . ($params['user_id'] ?? request()->ip());
            if (RateLimiter::tooManyAttempts($key, 5)) {
                throw new RuntimeException(
                    'Too many batch processing attempts. Please try again later.',
                    -32007,
                    ['retry_after' => RateLimiter::availableIn($key)]
                );
            }

            try {
                $results = $this->vinOcrService->batchProcessImages([
                    'images' => $params['images'],
                    'user_id' => $params['user_id'] ?? null,
                    'enhance_images' => $params['enhance_images'] ?? true,
                    'confidence_threshold' => $params['confidence_threshold'] ?? 0.8,
                ]);
                
                // Clear rate limiting on successful processing
                RateLimiter::clear($key);
                
                return [
                    'success' => true,
                    'batch_id' => $results['batch_id'],
                    'total_images' => count($params['images']),
                    'processed_count' => $results['processed_count'],
                    'failed_count' => $results['failed_count'],
                    'results' => $results['results'],
                    'processing_time_ms' => $results['processing_time_ms'],
                    'processed_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                // Increment rate limiting on failed processing
                RateLimiter::hit($key, 300); // 5 minutes
                
                throw new RuntimeException(
                    'Batch VIN OCR processing failed: ' . $e->getMessage(),
                    -32006,
                    ['images_count' => count($params['images'])]
                );
            }
        });
    }

    /**
     * Get supported image formats
     * 
     * @param array $params
     * @return array
     */
    public function getSupportedFormats(array $params): array
    {
        return $this->executeWithLogging('VinOcr@getSupportedFormats', $params, function () use ($params) {
            // Check cache first
            $cacheKey = 'vin_ocr_supported_formats';
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }

            try {
                $formats = $this->vinOcrService->getSupportedImageFormats();
                
                $result = [
                    'success' => true,
                    'supported_formats' => $formats['formats'],
                    'max_file_size_mb' => $formats['max_file_size_mb'],
                    'max_resolution' => $formats['max_resolution'],
                    'min_resolution' => $formats['min_resolution'],
                    'retrieved_at' => now()->toISOString(),
                ];
                
                // Cache for 24 hours (formats don't change often)
                Cache::put($cacheKey, $result, 86400);
                
                return $result;
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve supported formats: ' . $e->getMessage(),
                    -32007
                );
            }
        });
    }

    /**
     * Enhance image quality for better OCR
     * 
     * @param array $params
     * @return array
     */
    public function enhanceImage(array $params): array
    {
        $this->validate($params, [
            'image_url' => 'required|url',
            'enhancement_type' => 'sometimes|string|in:auto,contrast,brightness,sharpness,noise_reduction',
            'return_enhanced_image' => 'sometimes|boolean',
        ]);

        return $this->executeWithLogging('VinOcr@enhanceImage', $this->sanitizeForLogging($params), function () use ($params) {
            // Rate limiting for image enhancement
            $key = 'vin_enhance:' . request()->ip();
            if (RateLimiter::tooManyAttempts($key, 30)) {
                throw new RuntimeException(
                    'Too many image enhancement attempts. Please try again later.',
                    -32007,
                    ['retry_after' => RateLimiter::availableIn($key)]
                );
            }

            try {
                $result = $this->vinOcrService->enhanceImage([
                    'image_url' => $params['image_url'],
                    'enhancement_type' => $params['enhancement_type'] ?? 'auto',
                    'return_enhanced_image' => $params['return_enhanced_image'] ?? false,
                ]);
                
                // Clear rate limiting on successful enhancement
                RateLimiter::clear($key);
                
                return [
                    'success' => true,
                    'enhanced_image_url' => $result['enhanced_image_url'] ?? null,
                    'enhanced_image_data' => $result['enhanced_image_data'] ?? null,
                    'enhancement_applied' => $result['enhancement_applied'],
                    'quality_score' => $result['quality_score'],
                    'processing_time_ms' => $result['processing_time_ms'],
                    'enhanced_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                // Increment rate limiting on failed enhancement
                RateLimiter::hit($key, 60); // 1 minute
                
                throw new RuntimeException(
                    'Image enhancement failed: ' . $e->getMessage(),
                    -32008,
                    ['image_url' => $params['image_url']]
                );
            }
        });
    }

    /**
     * Get VIN decoder configuration
     * 
     * @param array $params
     * @return array
     */
    public function getDecoderConfig(array $params): array
    {
        return $this->executeWithLogging('VinOcr@getDecoderConfig', $params, function () use ($params) {
            // Check cache first
            $cacheKey = 'vin_decoder_config';
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }

            try {
                $config = $this->vinOcrService->getDecoderConfiguration();
                
                $result = [
                    'success' => true,
                    'supported_years' => $config['supported_years'],
                    'supported_manufacturers' => $config['supported_manufacturers'],
                    'data_sources' => $config['data_sources'],
                    'accuracy_rate' => $config['accuracy_rate'],
                    'last_updated' => $config['last_updated'],
                    'retrieved_at' => now()->toISOString(),
                ];
                
                // Cache for 12 hours
                Cache::put($cacheKey, $result, 43200);
                
                return $result;
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve decoder configuration: ' . $e->getMessage(),
                    -32009
                );
            }
        });
    }
}
