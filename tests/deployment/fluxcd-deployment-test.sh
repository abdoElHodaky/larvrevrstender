#!/bin/bash

# FluxCD Deployment Test Suite
# Tests FluxCD controller health, Git synchronization, and Kustomization reconciliation
# Part of Phase 1: Comprehensive Testing Framework

set -euo pipefail

# Configuration
NAMESPACE="flux-system"
TIMEOUT=300
TEST_REPO_URL="https://github.com/abdoElHodaky/larvrevrstender.git"
TEST_BRANCH="v2-blue-green-deploy"
LOG_FILE="/tmp/fluxcd-test-$(date +%Y%m%d-%H%M%S).log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
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

# Test 1: FluxCD Controller Health Check
test_fluxcd_controllers() {
    log "Testing FluxCD controller health..."
    
    # Check if namespace exists
    if ! kubectl get namespace "$NAMESPACE" &>/dev/null; then
        error "FluxCD namespace '$NAMESPACE' does not exist"
        return 1
    fi
    
    # Required controllers
    local controllers=(
        "source-controller"
        "kustomize-controller"
        "helm-controller"
        "notification-controller"
    )
    
    local all_healthy=true
    
    for controller in "${controllers[@]}"; do
        log "Checking $controller..."
        
        # Check if deployment exists
        if ! kubectl get deployment "$controller" -n "$NAMESPACE" &>/dev/null; then
            error "$controller deployment not found"
            all_healthy=false
            continue
        fi
        
        # Check if deployment is ready
        local ready_replicas
        ready_replicas=$(kubectl get deployment "$controller" -n "$NAMESPACE" -o jsonpath='{.status.readyReplicas}' 2>/dev/null || echo "0")
        local desired_replicas
        desired_replicas=$(kubectl get deployment "$controller" -n "$NAMESPACE" -o jsonpath='{.spec.replicas}' 2>/dev/null || echo "1")
        
        if [[ "$ready_replicas" != "$desired_replicas" ]]; then
            error "$controller is not ready ($ready_replicas/$desired_replicas replicas)"
            all_healthy=false
        else
            success "$controller is healthy ($ready_replicas/$desired_replicas replicas)"
        fi
        
        # Check pod status
        local pod_status
        pod_status=$(kubectl get pods -n "$NAMESPACE" -l app="$controller" -o jsonpath='{.items[0].status.phase}' 2>/dev/null || echo "Unknown")
        
        if [[ "$pod_status" != "Running" ]]; then
            error "$controller pod status: $pod_status"
            all_healthy=false
        fi
    done
    
    return $([[ "$all_healthy" == "true" ]] && echo 0 || echo 1)
}

# Test 2: Git Repository Synchronization
test_git_synchronization() {
    log "Testing Git repository synchronization..."
    
    # Check if GitRepository resource exists
    local git_repos
    git_repos=$(kubectl get gitrepository -n "$NAMESPACE" -o name 2>/dev/null || echo "")
    
    if [[ -z "$git_repos" ]]; then
        error "No GitRepository resources found"
        return 1
    fi
    
    local all_synced=true
    
    while IFS= read -r repo; do
        if [[ -n "$repo" ]]; then
            local repo_name
            repo_name=$(echo "$repo" | cut -d'/' -f2)
            log "Checking GitRepository: $repo_name"
            
            # Check repository status
            local ready_condition
            ready_condition=$(kubectl get "$repo" -n "$NAMESPACE" -o jsonpath='{.status.conditions[?(@.type=="Ready")].status}' 2>/dev/null || echo "Unknown")
            
            if [[ "$ready_condition" != "True" ]]; then
                error "GitRepository $repo_name is not ready"
                
                # Get detailed status
                local message
                message=$(kubectl get "$repo" -n "$NAMESPACE" -o jsonpath='{.status.conditions[?(@.type=="Ready")].message}' 2>/dev/null || echo "No message")
                error "Status message: $message"
                
                all_synced=false
            else
                success "GitRepository $repo_name is synchronized"
                
                # Check last sync time
                local last_sync
                last_sync=$(kubectl get "$repo" -n "$NAMESPACE" -o jsonpath='{.status.artifact.lastUpdateTime}' 2>/dev/null || echo "Unknown")
                log "Last sync: $last_sync"
            fi
        fi
    done <<< "$git_repos"
    
    return $([[ "$all_synced" == "true" ]] && echo 0 || echo 1)
}

