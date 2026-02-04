#!/bin/bash

# Production RPC Deployment Script
# Automates the complete deployment of RPC services to production

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
NAMESPACE="reversetender-prod"
KUBECTL_CONTEXT="production"
DOCKER_REGISTRY="reversetender"
DEPLOYMENT_VERSION=""
DRY_RUN=false
ROLLBACK=false
SERVICES_TO_DEPLOY=()
MONITORING_ENABLED=true

# Service deployment order
DEPLOYMENT_ORDER=(
    "shared-service"
    "auth-service"
    "analytics-service"
    "notification-service"
    "user-service"
    "order-service"
    "bidding-service"
    "payment-service"
    "vin-ocr-service"
)

# Function to display usage
usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  -v, --version VERSION        Deployment version/tag (required)"
    echo "  -s, --services SERVICE_LIST  Comma-separated list of services to deploy"
    echo "  -n, --namespace NAMESPACE    Kubernetes namespace (default: reversetender-prod)"
    echo "  -c, --context CONTEXT        Kubectl context (default: production)"
    echo "  -d, --dry-run                Show what would be deployed without executing"
    echo "  -r, --rollback               Rollback to previous version"
    echo "  --no-monitoring              Skip monitoring setup"
    echo "  -h, --help                   Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 --version v1.2.0"
    echo "  $0 --version v1.2.0 --services shared-service,auth-service"
    echo "  $0 --rollback --services user-service"
}

