#!/bin/bash

# Blue-Green Environment Deployment Script
# Deploys blue-green environments to Kind cluster for testing
# Part of Phase 1 Week 3: Kubernetes Cluster Validation

set -euo pipefail

# Configuration
CLUSTER_NAME="blue-green-test"
BLUE_NAMESPACE="reverse-tender-blue"
GREEN_NAMESPACE="reverse-tender-green"
REPO_URL="https://github.com/abdoElHodaky/larvrevrstender.git"
BRANCH="v2-blue-green-deploy"
LOG_FILE="/tmp/blue-green-deployment-$(date +%Y%m%d-%H%M%S).log"

# Service configuration
SERVICES=(
    "gateway-service:8009:ghcr.io/abdoelhodaky/larvrevrstender-gateway-service"
    "auth-service:8001:ghcr.io/abdoelhodaky/larvrevrstender-auth-service"
    "user-service:8002:ghcr.io/abdoelhodaky/larvrevrstender-user-service"
    "payment-service:8006:ghcr.io/abdoelhodaky/larvrevrstender-payment-service"
    "notification-service:8007:ghcr.io/abdoelhodaky/larvrevrstender-notification-service"
)

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

# Function to check prerequisites
check_prerequisites() {
    header "Checking Prerequisites"
    
    local prereq_failed=false
    
    # Check if Kind cluster exists
    if ! kind get clusters | grep -q "^$CLUSTER_NAME$"; then
        error "Kind cluster '$CLUSTER_NAME' does not exist"
        error "Run ./kind-cluster-setup.sh first"
        prereq_failed=true
    else
        success "Kind cluster '$CLUSTER_NAME' exists"
    fi
    
    # Check kubectl context
    local current_context
    current_context=$(kubectl config current-context 2>/dev/null || echo "")
    
    if [[ "$current_context" == "kind-$CLUSTER_NAME" ]]; then
        success "kubectl context is set to kind-$CLUSTER_NAME"
    else
        warning "kubectl context is '$current_context', switching to kind-$CLUSTER_NAME"
        kubectl config use-context "kind-$CLUSTER_NAME"
    fi
    
    # Check cluster connectivity
    if kubectl cluster-info &>/dev/null; then
        success "Cluster is accessible"
    else
        error "Cannot access cluster"
        prereq_failed=true
    fi
    
    # Check required namespaces
    for namespace in "$BLUE_NAMESPACE" "$GREEN_NAMESPACE"; do
        if kubectl get namespace "$namespace" &>/dev/null; then
            success "Namespace '$namespace' exists"
        else
            error "Namespace '$namespace' does not exist"
            prereq_failed=true
        fi
    done
    
    # Check FluxCD
    if command -v flux &>/dev/null && flux check &>/dev/null; then
        success "FluxCD is available and working"
    else
        warning "FluxCD is not available - will use direct kubectl deployment"
    fi
    
    return $([[ "$prereq_failed" == "false" ]] && echo 0 || echo 1)
}

# Function to create service deployment
create_service_deployment() {
    local service_name="$1"
    local service_port="$2"
    local image_name="$3"
    local namespace="$4"
    local image_tag="${5:-v2-blue-green-deploy}"
    
    cat << EOF
apiVersion: apps/v1
kind: Deployment
metadata:
  name: $service_name
  namespace: $namespace
  labels:
    app: $service_name
    environment: $(echo "$namespace" | grep -o -E "(blue|green)")
spec:
  replicas: 2
  selector:
    matchLabels:
      app: $service_name
  template:
    metadata:
      labels:
        app: $service_name
        environment: $(echo "$namespace" | grep -o -E "(blue|green)")
    spec:
      containers:
      - name: $service_name
        image: $image_name:$image_tag
        ports:
        - containerPort: $service_port
          name: http
        env:
        - name: APP_ENV
          value: "testing"
        - name: APP_DEBUG
          value: "true"
        - name: SERVICE_NAME
          value: "$service_name"
        - name: ENVIRONMENT
          value: "$(echo "$namespace" | grep -o -E "(blue|green)")"
        livenessProbe:
          httpGet:
            path: /health
            port: $service_port
          initialDelaySeconds: 30
          periodSeconds: 10
          timeoutSeconds: 5
          failureThreshold: 3
        readinessProbe:
          httpGet:
            path: /health
            port: $service_port
          initialDelaySeconds: 10
          periodSeconds: 5
          timeoutSeconds: 3
          failureThreshold: 3
        resources:
          requests:
            cpu: 100m
            memory: 128Mi
          limits:
            cpu: 500m
            memory: 512Mi
      restartPolicy: Always
---
apiVersion: v1
kind: Service
metadata:
  name: $service_name
  namespace: $namespace
  labels:
    app: $service_name
    environment: $(echo "$namespace" | grep -o -E "(blue|green)")
spec:
  selector:
    app: $service_name
  ports:
  - name: http
    port: $service_port
    targetPort: $service_port
    protocol: TCP
  type: ClusterIP
EOF
}

