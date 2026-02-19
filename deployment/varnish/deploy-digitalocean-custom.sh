#!/bin/bash

# DigitalOcean Custom Domain Varnish Deployment Script
# Automates the complete setup of Varnish with custom domain and SSL

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
DOMAIN="${1:-your-domain.com}"
SUBDOMAIN="${2:-cache}"
DROPLET_NAME="varnish-${SUBDOMAIN}"
REGION="${3:-nyc3}"
SIZE="${4:-s-2vcpu-2gb}"

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

echo -e "${BLUE}🌊 Deploying Varnish with Custom Domain on DigitalOcean${NC}"
echo -e "${BLUE}Domain: ${SUBDOMAIN}.${DOMAIN}${NC}"
echo -e "${BLUE}Region: ${REGION}${NC}"
echo -e "${BLUE}Size: ${SIZE}${NC}"

# Check prerequisites
check_prerequisites() {
    print_info "Checking prerequisites..."
    
    if ! command -v doctl &> /dev/null; then
        print_error "doctl CLI is required but not installed"
        echo "Install it from: https://docs.digitalocean.com/reference/doctl/how-to/install/"
        exit 1
    fi
    
    # Check if authenticated
    if ! doctl account get &> /dev/null; then
        print_error "doctl is not authenticated"
        echo "Run: doctl auth init"
        exit 1
    fi
    
    print_status "Prerequisites check passed"
}

# Create droplet
create_droplet() {
    print_info "Creating DigitalOcean droplet..."
    
    # Get SSH key ID
    SSH_KEY_ID=$(doctl compute ssh-key list --format ID --no-header | head -1)
    if [ -z "$SSH_KEY_ID" ]; then
        print_error "No SSH keys found. Please add an SSH key to your DigitalOcean account"
        exit 1
    fi
    
    # Create droplet
    doctl compute droplet create $DROPLET_NAME \
        --image ubuntu-22-04-x64 \
        --size $SIZE \
        --region $REGION \
        --ssh-keys $SSH_KEY_ID \
        --tag-names varnish,production,custom-domain \
        --wait
    
    # Get droplet IP
    DROPLET_IP=$(doctl compute droplet list --format "Name,PublicIPv4" --no-header | grep $DROPLET_NAME | awk '{print $2}')
    
    if [ -z "$DROPLET_IP" ]; then
        print_error "Failed to get droplet IP"
        exit 1
    fi
    
    print_status "Droplet created with IP: $DROPLET_IP"
}

# Configure DNS
configure_dns() {
    print_info "Configuring DNS records..."
    
    # Check if domain exists, create if not
    if ! doctl compute domain get $DOMAIN &> /dev/null; then
        print_info "Creating domain $DOMAIN..."
        doctl compute domain create $DOMAIN --ip-address $DROPLET_IP
    fi
    
    # Create A record for subdomain
    doctl compute domain records create $DOMAIN \
        --record-type A \
        --record-name $SUBDOMAIN \
        --record-data $DROPLET_IP \
        --record-ttl 300
    
    # Create CNAME for API subdomain
    doctl compute domain records create $DOMAIN \
        --record-type CNAME \
        --record-name api \
        --record-data "${SUBDOMAIN}.${DOMAIN}" \
        --record-ttl 300
    
    print_status "DNS records configured"
}

# Create SSL certificate
create_ssl_certificate() {
    print_info "Creating Let's Encrypt SSL certificate..."
    
    # Create certificate
    doctl compute certificate create \
        --name "${SUBDOMAIN}-ssl-cert" \
        --dns-names "${SUBDOMAIN}.${DOMAIN},api.${DOMAIN}" \
        --type lets_encrypt
    
    # Wait for certificate to be ready
    print_info "Waiting for SSL certificate to be ready..."
    local max_attempts=30
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        CERT_STATUS=$(doctl compute certificate list --format Name,State --no-header | grep "${SUBDOMAIN}-ssl-cert" | awk '{print $2}')
        
        if [ "$CERT_STATUS" = "verified" ]; then
            print_status "SSL certificate is ready"
            break
        elif [ "$CERT_STATUS" = "pending" ]; then
            print_info "Certificate verification in progress (attempt $attempt/$max_attempts)..."
            sleep 30
            ((attempt++))
        else
            print_error "Certificate verification failed with status: $CERT_STATUS"
            exit 1
        fi
    done
    
    if [ $attempt -gt $max_attempts ]; then
        print_error "SSL certificate verification timed out"
        exit 1
    fi
}

