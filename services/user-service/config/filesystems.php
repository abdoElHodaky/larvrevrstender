<?php

/**
 * Filesystem Configuration for User Service
 * 
 * This configuration imports the shared filesystem configuration
 * and can be extended with service-specific overrides if needed.
 */

// Import shared filesystem configuration
$sharedConfig = require_once __DIR__ . '/../../shared/config/filesystems.php';

// Service-specific overrides (if any)
$serviceOverrides = [
    // Add any user-service specific filesystem configurations here
    // Example for user avatars and documents:
    // 'disks' => [
    //     'user-avatars' => [
    //         'driver' => 's3',
    //         'key' => env('AWS_ACCESS_KEY_ID'),
    //         'secret' => env('AWS_SECRET_ACCESS_KEY'),
    //         'region' => env('AWS_DEFAULT_REGION'),
    //         'bucket' => env('AWS_BUCKET'),
    //         'url' => env('AWS_URL'),
    //         'endpoint' => env('AWS_ENDPOINT'),
    //         'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
    //         'throw' => false,
    //         'root' => 'user-service/avatars',
    //     ]
    // ]
];

// Merge shared config with service-specific overrides
return array_merge_recursive($sharedConfig, $serviceOverrides);
