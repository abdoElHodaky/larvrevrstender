# 🚀 Deployment Configuration - Reverse Tender Platform

This directory contains all deployment configurations, infrastructure as code, and automation scripts for the multi-cloud Reverse Tender Platform deployment.

## 📁 Directory Structure

```
deployment/
├── README.md                          # This file
├── docker/                           # Docker configurations
│   ├── production/                   # Production Docker configs
│   ├── staging/                      # Staging Docker configs
│   └── development/                  # Development Docker configs
├── terraform/                        # Infrastructure as Code
│   ├── digitalocean/                 # DigitalOcean infrastructure
│   ├── linode/                       # Linode infrastructure
│   └── shared/                       # Shared modules
├── ansible/                          # Configuration Management
│   ├── playbooks/                    # Ansible playbooks
│   ├── roles/                        # Reusable roles
│   └── inventory/                    # Server inventories
├── kubernetes/                       # K8s configurations (future)
│   ├── manifests/                    # K8s manifests
│   └── helm/                         # Helm charts
├── monitoring/                       # Monitoring configurations
│   ├── prometheus/                   # Prometheus configs
│   ├── grafana/                      # Grafana dashboards
│   └── elk/                          # ELK stack configs
├── scripts/                          # Deployment scripts
│   ├── deploy.sh                     # Main deployment script
│   ├── backup.sh                     # Backup automation
│   └── health-check.sh               # Health monitoring
└── multi-cloud/                      # Multi-cloud specific configs
    ├── load-balancer/                # Load balancer configs
    ├── database-replication/         # DB replication setup
    └── failover/                     # Failover automation
```

## 🎯 Deployment Environments

### **🔧 Development Environment**
- **Purpose**: Local development and testing
- **Infrastructure**: Docker Compose on developer machines
- **Database**: Local MySQL and Redis containers
- **Services**: All 8 microservices running locally
- **Access**: http://localhost:8000

### **🎭 Staging Environment**
- **Purpose**: Pre-production testing and QA
- **Infrastructure**: Single DigitalOcean droplet
- **Database**: Managed MySQL and Redis
- **Services**: All services with staging configurations
- **Access**: https://staging.reversetender.com

### **🚀 Production Environment**
- **Purpose**: Live production system
- **Infrastructure**: Multi-cloud (DigitalOcean + Linode)
- **Database**: High-availability MySQL with replication
- **Services**: Load-balanced microservices
- **Access**: https://reversetender.com

## 🏗️ Infrastructure as Code

### **Terraform Modules**
```hcl
# Main infrastructure module
module "reverse_tender_infrastructure" {
  source = "./terraform/shared/reverse-tender"
  
  # Environment configuration
  environment = var.environment
  region = var.region
  
  # Cluster configuration
  app_server_count = 3
  app_server_size = "s-2vcpu-4gb"
  
  # Database configuration
  db_primary_size = "s-4vcpu-8gb"
  db_replica_size = "s-2vcpu-4gb"
  
  # Cache configuration
  redis_primary_size = "s-1vcpu-2gb"
  redis_replica_size = "s-1vcpu-2gb"
  
  # Monitoring
  monitoring_enabled = true
  monitoring_size = "s-2vcpu-4gb"
  
  # Security
  enable_firewall = true
  enable_backups = true
  
  # Tags
  tags = {
    Project = "reverse-tender"
    Environment = var.environment
    ManagedBy = "terraform"
  }
}
```

### **Ansible Automation**
```yaml
# Main deployment playbook
- name: Deploy Reverse Tender Platform
  hosts: all
  become: yes
  vars:
    app_version: "{{ lookup('env', 'APP_VERSION') | default('latest') }}"
    environment: "{{ lookup('env', 'ENVIRONMENT') | default('production') }}"
  
  roles:
    - docker-setup
    - nginx-proxy
    - mysql-setup
    - redis-setup
    - application-deploy
    - monitoring-setup
    - security-hardening
  
  tasks:
    - name: Deploy application services
      docker_compose:
        project_src: /opt/reverse-tender
        files:
          - docker-compose.{{ environment }}.yml
        state: present
        
    - name: Run database migrations
      command: >
        docker-compose -f docker-compose.{{ environment }}.yml
        exec -T api-gateway php artisan migrate --force
        
    - name: Clear application cache
      command: >
        docker-compose -f docker-compose.{{ environment }}.yml
        exec -T api-gateway php artisan config:cache
```

## 🔄 Deployment Strategies

### **1. Blue-Green Deployment**
```bash
#!/bin/bash
# Blue-Green deployment script

ENVIRONMENT=$1
NEW_VERSION=$2

echo "🚀 Starting Blue-Green deployment..."
echo "Environment: $ENVIRONMENT"
echo "Version: $NEW_VERSION"

# Deploy to green environment
echo "📦 Deploying to green environment..."
ansible-playbook -i inventory/$ENVIRONMENT-green deploy.yml \
  -e app_version=$NEW_VERSION \
  -e environment=$ENVIRONMENT

# Health check green environment
echo "❤️ Running health checks..."
./scripts/health-check.sh $ENVIRONMENT-green

if [ $? -eq 0 ]; then
  echo "✅ Health checks passed, switching traffic..."
  # Switch load balancer to green
  ansible-playbook -i inventory/$ENVIRONMENT switch-traffic.yml \
    -e target_environment=green
  
  echo "🎉 Deployment successful!"
else
  echo "❌ Health checks failed, keeping blue environment"
  exit 1
fi
```

