#!/bin/bash

# Traffic Switch Test Suite
# Comprehensive testing for ingress routing, load balancer configuration,
# traffic distribution verification, and traffic rollback testing

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
LOG_FILE="/tmp/traffic-switch-test-$(date +%Y%m%d-%H%M%S).log"
TEST_NAMESPACE="reverse-tender-test"
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
        kubectl delete namespace "$TEST_NAMESPACE" --timeout=60s || true
    fi
    
    log "Cleanup completed"
}

# Set up cleanup trap
trap cleanup EXIT

# Prerequisites check
check_prerequisites() {
    log "Checking prerequisites..."
    
    # Check if kubectl is available
    if ! command -v kubectl &> /dev/null; then
        error "kubectl is not installed or not in PATH"
        return 1
    fi
    
    # Check if curl is available
    if ! command -v curl &> /dev/null; then
        error "curl is not installed or not in PATH"
        return 1
    fi
    
    # Check if jq is available
    if ! command -v jq &> /dev/null; then
        error "jq is not installed or not in PATH"
        return 1
    fi
    
    # Check Kubernetes cluster connectivity
    if ! kubectl cluster-info >/dev/null 2>&1; then
        error "Cannot connect to Kubernetes cluster"
        return 1
    fi
    
    success "Prerequisites check passed"
    return 0
}

# Setup test environment
setup_test_environment() {
    log "Setting up traffic switch test environment..."
    
    # Create test namespace
    kubectl create namespace "$TEST_NAMESPACE" || return 1
    
    # Deploy blue and green applications
    cat > /tmp/traffic-test-deployments.yaml << EOF
apiVersion: apps/v1
kind: Deployment
metadata:
  name: app-blue
  namespace: $TEST_NAMESPACE
spec:
  replicas: 2
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
        command: ["/bin/sh"]
        args:
        - -c
        - |
          echo "Blue Environment - Pod: \$HOSTNAME" > /usr/share/nginx/html/index.html
          echo "{\"version\": \"blue\", \"pod\": \"\$HOSTNAME\", \"timestamp\": \"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"}" > /usr/share/nginx/html/health
          nginx -g 'daemon off;'
---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: app-green
  namespace: $TEST_NAMESPACE
spec:
  replicas: 2
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
        command: ["/bin/sh"]
        args:
        - -c
        - |
          echo "Green Environment - Pod: \$HOSTNAME" > /usr/share/nginx/html/index.html
          echo "{\"version\": \"green\", \"pod\": \"\$HOSTNAME\", \"timestamp\": \"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"}" > /usr/share/nginx/html/health
          nginx -g 'daemon off;'
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
---
apiVersion: v1
kind: Service
metadata:
  name: app-active-service
  namespace: $TEST_NAMESPACE
spec:
  selector:
    app: test-app
    version: blue  # Initially points to blue
  ports:
  - port: 80
    targetPort: 80
EOF
    
    kubectl apply -f /tmp/traffic-test-deployments.yaml
    
    # Wait for deployments to be ready
    kubectl wait --for=condition=available --timeout=120s deployment/app-blue -n "$TEST_NAMESPACE"
    kubectl wait --for=condition=available --timeout=120s deployment/app-green -n "$TEST_NAMESPACE"
    
    # Create ingress for traffic routing
    cat > /tmp/traffic-test-ingress.yaml << EOF
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: app-ingress
  namespace: $TEST_NAMESPACE
  annotations:
    nginx.ingress.kubernetes.io/rewrite-target: /
spec:
  rules:
  - host: test-app.local
    http:
      paths:
      - path: /
        pathType: Prefix
        backend:
          service:
            name: app-active-service
            port:
              number: 80
EOF
    
    kubectl apply -f /tmp/traffic-test-ingress.yaml
    
    success "Traffic switch test environment setup completed"
    return 0
}

