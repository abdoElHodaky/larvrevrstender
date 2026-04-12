#!/bin/bash

# Database Failover System - Comprehensive Validation Script
# This script validates all dependencies and system readiness

set -e  # Exit on any error

echo "🔍 Database Failover System - Comprehensive Validation"
echo "======================================================"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Counters
TOTAL_CHECKS=0
PASSED_CHECKS=0
FAILED_CHECKS=0
WARNING_CHECKS=0

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[PASS]${NC} $1"
    ((PASSED_CHECKS++))
}

print_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
    ((WARNING_CHECKS++))
}

print_error() {
    echo -e "${RED}[FAIL]${NC} $1"
    ((FAILED_CHECKS++))
}

# Function to run a check
run_check() {
    ((TOTAL_CHECKS++))
    local description="$1"
    local command="$2"
    
    print_status "Checking: $description"
    
    if eval "$command" >/dev/null 2>&1; then
        print_success "$description"
        return 0
    else
        print_error "$description"
        return 1
    fi
}

# Function to run a check with warning
run_check_warn() {
    ((TOTAL_CHECKS++))
    local description="$1"
    local command="$2"
    
    print_status "Checking: $description"
    
    if eval "$command" >/dev/null 2>&1; then
        print_success "$description"
        return 0
    else
        print_warning "$description"
        return 1
    fi
}

echo ""
print_status "TIER 1: CRITICAL BLOCKING DEPENDENCIES"
print_status "======================================"

# Check 1: Project Structure
run_check "Project structure (auth-service exists)" "[ -d 'services/auth-service' ]"
run_check "Project structure (shared services exists)" "[ -d 'services/shared' ]"
run_check "Composer.json exists in auth-service" "[ -f 'services/auth-service/composer.json' ]"

# Check 2: PHP Environment
run_check "PHP is available" "command -v php"
run_check "Composer is available" "command -v composer"

PHP_VERSION=$(php -r "echo PHP_VERSION;" 2>/dev/null || echo "unknown")
print_status "PHP Version: $PHP_VERSION"

# Check 3: PHP Extensions
run_check "PDO extension loaded" "php -m | grep -q '^pdo$'"
run_check_warn "PDO PostgreSQL extension loaded" "php -m | grep -q 'pdo_pgsql'"
run_check_warn "PDO MySQL extension loaded" "php -m | grep -q 'pdo_mysql'"
run_check_warn "MongoDB extension loaded" "php -m | grep -q 'mongodb'"

# Check 4: Package Dependencies
cd services/auth-service

print_status "Checking Composer packages..."
run_check_warn "envor/laravel-managed-databases package" "composer show envor/laravel-managed-databases"
run_check_warn "usmonaliyev/laravel-db-connection-resolver package" "composer show usmonaliyev/laravel-db-connection-resolver"
run_check_warn "MongoDB driver package" "composer show | grep -E '(jenssegers/mongodb|mongodb/laravel-mongodb)'"

# Check 5: Laravel Application
run_check "Laravel application boots" "php artisan --version"
run_check "Laravel cache is clear" "php artisan config:clear"

cd ../..

# Check 6: Configuration Files
run_check "Database failover config exists" "[ -f 'services/shared/config/database-failover.php' ]"
run_check "Environment example exists" "[ -f 'services/auth-service/.env.failover-example' ]"
run_check_warn "Environment file exists" "[ -f 'services/auth-service/.env' ]"

# Check 7: Core Classes
print_status "Checking core failover classes..."
run_check "DatabaseFailoverInterface exists" "[ -f 'services/shared/src/Contracts/DatabaseFailoverInterface.php' ]"
run_check "DatabaseFailoverManager exists" "[ -f 'services/shared/src/Services/DatabaseFailoverManager.php' ]"
run_check "DatabaseHealthChecker exists" "[ -f 'services/shared/src/HealthCheck/DatabaseHealthChecker.php' ]"
run_check "ConnectionHealthStatus exists" "[ -f 'services/shared/src/HealthCheck/ConnectionHealthStatus.php' ]"
run_check "DatabaseFailoverMiddleware exists" "[ -f 'services/shared/src/Middleware/DatabaseFailoverMiddleware.php' ]"

