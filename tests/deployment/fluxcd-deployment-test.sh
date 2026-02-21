#!/bin/bash

# FluxCD Deployment Test Suite
# Comprehensive testing for FluxCD deployment processes, Git synchronization,
# and Kustomization reconciliation

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
LOG_FILE="/tmp/fluxcd-deployment-test-$(date +%Y%m%d-%H%M%S).log"
TEST_NAMESPACE="flux-system-test"
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
    
    # Clean up any test Git repositories
    rm -rf /tmp/fluxcd-test-repo-* || true
    
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
    
    # Check if flux CLI is available
    if ! command -v flux &> /dev/null; then
        error "flux CLI is not installed or not in PATH"
        return 1
    fi
    
    # Check if git is available
    if ! command -v git &> /dev/null; then
        error "git is not installed or not in PATH"
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

# Test 1: FluxCD Installation and Bootstrap
test_fluxcd_installation() {
    log "Testing FluxCD installation and bootstrap..."
    
    # Create test namespace
    kubectl create namespace "$TEST_NAMESPACE" || return 1
    
    # Check if FluxCD CRDs are installed
    local crds=(
        "gitrepositories.source.toolkit.fluxcd.io"
        "kustomizations.kustomize.toolkit.fluxcd.io"
        "helmreleases.helm.toolkit.fluxcd.io"
    )
    
    for crd in "${crds[@]}"; do
        if ! kubectl get crd "$crd" >/dev/null 2>&1; then
            error "FluxCD CRD not found: $crd"
            return 1
        fi
    done
    
    # Check FluxCD controllers are running
    local controllers=(
        "source-controller"
        "kustomize-controller"
        "helm-controller"
    )
    
    for controller in "${controllers[@]}"; do
        if ! kubectl get deployment "$controller" -n flux-system >/dev/null 2>&1; then
            error "FluxCD controller not found: $controller"
            return 1
        fi
        
        # Check if controller is ready
        if ! kubectl wait --for=condition=available --timeout=60s deployment/"$controller" -n flux-system; then
            error "FluxCD controller not ready: $controller"
            return 1
        fi
    done
    
    success "FluxCD installation test passed"
    return 0
}

# Test 2: Git Repository Synchronization
test_git_repository_sync() {
    log "Testing Git repository synchronization..."
    
    # Create a test Git repository
    local test_repo_dir="/tmp/fluxcd-test-repo-$(date +%s)"
    mkdir -p "$test_repo_dir"
    cd "$test_repo_dir"
    
    git init
    git config user.email "test@example.com"
    git config user.name "Test User"
    
    # Create test manifests
    mkdir -p manifests
    cat > manifests/test-configmap.yaml << EOF
apiVersion: v1
kind: ConfigMap
metadata:
  name: fluxcd-test-config
  namespace: $TEST_NAMESPACE
data:
  test-key: "test-value"
  sync-test: "$(date +%s)"
EOF
    
    git add .
    git commit -m "Initial test commit"
    
    # Create GitRepository resource
    cat > /tmp/test-gitrepository.yaml << EOF
apiVersion: source.toolkit.fluxcd.io/v1
kind: GitRepository
metadata:
  name: fluxcd-test-repo
  namespace: $TEST_NAMESPACE
spec:
  interval: 30s
  url: file://$test_repo_dir
  ref:
    branch: main
EOF
    
    kubectl apply -f /tmp/test-gitrepository.yaml
    
    # Wait for GitRepository to be ready
    if ! kubectl wait --for=condition=ready --timeout=120s gitrepository/fluxcd-test-repo -n "$TEST_NAMESPACE"; then
        error "GitRepository failed to become ready"
        return 1
    fi
    
    # Check GitRepository status
    local git_status
    git_status=$(kubectl get gitrepository fluxcd-test-repo -n "$TEST_NAMESPACE" -o jsonpath='{.status.conditions[?(@.type=="Ready")].status}')
    
    if [[ "$git_status" != "True" ]]; then
        error "GitRepository is not ready. Status: $git_status"
        kubectl describe gitrepository fluxcd-test-repo -n "$TEST_NAMESPACE"
        return 1
    fi
    
    success "Git repository synchronization test passed"
    return 0
}

