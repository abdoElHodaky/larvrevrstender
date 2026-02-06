<?php

/**
 * Filesystem Configuration for Auth Service
 *
 * This configuration imports the shared filesystem configuration
 * and can be extended with service-specific overrides if needed.
 */

// Import shared filesystem configuration
$sharedConfig = require __DIR__.'/../../shared/config/filesystems.php';

// Service-specific overrides (if any)
$serviceOverrides = [
    // Add any auth-service specific filesystem configurations here
    // Example:
    // 'disks' => [
    //     'auth-specific' => [
    //         'driver' => 's3',
    //         'key' => env('AUTH_AWS_ACCESS_KEY_ID'),
    //         // ... other config
    //     ]
    // ]
];

// Merge shared config with service-specific overrides
return array_merge_recursive($sharedConfig, $serviceOverrides);
