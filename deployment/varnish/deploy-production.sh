#!/bin/bash

# Production Varnish Deployment Script
# Deploys optimized Varnish configuration for production environments

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
DEPLOYMENT_TYPE="${1:-docker}"  # docker, kubernetes, cloud
CLOUD_PROVIDER="${2:-digitalocean}"  # digitalocean, linode
INSTANCE_SIZE="${3:-large}"  # small, standard, large

echo -e "${BLUE}🚀 Production Varnish Deployment${NC}"
echo -e "${BLUE}Deployment Type: ${DEPLOYMENT_TYPE}${NC}"
echo -e "${BLUE}Cloud Provider: ${CLOUD_PROVIDER}${NC}"
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

# Function to check prerequisites
check_prerequisites() {
    print_info "Checking prerequisites..."
    
    # Check if Docker is available
    if ! command -v docker &> /dev/null; then
        print_error "Docker is required but not installed"
        exit 1
    fi
    
    # Check if Docker Compose is available
    if ! command -v docker-compose &> /dev/null; then
        print_error "Docker Compose is required but not installed"
        exit 1
    fi
    
    # Check if required files exist
    if [ ! -f "production.vcl" ]; then
        print_error "production.vcl not found"
        exit 1
    fi
    
    if [ ! -f "docker-compose.production.yml" ]; then
        print_error "docker-compose.production.yml not found"
        exit 1
    fi
    
    print_status "Prerequisites check passed"
}

# Function to setup production environment
setup_production_environment() {
    print_info "Setting up production environment..."
    
    # Create necessary directories
    sudo mkdir -p /opt/varnish/storage
    sudo mkdir -p /opt/varnish/logs
    sudo mkdir -p /opt/varnish/config
    sudo mkdir -p /opt/varnish/backups
    
    # Set proper permissions
    sudo chown -R $USER:$USER /opt/varnish/
    
    # Create network if it doesn't exist
    docker network create reverse-tender-network 2>/dev/null || true
    
    print_status "Production environment setup complete"
}

# Function to deploy with Docker Compose
deploy_docker_production() {
    print_info "Deploying Varnish with Docker Compose (Production)..."
    
    # Set production environment variables
    export VARNISH_SIZE="4G"
    export VARNISH_THREADS="8"
    export VARNISH_TTL="600"
    export VARNISH_GRACE="3600"
    
    # Copy production configuration
    cp production.vcl /opt/varnish/config/
    
    # Deploy with production configuration
    docker-compose -f docker-compose.production.yml down 2>/dev/null || true
    docker-compose -f docker-compose.production.yml up -d
    
    # Wait for services to be ready
    print_info "Waiting for services to be ready..."
    sleep 60
    
    # Health check
    local max_attempts=10
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if curl -f http://localhost:80/health >/dev/null 2>&1; then
            print_status "Varnish production deployment successful!"
            break
        else
            print_warning "Health check failed (attempt $attempt/$max_attempts)"
            sleep 10
            ((attempt++))
        fi
    done
    
    if [ $attempt -gt $max_attempts ]; then
        print_error "Production deployment failed - health checks unsuccessful"
        return 1
    fi
    
    # Display deployment info
    show_deployment_info
}

# Function to deploy to cloud
deploy_cloud_production() {
    print_info "Deploying to cloud provider: $CLOUD_PROVIDER"
    
    # Use cloud deployment script with production settings
    if [ -f "./deploy-cloud-varnish.sh" ]; then
        ./deploy-cloud-varnish.sh "$CLOUD_PROVIDER" docker "$INSTANCE_SIZE"
    else
        print_error "Cloud deployment script not found"
        return 1
    fi
}

# Function to deploy to Kubernetes
deploy_kubernetes_production() {
    print_info "Deploying to Kubernetes (Production)..."
    
    # Check if kubectl is available
    if ! command -v kubectl &> /dev/null; then
        print_error "kubectl is required for Kubernetes deployment"
        return 1
    fi
    
    # Create production Kubernetes manifests
    create_kubernetes_production_manifests
    
    # Apply Kubernetes configuration
    kubectl apply -f k8s-varnish-production.yaml
    
    # Wait for deployment to be ready
    kubectl rollout status deployment/varnish-cache-prod --timeout=300s
    
    print_status "Kubernetes production deployment successful!"
}