# Check 8: Service Registration
cd services/auth-service

print_status "Checking service registration..."
run_check "SharedServiceProvider registered" "grep -q 'SharedServiceProvider' bootstrap/providers.php"
run_check "Middleware registered in Kernel" "grep -q 'DatabaseFailoverMiddleware' app/Http/Kernel.php"

# Check 9: Database Configuration
run_check "Multi-tier database config" "grep -q 'pgsql_secondary' config/database.php"
run_check "MongoDB connection config" "grep -q 'mongodb' config/database.php"
run_check "Failover metadata in config" "grep -q 'failover_priority' config/database.php"

# Check 10: Test Command
run_check "Test command exists" "[ -f 'app/Console/Commands/TestDatabaseFailover.php' ]"
run_check_warn "Test command registered" "php artisan list | grep -q 'db:test-failover'"

cd ../..

echo ""
print_status "TIER 2: FUNCTIONAL VALIDATION"
print_status "============================="

cd services/auth-service

# Check 11: Service Resolution
print_status "Testing service resolution..."
if php artisan tinker --execute="
try {
    app('Shared\\\\Contracts\\\\DatabaseFailoverInterface');
    echo 'DatabaseFailoverInterface: OK';
} catch (Exception \$e) {
    echo 'DatabaseFailoverInterface: FAIL - ' . \$e->getMessage();
    exit(1);
}
" 2>/dev/null; then
    print_success "DatabaseFailoverInterface service resolution"
    ((PASSED_CHECKS++))
else
    print_error "DatabaseFailoverInterface service resolution"
    ((FAILED_CHECKS++))
fi
((TOTAL_CHECKS++))

if php artisan tinker --execute="
try {
    app('Shared\\\\Services\\\\DatabaseFailoverManager');
    echo 'DatabaseFailoverManager: OK';
} catch (Exception \$e) {
    echo 'DatabaseFailoverManager: FAIL - ' . \$e->getMessage();
    exit(1);
}
" 2>/dev/null; then
    print_success "DatabaseFailoverManager service resolution"
    ((PASSED_CHECKS++))
else
    print_error "DatabaseFailoverManager service resolution"
    ((FAILED_CHECKS++))
fi
((TOTAL_CHECKS++))

if php artisan tinker --execute="
try {
    app('Shared\\\\HealthCheck\\\\DatabaseHealthChecker');
    echo 'DatabaseHealthChecker: OK';
} catch (Exception \$e) {
    echo 'DatabaseHealthChecker: FAIL - ' . \$e->getMessage();
    exit(1);
}
" 2>/dev/null; then
    print_success "DatabaseHealthChecker service resolution"
    ((PASSED_CHECKS++))
else
    print_error "DatabaseHealthChecker service resolution"
    ((FAILED_CHECKS++))
fi
((TOTAL_CHECKS++))

# Check 12: Configuration Loading
print_status "Testing configuration loading..."
if php artisan tinker --execute="
try {
    \$config = config('database-failover');
    if (empty(\$config)) {
        echo 'Config empty';
        exit(1);
    }
    echo 'Configuration loaded: ' . count(\$config) . ' keys';
} catch (Exception \$e) {
    echo 'Config load failed: ' . \$e->getMessage();
    exit(1);
}
" 2>/dev/null; then
    print_success "Database failover configuration loading"
    ((PASSED_CHECKS++))
else
    print_error "Database failover configuration loading"
    ((FAILED_CHECKS++))
fi
((TOTAL_CHECKS++))

