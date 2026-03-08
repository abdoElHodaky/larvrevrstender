# RPC Authentication System Documentation

## Overview

This document describes the comprehensive RPC (Remote Procedure Call) authentication system implemented for secure inter-service communication in the microservices architecture. The system uses Laravel Sanctum personal access tokens to authenticate service-to-service RPC calls.

## Architecture

### Components

1. **Token Generation System** - Generates Sanctum tokens for inter-service authentication
2. **Token Distribution** - Updates service environment files with authentication tokens
3. **RPC Client Integration** - Attaches tokens to outbound RPC requests
4. **Token Rotation System** - Automated token lifecycle management
5. **Authentication Testing** - Validates inter-service authentication

### Authentication Flow

```
Service A → HTTP Request with Bearer Token → Service B
                                          ↓
                                    Sanctum Guard
                                          ↓
                                   Token Validation
                                          ↓
                                    RPC Method Execution
```

## Commands

### 1. Generate RPC Tokens

Generate Sanctum tokens for all microservices.

```bash
cd services/auth-service
php artisan rpc:generate-tokens [options]
```

**Options:**
- `--regenerate` - Regenerate existing tokens
- `--service=name` - Generate tokens for specific service only
- `--expires-in=hours` - Token expiration in hours (default: 8760 = 1 year)

**Examples:**
```bash
# Generate tokens for all services
php artisan rpc:generate-tokens

# Regenerate all tokens
php artisan rpc:generate-tokens --regenerate

# Generate tokens for specific service
php artisan rpc:generate-tokens --service=user-service

# Set custom expiration (30 days)
php artisan rpc:generate-tokens --expires-in=720
```

### 2. Test RPC Authentication

Test authentication between services.

```bash
cd services/auth-service
php artisan rpc:test-authentication [options]
```

**Options:**
- `--service=name` - Test specific service only
- `--timeout=seconds` - Request timeout (default: 10)

**Examples:**
```bash
# Test all services
php artisan rpc:test-authentication

# Test specific service
php artisan rpc:test-authentication --service=user-service

# Test with custom timeout
php artisan rpc:test-authentication --timeout=30
```

### 3. Rotate RPC Tokens

Rotate tokens for security maintenance.

```bash
cd services/auth-service
php artisan rpc:rotate-tokens [options]
```

**Options:**
- `--dry-run` - Show what would be rotated without making changes
- `--force` - Force rotation even if tokens are not near expiration
- `--service=name` - Rotate tokens for specific service only
- `--expires-in=hours` - New token expiration (default: 8760)
- `--backup` - Create backup of current tokens

**Examples:**
```bash
# Preview rotation (dry run)
php artisan rpc:rotate-tokens --dry-run

# Force rotate all tokens with backup
php artisan rpc:rotate-tokens --force --backup

# Rotate specific service
php artisan rpc:rotate-tokens --service=gateway-service
```

## Token Architecture

### Token Format

Tokens follow Sanctum's format: `{prefix}|{hash}`

Example: `f8cdac38|f8cdac38743222a0aeb1788a676f50b64bfc472996e43c57b272a23d65d451ff`

### Token Abilities

Each token is assigned specific abilities:
- `rpc:call` - Permission to make RPC calls
- `rpc:validate` - Permission to validate tokens
- `service:{target}` - Target service identifier
- `caller:{source}` - Source service identifier

### Token Distribution

For each service, tokens are generated for communication with all other services:
- **Total Services**: 10
- **Tokens per Service**: 9 (excluding self)
- **Total Tokens**: 90

### Environment Variables

Tokens are stored in service `.env` files:
```env
RPC_USER_SERVICE_TOKEN=f8cdac38|f8cdac38743222a0aeb1788a676f50b64bfc472996e43c57b272a23d65d451ff
RPC_AUCTION_SERVICE_TOKEN=a4c82f85|a4c82f856b38048fada8fc24870f37f5db43c24fa27fa1fe7970730a9bd58a68
RPC_BIDDING_SERVICE_TOKEN=9c3cd959|9c3cd959f5e86af792528fdfed1aa36d15f8509e4b79af37fa91f16cfa8ff3f4
# ... additional tokens
```

## Service Integration

### RPC Configuration

Each service has an `config/rpc.php` file that defines service endpoints and token configuration:

```php
'services' => [
    'auth' => [
        'url' => env('RPC_AUTH_SERVICE_URL', 'http://auth-service:8080/rpc'),
        'token' => env('RPC_AUTH_SERVICE_TOKEN', ''),
    ],
    'user' => [
        'url' => env('RPC_USER_SERVICE_URL', 'http://user-service:8080/rpc'),
        'token' => env('RPC_USER_SERVICE_TOKEN', ''),
    ],
    // ... additional services
],
```

