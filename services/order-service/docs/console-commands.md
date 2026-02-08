# Console Commands - Laravel Workflow Saga Pattern

## 📋 Overview

This document provides comprehensive documentation for all console commands implemented in the Laravel Workflow Saga Pattern. These commands enable operational management, monitoring, and maintenance of the workflow system.

---

## 🖥️ Available Commands

### 1. [ProcessDlqRetryQueue](#1-processdlqretryqueue) - DLQ batch processing
### 2. [MonitorWorkflowAlerts](#2-monitorworkflowalerts) - Alert monitoring
### 3. [WorkflowMetrics](#3-workflowmetrics) - Metrics management

---

## 1. ProcessDlqRetryQueue

### **Command:** `workflow:process-dlq-retry-queue`

**Purpose:** Process the dead letter queue retry queue in batches with comprehensive statistics and progress tracking.

### **Signature:**
```bash
php artisan workflow:process-dlq-retry-queue 
    [--batch-size=10] 
    [--max-retries=5] 
    [--delay=30]
```

### **Options:**

| Option | Default | Description |
|--------|---------|-------------|
| `--batch-size` | 10 | Number of items to process in each batch |
| `--max-retries` | 5 | Maximum number of retry attempts per item |
| `--delay` | 30 | Delay in seconds between batches |

### **Usage Examples:**

#### Basic Usage
```bash
# Process DLQ with default settings
php artisan workflow:process-dlq-retry-queue
```

#### Custom Batch Size
```bash
# Process 20 items per batch
php artisan workflow:process-dlq-retry-queue --batch-size=20
```

#### High-Volume Processing
```bash
# Large batch with extended retries
php artisan workflow:process-dlq-retry-queue --batch-size=50 --max-retries=10 --delay=60
```

#### Quick Processing
```bash
# Small batches with minimal delay
php artisan workflow:process-dlq-retry-queue --batch-size=5 --delay=10
```

### **Output Example:**
```
Starting DLQ retry queue processing...
Batch size: 10, Max retries: 5, Delay: 30s

Initial DLQ Statistics:
┌─────────────────────┬───────┐
│ Metric              │ Count │
├─────────────────────┼───────┤
│ Pending Retries     │ 15    │
│ Manual Interventions│ 3     │
│ Daily Failures      │ 8     │
│ Resolution Rate     │ 94%   │
└─────────────────────┴───────┘

By Activity Type:
┌─────────────┬───────┐
│ Activity Type│ Count │
├─────────────┼───────┤
│ Payment     │ 8     │
│ Inventory   │ 4     │
│ Shipping    │ 3     │
└─────────────┴───────┘

Processing 10 items from retry queue...
✅ Retried: dlq-failure-123
🚀 Dispatched job: dlq-failure-456
✅ Retried: dlq-failure-789
❌ Failed: dlq-failure-101 - Max retries exceeded

Processing Summary:
┌─────────────────────┬───────┐
│ Metric              │ Count │
├─────────────────────┼───────┤
│ Immediate Successes │ 6     │
│ Jobs Dispatched     │ 3     │
│ Failures            │ 1     │
│ Total Processed     │ 10    │
└─────────────────────┴───────┘

Waiting 30 seconds before final statistics...

Final DLQ Statistics:
┌─────────────────────┬───────┐
│ Metric              │ Count │
├─────────────────────┼───────┤
│ Pending Retries     │ 8     │
│ Manual Interventions│ 4     │
│ Daily Failures      │ 8     │
│ Resolution Rate     │ 96%   │
└─────────────────────┴───────┘

DLQ retry queue processing completed successfully!
```

### **Features:**
- **Before/After Statistics**: Displays DLQ metrics before and after processing
- **Real-time Progress**: Shows success/failure status for each item
- **Job Dispatching**: Automatically dispatches ProcessDlqRetry jobs for async processing
- **Activity Type Breakdown**: Shows failures by activity type (payment, inventory, shipping)
- **Comprehensive Logging**: Detailed logs for audit and debugging
- **Error Handling**: Graceful handling of processing failures

### **Scheduling:**
```php
// In app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Process DLQ every 5 minutes
    $schedule->command('workflow:process-dlq-retry-queue --batch-size=20')
             ->everyFiveMinutes()
             ->withoutOverlapping();
             
    // Large batch processing during off-peak hours
    $schedule->command('workflow:process-dlq-retry-queue --batch-size=100 --delay=120')
             ->dailyAt('02:00')
             ->withoutOverlapping();
}
```

---

## 2. MonitorWorkflowAlerts

### **Command:** `workflow:monitor-alerts`

