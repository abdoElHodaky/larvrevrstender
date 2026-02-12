<div style="max-width: 38.2rem; line-height: 1.618; font-family: 'Inter', 'Segoe UI', 'Roboto', sans-serif;">

# <span style="font-size: 42px; font-weight: 700; line-height: 1.618;">⚙️ PostgreSQL Migration Setup Guide</span>

<p style="font-size: 16px; line-height: 1.618; margin-bottom: 2rem;">Step-by-step instructions for <strong>setting up and configuring</strong> the PostgreSQL migration framework for production use with comprehensive environment preparation and validation.</p>

## <span style="font-size: 26px; font-weight: 600; line-height: 1.618;">🎯 Setup Strategy</span>

### <span style="font-size: 20px; font-weight: 600; line-height: 1.618;">62% Major Concepts</span>

- **🏗️ Environment Preparation**: PHP 7.4+, PostgreSQL 12+, Docker orchestration with required extensions
- **🔧 Framework Installation**: Repository setup, dependency management, and configuration validation
- **⚡ Production Readiness**: Database access requirements, network connectivity, and performance optimization

<details style="border-left: 3px solid #4ECDC4; padding-left: 1rem; margin: 1rem 0;">
<summary style="font-weight: 600; cursor: pointer;">📋 Complete Setup Requirements</summary>

### System Requirements
- **PHP 7.4+** with PDO extensions (MySQL and PostgreSQL)
- **MySQL 5.7+** or **MariaDB 10.3+**
- **PostgreSQL 12+** with required extensions
- **Docker** and **Docker Compose** (for containerized deployment)
- **Kubernetes** (optional, for production orchestration)
- **Git** for version control
- **Sufficient disk space** (3x current database size recommended)

### Required PHP Extensions
```bash
# Install required PHP extensions
sudo apt-get install php-pdo php-mysql php-pgsql php-json php-mbstring php-curl

# Verify extensions
php -m | grep -E "(pdo|mysql|pgsql|json)"
```

### Database Access Requirements
- **MySQL**: Full read/write access to all service databases
- **PostgreSQL**: Superuser access for database creation and extension installation
- **Network connectivity** between migration environment and both databases

### Step 1: Clone and Setup Repository

```bash
# Clone the repository
git clone https://github.com/abdoElHodaky/larvrevrstender.git
cd larvrevrstender

# Switch to the migration branch
git checkout gateway

# Verify migration framework files
ls -la migration/
```

### Step 2: Configure Environment Variables

Create environment configuration file:

```bash
# Copy example environment file
cp .env.example .env

# Edit environment variables
nano .env
```

Required environment variables:

```bash
# MySQL Configuration
MYSQL_HOST=localhost
MYSQL_PORT=3306
MYSQL_USERNAME=root
MYSQL_PASSWORD=your_mysql_password

# PostgreSQL Configuration
POSTGRESQL_HOST=localhost
POSTGRESQL_PORT=5432
POSTGRESQL_USERNAME=postgres
POSTGRESQL_PASSWORD=your_postgres_password

# PgBouncer Configuration (for production)
PGBOUNCER_HOST=localhost
PGBOUNCER_PORT=6432

# Migration Settings
MIGRATION_BATCH_SIZE=1000
MIGRATION_TIMEOUT=300
MIGRATION_STRICT_MODE=false
MIGRATION_PARALLEL_WORKERS=1

# Validation Settings
VALIDATION_SAMPLE_SIZE=100
VALIDATION_CHECKSUM=true
VALIDATION_PERFORMANCE_THRESHOLD=1.5

# Backup Settings
BACKUP_ENABLED=true
BACKUP_DIRECTORY=migration/backups
BACKUP_RETENTION_DAYS=30

# Logging Settings
LOG_LEVEL=info
LOG_DIRECTORY=migration/logs
LOG_QUERIES=false

# Monitoring Settings
MONITORING_ENABLED=true
MONITORING_PROGRESS_INTERVAL=1000
MONITORING_HEALTH_CHECK_INTERVAL=30
```

### Step 3: Setup PostgreSQL Infrastructure