### **2. Rolling Deployment**
```bash
#!/bin/bash
# Rolling deployment script

ENVIRONMENT=$1
NEW_VERSION=$2

echo "🔄 Starting rolling deployment..."

# Deploy to each server sequentially
for server in app-1 app-2 app-3; do
  echo "📦 Deploying to $server..."
  
  # Remove server from load balancer
  ansible-playbook -i inventory/$ENVIRONMENT lb-remove.yml \
    -e target_server=$server
  
  # Deploy new version
  ansible-playbook -i inventory/$ENVIRONMENT deploy-single.yml \
    -e target_server=$server \
    -e app_version=$NEW_VERSION
  
  # Health check
  ./scripts/health-check.sh $ENVIRONMENT $server
  
  if [ $? -eq 0 ]; then
    # Add server back to load balancer
    ansible-playbook -i inventory/$ENVIRONMENT lb-add.yml \
      -e target_server=$server
    echo "✅ $server deployment successful"
  else
    echo "❌ $server deployment failed"
    exit 1
  fi
  
  # Wait before next server
  sleep 30
done

echo "🎉 Rolling deployment complete!"
```

## 📊 Monitoring & Alerting

### **Prometheus Configuration**
```yaml
# prometheus.yml
global:
  scrape_interval: 15s
  evaluation_interval: 15s

rule_files:
  - "alert_rules.yml"

scrape_configs:
  - job_name: 'reverse-tender-services'
    static_configs:
      - targets: 
        - 'app-1:8000'  # API Gateway
        - 'app-1:8001'  # Auth Service
        - 'app-2:8002'  # Bidding Service
        - 'app-2:8003'  # User Service
        - 'app-2:8004'  # Order Service
        - 'app-3:8005'  # Notification Service
        - 'app-3:8006'  # Payment Service
        - 'app-3:8007'  # Analytics Service
        - 'app-3:8008'  # VIN OCR Service
        
  - job_name: 'mysql-exporter'
    static_configs:
      - targets: ['db-primary:9104', 'db-replica:9104']
      
  - job_name: 'redis-exporter'
    static_configs:
      - targets: ['cache-primary:9121', 'cache-replica:9121']
      
  - job_name: 'node-exporter'
    static_configs:
      - targets: 
        - 'app-1:9100'
        - 'app-2:9100'
        - 'app-3:9100'
        - 'db-primary:9100'
        - 'cache-primary:9100'

alerting:
  alertmanagers:
    - static_configs:
        - targets: ['alertmanager:9093']
```

### **Grafana Dashboards**
- **System Overview**: High-level system health and performance
- **Service Metrics**: Individual microservice performance
- **Database Performance**: MySQL and Redis metrics
- **Business Metrics**: Orders, bids, payments, user activity
- **Infrastructure**: Server resources and network performance

## 🔒 Security Configuration

### **SSL/TLS Setup**
```nginx
# Nginx SSL configuration
server {
    listen 443 ssl http2;
    server_name reversetender.com;
    
    ssl_certificate /etc/ssl/certs/reversetender.com.crt;
    ssl_certificate_key /etc/ssl/private/reversetender.com.key;
    
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    ssl_prefer_server_ciphers off;
    
    # Security headers
    add_header Strict-Transport-Security "max-age=63072000" always;
    add_header X-Frame-Options DENY;
    add_header X-Content-Type-Options nosniff;
    
    location / {
        proxy_pass http://app_servers;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### **Firewall Rules**
```bash
# UFW firewall configuration
ufw default deny incoming
ufw default allow outgoing

# SSH access
ufw allow 22/tcp

# HTTP/HTTPS
ufw allow 80/tcp
ufw allow 443/tcp

# Application ports (internal only)
ufw allow from 10.0.0.0/8 to any port 8000:8008

# Database (internal only)
ufw allow from 10.0.0.0/8 to any port 3306
ufw allow from 10.0.0.0/8 to any port 6379

# Monitoring (internal only)
ufw allow from 10.0.0.0/8 to any port 9090:9200

ufw enable
```

## 📈 Scaling Strategies

### **Auto-scaling Configuration**
```yaml
# Auto-scaling rules
scaling_policies:
  app_servers:
    min_instances: 3
    max_instances: 10
    target_cpu: 70
    scale_up_cooldown: 300
    scale_down_cooldown: 600
    
  database:
    read_replicas:
      min: 1
      max: 3
      cpu_threshold: 80
      
  cache:
    memory_threshold: 85
    eviction_policy: "allkeys-lru"
```

### **Load Testing**
```yaml
# Artillery load test configuration
config:
  target: 'https://reversetender.com'
  phases:
    - duration: 300
      arrivalRate: 10
      name: "Warm up"
    - duration: 600
      arrivalRate: 50
      name: "Normal load"
    - duration: 300
      arrivalRate: 100
      name: "Peak load"
      
scenarios:
  - name: "User registration and bidding"
    weight: 70
    flow:
      - post:
          url: "/api/auth/register"
          json:
            name: "Test User"
            phone: "+966{{ $randomInt(500000000, 599999999) }}"
            password: "TestPass123!"
      - post:
          url: "/api/orders"
          json:
            title: "Need brake pads"
            description: "Front brake pads for Toyota Camry 2020"
            budget_max: 500
```

This comprehensive deployment configuration provides everything needed for a robust, scalable, and secure multi-cloud deployment of the Reverse Tender Platform.

