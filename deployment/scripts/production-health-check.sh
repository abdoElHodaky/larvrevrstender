#!/bin/bash

# Production Environment Health Check Script
# Performs comprehensive health checks for production environment

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENVIRONMENT="production"
NAMESPACE="reversetender-prod"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log() {
    echo -e "${BLUE}[PRODUCTION-HEALTH] $1${NC}"
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
    log "Starting production environment health check..."
    
    # Call the generic health check script with production parameters
    if [ -f "$SCRIPT_DIR/health-check.sh" ]; then
        log "Running comprehensive health check for production..."
        
        # Set environment variables for the generic health check
        export ENVIRONMENT="production"
        export NAMESPACE="reversetender-prod"
        export HEALTH_CHECK_TIMEOUT="30s"
        export HEALTH_CHECK_RETRIES="5"  # More retries for production
        
        # Run the generic health check
        bash "$SCRIPT_DIR/health-check.sh" --environment=production --namespace="$NAMESPACE"
        
        if [ $? -eq 0 ]; then
            success "Production environment health check completed successfully"
        else
            error "Production environment health check failed"
            exit 1
        fi
    else
        error "Generic health-check.sh script not found"
        exit 1
    fi
    
    # Additional production-specific checks
    log "Running production-specific health checks..."
    
    # Check if kubectl is available and configured
    if command -v kubectl >/dev/null 2>&1; then
        log "Checking Kubernetes cluster connectivity..."
        
        # Check if we can connect to the cluster
        if kubectl cluster-info >/dev/null 2>&1; then
            success "Kubernetes cluster connectivity verified"
            
            # Check namespace exists
            if kubectl get namespace "$NAMESPACE" >/dev/null 2>&1; then
                success "Namespace '$NAMESPACE' exists"
                
                # Check pod status with stricter requirements for production
                log "Checking pod status in production namespace..."
                failed_pods=0
                kubectl get pods -n "$NAMESPACE" --no-headers | while read line; do
                    pod_name=$(echo $line | awk '{print $1}')
                    pod_status=$(echo $line | awk '{print $3}')
                    pod_ready=$(echo $line | awk '{print $2}')
                    
                    if [ "$pod_status" = "Running" ]; then
                        # Check if pod is ready (all containers ready)
                        ready_count=$(echo $pod_ready | cut -d'/' -f1)
                        total_count=$(echo $pod_ready | cut -d'/' -f2)
                        
                        if [ "$ready_count" = "$total_count" ]; then
                            success "Pod $pod_name is running and ready ($pod_ready)"
                        else
                            warning "Pod $pod_name is running but not fully ready ($pod_ready)"
                            ((failed_pods++))
                        fi
                    else
                        error "Pod $pod_name status: $pod_status"
                        ((failed_pods++))
                    fi
                done
                
                # Check for any failed pods
                if [ $failed_pods -gt 0 ]; then
                    error "$failed_pods pods are not healthy in production"
                    exit 1
                fi
                
                # Check HPA status
                log "Checking Horizontal Pod Autoscaler status..."
                if kubectl get hpa -n "$NAMESPACE" >/dev/null 2>&1; then
                    success "HPA resources found in production"
                else
                    warning "No HPA resources found in production"
                fi
                
                # Check service status
                log "Checking service endpoints..."
                kubectl get services -n "$NAMESPACE" --no-headers | while read line; do
                    service_name=$(echo $line | awk '{print $1}')
                    service_type=$(echo $line | awk '{print $2}')
                    
                    if [ "$service_type" != "ClusterIP" ] && [ "$service_type" != "LoadBalancer" ]; then
                        continue
                    fi
                    
                    # Check if service has endpoints
                    if kubectl get endpoints "$service_name" -n "$NAMESPACE" >/dev/null 2>&1; then
                        endpoint_count=$(kubectl get endpoints "$service_name" -n "$NAMESPACE" -o jsonpath='{.subsets[*].addresses[*].ip}' | wc -w)
                        if [ $endpoint_count -gt 0 ]; then
                            success "Service $service_name has $endpoint_count endpoints"
                        else
                            warning "Service $service_name has no endpoints"
                        fi
                    else
                        warning "No endpoints found for service $service_name"
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
    
    # Production-specific monitoring checks
    log "Checking production monitoring systems..."
    
    # Check if monitoring namespace exists
    if kubectl get namespace monitoring >/dev/null 2>&1; then
        success "Monitoring namespace exists"
        
        # Check Prometheus
        if kubectl get pods -n monitoring -l app=prometheus --no-headers | grep -q Running; then
            success "Prometheus is running"
        else
            warning "Prometheus may not be running properly"
        fi
        
        # Check Grafana
        if kubectl get pods -n monitoring -l app=grafana --no-headers | grep -q Running; then
            success "Grafana is running"
        else
            warning "Grafana may not be running properly"
        fi
    else
        warning "Monitoring namespace not found"
    fi
    
    log "Production health check completed"
}

# Run main function
main "$@"

