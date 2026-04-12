# Gateway

## Overview

The Gateway service acts as the central API gateway for the Reverse Tender platform, providing unified access to all microservices, request routing, authentication, rate limiting, and cross-cutting concerns management.

## Features

- **API Gateway**: Central entry point for all client requests
- **Request Routing**: Intelligent routing to appropriate microservices
- **Authentication & Authorization**: JWT token validation and user authentication
- **Rate Limiting**: Request throttling and abuse prevention
- **Load Balancing**: Distribute requests across service instances
- **Request/Response Transformation**: Data format conversion and validation
- **Monitoring & Logging**: Centralized request logging and metrics collection
- **CORS Management**: Cross-origin resource sharing configuration
- **API Versioning**: Support for multiple API versions

## API Endpoints

### Health & Status
- `GET /health` - Gateway health check
- `GET /status` - Service status and metrics
- `GET /api/v1/status` - API version status

### Authentication Proxy
- `POST /api/v1/auth/login` - Proxy to auth service
- `POST /api/v1/auth/logout` - Proxy to auth service
- `POST /api/v1/auth/refresh` - Proxy to auth service

### Service Routing
- `GET|POST|PUT|DELETE /api/v1/users/*` - Route to users service
- `GET|POST|PUT|DELETE /api/v1/auctions/*` - Route to auctions service
- `GET|POST|PUT|DELETE /api/v1/bidding/*` - Route to bidding service
- `GET|POST|PUT|DELETE /api/v1/orders/*` - Route to orders service
- `GET|POST|PUT|DELETE /api/v1/payments/*` - Route to payments service
- `GET|POST|PUT|DELETE /api/v1/notifications/*` - Route to notifications service
- `GET|POST|PUT|DELETE /api/v1/analytics/*` - Route to analytics service

## Configuration

### Environment Variables

```bash
# Application Configuration
APP_NAME="Gateway"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database Configuration
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=gateway_db
DB_USERNAME=postgres
DB_PASSWORD=password

# Service URLs
AUTH_URL=http://localhost:8001
USERS_URL=http://localhost:8002
AUCTIONS_URL=http://localhost:8003
BIDDING_URL=http://localhost:8004
ANALYTICS_URL=http://localhost:8005
VIN_OCR_URL=http://localhost:8006
NOTIFICATIONS_URL=http://localhost:8007
ORDERS_URL=http://localhost:8008
PAYMENTS_URL=http://localhost:8009

# Service Authentication Tokens
AUTH_TOKEN=your_auth_token_here
USERS_TOKEN=your_users_token_here
AUCTIONS_TOKEN=your_auctions_token_here
BIDDING_TOKEN=your_bidding_token_here
ANALYTICS_TOKEN=your_analytics_token_here
VIN_OCR_TOKEN=your_vin_ocr_token_here
NOTIFICATIONS_TOKEN=your_notifications_token_here
ORDERS_TOKEN=your_orders_token_here
PAYMENTS_TOKEN=your_payments_token_here

# Rate Limiting
RATE_LIMIT_REQUESTS=1000
RATE_LIMIT_WINDOW=60

# CORS Configuration
CORS_ALLOWED_ORIGINS=*
CORS_ALLOWED_METHODS=GET,POST,PUT,DELETE,OPTIONS
CORS_ALLOWED_HEADERS=*
```

## Development Setup

### Prerequisites
- PHP 8.3+
- Composer
- PostgreSQL 15+
- Redis 7+

### Installation

```bash
# Navigate to gateway service
cd services/gateway

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run database migrations
php artisan migrate

# Start the service
php artisan serve --port=8000
```

### Testing

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test suite
./vendor/bin/phpunit --testsuite=Feature

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage
```

## Architecture

### Request Flow
1. **Client Request** → Gateway receives HTTP request
2. **Authentication** → Validate JWT token if required
3. **Rate Limiting** → Check request limits
4. **Route Resolution** → Determine target service
5. **Request Transformation** → Modify request if needed
6. **Service Call** → Forward to target microservice
7. **Response Transformation** → Process service response
8. **Client Response** → Return formatted response

### Service Discovery
The gateway maintains a service registry with:
- Service URLs and health status
- Load balancing configuration
- Circuit breaker settings
- Timeout configurations

### Error Handling
- **Service Unavailable**: Return 503 with retry information
- **Authentication Failure**: Return 401 with error details
- **Rate Limit Exceeded**: Return 429 with retry-after header
- **Validation Errors**: Return 422 with validation details

## Integration with Other Services

### Auth Service
- Token validation for protected routes
- User authentication and authorization
- Session management

### All Microservices
- Request routing and load balancing
- Health check monitoring
- Circuit breaker protection

### Monitoring Services
- Request metrics collection
- Performance monitoring
- Error tracking and alerting

## Monitoring & Logging

### Metrics Collected
- Request count and response times
- Error rates by service
- Rate limiting statistics
- Service health status

### Log Formats
- Access logs with request/response details
- Error logs with stack traces
- Performance logs with timing information
- Security logs for authentication events

## Security Features

- **JWT Authentication**: Token-based authentication
- **Rate Limiting**: Prevent API abuse
- **CORS Protection**: Cross-origin request control
- **Input Validation**: Request data validation
- **Security Headers**: Standard security headers
- **IP Whitelisting**: Optional IP-based access control

## Performance Optimization

- **Connection Pooling**: Reuse HTTP connections
- **Response Caching**: Cache frequently requested data
- **Request Compression**: Gzip compression support
- **Keep-Alive**: Persistent connections
- **Load Balancing**: Distribute load across instances

## Deployment

### Docker
```bash
# Build image
docker build -t gateway:latest .

# Run container
docker run -p 8000:8000 gateway:latest
```

### Production Considerations
- Use environment-specific configuration
- Enable SSL/TLS termination
- Configure proper logging levels
- Set up health check endpoints
- Implement graceful shutdown

---

The Gateway service is the central nervous system of the Reverse Tender platform, ensuring reliable, secure, and performant access to all microservices.
