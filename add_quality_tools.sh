#!/bin/bash

# Script to add quality assurance tools to all services
# This implements Phase 8: Quality Assurance Integration

SERVICES=(
    "analytics-service"
    "auction-service"
    "bidding-service"
    "gateway-service"
    "notification-service"
    "order-service"
    "payment-service"
    "vin-ocr-service"
)

# Quality scripts to add
QUALITY_SCRIPTS='        "ecs": [
            "vendor/bin/ecs check --fix"
        ],
        "stan": [
            "vendor/bin/phpstan analyse --memory-limit=2G"
        ],
        "unit": [
            "@php artisan test --testsuite=Unit"
        ],
        "coverage": [
            "@php artisan test --coverage --min=100"
        ],
        "feature": [
            "@php artisan test --testsuite=Feature"
        ],'

# Dev dependencies to add
DEV_DEPS='        "symplify/easy-coding-standard": "^12.3",
        "phpstan/phpstan": "^2.1",
        "phpstan/phpstan-laravel": "^2.1",
        "larastan/larastan": "^3.0"'

for service in "${SERVICES[@]}"; do
    echo "Processing $service..."
    
    composer_file="services/$service/composer.json"
    
    if [ -f "$composer_file" ]; then
        # Add quality scripts after the test script
        sed -i '/\"test\": \[/,/\],/{
            /\],/{
                a\
        "ecs": [\
            "vendor/bin/ecs check --fix"\
        ],\
        "stan": [\
            "vendor/bin/phpstan analyse --memory-limit=2G"\
        ],\
        "unit": [\
            "@php artisan test --testsuite=Unit"\
        ],\
        "coverage": [\
            "@php artisan test --coverage --min=100"\
        ],\
        "feature": [\
            "@php artisan test --testsuite=Feature"\
        ],
            }
        }' "$composer_file"
        
        # Add dev dependencies - find the last dev dependency and add after it
        sed -i '/\"require-dev\": {/,/}/ {
            /\"laravel\/pail\": \"[^\"]*\"$/{
                s/$/,/
                a\
        "symplify/easy-coding-standard": "^12.3",\
        "phpstan/phpstan": "^2.1",\
        "phpstan/phpstan-laravel": "^2.1",\
        "larastan/larastan": "^3.0"
            }
        }' "$composer_file"
        
        echo "✅ Updated $service"
    else
        echo "❌ $composer_file not found"
    fi
done

echo "🎉 Quality tools added to all services!"

