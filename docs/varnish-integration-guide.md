# <span style="font-size: 42px; font-weight: 700; line-height: 1.618;">🚀 Varnish Cache Integration Guide</span>
## <span style="font-size: 20px; font-weight: 500; line-height: 1.618; color: #4ECDC4;">Version 2.0 - Multi-Tier Caching Architecture</span>

<p style="font-size: 16px; line-height: 1.618; margin-bottom: 2rem;">This guide explains how to integrate <strong>Varnish caching server</strong> as the L1 cache layer in the V2 multi-tier caching architecture with the Reverse Tender microservices application.</p>

<div style="margin: 2rem 0; padding: 1.5rem; background: linear-gradient(135deg, #FF6B6B10, #4ECDC410); border-radius: 12px; border-left: 4px solid #FF6B6B;">

### <span style="font-size: 18px; font-weight: 600; color: #FF6B6B;">🚀 V2 Varnish Integration Features</span>

**L1 Cache Layer (Varnish):**
- **In-Memory HTTP Caching**: 2GB allocation for ultra-fast response
- **Sub-10ms Response Times**: Instant cache hits for HTTP requests
- **Intelligent TTL Management**: Dynamic cache expiration based on content type
- **VCL Configuration**: Custom Varnish Configuration Language rules

**Multi-Tier Integration:**
- **L1 → L2 Fallback**: Seamless integration with Upstash Redis
- **Cache Warming**: Automatic cache population from Redis layer
- **Smart Invalidation**: Coordinated cache busting across all tiers

</div>

## <span style="font-size: 26px; font-weight: 600; line-height: 1.618;">🏗️ V2 Architecture Overview</span>

Varnish is a high-performance HTTP accelerator that serves as the **L1 cache layer** in our multi-tier caching architecture, providing sub-10ms response times for cached content.

## <span style="font-size: 26px; font-weight: 600; line-height: 1.618;">🏗️ Multi-Tier Caching Architecture</span>

```
Internet → Varnish Cache (L1) → Upstash Redis (L2) → MongoDB Atlas (L3) → Gateway Service → Microservices
```

**V2 Multi-Tier Flow:**
- **L1 (Varnish)**: HTTP cache hits serve responses in sub-10ms
- **L2 (Upstash Redis)**: Cache misses fallback to Redis for sub-50ms responses
- **L3 (MongoDB Atlas)**: Final fallback to serverless database storage
- **Application Layer**: Only reached when all cache layers miss

Varnish acts as the **primary reverse proxy** in our multi-tier caching architecture, providing the fastest possible response times while seamlessly integrating with cloud-native cache layers.

## Prerequisites

- Docker and Docker Compose (for containerized deployment)
- Kubernetes cluster (for K8s deployment)
- Linux/macOS system (for standalone deployment)

## Quick Start

### 1. Update Environment Files

All service `.env` files have been updated with Varnish configuration:

```bash
# Varnish Cache Configuration
VARNISH_ENABLED=true
VARNISH_HOST=varnish
VARNISH_PORT=80
VARNISH_ADMIN_PORT=6081
VARNISH_TTL=300
VARNISH_GRACE=3600

# Cache Headers Configuration
CACHE_CONTROL_MAX_AGE=300
CACHE_CONTROL_PUBLIC=true
ETAG_ENABLED=true
LAST_MODIFIED_ENABLED=true
```

### 2. Deploy Varnish

Choose your deployment method:

#### Docker Compose (Recommended for Development)

```bash
cd deployment/varnish
./deploy-varnish.sh docker
```

#### Kubernetes (Recommended for Production)

```bash
cd deployment/varnish
./deploy-varnish.sh kubernetes
```

#### Standalone Installation

```bash
cd deployment/varnish
./deploy-varnish.sh standalone
```

### 3. Verify Installation

After deployment, verify Varnish is working:

```bash
# Test basic connectivity
curl -I http://localhost/health

# Check cache headers
curl -I http://localhost/api/auctions

# View cache statistics
varnishstat

# Monitor cache activity
varnishlog
```

## Configuration

### VCL Configuration

The main Varnish configuration is in `deployment/varnish/varnish.vcl`. Key features:

- **Backend Routing**: Routes requests to appropriate microservices based on URL patterns
- **Cache Rules**: Different TTL values for different content types
- **Health Checks**: Monitors backend service health
- **Cache Invalidation**: Supports PURGE and BAN methods
- **Compression**: Automatic gzip compression for text content

### Laravel Integration

#### Middleware

Use the `VarnishCacheMiddleware` in your Laravel routes:

```php
// In your route files
Route::middleware(['varnish:300'])->group(function () {
    Route::get('/api/auctions', [AuctionController::class, 'index']);
    Route::get('/api/users', [UserController::class, 'index']);
});
```

#### Configuration

Copy the Varnish configuration to your Laravel services:

```bash
# Copy shared configuration
cp services/shared/config/varnish.php services/your-service/config/
```

#### Cache Invalidation

Invalidate cache when data changes:

```php
use Shared\Middleware\VarnishCacheMiddleware;

// Purge specific patterns
VarnishCacheMiddleware::purgeCache(['/api/auctions.*']);

// Purge all cache
VarnishCacheMiddleware::purgeAll();
```

## Deployment Options

### Docker Compose

The Docker Compose setup includes:
- Varnish cache server
- Prometheus exporter for monitoring
- Automatic health checks
- Volume persistence

```yaml
services:
  varnish:
    image: varnish:7.4
    ports:
      - "80:80"
      - "6081:6081"
    volumes:
      - ./varnish.vcl:/etc/varnish/default.vcl:ro
```

### Kubernetes