# Test 3: Kustomization Reconciliation
test_kustomization_reconciliation() {
    log "Testing Kustomization reconciliation..."
    
    # Check if Kustomization resources exist
    local kustomizations
    kustomizations=$(kubectl get kustomization -n "$NAMESPACE" -o name 2>/dev/null || echo "")
    
    if [[ -z "$kustomizations" ]]; then
        error "No Kustomization resources found"
        return 1
    fi
    
    local all_reconciled=true
    
    while IFS= read -r kustomization; do
        if [[ -n "$kustomization" ]]; then
            local kust_name
            kust_name=$(echo "$kustomization" | cut -d'/' -f2)
            log "Checking Kustomization: $kust_name"
            
            # Check kustomization status
            local ready_condition
            ready_condition=$(kubectl get "$kustomization" -n "$NAMESPACE" -o jsonpath='{.status.conditions[?(@.type=="Ready")].status}' 2>/dev/null || echo "Unknown")
            
            if [[ "$ready_condition" != "True" ]]; then
                error "Kustomization $kust_name is not ready"
                
                # Get detailed status
                local message
                message=$(kubectl get "$kustomization" -n "$NAMESPACE" -o jsonpath='{.status.conditions[?(@.type=="Ready")].message}' 2>/dev/null || echo "No message")
                error "Status message: $message"
                
                all_reconciled=false
            else
                success "Kustomization $kust_name is reconciled"
                
                # Check last reconciliation time
                local last_reconcile
                last_reconcile=$(kubectl get "$kustomization" -n "$NAMESPACE" -o jsonpath='{.status.lastAppliedRevision}' 2>/dev/null || echo "Unknown")
                log "Last applied revision: $last_reconcile"
            fi
        fi
    done <<< "$kustomizations"
    
    return $([[ "$all_reconciled" == "true" ]] && echo 0 || echo 1)
}

# Test 4: Git Drift Detection
test_git_drift_detection() {
    log "Testing Git drift detection..."
    
    # Create a temporary change to test drift detection
    local test_configmap="fluxcd-drift-test"
    
    # Create test ConfigMap
    kubectl create configmap "$test_configmap" -n "$NAMESPACE" --from-literal=test=drift-detection --dry-run=client -o yaml | kubectl apply -f - &>/dev/null
    
    if ! kubectl get configmap "$test_configmap" -n "$NAMESPACE" &>/dev/null; then
        error "Failed to create test ConfigMap for drift detection"
        return 1
    fi
    
    # Modify the ConfigMap to simulate drift
    kubectl patch configmap "$test_configmap" -n "$NAMESPACE" --type merge -p '{"data":{"test":"modified-value"}}' &>/dev/null
    
    # Wait for FluxCD to detect and correct the drift
    local drift_corrected=false
    local attempts=0
    local max_attempts=30
    
    while [[ $attempts -lt $max_attempts ]]; do
        local current_value
        current_value=$(kubectl get configmap "$test_configmap" -n "$NAMESPACE" -o jsonpath='{.data.test}' 2>/dev/null || echo "")
        
        if [[ "$current_value" == "drift-detection" ]]; then
            drift_corrected=true
            break
        fi
        
        sleep 2
        ((attempts++))
    done
    
    # Cleanup
    kubectl delete configmap "$test_configmap" -n "$NAMESPACE" &>/dev/null || true
    
    if [[ "$drift_corrected" == "true" ]]; then
        success "Git drift detection and correction working (corrected in ${attempts}0 seconds)"
        return 0
    else
        error "Git drift detection failed - drift not corrected after $((max_attempts * 2)) seconds"
        return 1
    fi
}

