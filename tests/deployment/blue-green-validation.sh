#!/bin/bash

# Blue-Green Deployment Validation Test Suite
# Tests environment switching, health checks, and service discovery
# Part of Phase 1: Comprehensive Testing Framework

set -euo pipefail

# Configuration
BLUE_NAMESPACE="reverse-tender-blue"
GREEN_NAMESPACE="reverse-tender-green"
TIMEOUT=600
HEALTH_CHECK_TIMEOUT=120
LOG_FILE="/tmp/blue-green-test-$(date +%Y%m%d-%H%M%S).log"

# Service configuration
SERVICES=(
    "auth-service:8001"
    "user-service:8002"
    "analytics-service:8003"
    "order-service:8004"
    "bidding-service:8005"
    "payment-service:8006"
    "notification-service:8007"
    "vin-ocr-service:8008"
    "gateway-service:8009"
    "auction-service:8010"
)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Logging functions
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

info() {
    echo -e "${CYAN}[INFO]${NC} $1" | tee -a "$LOG_FILE"
}

# Test result tracking
TESTS_PASSED=0
TESTS_FAILED=0
FAILED_TESTS=()

# Function to run a test and track results
run_test() {
    local test_name="$1"
    local test_function="$2"
    
    log "Running test: $test_name"
    
    if $test_function; then
        success "✅ PASSED: $test_name"
        ((TESTS_PASSED++))
    else
        error "❌ FAILED: $test_name"
        FAILED_TESTS+=("$test_name")
        ((TESTS_FAILED++))
    fi
    
    echo "" | tee -a "$LOG_FILE"
}

# Helper function to check if namespace exists
namespace_exists() {
    local namespace="$1"
    kubectl get namespace "$namespace" &>/dev/null
}

# Helper function to wait for pods to be ready
wait_for_pods_ready() {
    local namespace="$1"
    local timeout="$2"
    local attempts=0
    local max_attempts=$((timeout / 5))
    
    log "Waiting for pods in namespace $namespace to be ready..."
    
    while [[ $attempts -lt $max_attempts ]]; do
        local not_ready_pods
        not_ready_pods=$(kubectl get pods -n "$namespace" --field-selector=status.phase!=Running -o name 2>/dev/null | wc -l)
        
        if [[ $not_ready_pods -eq 0 ]]; then
            # Check if all pods are actually ready (not just running)
            local total_pods
            total_pods=$(kubectl get pods -n "$namespace" -o name 2>/dev/null | wc -l)
            local ready_pods
            ready_pods=$(kubectl get pods -n "$namespace" -o jsonpath='{.items[*].status.conditions[?(@.type=="Ready")].status}' 2>/dev/null | grep -o "True" | wc -l)
            
            if [[ $total_pods -eq $ready_pods ]] && [[ $total_pods -gt 0 ]]; then
                success "All $total_pods pods in $namespace are ready"
                return 0
            fi
        fi
        
        sleep 5
        ((attempts++))
        
        if [[ $((attempts % 12)) -eq 0 ]]; then
            log "Still waiting for pods in $namespace... (${attempts}0s elapsed)"
        fi
    done
    
    error "Pods in $namespace did not become ready within ${timeout}s"
    return 1
}

# Test 1: Environment Namespace Validation
test_environment_namespaces() {
    log "Testing blue-green environment namespaces..."
    
    local namespaces_valid=true
    
    # Check blue namespace
    if namespace_exists "$BLUE_NAMESPACE"; then
        success "Blue namespace '$BLUE_NAMESPACE' exists"
    else
        error "Blue namespace '$BLUE_NAMESPACE' does not exist"
        namespaces_valid=false
    fi
    
    # Check green namespace
    if namespace_exists "$GREEN_NAMESPACE"; then
        success "Green namespace '$GREEN_NAMESPACE' exists"
    else
        error "Green namespace '$GREEN_NAMESPACE' does not exist"
        namespaces_valid=false
    fi
    
    return $([[ "$namespaces_valid" == "true" ]] && echo 0 || echo 1)
}

