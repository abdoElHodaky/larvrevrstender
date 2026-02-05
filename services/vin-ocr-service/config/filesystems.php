<?php

/**
 * Filesystem Configuration for VIN OCR Service
 * 
 * This configuration imports the shared filesystem configuration
 * and can be extended with service-specific overrides if needed.
 */

// Import shared filesystem configuration
$sharedConfig = require_once __DIR__ . '/../../shared/config/filesystems.php';

// Service-specific overrides (if any)
$serviceOverrides = [
    // Add any vin-ocr-service specific filesystem configurations here
];

// Merge shared config with service-specific overrides
return array_merge_recursive($sharedConfig, $serviceOverrides);
