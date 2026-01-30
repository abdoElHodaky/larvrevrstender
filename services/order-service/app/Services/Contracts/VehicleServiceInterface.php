<?php

namespace App\Services\Contracts;

/**
 * Vehicle Service Interface
 * 
 * Defines the contract for vehicle service implementations
 * Handles vehicle-related operations and ownership validation
 */
interface VehicleServiceInterface
{
    /**
     * Validate vehicle ownership
     */
    public function validateVehicleOwnership(int $vehicleId, int $customerId): bool;

    /**
     * Get vehicle details
     */
    public function getVehicleDetails(int $vehicleId): ?array;

    /**
     * Get vehicles by customer
     */
    public function getCustomerVehicles(int $customerId): array;

    /**
     * Validate VIN format
     */
    public function validateVin(string $vin): bool;

    /**
     * Get vehicle specifications by VIN
     */
    public function getVehicleSpecsByVin(string $vin): ?array;
}
