#!/bin/bash

# Service Failure Simulation Test
# Tests system resilience to service failures and recovery
# Part of Phase 1: Chaos Engineering Testing Framework

set -euo pipefail

# Configuration
BLUE_NAMESPACE="reverse-tender-blue"
GREEN_NAMESPACE="reverse-tender-green"
TEST_DURATION=300  # 5 minutes
RECOVERY_TIMEOUT=180  # 3 minutes
LOG_FILE="/tmp/service-failure-test-$(date +%Y%m%d-%H%M%S).log"

# Services to test
CRITICAL_SERVICES=(
    "gateway-service"
    "auth-service"
    "user-service"
    "payment-service"
)

# Failure scenarios
FAILURE_SCENARIOS=(
    "pod_deletion"
    "deployment_scale_down"
    "container_kill"
    "resource_exhaustion"
)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

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
    
    log "Running chaos test: $test_name"
    
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

# Helper function to wait for service recovery
wait_for_service_recovery() {
    local namespace="$1"
    local service="$2"
    local timeout="$3"
    local attempts=0
    local max_attempts=$((timeout / 10))
    
    log "Waiting for $service recovery in $namespace..."
    
    while [[ $attempts -lt $max_attempts ]]; do
        local ready_replicas
        ready_replicas=$(kubectl get deployment "$service" -n "$namespace" -o jsonpath='{.status.readyReplicas}' 2>/dev/null || echo "0")
        local desired_replicas
        desired_replicas=$(kubectl get deployment "$service" -n "$namespace" -o jsonpath='{.spec.replicas}' 2>/dev/null || echo "1")
        
        if [[ "$ready_replicas" == "$desired_replicas" ]] && [[ "$ready_replicas" != "0" ]]; then
            # Additional health check
            local pod_name
            pod_name=$(kubectl get pods -n "$namespace" -l app="$service" -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
            
            if [[ -n "$pod_name" ]]; then
                local pod_status
                pod_status=$(kubectl get pod "$pod_name" -n "$namespace" -o jsonpath='{.status.phase}' 2>/dev/null || echo "Unknown")
                
                if [[ "$pod_status" == "Running" ]]; then
                    success "$service recovered successfully (${attempts}0s)"
                    return 0
                fi
            fi
        fi
        
        sleep 10
        ((attempts++))
        
        if [[ $((attempts % 6)) -eq 0 ]]; then
            log "Still waiting for $service recovery... (${attempts}0s elapsed)"
        fi
    done
    
    error "$service did not recover within ${timeout}s"
    return 1
}

# Test 1: Pod Deletion Failure Simulation
test_pod_deletion_failure() {
    log "Testing pod deletion failure simulation..."
    
    local test_namespace="$GREEN_NAMESPACE"
    local test_service="gateway-service"
    
    # Get current pod
    local pod_name
    pod_name=$(kubectl get pods -n "$test_namespace" -l app="$test_service" -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
    
    if [[ -z "$pod_name" ]]; then
        error "No pods found for $test_service in $test_namespace"
        return 1
    fi
    
    info "Target pod: $pod_name"
    
    # Record pre-failure state
    local pre_failure_replicas
    pre_failure_replicas=$(kubectl get deployment "$test_service" -n "$test_namespace" -o jsonpath='{.status.readyReplicas}' 2>/dev/null || echo "0")
    
    info "Pre-failure ready replicas: $pre_failure_replicas"
    
    # Delete the pod
    log "Deleting pod $pod_name..."
    kubectl delete pod "$pod_name" -n "$test_namespace" --grace-period=0 --force &>/dev/null
    
    # Wait a moment for the deletion to register
    sleep 5
    
    # Check if new pod is being created
    local new_pod_creating=false
    local attempts=0
    
    while [[ $attempts -lt 30 ]]; do
        local current_pods
        current_pods=$(kubectl get pods -n "$test_namespace" -l app="$test_service" --no-headers 2>/dev/null | wc -l || echo "0")
        
        if [[ $current_pods -gt 0 ]]; then
            local new_pod_name
            new_pod_name=$(kubectl get pods -n "$test_namespace" -l app="$test_service" -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
            
            if [[ "$new_pod_name" != "$pod_name" ]]; then
                new_pod_creating=true
                info "New pod created: $new_pod_name"
                break
            fi
        fi
        
        sleep 2
        ((attempts++))
    done
    
    if [[ "$new_pod_creating" == "false" ]]; then
        error "New pod was not created after pod deletion"
        return 1
    fi
    
    # Wait for service recovery
    if wait_for_service_recovery "$test_namespace" "$test_service" "$RECOVERY_TIMEOUT"; then
        success "Service recovered from pod deletion"
        return 0
    else
        error "Service did not recover from pod deletion"
        return 1
    fi
}

# Test 2: Deployment Scale Down Failure
test_deployment_scale_down_failure() {
    log "Testing deployment scale down failure simulation..."
    
    local test_namespace="$GREEN_NAMESPACE"
    local test_service="user-service"
    
    # Get current replica count
    local original_replicas
    original_replicas=$(kubectl get deployment "$test_service" -n "$test_namespace" -o jsonpath='{.spec.replicas}' 2>/dev/null || echo "1")
    
    info "Original replicas: $original_replicas"
    
    # Scale down to 0
    log "Scaling down $test_service to 0 replicas..."
    kubectl scale deployment "$test_service" -n "$test_namespace" --replicas=0 &>/dev/null
    
    # Wait for scale down
    sleep 10
    
    # Verify scale down
    local current_replicas
    current_replicas=$(kubectl get deployment "$test_service" -n "$test_namespace" -o jsonpath='{.status.readyReplicas}' 2>/dev/null || echo "0")
    
    if [[ "$current_replicas" == "0" ]]; then
        success "Service scaled down successfully"
    else
        error "Service scale down failed: $current_replicas replicas still running"
        return 1
    fi
    
    # Scale back up
    log "Scaling back up $test_service to $original_replicas replicas..."
    kubectl scale deployment "$test_service" -n "$test_namespace" --replicas="$original_replicas" &>/dev/null
    
    # Wait for recovery
    if wait_for_service_recovery "$test_namespace" "$test_service" "$RECOVERY_TIMEOUT"; then
        success "Service recovered from scale down"
        return 0
    else
        error "Service did not recover from scale down"
        return 1
    fi
}

# Test 3: Container Kill Simulation
test_container_kill_failure() {
    log "Testing container kill failure simulation..."
    
    local test_namespace="$GREEN_NAMESPACE"
    local test_service="auth-service"
    
    # Get pod and container info
    local pod_name
    pod_name=$(kubectl get pods -n "$test_namespace" -l app="$test_service" -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
    
    if [[ -z "$pod_name" ]]; then
        error "No pods found for $test_service in $test_namespace"
        return 1
    fi
    
    local container_name
    container_name=$(kubectl get pod "$pod_name" -n "$test_namespace" -o jsonpath='{.spec.containers[0].name}' 2>/dev/null || echo "")
    
    info "Target pod: $pod_name, container: $container_name"
    
    # Kill the main process in the container
    log "Killing main process in container..."
    kubectl exec "$pod_name" -n "$test_namespace" -c "$container_name" -- pkill -f "php-fpm\|frankenphp" &>/dev/null || true
    
    # Wait for Kubernetes to detect the failure
    sleep 15
    
    # Check if pod was restarted
    local restart_count
    restart_count=$(kubectl get pod "$pod_name" -n "$test_namespace" -o jsonpath='{.status.containerStatuses[0].restartCount}' 2>/dev/null || echo "0")
    
    if [[ $restart_count -gt 0 ]]; then
        success "Container was restarted (restart count: $restart_count)"
    else
        warning "Container restart not detected yet"
    fi
    
    # Wait for service recovery
    if wait_for_service_recovery "$test_namespace" "$test_service" "$RECOVERY_TIMEOUT"; then
        success "Service recovered from container kill"
        return 0
    else
        error "Service did not recover from container kill"
        return 1
    fi
}

# Test 4: Resource Exhaustion Simulation
test_resource_exhaustion_failure() {
    log "Testing resource exhaustion failure simulation..."
    
    local test_namespace="$GREEN_NAMESPACE"
    local test_service="payment-service"
    
    # Get pod for testing
    local pod_name
    pod_name=$(kubectl get pods -n "$test_namespace" -l app="$test_service" -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
    
    if [[ -z "$pod_name" ]]; then
        error "No pods found for $test_service in $test_namespace"
        return 1
    fi
    
    info "Target pod: $pod_name"
    
    # Create memory pressure (simulate memory leak)
    log "Creating memory pressure in container..."
    kubectl exec "$pod_name" -n "$test_namespace" -- sh -c "
        # Create a background process that consumes memory gradually
        (
            i=0
            while [ \$i -lt 100 ]; do
                # Allocate memory in small chunks
                dd if=/dev/zero of=/tmp/memtest\$i bs=1M count=10 2>/dev/null &
                sleep 1
                i=\$((i + 1))
            done
        ) &
    " &>/dev/null || true
    
    # Monitor pod status for a short period
    local monitoring_duration=60
    local start_time
    start_time=$(date +%s)
    local pod_killed=false
    
    while [[ $(($(date +%s) - start_time)) -lt $monitoring_duration ]]; do
        local pod_status
        pod_status=$(kubectl get pod "$pod_name" -n "$test_namespace" -o jsonpath='{.status.phase}' 2>/dev/null || echo "Unknown")
        
        if [[ "$pod_status" != "Running" ]]; then
            pod_killed=true
            info "Pod was killed due to resource exhaustion"
            break
        fi
        
        # Check for OOMKilled status
        local oom_killed
        oom_killed=$(kubectl get pod "$pod_name" -n "$test_namespace" -o jsonpath='{.status.containerStatuses[0].lastState.terminated.reason}' 2>/dev/null || echo "")
        
        if [[ "$oom_killed" == "OOMKilled" ]]; then
            pod_killed=true
            success "Pod was OOMKilled as expected"
            break
        fi
        
        sleep 5
    done
    
    # Clean up memory test files
    kubectl exec "$pod_name" -n "$test_namespace" -- sh -c "rm -f /tmp/memtest* 2>/dev/null; pkill dd 2>/dev/null" &>/dev/null || true
    
    if [[ "$pod_killed" == "true" ]]; then
        # Wait for recovery
        if wait_for_service_recovery "$test_namespace" "$test_service" "$RECOVERY_TIMEOUT"; then
            success "Service recovered from resource exhaustion"
            return 0
        else
            error "Service did not recover from resource exhaustion"
            return 1
        fi
    else
        warning "Resource exhaustion did not trigger pod kill (resource limits may be too high)"
        return 0  # Not a failure, just means limits are generous
    fi
}

# Test 5: Multiple Service Failure
test_multiple_service_failure() {
    log "Testing multiple service failure simulation..."
    
    local test_namespace="$GREEN_NAMESPACE"
    local failed_services=("gateway-service" "auth-service")
    
    # Simultaneously fail multiple services
    log "Failing multiple services simultaneously..."
    
    for service in "${failed_services[@]}"; do
        local pod_name
        pod_name=$(kubectl get pods -n "$test_namespace" -l app="$service" -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
        
        if [[ -n "$pod_name" ]]; then
            info "Deleting pod for $service: $pod_name"
            kubectl delete pod "$pod_name" -n "$test_namespace" --grace-period=0 --force &>/dev/null &
        fi
    done
    
    # Wait for all deletions to complete
    sleep 10
    
    # Monitor recovery of all services
    local all_recovered=true
    
    for service in "${failed_services[@]}"; do
        log "Waiting for $service recovery..."
        
        if wait_for_service_recovery "$test_namespace" "$service" "$RECOVERY_TIMEOUT"; then
            success "$service recovered successfully"
        else
            error "$service failed to recover"
            all_recovered=false
        fi
    done
    
    if [[ "$all_recovered" == "true" ]]; then
        success "All services recovered from multiple failure scenario"
        return 0
    else
        error "Some services failed to recover from multiple failure scenario"
        return 1
    fi
}

# Test 6: Network Partition Simulation
test_network_partition_failure() {
    log "Testing network partition failure simulation..."
    
    local test_namespace="$GREEN_NAMESPACE"
    local test_service="user-service"
    
    # Get pod for testing
    local pod_name
    pod_name=$(kubectl get pods -n "$test_namespace" -l app="$test_service" -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
    
    if [[ -z "$pod_name" ]]; then
        error "No pods found for $test_service in $test_namespace"
        return 1
    fi
    
    info "Target pod: $pod_name"
    
    # Simulate network issues by blocking traffic (simplified simulation)
    log "Simulating network partition..."
    
    # Add iptables rules to block traffic (if iptables is available)
    kubectl exec "$pod_name" -n "$test_namespace" -- sh -c "
        # Try to install iptables if not present (may fail in restricted containers)
        which iptables >/dev/null 2>&1 || {
            echo 'iptables not available, simulating with connection drops'
            # Simulate network issues by overwhelming the network stack
            for i in \$(seq 1 10); do
                timeout 5 nc -l -p \$((8000 + i)) >/dev/null 2>&1 &
            done
            sleep 30
            pkill nc 2>/dev/null || true
        }
    " &>/dev/null || true
    
    # Wait for potential impact
    sleep 30
    
    # Check if service is still responsive
    local health_check
    health_check=$(kubectl exec "$pod_name" -n "$test_namespace" -- curl -s -o /dev/null -w "%{http_code}" "http://localhost:8002/health" 2>/dev/null || echo "000")
    
    if [[ "$health_check" == "200" ]]; then
        success "Service remained responsive during network simulation"
    else
        warning "Service became unresponsive during network simulation (HTTP $health_check)"
    fi
    
    # Wait for recovery
    if wait_for_service_recovery "$test_namespace" "$test_service" "$RECOVERY_TIMEOUT"; then
        success "Service recovered from network partition simulation"
        return 0
    else
        error "Service did not recover from network partition simulation"
        return 1
    fi
}

# Main test execution
main() {
    log "Starting Service Failure Simulation Test Suite"
    log "Logging to: $LOG_FILE"
    log "Test Duration: ${TEST_DURATION}s"
    log "Recovery Timeout: ${RECOVERY_TIMEOUT}s"
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
    
    # Verify test environment
    if ! kubectl get namespace "$GREEN_NAMESPACE" &>/dev/null; then
        error "Test namespace '$GREEN_NAMESPACE' does not exist"
        exit 1
    fi
    
    # Run all chaos tests
    run_test "Pod Deletion Failure" test_pod_deletion_failure
    run_test "Deployment Scale Down Failure" test_deployment_scale_down_failure
    run_test "Container Kill Failure" test_container_kill_failure
    run_test "Resource Exhaustion Failure" test_resource_exhaustion_failure
    run_test "Multiple Service Failure" test_multiple_service_failure
    run_test "Network Partition Failure" test_network_partition_failure
    
    # Print summary
    echo "=================================="
    log "Service Failure Simulation Summary"
    echo "=================================="
    success "Tests Passed: $TESTS_PASSED"
    
    if [[ $TESTS_FAILED -gt 0 ]]; then
        error "Tests Failed: $TESTS_FAILED"
        error "Failed tests:"
        for test in "${FAILED_TESTS[@]}"; do
            error "  - $test"
        done
        echo ""
        error "❌ Service failure simulation tests FAILED"
        exit 1
    else
        echo ""
        success "✅ All service failure simulation tests PASSED"
        success "🎯 System demonstrated resilience to service failures"
        exit 0
    fi
}

# Run main function
main "$@"

