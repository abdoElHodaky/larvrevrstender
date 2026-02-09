# PostgreSQL Migration Framework

This directory contains the complete migration framework for transitioning from MySQL to PostgreSQL across all 11 microservices.

## Directory Structure

```
migration/
├── README.md                          # This file
├── PostgreSQL-Migration-Plan.md       # Complete 16-week migration plan
├── assessment/                        # Phase 1: Assessment tools and reports
│   ├── data-volume-analysis.sql      # PostgreSQL compatibility analysis
│   ├── mysql-baseline-report.md      # Baseline performance report template
│   └── service-dependencies.md       # Service dependency analysis
├── scripts/                          # Phase 3: Migration execution scripts
│   ├── benchmark-mysql.sh            # MySQL performance benchmarking
│   ├── mysql-to-postgresql-schema.php # Schema conversion script
│   ├── data-migration.php            # Data migration with validation
│   ├── validate-migration.php        # Comprehensive validation framework
│   ├── rollback-migration.php        # Rollback and recovery procedures
│   └── migration-orchestrator.php    # Central orchestration script
├── config/                           # Configuration files
│   └── migration-config.php          # Central migration configuration
├── docker/                           # Phase 2: Docker infrastructure
│   └── postgresql/
│       └── init.sql                  # PostgreSQL initialization script
├── k8s/                              # Phase 2: Kubernetes infrastructure
│   └── postgresql-helm-values.yaml   # Helm chart configuration
├── reports/                          # Generated migration reports
├── logs/                             # Migration execution logs
├── state/                            # Migration state tracking
└── backups/                          # Database backups
```

## Quick Start

### 1. Phase 1-2: Assessment and Infrastructure Setup

```bash
# Start PostgreSQL infrastructure
docker compose up postgresql pgbouncer -d

# Run MySQL benchmarking (when MySQL is available)
./migration/scripts/benchmark-mysql.sh

# Analyze data volume and compatibility
mysql -u root -p < migration/assessment/data-volume-analysis.sql
```

### 2. Phase 3: Execute Migration

```bash
# Single service migration
php migration/scripts/migration-orchestrator.php migrate-service gateway-service

# Full migration (all services)
php migration/scripts/migration-orchestrator.php full-migration

# Validate migration
php migration/scripts/validate-migration.php gateway-service full
```

### 3. Rollback (if needed)

```bash
# Configuration rollback only
php migration/scripts/rollback-migration.php gateway-service configuration

# Full rollback (configuration + data)
php migration/scripts/rollback-migration.php gateway-service full

# Emergency rollback (fastest recovery)
php migration/scripts/rollback-migration.php gateway-service emergency
```

## Migration Scripts

### Schema Conversion
- **File**: `scripts/mysql-to-postgresql-schema.php`
- **Purpose**: Converts MySQL schemas to PostgreSQL-compatible schemas
- **Features**: 
  - Handles data type conversions (ENUM, AUTO_INCREMENT, etc.)
  - Converts indexes and constraints
  - Generates conversion reports

### Data Migration
- **File**: `scripts/data-migration.php`
- **Purpose**: Migrates data from MySQL to PostgreSQL with validation
- **Features**:
  - Batch processing for large datasets
  - Progress tracking and logging
  - Data type conversion and validation
  - Sequence updates

### Validation Framework
- **File**: `scripts/validate-migration.php`
- **Purpose**: Comprehensive validation of migrated data
- **Features**:
  - Schema structure validation
  - Data integrity checks
  - Row count verification
  - Performance validation
  - Business logic validation

### Rollback Manager
- **File**: `scripts/rollback-migration.php`
- **Purpose**: Provides rollback capabilities for failed migrations
- **Features**:
  - Configuration rollback
  - Data restoration from backups
  - Emergency recovery procedures
  - Service health validation

### Migration Orchestrator
- **File**: `scripts/migration-orchestrator.php`
- **Purpose**: Central orchestration of the complete migration process
- **Features**:
  - Dependency-aware migration ordering
  - Parallel execution support
  - Error handling and recovery
  - Progress tracking and reporting

## Configuration

### Central Configuration
- **File**: `config/migration-config.php`
- **Contains**:
  - Database connection settings
  - Service configurations and dependencies
  - Migration parameters (batch sizes, timeouts)
  - Data type mappings
  - Performance tuning settings

### Environment Variables
Key environment variables for configuration:

```bash
# Database connections
MYSQL_HOST=mysql
MYSQL_PORT=3306
POSTGRESQL_HOST=postgresql
POSTGRESQL_PORT=5432
PGBOUNCER_HOST=pgbouncer
PGBOUNCER_PORT=6432

# Migration settings
MIGRATION_BATCH_SIZE=1000
MIGRATION_TIMEOUT=300
MIGRATION_STRICT_MODE=false

# Validation settings
VALIDATION_SAMPLE_SIZE=100
VALIDATION_CHECKSUM=true

# Backup settings
BACKUP_ENABLED=true
BACKUP_RETENTION_DAYS=30
```

