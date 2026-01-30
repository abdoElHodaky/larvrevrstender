# Services Directory

This directory will contain the microservices for the Reverse Tender Platform.

## Planned Services

Based on the implementation plan, the following services will be implemented:

- **api-gateway** (Port 8000) - Central entry point with rate limiting
- **auth-service** (Port 8001) - JWT + OAuth + OTP verification  
- **bidding-service** (Port 8002) - Real-time auctions with WebSocket
- **user-service** (Port 8003) - Customer + merchant management
- **order-service** (Port 8004) - Request management + processing
- **notification-service** (Port 8005) - Push + SMS + email notifications
- **payment-service** (Port 8006) - Payment integration
- **analytics-service** (Port 8007) - Reports + business intelligence

## Development Status

🚧 **Phase 0: Foundation Setup** (Current)
- Docker orchestration complete
- Database architecture ready
- CI/CD pipeline configured

⏳ **Phase 1: Core Services** (Next)
- Auth Service implementation
- User Service development  
- Order Service foundation
- API endpoint development

## Getting Started

Each service will be a Laravel application with its own:
- `composer.json` for dependencies
- `Dockerfile` for containerization
- Database migrations
- API documentation
- Unit and integration tests

Services will communicate via HTTP APIs and share data through the centralized database with service-specific schemas.
