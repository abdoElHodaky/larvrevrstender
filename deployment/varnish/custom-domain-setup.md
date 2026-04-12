# Custom Domain Setup for DigitalOcean & Linode Varnish Deployment

This guide shows you how to configure custom domains with SSL/TLS for your Varnish deployment on DigitalOcean and Linode.

## 🌐 **Overview**

### **Architecture with Custom Domain:**
```
your-domain.com → DNS → Cloud Provider → Load Balancer → Varnish → Microservices
```

### **What We'll Configure:**
1. **Custom Domain**: `api.your-domain.com` or `cache.your-domain.com`
2. **SSL/TLS Certificate**: Free Let's Encrypt certificates
3. **Load Balancer**: Cloud provider load balancer with SSL termination
4. **DNS Configuration**: Proper A/CNAME records
5. **Varnish Configuration**: Handle SSL traffic properly

---

## 🌊 **DigitalOcean Custom Domain Setup**

### **Step 1: Create DigitalOcean Droplet with Varnish**

```bash
# Create droplet using doctl CLI
doctl compute droplet create varnish-cache \
  --image ubuntu-22-04-x64 \
  --size s-2vcpu-2gb \
  --region nyc3 \
  --ssh-keys YOUR_SSH_KEY_ID \
  --tag-names varnish,production

# Get droplet IP
doctl compute droplet list --format "Name,PublicIPv4"
```

### **Step 2: Configure DNS in DigitalOcean**

```bash
# Add your domain to DigitalOcean DNS
doctl compute domain create your-domain.com

# Create A record pointing to your droplet
doctl compute domain records create your-domain.com \
  --record-type A \
  --record-name cache \
  --record-data YOUR_DROPLET_IP \
  --record-ttl 300

# Create CNAME for API subdomain
doctl compute domain records create your-domain.com \
  --record-type CNAME \
  --record-name api \
  --record-data cache.your-domain.com \
  --record-ttl 300
```

### **Step 3: Create DigitalOcean Load Balancer with SSL**

```bash
# Create load balancer with SSL termination
doctl compute load-balancer create \
  --name varnish-lb \
  --forwarding-rules entry_protocol:https,entry_port:443,target_protocol:http,target_port:80,certificate_id:YOUR_CERT_ID \
  --forwarding-rules entry_protocol:http,entry_port:80,target_protocol:http,target_port:80 \
  --health-check protocol:http,port:80,path:/health,check_interval_seconds:10,response_timeout_seconds:5,unhealthy_threshold:3,healthy_threshold:2 \
  --region nyc3 \
  --tag-name varnish
```

### **Step 4: DigitalOcean SSL Certificate**

```bash
# Create Let's Encrypt certificate
doctl compute certificate create \
  --name varnish-ssl-cert \
  --dns-names cache.your-domain.com,api.your-domain.com \
  --type lets_encrypt
```

### **Step 5: DigitalOcean Varnish Configuration**

Create `deployment/varnish/digitalocean-custom-domain.vcl`:

```vcl
vcl 4.1;

# Backend configuration for DigitalOcean
backend gateway_do {
    .host = "10.0.0.10";  # Private IP of your gateway service
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
    if (req.http.Host ~ "^(cache|api)\.your-domain\.com$") {
        set req.http.Host = "cache.your-domain.com";
    }
    
    # Handle SSL termination at load balancer
    if (req.http.X-Forwarded-Proto != "https" && req.http.Host ~ "your-domain\.com") {
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
```

### **Step 6: DigitalOcean Docker Compose with Custom Domain**

Create `deployment/varnish/docker-compose.digitalocean.yml`:

```yaml
version: '3.8'

services:
  varnish-do:
    image: varnish:7.4
    container_name: varnish-digitalocean
    ports:
      - "80:80"
      - "6081:6081"
    volumes:
      - ./digitalocean-custom-domain.vcl:/etc/varnish/default.vcl:ro
    environment:
      - VARNISH_SIZE=2G
      - CUSTOM_DOMAIN=cache.your-domain.com
    command: >
      varnishd -F
      -f /etc/varnish/default.vcl
      -s malloc,2G
      -a :80
      -T :6081
      -p default_ttl=300
      -p default_grace=3600
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "-H", "Host: cache.your-domain.com", "http://localhost:80/health"]
      interval: 30s
      timeout: 10s
      retries: 3
    networks:
      - do-network

  # SSL certificate renewal
  certbot:
    image: certbot/certbot
    container_name: certbot-do
    volumes:
      - certbot-certs:/etc/letsencrypt
      - certbot-www:/var/www/certbot
    command: certonly --webroot --webroot-path=/var/www/certbot --email your-email@domain.com --agree-tos --no-eff-email -d cache.your-domain.com -d api.your-domain.com

volumes:
  certbot-certs:
  certbot-www:

networks:
  do-network:
    driver: bridge
```

