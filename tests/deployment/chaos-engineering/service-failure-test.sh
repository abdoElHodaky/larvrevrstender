#!/bin/bash

# Service Failure Chaos Engineering Test
# Tests system resilience under service failure conditions

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
LOG_FILE="/tmp/service-failure-chaos-test-$(date +%Y%m%d-%H%M%S).log"
TEST_NAMESPACE="chaos-test-service-failure"
TEST_TIMEOUT=300

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

# Test result tracking
TESTS_PASSED=0
TESTS_FAILED=0
FAILED_TESTS=()

# Test execution wrapper
run_test() {
    local test_name="$1"
    local test_function="$2"
    
    log "Running chaos test: $test_name"
    
    if $test_function; then
        success "Chaos test passed: $test_name"
        ((TESTS_PASSED++))
        return 0
    else
        error "Chaos test failed: $test_name"
        FAILED_TESTS+=("$test_name")
        ((TESTS_FAILED++))
        return 1
    fi
}

# Cleanup function
cleanup() {
    log "Cleaning up chaos test resources..."
    
    # Delete test namespace if it exists
    if kubectl get namespace "$TEST_NAMESPACE" >/dev/null 2>&1; then
        kubectl delete namespace "$TEST_NAMESPACE" --timeout=120s || true
    fi
    
    log "Chaos test cleanup completed"
}

# Set up cleanup trap
trap cleanup EXIT

# Setup chaos test environment
setup_chaos_environment() {
    log "Setting up chaos test environment..."
    
    # Create test namespace
    kubectl create namespace "$TEST_NAMESPACE" || return 1
    
    # Deploy test application with multiple services
    cat > /tmp/chaos-test-app.yaml << EOF
apiVersion: apps/v1
kind: Deployment
metadata:
  name: frontend-service
  namespace: $TEST_NAMESPACE
spec:
  replicas: 3
  selector:
    matchLabels:
      app: frontend
  template:
    metadata:
      labels:
        app: frontend
    spec:
      containers:
      - name: frontend
        image: nginx:alpine
        ports:
        - containerPort: 80
        command: ["/bin/sh"]
        args:
        - -c
        - |
          echo "Frontend Service - Pod: \$HOSTNAME" > /usr/share/nginx/html/index.html
          echo "{\"service\": \"frontend\", \"pod\": \"\$HOSTNAME\", \"status\": \"healthy\"}" > /usr/share/nginx/html/health
          nginx -g 'daemon off;'
        readinessProbe:
          httpGet:
            path: /health
            port: 80
          initialDelaySeconds: 5
          periodSeconds: 5
        livenessProbe:
          httpGet:
            path: /health
            port: 80
          initialDelaySeconds: 10
          periodSeconds: 10
---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: backend-service
  namespace: $TEST_NAMESPACE
spec:
  replicas: 2
  selector:
    matchLabels:
      app: backend
  template:
    metadata:
      labels:
        app: backend
    spec:
      containers:
      - name: backend
        image: nginx:alpine
        ports:
        - containerPort: 80
        command: ["/bin/sh"]
        args:
        - -c
        - |
          echo "Backend Service - Pod: \$HOSTNAME" > /usr/share/nginx/html/index.html
          echo "{\"service\": \"backend\", \"pod\": \"\$HOSTNAME\", \"status\": \"healthy\"}" > /usr/share/nginx/html/health
          nginx -g 'daemon off;'
        readinessProbe:
          httpGet:
            path: /health
            port: 80
          initialDelaySeconds: 5
          periodSeconds: 5
        livenessProbe:
          httpGet:
            path: /health
            port: 80
          initialDelaySeconds: 10
          periodSeconds: 10
---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: database-service
  namespace: $TEST_NAMESPACE
spec:
  replicas: 1
  selector:
    matchLabels:
      app: database
  template:
    metadata:
      labels:
        app: database
    spec:
      containers:
      - name: database
        image: nginx:alpine
        ports:
        - containerPort: 80
        command: ["/bin/sh"]
        args:
        - -c
        - |
          echo "Database Service - Pod: \$HOSTNAME" > /usr/share/nginx/html/index.html
          echo "{\"service\": \"database\", \"pod\": \"\$HOSTNAME\", \"status\": \"healthy\"}" > /usr/share/nginx/html/health
          nginx -g 'daemon off;'
        readinessProbe:
          httpGet:
            path: /health
            port: 80
          initialDelaySeconds: 5
          periodSeconds: 5
        livenessProbe:
          httpGet:
            path: /health
            port: 80
          initialDelaySeconds: 10
          periodSeconds: 10
---
apiVersion: v1
kind: Service
metadata:
  name: frontend-service
  namespace: $TEST_NAMESPACE
spec:
  selector:
    app: frontend
  ports:
  - port: 80
    targetPort: 80
---
apiVersion: v1
kind: Service
metadata:
  name: backend-service
  namespace: $TEST_NAMESPACE
spec:
  selector:
    app: backend
  ports:
  - port: 80
    targetPort: 80
---
apiVersion: v1
kind: Service
metadata:
  name: database-service
  namespace: $TEST_NAMESPACE
spec:
  selector:
    app: database
  ports:
  - port: 80
    targetPort: 80
EOF
    
    kubectl apply -f /tmp/chaos-test-app.yaml
    
    # Wait for all deployments to be ready
    kubectl wait --for=condition=available --timeout=180s deployment/frontend-service -n "$TEST_NAMESPACE"
    kubectl wait --for=condition=available --timeout=180s deployment/backend-service -n "$TEST_NAMESPACE"
    kubectl wait --for=condition=available --timeout=180s deployment/database-service -n "$TEST_NAMESPACE"
    
    success "Chaos test environment setup completed"
    return 0
}

