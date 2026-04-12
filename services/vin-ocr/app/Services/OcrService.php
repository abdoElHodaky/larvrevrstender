<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Exception;
use Shared\Core\BaseService;

class OcrService extends BaseService
{
    /**
     * Extract VIN from image using OCR
     *
     * @param string $imagePath
     * @param bool $preprocessing
     * @param float $confidenceThreshold
     * @return array
     */
    public function extractVinFromImage(string $imagePath, bool $preprocessing = true, float $confidenceThreshold = 0.7): array
    {
        try {
            Log::info('Starting VIN OCR extraction', [
                'image_path' => $imagePath,
                'preprocessing' => $preprocessing,
                'confidence_threshold' => $confidenceThreshold
            ]);

            // Get the full path to the image
            $fullImagePath = Storage::path($imagePath);
            
            if (!file_exists($fullImagePath)) {
                throw new Exception("Image file not found: {$fullImagePath}");
            }

            // Preprocess image if requested
            $processedImagePath = null;
            if ($preprocessing) {
                $processedImagePath = $this->preprocessImage($imagePath);
                $fullImagePath = Storage::path($processedImagePath);
            }

            // Perform OCR extraction
            $ocrResult = $this->performOcr($fullImagePath);
            
            // Extract VIN from OCR text
            $vinData = $this->extractVinFromText($ocrResult['text'], $confidenceThreshold);

            $result = [
                'vin' => $vinData['vin'],
                'confidence' => $vinData['confidence'],
                'processed_image_path' => $processedImagePath,
                'metadata' => [
                    'ocr_engine' => $ocrResult['engine'],
                    'processing_time_ms' => $ocrResult['processing_time'],
                    'raw_text' => $ocrResult['text'],
                    'preprocessing_applied' => $preprocessing,
                    'confidence_threshold' => $confidenceThreshold,
                    'extraction_method' => $vinData['method'],
                    'detected_patterns' => $vinData['patterns'] ?? []
                ]
            ];

            Log::info('VIN OCR extraction completed', [
                'vin' => $result['vin'],
                'confidence' => $result['confidence'],
                'processing_time' => $ocrResult['processing_time']
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('VIN OCR extraction failed', [
                'image_path' => $imagePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'vin' => null,
                'confidence' => 0.0,
                'processed_image_path' => null,
                'metadata' => [
                    'error' => $e->getMessage(),
                    'ocr_engine' => 'none',
                    'processing_time_ms' => 0,
                    'preprocessing_applied' => $preprocessing
                ]
            ];
        }
    }

    /**
     * Preprocess image for better OCR results
     *
     * @param string $imagePath
     * @return string
     */
    protected function preprocessImage(string $imagePath): string
    {
        try {
            $image = Image::make(Storage::path($imagePath));
            
            // Apply image enhancements for better OCR
            $image->greyscale()
                  ->contrast(20)
                  ->brightness(10)
                  ->sharpen(10);

            // Resize if too large (optimal size for OCR)
            if ($image->width() > 1920 || $image->height() > 1080) {
                $image->resize(1920, 1080, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            }

            // Save processed image
            $processedPath = 'processed/' . pathinfo($imagePath, PATHINFO_FILENAME) . '_processed.png';
            $image->save(Storage::path($processedPath), 90, 'png');

            Log::info('Image preprocessing completed', [
                'original_path' => $imagePath,
                'processed_path' => $processedPath,
                'dimensions' => $image->width() . 'x' . $image->height()
            ]);

            return $processedPath;

        } catch (Exception $e) {
            Log::warning('Image preprocessing failed, using original', [
                'image_path' => $imagePath,
                'error' => $e->getMessage()
            ]);
            
            return $imagePath;
        }
    }

    /**
     * Perform OCR on image
     *
     * @param string $fullImagePath
     * @return array
     */
    protected function performOcr(string $fullImagePath): array
    {
        $startTime = microtime(true);
        
        // Mock OCR implementation - replace with actual OCR service
        // This could be Tesseract, Google Cloud Vision, AWS Textract, Azure Computer Vision, etc.
        
        $mockTexts = [
            // Common VIN patterns found in images
            '1HGBH41JXMN109186',
            'JH4KA7561PC008269', 
            '1G1ZT52F04F260429',
            'WBAVB13596PT12345',
            '5NPE34AF4FH123456',
            'KMHD35LH3EU123456',
            'Some random text 1HGBH41JXMN109186 more text',
            'VIN: JH4KA7561PC008269 Model: Accord',
            'Vehicle Identification Number 1G1ZT52F04F260429',
            'Serial: WBAVB13596PT12345 Year: 2006'
        ];
        
        // Simulate OCR processing time
        usleep(rand(500000, 2000000)); // 0.5-2 seconds
        
        $processingTime = (microtime(true) - $startTime) * 1000;
        
        // Return mock OCR result
        $mockText = $mockTexts[array_rand($mockTexts)];
        
        return [
            'text' => $mockText,
            'engine' => 'mock_ocr_v1.0',
            'processing_time' => round($processingTime, 2)
        ];
    }

    /**
     * Extract VIN from OCR text
     *
     * @param string $text
     * @param float $confidenceThreshold
     * @return array
     */
    protected function extractVinFromText(string $text, float $confidenceThreshold): array
    {
        // VIN pattern: 17 characters, no I, O, Q
        $vinPattern = '/[A-HJ-NPR-Z0-9]{17}/';
        
        $patterns = [];
        $bestVin = null;
        $bestConfidence = 0.0;
        $method = 'pattern_matching';
        
        // Find all potential VIN matches
        if (preg_match_all($vinPattern, strtoupper($text), $matches)) {
            foreach ($matches[0] as $match) {
                $confidence = $this->calculateVinConfidence($match, $text);
                $patterns[] = [
                    'vin' => $match,
                    'confidence' => $confidence,
                    'position' => strpos($text, $match)
                ];
                
                if ($confidence > $bestConfidence && $confidence >= $confidenceThreshold) {
                    $bestVin = $match;
                    $bestConfidence = $confidence;
                }
            }
        }
        
        // Sort patterns by confidence
        usort($patterns, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });
        
        return [
            'vin' => $bestVin,
            'confidence' => $bestConfidence,
            'method' => $method,
            'patterns' => $patterns
        ];
    }

    /**
     * Calculate confidence score for a potential VIN
     *
     * @param string $vin
     * @param string $context
     * @return float
     */
    protected function calculateVinConfidence(string $vin, string $context): float
    {
        $confidence = 0.0;
        
        // Base confidence for 17-character match
        if (strlen($vin) === 17) {
            $confidence += 0.3;
        }
        
        // Check for valid VIN format (no I, O, Q)
        if (!preg_match('/[IOQ]/', $vin)) {
            $confidence += 0.2;
        }
        
        // Check for context clues
        $contextClues = ['VIN', 'VEHICLE', 'IDENTIFICATION', 'NUMBER', 'SERIAL'];
        foreach ($contextClues as $clue) {
            if (stripos($context, $clue) !== false) {
                $confidence += 0.1;
                break;
            }
        }
        
        // Check for valid manufacturer codes (first 3 characters)
        $validWMIs = [
            '1HG', 'JH4', '1G1', 'WBA', '5NP', 'KMH', 'JHM', 'WVW', 
            '1FT', '2HG', '3VW', '4T1', '5YJ', '6G2', '7FA', '8AG'
        ];
        
        $wmi = substr($vin, 0, 3);
        if (in_array($wmi, $validWMIs)) {
            $confidence += 0.2;
        }
        
        // Check for valid year code (10th position)
        $yearCode = substr($vin, 9, 1);
        $validYearCodes = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'P', 'R', 'S', 'T', 'V', 'W', 'X', 'Y', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        if (in_array($yearCode, $validYearCodes)) {
            $confidence += 0.2;
        }
        
        return min($confidence, 1.0);
    }

    /**
     * Get supported OCR engines
     *
     * @return array
     */
    public function getSupportedEngines(): array
    {
        return [
            'mock_ocr_v1.0' => [
                'name' => 'Mock OCR Engine',
                'version' => '1.0',
                'supported_formats' => ['jpg', 'jpeg', 'png', 'gif', 'bmp'],
                'max_file_size' => '10MB',
                'features' => ['preprocessing', 'confidence_scoring', 'pattern_matching']
            ]
            // Add real OCR engines here:
            // 'tesseract' => [...],
            // 'google_vision' => [...],
            // 'aws_textract' => [...],
            // 'azure_computer_vision' => [...]
        ];
    }

    /**
     * Validate OCR configuration
     *
     * @return bool
     */
    public function validateConfiguration(): bool
    {
        try {
            // Check if required directories exist
            $requiredDirs = ['processed'];
            foreach ($requiredDirs as $dir) {
                if (!Storage::exists($dir)) {
                    Storage::makeDirectory($dir);
                }
            }
            
            // Check if Intervention Image is available
            if (!class_exists('Intervention\Image\Facades\Image')) {
                Log::warning('Intervention Image not available for preprocessing');
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            Log::error('OCR configuration validation failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
