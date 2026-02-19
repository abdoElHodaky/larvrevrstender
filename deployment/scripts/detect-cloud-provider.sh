#!/bin/bash

# Cloud Provider Detection Script for Kubernetes Clusters
# Automatically detects the cloud provider based on cluster characteristics

set -euo pipefail

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
Cloud Provider Detection Script

USAGE:
    $0 [OPTIONS]

OPTIONS:
    -v, --verbose              Enable verbose output
    -j, --json                 Output result in JSON format
    -q, --quiet                Suppress all output except the result
    -h, --help                 Show this help message

DESCRIPTION:
    This script automatically detects the cloud provider of the current
    Kubernetes cluster by analyzing various cluster characteristics including:
    - Kubernetes context names
    - Node labels and annotations
    - API server endpoints
    - Storage classes
    - Load balancer implementations

SUPPORTED CLOUD PROVIDERS:
    - Google Cloud Platform (GKE)
    - Microsoft Azure (AKS)
    - Amazon Web Services (EKS)
    - DigitalOcean (DOKS)
    - Linode (LKE)
    - OpenStack
    - On-premises/Unknown

OUTPUT:
    The script outputs the detected cloud provider name in lowercase:
    gcp, azure, aws, digitalocean, linode, openstack, or unknown

EXAMPLES:
    # Basic detection
    $0

    # Verbose output
    $0 --verbose

    # JSON output
    $0 --json

    # Use in scripts
    PROVIDER=$(./detect-cloud-provider.sh --quiet)
    echo "Detected provider: $PROVIDER"

EOF
}

# Default values
VERBOSE=false
JSON_OUTPUT=false
QUIET=false

# Parse command line arguments
parse_args() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            -v|--verbose)
                VERBOSE=true
                shift
                ;;
            -j|--json)
                JSON_OUTPUT=true
                shift
                ;;
            -q|--quiet)
                QUIET=true
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

# Verbose logging (only if verbose mode is enabled and not quiet)
log_verbose() {
    if [[ "$VERBOSE" == true ]] && [[ "$QUIET" != true ]]; then
        log_info "$1"
    fi
}

# Check if kubectl is available and cluster is accessible
check_kubectl() {
    if ! command -v kubectl &> /dev/null; then
        if [[ "$QUIET" != true ]]; then
            log_error "kubectl is not installed or not in PATH"
        fi
        exit 1
    fi
    
    if ! kubectl cluster-info &> /dev/null; then
        if [[ "$QUIET" != true ]]; then
            log_error "Cannot connect to Kubernetes cluster"
        fi
        exit 1
    fi
    
    log_verbose "kubectl connectivity verified"
}

# Detect cloud provider based on Kubernetes context
detect_from_context() {
    local context=$(kubectl config current-context 2>/dev/null || echo "")
    log_verbose "Current context: $context"
    
    # GKE patterns
    if [[ "$context" == *"gke_"* ]] || [[ "$context" == *"gke-"* ]]; then
        echo "gcp"
        return 0
    fi
    
    # AKS patterns
    if [[ "$context" == *"aks-"* ]] || [[ "$context" == *"-aks"* ]]; then
        echo "azure"
        return 0
    fi
    
    # EKS patterns
    if [[ "$context" == *"eks"* ]] || [[ "$context" == *"@"*".eks."* ]]; then
        echo "aws"
        return 0
    fi
    
    # DigitalOcean patterns
    if [[ "$context" == *"do-"* ]] || [[ "$context" == *"digitalocean"* ]] || [[ "$context" == *"doks"* ]]; then
        echo "digitalocean"
        return 0
    fi
    
    # Linode patterns
    if [[ "$context" == *"lke"* ]] || [[ "$context" == *"linode"* ]]; then
        echo "linode"
        return 0
    fi
    
    log_verbose "Could not detect provider from context"
    return 1
}

# Detect cloud provider based on node labels
detect_from_node_labels() {
    local node_labels=$(kubectl get nodes -o jsonpath='{.items[0].metadata.labels}' 2>/dev/null || echo "")
    log_verbose "Analyzing node labels..."
    
    # GCP/GKE patterns
    if [[ "$node_labels" == *"cloud.google.com"* ]] || [[ "$node_labels" == *"gke.io"* ]]; then
        echo "gcp"
        return 0
    fi
    
    # Azure/AKS patterns
    if [[ "$node_labels" == *"kubernetes.azure.com"* ]] || [[ "$node_labels" == *"agentpool"* ]]; then
        echo "azure"
        return 0
    fi
    
    # AWS/EKS patterns
    if [[ "$node_labels" == *"eks.amazonaws.com"* ]] || [[ "$node_labels" == *"alpha.eksctl.io"* ]]; then
        echo "aws"
        return 0
    fi
    
    # DigitalOcean patterns
    if [[ "$node_labels" == *"digitalocean.com"* ]] || [[ "$node_labels" == *"doks.digitalocean.com"* ]]; then
        echo "digitalocean"
        return 0
    fi
    
    # Linode patterns
    if [[ "$node_labels" == *"linode.com"* ]] || [[ "$node_labels" == *"lke.linode.com"* ]]; then
        echo "linode"
        return 0
    fi
    
    log_verbose "Could not detect provider from node labels"
    return 1
}

