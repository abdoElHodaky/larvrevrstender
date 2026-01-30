<?php

namespace App\Http\Controllers;

use App\Services\VehicleService;
use App\Services\CustomerService;
use App\Http\Requests\CreateVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Http\Resources\VehicleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    private VehicleService $vehicleService;
    private CustomerService $customerService;

    public function __construct(VehicleService $vehicleService, CustomerService $customerService)
    {
        $this->vehicleService = $vehicleService;
        $this->customerService = $customerService;
    }

    /**
     * Get customer vehicles.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $customer = $this->customerService->getProfile($userId);
            $vehicles = $this->vehicleService->getCustomerVehicles($customer->id);
            
            return response()->json([
                'success' => true,
                'data' => VehicleResource::collection($vehicles)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get vehicles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific vehicle.
     */
    public function show(int $vehicleId): JsonResponse
    {
        try {
            $vehicle = $this->vehicleService->getVehicle($vehicleId);
            
            return response()->json([
                'success' => true,
                'data' => new VehicleResource($vehicle)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found'
            ], 404);
        }
    }

    /**
     * Add new vehicle.
     */
    public function store(CreateVehicleRequest $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $customer = $this->customerService->getProfile($userId);
            
            // Validate vehicle data
            $validation = $this->vehicleService->validateVehicleData($request->validated());
            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors']
                ], 422);
            }
            
            $vehicle = $this->vehicleService->addVehicle($customer->id, $request->validated());
            
            return response()->json([
                'success' => true,
                'message' => 'Vehicle added successfully',
                'data' => new VehicleResource($vehicle)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add vehicle: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update vehicle.
     */
    public function update(UpdateVehicleRequest $request, int $vehicleId): JsonResponse
    {
        try {
            // Validate vehicle data
            $validation = $this->vehicleService->validateVehicleData($request->validated());
            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors']
                ], 422);
            }
            
            $vehicle = $this->vehicleService->updateVehicle($vehicleId, $request->validated());
            
            return response()->json([
                'success' => true,
                'message' => 'Vehicle updated successfully',
                'data' => new VehicleResource($vehicle)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update vehicle: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set vehicle as primary.
     */
    public function setPrimary(int $vehicleId): JsonResponse
    {
        try {
            $vehicle = $this->vehicleService->setPrimaryVehicle($vehicleId);
            
            return response()->json([
                'success' => true,
                'message' => 'Primary vehicle updated successfully',
                'data' => new VehicleResource($vehicle)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to set primary vehicle: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get primary vehicle.
     */
    public function primary(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $customer = $this->customerService->getProfile($userId);
            $vehicle = $this->vehicleService->getCustomerPrimaryVehicle($customer->id);
            
            if (!$vehicle) {
                return response()->json([
                    'success' => false,
                    'message' => 'No primary vehicle found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => new VehicleResource($vehicle)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get primary vehicle: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search vehicles.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'brand_id' => 'nullable|integer|exists:brands,id',
            'model_id' => 'nullable|integer|exists:vehicle_models,id',
            'year_start' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'year_end' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'has_vin' => 'nullable|boolean',
            'min_vin_confidence' => 'nullable|numeric|min:0|max:1',
        ]);

        try {
            $filters = $request->only(['brand_id', 'model_id', 'year_start', 'year_end', 'has_vin', 'min_vin_confidence']);
            $vehicles = $this->vehicleService->searchVehicles($filters);
            
            return response()->json([
                'success' => true,
                'data' => VehicleResource::collection($vehicles)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search vehicles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get vehicle statistics.
     */
    public function stats(int $vehicleId): JsonResponse
    {
        try {
            $stats = $this->vehicleService->getVehicleStats($vehicleId);
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get vehicle statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update VIN confidence.
     */
    public function updateVinConfidence(Request $request, int $vehicleId): JsonResponse
    {
        $request->validate([
            'confidence' => 'required|numeric|min:0|max:1'
        ]);

        try {
            $vehicle = $this->vehicleService->updateVINConfidence($vehicleId, $request->confidence);
            
            return response()->json([
                'success' => true,
                'message' => 'VIN confidence updated successfully',
                'data' => new VehicleResource($vehicle)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update VIN confidence: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get vehicles by brand.
     */
    public function byBrand(int $brandId): JsonResponse
    {
        try {
            $vehicles = $this->vehicleService->getVehiclesByBrand($brandId);
            
            return response()->json([
                'success' => true,
                'data' => VehicleResource::collection($vehicles)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get vehicles by brand: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get vehicles by year range.
     */
    public function byYearRange(Request $request): JsonResponse
    {
        $request->validate([
            'start_year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'end_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
        ]);

        try {
            $vehicles = $this->vehicleService->getVehiclesByYearRange(
                $request->start_year,
                $request->end_year
            );
            
            return response()->json([
                'success' => true,
                'data' => VehicleResource::collection($vehicles)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get vehicles by year range: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get high confidence VIN vehicles.
     */
    public function highConfidenceVin(Request $request): JsonResponse
    {
        $request->validate([
            'min_confidence' => 'nullable|numeric|min:0|max:1'
        ]);

        try {
            $minConfidence = $request->min_confidence ?? 0.8;
            $vehicles = $this->vehicleService->getHighConfidenceVINVehicles($minConfidence);
            
            return response()->json([
                'success' => true,
                'data' => VehicleResource::collection($vehicles)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get high confidence VIN vehicles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete vehicle.
     */
    public function destroy(int $vehicleId): JsonResponse
    {
        try {
            $deleted = $this->vehicleService->deleteVehicle($vehicleId);
            
            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Vehicle deleted successfully'
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete vehicle'
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete vehicle: ' . $e->getMessage()
            ], 500);
        }
    }
}