#### Option A: Docker Compose (Recommended for Development/Testing)

```bash
# Start PostgreSQL and PgBouncer
docker compose up postgresql pgbouncer -d

# Verify containers are running
docker compose ps

# Check PostgreSQL logs
docker compose logs postgresql

# Test connectivity
docker exec -it reverse_tender_postgresql psql -U postgres -c "SELECT version();"
```

#### Option B: Native PostgreSQL Installation

```bash
# Install PostgreSQL (Ubuntu/Debian)
sudo apt-get update
sudo apt-get install postgresql postgresql-contrib

# Start PostgreSQL service
sudo systemctl start postgresql
sudo systemctl enable postgresql

# Create migration user
sudo -u postgres createuser --superuser migration_user
sudo -u postgres psql -c "ALTER USER migration_user PASSWORD 'secure_password';"
```

### Step 4: Install Required PostgreSQL Extensions

```bash
# Connect to PostgreSQL as superuser
psql -h localhost -U postgres

# Install extensions globally
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_stat_statements";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS "btree_gin";
CREATE EXTENSION IF NOT EXISTS "btree_gist";

# Exit PostgreSQL
\q
```

### Step 5: Initialize Service Databases

```bash
# Run PostgreSQL initialization script
docker exec -it reverse_tender_postgresql psql -U postgres -f /docker-entrypoint-initdb.d/init.sql

# Or manually create databases
psql -h localhost -U postgres -f migration/docker/postgresql/init.sql
```

### Step 6: Configure PgBouncer (Production)

Create PgBouncer configuration:

```bash
# Create PgBouncer config directory
mkdir -p /etc/pgbouncer

# Create pgbouncer.ini
cat > /etc/pgbouncer/pgbouncer.ini << EOF
[databases]
gateway_service = host=postgresql port=5432 dbname=gateway_service
auth_service = host=postgresql port=5432 dbname=auth_service
user_service = host=postgresql port=5432 dbname=user_service
analytics_service = host=postgresql port=5432 dbname=analytics_service
order_service = host=postgresql port=5432 dbname=order_service
payment_service = host=postgresql port=5432 dbname=payment_service
bidding_service = host=postgresql port=5432 dbname=bidding_service
auction_service = host=postgresql port=5432 dbname=auction_service
notification_service = host=postgresql port=5432 dbname=notification_service
vin_ocr_service = host=postgresql port=5432 dbname=vin_ocr_service

[pgbouncer]
listen_port = 6432
listen_addr = *
auth_type = md5
auth_file = /etc/pgbouncer/userlist.txt
logfile = /var/log/pgbouncer/pgbouncer.log
pidfile = /var/run/pgbouncer/pgbouncer.pid
admin_users = postgres
pool_mode = transaction
server_reset_query = DISCARD ALL
max_client_conn = 1000
default_pool_size = 25
reserve_pool_size = 5
EOF

# Create userlist.txt
cat > /etc/pgbouncer/userlist.txt << EOF
"postgres" "md5hashed_password"
"gateway_user" "md5hashed_password"
"auth_user" "md5hashed_password"
EOF
```

### Step 7: Validate Installation

Run the integration test suite:

```bash
# Run integration tests
php migration/scripts/integration-tests.php

# Expected output: All tests should pass
# Integration Test Results: PASSED
# Tests Run: 10, Passed: 10, Failed: 0
```

Run infrastructure validation:

```bash
# Validate PostgreSQL infrastructure
php migration/scripts/validate-infrastructure.php

# Expected output: All infrastructure checks should pass
# Infrastructure Validation: PASSED
# Success Rate: 100%
```

## Configuration Customization

### Service-Specific Configuration

Edit `migration/config/migration-config.php` to customize service settings:

```php
'services' => [
    'gateway-service' => [
        'mysql_database' => 'gateway_service',
        'postgres_database' => 'gateway_service',
        'postgres_user' => 'gateway_user',
        'postgres_password' => 'gateway_password',
        'priority' => 1, // Migration order
        'dependencies' => [], // Service dependencies
        'health_endpoint' => 'http://localhost:8000/health',
        'config_files' => [
            'env' => 'services/gateway-service/.env',
            'database' => 'services/gateway-service/config/database.php',
        ],
    ],
    // ... other services
],
```

