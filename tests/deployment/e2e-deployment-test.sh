#!/bin/bash

# End-to-End Deployment Test Suite
# Complete deployment cycle tests with pre/post validation and rollback scenarios

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
LOG_FILE="/tmp/e2e-deployment-test-$(date +%Y%m%d-%H%M%S).log"
TEST_NAMESPACE="reverse-tender-e2e"
TEST_TIMEOUT=600

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
    
    log "Running test: $test_name"
    
    if $test_function; then
        success "Test passed: $test_name"
        ((TESTS_PASSED++))
        return 0
    else
        error "Test failed: $test_name"
        FAILED_TESTS+=("$test_name")
        ((TESTS_FAILED++))
        return 1
    fi
}

# Cleanup function
cleanup() {
    log "Cleaning up test resources..."
    
    # Delete test namespace if it exists
    if kubectl get namespace "$TEST_NAMESPACE" >/dev/null 2>&1; then
        kubectl delete namespace "$TEST_NAMESPACE" --timeout=120s || true
    fi
    
    log "Cleanup completed"
}

# Set up cleanup trap
trap cleanup EXIT

# Prerequisites check
check_prerequisites() {
    log "Checking prerequisites..."
    
    # Check required tools
    local required_tools=("kubectl" "flux" "git" "jq" "curl")
    for tool in "${required_tools[@]}"; do
        if ! command -v "$tool" &> /dev/null; then
            error "$tool is not installed or not in PATH"
            return 1
        fi
    done
    
    # Check Kubernetes cluster connectivity
    if ! kubectl cluster-info >/dev/null 2>&1; then
        error "Cannot connect to Kubernetes cluster"
        return 1
    fi
    
    success "Prerequisites check passed"
    return 0
}

# Test 1: Pre-deployment Validation
test_pre_deployment_validation() {
    log "Testing pre-deployment validation..."
    
    # Create test namespace
    kubectl create namespace "$TEST_NAMESPACE" || return 1
    
    # Check cluster resources
    local node_count
    node_count=$(kubectl get nodes --no-headers | wc -l)
    
    if [[ $node_count -lt 1 ]]; then
        error "Insufficient nodes in cluster. Found: $node_count"
        return 1
    fi
    
    log "Cluster has $node_count node(s)"
    
    # Check available resources
    local total_cpu total_memory
    total_cpu=$(kubectl describe nodes | grep -A 5 "Allocatable:" | grep "cpu:" | awk '{sum += $2} END {print sum}')
    total_memory=$(kubectl describe nodes | grep -A 5 "Allocatable:" | grep "memory:" | awk '{sum += $2} END {print sum}')
    
    log "Available resources - CPU: ${total_cpu:-unknown}, Memory: ${total_memory:-unknown}"
    
    # Check if FluxCD is installed
    if ! kubectl get namespace flux-system >/dev/null 2>&1; then
        error "FluxCD namespace not found. FluxCD must be installed for E2E tests"
        return 1
    fi
    
    # Check FluxCD controllers
    local controllers=("source-controller" "kustomize-controller" "helm-controller")
    for controller in "${controllers[@]}"; do
        if ! kubectl get deployment "$controller" -n flux-system >/dev/null 2>&1; then
            error "FluxCD controller not found: $controller"
            return 1
        fi
        
        if ! kubectl wait --for=condition=available --timeout=60s deployment/"$controller" -n flux-system; then
            error "FluxCD controller not ready: $controller"
            return 1
        fi
    done
    
    success "Pre-deployment validation test passed"
    return 0
}

# Test 2: Blue Environment Deployment
test_blue_environment_deployment() {
    log "Testing blue environment deployment..."
    
    # Create blue environment configuration
    cat > /tmp/blue-environment.yaml << EOF
apiVersion: v1
kind: ConfigMap
metadata:
  name: deployment-config
  namespace: $TEST_NAMESPACE
data:
  ENVIRONMENT_COLOR: "blue"
  DEPLOYMENT_VERSION: "v1.0.0"
  DEPLOYMENT_TIMESTAMP: "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: app-blue
  namespace: $TEST_NAMESPACE
  labels:
    app: test-app
    version: blue
spec:
  replicas: 3
  selector:
    matchLabels:
      app: test-app
      version: blue
  template:
    metadata:
      labels:
        app: test-app
        version: blue
    spec:
      containers:
      - name: app
        image: nginx:alpine
        ports:
        - containerPort: 80
        env:
        - name: ENVIRONMENT_COLOR
          value: "blue"
        - name: DEPLOYMENT_VERSION
          value: "v1.0.0"
        command: ["/bin/sh"]
        args:
        - -c
        - |
          echo "Blue Environment v1.0.0 - Pod: \$HOSTNAME" > /usr/share/nginx/html/index.html
          echo "{\"environment\": \"blue\", \"version\": \"v1.0.0\", \"pod\": \"\$HOSTNAME\", \"status\": \"healthy\", \"timestamp\": \"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"}" > /usr/share/nginx/html/health
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
        resources:
          requests:
            cpu: 100m
            memory: 128Mi
          limits:
            cpu: 200m
            memory: 256Mi
