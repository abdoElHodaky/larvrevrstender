#!/bin/bash

# Linode Custom Domain Varnish Deployment Script
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
INSTANCE_LABEL="varnish-${SUBDOMAIN}"
REGION="${3:-us-east}"
TYPE="${4:-g6-standard-2}"

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

echo -e "${BLUE}🟦 Deploying Varnish with Custom Domain on Linode${NC}"
echo -e "${BLUE}Domain: ${SUBDOMAIN}.${DOMAIN}${NC}"
echo -e "${BLUE}Region: ${REGION}${NC}"
echo -e "${BLUE}Type: ${TYPE}${NC}"

# Check prerequisites
check_prerequisites() {
    print_info "Checking prerequisites..."
    
    if ! command -v linode-cli &> /dev/null; then
        print_error "linode-cli is required but not installed"
        echo "Install it with: pip3 install linode-cli"
        exit 1
    fi
    
    # Check if authenticated
    if ! linode-cli profile view &> /dev/null; then
        print_error "linode-cli is not authenticated"
        echo "Run: linode-cli configure"
        exit 1
    fi
    
    print_status "Prerequisites check passed"
}

# Create Linode instance
create_instance() {
    print_info "Creating Linode instance..."
    
    # Generate root password
    ROOT_PASS=$(openssl rand -base64 32)
    
    # Get SSH public key
    if [ -f ~/.ssh/id_rsa.pub ]; then
        SSH_KEY=$(cat ~/.ssh/id_rsa.pub)
    else
        print_error "SSH public key not found at ~/.ssh/id_rsa.pub"
        echo "Generate one with: ssh-keygen -t rsa -b 4096"
        exit 1
    fi
    
    # Create instance
    linode-cli linodes create \
        --type $TYPE \
        --region $REGION \
        --image linode/ubuntu22.04 \
        --label $INSTANCE_LABEL \
        --root_pass "$ROOT_PASS" \
        --authorized_keys "$SSH_KEY" \
        --tags varnish,production,custom-domain
    
    # Wait for instance to be running
    print_info "Waiting for instance to be ready..."
    sleep 60
    
    # Get instance IP
    INSTANCE_IP=$(linode-cli linodes list --text --format "label,ipv4" | grep $INSTANCE_LABEL | awk '{print $2}')
    
    if [ -z "$INSTANCE_IP" ]; then
        print_error "Failed to get instance IP"
        exit 1
    fi
    
    print_status "Instance created with IP: $INSTANCE_IP"
}

# Configure DNS
configure_dns() {
    print_info "Configuring DNS records..."
    
    # Check if domain exists, create if not
    DOMAIN_ID=$(linode-cli domains list --text --format "id,domain" | grep "$DOMAIN" | awk '{print $1}')
    
    if [ -z "$DOMAIN_ID" ]; then
        print_info "Creating domain $DOMAIN..."
        DOMAIN_ID=$(linode-cli domains create \
            --domain $DOMAIN \
            --type master \
            --soa_email "admin@${DOMAIN}" \
            --text --format "id" --no-headers)
    fi
    
    # Create A record for subdomain
    linode-cli domains records create $DOMAIN_ID \
        --type A \
        --name $SUBDOMAIN \
        --target $INSTANCE_IP \
        --ttl_sec 300
    
    # Create CNAME for API subdomain
    linode-cli domains records create $DOMAIN_ID \
        --type CNAME \
        --name api \
        --target "${SUBDOMAIN}.${DOMAIN}" \
        --ttl_sec 300
    
    print_status "DNS records configured"
}

