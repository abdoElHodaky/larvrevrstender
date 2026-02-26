# Database Failover System - Installation Guide

## 🚀 **QUICK START - MAKING THE SYSTEM FUNCTIONAL**

This guide will take you from the current 65% implementation to a fully functional database failover system.

---

## 📋 **PREREQUISITES**

- Laravel 12+ microservices architecture
- PostgreSQL (Neon + Cloud Provider)
- MongoDB Atlas (optional fallback)
- Composer package manager
- Redis for caching (recommended)

---

## 🔧 **STEP 1: PACKAGE INSTALLATION**

### **Install Required Packages in Auth-Service (Pilot)**

```bash
cd services/auth-service

# Install database management packages
composer require envor/laravel-managed-databases:^1.0
composer require usmonaliyev/laravel-db-connection-resolver:^1.0

# Update autoloader
composer dump-autoload
```

### **Verify Package Installation**

```bash
# Check if packages are installed
composer show | grep -E "(envor|usmonaliyev)"

# Should show:
# envor/laravel-managed-databases
# usmonaliyev/laravel-db-connection-resolver
```

---

## ⚙️ **STEP 2: ENVIRONMENT CONFIGURATION**

### **Update .env File**

Copy the failover configuration to your `.env` file:

```bash
cd services/auth-service
cp .env.failover-example .env.failover
```

Add these variables to your `.env` file:

```env
# Database Connection (Primary)
DB_CONNECTION=pgsql

# Primary Database (Neon PostgreSQL)
NEON_DATABASE_URL=postgresql://user:password@host:5432/database
NEON_DB_HOST=your-neon-host.neon.tech
NEON_DB_PORT=5432
NEON_DB_DATABASE=reverse_tender_auth
NEON_DB_USERNAME=your-neon-username
NEON_DB_PASSWORD=your-neon-password

# Secondary Database (Cloud PostgreSQL)
CLOUD_DATABASE_URL=postgresql://user:password@host:5432/database
CLOUD_DB_HOST=your-cloud-host.com
CLOUD_DB_PORT=5432
CLOUD_DB_DATABASE=reverse_tender_auth
CLOUD_DB_USERNAME=your-cloud-username
CLOUD_DB_PASSWORD=your-cloud-password

# Fallback Database (MongoDB Atlas)
MONGO_DB_HOST=your-cluster.mongodb.net
MONGO_DB_PORT=27017
MONGO_DB_DATABASE=reverse_tender_auth
MONGO_DB_USERNAME=your-mongo-username
MONGO_DB_PASSWORD=your-mongo-password
MONGO_DB_AUTHENTICATION_DATABASE=admin

# Database Failover Settings
DATABASE_FAILOVER_ENABLED=true
DB_PRIMARY_CONNECTION=neon_postgresql
DB_SECONDARY_CONNECTION=cloud_postgresql
DB_FALLBACK_CONNECTION=mongodb_atlas
DB_HEALTH_CHECK_INTERVAL=30
DB_AUTOMATIC_FAILOVER=true
DB_GRACEFUL_DEGRADATION=true

# Service Configuration
SERVICE_NAME=auth-service
```

---

## 🧪 **STEP 3: TESTING & VALIDATION**

### **Test Service Registration**

```bash
cd services/auth-service

# Test if services are properly registered
php artisan tinker

# In tinker:
app(\Shared\Contracts\DatabaseFailoverInterface::class)
app(\Shared\Services\DatabaseFailoverManager::class)
app(\Shared\HealthCheck\DatabaseHealthChecker::class)
```

### **Run Comprehensive Tests**

```bash
# Run the comprehensive failover test
php artisan db:test-failover

# Check health status only
php artisan db:test-failover --check-health

# Test specific connection
php artisan db:test-failover --connection=pgsql

# Trigger manual failover
php artisan db:test-failover --trigger-failover
```

### **Expected Test Results**

✅ **All tests should pass:**
- Service registration ✅
- Configuration loading ✅
- Health checker ✅
- Failover manager ✅
- Database connections ✅

---

## 🔄 **STEP 4: VERIFY MIDDLEWARE INTEGRATION**

### **Test Middleware Functionality**

```bash
# Start the development server
php artisan serve

# Make a test request to trigger middleware
curl -X GET http://localhost:8000/api/health

# Check logs for middleware activity
tail -f storage/logs/laravel.log | grep -i failover
```

### **Expected Middleware Behavior**