---
apiVersion: v1
kind: Service
metadata:
  name: app-blue-service
  namespace: $TEST_NAMESPACE
spec:
  selector:
    app: test-app
    version: blue
  ports:
  - port: 80
    targetPort: 80
  type: ClusterIP
EOF
    
    kubectl apply -f /tmp/blue-environment.yaml
    
    # Wait for blue deployment to be ready
    if ! kubectl wait --for=condition=available --timeout=180s deployment/app-blue -n "$TEST_NAMESPACE"; then
        error "Blue deployment failed to become ready"
        kubectl describe deployment app-blue -n "$TEST_NAMESPACE"
        return 1
    fi
    
    # Verify all pods are running
    local blue_pods_ready
    blue_pods_ready=$(kubectl get deployment app-blue -n "$TEST_NAMESPACE" -o jsonpath='{.status.readyReplicas}')
    
    if [[ "$blue_pods_ready" != "3" ]]; then
        error "Blue deployment does not have all pods ready. Expected: 3, Got: $blue_pods_ready"
        return 1
    fi
    
    # Test blue environment health
    local blue_pod
    blue_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l version=blue -o jsonpath='{.items[0].metadata.name}')
    
    local health_response
    health_response=$(kubectl exec -n "$TEST_NAMESPACE" "$blue_pod" -- curl -s http://localhost/health)
    
    if ! echo "$health_response" | jq -e '.status == "healthy" and .environment == "blue"' >/dev/null; then
        error "Blue environment health check failed. Response: $health_response"
        return 1
    fi
    
    success "Blue environment deployment test passed"
    return 0
}

# Test 3: Green Environment Deployment
test_green_environment_deployment() {
    log "Testing green environment deployment..."
    
    # Create green environment with updated version
    cat > /tmp/green-environment.yaml << EOF
apiVersion: apps/v1
kind: Deployment
metadata:
  name: app-green
  namespace: $TEST_NAMESPACE
  labels:
    app: test-app
    version: green
spec:
  replicas: 3
  selector:
    matchLabels:
      app: test-app
      version: green
  template:
    metadata:
      labels:
        app: test-app
        version: green
    spec:
      containers:
      - name: app
        image: nginx:alpine
        ports:
        - containerPort: 80
        env:
        - name: ENVIRONMENT_COLOR
          value: "green"
        - name: DEPLOYMENT_VERSION
          value: "v1.1.0"
        command: ["/bin/sh"]
        args:
        - -c
        - |
          echo "Green Environment v1.1.0 - Pod: \$HOSTNAME" > /usr/share/nginx/html/index.html
          echo "{\"environment\": \"green\", \"version\": \"v1.1.0\", \"pod\": \"\$HOSTNAME\", \"status\": \"healthy\", \"timestamp\": \"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"}" > /usr/share/nginx/html/health
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
        resources:
          requests:
            cpu: 100m
            memory: 128Mi
          limits:
            cpu: 200m
            memory: 256Mi
---
apiVersion: v1
kind: Service
metadata:
  name: app-green-service
  namespace: $TEST_NAMESPACE
spec:
  selector:
    app: test-app
    version: green
  ports:
  - port: 80
    targetPort: 80
  type: ClusterIP
