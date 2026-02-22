#!/bin/bash

# Kind Cluster Setup and Validation Script
# Creates local Kubernetes cluster for blue-green deployment testing
# Part of Phase 1 Week 3: Kubernetes Cluster Validation

set -euo pipefail

# Configuration
CLUSTER_NAME="blue-green-test"
KIND_VERSION="v0.20.0"
KUBECTL_VERSION="v1.28.0"
FLUX_VERSION="v2.2.2"
LOG_FILE="/tmp/kind-cluster-setup-$(date +%Y%m%d-%H%M%S).log"

# Cluster configuration
WORKER_NODES=2
CONTROL_PLANE_NODES=1
KUBERNETES_VERSION="v1.28.0"

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

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to install Kind
install_kind() {
    header "Installing Kind"
    
    if command_exists kind; then
        local current_version
        current_version=$(kind version | grep -o 'v[0-9]\+\.[0-9]\+\.[0-9]\+' | head -1 || echo "unknown")
        
        if [[ "$current_version" == "$KIND_VERSION" ]]; then
            success "Kind $KIND_VERSION is already installed"
            return 0
        else
            info "Kind $current_version found, upgrading to $KIND_VERSION"
        fi
    fi
    
    log "Downloading Kind $KIND_VERSION..."
    
    local os
    os=$(uname -s | tr '[:upper:]' '[:lower:]')
    local arch
    arch=$(uname -m)
    
    # Map architecture names
    case $arch in
        x86_64) arch="amd64" ;;
        aarch64) arch="arm64" ;;
        armv7l) arch="arm" ;;
    esac
    
    local kind_url="https://kind.sigs.k8s.io/dl/${KIND_VERSION}/kind-${os}-${arch}"
    
    if curl -Lo ./kind "$kind_url" 2>/dev/null; then
        chmod +x ./kind
        sudo mv ./kind /usr/local/bin/kind
        success "Kind $KIND_VERSION installed successfully"
    else
        error "Failed to download Kind"
        return 1
    fi
}

# Function to install kubectl
install_kubectl() {
    header "Installing kubectl"
    
    if command_exists kubectl; then
        local current_version
        current_version=$(kubectl version --client -o json 2>/dev/null | jq -r '.clientVersion.gitVersion' 2>/dev/null || echo "unknown")
        
        if [[ "$current_version" == "$KUBECTL_VERSION" ]]; then
            success "kubectl $KUBECTL_VERSION is already installed"
            return 0
        else
            info "kubectl $current_version found, upgrading to $KUBECTL_VERSION"
        fi
    fi
    
    log "Downloading kubectl $KUBECTL_VERSION..."
    
    local os
    os=$(uname -s | tr '[:upper:]' '[:lower:]')
    local arch
    arch=$(uname -m)
    
    case $arch in
        x86_64) arch="amd64" ;;
        aarch64) arch="arm64" ;;
        armv7l) arch="arm" ;;
    esac
    
    local kubectl_url="https://dl.k8s.io/release/${KUBECTL_VERSION}/bin/${os}/${arch}/kubectl"
    
    if curl -LO "$kubectl_url" 2>/dev/null; then
        chmod +x kubectl
        sudo mv kubectl /usr/local/bin/kubectl
        success "kubectl $KUBECTL_VERSION installed successfully"
    else
        error "Failed to download kubectl"
        return 1
    fi
}

# Function to install Flux CLI
install_flux() {
    header "Installing Flux CLI"
    
    if command_exists flux; then
        local current_version
        current_version=$(flux version --client 2>/dev/null | grep -o 'v[0-9]\+\.[0-9]\+\.[0-9]\+' | head -1 || echo "unknown")
        
        if [[ "$current_version" == "$FLUX_VERSION" ]]; then
            success "Flux CLI $FLUX_VERSION is already installed"
            return 0
        else
            info "Flux CLI $current_version found, upgrading to $FLUX_VERSION"
        fi
    fi
    
    log "Installing Flux CLI $FLUX_VERSION..."
    
    if curl -s https://fluxcd.io/install.sh | sudo bash -s -- --version="$FLUX_VERSION" 2>/dev/null; then
        success "Flux CLI $FLUX_VERSION installed successfully"
    else
        error "Failed to install Flux CLI"
        return 1
    fi
}

