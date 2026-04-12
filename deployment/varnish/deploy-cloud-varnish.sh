#!/bin/bash

# Cloud-Specific Varnish Deployment Script
# Supports DigitalOcean and Linode cloud providers with optimized configurations

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
CLOUD_PROVIDER="${1:-digitalocean}"  # digitalocean, linode
DEPLOYMENT_TYPE="${2:-docker}"       # docker, kubernetes, marketplace
INSTANCE_SIZE="${3:-standard}"       # small, standard, large

echo -e "${BLUE}🌐 Cloud Varnish Deployment Script${NC}"
echo -e "${BLUE}Cloud Provider: ${CLOUD_PROVIDER}${NC}"
echo -e "${BLUE}Deployment Type: ${DEPLOYMENT_TYPE}${NC}"
echo -e "${BLUE}Instance Size: ${INSTANCE_SIZE}${NC}"

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

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to get cloud-specific configuration
get_cloud_config() {
    case "$CLOUD_PROVIDER" in
        "digitalocean"|"do")
            CLOUD_CONFIG_FILE="cloud/digitalocean-droplet.yml"
            CLOUD_NAME="DigitalOcean"
            CLOUD_CLI="doctl"
            ;;
        "linode")
            CLOUD_CONFIG_FILE="cloud/linode-instance.yml"
            CLOUD_NAME="Linode"
            CLOUD_CLI="linode-cli"
            ;;
        *)
            print_error "Unsupported cloud provider: $CLOUD_PROVIDER"
            show_usage
            exit 1
            ;;
    esac
}

# Function to check cloud CLI availability
check_cloud_cli() {
    if ! command_exists "$CLOUD_CLI"; then
        print_warning "$CLOUD_CLI not found. Some features may not be available."
        print_info "Install $CLOUD_CLI for full cloud integration:"
        case "$CLOUD_PROVIDER" in
            "digitalocean"|"do")
                echo "  - Install: https://docs.digitalocean.com/reference/doctl/how-to/install/"
                echo "  - Auth: doctl auth init"
                ;;
            "linode")
                echo "  - Install: pip3 install linode-cli"
                echo "  - Auth: linode-cli configure"
                ;;
        esac
        return 1
    else
        print_status "$CLOUD_CLI found and available"
        return 0
    fi
}

# Function to deploy with Docker Compose (cloud-optimized)
deploy_docker_cloud() {
    print_status "Deploying Varnish with Docker Compose on $CLOUD_NAME..."
    
    if [ ! -f "$CLOUD_CONFIG_FILE" ]; then
        print_error "Cloud configuration file not found: $CLOUD_CONFIG_FILE"
        exit 1
    fi
    
    # Create network if it doesn't exist
    docker network create reverse-tender-network 2>/dev/null || true
    
    # Create storage directory
    sudo mkdir -p /opt/varnish/storage
    sudo chown -R $USER:$USER /opt/varnish/storage
    
    # Deploy with cloud-specific configuration
    docker-compose -f "$CLOUD_CONFIG_FILE" up -d
    
    # Wait for Varnish to be ready
    echo "Waiting for Varnish to be ready..."
    sleep 15
    
    # Test Varnish
    if curl -f http://localhost:80/health >/dev/null 2>&1; then
        print_status "Varnish is running and healthy on $CLOUD_NAME"
    else
        print_warning "Varnish may not be fully ready yet"
    fi
    
    print_status "Varnish deployed successfully on $CLOUD_NAME"
    show_access_info
}

# Function to deploy marketplace solution
deploy_marketplace() {
    case "$CLOUD_PROVIDER" in
        "digitalocean"|"do")
            deploy_digitalocean_marketplace
            ;;
        "linode")
            deploy_linode_marketplace
            ;;
        *)
            print_error "Marketplace deployment not supported for $CLOUD_PROVIDER"
            exit 1
            ;;
    esac
}