# Test 1: Single Pod Failure
test_single_pod_failure() {
    log "Testing single pod failure resilience..."
    
    # Get initial pod count
    local initial_frontend_pods
    initial_frontend_pods=$(kubectl get pods -n "$TEST_NAMESPACE" -l app=frontend --no-headers | wc -l)
    
    if [[ $initial_frontend_pods -lt 3 ]]; then
        error "Insufficient frontend pods for chaos test. Expected: 3, Got: $initial_frontend_pods"
        return 1
    fi
    
    # Kill one frontend pod
    local target_pod
    target_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l app=frontend -o jsonpath='{.items[0].metadata.name}')
    
    log "Terminating pod: $target_pod"
    kubectl delete pod "$target_pod" -n "$TEST_NAMESPACE"
    
    # Wait for replacement pod to be created
    sleep 10
    
    # Check that service is still available
    local test_pod
    test_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l app=frontend -o jsonpath='{.items[0].metadata.name}')
    
    local service_ip
    service_ip=$(kubectl get service frontend-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.clusterIP}')
    
    # Test service availability
    if ! kubectl exec -n "$TEST_NAMESPACE" "$test_pod" -- curl -s --max-time 10 "http://$service_ip/health" >/dev/null; then
        error "Service unavailable after single pod failure"
        return 1
    fi
    
    # Wait for deployment to recover
    if ! kubectl wait --for=condition=available --timeout=120s deployment/frontend-service -n "$TEST_NAMESPACE"; then
        error "Deployment failed to recover from single pod failure"
        return 1
    fi
    
    # Verify pod count is restored
    local recovered_pods
    recovered_pods=$(kubectl get pods -n "$TEST_NAMESPACE" -l app=frontend --no-headers | wc -l)
    
    if [[ $recovered_pods -ne $initial_frontend_pods ]]; then
        error "Pod count not restored. Expected: $initial_frontend_pods, Got: $recovered_pods"
        return 1
    fi
    
    success "Single pod failure test passed - service remained available and recovered"
    return 0
}

