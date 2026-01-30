<?php

namespace App\Services;

use App\Services\Contracts\VehicleServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Vehicle Service
 * 
 * Handles communication with the user service for vehicle-related operations
 */
class VehicleService implements VehicleServiceInterface
{
    protected string $userServiceUrl;

    public function __construct(string $userServiceUrl)
    {
        $this->userServiceUrl = $userServiceUrl;
    }

    /**
     * Validate vehicle ownership
     */
    public function validateVehicleOwnership(int $vehicleId, int $customerId): bool
    {
        try {
            $response = Http::timeout(10)
                ->get("{$this->userServiceUrl}/api/internal/vehicles/{$vehicleId}/owner", [
                    'customer_id' => $customerId
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['is_owner'] ?? false;
            }

            Log::warning('Failed to validate vehicle ownership', [
                'vehicle_id' => $vehicleId,
                'customer_id' => $customerId,
                'response' => $response->body()
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Vehicle service error', [
                'vehicle_id' => $vehicleId,
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Unable to validate vehicle ownership');
        }
    }

    /**
     * Get vehicle details
     */
    public function getVehicleDetails(int $vehicleId): ?array
    {
        try {
            $response = Http::timeout(10)
                ->get("{$this->userServiceUrl}/api/internal/vehicles/{$vehicleId}");

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get vehicle details', [
                'vehicle_id' => $vehicleId,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Get vehicles by customer
     */
    public function getCustomerVehicles(int $customerId): array
    {
        try {
            $response = Http::timeout(10)
                ->get("{$this->userServiceUrl}/api/internal/customers/{$customerId}/vehicles");

            if ($response->successful()) {
                return $response->json()['vehicles'] ?? [];
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Failed to get customer vehicles', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
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
     * Get vehicle specifications by VIN
     */
    public function getVehicleSpecsByVin(string $vin): ?array
    {
        try {
            $response = Http::timeout(15)
                ->get("{$this->userServiceUrl}/api/internal/vehicles/vin/{$vin}/specs");

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get vehicle specs by VIN', [
                'vin' => $vin,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }
}