# Function to deploy DigitalOcean marketplace
deploy_digitalocean_marketplace() {
    print_status "Deploying DigitalOcean Marketplace Varnish Cache..."
    
    if check_cloud_cli; then
        # Get available regions
        print_info "Available regions:"
        doctl compute region list --format Slug,Name,Available 2>/dev/null || echo "Run 'doctl auth init' to authenticate"
        
        # Get available sizes
        print_info "Recommended droplet sizes for Varnish:"
        echo "  - s-1vcpu-1gb ($6/month) - Testing/Development"
        echo "  - s-2vcpu-2gb ($12/month) - Small production"
        echo "  - s-2vcpu-4gb ($24/month) - Medium production"
        echo "  - s-4vcpu-8gb ($48/month) - Large production"
        
        print_info "To create a Varnish droplet:"
        echo "1. Visit: https://marketplace.digitalocean.com/apps/varnish-cache"
        echo "2. Click 'Create Varnish Cache Droplet'"
        echo "3. Configure your droplet settings"
        echo "4. Deploy and wait for setup to complete"
        
        # Optionally create droplet via CLI
        read -p "Create droplet via CLI? (y/N): " create_droplet
        if [[ $create_droplet =~ ^[Yy]$ ]]; then
            create_digitalocean_droplet
        fi
    else
        print_info "Manual deployment steps:"
        echo "1. Visit: https://marketplace.digitalocean.com/apps/varnish-cache"
        echo "2. Click 'Create Varnish Cache Droplet'"
        echo "3. Select region and droplet size"
        echo "4. Add SSH keys and configure settings"
        echo "5. Create droplet and wait for deployment"
    fi
}

# Function to create DigitalOcean droplet via CLI
create_digitalocean_droplet() {
    local size="s-2vcpu-2gb"
    local region="nyc3"
    local image="varnish-cache-20-04"  # Marketplace image slug
    
    case "$INSTANCE_SIZE" in
        "small")
            size="s-1vcpu-1gb"
            ;;
        "standard")
            size="s-2vcpu-2gb"
            ;;
        "large")
            size="s-4vcpu-8gb"
            ;;
    esac
    
    print_info "Creating DigitalOcean droplet with Varnish..."
    print_info "Size: $size, Region: $region"
    
    # Create droplet
    if doctl compute droplet create varnish-cache-$(date +%s) \
        --image "$image" \
        --size "$size" \
        --region "$region" \
        --ssh-keys $(doctl compute ssh-key list --format ID --no-header | tr '\n' ',' | sed 's/,$//') \
        --enable-monitoring \
        --enable-ipv6 \
        --wait; then
        print_status "DigitalOcean droplet created successfully!"
        
        # Get droplet IP
        local droplet_ip=$(doctl compute droplet list --format Name,PublicIPv4 --no-header | grep varnish-cache | awk '{print $2}')
        if [ -n "$droplet_ip" ]; then
            print_status "Droplet IP: $droplet_ip"
            print_info "SSH access: ssh root@$droplet_ip"
            print_info "Varnish will be available at: http://$droplet_ip"
        fi
    else
        print_error "Failed to create DigitalOcean droplet"
    fi
}

# Function to deploy Linode marketplace
deploy_linode_marketplace() {
    print_status "Deploying Linode Varnish instance..."
    
    if check_cloud_cli; then
        # Get available regions
        print_info "Available regions:"
        linode-cli regions list --text --no-headers --format="id,country" 2>/dev/null || echo "Run 'linode-cli configure' to authenticate"
        
        # Get available types
        print_info "Recommended Linode types for Varnish:"
        echo "  - g6-nanode-1 ($5/month) - Testing/Development"
        echo "  - g6-standard-1 ($10/month) - Small production"
        echo "  - g6-standard-2 ($20/month) - Medium production"
        echo "  - g6-standard-4 ($40/month) - Large production"
        
        print_info "To create a Linode instance with Varnish:"
        echo "1. Use Linode's One-Click Apps or manual installation"
        echo "2. Follow: https://www.linode.com/docs/guides/getting-started-with-varnish-cache/"
        
        # Optionally create instance via CLI
        read -p "Create Linode instance via CLI? (y/N): " create_instance
        if [[ $create_instance =~ ^[Yy]$ ]]; then
            create_linode_instance
        fi
    else
        print_info "Manual deployment steps:"
        echo "1. Create a new Linode instance"
        echo "2. Choose Ubuntu 22.04 LTS"
        echo "3. Follow the Varnish installation guide"
        echo "4. Use our configuration files for setup"
    fi
}