# Test 1: Ingress Routing Validation
test_ingress_routing_validation() {
    log "Testing ingress routing validation..."
    
    # Check if ingress exists
    if ! kubectl get ingress app-ingress -n "$TEST_NAMESPACE" >/dev/null 2>&1; then
        error "Ingress not found"
        return 1
    fi
    
    # Get ingress details
    local ingress_backend
    ingress_backend=$(kubectl get ingress app-ingress -n "$TEST_NAMESPACE" -o jsonpath='{.spec.rules[0].http.paths[0].backend.service.name}')
    
    if [[ "$ingress_backend" != "app-active-service" ]]; then
        error "Ingress backend is incorrect. Expected: app-active-service, Got: $ingress_backend"
        return 1
    fi
    
    # Test routing through a test pod
    local test_pod
    test_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l version=blue -o jsonpath='{.items[0].metadata.name}')
    
    # Get active service IP
    local active_service_ip
    active_service_ip=$(kubectl get service app-active-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.clusterIP}')
    
    # Test routing to active service
    local response
    response=$(kubectl exec -n "$TEST_NAMESPACE" "$test_pod" -- curl -s "http://$active_service_ip/health")
    
    if ! echo "$response" | jq -e '.version' >/dev/null; then
        error "Invalid response from active service: $response"
        return 1
    fi
    
    local active_version
    active_version=$(echo "$response" | jq -r '.version')
    log "Active service is routing to: $active_version environment"
    
    success "Ingress routing validation test passed"
    return 0
}

# Test 2: Load Balancer Configuration Testing
test_load_balancer_configuration() {
    log "Testing load balancer configuration..."
    
    # Test load balancing within blue environment
    local blue_service_ip
    blue_service_ip=$(kubectl get service app-blue-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.clusterIP}')
    
    local test_pod
    test_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l version=blue -o jsonpath='{.items[0].metadata.name}')
    
    # Make multiple requests to test load balancing
    local blue_responses=()
    for i in {1..20}; do
        local response
        response=$(kubectl exec -n "$TEST_NAMESPACE" "$test_pod" -- curl -s "http://$blue_service_ip/health" | jq -r '.pod')
        blue_responses+=("$response")
    done
    
    # Check distribution across pods
    local unique_blue_pods
    unique_blue_pods=$(printf '%s\n' "${blue_responses[@]}" | sort -u | wc -l)
    
    if [[ $unique_blue_pods -lt 2 ]]; then
        error "Blue environment load balancing not working. Only $unique_blue_pods unique pods responded"
        return 1
    fi
    
    log "Blue environment: Requests distributed across $unique_blue_pods pods"
    
    # Test load balancing within green environment
    local green_service_ip
    green_service_ip=$(kubectl get service app-green-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.clusterIP}')
    
    local green_responses=()
    for i in {1..20}; do
        local response
        response=$(kubectl exec -n "$TEST_NAMESPACE" "$test_pod" -- curl -s "http://$green_service_ip/health" | jq -r '.pod')
        green_responses+=("$response")
    done
    
    local unique_green_pods
    unique_green_pods=$(printf '%s\n' "${green_responses[@]}" | sort -u | wc -l)
    
    if [[ $unique_green_pods -lt 2 ]]; then
        error "Green environment load balancing not working. Only $unique_green_pods unique pods responded"
        return 1
    fi
    
    log "Green environment: Requests distributed across $unique_green_pods pods"
    
    success "Load balancer configuration test passed"
    return 0
}

# Test 3: Traffic Distribution Verification
test_traffic_distribution_verification() {
    log "Testing traffic distribution verification..."
    
    # Get current active service selector
    local current_selector
    current_selector=$(kubectl get service app-active-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.selector.version}')
    
    log "Current active service points to: $current_selector environment"
    
    # Test traffic distribution to active environment
    local active_service_ip
    active_service_ip=$(kubectl get service app-active-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.clusterIP}')
    
    local test_pod
    test_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l version=blue -o jsonpath='{.items[0].metadata.name}')
    
    # Make multiple requests and verify they all go to the same environment
    local responses=()
    for i in {1..10}; do
        local response
        response=$(kubectl exec -n "$TEST_NAMESPACE" "$test_pod" -- curl -s "http://$active_service_ip/health" | jq -r '.version')
        responses+=("$response")
    done
    
    # Check that all responses are from the same environment
    local unique_versions
    unique_versions=$(printf '%s\n' "${responses[@]}" | sort -u | wc -l)
    
    if [[ $unique_versions -ne 1 ]]; then
        error "Traffic is being distributed across multiple environments. Expected: 1, Got: $unique_versions"
        return 1
    fi
    
    local active_version
    active_version=$(printf '%s\n' "${responses[@]}" | head -1)
    
    if [[ "$active_version" != "$current_selector" ]]; then
        error "Traffic is not going to the expected environment. Expected: $current_selector, Got: $active_version"
        return 1
    fi
    
    log "All traffic is correctly routed to $active_version environment"
    
    success "Traffic distribution verification test passed"
    return 0
}

