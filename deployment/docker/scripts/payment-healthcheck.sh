#!/bin/bash

# Payment Service Health Check Script
# This script performs comprehensive health checks for the payment service

set -e

# Configuration
HEALTH_ENDPOINT="http://localhost:8004/octane/health"
API_ENDPOINT="http://localhost:8004/api/health"
TIMEOUT=10
MAX_RETRIES=3

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

warn() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING: $1${NC}"
}

error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR: $1${NC}"
}

# Function to check HTTP endpoint
check_endpoint() {
    local endpoint=$1
    local description=$2
    local retry_count=0
    
    while [ $retry_count -lt $MAX_RETRIES ]; do
        if curl -f -s --max-time $TIMEOUT "$endpoint" > /dev/null 2>&1; then
            log "$description is healthy"
            return 0
        else
            retry_count=$((retry_count + 1))
            if [ $retry_count -lt $MAX_RETRIES ]; then
                warn "$description check failed, retrying ($retry_count/$MAX_RETRIES)..."
                sleep 2
            fi
        fi
    done
    
    error "$description is unhealthy after $MAX_RETRIES attempts"
    return 1
}

# Function to check database connectivity
check_database() {
    log "Checking database connectivity..."
    
    # Try to connect to the database using PHP artisan
    if php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database connection successful';" > /dev/null 2>&1; then
        log "Database connectivity is healthy"
        return 0
    else
        error "Database connectivity failed"
        return 1
    fi
}

# Function to check Redis connectivity
check_redis() {
    log "Checking Redis connectivity..."
    
    # Try to connect to Redis using PHP artisan
    if php artisan tinker --execute="Redis::ping(); echo 'Redis connection successful';" > /dev/null 2>&1; then
        log "Redis connectivity is healthy"
        return 0
    else
        warn "Redis connectivity failed (non-critical)"
        return 0  # Redis failure is not critical for basic health
    fi
}

# Function to check payment gateway connectivity
check_payment_gateways() {
    log "Checking payment gateway connectivity..."
    
    # Check if payment service can reach configured gateways
    local gateway_check_endpoint="http://localhost:8004/api/gateways/health"
    
    if curl -f -s --max-time $TIMEOUT "$gateway_check_endpoint" > /dev/null 2>&1; then
        log "Payment gateways are reachable"
        return 0
    else
        warn "Payment gateway health check failed (may be expected in development)"
        return 0  # Gateway connectivity issues are not critical for basic health
    fi
}

# Function to check disk space
check_disk_space() {
    log "Checking disk space..."
    
    # Check if we have at least 1GB free space
    local available_space=$(df /var/www/storage | awk 'NR==2 {print $4}')
    local min_space=1048576  # 1GB in KB
    
    if [ "$available_space" -gt "$min_space" ]; then
        log "Disk space is sufficient ($(($available_space / 1024))MB available)"
        return 0
    else
        warn "Low disk space: $(($available_space / 1024))MB available"
        return 0  # Low disk space is a warning, not a failure
    fi
}

# Function to check memory usage
check_memory() {
    log "Checking memory usage..."
    
    # Get memory usage percentage
    local memory_usage=$(free | awk 'NR==2{printf "%.0f", $3*100/$2}')
    
    if [ "$memory_usage" -lt 90 ]; then
        log "Memory usage is healthy (${memory_usage}%)"
        return 0
    else
        warn "High memory usage: ${memory_usage}%"
        return 0  # High memory usage is a warning, not a failure
    fi
}

# Main health check function
main() {
    log "Starting payment service health check..."
    
    local exit_code=0
    
    # Critical checks (must pass)
    if ! check_endpoint "$HEALTH_ENDPOINT" "Octane health endpoint"; then
        exit_code=1
    fi
    
    if ! check_endpoint "$API_ENDPOINT" "API health endpoint"; then
        exit_code=1
    fi
    
    if ! check_database; then
        exit_code=1
    fi
    
    # Non-critical checks (warnings only)
    check_redis
    check_payment_gateways
    check_disk_space
    check_memory
    
    if [ $exit_code -eq 0 ]; then
        log "Payment service health check completed successfully"
    else
        error "Payment service health check failed"
    fi
    
    exit $exit_code
}

# Run the health check
main "$@"
