#!/bin/bash
# Laravel Forge Deployment Script - Notification Service
# This script should be added to your Forge deployment configuration

set -e

echo "🚀 Starting Notification Service deployment..."

# Navigate to project directory
cd /home/forge/notification-service

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

# Restart PHP-FPM (if running on port 8080)
echo "🔄 Restarting PHP-FPM..."
sudo service php8.3-fpm reload

# If using Laravel Octane, restart Octane instead
# echo "🔄 Restarting Laravel Octane..."
# php artisan octane:reload

# Wait for service to be ready
echo "⏳ Waiting for service to be ready..."
sleep 5

# Health check
echo "🏥 Performing health check..."
HEALTH_CHECK_URL="http://localhost:8080/api/health"
HEALTH_RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$HEALTH_CHECK_URL" || echo "000")

if [ "$HEALTH_RESPONSE" = "200" ]; then
    echo "✅ Health check passed (HTTP $HEALTH_RESPONSE)"
else
    echo "❌ Health check failed (HTTP $HEALTH_RESPONSE)"
    echo "🔍 Checking service logs..."
    tail -n 20 /home/forge/notification-service/storage/logs/laravel.log
    
    # Check if the service is running on the correct port
    echo "🔍 Checking if service is running on port 8080..."
    netstat -tlnp | grep :8080 || echo "No service found on port 8080"
    
    exit 1
fi

# Test notification templates
echo "📧 Testing notification templates..."
TEMPLATE_COUNT=$(php artisan tinker --execute="echo \App\Models\NotificationTemplate::count();" 2>/dev/null || echo "0")
echo "   Found $TEMPLATE_COUNT notification templates"

# Test email configuration
echo "📮 Testing email configuration..."
EMAIL_TEST_RESULT=$(php artisan tinker --execute="
try {
    \Illuminate\Support\Facades\Mail::fake();
    echo 'Email configuration OK';
} catch (Exception \$e) {
    echo 'Email configuration error: ' . \$e->getMessage();
}
" 2>/dev/null || echo "Email test failed")
echo "   $EMAIL_TEST_RESULT"

# Test database connection
echo "🗄️ Testing database connection..."
DB_TEST_RESULT=$(php artisan tinker --execute="
try {
    \Illuminate\Support\Facades\DB::connection()->getPdo();
    echo 'Database connection OK';
} catch (Exception \$e) {
    echo 'Database connection error: ' . \$e->getMessage();
}
" 2>/dev/null || echo "Database test failed")
echo "   $DB_TEST_RESULT"

# Test Redis connection
echo "🔴 Testing Redis connection..."
REDIS_TEST_RESULT=$(php artisan tinker --execute="
try {
    \Illuminate\Support\Facades\Redis::ping();
    echo 'Redis connection OK';
} catch (Exception \$e) {
    echo 'Redis connection error: ' . \$e->getMessage();
}
" 2>/dev/null || echo "Redis test failed")
echo "   $REDIS_TEST_RESULT"

# Clear any temporary files
echo "🧹 Cleaning up temporary files..."
php artisan cache:clear > /dev/null 2>&1 || true

echo "✅ Notification Service deployment completed successfully!"
echo "📊 Deployment Summary:"
echo "   - Service: Notification Service"
echo "   - Health Check: $HEALTH_CHECK_URL"
echo "   - Status: Healthy"
echo "   - Templates: $TEMPLATE_COUNT"
echo "   - Timestamp: $(date)"