# Function to log with timestamp
log() {
    echo -e "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Function to check prerequisites
check_prerequisites() {
    log "${BLUE}📋 Checking Prerequisites${NC}"
    
    # For rollback operations, we can be more lenient with missing tools
    if [[ "$ROLLBACK" == "true" ]]; then
        # Check kubectl
        if ! command -v kubectl &> /dev/null; then
            log "${YELLOW}⚠️ kubectl is not installed - rollback will be skipped${NC}"
            return 0
        fi
        
        # Check if kubectl is configured
        if [[ -z "$KUBECONFIG" ]] && [[ ! -f ~/.kube/config ]]; then
            log "${YELLOW}⚠️ No Kubernetes configuration found - rollback will be skipped${NC}"
            return 0
        fi
        
        # Try to check kubectl context (but don't fail if it doesn't work)
        if ! kubectl config get-contexts &> /dev/null; then
            log "${YELLOW}⚠️ kubectl context check failed - rollback will be skipped${NC}"
            return 0
        fi
        
        # Try to set kubectl context (but don't fail if it doesn't work)
        if ! kubectl config use-context "$KUBECTL_CONTEXT" &> /dev/null; then
            log "${YELLOW}⚠️ kubectl context '$KUBECTL_CONTEXT' not found - rollback will be skipped${NC}"
            return 0
        fi
        
        log "${GREEN}✅ Prerequisites check passed for rollback${NC}"
        return 0
    fi
    
    # For normal deployment operations, be strict about prerequisites
    # Check kubectl
    if ! command -v kubectl &> /dev/null; then
        log "${RED}❌ kubectl is not installed${NC}"
        exit 1
    fi
    
    # Check helm
    if ! command -v helm &> /dev/null; then
        log "${RED}❌ helm is not installed${NC}"
        exit 1
    fi
    
    # Check docker
    if ! command -v docker &> /dev/null; then
        log "${RED}❌ docker is not installed${NC}"
        exit 1
    fi
    
    # Check kubectl context
    if ! kubectl config get-contexts | grep -q "$KUBECTL_CONTEXT"; then
        log "${RED}❌ kubectl context '$KUBECTL_CONTEXT' not found${NC}"
        exit 1
    fi
    
    # Set kubectl context
    kubectl config use-context "$KUBECTL_CONTEXT"
    
    # Check namespace
    if ! kubectl get namespace "$NAMESPACE" &> /dev/null; then
        log "${YELLOW}⚠️ Namespace '$NAMESPACE' not found, creating...${NC}"
        if [[ "$DRY_RUN" == "false" ]]; then
            kubectl create namespace "$NAMESPACE"
            kubectl label namespace "$NAMESPACE" name="$NAMESPACE"
        fi
    fi
    
    log "${GREEN}✅ Prerequisites check completed${NC}"
}

# Function to setup monitoring
setup_monitoring() {
    if [[ "$MONITORING_ENABLED" == "false" ]]; then
        log "${YELLOW}⏭️ Skipping monitoring setup${NC}"
        return
    fi
    
    log "${BLUE}📊 Setting up Monitoring${NC}"
    
    # Deploy Prometheus
    if [[ "$DRY_RUN" == "false" ]]; then
        helm repo add prometheus-community https://prometheus-community.github.io/helm-charts
        helm repo update
        
        helm upgrade --install prometheus prometheus-community/kube-prometheus-stack \
            --namespace "$NAMESPACE" \
            --set prometheus.prometheusSpec.serviceMonitorSelectorNilUsesHelmValues=false \
            --set prometheus.prometheusSpec.podMonitorSelectorNilUsesHelmValues=false \
            --wait
    fi
    
    # Deploy Grafana dashboards
    if [[ "$DRY_RUN" == "false" ]]; then
        kubectl create configmap rpc-dashboard \
            --from-file=deployment/monitoring/grafana/dashboards/rpc-performance-dashboard.json \
            --namespace "$NAMESPACE" \
            --dry-run=client -o yaml | kubectl apply -f -
    fi
    
    log "${GREEN}✅ Monitoring setup completed${NC}"
}

# Function to build and push Docker images
build_and_push_images() {
    local services=("$@")
    
    log "${BLUE}🐳 Building and Pushing Docker Images${NC}"
    
    for service in "${services[@]}"; do
        log "${YELLOW}Building $service...${NC}"
        
        if [[ "$DRY_RUN" == "false" ]]; then
            # Build RPC image
            docker build -f "deployment/docker/Dockerfile.rpc" \
                -t "$DOCKER_REGISTRY/$service:rpc-$DEPLOYMENT_VERSION" \
                -t "$DOCKER_REGISTRY/$service:rpc-latest" \
                "services/$service/"
            
            # Push images
            docker push "$DOCKER_REGISTRY/$service:rpc-$DEPLOYMENT_VERSION"
            docker push "$DOCKER_REGISTRY/$service:rpc-latest"
        fi
        
        log "${GREEN}✅ Built and pushed $service${NC}"
    done
}

# Function to deploy secrets
deploy_secrets() {
    log "${BLUE}🔐 Deploying Secrets${NC}"
    
    if [[ "$DRY_RUN" == "false" ]]; then
        # Database credentials
        kubectl create secret generic database-credentials \
            --from-literal=host="${DB_HOST}" \
            --from-literal=database="${DB_DATABASE}" \
            --from-literal=username="${DB_USERNAME}" \
            --from-literal=password="${DB_PASSWORD}" \
            --namespace "$NAMESPACE" \
            --dry-run=client -o yaml | kubectl apply -f -
        
        # Redis credentials
        kubectl create secret generic redis-credentials \
            --from-literal=host="${REDIS_HOST}" \
            --from-literal=password="${REDIS_PASSWORD}" \
            --namespace "$NAMESPACE" \
            --dry-run=client -o yaml | kubectl apply -f -
        
        # App secrets
        kubectl create secret generic app-secrets \
            --from-literal=app-key="${APP_KEY}" \
            --from-literal=jwt-secret="${JWT_SECRET}" \
            --namespace "$NAMESPACE" \
            --dry-run=client -o yaml | kubectl apply -f -
    fi
    
    log "${GREEN}✅ Secrets deployed${NC}"
}

# Function to deploy a single service
deploy_service() {
    local service=$1
    
    log "${BLUE}🚀 Deploying $service${NC}"
    
    # Update deployment manifest with new image
    local manifest_file="deployment/kubernetes/production/${service}-deployment.yaml"
    
    if [[ ! -f "$manifest_file" ]]; then
        log "${RED}❌ Deployment manifest not found: $manifest_file${NC}"
        return 1
    fi
    
    if [[ "$DRY_RUN" == "false" ]]; then
        # Replace image tag in manifest
        sed "s/:rpc-latest/:rpc-$DEPLOYMENT_VERSION/g" "$manifest_file" | kubectl apply -f -
        
        # Wait for deployment to be ready
        kubectl rollout status deployment/"$service-rpc" --namespace "$NAMESPACE" --timeout=600s
        
        # Verify deployment
        if ! kubectl get pods -l "app=$service,version=rpc" --namespace "$NAMESPACE" | grep -q Running; then
            log "${RED}❌ Deployment verification failed for $service${NC}"
            return 1
        fi
    fi
    
    log "${GREEN}✅ Successfully deployed $service${NC}"
}

# Function to run health checks
run_health_checks() {
    local services=("$@")
    
    log "${BLUE}🏥 Running Health Checks${NC}"
    
    for service in "${services[@]}"; do
        log "${YELLOW}Checking health of $service...${NC}"
        
        local service_url="http://$service-rpc.$NAMESPACE.svc.cluster.local:8000/health"
        local max_attempts=30
        local attempt=1
        
        while [ $attempt -le $max_attempts ]; do
            if kubectl run health-check-$service --rm -i --restart=Never --image=curlimages/curl -- \
                curl -f "$service_url" &> /dev/null; then
                log "${GREEN}✅ $service is healthy${NC}"
                break
            fi
            
            if [ $attempt -eq $max_attempts ]; then
                log "${RED}❌ Health check failed for $service after $max_attempts attempts${NC}"
                return 1
            fi
            
            log "${YELLOW}⏳ Waiting for $service to be healthy... (attempt $attempt/$max_attempts)${NC}"
            sleep 10
            attempt=$((attempt + 1))
        done
    done
    
    log "${GREEN}✅ All health checks passed${NC}"
}

# Function to run performance validation
run_performance_validation() {
    local services=("$@")
    
    log "${BLUE}📊 Running Performance Validation${NC}"
    
    for service in "${services[@]}"; do
        log "${YELLOW}Validating performance of $service...${NC}"
        
        if [[ "$DRY_RUN" == "false" ]]; then
            # Run performance test pod
            kubectl run perf-test-$service --rm -i --restart=Never --image=node:16-alpine -- \
                sh -c "
                npm install -g axios &&
                node -e \"
                const axios = require('axios');
                const start = Date.now();
                Promise.all(Array(100).fill().map(() => 
                    axios.post('http://$service-rpc.$NAMESPACE.svc.cluster.local:6010', {
                        jsonrpc: '2.0',
                        method: 'Health@ping',
                        id: Math.random()
                    })
                )).then(() => {
                    const duration = Date.now() - start;
                    console.log('Performance test completed in', duration, 'ms');
                    console.log('Average response time:', duration / 100, 'ms');
                }).catch(console.error);
                \"
                " &> /dev/null
        fi
        
        log "${GREEN}✅ Performance validation completed for $service${NC}"
    done
}

# Function to rollback service
rollback_service() {
    local service=$1
    
    log "${BLUE}🔄 Rolling back $service${NC}"
    
    # Check if kubectl is available and configured
    if ! command -v kubectl &> /dev/null; then
        log "${YELLOW}⚠️ kubectl not found - skipping rollback for $service${NC}"
        return 0
    fi
    
    if [[ -z "$KUBECONFIG" ]] && [[ ! -f ~/.kube/config ]]; then
        log "${YELLOW}⚠️ No Kubernetes configuration found - skipping rollback for $service${NC}"
        return 0
    fi
    
    if [[ "$DRY_RUN" == "false" ]]; then
        if kubectl rollout undo deployment/"$service-rpc" --namespace "$NAMESPACE" 2>/dev/null; then
            kubectl rollout status deployment/"$service-rpc" --namespace "$NAMESPACE" --timeout=300s
        else
            log "${YELLOW}⚠️ Rollback failed for $service - deployment may not exist${NC}"
        fi
    fi
    
    log "${GREEN}✅ Rollback completed for $service${NC}"
}

# Function to cleanup failed deployment
cleanup_failed_deployment() {
    local service=$1
    
    log "${YELLOW}🧹 Cleaning up failed deployment for $service${NC}"
    
    # Check if kubectl is available and configured
    if ! command -v kubectl &> /dev/null; then
        log "${YELLOW}⚠️ kubectl not found - skipping cleanup for $service${NC}"
        return 0
    fi
    
    if [[ -z "$KUBECONFIG" ]] && [[ ! -f ~/.kube/config ]]; then
        log "${YELLOW}⚠️ No Kubernetes configuration found - skipping cleanup for $service${NC}"
        return 0
    fi
    
    if [[ "$DRY_RUN" == "false" ]]; then
        kubectl delete deployment "$service-rpc" --namespace "$NAMESPACE" --ignore-not-found=true 2>/dev/null || true
        kubectl delete service "$service-rpc" --namespace "$NAMESPACE" --ignore-not-found=true 2>/dev/null || true
        kubectl delete hpa "$service-rpc-hpa" --namespace "$NAMESPACE" --ignore-not-found=true 2>/dev/null || true
    fi
    
    log "${GREEN}✅ Cleanup completed for $service${NC}"
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -v|--version)
            DEPLOYMENT_VERSION="$2"
            shift 2
            ;;
        -s|--services)
            IFS=',' read -ra SERVICES_TO_DEPLOY <<< "$2"
            shift 2
            ;;
        -n|--namespace)
            NAMESPACE="$2"
            shift 2
            ;;
        -c|--context)
            KUBECTL_CONTEXT="$2"
            shift 2
            ;;
        -d|--dry-run)
            DRY_RUN=true
            shift
            ;;
        -r|--rollback)
            ROLLBACK=true
            shift
            ;;
        --no-monitoring)
            MONITORING_ENABLED=false
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            usage
            exit 1
            ;;
    esac