---

## 🟦 **Linode Custom Domain Setup**

### **Step 1: Create Linode Instance with Varnish**

```bash
# Create Linode instance using linode-cli
linode-cli linodes create \
  --type g6-standard-2 \
  --region us-east \
  --image linode/ubuntu22.04 \
  --label varnish-cache \
  --root_pass YOUR_ROOT_PASSWORD \
  --authorized_keys "YOUR_SSH_PUBLIC_KEY"

# Get instance IP
linode-cli linodes list --text --format "label,ipv4"
```

### **Step 2: Configure DNS in Linode**

```bash
# Create domain in Linode DNS Manager
linode-cli domains create \
  --domain your-domain.com \
  --type master \
  --soa_email admin@your-domain.com

# Create A record for cache subdomain
linode-cli domains records create YOUR_DOMAIN_ID \
  --type A \
  --name cache \
  --target YOUR_LINODE_IP \
  --ttl_sec 300

# Create CNAME for API subdomain
linode-cli domains records create YOUR_DOMAIN_ID \
  --type CNAME \
  --name api \
  --target cache.your-domain.com \
  --ttl_sec 300
```

### **Step 3: Create Linode NodeBalancer with SSL**

```bash
# Create NodeBalancer
linode-cli nodebalancers create \
  --region us-east \
  --label varnish-nodebalancer

# Create NodeBalancer config with SSL
linode-cli nodebalancers configs create YOUR_NODEBALANCER_ID \
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
  --ssl_cert "$(cat /path/to/your/cert.pem)" \
  --ssl_key "$(cat /path/to/your/private.key)"

# Add HTTP config (redirect to HTTPS)
linode-cli nodebalancers configs create YOUR_NODEBALANCER_ID \
  --port 80 \
  --protocol http \
  --algorithm roundrobin
```

### **Step 4: Linode SSL Certificate Setup**

```bash
# Install Certbot on Linode instance
sudo apt update
sudo apt install certbot python3-certbot-nginx

# Get Let's Encrypt certificate
sudo certbot certonly --standalone \
  -d cache.your-domain.com \
  -d api.your-domain.com \
  --email your-email@domain.com \
  --agree-tos \
  --non-interactive

# Set up auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

### **Step 5: Linode Varnish Configuration**

Create `deployment/varnish/linode-custom-domain.vcl`:

```vcl
vcl 4.1;

