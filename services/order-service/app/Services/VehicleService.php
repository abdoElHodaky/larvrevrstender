<?php

namespace App\Services;

use App\Services\Contracts\VehicleServiceInterface;
use App\RPC\Adapters\UserServiceAdapter;
use Illuminate\Support\Facades\Log;

/**
 * Vehicle Service
 *
 * Handles communication with the user service for vehicle-related operations via RPC
 */
class VehicleService implements VehicleServiceInterface
{
    protected UserServiceAdapter $userAdapter;

    public function __construct(UserServiceAdapter $userAdapter)
    {
        $this->userAdapter = $userAdapter;
    }

    /**
     * Validate vehicle ownership via RPC
     */
    public function validateVehicleOwnership(int $vehicleId, int $customerId): bool
    {
        try {
            $result = $this->userAdapter->validateVehicleOwnership($vehicleId, $customerId);

            if ($result) {
                return $result['is_owner'] ?? false;
            }

            Log::warning('Failed to validate vehicle ownership via RPC', [
                'vehicle_id' => $vehicleId,
                'customer_id' => $customerId,
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Vehicle service RPC error', [
                'vehicle_id' => $vehicleId,
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Unable to validate vehicle ownership');
        }
    }

    /**
     * Get vehicle details via RPC
     */
    public function getVehicleDetails(int $vehicleId): ?array
    {
        try {
            $result = $this->userAdapter->getVehicleDetails($vehicleId);

            if ($result) {
                return $result;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get vehicle details via RPC', [
                'vehicle_id' => $vehicleId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get vehicles by customer via RPC
     */
    public function getCustomerVehicles(int $customerId): array
    {
        try {
            $result = $this->userAdapter->getCustomerVehicles($customerId);

            if ($result) {
                return $result['vehicles'] ?? [];
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Failed to get customer vehicles via RPC', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Validate VIN format
     */
    public function validateVin(string $vin): bool
    {
        // Basic VIN validation (17 characters, alphanumeric except I, O, Q)
        if (strlen($vin) !== 17) {
            return false;
        }

        return preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', strtoupper($vin)) === 1;
    }

    /**
     * Get vehicle specifications by VIN via RPC
     */
    public function getVehicleSpecsByVin(string $vin): ?array
    {
        try {
            $result = $this->userAdapter->getVehicleSpecsByVin($vin);

            if ($result) {
                return $result;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get vehicle specs by VIN via RPC', [
                'vin' => $vin,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
