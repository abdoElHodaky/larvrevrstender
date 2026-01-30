<?php

namespace App\Http\Controllers;

use App\Services\VinOcrService;
use App\Services\CustomerService;
use App\Http\Resources\VehicleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VinOcrController extends Controller
{
    private VinOcrService $vinOcrService;
    private CustomerService $customerService;

    public function __construct(VinOcrService $vinOcrService, CustomerService $customerService)
    {
        $this->vinOcrService = $vinOcrService;
        $this->customerService = $customerService;
    }

    /**
     * Process VIN from uploaded image.
     */
    public function processImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB max
        ]);

        try {
            $userId = $request->user()->id;
            $customer = $this->customerService->getProfile($userId);
            
            $result = $this->vinOcrService->processVinFromImage($customer->id, $request->file('image'));
            
            $response = [
                'success' => $result['success'],
                'vin' => $result['vin'],
                'confidence' => $result['confidence'],
                'image_path' => $result['image_path'] ?? null,
            ];
            
            if (!$result['success']) {
                $response['error'] = $result['error'] ?? 'Failed to process VIN from image';
                $response['validation_errors'] = $result['validation_errors'] ?? [];
                
                return response()->json($response, 422);
            }
            
            // Include vehicle data if created
            if (isset($result['vehicle'])) {
                $response['vehicle'] = new VehicleResource($result['vehicle']);
                $response['message'] = 'VIN processed successfully and vehicle added';
            } else {
                $response['message'] = 'VIN extracted but requires manual verification';
                $response['extracted_data'] = $result['extracted_data'] ?? [];
            }
            
            return response()->json($response, 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process VIN from image: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process VIN from text input.
     */
    public function processText(Request $request): JsonResponse
    {
        $request->validate([
            'vin' => 'required|string|min:17|max:17',
        ]);

        try {
            $userId = $request->user()->id;
            $customer = $this->customerService->getProfile($userId);
            
            $result = $this->vinOcrService->processVinFromText($customer->id, $request->vin);
            
            $response = [
                'success' => $result['success'],
                'vin' => $result['vin'],
                'confidence' => $result['confidence'],
            ];
            
            if (!$result['success']) {
                $response['error'] = $result['error'] ?? 'Failed to process VIN';
                $response['validation_errors'] = $result['validation_errors'] ?? [];
                
                return response()->json($response, 422);
            }
            
            // Include vehicle data if created
            if (isset($result['vehicle'])) {
                $response['vehicle'] = new VehicleResource($result['vehicle']);
                $response['message'] = 'VIN processed successfully and vehicle added';
                $response['extracted_data'] = $result['extracted_data'] ?? [];
            }
            
            return response()->json($response, 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process VIN: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reprocess VIN with manual corrections.
     */
    public function reprocess(Request $request, int $vehicleId): JsonResponse
    {
        $request->validate([
            'corrected_vin' => 'required|string|min:17|max:17',
        ]);

        try {
            $result = $this->vinOcrService->reprocessVinWithCorrections($vehicleId, $request->corrected_vin);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to reprocess VIN',
                    'errors' => $result['errors'] ?? []
                ], 422);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'VIN reprocessed successfully',
                'vehicle' => new VehicleResource($result['vehicle']),
                'extracted_data' => $result['extracted_data'] ?? []
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reprocess VIN: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get OCR processing statistics.
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->vinOcrService->getOcrStats();
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get OCR statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate VIN format.
     */
    public function validateVin(Request $request): JsonResponse
    {
        $request->validate([
            'vin' => 'required|string|min:17|max:17',
        ]);

        try {
            // Use the VIN OCR service's validation logic
            $cleanVin = strtoupper(trim($request->vin));
            $cleanVin = preg_replace('/[^A-HJ-NPR-Z0-9]/', '', $cleanVin);
            
            $errors = [];
            
            // Check length
            if (strlen($cleanVin) !== 17) {
                $errors[] = 'VIN must be exactly 17 characters long';
            }
            
            // Check for invalid characters
            if (preg_match('/[IOQ]/', $cleanVin)) {
                $errors[] = 'VIN contains invalid characters (I, O, Q are not allowed)';
            }
            
            // Check format
            if (!preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $cleanVin)) {
                $errors[] = 'VIN format is invalid';
            }
            
            $valid = empty($errors);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'vin' => $cleanVin,
                    'valid' => $valid,
                    'errors' => $errors,
                    'original_vin' => $request->vin
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate VIN: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extract vehicle data from VIN.
     */
    public function extractData(Request $request): JsonResponse
    {
        $request->validate([
            'vin' => 'required|string|min:17|max:17',
        ]);

        try {
            $cleanVin = strtoupper(trim($request->vin));
            $cleanVin = preg_replace('/[^A-HJ-NPR-Z0-9]/', '', $cleanVin);
            
            if (strlen($cleanVin) !== 17) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid VIN length'
                ], 422);
            }
            
            // Extract basic information from VIN structure
            $wmi = substr($cleanVin, 0, 3); // World Manufacturer Identifier
            $vds = substr($cleanVin, 3, 6); // Vehicle Descriptor Section
            $vis = substr($cleanVin, 9, 8); // Vehicle Identifier Section
            
            // Decode year from position 10
            $yearCodes = [
                'A' => 2010, 'B' => 2011, 'C' => 2012, 'D' => 2013, 'E' => 2014,
                'F' => 2015, 'G' => 2016, 'H' => 2017, 'J' => 2018, 'K' => 2019,
                'L' => 2020, 'M' => 2021, 'N' => 2022, 'P' => 2023, 'R' => 2024,
                '1' => 2001, '2' => 2002, '3' => 2003, '4' => 2004, '5' => 2005,
                '6' => 2006, '7' => 2007, '8' => 2008, '9' => 2009
            ];
            
            $year = $yearCodes[$cleanVin[9]] ?? null;
            
            // Try to match brand from WMI
            $wmiBrands = [
                '1HG' => 'Honda', '1G1' => 'Chevrolet', 'JH4' => 'Acura',
                'WBA' => 'BMW', 'WDD' => 'Mercedes-Benz', '4T1' => 'Toyota',
                'JN1' => 'Nissan', 'KMH' => 'Hyundai', 'KNA' => 'Kia',
                '5NP' => 'Hyundai', 'SAL' => 'Land Rover', 'WAU' => 'Audi',
                'WVW' => 'Volkswagen',
            ];
            
            $brandName = $wmiBrands[$wmi] ?? null;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'vin' => $cleanVin,
                    'year' => $year,
                    'brand_name' => $brandName,
                    'wmi' => $wmi,
                    'vds' => $vds,
                    'vis' => $vis,
                    'decoded_sections' => [
                        'world_manufacturer_identifier' => $wmi,
                        'vehicle_descriptor_section' => $vds,
                        'vehicle_identifier_section' => $vis
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to extract data from VIN: ' . $e->getMessage()
            ], 500);
        }
    }
}

