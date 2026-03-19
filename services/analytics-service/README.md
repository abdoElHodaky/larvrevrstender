# Analytics Service

## Overview

The Analytics Service is responsible for collecting, processing, and analyzing data across the Reverse Tender platform. It provides insights into user behavior, auction performance, bidding patterns, and overall platform metrics.

## Features

- **Real-time Analytics**: Track user interactions and auction activities in real-time
- **Performance Metrics**: Monitor auction success rates, bidding patterns, and user engagement
- **Data Aggregation**: Collect and aggregate data from multiple services
- **Reporting**: Generate comprehensive reports for business intelligence
- **Google Analytics Integration**: Connect with Google Analytics for web analytics

## API Endpoints

### Analytics Data Collection
- `POST /api/events` - Record analytics events
- `POST /api/metrics` - Submit custom metrics
- `GET /api/reports/{type}` - Generate reports

### Dashboard Data
- `GET /api/dashboard/overview` - Get platform overview metrics
- `GET /api/dashboard/auctions` - Get auction analytics
- `GET /api/dashboard/users` - Get user behavior analytics

## Configuration

### Environment Variables

Key configuration variables for the analytics service:

```bash
# Google Analytics Configuration
ANALYTICS_VIEW_ID=your_ga_view_id
GOOGLE_APPLICATION_CREDENTIALS=path/to/credentials.json

# Database Configuration
DB_CONNECTION=mysql
DB_DATABASE=analytics_service

# RPC Configuration
RPC_TIMEOUT=30
RPC_RETRY_ATTEMPTS=3
```

### RPC Integration

The analytics service communicates with other services via RPC:

- **Auth Service**: User authentication and authorization
- **User Service**: User profile and behavior data
- **Auction Service**: Auction performance metrics
- **Bidding Service**: Bidding pattern analysis
- **Order Service**: Transaction analytics
- **Payment Service**: Financial metrics

## Data Models

### Event Tracking
- User interactions (page views, clicks, searches)
- Auction events (creation, bidding, completion)
- System events (errors, performance metrics)

### Metrics Collection
- Real-time counters
- Time-series data
- Aggregated statistics
- Custom business metrics

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
   php artisan serve --port=8005
   ```

### Testing

```bash
# Run unit tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
```

## Deployment

The analytics service is containerized and deployed using Docker:

```bash
# Build container
docker build -t analytics-service .

# Run container
docker run -p 8005:8000 analytics-service
```

## Monitoring

- **Health Check**: `GET /health`
- **Metrics Endpoint**: `GET /metrics`
- **Logs**: Structured logging with correlation IDs

## Dependencies

- **Laravel Framework**: ^12.0
- **Google Analytics**: For web analytics integration
- **Redis**: For caching and real-time data
- **MySQL**: Primary data storage

## Contributing

1. Follow PSR-12 coding standards
2. Write comprehensive tests for new features
3. Update documentation for API changes
4. Use semantic commit messages

## Support

For issues and questions related to the analytics service, please contact the development team or create an issue in the project repository.