# Function to create Linode instance via CLI
create_linode_instance() {
    local type="g6-standard-2"
    local region="us-east"
    local image="linode/ubuntu22.04"
    
    case "$INSTANCE_SIZE" in
        "small")
            type="g6-standard-1"
            ;;
        "standard")
            type="g6-standard-2"
            ;;
        "large")
            type="g6-standard-4"
            ;;
    esac
    
    print_info "Creating Linode instance for Varnish..."
    print_info "Type: $type, Region: $region"
    
    # Create instance
    if linode-cli linodes create \
        --type "$type" \
        --region "$region" \
        --image "$image" \
        --label "varnish-cache-$(date +%s)" \
        --root_pass "$(openssl rand -base64 32)" \
        --authorized_keys "$(cat ~/.ssh/id_rsa.pub 2>/dev/null || echo '')" \
        --backups_enabled true; then
        print_status "Linode instance created successfully!"
        
        # Get instance IP
        local instance_ip=$(linode-cli linodes list --text --no-headers --format="label,ipv4" | grep varnish-cache | awk '{print $2}' | head -1)
        if [ -n "$instance_ip" ]; then
            print_status "Instance IP: $instance_ip"
            print_info "SSH access: ssh root@$instance_ip"
            print_info "Install Varnish: apt update && apt install -y varnish"
        fi
    else
        print_error "Failed to create Linode instance"
    fi
}

# Function to show access information
show_access_info() {
    echo ""
    print_status "🎉 Varnish deployment completed on $CLOUD_NAME!"
    echo ""
    echo -e "${BLUE}📋 Access Information:${NC}"
    echo "- Varnish HTTP: http://localhost:80"
    echo "- Admin Interface: http://localhost:6081"
    echo "- Prometheus Metrics: http://localhost:9131/metrics"
    echo ""
    echo -e "${BLUE}🔧 Management Commands:${NC}"
    echo "- Monitor: ./monitor-varnish.sh monitor"
    echo "- Statistics: ./monitor-varnish.sh stats"
    echo "- Purge cache: ./monitor-varnish.sh purge '/api/auctions.*'"
    echo ""
    echo -e "${BLUE}📊 Cloud-Specific Features:${NC}"
    case "$CLOUD_PROVIDER" in
        "digitalocean"|"do")
            echo "- DigitalOcean Monitoring: Enabled"
            echo "- Droplet Backups: Configure in control panel"
            echo "- Load Balancer: Available for multiple instances"
            echo "- Spaces Integration: Configure for static assets"
            ;;
        "linode")
            echo "- Longview Monitoring: Available"
            echo "- NodeBalancer: Available for load balancing"
            echo "- Block Storage: Configure for cache persistence"
            echo "- Object Storage: Available for static assets"
            ;;
    esac
}

# Function to show usage information
show_usage() {
    echo "Cloud Varnish Deployment Script"
    echo ""
    echo "Usage: $0 [cloud_provider] [deployment_type] [instance_size]"
    echo ""
    echo "Cloud Providers:"
    echo "  digitalocean, do  - DigitalOcean (default)"
    echo "  linode           - Linode"
    echo ""
    echo "Deployment Types:"
    echo "  docker           - Docker Compose deployment (default)"
    echo "  kubernetes       - Kubernetes deployment"
    echo "  marketplace      - Cloud marketplace deployment"
    echo ""
    echo "Instance Sizes:"
    echo "  small            - 1-2 CPU, 1-2GB RAM"
    echo "  standard         - 2 CPU, 2-4GB RAM (default)"
    echo "  large            - 4+ CPU, 8+ GB RAM"
    echo ""
    echo "Examples:"
    echo "  $0 digitalocean docker standard"
    echo "  $0 linode marketplace small"
    echo "  $0 do kubernetes large"
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
get_cloud_config

case "$DEPLOYMENT_TYPE" in
    "docker")
        deploy_docker_cloud
        ;;
    "kubernetes"|"k8s")
        print_info "Using standard Kubernetes deployment..."
        ./deploy-varnish.sh kubernetes
        ;;
    "marketplace")
        deploy_marketplace
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
