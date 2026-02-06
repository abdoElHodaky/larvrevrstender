<?php

namespace Shared\Procedures\Micro;

use Shared\Core\BaseProcedure;
use Exception;

/**
 * Security Micro Procedure
 * 
 * Provides comprehensive security infrastructure including authentication,
 * authorization, rate limiting, and security audit for cross-service operations.
 */
trait SecurityProcedure
{
    /**
     * Authenticate JWT token
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function authenticateToken(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'token' => ['required' => true, 'type' => 'string'],
                'service' => ['type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $token = $params['token'];
            $service = $params['service'] ?? 'unknown';

            // Validate JWT token
            $tokenData = $this->validateJwtToken($token);
            
            if (!$tokenData['valid']) {
                $this->recordSecurityEvent('authentication_failed', [
                    'service' => $service,
                    'reason' => $tokenData['error'],
                    'ip_address' => $context['ip_address'] ?? null
                ]);

                return $this->errorResponse('Authentication failed', $tokenData);
            }

            // Record successful authentication
            $this->recordSecurityEvent('authentication_success', [
                'user_id' => $tokenData['user_id'],
                'service' => $service,
                'ip_address' => $context['ip_address'] ?? null
            ]);

            $this->recordMetric('authentication_success', 1, [
                'service' => $service
            ]);

            return $this->successResponse($tokenData, 'Authentication successful');

        } catch (Exception $e) {
            $this->log('error', 'Authentication failed', [
                'error' => $e->getMessage(),
                'service' => $params['service'] ?? null
            ]);

            return $this->errorResponse('Authentication failed: ' . $e->getMessage());
        }
    }

    /**
     * Check user authorization
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function checkAuthorization(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'user_id' => ['required' => true, 'type' => 'int'],
                'permission' => ['required' => true, 'type' => 'string'],
                'resource' => ['type' => 'string'],
                'action' => ['type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $userId = $params['user_id'];
            $permission = $params['permission'];
            $resource = $params['resource'] ?? null;
            $action = $params['action'] ?? null;

            // Check user permissions
            $hasPermission = $this->checkUserPermission($userId, $permission, $resource, $action);

            if (!$hasPermission) {
                $this->recordSecurityEvent('authorization_denied', [
                    'user_id' => $userId,
                    'permission' => $permission,
                    'resource' => $resource,
                    'action' => $action,
                    'ip_address' => $context['ip_address'] ?? null
                ]);

                return $this->errorResponse('Access denied', [
                    'user_id' => $userId,
                    'permission' => $permission,
                    'authorized' => false
                ]);
            }

            $this->recordMetric('authorization_success', 1, [
                'permission' => $permission
            ]);

            return $this->successResponse([
                'user_id' => $userId,
                'permission' => $permission,
                'authorized' => true
            ], 'Authorization successful');

        } catch (Exception $e) {
            $this->log('error', 'Authorization check failed', [
                'error' => $e->getMessage(),
                'user_id' => $params['user_id'] ?? null
            ]);

            return $this->errorResponse('Authorization check failed: ' . $e->getMessage());
        }
    }

    /**
     * Apply rate limiting
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function applyRateLimit(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'identifier' => ['required' => true, 'type' => 'string'],
                'limit' => ['type' => 'int'],
                'window' => ['type' => 'int'],
                'action' => ['type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $identifier = $params['identifier'];
            $limit = $params['limit'] ?? 1000; // requests per window
            $window = $params['window'] ?? 60; // seconds
            $action = $params['action'] ?? 'api_request';

            // Check rate limit
            $rateLimitResult = $this->checkRateLimit($identifier, $limit, $window);

            if ($rateLimitResult['exceeded']) {
                $this->recordSecurityEvent('rate_limit_exceeded', [
                    'identifier' => $identifier,
                    'limit' => $limit,
                    'window' => $window,
                    'current_count' => $rateLimitResult['current_count'],
                    'action' => $action
                ]);

                return $this->errorResponse('Rate limit exceeded', $rateLimitResult);
            }

            $this->recordMetric('rate_limit_check', 1, [
                'action' => $action,
                'exceeded' => false
            ]);

            return $this->successResponse($rateLimitResult, 'Rate limit check passed');

        } catch (Exception $e) {
            $this->log('error', 'Rate limit check failed', [
                'error' => $e->getMessage(),
                'identifier' => $params['identifier'] ?? null
            ]);

            return $this->errorResponse('Rate limit check failed: ' . $e->getMessage());
        }
    }

    /**
     * Encrypt sensitive data
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function encryptData(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'data' => ['required' => true],
                'algorithm' => ['type' => 'string'],
                'key_id' => ['type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $data = $params['data'];
            $algorithm = $params['algorithm'] ?? 'AES-256-GCM';
            $keyId = $params['key_id'] ?? 'default';

            // Encrypt data
            $encryptionResult = $this->performEncryption($data, $algorithm, $keyId);

            if (!$encryptionResult['success']) {
                return $this->errorResponse('Encryption failed', $encryptionResult);
            }

            $this->recordMetric('data_encrypted', 1, [
                'algorithm' => $algorithm,
                'key_id' => $keyId
            ]);

            return $this->successResponse($encryptionResult, 'Data encrypted successfully');

        } catch (Exception $e) {
            $this->log('error', 'Data encryption failed', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Data encryption failed: ' . $e->getMessage());
        }
    }

    /**
     * Decrypt sensitive data
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function decryptData(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'encrypted_data' => ['required' => true, 'type' => 'string'],
                'algorithm' => ['type' => 'string'],
                'key_id' => ['type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $encryptedData = $params['encrypted_data'];
            $algorithm = $params['algorithm'] ?? 'AES-256-GCM';
            $keyId = $params['key_id'] ?? 'default';

            // Decrypt data
            $decryptionResult = $this->performDecryption($encryptedData, $algorithm, $keyId);

            if (!$decryptionResult['success']) {
                return $this->errorResponse('Decryption failed', $decryptionResult);
            }

            $this->recordMetric('data_decrypted', 1, [
                'algorithm' => $algorithm,
                'key_id' => $keyId
            ]);

            return $this->successResponse($decryptionResult, 'Data decrypted successfully');

        } catch (Exception $e) {
            $this->log('error', 'Data decryption failed', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Data decryption failed: ' . $e->getMessage());
        }
    }

    /**
     * Validate JWT token
     *
     * @param string $token
     * @return array
     */
    private function validateJwtToken(string $token): array
    {
        try {
            // This would integrate with your JWT library
            // For now, return a mock validation
            if (empty($token) || strlen($token) < 10) {
                return [
                    'valid' => false,
                    'error' => 'Invalid token format'
                ];
            }

            // Mock JWT validation - replace with actual JWT library
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return [
                    'valid' => false,
                    'error' => 'Invalid JWT format'
                ];
            }

            // Mock successful validation
            return [
                'valid' => true,
                'user_id' => 123,
                'email' => 'user@example.com',
                'roles' => ['user'],
                'permissions' => ['read', 'write'],
                'expires_at' => time() + 3600
            ];

        } catch (Exception $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check user permission
     *
     * @param int $userId
     * @param string $permission
     * @param string|null $resource
     * @param string|null $action
     * @return bool
     */
    private function checkUserPermission(int $userId, string $permission, ?string $resource = null, ?string $action = null): bool
    {
        // This would integrate with your permission system
        // For now, return a mock check
        $userPermissions = $this->getUserPermissions($userId);
        
        if (in_array($permission, $userPermissions)) {
            return true;
        }

        // Check resource-specific permissions
        if ($resource && $action) {
            $resourcePermission = "{$resource}.{$action}";
            return in_array($resourcePermission, $userPermissions);
        }

        return false;
    }

    /**
     * Get user permissions
     *
     * @param int $userId
     * @return array
     */
    private function getUserPermissions(int $userId): array
    {
        // This would query your database or cache
        // For now, return mock permissions
        return [
            'read',
            'write',
            'events.publish',
            'cache.set',
            'cache.get',
            'services.register'
        ];
    }

    /**
     * Check rate limit
     *
     * @param string $identifier
     * @param int $limit
     * @param int $window
     * @return array
     */
    private function checkRateLimit(string $identifier, int $limit, int $window): array
    {
        $cacheKey = "rate_limit:{$identifier}";
        $currentTime = time();
        $windowStart = $currentTime - $window;

        // Get current count from cache
        $rateLimitData = $this->getCached($cacheKey, [
            'count' => 0,
            'window_start' => $currentTime,
            'requests' => []
        ]);

        // Clean old requests
        $rateLimitData['requests'] = array_filter($rateLimitData['requests'], function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });

        // Add current request
        $rateLimitData['requests'][] = $currentTime;
        $currentCount = count($rateLimitData['requests']);

        // Update cache
        $this->cache($cacheKey, $rateLimitData, $window);

        return [
            'exceeded' => $currentCount > $limit,
            'current_count' => $currentCount,
            'limit' => $limit,
            'window' => $window,
            'reset_time' => $currentTime + $window
        ];
    }

