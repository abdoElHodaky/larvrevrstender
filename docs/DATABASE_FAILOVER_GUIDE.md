# Database Failover Guide for Reverse Tender Platform

## Overview

This guide covers the comprehensive database failover system implemented for the Reverse Tender Platform. The system supports automatic failover across three database providers with different priorities and capabilities.

## Architecture

### Database Providers

The system supports three database providers in a hierarchical failover strategy:

1. **Primary: Neon PostgreSQL** (Priority 1)
   - Serverless PostgreSQL platform
   - Automatic scaling and connection management
   - Built-in backups and point-in-time recovery
   - Branch-based development environments

2. **Secondary: Cloud-Native PostgreSQL (CNPG)** (Priority 2)
   - Self-hosted Kubernetes-native PostgreSQL
   - Built-in replication and automatic failover
   - Backup and recovery with WAL archiving
   - Cluster management with primary/replica topology

3. **Tertiary: MongoDB Atlas** (Priority 3)
   - Cloud-hosted MongoDB with automatic failover
   - Replica sets with automatic recovery
   - Atlas CLI for cluster management
   - Multi-region deployment support

### Failover Strategy

The system implements a **primary-secondary-tertiary** failover strategy:

```
Application Request
    ↓
Database Selector (Init Container)
    ↓
    ├── 1. Try Neon PostgreSQL (Primary)
    ├── 2. Try CNPG PostgreSQL (Secondary)
    ├── 3. Try MongoDB Atlas (Tertiary)
    └── 4. Fallback to SQLite (Emergency)
    ↓
Connection Pool & Health Checks
    ↓
Database Metrics & Monitoring
```

## Configuration

### Environment Variables

#### Database Failover Configuration
```bash
# Failover Strategy
DB_FAILOVER_ENABLED=true
DB_FAILOVER_STRATEGY=primary-secondary-tertiary
DB_FAILOVER_TIMEOUT=30
DB_FAILOVER_RETRY_ATTEMPTS=3
DB_FAILOVER_RETRY_DELAY=5

# Health Check Configuration
DB_FAILOVER_HEALTH_CHECK_INTERVAL=10
DB_FAILOVER_CIRCUIT_BREAKER_ENABLED=true
DB_FAILOVER_CIRCUIT_BREAKER_THRESHOLD=5
DB_FAILOVER_CIRCUIT_BREAKER_TIMEOUT=60
```

#### Neon PostgreSQL Configuration
```bash
# Connection Details
NEON_DATABASE_URL=postgresql://username:password@host.neon.tech:5432/database?sslmode=require
NEON_DB_HOST=host.neon.tech
NEON_DB_USERNAME=username
NEON_DB_PASSWORD=password
NEON_DB_DATABASE=database

# API Configuration
NEON_API_KEY=your_neon_api_key
NEON_PROJECT_ID=your_project_id
NEON_BRANCH_ID=your_branch_id
```

#### CNPG PostgreSQL Configuration
```bash
# Connection Details
CNPG_DB_HOST=reverse-tender-postgres-rw.default.svc.cluster.local
CNPG_DB_HOST_RO=reverse-tender-postgres-ro.default.svc.cluster.local
CNPG_DB_USERNAME=app_user
CNPG_DB_PASSWORD=secure_password
CNPG_DB_DATABASE=reversetender

# Cluster Configuration
CNPG_CLUSTER_NAME=reverse-tender-postgres
CNPG_INSTANCES=3
CNPG_POSTGRESQL_VERSION=16
```

#### MongoDB Atlas Configuration
```bash
# Connection Details
MONGODB_ATLAS_CONNECTION_STRING=mongodb+srv://username:password@cluster.mongodb.net/database?retryWrites=true&w=majority
MONGODB_ATLAS_USERNAME=username
MONGODB_ATLAS_PASSWORD=password
MONGODB_ATLAS_DATABASE=database

# API Configuration
MONGODB_ATLAS_API_PUBLIC_KEY=your_public_key
MONGODB_ATLAS_API_PRIVATE_KEY=your_private_key
MONGODB_ATLAS_PROJECT_ID=your_project_id
```

## Deployment

### Prerequisites

1. **Kubernetes Cluster** with Gateway API support
2. **kubectl** configured with cluster access
3. **Database credentials** for all providers
4. **Monitoring tools** (Prometheus, Grafana) for observability

### Quick Start

1. **Clone the repository and navigate to deployment directory:**
   ```bash
   cd deployment
   ```

2. **Run the database failover setup script:**
   ```bash
   ./scripts/setup-database-failover.sh --provider=all --environment=production
   ```

3. **Update secrets with actual credentials:**
   ```bash
   kubectl edit secret database-failover-secrets -n default
   ```