# Test 2: Multiple Pod Failure
test_multiple_pod_failure() {
    log "Testing multiple pod failure resilience..."
    
    # Kill 2 out of 3 frontend pods
    local pods_to_kill
    readarray -t pods_to_kill < <(kubectl get pods -n "$TEST_NAMESPACE" -l app=frontend -o jsonpath='{.items[0:2].metadata.name}' | tr ' ' '\n')
    
    log "Terminating multiple pods: ${pods_to_kill[*]}"
    
    for pod in "${pods_to_kill[@]}"; do
        kubectl delete pod "$pod" -n "$TEST_NAMESPACE" &
    done
    
    # Wait for deletions to complete
    wait
    sleep 5
    
    # Check that at least one pod is still running
    local running_pods
    running_pods=$(kubectl get pods -n "$TEST_NAMESPACE" -l app=frontend --field-selector=status.phase=Running --no-headers | wc -l)
    
    if [[ $running_pods -lt 1 ]]; then
        error "No frontend pods running after multiple pod failure"
        return 1
    fi
    
    # Test service availability with reduced capacity
    local remaining_pod
    remaining_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l app=frontend --field-selector=status.phase=Running -o jsonpath='{.items[0].metadata.name}')
    
    local service_ip
    service_ip=$(kubectl get service frontend-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.clusterIP}')
    
    # Test service availability
    if ! kubectl exec -n "$TEST_NAMESPACE" "$remaining_pod" -- curl -s --max-time 10 "http://$service_ip/health" >/dev/null; then
        error "Service unavailable after multiple pod failure"
        return 1
    fi
    
    # Wait for full recovery
    if ! kubectl wait --for=condition=available --timeout=180s deployment/frontend-service -n "$TEST_NAMESPACE"; then
        error "Deployment failed to recover from multiple pod failure"
        return 1
    fi
    
    # Verify full pod count is restored
    local recovered_pods
    recovered_pods=$(kubectl get pods -n "$TEST_NAMESPACE" -l app=frontend --no-headers | wc -l)
    
    if [[ $recovered_pods -ne 3 ]]; then
        error "Full pod count not restored. Expected: 3, Got: $recovered_pods"
        return 1
    fi
    
    success "Multiple pod failure test passed - service remained available and fully recovered"
    return 0
}

# Test 3: Service Dependency Failure
test_service_dependency_failure() {
    log "Testing service dependency failure resilience..."
    
    # Scale down backend service to simulate dependency failure
    kubectl scale deployment backend-service --replicas=0 -n "$TEST_NAMESPACE"
    
    # Wait for backend pods to terminate
    sleep 15
    
    # Verify backend service is down
    local backend_pods
    backend_pods=$(kubectl get pods -n "$TEST_NAMESPACE" -l app=backend --no-headers | wc -l)
    
    if [[ $backend_pods -gt 0 ]]; then
        error "Backend service pods still running after scale down"
        return 1
    fi
    
    log "Backend service dependency is down"
    
    # Test that frontend service is still accessible (graceful degradation)
    local frontend_pod
    frontend_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l app=frontend -o jsonpath='{.items[0].metadata.name}')
    
    local frontend_service_ip
    frontend_service_ip=$(kubectl get service frontend-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.clusterIP}')
    
    # Frontend should still be accessible
    if ! kubectl exec -n "$TEST_NAMESPACE" "$frontend_pod" -- curl -s --max-time 10 "http://$frontend_service_ip/health" >/dev/null; then
        error "Frontend service unavailable when backend dependency is down"
        return 1
    fi
    
    log "Frontend service remains available despite backend dependency failure"
    
    # Test that backend service is indeed unreachable
    local backend_service_ip
    backend_service_ip=$(kubectl get service backend-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.clusterIP}')
    
    if kubectl exec -n "$TEST_NAMESPACE" "$frontend_pod" -- curl -s --max-time 5 "http://$backend_service_ip/health" >/dev/null 2>&1; then
        error "Backend service should be unreachable but is responding"
        return 1
    fi
    
    log "Backend service is correctly unreachable"
    
    # Restore backend service
    kubectl scale deployment backend-service --replicas=2 -n "$TEST_NAMESPACE"
    
    # Wait for backend recovery
    if ! kubectl wait --for=condition=available --timeout=120s deployment/backend-service -n "$TEST_NAMESPACE"; then
        error "Backend service failed to recover"
        return 1
    fi
    
    # Verify backend service is accessible again
    if ! kubectl exec -n "$TEST_NAMESPACE" "$frontend_pod" -- curl -s --max-time 10 "http://$backend_service_ip/health" >/dev/null; then
        error "Backend service not accessible after recovery"
        return 1
    fi
    
    success "Service dependency failure test passed - graceful degradation and recovery"
    return 0
}