# Test 3: Kustomization Reconciliation
test_kustomization_reconciliation() {
    log "Testing Kustomization reconciliation..."
    
    # Create Kustomization resource
    cat > /tmp/test-kustomization.yaml << EOF
apiVersion: kustomize.toolkit.fluxcd.io/v1
kind: Kustomization
metadata:
  name: fluxcd-test-kustomization
  namespace: $TEST_NAMESPACE
spec:
  interval: 30s
  path: "./manifests"
  prune: true
  sourceRef:
    kind: GitRepository
    name: fluxcd-test-repo
  targetNamespace: $TEST_NAMESPACE
  timeout: 2m
EOF
    
    kubectl apply -f /tmp/test-kustomization.yaml
    
    # Wait for Kustomization to be ready
    if ! kubectl wait --for=condition=ready --timeout=180s kustomization/fluxcd-test-kustomization -n "$TEST_NAMESPACE"; then
        error "Kustomization failed to become ready"
        kubectl describe kustomization fluxcd-test-kustomization -n "$TEST_NAMESPACE"
        return 1
    fi
    
    # Check if the ConfigMap was created
    if ! kubectl get configmap fluxcd-test-config -n "$TEST_NAMESPACE" >/dev/null 2>&1; then
        error "ConfigMap was not created by Kustomization"
        return 1
    fi
    
    # Verify ConfigMap content
    local config_value
    config_value=$(kubectl get configmap fluxcd-test-config -n "$TEST_NAMESPACE" -o jsonpath='{.data.test-key}')
    
    if [[ "$config_value" != "test-value" ]]; then
        error "ConfigMap content is incorrect. Expected: test-value, Got: $config_value"
        return 1
    fi
    
    success "Kustomization reconciliation test passed"
    return 0
}

# Test 4: Git Drift Detection
test_git_drift_detection() {
    log "Testing Git drift detection..."
    
    # Modify the ConfigMap directly in the cluster
    kubectl patch configmap fluxcd-test-config -n "$TEST_NAMESPACE" --patch '{"data":{"test-key":"modified-value"}}'
    
    # Wait for reconciliation to detect and fix the drift
    local max_attempts=10
    local attempt=0
    
    while [[ $attempt -lt $max_attempts ]]; do
        sleep 10
        local current_value
        current_value=$(kubectl get configmap fluxcd-test-config -n "$TEST_NAMESPACE" -o jsonpath='{.data.test-key}')
        
        if [[ "$current_value" == "test-value" ]]; then
            success "Git drift was detected and corrected"
            return 0
        fi
        
        ((attempt++))
        log "Waiting for drift correction... (attempt $attempt/$max_attempts)"
    done
    
    error "Git drift was not corrected within expected time"
    return 1
}

# Test 5: FluxCD Controller Failure Recovery
test_controller_failure_recovery() {
    log "Testing FluxCD controller failure recovery..."
    
    # Scale down source-controller
    kubectl scale deployment source-controller --replicas=0 -n flux-system
    
    # Wait for controller to be unavailable
    sleep 10
    
    # Scale back up
    kubectl scale deployment source-controller --replicas=1 -n flux-system
    
    # Wait for controller to be ready
    if ! kubectl wait --for=condition=available --timeout=120s deployment/source-controller -n flux-system; then
        error "Source controller failed to recover"
        return 1
    fi
    
    # Verify GitRepository is still functional
    if ! kubectl wait --for=condition=ready --timeout=60s gitrepository/fluxcd-test-repo -n "$TEST_NAMESPACE"; then
        error "GitRepository failed to recover after controller restart"
        return 1
    fi
    
    success "FluxCD controller failure recovery test passed"
    return 0
}

# Test 6: Resource Validation and Health Checks
test_resource_validation() {
    log "Testing resource validation and health checks..."
    
    # Create an invalid resource to test validation
    cat > /tmp/invalid-resource.yaml << EOF
apiVersion: v1
kind: ConfigMap
metadata:
  name: invalid-config
  namespace: $TEST_NAMESPACE
data:
  # This should cause validation issues
  invalid-key: |
    This is a very long value that might cause issues
    $(printf 'x%.0s' {1..10000})
EOF
    
    # Update Git repository with invalid resource
    local test_repo_dir="/tmp/fluxcd-test-repo-$(ls -t /tmp/ | grep fluxcd-test-repo | head -1 | cut -d'-' -f4-)"
    cp /tmp/invalid-resource.yaml "$test_repo_dir/manifests/"
    
    cd "$test_repo_dir"
    git add .
    git commit -m "Add invalid resource for testing"
    
    # Wait and check if Kustomization handles the invalid resource appropriately
    sleep 30
    
    # Check Kustomization status
    local kustomization_status
    kustomization_status=$(kubectl get kustomization fluxcd-test-kustomization -n "$TEST_NAMESPACE" -o jsonpath='{.status.conditions[?(@.type=="Ready")].status}')
    
    # The Kustomization should either handle it gracefully or report an error
    if [[ "$kustomization_status" == "Unknown" ]]; then
        warning "Kustomization is in Unknown state, which may be expected for invalid resources"
    fi
    
    # Remove the invalid resource
    rm "$test_repo_dir/manifests/invalid-resource.yaml"
    cd "$test_repo_dir"
    git add .
    git commit -m "Remove invalid resource"
    
    # Wait for recovery
    if ! kubectl wait --for=condition=ready --timeout=120s kustomization/fluxcd-test-kustomization -n "$TEST_NAMESPACE"; then
        error "Kustomization failed to recover from invalid resource"
        return 1
    fi
    
    success "Resource validation test passed"
    return 0
}

