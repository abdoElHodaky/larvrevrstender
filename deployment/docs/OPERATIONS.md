<div style="max-width: 38.2rem; line-height: 1.618; font-family: 'Inter', 'Segoe UI', 'Roboto', sans-serif;">

# <span style="font-size: 42px; font-weight: 700; line-height: 1.618;">⚙️ Operations Guide</span>

<p style="font-size: 16px; line-height: 1.618; margin-bottom: 2rem;">Comprehensive operational procedures for the <strong>Reverse Tender Platform</strong> running on Laravel 12 + Octane with FrankenPHP, deployed using microservices architecture with Kubernetes orchestration.</p>

## <span style="font-size: 26px; font-weight: 600; line-height: 1.618;">🎯 Operations Strategy Overview</span>

### <span style="font-size: 20px; font-weight: 600; line-height: 1.618;">62% Major Concepts</span>

- **🏗️ Microservices Architecture**: 9 services with Laravel 12 + Octane and FrankenPHP optimization
- **📊 Comprehensive Monitoring**: Kubernetes orchestration with health endpoints and alerting systems
- **🔄 Operational Excellence**: Deployment procedures, scaling operations, and emergency response protocols

<details style="border-left: 3px solid #4ECDC4; padding-left: 1rem; margin: 1rem 0;">
<summary style="font-weight: 600; cursor: pointer;">⚙️ Complete Operations Manual</summary>

### Table of Contents

