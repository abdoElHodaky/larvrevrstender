# Production Deployment Guide
## Reverse Tender Platform - Saudi Arabia

### Overview
This guide provides comprehensive instructions for deploying the Reverse Tender Platform to production environments in Saudi Arabia, ensuring ZATCA compliance and optimal performance.

## Prerequisites

### Infrastructure Requirements
- **Kubernetes Cluster**: EKS, GKE, or AKS with minimum 3 nodes
- **Database**: AWS RDS MySQL 8.0+ with read replicas
- **Cache**: Redis Cluster with persistence
- **Storage**: AWS S3 or equivalent object storage
- **CDN**: CloudFront or equivalent for static assets
- **Load Balancer**: Application Load Balancer with SSL termination

### Required Accounts & Services
- AWS Account with appropriate IAM roles
- Domain registration for reversetender.sa
- SSL certificates (Let's Encrypt or commercial)
- Payment gateway accounts (Stripe, PayPal, Mada, STC Pay)
- OCR service accounts (Google Cloud Vision, AWS Textract, Azure)
- ZATCA registration and certificates
- SMS provider account (Unifonic for Saudi Arabia)
- Monitoring services (New Relic, Sentry)

## Environment Setup

### 1. Database Configuration

#### Primary Database (RDS MySQL)
```sql
-- Create production database
CREATE DATABASE reversetender_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create dedicated user
CREATE USER 'reversetender_user'@'%' IDENTIFIED BY 'SECURE_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON reversetender_production.* TO 'reversetender_user'@'%';

-- Performance optimization
SET GLOBAL innodb_buffer_pool_size = 2147483648; -- 2GB
SET GLOBAL max_connections = 1000;
SET GLOBAL query_cache_size = 268435456; -- 256MB
```

#### Read Replica Setup
```bash
# Create read replica in AWS
aws rds create-db-instance-read-replica \
    --db-instance-identifier reversetender-db-replica \
    --source-db-instance-identifier reversetender-db-primary \
    --db-instance-class db.t3.large \
    --publicly-accessible \
    --multi-az
```

### 2. Redis Configuration

#### Redis Cluster Setup
```bash
# Create Redis cluster in AWS ElastiCache
aws elasticache create-replication-group \
    --replication-group-id reversetender-redis \
    --description "Reverse Tender Platform Redis Cluster" \
    --num-cache-clusters 3 \
    --cache-node-type cache.t3.medium \
    --engine redis \
    --engine-version 7.0 \
    --port 6379 \
    --parameter-group-name default.redis7 \
    --subnet-group-name reversetender-subnet-group \
    --security-group-ids sg-xxxxxxxxx \
    --at-rest-encryption-enabled \
    --transit-encryption-enabled
```

### 3. Storage Configuration

#### S3 Bucket Setup
```bash
# Create S3 bucket for file storage
aws s3 mb s3://reversetender-storage-prod --region me-south-1

# Configure bucket policy
aws s3api put-bucket-policy --bucket reversetender-storage-prod --policy '{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "PublicReadGetObject",
      "Effect": "Allow",
      "Principal": "*",
      "Action": "s3:GetObject",
      "Resource": "arn:aws:s3:::reversetender-storage-prod/public/*"
    }
  ]
}'

# Enable versioning
aws s3api put-bucket-versioning --bucket reversetender-storage-prod --versioning-configuration Status=Enabled
```

## Application Deployment

### 1. Container Images

#### Build and Push Images
```bash
# Build User Service
cd services/user-service
docker build -t reversetender/user-service:v1.0.0 .
docker push reversetender/user-service:v1.0.0

# Build Order Service
cd ../order-service
docker build -t reversetender/order-service:v1.0.0 .
docker push reversetender/order-service:v1.0.0

# Build Payment Service
cd ../payment-service
docker build -t reversetender/payment-service:v1.0.0 .
docker push reversetender/payment-service:v1.0.0

# Build Notification Service
cd ../notification-service
docker build -t reversetender/notification-service:v1.0.0 .
docker push reversetender/notification-service:v1.0.0
```

### 2. Kubernetes Deployment

#### Create Namespace
```bash
kubectl create namespace reversetender
kubectl label namespace reversetender name=reversetender
```

#### Deploy Secrets
```bash
# Create environment secrets
kubectl create secret generic app-secrets \
    --from-env-file=deployment/environments/.env.production \
    --namespace=reversetender

# Create SSL certificates
kubectl create secret tls reversetender-tls \
    --cert=ssl/reversetender.sa.crt \
    --key=ssl/reversetender.sa.key \
    --namespace=reversetender

# Create ZATCA certificates
kubectl create secret generic zatca-certificates \
    --from-file=zatca-prod.pem=certificates/zatca-prod.pem \
    --from-file=zatca-prod-key.pem=certificates/zatca-prod-key.pem \
    --namespace=reversetender
```

#### Deploy Services
```bash
# Deploy API Gateway
kubectl apply -f deployment/kubernetes/api-gateway.yaml

# Deploy User Service
kubectl apply -f deployment/kubernetes/user-service.yaml

# Deploy Order Service
kubectl apply -f deployment/kubernetes/order-service.yaml

# Deploy Payment Service
kubectl apply -f deployment/kubernetes/payment-service.yaml

# Deploy Notification Service
kubectl apply -f deployment/kubernetes/notification-service.yaml

# Deploy Redis
kubectl apply -f deployment/kubernetes/redis.yaml

# Deploy Monitoring Stack
kubectl apply -f deployment/kubernetes/monitoring.yaml
```

### 3. Database Migration

#### Run Migrations
```bash
# Connect to User Service pod
kubectl exec -it deployment/user-service -n reversetender -- bash

# Run migrations
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder

# Repeat for other services
kubectl exec -it deployment/order-service -n reversetender -- php artisan migrate --force
kubectl exec -it deployment/payment-service -n reversetender -- php artisan migrate --force
kubectl exec -it deployment/notification-service -n reversetender -- php artisan migrate --force
```

## SSL and Domain Configuration

### 1. DNS Configuration
```bash
# Configure DNS records
# A record: reversetender.sa -> Load Balancer IP
# A record: www.reversetender.sa -> Load Balancer IP
# A record: admin.reversetender.sa -> Load Balancer IP
# A record: api.reversetender.sa -> Load Balancer IP
```

### 2. SSL Certificate Setup
```bash
# Install cert-manager for automatic SSL
kubectl apply -f https://github.com/cert-manager/cert-manager/releases/download/v1.13.0/cert-manager.yaml

# Create ClusterIssuer for Let's Encrypt
kubectl apply -f - <<EOF
apiVersion: cert-manager.io/v1
kind: ClusterIssuer
metadata:
  name: letsencrypt-prod
spec:
  acme:
    server: https://acme-v02.api.letsencrypt.org/directory
    email: admin@reversetender.sa
    privateKeySecretRef:
      name: letsencrypt-prod
    solvers:
    - http01:
        ingress:
          class: nginx
EOF
```

## External Service Configuration

### 1. Payment Gateways

#### Stripe Configuration
```bash
# Test Stripe connection
curl -X POST https://api.stripe.com/v1/payment_intents \
  -H "Authorization: Bearer sk_live_..." \
  -d "amount=1000" \
  -d "currency=sar"
```

#### Mada Configuration
```bash
# Configure Mada webhook endpoint
curl -X POST https://api.mada.sa/v1/webhooks \
  -H "Authorization: Bearer YOUR_MADA_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://api.reversetender.sa/v1/webhooks/mada",
    "events": ["payment.succeeded", "payment.failed"]
  }'
```

### 2. ZATCA Integration

#### Configure ZATCA Certificates
```bash
# Install ZATCA production certificates
kubectl create secret generic zatca-prod-certs \
    --from-file=certificate.pem=zatca/prod/certificate.pem \
    --from-file=private-key.pem=zatca/prod/private-key.pem \
    --namespace=reversetender

# Test ZATCA connection
curl -X POST https://api.zatca.gov.sa/v1/invoices \
  -H "Authorization: Bearer YOUR_ZATCA_TOKEN" \
  -H "Content-Type: application/json" \
  --cert zatca/prod/certificate.pem \
  --key zatca/prod/private-key.pem
```

### 3. OCR Services

#### Google Cloud Vision Setup
```bash
# Create service account key
gcloud iam service-accounts keys create google-cloud-key.json \
    --iam-account=reversetender@project-id.iam.gserviceaccount.com

# Create Kubernetes secret
kubectl create secret generic google-cloud-credentials \
    --from-file=google-cloud-key.json \
    --namespace=reversetender
```

## Monitoring and Logging

### 1. Prometheus and Grafana
```bash
# Deploy monitoring stack
helm repo add prometheus-community https://prometheus-community.github.io/helm-charts
helm repo update

helm install prometheus prometheus-community/kube-prometheus-stack \
    --namespace monitoring \
    --create-namespace \
    --set grafana.adminPassword=SECURE_PASSWORD
```

### 2. ELK Stack for Logging
```bash
# Deploy Elasticsearch
helm repo add elastic https://helm.elastic.co
helm install elasticsearch elastic/elasticsearch \
    --namespace logging \
    --create-namespace

# Deploy Kibana
helm install kibana elastic/kibana \
    --namespace logging

# Deploy Logstash
helm install logstash elastic/logstash \
    --namespace logging
```

### 3. Application Monitoring
```bash
# Configure New Relic
kubectl create secret generic newrelic-license \
    --from-literal=license-key=YOUR_NEW_RELIC_LICENSE_KEY \
    --namespace=reversetender

# Configure Sentry
kubectl create secret generic sentry-dsn \
    --from-literal=dsn=YOUR_SENTRY_DSN \
    --namespace=reversetender
```

## Performance Optimization

### 1. Database Optimization
```sql
-- Create indexes for performance
CREATE INDEX idx_part_requests_status_created ON part_requests(status, created_at);
CREATE INDEX idx_bids_part_request_status ON bids(part_request_id, status);
CREATE INDEX idx_orders_customer_status ON orders(customer_id, status);
CREATE INDEX idx_payments_status_created ON payments(status, created_at);
CREATE INDEX idx_notifications_user_status ON notifications(user_id, status);

-- Configure MySQL for production
SET GLOBAL innodb_buffer_pool_size = 4294967296; -- 4GB
SET GLOBAL innodb_log_file_size = 1073741824; -- 1GB
SET GLOBAL max_connections = 2000;
SET GLOBAL innodb_flush_log_at_trx_commit = 2;
```

### 2. Redis Optimization
```bash
# Configure Redis for production
redis-cli CONFIG SET maxmemory 2gb
redis-cli CONFIG SET maxmemory-policy allkeys-lru
redis-cli CONFIG SET save "900 1 300 10 60 10000"
```

### 3. Application Caching
```bash
# Configure application cache
kubectl exec -it deployment/user-service -n reversetender -- php artisan config:cache
kubectl exec -it deployment/user-service -n reversetender -- php artisan route:cache
kubectl exec -it deployment/user-service -n reversetender -- php artisan view:cache
```

## Security Configuration

### 1. Network Security
```bash
# Configure security groups
aws ec2 create-security-group \
    --group-name reversetender-app \
    --description "Reverse Tender Application Security Group"

# Allow HTTPS traffic
aws ec2 authorize-security-group-ingress \
    --group-id sg-xxxxxxxxx \
    --protocol tcp \
    --port 443 \
    --cidr 0.0.0.0/0

# Allow HTTP traffic (redirect to HTTPS)
aws ec2 authorize-security-group-ingress \
    --group-id sg-xxxxxxxxx \
    --protocol tcp \
    --port 80 \
    --cidr 0.0.0.0/0
```

### 2. WAF Configuration
```bash
# Create WAF rules
aws wafv2 create-web-acl \
    --name reversetender-waf \
    --scope CLOUDFRONT \
    --default-action Allow={} \
    --rules file://waf-rules.json
```

### 3. Backup Configuration
```bash
# Configure automated backups
aws rds modify-db-instance \
    --db-instance-identifier reversetender-db-primary \
    --backup-retention-period 30 \
    --preferred-backup-window "03:00-04:00"

# Configure S3 backup
aws s3api put-bucket-versioning \
    --bucket reversetender-storage-prod \
    --versioning-configuration Status=Enabled

aws s3api put-bucket-lifecycle-configuration \
    --bucket reversetender-storage-prod \
    --lifecycle-configuration file://s3-lifecycle.json
```

## Health Checks and Monitoring

### 1. Application Health Checks
```bash
# Test API endpoints
curl -f https://api.reversetender.sa/v1/health
curl -f https://reversetender.sa/health

# Test database connectivity
kubectl exec -it deployment/user-service -n reversetender -- php artisan tinker --execute="DB::connection()->getPdo();"
```

### 2. Performance Monitoring
```bash
# Monitor response times
curl -w "@curl-format.txt" -o /dev/null -s https://api.reversetender.sa/v1/health

# Monitor database performance
kubectl exec -it mysql-pod -- mysql -u root -p -e "SHOW PROCESSLIST;"
```

## Troubleshooting

### Common Issues

#### 1. Database Connection Issues
```bash
# Check database connectivity
kubectl exec -it deployment/user-service -n reversetender -- php artisan tinker
>>> DB::connection()->getPdo();

# Check database logs
kubectl logs deployment/mysql -n reversetender
```

#### 2. Redis Connection Issues
```bash
# Test Redis connectivity
kubectl exec -it deployment/redis -n reversetender -- redis-cli ping

# Check Redis logs
kubectl logs deployment/redis -n reversetender
```

#### 3. SSL Certificate Issues
```bash
# Check certificate status
kubectl describe certificate reversetender-tls -n reversetender

# Renew certificates
kubectl delete certificate reversetender-tls -n reversetender
kubectl apply -f deployment/kubernetes/certificates.yaml
```

### Log Analysis
```bash
# View application logs
kubectl logs deployment/user-service -n reversetender --tail=100
kubectl logs deployment/order-service -n reversetender --tail=100
kubectl logs deployment/payment-service -n reversetender --tail=100

# View Nginx logs
kubectl logs deployment/api-gateway -n reversetender --tail=100
```

## Maintenance Procedures

### 1. Regular Updates
```bash
# Update application images
kubectl set image deployment/user-service user-service=reversetender/user-service:v1.0.1 -n reversetender
kubectl rollout status deployment/user-service -n reversetender

# Update database schema
kubectl exec -it deployment/user-service -n reversetender -- php artisan migrate --force
```

### 2. Backup Procedures
```bash
# Database backup
kubectl exec -it mysql-pod -n reversetender -- mysqldump -u root -p reversetender_production > backup-$(date +%Y%m%d).sql

# File storage backup
aws s3 sync s3://reversetender-storage-prod s3://reversetender-backup-$(date +%Y%m%d) --region me-south-1
```

### 3. Scaling Procedures
```bash
# Scale application pods
kubectl scale deployment user-service --replicas=5 -n reversetender
kubectl scale deployment order-service --replicas=3 -n reversetender
kubectl scale deployment payment-service --replicas=3 -n reversetender

# Scale database
aws rds modify-db-instance \
    --db-instance-identifier reversetender-db-primary \
    --db-instance-class db.r5.xlarge \
    --apply-immediately
```

## Compliance and Auditing

### ZATCA Compliance Checklist
- [ ] ZATCA certificates installed and configured
- [ ] Invoice generation includes required fields
- [ ] QR codes generated for all invoices
- [ ] Tax calculations accurate (15% VAT)
- [ ] Audit trail maintained for all transactions
- [ ] Regular submission to ZATCA portal

### Security Audit Checklist
- [ ] All secrets stored in Kubernetes secrets
- [ ] SSL certificates valid and auto-renewing
- [ ] Database connections encrypted
- [ ] API rate limiting configured
- [ ] WAF rules active
- [ ] Regular security scans performed
- [ ] Backup and recovery procedures tested

## Support and Escalation

### Contact Information
- **Technical Support**: tech-support@reversetender.sa
- **Security Issues**: security@reversetender.sa
- **ZATCA Compliance**: compliance@reversetender.sa
- **Emergency Hotline**: +966-11-XXX-XXXX

### Escalation Procedures
1. **Level 1**: Application issues, user support
2. **Level 2**: Infrastructure issues, performance problems
3. **Level 3**: Security incidents, data breaches
4. **Level 4**: Business continuity, disaster recovery

This deployment guide ensures a robust, secure, and compliant production environment for the Reverse Tender Platform in Saudi Arabia.