# Function to create Kind cluster configuration
create_cluster_config() {
    header "Creating Kind cluster configuration"
    
    local config_file="/tmp/kind-cluster-config.yaml"
    
    cat > "$config_file" << EOF
kind: Cluster
apiVersion: kind.x-k8s.io/v1alpha4
name: $CLUSTER_NAME
nodes:
- role: control-plane
  kubeadmConfigPatches:
  - |
    kind: InitConfiguration
    nodeRegistration:
      kubeletExtraArgs:
        node-labels: "ingress-ready=true"
  extraPortMappings:
  - containerPort: 80
    hostPort: 80
    protocol: TCP
  - containerPort: 443
    hostPort: 443
    protocol: TCP
  - containerPort: 30080
    hostPort: 30080
    protocol: TCP
  - containerPort: 30443
    hostPort: 30443
    protocol: TCP
EOF

    # Add worker nodes
    for ((i=1; i<=WORKER_NODES; i++)); do
        cat >> "$config_file" << EOF
- role: worker
EOF
    done
    
    info "Cluster configuration created: $config_file"
    echo "$config_file"
}

# Function to create Kind cluster
create_kind_cluster() {
    header "Creating Kind cluster"
    
    # Check if cluster already exists
    if kind get clusters | grep -q "^$CLUSTER_NAME$"; then
        warning "Cluster '$CLUSTER_NAME' already exists"
        read -p "Delete existing cluster and recreate? (y/N): " -n 1 -r
        echo
        
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            log "Deleting existing cluster..."
            kind delete cluster --name "$CLUSTER_NAME"
        else
            info "Using existing cluster"
            return 0
        fi
    fi
    
    local config_file
    config_file=$(create_cluster_config)
    
    log "Creating Kind cluster '$CLUSTER_NAME' with $WORKER_NODES worker nodes..."
    
    if kind create cluster --config "$config_file" --image "kindest/node:$KUBERNETES_VERSION"; then
        success "Kind cluster '$CLUSTER_NAME' created successfully"
        
        # Wait for cluster to be ready
        log "Waiting for cluster to be ready..."
        kubectl wait --for=condition=Ready nodes --all --timeout=300s
        
        success "All nodes are ready"
    else
        error "Failed to create Kind cluster"
        return 1
    fi
    
    # Clean up config file
    rm -f "$config_file"
}

# Function to install ingress controller
install_ingress_controller() {
    header "Installing NGINX Ingress Controller"
    
    log "Applying NGINX ingress controller manifest..."
    
    if kubectl apply -f https://raw.githubusercontent.com/kubernetes/ingress-nginx/main/deploy/static/provider/kind/deploy.yaml; then
        success "NGINX ingress controller manifest applied"
        
        log "Waiting for ingress controller to be ready..."
        kubectl wait --namespace ingress-nginx \
            --for=condition=ready pod \
            --selector=app.kubernetes.io/component=controller \
            --timeout=300s
        
        success "NGINX ingress controller is ready"
    else
        error "Failed to install NGINX ingress controller"
        return 1
    fi
}

# Function to install FluxCD
install_fluxcd() {
    header "Installing FluxCD"
    
    log "Checking FluxCD prerequisites..."
    
    if flux check --pre; then
        success "FluxCD prerequisites satisfied"
    else
        error "FluxCD prerequisites not satisfied"
        return 1
    fi
    
    log "Installing FluxCD components..."
    
    if flux install; then
        success "FluxCD installed successfully"
        
        log "Waiting for FluxCD controllers to be ready..."
        kubectl wait --for=condition=Ready pods --all -n flux-system --timeout=300s
        
        success "All FluxCD controllers are ready"
        
        # Verify installation
        log "Verifying FluxCD installation..."
        if flux check; then
            success "FluxCD installation verified"
        else
            warning "FluxCD installation verification failed"
        fi
    else
        error "Failed to install FluxCD"
        return 1
    fi
}

# Function to create test namespaces
create_test_namespaces() {
    header "Creating test namespaces"
    
    local namespaces=("reverse-tender-blue" "reverse-tender-green" "monitoring")
    
    for namespace in "${namespaces[@]}"; do
        log "Creating namespace: $namespace"
        
        if kubectl create namespace "$namespace" 2>/dev/null || kubectl get namespace "$namespace" &>/dev/null; then
            success "Namespace '$namespace' is ready"
        else
            error "Failed to create namespace '$namespace'"
            return 1
        fi
    done
}