# Test 4: Blue-Green Traffic Switch
test_blue_green_traffic_switch() {
    log "Testing blue-green traffic switch..."
    
    # Get current active environment
    local current_version
    current_version=$(kubectl get service app-active-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.selector.version}')
    
    # Determine target environment
    local target_version
    if [[ "$current_version" == "blue" ]]; then
        target_version="green"
    else
        target_version="blue"
    fi
    
    log "Switching traffic from $current_version to $target_version"
    
    # Perform traffic switch by updating service selector
    kubectl patch service app-active-service -n "$TEST_NAMESPACE" --patch "{\"spec\":{\"selector\":{\"version\":\"$target_version\"}}}"
    
    # Wait for service to update
    sleep 10
    
    # Verify the switch
    local new_version
    new_version=$(kubectl get service app-active-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.selector.version}')
    
    if [[ "$new_version" != "$target_version" ]]; then
        error "Service selector was not updated. Expected: $target_version, Got: $new_version"
        return 1
    fi
    
    # Test that traffic is now going to the new environment
    local active_service_ip
    active_service_ip=$(kubectl get service app-active-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.clusterIP}')
    
    local test_pod
    test_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l version=blue -o jsonpath='{.items[0].metadata.name}')
    
    # Wait for DNS/service mesh to propagate changes
    sleep 15
    
    # Verify traffic is going to new environment
    local verification_attempts=5
    local successful_switches=0
    
    for i in $(seq 1 $verification_attempts); do
        local response
        response=$(kubectl exec -n "$TEST_NAMESPACE" "$test_pod" -- curl -s "http://$active_service_ip/health" | jq -r '.version')
        
        if [[ "$response" == "$target_version" ]]; then
            ((successful_switches++))
        fi
        
        sleep 2
    done
    
    if [[ $successful_switches -lt $((verification_attempts - 1)) ]]; then
        error "Traffic switch verification failed. Only $successful_switches/$verification_attempts requests went to $target_version"
        return 1
    fi
    
    log "Traffic successfully switched to $target_version environment"
    
    # Switch back to original environment for cleanup
    kubectl patch service app-active-service -n "$TEST_NAMESPACE" --patch "{\"spec\":{\"selector\":{\"version\":\"$current_version\"}}}"
    
    success "Blue-green traffic switch test passed"
    return 0
}