# Create NodeBalancer
create_nodebalancer() {
    print_info "Creating NodeBalancer..."
    
    # Create NodeBalancer
    NODEBALANCER_ID=$(linode-cli nodebalancers create \
        --region $REGION \
        --label "${SUBDOMAIN}-nodebalancer" \
        --text --format "id" --no-headers)
    
    # Get instance ID
    INSTANCE_ID=$(linode-cli linodes list --text --format "id,label" | grep $INSTANCE_LABEL | awk '{print $1}')
    
    # Create HTTP config
    CONFIG_ID=$(linode-cli nodebalancers configs create $NODEBALANCER_ID \
        --port 80 \
        --protocol http \
        --algorithm roundrobin \
        --stickiness none \
        --check http_body \
        --check_path /health \
        --check_body "OK" \
        --check_interval 10 \
        --check_timeout 5 \
        --check_attempts 3 \
        --text --format "id" --no-headers)
    
    # Add instance to NodeBalancer
    linode-cli nodebalancers nodes create $NODEBALANCER_ID $CONFIG_ID \
        --address "${INSTANCE_IP}:80" \
        --label "${INSTANCE_LABEL}-node" \
        --weight 100 \
        --mode accept
    
    # Get NodeBalancer IP
    sleep 30
    NB_IP=$(linode-cli nodebalancers list --text --format "id,ipv4" | grep $NODEBALANCER_ID | awk '{print $2}')
    
    print_status "NodeBalancer created with IP: $NB_IP"
}

# Update DNS to point to NodeBalancer
update_dns_to_nb() {
    print_info "Updating DNS to point to NodeBalancer..."
    
    # Get record ID for subdomain
    RECORD_ID=$(linode-cli domains records list $DOMAIN_ID --text --format "id,name,type" | grep "$SUBDOMAIN.*A" | awk '{print $1}')
    
    # Update A record to point to NodeBalancer
    linode-cli domains records update $DOMAIN_ID $RECORD_ID \
        --target $NB_IP
    
    print_status "DNS updated to point to NodeBalancer"
}

# Deploy Varnish configuration
deploy_varnish() {
    print_info "Deploying Varnish configuration to instance..."
    
    # Wait for instance to be fully ready
    print_info "Waiting for instance to be fully ready..."
    sleep 120
    
    # Create custom VCL configuration
    cat > /tmp/linode-custom-domain.vcl << EOF
vcl 4.1;

# Backend configuration for Linode
backend gateway_linode {
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
    
    # Handle SSL termination at NodeBalancer
    if (req.http.X-Forwarded-Proto != "https" && req.http.Host ~ "${DOMAIN//./\\.}") {
        return (synth(301, "https://" + req.http.Host + req.url));
    }
    
    # Set backend
    set req.backend_hint = gateway_linode;
    
    # API routing with Linode optimizations
    if (req.url ~ "^/api/") {
        # Linode-specific caching
        if (req.url ~ "^/api/(auctions|users|categories)") {
            # Remove Linode-specific headers
            unset req.http.X-Linode-*;
            return (hash);
        }
    }
    
    return (hash);
}

sub vcl_backend_response {
    # Linode-optimized caching
    if (bereq.url ~ "^/api/") {
        set beresp.http.Cache-Control = "public, max-age=600";
        set beresp.ttl = 600s;
        set beresp.grace = 3600s;
    }
    
    # Add Linode-specific headers
    set beresp.http.X-Served-By = "Linode-Varnish";
    set beresp.http.X-Linode-Region = "${REGION}";
    
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
    
    # Remove Linode internal headers
    unset resp.http.X-Linode-*;
    
    return (deliver);
}
EOF
    
    # Copy VCL to instance
    scp -o StrictHostKeyChecking=no /tmp/linode-custom-domain.vcl root@$INSTANCE_IP:/tmp/
    
    # Install and configure Varnish
    ssh -o StrictHostKeyChecking=no root@$INSTANCE_IP << 'ENDSSH'
        # Update system
        apt update && apt upgrade -y
        
        # Install Docker
        curl -fsSL https://get.docker.com -o get-docker.sh
        sh get-docker.sh
        
        # Install Docker Compose
        curl -L "https://github.com/docker/compose/releases/download/v2.20.0/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
        chmod +x /usr/local/bin/docker-compose
        
        # Install Certbot for SSL
        apt install -y certbot python3-certbot-nginx
        
        # Create Varnish directory
        mkdir -p /opt/varnish
        mv /tmp/linode-custom-domain.vcl /opt/varnish/default.vcl
        
        # Create Docker Compose file
        cat > /opt/varnish/docker-compose.yml << 'EOFCOMPOSE'
version: '3.8'

services:
  varnish:
    image: varnish:7.4
    container_name: varnish-linode
    ports:
      - "80:80"
      - "6081:6081"
    volumes:
      - ./default.vcl:/etc/varnish/default.vcl:ro
      - /etc/letsencrypt:/etc/letsencrypt:ro
    environment:
      - VARNISH_SIZE=2G
    command: >
      varnishd -F
      -f /etc/varnish/default.vcl
      -s malloc,2G
      -a :80
      -T :6081
      -p default_ttl=600
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

  # Nginx for SSL termination (if needed)
  nginx:
    image: nginx:alpine
    container_name: nginx-ssl
    ports:
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf:ro
      - /etc/letsencrypt:/etc/letsencrypt:ro
    depends_on:
      - varnish
    restart: unless-stopped
EOFCOMPOSE
        
        # Create health endpoint
        echo "OK" > /opt/varnish/health.html
        
        # Create basic nginx config for SSL
        cat > /opt/varnish/nginx.conf << 'EOFNGINX'
events {
    worker_connections 1024;
}

http {
    upstream varnish {
        server varnish:80;
    }

    server {
        listen 443 ssl;
        server_name _;

        ssl_certificate /etc/letsencrypt/live/DOMAIN_PLACEHOLDER/fullchain.pem;
        ssl_certificate_key /etc/letsencrypt/live/DOMAIN_PLACEHOLDER/privkey.pem;

        location / {
            proxy_pass http://varnish;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
        }
    }
}
EOFNGINX
        
        # Start services
        cd /opt/varnish
        docker-compose up -d varnish health
ENDSSH
    
    print_status "Varnish deployed and running"
}

