#!/bin/bash

# Varnish Cache Deployment Script
# This script deploys Varnish cache server for the Reverse Tender application

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
VARNISH_VERSION="7.4"
VARNISH_MEMORY="256M"
VARNISH_PORT="80"
VARNISH_ADMIN_PORT="6081"
DEPLOYMENT_TYPE="${1:-docker}" # docker, kubernetes, or standalone

echo -e "${BLUE}🚀 Starting Varnish Cache Deployment${NC}"
echo -e "${BLUE}Deployment Type: ${DEPLOYMENT_TYPE}${NC}"

# Function to print colored output
print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to deploy with Docker Compose
deploy_docker() {
    print_status "Deploying Varnish with Docker Compose..."
    
    if ! command_exists docker-compose; then
        print_error "Docker Compose is not installed"
        exit 1
    fi
    
    # Create network if it doesn't exist
    docker network create reverse-tender-network 2>/dev/null || true
    
    # Deploy Varnish
    docker-compose -f docker-compose.varnish.yml up -d
    
    # Wait for Varnish to be ready
    echo "Waiting for Varnish to be ready..."
    sleep 10
    
    # Test Varnish
    if curl -f http://localhost:${VARNISH_PORT}/health >/dev/null 2>&1; then
        print_status "Varnish is running and healthy"
    else
        print_warning "Varnish may not be fully ready yet"
    fi
    
    print_status "Varnish deployed successfully with Docker Compose"
    echo -e "${BLUE}Access Varnish at: http://localhost:${VARNISH_PORT}${NC}"
    echo -e "${BLUE}Admin interface at: http://localhost:${VARNISH_ADMIN_PORT}${NC}"
}

# Function to deploy with Kubernetes
deploy_kubernetes() {
    print_status "Deploying Varnish with Kubernetes..."
    
    if ! command_exists kubectl; then
        print_error "kubectl is not installed"
        exit 1
    fi
    
    # Create namespace if it doesn't exist
    kubectl create namespace reverse-tender 2>/dev/null || true
    
    # Apply Kubernetes manifests
    kubectl apply -f k8s-varnish.yaml
    
    # Wait for deployment to be ready
    echo "Waiting for Varnish deployment to be ready..."
    kubectl wait --for=condition=available --timeout=300s deployment/varnish -n reverse-tender
    
    # Get service information
    VARNISH_SERVICE=$(kubectl get svc varnish -n reverse-tender -o jsonpath='{.status.loadBalancer.ingress[0].ip}')
    if [ -z "$VARNISH_SERVICE" ]; then
        VARNISH_SERVICE=$(kubectl get svc varnish -n reverse-tender -o jsonpath='{.spec.clusterIP}')
    fi
    
    print_status "Varnish deployed successfully with Kubernetes"
    echo -e "${BLUE}Varnish Service IP: ${VARNISH_SERVICE}${NC}"
    echo -e "${BLUE}Port: ${VARNISH_PORT}${NC}"
}

