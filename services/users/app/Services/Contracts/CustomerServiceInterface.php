<?php

namespace App\Services\Contracts;

use App\Models\CustomerProfile;
use Illuminate\Support\Collection;

/**
 * Customer Service Contract
 * 
 * Defines the interface for customer management services
 */
interface CustomerServiceInterface
{
    /**
     * Create customer profile
     */
    public function createCustomerProfile(int $userId, array $profileData): CustomerProfile;

    /**
     * Update customer profile
     */
    public function updateCustomerProfile(int $customerId, array $profileData): CustomerProfile;

    /**
     * Get customer profile by ID
     */
    public function getCustomerProfile(int $customerId): CustomerProfile;

    /**
     * Get customer profile by user ID
     */
    public function getCustomerProfileByUserId(int $userId): ?CustomerProfile;

    /**
     * Get customer vehicles
     */
    public function getCustomerVehicles(int $customerId): Collection;

    /**
     * Add vehicle to customer
     */
    public function addCustomerVehicle(int $customerId, array $vehicleData): array;

    /**
     * Update customer vehicle
     */
    public function updateCustomerVehicle(int $customerId, int $vehicleId, array $vehicleData): array;

    /**
     * Remove customer vehicle
     */
    public function removeCustomerVehicle(int $customerId, int $vehicleId): array;

    /**
     * Get customer addresses
     */
    public function getCustomerAddresses(int $customerId): Collection;

    /**
     * Add customer address
     */
    public function addCustomerAddress(int $customerId, array $addressData): array;

    /**
     * Update customer address
     */
    public function updateCustomerAddress(int $customerId, int $addressId, array $addressData): array;

    /**
     * Remove customer address
     */
    public function removeCustomerAddress(int $customerId, int $addressId): array;
}