# Test 7: Metrics and Monitoring Integration
test_metrics_monitoring() {
    log "Testing metrics and monitoring integration..."
    
    # Check if FluxCD metrics are being exposed
    local controllers=("source-controller" "kustomize-controller" "helm-controller")
    
    for controller in "${controllers[@]}"; do
        # Get controller pod
        local pod_name
        pod_name=$(kubectl get pods -n flux-system -l app="$controller" -o jsonpath='{.items[0].metadata.name}')
        
        if [[ -z "$pod_name" ]]; then
            error "No pod found for controller: $controller"
            return 1
        fi
        
        # Check if metrics endpoint is accessible
        if ! kubectl exec -n flux-system "$pod_name" -- wget -q -O- http://localhost:8080/metrics | grep -q "gotk_"; then
            error "Metrics not accessible for controller: $controller"
            return 1
        fi
    done
    
    # Check if ServiceMonitor exists
    if ! kubectl get servicemonitor flux-system -n flux-system >/dev/null 2>&1; then
        warning "ServiceMonitor for FluxCD not found"
    fi
    
    success "Metrics and monitoring integration test passed"
    return 0
}

# Test 8: Performance and Resource Usage
test_performance_resource_usage() {
    log "Testing performance and resource usage..."
    
    # Get resource usage for FluxCD controllers
    local controllers=("source-controller" "kustomize-controller" "helm-controller")
    
    for controller in "${controllers[@]}"; do
        local pod_name
        pod_name=$(kubectl get pods -n flux-system -l app="$controller" -o jsonpath='{.items[0].metadata.name}')
        
        if [[ -z "$pod_name" ]]; then
            error "No pod found for controller: $controller"
            return 1
        fi
        
        # Get resource usage
        local cpu_usage memory_usage
        cpu_usage=$(kubectl top pod "$pod_name" -n flux-system --no-headers | awk '{print $2}')
        memory_usage=$(kubectl top pod "$pod_name" -n flux-system --no-headers | awk '{print $3}')
        
        log "Controller $controller - CPU: $cpu_usage, Memory: $memory_usage"
        
        # Check if resource usage is within reasonable limits
        # Convert memory to MB for comparison
        local memory_mb
        if [[ "$memory_usage" =~ ([0-9]+)Mi ]]; then
            memory_mb=${BASH_REMATCH[1]}
        elif [[ "$memory_usage" =~ ([0-9]+)Gi ]]; then
            memory_mb=$((${BASH_REMATCH[1]} * 1024))
        else
            memory_mb=0
        fi
        
        # Alert if memory usage is too high (>500MB)
        if [[ $memory_mb -gt 500 ]]; then
            warning "High memory usage for $controller: ${memory_usage}"
        fi
    done
    
    success "Performance and resource usage test passed"
    return 0
}

# Main test execution
main() {
    log "Starting FluxCD Deployment Test Suite"
    log "Log file: $LOG_FILE"
    
    # Check prerequisites
    if ! check_prerequisites; then
        error "Prerequisites check failed"
        exit 1
    fi
    
    # Run all tests
    run_test "FluxCD Installation and Bootstrap" test_fluxcd_installation
    run_test "Git Repository Synchronization" test_git_repository_sync
    run_test "Kustomization Reconciliation" test_kustomization_reconciliation
    run_test "Git Drift Detection" test_git_drift_detection
    run_test "Controller Failure Recovery" test_controller_failure_recovery
    run_test "Resource Validation" test_resource_validation
    run_test "Metrics and Monitoring Integration" test_metrics_monitoring
    run_test "Performance and Resource Usage" test_performance_resource_usage
    
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