**Purpose:** Continuously monitor workflow metrics and trigger alerts when thresholds are exceeded.

### **Signature:**
```bash
php artisan workflow:monitor-alerts 
    [--check-interval=60] 
    [--max-iterations=0] 
    [--verbose]
```

### **Options:**

| Option | Default | Description |
|--------|---------|-------------|
| `--check-interval` | 60 | Interval in seconds between alert checks |
| `--max-iterations` | 0 | Maximum iterations (0 for infinite) |
| `--verbose` | false | Show detailed output and statistics |

### **Usage Examples:**

#### Continuous Monitoring
```bash
# Run continuous monitoring with default settings
php artisan workflow:monitor-alerts
```

#### Verbose Monitoring
```bash
# Detailed output with statistics
php artisan workflow:monitor-alerts --verbose
```

#### Frequent Checks
```bash
# Check every 30 seconds for 100 iterations
php artisan workflow:monitor-alerts --check-interval=30 --max-iterations=100
```

#### Development Mode
```bash
# Quick checks for testing
php artisan workflow:monitor-alerts --check-interval=10 --max-iterations=5 --verbose
```

### **Output Example:**
```
Starting workflow alert monitoring...
Check interval: 60 seconds
Max iterations: infinite

Iteration 1 - Checking alerts at 2026-02-08 05:30:00
✅ No alerts triggered
Check completed in 45.23ms

📊 Today's Alert Statistics:
┌──────────┬───────┐
│ Severity │ Count │
├──────────┼───────┤
│ Total    │ 5     │
│ Critical │ 1     │
│ Warning  │ 3     │
│ Info     │ 1     │
└──────────┴───────┘

By Type:
┌─────────────────┬───────┐
│ Type            │ Count │
├─────────────────┼───────┤
│ Dlq Threshold   │ 2     │
│ Failure Rate    │ 1     │
│ Response Time   │ 2     │
└─────────────────┴───────┘

Iteration 2 - Checking alerts at 2026-02-08 05:31:00
🚨 2 alert(s) triggered:
  🟡 [warning] dlq_threshold: DLQ pending count (12) exceeds threshold (10)
  🔴 [critical] manual_intervention_critical: Critical manual intervention required for workflow order-saga-123
Check completed in 67.45ms
```

### **Features:**
- **Continuous Monitoring**: Runs indefinitely or for specified iterations
- **Real-time Alerts**: Immediate notification when thresholds are exceeded
- **Alert Statistics**: Displays daily alert counts by severity and type
- **Performance Tracking**: Shows check duration and iteration timing
- **Configurable Intervals**: Adjustable check frequency for different environments
- **Graceful Error Handling**: Continues monitoring even if individual checks fail

### **Alert Types Monitored:**
- **DLQ Threshold**: Pending retry count exceeds threshold
- **Failure Rate**: Workflow failure rate exceeds percentage threshold
- **Response Time**: Average response time exceeds time threshold
- **Queue Depth**: Queue size exceeds capacity threshold
- **Resource Usage**: CPU/memory usage exceeds utilization threshold

### **Scheduling:**
```php
// In app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Continuous monitoring (runs as daemon)
    $schedule->command('workflow:monitor-alerts --check-interval=60')
             ->runInBackground()
             ->withoutOverlapping();
             
    // Frequent checks during business hours
    $schedule->command('workflow:monitor-alerts --check-interval=30 --max-iterations=120')
             ->hourlyAt(0)
             ->between('9:00', '17:00')
             ->weekdays();
}
```

---

## 3. WorkflowMetrics

### **Command:** `workflow:metrics`

**Purpose:** Generate, aggregate, and manage workflow metrics for dashboard and reporting purposes.

### **Signature:**
```bash
php artisan workflow:metrics 
    [--action=generate] 
    [--timeframe=24h] 
    [--force]
```

### **Options:**

| Option | Default | Description |
|--------|---------|-------------|
| `--action` | generate | Action to perform: generate, aggregate, cleanup |
| `--timeframe` | 24h | Timeframe for metrics: 1h, 24h, 7d, 30d |
| `--force` | false | Force regeneration of existing metrics |

### **Usage Examples:**

#### Generate Current Metrics
```bash
# Generate metrics for last 24 hours
php artisan workflow:metrics --action=generate
```

#### Aggregate Historical Data
```bash
# Aggregate metrics for last 7 days
php artisan workflow:metrics --action=aggregate --timeframe=7d
```

#### Cleanup Old Metrics
```bash
# Clean up metrics older than 30 days
php artisan workflow:metrics --action=cleanup --timeframe=30d
```

