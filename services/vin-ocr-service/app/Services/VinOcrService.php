<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Carbon\Carbon;
use Shared\Core\BaseService;

class VinOcrService extends BaseService
{
    private ImageManager $imageManager;
    private array $ocrConfig;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
        $this->ocrConfig = config('services.ocr', [
            'confidence_threshold' => 0.8,
            'max_image_size' => 5 * 1024 * 1024, // 5MB
            'supported_formats' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
            'cache_ttl' => 3600 // 1 hour
        ]);
    }

    /**
     * Process VIN from image URL
     */
    public function processVinImage(
        string $imageUrl,
        ?int $userId = null,
        bool $enhanceImage = true,
        float $confidenceThreshold = 0.8
    ): array {
        try {
            Log::info('Processing VIN image', [
                'image_url' => $imageUrl,
                'user_id' => $userId,
                'enhance_image' => $enhanceImage,
                'confidence_threshold' => $confidenceThreshold
            ]);

            // Check cache first
            $cacheKey = 'vin_ocr:' . md5($imageUrl . $enhanceImage . $confidenceThreshold);
            $cachedResult = Cache::get($cacheKey);
            if ($cachedResult) {
                Log::info('VIN OCR result found in cache', ['cache_key' => $cacheKey]);
                return $cachedResult;
            }

            // Download and validate image
            $imageData = $this->downloadImage($imageUrl);
            $this->validateImage($imageData);

            // Enhance image if requested
            if ($enhanceImage) {
                $imageData = $this->enhanceImageForOcr($imageData);
            }

            // Extract VIN using OCR
            $ocrResult = $this->extractVinFromImage($imageData, $confidenceThreshold);

            // Validate extracted VIN
            $vinData = $this->validateAndEnrichVin($ocrResult['vin'] ?? '');

            $result = [
                'success' => true,
                'data' => [
                    'vin' => $ocrResult['vin'] ?? null,
                    'confidence' => $ocrResult['confidence'] ?? 0,
                    'vin_data' => $vinData,
                    'processing_time' => $ocrResult['processing_time'] ?? 0,
                    'enhanced' => $enhanceImage,
                    'processed_at' => Carbon::now()->toISOString()
                ]
            ];

            // Cache successful results
            if ($result['data']['confidence'] >= $confidenceThreshold) {
                Cache::put($cacheKey, $result, $this->ocrConfig['cache_ttl']);
            }

            Log::info('VIN OCR processing completed', [
                'vin' => $result['data']['vin'],
                'confidence' => $result['data']['confidence'],
                'user_id' => $userId
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('VIN OCR processing failed', [
                'image_url' => $imageUrl,
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'VIN OCR processing failed',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract VIN data from a VIN string
     */
    public function extractVinData(string $vin): array
    {
        try {
            $vinData = $this->validateAndEnrichVin($vin);

            return [
                'success' => true,
                'data' => $vinData
            ];
        } catch (\Exception $e) {
            Log::error('VIN data extraction failed', [
                'vin' => $vin,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'VIN data extraction failed',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate VIN format
     */
    public function validateVin(string $vin): array
    {
        try {
            $vin = strtoupper(trim($vin));

            // Basic VIN validation
            $isValid = $this->isValidVinFormat($vin);

            return [
                'success' => true,
                'data' => [
                    'vin' => $vin,
                    'is_valid' => $isValid,
                    'length' => strlen($vin),
                    'format_errors' => $isValid ? [] : $this->getVinFormatErrors($vin)
                ]
            ];
        } catch (\Exception $e) {
            Log::error('VIN validation failed', [
                'vin' => $vin,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'VIN validation failed',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Enhance image for better OCR results
     */
    public function enhanceImage(string $imageData): array
    {
        try {
            $enhancedImageData = $this->enhanceImageForOcr($imageData);

            return [
                'success' => true,
                'data' => [
                    'enhanced_image' => base64_encode($enhancedImageData),
                    'original_size' => strlen($imageData),
                    'enhanced_size' => strlen($enhancedImageData),
                    'processed_at' => Carbon::now()->toISOString()
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Image enhancement failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Image enhancement failed',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get cached OCR result
     */
    public function getCacheEntry(string $imageUrl, bool $enhanceImage = true, float $confidenceThreshold = 0.8): array
    {
        try {
            $cacheKey = 'vin_ocr:' . md5($imageUrl . $enhanceImage . $confidenceThreshold);
            $cachedResult = Cache::get($cacheKey);

            return [
                'success' => true,
                'data' => [
                    'cache_key' => $cacheKey,
                    'cached' => $cachedResult !== null,
                    'result' => $cachedResult
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Cache retrieval failed', [
                'image_url' => $imageUrl,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Cache retrieval failed',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Download image from URL
     */
    private function downloadImage(string $imageUrl): string
    {
        $response = Http::timeout(30)->get($imageUrl);

        if (!$response->successful()) {
            throw new \Exception("Failed to download image: HTTP {$response->status()}");
        }

        return $response->body();
    }

    /**
     * Validate image data
     */
    private function validateImage(string $imageData): void
    {
        if (strlen($imageData) > $this->ocrConfig['max_image_size']) {
            throw new \Exception('Image size exceeds maximum allowed size');
        }

        // Try to create image to validate format
        try {
            $this->imageManager->read($imageData);
        } catch (\Exception $e) {
            throw new \Exception('Invalid image format: ' . $e->getMessage());
        }
    }

    /**
     * Enhance image for better OCR results
     */
    private function enhanceImageForOcr(string $imageData): string
    {
        $image = $this->imageManager->read($imageData);

        // Apply image enhancements for better OCR
        $image = $image
            ->greyscale() // Convert to grayscale
            ->contrast(20) // Increase contrast
            ->brightness(10) // Slight brightness adjustment
            ->sharpen(10); // Sharpen the image

        // Resize if too large (OCR works better on moderately sized images)
        if ($image->width() > 1920 || $image->height() > 1080) {
            $image = $image->scale(width: 1920);
        }

        return $image->toPng()->toString();
    }

    /**
     * Extract VIN from image using OCR (mock implementation)
     */
    private function extractVinFromImage(string $imageData, float $confidenceThreshold): array
    {
        $startTime = microtime(true);

        // This is a mock implementation
        // In a real implementation, you would use:
        // - Tesseract OCR
        // - Google Cloud Vision API
        // - AWS Textract
        // - Azure Computer Vision
        // - Or another OCR service

        // Mock VIN extraction with random confidence
        $mockVins = [
            '1HGBH41JXMN109186',
            '2FMDK3GC4DBA12345',
            '3VWDX7AJ5DM123456',
            '4T1BF1FK5DU123456',
            '5NPE34AF5DH123456'
        ];

        $mockVin = $mockVins[array_rand($mockVins)];
        $mockConfidence = rand(70, 95) / 100; // Random confidence between 0.7 and 0.95

        $processingTime = microtime(true) - $startTime;

        // Only return VIN if confidence meets threshold
        if ($mockConfidence >= $confidenceThreshold) {
            return [
                'vin' => $mockVin,
                'confidence' => $mockConfidence,
                'processing_time' => $processingTime
            ];
        }

        return [
            'vin' => null,
            'confidence' => $mockConfidence,
            'processing_time' => $processingTime
        ];
    }

    /**
     * Validate and enrich VIN data
     */
    private function validateAndEnrichVin(string $vin): array
    {
        $vin = strtoupper(trim($vin));

        if (!$this->isValidVinFormat($vin)) {
            return [
                'vin' => $vin,
                'is_valid' => false,
                'errors' => $this->getVinFormatErrors($vin)
            ];
        }

        // Extract VIN components (simplified)
        return [
            'vin' => $vin,
            'is_valid' => true,
            'wmi' => substr($vin, 0, 3), // World Manufacturer Identifier
            'vds' => substr($vin, 3, 6), // Vehicle Descriptor Section
            'vis' => substr($vin, 9, 8), // Vehicle Identifier Section
            'year_code' => substr($vin, 9, 1),
            'plant_code' => substr($vin, 10, 1),
            'serial_number' => substr($vin, 11, 6),
            'estimated_year' => $this->getVinYear(substr($vin, 9, 1)),
            'manufacturer' => $this->getManufacturerFromWmi(substr($vin, 0, 3))
        ];
    }

    /**
     * Check if VIN format is valid
     */
    private function isValidVinFormat(string $vin): bool
    {
        // VIN must be exactly 17 characters
        if (strlen($vin) !== 17) {
            return false;
        }

        // VIN cannot contain I, O, or Q
        if (preg_match('/[IOQ]/', $vin)) {
            return false;
        }

        // VIN must contain only alphanumeric characters
        if (!preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin)) {
            return false;
        }

        return true;
    }

    /**
     * Get VIN format errors
     */
    private function getVinFormatErrors(string $vin): array
    {
        $errors = [];

        if (strlen($vin) !== 17) {
            $errors[] = 'VIN must be exactly 17 characters long';
        }

        if (preg_match('/[IOQ]/', $vin)) {
            $errors[] = 'VIN cannot contain letters I, O, or Q';
        }

        if (!preg_match('/^[A-HJ-NPR-Z0-9]*$/', $vin)) {
            $errors[] = 'VIN can only contain letters A-H, J-N, P-R, T-Z and numbers 0-9';
        }

        return $errors;
    }

    /**
     * Get estimated year from VIN year code
     */
    private function getVinYear(string $yearCode): ?int
    {
        $yearCodes = [
            'A' => 1980, 'B' => 1981, 'C' => 1982, 'D' => 1983, 'E' => 1984,
            'F' => 1985, 'G' => 1986, 'H' => 1987, 'J' => 1988, 'K' => 1989,
            'L' => 1990, 'M' => 1991, 'N' => 1992, 'P' => 1993, 'R' => 1994,
            'S' => 1995, 'T' => 1996, 'V' => 1997, 'W' => 1998, 'X' => 1999,
            'Y' => 2000, '1' => 2001, '2' => 2002, '3' => 2003, '4' => 2004,
            '5' => 2005, '6' => 2006, '7' => 2007, '8' => 2008, '9' => 2009,
        ];

        return $yearCodes[$yearCode] ?? null;
    }

    /**
     * Get manufacturer from WMI (simplified)
     */
    private function getManufacturerFromWmi(string $wmi): ?string
    {
        $manufacturers = [
            '1HG' => 'Honda',
            '2FM' => 'Ford',
            '3VW' => 'Volkswagen',
            '4T1' => 'Toyota',
            '5NP' => 'Hyundai',
            'JHM' => 'Honda',
            'KMH' => 'Hyundai',
            'WBA' => 'BMW',
            'WDD' => 'Mercedes-Benz',
            'YV1' => 'Volvo'
        ];

        return $manufacturers[$wmi] ?? 'Unknown';
    }
}