# Create load balancer
create_load_balancer() {
    print_info "Creating load balancer with SSL termination..."
    
    # Get certificate ID
    CERT_ID=$(doctl compute certificate list --format ID,Name --no-header | grep "${SUBDOMAIN}-ssl-cert" | awk '{print $1}')
    
    if [ -z "$CERT_ID" ]; then
        print_error "Failed to get certificate ID"
        exit 1
    fi
    
    # Get droplet ID
    DROPLET_ID=$(doctl compute droplet list --format ID,Name --no-header | grep $DROPLET_NAME | awk '{print $1}')
    
    # Create load balancer
    doctl compute load-balancer create \
        --name "${SUBDOMAIN}-lb" \
        --forwarding-rules "entry_protocol:https,entry_port:443,target_protocol:http,target_port:80,certificate_id:$CERT_ID" \
        --forwarding-rules "entry_protocol:http,entry_port:80,target_protocol:http,target_port:80" \
        --health-check "protocol:http,port:80,path:/health,check_interval_seconds:10,response_timeout_seconds:5,unhealthy_threshold:3,healthy_threshold:2" \
        --region $REGION \
        --droplet-ids $DROPLET_ID
    
    # Get load balancer IP
    sleep 30
    LB_IP=$(doctl compute load-balancer list --format Name,IP --no-header | grep "${SUBDOMAIN}-lb" | awk '{print $2}')
    
    print_status "Load balancer created with IP: $LB_IP"
}

# Update DNS to point to load balancer
update_dns_to_lb() {
    print_info "Updating DNS to point to load balancer..."
    
    # Get record ID for subdomain
    RECORD_ID=$(doctl compute domain records list $DOMAIN --format ID,Name,Type --no-header | grep "$SUBDOMAIN.*A" | awk '{print $1}')
    
    # Update A record to point to load balancer
    doctl compute domain records update $DOMAIN \
        --record-id $RECORD_ID \
        --record-data $LB_IP
    
    print_status "DNS updated to point to load balancer"
}

# Deploy Varnish configuration
deploy_varnish() {
    print_info "Deploying Varnish configuration to droplet..."
    
    # Create custom VCL configuration
    cat > /tmp/digitalocean-custom-domain.vcl << EOF
vcl 4.1;

# Backend configuration for DigitalOcean
backend gateway_do {
    .host = "127.0.0.1";  # Local gateway service
    .port = "8080";
    .connect_timeout = 2s;
    .first_byte_timeout = 10s;
    .between_bytes_timeout = 2s;
    .max_connections = 100;
    .probe = {
        .url = "/health";
        .interval = 10s;
        .timeout = 3s;
        .window = 5;
        .threshold = 3;
    };
}

# Handle custom domain requests
sub vcl_recv {
    # Normalize host header for custom domains
    if (req.http.Host ~ "^(${SUBDOMAIN}|api)\.${DOMAIN//./\\.}\$") {
        set req.http.Host = "${SUBDOMAIN}.${DOMAIN}";
    }
    
    # Handle SSL termination at load balancer
    if (req.http.X-Forwarded-Proto != "https" && req.http.Host ~ "${DOMAIN//./\\.}") {
        return (synth(301, "https://" + req.http.Host + req.url));
    }
    
    # Set backend
    set req.backend_hint = gateway_do;
    
    # API routing
    if (req.url ~ "^/api/") {
        # Cache API responses
        if (req.url ~ "^/api/(auctions|users|categories)") {
            return (hash);
        }
    }
    
    return (hash);
}

sub vcl_backend_response {
    # Set custom domain cache headers
    if (bereq.url ~ "^/api/") {
        set beresp.http.Cache-Control = "public, max-age=300";
        set beresp.ttl = 300s;
    }
    
    # Add custom headers for domain
    set beresp.http.X-Served-By = "DigitalOcean-Varnish";
    
    return (deliver);
}

sub vcl_deliver {
    # Add cache headers
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
    } else {
        set resp.http.X-Cache = "MISS";
    }
    
    # Security headers for custom domain
    set resp.http.Strict-Transport-Security = "max-age=31536000; includeSubDomains";
    set resp.http.X-Frame-Options = "SAMEORIGIN";
    
    return (deliver);
}
EOF
    
    # Copy VCL to droplet
    scp -o StrictHostKeyChecking=no /tmp/digitalocean-custom-domain.vcl root@$DROPLET_IP:/tmp/
    
    # Install and configure Varnish
    ssh -o StrictHostKeyChecking=no root@$DROPLET_IP << 'ENDSSH'
        # Update system
        apt update && apt upgrade -y
        
        # Install Docker
        curl -fsSL https://get.docker.com -o get-docker.sh
        sh get-docker.sh
        
        # Install Docker Compose
        curl -L "https://github.com/docker/compose/releases/download/v2.20.0/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
        chmod +x /usr/local/bin/docker-compose
        
        # Create Varnish directory
        mkdir -p /opt/varnish
        mv /tmp/digitalocean-custom-domain.vcl /opt/varnish/default.vcl
        
        # Create Docker Compose file
        cat > /opt/varnish/docker-compose.yml << 'EOFCOMPOSE'
