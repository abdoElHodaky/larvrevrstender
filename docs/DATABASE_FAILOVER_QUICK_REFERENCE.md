# 🚀 Database Failover Quick Reference Guide

## 📋 TL;DR - What You Need to Know

### 🎯 Service Classification

| Service | Failover Type | Why? | Lines of Code |
|---------|---------------|------|---------------|
| **Order** | ✅ Complex | Revenue = Money | 17 lines (was 226) |
| **Payment** | ✅ Complex | Financial/PCI DSS | 17 lines (was 342) |
| **User** | ✅ Complex | Customer Data | 17 lines (was 280) |
| **Auth** | ✅ Complex | System Security | 17 lines (NEW) |
| **Bidding** | ✅ Complex | Auction Revenue | 17 lines (was 200) |
| **Notification** | ❌ Simple | Async/Retry | No complex failover |
| **VIN-OCR** | ❌ Simple | Regenerable | No complex failover |
| **Analytics** | ❌ Simple | Eventual Consistency | 34 lines (simple) |
| **Gateway** | ❌ None | Routing Only | No database storage |

## 🏗️ Architecture Pattern

### Shared Library (services/shared/src/Listeners/)
```
BaseDatabaseFailoverHandler.php (293 lines) - Common patterns
OrderServiceDatabaseFailoverHandler.php (190 lines) - Order-specific logic
PaymentServiceDatabaseFailoverHandler.php (204 lines) - Payment-specific logic
UserServiceDatabaseFailoverHandler.php (191 lines) - User-specific logic
AuthServiceDatabaseFailoverHandler.php (196 lines) - Auth-specific logic
BiddingServiceDatabaseFailoverHandler.php (186 lines) - Bidding-specific logic
```

### Service Implementation (services/{service}/app/Listeners/)
```php
<?php
namespace App\Listeners;
use Shared\Listeners\OrderServiceDatabaseFailoverHandler;

class HandleDatabaseFailover extends OrderServiceDatabaseFailoverHandler
{
    // All implementation inherited from shared library
}
```

## ⚙️ Key Configurations

### Success Rate Thresholds
- **Auth Service**: 99.0% (highest - security critical)
- **Payment Service**: 98.0% (financial operations)
- **Bidding Service**: 98.5% (auction integrity)
- **Order Service**: 95.0% (revenue operations)
- **User Service**: 97.0% (user experience)

### Buffer Alert Thresholds
- **Auth Service**: 20 operations (most sensitive)
- **Bidding Service**: 25 operations (auction timing)
- **Payment Service**: 30 operations (financial risk)
- **User Service**: 40 operations (user tolerance)
- **Order Service**: 50 operations (order volume)

### Critical Operation Max Delays
- **Permission Check**: 2 seconds (Auth)
- **User Login**: 3 seconds (Auth)
- **Payment Processing**: 3 seconds (Payment)
- **Bid Submission**: 5 seconds (Bidding)
- **Order Creation**: 10 seconds (Order)

## 🚨 Emergency Stakeholders

### Order Service
- Operations Director, Revenue Team, E-commerce Manager

### Payment Service  
- CFO, Finance Director, Compliance Officer, Risk Management

### User Service
- Customer Success Director, Support Lead, Product Manager

### Auth Service
- Security Team Lead, IT Security Manager, System Administrator

### Bidding Service
- Auction Operations Director, Auction Management Team

## 🔧 Developer Actions

### Adding a New Critical Service
1. Create `{Service}DatabaseFailoverHandler.php` in shared library
2. Define service-specific configuration and stakeholders
3. Create simple listener in service that extends shared handler
4. Test failover scenarios

### Updating Failover Logic
1. Modify shared handler in `services/shared/src/Listeners/`
2. Changes automatically apply to all services using that handler
3. Test across all dependent services

### Debugging Failover Issues
1. Check cache flags: `{service}_service_mode`, `{service}_service_failover_started`
2. Review logs: Look for "CRITICAL" level messages with service name
3. Verify stakeholder notifications: Check cache keys `{service}_service_*_alert_*`

## 📊 Monitoring Commands

### Check Service Status
```bash
# Check if service is in failover mode
redis-cli GET payment_service_mode

# Check failover coordination status  
redis-cli GET auth_service_coordinating_security_protection

# Check emergency procedures status
redis-cli GET order_service_emergency_procedures_active
```

### Health Metrics
```bash
# Service health status
redis-cli GET {service}_service_health

# Failover count
redis-cli GET {service}_service_failover_count

# Last failover timestamp
redis-cli GET {service}_service_last_failover
```

## 🎯 Business Impact Levels

### CRITICAL (Immediate C-Level Notification)
- **Payment Service**: Financial operations suspended
- **Auth Service**: Authentication system compromised
- **Order Service**: Revenue generation halted

### HIGH (Operations Team Notification)
- **User Service**: Customer experience degraded
- **Bidding Service**: Auction integrity at risk

### MEDIUM (Technical Team Notification)
- **Analytics Service**: Reporting delayed (acceptable)

### LOW (Basic Logging Only)
- **Notification Service**: Messages queued for retry
- **VIN-OCR Service**: Processing jobs queued for retry

## 🔄 Recovery Procedures

### Critical Services (< 15 minutes target)
1. **Detection** (< 5 seconds)
2. **Stakeholder Alert** (< 30 seconds)
3. **Service Coordination** (< 2 minutes)
4. **Emergency Procedures** (< 5 minutes)
5. **Recovery Initiation** (< 15 minutes)

### Non-Critical Services (< 5 minutes target)
1. **Detection** (< 5 seconds)
2. **Simple Restart** (< 5 minutes)

## 💡 Best Practices

### DO ✅
- Use shared handlers for consistent behavior
- Configure service-specific thresholds based on business impact
- Test failover scenarios regularly
- Monitor cache flags for service coordination
- Update shared library for cross-service improvements

### DON'T ❌
- Duplicate failover logic across services
- Use same configuration for all services
- Ignore business impact when designing resilience
- Skip testing of emergency procedures
- Modify individual service handlers instead of shared library

## 🚀 Quick Commands

### Test Failover (Development)
```bash
# Trigger test failover event
php artisan db:failover:test --service=payment

# Check service response
redis-cli GET payment_service_mode
```

### Monitor Active Failovers
```bash
# List all services in failover mode
redis-cli KEYS "*_service_mode" | xargs redis-cli MGET

# Check emergency procedures
redis-cli KEYS "*_emergency_procedures_active"
```

### Reset Service After Recovery
```bash
# Clear failover flags
redis-cli DEL payment_service_mode
redis-cli DEL payment_service_failover_started
redis-cli DEL payment_service_emergency_procedures_active
```

---

**Remember: This architecture is designed to be business-aware - not every service needs the same level of complexity!** 🎯