EOF
    
    kubectl apply -f /tmp/green-environment.yaml
    
    # Wait for green deployment to be ready
    if ! kubectl wait --for=condition=available --timeout=180s deployment/app-green -n "$TEST_NAMESPACE"; then
        error "Green deployment failed to become ready"
        kubectl describe deployment app-green -n "$TEST_NAMESPACE"
        return 1
    fi
    
    # Verify all pods are running
    local green_pods_ready
    green_pods_ready=$(kubectl get deployment app-green -n "$TEST_NAMESPACE" -o jsonpath='{.status.readyReplicas}')
    
    if [[ "$green_pods_ready" != "3" ]]; then
        error "Green deployment does not have all pods ready. Expected: 3, Got: $green_pods_ready"
        return 1
    fi
    
    # Test green environment health
    local green_pod
    green_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l version=green -o jsonpath='{.items[0].metadata.name}')
    
    local health_response
    health_response=$(kubectl exec -n "$TEST_NAMESPACE" "$green_pod" -- curl -s http://localhost/health)
    
    if ! echo "$health_response" | jq -e '.status == "healthy" and .environment == "green" and .version == "v1.1.0"' >/dev/null; then
        error "Green environment health check failed. Response: $health_response"
        return 1
    fi
    
    success "Green environment deployment test passed"
    return 0
}

# Test 4: Traffic Switching Validation
test_traffic_switching_validation() {
    log "Testing traffic switching validation..."
    
    # Create active service pointing to blue initially
    cat > /tmp/active-service.yaml << EOF
apiVersion: v1
kind: Service
metadata:
  name: app-active-service
  namespace: $TEST_NAMESPACE
spec:
  selector:
    app: test-app
    version: blue
  ports:
  - port: 80
    targetPort: 80
  type: ClusterIP
EOF
    
    kubectl apply -f /tmp/active-service.yaml
    
    # Test initial traffic to blue
    local test_pod
    test_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l version=blue -o jsonpath='{.items[0].metadata.name}')
    
    local active_service_ip
    active_service_ip=$(kubectl get service app-active-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.clusterIP}')
    
    local initial_response
    initial_response=$(kubectl exec -n "$TEST_NAMESPACE" "$test_pod" -- curl -s "http://$active_service_ip/health")
    
    local initial_env
    initial_env=$(echo "$initial_response" | jq -r '.environment')
    
    if [[ "$initial_env" != "blue" ]]; then
        error "Initial traffic not routing to blue environment. Got: $initial_env"
        return 1
    fi
    
    log "Initial traffic correctly routed to blue environment"
    
    # Switch traffic to green
    kubectl patch service app-active-service -n "$TEST_NAMESPACE" --patch '{"spec":{"selector":{"version":"green"}}}'
    
    # Wait for service update to propagate
    sleep 15
    
    # Test traffic switch
    local switched_response
    switched_response=$(kubectl exec -n "$TEST_NAMESPACE" "$test_pod" -- curl -s "http://$active_service_ip/health")
    
    local switched_env
    switched_env=$(echo "$switched_response" | jq -r '.environment')
    
    if [[ "$switched_env" != "green" ]]; then
        error "Traffic switch failed. Expected: green, Got: $switched_env"
        return 1
    fi
    
    log "Traffic successfully switched to green environment"
    
    # Verify version upgrade
    local switched_version
    switched_version=$(echo "$switched_response" | jq -r '.version')
    
    if [[ "$switched_version" != "v1.1.0" ]]; then
        error "Version not updated after switch. Expected: v1.1.0, Got: $switched_version"
        return 1
    fi
    
    success "Traffic switching validation test passed"
    return 0
}

