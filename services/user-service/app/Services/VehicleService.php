<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\Brand;
use App\Models\VehicleModel;
use App\Models\Trim;
use App\Models\CustomerProfile;
use App\Events\VehicleAdded;
use App\Events\VehicleUpdated;
use App\Events\PrimaryVehicleChanged;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class VehicleService
{
    /**
     * Get vehicle by ID.
     */
    public function getVehicle(int $vehicleId): Vehicle
    {
        return Vehicle::with(['brand', 'vehicleModel', 'trim', 'customer'])->findOrFail($vehicleId);
    }

    /**
     * Get customer vehicles.
     */
    public function getCustomerVehicles(int $customerId): Collection
    {
        return Vehicle::with(['brand', 'vehicleModel', 'trim'])
                     ->byCustomer($customerId)
                     ->get();
    }

    /**
     * Add vehicle to customer.
     */
    public function addVehicle(int $customerId, array $vehicleData): Vehicle
    {
        // Validate customer exists
        $customer = CustomerProfile::findOrFail($customerId);
        
        // If this is the first vehicle, make it primary
        $isFirstVehicle = $customer->vehicles()->count() === 0;
        if ($isFirstVehicle) {
            $vehicleData['is_primary'] = true;
        }
        
        $vehicleData['customer_id'] = $customerId;
        
        $vehicle = Vehicle::create($vehicleData);
        
        event(new VehicleAdded($vehicle));
        
        return $vehicle->load(['brand', 'vehicleModel', 'trim']);
    }

    /**
     * Update vehicle.
     */
    public function updateVehicle(int $vehicleId, array $vehicleData): Vehicle
    {
        $vehicle = $this->getVehicle($vehicleId);
        $vehicle->update($vehicleData);
        
        event(new VehicleUpdated($vehicle));
        
        return $vehicle->fresh(['brand', 'vehicleModel', 'trim']);
    }

    /**
     * Set vehicle as primary.
     */
    public function setPrimaryVehicle(int $vehicleId): Vehicle
    {
        $vehicle = $this->getVehicle($vehicleId);
        $oldPrimary = $vehicle->customer->primaryVehicle;
        
        $vehicle->setAsPrimary();
        
        event(new PrimaryVehicleChanged($vehicle, $oldPrimary));
        
        return $vehicle->fresh(['brand', 'vehicleModel', 'trim']);
    }

    /**
     * Delete vehicle.
     */
    public function deleteVehicle(int $vehicleId): bool
    {
        $vehicle = $this->getVehicle($vehicleId);
        
        // If this was the primary vehicle, set another as primary
        if ($vehicle->isPrimary()) {
            $nextVehicle = Vehicle::byCustomer($vehicle->customer_id)
                                 ->where('id', '!=', $vehicleId)
                                 ->first();
            
            if ($nextVehicle) {
                $nextVehicle->setAsPrimary();
            }
        }
        
        return $vehicle->delete();
    }

    /**
     * Add vehicle from VIN OCR.
     */
    public function addVehicleFromVIN(int $customerId, string $vin, float $confidence, array $extractedData = []): Vehicle
    {
        $vehicleData = array_merge([
            'customer_id' => $customerId,
            'vin' => $vin,
            'vin_confidence' => $confidence,
        ], $extractedData);
        
        // Try to match brand and model from extracted data
        if (isset($extractedData['brand_name'])) {
            $brand = Brand::where('name', 'like', '%' . $extractedData['brand_name'] . '%')->first();
            if ($brand) {
                $vehicleData['brand_id'] = $brand->id;
                
                if (isset($extractedData['model_name'])) {
                    $model = VehicleModel::where('brand_id', $brand->id)
                                       ->where('name', 'like', '%' . $extractedData['model_name'] . '%')
                                       ->first();
                    if ($model) {
                        $vehicleData['model_id'] = $model->id;
                    }
                }
            }
        }
        
        return $this->addVehicle($customerId, $vehicleData);
    }

    /**
     * Update VIN confidence after manual verification.
     */
    public function updateVINConfidence(int $vehicleId, float $confidence): Vehicle
    {
        $vehicle = $this->getVehicle($vehicleId);
        $vehicle->update(['vin_confidence' => $confidence]);
        
        event(new VehicleUpdated($vehicle));
        
        return $vehicle->fresh(['brand', 'vehicleModel', 'trim']);
    }

    /**
     * Get vehicles by brand.
     */
    public function getVehiclesByBrand(int $brandId): Collection
    {
        return Vehicle::with(['vehicleModel', 'trim', 'customer'])
                     ->byBrand($brandId)
                     ->get();
    }

    /**
     * Get vehicles by year range.
     */
    public function getVehiclesByYearRange(int $startYear, int $endYear = null): Collection
    {
        return Vehicle::with(['brand', 'vehicleModel', 'trim', 'customer'])
                     ->byYearRange($startYear, $endYear)
                     ->get();
    }

    /**
     * Search vehicles with filters.
     */
    public function searchVehicles(array $filters): Collection
    {
        $query = Vehicle::with(['brand', 'vehicleModel', 'trim', 'customer']);
        
        if (isset($filters['brand_id'])) {
            $query->byBrand($filters['brand_id']);
        }
        
        if (isset($filters['model_id'])) {
            $query->where('model_id', $filters['model_id']);
        }
        
        if (isset($filters['year_start'])) {
            $endYear = $filters['year_end'] ?? null;
            $query->byYearRange($filters['year_start'], $endYear);
        }
        
        if (isset($filters['has_vin']) && $filters['has_vin']) {
            $query->withVin();
        }
        
        if (isset($filters['min_vin_confidence'])) {
            $query->highVinConfidence($filters['min_vin_confidence']);
        }
        
        if (isset($filters['customer_id'])) {
            $query->byCustomer($filters['customer_id']);
        }
        
        return $query->get();
    }

    /**
     * Get vehicle statistics.
     */
    public function getVehicleStats(int $vehicleId): array
    {
        $vehicle = $this->getVehicle($vehicleId);
        
        return [
            'display_name' => $vehicle->display_name,
            'specifications' => $vehicle->specifications,
            'vin_confidence_level' => $vehicle->vin_confidence_level,
            'has_high_vin_confidence' => $vehicle->hasHighVinConfidence(),
            'is_primary' => $vehicle->isPrimary(),
            'vin_valid' => $vehicle->validateVin(),
            'formatted_mileage' => $vehicle->formatted_mileage,
        ];
    }

    /**
     * Validate vehicle data.
     */
    public function validateVehicleData(array $vehicleData): array
    {
        $errors = [];
        
        // Validate required fields
        if (empty($vehicleData['brand_id'])) {
            $errors[] = 'Brand is required';
        }
        
        if (empty($vehicleData['model_id'])) {
            $errors[] = 'Model is required';
        }
        
        if (empty($vehicleData['year']) || $vehicleData['year'] < 1900 || $vehicleData['year'] > date('Y') + 1) {
            $errors[] = 'Valid year is required';
        }
        
        // Validate VIN if provided
        if (!empty($vehicleData['vin'])) {
            if (strlen($vehicleData['vin']) !== 17) {
                $errors[] = 'VIN must be exactly 17 characters';
            } elseif (!preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vehicleData['vin'])) {
                $errors[] = 'VIN contains invalid characters';
            }
        }
        
        // Validate brand and model relationship
        if (!empty($vehicleData['brand_id']) && !empty($vehicleData['model_id'])) {
            $model = VehicleModel::where('id', $vehicleData['model_id'])
                               ->where('brand_id', $vehicleData['brand_id'])
                               ->first();
            if (!$model) {
                $errors[] = 'Model does not belong to the selected brand';
            }
        }
        
        // Validate trim and model relationship
        if (!empty($vehicleData['trim_id']) && !empty($vehicleData['model_id'])) {
            $trim = Trim::where('id', $vehicleData['trim_id'])
                       ->where('model_id', $vehicleData['model_id'])
                       ->first();
            if (!$trim) {
                $errors[] = 'Trim does not belong to the selected model';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get customer's primary vehicle.
     */
    public function getCustomerPrimaryVehicle(int $customerId): ?Vehicle
    {
        return Vehicle::with(['brand', 'vehicleModel', 'trim'])
                     ->byCustomer($customerId)
                     ->primary()
                     ->first();
    }

    /**
     * Get vehicles with high VIN confidence.
     */
    public function getHighConfidenceVINVehicles(float $minConfidence = 0.8): Collection
    {
        return Vehicle::with(['brand', 'vehicleModel', 'trim', 'customer'])
                     ->highVinConfidence($minConfidence)
                     ->get();
    }
}