version: '3.8'

services:
  varnish:
    image: varnish:7.4
    container_name: varnish-digitalocean
    ports:
      - "80:80"
      - "6081:6081"
    volumes:
      - ./default.vcl:/etc/varnish/default.vcl:ro
    command: >
      varnishd -F
      -f /etc/varnish/default.vcl
      -s malloc,1G
      -a :80
      -T :6081
      -p default_ttl=300
      -p default_grace=3600
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:80/health"]
      interval: 30s
      timeout: 10s
      retries: 3

  # Simple health endpoint
  health:
    image: nginx:alpine
    container_name: health-endpoint
    ports:
      - "8080:80"
    volumes:
      - ./health.html:/usr/share/nginx/html/health:ro
    restart: unless-stopped
EOFCOMPOSE
        
        # Create health endpoint
        echo "OK" > /opt/varnish/health.html
        
        # Start services
        cd /opt/varnish
        docker-compose up -d
ENDSSH
    
    print_status "Varnish deployed and running"
}

# Test deployment
test_deployment() {
    print_info "Testing deployment..."
    
    # Wait for DNS propagation
    print_info "Waiting for DNS propagation..."
    sleep 60
    
    # Test HTTP endpoint
    if curl -f -s "http://${SUBDOMAIN}.${DOMAIN}/health" > /dev/null; then
        print_status "HTTP endpoint is working"
    else
        print_warning "HTTP endpoint test failed (may need more time for DNS propagation)"
    fi
    
    # Test HTTPS endpoint
    if curl -f -s "https://${SUBDOMAIN}.${DOMAIN}/health" > /dev/null; then
        print_status "HTTPS endpoint is working"
    else
        print_warning "HTTPS endpoint test failed (may need more time for SSL setup)"
    fi
}

# Show deployment information
show_deployment_info() {
    echo ""
    print_status "🎉 DigitalOcean Custom Domain Deployment Complete!"
    echo ""
    echo -e "${BLUE}📋 Deployment Information:${NC}"
    echo "   - Custom Domain: https://${SUBDOMAIN}.${DOMAIN}"
    echo "   - API Endpoint: https://api.${DOMAIN}"
    echo "   - Droplet IP: $DROPLET_IP"
    echo "   - Load Balancer IP: $LB_IP"
    echo ""
    echo -e "${BLUE}🔧 Management Commands:${NC}"
    echo "   - SSH to droplet: ssh root@$DROPLET_IP"
    echo "   - View Varnish logs: ssh root@$DROPLET_IP 'docker logs varnish-digitalocean'"
    echo "   - Restart Varnish: ssh root@$DROPLET_IP 'cd /opt/varnish && docker-compose restart'"
    echo ""
    echo -e "${BLUE}🧪 Test Commands:${NC}"
    echo "   - Test health: curl https://${SUBDOMAIN}.${DOMAIN}/health"
    echo "   - Test caching: curl -I https://${SUBDOMAIN}.${DOMAIN}/api/auctions"
    echo "   - Performance test: ab -n 100 -c 10 https://${SUBDOMAIN}.${DOMAIN}/health"
    echo ""
    echo -e "${BLUE}📊 Monitoring:${NC}"
    echo "   - DigitalOcean Dashboard: https://cloud.digitalocean.com/"
    echo "   - Load Balancer: https://cloud.digitalocean.com/networking/load_balancers"
    echo "   - SSL Certificate: https://cloud.digitalocean.com/networking/certificates"
}

# Main execution
main() {
    check_prerequisites
    create_droplet
    configure_dns
    create_ssl_certificate
    create_load_balancer
    update_dns_to_lb
    deploy_varnish
    test_deployment
    show_deployment_info
}

# Show usage if help requested
if [[ "$1" == "help" || "$1" == "-h" || "$1" == "--help" ]]; then
    echo "DigitalOcean Custom Domain Varnish Deployment Script"
    echo ""
    echo "Usage: $0 [domain] [subdomain] [region] [size]"
    echo ""
    echo "Parameters:"
    echo "  domain     - Your domain name (default: your-domain.com)"
    echo "  subdomain  - Subdomain for cache (default: cache)"
    echo "  region     - DigitalOcean region (default: nyc3)"
    echo "  size       - Droplet size (default: s-2vcpu-2gb)"
    echo ""
    echo "Examples:"
    echo "  $0 example.com cache nyc3 s-2vcpu-2gb"
    echo "  $0 mysite.com api fra1 s-1vcpu-2gb"
    echo ""
    echo "Prerequisites:"
    echo "  - doctl CLI installed and authenticated"
    echo "  - SSH key added to DigitalOcean account"
    echo "  - Domain DNS managed by DigitalOcean (or update manually)"
    exit 0
fi

# Run main function
main