## Service Migration Order

Based on dependency analysis, services are migrated in this order:

### Phase 1: Foundation Services (Priority 1)
- **gateway-service** - No dependencies, central routing
- **auth-service** - No dependencies, authentication foundation
- **user-service** - Depends on auth-service

### Phase 2: Business Logic Services (Priority 2)
- **order-service** - Depends on user-service, auth-service
- **payment-service** - Depends on order-service, user-service, auth-service
- **bidding-service** - Depends on user-service, auth-service

### Phase 3: Extended Services (Priority 3)
- **auction-service** - Depends on bidding-service, user-service, auth-service
- **notification-service** - Depends on auction-service, user-service, auth-service
- **vin-ocr-service** - Depends on user-service, auth-service

### Phase 4: Analytics (Priority 4)
- **analytics-service** - Depends on all other services, OLAP implementation

## Database Features

### PostgreSQL Extensions Installed
- **uuid-ossp** - UUID generation
- **pg_stat_statements** - Query performance monitoring
- **pg_trgm** - Full-text search and trigram indexes
- **pgcrypto** - Password hashing (Auth and Payment services)
- **btree_gin, btree_gist** - Advanced indexing for Analytics

### Connection Pooling
- **PgBouncer** configured for transaction-level pooling
- **1000 max client connections**, 25 default pool size
- **Port 6432** for pooled connections

### Performance Optimizations
- **shared_buffers**: 256MB
- **effective_cache_size**: 1GB
- **work_mem**: 4MB
- **maintenance_work_mem**: 64MB

## Monitoring and Logging

### Log Files
- **orchestrator_YYYY-MM-DD.log** - Main orchestration logs
- **data_migration_YYYY-MM-DD.log** - Data migration logs
- **validation_YYYY-MM-DD.log** - Validation logs
- **rollback_YYYY-MM-DD.log** - Rollback operation logs

### Reports
- **migration_plan_TIMESTAMP.json** - Complete migration execution plan
- **data_migration_SERVICE_TIMESTAMP.json** - Per-service migration results
- **validation_SERVICE_TIMESTAMP.json** - Validation results
- **rollback_SERVICE_TIMESTAMP.json** - Rollback operation results

### State Tracking
- **migration_state.json** - Tracks completed and failed services
- Enables resuming interrupted migrations
- Provides rollback coordination

## Troubleshooting

### Common Issues

1. **Connection Timeouts**
   - Increase `MIGRATION_TIMEOUT` environment variable
   - Check database connectivity and network latency

2. **Memory Issues**
   - Reduce `MIGRATION_BATCH_SIZE`
   - Increase `MIGRATION_MEMORY_LIMIT`

3. **Data Type Conversion Errors**
   - Review conversion logs in reports directory
   - Check `type_mapping` configuration in migration-config.php

4. **Validation Failures**
   - Run validation with `quick` level first
   - Check specific validation reports for detailed errors

### Emergency Procedures

1. **Stop Migration**
   ```bash
   # Kill running processes
   pkill -f migration-orchestrator.php
   
   # Check migration state
   cat migration/state/migration_state.json
   ```

2. **Emergency Rollback**
   ```bash
   # Rollback all completed services
   php migration/scripts/rollback-migration.php <service-name> emergency
   ```

3. **Service Health Check**
   ```bash
   # Check service endpoints
   curl http://localhost:8000/health  # gateway-service
   curl http://localhost:8001/health  # auth-service
   # ... etc for other services
   ```

## Security Considerations

- All database passwords are encrypted in configuration
- TruffleHog scanning prevents secret commits
- Backup files are created with restricted permissions
- Connection pooling reduces attack surface
- Audit logging for all migration operations

## Performance Expectations

### Expected Improvements
- **JSON handling**: 2-3x faster with JSONB
- **Full-text search**: 5-10x faster with GIN indexes
- **Concurrent reads**: 20-30% improvement
- **OLAP queries**: 50-100% improvement for analytics

### Migration Performance
- **Small tables** (< 1M rows): 5-10 minutes
- **Medium tables** (1-10M rows): 30-60 minutes
- **Large tables** (> 10M rows): 2-4 hours
- **Total migration time**: 8-16 hours (depending on data volume)

## Support and Maintenance

### Post-Migration Tasks
1. Update monitoring dashboards for PostgreSQL metrics
2. Adjust backup schedules and retention policies
3. Update documentation and runbooks
4. Train team on PostgreSQL-specific operations
5. Optimize queries based on PostgreSQL query planner

### Ongoing Monitoring
- Monitor query performance with pg_stat_statements
- Track connection pool utilization
- Monitor disk space and growth patterns
- Regular VACUUM and ANALYZE operations
- Index usage analysis and optimization

---

For detailed migration procedures and troubleshooting, refer to the complete migration plan in `PostgreSQL-Migration-Plan.md`.