# Function to deploy standalone
deploy_standalone() {
    print_status "Installing Varnish standalone..."
    
    # Detect OS
    if [[ "$OSTYPE" == "linux-gnu"* ]]; then
        # Ubuntu/Debian
        if command_exists apt-get; then
            sudo apt-get update
            sudo apt-get install -y varnish
        # CentOS/RHEL
        elif command_exists yum; then
            sudo yum install -y epel-release
            sudo yum install -y varnish
        # Fedora
        elif command_exists dnf; then
            sudo dnf install -y varnish
        else
            print_error "Unsupported Linux distribution"
            exit 1
        fi
    elif [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS
        if command_exists brew; then
            brew install varnish
        else
            print_error "Homebrew is required for macOS installation"
            exit 1
        fi
    else
        print_error "Unsupported operating system"
        exit 1
    fi
    
    # Copy configuration
    sudo cp varnish.vcl /etc/varnish/default.vcl
    
    # Configure Varnish service
    if [[ "$OSTYPE" == "linux-gnu"* ]]; then
        # Create systemd override
        sudo mkdir -p /etc/systemd/system/varnish.service.d/
        cat << EOF | sudo tee /etc/systemd/system/varnish.service.d/override.conf
[Service]
ExecStart=
ExecStart=/usr/sbin/varnishd \\
    -a :${VARNISH_PORT} \\
    -T localhost:${VARNISH_ADMIN_PORT} \\
    -f /etc/varnish/default.vcl \\
    -s malloc,${VARNISH_MEMORY} \\
    -p default_ttl=300 \\
    -p default_grace=3600
EOF
        
        # Reload and start service
        sudo systemctl daemon-reload
        sudo systemctl enable varnish
        sudo systemctl restart varnish
        
        # Check status
        if sudo systemctl is-active --quiet varnish; then
            print_status "Varnish service is running"
        else
            print_error "Failed to start Varnish service"
            exit 1
        fi
    fi
    
    print_status "Varnish installed and configured successfully"
}

# Function to test Varnish installation
test_varnish() {
    print_status "Testing Varnish installation..."
    
    local test_url="http://localhost:${VARNISH_PORT}/health"
    
    # Test basic connectivity
    if curl -f "$test_url" >/dev/null 2>&1; then
        print_status "Varnish is responding to requests"
    else
        print_warning "Varnish may not be fully ready or health endpoint not available"
    fi
    
    # Test cache headers
    echo "Testing cache headers..."
    CACHE_HEADER=$(curl -s -I "$test_url" | grep -i "x-cache" || echo "No cache header found")
    echo "Cache status: $CACHE_HEADER"
    
    # Test admin interface (if accessible)
    if curl -f "http://localhost:${VARNISH_ADMIN_PORT}" >/dev/null 2>&1; then
        print_status "Varnish admin interface is accessible"
    else
        print_warning "Varnish admin interface may not be accessible from this host"
    fi
}

# Function to show usage information
show_usage() {
    echo "Usage: $0 [deployment_type]"
    echo ""
    echo "Deployment types:"
    echo "  docker      - Deploy using Docker Compose (default)"
    echo "  kubernetes  - Deploy using Kubernetes"
    echo "  standalone  - Install Varnish directly on the system"
    echo ""
    echo "Examples:"
    echo "  $0 docker"
    echo "  $0 kubernetes"
    echo "  $0 standalone"
}

# Function to cleanup on exit
cleanup() {
    if [ $? -ne 0 ]; then
        print_error "Deployment failed!"
        echo "Check the logs above for more information."
    fi
}

trap cleanup EXIT

# Main deployment logic
case "$DEPLOYMENT_TYPE" in
    "docker")
        deploy_docker
        ;;
    "kubernetes"|"k8s")
        deploy_kubernetes
        ;;
    "standalone"|"local")
        deploy_standalone
        ;;
    "help"|"-h"|"--help")
        show_usage
        exit 0
        ;;
    *)
        print_error "Unknown deployment type: $DEPLOYMENT_TYPE"
        show_usage
        exit 1
        ;;
esac

# Test the deployment
test_varnish

print_status "Varnish deployment completed successfully!"
echo ""
echo -e "${BLUE}📋 Next Steps:${NC}"
echo "1. Update your application configuration to use Varnish"
echo "2. Configure your load balancer to point to Varnish"
echo "3. Monitor Varnish performance and adjust configuration as needed"
echo ""
echo -e "${BLUE}📊 Monitoring:${NC}"
echo "- Varnish stats: varnishstat"
echo "- Varnish log: varnishlog"
echo "- Admin commands: varnishadm -T localhost:${VARNISH_ADMIN_PORT}"
echo ""
echo -e "${BLUE}🔧 Configuration:${NC}"
echo "- VCL file: /etc/varnish/default.vcl"
echo "- Memory: ${VARNISH_MEMORY}"
echo "- Port: ${VARNISH_PORT}"
echo "- Admin port: ${VARNISH_ADMIN_PORT}"