# Check 13: Database Connections Configuration
print_status "Testing database connections configuration..."
CONNECTIONS=("pgsql" "pgsql_secondary" "mongodb")
for conn in "${CONNECTIONS[@]}"; do
    if php artisan tinker --execute="
    try {
        \$config = config('database.connections.$conn');
        if (empty(\$config)) {
            echo 'Connection $conn not configured';
            exit(1);
        }
        echo 'Connection $conn configured';
    } catch (Exception \$e) {
        echo 'Connection $conn error: ' . \$e->getMessage();
        exit(1);
    }
    " 2>/dev/null; then
        print_success "Database connection '$conn' configuration"
        ((PASSED_CHECKS++))
    else
        print_warning "Database connection '$conn' configuration"
        ((WARNING_CHECKS++))
    fi
    ((TOTAL_CHECKS++))
done

cd ../..

echo ""
print_status "TIER 3: INTEGRATION VALIDATION"
print_status "=============================="

cd services/auth-service

# Check 14: Middleware Integration
print_status "Testing middleware integration..."
if grep -q "DatabaseFailoverMiddleware" app/Http/Kernel.php; then
    if grep -q "db.failover" app/Http/Kernel.php; then
        print_success "Middleware registered in global stack and as alias"
        ((PASSED_CHECKS++))
    else
        print_warning "Middleware in global stack but no alias"
        ((WARNING_CHECKS++))
    fi
else
    print_error "Middleware not registered in Kernel"
    ((FAILED_CHECKS++))
fi
((TOTAL_CHECKS++))

# Check 15: Environment Variables
print_status "Checking environment variables..."
if [ -f ".env" ]; then
    ENV_VARS=("DB_CONNECTION" "DATABASE_FAILOVER_ENABLED")
    for var in "${ENV_VARS[@]}"; do
        if grep -q "^$var=" .env; then
            print_success "Environment variable '$var' set"
            ((PASSED_CHECKS++))
        else
            print_warning "Environment variable '$var' not set"
            ((WARNING_CHECKS++))
        fi
        ((TOTAL_CHECKS++))
    done
else
    print_error "No .env file found"
    ((FAILED_CHECKS++))
    ((TOTAL_CHECKS++))
fi

cd ../..

# Final Summary
echo ""
print_status "VALIDATION SUMMARY"
print_status "=================="

echo ""
print_status "Total Checks: $TOTAL_CHECKS"
print_success "Passed: $PASSED_CHECKS"
print_warning "Warnings: $WARNING_CHECKS"
print_error "Failed: $FAILED_CHECKS"

echo ""
PASS_RATE=$((PASSED_CHECKS * 100 / TOTAL_CHECKS))
print_status "Pass Rate: $PASS_RATE%"

echo ""
if [ $FAILED_CHECKS -eq 0 ]; then
    if [ $WARNING_CHECKS -eq 0 ]; then
        print_success "🎉 ALL VALIDATIONS PASSED! System is ready for production."
        echo ""
        print_status "Next steps:"
        echo "  1. Add real database credentials to services/auth-service/.env"
        echo "  2. Run: cd services/auth-service && php artisan db:test-failover"
        echo "  3. Verify all functional tests pass"
    else
        print_warning "⚠️  SYSTEM MOSTLY READY - Some warnings need attention"
        echo ""
        print_status "Address warnings above, then:"
        echo "  1. Add real database credentials to services/auth-service/.env"
        echo "  2. Run: cd services/auth-service && php artisan db:test-failover"
    fi
else
    print_error "❌ CRITICAL ISSUES FOUND - System not ready"
    echo ""
    print_status "Fix the failed checks above before proceeding."
    echo "Run: ./install-dependencies.sh to address missing dependencies"
fi

echo ""
print_status "For detailed guidance, see:"
echo "  - INSTALLATION-GUIDE.md"
echo "  - CRITICAL-DEPENDENCIES-ANALYSIS.md"

exit $FAILED_CHECKS
