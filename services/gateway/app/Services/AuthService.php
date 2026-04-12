<?php

namespace App\Services;

use Shared\RPC\Clients\AuthServiceClient;
use Illuminate\Http\JsonResponse;

/**
 * Gateway Auth Service
 * 
 * Wrapper around RPC AuthServiceClient for gateway-specific authentication needs
 */
class AuthService
{
    private AuthServiceClient $authRpcClient;

    public function __construct(AuthServiceClient $authRpcClient)
    {
        $this->authRpcClient = $authRpcClient;
    }

    /**
     * Register a new user via auth service
     */
    public function register(array $userData): array
    {
        $response = $this->authRpcClient->register(
            $userData['email'],
            $userData['password'],
            $userData['name'] ?? '',
            $userData
        );

        return $response->getData();
    }

    /**
     * Authenticate user credentials
     */
    public function login(string $email, string $password): array
    {
        $response = $this->authRpcClient->authenticate($email, $password);
        return $response->getData();
    }

    /**
     * Validate JWT token
     */
    public function validateToken(string $token): array
    {
        $response = $this->authRpcClient->validateToken($token);
        return $response->getData();
    }

    /**
     * Refresh JWT token
     */
    public function refreshToken(string $refreshToken): array
    {
        $response = $this->authRpcClient->refreshToken($refreshToken);
        return $response->getData();
    }

    /**
     * Logout user
     */
    public function logout(string $token): array
    {
        $response = $this->authRpcClient->logout($token);
        return $response->getData();
    }

    /**
     * Get user profile
     */
    public function getProfile(string $token): array
    {
        $response = $this->authRpcClient->getProfile($token);
        return $response->getData();
    }

    /**
     * Update user profile
     */
    public function updateProfile(string $token, array $profileData): array
    {
        $response = $this->authRpcClient->updateProfile($token, $profileData);
        return $response->getData();
    }

    /**
     * Check if user has permission
     */
    public function hasPermission(int $userId, string $permission): bool
    {
        $response = $this->authRpcClient->checkPermission($userId, $permission);
        $data = $response->getData();
        return $data['has_permission'] ?? false;
    }

    /**
     * Check if user has role
     */
    public function hasRole(int $userId, string $role): bool
    {
        $response = $this->authRpcClient->checkRole($userId, $role);
        $data = $response->getData();
        return $data['has_role'] ?? false;
    }
}