# Test 5: Canary Deployment Validation
test_canary_deployment_validation() {
    log "Testing canary deployment validation..."
    
    # Create a canary service that splits traffic
    cat > /tmp/canary-service.yaml << EOF
apiVersion: v1
kind: Service
metadata:
  name: app-canary-service
  namespace: $TEST_NAMESPACE
spec:
  selector:
    app: test-app
    # No version selector - will route to both blue and green
  ports:
  - port: 80
    targetPort: 80
EOF
    
    kubectl apply -f /tmp/canary-service.yaml
    
    # Test traffic distribution across both environments
    local canary_service_ip
    canary_service_ip=$(kubectl get service app-canary-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.clusterIP}')
    
    local test_pod
    test_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l version=blue -o jsonpath='{.items[0].metadata.name}')
    
    # Make multiple requests to see traffic distribution
    local blue_count=0
    local green_count=0
    local total_requests=50
    
    for i in $(seq 1 $total_requests); do
        local response
        response=$(kubectl exec -n "$TEST_NAMESPACE" "$test_pod" -- curl -s "http://$canary_service_ip/health" | jq -r '.version')
        
        if [[ "$response" == "blue" ]]; then
            ((blue_count++))
        elif [[ "$response" == "green" ]]; then
            ((green_count++))
        fi
    done
    
    log "Canary traffic distribution - Blue: $blue_count, Green: $green_count (out of $total_requests)"
    
    # Verify that traffic is distributed to both environments
    if [[ $blue_count -eq 0 ]]; then
        error "No traffic reached blue environment in canary deployment"
        return 1
    fi
    
    if [[ $green_count -eq 0 ]]; then
        error "No traffic reached green environment in canary deployment"
        return 1
    fi
    
    # Check that distribution is reasonably balanced (within 80-20 to 20-80 range)
    local blue_percentage=$((blue_count * 100 / total_requests))
    local green_percentage=$((green_count * 100 / total_requests))
    
    if [[ $blue_percentage -lt 20 || $blue_percentage -gt 80 ]]; then
        warning "Canary traffic distribution may be unbalanced. Blue: ${blue_percentage}%, Green: ${green_percentage}%"
    else
        log "Canary traffic distribution is balanced. Blue: ${blue_percentage}%, Green: ${green_percentage}%"
    fi
    
    # Clean up canary service
    kubectl delete service app-canary-service -n "$TEST_NAMESPACE"
    
    success "Canary deployment validation test passed"
    return 0
}

# Test 6: Traffic Rollback Testing
test_traffic_rollback() {
    log "Testing traffic rollback..."
    
    # Get current active environment
    local original_version
    original_version=$(kubectl get service app-active-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.selector.version}')
    
    # Switch to other environment
    local target_version
    if [[ "$original_version" == "blue" ]]; then
        target_version="green"
    else
        target_version="blue"
    fi
    
    log "Switching from $original_version to $target_version for rollback test"
    
    # Perform switch
    kubectl patch service app-active-service -n "$TEST_NAMESPACE" --patch "{\"spec\":{\"selector\":{\"version\":\"$target_version\"}}}"
    
    # Wait for switch to take effect
    sleep 10
    
    # Verify switch occurred
    local current_version
    current_version=$(kubectl get service app-active-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.selector.version}')
    
    if [[ "$current_version" != "$target_version" ]]; then
        error "Initial switch failed. Expected: $target_version, Got: $current_version"
        return 1
    fi
    
    log "Successfully switched to $target_version. Now testing rollback..."
    
    # Simulate rollback scenario (switch back to original)
    kubectl patch service app-active-service -n "$TEST_NAMESPACE" --patch "{\"spec\":{\"selector\":{\"version\":\"$original_version\"}}}"
    
    # Wait for rollback to take effect
    sleep 10
    
    # Verify rollback
    local rollback_version
    rollback_version=$(kubectl get service app-active-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.selector.version}')
    
    if [[ "$rollback_version" != "$original_version" ]]; then
        error "Rollback failed. Expected: $original_version, Got: $rollback_version"
        return 1
    fi
    
    # Test that traffic is flowing to rolled-back environment
    local active_service_ip
    active_service_ip=$(kubectl get service app-active-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.clusterIP}')
    
    local test_pod
    test_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l version=blue -o jsonpath='{.items[0].metadata.name}')
    
    # Wait for changes to propagate
    sleep 15
    
    # Verify traffic is going to rolled-back environment
    local verification_response
    verification_response=$(kubectl exec -n "$TEST_NAMESPACE" "$test_pod" -- curl -s "http://$active_service_ip/health" | jq -r '.version')
    
    if [[ "$verification_response" != "$original_version" ]]; then
        error "Traffic is not flowing to rolled-back environment. Expected: $original_version, Got: $verification_response"
        return 1
    fi
    
    log "Successfully rolled back to $original_version environment"
    
    success "Traffic rollback test passed"
    return 0
}

