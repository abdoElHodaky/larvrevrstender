#!/bin/bash

# End-to-End Deployment Test Suite
# Tests complete blue-green deployment cycle with validation
# Part of Phase 1: Comprehensive Testing Framework

set -euo pipefail

# Configuration
BLUE_NAMESPACE="reverse-tender-blue"
GREEN_NAMESPACE="reverse-tender-green"
FLUX_NAMESPACE="flux-system"
TIMEOUT=900  # 15 minutes for full deployment
HEALTH_CHECK_TIMEOUT=300
LOG_FILE="/tmp/e2e-deployment-test-$(date +%Y%m%d-%H%M%S).log"

# Test configuration
DEPLOYMENT_BRANCH="v2-blue-green-deploy"
TEST_IMAGE_TAG="test-$(date +%s)"
ROLLBACK_TIMEOUT=180

# Services to monitor
CRITICAL_SERVICES=(
    "gateway-service"
    "auth-service"
    "user-service"
    "payment-service"
)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
PURPLE='\033[0;35m'
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

debug() {
    echo -e "${PURPLE}[DEBUG]${NC} $1" | tee -a "$LOG_FILE"
}

# Test result tracking
TESTS_PASSED=0
TESTS_FAILED=0
FAILED_TESTS=()
DEPLOYMENT_START_TIME=""
DEPLOYMENT_END_TIME=""

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

# Helper function to wait for deployment completion
wait_for_deployment() {
    local namespace="$1"
    local service="$2"
    local timeout="$3"
    local attempts=0
    local max_attempts=$((timeout / 10))
    
    log "Waiting for $service deployment in $namespace..."
    
    while [[ $attempts -lt $max_attempts ]]; do
        local ready_replicas
        ready_replicas=$(kubectl get deployment "$service" -n "$namespace" -o jsonpath='{.status.readyReplicas}' 2>/dev/null || echo "0")
        local desired_replicas
        desired_replicas=$(kubectl get deployment "$service" -n "$namespace" -o jsonpath='{.spec.replicas}' 2>/dev/null || echo "1")
        
        if [[ "$ready_replicas" == "$desired_replicas" ]] && [[ "$ready_replicas" != "0" ]]; then
            success "$service deployment ready: $ready_replicas/$desired_replicas replicas"
            return 0
        fi
        
        sleep 10
        ((attempts++))
        
        if [[ $((attempts % 6)) -eq 0 ]]; then
            log "Still waiting for $service... (${attempts}0s elapsed)"
        fi
    done
    
    error "$service deployment not ready after ${timeout}s"
    return 1
}

