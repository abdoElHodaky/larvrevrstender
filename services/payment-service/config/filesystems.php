<?php

/**
 * Filesystem Configuration for Payment Service
 *
 * This configuration imports the shared filesystem configuration
 * and can be extended with service-specific overrides if needed.
 */

// Import shared filesystem configuration safely
$sharedConfigPath = __DIR__.'/../../shared/config/filesystems.php';
$sharedConfig = [];

if (file_exists($sharedConfigPath)) {
    $sharedConfig = require $sharedConfigPath;
    
    // Ensure we got an array
    if (!is_array($sharedConfig)) {
        $sharedConfig = [];
    }
} else {
    // Fallback configuration for testing or when shared config is not available
    $sharedConfig = [
        'default' => env('FILESYSTEM_DISK', 'local'),
        'disks' => [
            'local' => [
                'driver' => 'local',
                'root' => storage_path('app'),
                'throw' => false,
            ],
            'public' => [
                'driver' => 'local',
                'root' => storage_path('app/public'),
                'url' => env('APP_URL').'/storage',
                'visibility' => 'public',
                'throw' => false,
            ],
        ],
        'links' => [
            public_path('storage') => storage_path('app/public'),
        ],
    ];
}

// Service-specific overrides (if any)
$serviceOverrides = [
    // Add any payment-service specific filesystem configurations here
];

// Merge shared config with service-specific overrides
return array_merge_recursive($sharedConfig, $serviceOverrides);