# Function to create Kubernetes production manifests
create_kubernetes_production_manifests() {
    cat > k8s-varnish-production.yaml << EOF
apiVersion: apps/v1
kind: Deployment
metadata:
  name: varnish-cache-prod
  labels:
    app: varnish-cache
    environment: production
spec:
  replicas: 3
  selector:
    matchLabels:
      app: varnish-cache
  template:
    metadata:
      labels:
        app: varnish-cache
    spec:
      containers:
      - name: varnish
        image: varnish:7.4
        ports:
        - containerPort: 80
        - containerPort: 6081
        resources:
          requests:
            memory: "2Gi"
            cpu: "1000m"
          limits:
            memory: "4Gi"
            cpu: "2000m"
        env:
        - name: VARNISH_SIZE
          value: "2G"
        volumeMounts:
        - name: varnish-config
          mountPath: /etc/varnish/default.vcl
          subPath: production.vcl
        livenessProbe:
          httpGet:
            path: /health
            port: 80
          initialDelaySeconds: 30
          periodSeconds: 10
        readinessProbe:
          httpGet:
            path: /health
            port: 80
          initialDelaySeconds: 5
          periodSeconds: 5
      volumes:
      - name: varnish-config
        configMap:
          name: varnish-config
---
apiVersion: v1
kind: ConfigMap
metadata:
  name: varnish-config
data:
  production.vcl: |
$(cat production.vcl | sed 's/^/    /')
---
apiVersion: v1
kind: Service
metadata:
  name: varnish-service
spec:
  selector:
    app: varnish-cache
  ports:
  - name: http
    port: 80
    targetPort: 80
  - name: admin
    port: 6081
    targetPort: 6081
  type: LoadBalancer
EOF
}

