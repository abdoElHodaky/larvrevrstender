# Varnish Cache Integration

This directory contains all the necessary files for integrating Varnish caching server with the Reverse Tender microservices application.

## Files Overview

### Core Configuration
- **`varnish.vcl`** - Main Varnish Configuration Language file with routing and caching rules
- **`docker-compose.varnish.yml`** - Docker Compose deployment configuration
- **`k8s-varnish.yaml`** - Kubernetes deployment manifests
- **`deploy-varnish.sh`** - Automated deployment script (executable)

### Laravel Integration
- **`../services/shared/src/Middleware/VarnishCacheMiddleware.php`** - Laravel middleware for cache headers
- **`../services/shared/config/varnish.php`** - Configuration file for Laravel services

## Quick Start

### 1. Deploy Varnish

Choose your deployment method:

```bash
# Docker Compose (Development)
./deploy-varnish.sh docker

# Kubernetes (Production)
./deploy-varnish.sh kubernetes

# Standalone Installation
./deploy-varnish.sh standalone
```

### 2. Verify Installation

```bash
# Test connectivity
curl -I http://localhost/health

# Check cache headers
curl -I http://localhost/api/auctions

# Monitor statistics
varnishstat
```

### 3. Use in Laravel

```php
// Apply caching middleware to routes
Route::middleware(['varnish:300'])->group(function () {
    Route::get('/api/auctions', [AuctionController::class, 'index']);
});

// Purge cache when data changes
VarnishCacheMiddleware::purgeCache(['/api/auctions.*']);
```

## Architecture

```
Internet → Varnish Cache (Port 80) → Gateway Service → Microservices
                ↓
           Admin Interface (Port 6081)
```

## Key Features

- **Intelligent Routing**: Routes requests to appropriate microservices
- **Content-Type Caching**: Different TTL for HTML, JSON, CSS, images
- **Health Checks**: Monitors all backend services
- **Cache Invalidation**: PURGE/BAN support for selective clearing
- **Compression**: Automatic gzip for text content
- **Monitoring**: Prometheus metrics and debug headers

## Performance Benefits

- **10-100x faster** response times for cached content
- **Reduced server load** on backend microservices
- **Better scalability** for high-traffic scenarios
- **Improved user experience** with faster API responses

## Documentation

For complete documentation, see: `../docs/varnish-integration-guide.md`

## Support

- Varnish Statistics: `varnishstat`
- Real-time Logs: `varnishlog`
- Admin Interface: `varnishadm -T localhost:6081`
- Configuration Test: `varnishd -C -f varnish.vcl`