# Test 5: Controller Failure Recovery
test_controller_failure_recovery() {
    log "Testing FluxCD controller failure recovery..."
    
    # Test source-controller recovery
    local controller="source-controller"
    
    log "Testing $controller failure recovery..."
    
    # Get current pod name
    local pod_name
    pod_name=$(kubectl get pods -n "$NAMESPACE" -l app="$controller" -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
    
    if [[ -z "$pod_name" ]]; then
        error "Could not find $controller pod"
        return 1
    fi
    
    # Delete the pod to simulate failure
    kubectl delete pod "$pod_name" -n "$NAMESPACE" &>/dev/null
    
    # Wait for pod to be recreated and become ready
    local recovery_successful=false
    local attempts=0
    local max_attempts=60
    
    while [[ $attempts -lt $max_attempts ]]; do
        local ready_replicas
        ready_replicas=$(kubectl get deployment "$controller" -n "$NAMESPACE" -o jsonpath='{.status.readyReplicas}' 2>/dev/null || echo "0")
        local desired_replicas
        desired_replicas=$(kubectl get deployment "$controller" -n "$NAMESPACE" -o jsonpath='{.spec.replicas}' 2>/dev/null || echo "1")
        
        if [[ "$ready_replicas" == "$desired_replicas" ]]; then
            # Check if it's a new pod
            local new_pod_name
            new_pod_name=$(kubectl get pods -n "$NAMESPACE" -l app="$controller" -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
            
            if [[ "$new_pod_name" != "$pod_name" ]]; then
                recovery_successful=true
                break
            fi
        fi
        
        sleep 5
        ((attempts++))
    done
    
    if [[ "$recovery_successful" == "true" ]]; then
        success "$controller recovered successfully (took ${attempts}0 seconds)"
        return 0
    else
        error "$controller failed to recover after $((max_attempts * 5)) seconds"
        return 1
    fi
}

# Test 6: Resource Validation
test_resource_validation() {
    log "Testing FluxCD resource validation..."
    
    # Check for required CRDs
    local required_crds=(
        "gitrepositories.source.toolkit.fluxcd.io"
        "kustomizations.kustomize.toolkit.fluxcd.io"
        "helmrepositories.source.toolkit.fluxcd.io"
        "helmreleases.helm.toolkit.fluxcd.io"
    )
    
    local all_crds_present=true
    
    for crd in "${required_crds[@]}"; do
        if kubectl get crd "$crd" &>/dev/null; then
            success "CRD $crd is present"
        else
            error "CRD $crd is missing"
            all_crds_present=false
        fi
    done
    
    return $([[ "$all_crds_present" == "true" ]] && echo 0 || echo 1)
}

# Main test execution
main() {
    log "Starting FluxCD Deployment Test Suite"
    log "Logging to: $LOG_FILE"
    log "Namespace: $NAMESPACE"
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
    run_test "FluxCD Controller Health Check" test_fluxcd_controllers
    run_test "Git Repository Synchronization" test_git_synchronization
    run_test "Kustomization Reconciliation" test_kustomization_reconciliation
    run_test "Git Drift Detection" test_git_drift_detection
    run_test "Controller Failure Recovery" test_controller_failure_recovery
    run_test "Resource Validation" test_resource_validation
    
    # Print summary
    echo "=================================="
    log "FluxCD Deployment Test Summary"
    echo "=================================="
    success "Tests Passed: $TESTS_PASSED"
    
    if [[ $TESTS_FAILED -gt 0 ]]; then
        error "Tests Failed: $TESTS_FAILED"
        error "Failed tests:"
        for test in "${FAILED_TESTS[@]}"; do
            error "  - $test"
        done
        echo ""
        error "❌ FluxCD deployment tests FAILED"
        exit 1
    else
        echo ""
        success "✅ All FluxCD deployment tests PASSED"
        exit 0
    fi
}

# Run main function
main "$@"