- ✅ Middleware loads before each request
- ✅ Health checks run automatically
- ✅ Connection switching occurs when needed
- ✅ Graceful degradation activates when appropriate

---

## 📊 **STEP 5: MONITORING & VALIDATION**

### **Health Status Monitoring**

```bash
# Monitor health status in real-time
watch -n 5 'php artisan db:test-failover --check-health'

# Check health history
php artisan tinker
# In tinker:
$checker = app(\Shared\HealthCheck\DatabaseHealthChecker::class);
$history = $checker->getHealthHistory('pgsql', 10);
dd($history);
```

### **Failover Event Logging**

Monitor these log entries:
- `Database connection switched`
- `Failover successful`
- `Health check failed`
- `Graceful degradation enabled`

---

## 🚀 **STEP 6: ROLLOUT TO OTHER SERVICES**

### **Replicate to Other Microservices**

For each additional service:

1. **Update composer.json** (copy from auth-service)
2. **Add SharedServiceProvider** to bootstrap/providers.php
3. **Update database.php** configuration
4. **Add middleware** to Kernel.php
5. **Copy environment variables**
6. **Run tests**

### **Services to Update**

- ✅ auth-service (completed)
- ⏳ tender-service
- ⏳ notification-service
- ⏳ file-service
- ⏳ payment-service
- ⏳ user-service
- ⏳ admin-service
- ⏳ reporting-service
- ⏳ integration-service
- ⏳ workflow-service
- ⏳ audit-service

---

## 🔧 **TROUBLESHOOTING**

### **Common Issues & Solutions**

#### **1. Package Installation Fails**
```bash
# Clear composer cache
composer clear-cache
composer install --no-cache

# Update composer
composer self-update
```

#### **2. Service Registration Fails**
```bash
# Clear Laravel caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Regenerate autoloader
composer dump-autoload
```

#### **3. Database Connection Issues**
```bash
# Test individual connections
php artisan db:test-failover --connection=pgsql
php artisan db:test-failover --connection=pgsql_secondary
php artisan db:test-failover --connection=mongodb

# Check configuration
php artisan config:show database.connections
```

#### **4. Middleware Not Working**
```bash
# Verify middleware registration
php artisan route:list --middleware=db.failover

# Check middleware loading
php artisan tinker
# In tinker:
app('router')->getMiddleware()
```

#### **5. Health Checks Failing**
```bash
# Test health checker directly
php artisan tinker
# In tinker:
$checker = app(\Shared\HealthCheck\DatabaseHealthChecker::class);
$status = $checker->checkConnection('pgsql');
dd($status->getDetails());
```

---

## 📈 **PERFORMANCE OPTIMIZATION**

### **Recommended Settings**

```env
# Optimize health check intervals
DB_HEALTH_CHECK_INTERVAL=30

# Enable caching for health status
CACHE_DRIVER=redis

# Optimize database timeouts
DB_TIMEOUT=30
```

### **Production Considerations**

- **Health Check Frequency**: 30-60 seconds
- **Connection Timeouts**: 30 seconds
- **Failover Threshold**: 3 consecutive failures
- **Cache Duration**: 30 seconds for health status

---

## ✅ **SUCCESS CRITERIA**

### **System is Functional When:**

1. ✅ All packages installed successfully
2. ✅ Services registered in Laravel container
3. ✅ Health checks return status for all connections
4. ✅ Failover manager can switch connections
5. ✅ Middleware intercepts requests
6. ✅ Test command passes all checks
7. ✅ Logs show failover events

### **Expected Performance**

- **Health Check Duration**: < 100ms per connection
- **Failover Switch Time**: < 1 second
- **Request Overhead**: < 5ms additional latency
- **System Availability**: 99.9%+ with 3-tier failover

---

## 🎯 **NEXT STEPS AFTER INSTALLATION**

1. **Load Testing**: Test failover under load
2. **Monitoring Setup**: Implement health dashboards
3. **Alerting**: Set up notifications for failover events
4. **Documentation**: Update team documentation
5. **Training**: Train team on failover procedures

---

## 📞 **SUPPORT**

If you encounter issues during installation:

1. **Check Logs**: `storage/logs/laravel.log`
2. **Run Tests**: `php artisan db:test-failover`
3. **Verify Config**: `php artisan config:show database-failover`
4. **Test Services**: `php artisan tinker` → test service resolution

The system is designed to be **robust and self-healing**. Once properly configured, it will automatically handle database failures and maintain high availability! 🚀