# Setup SSL certificate
setup_ssl() {
    print_info "Setting up SSL certificate..."
    
    # Wait for DNS propagation
    print_info "Waiting for DNS propagation..."
    sleep 120
    
    # Get SSL certificate
    ssh -o StrictHostKeyChecking=no root@$INSTANCE_IP << ENDSSH
        # Stop nginx if running
        docker-compose -f /opt/varnish/docker-compose.yml stop nginx 2>/dev/null || true
        
        # Get Let's Encrypt certificate
        certbot certonly --standalone \
            -d ${SUBDOMAIN}.${DOMAIN} \
            -d api.${DOMAIN} \
            --email admin@${DOMAIN} \
            --agree-tos \
            --non-interactive \
            --expand
        
        # Update nginx config with actual domain
        sed -i "s/DOMAIN_PLACEHOLDER/${SUBDOMAIN}.${DOMAIN}/g" /opt/varnish/nginx.conf
        
        # Start nginx with SSL
        cd /opt/varnish
        docker-compose up -d nginx
        
        # Set up auto-renewal
        (crontab -l 2>/dev/null; echo "0 12 * * * /usr/bin/certbot renew --quiet && docker-compose -f /opt/varnish/docker-compose.yml restart nginx") | crontab -
ENDSSH
    
    print_status "SSL certificate configured"
}

