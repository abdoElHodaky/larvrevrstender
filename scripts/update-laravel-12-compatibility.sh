#!/bin/bash

# Laravel 12 Compatibility Update Script
# Updates all microservices to support Laravel 12 with zero breaking changes

set -e

echo "🚀 Updating all services for Laravel 12 compatibility..."

# Array of all services
services=(
    "analytics-service"
    "auth-service"
    "bidding-service"
    "notification-service"
    "order-service"
    "payment-service"
    "user-service"
    "vin-ocr-service"
)

# Update each service's composer.json for Laravel 12 compatibility
for service in "${services[@]}"; do
    echo "📦 Updating $service for Laravel 12..."
    
    cd "services/$service"
    
    # Update Laravel framework to support both 11.x and 12.x
    if [ -f composer.json ]; then
        # Update Laravel framework version constraint
        sed -i 's/"laravel\/framework": "\^11\.48"/"laravel\/framework": "^11.48|^12.0"/' composer.json
        
        # Update other Laravel packages for Laravel 12 compatibility
        sed -i 's/"laravel\/sanctum": "\^4\.3"/"laravel\/sanctum": "^4.3|^5.0"/' composer.json
        sed -i 's/"laravel\/tinker": "\^2\.11"/"laravel\/tinker": "^2.11|^3.0"/' composer.json
        sed -i 's/"laravel\/horizon": "\^5\.43"/"laravel\/horizon": "^5.43|^6.0"/' composer.json
        sed -i 's/"laravel\/telescope": "\^5\.16"/"laravel\/telescope": "^5.16|^6.0"/' composer.json
        sed -i 's/"laravel\/sail": "\^1\.32"/"laravel\/sail": "^1.32|^2.0"/' composer.json
        sed -i 's/"spatie\/laravel-ignition": "\^2\.8"/"spatie\/laravel-ignition": "^2.8|^3.0"/' composer.json
        
        # Update PHP version requirement for Laravel 12
        sed -i 's/"php": "\^8\.2"/"php": "^8.2|^8.3|^8.4"/' composer.json
        
        echo "✅ Updated $service composer.json for Laravel 12"
    fi
    
    cd ../..
done

echo "🎉 All services updated for Laravel 12 compatibility!"