# Function to install metrics server
install_metrics_server() {
    header "Installing Metrics Server"
    
    log "Applying metrics server manifest..."
    
    # Use metrics server configuration suitable for Kind
    cat << EOF | kubectl apply -f -
apiVersion: v1
kind: ServiceAccount
metadata:
  labels:
    k8s-app: metrics-server
  name: metrics-server
  namespace: kube-system
---
apiVersion: rbac.authorization.k8s.io/v1
kind: ClusterRole
metadata:
  labels:
    k8s-app: metrics-server
    rbac.authorization.k8s.io/aggregate-to-admin: "true"
    rbac.authorization.k8s.io/aggregate-to-edit: "true"
    rbac.authorization.k8s.io/aggregate-to-view: "true"
  name: system:aggregated-metrics-reader
rules:
- apiGroups:
  - metrics.k8s.io
  resources:
  - pods
  - nodes
  verbs:
  - get
  - list
---
apiVersion: rbac.authorization.k8s.io/v1
kind: ClusterRole
metadata:
  labels:
    k8s-app: metrics-server
  name: system:metrics-server
rules:
- apiGroups:
  - ""
  resources:
  - nodes/metrics
  verbs:
  - get
- apiGroups:
  - ""
  resources:
  - pods
  - nodes
  verbs:
  - get
  - list
  - watch
---
apiVersion: rbac.authorization.k8s.io/v1
kind: RoleBinding
metadata:
  labels:
    k8s-app: metrics-server
  name: metrics-server-auth-reader
  namespace: kube-system
roleRef:
  apiGroup: rbac.authorization.k8s.io
  kind: Role
  name: extension-apiserver-authentication-reader
subjects:
- kind: ServiceAccount
  name: metrics-server
  namespace: kube-system
---
apiVersion: rbac.authorization.k8s.io/v1
kind: ClusterRoleBinding
metadata:
  labels:
    k8s-app: metrics-server
  name: metrics-server:system:auth-delegator
roleRef:
  apiGroup: rbac.authorization.k8s.io
  kind: ClusterRole
  name: system:auth-delegator
subjects:
- kind: ServiceAccount
  name: metrics-server
  namespace: kube-system
---
apiVersion: rbac.authorization.k8s.io/v1
kind: ClusterRoleBinding
metadata:
  labels:
    k8s-app: metrics-server
  name: system:metrics-server
roleRef:
  apiGroup: rbac.authorization.k8s.io
  kind: ClusterRole
  name: system:metrics-server
subjects:
- kind: ServiceAccount
  name: metrics-server
  namespace: kube-system
---
apiVersion: v1
kind: Service
metadata:
  labels:
    k8s-app: metrics-server
  name: metrics-server
  namespace: kube-system
spec:
  ports:
  - name: https
    port: 443
    protocol: TCP
    targetPort: https
  selector:
    k8s-app: metrics-server
---
apiVersion: apps/v1
kind: Deployment
metadata:
  labels:
    k8s-app: metrics-server
  name: metrics-server
  namespace: kube-system
spec:
  selector:
    matchLabels:
      k8s-app: metrics-server
  strategy:
    rollingUpdate:
      maxUnavailable: 0
  template:
    metadata:
      labels:
        k8s-app: metrics-server
    spec:
      containers:
      - args:
        - --cert-dir=/tmp
        - --secure-port=4443
        - --kubelet-preferred-address-types=InternalIP,ExternalIP,Hostname
        - --kubelet-use-node-status-port
        - --metric-resolution=15s
        - --kubelet-insecure-tls
        image: registry.k8s.io/metrics-server/metrics-server:v0.6.4
        imagePullPolicy: IfNotPresent
        livenessProbe:
          failureThreshold: 3
          httpGet:
            path: /livez
            port: https
            scheme: HTTPS
          periodSeconds: 10
        name: metrics-server
        ports:
        - containerPort: 4443
          name: https
          protocol: TCP
        readinessProbe:
          failureThreshold: 3
          httpGet:
            path: /readyz
            port: https
            scheme: HTTPS
          initialDelaySeconds: 20
          periodSeconds: 10
        resources:
          requests:
            cpu: 100m
            memory: 200Mi
        securityContext:
          allowPrivilegeEscalation: false
          readOnlyRootFilesystem: true
          runAsNonRoot: true
          runAsUser: 1000
        volumeMounts:
        - mountPath: /tmp
          name: tmp-dir
      nodeSelector:
        kubernetes.io/os: linux
      priorityClassName: system-cluster-critical
      serviceAccountName: metrics-server
      volumes:
      - emptyDir: {}
        name: tmp-dir
---
apiVersion: apiregistration.k8s.io/v1
kind: APIService
metadata:
  labels:
    k8s-app: metrics-server
  name: v1beta1.metrics.k8s.io
spec:
  group: metrics.k8s.io
  groupPriorityMinimum: 100
  insecureSkipTLSVerify: true
  service:
    name: metrics-server
    namespace: kube-system
  version: v1beta1
  versionPriority: 100
EOF
    
    if [[ $? -eq 0 ]]; then
        success "Metrics server manifest applied"
        
        log "Waiting for metrics server to be ready..."
        kubectl wait --for=condition=Ready pods -l k8s-app=metrics-server -n kube-system --timeout=300s
        
        success "Metrics server is ready"
    else
        warning "Failed to install metrics server (optional component)"
    fi
}