#### Force Regeneration
```bash
# Force regenerate all metrics
php artisan workflow:metrics --action=generate --force
```

### **Output Example:**
```
Workflow Metrics Management
Action: generate
Timeframe: 24h

Generating workflow metrics...
✅ Workflow overview metrics generated
✅ Activity performance metrics calculated
✅ Correlation metrics aggregated
✅ DLQ statistics updated
✅ Alert metrics compiled

Metrics Summary:
┌─────────────────────┬─────────┐
│ Metric Category     │ Records │
├─────────────────────┼─────────┤
│ Workflow Overview   │ 1,250   │
│ Activity Performance│ 3,750   │
│ Correlation Data    │ 2,100   │
│ DLQ Statistics      │ 45      │
│ Alert Records       │ 15      │
└─────────────────────┴─────────┘

Cache Keys Updated:
- workflow.metrics.overview
- workflow.metrics.activities.*
- correlation.metrics.24h.*
- dlq.metrics.*
- alerts.daily.*

Metrics generation completed in 2.34 seconds.
```

### **Features:**
- **Multi-Action Support**: Generate, aggregate, or cleanup metrics
- **Flexible Timeframes**: Support for various time periods
- **Cache Management**: Efficient cache key management and updates
- **Performance Tracking**: Execution time and record count reporting
- **Force Regeneration**: Override existing metrics when needed

---

## 🔧 Command Integration

### **Supervisor Configuration**

For production environments, use Supervisor to manage long-running commands:

```ini
[program:workflow-alert-monitor]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan workflow:monitor-alerts --check-interval=60
directory=/path/to/project
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/workflow-alerts.log
```

### **Cron Configuration**

For scheduled execution:

```bash
# Process DLQ every 5 minutes
*/5 * * * * cd /path/to/project && php artisan workflow:process-dlq-retry-queue --batch-size=20

# Generate metrics every hour
0 * * * * cd /path/to/project && php artisan workflow:metrics --action=generate

# Cleanup old metrics daily at 2 AM
0 2 * * * cd /path/to/project && php artisan workflow:metrics --action=cleanup --timeframe=30d
```

---

## 🚨 Error Handling

### **Common Exit Codes:**
- `0`: Success
- `1`: General error
- `2`: Invalid arguments
- `3`: Service unavailable

### **Error Examples:**

#### DLQ Processing Error
```
Error during DLQ processing: Service temporarily unavailable
Retrying in 30 seconds...
```

#### Alert Monitoring Error
```
Error during alert monitoring: Failed to connect to cache
Iteration 5 - Retrying in 60 seconds...
```

#### Metrics Generation Error
```
Failed to generate workflow metrics: Database connection timeout
Please check database connectivity and try again.
```

---

## 📊 Logging

All commands provide comprehensive logging:

### **Log Levels:**
- **DEBUG**: Detailed execution information
- **INFO**: General operational messages
- **WARNING**: Non-critical issues
- **ERROR**: Error conditions
- **CRITICAL**: Critical failures

### **Log Examples:**

```php
// DLQ Processing
Log::info('DLQ retry queue processing completed', [
    'batch_size' => 10,
    'total_processed' => 8,
    'immediate_successes' => 6,
    'jobs_dispatched' => 2,
    'failures' => 0,
]);

// Alert Monitoring
Log::debug('Workflow alert monitoring check completed', [
    'iteration' => 15,
    'triggered_alerts' => 2,
    'duration_ms' => 45.23,
]);

// Metrics Generation
Log::info('Workflow metrics generated', [
    'timeframe' => '24h',
    'records_processed' => 1250,
    'cache_keys_updated' => 15,
    'duration_seconds' => 2.34,
]);
```

---

## 🔗 Related Documentation

- [API Documentation](api-documentation.md)
- [Service Architecture](service-architecture.md)
- [Monitoring & Alerting](monitoring-alerting.md)
- [Operational Runbooks](operational-runbooks.md)

---

## 💡 Best Practices

### **Production Usage:**
1. **Use Supervisor** for long-running monitoring commands
2. **Schedule regular DLQ processing** during off-peak hours
3. **Monitor command logs** for errors and performance issues
4. **Set appropriate batch sizes** based on system capacity
5. **Use verbose mode** only for debugging

### **Development Usage:**
1. **Use smaller batch sizes** for faster feedback
2. **Enable verbose output** for detailed information
3. **Set shorter check intervals** for rapid testing
4. **Use max-iterations** to limit execution time

### **Monitoring:**
1. **Track command execution time** for performance optimization
2. **Monitor error rates** and set up alerts for failures
3. **Review logs regularly** for operational insights
4. **Adjust parameters** based on system load and performance
