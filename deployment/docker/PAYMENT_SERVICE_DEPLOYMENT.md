<div style="max-width: 38.2rem; line-height: 1.618; font-family: 'Inter', 'Segoe UI', 'Roboto', sans-serif;">

# <span style="font-size: 42px; font-weight: 700; line-height: 1.618;">💳 Payment Service Deployment</span>

<p style="font-size: 16px; line-height: 1.618; margin-bottom: 2rem;">Deployment configuration for the <strong>enterprise payment service</strong> with multi-gateway support, PCI DSS compliance, and scalable architecture for high-volume transactions.</p>

## <span style="font-size: 26px; font-weight: 600; line-height: 1.618;">🎯 Payment Strategy Overview</span>

### <span style="font-size: 20px; font-weight: 600; line-height: 1.618;">62% Major Concepts</span>

- **💰 Multi-Gateway Support**: 6 payment gateways (Stripe, PayPal, Mada, STC Pay, Razorpay, Square)
- **🔒 Enterprise Security**: PCI DSS compliance, encryption, and comprehensive audit trails
- **📊 Advanced Analytics**: Real-time monitoring, reporting, and scalable high-volume architecture

<details style="border-left: 3px solid #4ECDC4; padding-left: 1rem; margin: 1rem 0;">
<summary style="font-weight: 600; cursor: pointer;">💳 Complete Payment Deployment</summary>

### Environment Configuration

Copy the sample environment file and configure your payment gateways:

```bash
cp deployment/config/payment-service/payment.env .env.payment
```

Edit `.env.payment` with your actual gateway credentials:

```bash
# Required for Stripe
STRIPE_PUBLIC_KEY=pk_live_your_actual_stripe_public_key
STRIPE_SECRET_KEY=sk_live_your_actual_stripe_secret_key
STRIPE_WEBHOOK_SECRET=whsec_your_actual_webhook_secret

# Required for PayPal
PAYPAL_CLIENT_ID=your_actual_paypal_client_id
PAYPAL_CLIENT_SECRET=your_actual_paypal_client_secret
PAYPAL_MODE=live  # Change to 'live' for production

# Required for Saudi Arabia
MADA_MERCHANT_ID=your_actual_mada_merchant_id
MADA_SECRET_KEY=your_actual_mada_secret_key
STC_PAY_MERCHANT_ID=your_actual_stc_pay_merchant_id
STC_PAY_SECRET_KEY=your_actual_stc_pay_secret_key

# Security (CRITICAL - Generate strong keys)
PAYMENT_ENCRYPTION_KEY=your_32_character_encryption_key_here
```

### 2. Deploy with Docker Compose

```bash
# Development deployment
docker-compose -f docker-compose.base.yml -f docker-compose.override.yml up payment-service

# Production deployment
docker-compose -f docker-compose.base.yml -f environments/production.yml up payment-service
```

### 3. Verify Deployment

```bash
# Check service health
curl http://localhost:8004/octane/health

# Check payment gateway connectivity
curl http://localhost:8004/api/gateways/health

# Run comprehensive health check
docker exec rt_payment_service /usr/local/bin/payment-healthcheck.sh
```

## Configuration Details

### Payment Gateway Configuration

#### Stripe
```env
STRIPE_ENABLED=true
STRIPE_PUBLIC_KEY=pk_live_...
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_API_VERSION=2023-10-16
```

#### PayPal
```env
PAYPAL_ENABLED=true
PAYPAL_MODE=live  # or 'sandbox' for testing
PAYPAL_CLIENT_ID=your_client_id
PAYPAL_CLIENT_SECRET=your_client_secret
PAYPAL_WEBHOOK_ID=your_webhook_id
```

#### Mada (Saudi Arabia)
```env
MADA_ENABLED=true
MADA_MERCHANT_ID=your_merchant_id
MADA_TERMINAL_ID=your_terminal_id
MADA_SECRET_KEY=your_secret_key
MADA_API_URL=https://api.mada.sa/v1
```

#### STC Pay (Saudi Arabia)
```env
STC_PAY_ENABLED=true
STC_PAY_MERCHANT_ID=your_merchant_id
STC_PAY_SECRET_KEY=your_secret_key
STC_PAY_API_URL=https://api.stcpay.com.sa/v1
```

### Security Configuration

```env
# Encryption (REQUIRED)
PAYMENT_ENCRYPTION_KEY=your_32_character_encryption_key_here

# Webhook Security
WEBHOOK_SIGNATURE_VERIFICATION=true

# Rate Limiting
PAYMENT_RATE_LIMIT=100

# Token Security
PAYMENT_TOKEN_EXPIRY=3600
```

### Performance Configuration

```env
# Resource Allocation
OCTANE_WORKERS=6
OCTANE_TASK_WORKERS=8
OCTANE_MEMORY_LIMIT=1G

# FrankenPHP Optimization
FRANKENPHP_NUM_THREADS=6

# PHP OPCache
PHP_OPCACHE_MEMORY_CONSUMPTION=512
```

## Docker Configuration

### Enhanced Dockerfile

The payment service uses a specialized Dockerfile (`Dockerfile.payment`) with:

- **Payment-specific PHP extensions**: bcmath, sodium, xml, curl
- **Security hardening**: Non-root user, secure PHP configuration
- **Performance optimization**: OPCache tuning, classmap authoritative
- **Health monitoring**: Comprehensive health check script

### Resource Requirements

```yaml
deploy:
  resources:
    limits:
      memory: 2G      # Increased for payment processing
      cpus: '2.0'     # Increased for concurrent transactions
    reservations:
      memory: 1G      # Higher baseline for reliability
      cpus: '1.0'
```

### Health Checks

```yaml
healthcheck:
  test: ["CMD", "curl", "-f", "http://localhost:8004/octane/health"]
  interval: 30s
  timeout: 15s      # Increased timeout for payment processing
  retries: 5        # More retries for reliability
  start_period: 90s # More time for payment service initialization
```

## Production Deployment

### 1. Environment-Specific Configuration

Create production environment file:

```bash
# deployment/docker/environments/production.yml
services:
  payment-service:
    environment:
      # Production gateway settings
      STRIPE_ENABLED: true
      PAYPAL_MODE: live
      MADA_ENABLED: true
      STC_PAY_ENABLED: true
      
      # Production security
      WEBHOOK_SIGNATURE_VERIFICATION: true
      PAYMENT_AUDIT_ENABLED: true
      
      # Production performance
      OCTANE_WORKERS: 8
      OCTANE_TASK_WORKERS: 12
```

### 2. SSL/TLS Configuration

Ensure HTTPS is configured for webhook endpoints:

```env
APP_URL=https://payments.yourdomain.com
OCTANE_HTTPS=true
```

### 3. Database Configuration

Configure dedicated payment database:

```env
DB_HOST=payment-db-cluster
DB_DATABASE=reverse_tender_payments
DB_USERNAME=payment_service_user
DB_PASSWORD=secure_payment_db_password
```

### 4. Monitoring and Logging

```env
# Enhanced logging for production
PAYMENT_LOGGING_LEVEL=info
PAYMENT_ANALYTICS_ENABLED=true
PAYMENT_METRICS_ENABLED=true
PAYMENT_AUDIT_ENABLED=true
```

## Security Considerations

### 1. Encryption Keys

Generate strong encryption keys:

```bash
# Generate 32-character encryption key
openssl rand -hex 16
```

### 2. Webhook Security

- Enable signature verification for all gateways
- Use HTTPS endpoints for webhooks
- Implement replay attack prevention

### 3. Network Security

```yaml
networks:
  payment-network:
    driver: bridge
    ipam:
      config:
        - subnet: 172.20.0.0/16
```

### 4. Secrets Management

Use Docker secrets or external secret management:

```yaml
secrets:
  stripe_secret_key:
    external: true
  paypal_client_secret:
    external: true
```

## Monitoring and Maintenance

### Health Monitoring

The payment service includes comprehensive health monitoring:

```bash
# Manual health check
docker exec rt_payment_service /usr/local/bin/payment-healthcheck.sh

# Automated monitoring endpoints
curl http://localhost:8004/octane/health
curl http://localhost:8004/api/health
curl http://localhost:8004/api/gateways/health
```

### Log Management

```bash
# View payment service logs
docker logs rt_payment_service

# View specific log files
docker exec rt_payment_service tail -f storage/logs/laravel.log
docker exec rt_payment_service tail -f storage/logs/payment.log
```

### Performance Monitoring

Monitor key metrics:

- **Response Time**: Payment processing latency
- **Success Rate**: Transaction success percentage
- **Gateway Health**: Individual gateway availability
- **Resource Usage**: Memory and CPU utilization

## Troubleshooting

### Common Issues

1. **Gateway Connection Failures**
   ```bash
   # Check gateway connectivity
   curl http://localhost:8004/api/gateways/health
   ```

2. **Database Connection Issues**
   ```bash
   # Test database connectivity
   docker exec rt_payment_service php artisan tinker --execute="DB::connection()->getPdo();"
   ```

3. **Memory Issues**
   ```bash
   # Check memory usage
   docker stats rt_payment_service
   ```

### Debug Mode

Enable debug mode for troubleshooting:

```env
APP_DEBUG=true
LOG_LEVEL=debug
PAYMENT_LOGGING_LEVEL=debug
```

## Scaling Considerations

### Horizontal Scaling

The payment service supports horizontal scaling:

```yaml
deploy:
  replicas: 3
  update_config:
    parallelism: 1
    delay: 10s
  restart_policy:
    condition: on-failure
```

### Load Balancing

Configure load balancing for multiple instances:

```yaml
services:
  payment-lb:
    image: nginx:alpine
    ports:
      - "8004:80"
    depends_on:
      - payment-service
```

## Backup and Recovery

### Database Backups

```bash
# Automated backup script
docker exec mysql-primary mysqldump reverse_tender_payments > payment_backup_$(date +%Y%m%d).sql
```

### Configuration Backups

```bash
# Backup environment configuration
cp .env.payment .env.payment.backup.$(date +%Y%m%d)
```

## Support and Documentation

- **API Documentation**: Available at `/api/documentation`
- **Health Endpoints**: `/octane/health`, `/api/health`
- **Metrics Endpoint**: `/api/metrics`
- **Gateway Status**: `/api/gateways/health`

For additional support, refer to the main deployment documentation or contact the development team.