# Create HTTPS NodeBalancer config
create_https_config() {
    print_info "Creating HTTPS NodeBalancer configuration..."
    
    # Create HTTPS config
    HTTPS_CONFIG_ID=$(linode-cli nodebalancers configs create $NODEBALANCER_ID \
        --port 443 \
        --protocol https \
        --algorithm roundrobin \
        --stickiness none \
        --check http_body \
        --check_path /health \
        --check_body "OK" \
        --check_interval 10 \
        --check_timeout 5 \
        --check_attempts 3 \
        --ssl_cert "$(ssh root@$INSTANCE_IP 'cat /etc/letsencrypt/live/${SUBDOMAIN}.${DOMAIN}/fullchain.pem')" \
        --ssl_key "$(ssh root@$INSTANCE_IP 'cat /etc/letsencrypt/live/${SUBDOMAIN}.${DOMAIN}/privkey.pem')" \
        --text --format "id" --no-headers)
    
    # Add instance to HTTPS config
    linode-cli nodebalancers nodes create $NODEBALANCER_ID $HTTPS_CONFIG_ID \
        --address "${INSTANCE_IP}:443" \
        --label "${INSTANCE_LABEL}-https-node" \
        --weight 100 \
        --mode accept
    
    print_status "HTTPS NodeBalancer configuration created"
}

# Test deployment
test_deployment() {
    print_info "Testing deployment..."
    
    # Wait for services to be ready
    print_info "Waiting for services to be ready..."
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
    print_status "🎉 Linode Custom Domain Deployment Complete!"
    echo ""
    echo -e "${BLUE}📋 Deployment Information:${NC}"
    echo "   - Custom Domain: https://${SUBDOMAIN}.${DOMAIN}"
    echo "   - API Endpoint: https://api.${DOMAIN}"
    echo "   - Instance IP: $INSTANCE_IP"
    echo "   - NodeBalancer IP: $NB_IP"
    echo ""
    echo -e "${BLUE}🔧 Management Commands:${NC}"
    echo "   - SSH to instance: ssh root@$INSTANCE_IP"
    echo "   - View Varnish logs: ssh root@$INSTANCE_IP 'docker logs varnish-linode'"
    echo "   - Restart Varnish: ssh root@$INSTANCE_IP 'cd /opt/varnish && docker-compose restart'"
    echo "   - Renew SSL: ssh root@$INSTANCE_IP 'certbot renew'"
    echo ""
    echo -e "${BLUE}🧪 Test Commands:${NC}"
    echo "   - Test health: curl https://${SUBDOMAIN}.${DOMAIN}/health"
    echo "   - Test caching: curl -I https://${SUBDOMAIN}.${DOMAIN}/api/auctions"
    echo "   - Performance test: ab -n 100 -c 10 https://${SUBDOMAIN}.${DOMAIN}/health"
    echo ""
    echo -e "${BLUE}📊 Monitoring:${NC}"
    echo "   - Linode Dashboard: https://cloud.linode.com/"
    echo "   - NodeBalancer: https://cloud.linode.com/nodebalancers"
    echo "   - DNS Manager: https://cloud.linode.com/domains"
}

# Main execution
main() {
    check_prerequisites
    create_instance
    configure_dns
    create_nodebalancer
    update_dns_to_nb
    deploy_varnish
    setup_ssl
    create_https_config
    test_deployment
    show_deployment_info
}

# Show usage if help requested
if [[ "$1" == "help" || "$1" == "-h" || "$1" == "--help" ]]; then
    echo "Linode Custom Domain Varnish Deployment Script"
    echo ""
    echo "Usage: $0 [domain] [subdomain] [region] [type]"
    echo ""
    echo "Parameters:"
    echo "  domain     - Your domain name (default: your-domain.com)"
    echo "  subdomain  - Subdomain for cache (default: cache)"
    echo "  region     - Linode region (default: us-east)"
    echo "  type       - Instance type (default: g6-standard-2)"
    echo ""
    echo "Examples:"
    echo "  $0 example.com cache us-east g6-standard-2"
    echo "  $0 mysite.com api eu-west g6-standard-1"
    echo ""
    echo "Prerequisites:"
    echo "  - linode-cli installed and configured"
    echo "  - SSH key pair generated (~/.ssh/id_rsa.pub)"
    echo "  - Domain DNS managed by Linode (or update manually)"
    exit 0
fi

# Run main function
main