    /**
     * Perform encryption
     *
     * @param mixed $data
     * @param string $algorithm
     * @param string $keyId
     * @return array
     */
    private function performEncryption($data, string $algorithm, string $keyId): array
    {
        try {
            $serializedData = serialize($data);
            $key = $this->getEncryptionKey($keyId);
            
            if ($algorithm === 'AES-256-GCM') {
                $iv = random_bytes(12);
                $encrypted = openssl_encrypt($serializedData, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
                
                if ($encrypted === false) {
                    return [
                        'success' => false,
                        'error' => 'Encryption failed'
                    ];
                }

                return [
                    'success' => true,
                    'encrypted_data' => base64_encode($iv . $tag . $encrypted),
                    'algorithm' => $algorithm,
                    'key_id' => $keyId
                ];
            }

            return [
                'success' => false,
                'error' => 'Unsupported encryption algorithm'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Perform decryption
     *
     * @param string $encryptedData
     * @param string $algorithm
     * @param string $keyId
     * @return array
     */
    private function performDecryption(string $encryptedData, string $algorithm, string $keyId): array
    {
        try {
            $key = $this->getEncryptionKey($keyId);
            $data = base64_decode($encryptedData);
            
            if ($algorithm === 'AES-256-GCM') {
                $iv = substr($data, 0, 12);
                $tag = substr($data, 12, 16);
                $encrypted = substr($data, 28);
                
                $decrypted = openssl_decrypt($encrypted, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
                
                if ($decrypted === false) {
                    return [
                        'success' => false,
                        'error' => 'Decryption failed'
                    ];
                }

                $originalData = unserialize($decrypted);

                return [
                    'success' => true,
                    'decrypted_data' => $originalData,
                    'algorithm' => $algorithm,
                    'key_id' => $keyId
                ];
            }

            return [
                'success' => false,
                'error' => 'Unsupported decryption algorithm'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get encryption key
     *
     * @param string $keyId
     * @return string
     */
    private function getEncryptionKey(string $keyId): string
    {
        // This would retrieve the key from a secure key management system
        // For now, return a mock key
        $keys = [
            'default' => hash('sha256', 'default-encryption-key', true),
            'user-data' => hash('sha256', 'user-data-encryption-key', true),
            'payment' => hash('sha256', 'payment-encryption-key', true)
        ];

        return $keys[$keyId] ?? $keys['default'];
    }

    /**
     * Record security event
     *
     * @param string $event
     * @param array $data
     * @return void
     */
    private function recordSecurityEvent(string $event, array $data): void
    {
        $this->log('info', 'Security event recorded', [
            'event' => $event,
            'data' => $data,
            'timestamp' => now()->toISOString()
        ]);

        // This would also store in a security audit log
        $auditData = [
            'event' => $event,
            'data' => $data,
            'timestamp' => now()->toISOString(),
            'trace_id' => $this->context['trace_id'] ?? null
        ];

        $this->cache("security_event:" . uniqid(), $auditData, 86400 * 30); // 30 days
    }
}