# Function to create ingress configuration
create_ingress_config() {
    local namespace="$1"
    local environment=$(echo "$namespace" | grep -o -E "(blue|green)")
    
    cat << EOF
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: reverse-tender-$environment
  namespace: $namespace
  annotations:
    kubernetes.io/ingress.class: nginx
    nginx.ingress.kubernetes.io/rewrite-target: /
    nginx.ingress.kubernetes.io/ssl-redirect: "false"
spec:
  rules:
  - host: $environment.reverse-tender.local
    http:
      paths:
      - path: /
        pathType: Prefix
        backend:
          service:
            name: gateway-service
            port:
              number: 8009
      - path: /auth
        pathType: Prefix
        backend:
          service:
            name: auth-service
            port:
              number: 8001
      - path: /api/users
        pathType: Prefix
        backend:
          service:
            name: user-service
            port:
              number: 8002
      - path: /api/payments
        pathType: Prefix
        backend:
          service:
            name: payment-service
            port:
              number: 8006
      - path: /api/notifications
        pathType: Prefix
        backend:
          service:
            name: notification-service
            port:
              number: 8007
EOF
}

# Function to create main ingress (traffic switching)
create_main_ingress() {
    local active_namespace="$1"
    
    cat << EOF
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: reverse-tender-main
  namespace: $active_namespace
  annotations:
    kubernetes.io/ingress.class: nginx
    nginx.ingress.kubernetes.io/rewrite-target: /
    nginx.ingress.kubernetes.io/ssl-redirect: "false"
spec:
  rules:
  - host: reverse-tender.local
    http:
      paths:
      - path: /
        pathType: Prefix
        backend:
          service:
            name: gateway-service
            port:
              number: 8009
EOF
}

# Function to deploy services to environment
deploy_services_to_environment() {
    local namespace="$1"
    local environment=$(echo "$namespace" | grep -o -E "(blue|green)")
    
    header "Deploying services to $environment environment ($namespace)"
    
    local deployment_failed=false
    
    for service_config in "${SERVICES[@]}"; do
        local service_name
        service_name=$(echo "$service_config" | cut -d':' -f1)
        local service_port
        service_port=$(echo "$service_config" | cut -d':' -f2)
        local image_name
        image_name=$(echo "$service_config" | cut -d':' -f3)
        
        log "Deploying $service_name to $environment environment..."
        
        # Create deployment manifest
        local manifest
        manifest=$(create_service_deployment "$service_name" "$service_port" "$image_name" "$namespace")
        
        # Apply manifest
        if echo "$manifest" | kubectl apply -f -; then
            success "$service_name deployment created in $environment"
        else
            error "Failed to create $service_name deployment in $environment"
            deployment_failed=true
        fi
    done
    
    if [[ "$deployment_failed" == "false" ]]; then
        success "All services deployed to $environment environment"
        return 0
    else
        error "Some services failed to deploy to $environment environment"
        return 1
    fi
}

# Function to wait for deployments to be ready
wait_for_deployments() {
    local namespace="$1"
    local environment=$(echo "$namespace" | grep -o -E "(blue|green)")
    local timeout=300
    
    header "Waiting for deployments in $environment environment"
    
    local all_ready=true
    
    for service_config in "${SERVICES[@]}"; do
        local service_name
        service_name=$(echo "$service_config" | cut -d':' -f1)
        
        log "Waiting for $service_name deployment to be ready..."
        
        if kubectl wait --for=condition=Available deployment/"$service_name" -n "$namespace" --timeout="${timeout}s"; then
            success "$service_name deployment is ready"
        else
            error "$service_name deployment failed to become ready"
            all_ready=false
        fi
    done
    
    return $([[ "$all_ready" == "true" ]] && echo 0 || echo 1)
}