# Backend configuration for Linode
backend gateway_linode {
    .host = "192.168.1.10";  # Private IP of your gateway service
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
    if (req.http.Host ~ "^(cache|api)\.your-domain\.com$") {
        set req.http.Host = "cache.your-domain.com";
    }
    
    # Handle SSL termination at NodeBalancer
    if (req.http.X-Forwarded-Proto != "https" && req.http.Host ~ "your-domain\.com") {
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
    set beresp.http.X-Linode-Region = "us-east";
    
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
```

### **Step 6: Linode Docker Compose with Custom Domain**

Create `deployment/varnish/docker-compose.linode.yml`:

```yaml
version: '3.8'

services:
  varnish-linode:
    image: varnish:7.4
    container_name: varnish-linode
    ports:
      - "80:80"
      - "6081:6081"
    volumes:
      - ./linode-custom-domain.vcl:/etc/varnish/default.vcl:ro
      - /etc/letsencrypt:/etc/letsencrypt:ro
    environment:
      - VARNISH_SIZE=2G
      - CUSTOM_DOMAIN=cache.your-domain.com
      - LINODE_REGION=us-east
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
      test: ["CMD", "curl", "-f", "-H", "Host: cache.your-domain.com", "http://localhost:80/health"]
      interval: 30s
      timeout: 10s
      retries: 3
    networks:
      - linode-network

  # Longview monitoring integration
  longview:
    image: linode/longview
    container_name: longview-agent
    environment:
      - LONGVIEW_KEY=YOUR_LONGVIEW_KEY
    volumes:
      - /proc:/host/proc:ro
      - /sys:/host/sys:ro
    privileged: true
    restart: unless-stopped

networks:
  linode-network:
    driver: bridge
```

---

## 🚀 **Automated Deployment Scripts**

### **DigitalOcean Custom Domain Deployment**

Create `deployment/varnish/deploy-digitalocean-custom.sh`:

```bash
#!/bin/bash

set -e

DOMAIN="${1:-your-domain.com}"
SUBDOMAIN="${2:-cache}"
DROPLET_NAME="varnish-${SUBDOMAIN}"

echo "🌊 Deploying Varnish with custom domain on DigitalOcean..."
echo "Domain: ${SUBDOMAIN}.${DOMAIN}"

# Create droplet
echo "Creating droplet..."
doctl compute droplet create $DROPLET_NAME \
  --image ubuntu-22-04-x64 \
  --size s-2vcpu-2gb \
  --region nyc3 \
  --ssh-keys $(doctl compute ssh-key list --format ID --no-header | head -1) \
  --wait

# Get droplet IP
DROPLET_IP=$(doctl compute droplet list --format "Name,PublicIPv4" --no-header | grep $DROPLET_NAME | awk '{print $2}')
echo "Droplet IP: $DROPLET_IP"

# Configure DNS
echo "Configuring DNS..."
doctl compute domain records create $DOMAIN \
  --record-type A \
  --record-name $SUBDOMAIN \
  --record-data $DROPLET_IP \
  --record-ttl 300

# Create SSL certificate
echo "Creating SSL certificate..."
doctl compute certificate create \
  --name "${SUBDOMAIN}-ssl-cert" \
  --dns-names "${SUBDOMAIN}.${DOMAIN}" \
  --type lets_encrypt

# Wait for certificate
sleep 60

# Create load balancer
echo "Creating load balancer..."
CERT_ID=$(doctl compute certificate list --format ID,Name --no-header | grep "${SUBDOMAIN}-ssl-cert" | awk '{print $1}')
doctl compute load-balancer create \
  --name "${SUBDOMAIN}-lb" \
  --forwarding-rules "entry_protocol:https,entry_port:443,target_protocol:http,target_port:80,certificate_id:$CERT_ID" \
  --forwarding-rules "entry_protocol:http,entry_port:80,target_protocol:http,target_port:80" \
  --health-check "protocol:http,port:80,path:/health" \
  --region nyc3 \
  --droplet-ids $(doctl compute droplet list --format ID,Name --no-header | grep $DROPLET_NAME | awk '{print $1}')

echo "✅ DigitalOcean deployment complete!"
echo "Your Varnish cache is available at: https://${SUBDOMAIN}.${DOMAIN}"
```

### **Linode Custom Domain Deployment**

Create `deployment/varnish/deploy-linode-custom.sh`:

```bash
#!/bin/bash

set -e

DOMAIN="${1:-your-domain.com}"
SUBDOMAIN="${2:-cache}"
INSTANCE_LABEL="varnish-${SUBDOMAIN}"

echo "🟦 Deploying Varnish with custom domain on Linode..."
echo "Domain: ${SUBDOMAIN}.${DOMAIN}"

# Create Linode instance
echo "Creating Linode instance..."
linode-cli linodes create \
  --type g6-standard-2 \
  --region us-east \
  --image linode/ubuntu22.04 \
  --label $INSTANCE_LABEL \
  --root_pass $(openssl rand -base64 32) \
  --authorized_keys "$(cat ~/.ssh/id_rsa.pub)"

# Get instance IP
INSTANCE_IP=$(linode-cli linodes list --text --format "label,ipv4" | grep $INSTANCE_LABEL | awk '{print $2}')
echo "Instance IP: $INSTANCE_IP"

# Configure DNS
echo "Configuring DNS..."
DOMAIN_ID=$(linode-cli domains list --text --format "id,domain" | grep $DOMAIN | awk '{print $1}')
linode-cli domains records create $DOMAIN_ID \
  --type A \
  --name $SUBDOMAIN \
  --target $INSTANCE_IP \
  --ttl_sec 300

# Create NodeBalancer
echo "Creating NodeBalancer..."
NODEBALANCER_ID=$(linode-cli nodebalancers create \
  --region us-east \
  --label "${SUBDOMAIN}-nodebalancer" \
  --text --format "id" --no-headers)

# Add instance to NodeBalancer
INSTANCE_ID=$(linode-cli linodes list --text --format "id,label" | grep $INSTANCE_LABEL | awk '{print $1}')
linode-cli nodebalancers configs create $NODEBALANCER_ID \
  --port 80 \
  --protocol http \
  --algorithm roundrobin

echo "✅ Linode deployment complete!"
echo "Your Varnish cache is available at: http://${SUBDOMAIN}.${DOMAIN}"
echo "Configure SSL certificate manually using certbot on the instance"
```

---

## 🔧 **Configuration Management**

### **Environment Variables for Custom Domains**

Create `.env.custom-domain`:

```bash
# Custom domain configuration
CUSTOM_DOMAIN=cache.your-domain.com
API_DOMAIN=api.your-domain.com
SSL_EMAIL=admin@your-domain.com

# DigitalOcean specific
DO_REGION=nyc3
DO_SIZE=s-2vcpu-2gb
DO_SSH_KEY_ID=your-ssh-key-id

# Linode specific
LINODE_REGION=us-east
LINODE_TYPE=g6-standard-2
LONGVIEW_KEY=your-longview-key

# SSL configuration
SSL_PROVIDER=letsencrypt
SSL_AUTO_RENEW=true
SSL_RENEWAL_EMAIL=ssl-admin@your-domain.com
```

### **DNS Configuration Template**

Create `deployment/varnish/dns-config.json`:

```json
{
  "domain": "your-domain.com",
  "records": [
    {
      "type": "A",
      "name": "cache",
      "value": "YOUR_SERVER_IP",
      "ttl": 300
    },
    {
      "type": "CNAME", 
      "name": "api",
      "value": "cache.your-domain.com",
      "ttl": 300
    },
    {
      "type": "TXT",
      "name": "_acme-challenge.cache",
      "value": "ACME_CHALLENGE_VALUE",
      "ttl": 60
    }
  ]
}
```

---

## 📋 **Custom Domain Checklist**

### **Pre-deployment:**
- ✅ **Domain registered** and accessible
- ✅ **DNS provider** configured (DigitalOcean/Linode DNS or external)
- ✅ **SSH keys** added to cloud provider
- ✅ **CLI tools** installed (doctl/linode-cli)

### **DigitalOcean Setup:**
- ✅ **Droplet created** with appropriate size
- ✅ **DNS records** configured (A record for subdomain)
- ✅ **SSL certificate** created via Let's Encrypt
- ✅ **Load balancer** configured with SSL termination
- ✅ **Varnish VCL** updated for custom domain
- ✅ **Health checks** configured

### **Linode Setup:**
- ✅ **Instance created** with appropriate plan
- ✅ **DNS records** configured in Linode DNS Manager
- ✅ **NodeBalancer** created for load balancing
- ✅ **SSL certificate** installed via Certbot
- ✅ **Varnish VCL** updated for custom domain
- ✅ **Longview monitoring** enabled

### **Post-deployment:**
- ✅ **SSL certificate** working (https://)
- ✅ **DNS propagation** complete
- ✅ **Cache headers** present in responses
- ✅ **Health checks** passing
- ✅ **Monitoring** configured and working
- ✅ **Auto-renewal** set up for SSL certificates

---

## 🎯 **Testing Your Custom Domain Setup**

```bash
# Test DNS resolution
dig cache.your-domain.com
nslookup api.your-domain.com

# Test SSL certificate
curl -I https://cache.your-domain.com/health
openssl s_client -connect cache.your-domain.com:443 -servername cache.your-domain.com

# Test Varnish caching
curl -I https://cache.your-domain.com/api/auctions
# Should show X-Cache: HIT or MISS header

# Test API routing
curl https://api.your-domain.com/api/users
curl https://cache.your-domain.com/api/categories

# Performance test
ab -n 100 -c 10 https://cache.your-domain.com/api/auctions
```

---

## 🚀 **Quick Setup Commands**

### **DigitalOcean:**
```bash
# Deploy with custom domain
./deploy-digitalocean-custom.sh your-domain.com cache

# Test deployment
curl -I https://cache.your-domain.com/health
```

### **Linode:**
```bash
# Deploy with custom domain  
./deploy-linode-custom.sh your-domain.com cache

# Test deployment
curl -I https://cache.your-domain.com/health
```

Your custom domain Varnish deployment is now ready with SSL/TLS and optimized caching! 🎉
