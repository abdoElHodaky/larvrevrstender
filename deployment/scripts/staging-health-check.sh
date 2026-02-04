#!/bin/bash

# Staging Environment Health Check Script
# Performs comprehensive health checks for staging environment

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENVIRONMENT="staging"
NAMESPACE="reversetender-staging"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log() {
    echo -e "${BLUE}[STAGING-HEALTH] $1${NC}"
}

success() {
    echo -e "${GREEN}[HEALTHY] $1${NC}"
}

warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}"
}

# Main health check function
main() {
    log "Starting staging environment health check..."
    
    # Call the generic health check script with staging parameters
    if [ -f "$SCRIPT_DIR/health-check.sh" ]; then
        log "Running comprehensive health check for staging..."
        
        # Set environment variables for the generic health check
        export ENVIRONMENT="staging"
        export NAMESPACE="reversetender-staging"
        export HEALTH_CHECK_TIMEOUT="30s"
        export HEALTH_CHECK_RETRIES="3"
        
        # Run the generic health check
        bash "$SCRIPT_DIR/health-check.sh" --environment=staging --namespace="$NAMESPACE"
        
        if [ $? -eq 0 ]; then
            success "Staging environment health check completed successfully"
        else
            error "Staging environment health check failed"
            exit 1
        fi
    else
        error "Generic health-check.sh script not found"
        exit 1
    fi
    
    # Additional staging-specific checks
    log "Running staging-specific health checks..."
    
    # Check if kubectl is available and configured
    if command -v kubectl >/dev/null 2>&1; then
        log "Checking Kubernetes cluster connectivity..."
        
        # Check if we can connect to the cluster
        if kubectl cluster-info >/dev/null 2>&1; then
            success "Kubernetes cluster connectivity verified"
            
            # Check namespace exists
            if kubectl get namespace "$NAMESPACE" >/dev/null 2>&1; then
                success "Namespace '$NAMESPACE' exists"
                
                # Check pod status
                log "Checking pod status in staging namespace..."
                kubectl get pods -n "$NAMESPACE" --no-headers | while read line; do
                    pod_name=$(echo $line | awk '{print $1}')
                    pod_status=$(echo $line | awk '{print $3}')
                    
                    if [ "$pod_status" = "Running" ]; then
                        success "Pod $pod_name is running"
                    else
                        warning "Pod $pod_name status: $pod_status"
                    fi
                done
                
            else
                error "Namespace '$NAMESPACE' not found"
                exit 1
            fi
        else
            error "Cannot connect to Kubernetes cluster"
            exit 1
        fi
    else
        warning "kubectl not available - skipping Kubernetes checks"
    fi
    
    log "Staging health check completed"
}

# Run main function
main "$@"

