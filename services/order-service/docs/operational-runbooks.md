# Operational Runbooks - Laravel Workflow Saga Pattern

## 📋 Table of Contents

1. [System Health Monitoring](#system-health-monitoring)
2. [Troubleshooting Procedures](#troubleshooting-procedures)
3. [Performance Issues](#performance-issues)
4. [Dead Letter Queue Management](#dead-letter-queue-management)
5. [Alert Response Procedures](#alert-response-procedures)
6. [Maintenance Tasks](#maintenance-tasks)
7. [Disaster Recovery](#disaster-recovery)
8. [Scaling Procedures](#scaling-procedures)
9. [Security Incident Response](#security-incident-response)
10. [Common Issues & Solutions](#common-issues--solutions)

---

## 🏥 System Health Monitoring

### **Daily Health Checks**

Perform these checks every morning:

```bash
# 1. Check application status
curl -f http://your-domain.com/api/health || echo "❌ Application DOWN"

# 2. Check queue workers
sudo supervisorctl status | grep workflow
# Expected: workflow-worker:* RUNNING
# Expected: dlq-worker:* RUNNING

# 3. Check Redis connectivity
redis-cli ping
# Expected: PONG

# 4. Check database connectivity
php artisan tinker --execute="DB::connection()->getPdo(); echo 'DB OK';"

# 5. Check disk space
df -h | grep -E "(/$|/var)"
# Alert if > 80% usage

# 6. Check memory usage
free -h
# Alert if available memory < 1GB

# 7. Check workflow metrics
curl -s http://your-domain.com/api/workflow/metrics/overview | jq '.data.health_status'
# Expected: "healthy"
```

### **Automated Health Monitoring Script**

Create `/opt/scripts/workflow-health-check.sh`:

```bash
#!/bin/bash

LOG_FILE="/var/log/workflow-health.log"
ALERT_EMAIL="ops@yourcompany.com"
SLACK_WEBHOOK="https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK"

log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a $LOG_FILE
}

send_alert() {
    local message="$1"
    local severity="$2"
    
    # Log the alert
    log_message "ALERT [$severity]: $message"
    
    # Send Slack notification
    curl -X POST -H 'Content-type: application/json' \
        --data "{\"text\":\"🚨 Workflow Alert [$severity]: $message\"}" \
        $SLACK_WEBHOOK
    
    # Send email for critical alerts
    if [ "$severity" = "CRITICAL" ]; then
        echo "$message" | mail -s "CRITICAL: Workflow System Alert" $ALERT_EMAIL
    fi
}

check_application() {
    if ! curl -f -s http://localhost/api/health > /dev/null; then
        send_alert "Application health check failed" "CRITICAL"
        return 1
    fi
    return 0
}

check_workers() {
    local failed_workers=$(sudo supervisorctl status | grep workflow | grep -v RUNNING | wc -l)
    if [ $failed_workers -gt 0 ]; then
        send_alert "$failed_workers workflow workers are not running" "CRITICAL"
        return 1
    fi
    return 0
}

check_redis() {
    if ! redis-cli ping > /dev/null 2>&1; then
        send_alert "Redis is not responding" "CRITICAL"
        return 1
    fi
    return 0
}

check_database() {
    if ! php /var/www/artisan tinker --execute="DB::connection()->getPdo();" > /dev/null 2>&1; then
        send_alert "Database connection failed" "CRITICAL"
        return 1
    fi
    return 0
}

check_disk_space() {
    local usage=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
    if [ $usage -gt 85 ]; then
        send_alert "Disk usage is at ${usage}%" "WARNING"
    fi
    if [ $usage -gt 95 ]; then
        send_alert "Disk usage is critically high at ${usage}%" "CRITICAL"
        return 1
    fi
    return 0
}

check_memory() {
    local available=$(free | awk 'NR==2{printf "%.0f", $7/1024/1024}')
    if [ $available -lt 1 ]; then
        send_alert "Available memory is low: ${available}GB" "WARNING"
    fi
    if [ $available -lt 0.5 ]; then
        send_alert "Available memory is critically low: ${available}GB" "CRITICAL"
        return 1
    fi
    return 0
}

# Run all checks
log_message "Starting health checks"

check_application
check_workers
check_redis
check_database
check_disk_space
check_memory

log_message "Health checks completed"
```

### **Cron Job Setup**

Add to crontab:

```bash
# Run health checks every 5 minutes
*/5 * * * * /opt/scripts/workflow-health-check.sh

# Daily comprehensive report
0 8 * * * /opt/scripts/workflow-daily-report.sh
```

---

## 🔧 Troubleshooting Procedures

### **Application Not Responding**

**Symptoms**: HTTP 500 errors, timeouts, or no response

**Investigation Steps**:

```bash
# 1. Check application logs
tail -f /var/www/storage/logs/laravel.log

# 2. Check web server logs
tail -f /var/log/nginx/error.log

# 3. Check PHP-FPM status
sudo systemctl status php8.1-fpm

# 4. Check system resources
top
htop
iotop

# 5. Check database connections
mysql -u workflow_user -p -e "SHOW PROCESSLIST;"
```

**Resolution Steps**:

```bash
# 1. Restart PHP-FPM
sudo systemctl restart php8.1-fpm

# 2. Restart web server
sudo systemctl restart nginx

# 3. Clear application caches
cd /var/www
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 4. Restart queue workers
sudo supervisorctl restart workflow-worker:*

# 5. If still failing, restart the entire application
sudo supervisorctl stop all
sudo systemctl restart nginx php8.1-fpm redis mysql
sudo supervisorctl start all
```

### **Queue Workers Failing**

**Symptoms**: Jobs not processing, increasing queue depth

**Investigation Steps**:

```bash
# 1. Check worker status
sudo supervisorctl status | grep workflow

# 2. Check worker logs
tail -f /var/www/storage/logs/worker.log

# 3. Check failed jobs
php artisan queue:failed

# 4. Check queue depth
redis-cli llen "queues:workflow"
redis-cli llen "queues:dlq"

# 5. Check for memory leaks
ps aux | grep "queue:work" | awk '{print $6}' | sort -n
```

**Resolution Steps**:

```bash
# 1. Restart workers
sudo supervisorctl restart workflow-worker:*
sudo supervisorctl restart dlq-worker:*

# 2. Clear stuck jobs (if safe)
redis-cli del "queues:workflow"

# 3. Retry failed jobs
php artisan queue:retry all

# 4. Increase worker memory limit
# Edit supervisor config: command=php artisan queue:work --memory=512

# 5. Scale workers if needed
sudo supervisorctl stop workflow-worker:*
# Edit /etc/supervisor/conf.d/workflow-workers.conf
# Increase numprocs=8
sudo supervisorctl reread
sudo supervisorctl update
```

### **Database Performance Issues**

**Symptoms**: Slow queries, connection timeouts

**Investigation Steps**:

```bash
# 1. Check slow query log
mysql -u root -p -e "SHOW VARIABLES LIKE 'slow_query_log';"
tail -f /var/log/mysql/mysql-slow.log

# 2. Check active connections
mysql -u root -p -e "SHOW PROCESSLIST;"

# 3. Check table locks
mysql -u root -p -e "SHOW OPEN TABLES WHERE In_use > 0;"

# 4. Check database size
mysql -u root -p -e "
SELECT 
    table_schema AS 'Database',
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
FROM information_schema.tables 
GROUP BY table_schema;"
```

**Resolution Steps**:

```bash
# 1. Optimize tables
mysql -u root -p workflow_saga -e "OPTIMIZE TABLE orders, workflow_events, workflow_traces;"

# 2. Update table statistics
mysql -u root -p workflow_saga -e "ANALYZE TABLE orders, workflow_events, workflow_traces;"

# 3. Kill long-running queries (if safe)
mysql -u root -p -e "KILL <process_id>;"

# 4. Restart MySQL (last resort)
sudo systemctl restart mysql
```

---

## ⚡ Performance Issues

### **High Response Times**

**Investigation Checklist**:

```bash
# 1. Check application performance metrics
curl -s http://localhost/api/workflow/metrics/performance | jq '.data.avg_response_time_ms'

# 2. Check database query performance
mysql -u root -p -e "
SELECT 
    query_time, 
    lock_time, 
    rows_sent, 
    rows_examined, 
    sql_text 
FROM mysql.slow_log 
ORDER BY query_time DESC 
LIMIT 10;"

# 3. Check Redis performance
redis-cli --latency-history

# 4. Check system load
uptime
iostat 1 5

# 5. Check network latency
ping -c 5 database-server
ping -c 5 redis-server
```

**Optimization Steps**:

```bash
# 1. Enable OPcache
sudo nano /etc/php/8.1/fpm/php.ini
# opcache.enable=1
# opcache.memory_consumption=256

# 2. Optimize database queries
php artisan telescope:clear
# Monitor slow queries in Telescope

# 3. Implement caching
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Scale Redis if needed
# Add Redis cluster or increase memory

# 5. Scale application servers
# Add more PHP-FPM workers or application instances
```

### **Memory Leaks**

**Detection**:

```bash
# Monitor memory usage over time
while true; do
    ps aux | grep "queue:work" | awk '{sum+=$6} END {print "Total Memory: " sum/1024 " MB"}'
    sleep 60
done

# Check for memory leaks in specific processes
sudo pmap -x $(pgrep -f "queue:work")
```

**Resolution**:

```bash
# 1. Restart workers regularly
# Add to supervisor config: autorestart=true, stopwaitsecs=3600

# 2. Limit worker memory
# command=php artisan queue:work --memory=256

# 3. Limit job processing time
# command=php artisan queue:work --timeout=300

# 4. Monitor and restart workers automatically
echo "0 */2 * * * sudo supervisorctl restart workflow-worker:*" | crontab -
```

---

## 💀 Dead Letter Queue Management

### **DLQ Monitoring**

**Daily DLQ Check**:

```bash
# Check DLQ statistics
curl -s http://localhost/api/workflow/dlq/statistics | jq '.data'

# Check manual interventions
curl -s http://localhost/api/workflow/dlq/manual-interventions | jq '.data | length'

# Check failed job count
php artisan queue:failed | wc -l
```

### **DLQ Processing Procedures**

**Manual Intervention Required**:

```bash
# 1. Get pending interventions
curl -s http://localhost/api/workflow/dlq/manual-interventions | jq '.data[]'

# 2. Investigate specific failure
curl -s http://localhost/api/workflow/dlq/manual-interventions | jq '.data[] | select(.id=="FAILURE_ID")'

# 3. Resolve intervention (after fixing root cause)
curl -X POST http://localhost/api/workflow/dlq/resolve/FAILURE_ID \
  -H "Content-Type: application/json" \
  -d '{"resolution":"Fixed database connection issue","resolved_by":"ops-team"}'

# 4. Retry failed activity (if appropriate)
curl -X POST http://localhost/api/workflow/dlq/retry/FAILURE_ID \
  -H "Content-Type: application/json" \
  -d '{"retry_reason":"Infrastructure issue resolved"}'
```

**Bulk DLQ Processing**:

```bash
# Process retry queue
curl -X POST http://localhost/api/workflow/dlq/process-retry-queue

# Retry all failed jobs (use with caution)
php artisan queue:retry all

# Clear old failed jobs
php artisan queue:flush
```

---

## 🚨 Alert Response Procedures

### **Critical Alerts**

**High Error Rate Alert**:

1. **Immediate Response** (within 5 minutes):
   ```bash
   # Check error rate
   curl -s http://localhost/api/workflow/metrics/overview | jq '.data.error_rate'
   
   # Check recent errors
   tail -n 100 /var/www/storage/logs/laravel.log | grep ERROR
   ```

2. **Investigation** (within 15 minutes):
   ```bash
   # Check failed jobs
   php artisan queue:failed
   
   # Check database connectivity
   php artisan tinker --execute="DB::connection()->getPdo();"
   
   # Check external service status
   curl -f https://external-api.com/health
   ```

3. **Resolution** (within 30 minutes):
   - Fix identified root cause
   - Restart affected services
   - Monitor error rate recovery

**Queue Depth Alert**:

1. **Immediate Response**:
   ```bash
   # Check queue depths
   redis-cli llen "queues:workflow"
   redis-cli llen "queues:dlq"
   
   # Check worker status
   sudo supervisorctl status | grep workflow
   ```

2. **Scale Workers**:
   ```bash
   # Temporarily increase workers
   sudo supervisorctl stop workflow-worker:*
   # Edit supervisor config to increase numprocs
   sudo supervisorctl reread && sudo supervisorctl update
   ```

### **Warning Alerts**

**High Response Time Alert**:

```bash
# 1. Check current performance
curl -s http://localhost/api/workflow/metrics/performance

# 2. Check system resources
top
iostat

# 3. Check slow queries
mysql -u root -p -e "SHOW PROCESSLIST;"

# 4. Optimize if needed
php artisan cache:clear
mysql -u root -p workflow_saga -e "OPTIMIZE TABLE orders;"
```

---

## 🔧 Maintenance Tasks

### **Daily Maintenance**

```bash
#!/bin/bash
# /opt/scripts/daily-maintenance.sh

# 1. Clean old logs
find /var/www/storage/logs -name "*.log" -mtime +7 -delete

# 2. Clean old failed jobs
php artisan queue:prune-failed --hours=168  # 7 days

# 3. Clean old workflow events
mysql -u workflow_user -p workflow_saga -e "
DELETE FROM workflow_events 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);"

# 4. Clean old correlation data
redis-cli --scan --pattern "correlation:*" | xargs -L 1000 redis-cli del

# 5. Optimize database tables
mysql -u workflow_user -p workflow_saga -e "
OPTIMIZE TABLE orders, workflow_events, workflow_traces, failed_jobs;"

# 6. Update application caches
cd /var/www
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Backup critical data
mysqldump -u backup_user -p workflow_saga > /backups/workflow_$(date +%Y%m%d).sql
```

### **Weekly Maintenance**

```bash
#!/bin/bash
# /opt/scripts/weekly-maintenance.sh

# 1. Update system packages
sudo apt update && sudo apt upgrade -y

# 2. Restart all services
sudo systemctl restart nginx php8.1-fpm mysql redis
sudo supervisorctl restart all

# 3. Check disk usage and clean if needed
if [ $(df / | awk 'NR==2 {print $5}' | sed 's/%//') -gt 80 ]; then
    # Clean old backups
    find /backups -name "*.sql" -mtime +30 -delete
    
    # Clean old logs
    journalctl --vacuum-time=7d
fi

# 4. Performance analysis
php artisan telescope:clear
# Generate performance report
curl -s http://localhost/api/workflow/metrics/overview > /reports/weekly_$(date +%Y%m%d).json
```

### **Monthly Maintenance**

```bash
#!/bin/bash
# /opt/scripts/monthly-maintenance.sh

# 1. Full database optimization
mysql -u root -p workflow_saga -e "
ANALYZE TABLE orders, workflow_events, workflow_traces;
OPTIMIZE TABLE orders, workflow_events, workflow_traces;
CHECK TABLE orders, workflow_events, workflow_traces;"

# 2. Security updates
sudo apt update && sudo apt upgrade -y
sudo apt autoremove -y

# 3. Certificate renewal (if using Let's Encrypt)
sudo certbot renew

# 4. Backup verification
# Test restore from latest backup
mysqldump -u backup_user -p workflow_saga > /tmp/test_backup.sql
mysql -u root -p -e "CREATE DATABASE test_restore;"
mysql -u root -p test_restore < /tmp/test_backup.sql
mysql -u root -p -e "DROP DATABASE test_restore;"
rm /tmp/test_backup.sql

# 5. Performance baseline update
# Store monthly performance metrics for trending
```

---

## 🚑 Disaster Recovery

### **Database Recovery**

**Complete Database Loss**:

```bash
# 1. Stop application
sudo supervisorctl stop all
sudo systemctl stop nginx

# 2. Restore from backup
mysql -u root -p -e "CREATE DATABASE workflow_saga;"
mysql -u root -p workflow_saga < /backups/latest_backup.sql

# 3. Verify restoration
mysql -u root -p workflow_saga -e "SELECT COUNT(*) FROM orders;"

# 4. Start application
sudo systemctl start nginx
sudo supervisorctl start all

# 5. Verify application functionality
curl -f http://localhost/api/health
```

**Partial Data Corruption**:

```bash
# 1. Identify corrupted tables
mysql -u root -p workflow_saga -e "CHECK TABLE orders, workflow_events, workflow_traces;"

# 2. Repair if possible
mysql -u root -p workflow_saga -e "REPAIR TABLE corrupted_table;"

# 3. Restore specific tables if needed
mysql -u root -p workflow_saga -e "DROP TABLE corrupted_table;"
mysql -u root -p workflow_saga < /backups/table_specific_backup.sql
```

### **Application Server Recovery**

**Complete Server Loss**:

1. **Provision new server**
2. **Deploy application**:
   ```bash
   # Clone repository
   git clone https://github.com/your-org/workflow-saga.git
   cd workflow-saga
   
   # Install dependencies
   composer install --no-dev --optimize-autoloader
   
   # Configure environment
   cp .env.production .env
   php artisan key:generate
   
   # Run migrations
   php artisan migrate --force
   
   # Optimize for production
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

3. **Configure services**:
   ```bash
   # Setup supervisor
   sudo cp deployment/supervisor/* /etc/supervisor/conf.d/
   sudo supervisorctl reread && sudo supervisorctl update
   
   # Setup nginx
   sudo cp deployment/nginx/workflow-saga.conf /etc/nginx/sites-available/
   sudo ln -s /etc/nginx/sites-available/workflow-saga.conf /etc/nginx/sites-enabled/
   sudo systemctl restart nginx
   ```

---

## 📈 Scaling Procedures

### **Horizontal Scaling**

**Adding Application Servers**:

```bash
# 1. Provision new server
# 2. Deploy application (same as disaster recovery)
# 3. Configure load balancer
# 4. Add to monitoring

# Load balancer configuration (nginx upstream)
upstream workflow_app {
    server 10.0.1.10:80;
    server 10.0.1.11:80;  # New server
    server 10.0.1.12:80;  # New server
}
```

**Scaling Queue Workers**:

```bash
# 1. Increase workers on existing servers
sudo supervisorctl stop workflow-worker:*
# Edit /etc/supervisor/conf.d/workflow-workers.conf
# numprocs=8  # Increase from 4
sudo supervisorctl reread && sudo supervisorctl update

# 2. Add dedicated worker servers
# Deploy application without web server
# Configure only queue workers
```

### **Vertical Scaling**

**Increasing Server Resources**:

```bash
# 1. Schedule maintenance window
# 2. Stop application gracefully
sudo supervisorctl stop all

# 3. Resize server (cloud provider specific)
# 4. Update configuration for new resources

# PHP-FPM optimization for more memory
sudo nano /etc/php/8.1/fpm/pool.d/www.conf
# pm.max_children = 50  # Increase based on memory
# pm.start_servers = 10
# pm.min_spare_servers = 5
# pm.max_spare_servers = 15

# 5. Restart services
sudo systemctl restart php8.1-fpm nginx
sudo supervisorctl start all
```

---

## 🔒 Security Incident Response

### **Suspected Breach**

**Immediate Response** (within 15 minutes):

```bash
# 1. Isolate affected systems
sudo iptables -A INPUT -j DROP  # Block all incoming traffic
sudo iptables -A OUTPUT -j DROP  # Block all outgoing traffic
sudo iptables -I INPUT -p tcp --dport 22 -j ACCEPT  # Keep SSH access

# 2. Preserve evidence
sudo dd if=/dev/sda of=/forensics/disk_image.dd bs=4096
sudo netstat -tulpn > /forensics/network_connections.txt
sudo ps aux > /forensics/running_processes.txt

# 3. Check for unauthorized access
sudo last | head -20
sudo grep "Failed password" /var/log/auth.log | tail -20
sudo find /var/www -name "*.php" -mtime -1  # Recently modified files
```

**Investigation** (within 1 hour):

```bash
# 1. Check application logs for suspicious activity
grep -i "unauthorized\|hack\|inject\|exploit" /var/www/storage/logs/laravel.log

# 2. Check database for unauthorized changes
mysql -u root -p workflow_saga -e "
SELECT * FROM orders WHERE updated_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
ORDER BY updated_at DESC;"

# 3. Check for malicious files
sudo find /var/www -name "*.php" -exec grep -l "eval\|base64_decode\|shell_exec" {} \;

# 4. Check system integrity
sudo aide --check  # If AIDE is configured
```

**Recovery** (within 4 hours):

```bash
# 1. Remove malicious files
sudo rm /path/to/malicious/file.php

# 2. Restore from clean backup if needed
# 3. Update all passwords
# 4. Apply security patches
# 5. Restore normal operations with enhanced monitoring
```

---

## 🔍 Common Issues & Solutions

### **Issue: Queue Jobs Stuck in Processing**

**Symptoms**: Jobs show as processing but never complete

**Solution**:
```bash
# 1. Find stuck jobs
redis-cli hgetall "queues:workflow:reserved"

# 2. Clear reserved jobs (if safe)
redis-cli del "queues:workflow:reserved"

# 3. Restart workers
sudo supervisorctl restart workflow-worker:*

# 4. Retry failed jobs
php artisan queue:retry all
```

### **Issue: High Memory Usage**

**Symptoms**: Server running out of memory, OOM killer active

**Solution**:
```bash
# 1. Identify memory hogs
ps aux --sort=-%mem | head -10

# 2. Restart PHP-FPM
sudo systemctl restart php8.1-fpm

# 3. Optimize PHP-FPM configuration
sudo nano /etc/php/8.1/fpm/pool.d/www.conf
# Reduce pm.max_children based on available memory

# 4. Clear application caches
php artisan cache:clear
php artisan view:clear
```

### **Issue: Database Connection Errors**

**Symptoms**: "Too many connections" or connection timeouts

**Solution**:
```bash
# 1. Check current connections
mysql -u root -p -e "SHOW STATUS LIKE 'Threads_connected';"
mysql -u root -p -e "SHOW VARIABLES LIKE 'max_connections';"

# 2. Kill idle connections
mysql -u root -p -e "
SELECT CONCAT('KILL ', id, ';') 
FROM INFORMATION_SCHEMA.PROCESSLIST 
WHERE Command = 'Sleep' AND Time > 300;"

# 3. Increase max_connections (if needed)
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
# max_connections = 200
sudo systemctl restart mysql

# 4. Optimize application connection pooling
# Update database configuration in Laravel
```

This operational runbook provides comprehensive procedures for maintaining, troubleshooting, and recovering the Laravel Workflow Saga Pattern system in production environments.