# Test 5: Health Check Sequence
test_health_check_sequence() {
    log "Testing health check sequence..."
    
    # Test comprehensive health checks for both environments
    local environments=("blue" "green")
    
    for env in "${environments[@]}"; do
        log "Testing health checks for $env environment"
        
        # Get all pods for the environment
        local pods
        readarray -t pods < <(kubectl get pods -n "$TEST_NAMESPACE" -l version="$env" -o jsonpath='{.items[*].metadata.name}' | tr ' ' '\n')
        
        for pod in "${pods[@]}"; do
            # Test readiness probe
            local ready_status
            ready_status=$(kubectl get pod "$pod" -n "$TEST_NAMESPACE" -o jsonpath='{.status.conditions[?(@.type=="Ready")].status}')
            
            if [[ "$ready_status" != "True" ]]; then
                error "Pod $pod in $env environment is not ready"
                return 1
            fi
            
            # Test liveness probe
            local restart_count
            restart_count=$(kubectl get pod "$pod" -n "$TEST_NAMESPACE" -o jsonpath='{.status.containerStatuses[0].restartCount}')
            
            if [[ $restart_count -gt 0 ]]; then
                warning "Pod $pod in $env environment has restarted $restart_count times"
            fi
            
            # Test application health endpoint
            local app_health
            app_health=$(kubectl exec -n "$TEST_NAMESPACE" "$pod" -- curl -s http://localhost/health)
            
            if ! echo "$app_health" | jq -e '.status == "healthy"' >/dev/null; then
                error "Application health check failed for pod $pod in $env environment"
                return 1
            fi
        done
        
        log "$env environment health checks passed for all ${#pods[@]} pods"
    done
    
    success "Health check sequence test passed"
    return 0
}

# Test 6: Rollback Procedures
test_rollback_procedures() {
    log "Testing rollback procedures..."
    
    # Current state should be green (from previous test)
    local current_env
    current_env=$(kubectl get service app-active-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.selector.version}')
    
    if [[ "$current_env" != "green" ]]; then
        error "Expected current environment to be green, got: $current_env"
        return 1
    fi
    
    log "Current active environment: $current_env"
    
    # Simulate a problem with green environment (scale down to simulate failure)
    kubectl scale deployment app-green --replicas=0 -n "$TEST_NAMESPACE"
    
    # Wait for pods to terminate
    sleep 10
    
    # Verify green environment is down
    local green_pods
    green_pods=$(kubectl get pods -n "$TEST_NAMESPACE" -l version=green --no-headers | wc -l)
    
    if [[ $green_pods -gt 0 ]]; then
        error "Green environment pods still running after scale down"
        return 1
    fi
    
    log "Simulated green environment failure"
    
    # Perform rollback to blue
    kubectl patch service app-active-service -n "$TEST_NAMESPACE" --patch '{"spec":{"selector":{"version":"blue"}}}'
    
    # Update deployment config to reflect rollback
    kubectl patch configmap deployment-config -n "$TEST_NAMESPACE" --patch '{"data":{"ENVIRONMENT_COLOR":"blue","ROLLBACK_TIMESTAMP":"'$(date -u +%Y-%m-%dT%H:%M:%SZ)'"}}'
    
    # Wait for rollback to take effect
    sleep 10
    
    # Test rollback
    local test_pod
    test_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l version=blue -o jsonpath='{.items[0].metadata.name}')
    
    local active_service_ip
    active_service_ip=$(kubectl get service app-active-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.clusterIP}')
    
    local rollback_response
    rollback_response=$(kubectl exec -n "$TEST_NAMESPACE" "$test_pod" -- curl -s "http://$active_service_ip/health")
    
    local rollback_env
    rollback_env=$(echo "$rollback_response" | jq -r '.environment')
    
    if [[ "$rollback_env" != "blue" ]]; then
        error "Rollback failed. Expected: blue, Got: $rollback_env"
        return 1
    fi
    
    log "Successfully rolled back to blue environment"
    
    # Restore green environment for cleanup
    kubectl scale deployment app-green --replicas=3 -n "$TEST_NAMESPACE"
    kubectl wait --for=condition=available --timeout=120s deployment/app-green -n "$TEST_NAMESPACE"
    
    success "Rollback procedures test passed"
    return 0
}

# Test 7: Resource Utilization Monitoring
test_resource_utilization_monitoring() {
    log "Testing resource utilization monitoring..."
    
    # Check resource usage for both environments
    local environments=("blue" "green")
    
    for env in "${environments[@]}"; do
        log "Checking resource utilization for $env environment"
        
        # Get deployment resource requests and limits
        local cpu_requests memory_requests cpu_limits memory_limits
        cpu_requests=$(kubectl get deployment "app-$env" -n "$TEST_NAMESPACE" -o jsonpath='{.spec.template.spec.containers[0].resources.requests.cpu}')
        memory_requests=$(kubectl get deployment "app-$env" -n "$TEST_NAMESPACE" -o jsonpath='{.spec.template.spec.containers[0].resources.requests.memory}')
        cpu_limits=$(kubectl get deployment "app-$env" -n "$TEST_NAMESPACE" -o jsonpath='{.spec.template.spec.containers[0].resources.limits.cpu}')
        memory_limits=$(kubectl get deployment "app-$env" -n "$TEST_NAMESPACE" -o jsonpath='{.spec.template.spec.containers[0].resources.limits.memory}')
        
        log "$env environment resources - CPU: $cpu_requests/$cpu_limits, Memory: $memory_requests/$memory_limits"
        
        # Check actual resource usage (if metrics-server is available)
        if kubectl top pods -n "$TEST_NAMESPACE" >/dev/null 2>&1; then
            local pods
            readarray -t pods < <(kubectl get pods -n "$TEST_NAMESPACE" -l version="$env" -o jsonpath='{.items[*].metadata.name}' | tr ' ' '\n')
            
            for pod in "${pods[@]}"; do
                local cpu_usage memory_usage
                cpu_usage=$(kubectl top pod "$pod" -n "$TEST_NAMESPACE" --no-headers | awk '{print $2}')
                memory_usage=$(kubectl top pod "$pod" -n "$TEST_NAMESPACE" --no-headers | awk '{print $3}')
                
                log "Pod $pod usage - CPU: $cpu_usage, Memory: $memory_usage"
            done
        else
            warning "Metrics server not available, skipping actual usage monitoring"
        fi
    done
    
    success "Resource utilization monitoring test passed"
    return 0
}