# Test 4: Critical Service Failure
test_critical_service_failure() {
    log "Testing critical service failure resilience..."
    
    # Scale down database service (critical dependency)
    kubectl scale deployment database-service --replicas=0 -n "$TEST_NAMESPACE"
    
    # Wait for database pod to terminate
    sleep 15
    
    # Verify database service is down
    local database_pods
    database_pods=$(kubectl get pods -n "$TEST_NAMESPACE" -l app=database --no-headers | wc -l)
    
    if [[ $database_pods -gt 0 ]]; then
        error "Database service pod still running after scale down"
        return 1
    fi
    
    log "Critical database service is down"
    
    # Test system behavior when critical dependency is unavailable
    local frontend_pod
    frontend_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l app=frontend -o jsonpath='{.items[0].metadata.name}')
    
    local database_service_ip
    database_service_ip=$(kubectl get service database-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.clusterIP}')
    
    # Database should be unreachable
    if kubectl exec -n "$TEST_NAMESPACE" "$frontend_pod" -- curl -s --max-time 5 "http://$database_service_ip/health" >/dev/null 2>&1; then
        error "Database service should be unreachable but is responding"
        return 1
    fi
    
    log "Database service is correctly unreachable"
    
    # Frontend should still be running (even if degraded)
    local frontend_service_ip
    frontend_service_ip=$(kubectl get service frontend-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.clusterIP}')
    
    if ! kubectl exec -n "$TEST_NAMESPACE" "$frontend_pod" -- curl -s --max-time 10 "http://$frontend_service_ip/health" >/dev/null; then
        warning "Frontend service unavailable when database is down (may be expected behavior)"
    else
        log "Frontend service remains available despite database failure"
    fi
    
    # Restore database service
    kubectl scale deployment database-service --replicas=1 -n "$TEST_NAMESPACE"
    
    # Wait for database recovery
    if ! kubectl wait --for=condition=available --timeout=120s deployment/database-service -n "$TEST_NAMESPACE"; then
        error "Database service failed to recover"
        return 1
    fi
    
    # Verify database service is accessible again
    if ! kubectl exec -n "$TEST_NAMESPACE" "$frontend_pod" -- curl -s --max-time 10 "http://$database_service_ip/health" >/dev/null; then
        error "Database service not accessible after recovery"
        return 1
    fi
    
    success "Critical service failure test passed - system handled critical dependency failure"
    return 0
}