# Function to validate cluster setup
validate_cluster_setup() {
    header "Validating cluster setup"
    
    local validation_passed=true
    
    # Check cluster info
    log "Checking cluster info..."
    if kubectl cluster-info; then
        success "Cluster info retrieved successfully"
    else
        error "Failed to get cluster info"
        validation_passed=false
    fi
    
    # Check nodes
    log "Checking node status..."
    local node_count
    node_count=$(kubectl get nodes --no-headers | wc -l)
    local expected_nodes=$((CONTROL_PLANE_NODES + WORKER_NODES))
    
    if [[ $node_count -eq $expected_nodes ]]; then
        success "All $expected_nodes nodes are present"
    else
        error "Expected $expected_nodes nodes, found $node_count"
        validation_passed=false
    fi
    
    # Check node readiness
    local ready_nodes
    ready_nodes=$(kubectl get nodes --no-headers | grep -c " Ready ")
    
    if [[ $ready_nodes -eq $expected_nodes ]]; then
        success "All $expected_nodes nodes are ready"
    else
        error "Only $ready_nodes/$expected_nodes nodes are ready"
        validation_passed=false
    fi
    
    # Check system pods
    log "Checking system pods..."
    local system_pods_ready
    system_pods_ready=$(kubectl get pods -n kube-system --no-headers | grep -c " Running ")
    
    if [[ $system_pods_ready -gt 0 ]]; then
        success "$system_pods_ready system pods are running"
    else
        error "No system pods are running"
        validation_passed=false
    fi
    
    # Check FluxCD
    log "Checking FluxCD installation..."
    if flux check; then
        success "FluxCD is working correctly"
    else
        error "FluxCD check failed"
        validation_passed=false
    fi
    
    # Check ingress controller
    log "Checking ingress controller..."
    local ingress_pods_ready
    ingress_pods_ready=$(kubectl get pods -n ingress-nginx --no-headers | grep -c " Running ")
    
    if [[ $ingress_pods_ready -gt 0 ]]; then
        success "Ingress controller is running ($ingress_pods_ready pods)"
    else
        error "Ingress controller is not running"
        validation_passed=false
    fi
    
    # Check test namespaces
    log "Checking test namespaces..."
    local test_namespaces=("reverse-tender-blue" "reverse-tender-green")
    
    for namespace in "${test_namespaces[@]}"; do
        if kubectl get namespace "$namespace" &>/dev/null; then
            success "Namespace '$namespace' exists"
        else
            error "Namespace '$namespace' does not exist"
            validation_passed=false
        fi
    done
    
    return $([[ "$validation_passed" == "true" ]] && echo 0 || echo 1)
}

