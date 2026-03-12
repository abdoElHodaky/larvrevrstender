#!/bin/bash

# Script to update composer.lock files for all services
# This fixes the issue where new dev dependencies were added to composer.json
# but the lock files weren't updated

set -e

export COMPOSER_ALLOW_SUPERUSER=1

echo "🔄 Updating composer.lock files for all services..."

# List of services with composer.json files
SERVICES=(
    "analytics-service"
    "auction-service"
    "auth-service"
    "bidding-service"
    "gateway-service"
    "notification-service"
    "payment-service"
    "shared"
    "user-service"
    "vin-ocr-service"
)

# Function to update composer lock for a service
update_service_lock() {
    local service=$1
    local service_path="services/$service"
    
    echo "📦 Updating $service..."
    
    if [ ! -f "$service_path/composer.json" ]; then
        echo "⚠️  No composer.json found for $service, skipping..."
        return
    fi
    
    cd "$service_path"
    
    # Create .env file if it doesn't exist (needed for some Laravel services)
    if [ -f ".env.example" ] && [ ! -f ".env" ]; then
        cp .env.example .env
        echo "✅ Created .env file for $service"
    fi
    
    # Update composer.lock file
    echo "🔄 Running composer update for $service..."
    if composer update --no-scripts --no-interaction --prefer-dist --quiet; then
        echo "✅ Successfully updated composer.lock for $service"
    else
        echo "❌ Failed to update composer.lock for $service"
        cd - > /dev/null
        return 1
    fi
    
    cd - > /dev/null
}

# Update each service (skip order-service since it's already done)
for service in "${SERVICES[@]}"; do
    echo ""
    echo "===================="
    echo "Processing $service"
    echo "===================="
    
    if update_service_lock "$service"; then
        echo "✅ $service completed successfully"
    else
        echo "❌ $service failed - continuing with others..."
        # Don't exit on failure, continue with other services
    fi
done

echo ""
echo "🎉 Composer lock update process completed!"
echo ""
echo "📋 Summary:"
echo "   - Processed ${#SERVICES[@]} services"
echo "   - New dev dependencies are now properly locked"
echo "   - Quality assurance tools (PHPStan, ECS) are ready to use"