# Test 5: Cascading Failure Simulation
test_cascading_failure_simulation() {
    log "Testing cascading failure resilience..."
    
    # Simulate cascading failure: database -> backend -> frontend
    log "Initiating cascading failure simulation"
    
    # Step 1: Kill database
    kubectl scale deployment database-service --replicas=0 -n "$TEST_NAMESPACE"
    sleep 10
    
    # Step 2: Kill backend (simulating dependency on database)
    kubectl scale deployment backend-service --replicas=0 -n "$TEST_NAMESPACE"
    sleep 10
    
    # Step 3: Stress frontend (simulating increased load due to failures)
    kubectl scale deployment frontend-service --replicas=1 -n "$TEST_NAMESPACE"
    sleep 10
    
    log "Cascading failure simulation complete - all dependencies down, frontend stressed"
    
    # Verify system state
    local database_pods backend_pods frontend_pods
    database_pods=$(kubectl get pods -n "$TEST_NAMESPACE" -l app=database --no-headers | wc -l)
    backend_pods=$(kubectl get pods -n "$TEST_NAMESPACE" -l app=backend --no-headers | wc -l)
    frontend_pods=$(kubectl get pods -n "$TEST_NAMESPACE" -l app=frontend --no-headers | wc -l)
    
    if [[ $database_pods -ne 0 || $backend_pods -ne 0 || $frontend_pods -ne 1 ]]; then
        error "Cascading failure simulation not in expected state"
        return 1
    fi
    
    # Test if frontend can still serve basic requests
    local frontend_pod
    frontend_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l app=frontend -o jsonpath='{.items[0].metadata.name}')
    
    local frontend_service_ip
    frontend_service_ip=$(kubectl get service frontend-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.clusterIP}')
    
    # Frontend should still respond (even if degraded)
    if kubectl exec -n "$TEST_NAMESPACE" "$frontend_pod" -- curl -s --max-time 10 "http://$frontend_service_ip/health" >/dev/null; then
        log "Frontend service remains responsive during cascading failure"
    else
        warning "Frontend service unavailable during cascading failure (may be expected)"
    fi
    
    # Begin recovery process
    log "Starting recovery from cascading failure"
    
    # Step 1: Restore database
    kubectl scale deployment database-service --replicas=1 -n "$TEST_NAMESPACE"
    kubectl wait --for=condition=available --timeout=120s deployment/database-service -n "$TEST_NAMESPACE"
    
    # Step 2: Restore backend
    kubectl scale deployment backend-service --replicas=2 -n "$TEST_NAMESPACE"
    kubectl wait --for=condition=available --timeout=120s deployment/backend-service -n "$TEST_NAMESPACE"
    
    # Step 3: Restore frontend to full capacity
    kubectl scale deployment frontend-service --replicas=3 -n "$TEST_NAMESPACE"
    kubectl wait --for=condition=available --timeout=120s deployment/frontend-service -n "$TEST_NAMESPACE"
    
    # Verify full recovery
    database_pods=$(kubectl get pods -n "$TEST_NAMESPACE" -l app=database --no-headers | wc -l)
    backend_pods=$(kubectl get pods -n "$TEST_NAMESPACE" -l app=backend --no-headers | wc -l)
    frontend_pods=$(kubectl get pods -n "$TEST_NAMESPACE" -l app=frontend --no-headers | wc -l)
    
    if [[ $database_pods -ne 1 || $backend_pods -ne 2 || $frontend_pods -ne 3 ]]; then
        error "System not fully recovered from cascading failure"
        return 1
    fi
    
    # Test full system functionality
    if ! kubectl exec -n "$TEST_NAMESPACE" "$frontend_pod" -- curl -s --max-time 10 "http://$frontend_service_ip/health" >/dev/null; then
        error "Frontend service not accessible after cascading failure recovery"
        return 1
    fi
    
    success "Cascading failure simulation test passed - system recovered from complete failure"
    return 0
}

# Main test execution
main() {
    log "Starting Service Failure Chaos Engineering Test Suite"
    log "Log file: $LOG_FILE"
    
    # Setup chaos test environment
    if ! setup_chaos_environment; then
        error "Chaos test environment setup failed"
        exit 1
    fi
    
    # Run all chaos tests
    run_test "Single Pod Failure" test_single_pod_failure
    run_test "Multiple Pod Failure" test_multiple_pod_failure
    run_test "Service Dependency Failure" test_service_dependency_failure
    run_test "Critical Service Failure" test_critical_service_failure
    run_test "Cascading Failure Simulation" test_cascading_failure_simulation
    
    # Print test summary
    log "Chaos Test Summary:"
    log "=================="
    success "Chaos Tests Passed: $TESTS_PASSED"
    
    if [[ $TESTS_FAILED -gt 0 ]]; then
        error "Chaos Tests Failed: $TESTS_FAILED"
        error "Failed Chaos Tests:"
        for test in "${FAILED_TESTS[@]}"; do
            error "  - $test"
        done
        exit 1
    else
        success "All chaos engineering tests passed!"
        exit 0
    fi
}

# Run main function
main "$@"