# Test 8: Post-deployment Validation
test_post_deployment_validation() {
    log "Testing post-deployment validation..."
    
    # Verify both environments are healthy
    local environments=("blue" "green")
    
    for env in "${environments[@]}"; do
        # Check deployment status
        local deployment_status
        deployment_status=$(kubectl get deployment "app-$env" -n "$TEST_NAMESPACE" -o jsonpath='{.status.conditions[?(@.type=="Available")].status}')
        
        if [[ "$deployment_status" != "True" ]]; then
            error "$env deployment is not available"
            return 1
        fi
        
        # Check service endpoints
        local endpoints
        endpoints=$(kubectl get endpoints "app-$env-service" -n "$TEST_NAMESPACE" -o jsonpath='{.subsets[0].addresses}')
        
        if [[ -z "$endpoints" ]]; then
            error "$env service has no endpoints"
            return 1
        fi
        
        local endpoint_count
        endpoint_count=$(echo "$endpoints" | jq '. | length')
        
        if [[ $endpoint_count -lt 3 ]]; then
            error "$env service has insufficient endpoints. Expected: 3, Got: $endpoint_count"
            return 1
        fi
        
        log "$env environment has $endpoint_count healthy endpoints"
    done
    
    # Test cross-environment connectivity
    local blue_pod green_service_ip
    blue_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l version=blue -o jsonpath='{.items[0].metadata.name}')
    green_service_ip=$(kubectl get service app-green-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.clusterIP}')
    
    if ! kubectl exec -n "$TEST_NAMESPACE" "$blue_pod" -- curl -s --max-time 10 "http://$green_service_ip/health" >/dev/null; then
        error "Cross-environment connectivity test failed"
        return 1
    fi
    
    # Verify configuration consistency
    local config_env
    config_env=$(kubectl get configmap deployment-config -n "$TEST_NAMESPACE" -o jsonpath='{.data.ENVIRONMENT_COLOR}')
    
    local active_env
    active_env=$(kubectl get service app-active-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.selector.version}')
    
    if [[ "$config_env" != "$active_env" ]]; then
        warning "Configuration environment ($config_env) does not match active environment ($active_env)"
    fi
    
    success "Post-deployment validation test passed"
    return 0
}

# Main test execution
main() {
    log "Starting End-to-End Deployment Test Suite"
    log "Log file: $LOG_FILE"
    
    # Check prerequisites
    if ! check_prerequisites; then
        error "Prerequisites check failed"
        exit 1
    fi
    
    # Run all tests in sequence
    run_test "Pre-deployment Validation" test_pre_deployment_validation
    run_test "Blue Environment Deployment" test_blue_environment_deployment
    run_test "Green Environment Deployment" test_green_environment_deployment
    run_test "Traffic Switching Validation" test_traffic_switching_validation
    run_test "Health Check Sequence" test_health_check_sequence
    run_test "Rollback Procedures" test_rollback_procedures
    run_test "Resource Utilization Monitoring" test_resource_utilization_monitoring
    run_test "Post-deployment Validation" test_post_deployment_validation
    
    # Print test summary
    log "Test Summary:"
    log "============="
    success "Tests Passed: $TESTS_PASSED"
    
    if [[ $TESTS_FAILED -gt 0 ]]; then
        error "Tests Failed: $TESTS_FAILED"
        error "Failed Tests:"
        for test in "${FAILED_TESTS[@]}"; do
            error "  - $test"
        done
        exit 1
    else
        success "All E2E tests passed!"
        exit 0
    fi
}

# Run main function
main "$@"
