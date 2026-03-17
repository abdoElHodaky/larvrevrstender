<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\VehicleResource;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shared\RPC\Clients\VinOcrServiceClient;
use Shared\RPC\Exceptions\RpcException;

/**
 * VIN OCR Controller - PHP 8.3 & Laravel 12 Implementation
 * 
 * Handles VIN OCR operations via RPC communication with dedicated vin-ocr-service.
 * Uses modern PHP 8.3 constructor property promotion and proper typing.
 */
class VinOcrController extends Controller
{
    public function __construct(
        private readonly VinOcrServiceClient $vinOcrClient,
        private readonly CustomerService $customerService
    ) {}

    /**
     * Process VIN from uploaded image via RPC.
     */
    public function processImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB max
        ]);

        try {
            $userId = $request->user()->id;
            $customer = $this->customerService->getProfile($userId);

            // Prepare image data for RPC call
            $imageData = [
                'image' => base64_encode(file_get_contents($validated['image']->path())),
                'image_name' => $validated['image']->getClientOriginalName(),
                'image_mime' => $validated['image']->getMimeType(),
                'user_id' => $customer->id,
                'vehicle_id' => $request->input('vehicle_id'),
                'preprocessing' => $request->boolean('preprocessing', true),
                'confidence_threshold' => $request->input('confidence_threshold', 0.8),
            ];

            $rpcResponse = $this->vinOcrClient->processVinImage($imageData);

            if (!$rpcResponse->isSuccessful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to process VIN from image',
                    'error' => $rpcResponse->getError(),
                ], $rpcResponse->getStatusCode());
            }

            $result = $rpcResponse->getData();

            $response = [
                'success' => $result['success'] ?? true,
                'vin' => $result['vin'] ?? null,
                'confidence' => $result['confidence'] ?? null,
                'image_path' => $result['image_path'] ?? null,
            ];

            if (!($result['success'] ?? true)) {
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

        } catch (RpcException $e) {
            return response()->json([
                'success' => false,
                'message' => 'RPC communication failed: ' . $e->getMessage(),
                'error_code' => 'rpc_error',
            ], 503);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process VIN from image: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process VIN from text input via RPC.
     */
    public function processText(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vin' => 'required|string|min:17|max:17',
        ]);

        try {
            $userId = $request->user()->id;
            $customer = $this->customerService->getProfile($userId);

            $rpcResponse = $this->vinOcrClient->processVinText(
                $validated['vin'],
                $customer->id,
                $request->input('vehicle_id')
            );

            if (!$rpcResponse->isSuccessful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to process VIN',
                    'error' => $rpcResponse->getError(),
                ], $rpcResponse->getStatusCode());
            }

            $result = $rpcResponse->getData();

            $response = [
                'success' => $result['success'] ?? true,
                'vin' => $result['vin'] ?? $validated['vin'],
                'confidence' => $result['confidence'] ?? null,
            ];

            if (!($result['success'] ?? true)) {
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

        } catch (RpcException $e) {
            return response()->json([
                'success' => false,
                'message' => 'RPC communication failed: ' . $e->getMessage(),
                'error_code' => 'rpc_error',
            ], 503);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process VIN: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reprocess VIN with manual corrections via RPC.
     */
    public function reprocess(Request $request, int $vehicleId): JsonResponse
    {
        $validated = $request->validate([
            'corrected_vin' => 'required|string|min:17|max:17',
        ]);

        try {
            $rpcResponse = $this->vinOcrClient->reprocessVin($vehicleId, $validated['corrected_vin']);

            if (!$rpcResponse->isSuccessful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to reprocess VIN',
                    'error' => $rpcResponse->getError(),
                ], $rpcResponse->getStatusCode());
            }

            $result = $rpcResponse->getData();

            if (!($result['success'] ?? true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to reprocess VIN',
                    'errors' => $result['errors'] ?? [],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'VIN reprocessed successfully',
                'vehicle' => new VehicleResource($result['vehicle']),
                'extracted_data' => $result['extracted_data'] ?? [],
            ]);

        } catch (RpcException $e) {
            return response()->json([
                'success' => false,
                'message' => 'RPC communication failed: ' . $e->getMessage(),
                'error_code' => 'rpc_error',
            ], 503);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reprocess VIN: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get OCR processing statistics via RPC.
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()?->id;
            $rpcResponse = $this->vinOcrClient->getOcrStats($userId);

            if (!$rpcResponse->isSuccessful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to get OCR statistics',
                    'error' => $rpcResponse->getError(),
                ], $rpcResponse->getStatusCode());
            }

            return response()->json([
                'success' => true,
                'data' => $rpcResponse->getData(),
            ]);

        } catch (RpcException $e) {
            return response()->json([
                'success' => false,
                'message' => 'RPC communication failed: ' . $e->getMessage(),
                'error_code' => 'rpc_error',
            ], 503);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get OCR statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate VIN format via RPC.
     */
    public function validateVin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vin' => 'required|string|min:17|max:17',
        ]);

        try {
            $rpcResponse = $this->vinOcrClient->validateVin($validated['vin']);

            if (!$rpcResponse->isSuccessful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to validate VIN',
                    'error' => $rpcResponse->getError(),
                ], $rpcResponse->getStatusCode());
            }

            return response()->json([
                'success' => true,
                'data' => $rpcResponse->getData(),
            ]);

        } catch (RpcException $e) {
            return response()->json([
                'success' => false,
                'message' => 'RPC communication failed: ' . $e->getMessage(),
                'error_code' => 'rpc_error',
            ], 503);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate VIN: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extract vehicle data from VIN via RPC.
     */
    public function extractData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vin' => 'required|string|min:17|max:17',
        ]);

        try {
            $rpcResponse = $this->vinOcrClient->extractVinData($validated['vin']);

            if (!$rpcResponse->isSuccessful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to extract data from VIN',
                    'error' => $rpcResponse->getError(),
                ], $rpcResponse->getStatusCode());
            }

            return response()->json([
                'success' => true,
                'data' => $rpcResponse->getData(),
            ]);

        } catch (RpcException $e) {
            return response()->json([
                'success' => false,
                'message' => 'RPC communication failed: ' . $e->getMessage(),
                'error_code' => 'rpc_error',
            ], 503);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to extract data from VIN: ' . $e->getMessage(),
            ], 500);
        }
    }
}
