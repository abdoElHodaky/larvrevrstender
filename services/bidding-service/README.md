# Bidding Service

## Overview

The Bidding Service manages real-time bidding operations for the Reverse Tender platform. It handles bid placement, validation, real-time updates, and implements the **Dual Controller Pattern** for optimal performance and cross-service orchestration.

## Features

- **Real-time Bidding**: Live bid placement and updates using WebSockets
- **Bid Validation**: Comprehensive bid validation and business rules
- **Pusher Integration**: Real-time notifications for bidding events
- **Dual Controller Architecture**: Separate controllers for direct operations and cross-service orchestration
- **WebSocket Support**: Real-time bidding updates and notifications

## Architecture

### Dual Controller Pattern

This service implements the Dual Controller Pattern with two distinct controller types:

#### Root Controllers (`app/Http/Controllers/`)
- **Purpose**: Direct bidding operations within service boundary
- **Use Cases**: Bid placement, bid history, performance-critical operations
- **Example**: `BiddingController.php`

#### API Controllers (`app/Http/Controllers/Api/`)
- **Purpose**: Cross-service orchestration using shared procedures
- **Use Cases**: Complex bidding workflows, auction validation, distributed operations
- **Example**: `Api/BiddingController.php`

For detailed information about this pattern, see [DUAL_CONTROLLER_PATTERN.md](../../DUAL_CONTROLLER_PATTERN.md).

## API Endpoints

### Direct Operations (Root Controllers)
- `POST /bids` - Place bid (direct)
- `GET /bids/{auctionId}` - Get auction bids
- `GET /bids/user/{userId}` - Get user bid history
- `PUT /bids/{id}` - Update bid (if allowed)
- `DELETE /bids/{id}` - Cancel bid (if allowed)

### Orchestrated Operations (API Controllers)
- `POST /api/v1/bids/place` - Place bid with full validation workflow
- `POST /api/v1/bids/{id}/validate` - Validate bid across services
- `GET /api/v1/bids/analytics` - Get bidding analytics

### Real-time Operations
- `GET /ws/bidding/{auctionId}` - WebSocket connection for real-time updates
- `POST /api/notifications/bid-placed` - Trigger bid notifications

## Configuration

### Environment Variables

```bash
# Application Configuration
APP_NAME="Bidding Service"
APP_URL=http://localhost:8002

# Database Configuration
DB_CONNECTION=mysql
DB_DATABASE=bidding_service

# Pusher Configuration for Real-time Bidding
PUSHER_APP_ID=your_pusher_app_id
PUSHER_APP_KEY=your_pusher_app_key
PUSHER_APP_SECRET=your_pusher_app_secret
PUSHER_HOST=your_pusher_host
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

# WebSocket Configuration
WEBSOCKET_HOST=127.0.0.1
WEBSOCKET_PORT=8080

# Service URLs
AUTH_SERVICE_URL=http://localhost:8000
USER_SERVICE_URL=http://localhost:8001
ORDER_SERVICE_URL=http://localhost:8003
PAYMENT_SERVICE_URL=http://localhost:8004
ANALYTICS_SERVICE_URL=http://localhost:8005
```

### RPC Integration

The bidding service communicates with other services via RPC:

- **Auction Service**: Auction validation and status updates
- **Auth Service**: User authentication and authorization
- **User Service**: User profile and bidding limits
- **Payment Service**: Payment validation and processing
- **Notification Service**: Bid notifications and alerts
- **Analytics Service**: Bidding pattern tracking

## Real-time Features

### Pusher Integration

The service uses Pusher for real-time bidding updates:

```javascript
// Client-side integration
const pusher = new Pusher('your_pusher_key', {
  cluster: 'mt1'
});

const channel = pusher.subscribe('auction.123');
channel.bind('bid.placed', function(data) {
  // Update UI with new bid
  updateBidDisplay(data.bid);
});
```

### WebSocket Events

