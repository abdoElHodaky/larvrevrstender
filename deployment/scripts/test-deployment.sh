#!/bin/bash

# End-to-End Deployment Testing Script for Reverse Tender Platform
# Tests the complete deployment pipeline across all environments

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
LOG_FILE="/tmp/deployment-test-$(date +%Y%m%d-%H%M%S).log"

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

# Test configuration
ENVIRONMENTS=("development" "staging" "production")
SERVICES=("api-gateway" "auth-service" "user-service" "bidding-service" "order-service" "payment-service" "notification-service" "analytics-service" "vin-ocr-service")
TIMEOUT=300

# Test functions
test_docker_compose() {
    local env=$1
    log "Testing Docker Compose for $env environment..."
    
    cd "$PROJECT_ROOT"
    
    # Test base docker-compose
    if docker-compose config > /dev/null 2>&1; then
        success "Base docker-compose.yml is valid"
    else
        error "Base docker-compose.yml is invalid"
        return 1
    fi
    
    # Test development override
    if [ "$env" = "development" ]; then
        if docker-compose -f docker-compose.yml -f deployment/docker/docker-compose.override.yml config > /dev/null 2>&1; then
            success "Development docker-compose override is valid"
        else
            error "Development docker-compose override is invalid"
            return 1
        fi
    fi
    
    # Test monitoring stack
    if docker-compose -f deployment/monitoring/docker-compose.monitoring.yml config > /dev/null 2>&1; then
        success "Monitoring docker-compose is valid"
    else
        error "Monitoring docker-compose is invalid"
        return 1
    fi
}

test_kubernetes_manifests() {
    local env=$1
    log "Testing Kubernetes manifests for $env environment..."
    
    cd "$PROJECT_ROOT/deployment/k8s"
    
    # Test base manifests
    if kubectl apply --dry-run=client -k base/ > /dev/null 2>&1; then
        success "Base Kubernetes manifests are valid"
    else
        error "Base Kubernetes manifests are invalid"
        return 1
    fi
    
    # Test environment overlay
    if [ -d "overlays/$env" ]; then
        if kubectl apply --dry-run=client -k "overlays/$env/" > /dev/null 2>&1; then
            success "$env overlay manifests are valid"
        else
            error "$env overlay manifests are invalid"
            return 1
        fi
    else
        warning "$env overlay directory not found"
    fi
}