1. [Platform Architecture](#platform-architecture)
2. [Environment Management](#environment-management)
3. [Deployment Procedures](#deployment-procedures)
4. [Service Management](#service-management)
5. [Monitoring and Alerting](#monitoring-and-alerting)
6. [Scaling Operations](#scaling-operations)
7. [Backup and Recovery](#backup-and-recovery)
8. [Security Operations](#security-operations)
9. [Performance Optimization](#performance-optimization)
10. [Emergency Procedures](#emergency-procedures)

### Platform Architecture

#### Services Overview

The platform consists of 9 microservices:

| Service | Purpose | Port | Health Endpoint |
|---------|---------|------|-----------------|
| api-gateway | Main API gateway and routing | 80 | `/octane/health` |
| auth-service | Authentication and authorization | 80 | `/octane/health` |
| user-service | User management | 80 | `/octane/health` |
| bidding-service | Real-time bidding with WebSockets | 80 | `/octane/health` |
| order-service | Order processing | 80 | `/octane/health` |
| payment-service | Payment processing | 80 | `/octane/health` |
| notification-service | Notifications and messaging | 80 | `/octane/health` |
| analytics-service | Analytics and reporting | 80 | `/octane/health` |
| vin-ocr-service | VIN OCR processing | 80 | `/octane/health` |

#### Infrastructure Components

- **Database**: MySQL 8.0 with master-slave replication
- **Cache**: Redis 7.0 with clustering
- **Load Balancer**: NGINX Ingress Controller
- **Monitoring**: Prometheus + Grafana + Jaeger
- **Container Runtime**: Docker with FrankenPHP
- **Orchestration**: Kubernetes 1.28+

## Environment Management

### Environment Configuration

The platform supports three environments:

#### Development Environment
```bash
# Configuration
OCTANE_WORKERS=2
OCTANE_TASK_WORKERS=2
OCTANE_WATCH=true
PHP_OPCACHE_ENABLE=0
APP_DEBUG=true

# Purpose: Local development with hot reload
# Resource Usage: Minimal (256M memory limit)
```

#### Staging Environment
```bash
# Configuration
OCTANE_WORKERS=3
OCTANE_TASK_WORKERS=4
OCTANE_WATCH=false
PHP_OPCACHE_ENABLE=1
APP_DEBUG=false

# Purpose: Testing and performance validation
# Resource Usage: Moderate (384M memory limit)
```

#### Production Environment
```bash
# Configuration
OCTANE_WORKERS=4
OCTANE_TASK_WORKERS=6
OCTANE_WATCH=false
PHP_OPCACHE_ENABLE=1
APP_DEBUG=false

# Purpose: High-performance production workloads
# Resource Usage: Optimized (512M memory limit)
```

### Environment Switching

To switch between environments:

```bash
# Set environment variables
export APP_ENV=production
export KUBE_NAMESPACE=reverse-tender-prod

# Apply environment-specific configuration
kubectl apply -k deployment/k8s/overlays/production/

# Verify deployment
kubectl get pods -n $KUBE_NAMESPACE
```

## Deployment Procedures

### Standard Deployment

1. **Pre-deployment Checks**
   ```bash
   # Run validation scripts
   ./deployment/scripts/validate-octane.sh
   ./deployment/scripts/test-deployment.sh
   
   # Check cluster health
   kubectl cluster-info
   kubectl get nodes
   ```

2. **Deploy to Staging**
   ```bash
   # Deploy to staging first
   kubectl apply -k deployment/k8s/overlays/staging/
   
   # Wait for rollout
   kubectl rollout status deployment/staging-api-gateway -n reverse-tender-staging
   
   # Run health checks
   ./deployment/scripts/health-check.sh
   ```

3. **Deploy to Production**
   ```bash
   # Deploy to production
   kubectl apply -k deployment/k8s/overlays/production/
   
   # Monitor rollout
   kubectl rollout status deployment/api-gateway -n reverse-tender
   
   # Verify all services
   kubectl get pods -n reverse-tender
   ```

### Rolling Updates

For zero-downtime updates:

```bash
# Update image tag in kustomization.yaml
# Then apply the changes
kubectl apply -k deployment/k8s/overlays/production/

# Monitor the rollout
kubectl rollout status deployment/api-gateway -n reverse-tender

# If issues occur, rollback
kubectl rollout undo deployment/api-gateway -n reverse-tender
```

### Blue-Green Deployment

For critical updates:

```bash
# Create blue environment
kubectl apply -k deployment/k8s/overlays/production/ --namespace=reverse-tender-blue

# Test blue environment
./deployment/scripts/health-check.sh

# Switch traffic (update ingress)
kubectl patch ingress reverse-tender-ingress -n reverse-tender -p '{"spec":{"rules":[{"host":"api.reversetender.com","http":{"paths":[{"path":"/","pathType":"Prefix","backend":{"service":{"name":"blue-api-gateway","port":{"number":80}}}}]}}]}}'

# Monitor and cleanup green environment when stable
kubectl delete namespace reverse-tender-green
```

## Service Management

### Octane Worker Management

#### Check Worker Status
```bash
# Using the management script
./deployment/scripts/octane-management.sh status

# Manual check via API
curl http://api.reversetender.com/octane/health
curl http://api.reversetender.com/octane/metrics
```

#### Restart Workers
```bash
# Graceful restart of all workers
./deployment/scripts/octane-management.sh restart-workers

# Restart specific service workers
kubectl exec -n reverse-tender deployment/api-gateway -- php artisan octane:reload
```

#### Scale Workers
```bash
# Update worker count in environment config
# Then restart the service
kubectl rollout restart deployment/api-gateway -n reverse-tender
```

### Service Scaling

#### Manual Scaling
```bash
# Scale specific service
kubectl scale deployment api-gateway --replicas=5 -n reverse-tender

# Scale all services
for service in api-gateway auth-service user-service bidding-service order-service payment-service notification-service analytics-service vin-ocr-service; do
    kubectl scale deployment $service --replicas=3 -n reverse-tender
done
```

#### Auto-scaling Configuration
```bash
# Check HPA status
kubectl get hpa -n reverse-tender

# Update HPA settings
kubectl patch hpa api-gateway-hpa -n reverse-tender -p '{"spec":{"maxReplicas":10}}'
```

## Monitoring and Alerting

### Accessing Monitoring Tools

#### Grafana Dashboard
```bash
# Port forward to access locally
kubectl port-forward svc/grafana 3000:3000 -n monitoring

# Access at http://localhost:3000
# Default credentials: admin/admin
```

#### Prometheus
```bash
# Port forward to access locally
kubectl port-forward svc/prometheus 9090:9090 -n monitoring

# Access at http://localhost:9090
```

#### Jaeger Tracing
```bash
# Port forward to access locally
kubectl port-forward svc/jaeger-query 16686:16686 -n monitoring

# Access at http://localhost:16686
```

### Key Metrics to Monitor

#### Octane Metrics
- `octane_workers_active` - Active worker count
- `octane_workers_total` - Total worker count
- `octane_requests_total` - Total requests processed
- `octane_request_duration_seconds` - Request processing time
- `octane_memory_usage_bytes` - Memory usage per worker

#### Application Metrics
- `http_requests_total` - HTTP request count
- `http_request_duration_seconds` - HTTP request duration
- `mysql_connections_active` - Database connections
- `redis_connected_clients` - Redis connections

#### Infrastructure Metrics
- `node_cpu_usage_percent` - CPU usage
- `node_memory_usage_percent` - Memory usage
- `node_disk_usage_percent` - Disk usage
- `kube_pod_status_ready` - Pod readiness

### Alert Response Procedures

#### High Error Rate Alert
1. Check Grafana dashboard for affected services
2. Review application logs: `kubectl logs -n reverse-tender deployment/api-gateway`
3. Check database connectivity
4. Scale up affected services if needed
5. Investigate root cause in application code

#### High Memory Usage Alert
1. Check Octane worker memory usage
2. Restart workers if memory leak detected: `./deployment/scripts/octane-management.sh restart-workers`
3. Scale up service replicas if needed
4. Review application code for memory leaks

#### Database Connection Issues
1. Check MySQL status: `kubectl exec -n reverse-tender deployment/mysql -- mysqladmin status`
2. Check connection pool settings
3. Scale database if needed
4. Review slow query log

## Scaling Operations

### Horizontal Scaling

#### Automatic Scaling (HPA)
The platform uses Horizontal Pod Autoscaler based on:
- CPU utilization (target: 70%)
- Memory utilization (target: 80%)
- Custom metrics (requests per second)

#### Manual Scaling Commands
```bash
# Scale API Gateway
kubectl scale deployment api-gateway --replicas=10 -n reverse-tender

# Scale Bidding Service (high traffic)
kubectl scale deployment bidding-service --replicas=15 -n reverse-tender

# Scale Payment Service (critical)
kubectl scale deployment payment-service --replicas=8 -n reverse-tender
```

### Vertical Scaling

#### Update Resource Limits
```bash
# Update deployment resource limits
kubectl patch deployment api-gateway -n reverse-tender -p '{"spec":{"template":{"spec":{"containers":[{"name":"api-gateway","resources":{"limits":{"memory":"1Gi","cpu":"1000m"}}}]}}}}'
```

#### Octane Worker Scaling
```bash
# Update worker count in environment config
# Edit deployment/config/environments/production.env
OCTANE_WORKERS=6
OCTANE_TASK_WORKERS=8

# Apply changes
kubectl rollout restart deployment/api-gateway -n reverse-tender
```

## Backup and Recovery

### Database Backup

#### Automated Backup
```bash
# Daily backup script (run via cron)
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
kubectl exec -n reverse-tender deployment/mysql -- mysqldump --all-databases > backup_$DATE.sql
aws s3 cp backup_$DATE.sql s3://reversetender-backups/mysql/
```

#### Manual Backup
```bash
# Create backup
kubectl exec -n reverse-tender deployment/mysql -- mysqldump reverse_tender > backup.sql

# Restore backup
kubectl exec -i -n reverse-tender deployment/mysql -- mysql reverse_tender < backup.sql
```

### Configuration Backup

#### Kubernetes Resources
```bash
# Backup all Kubernetes resources
kubectl get all -n reverse-tender -o yaml > k8s-backup.yaml

# Backup secrets and configmaps
kubectl get secrets,configmaps -n reverse-tender -o yaml > k8s-config-backup.yaml
```

#### Application Configuration
```bash
# Backup environment configurations
tar -czf config-backup.tar.gz deployment/config/

# Backup monitoring configuration
tar -czf monitoring-backup.tar.gz deployment/monitoring/
```

## Security Operations

### Certificate Management

#### SSL Certificate Renewal
```bash
# Check certificate expiry
kubectl get secret tls-secret -n reverse-tender -o jsonpath='{.data.tls\.crt}' | base64 -d | openssl x509 -noout -dates

# Update certificate
kubectl create secret tls tls-secret --cert=new-cert.pem --key=new-key.pem -n reverse-tender --dry-run=client -o yaml | kubectl apply -f -
```

#### Secret Rotation
```bash
# Rotate JWT secret
kubectl patch secret app-secrets -n reverse-tender -p '{"data":{"JWT_SECRET":"'$(echo -n "new-jwt-secret" | base64)'"}}'

# Restart services to pick up new secret
kubectl rollout restart deployment/auth-service -n reverse-tender
```

### Security Monitoring

#### Check for Vulnerabilities
```bash
# Scan for secrets in code
trufflehog filesystem deployment/ --no-update

# Check container vulnerabilities
trivy image reversetender/api-gateway:latest
```

#### Network Policy Validation
```bash
# Test network policies
kubectl exec -n reverse-tender deployment/api-gateway -- nc -zv mysql 3306
kubectl exec -n reverse-tender deployment/api-gateway -- nc -zv redis 6379
```

## Performance Optimization

### Octane Performance Tuning

#### Worker Optimization
```bash
# Monitor worker performance
curl http://api.reversetender.com/octane/metrics | grep octane_workers

# Adjust worker count based on load
# Edit environment configuration and restart services
```

#### Memory Optimization
```bash
# Check memory usage per worker
kubectl top pods -n reverse-tender

# Adjust memory limits if needed
kubectl patch deployment api-gateway -n reverse-tender -p '{"spec":{"template":{"spec":{"containers":[{"name":"api-gateway","resources":{"limits":{"memory":"768Mi"}}}]}}}}'
```

### Database Performance

#### Query Optimization
```bash
# Enable slow query log
kubectl exec -n reverse-tender deployment/mysql -- mysql -e "SET GLOBAL slow_query_log = 'ON';"

# Check slow queries
kubectl exec -n reverse-tender deployment/mysql -- mysqldumpslow /var/log/mysql/slow.log
```

#### Connection Pool Tuning
```bash
# Check connection status
kubectl exec -n reverse-tender deployment/mysql -- mysql -e "SHOW STATUS LIKE 'Threads_%';"

# Adjust connection limits in application configuration
```

### Cache Optimization

#### Redis Performance
```bash
# Check Redis performance
kubectl exec -n reverse-tender deployment/redis -- redis-cli info stats

# Monitor cache hit ratio
kubectl exec -n reverse-tender deployment/redis -- redis-cli info stats | grep keyspace_hits
```

## Emergency Procedures

### Service Outage Response

#### Immediate Response (0-5 minutes)
1. **Assess Impact**
   ```bash
   # Check service status
   kubectl get pods -n reverse-tender
   ./deployment/scripts/health-check.sh
   ```

2. **Quick Fixes**
   ```bash
   # Restart failing pods
   kubectl delete pod -l app=api-gateway -n reverse-tender
   
   # Scale up healthy services
   kubectl scale deployment bidding-service --replicas=10 -n reverse-tender
   ```

#### Short-term Response (5-30 minutes)
1. **Investigate Root Cause**
   ```bash
   # Check logs
   kubectl logs -n reverse-tender deployment/api-gateway --tail=100
   
   # Check metrics
   # Access Grafana dashboard
   ```

2. **Implement Workarounds**
   ```bash
   # Route traffic to healthy services
   kubectl patch ingress reverse-tender-ingress -n reverse-tender -p '{"spec":{"rules":[...]}}'
   
   # Enable maintenance mode if needed
   ```

#### Long-term Response (30+ minutes)
1. **Deploy Fixes**
   ```bash
   # Deploy hotfix
   kubectl set image deployment/api-gateway api-gateway=reversetender/api-gateway:hotfix -n reverse-tender
   
   # Monitor rollout
   kubectl rollout status deployment/api-gateway -n reverse-tender
   ```

2. **Post-incident Review**
   - Document incident timeline
   - Identify root cause
   - Implement preventive measures
   - Update runbooks

### Database Emergency Procedures

#### Database Failover
```bash
# Promote slave to master
kubectl exec -n reverse-tender deployment/mysql-slave -- mysql -e "STOP SLAVE; RESET MASTER;"

# Update application configuration to point to new master
kubectl patch configmap app-config -n reverse-tender -p '{"data":{"DB_HOST":"mysql-slave"}}'

# Restart applications
kubectl rollout restart deployment -n reverse-tender
```

#### Database Recovery
```bash
# Restore from backup
kubectl exec -i -n reverse-tender deployment/mysql -- mysql < latest-backup.sql

# Verify data integrity
kubectl exec -n reverse-tender deployment/mysql -- mysql -e "CHECK TABLE users, orders, bids;"
```

### Network Issues

#### DNS Resolution Problems
```bash
# Check DNS resolution
kubectl exec -n reverse-tender deployment/api-gateway -- nslookup mysql

# Restart CoreDNS if needed
kubectl rollout restart deployment/coredns -n kube-system
```

#### Load Balancer Issues
```bash
# Check ingress controller
kubectl get pods -n ingress-nginx

# Restart ingress controller
kubectl rollout restart deployment/nginx-ingress-controller -n ingress-nginx
```

## Maintenance Procedures

### Scheduled Maintenance

#### Pre-maintenance Checklist
- [ ] Notify stakeholders
- [ ] Create database backup
- [ ] Scale up services for redundancy
- [ ] Prepare rollback plan
- [ ] Test in staging environment

#### During Maintenance
```bash
# Enable maintenance mode
kubectl patch configmap app-config -n reverse-tender -p '{"data":{"MAINTENANCE_MODE":"true"}}'

# Perform maintenance tasks
# ...

# Disable maintenance mode
kubectl patch configmap app-config -n reverse-tender -p '{"data":{"MAINTENANCE_MODE":"false"}}'
```

#### Post-maintenance Checklist
- [ ] Verify all services are healthy
- [ ] Run health checks
- [ ] Monitor metrics for anomalies
- [ ] Update documentation
- [ ] Notify stakeholders of completion

### Regular Maintenance Tasks

#### Daily
- Check service health status
- Review error logs
- Monitor resource usage
- Verify backup completion

#### Weekly
- Review performance metrics
- Update security patches
- Clean up old logs
- Test disaster recovery procedures

#### Monthly
- Review and update documentation
- Conduct security audit
- Optimize database performance
- Review and update monitoring alerts

## Contact Information

### On-call Rotation
- **Primary**: DevOps Team Lead
- **Secondary**: Senior Backend Developer
- **Escalation**: CTO

### Emergency Contacts
- **Slack**: #ops-emergency
- **Phone**: +1-XXX-XXX-XXXX
- **Email**: ops@reversetender.com

### External Vendors
- **Cloud Provider**: AWS Support
- **Monitoring**: Grafana Labs Support
- **Database**: MySQL Enterprise Support

---

*This document should be reviewed and updated quarterly or after any major infrastructure changes.*