- `bid.placed` - New bid placed on auction
- `bid.outbid` - User has been outbid
- `auction.ending` - Auction ending soon
- `auction.ended` - Auction has ended

## Data Models

### Bid
- Bid amount and timestamp
- User and auction associations
- Bid status (active, outbid, winning)
- Validation metadata

### BidHistory
- Complete bidding history for auctions
- Bid progression and patterns
- Analytics and reporting data

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
   php artisan serve --port=8002
   ```

5. **Start WebSocket Server**
   ```bash
   php artisan websockets:serve
   ```

### Testing

```bash
# Run all tests
php artisan test

# Run real-time tests
php artisan test --group=realtime

# Run bidding logic tests
php artisan test --group=bidding
```

### Real-time Testing

```bash
# Test WebSocket connections
php artisan test:websocket

# Test Pusher integration
php artisan test:pusher
```

## Deployment

### Docker Deployment

```bash
# Build container
docker build -t bidding-service .

# Run container with WebSocket support
docker run -p 8002:8000 -p 8080:8080 bidding-service
```

### Production Configuration

- **Database**: MySQL with optimized indexes for bid queries
- **Cache**: Redis for session management and bid caching
- **WebSockets**: Dedicated WebSocket server for real-time updates
- **Load Balancing**: Support for multiple instances with sticky sessions

## Performance Considerations

### Real-time Optimization

- **Connection Pooling**: Efficient WebSocket connection management
- **Message Queuing**: Asynchronous bid processing
- **Caching Strategy**: Redis caching for active auctions
- **Database Optimization**: Indexed queries for bid retrieval

### Scalability

- **Horizontal Scaling**: Support for multiple bidding service instances
- **Load Distribution**: Distribute WebSocket connections across servers
- **Queue Management**: Background processing for non-critical operations

## Security

### Bid Validation

- **Authentication**: Secure user authentication for bid placement
- **Authorization**: User permission validation
- **Rate Limiting**: Prevent bid spam and abuse
- **Input Validation**: Comprehensive bid data validation

### Real-time Security

- **WebSocket Authentication**: Secure WebSocket connections
- **Channel Authorization**: User-specific channel access
- **Message Encryption**: Encrypted real-time communications

## Monitoring

- **Health Check**: `GET /health`
- **Metrics**: `GET /metrics`
- **WebSocket Status**: `GET /ws/status`
- **Bid Analytics**: Real-time bidding statistics

## Dependencies

- **Laravel Framework**: ^12.0
- **Pusher PHP Server**: Real-time communication
- **Laravel WebSockets**: WebSocket server implementation
- **Redis**: Caching and session management
- **MySQL**: Primary database for bid storage

## Business Rules

### Bid Validation Rules

1. **Minimum Increment**: Bids must meet minimum increment requirements
2. **User Limits**: Users cannot exceed their bidding limits
3. **Auction Status**: Bids only accepted on active auctions
4. **Self-Bidding**: Users cannot bid on their own auctions
5. **Time Limits**: Bids must be placed before auction end time

### Real-time Rules

1. **Immediate Updates**: All connected users receive instant bid updates
2. **Outbid Notifications**: Users are immediately notified when outbid
3. **Auction Extensions**: Automatic auction extensions for last-minute bids
4. **Connection Recovery**: Automatic reconnection for dropped connections

## Contributing

1. Follow PSR-12 coding standards
2. Write comprehensive tests for bidding logic
3. Test real-time functionality thoroughly
4. Update documentation for API changes
5. Follow the Dual Controller Pattern for new endpoints

## Support

For issues and questions related to the bidding service, please contact the development team or create an issue in the project repository.

## Related Documentation

- [Dual Controller Pattern](../../DUAL_CONTROLLER_PATTERN.md)
- [Real-time Bidding Guide](./docs/realtime-bidding.md)
- [WebSocket API](./docs/websocket-api.md)