4. **Verify deployment:**
   ```bash
   kubectl get pods -l database.kubernetes.io/failover-enabled=true
   ```

### Manual Deployment

#### 1. Deploy Database Failover Configuration
```bash
kubectl apply -f k8s/base/database-failover-config.yaml
kubectl apply -f k8s/base/database-failover-secrets.yaml
```

#### 2. Deploy CNPG Operator and Cluster
```bash
kubectl apply -f k8s/base/cnpg-operator.yaml
kubectl apply -f k8s/base/cnpg-cluster.yaml
```

#### 3. Deploy Gateway API with Failover Support
```bash
kubectl apply -f k8s/base/gateway-api/database-failover-gateway.yaml
kubectl apply -f k8s/base/gateway-api/database-failover-policies.yaml
```

#### 4. Deploy Applications with Failover Support
```bash
kubectl apply -f k8s/base/deployments-with-database-failover.yaml
```

## Monitoring and Observability

### Health Check Endpoints

The system provides several health check endpoints:

- **Overall Database Health:** `GET /health/database`
- **Neon PostgreSQL Health:** `GET /health/neon`
- **CNPG PostgreSQL Health:** `GET /health/cnpg`
- **MongoDB Atlas Health:** `GET /health/mongodb`

### Metrics

Key metrics exposed by the system:

- `database_failover_active_provider` - Currently active database provider
- `database_failover_available_count` - Number of available database providers
- `database_connection_pool_size` - Connection pool size per provider
- `database_query_duration_seconds` - Query execution time per provider
- `database_failover_events_total` - Total number of failover events

### Alerts

Recommended alerts for production environments:

```yaml
groups:
- name: database-failover
  rules:
  - alert: DatabaseFailoverTriggered
    expr: database_failover_active != database_failover_primary
    for: 1m
    labels:
      severity: warning
    annotations:
      summary: "Database failover has been triggered"
      description: "Database failover from {{ $labels.from }} to {{ $labels.to }}"
  
  - alert: AllDatabasesDown
    expr: database_failover_available_count == 0
    for: 30s
    labels:
      severity: critical
    annotations:
      summary: "All databases are unavailable"
      description: "All configured databases are currently unavailable"
  
  - alert: DatabaseConnectionPoolExhausted
    expr: database_connection_pool_active / database_connection_pool_max > 0.9
    for: 2m
    labels:
      severity: warning
    annotations:
      summary: "Database connection pool nearly exhausted"
      description: "Connection pool for {{ $labels.provider }} is {{ $value }}% full"
```

## Operations

### Testing Failover

#### 1. Test Primary Database Failover
```bash
# Simulate Neon PostgreSQL failure
kubectl patch configmap database-failover-config -p '{"data":{"NEON_ENABLED":"false"}}'

# Monitor failover
kubectl logs -l app=database-health-monitor -f
```

#### 2. Test Secondary Database Failover
```bash
# Scale down CNPG cluster
kubectl scale cluster reverse-tender-postgres --replicas=0

# Monitor failover
kubectl logs -l app=api-gateway -f
```

#### 3. Test Complete Failover Chain
```bash
# Disable all PostgreSQL providers
kubectl patch configmap database-failover-config -p '{"data":{"NEON_ENABLED":"false","CNPG_ENABLED":"false"}}'

# Verify MongoDB Atlas is used
curl -H "X-Database-Provider: mongodb" https://api.reversetender.com/health/database
```

### Manual Failover

#### Force Failover to Specific Provider
```bash
# Force failover to CNPG
kubectl patch configmap database-failover-config -p '{"data":{"DB_PRIMARY_PROVIDER":"cnpg"}}'

# Force failover to MongoDB
kubectl patch configmap database-failover-config -p '{"data":{"DB_PRIMARY_PROVIDER":"mongodb"}}'

# Restart deployments to pick up changes
kubectl rollout restart deployment/api-gateway
kubectl rollout restart deployment/auth-service
```

### Backup and Recovery

#### CNPG Backup
```bash
# Trigger manual backup
kubectl create job --from=cronjob/reverse-tender-postgres-backup manual-backup-$(date +%Y%m%d-%H%M%S)

# List available backups
kubectl get backups

# Restore from backup
kubectl apply -f - <<EOF
apiVersion: postgresql.cnpg.io/v1
kind: Cluster
metadata:
  name: reverse-tender-postgres-restored
spec:
  instances: 3
  bootstrap:
    recovery:
      backup:
        name: backup-20240101-120000
EOF
```

