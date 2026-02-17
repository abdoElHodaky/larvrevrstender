#!/bin/bash

# Multi-Cloud Gateway API Deployment Script for Reverse Tender Platform
# Supports GCP, Azure, DigitalOcean, Linode, and OpenStack

set -euo pipefail

# Script configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOYMENT_DIR="$(dirname "$SCRIPT_DIR")"
PROJECT_ROOT="$(dirname "$DEPLOYMENT_DIR")"

# Default values
CLOUD_PROVIDER=""
ENVIRONMENT="production"
NAMESPACE="reverse-tender"
DRY_RUN=false
VERBOSE=false
VALIDATE_ONLY=false
FORCE_DEPLOY=false

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Usage function
usage() {
    cat << EOF
Multi-Cloud Gateway API Deployment Script

USAGE:
    $0 [OPTIONS]

OPTIONS:
    -p, --provider PROVIDER     Cloud provider (gcp|azure|digitalocean|linode|openstack)
    -e, --environment ENV       Environment (dev|staging|production) [default: production]
    -n, --namespace NAMESPACE   Kubernetes namespace [default: reverse-tender]
    -d, --dry-run              Show what would be deployed without applying
    -v, --verbose              Enable verbose output
    --validate-only            Only validate configurations without deploying
    --force                    Force deployment even if validation fails
    -h, --help                 Show this help message

EXAMPLES:
    # Deploy to GCP production
    $0 --provider gcp --environment production

    # Dry run for Azure staging
    $0 --provider azure --environment staging --dry-run

    # Validate DigitalOcean configuration
    $0 --provider digitalocean --validate-only

    # Deploy to Linode with verbose output
    $0 --provider linode --verbose

SUPPORTED CLOUD PROVIDERS:
    gcp           Google Cloud Platform (GKE)
    azure         Microsoft Azure (AKS)
    digitalocean  DigitalOcean (DOKS)
    linode        Linode (LKE)
    openstack     OpenStack

EOF
}

# Parse command line arguments
parse_args() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            -p|--provider)
                CLOUD_PROVIDER="$2"
                shift 2
                ;;
            -e|--environment)
                ENVIRONMENT="$2"
                shift 2
                ;;
            -n|--namespace)
                NAMESPACE="$2"
                shift 2
                ;;
            -d|--dry-run)
                DRY_RUN=true
                shift
                ;;
            -v|--verbose)
                VERBOSE=true
                shift
                ;;
            --validate-only)
                VALIDATE_ONLY=true
                shift
                ;;
            --force)
                FORCE_DEPLOY=true
                shift
                ;;
            -h|--help)
                usage
                exit 0
                ;;
            *)
                log_error "Unknown option: $1"
                usage
                exit 1
                ;;
        esac
    done
}

# Validate prerequisites
validate_prerequisites() {
    log_info "Validating prerequisites..."
    
    # Check required tools
    local required_tools=("kubectl" "kustomize")
    for tool in "${required_tools[@]}"; do
        if ! command -v "$tool" &> /dev/null; then
            log_error "$tool is required but not installed"
            exit 1
        fi
    done
    
    # Check cloud provider specific tools
    case "$CLOUD_PROVIDER" in
        gcp)
            if ! command -v gcloud &> /dev/null; then
                log_error "gcloud CLI is required for GCP deployment"
                exit 1
            fi
            ;;
        azure)
            if ! command -v az &> /dev/null; then
                log_error "Azure CLI is required for Azure deployment"
                exit 1
            fi
            ;;
        digitalocean)
            if ! command -v doctl &> /dev/null; then
                log_warning "doctl CLI is recommended for DigitalOcean deployment"
            fi
            ;;
        linode)
            if ! command -v linode-cli &> /dev/null; then
                log_warning "linode-cli is recommended for Linode deployment"
            fi
            ;;
        openstack)
            if ! command -v openstack &> /dev/null; then
                log_error "OpenStack CLI is required for OpenStack deployment"
                exit 1
            fi
            ;;
    esac
    
    # Check Kubernetes connectivity
    if ! kubectl cluster-info &> /dev/null; then
        log_error "Cannot connect to Kubernetes cluster"
        exit 1
    fi
    
    log_success "Prerequisites validation passed"
}

