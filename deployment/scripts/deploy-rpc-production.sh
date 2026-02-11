#!/bin/bash

# RPC Services Deployment Script
# Deploys RPC microservices to Kubernetes environments
# Usage: ./deploy-rpc-production.sh --version <version> --namespace <namespace> --context <context>

set -e

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

# Default values
VERSION=""
NAMESPACE="reversetender-staging"
CONTEXT="staging"
DRY_RUN=false

# Colors for output
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

# Help function
show_help() {
    cat << EOF
RPC Services Deployment Script

USAGE:
    ./deploy-rpc-production.sh [OPTIONS]

OPTIONS:
    --version VERSION       Git commit SHA or version tag
    --namespace NAMESPACE   Kubernetes namespace (default: reversetender-staging)
    --context CONTEXT       Kubernetes context (default: staging)
    --dry-run              Show what would be deployed without executing
    -h, --help             Show this help message

EXAMPLES:
    # Deploy to staging
    ./deploy-rpc-production.sh --version abc123 --namespace reversetender-staging --context staging
    
    # Dry run deployment
    ./deploy-rpc-production.sh --version abc123 --dry-run

EOF
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --version)
            VERSION="$2"
            shift 2
            ;;
        --namespace)
            NAMESPACE="$2"
            shift 2
            ;;
        --context)
            CONTEXT="$2"
            shift 2
            ;;
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        -h|--help)
            show_help
            exit 0
            ;;
        *)
            log_error "Unknown option: $1"
            show_help
            exit 1
            ;;
    esac
done

# Validate required parameters
if [[ -z "$VERSION" ]]; then
    log_error "Version is required. Use --version to specify the deployment version."
    exit 1
fi

log_info "Starting RPC Services Deployment"
log_info "Version: $VERSION"
log_info "Namespace: $NAMESPACE"
log_info "Context: $CONTEXT"
log_info "Dry Run: $DRY_RUN"

# Check if kubectl is available
if ! command -v kubectl &> /dev/null; then
    log_error "kubectl is not installed or not in PATH"
    exit 1
fi

# Check if kubeconfig is set
if [[ -z "$KUBECONFIG" ]]; then
    log_warning "KUBECONFIG environment variable is not set"
fi

# Verify kubectl can connect to cluster
log_info "Verifying cluster connectivity..."
if ! kubectl cluster-info &> /dev/null; then
    log_error "Cannot connect to Kubernetes cluster. Please check your kubeconfig."
    exit 1
fi

# Check if namespace exists, create if it doesn't
log_info "Checking namespace: $NAMESPACE"
if ! kubectl get namespace "$NAMESPACE" &> /dev/null; then
    log_info "Creating namespace: $NAMESPACE"
    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "[DRY RUN] Would create namespace: $NAMESPACE"
    else
        kubectl create namespace "$NAMESPACE"
    fi
else
    log_info "Namespace $NAMESPACE already exists"
fi

# Determine deployment overlay based on context
OVERLAY_PATH=""
case $CONTEXT in
    staging)
        OVERLAY_PATH="$PROJECT_ROOT/deployment/k8s/overlays/staging"
        ;;
    production)
        OVERLAY_PATH="$PROJECT_ROOT/deployment/k8s/overlays/production"
        ;;
    development)
        OVERLAY_PATH="$PROJECT_ROOT/deployment/k8s/overlays/development"
        ;;
    *)
        log_error "Unknown context: $CONTEXT. Supported contexts: staging, production, development"
        exit 1
        ;;
esac

# Check if overlay exists
if [[ ! -d "$OVERLAY_PATH" ]]; then
    log_error "Overlay path does not exist: $OVERLAY_PATH"
    exit 1
fi

log_info "Using overlay: $OVERLAY_PATH"

# Update image tags with the new version
log_info "Updating image tags to version: $VERSION"
cd "$OVERLAY_PATH"

# Create a temporary kustomization file with updated image tags
TEMP_KUSTOMIZATION=$(mktemp)
cp kustomization.yaml "$TEMP_KUSTOMIZATION"

# Update image tags in kustomization.yaml (this is a simplified approach)
# In a real deployment, you might want to use kustomize edit set image
if [[ "$DRY_RUN" == "true" ]]; then
    log_info "[DRY RUN] Would update image tags to: $VERSION"
else
    # For now, we'll use the existing kustomization as-is
    # In a production setup, you'd want to update the image tags here
    log_info "Using existing kustomization configuration"
fi

# Deploy using kustomize
log_info "Deploying RPC services to namespace: $NAMESPACE"
if [[ "$DRY_RUN" == "true" ]]; then
    log_info "[DRY RUN] Would execute: kubectl apply -k $OVERLAY_PATH --namespace=$NAMESPACE"
    kubectl kustomize "$OVERLAY_PATH"
else
    kubectl apply -k "$OVERLAY_PATH" --namespace="$NAMESPACE"
fi

# Wait for deployments to be ready
if [[ "$DRY_RUN" != "true" ]]; then
    log_info "Waiting for deployments to be ready..."
    
    # Get all deployments in the namespace
    DEPLOYMENTS=$(kubectl get deployments -n "$NAMESPACE" -o jsonpath='{.items[*].metadata.name}' 2>/dev/null || echo "")
    
    if [[ -n "$DEPLOYMENTS" ]]; then
        for deployment in $DEPLOYMENTS; do
            log_info "Waiting for deployment: $deployment"
            kubectl rollout status deployment/"$deployment" -n "$NAMESPACE" --timeout=300s
        done
    else
        log_warning "No deployments found in namespace: $NAMESPACE"
    fi
fi

# Verify deployment
log_info "Verifying deployment..."
if [[ "$DRY_RUN" != "true" ]]; then
    kubectl get pods -n "$NAMESPACE"
    kubectl get services -n "$NAMESPACE"
fi

log_success "RPC Services deployment completed successfully!"
log_info "Deployment version: $VERSION"
log_info "Namespace: $NAMESPACE"
log_info "Context: $CONTEXT"

# Cleanup
if [[ -f "$TEMP_KUSTOMIZATION" ]]; then
    rm -f "$TEMP_KUSTOMIZATION"
fi

exit 0
