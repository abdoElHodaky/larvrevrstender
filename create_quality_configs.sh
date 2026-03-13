#!/bin/bash

# Script to create quality assurance configuration files for all services
# This completes Phase 8: Quality Assurance Integration

SERVICES=(
    "auth-service"
    "user-service"
    "analytics-service"
    "auction-service"
    "bidding-service"
    "gateway-service"
    "notification-service"
    "order-service"
    "payment-service"
    "vin-ocr-service"
)

# PHPStan configuration template
PHPSTAN_CONFIG='includes:
    - ./vendor/larastan/larastan/extension.neon

parameters:
    paths:
        - app/
        - config/
        - database/
        - routes/
    
    # Rule level (0-9, 9 is strictest)
    level: 8
    
    # Ignore errors in vendor and generated files
    excludePaths:
        - vendor/
        - storage/
        - bootstrap/cache/
        - node_modules/
    
    # Laravel specific settings
    checkMissingIterableValueType: false
    checkGenericClassInNonGenericObjectType: false
    
    # Additional rules
    checkTooWideReturnTypesInProtectedAndPublicMethods: true
    checkUninitializedProperties: true
    checkDynamicProperties: false
    
    # Ignore common Laravel patterns
    ignoreErrors:
        - "#Call to an undefined method Illuminate\\\\.*#"
        - "#Access to an undefined property Illuminate\\\\.*#"
        - "#Method .* should return .* but returns Illuminate\\\\.*#"'

# ECS configuration template
ECS_CONFIG='<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . "/app",
        __DIR__ . "/config",
        __DIR__ . "/database",
        __DIR__ . "/routes",
        __DIR__ . "/tests",
    ])
    ->withSkip([
        __DIR__ . "/vendor",
        __DIR__ . "/storage",
        __DIR__ . "/bootstrap/cache",
        __DIR__ . "/node_modules",
    ])
    ->withSets([
        SetList::CLEAN_CODE,
        SetList::PSR_12,
        SetList::COMMON,
        SetList::SYMPLIFY,
    ])
    ->withRules([
        // Add specific rules for Laravel/PHP 8.3 modernization
        \PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer::class,
        \PhpCsFixer\Fixer\ClassNotation\ClassAttributesSeparationFixer::class,
        \PhpCsFixer\Fixer\FunctionNotation\ReturnTypeDeclarationFixer::class,
        \PhpCsFixer\Fixer\Phpdoc\PhpdocTypesOrderFixer::class,
    ]);'

for service in "${SERVICES[@]}"; do
    echo "Creating quality configs for $service..."
    
    service_dir="services/$service"
    
    if [ -d "$service_dir" ]; then
        # Create PHPStan configuration
        echo "$PHPSTAN_CONFIG" > "$service_dir/phpstan.neon"
        
        # Create ECS configuration
        echo "$ECS_CONFIG" > "$service_dir/ecs.php"
        
        echo "✅ Created configs for $service"
    else
        echo "❌ $service_dir not found"
    fi
done

echo "🎉 Quality configuration files created for all services!"

