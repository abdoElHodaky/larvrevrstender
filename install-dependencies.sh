#!/bin/bash

# Database Failover System - Critical Dependencies Installation Script
# This script addresses the Tier 1 critical blocking dependencies

set -e  # Exit on any error

echo "🚀 Database Failover System - Critical Dependencies Installation"
echo "================================================================"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Check prerequisites
print_status "Checking prerequisites..."

if ! command_exists composer; then
    print_error "Composer is not installed. Please install Composer first."
    exit 1
fi

if ! command_exists php; then
    print_error "PHP is not installed. Please install PHP first."
    exit 1
fi

# Check PHP version
PHP_VERSION=$(php -r "echo PHP_VERSION;")
print_status "PHP Version: $PHP_VERSION"

# Check if we're in the correct directory
if [ ! -f "services/auth-service/composer.json" ]; then
    print_error "Please run this script from the project root directory"
    exit 1
fi

print_success "Prerequisites check passed"

# TIER 1 DEPENDENCY 1: Package Installation
echo ""
print_status "TIER 1 DEPENDENCY 1: Installing required packages in auth-service..."

cd services/auth-service

print_status "Current directory: $(pwd)"
print_status "Installing Laravel database management packages..."

# Install packages
if composer require envor/laravel-managed-databases:^1.0; then
    print_success "✅ envor/laravel-managed-databases installed successfully"
else
    print_warning "⚠️  envor/laravel-managed-databases installation failed - package may not exist"
    print_status "Continuing with alternative approach..."
fi

if composer require usmonaliyev/laravel-db-connection-resolver:^1.0; then
    print_success "✅ usmonaliyev/laravel-db-connection-resolver installed successfully"
else
    print_warning "⚠️  usmonaliyev/laravel-db-connection-resolver installation failed - package may not exist"
    print_status "Continuing with alternative approach..."
fi

# TIER 1 DEPENDENCY 2: MongoDB Driver Installation
print_status "Installing MongoDB driver..."

if composer require jenssegers/mongodb:^4.0; then
    print_success "✅ MongoDB driver (jenssegers/mongodb) installed successfully"
else
    print_warning "⚠️  jenssegers/mongodb installation failed, trying alternative..."
    
    if composer require mongodb/laravel-mongodb; then
        print_success "✅ MongoDB driver (mongodb/laravel-mongodb) installed successfully"
    else
        print_error "❌ Failed to install MongoDB driver. Please install manually."
    fi
fi

# Update autoloader
print_status "Updating Composer autoloader..."
composer dump-autoload
print_success "✅ Autoloader updated"

# Check installed packages
print_status "Verifying installed packages..."
composer show | grep -E "(envor|usmonaliyev|mongodb|jenssegers)" || print_warning "Some packages may not be installed"

cd ../..

# TIER 1 DEPENDENCY 3: Configuration File Check
echo ""
print_status "TIER 1 DEPENDENCY 3: Checking configuration file..."

CONFIG_FILE="services/shared/config/database-failover.php"
if [ -f "$CONFIG_FILE" ]; then
    print_success "✅ Database failover configuration file exists"
else
    print_error "❌ Database failover configuration file missing: $CONFIG_FILE"
    exit 1
fi

# TIER 1 DEPENDENCY 4: Environment Configuration
echo ""
print_status "TIER 1 DEPENDENCY 4: Setting up environment configuration..."

ENV_EXAMPLE="services/auth-service/.env.failover-example"
ENV_FILE="services/auth-service/.env"

if [ -f "$ENV_EXAMPLE" ]; then
    print_success "✅ Environment example file exists"
    
    if [ -f "$ENV_FILE" ]; then
        print_warning "⚠️  .env file already exists"
        print_status "Please manually add the failover configuration variables from .env.failover-example"
        print_status "Required variables:"
        echo "  - NEON_DATABASE_URL"
        echo "  - CLOUD_DATABASE_URL" 
        echo "  - MONGO_DB_HOST"
        echo "  - DATABASE_FAILOVER_ENABLED=true"
    else
        print_status "Creating .env file from example..."
        cp "$ENV_EXAMPLE" "$ENV_FILE"
        print_success "✅ .env file created from example"
        print_warning "⚠️  Please update the database credentials in .env file"
    fi
else
    print_error "❌ Environment example file missing: $ENV_EXAMPLE"
fi

# Check PHP extensions
echo ""
print_status "Checking PHP extensions..."

# Check for PDO extensions
if php -m | grep -q pdo_pgsql; then
    print_success "✅ PDO PostgreSQL extension is available"
else
    print_warning "⚠️  PDO PostgreSQL extension not found"
fi

if php -m | grep -q pdo_mysql; then
    print_success "✅ PDO MySQL extension is available"
else
    print_warning "⚠️  PDO MySQL extension not found"
fi

if php -m | grep -q mongodb; then
    print_success "✅ MongoDB extension is available"
else
    print_warning "⚠️  MongoDB extension not found - install with: pecl install mongodb"
fi

# Test basic functionality
echo ""
print_status "Testing basic functionality..."

cd services/auth-service

# Test if Laravel can boot
if php artisan --version >/dev/null 2>&1; then
    print_success "✅ Laravel application can boot successfully"
else
    print_error "❌ Laravel application failed to boot"
    exit 1
fi

# Test if our services are registered
print_status "Testing service registration..."
if php artisan tinker --execute="app('Shared\\\\Services\\\\DatabaseFailoverManager'); echo 'Service registered successfully';" 2>/dev/null; then
    print_success "✅ DatabaseFailoverManager service is registered"
else
    print_warning "⚠️  DatabaseFailoverManager service registration test failed"
fi

# Test failover command if it exists
if php artisan list | grep -q "db:test-failover"; then
    print_success "✅ Database failover test command is available"
    print_status "You can now run: php artisan db:test-failover"
else
    print_warning "⚠️  Database failover test command not found"
fi

cd ../..

# Summary
echo ""
print_status "INSTALLATION SUMMARY"
print_status "===================="

echo ""
print_success "✅ TIER 1 CRITICAL DEPENDENCIES ADDRESSED:"
echo "   1. Package installation attempted"
echo "   2. MongoDB driver installation attempted"  
echo "   3. Configuration file verified"
echo "   4. Environment setup initiated"

echo ""
print_warning "⚠️  NEXT STEPS REQUIRED:"
echo "   1. Update database credentials in services/auth-service/.env"
echo "   2. Install missing PHP extensions if any"
echo "   3. Run: cd services/auth-service && php artisan db:test-failover"
echo "   4. Verify all 5 tests pass"

echo ""
print_status "TESTING COMMANDS:"
echo "   cd services/auth-service"
echo "   php artisan db:test-failover                    # Full system test"
echo "   php artisan db:test-failover --check-health     # Health status only"
echo "   php artisan db:test-failover --connection=pgsql # Test specific connection"

echo ""
if [ -f "services/auth-service/.env" ]; then
    print_success "🎯 SYSTEM STATUS: Ready for testing (pending database credentials)"
else
    print_warning "🎯 SYSTEM STATUS: Environment configuration needed"
fi

print_status "📋 See INSTALLATION-GUIDE.md for detailed next steps"
print_success "🚀 Critical dependencies installation completed!"