# Test 7: Ingress Controller Health
test_ingress_controller_health() {
    log "Testing ingress controller health..."
    
    # Check if ingress controller is running (common ingress controllers)
    local ingress_controllers=("nginx-ingress-controller" "traefik" "istio-ingressgateway")
    local controller_found=false
    
    for controller in "${ingress_controllers[@]}"; do
        if kubectl get pods --all-namespaces -l app.kubernetes.io/name="$controller" >/dev/null 2>&1; then
            log "Found ingress controller: $controller"
            controller_found=true
            
            # Check controller health
            local controller_pods
            controller_pods=$(kubectl get pods --all-namespaces -l app.kubernetes.io/name="$controller" --no-headers | wc -l)
            
            if [[ $controller_pods -eq 0 ]]; then
                error "No $controller pods found"
                return 1
            fi
            
            log "$controller has $controller_pods pod(s) running"
            break
        fi
    done
    
    if [[ "$controller_found" == false ]]; then
        warning "No common ingress controller found. Ingress functionality may be limited."
    fi
    
    # Test ingress resource status
    local ingress_ready
    ingress_ready=$(kubectl get ingress app-ingress -n "$TEST_NAMESPACE" -o jsonpath='{.status.loadBalancer.ingress}')
    
    if [[ -n "$ingress_ready" ]]; then
        log "Ingress has load balancer assigned"
    else
        warning "Ingress does not have load balancer assigned (may be expected in test environment)"
    fi
    
    success "Ingress controller health test passed"
    return 0
}

# Test 8: Network Policy Validation
test_network_policy_validation() {
    log "Testing network policy validation..."
    
    # Create a test network policy
    cat > /tmp/test-network-policy.yaml << EOF
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: test-network-policy
  namespace: $TEST_NAMESPACE
spec:
  podSelector:
    matchLabels:
      app: test-app
  policyTypes:
  - Ingress
  - Egress
  ingress:
  - from:
    - podSelector:
        matchLabels:
          app: test-app
    ports:
    - protocol: TCP
      port: 80
  egress:
  - to:
    - podSelector:
        matchLabels:
          app: test-app
    ports:
    - protocol: TCP
      port: 80
  - to: []  # Allow DNS
    ports:
    - protocol: UDP
      port: 53
EOF
    
    kubectl apply -f /tmp/test-network-policy.yaml
    
    # Test that pods can still communicate within the same app
    local blue_pod green_pod
    blue_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l version=blue -o jsonpath='{.items[0].metadata.name}')
    green_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l version=green -o jsonpath='{.items[0].metadata.name}')
    
    local green_service_ip
    green_service_ip=$(kubectl get service app-green-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.clusterIP}')
    
    # Test communication from blue to green (should work)
    if ! kubectl exec -n "$TEST_NAMESPACE" "$blue_pod" -- curl -s --max-time 10 "http://$green_service_ip/health" >/dev/null; then
        error "Network policy is blocking legitimate traffic between blue and green environments"
        return 1
    fi
    
    log "Network policy allows legitimate inter-environment communication"
    
    # Clean up network policy
    kubectl delete networkpolicy test-network-policy -n "$TEST_NAMESPACE"
    
    success "Network policy validation test passed"
    return 0
}

# Main test execution
main() {
    log "Starting Traffic Switch Test Suite"
    log "Log file: $LOG_FILE"
    
    # Check prerequisites
    if ! check_prerequisites; then
        error "Prerequisites check failed"
        exit 1
    fi
    
    # Setup test environment
    if ! setup_test_environment; then
        error "Test environment setup failed"
        exit 1
    fi
    
    # Run all tests
    run_test "Ingress Routing Validation" test_ingress_routing_validation
    run_test "Load Balancer Configuration" test_load_balancer_configuration
    run_test "Traffic Distribution Verification" test_traffic_distribution_verification
    run_test "Blue-Green Traffic Switch" test_blue_green_traffic_switch
    run_test "Canary Deployment Validation" test_canary_deployment_validation
    run_test "Traffic Rollback Testing" test_traffic_rollback
    run_test "Ingress Controller Health" test_ingress_controller_health
    run_test "Network Policy Validation" test_network_policy_validation
    
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
        success "All tests passed!"
        exit 0
    fi
}

# Run main function
main "$@"