test_environment_configs() {
    local env=$1
    log "Testing environment configuration for $env..."
    
    local env_file="$PROJECT_ROOT/deployment/config/environments/$env.env"
    
    if [ -f "$env_file" ]; then
        # Check for required variables
        local required_vars=("OCTANE_WORKERS" "OCTANE_TASK_WORKERS" "PHP_OPCACHE_ENABLE" "APP_ENV")
        local missing_vars=()
        
        for var in "${required_vars[@]}"; do
            if ! grep -q "^$var=" "$env_file"; then
                missing_vars+=("$var")
            fi
        done
        
        if [ ${#missing_vars[@]} -eq 0 ]; then
            success "All required environment variables present in $env.env"
        else
            error "Missing required variables in $env.env: ${missing_vars[*]}"
            return 1
        fi
    else
        error "Environment file not found: $env_file"
        return 1
    fi
}

test_terraform_configuration() {
    log "Testing Terraform configuration..."
    
    cd "$PROJECT_ROOT/deployment/terraform"
    
    # Initialize Terraform
    if terraform init -backend=false > /dev/null 2>&1; then
        success "Terraform initialization successful"
    else
        error "Terraform initialization failed"
        return 1
    fi
    
    # Validate configuration
    if terraform validate > /dev/null 2>&1; then
        success "Terraform configuration is valid"
    else
        error "Terraform configuration is invalid"
        return 1
    fi
    
    # Plan (dry run)
    if terraform plan -out=/tmp/tfplan > /dev/null 2>&1; then
        success "Terraform plan successful"
        rm -f /tmp/tfplan
    else
        error "Terraform plan failed"
        return 1
    fi
}

test_monitoring_configuration() {
    log "Testing monitoring configuration..."
    
    # Test Prometheus configuration
    if command -v promtool > /dev/null 2>&1; then
        if promtool check config "$PROJECT_ROOT/deployment/monitoring/prometheus.yml" > /dev/null 2>&1; then
            success "Prometheus configuration is valid"
        else
            error "Prometheus configuration is invalid"
            return 1
        fi
    else
        warning "promtool not available, skipping Prometheus config validation"
    fi
    
    # Test Alertmanager configuration
    if command -v amtool > /dev/null 2>&1; then
        if amtool check-config "$PROJECT_ROOT/deployment/monitoring/alertmanager.yml" > /dev/null 2>&1; then
            success "Alertmanager configuration is valid"
        else
            error "Alertmanager configuration is invalid"
            return 1
        fi
    else
        warning "amtool not available, skipping Alertmanager config validation"
    fi
    
    # Test Grafana dashboards
    local dashboard_dir="$PROJECT_ROOT/deployment/monitoring/grafana/dashboards"
    if [ -d "$dashboard_dir" ]; then
        local dashboard_count=$(find "$dashboard_dir" -name "*.json" | wc -l)
        if [ "$dashboard_count" -gt 0 ]; then
            success "Found $dashboard_count Grafana dashboards"
            
            # Validate JSON syntax
            local invalid_dashboards=0
            for dashboard in "$dashboard_dir"/*.json; do
                if [ -f "$dashboard" ]; then
                    if ! jq empty "$dashboard" > /dev/null 2>&1; then
                        error "Invalid JSON in dashboard: $(basename "$dashboard")"
                        ((invalid_dashboards++))
                    fi
                fi
            done
            
            if [ "$invalid_dashboards" -eq 0 ]; then
                success "All Grafana dashboards have valid JSON"
            else
                error "$invalid_dashboards dashboard(s) have invalid JSON"
                return 1
            fi
        else
            warning "No Grafana dashboards found"
        fi
    else
        warning "Grafana dashboards directory not found"
    fi
}

test_security_configuration() {
    log "Testing security configuration..."
    
    # Test network policies
    local network_policy_file="$PROJECT_ROOT/deployment/security/network-policies.yaml"
    if [ -f "$network_policy_file" ]; then
        if kubectl apply --dry-run=client -f "$network_policy_file" > /dev/null 2>&1; then
            success "Network policies are valid"
        else
            error "Network policies are invalid"
            return 1
        fi
    else
        warning "Network policies file not found"
    fi
    
    # Check for secrets in configuration files
    log "Scanning for potential secrets in configuration files..."
    if command -v trufflehog > /dev/null 2>&1; then
        if trufflehog filesystem "$PROJECT_ROOT/deployment" --no-update > /dev/null 2>&1; then
            success "No secrets detected in deployment configuration"
        else
            warning "Potential secrets detected - please review trufflehog output"
        fi
    else
        warning "trufflehog not available, skipping secret scanning"
    fi
}

test_deployment_scripts() {
    log "Testing deployment scripts..."
    
    # Test main deployment script
    local deploy_script="$PROJECT_ROOT/deployment/deploy.sh"
    if [ -f "$deploy_script" ] && [ -x "$deploy_script" ]; then
        success "Main deployment script exists and is executable"
    else
        error "Main deployment script is missing or not executable"
        return 1
    fi
    
    # Test Octane management script
    local octane_script="$PROJECT_ROOT/deployment/scripts/octane-management.sh"
    if [ -f "$octane_script" ] && [ -x "$octane_script" ]; then
        success "Octane management script exists and is executable"
    else
        error "Octane management script is missing or not executable"
        return 1
    fi
    
    # Test validation script
    local validate_script="$PROJECT_ROOT/deployment/scripts/validate.sh"
    if [ -f "$validate_script" ] && [ -x "$validate_script" ]; then
        success "Validation script exists and is executable"
    else
        error "Validation script is missing or not executable"
        return 1
    fi
}

# Integration tests
test_service_integration() {
    log "Testing service integration configuration..."
    
    # Check service dependencies in docker-compose
    local compose_file="$PROJECT_ROOT/docker-compose.yml"
    if [ -f "$compose_file" ]; then
        # Check if all services are defined
        local missing_services=()
        for service in "${SERVICES[@]}"; do
            if ! grep -q "^  $service:" "$compose_file"; then
                missing_services+=("$service")
            fi
        done
        
        if [ ${#missing_services[@]} -eq 0 ]; then
            success "All required services defined in docker-compose.yml"
        else
            error "Missing services in docker-compose.yml: ${missing_services[*]}"
            return 1
        fi
    else
        error "docker-compose.yml not found"
        return 1
    fi
}

# Performance tests
test_configuration_performance() {
    log "Testing configuration performance settings..."
    
    for env in "${ENVIRONMENTS[@]}"; do
        local env_file="$PROJECT_ROOT/deployment/config/environments/$env.env"
        if [ -f "$env_file" ]; then
            # Check Octane worker configuration
            local workers=$(grep "^OCTANE_WORKERS=" "$env_file" | cut -d'=' -f2)
            local task_workers=$(grep "^OCTANE_TASK_WORKERS=" "$env_file" | cut -d'=' -f2)
            
            if [ -n "$workers" ] && [ -n "$task_workers" ]; then
                if [ "$workers" -gt 0 ] && [ "$task_workers" -gt 0 ]; then
                    success "$env environment has valid Octane worker configuration (workers: $workers, task_workers: $task_workers)"
                else
                    error "$env environment has invalid Octane worker configuration"
                    return 1
                fi
            else
                error "$env environment missing Octane worker configuration"
                return 1
            fi
        fi
    done
}

# Main test execution
main() {
    log "Starting end-to-end deployment testing..."
    log "Log file: $LOG_FILE"
    
    local total_tests=0
    local passed_tests=0
    local failed_tests=0
    
    # Test each environment
    for env in "${ENVIRONMENTS[@]}"; do
        log "Testing $env environment..."
        
        # Docker Compose tests
        ((total_tests++))
        if test_docker_compose "$env"; then
            ((passed_tests++))
        else
            ((failed_tests++))
        fi
        
        # Kubernetes tests
        ((total_tests++))
        if test_kubernetes_manifests "$env"; then
            ((passed_tests++))
        else
            ((failed_tests++))
        fi
        
        # Environment configuration tests
        ((total_tests++))
        if test_environment_configs "$env"; then
            ((passed_tests++))
        else
            ((failed_tests++))
        fi
    done
    
    # Infrastructure tests
    ((total_tests++))
    if test_terraform_configuration; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    
    ((total_tests++))
    if test_monitoring_configuration; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    
    ((total_tests++))
    if test_security_configuration; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    
    ((total_tests++))
    if test_deployment_scripts; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    
    ((total_tests++))
    if test_service_integration; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    
    ((total_tests++))
    if test_configuration_performance; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    
    # Summary
    log "Test execution completed"
    log "Total tests: $total_tests"
    success "Passed tests: $passed_tests"
    if [ "$failed_tests" -gt 0 ]; then
        error "Failed tests: $failed_tests"
    else
        log "Failed tests: $failed_tests"
    fi
    
    log "Detailed log available at: $LOG_FILE"
    
    if [ "$failed_tests" -eq 0 ]; then
        success "All deployment tests passed! 🎉"
        exit 0
    else
        error "Some deployment tests failed. Please review the log file."
        exit 1
    fi
}

# Script execution
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi

