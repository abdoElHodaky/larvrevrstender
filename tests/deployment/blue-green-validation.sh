#!/bin/bash

# Blue-Green Deployment Validation Test Suite
# Comprehensive testing for blue-green environment switching, health checks,
# cross-environment connectivity, and service discovery validation

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
LOG_FILE="/tmp/blue-green-validation-test-$(date +%Y%m%d-%H%M%S).log"
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
    
    # Clean up any test services
    kubectl delete service test-blue-service test-green-service -n "$TEST_NAMESPACE" 2>/dev/null || true
    
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
    log "Setting up test environment..."
    
    # Create test namespace
    kubectl create namespace "$TEST_NAMESPACE" || return 1
    
    # Create blue-green config for testing
    cat > /tmp/test-blue-green-config.yaml << EOF
apiVersion: v1
kind: ConfigMap
metadata:
  name: blue-green-config
  namespace: $TEST_NAMESPACE
data:
  ENVIRONMENT_COLOR: "blue"
  DEPLOYMENT_TIMESTAMP: "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  PREVIOUS_COLOR: "green"
  HEALTH_CHECK_TIMEOUT: "30"
  TRAFFIC_SWITCH_TIMEOUT: "60"
EOF
    
    kubectl apply -f /tmp/test-blue-green-config.yaml
    
    success "Test environment setup completed"
    return 0
}

# Test 1: Environment Color Configuration
test_environment_color_configuration() {
    log "Testing environment color configuration..."
    
    # Check if blue-green config exists
    if ! kubectl get configmap blue-green-config -n "$TEST_NAMESPACE" >/dev/null 2>&1; then
        error "Blue-green config not found"
        return 1
    fi
    
    # Get current environment color
    local current_color
    current_color=$(kubectl get configmap blue-green-config -n "$TEST_NAMESPACE" -o jsonpath='{.data.ENVIRONMENT_COLOR}')
    
    if [[ -z "$current_color" ]]; then
        error "Environment color not set in config"
        return 1
    fi
    
    if [[ "$current_color" != "blue" && "$current_color" != "green" ]]; then
        error "Invalid environment color: $current_color"
        return 1
    fi
    
    log "Current environment color: $current_color"
    
    # Test environment color switching
    local new_color
    if [[ "$current_color" == "blue" ]]; then
        new_color="green"
    else
        new_color="blue"
    fi
    
    # Update environment color
    kubectl patch configmap blue-green-config -n "$TEST_NAMESPACE" --patch "{\"data\":{\"ENVIRONMENT_COLOR\":\"$new_color\",\"PREVIOUS_COLOR\":\"$current_color\"}}"
    
    # Verify the change
    local updated_color
    updated_color=$(kubectl get configmap blue-green-config -n "$TEST_NAMESPACE" -o jsonpath='{.data.ENVIRONMENT_COLOR}')
    
    if [[ "$updated_color" != "$new_color" ]]; then
        error "Environment color was not updated correctly. Expected: $new_color, Got: $updated_color"
        return 1
    fi
    
    # Restore original color
    kubectl patch configmap blue-green-config -n "$TEST_NAMESPACE" --patch "{\"data\":{\"ENVIRONMENT_COLOR\":\"$current_color\",\"PREVIOUS_COLOR\":\"$new_color\"}}"
    
    success "Environment color configuration test passed"
    return 0
}

# Test 2: Blue and Green Environment Deployment
test_blue_green_environment_deployment() {
    log "Testing blue and green environment deployment..."
    
    # Create blue environment deployment
    cat > /tmp/test-blue-deployment.yaml << EOF
apiVersion: apps/v1
kind: Deployment
metadata:
  name: test-app-blue
  namespace: $TEST_NAMESPACE
  labels:
    app: test-app
    environment-color: blue
spec:
  replicas: 2
  selector:
    matchLabels:
      app: test-app
      environment-color: blue
  template:
    metadata:
      labels:
        app: test-app
        environment-color: blue
    spec:
      containers:
      - name: test-app
        image: nginx:alpine
        ports:
        - containerPort: 80
        env:
        - name: ENVIRONMENT_COLOR
          value: "blue"
        - name: POD_NAME
          valueFrom:
            fieldRef:
              fieldPath: metadata.name
        command: ["/bin/sh"]
        args:
        - -c
        - |
          echo "Environment: \$ENVIRONMENT_COLOR, Pod: \$POD_NAME" > /usr/share/nginx/html/index.html
          echo "{\"environment_color\": \"\$ENVIRONMENT_COLOR\", \"pod_name\": \"\$POD_NAME\", \"status\": \"healthy\"}" > /usr/share/nginx/html/health
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
  name: test-blue-service
  namespace: $TEST_NAMESPACE
  labels:
    app: test-app
    environment-color: blue
spec:
  selector:
    app: test-app
    environment-color: blue
  ports:
  - port: 80
    targetPort: 80
  type: ClusterIP
EOF
    
    kubectl apply -f /tmp/test-blue-deployment.yaml
    
    # Create green environment deployment
    cat > /tmp/test-green-deployment.yaml << EOF
apiVersion: apps/v1
kind: Deployment
metadata:
  name: test-app-green
  namespace: $TEST_NAMESPACE
  labels:
    app: test-app
    environment-color: green
spec:
  replicas: 2
  selector:
    matchLabels:
      app: test-app
      environment-color: green
  template:
    metadata:
      labels:
        app: test-app
        environment-color: green
    spec:
      containers:
      - name: test-app
        image: nginx:alpine
        ports:
        - containerPort: 80
        env:
        - name: ENVIRONMENT_COLOR
          value: "green"
        - name: POD_NAME
          valueFrom:
            fieldRef:
              fieldPath: metadata.name
        command: ["/bin/sh"]
        args:
        - -c
        - |
          echo "Environment: \$ENVIRONMENT_COLOR, Pod: \$POD_NAME" > /usr/share/nginx/html/index.html
          echo "{\"environment_color\": \"\$ENVIRONMENT_COLOR\", \"pod_name\": \"\$POD_NAME\", \"status\": \"healthy\"}" > /usr/share/nginx/html/health
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
  name: test-green-service
  namespace: $TEST_NAMESPACE
  labels:
    app: test-app
    environment-color: green
spec:
  selector:
    app: test-app
    environment-color: green
  ports:
  - port: 80
    targetPort: 80
  type: ClusterIP
EOF
    
    kubectl apply -f /tmp/test-green-deployment.yaml
    
    # Wait for deployments to be ready
    if ! kubectl wait --for=condition=available --timeout=120s deployment/test-app-blue -n "$TEST_NAMESPACE"; then
        error "Blue deployment failed to become ready"
        return 1
    fi
    
    if ! kubectl wait --for=condition=available --timeout=120s deployment/test-app-green -n "$TEST_NAMESPACE"; then
        error "Green deployment failed to become ready"
        return 1
    fi
    
    # Verify both environments are running
    local blue_pods green_pods
    blue_pods=$(kubectl get pods -n "$TEST_NAMESPACE" -l environment-color=blue --no-headers | wc -l)
    green_pods=$(kubectl get pods -n "$TEST_NAMESPACE" -l environment-color=green --no-headers | wc -l)
    
    if [[ $blue_pods -lt 2 ]]; then
        error "Blue environment does not have enough pods running. Expected: 2, Got: $blue_pods"
        return 1
    fi
    
    if [[ $green_pods -lt 2 ]]; then
        error "Green environment does not have enough pods running. Expected: 2, Got: $green_pods"
        return 1
    fi
    
    success "Blue and green environment deployment test passed"
    return 0
}

# Test 3: Health Check Validation
test_health_check_validation() {
    log "Testing health check validation..."
    
    # Test blue environment health
    local blue_pod
    blue_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l environment-color=blue -o jsonpath='{.items[0].metadata.name}')
    
    if [[ -z "$blue_pod" ]]; then
        error "No blue pods found"
        return 1
    fi
    
    # Test health endpoint
    local blue_health
    blue_health=$(kubectl exec -n "$TEST_NAMESPACE" "$blue_pod" -- curl -s http://localhost/health)
    
    if ! echo "$blue_health" | jq -e '.status == "healthy"' >/dev/null; then
        error "Blue environment health check failed. Response: $blue_health"
        return 1
    fi
    
    # Test green environment health
    local green_pod
    green_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l environment-color=green -o jsonpath='{.items[0].metadata.name}')
    
    if [[ -z "$green_pod" ]]; then
        error "No green pods found"
        return 1
    fi
    
    local green_health
    green_health=$(kubectl exec -n "$TEST_NAMESPACE" "$green_pod" -- curl -s http://localhost/health)
    
    if ! echo "$green_health" | jq -e '.status == "healthy"' >/dev/null; then
        error "Green environment health check failed. Response: $green_health"
        return 1
    fi
    
    # Verify environment colors in health responses
    local blue_color green_color
    blue_color=$(echo "$blue_health" | jq -r '.environment_color')
    green_color=$(echo "$green_health" | jq -r '.environment_color')
    
    if [[ "$blue_color" != "blue" ]]; then
        error "Blue environment reporting incorrect color: $blue_color"
        return 1
    fi
    
    if [[ "$green_color" != "green" ]]; then
        error "Green environment reporting incorrect color: $green_color"
        return 1
    fi
    
    success "Health check validation test passed"
    return 0
}

# Test 4: Cross-Environment Connectivity
test_cross_environment_connectivity() {
    log "Testing cross-environment connectivity..."
    
    # Get pod from blue environment
    local blue_pod
    blue_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l environment-color=blue -o jsonpath='{.items[0].metadata.name}')
    
    # Test connectivity from blue to green service
    local green_service_ip
    green_service_ip=$(kubectl get service test-green-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.clusterIP}')
    
    if [[ -z "$green_service_ip" ]]; then
        error "Green service IP not found"
        return 1
    fi
    
    # Test HTTP connectivity
    if ! kubectl exec -n "$TEST_NAMESPACE" "$blue_pod" -- curl -s --max-time 10 "http://$green_service_ip/health" >/dev/null; then
        error "Blue environment cannot connect to green service"
        return 1
    fi
    
    # Test connectivity from green to blue service
    local green_pod
    green_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l environment-color=green -o jsonpath='{.items[0].metadata.name}')
    
    local blue_service_ip
    blue_service_ip=$(kubectl get service test-blue-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.clusterIP}')
    
    if [[ -z "$blue_service_ip" ]]; then
        error "Blue service IP not found"
        return 1
    fi
    
    if ! kubectl exec -n "$TEST_NAMESPACE" "$green_pod" -- curl -s --max-time 10 "http://$blue_service_ip/health" >/dev/null; then
        error "Green environment cannot connect to blue service"
        return 1
    fi
    
    success "Cross-environment connectivity test passed"
    return 0
}

# Test 5: Service Discovery Validation
test_service_discovery_validation() {
    log "Testing service discovery validation..."
    
    # Test DNS resolution for services
    local blue_pod
    blue_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l environment-color=blue -o jsonpath='{.items[0].metadata.name}')
    
    # Test DNS resolution for green service
    if ! kubectl exec -n "$TEST_NAMESPACE" "$blue_pod" -- nslookup test-green-service."$TEST_NAMESPACE".svc.cluster.local >/dev/null 2>&1; then
        error "DNS resolution failed for green service from blue environment"
        return 1
    fi
    
    # Test DNS resolution for blue service from green environment
    local green_pod
    green_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l environment-color=green -o jsonpath='{.items[0].metadata.name}')
    
    if ! kubectl exec -n "$TEST_NAMESPACE" "$green_pod" -- nslookup test-blue-service."$TEST_NAMESPACE".svc.cluster.local >/dev/null 2>&1; then
        error "DNS resolution failed for blue service from green environment"
        return 1
    fi
    
    # Test service endpoint discovery
    local blue_endpoints green_endpoints
    blue_endpoints=$(kubectl get endpoints test-blue-service -n "$TEST_NAMESPACE" -o jsonpath='{.subsets[0].addresses}')
    green_endpoints=$(kubectl get endpoints test-green-service -n "$TEST_NAMESPACE" -o jsonpath='{.subsets[0].addresses}')
    
    if [[ -z "$blue_endpoints" ]]; then
        error "No endpoints found for blue service"
        return 1
    fi
    
    if [[ -z "$green_endpoints" ]]; then
        error "No endpoints found for green service"
        return 1
    fi
    
    # Count endpoints
    local blue_endpoint_count green_endpoint_count
    blue_endpoint_count=$(echo "$blue_endpoints" | jq '. | length')
    green_endpoint_count=$(echo "$green_endpoints" | jq '. | length')
    
    if [[ $blue_endpoint_count -lt 2 ]]; then
        error "Blue service has insufficient endpoints. Expected: 2, Got: $blue_endpoint_count"
        return 1
    fi
    
    if [[ $green_endpoint_count -lt 2 ]]; then
        error "Green service has insufficient endpoints. Expected: 2, Got: $green_endpoint_count"
        return 1
    fi
    
    success "Service discovery validation test passed"
    return 0
}

# Test 6: Environment Switching Simulation
test_environment_switching() {
    log "Testing environment switching simulation..."
    
    # Get current active environment
    local current_color
    current_color=$(kubectl get configmap blue-green-config -n "$TEST_NAMESPACE" -o jsonpath='{.data.ENVIRONMENT_COLOR}')
    
    # Determine target environment
    local target_color
    if [[ "$current_color" == "blue" ]]; then
        target_color="green"
    else
        target_color="blue"
    fi
    
    log "Simulating switch from $current_color to $target_color environment"
    
    # Update configuration to switch environments
    kubectl patch configmap blue-green-config -n "$TEST_NAMESPACE" --patch "{
        \"data\": {
            \"ENVIRONMENT_COLOR\": \"$target_color\",
            \"PREVIOUS_COLOR\": \"$current_color\",
            \"DEPLOYMENT_TIMESTAMP\": \"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"
        }
    }"
    
    # Verify the switch
    local new_color
    new_color=$(kubectl get configmap blue-green-config -n "$TEST_NAMESPACE" -o jsonpath='{.data.ENVIRONMENT_COLOR}')
    
    if [[ "$new_color" != "$target_color" ]]; then
        error "Environment switch failed. Expected: $target_color, Got: $new_color"
        return 1
    fi
    
    # Verify previous color is recorded
    local previous_color
    previous_color=$(kubectl get configmap blue-green-config -n "$TEST_NAMESPACE" -o jsonpath='{.data.PREVIOUS_COLOR}')
    
    if [[ "$previous_color" != "$current_color" ]]; then
        error "Previous color not recorded correctly. Expected: $current_color, Got: $previous_color"
        return 1
    fi
    
    # Test that both environments are still healthy after switch
    sleep 5
    
    # Verify target environment is healthy
    local target_pod target_health
    target_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l environment-color="$target_color" -o jsonpath='{.items[0].metadata.name}')
    target_health=$(kubectl exec -n "$TEST_NAMESPACE" "$target_pod" -- curl -s http://localhost/health)
    
    if ! echo "$target_health" | jq -e '.status == "healthy"' >/dev/null; then
        error "Target environment ($target_color) is not healthy after switch"
        return 1
    fi
    
    # Switch back to original environment
    kubectl patch configmap blue-green-config -n "$TEST_NAMESPACE" --patch "{
        \"data\": {
            \"ENVIRONMENT_COLOR\": \"$current_color\",
            \"PREVIOUS_COLOR\": \"$target_color\",
            \"DEPLOYMENT_TIMESTAMP\": \"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"
        }
    }"
    
    success "Environment switching simulation test passed"
    return 0
}