# Detect cloud provider based on API server endpoint
detect_from_api_server() {
    local api_server=$(kubectl cluster-info | grep "Kubernetes control plane" | awk '{print $NF}' 2>/dev/null || echo "")
    log_verbose "API server endpoint: $api_server"
    
    # GCP/GKE patterns
    if [[ "$api_server" == *".googleapis.com"* ]] || [[ "$api_server" == *"gke"* ]]; then
        echo "gcp"
        return 0
    fi
    
    # Azure/AKS patterns
    if [[ "$api_server" == *".azmk8s.io"* ]] || [[ "$api_server" == *".azure.com"* ]]; then
        echo "azure"
        return 0
    fi
    
    # AWS/EKS patterns
    if [[ "$api_server" == *".eks.amazonaws.com"* ]] || [[ "$api_server" == *".sk1.amazonaws.com"* ]]; then
        echo "aws"
        return 0
    fi
    
    # DigitalOcean patterns
    if [[ "$api_server" == *".digitalocean.com"* ]] || [[ "$api_server" == *".k8s.ondigitalocean.com"* ]]; then
        echo "digitalocean"
        return 0
    fi
    
    # Linode patterns
    if [[ "$api_server" == *".linode.com"* ]] || [[ "$api_server" == *".lke.linode.com"* ]]; then
        echo "linode"
        return 0
    fi
    
    log_verbose "Could not detect provider from API server endpoint"
    return 1
}

# Detect cloud provider based on storage classes
detect_from_storage_classes() {
    local storage_classes=$(kubectl get storageclass -o jsonpath='{.items[*].provisioner}' 2>/dev/null || echo "")
    log_verbose "Storage class provisioners: $storage_classes"
    
    # GCP/GKE patterns
    if [[ "$storage_classes" == *"kubernetes.io/gce-pd"* ]] || [[ "$storage_classes" == *"pd.csi.storage.gke.io"* ]]; then
        echo "gcp"
        return 0
    fi
    
    # Azure/AKS patterns
    if [[ "$storage_classes" == *"kubernetes.io/azure-disk"* ]] || [[ "$storage_classes" == *"disk.csi.azure.com"* ]]; then
        echo "azure"
        return 0
    fi
    
    # AWS/EKS patterns
    if [[ "$storage_classes" == *"kubernetes.io/aws-ebs"* ]] || [[ "$storage_classes" == *"ebs.csi.aws.com"* ]]; then
        echo "aws"
        return 0
    fi
    
    # DigitalOcean patterns
    if [[ "$storage_classes" == *"dobs.csi.digitalocean.com"* ]]; then
        echo "digitalocean"
        return 0
    fi
    
    # Linode patterns
    if [[ "$storage_classes" == *"linode.com/block-storage"* ]]; then
        echo "linode"
        return 0
    fi
    
    # OpenStack patterns
    if [[ "$storage_classes" == *"kubernetes.io/cinder"* ]] || [[ "$storage_classes" == *"cinder.csi.openstack.org"* ]]; then
        echo "openstack"
        return 0
    fi
    
    log_verbose "Could not detect provider from storage classes"
    return 1
}