# Function to setup monitoring
setup_monitoring() {
    print_info "Setting up production monitoring..."
    
    # Check if monitoring is accessible
    if curl -f http://localhost:9131/metrics >/dev/null 2>&1; then
        print_status "Prometheus metrics available at: http://localhost:9131/metrics"
    else
        print_warning "Monitoring may not be fully ready yet"
    fi
    
    # Setup log rotation
    cat > /opt/varnish/logrotate.conf << EOF
/opt/varnish/logs/*.log {
    daily
    rotate 7
    compress
    delaycompress
    missingok
    notifempty
    create 644 $USER $USER
}
EOF
    
    print_status "Monitoring setup complete"
}

# Function to setup backup
setup_backup() {
    print_info "Setting up production backup..."
    
    # Create backup script
    cat > /opt/varnish/backup.sh << 'EOF'
#!/bin/bash
BACKUP_DIR="/opt/varnish/backups"
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="varnish-backup-$DATE.tar.gz"

# Create backup
tar -czf "$BACKUP_DIR/$BACKUP_FILE" \
    /opt/varnish/config/ \
    /opt/varnish/logs/ \
    deployment/varnish/

# Keep only last 7 backups
find "$BACKUP_DIR" -name "varnish-backup-*.tar.gz" -mtime +7 -delete

echo "Backup created: $BACKUP_FILE"
EOF
    
    chmod +x /opt/varnish/backup.sh
    
    # Add to crontab (daily backup at 2 AM)
    (crontab -l 2>/dev/null; echo "0 2 * * * /opt/varnish/backup.sh") | crontab -
    
    print_status "Backup setup complete"
}

# Function to show deployment information
show_deployment_info() {
    echo ""
    print_status "🎉 Production Deployment Complete!"
    echo ""
    echo -e "${BLUE}📋 Production URLs:${NC}"
    echo "   - Varnish HTTP: http://localhost:80"
    echo "   - Admin Interface: http://localhost:6081"
    echo "   - Prometheus Metrics: http://localhost:9131/metrics"
    echo ""
    echo -e "${BLUE}🔧 Management Commands:${NC}"
    echo "   - Monitor: ./monitor-varnish.sh monitor"
    echo "   - Statistics: ./monitor-varnish.sh stats"
    echo "   - Purge cache: ./monitor-varnish.sh purge '/api/auctions.*'"
    echo "   - View logs: docker logs varnish-production"
    echo "   - Backup: /opt/varnish/backup.sh"
    echo ""
    echo -e "${BLUE}📊 Expected Performance:${NC}"
    echo "   - Response Time: 10-50ms (with CDN)"
    echo "   - Cache Hit Ratio: 80-95%"
    echo "   - Concurrent Users: 1000-5000+"
    echo "   - Server Load Reduction: Up to 99%"
    echo ""
    echo -e "${BLUE}🌍 Next Steps for Global CDN:${NC}"
    echo "   1. Set up Fastly CDN integration"
    echo "   2. Configure DNS to point to CDN"
    echo "   3. Enable SSL/TLS certificates"
    echo "   4. Set up monitoring and alerting"
}

# Function to setup CDN integration
setup_cdn_integration() {
    print_info "Setting up CDN integration guidance..."
    
    echo ""
    echo -e "${BLUE}🌍 CDN Integration Options:${NC}"
    echo ""
    echo -e "${GREEN}Option 1: Fastly (Premium)${NC}"
    echo "   - Cost: ~$50-150/month"
    echo "   - Features: Real-time purging, edge computing"
    echo "   - Setup: Use cdn/fastly-integration.vcl"
    echo ""
    echo -e "${GREEN}Option 2: KeyCDN (Affordable)${NC}"
    echo "   - Cost: ~$0.04/GB"
    echo "   - Features: 25+ POPs, HTTP/2"
    echo "   - Setup: Configure pull zone"
    echo ""
    echo -e "${BLUE}CDN Setup Commands:${NC}"
    echo ""
    echo "# Fastly setup"
    echo "npm install -g @fastly/cli"
    echo "fastly auth"
    echo "fastly service create --name='reverse-tender-cdn'"
    echo ""
    echo "# KeyCDN setup"
    echo "curl -X POST https://api.keycdn.com/zones.json \\"
    echo "  -H 'Authorization: YOUR_API_KEY' \\"
    echo "  -d '{\"name\": \"reverse-tender\", \"type\": \"pull\", \"origin_url\": \"http://your-server.com\"}'"
}

# Function to run performance tests
run_performance_tests() {
    print_info "Running performance tests..."
    
    # Basic performance test
    if command -v ab &> /dev/null; then
        print_info "Running Apache Bench test..."
        ab -n 1000 -c 10 http://localhost:80/health > /tmp/varnish-perf-test.txt
        
        # Extract key metrics
        local rps=$(grep "Requests per second" /tmp/varnish-perf-test.txt | awk '{print $4}')
        local response_time=$(grep "Time per request" /tmp/varnish-perf-test.txt | head -1 | awk '{print $4}')
        
        print_status "Performance Test Results:"
        echo "   - Requests per second: $rps"
        echo "   - Average response time: ${response_time}ms"
    else
        print_warning "Apache Bench (ab) not available for performance testing"
    fi
}

# Main deployment logic
main() {
    echo -e "${BLUE}Starting Production Varnish Deployment...${NC}"
    
    # Check prerequisites
    check_prerequisites
    
    # Setup production environment
    setup_production_environment
    
    # Deploy based on type
    case "$DEPLOYMENT_TYPE" in
        "docker")
            deploy_docker_production
            ;;
        "kubernetes"|"k8s")
            deploy_kubernetes_production
            ;;
        "cloud")
            deploy_cloud_production
            ;;
        *)
            print_error "Unknown deployment type: $DEPLOYMENT_TYPE"
            echo "Supported types: docker, kubernetes, cloud"
            exit 1
            ;;
    esac
    
    # Setup monitoring and backup
    setup_monitoring
    setup_backup
    
    # Show CDN integration guidance
    setup_cdn_integration
    
    # Run performance tests
    run_performance_tests
    
    print_status "Production deployment completed successfully! 🎉"
}

# Show usage if help requested
if [[ "$1" == "help" || "$1" == "-h" || "$1" == "--help" ]]; then
    echo "Production Varnish Deployment Script"
    echo ""
    echo "Usage: $0 [deployment_type] [cloud_provider] [instance_size]"
    echo ""
    echo "Deployment Types:"
    echo "  docker      - Docker Compose deployment (default)"
    echo "  kubernetes  - Kubernetes deployment"
    echo "  cloud       - Cloud provider deployment"
    echo ""
    echo "Cloud Providers:"
    echo "  digitalocean - DigitalOcean (default)"
    echo "  linode      - Linode"
    echo ""
    echo "Instance Sizes:"
    echo "  small       - 1-2 CPU, 1-2GB RAM"
    echo "  standard    - 2 CPU, 2-4GB RAM"
    echo "  large       - 4+ CPU, 8+ GB RAM (default)"
    echo ""
    echo "Examples:"
    echo "  $0 docker"
    echo "  $0 kubernetes"
    echo "  $0 cloud digitalocean large"
    exit 0
fi

# Run main function
main