# Function to create ingress configurations
create_ingress_configurations() {
    header "Creating ingress configurations"
    
    # Create environment-specific ingresses
    for namespace in "$BLUE_NAMESPACE" "$GREEN_NAMESPACE"; do
        local environment=$(echo "$namespace" | grep -o -E "(blue|green)")
        
        log "Creating ingress for $environment environment..."
        
        local ingress_manifest
        ingress_manifest=$(create_ingress_config "$namespace")
        
        if echo "$ingress_manifest" | kubectl apply -f -; then
            success "Ingress created for $environment environment"
        else
            error "Failed to create ingress for $environment environment"
            return 1
        fi
    done
    
    # Create main ingress (initially pointing to blue)
    log "Creating main ingress (pointing to blue environment)..."
    
    local main_ingress
    main_ingress=$(create_main_ingress "$BLUE_NAMESPACE")
    
    if echo "$main_ingress" | kubectl apply -f -; then
        success "Main ingress created (pointing to blue environment)"
    else
        error "Failed to create main ingress"
        return 1
    fi
}

# Function to validate deployments
validate_deployments() {
    header "Validating deployments"
    
    local validation_passed=true
    
    for namespace in "$BLUE_NAMESPACE" "$GREEN_NAMESPACE"; do
        local environment=$(echo "$namespace" | grep -o -E "(blue|green)")
        
        log "Validating $environment environment..."
        
        # Check deployments
        local ready_deployments
        ready_deployments=$(kubectl get deployments -n "$namespace" --no-headers | grep -c " 2/2 ")
        local total_deployments=${#SERVICES[@]}
        
        if [[ $ready_deployments -eq $total_deployments ]]; then
            success "$environment environment: $ready_deployments/$total_deployments deployments ready"
        else
            error "$environment environment: only $ready_deployments/$total_deployments deployments ready"
            validation_passed=false
        fi
        
        # Check services
        local service_count
        service_count=$(kubectl get services -n "$namespace" --no-headers | wc -l)
        
        if [[ $service_count -eq $total_deployments ]]; then
            success "$environment environment: $service_count services created"
        else
            error "$environment environment: only $service_count/$total_deployments services created"
            validation_passed=false
        fi
        
        # Check pods
        local running_pods
        running_pods=$(kubectl get pods -n "$namespace" --no-headers | grep -c " Running ")
        local expected_pods=$((total_deployments * 2))  # 2 replicas per service
        
        if [[ $running_pods -eq $expected_pods ]]; then
            success "$environment environment: $running_pods/$expected_pods pods running"
        else
            warning "$environment environment: $running_pods/$expected_pods pods running"
        fi
    done
    
    # Check ingresses
    log "Validating ingress configurations..."
    
    local ingress_count
    ingress_count=$(kubectl get ingress --all-namespaces --no-headers | wc -l)
    
    if [[ $ingress_count -ge 3 ]]; then  # 2 environment ingresses + 1 main ingress
        success "$ingress_count ingress configurations created"
    else
        error "Only $ingress_count ingress configurations found (expected at least 3)"
        validation_passed=false
    fi
    
    return $([[ "$validation_passed" == "true" ]] && echo 0 || echo 1)
}

# Function to test connectivity
test_connectivity() {
    header "Testing connectivity"
    
    local connectivity_passed=true
    
    # Test internal connectivity
    for namespace in "$BLUE_NAMESPACE" "$GREEN_NAMESPACE"; do
        local environment=$(echo "$namespace" | grep -o -E "(blue|green)")
        
        log "Testing internal connectivity in $environment environment..."
        
        # Get a test pod
        local test_pod
        test_pod=$(kubectl get pods -n "$namespace" -l app=gateway-service -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
        
        if [[ -n "$test_pod" ]]; then
            # Test service discovery
            for service_config in "${SERVICES[@]}"; do
                local service_name
                service_name=$(echo "$service_config" | cut -d':' -f1)
                local service_port
                service_port=$(echo "$service_config" | cut -d':' -f2)
                
                if [[ "$service_name" != "gateway-service" ]]; then
                    local dns_test
                    dns_test=$(kubectl exec "$test_pod" -n "$namespace" -- nslookup "$service_name.$namespace.svc.cluster.local" 2>/dev/null | grep -c "Address:" || echo "0")
                    
                    if [[ $dns_test -gt 0 ]]; then
                        success "$environment: DNS resolution for $service_name works"
                    else
                        error "$environment: DNS resolution for $service_name failed"
                        connectivity_passed=false
                    fi
                fi
            done
        else
            error "No gateway-service pod found in $environment environment"
            connectivity_passed=false
        fi
    done
    
    return $([[ "$connectivity_passed" == "true" ]] && echo 0 || echo 1)
}

# Function to generate deployment summary
generate_deployment_summary() {
    header "Generating deployment summary"
    
    local summary_file="/tmp/blue-green-deployment-summary-$(date +%Y%m%d-%H%M%S).txt"
    
    cat > "$summary_file" << EOF
# Blue-Green Deployment Summary
Generated: $(date -Iseconds)
Cluster: $CLUSTER_NAME
Repository: $REPO_URL
Branch: $BRANCH

## Blue Environment ($BLUE_NAMESPACE)
$(kubectl get all -n "$BLUE_NAMESPACE")

## Green Environment ($GREEN_NAMESPACE)
$(kubectl get all -n "$GREEN_NAMESPACE")

## Ingress Configurations
$(kubectl get ingress --all-namespaces)

## Service Endpoints
Blue Environment:
$(kubectl get endpoints -n "$BLUE_NAMESPACE")

Green Environment:
$(kubectl get endpoints -n "$GREEN_NAMESPACE")

## Testing Commands

# Test blue environment directly
curl -H "Host: blue.reverse-tender.local" http://localhost/health

# Test green environment directly  
curl -H "Host: green.reverse-tender.local" http://localhost/health

# Test main ingress (currently pointing to blue)
curl -H "Host: reverse-tender.local" http://localhost/health

# Switch traffic to green environment
kubectl patch ingress reverse-tender-main -n $BLUE_NAMESPACE -p '{"spec":{"rules":[{"host":"reverse-tender.local","http":{"paths":[{"path":"/","pathType":"Prefix","backend":{"service":{"name":"gateway-service","port":{"number":8009}}}}]}}]}}'

# Port forward for local testing
kubectl port-forward -n ingress-nginx service/ingress-nginx-controller 8080:80

## Cleanup Commands

# Delete blue-green deployments
kubectl delete namespace $BLUE_NAMESPACE $GREEN_NAMESPACE

# Delete Kind cluster
kind delete cluster --name $CLUSTER_NAME

EOF
    
    success "Deployment summary saved to: $summary_file"
    
    info ""
    info "🎉 Blue-Green deployment complete!"
    info ""
    info "Next steps:"
    info "1. Test the deployment: ../deployment/run-all-tests.sh"
    info "2. Access services via port-forward: kubectl port-forward -n ingress-nginx service/ingress-nginx-controller 8080:80"
    info "3. Test blue environment: curl -H 'Host: blue.reverse-tender.local' http://localhost:8080/health"
    info "4. Test green environment: curl -H 'Host: green.reverse-tender.local' http://localhost:8080/health"
    info ""
    info "Summary file: $summary_file"
}

# Function to show usage
show_usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  --cluster-name NAME    Set cluster name (default: $CLUSTER_NAME)"
    echo "  --repo-url URL         Set repository URL (default: $REPO_URL)"
    echo "  --branch BRANCH        Set branch name (default: $BRANCH)"
    echo "  --image-tag TAG        Set image tag (default: v2-blue-green-deploy)"
    echo "  --skip-validation      Skip deployment validation"
    echo "  --help                 Show this help message"
}

# Main execution
main() {
    local image_tag="v2-blue-green-deploy"
    local skip_validation=false
    
    # Parse command line arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --cluster-name)
                CLUSTER_NAME="$2"
                shift 2
                ;;
            --repo-url)
                REPO_URL="$2"
                shift 2
                ;;
            --branch)
                BRANCH="$2"
                shift 2
                ;;
            --image-tag)
                image_tag="$2"
                shift 2
                ;;
            --skip-validation)
                skip_validation=true
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
    
    header "🚀 Blue-Green Environment Deployment"
    log "Logging to: $LOG_FILE"
    log "Cluster: $CLUSTER_NAME"
    log "Repository: $REPO_URL"
    log "Branch: $BRANCH"
    log "Image tag: $image_tag"
    echo ""
    
    # Check prerequisites
    check_prerequisites || exit 1
    
    # Deploy to both environments
    deploy_services_to_environment "$BLUE_NAMESPACE" || exit 1
    deploy_services_to_environment "$GREEN_NAMESPACE" || exit 1
    
    # Wait for deployments
    wait_for_deployments "$BLUE_NAMESPACE" || exit 1
    wait_for_deployments "$GREEN_NAMESPACE" || exit 1
    
    # Create ingress configurations
    create_ingress_configurations || exit 1
    
    # Validate deployments
    if [[ "$skip_validation" == "false" ]]; then
        if validate_deployments; then
            success "✅ Deployment validation passed"
        else
            error "❌ Deployment validation failed"
            exit 1
        fi
        
        # Test connectivity
        if test_connectivity; then
            success "✅ Connectivity tests passed"
        else
            warning "⚠️ Some connectivity tests failed"
        fi
    fi
    
    # Generate summary
    generate_deployment_summary
    
    success "🎉 Blue-Green environment deployment complete!"
}

# Run main function with all arguments
main "$@"