The Kubernetes deployment includes:
- ConfigMap for VCL configuration
- Deployment with 2 replicas
- LoadBalancer service
- Ingress with SSL termination
- Resource limits and health checks

```bash
kubectl apply -f deployment/varnish/k8s-varnish.yaml
```

### Standalone

For standalone installation on Linux/macOS:
- Installs Varnish using system package manager
- Configures systemd service (Linux)
- Sets up proper VCL configuration
- Enables automatic startup

## Monitoring and Debugging

### Varnish Statistics

```bash
# Real-time statistics
varnishstat

# Specific metrics
varnishstat -f MAIN.cache_hit -f MAIN.cache_miss

# Hit ratio
varnishstat -1 | grep cache_hit
```

### Logging

```bash
# View all requests
varnishlog

# Filter by specific criteria
varnishlog -q "ReqURL ~ '/api/auctions'"

# View only cache misses
varnishlog -q "VCL_call ~ 'MISS'"
```

### Admin Interface

```bash
# Connect to admin interface
varnishadm -T localhost:6081

# View backend health
varnishadm backend.list

# Purge cache
varnishadm "ban req.url ~ /api/auctions"
```

## Cache Strategy

### TTL Configuration

Different content types have different cache durations:

- **HTML**: 5 minutes
- **JSON API**: 2 minutes
- **CSS/JS**: 1 hour
- **Images**: 24 hours
- **Static assets**: 24 hours

### Cache Rules

#### Never Cache
- Authentication endpoints (`/api/auth/*`)
- Create/Update/Delete operations
- Admin interfaces
- Private content

#### Cache with Custom TTL
- Public API endpoints
- Health checks
- Status pages

#### Long-term Cache
- Static assets
- Images
- CSS/JS files

### Cache Invalidation

#### Automatic Invalidation
- Set up model observers to purge cache when data changes
- Use cache tags for selective invalidation

#### Manual Invalidation
```php
// Purge specific URLs
VarnishCacheMiddleware::purgeCache(['/api/auctions/123']);

// Purge by pattern
VarnishCacheMiddleware::purgeCache(['/api/auctions.*']);
```

## Performance Optimization

### Memory Configuration

Adjust Varnish memory based on your needs:

```bash
# In docker-compose.yml
environment:
  - VARNISH_SIZE=512M  # Increase for more cache

# In standalone installation
# Edit /etc/systemd/system/varnish.service.d/override.conf
-s malloc,512M
```

### Backend Configuration

Optimize backend connections:

```vcl
backend gateway_service {
    .host = "gateway-service";
    .port = "8080";
    .connect_timeout = 5s;
    .first_byte_timeout = 30s;
    .between_bytes_timeout = 5s;
}
```

### Health Checks

Configure appropriate health check intervals:

```vcl
.probe = {
    .url = "/health";
    .timeout = 5s;
    .interval = 10s;
    .window = 5;
    .threshold = 3;
}
```

## Security Considerations

### Access Control

Limit admin access:

```vcl
acl purge {
    "localhost";
    "127.0.0.1";
    "10.0.0.0"/8;
    "172.16.0.0"/12;
    "192.168.0.0"/16;
}
```

### SSL Termination

For production, terminate SSL at the load balancer level before Varnish, or use Varnish with SSL support.

### Cache Poisoning Prevention

- Normalize URLs
- Remove tracking parameters
- Validate cache keys

## Troubleshooting

### Common Issues

#### Varnish Not Starting
```bash
# Check configuration syntax
varnishd -C -f /etc/varnish/default.vcl

# Check logs
journalctl -u varnish -f
```

#### Cache Not Working
```bash
# Check if requests are reaching Varnish
varnishlog -q "ReqURL ~ '/api/auctions'"

# Verify cache headers
curl -I http://localhost/api/auctions
```

#### Backend Connection Issues
```bash
# Check backend health
varnishadm backend.list

# Test backend directly
curl http://gateway-service:8080/health
```

### Debug Headers

Enable debug headers in development:

```bash
# In .env
VARNISH_DEBUG=true
```

This adds helpful headers:
- `X-Cache`: HIT or MISS
- `X-Cache-Hits`: Number of hits
- `X-Cache-TTL`: Cache TTL
- `X-Served-By`: Server hostname

## Best Practices

1. **Start with Conservative TTL**: Begin with short cache times and increase gradually
2. **Monitor Hit Ratios**: Aim for >80% cache hit ratio
3. **Use Cache Tags**: Implement cache tagging for selective invalidation
4. **Test Cache Invalidation**: Ensure cache purges work correctly
5. **Monitor Backend Health**: Set up proper health checks
6. **Log Cache Activity**: Monitor cache performance and issues
7. **Regular Maintenance**: Clear old cache entries and monitor memory usage

## Integration with CI/CD

Add Varnish deployment to your CI/CD pipeline:

```yaml
# In .github/workflows/deploy.yml
- name: Deploy Varnish
  run: |
    cd deployment/varnish
    ./deploy-varnish.sh kubernetes
```

## Scaling Considerations

For high-traffic applications:

1. **Multiple Varnish Instances**: Deploy multiple Varnish instances behind a load balancer
2. **Shared Cache Storage**: Consider using shared storage for cache data
3. **Cache Warming**: Implement cache warming strategies
4. **Geographic Distribution**: Deploy Varnish instances in multiple regions

## Conclusion

Varnish integration provides significant performance improvements for the Reverse Tender application. With proper configuration and monitoring, you can achieve:

- **Reduced Response Times**: 10-100x faster response times for cached content
- **Lower Server Load**: Reduced load on backend services
- **Better Scalability**: Handle more concurrent users
- **Improved User Experience**: Faster page loads and API responses

For questions or issues, refer to the [Varnish documentation](https://varnish-cache.org/docs/) or check the troubleshooting section above.