# Test 2: Service Deployment Validation
test_service_deployments() {
    log "Testing service deployments in both environments..."
    
    local all_deployments_valid=true
    
    for namespace in "$BLUE_NAMESPACE" "$GREEN_NAMESPACE"; do
        log "Checking deployments in $namespace..."
        
        for service_config in "${SERVICES[@]}"; do
            local service_name
            service_name=$(echo "$service_config" | cut -d':' -f1)
            
            # Check if deployment exists
            if kubectl get deployment "$service_name" -n "$namespace" &>/dev/null; then
                # Check deployment status
                local ready_replicas
                ready_replicas=$(kubectl get deployment "$service_name" -n "$namespace" -o jsonpath='{.status.readyReplicas}' 2>/dev/null || echo "0")
                local desired_replicas
                desired_replicas=$(kubectl get deployment "$service_name" -n "$namespace" -o jsonpath='{.spec.replicas}' 2>/dev/null || echo "1")
                
                if [[ "$ready_replicas" == "$desired_replicas" ]]; then
                    success "$service_name deployment in $namespace: $ready_replicas/$desired_replicas ready"
                else
                    error "$service_name deployment in $namespace: $ready_replicas/$desired_replicas ready"
                    all_deployments_valid=false
                fi
            else
                error "$service_name deployment not found in $namespace"
                all_deployments_valid=false
            fi
        done
    done
    
    return $([[ "$all_deployments_valid" == "true" ]] && echo 0 || echo 1)
}

