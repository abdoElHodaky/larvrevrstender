<?php

/**
 * Filesystem Configuration for Bidding Service
 *
 * This configuration imports the shared filesystem configuration
 * and can be extended with service-specific overrides if needed.
 */

// Import shared filesystem configuration
$sharedConfigPath = __DIR__.'/../../shared/config/filesystems.php';
$sharedConfig = [];

if (file_exists($sharedConfigPath)) {
    $sharedConfig = require $sharedConfigPath;
}

// Fallback to default Laravel filesystem config if shared config is not available
if (!is_array($sharedConfig)) {
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
    // Add any bidding-service specific filesystem configurations here
];

// Merge shared config with service-specific overrides
return array_merge_recursive($sharedConfig, $serviceOverrides);