#### Neon Branch Management
```bash
# Create development branch
curl -X POST "https://console.neon.tech/api/v2/projects/$NEON_PROJECT_ID/branches" \
  -H "Authorization: Bearer $NEON_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"name": "development", "parent_id": "main"}'

# Switch to development branch
kubectl patch secret neon-postgresql-secrets -p '{"data":{"NEON_BRANCH_ID":"'$(echo -n "development_branch_id" | base64)'"}}'
```

## Troubleshooting

### Common Issues

#### 1. Connection Pool Exhaustion
**Symptoms:** Applications receiving connection timeout errors
**Solution:**
```bash
# Increase pool size
kubectl patch configmap database-failover-config -p '{"data":{"DB_POOL_MAX_CONNECTIONS":"100"}}'

# Or restart applications to reset pools
kubectl rollout restart deployment/api-gateway
```

#### 2. Failover Not Triggering
**Symptoms:** Applications still trying to connect to failed database
**Solution:**
```bash
# Check health check configuration
kubectl describe configmap database-failover-config

# Check init container logs
kubectl logs deployment/api-gateway -c database-failover-init

# Manually trigger failover
kubectl delete pods -l app=api-gateway
```

#### 3. CNPG Cluster Not Starting
**Symptoms:** CNPG cluster stuck in pending state
**Solution:**
```bash
# Check operator logs
kubectl logs -n cnpg-system deployment/cnpg-operator

# Check storage class
kubectl get storageclass

# Check node resources
kubectl describe nodes
```

### Debugging Commands

```bash
# Check database failover status
kubectl get configmap database-failover-config -o yaml

# View active database provider
kubectl exec deployment/api-gateway -- cat /shared/active-database

# Check database connection strings
kubectl exec deployment/api-gateway -- cat /shared/database-url

# Monitor failover events
kubectl get events --field-selector reason=DatabaseFailover

# Check database health
kubectl port-forward service/database-health-service 8080:8080
curl http://localhost:8080/health/database
```

## Security Considerations

### Secrets Management
- Use Kubernetes secrets for database credentials
- Rotate credentials regularly using automated tools
- Implement least-privilege access for database users
- Use TLS/SSL for all database connections

### Network Security
- Configure network policies to restrict database access
- Use private networking for CNPG clusters
- Implement IP whitelisting for cloud databases
- Monitor database access logs

### Backup Security
- Encrypt backups at rest and in transit
- Store backups in separate security domains
- Implement backup retention policies
- Test backup restoration procedures regularly

## Performance Optimization

### Connection Pooling
- Configure appropriate pool sizes based on workload
- Use transaction-level pooling for better efficiency
- Monitor pool utilization and adjust as needed
- Implement connection pool monitoring

### Query Optimization
- Use read replicas for read-heavy workloads
- Implement query caching where appropriate
- Monitor slow queries and optimize them
- Use database-specific optimization features

### Resource Allocation
- Allocate sufficient CPU and memory for databases
- Use appropriate storage classes for performance
- Configure resource limits and requests properly
- Monitor resource utilization and scale as needed

## Migration Guide

### From Single Database to Failover
1. **Backup existing data**
2. **Deploy failover infrastructure**
3. **Configure primary database as existing database**
4. **Add secondary and tertiary databases**
5. **Test failover scenarios**
6. **Update application configurations**
7. **Monitor and validate**

### Between Database Providers
1. **Export data from source database**
2. **Set up target database**
3. **Import data to target database**
4. **Update failover configuration**
5. **Test application functionality**
6. **Switch traffic gradually**
7. **Decommission old database**

## Best Practices

### Development
- Use database branches for feature development
- Implement database schema versioning
- Use migrations for schema changes
- Test failover scenarios in development

### Staging
- Mirror production database configuration
- Test failover procedures regularly
- Validate backup and recovery processes
- Performance test under load

### Production
- Monitor database health continuously
- Implement automated failover testing
- Maintain up-to-date runbooks
- Regular disaster recovery drills

## Support and Resources

### Documentation
- [Neon PostgreSQL Documentation](https://neon.tech/docs)
- [Cloud-Native PostgreSQL Documentation](https://cloudnative-pg.io/documentation/)
- [MongoDB Atlas Documentation](https://docs.atlas.mongodb.com/)
- [Kubernetes Gateway API Documentation](https://gateway-api.sigs.k8s.io/)

### Community
- [CNPG Community Slack](https://cloudnative-pg.slack.com)
- [Neon Community Discord](https://discord.gg/neon)
- [MongoDB Community Forums](https://community.mongodb.com/)

### Support Contacts
- **Infrastructure Team:** infrastructure@reversetender.com
- **Database Team:** database@reversetender.com
- **On-call Support:** +1-555-0123 (24/7)

---

**Last Updated:** March 8, 2026  
**Version:** 3.0.0  
**Maintainer:** Infrastructure Team