# Test 3: Health Check Validation
test_health_checks() {
    log "Testing health checks for all services..."
    
    local all_health_checks_pass=true
    
    for namespace in "$BLUE_NAMESPACE" "$GREEN_NAMESPACE"; do
        log "Testing health checks in $namespace..."
        
        for service_config in "${SERVICES[@]}"; do
            local service_name
            service_name=$(echo "$service_config" | cut -d':' -f1)
            local service_port
            service_port=$(echo "$service_config" | cut -d':' -f2)
            
            # Get service endpoint
            local service_ip
            service_ip=$(kubectl get service "$service_name" -n "$namespace" -o jsonpath='{.spec.clusterIP}' 2>/dev/null || echo "")
            
            if [[ -z "$service_ip" ]]; then
                error "Service $service_name not found in $namespace"
                all_health_checks_pass=false
                continue
            fi
            
            # Test health check endpoint
            local health_check_url="http://$service_ip:$service_port/health"
            
            # Use kubectl exec to test from within cluster
            local test_pod
            test_pod=$(kubectl get pods -n "$namespace" -l app="$service_name" -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
            
            if [[ -n "$test_pod" ]]; then
                local health_response
                health_response=$(kubectl exec "$test_pod" -n "$namespace" -- curl -s -o /dev/null -w "%{http_code}" "$health_check_url" 2>/dev/null || echo "000")
                
                if [[ "$health_response" == "200" ]]; then
                    success "$service_name health check in $namespace: HTTP $health_response"
                else
                    error "$service_name health check in $namespace: HTTP $health_response"
                    all_health_checks_pass=false
                fi
            else
                error "No pods found for $service_name in $namespace"
                all_health_checks_pass=false
            fi
        done
    done
    
    return $([[ "$all_health_checks_pass" == "true" ]] && echo 0 || echo 1)
}

# Test 4: Cross-Environment Connectivity
test_cross_environment_connectivity() {
    log "Testing cross-environment connectivity..."
    
    local connectivity_valid=true
    
    # Test connectivity from blue to green
    log "Testing connectivity from blue to green environment..."
    
    local blue_test_pod
    blue_test_pod=$(kubectl get pods -n "$BLUE_NAMESPACE" -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
    
    if [[ -z "$blue_test_pod" ]]; then
        error "No pods found in blue environment for connectivity test"
        return 1
    fi
    
    # Test connection to green gateway service
    local green_gateway_ip
    green_gateway_ip=$(kubectl get service gateway-service -n "$GREEN_NAMESPACE" -o jsonpath='{.spec.clusterIP}' 2>/dev/null || echo "")
    
    if [[ -n "$green_gateway_ip" ]]; then
        local connectivity_test
        connectivity_test=$(kubectl exec "$blue_test_pod" -n "$BLUE_NAMESPACE" -- curl -s -o /dev/null -w "%{http_code}" "http://$green_gateway_ip:8009/health" 2>/dev/null || echo "000")
        
        if [[ "$connectivity_test" == "200" ]]; then
            success "Blue to Green connectivity: HTTP $connectivity_test"
        else
            error "Blue to Green connectivity failed: HTTP $connectivity_test"
            connectivity_valid=false
        fi
    else
        error "Green gateway service IP not found"
        connectivity_valid=false
    fi
    
    # Test connectivity from green to blue
    log "Testing connectivity from green to blue environment..."
    
    local green_test_pod
    green_test_pod=$(kubectl get pods -n "$GREEN_NAMESPACE" -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
    
    if [[ -n "$green_test_pod" ]]; then
        local blue_gateway_ip
        blue_gateway_ip=$(kubectl get service gateway-service -n "$BLUE_NAMESPACE" -o jsonpath='{.spec.clusterIP}' 2>/dev/null || echo "")
        
        if [[ -n "$blue_gateway_ip" ]]; then
            local connectivity_test
            connectivity_test=$(kubectl exec "$green_test_pod" -n "$GREEN_NAMESPACE" -- curl -s -o /dev/null -w "%{http_code}" "http://$blue_gateway_ip:8009/health" 2>/dev/null || echo "000")
            
            if [[ "$connectivity_test" == "200" ]]; then
                success "Green to Blue connectivity: HTTP $connectivity_test"
            else
                error "Green to Blue connectivity failed: HTTP $connectivity_test"
                connectivity_valid=false
            fi
        else
            error "Blue gateway service IP not found"
            connectivity_valid=false
        fi
    else
        error "No pods found in green environment for connectivity test"
        connectivity_valid=false
    fi
    
    return $([[ "$connectivity_valid" == "true" ]] && echo 0 || echo 1)
}

# Test 5: Service Discovery Validation
test_service_discovery() {
    log "Testing service discovery within environments..."
    
    local service_discovery_valid=true
    
    for namespace in "$BLUE_NAMESPACE" "$GREEN_NAMESPACE"; do
        log "Testing service discovery in $namespace..."
        
        # Get a test pod
        local test_pod
        test_pod=$(kubectl get pods -n "$namespace" -l app=gateway-service -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
        
        if [[ -z "$test_pod" ]]; then
            error "No gateway-service pod found in $namespace for service discovery test"
            service_discovery_valid=false
            continue
        fi
        
        # Test DNS resolution for each service
        for service_config in "${SERVICES[@]}"; do
            local service_name
            service_name=$(echo "$service_config" | cut -d':' -f1)
            
            # Test DNS resolution
            local dns_test
            dns_test=$(kubectl exec "$test_pod" -n "$namespace" -- nslookup "$service_name.$namespace.svc.cluster.local" 2>/dev/null | grep -c "Address:" || echo "0")
            
            if [[ $dns_test -gt 0 ]]; then
                success "Service discovery for $service_name in $namespace: DNS resolved"
            else
                error "Service discovery for $service_name in $namespace: DNS resolution failed"
                service_discovery_valid=false
            fi
        done
    done
    
    return $([[ "$service_discovery_valid" == "true" ]] && echo 0 || echo 1)
}

# Test 6: Environment Consistency Check
test_environment_consistency() {
    log "Testing environment consistency between blue and green..."
    
    local consistency_valid=true
    
    # Compare service configurations
    for service_config in "${SERVICES[@]}"; do
        local service_name
        service_name=$(echo "$service_config" | cut -d':' -f1)
        
        # Get blue environment configuration
        local blue_image
        blue_image=$(kubectl get deployment "$service_name" -n "$BLUE_NAMESPACE" -o jsonpath='{.spec.template.spec.containers[0].image}' 2>/dev/null || echo "")
        local blue_replicas
        blue_replicas=$(kubectl get deployment "$service_name" -n "$BLUE_NAMESPACE" -o jsonpath='{.spec.replicas}' 2>/dev/null || echo "")
        
        # Get green environment configuration
        local green_image
        green_image=$(kubectl get deployment "$service_name" -n "$GREEN_NAMESPACE" -o jsonpath='{.spec.template.spec.containers[0].image}' 2>/dev/null || echo "")
        local green_replicas
        green_replicas=$(kubectl get deployment "$service_name" -n "$GREEN_NAMESPACE" -o jsonpath='{.spec.replicas}' 2>/dev/null || echo "")
        
        # Compare configurations
        if [[ "$blue_image" == "$green_image" ]]; then
            success "$service_name image consistency: $blue_image"
        else
            warning "$service_name image difference: Blue=$blue_image, Green=$green_image"
            # This might be expected during deployment, so it's a warning not an error
        fi
        
        if [[ "$blue_replicas" == "$green_replicas" ]]; then
            success "$service_name replica consistency: $blue_replicas"
        else
            error "$service_name replica difference: Blue=$blue_replicas, Green=$green_replicas"
            consistency_valid=false
        fi
    done
    
    return $([[ "$consistency_valid" == "true" ]] && echo 0 || echo 1)
}

# Test 7: Database Migration Coordination
test_database_migration_coordination() {
    log "Testing database migration coordination..."
    
    # Check if migration service is available
    local migration_pod
    migration_pod=$(kubectl get pods -n "$BLUE_NAMESPACE" -l app=shared -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
    
    if [[ -z "$migration_pod" ]]; then
        migration_pod=$(kubectl get pods -n "$GREEN_NAMESPACE" -l app=shared -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
    fi
    
    if [[ -z "$migration_pod" ]]; then
        error "No shared service pod found for migration testing"
        return 1
    fi
    
    local namespace
    namespace=$(kubectl get pod "$migration_pod" -o jsonpath='{.metadata.namespace}' 2>/dev/null || echo "")
    
    # Test migration status check
    local migration_status
    migration_status=$(kubectl exec "$migration_pod" -n "$namespace" -- php artisan blue-green:migration-status 2>/dev/null || echo "FAILED")
    
    if [[ "$migration_status" != "FAILED" ]]; then
        success "Database migration coordination is functional"
        info "Migration status: $migration_status"
        return 0
    else
        error "Database migration coordination test failed"
        return 1
    fi
}

# Main test execution
main() {
    log "Starting Blue-Green Deployment Validation Test Suite"
    log "Logging to: $LOG_FILE"
    log "Blue Namespace: $BLUE_NAMESPACE"
    log "Green Namespace: $GREEN_NAMESPACE"
    log "Timeout: ${TIMEOUT}s"
    echo ""
    
    # Check if kubectl is available
    if ! command -v kubectl &>/dev/null; then
        error "kubectl is not installed or not in PATH"
        exit 1
    fi
    
    # Check if cluster is accessible
    if ! kubectl cluster-info &>/dev/null; then
        error "Cannot access Kubernetes cluster"
        exit 1
    fi
    
    # Run all tests
    run_test "Environment Namespace Validation" test_environment_namespaces
    run_test "Service Deployment Validation" test_service_deployments
    run_test "Health Check Validation" test_health_checks
    run_test "Cross-Environment Connectivity" test_cross_environment_connectivity
    run_test "Service Discovery Validation" test_service_discovery
    run_test "Environment Consistency Check" test_environment_consistency
    run_test "Database Migration Coordination" test_database_migration_coordination
    
    # Print summary
    echo "=================================="
    log "Blue-Green Validation Test Summary"
    echo "=================================="
    success "Tests Passed: $TESTS_PASSED"
    
    if [[ $TESTS_FAILED -gt 0 ]]; then
        error "Tests Failed: $TESTS_FAILED"
        error "Failed tests:"
        for test in "${FAILED_TESTS[@]}"; do
            error "  - $test"
        done
        echo ""
        error "❌ Blue-Green validation tests FAILED"
        exit 1
    else
        echo ""
        success "✅ All Blue-Green validation tests PASSED"
        exit 0
    fi
}

# Run main function
main "$@"