### Performance Tuning

Adjust performance settings based on your environment:

```php
'migration' => [
    'batch_size' => 2000, // Increase for better performance
    'parallel_workers' => 4, // Use multiple workers
    'memory_limit' => '1G', // Increase memory limit
],

'performance' => [
    'postgresql' => [
        'shared_buffers' => '512MB', // Increase for larger datasets
        'effective_cache_size' => '2GB',
        'work_mem' => '8MB',
        'maintenance_work_mem' => '128MB',
    ],
],
```

### Environment-Specific Overrides

Configure different settings for different environments:

```php
'environments' => [
    'production' => [
        'migration' => [
            'batch_size' => 5000,
            'parallel_workers' => 8,
            'strict_mode' => false,
        ],
        'postgresql' => [
            'host' => env('PGBOUNCER_HOST', 'pgbouncer'),
            'port' => env('PGBOUNCER_PORT', 6432),
        ],
        'monitoring' => [
            'alert_on_failure' => true,
            'webhook_url' => 'https://hooks.slack.com/your-webhook',
        ],
    ],
],
```

## Security Configuration

### Database Security

```bash
# Create dedicated migration user with limited privileges
psql -h localhost -U postgres << EOF
CREATE USER migration_user WITH PASSWORD 'secure_random_password';
GRANT CREATE ON DATABASE postgres TO migration_user;
GRANT USAGE ON SCHEMA public TO migration_user;
GRANT CREATE ON SCHEMA public TO migration_user;
EOF
```

### File Permissions

```bash
# Set appropriate permissions
chmod 755 migration/scripts/*.php
chmod 600 migration/config/migration-config.php
chmod 700 migration/backups/
chmod 755 migration/logs/
```

### Secrets Management

```bash
# Use environment variables for sensitive data
export MYSQL_PASSWORD="$(cat /path/to/mysql/password/file)"
export POSTGRESQL_PASSWORD="$(cat /path/to/postgresql/password/file)"

# Or use a secrets management system
# kubectl create secret generic migration-secrets \
#   --from-literal=mysql-password=your-mysql-password \
#   --from-literal=postgresql-password=your-postgresql-password
```

## Monitoring and Logging

### Log Configuration

```bash
# Create log rotation configuration
cat > /etc/logrotate.d/migration << EOF
/path/to/migration/logs/*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    create 644 www-data www-data
}
EOF
```

### Monitoring Setup

```bash
# Install monitoring tools (optional)
# Prometheus, Grafana, or your preferred monitoring stack

# Configure PostgreSQL monitoring
# Add to postgresql.conf:
# shared_preload_libraries = 'pg_stat_statements'
# pg_stat_statements.track = all
# log_statement = 'all'
# log_min_duration_statement = 1000
```

## Troubleshooting

### Common Issues

#### 1. Connection Errors
```bash
# Test MySQL connection
mysql -h $MYSQL_HOST -P $MYSQL_PORT -u $MYSQL_USERNAME -p

# Test PostgreSQL connection
psql -h $POSTGRESQL_HOST -p $POSTGRESQL_PORT -U $POSTGRESQL_USERNAME -d postgres

# Test PgBouncer connection
psql -h $PGBOUNCER_HOST -p $PGBOUNCER_PORT -U $POSTGRESQL_USERNAME -d gateway_service
```

#### 2. Permission Issues
```bash
# Check PostgreSQL permissions
psql -h localhost -U postgres -c "SELECT * FROM pg_user;"

# Check file permissions
ls -la migration/scripts/
ls -la migration/config/
```

#### 3. Extension Issues
```bash
# Check installed extensions
psql -h localhost -U postgres -d gateway_service -c "SELECT * FROM pg_extension;"

# Install missing extensions
psql -h localhost -U postgres -d gateway_service -c "CREATE EXTENSION IF NOT EXISTS pg_trgm;"
```

