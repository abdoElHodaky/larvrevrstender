#!/bin/bash

# Octane-Specific Validation Script for Reverse Tender Platform
# Validates Octane configuration, health, and performance across all services

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
LOG_FILE="/tmp/octane-validation-$(date +%Y%m%d-%H%M%S).log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[SUCCESS] $1${NC}" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}" | tee -a "$LOG_FILE"
}

# Configuration
ENVIRONMENTS=("development" "staging" "production")
SERVICES=("api-gateway" "auth-service" "user-service" "bidding-service" "order-service" "payment-service" "notification-service" "analytics-service" "vin-ocr-service")
TIMEOUT=30

# Octane configuration validation
validate_octane_config() {
    local env=$1
    log "Validating Octane configuration for $env environment..."
    
    local env_file="$PROJECT_ROOT/deployment/config/environments/$env.env"
    
    if [ ! -f "$env_file" ]; then
        error "Environment file not found: $env_file"
        return 1
    fi
    
    # Required Octane variables
    local required_vars=(
        "OCTANE_WORKERS"
        "OCTANE_TASK_WORKERS"
        "OCTANE_MAX_REQUESTS"
        "OCTANE_MEMORY_LIMIT"
        "OCTANE_SERVER"
        "FRANKENPHP_WORKER_MODE"
    )
    
    local missing_vars=()
    local invalid_vars=()
    
    for var in "${required_vars[@]}"; do
        if ! grep -q "^$var=" "$env_file"; then
            missing_vars+=("$var")
        else
            local value=$(grep "^$var=" "$env_file" | cut -d'=' -f2)
            
            # Validate specific variables
            case $var in
                "OCTANE_WORKERS"|"OCTANE_TASK_WORKERS")
                    if ! [[ "$value" =~ ^[0-9]+$ ]] || [ "$value" -lt 1 ]; then
                        invalid_vars+=("$var (must be positive integer)")
                    fi
                    ;;
                "OCTANE_MAX_REQUESTS")
                    if ! [[ "$value" =~ ^[0-9]+$ ]] || [ "$value" -lt 50 ]; then
                        invalid_vars+=("$var (must be >= 50)")
                    fi
                    ;;
                "OCTANE_MEMORY_LIMIT")
                    if ! [[ "$value" =~ ^[0-9]+[MG]$ ]]; then
                        invalid_vars+=("$var (must be in format like 256M or 1G)")
                    fi
                    ;;
                "OCTANE_SERVER")
                    if [ "$value" != "frankenphp" ] && [ "$value" != "swoole" ] && [ "$value" != "roadrunner" ]; then
                        invalid_vars+=("$var (must be frankenphp, swoole, or roadrunner)")
                    fi
                    ;;
                "FRANKENPHP_WORKER_MODE")
                    if [ "$value" != "true" ] && [ "$value" != "false" ]; then
                        invalid_vars+=("$var (must be true or false)")
                    fi
                    ;;
            esac
        fi
    done
    
    if [ ${#missing_vars[@]} -eq 0 ] && [ ${#invalid_vars[@]} -eq 0 ]; then
        success "Octane configuration valid for $env environment"
        
        # Log configuration summary
        local workers=$(grep "^OCTANE_WORKERS=" "$env_file" | cut -d'=' -f2)
        local task_workers=$(grep "^OCTANE_TASK_WORKERS=" "$env_file" | cut -d'=' -f2)
        local memory_limit=$(grep "^OCTANE_MEMORY_LIMIT=" "$env_file" | cut -d'=' -f2)
        log "  Workers: $workers, Task Workers: $task_workers, Memory Limit: $memory_limit"
        
        return 0
    else
        if [ ${#missing_vars[@]} -gt 0 ]; then
            error "Missing Octane variables in $env: ${missing_vars[*]}"
        fi
        if [ ${#invalid_vars[@]} -gt 0 ]; then
            error "Invalid Octane variables in $env: ${invalid_vars[*]}"
        fi
        return 1
    fi
}

# Validate FrankenPHP configuration
validate_frankenphp_config() {
    log "Validating FrankenPHP configuration..."
    
    local frankenphp_config="$PROJECT_ROOT/deployment/config/frankenphp/Caddyfile"
    
    if [ ! -f "$frankenphp_config" ]; then
        error "FrankenPHP Caddyfile not found: $frankenphp_config"
        return 1
    fi
    
    # Check for required directives
    local required_directives=("frankenphp" "php_server" "root")
    local missing_directives=()
    
    for directive in "${required_directives[@]}"; do
        if ! grep -q "$directive" "$frankenphp_config"; then
            missing_directives+=("$directive")
        fi
    done
    
    if [ ${#missing_directives[@]} -eq 0 ]; then
        success "FrankenPHP Caddyfile configuration is valid"
        return 0
    else
        error "Missing FrankenPHP directives: ${missing_directives[*]}"
        return 1
    fi
}

# Validate Docker Compose Octane configuration
validate_docker_octane_config() {
    log "Validating Docker Compose Octane configuration..."
    
    local compose_file="$PROJECT_ROOT/docker-compose.yml"
    local override_file="$PROJECT_ROOT/deployment/docker/docker-compose.override.yml"
    
    if [ ! -f "$compose_file" ]; then
        error "Docker Compose file not found: $compose_file"
        return 1
    fi
    
    # Check for Octane-related environment variables in services
    local octane_vars_found=0
    
    for service in "${SERVICES[@]}"; do
        if grep -A 20 "^  $service:" "$compose_file" | grep -q "OCTANE_"; then
            ((octane_vars_found++))
        fi
    done
    
    if [ "$octane_vars_found" -gt 0 ]; then
        success "Found Octane configuration in $octane_vars_found services"
    else
        warning "No Octane configuration found in Docker Compose services"
    fi
    
    # Check development override
    if [ -f "$override_file" ]; then
        if grep -q "octane-dev-env" "$override_file"; then
            success "Development Octane configuration found in override file"
        else
            warning "No development Octane configuration in override file"
        fi
    else
        warning "Docker Compose override file not found"
    fi
    
    return 0
}

# Validate Kubernetes Octane configuration
validate_k8s_octane_config() {
    local env=$1
    log "Validating Kubernetes Octane configuration for $env..."
    
    local overlay_dir="$PROJECT_ROOT/deployment/k8s/overlays/$env"
    
    if [ ! -d "$overlay_dir" ]; then
        warning "Kubernetes overlay not found for $env environment"
        return 0
    fi
    
    local kustomization_file="$overlay_dir/kustomization.yaml"
    
    if [ ! -f "$kustomization_file" ]; then
        error "Kustomization file not found: $kustomization_file"
        return 1
    fi
    
    # Check for Octane-specific configuration
    local octane_config_found=false
    
    if grep -q "OCTANE_" "$kustomization_file"; then
        octane_config_found=true
        success "Octane configuration found in $env Kubernetes overlay"
        
        # Count Octane variables
        local octane_var_count=$(grep -c "OCTANE_" "$kustomization_file")
        log "  Found $octane_var_count Octane-related variables"
    else
        warning "No Octane configuration found in $env Kubernetes overlay"
    fi
    
    return 0
}

# Health check validation
validate_octane_health_endpoints() {
    log "Validating Octane health check endpoints..."
    
    # Check if health endpoints are configured in ingress
    local ingress_file="$PROJECT_ROOT/deployment/k8s/base/ingress.yaml"
    
    if [ -f "$ingress_file" ]; then
        if grep -q "/octane/health" "$ingress_file"; then
            success "Octane health check endpoint configured in ingress"
        else
            warning "Octane health check endpoint not found in ingress configuration"
        fi
    else
        warning "Ingress configuration file not found"
    fi
    
    # Check services configuration
    local services_file="$PROJECT_ROOT/deployment/k8s/base/services.yaml"
    
    if [ -f "$services_file" ]; then
        if grep -q "octane" "$services_file"; then
            success "Octane-related configuration found in services"
        else
            warning "No Octane-related configuration in services file"
        fi
    else
        warning "Services configuration file not found"
    fi
}

# Performance configuration validation
validate_octane_performance_config() {
    log "Validating Octane performance configuration..."
    
    for env in "${ENVIRONMENTS[@]}"; do
        local env_file="$PROJECT_ROOT/deployment/config/environments/$env.env"
        
        if [ ! -f "$env_file" ]; then
            continue
        fi
        
        # Get configuration values
        local workers=$(grep "^OCTANE_WORKERS=" "$env_file" 2>/dev/null | cut -d'=' -f2 || echo "0")
        local task_workers=$(grep "^OCTANE_TASK_WORKERS=" "$env_file" 2>/dev/null | cut -d'=' -f2 || echo "0")
        local max_requests=$(grep "^OCTANE_MAX_REQUESTS=" "$env_file" 2>/dev/null | cut -d'=' -f2 || echo "0")
        
        # Validate performance scaling
        case $env in
            "development")
                if [ "$workers" -le 2 ] && [ "$task_workers" -le 2 ]; then
                    success "$env environment has appropriate resource limits (workers: $workers, task_workers: $task_workers)"
                else
                    warning "$env environment may have excessive resource allocation"
                fi
                ;;
            "staging")
                if [ "$workers" -ge 2 ] && [ "$workers" -le 4 ] && [ "$task_workers" -ge 2 ] && [ "$task_workers" -le 6 ]; then
                    success "$env environment has balanced resource allocation (workers: $workers, task_workers: $task_workers)"
                else
                    warning "$env environment resource allocation may need adjustment"
                fi
                ;;
            "production")
                if [ "$workers" -ge 3 ] && [ "$task_workers" -ge 4 ]; then
                    success "$env environment has production-ready resource allocation (workers: $workers, task_workers: $task_workers)"
                else
                    warning "$env environment may need more resources for production workload"
                fi
                ;;
        esac
        
        # Validate max requests scaling
        if [ "$max_requests" -gt 0 ]; then
            local requests_per_worker=$((max_requests / workers))
            if [ "$requests_per_worker" -ge 50 ] && [ "$requests_per_worker" -le 500 ]; then
                success "$env environment has reasonable requests per worker ratio ($requests_per_worker)"
            else
                warning "$env environment requests per worker ratio may need adjustment ($requests_per_worker)"
            fi
        fi
    done
}

# Monitoring configuration validation
validate_octane_monitoring() {
    log "Validating Octane monitoring configuration..."
    
    # Check for Octane-specific dashboards
    local dashboard_dir="$PROJECT_ROOT/deployment/monitoring/grafana/dashboards"
    
    if [ -d "$dashboard_dir" ]; then
        local octane_dashboards=$(find "$dashboard_dir" -name "*octane*" -type f | wc -l)
        
        if [ "$octane_dashboards" -gt 0 ]; then
            success "Found $octane_dashboards Octane-specific dashboard(s)"
            
            # List dashboard files
            find "$dashboard_dir" -name "*octane*" -type f -exec basename {} \; | while read -r dashboard; do
                log "  - $dashboard"
            done
        else
            warning "No Octane-specific dashboards found"
        fi
    else
        warning "Grafana dashboards directory not found"
    fi
    
    # Check Prometheus configuration for Octane metrics
    local prometheus_config="$PROJECT_ROOT/deployment/monitoring/prometheus.yml"
    
    if [ -f "$prometheus_config" ]; then
        if grep -q "octane" "$prometheus_config"; then
            success "Octane metrics configuration found in Prometheus"
        else
            warning "No Octane-specific metrics configuration in Prometheus"
        fi
    else
        warning "Prometheus configuration file not found"
    fi
}

# Security validation for Octane
validate_octane_security() {
    log "Validating Octane security configuration..."
    
    # Check for secure defaults in production
    local prod_env="$PROJECT_ROOT/deployment/config/environments/production.env"
    
    if [ -f "$prod_env" ]; then
        # Check if debug mode is disabled
        local app_debug=$(grep "^APP_DEBUG=" "$prod_env" 2>/dev/null | cut -d'=' -f2 || echo "true")
        if [ "$app_debug" = "false" ]; then
            success "Debug mode disabled in production"
        else
            error "Debug mode should be disabled in production"
            return 1
        fi
        
        # Check if file watching is disabled
        local octane_watch=$(grep "^OCTANE_WATCH=" "$prod_env" 2>/dev/null | cut -d'=' -f2 || echo "true")
        if [ "$octane_watch" = "false" ]; then
            success "File watching disabled in production"
        else
            warning "File watching should be disabled in production for security"
        fi
        
        # Check OPcache settings
        local opcache_validate=$(grep "^PHP_OPCACHE_VALIDATE_TIMESTAMPS=" "$prod_env" 2>/dev/null | cut -d'=' -f2 || echo "1")
        if [ "$opcache_validate" = "0" ]; then
            success "OPcache timestamp validation disabled in production (performance optimized)"
        else
            warning "Consider disabling OPcache timestamp validation in production"
        fi
    else
        warning "Production environment file not found"
    fi
}

# Integration tests
test_octane_integration() {
    log "Testing Octane integration with other components..."
    
    # Check if Octane health endpoints are referenced in load balancer config
    local ingress_file="$PROJECT_ROOT/deployment/k8s/base/ingress.yaml"
    
    if [ -f "$ingress_file" ]; then
        if grep -q "health-check-path.*octane" "$ingress_file"; then
            success "Load balancer configured to use Octane health endpoints"
        else
            warning "Load balancer may not be using Octane health endpoints"
        fi
    fi
    
    # Check HPA configuration for Octane services
    local hpa_file="$PROJECT_ROOT/deployment/k8s/base/hpa.yaml"
    
    if [ -f "$hpa_file" ]; then
        local hpa_services=$(grep -c "kind: HorizontalPodAutoscaler" "$hpa_file")
        local total_services=${#SERVICES[@]}
        
        if [ "$hpa_services" -eq "$total_services" ]; then
            success "HPA configured for all $total_services services"
        else
            warning "HPA configured for $hpa_services out of $total_services services"
        fi
    else
        warning "HPA configuration file not found"
    fi
}

# Main validation function
main() {
    log "Starting Octane-specific validation..."
    log "Log file: $LOG_FILE"
    
    local total_tests=0
    local passed_tests=0
    local failed_tests=0
    
    # Configuration validation
    for env in "${ENVIRONMENTS[@]}"; do
        ((total_tests++))
        if validate_octane_config "$env"; then
            ((passed_tests++))
        else
            ((failed_tests++))
        fi
        
        ((total_tests++))
        if validate_k8s_octane_config "$env"; then
            ((passed_tests++))
        else
            ((failed_tests++))
        fi
    done
    
    # FrankenPHP validation
    ((total_tests++))
    if validate_frankenphp_config; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    
    # Docker validation
    ((total_tests++))
    if validate_docker_octane_config; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    
    # Health endpoints validation
    ((total_tests++))
    if validate_octane_health_endpoints; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    
    # Performance validation
    ((total_tests++))
    if validate_octane_performance_config; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    
    # Monitoring validation
    ((total_tests++))
    if validate_octane_monitoring; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    
    # Security validation
    ((total_tests++))
    if validate_octane_security; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    
    # Integration validation
    ((total_tests++))
    if test_octane_integration; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    
    # Summary
    log "Octane validation completed"
    log "Total tests: $total_tests"
    success "Passed tests: $passed_tests"
    if [ "$failed_tests" -gt 0 ]; then
        error "Failed tests: $failed_tests"
    else
        log "Failed tests: $failed_tests"
    fi
    
    log "Detailed log available at: $LOG_FILE"
    
    if [ "$failed_tests" -eq 0 ]; then
        success "All Octane validation tests passed! 🚀"
        exit 0
    else
        error "Some Octane validation tests failed. Please review the log file."
        exit 1
    fi
}

# Script execution
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi

