#!/bin/bash
# Laravel Forge Deployment Script - Shared Service
# This script should be added to your Forge deployment configuration

set -e

echo "🚀 Starting Shared Service deployment..."

# Navigate to project directory
cd /home/forge/shared-service

# Pull latest changes
echo "📥 Pulling latest changes..."
git pull origin main

# Install/update dependencies
echo "📦 Installing dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Clear and rebuild caches
echo "🔄 Rebuilding caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Run database migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

# Rebuild optimized caches
echo "⚡ Building optimized caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue workers
echo "🔄 Restarting queue workers..."
php artisan queue:restart

# Restart PHP-FPM
echo "🔄 Restarting PHP-FPM..."
sudo service php8.3-fpm reload

# Wait for service to be ready
echo "⏳ Waiting for service to be ready..."
sleep 5

# Health check
echo "🏥 Performing health check..."
HEALTH_CHECK_URL="http://localhost/api/health"
HEALTH_RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$HEALTH_CHECK_URL" || echo "000")

if [ "$HEALTH_RESPONSE" = "200" ]; then
    echo "✅ Health check passed (HTTP $HEALTH_RESPONSE)"
else
    echo "❌ Health check failed (HTTP $HEALTH_RESPONSE)"
    echo "🔍 Checking service logs..."
    tail -n 20 /home/forge/shared-service/storage/logs/laravel.log
    exit 1
fi

# Test notification service communication
echo "🔗 Testing notification service communication..."
NOTIFICATION_TEST_URL="http://10.0.0.3:8080/api/health"
NOTIFICATION_RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$NOTIFICATION_TEST_URL" || echo "000")

if [ "$NOTIFICATION_RESPONSE" = "200" ]; then
    echo "✅ Notification service communication test passed"
else
    echo "⚠️ Warning: Notification service communication test failed (HTTP $NOTIFICATION_RESPONSE)"
    echo "   This may be expected if notification service is not yet deployed"
fi

# Clear any temporary files
echo "🧹 Cleaning up temporary files..."
php artisan cache:clear > /dev/null 2>&1 || true

echo "✅ Shared Service deployment completed successfully!"
echo "📊 Deployment Summary:"
echo "   - Service: Shared Service"
echo "   - Health Check: $HEALTH_CHECK_URL"
echo "   - Status: Healthy"
echo "   - Timestamp: $(date)"