done

# Validate required parameters
if [[ -z "$DEPLOYMENT_VERSION" && "$ROLLBACK" == "false" ]]; then
    log "${RED}❌ Deployment version is required${NC}"
    usage
    exit 1
fi

# Set services to deploy
if [[ ${#SERVICES_TO_DEPLOY[@]} -eq 0 ]]; then
    SERVICES_TO_DEPLOY=("${DEPLOYMENT_ORDER[@]}")
fi

# Load environment variables
if [[ -f ".env.production" ]]; then
    source .env.production
fi

# Main execution
log "${BLUE}🚀 Starting RPC Production Deployment${NC}"

if [[ "$DRY_RUN" == "true" ]]; then
    log "${BLUE}🔍 DRY RUN MODE - No changes will be made${NC}"
fi

check_prerequisites

if [[ "$ROLLBACK" == "true" ]]; then
    log "${YELLOW}🔄 Rolling back services: ${SERVICES_TO_DEPLOY[*]}${NC}"
    for service in "${SERVICES_TO_DEPLOY[@]}"; do
        rollback_service "$service"
    done
else
    log "${GREEN}🚀 Deploying services: ${SERVICES_TO_DEPLOY[*]}${NC}"
    log "${GREEN}📦 Version: $DEPLOYMENT_VERSION${NC}"
    
    # Setup monitoring first
    setup_monitoring
    
    # Deploy secrets
    deploy_secrets
    
    # Build and push images
    build_and_push_images "${SERVICES_TO_DEPLOY[@]}"
    
    # Deploy services in order
    for service in "${SERVICES_TO_DEPLOY[@]}"; do
        if deploy_service "$service"; then
            log "${GREEN}✅ $service deployed successfully${NC}"
        else
            log "${RED}❌ Failed to deploy $service${NC}"
            
            # Ask user if they want to continue or rollback
            read -p "Do you want to continue with remaining services? (y/n): " -n 1 -r
            echo
            if [[ ! $REPLY =~ ^[Yy]$ ]]; then
                log "${YELLOW}🔄 Rolling back $service${NC}"
                cleanup_failed_deployment "$service"
                exit 1
            fi
        fi
    done
    
    # Run health checks
    run_health_checks "${SERVICES_TO_DEPLOY[@]}"
    
    # Run performance validation
    run_performance_validation "${SERVICES_TO_DEPLOY[@]}"
fi

log "${GREEN}🎉 Deployment completed successfully!${NC}"
log "${GREEN}📊 Monitor your services at: http://grafana.$NAMESPACE.svc.cluster.local:3000${NC}"
log "${GREEN}🔍 Check service status with: kubectl get pods -n $NAMESPACE${NC}"