### RPC Service Provider

The `RpcServiceProvider` in each service configures HTTP clients with authentication:

```php
$this->app->singleton('AuthRpc', function () {
    return new \Sajya\Client\Client(
        \Illuminate\Support\Facades\Http::baseUrl(config('rpc.services.auth.url'))
            ->withToken(config('rpc.services.auth.token'))
            ->withHeaders([
                'X-Service-Name' => 'auction-service',
                'X-Correlation-ID' => request()->header('X-Correlation-ID', uniqid('rpc_', true)),
            ])
            ->timeout(config('rpc.client.timeout', 5))
    );
});
```

### Request Headers

RPC requests include the following headers:
- `Authorization: Bearer {token}` - Sanctum authentication token
- `X-Service-Name` - Calling service identifier
- `X-Correlation-ID` - Request tracing identifier
- `Content-Type: application/json` - JSON-RPC content type

## Production Deployment

### Automated Token Rotation

Use the provided shell script for production token rotation:

```bash
# Monthly rotation with backup
./scripts/rpc-token-rotation.sh --backup --webhook="$WEBHOOK_URL"

# Dry run to check status
./scripts/rpc-token-rotation.sh --dry-run

# Force rotation for all services
./scripts/rpc-token-rotation.sh --force --backup
```

### Cron Job Setup

Add to your production server's crontab:

```bash
# Monthly token rotation (1st day of month at 2:00 AM)
0 2 1 * * /path/to/project/scripts/rpc-token-rotation.sh --backup >> /var/log/rpc-rotation.log 2>&1

# Weekly health check (Sundays at 3:00 AM)
0 3 * * 0 /path/to/project/scripts/rpc-token-rotation.sh --dry-run >> /var/log/rpc-health.log 2>&1
```

### Environment Variables

Set these environment variables for production:

```bash
RPC_ROTATION_WEBHOOK=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
RPC_ROTATION_BACKUP=true
```

## Security Considerations

### Token Security

1. **File Permissions**: Ensure `.env` files have restricted permissions (600)
2. **Token Expiration**: Use appropriate expiration times for your environment
3. **Token Rotation**: Implement regular token rotation (monthly recommended)
4. **Backup Strategy**: Keep secure backups of tokens before rotation

### Monitoring

1. **Authentication Logs**: Monitor Sanctum logs for authentication failures
2. **Token Usage**: Track token usage patterns for anomaly detection
3. **Rotation Alerts**: Set up notifications for token rotation events
4. **Health Checks**: Regular authentication testing between services

### Best Practices

1. **Principle of Least Privilege**: Tokens have specific abilities for their intended use
2. **Service Isolation**: Each service has unique tokens for others
3. **Audit Trail**: All token operations are logged
4. **Graceful Degradation**: Static token fallback for development environments

## Troubleshooting

### Common Issues

1. **Authentication Failures**
   - Check token presence in `.env` files
   - Verify token format and validity
   - Ensure services are restarted after token updates

2. **Token Generation Errors**
   - Verify database connectivity
   - Check Sanctum configuration
   - Ensure proper file permissions

3. **Service Communication Issues**
   - Test network connectivity between services
   - Verify RPC endpoint URLs
   - Check service health and availability

### Debugging Commands

```bash
# Check token configuration
grep "RPC_.*_TOKEN" services/*/\.env

# Test specific service authentication
php artisan rpc:test-authentication --service=user-service

# Check token status
php artisan rpc:rotate-tokens --dry-run

# View Sanctum tokens (if database available)
php artisan tinker
>>> \Laravel\Sanctum\PersonalAccessToken::all()
```

## Migration Guide

### From No Authentication

1. Generate initial tokens:
   ```bash
   php artisan rpc:generate-tokens
   ```

2. Restart all services to load tokens

3. Test authentication:
   ```bash
   php artisan rpc:test-authentication
   ```

### Token Rotation

1. Create backup (optional):
   ```bash
   php artisan rpc:rotate-tokens --backup --dry-run
   ```

2. Perform rotation:
   ```bash
   php artisan rpc:rotate-tokens --force --backup
   ```

3. Restart services and test

## Support

For issues or questions regarding the RPC authentication system:

1. Check the troubleshooting section above
2. Review service logs for authentication errors
3. Test authentication using the provided commands
4. Verify token configuration and service restart procedures

## Changelog

### Version 1.0.0 (2026-03-08)

- Initial implementation of RPC authentication system
- Sanctum-based token generation and management
- Automated token rotation with backup support
- Comprehensive testing and monitoring tools
- Production deployment scripts and documentation
