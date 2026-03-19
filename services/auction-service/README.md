# Auction Service

## Overview

The Auction Service is the core component of the Reverse Tender platform, responsible for managing auction lifecycles, vehicle listings, and auction-related operations. It implements the **Dual Controller Pattern** for optimal performance and cross-service orchestration.

## Features

- **Auction Management**: Create, update, and manage auction listings
- **Vehicle Integration**: Handle vehicle data and VIN processing
- **Image Upload**: Support for multiple auction images
- **Real-time Updates**: Live auction status and bidding updates
- **Dual Controller Architecture**: Separate controllers for direct operations and cross-service orchestration

## Architecture

### Dual Controller Pattern

This service implements the Dual Controller Pattern with two distinct controller types:

#### Root Controllers (`app/Http/Controllers/`)
- **Purpose**: Direct service operations within auction service boundary
- **Use Cases**: CRUD operations, internal queries, performance-critical endpoints
- **Example**: `AuctionController.php`, `BiddingController.php`

#### API Controllers (`app/Http/Controllers/Api/`)
- **Purpose**: Cross-service orchestration using shared procedures
- **Use Cases**: Complex workflows, external API endpoints, distributed operations
- **Example**: `Api/AuctionController.php`

For detailed information about this pattern, see [DUAL_CONTROLLER_PATTERN.md](../../DUAL_CONTROLLER_PATTERN.md).

## API Endpoints

### Direct Operations (Root Controllers)
- `GET /auctions` - List auctions
- `POST /auctions` - Create auction (direct)
- `GET /auctions/{id}` - Get auction details
- `PUT /auctions/{id}` - Update auction
- `DELETE /auctions/{id}` - Delete auction

### Orchestrated Operations (API Controllers)
- `POST /api/v1/auctions/create` - Create auction with full workflow
- `POST /api/v1/auctions/{id}/complete` - Complete auction lifecycle
- `POST /api/v1/auctions/{id}/cancel` - Cancel auction with notifications

### Image Management
- `POST /auctions/{id}/images` - Upload auction images
- `DELETE /auctions/{id}/images/{imageId}` - Remove auction image

## Configuration

### Environment Variables

```bash
# Application Configuration
APP_NAME=AuctionService
APP_URL=http://localhost:8000

# Database Configuration (PostgreSQL/Neon)
DB_CONNECTION=pgsql
DB_HOST=your-neon-host.neon.tech
DB_DATABASE=auction_service_db

# Redis Configuration (Upstash)
REDIS_URL=redis://default:password@host:port
REDIS_HOST=your-upstash-host.upstash.io

# JWT Configuration
JWT_SECRET=your_jwt_secret_key
JWT_TTL=60

# Microservices URLs
BIDDING_SERVICE_URL=http://bidding-service:8000
NOTIFICATION_SERVICE_URL=http://notification-service:8000
AUTH_SERVICE_URL=http://auth-service:8000
```

### RPC Integration

The auction service communicates with other services via RPC:

- **Auth Service**: User authentication and authorization
- **User Service**: User profile validation
- **Bidding Service**: Bid management and validation
- **Notification Service**: Auction notifications
- **Order Service**: Order creation upon auction completion
- **Analytics Service**: Auction performance tracking

## Data Models

### Auction
- Basic auction information (title, description, pricing)
- Timing configuration (start time, duration, end time)
- Status management (draft, active, completed, cancelled)
- Vehicle association and metadata

### Vehicle
- VIN processing and validation
- Vehicle specifications and details
- Image gallery management
- Condition reports

## Development

### Local Setup

1. **Install Dependencies**
   ```bash
   composer install
   ```

2. **Environment Configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Database Setup**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

4. **Start Development Server**
   ```bash
   php artisan serve --port=8000
   ```

### Testing

```bash
# Run all tests
php artisan test

# Run specific test categories
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run tests with coverage
php artisan test --coverage
```

### Code Quality

```bash
# Run PHP CS Fixer
./vendor/bin/pint

# Run PHPStan analysis
./vendor/bin/phpstan analyse
```

## Deployment

### Docker Deployment

```bash
# Build container
docker build -t auction-service .

# Run container
docker run -p 8000:8000 auction-service
```

### Production Configuration

- **Database**: PostgreSQL (Neon) for production reliability
- **Cache**: Redis (Upstash) for session and cache management
- **File Storage**: AWS S3 for auction images
- **Monitoring**: Health checks and metrics endpoints

## Monitoring

- **Health Check**: `GET /health`
- **Metrics**: `GET /metrics`
- **Logs**: Structured logging with correlation IDs
- **Performance**: Database query monitoring and optimization

## Security

- **JWT Authentication**: Secure API access
- **Input Validation**: Comprehensive request validation
- **Rate Limiting**: API rate limiting and throttling
- **CORS**: Configured for cross-origin requests

## Dependencies

- **Laravel Framework**: ^12.0
- **PostgreSQL**: Primary database
- **Redis**: Caching and session management
- **JWT Auth**: Authentication system
- **Guzzle HTTP**: Service communication
- **Image Processing**: For auction image handling

## Performance Considerations

- **Database Indexing**: Optimized queries for auction searches
- **Caching Strategy**: Redis caching for frequently accessed data
- **Image Optimization**: Compressed and resized auction images
- **Query Optimization**: Efficient database queries and relationships

## Contributing

1. Follow PSR-12 coding standards
2. Write comprehensive tests for new features
3. Update documentation for API changes
4. Use semantic commit messages
5. Follow the Dual Controller Pattern for new endpoints

## Support

For issues and questions related to the auction service, please contact the development team or create an issue in the project repository.

## Related Documentation

- [Dual Controller Pattern](../../DUAL_CONTROLLER_PATTERN.md)
- [API Documentation](./docs/api.md)
- [Database Schema](./docs/database.md)
