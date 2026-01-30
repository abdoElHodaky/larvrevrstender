<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Vehicle Service
 * 
 * Handles communication with the user service for vehicle-related operations
 */
class VehicleService
{
    protected string $userServiceUrl;

    public function __construct()
    {
        $this->userServiceUrl = config('services.user.url', env('USER_SERVICE_URL'));
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
}
