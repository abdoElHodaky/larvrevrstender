<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\Brand;
use App\Models\VehicleModel;
use App\Events\VinOcrProcessed;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

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
                'extracted_data' => $ocrResult['extracted_data'] ?? []
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
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to process VIN from image: ' . $e->getMessage(),
                'vin' => null,
                'confidence' => 0.0
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
                'extracted_data' => []
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
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to process VIN: ' . $e->getMessage(),
                'vin' => $vin,
                'confidence' => 0.0
            ];
        }
    }

    /**
     * Store uploaded image.
     */
    private function storeImage(UploadedFile $image): string
    {
        $filename = 'vin_' . time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
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
            '5NPE34AF4HH012345'
        ];
        
        $mockVin = $mockVins[array_rand($mockVins)];
        $confidence = rand(70, 95) / 100; // Random confidence between 0.7 and 0.95
        
        // Extract additional data from VIN
        $extractedData = $this->extractVehicleDataFromVin($mockVin);
        
        return [
            'vin' => $mockVin,
            'confidence' => $confidence,
            'extracted_data' => $extractedData
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
        if (!preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin)) {
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
            'errors' => $errors
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
            'S' => 2, 'T' => 3, 'U' => 4, 'V' => 5, 'W' => 6, 'X' => 7, 'Y' => 8, 'Z' => 9
        ];
        
        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            if ($i === 8) continue; // Skip check digit position
            
            $char = $vin[$i];
            $value = is_numeric($char) ? (int)$char : ($values[$char] ?? 0);
            $sum += $value * $weights[$i];
        }
        
        $remainder = $sum % 11;
        return $remainder === 10 ? 'X' : (string)$remainder;
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
            '6' => 2006, '7' => 2007, '8' => 2008, '9' => 2009
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
            ]
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
            
            if (!$validationResult['valid']) {
                return [
                    'success' => false,
                    'errors' => $validationResult['errors']
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
                'extracted_data' => $extractedData
            ];
            
        } catch (\Exception $e) {
            Log::error('VIN reprocessing failed', [
                'vehicle_id' => $vehicleId,
                'corrected_vin' => $correctedVin,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to reprocess VIN: ' . $e->getMessage()
            ];
        }
    }
}

