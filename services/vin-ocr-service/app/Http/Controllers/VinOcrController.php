<?php

namespace App\Http\Controllers;

use App\Services\OcrService;
use App\Services\VinValidationService;
use App\Models\VinOcrLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VinOcrController extends Controller
{
    protected OcrService $ocrService;
    protected VinValidationService $vinValidationService;

    public function __construct(OcrService $ocrService, VinValidationService $vinValidationService)
    {
        $this->ocrService = $ocrService;
        $this->vinValidationService = $vinValidationService;
    }

    /**
     * Process VIN from uploaded image
     */
    public function processVinImage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB max
            'vehicle_id' => 'nullable|integer',
            'user_id' => 'required|integer',
            'preprocessing' => 'nullable|boolean',
            'confidence_threshold' => 'nullable|numeric|min:0|max:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Store the uploaded image
            $image = $request->file('image');
            $imagePath = $this->storeImage($image);

            // Create OCR log entry
            $ocrLog = VinOcrLog::create([
                'vehicle_id' => $request->vehicle_id,
                'user_id' => $request->user_id,
                'original_image_path' => $imagePath,
                'status' => 'processing'
            ]);

            // Process the image with OCR
            $result = $this->ocrService->extractVinFromImage(
                $imagePath,
                $request->boolean('preprocessing', true),
                $request->input('confidence_threshold', 0.7)
            );

            // Update OCR log with results
            $ocrLog->update([
                'extracted_vin' => $result['vin'],
                'confidence_score' => $result['confidence'],
                'ocr_metadata' => $result['metadata'],
                'processed_image_path' => $result['processed_image_path'] ?? null,
                'status' => $result['vin'] ? 'completed' : 'failed'
            ]);

            // Validate VIN if extracted
            $validationResult = null;
            if ($result['vin']) {
                $validationResult = $this->vinValidationService->validateVin($result['vin']);
                
                // Update log with validation results
                $ocrLog->update([
                    'validation_result' => $validationResult
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'VIN processing completed',
                'data' => [
                    'ocr_log_id' => $ocrLog->id,
                    'extracted_vin' => $result['vin'],
                    'confidence_score' => $result['confidence'],
                    'validation_result' => $validationResult,
                    'status' => $ocrLog->status,
                    'metadata' => $result['metadata']
                ]
            ], 200);

        } catch (\Exception $e) {
            // Update log status to failed if exists
            if (isset($ocrLog)) {
                $ocrLog->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'VIN processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get OCR processing status
     */
    public function getOcrStatus(int $ocrLogId): JsonResponse
    {
        try {
            $ocrLog = VinOcrLog::findOrFail($ocrLogId);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $ocrLog->id,
                    'status' => $ocrLog->status,
                    'extracted_vin' => $ocrLog->extracted_vin,
                    'confidence_score' => $ocrLog->confidence_score,
                    'validation_result' => $ocrLog->validation_result,
                    'created_at' => $ocrLog->created_at,
                    'updated_at' => $ocrLog->updated_at
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'OCR log not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get OCR history for a user or vehicle
     */
    public function getOcrHistory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer',
            'vehicle_id' => 'nullable|integer',
            'status' => 'nullable|string|in:processing,completed,failed',
            'limit' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = VinOcrLog::query();

            if ($request->user_id) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->vehicle_id) {
                $query->where('vehicle_id', $request->vehicle_id);
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            $logs = $query->orderBy('created_at', 'desc')
                         ->paginate($request->input('limit', 20));

            return response()->json([
                'success' => true,
                'data' => $logs
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get OCR history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate VIN manually
     */
    public function validateVin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vin' => 'required|string|size:17'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $validationResult = $this->vinValidationService->validateVin($request->vin);

            return response()->json([
                'success' => true,
                'data' => $validationResult
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'VIN validation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get vehicle information by VIN
     */
    public function getVehicleInfo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vin' => 'required|string|size:17'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $vehicleInfo = $this->vinValidationService->getVehicleInfo($request->vin);

            return response()->json([
                'success' => true,
                'data' => $vehicleInfo
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get vehicle information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reprocess failed OCR
     */
    public function reprocessOcr(int $ocrLogId): JsonResponse
    {
        try {
            $ocrLog = VinOcrLog::findOrFail($ocrLogId);

            if ($ocrLog->status === 'processing') {
                return response()->json([
                    'success' => false,
                    'message' => 'OCR is already processing'
                ], 400);
            }

            // Update status to processing
            $ocrLog->update(['status' => 'processing']);

            // Reprocess the image
            $result = $this->ocrService->extractVinFromImage(
                $ocrLog->original_image_path,
                true,
                0.7
            );

            // Update with new results
            $ocrLog->update([
                'extracted_vin' => $result['vin'],
                'confidence_score' => $result['confidence'],
                'ocr_metadata' => $result['metadata'],
                'processed_image_path' => $result['processed_image_path'] ?? null,
                'status' => $result['vin'] ? 'completed' : 'failed'
            ]);

            // Validate VIN if extracted
            $validationResult = null;
            if ($result['vin']) {
                $validationResult = $this->vinValidationService->validateVin($result['vin']);
                $ocrLog->update(['validation_result' => $validationResult]);
            }

            return response()->json([
                'success' => true,
                'message' => 'OCR reprocessing completed',
                'data' => [
                    'ocr_log_id' => $ocrLog->id,
                    'extracted_vin' => $result['vin'],
                    'confidence_score' => $result['confidence'],
                    'validation_result' => $validationResult,
                    'status' => $ocrLog->status
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'OCR reprocessing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store uploaded image
     */
    private function storeImage($image): string
    {
        $filename = Str::uuid() . '.' . $image->getClientOriginalExtension();
        $path = 'vin-images/' . date('Y/m/d') . '/' . $filename;
        
        Storage::disk('public')->put($path, file_get_contents($image));
        
        return $path;
    }

    /**
     * Get OCR statistics
     */
    public function getOcrStatistics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'user_id' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = VinOcrLog::query();

            if ($request->start_date) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }

            if ($request->end_date) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            if ($request->user_id) {
                $query->where('user_id', $request->user_id);
            }

            $statistics = [
                'total_processed' => $query->count(),
                'successful' => $query->where('status', 'completed')->count(),
                'failed' => $query->where('status', 'failed')->count(),
                'processing' => $query->where('status', 'processing')->count(),
                'average_confidence' => $query->where('status', 'completed')->avg('confidence_score'),
                'success_rate' => 0
            ];

            if ($statistics['total_processed'] > 0) {
                $statistics['success_rate'] = ($statistics['successful'] / $statistics['total_processed']) * 100;
            }

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get OCR statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