#### 4. Memory Issues
```bash
# Check available memory
free -h

# Adjust batch sizes
export MIGRATION_BATCH_SIZE=500

# Increase PHP memory limit
php -d memory_limit=2G migration/scripts/data-migration.php
```

### Log Analysis

```bash
# Check migration logs
tail -f migration/logs/data_migration_$(date +%Y-%m-%d).log

# Check PostgreSQL logs
tail -f /var/log/postgresql/postgresql-*.log

# Check system resources
htop
iostat -x 1
```

## Performance Optimization

### Database Tuning

#### PostgreSQL Configuration
```bash
# Edit postgresql.conf
sudo nano /etc/postgresql/*/main/postgresql.conf

# Key settings for migration:
shared_buffers = 25% of RAM
effective_cache_size = 75% of RAM
work_mem = 4MB
maintenance_work_mem = 256MB
checkpoint_completion_target = 0.9
wal_buffers = 16MB
default_statistics_target = 100
random_page_cost = 1.1
```

#### MySQL Configuration
```bash
# Edit my.cnf
sudo nano /etc/mysql/my.cnf

# Key settings for migration:
innodb_buffer_pool_size = 70% of RAM
innodb_log_file_size = 256MB
innodb_flush_log_at_trx_commit = 2
query_cache_size = 64MB
tmp_table_size = 64MB
max_heap_table_size = 64MB
```

### Migration Optimization

```bash
# Use larger batch sizes for better performance
export MIGRATION_BATCH_SIZE=5000

# Enable parallel processing
export MIGRATION_PARALLEL_WORKERS=4

# Disable strict mode for faster processing
export MIGRATION_STRICT_MODE=false

# Use PgBouncer for connection pooling
export POSTGRESQL_HOST=pgbouncer
export POSTGRESQL_PORT=6432
```

## Backup and Recovery

### Pre-Migration Backup

```bash
# Create comprehensive backup
./migration/scripts/benchmark-mysql.sh --backup-only

# Verify backup integrity
mysql -u root -p < migration/backups/gateway_service_pre_migration_*.sql
```

### Recovery Procedures

```bash
# Emergency rollback
php migration/scripts/rollback-migration.php gateway-service emergency

# Full data rollback
php migration/scripts/rollback-migration.php gateway-service full

# Configuration rollback only
php migration/scripts/rollback-migration.php gateway-service configuration
```

## Production Deployment

### Pre-Deployment Checklist

- [ ] All integration tests pass
- [ ] Infrastructure validation successful
- [ ] Performance baselines established
- [ ] Backup procedures tested
- [ ] Rollback procedures validated
- [ ] Team training completed
- [ ] Maintenance window scheduled
- [ ] Stakeholders notified

### Deployment Steps

1. **Final Validation**
   ```bash
   php migration/scripts/integration-tests.php
   php migration/scripts/validate-infrastructure.php
   ```

2. **Baseline Establishment**
   ```bash
   php migration/scripts/generate-baseline-report.php
   ```

3. **Pilot Migration**
   ```bash
   php migration/scripts/pilot-migration.php
   ```

4. **Production Migration**
   ```bash
   php migration/scripts/migration-orchestrator.php full-migration
   ```

### Post-Deployment

- Monitor system performance for 24-48 hours
- Validate all application functionality
- Update monitoring dashboards
- Document lessons learned
- Plan subsequent service migrations

---

## Support and Maintenance

### Regular Maintenance Tasks

```bash
# Weekly: Check migration logs
find migration/logs/ -name "*.log" -mtime +7 -delete

# Monthly: Validate backup integrity
php migration/scripts/rollback-migration.php --test-backup

# Quarterly: Review and optimize performance
php migration/scripts/generate-baseline-report.php
```

### Getting Help

- **Documentation**: Refer to `migration/README.md` and phase-specific guides
- **Logs**: Check `migration/logs/` for detailed execution logs
- **Reports**: Review `migration/reports/` for validation and performance reports
- **Troubleshooting**: Consult the troubleshooting section in this guide

---

*This setup guide ensures a successful PostgreSQL migration framework deployment with enterprise-grade reliability and performance.*
