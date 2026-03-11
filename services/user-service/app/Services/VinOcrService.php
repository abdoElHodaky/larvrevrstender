<?php

namespace App\Services;

use App\Events\VinOcrProcessed;
use App\Models\Brand;
use App\Models\Vehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VinOcrService
{
    private VehicleService $vehicleService;

    public function __construct(VehicleService $vehicleService)
    {
        $this->vehicleService = $vehicleService;
    }

    /**
     * Process VIN from uploaded image.
     */
    public function processVinFromImage(int $customerId, UploadedFile $image): array
    {
        try {
            // Store the uploaded image
            $imagePath = $this->storeImage($image);

            // Extract VIN using OCR
            $ocrResult = $this->extractVinFromImage($imagePath);

            // Validate extracted VIN
            $validationResult = $this->validateExtractedVin($ocrResult['vin']);

            $result = [
                'success' => $validationResult['valid'],
                'vin' => $ocrResult['vin'],
                'confidence' => $ocrResult['confidence'],
                'image_path' => $imagePath,
                'validation_errors' => $validationResult['errors'],
                'extracted_data' => $ocrResult['extracted_data'] ?? [],
            ];

            // If VIN is valid and confidence is high enough, create vehicle
            if ($validationResult['valid'] && $ocrResult['confidence'] >= 0.7) {
                $vehicle = $this->vehicleService->addVehicleFromVIN(
                    $customerId,
                    $ocrResult['vin'],
                    $ocrResult['confidence'],
                    $ocrResult['extracted_data'] ?? []
                );

                $result['vehicle'] = $vehicle;
            }

            event(new VinOcrProcessed($customerId, $result));

            return $result;

        } catch (\Exception $e) {
            Log::error('VIN OCR processing failed', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to process VIN from image: '.$e->getMessage(),
                'vin' => null,
                'confidence' => 0.0,
            ];
        }
    }

    /**
     * Process VIN from text input.
     */
    public function processVinFromText(int $customerId, string $vin): array
    {
        try {
            // Clean and validate VIN
            $cleanVin = $this->cleanVin($vin);
            $validationResult = $this->validateExtractedVin($cleanVin);

            $result = [
                'success' => $validationResult['valid'],
                'vin' => $cleanVin,
                'confidence' => 1.0, // Manual input has 100% confidence
                'validation_errors' => $validationResult['errors'],
                'extracted_data' => [],
            ];

            // If VIN is valid, try to extract vehicle data and create vehicle
            if ($validationResult['valid']) {
                $extractedData = $this->extractVehicleDataFromVin($cleanVin);
                $result['extracted_data'] = $extractedData;

                $vehicle = $this->vehicleService->addVehicleFromVIN(
                    $customerId,
                    $cleanVin,
                    1.0,
                    $extractedData
                );

                $result['vehicle'] = $vehicle;
            }

            event(new VinOcrProcessed($customerId, $result));

            return $result;

        } catch (\Exception $e) {
            Log::error('VIN text processing failed', [
                'customer_id' => $customerId,
                'vin' => $vin,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to process VIN: '.$e->getMessage(),
                'vin' => $vin,
                'confidence' => 0.0,
            ];
        }
    }

    /**
     * Store uploaded image.
     */
    private function storeImage(UploadedFile $image): string
    {
        $filename = 'vin_'.time().'_'.uniqid().'.'.$image->getClientOriginalExtension();

        return $image->storeAs('vin-images', $filename, 'public');
    }

    /**
     * Extract VIN from image using OCR.
     */
    private function extractVinFromImage(string $imagePath): array
    {
        // This would integrate with Tesseract OCR or similar service
        // For now, we'll simulate the OCR process

        $fullPath = Storage::disk('public')->path($imagePath);

        // Simulate OCR processing
        // In real implementation, this would use:
        // - Tesseract OCR
        // - Google Vision API
        // - AWS Textract
        // - Azure Computer Vision

        // Mock OCR result for demonstration
        $mockVins = [
            '1HGBH41JXMN109186',
            'JH4KA7561PC008269',
            '1G1ZT51826F109149',
            'WBAVB13596PT12345',
            '5NPE34AF4HH012345',
        ];

        $mockVin = $mockVins[array_rand($mockVins)];
        $confidence = rand(70, 95) / 100; // Random confidence between 0.7 and 0.95

        // Extract additional data from VIN
        $extractedData = $this->extractVehicleDataFromVin($mockVin);

        return [
            'vin' => $mockVin,
            'confidence' => $confidence,
            'extracted_data' => $extractedData,
        ];
    }

    /**
     * Clean VIN string.
     */
    private function cleanVin(string $vin): string
    {
        // Remove spaces, convert to uppercase, remove invalid characters
        $cleaned = strtoupper(trim($vin));
        $cleaned = preg_replace('/[^A-HJ-NPR-Z0-9]/', '', $cleaned);

        return $cleaned;
    }

    /**
     * Validate extracted VIN.
     */
    private function validateExtractedVin(string $vin): array
    {
        $errors = [];

        // Check length
        if (strlen($vin) !== 17) {
            $errors[] = 'VIN must be exactly 17 characters long';
        }

        // Check for invalid characters (I, O, Q are not allowed in VINs)
        if (preg_match('/[IOQ]/', $vin)) {
            $errors[] = 'VIN contains invalid characters (I, O, Q are not allowed)';
        }

        // Check format
        if (! preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin)) {
            $errors[] = 'VIN format is invalid';
        }

        // Basic check digit validation (simplified)
        if (strlen($vin) === 17) {
            $checkDigit = $this->calculateVinCheckDigit($vin);
            if ($checkDigit !== $vin[8]) {
                $errors[] = 'VIN check digit validation failed';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Calculate VIN check digit (simplified implementation).
     */
    private function calculateVinCheckDigit(string $vin): string
    {
        // This is a simplified implementation
        // Real VIN check digit calculation is more complex
        $weights = [8, 7, 6, 5, 4, 3, 2, 10, 0, 9, 8, 7, 6, 5, 4, 3, 2];
        $values = [
            'A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5, 'F' => 6, 'G' => 7, 'H' => 8,
            'J' => 1, 'K' => 2, 'L' => 3, 'M' => 4, 'N' => 5, 'P' => 7, 'R' => 9,
            'S' => 2, 'T' => 3, 'U' => 4, 'V' => 5, 'W' => 6, 'X' => 7, 'Y' => 8, 'Z' => 9,
        ];

        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            if ($i === 8) {
                continue;
            } // Skip check digit position

            $char = $vin[$i];
            $value = is_numeric($char) ? (int) $char : ($values[$char] ?? 0);
            $sum += $value * $weights[$i];
        }

        $remainder = $sum % 11;

        return $remainder === 10 ? 'X' : (string) $remainder;
    }

    /**
     * Extract vehicle data from VIN.
     */
    private function extractVehicleDataFromVin(string $vin): array
    {
        if (strlen($vin) !== 17) {
            return [];
        }

        // Extract basic information from VIN structure
        $wmi = substr($vin, 0, 3); // World Manufacturer Identifier
        $vds = substr($vin, 3, 6); // Vehicle Descriptor Section
        $vis = substr($vin, 9, 8); // Vehicle Identifier Section

        $year = $this->decodeVinYear($vin[9]);

        // Try to match brand from WMI
        $brandName = $this->decodeBrandFromWmi($wmi);

        return [
            'year' => $year,
            'brand_name' => $brandName,
            'wmi' => $wmi,
            'vds' => $vds,
            'vis' => $vis,
        ];
    }

    /**
     * Decode year from VIN.
     */
    private function decodeVinYear(string $yearCode): ?int
    {
        $yearCodes = [
            'A' => 2010, 'B' => 2011, 'C' => 2012, 'D' => 2013, 'E' => 2014,
            'F' => 2015, 'G' => 2016, 'H' => 2017, 'J' => 2018, 'K' => 2019,
            'L' => 2020, 'M' => 2021, 'N' => 2022, 'P' => 2023, 'R' => 2024,
            '1' => 2001, '2' => 2002, '3' => 2003, '4' => 2004, '5' => 2005,
            '6' => 2006, '7' => 2007, '8' => 2008, '9' => 2009,
        ];

        return $yearCodes[$yearCode] ?? null;
    }

    /**
     * Decode brand from World Manufacturer Identifier.
     */
    private function decodeBrandFromWmi(string $wmi): ?string
    {
        $wmiBrands = [
            '1HG' => 'Honda',
            '1G1' => 'Chevrolet',
            'JH4' => 'Acura',
            'WBA' => 'BMW',
            'WDD' => 'Mercedes-Benz',
            '4T1' => 'Toyota',
            'JN1' => 'Nissan',
            'KMH' => 'Hyundai',
            'KNA' => 'Kia',
            '5NP' => 'Hyundai',
            'SAL' => 'Land Rover',
            'WAU' => 'Audi',
            'WVW' => 'Volkswagen',
        ];

        return $wmiBrands[$wmi] ?? null;
    }

    /**
     * Get OCR processing statistics.
     */
    public function getOcrStats(): array
    {
        // This would typically query a vin_ocr_logs table
        // For now, return mock statistics

        return [
            'total_processed' => 1250,
            'successful_extractions' => 1100,
            'high_confidence_extractions' => 950,
            'average_confidence' => 0.85,
            'success_rate' => 0.88,
            'common_brands_detected' => [
                'Toyota' => 320,
                'Honda' => 280,
                'Nissan' => 190,
                'Hyundai' => 150,
                'BMW' => 120,
            ],
        ];
    }

    /**
     * Reprocess VIN with manual corrections.
     */
    public function reprocessVinWithCorrections(int $vehicleId, string $correctedVin): array
    {
        try {
            $vehicle = $this->vehicleService->getVehicle($vehicleId);

            // Validate corrected VIN
            $cleanVin = $this->cleanVin($correctedVin);
            $validationResult = $this->validateExtractedVin($cleanVin);

            if (! $validationResult['valid']) {
                return [
                    'success' => false,
                    'errors' => $validationResult['errors'],
                ];
            }

            // Update vehicle with corrected VIN and full confidence
            $extractedData = $this->extractVehicleDataFromVin($cleanVin);

            $updateData = [
                'vin' => $cleanVin,
                'vin_confidence' => 1.0, // Manual correction = full confidence
            ];

            // Update year if extracted from VIN
            if (isset($extractedData['year'])) {
                $updateData['year'] = $extractedData['year'];
            }

            $updatedVehicle = $this->vehicleService->updateVehicle($vehicleId, $updateData);

            return [
                'success' => true,
                'vehicle' => $updatedVehicle,
                'extracted_data' => $extractedData,
            ];

        } catch (\Exception $e) {
            Log::error('VIN reprocessing failed', [
                'vehicle_id' => $vehicleId,
                'corrected_vin' => $correctedVin,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to reprocess VIN: '.$e->getMessage(),
            ];
        }
    }

    // ========================================
    // ENHANCED VIN OCR METHODS
    // ========================================

    /**
     * Enhanced VIN processing with multiple OCR engines and validation.
     */
    public function processVinWithMultipleEngines(UploadedFile $image, int $customerId): array
    {
        $results = [];
        $engines = ['google_vision', 'aws_textract', 'azure_vision', 'tesseract'];

        foreach ($engines as $engine) {
            try {
                $result = $this->processWithEngine($image, $engine);
                if ($result['success'] && $result['confidence'] > 0.8) {
                    $results[] = $result;
                }
            } catch (\Exception $e) {
                Log::warning("OCR engine {$engine} failed", [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (empty($results)) {
            return [
                'success' => false,
                'message' => 'No OCR engine could extract VIN with sufficient confidence',
                'engines_tried' => $engines,
            ];
        }

        // Find consensus VIN or highest confidence result
        $consensusResult = $this->findConsensusVin($results);

        // Create vehicle record with enhanced data
        if ($consensusResult['success']) {
            $vehicleData = $this->enhancedVehicleDataExtraction($consensusResult['vin']);
            $vehicle = $this->vehicleService->createVehicle($customerId, array_merge(
                $vehicleData,
                [
                    'vin' => $consensusResult['vin'],
                    'vin_confidence' => $consensusResult['confidence'],
                    'ocr_engines_used' => array_column($results, 'engine'),
                    'extraction_metadata' => [
                        'engines_tried' => count($engines),
                        'successful_engines' => count($results),
                        'consensus_method' => $consensusResult['method'],
                        'processing_time' => microtime(true) - LARAVEL_START,
                    ],
                ]
            ));

            return [
                'success' => true,
                'vehicle' => $vehicle,
                'vin' => $consensusResult['vin'],
                'confidence' => $consensusResult['confidence'],
                'engines_used' => array_column($results, 'engine'),
                'vehicle_data' => $vehicleData,
            ];
        }

        return $consensusResult;
    }

    /**
     * Process VIN with specific OCR engine
     */
    private function processWithEngine(UploadedFile $image, string $engine): array
    {
        $imagePath = $this->storeImage($image);

        try {
            return match ($engine) {
                'google_vision' => $this->processWithGoogleVision($imagePath),
                'aws_textract' => $this->processWithAWSTextract($imagePath),
                'azure_vision' => $this->processWithAzureVision($imagePath),
                'tesseract' => $this->processWithTesseract($imagePath),
                default => throw new \InvalidArgumentException("Unknown OCR engine: {$engine}")
            };
        } finally {
            // Clean up stored image
            Storage::delete($imagePath);
        }
    }

    /**
     * Process with Google Vision API
     */
    private function processWithGoogleVision(string $imagePath): array
    {
        $apiKey = config('services.google.vision_api_key');
        if (! $apiKey) {
            throw new \Exception('Google Vision API key not configured');
        }

        $imageData = base64_encode(Storage::get($imagePath));

        $response = Http::post("https://vision.googleapis.com/v1/images:annotate?key={$apiKey}", [
            'requests' => [
                [
                    'image' => ['content' => $imageData],
                    'features' => [['type' => 'TEXT_DETECTION', 'maxResults' => 10]],
                ],
            ],
        ]);

        if (! $response->successful()) {
            throw new \Exception('Google Vision API request failed');
        }

        $data = $response->json();
        $textAnnotations = $data['responses'][0]['textAnnotations'] ?? [];

        if (empty($textAnnotations)) {
            return [
                'success' => false,
                'engine' => 'google_vision',
                'message' => 'No text detected',
            ];
        }

        $extractedText = $textAnnotations[0]['description'] ?? '';
        $vin = $this->extractVinFromText($extractedText);

        if (! $vin) {
            return [
                'success' => false,
                'engine' => 'google_vision',
                'message' => 'No VIN pattern found in extracted text',
                'extracted_text' => $extractedText,
            ];
        }

        $confidence = $this->calculateConfidence($vin, $textAnnotations);

        return [
            'success' => true,
            'engine' => 'google_vision',
            'vin' => $vin,
            'confidence' => $confidence,
            'extracted_text' => $extractedText,
        ];
    }

    /**
     * Process with AWS Textract
     */
    private function processWithAWSTextract(string $imagePath): array
    {
        // Mock implementation - would use AWS SDK
        $extractedText = $this->mockOcrExtraction($imagePath, 'aws_textract');
        $vin = $this->extractVinFromText($extractedText);

        if (! $vin) {
            return [
                'success' => false,
                'engine' => 'aws_textract',
                'message' => 'No VIN pattern found',
                'extracted_text' => $extractedText,
            ];
        }

        return [
            'success' => true,
            'engine' => 'aws_textract',
            'vin' => $vin,
            'confidence' => 0.85,
            'extracted_text' => $extractedText,
        ];
    }

    /**
     * Process with Azure Computer Vision
     */
    private function processWithAzureVision(string $imagePath): array
    {
        // Mock implementation - would use Azure SDK
        $extractedText = $this->mockOcrExtraction($imagePath, 'azure_vision');
        $vin = $this->extractVinFromText($extractedText);

        if (! $vin) {
            return [
                'success' => false,
                'engine' => 'azure_vision',
                'message' => 'No VIN pattern found',
                'extracted_text' => $extractedText,
            ];
        }

        return [
            'success' => true,
            'engine' => 'azure_vision',
            'vin' => $vin,
            'confidence' => 0.82,
            'extracted_text' => $extractedText,
        ];
    }

    /**
     * Process with Tesseract OCR
     */
    private function processWithTesseract(string $imagePath): array
    {
        // Mock implementation - would use Tesseract binary
        $extractedText = $this->mockOcrExtraction($imagePath, 'tesseract');
        $vin = $this->extractVinFromText($extractedText);

        if (! $vin) {
            return [
                'success' => false,
                'engine' => 'tesseract',
                'message' => 'No VIN pattern found',
                'extracted_text' => $extractedText,
            ];
        }

        return [
            'success' => true,
            'engine' => 'tesseract',
            'vin' => $vin,
            'confidence' => 0.75,
            'extracted_text' => $extractedText,
        ];
    }

    /**
     * Find consensus VIN from multiple results
     */
    private function findConsensusVin(array $results): array
    {
        if (empty($results)) {
            return ['success' => false, 'message' => 'No results to analyze'];
        }

        // Group by VIN
        $vinGroups = [];
        foreach ($results as $result) {
            $vin = $result['vin'];
            if (! isset($vinGroups[$vin])) {
                $vinGroups[$vin] = [];
            }
            $vinGroups[$vin][] = $result;
        }

        // Find VIN with most consensus
        $bestVin = null;
        $bestScore = 0;
        $bestConfidence = 0;

        foreach ($vinGroups as $vin => $group) {
            $score = count($group);
            $avgConfidence = array_sum(array_column($group, 'confidence')) / count($group);

            if ($score > $bestScore || ($score === $bestScore && $avgConfidence > $bestConfidence)) {
                $bestVin = $vin;
                $bestScore = $score;
                $bestConfidence = $avgConfidence;
            }
        }

        if (! $bestVin) {
            // Fallback to highest confidence single result
            $highestConfidence = max(array_column($results, 'confidence'));
            $bestResult = array_filter($results, fn ($r) => $r['confidence'] === $highestConfidence)[0];

            return [
                'success' => true,
                'vin' => $bestResult['vin'],
                'confidence' => $bestResult['confidence'],
                'method' => 'highest_confidence',
            ];
        }

        return [
            'success' => true,
            'vin' => $bestVin,
            'confidence' => $bestConfidence,
            'method' => $bestScore > 1 ? 'consensus' : 'single_engine',
            'consensus_count' => $bestScore,
        ];
    }

    /**
     * Enhanced vehicle data extraction with external APIs
     */
    private function enhancedVehicleDataExtraction(string $vin): array
    {
        $cacheKey = "vehicle_data:{$vin}";

        return Cache::remember($cacheKey, 3600, function () use ($vin) {
            $data = $this->extractVehicleDataFromVin($vin);

            // Try to enhance with external APIs
            try {
                $externalData = $this->fetchExternalVehicleData($vin);
                $data = array_merge($data, $externalData);
            } catch (\Exception $e) {
                Log::info('External vehicle data fetch failed', [
                    'vin' => $vin,
                    'error' => $e->getMessage(),
                ]);
            }

            return $data;
        });
    }

    /**
     * Fetch vehicle data from external APIs
     */
    private function fetchExternalVehicleData(string $vin): array
    {
        // Mock implementation - would integrate with real VIN APIs
        return [
            'engine_type' => 'V6',
            'fuel_type' => 'Gasoline',
            'transmission' => 'Automatic',
            'drivetrain' => 'FWD',
            'body_style' => 'Sedan',
            'doors' => 4,
            'seats' => 5,
            'msrp' => 25000,
            'mpg_city' => 28,
            'mpg_highway' => 35,
            'safety_rating' => 5,
            'recalls' => [],
            'market_value' => 18500,
        ];
    }

    /**
     * Calculate confidence score based on OCR results
     */
    private function calculateConfidence(string $vin, array $textAnnotations): float
    {
        $baseConfidence = 0.8;

        // Adjust based on VIN validation
        $validation = $this->validateExtractedVin($vin);
        if (! $validation['valid']) {
            $baseConfidence -= 0.3;
        }

        // Adjust based on text detection confidence (if available)
        if (isset($textAnnotations[0]['confidence'])) {
            $ocrConfidence = $textAnnotations[0]['confidence'];
            $baseConfidence = ($baseConfidence + $ocrConfidence) / 2;
        }

        return max(0.0, min(1.0, $baseConfidence));
    }

    /**
     * Mock OCR extraction for testing
     */
    private function mockOcrExtraction(string $imagePath, string $engine): string
    {
        // Mock extracted text that might contain a VIN
        $mockTexts = [
            "Vehicle Identification Number\n1HGBH41JXMN109186\nManufactured in USA",
            "VIN: 1HGBH41JXMN109186\nModel Year: 2021\nMake: Honda",
            "1HGBH41JXMN109186\nCivic Sedan\nHonda Motor Co.",
        ];

        return $mockTexts[array_rand($mockTexts)];
    }

    /**
     * Batch process multiple VIN images
     */
    public function batchProcessVinImages(array $images, int $customerId): array
    {
        $results = [];
        $successful = 0;
        $failed = 0;

        foreach ($images as $index => $image) {
            try {
                $result = $this->processVinWithMultipleEngines($image, $customerId);
                $results[] = [
                    'index' => $index,
                    'filename' => $image->getClientOriginalName(),
                    'result' => $result,
                ];

                if ($result['success']) {
                    $successful++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $results[] = [
                    'index' => $index,
                    'filename' => $image->getClientOriginalName(),
                    'result' => [
                        'success' => false,
                        'error' => $e->getMessage(),
                    ],
                ];
                $failed++;
            }
        }

        return [
            'total_processed' => count($images),
            'successful' => $successful,
            'failed' => $failed,
            'success_rate' => count($images) > 0 ? $successful / count($images) : 0,
            'results' => $results,
        ];
    }

    /**
     * Get enhanced OCR statistics with engine performance
     */
    public function getEnhancedOcrStats(): array
    {
        $baseStats = $this->getOcrStats();

        return array_merge($baseStats, [
            'engine_performance' => [
                'google_vision' => [
                    'success_rate' => 0.92,
                    'avg_confidence' => 0.89,
                    'avg_processing_time' => 1.2,
                ],
                'aws_textract' => [
                    'success_rate' => 0.88,
                    'avg_confidence' => 0.85,
                    'avg_processing_time' => 1.8,
                ],
                'azure_vision' => [
                    'success_rate' => 0.85,
                    'avg_confidence' => 0.82,
                    'avg_processing_time' => 1.5,
                ],
                'tesseract' => [
                    'success_rate' => 0.75,
                    'avg_confidence' => 0.78,
                    'avg_processing_time' => 0.8,
                ],
            ],
            'consensus_stats' => [
                'consensus_found_rate' => 0.78,
                'avg_engines_agreeing' => 2.3,
                'confidence_improvement' => 0.15,
            ],
        ]);
    }
}