# Detect cloud provider automatically
detect_cloud_provider() {
    if [[ -n "$CLOUD_PROVIDER" ]]; then
        return 0
    fi
    
    log_info "Auto-detecting cloud provider..."
    
    # Try to detect based on Kubernetes context
    local context=$(kubectl config current-context 2>/dev/null || echo "")
    
    if [[ "$context" == *"gke"* ]]; then
        CLOUD_PROVIDER="gcp"
        log_info "Detected Google Cloud Platform (GKE)"
    elif [[ "$context" == *"aks"* ]]; then
        CLOUD_PROVIDER="azure"
        log_info "Detected Microsoft Azure (AKS)"
    elif [[ "$context" == *"do-"* ]] || [[ "$context" == *"digitalocean"* ]]; then
        CLOUD_PROVIDER="digitalocean"
        log_info "Detected DigitalOcean (DOKS)"
    elif [[ "$context" == *"lke"* ]] || [[ "$context" == *"linode"* ]]; then
        CLOUD_PROVIDER="linode"
        log_info "Detected Linode (LKE)"
    else
        # Try to detect based on node labels
        local node_labels=$(kubectl get nodes -o jsonpath='{.items[0].metadata.labels}' 2>/dev/null || echo "")
        
        if [[ "$node_labels" == *"cloud.google.com"* ]]; then
            CLOUD_PROVIDER="gcp"
            log_info "Detected Google Cloud Platform (GKE) from node labels"
        elif [[ "$node_labels" == *"kubernetes.azure.com"* ]]; then
            CLOUD_PROVIDER="azure"
            log_info "Detected Microsoft Azure (AKS) from node labels"
        elif [[ "$node_labels" == *"digitalocean.com"* ]]; then
            CLOUD_PROVIDER="digitalocean"
            log_info "Detected DigitalOcean (DOKS) from node labels"
        elif [[ "$node_labels" == *"linode.com"* ]]; then
            CLOUD_PROVIDER="linode"
            log_info "Detected Linode (LKE) from node labels"
        else
            CLOUD_PROVIDER="openstack"
            log_warning "Could not auto-detect cloud provider, defaulting to OpenStack"
        fi
    fi
}

# Validate cloud provider
validate_cloud_provider() {
    local valid_providers=("gcp" "azure" "digitalocean" "linode" "openstack")
    
    if [[ -z "$CLOUD_PROVIDER" ]]; then
        log_error "Cloud provider must be specified or auto-detected"
        exit 1
    fi
    
    if [[ ! " ${valid_providers[@]} " =~ " ${CLOUD_PROVIDER} " ]]; then
        log_error "Invalid cloud provider: $CLOUD_PROVIDER"
        log_error "Valid providers: ${valid_providers[*]}"
        exit 1
    fi
    
    log_info "Using cloud provider: $CLOUD_PROVIDER"
}