# Function to generate cluster info
generate_cluster_info() {
    header "Generating cluster information"
    
    local info_file="/tmp/kind-cluster-info-$(date +%Y%m%d-%H%M%S).txt"
    
    cat > "$info_file" << EOF
# Kind Cluster Information
Generated: $(date -Iseconds)
Cluster Name: $CLUSTER_NAME
Kubernetes Version: $KUBERNETES_VERSION

## Cluster Nodes
$(kubectl get nodes -o wide)

## System Pods
$(kubectl get pods -n kube-system)

## FluxCD Status
$(flux check)

## Ingress Controller
$(kubectl get pods -n ingress-nginx)

## Test Namespaces
$(kubectl get namespaces | grep -E "(reverse-tender|monitoring)")

## Cluster Resources
$(kubectl top nodes 2>/dev/null || echo "Metrics not available")

## Storage Classes
$(kubectl get storageclass)

## Cluster Access
Kubeconfig: $(kubectl config current-context)
API Server: $(kubectl config view --minify -o jsonpath='{.clusters[0].cluster.server}')

## Port Forwards for Testing
# Access ingress controller
kubectl port-forward -n ingress-nginx service/ingress-nginx-controller 8080:80

# Access FluxCD UI (if installed)
kubectl port-forward -n flux-system service/weave-gitops 9001:9001

EOF
    
    success "Cluster information saved to: $info_file"
    info "Cluster setup complete!"
    info ""
    info "Next steps:"
    info "1. Deploy blue-green environments: ./deploy-blue-green-environments.sh"
    info "2. Run validation tests: ../deployment/run-all-tests.sh"
    info "3. Access cluster: kubectl config use-context kind-$CLUSTER_NAME"
    info ""
    info "To delete cluster: kind delete cluster --name $CLUSTER_NAME"
}

# Function to show usage
show_usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  --cluster-name NAME    Set cluster name (default: $CLUSTER_NAME)"
    echo "  --workers N           Set number of worker nodes (default: $WORKER_NODES)"
    echo "  --k8s-version VERSION Set Kubernetes version (default: $KUBERNETES_VERSION)"
    echo "  --skip-flux           Skip FluxCD installation"
    echo "  --skip-ingress        Skip ingress controller installation"
    echo "  --skip-metrics        Skip metrics server installation"
    echo "  --help                Show this help message"
}

# Main execution
main() {
    local skip_flux=false
    local skip_ingress=false
    local skip_metrics=false
    
    # Parse command line arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --cluster-name)
                CLUSTER_NAME="$2"
                shift 2
                ;;
            --workers)
                WORKER_NODES="$2"
                shift 2
                ;;
            --k8s-version)
                KUBERNETES_VERSION="$2"
                shift 2
                ;;
            --skip-flux)
                skip_flux=true
                shift
                ;;
            --skip-ingress)
                skip_ingress=true
                shift
                ;;
            --skip-metrics)
                skip_metrics=true
                shift
                ;;
            --help)
                show_usage
                exit 0
                ;;
            *)
                error "Unknown option: $1"
                show_usage
                exit 1
                ;;
        esac
    done
    
    header "🚀 Kind Cluster Setup for Blue-Green Deployment Testing"
    log "Logging to: $LOG_FILE"
    log "Cluster name: $CLUSTER_NAME"
    log "Worker nodes: $WORKER_NODES"
    log "Kubernetes version: $KUBERNETES_VERSION"
    echo ""
    
    # Check prerequisites
    if ! command_exists docker; then
        error "Docker is required but not installed"
        exit 1
    fi
    
    if ! docker info &>/dev/null; then
        error "Docker is not running"
        exit 1
    fi
    
    # Install required tools
    install_kind || exit 1
    install_kubectl || exit 1
    
    if [[ "$skip_flux" == "false" ]]; then
        install_flux || exit 1
    fi
    
    # Create cluster
    create_kind_cluster || exit 1
    
    # Install components
    if [[ "$skip_ingress" == "false" ]]; then
        install_ingress_controller || exit 1
    fi
    
    if [[ "$skip_flux" == "false" ]]; then
        install_fluxcd || exit 1
    fi
    
    # Create test namespaces
    create_test_namespaces || exit 1
    
    # Install optional components
    if [[ "$skip_metrics" == "false" ]]; then
        install_metrics_server
    fi
    
    # Validate setup
    if validate_cluster_setup; then
        success "✅ Cluster validation passed"
    else
        error "❌ Cluster validation failed"
        exit 1
    fi
    
    # Generate cluster info
    generate_cluster_info
    
    success "🎉 Kind cluster setup complete!"
    success "Cluster '$CLUSTER_NAME' is ready for blue-green deployment testing"
}

# Run main function with all arguments
main "$@"

