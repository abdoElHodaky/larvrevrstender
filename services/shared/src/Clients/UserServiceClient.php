<?php

declare(strict_types=1);

namespace Shared\Clients;

use Shared\RPC\AbstractRpcClient;
use Shared\RPC\Enums\ServiceType;
use Shared\RPC\ValueObjects\RpcRequest;
use Shared\RPC\ValueObjects\RpcResponse;

/**
 * User Service RPC Client - PHP 8.3 & Laravel 12 Implementation
 * 
 * Modern, type-safe RPC client for user management service
 * with comprehensive user profile and account management.
 */
class UserServiceClient extends AbstractRpcClient
{
    public function __construct($httpClient, string $environment = 'local')
    {
        parent::__construct($httpClient, ServiceType::USER, $environment);
    }

    /**
     * Get user profile by ID
     */
    public function getUserProfile(int $userId): RpcResponse
    {
        $request = RpcRequest::get("/users/{$userId}");
        return $this->call($request);
    }

    /**
     * Update user profile
     */
    public function updateUserProfile(int $userId, array $profileData): RpcResponse
    {
        $request = RpcRequest::put("/users/{$userId}", $profileData);
        return $this->call($request);
    }

    /**
     * Create new user
     */
    public function createUser(array $userData): RpcResponse
    {
        $request = RpcRequest::post('/users', $userData);
        return $this->call($request);
    }

    /**
     * Delete user account
     */
    public function deleteUser(int $userId): RpcResponse
    {
        $request = RpcRequest::delete("/users/{$userId}");
        return $this->call($request);
    }

    /**
     * Get user by email
     */
    public function getUserByEmail(string $email): RpcResponse
    {
        $request = RpcRequest::get('/users/by-email', ['email' => $email]);
        return $this->call($request);
    }

    /**
     * Search users with filters
     */
    public function searchUsers(array $filters = [], int $page = 1, int $limit = 20): RpcResponse
    {
        $request = RpcRequest::get('/users/search', [
            'filters' => $filters,
            'page' => $page,
            'limit' => $limit,
        ]);
        return $this->call($request);
    }

    /**
     * Get customer profile
     */
    public function getCustomerProfile(int $userId): RpcResponse
    {
        $request = RpcRequest::get("/users/{$userId}/customer-profile");
        return $this->call($request);
    }

    /**
     * Update customer profile
     */
    public function updateCustomerProfile(int $userId, array $customerData): RpcResponse
    {
        $request = RpcRequest::put("/users/{$userId}/customer-profile", $customerData);
        return $this->call($request);
    }

    /**
     * Get merchant profile
     */
    public function getMerchantProfile(int $userId): RpcResponse
    {
        $request = RpcRequest::get("/users/{$userId}/merchant-profile");
        return $this->call($request);
    }

    /**
     * Update merchant profile
     */
    public function updateMerchantProfile(int $userId, array $merchantData): RpcResponse
    {
        $request = RpcRequest::put("/users/{$userId}/merchant-profile", $merchantData);
        return $this->call($request);
    }

    /**
     * Verify user account
     */
    public function verifyUser(int $userId, string $verificationToken): RpcResponse
    {
        $request = RpcRequest::post("/users/{$userId}/verify", [
            'verification_token' => $verificationToken,
        ]);
        return $this->call($request);
    }

    /**
     * Suspend user account
     */
    public function suspendUser(int $userId, string $reason): RpcResponse
    {
        $request = RpcRequest::post("/users/{$userId}/suspend", [
            'reason' => $reason,
        ]);
        return $this->call($request);
    }

    /**
     * Reactivate user account
     */
    public function reactivateUser(int $userId): RpcResponse
    {
        $request = RpcRequest::post("/users/{$userId}/reactivate");
        return $this->call($request);
    }
}
