#!/bin/bash

# Fix composer.json dependencies for all services
services=(
    "analytics-service"
    "bidding-service"
    "notification-service"
    "order-service"
    "payment-service"
    "user-service"
    "vin-ocr-service"
)

for service in "${services[@]}"; do
    echo "Fixing dependencies for $service..."
    
    # Fix the dependencies in composer.json
    sed -i 's/"laravel\/sanctum": "\^5\.0"/"laravel\/sanctum": "^4.0"/g' "services/$service/composer.json"
    sed -i 's/"laravel\/tinker": "\^3\.0"/"laravel\/tinker": "^2.11"/g' "services/$service/composer.json"
    sed -i 's/"laravel\/horizon": "\^6\.0"/"laravel\/horizon": "^5.43"/g' "services/$service/composer.json"
    sed -i 's/"laravel\/telescope": "\^6\.0"/"laravel\/telescope": "^5.16"/g' "services/$service/composer.json"
    sed -i 's/"laravel\/sail": "\^2\.0"/"laravel\/sail": "^1.32"/g' "services/$service/composer.json"
    sed -i 's/"nunomaduro\/collision": "\^8\.4"/"nunomaduro\/collision": "^8.6"/g' "services/$service/composer.json"
    sed -i 's/"nunomaduro\/collision": "\^7\.10|\^8\.0"/"nunomaduro\/collision": "^8.6"/g' "services/$service/composer.json"
    sed -i 's/"phpunit\/phpunit": "\^10\.5"/"phpunit\/phpunit": "^11.5"/g' "services/$service/composer.json"
    sed -i 's/"spatie\/laravel-ignition": "\^3\.0"/"spatie\/laravel-ignition": "^2.8"/g' "services/$service/composer.json"
    # Remove larastan as it doesn't support Laravel 12 yet
    sed -i '/"larastan\/larastan": "\^2\.9"/d' "services/$service/composer.json"
    # Clean up trailing comma if larastan was the last item
    sed -i 's/,$//' "services/$service/composer.json"
    
    echo "✅ Fixed $service"
done

echo "🎉 All services fixed!"