# Validate Gateway API configuration
validate_gateway_config() {
    log_info "Validating Gateway API configuration for $CLOUD_PROVIDER..."
    
    local config_dir="$DEPLOYMENT_DIR/$CLOUD_PROVIDER/gateway-api"
    
    if [[ ! -d "$config_dir" ]]; then
        log_error "Gateway API configuration not found for $CLOUD_PROVIDER at $config_dir"
        exit 1
    fi
    
    # Check required files
    local required_files=("gatewayclass.yaml" "gateway.yaml")
    for file in "${required_files[@]}"; do
        if [[ ! -f "$config_dir/$file" ]]; then
            log_error "Required file not found: $config_dir/$file"
            exit 1
        fi
    done
    
    # Validate YAML syntax
    for yaml_file in "$config_dir"/*.yaml; do
        if [[ -f "$yaml_file" ]]; then
            if ! kubectl apply --dry-run=client -f "$yaml_file" &> /dev/null; then
                log_error "Invalid YAML syntax in $yaml_file"
                if [[ "$FORCE_DEPLOY" != true ]]; then
                    exit 1
                fi
            fi
        fi
    done
    
    log_success "Gateway API configuration validation passed"
}

# Install Gateway API CRDs
install_gateway_api_crds() {
    log_info "Installing Gateway API CRDs..."
    
    # Check if Gateway API CRDs are already installed
    if kubectl get crd gateways.gateway.networking.k8s.io &> /dev/null; then
        log_info "Gateway API CRDs already installed"
        return 0
    fi
    
    # Install Gateway API CRDs
    local gateway_api_version="v1.0.0"
    local crd_url="https://github.com/kubernetes-sigs/gateway-api/releases/download/${gateway_api_version}/standard-install.yaml"
    
    if [[ "$DRY_RUN" == true ]]; then
        log_info "[DRY RUN] Would install Gateway API CRDs from $crd_url"
        return 0
    fi
    
    if ! kubectl apply -f "$crd_url"; then
        log_error "Failed to install Gateway API CRDs"
        exit 1
    fi
    
    # Wait for CRDs to be established
    log_info "Waiting for Gateway API CRDs to be established..."
    kubectl wait --for condition=established --timeout=60s crd/gateways.gateway.networking.k8s.io
    kubectl wait --for condition=established --timeout=60s crd/gatewayclasses.gateway.networking.k8s.io
    kubectl wait --for condition=established --timeout=60s crd/httproutes.gateway.networking.k8s.io
    
    log_success "Gateway API CRDs installed successfully"
}

# Create namespace if it doesn't exist
create_namespace() {
    log_info "Ensuring namespace $NAMESPACE exists..."
    
    if kubectl get namespace "$NAMESPACE" &> /dev/null; then
        log_info "Namespace $NAMESPACE already exists"
        return 0
    fi
    
    if [[ "$DRY_RUN" == true ]]; then
        log_info "[DRY RUN] Would create namespace $NAMESPACE"
        return 0
    fi
    
    kubectl create namespace "$NAMESPACE"
    log_success "Created namespace $NAMESPACE"
}

# Deploy Gateway API configuration
deploy_gateway_api() {
    log_info "Deploying Gateway API configuration for $CLOUD_PROVIDER..."
    
    local config_dir="$DEPLOYMENT_DIR/$CLOUD_PROVIDER/gateway-api"
    
    # Check if kustomization.yaml exists
    if [[ -f "$config_dir/kustomization.yaml" ]]; then
        log_info "Using kustomize for deployment"
        
        if [[ "$DRY_RUN" == true ]]; then
            log_info "[DRY RUN] Would apply kustomization from $config_dir"
            kustomize build "$config_dir" | kubectl apply --dry-run=client -f -
        else
            kustomize build "$config_dir" | kubectl apply -f -
        fi
    else
        log_info "Applying YAML files directly"
        
        for yaml_file in "$config_dir"/*.yaml; do
            if [[ -f "$yaml_file" ]]; then
                log_info "Applying $yaml_file"
                
                if [[ "$DRY_RUN" == true ]]; then
                    kubectl apply --dry-run=client -f "$yaml_file"
                else
                    kubectl apply -f "$yaml_file"
                fi
            fi
        done
    fi
    
    if [[ "$DRY_RUN" != true ]]; then
        log_success "Gateway API configuration deployed successfully"
    else
        log_success "Dry run completed successfully"
    fi
}

# Wait for Gateway to be ready
wait_for_gateway() {
    if [[ "$DRY_RUN" == true ]] || [[ "$VALIDATE_ONLY" == true ]]; then
        return 0
    fi
    
    log_info "Waiting for Gateway to be ready..."
    
    local gateway_name="reverse-tender-${CLOUD_PROVIDER}-gateway"
    local timeout=300  # 5 minutes
    local elapsed=0
    
    while [[ $elapsed -lt $timeout ]]; do
        local status=$(kubectl get gateway "$gateway_name" -n "$NAMESPACE" -o jsonpath='{.status.conditions[?(@.type=="Programmed")].status}' 2>/dev/null || echo "")
        
        if [[ "$status" == "True" ]]; then
            log_success "Gateway is ready"
            return 0
        fi
        
        log_info "Gateway not ready yet, waiting... (${elapsed}s/${timeout}s)"
        sleep 10
        elapsed=$((elapsed + 10))
    done
    
    log_error "Gateway did not become ready within $timeout seconds"
    kubectl describe gateway "$gateway_name" -n "$NAMESPACE"
    exit 1
}

# Display deployment status
show_deployment_status() {
    if [[ "$DRY_RUN" == true ]] || [[ "$VALIDATE_ONLY" == true ]]; then
        return 0
    fi
    
    log_info "Deployment Status:"
    echo
    
    # Show GatewayClass
    echo "GatewayClass:"
    kubectl get gatewayclass -o wide
    echo
    
    # Show Gateway
    echo "Gateway:"
    kubectl get gateway -n "$NAMESPACE" -o wide
    echo
    
    # Show HTTPRoutes
    echo "HTTPRoutes:"
    kubectl get httproute -n "$NAMESPACE" -o wide
    echo
    
    # Show Services
    echo "Services:"
    kubectl get service -n "$NAMESPACE" -o wide
    echo
    
    # Show Gateway status details
    local gateway_name="reverse-tender-${CLOUD_PROVIDER}-gateway"
    if kubectl get gateway "$gateway_name" -n "$NAMESPACE" &> /dev/null; then
        echo "Gateway Status Details:"
        kubectl describe gateway "$gateway_name" -n "$NAMESPACE"
    fi
}

# Main function
main() {
    log_info "Starting Multi-Cloud Gateway API Deployment"
    log_info "Script: $0"
    log_info "Arguments: $*"
    echo
    
    # Parse arguments
    parse_args "$@"
    
    # Auto-detect cloud provider if not specified
    detect_cloud_provider
    
    # Validate inputs
    validate_cloud_provider
    validate_prerequisites
    
    # Validate configuration
    validate_gateway_config
    
    if [[ "$VALIDATE_ONLY" == true ]]; then
        log_success "Configuration validation completed successfully"
        exit 0
    fi
    
    # Install Gateway API CRDs
    install_gateway_api_crds
    
    # Create namespace
    create_namespace
    
    # Deploy Gateway API
    deploy_gateway_api
    
    # Wait for Gateway to be ready
    wait_for_gateway
    
    # Show deployment status
    show_deployment_status
    
    log_success "Multi-Cloud Gateway API deployment completed successfully!"
    
    if [[ "$DRY_RUN" != true ]]; then
        echo
        log_info "Next steps:"
        echo "1. Verify Gateway API resources are working correctly"
        echo "2. Update DNS records to point to the load balancer IP"
        echo "3. Test API endpoints and WebSocket connections"
        echo "4. Monitor Gateway API metrics and logs"
    fi
}

# Run main function with all arguments
main "$@"