# Test 7: Load Balancing and Traffic Distribution
test_load_balancing_traffic_distribution() {
    log "Testing load balancing and traffic distribution..."
    
    # Test load balancing within blue environment
    local blue_responses=()
    local blue_service_ip
    blue_service_ip=$(kubectl get service test-blue-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.clusterIP}')
    
    # Make multiple requests to test load balancing
    local test_pod
    test_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l environment-color=blue -o jsonpath='{.items[0].metadata.name}')
    
    for i in {1..10}; do
        local response
        response=$(kubectl exec -n "$TEST_NAMESPACE" "$test_pod" -- curl -s "http://$blue_service_ip/health" | jq -r '.pod_name')
        blue_responses+=("$response")
    done
    
    # Check if requests were distributed across multiple pods
    local unique_pods
    unique_pods=$(printf '%s\n' "${blue_responses[@]}" | sort -u | wc -l)
    
    if [[ $unique_pods -lt 2 ]]; then
        warning "Load balancing may not be working properly. Only $unique_pods unique pods responded"
    else
        log "Load balancing working correctly. Requests distributed across $unique_pods pods"
    fi
    
    # Test the same for green environment
    local green_responses=()
    local green_service_ip
    green_service_ip=$(kubectl get service test-green-service -n "$TEST_NAMESPACE" -o jsonpath='{.spec.clusterIP}')
    
    for i in {1..10}; do
        local response
        response=$(kubectl exec -n "$TEST_NAMESPACE" "$test_pod" -- curl -s "http://$green_service_ip/health" | jq -r '.pod_name')
        green_responses+=("$response")
    done
    
    unique_pods=$(printf '%s\n' "${green_responses[@]}" | sort -u | wc -l)
    
    if [[ $unique_pods -lt 2 ]]; then
        warning "Green environment load balancing may not be working properly. Only $unique_pods unique pods responded"
    else
        log "Green environment load balancing working correctly. Requests distributed across $unique_pods pods"
    fi
    
    success "Load balancing and traffic distribution test passed"
    return 0
}

# Test 8: Environment Consistency Validation
test_environment_consistency() {
    log "Testing environment consistency validation..."
    
    # Check that all blue pods report the same environment color
    local blue_pods
    readarray -t blue_pods < <(kubectl get pods -n "$TEST_NAMESPACE" -l environment-color=blue -o jsonpath='{.items[*].metadata.name}' | tr ' ' '\n')
    
    for pod in "${blue_pods[@]}"; do
        local pod_color
        pod_color=$(kubectl exec -n "$TEST_NAMESPACE" "$pod" -- curl -s http://localhost/health | jq -r '.environment_color')
        
        if [[ "$pod_color" != "blue" ]]; then
            error "Blue pod $pod reporting incorrect environment color: $pod_color"
            return 1
        fi
    done
    
    # Check that all green pods report the same environment color
    local green_pods
    readarray -t green_pods < <(kubectl get pods -n "$TEST_NAMESPACE" -l environment-color=green -o jsonpath='{.items[*].metadata.name}' | tr ' ' '\n')
    
    for pod in "${green_pods[@]}"; do
        local pod_color
        pod_color=$(kubectl exec -n "$TEST_NAMESPACE" "$pod" -- curl -s http://localhost/health | jq -r '.environment_color')
        
        if [[ "$pod_color" != "green" ]]; then
            error "Green pod $pod reporting incorrect environment color: $pod_color"
            return 1
        fi
    done
    
    # Verify that environments are isolated (no cross-contamination)
    local blue_pod_count_in_green green_pod_count_in_blue
    blue_pod_count_in_green=$(kubectl get pods -n "$TEST_NAMESPACE" -l app=test-app,environment-color=green --field-selector=metadata.name~=blue --no-headers 2>/dev/null | wc -l)
    green_pod_count_in_blue=$(kubectl get pods -n "$TEST_NAMESPACE" -l app=test-app,environment-color=blue --field-selector=metadata.name~=green --no-headers 2>/dev/null | wc -l)
    
    if [[ $blue_pod_count_in_green -gt 0 ]]; then
        error "Found blue pods in green environment (cross-contamination detected)"
        return 1
    fi
    
    if [[ $green_pod_count_in_blue -gt 0 ]]; then
        error "Found green pods in blue environment (cross-contamination detected)"
        return 1
    fi
    
    success "Environment consistency validation test passed"
    return 0
}

# Main test execution
main() {
    log "Starting Blue-Green Deployment Validation Test Suite"
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
    run_test "Environment Color Configuration" test_environment_color_configuration
    run_test "Blue and Green Environment Deployment" test_blue_green_environment_deployment
    run_test "Health Check Validation" test_health_check_validation
    run_test "Cross-Environment Connectivity" test_cross_environment_connectivity
    run_test "Service Discovery Validation" test_service_discovery_validation
    run_test "Environment Switching Simulation" test_environment_switching
    run_test "Load Balancing and Traffic Distribution" test_load_balancing_traffic_distribution
    run_test "Environment Consistency Validation" test_environment_consistency
    
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