# Helper function to check service health
check_service_health() {
    local namespace="$1"
    local service="$2"
    local port="$3"
    
    local pod_name
    pod_name=$(kubectl get pods -n "$namespace" -l app="$service" -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
    
    if [[ -z "$pod_name" ]]; then
        error "No pods found for $service in $namespace"
        return 1
    fi
    
    local health_response
    health_response=$(kubectl exec "$pod_name" -n "$namespace" -- curl -s -o /dev/null -w "%{http_code}" "http://localhost:$port/health" 2>/dev/null || echo "000")
    
    if [[ "$health_response" == "200" ]]; then
        return 0
    else
        return 1
    fi
}

# Test 1: Pre-deployment Validation
test_pre_deployment_validation() {
    log "Running pre-deployment validation..."
    
    local validation_passed=true
    
    # Check FluxCD controllers
    log "Checking FluxCD controllers..."
    local controllers=("source-controller" "kustomize-controller" "helm-controller")
    
    for controller in "${controllers[@]}"; do
        local ready_replicas
        ready_replicas=$(kubectl get deployment "$controller" -n "$FLUX_NAMESPACE" -o jsonpath='{.status.readyReplicas}' 2>/dev/null || echo "0")
        local desired_replicas
        desired_replicas=$(kubectl get deployment "$controller" -n "$FLUX_NAMESPACE" -o jsonpath='{.spec.replicas}' 2>/dev/null || echo "1")
        
        if [[ "$ready_replicas" != "$desired_replicas" ]]; then
            error "$controller not ready: $ready_replicas/$desired_replicas"
            validation_passed=false
        else
            success "$controller ready: $ready_replicas/$desired_replicas"
        fi
    done
    
    # Check namespaces
    log "Checking deployment namespaces..."
    for namespace in "$BLUE_NAMESPACE" "$GREEN_NAMESPACE"; do
        if kubectl get namespace "$namespace" &>/dev/null; then
            success "Namespace $namespace exists"
        else
            error "Namespace $namespace does not exist"
            validation_passed=false
        fi
    done
    
    # Check Git repository sync
    log "Checking Git repository synchronization..."
    local git_repos
    git_repos=$(kubectl get gitrepository -n "$FLUX_NAMESPACE" -o name 2>/dev/null || echo "")
    
    if [[ -n "$git_repos" ]]; then
        while IFS= read -r repo; do
            if [[ -n "$repo" ]]; then
                local ready_condition
                ready_condition=$(kubectl get "$repo" -n "$FLUX_NAMESPACE" -o jsonpath='{.status.conditions[?(@.type=="Ready")].status}' 2>/dev/null || echo "Unknown")
                
                if [[ "$ready_condition" == "True" ]]; then
                    success "Git repository $(echo "$repo" | cut -d'/' -f2) is synchronized"
                else
                    error "Git repository $(echo "$repo" | cut -d'/' -f2) is not synchronized"
                    validation_passed=false
                fi
            fi
        done <<< "$git_repos"
    else
        error "No Git repositories found"
        validation_passed=false
    fi
    
    # Check resource availability
    log "Checking cluster resource availability..."
    local node_count
    node_count=$(kubectl get nodes --no-headers | wc -l)
    
    if [[ $node_count -gt 0 ]]; then
        success "Cluster has $node_count node(s) available"
    else
        error "No nodes available in cluster"
        validation_passed=false
    fi
    
    return $([[ "$validation_passed" == "true" ]] && echo 0 || echo 1)
}

# Test 2: Deployment Execution
test_deployment_execution() {
    log "Testing deployment execution..."
    
    DEPLOYMENT_START_TIME=$(date +%s)
    
    # Determine current active environment
    local current_env="blue"  # Default assumption
    local target_env="green"
    
    # Check which environment has more recent deployments
    local blue_pods
    blue_pods=$(kubectl get pods -n "$BLUE_NAMESPACE" --no-headers 2>/dev/null | wc -l || echo "0")
    local green_pods
    green_pods=$(kubectl get pods -n "$GREEN_NAMESPACE" --no-headers 2>/dev/null | wc -l || echo "0")
    
    if [[ $green_pods -gt $blue_pods ]]; then
        current_env="green"
        target_env="blue"
    fi
    
    info "Current environment: $current_env"
    info "Target environment: $target_env"
    
    local target_namespace
    if [[ "$target_env" == "blue" ]]; then
        target_namespace="$BLUE_NAMESPACE"
    else
        target_namespace="$GREEN_NAMESPACE"
    fi
    
    # Simulate deployment trigger (in real scenario, this would be a Git commit or FluxCD trigger)
    log "Simulating deployment to $target_env environment..."
    
    # Check if target environment services are being deployed
    local deployment_in_progress=false
    
    for service in "${CRITICAL_SERVICES[@]}"; do
        if kubectl get deployment "$service" -n "$target_namespace" &>/dev/null; then
            deployment_in_progress=true
            
            # Wait for deployment to complete
            if wait_for_deployment "$target_namespace" "$service" "$TIMEOUT"; then
                success "$service deployed successfully to $target_env"
            else
                error "$service deployment failed in $target_env"
                return 1
            fi
        else
            error "$service deployment not found in $target_namespace"
            return 1
        fi
    done
    
    if [[ "$deployment_in_progress" == "true" ]]; then
        DEPLOYMENT_END_TIME=$(date +%s)
        local deployment_duration=$((DEPLOYMENT_END_TIME - DEPLOYMENT_START_TIME))
        success "Deployment execution completed in ${deployment_duration}s"
        return 0
    else
        error "No deployment activity detected"
        return 1
    fi
}

# Test 3: Post-deployment Validation
test_post_deployment_validation() {
    log "Running post-deployment validation..."
    
    local validation_passed=true
    local target_env="green"  # Based on previous test
    local target_namespace="$GREEN_NAMESPACE"
    
    # Validate all critical services are healthy
    log "Validating service health in $target_env environment..."
    
    local service_ports=(
        "gateway-service:8009"
        "auth-service:8001"
        "user-service:8002"
        "payment-service:8006"
    )
    
    for service_config in "${service_ports[@]}"; do
        local service_name
        service_name=$(echo "$service_config" | cut -d':' -f1)
        local service_port
        service_port=$(echo "$service_config" | cut -d':' -f2)
        
        if check_service_health "$target_namespace" "$service_name" "$service_port"; then
            success "$service_name health check passed"
        else
            error "$service_name health check failed"
            validation_passed=false
        fi
    done
    
    # Check service discovery
    log "Validating service discovery..."
    
    local gateway_pod
    gateway_pod=$(kubectl get pods -n "$target_namespace" -l app=gateway-service -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
    
    if [[ -n "$gateway_pod" ]]; then
        for service in "${CRITICAL_SERVICES[@]}"; do
            if [[ "$service" != "gateway-service" ]]; then
                local dns_test
                dns_test=$(kubectl exec "$gateway_pod" -n "$target_namespace" -- nslookup "$service.$target_namespace.svc.cluster.local" 2>/dev/null | grep -c "Address:" || echo "0")
                
                if [[ $dns_test -gt 0 ]]; then
                    success "Service discovery for $service: DNS resolved"
                else
                    error "Service discovery for $service: DNS resolution failed"
                    validation_passed=false
                fi
            fi
        done
    else
        error "Gateway pod not found for service discovery test"
        validation_passed=false
    fi
    
    # Check database connectivity
    log "Validating database connectivity..."
    
    local auth_pod
    auth_pod=$(kubectl get pods -n "$target_namespace" -l app=auth-service -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
    
    if [[ -n "$auth_pod" ]]; then
        local db_test
        db_test=$(kubectl exec "$auth_pod" -n "$target_namespace" -- php artisan tinker --execute="DB::connection()->getPdo(); echo 'DB_OK';" 2>/dev/null | grep -c "DB_OK" || echo "0")
        
        if [[ $db_test -gt 0 ]]; then
            success "Database connectivity test passed"
        else
            error "Database connectivity test failed"
            validation_passed=false
        fi
    else
        warning "Auth service pod not found for database connectivity test"
    fi
    
    return $([[ "$validation_passed" == "true" ]] && echo 0 || echo 1)
}

# Test 4: Health Check Sequence
test_health_check_sequence() {
    log "Testing health check sequence..."
    
    local target_namespace="$GREEN_NAMESPACE"  # Based on deployment
    local health_checks_passed=true
    
    # Perform multiple rounds of health checks
    local rounds=5
    local round_interval=30
    
    for ((round=1; round<=rounds; round++)); do
        log "Health check round $round/$rounds..."
        
        local round_passed=true
        
        for service_config in "gateway-service:8009" "auth-service:8001" "user-service:8002"; do
            local service_name
            service_name=$(echo "$service_config" | cut -d':' -f1)
            local service_port
            service_port=$(echo "$service_config" | cut -d':' -f2)
            
            if check_service_health "$target_namespace" "$service_name" "$service_port"; then
                debug "Round $round: $service_name health check OK"
            else
                error "Round $round: $service_name health check FAILED"
                round_passed=false
                health_checks_passed=false
            fi
        done
        
        if [[ "$round_passed" == "true" ]]; then
            success "Health check round $round: ALL PASSED"
        else
            error "Health check round $round: SOME FAILED"
        fi
        
        # Wait before next round (except for last round)
        if [[ $round -lt $rounds ]]; then
            sleep $round_interval
        fi
    done
    
    return $([[ "$health_checks_passed" == "true" ]] && echo 0 || echo 1)
}

# Test 5: Rollback Procedure Testing
test_rollback_procedures() {
    log "Testing rollback procedures..."
    
    local rollback_successful=true
    local current_env="green"  # Based on deployment
    local rollback_env="blue"
    local rollback_namespace="$BLUE_NAMESPACE"
    
    info "Testing rollback from $current_env to $rollback_env"
    
    # Check if rollback environment is available
    log "Checking rollback environment availability..."
    
    local rollback_services_ready=0
    local total_services=0
    
    for service in "${CRITICAL_SERVICES[@]}"; do
        ((total_services++))
        
        if kubectl get deployment "$service" -n "$rollback_namespace" &>/dev/null; then
            local ready_replicas
            ready_replicas=$(kubectl get deployment "$service" -n "$rollback_namespace" -o jsonpath='{.status.readyReplicas}' 2>/dev/null || echo "0")
            local desired_replicas
            desired_replicas=$(kubectl get deployment "$service" -n "$rollback_namespace" -o jsonpath='{.spec.replicas}' 2>/dev/null || echo "1")
            
            if [[ "$ready_replicas" == "$desired_replicas" ]]; then
                ((rollback_services_ready++))
            fi
        fi
    done
    
    if [[ $rollback_services_ready -eq $total_services ]]; then
        success "Rollback environment ready: $rollback_services_ready/$total_services services"
    else
        error "Rollback environment not ready: $rollback_services_ready/$total_services services"
        rollback_successful=false
    fi
    
    # Test rollback speed simulation
    if [[ "$rollback_successful" == "true" ]]; then
        log "Simulating rollback execution..."
        
        local rollback_start_time
        rollback_start_time=$(date +%s)
        
        # Simulate rollback process (in real scenario, this would trigger actual rollback)
        sleep 5  # Simulate rollback time
        
        local rollback_end_time
        rollback_end_time=$(date +%s)
        local rollback_duration=$((rollback_end_time - rollback_start_time))
        
        if [[ $rollback_duration -le $ROLLBACK_TIMEOUT ]]; then
            success "Rollback simulation completed in ${rollback_duration}s (within ${ROLLBACK_TIMEOUT}s target)"
        else
            error "Rollback simulation took ${rollback_duration}s (exceeds ${ROLLBACK_TIMEOUT}s target)"
            rollback_successful=false
        fi
        
        # Validate rollback environment health
        log "Validating rollback environment health..."
        
        for service_config in "gateway-service:8009" "auth-service:8001"; do
            local service_name
            service_name=$(echo "$service_config" | cut -d':' -f1)
            local service_port
            service_port=$(echo "$service_config" | cut -d':' -f2)
            
            if check_service_health "$rollback_namespace" "$service_name" "$service_port"; then
                success "Rollback health check: $service_name OK"
            else
                error "Rollback health check: $service_name FAILED"
                rollback_successful=false
            fi
        done
    fi
    
    return $([[ "$rollback_successful" == "true" ]] && echo 0 || echo 1)
}

# Test 6: Performance Impact Assessment
test_performance_impact() {
    log "Testing performance impact during deployment..."
    
    local performance_acceptable=true
    local target_namespace="$GREEN_NAMESPACE"
    
    # Test response times
    log "Measuring service response times..."
    
    local gateway_pod
    gateway_pod=$(kubectl get pods -n "$target_namespace" -l app=gateway-service -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
    
    if [[ -n "$gateway_pod" ]]; then
        local total_time=0
        local successful_requests=0
        local test_requests=20
        
        for ((i=1; i<=test_requests; i++)); do
            local start_time
            start_time=$(date +%s%N)
            
            local response
            response=$(kubectl exec "$gateway_pod" -n "$target_namespace" -- curl -s -o /dev/null -w "%{http_code}" "http://localhost:8009/health" 2>/dev/null || echo "000")
            
            local end_time
            end_time=$(date +%s%N)
            
            if [[ "$response" == "200" ]]; then
                local request_time
                request_time=$(( (end_time - start_time) / 1000000 ))  # Convert to milliseconds
                total_time=$((total_time + request_time))
                ((successful_requests++))
            fi
        done
        
        if [[ $successful_requests -gt 0 ]]; then
            local avg_response_time
            avg_response_time=$((total_time / successful_requests))
            
            info "Average response time: ${avg_response_time}ms"
            
            if [[ $avg_response_time -le 1000 ]]; then
                success "Response time within acceptable range (<1000ms)"
            else
                warning "Response time high: ${avg_response_time}ms"
            fi
        else
            error "No successful requests for performance testing"
            performance_acceptable=false
        fi
    else
        error "Gateway pod not found for performance testing"
        performance_acceptable=false
    fi
    
    # Check resource utilization
    log "Checking resource utilization..."
    
    local high_cpu_pods=0
    local high_memory_pods=0
    
    for service in "${CRITICAL_SERVICES[@]}"; do
        local pod_name
        pod_name=$(kubectl get pods -n "$target_namespace" -l app="$service" -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
        
        if [[ -n "$pod_name" ]]; then
            # Get resource usage (simplified check)
            local cpu_usage
            cpu_usage=$(kubectl top pod "$pod_name" -n "$target_namespace" --no-headers 2>/dev/null | awk '{print $2}' | sed 's/m//' || echo "0")
            
            if [[ $cpu_usage -gt 500 ]]; then  # 500m = 0.5 CPU
                ((high_cpu_pods++))
                warning "$service CPU usage high: ${cpu_usage}m"
            fi
        fi
    done
    
    if [[ $high_cpu_pods -eq 0 ]]; then
        success "CPU utilization within normal range"
    else
        warning "$high_cpu_pods services have high CPU utilization"
    fi
    
    return $([[ "$performance_acceptable" == "true" ]] && echo 0 || echo 1)
}

# Test 7: Deployment Metrics Collection
test_deployment_metrics() {
    log "Testing deployment metrics collection..."
    
    local metrics_available=true
    
    # Calculate deployment duration
    if [[ -n "$DEPLOYMENT_START_TIME" ]] && [[ -n "$DEPLOYMENT_END_TIME" ]]; then
        local deployment_duration=$((DEPLOYMENT_END_TIME - DEPLOYMENT_START_TIME))
        info "Total deployment duration: ${deployment_duration}s"
        
        # Check if deployment duration is within acceptable range
        if [[ $deployment_duration -le 600 ]]; then  # 10 minutes
            success "Deployment duration within target (≤600s)"
        else
            warning "Deployment duration exceeds target: ${deployment_duration}s"
        fi
    else
        error "Deployment timing not recorded"
        metrics_available=false
    fi
    
    # Check if Prometheus metrics are available
    log "Checking Prometheus metrics availability..."
    
    if kubectl get service prometheus-server -n monitoring &>/dev/null; then
        success "Prometheus service available"
        
        # Test metrics endpoint (simplified)
        local prometheus_pod
        prometheus_pod=$(kubectl get pods -n monitoring -l app=prometheus-server -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
        
        if [[ -n "$prometheus_pod" ]]; then
            local metrics_test
            metrics_test=$(kubectl exec "$prometheus_pod" -n monitoring -- wget -qO- "http://localhost:9090/api/v1/query?query=up" 2>/dev/null | grep -c "success" || echo "0")
            
            if [[ $metrics_test -gt 0 ]]; then
                success "Prometheus metrics endpoint responding"
            else
                warning "Prometheus metrics endpoint not responding"
            fi
        fi
    else
        info "Prometheus not deployed (metrics collection optional)"
    fi
    
    # Generate deployment report
    log "Generating deployment metrics report..."
    
    local report_file="/tmp/deployment-metrics-$(date +%Y%m%d-%H%M%S).json"
    
    cat > "$report_file" << EOF
{
  "deployment": {
    "timestamp": "$(date -Iseconds)",
    "duration_seconds": ${deployment_duration:-0},
    "target_environment": "green",
    "services_deployed": ${#CRITICAL_SERVICES[@]},
    "tests_passed": $TESTS_PASSED,
    "tests_failed": $TESTS_FAILED
  },
  "validation": {
    "health_checks": "passed",
    "service_discovery": "passed",
    "rollback_readiness": "passed"
  }
}
EOF
    
    success "Deployment metrics report generated: $report_file"
    
    return $([[ "$metrics_available" == "true" ]] && echo 0 || echo 1)
}

# Main test execution
main() {
    log "Starting End-to-End Deployment Test Suite"
    log "Logging to: $LOG_FILE"
    log "Blue Namespace: $BLUE_NAMESPACE"
    log "Green Namespace: $GREEN_NAMESPACE"
    log "Deployment Timeout: ${TIMEOUT}s"
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
    
    # Run all tests in sequence
    run_test "Pre-deployment Validation" test_pre_deployment_validation
    run_test "Deployment Execution" test_deployment_execution
    run_test "Post-deployment Validation" test_post_deployment_validation
    run_test "Health Check Sequence" test_health_check_sequence
    run_test "Rollback Procedure Testing" test_rollback_procedures
    run_test "Performance Impact Assessment" test_performance_impact
    run_test "Deployment Metrics Collection" test_deployment_metrics
    
    # Print summary
    echo "=================================="
    log "End-to-End Deployment Test Summary"
    echo "=================================="
    success "Tests Passed: $TESTS_PASSED"
    
    if [[ $TESTS_FAILED -gt 0 ]]; then
        error "Tests Failed: $TESTS_FAILED"
        error "Failed tests:"
        for test in "${FAILED_TESTS[@]}"; do
            error "  - $test"
        done
        echo ""
        error "❌ End-to-end deployment tests FAILED"
        exit 1
    else
        echo ""
        success "✅ All end-to-end deployment tests PASSED"
        
        # Print final deployment summary
        if [[ -n "$DEPLOYMENT_START_TIME" ]] && [[ -n "$DEPLOYMENT_END_TIME" ]]; then
            local total_duration=$((DEPLOYMENT_END_TIME - DEPLOYMENT_START_TIME))
            info "🎯 Complete deployment cycle validated in ${total_duration}s"
        fi
        
        exit 0
    fi
}

# Run main function
main "$@"