# Detect cloud provider based on load balancer services
detect_from_load_balancers() {
    local lb_annotations=$(kubectl get services --all-namespaces -o jsonpath='{.items[?(@.spec.type=="LoadBalancer")].metadata.annotations}' 2>/dev/null || echo "")
    log_verbose "Analyzing load balancer annotations..."
    
    # GCP patterns
    if [[ "$lb_annotations" == *"cloud.google.com"* ]]; then
        echo "gcp"
        return 0
    fi
    
    # Azure patterns
    if [[ "$lb_annotations" == *"service.beta.kubernetes.io/azure-load-balancer"* ]]; then
        echo "azure"
        return 0
    fi
    
    # AWS patterns
    if [[ "$lb_annotations" == *"service.beta.kubernetes.io/aws-load-balancer"* ]]; then
        echo "aws"
        return 0
    fi
    
    # DigitalOcean patterns
    if [[ "$lb_annotations" == *"service.beta.kubernetes.io/do-loadbalancer"* ]]; then
        echo "digitalocean"
        return 0
    fi
    
    # Linode patterns
    if [[ "$lb_annotations" == *"service.beta.kubernetes.io/linode-loadbalancer"* ]]; then
        echo "linode"
        return 0
    fi
    
    # OpenStack patterns
    if [[ "$lb_annotations" == *"service.beta.kubernetes.io/openstack-internal-load-balancer"* ]]; then
        echo "openstack"
        return 0
    fi
    
    log_verbose "Could not detect provider from load balancer annotations"
    return 1
}

# Detect cloud provider based on system pods
detect_from_system_pods() {
    local system_pods=$(kubectl get pods -n kube-system -o jsonpath='{.items[*].metadata.name}' 2>/dev/null || echo "")
    log_verbose "Analyzing system pods..."
    
    # GCP/GKE patterns
    if [[ "$system_pods" == *"gke-"* ]] || [[ "$system_pods" == *"gcp-"* ]] || [[ "$system_pods" == *"fluentd-gcp"* ]]; then
        echo "gcp"
        return 0
    fi
    
    # Azure/AKS patterns
    if [[ "$system_pods" == *"azure-"* ]] || [[ "$system_pods" == *"aks-"* ]] || [[ "$system_pods" == *"omsagent"* ]]; then
        echo "azure"
        return 0
    fi
    
    # AWS/EKS patterns
    if [[ "$system_pods" == *"aws-"* ]] || [[ "$system_pods" == *"eks-"* ]] || [[ "$system_pods" == *"amazon-"* ]]; then
        echo "aws"
        return 0
    fi
    
    # DigitalOcean patterns
    if [[ "$system_pods" == *"do-"* ]] || [[ "$system_pods" == *"digitalocean"* ]]; then
        echo "digitalocean"
        return 0
    fi
    
    # Linode patterns
    if [[ "$system_pods" == *"linode"* ]] || [[ "$system_pods" == *"lke-"* ]]; then
        echo "linode"
        return 0
    fi
    
    # OpenStack patterns
    if [[ "$system_pods" == *"openstack"* ]] || [[ "$system_pods" == *"cinder"* ]] || [[ "$system_pods" == *"neutron"* ]]; then
        echo "openstack"
        return 0
    fi
    
    log_verbose "Could not detect provider from system pods"
    return 1
}

# Main detection function
detect_cloud_provider() {
    log_verbose "Starting cloud provider detection..."
    
    # Try different detection methods in order of reliability
    local detection_methods=(
        "detect_from_context"
        "detect_from_node_labels"
        "detect_from_api_server"
        "detect_from_storage_classes"
        "detect_from_load_balancers"
        "detect_from_system_pods"
    )
    
    for method in "${detection_methods[@]}"; do
        log_verbose "Trying detection method: $method"
        if provider=$($method); then
            log_verbose "Detection successful using $method: $provider"
            echo "$provider"
            return 0
        fi
    done
    
    log_verbose "All detection methods failed, returning unknown"
    echo "unknown"
    return 1
}

# Output result in JSON format
output_json() {
    local provider="$1"
    local confidence="high"
    
    if [[ "$provider" == "unknown" ]]; then
        confidence="none"
    fi
    
    cat << EOF
{
  "provider": "$provider",
  "confidence": "$confidence",
  "timestamp": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
  "detection_method": "multi-method",
  "supported_providers": [
    "gcp",
    "azure", 
    "aws",
    "digitalocean",
    "linode",
    "openstack"
  ]
}
EOF
}

# Main function
main() {
    # Parse arguments
    parse_args "$@"
    
    # Check prerequisites
    check_kubectl
    
    # Detect cloud provider
    local provider
    provider=$(detect_cloud_provider)
    
    # Output result
    if [[ "$JSON_OUTPUT" == true ]]; then
        output_json "$provider"
    elif [[ "$QUIET" == true ]]; then
        echo "$provider"
    else
        if [[ "$provider" != "unknown" ]]; then
            log_success "Detected cloud provider: $provider"
        else
            log_warning "Could not detect cloud provider"
        fi
        echo "$provider"
    fi
    
    # Exit with appropriate code
    if [[ "$provider" == "unknown" ]]; then
        exit 1
    else
        exit 0
    fi
}

# Run main function with all arguments
main "$@"

