#!/bin/bash

# Pre-flight Checks for Production Deployment
# Validates production readiness before blue-green deployment
# Part of Phase 1 Week 3: Kubernetes Cluster Validation

set -euo pipefail

# Configuration
LOG_FILE="/tmp/pre-flight-checks-$(date +%Y%m%d-%H%M%S).log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
PURPLE='\033[0;35m'
BOLD='\033[1m'
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

header() {
    echo -e "${BOLD}${PURPLE}$1${NC}" | tee -a "$LOG_FILE"
}

# Test result tracking
CHECKS_PASSED=0
CHECKS_FAILED=0
FAILED_CHECKS=()

# Function to run a check and track results
run_check() {
    local check_name="$1"
    local check_function="$2"
    
    log "Running check: $check_name"
    
    if $check_function; then
        success "✅ PASSED: $check_name"
        ((CHECKS_PASSED++))
    else
        error "❌ FAILED: $check_name"
        FAILED_CHECKS+=("$check_name")
        ((CHECKS_FAILED++))
    fi
    
    echo "" | tee -a "$LOG_FILE"
}

# Check cluster connectivity
check_cluster_connectivity() {
    if kubectl cluster-info &>/dev/null; then
        success "Cluster is accessible"
        return 0
    else
        error "Cannot access cluster"
        return 1
    fi
}

# Check required namespaces
check_required_namespaces() {
    local namespaces=("reverse-tender-blue" "reverse-tender-green" "flux-system")
    local missing_namespaces=()
    
    for namespace in "${namespaces[@]}"; do
        if kubectl get namespace "$namespace" &>/dev/null; then
            success "Namespace '$namespace' exists"
        else
            error "Namespace '$namespace' missing"
            missing_namespaces+=("$namespace")
        fi
    done
    
    return $([[ ${#missing_namespaces[@]} -eq 0 ]] && echo 0 || echo 1)
}

# Check FluxCD installation
check_fluxcd_installation() {
    if command -v flux &>/dev/null && flux check &>/dev/null; then
        success "FluxCD is installed and working"
        return 0
    else
        error "FluxCD is not working properly"
        return 1
    fi
}

# Check ingress controller
check_ingress_controller() {
    local ingress_pods
    ingress_pods=$(kubectl get pods -n ingress-nginx --no-headers 2>/dev/null | grep -c " Running " || echo "0")
    
    if [[ $ingress_pods -gt 0 ]]; then
        success "Ingress controller is running ($ingress_pods pods)"
        return 0
    else
        error "Ingress controller is not running"
        return 1
    fi
}

# Check resource availability
check_resource_availability() {
    local nodes_ready
    nodes_ready=$(kubectl get nodes --no-headers | grep -c " Ready " || echo "0")
    
    if [[ $nodes_ready -gt 0 ]]; then
        success "$nodes_ready nodes are ready"
        return 0
    else
        error "No nodes are ready"
        return 1
    fi
}

# Main execution
main() {
    header "🚀 Pre-flight Checks for Production Deployment"
    log "Logging to: $LOG_FILE"
    echo ""
    
    # Run all checks
    run_check "Cluster Connectivity" check_cluster_connectivity
    run_check "Required Namespaces" check_required_namespaces
    run_check "FluxCD Installation" check_fluxcd_installation
    run_check "Ingress Controller" check_ingress_controller
    run_check "Resource Availability" check_resource_availability
    
    # Final summary
    header "🏁 Pre-flight Checks Complete"
    echo "=================================="
    success "Checks Passed: $CHECKS_PASSED"
    
    if [[ $CHECKS_FAILED -gt 0 ]]; then
        error "Checks Failed: $CHECKS_FAILED"
        error "Failed checks:"
        for check in "${FAILED_CHECKS[@]}"; do
            error "  - $check"
        done
        echo ""
        error "❌ Pre-flight checks FAILED - not ready for production"
        exit 1
    else
        echo ""
        success "✅ All pre-flight checks PASSED - ready for production deployment"
        exit 0
    fi
}

# Run main function
main "$@"
