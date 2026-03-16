<?php

declare(strict_types=1);

namespace Shared\Clients;

use Shared\RPC\AbstractRpcClient;
use Shared\RPC\Enums\ServiceType;
use Shared\RPC\ValueObjects\RpcRequest;
use Shared\RPC\ValueObjects\RpcResponse;

/**
 * Auth Service RPC Client - PHP 8.3 & Laravel 12 Implementation
 * 
 * Modern, type-safe RPC client for authentication service
 * with comprehensive error handling and standardized methods.
 */
class AuthServiceClient extends AbstractRpcClient
{
    public function __construct($httpClient, string $environment = 'local')
    {
        parent::__construct($httpClient, ServiceType::AUTH, $environment);
    }

    /**
     * Authenticate user with credentials
     */
    public function authenticate(string $email, string $password): RpcResponse
    {
        $request = RpcRequest::post('/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);

        return $this->call($request);
    }

    /**
     * Register a new user
     */
    public function register(array $userData): RpcResponse
    {
        $request = RpcRequest::post('/auth/register', $userData);
        return $this->call($request);
    }

    /**
     * Validate an authentication token
     */
    public function validateToken(string $token): RpcResponse
    {
        $request = RpcRequest::post('/auth/validate', [
            'token' => $token,
        ]);

        return $this->call($request);
    }

    /**
     * Refresh an authentication token
     */
    public function refreshToken(string $refreshToken): RpcResponse
    {
        $request = RpcRequest::post('/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        return $this->call($request);
    }

    /**
     * Logout user and invalidate token
     */
    public function logout(string $token): RpcResponse
    {
        $request = RpcRequest::post('/auth/logout')
            ->withHeaders(['Authorization' => "Bearer {$token}"]);

        return $this->call($request);
    }

    /**
     * Get user profile by token
     */
    public function getUserProfile(string $token): RpcResponse
    {
        $request = RpcRequest::get('/auth/profile')
            ->withHeaders(['Authorization' => "Bearer {$token}"]);

        return $this->call($request);
    }

    /**
     * Reset user password
     */
    public function resetPassword(string $email): RpcResponse
    {
        $request = RpcRequest::post('/auth/password/reset', [
            'email' => $email,
        ]);

        return $this->call($request);
    }

    /**
     * Confirm password reset with token
     */
    public function confirmPasswordReset(string $token, string $newPassword): RpcResponse
    {
        $request = RpcRequest::post('/auth/password/confirm', [
            'token' => $token,
            'password' => $newPassword,
        ]);

        return $this->call($request);
    }

    /**
     * Generate RPC token for service-to-service communication
     */
    public function generateRpcToken(string $serviceId): RpcResponse
    {
        $request = RpcRequest::post('/auth/rpc/token', [
            'service_id' => $serviceId,
        ]);

        return $this->call($request);
    }

    /**
     * Validate RPC token for service-to-service communication
     */
    public function validateRpcToken(string $token, string $serviceId): RpcResponse
    {
        $request = RpcRequest::post('/auth/rpc/validate', [
            'token' => $token,
            'service_id' => $serviceId,
        ]);

        return $this->call($request);
    }
}
