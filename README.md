# 🚀 Reverse Tender Platform

A comprehensive microservices-based platform for reverse tendering with real-time bidding capabilities, built with Laravel/Lumen and modern technologies.

## 📋 Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Features](#features)
- [Services](#services)
- [Technology Stack](#technology-stack)
- [Installation](#installation)
- [Configuration](#configuration)
- [API Documentation](#api-documentation)
- [Real-time Features](#real-time-features)
- [Deployment](#deployment)
- [Development](#development)
- [Contributing](#contributing)

## 🎯 Overview

The Reverse Tender Platform is a modern, scalable solution for reverse tendering where customers post requirements and merchants bid to fulfill them. The platform features real-time bidding, comprehensive analytics, ZATCA compliance for Saudi Arabia, and advanced vehicle identification through OCR.

### Key Highlights

- **🏗️ Microservices Architecture**: 9 independent, scalable services
- **⚡ Real-time Bidding**: Laravel Reverb WebSocket integration
- **🏛️ ZATCA Compliance**: Saudi Arabia e-invoicing integration
- **📊 Advanced Analytics**: Business intelligence and reporting
- **🔍 VIN OCR**: Vehicle identification number processing
- **🔐 Multi-factor Authentication**: JWT + OAuth + OTP support
- **🌐 Multi-cloud Deployment**: DigitalOcean + Linode infrastructure

## 🏛️ Architecture

### Microservices Overview

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   API Gateway   │────│  Auth Service   │────│  User Service   │
│     :8000       │    │     :8001       │    │     :8003       │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│ Bidding Service │────│ Order Service   │────│Notification Svc │
│     :8002       │    │     :8004       │    │     :8005       │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│ Payment Service │────│Analytics Service│────│ VIN OCR Service │
│     :8006       │    │     :8007       │    │     :8008       │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### Technology Stack

- **Backend Framework**: Laravel 10.x / Lumen
- **Real-time**: Laravel Reverb (WebSocket)
- **Authentication**: Laravel Sanctum + JWT
- **Database**: MySQL 8.0 with Redis caching
- **Queue System**: Redis-based queues
- **File Storage**: S3-compatible storage
- **Containerization**: Docker & Docker Compose
- **Orchestration**: Kubernetes ready
- **Monitoring**: Laravel Telescope + Pulse

## ✨ Features

### 🔐 Authentication & Authorization
- Multi-factor authentication (Email + Phone + OTP)
- JWT token-based authentication
- OAuth integration (Google, Facebook, Apple)
- Role-based access control (Customer, Merchant, Admin)
- Session management and device tracking

### 📱 Real-time Bidding
- **Laravel Reverb Integration**: Native WebSocket support
- **Live Bid Updates**: Real-time bid notifications
- **Competition Tracking**: Live competition level indicators
- **Multi-channel Broadcasting**: Public, private, and presence channels
- **Bid Analytics**: Real-time bidding insights

### 📊 Analytics & Reporting
- **User Analytics**: Behavior tracking and insights
- **Business Metrics**: KPI monitoring and trends
- **Custom Reports**: PDF and Excel export
- **Real-time Dashboard**: Live metrics and statistics
- **Conversion Funnel**: User journey analysis

### 🏛️ ZATCA E-Invoicing (Saudi Arabia)
- **Invoice Generation**: Automatic numbering and QR codes
- **ZATCA API Integration**: Direct submission to government servers
- **Tax Compliance**: VAT calculation and reporting
- **Audit Trail**: Complete logging and status tracking
- **National ID Validation**: Saudi-specific validation

### 🔍 VIN OCR Processing
- **Image Processing**: Vehicle identification from images
- **OCR Engine**: Tesseract and Google Vision API support
- **VIN Validation**: Luhn algorithm validation
- **Processing History**: Complete audit trail
- **Batch Processing**: Multiple image processing

## 🛠️ Services

### 1. 🔐 Auth Service (Port 8001)
**Purpose**: User authentication and authorization
- User registration and login
- JWT token management
- OAuth integration
- Two-factor authentication
- Session management

**Key Endpoints**:
- `POST /api/v1/auth/register` - User registration
- `POST /api/v1/auth/login` - User login
- `GET /api/v1/auth/me` - Get user profile
- `POST /api/v1/auth/otp/send` - Send OTP

### 2. ⚡ Bidding Service (Port 8002)
**Purpose**: Real-time bidding with Laravel Reverb
- Real-time bid placement
- Competition tracking
- Bid management
- WebSocket connections

**Key Features**:
- Laravel Reverb WebSocket server
- Real-time bid notifications
- Competition level indicators
- Multi-channel broadcasting

### 3. 👥 User Service (Port 8003)
**Purpose**: User profile and preference management
- Customer profiles
- Merchant profiles
- Preference management
- Profile verification

### 4. 📦 Order Service (Port 8004)
**Purpose**: Order and requirement management
- Order creation and management
- Requirement specifications
- Order tracking
- Status management

### 5. 📢 Notification Service (Port 8005)
**Purpose**: Multi-channel notifications
- Push notifications (FCM, APNS)
- SMS notifications (Twilio)
- Email notifications (SendGrid)
- In-app notifications

### 6. 💳 Payment Service (Port 8006)
**Purpose**: Payment processing and ZATCA compliance
- Payment gateway integration
- ZATCA e-invoicing
- Tax calculations
- Invoice generation

### 7. 📊 Analytics Service (Port 8007)
**Purpose**: Business intelligence and reporting
- Event tracking
- User analytics
- Business metrics
- Custom reports

### 8. 🔍 VIN OCR Service (Port 8008)
**Purpose**: Vehicle identification processing
- Image upload and processing
- OCR text extraction
- VIN validation
- Vehicle information lookup

### 9. 🌐 API Gateway (Port 8000)
**Purpose**: Request routing and load balancing
- Service discovery
- Load balancing
- Rate limiting
- Request/response transformation

## 🚀 Installation

### Prerequisites

- Docker & Docker Compose
- PHP 8.1+
- Composer
- Node.js 18+ (for frontend)
- MySQL 8.0
- Redis 6.0+

### Quick Start

1. **Clone the repository**
```bash
git clone https://github.com/abdoElHodaky/larvrevrstender.git
cd larvrevrstender
```

2. **Environment Setup**
```bash
# Copy environment files
cp .env.example .env
cp services/auth-service/.env.example services/auth-service/.env
cp services/bidding-service/.env.example services/bidding-service/.env
# ... repeat for all services
```

3. **Install Dependencies**
```bash
# Install PHP dependencies for each service
cd services/auth-service && composer install
cd ../bidding-service && composer install
cd ../analytics-service && composer install
# ... repeat for all services
```

4. **Database Setup**
```bash
# Start infrastructure services
docker-compose -f deployment/docker/development/docker-compose.yml up -d mysql redis

# Run migrations for each service
cd services/auth-service && php artisan migrate --seed
cd ../user-service && php artisan migrate --seed
# ... repeat for all services
```

5. **Start Services**
```bash
# Development mode
docker-compose -f deployment/docker/development/docker-compose.yml up -d

# Or start individual services
cd services/auth-service && php artisan serve --port=8001
cd services/bidding-service && php artisan serve --port=8002
# ... repeat for all services
```

## ⚙️ Configuration

### Environment Variables

Each service has its own `.env` file with specific configurations:

#### Auth Service
```env
APP_NAME="Reverse Tender Auth Service"
DB_DATABASE=reverse_tender_auth
JWT_SECRET=your-jwt-secret
TWILIO_SID=your-twilio-sid
GOOGLE_CLIENT_ID=your-google-client-id
```

#### Bidding Service (Laravel Reverb)
```env
APP_NAME="Reverse Tender Bidding Service"
DB_DATABASE=reverse_tender_bidding
BROADCAST_DRIVER=reverb
REVERB_APP_ID=reverse-tender
REVERB_APP_KEY=reverse-tender-key
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
```

#### Payment Service (ZATCA)
```env
APP_NAME="Reverse Tender Payment Service"
DB_DATABASE=reverse_tender_payments
ZATCA_API_URL=https://api.zatca.gov.sa
ZATCA_API_KEY=your-zatca-api-key
ZATCA_CERTIFICATE_PATH=/path/to/certificate.pem
```

### Service Discovery

Services communicate through environment-defined URLs:

```env
API_GATEWAY_URL=http://localhost:8000
AUTH_SERVICE_URL=http://localhost:8001
BIDDING_SERVICE_URL=http://localhost:8002
USER_SERVICE_URL=http://localhost:8003
```

## 📚 API Documentation

### Authentication

All API requests (except public endpoints) require authentication:

```bash
# Login to get token
curl -X POST http://localhost:8001/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "password"}'

# Use token in subsequent requests
curl -X GET http://localhost:8001/api/v1/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Real-time Bidding

Connect to Laravel Reverb WebSocket:

```javascript
// Frontend JavaScript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: 'reverse-tender-key',
    wsHost: 'localhost',
    wsPort: 8080,
    wssPort: 8080,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
});

// Listen for bid events
Echo.channel('bidding.order.123')
    .listen('BidPlaced', (e) => {
        console.log('New bid placed:', e.bid);
    })
    .listen('BidUpdated', (e) => {
        console.log('Bid updated:', e.bid);
    })
    .listen('BidAwarded', (e) => {
        console.log('Bid awarded:', e.bid);
    });
```

### Service Endpoints

#### Auth Service (8001)
- `POST /api/v1/auth/register` - User registration
- `POST /api/v1/auth/login` - User login
- `GET /api/v1/auth/me` - Get user profile
- `POST /api/v1/auth/otp/send` - Send OTP
- `POST /api/v1/auth/social/{provider}/login` - Social login

#### Bidding Service (8002)
- `GET /api/v1/bids` - Get bids
- `POST /api/v1/bids` - Place bid
- `PUT /api/v1/bids/{id}` - Update bid
- `DELETE /api/v1/bids/{id}` - Cancel bid

#### Analytics Service (8007)
- `POST /api/v1/analytics/events` - Track event
- `GET /api/v1/analytics/dashboard` - Dashboard data
- `GET /api/v1/analytics/metrics` - Business metrics
- `POST /api/v1/analytics/reports` - Generate report

#### VIN OCR Service (8008)
- `POST /api/v1/vin/upload` - Upload VIN image
- `GET /api/v1/vin/process/{id}` - Get processing status
- `GET /api/v1/vin/history` - Processing history

## ⚡ Real-time Features

### Laravel Reverb Integration

The platform uses Laravel Reverb for real-time features:

#### Bidding Events
- **BidPlaced**: Notifies when a new bid is placed
- **BidUpdated**: Notifies when a bid is modified
- **BidAwarded**: Notifies when a bid wins

#### Channel Types
- **Public Channels**: `bidding.order.{orderId}` - Open bidding updates
- **Private Channels**: `private-user.{userId}` - User-specific notifications
- **Presence Channels**: `presence-bidding.{orderId}` - Active bidders

#### Configuration
```php
// config/broadcasting.php
'reverb' => [
    'driver' => 'reverb',
    'key' => env('REVERB_APP_KEY'),
    'secret' => env('REVERB_APP_SECRET'),
    'app_id' => env('REVERB_APP_ID'),
    'options' => [
        'host' => env('REVERB_HOST', '127.0.0.1'),
        'port' => env('REVERB_PORT', 8080),
        'scheme' => env('REVERB_SCHEME', 'http'),
    ],
],
```

## 🚀 Deployment

### Development Environment

```bash
# Start all services in development mode
docker-compose -f deployment/docker/development/docker-compose.yml up -d
```

### Production Environment

```bash
# Deploy to production
docker-compose -f deployment/docker/production/docker-compose.yml up -d

# Or use Kubernetes
kubectl apply -f deployment/kubernetes/
```

### Multi-cloud Deployment

The platform supports deployment across multiple cloud providers:

- **Primary**: DigitalOcean (Application servers)
- **Secondary**: Linode (Database and storage)
- **CDN**: CloudFlare for global content delivery

### Environment-specific Configurations

#### Development
- Debug mode enabled
- Local database and Redis
- Detailed logging
- Development tools (Telescope, DebugBar)

#### Staging
- Production-like environment
- Staging database
- Monitoring enabled
- Rate limiting active

#### Production
- Optimized performance
- Production database with replication
- Enhanced security
- Comprehensive monitoring

## 👨‍💻 Development

### Project Structure

```
larvrevrstender/
├── services/                    # Microservices
│   ├── auth-service/           # Authentication service
│   ├── bidding-service/        # Real-time bidding
│   ├── user-service/           # User management
│   ├── order-service/          # Order management
│   ├── notification-service/   # Notifications
│   ├── payment-service/        # Payments & ZATCA
│   ├── analytics-service/      # Analytics & reporting
│   ├── vin-ocr-service/       # VIN OCR processing
│   └── api-gateway/           # API gateway
├── deployment/                 # Deployment configurations
│   ├── docker/                # Docker configurations
│   ├── kubernetes/            # Kubernetes manifests
│   └── terraform/             # Infrastructure as code
├── docs/                      # Documentation
├── frontend/                  # Frontend applications
└── shared/                    # Shared libraries
```

### Service Structure (Laravel/Lumen Compatible)

Each service follows standard Laravel/Lumen structure:

```
service-name/
├── app/
│   ├── Http/Controllers/      # API controllers
│   ├── Models/               # Eloquent models
│   ├── Services/             # Business logic
│   ├── Events/               # Laravel events
│   ├── Listeners/            # Event listeners
│   ├── Providers/            # Service providers
│   └── Middleware/           # Custom middleware
├── config/                   # Configuration files
├── database/
│   ├── migrations/           # Database migrations
│   ├── seeders/             # Database seeders
│   └── factories/           # Model factories
├── routes/
│   └── api.php              # API routes
├── tests/
│   ├── Feature/             # Feature tests
│   └── Unit/                # Unit tests
├── composer.json            # PHP dependencies
├── .env.example            # Environment template
└── README.md               # Service documentation
```

### Development Workflow

1. **Feature Development**
```bash
# Create feature branch
git checkout -b feature/new-feature

# Make changes to relevant services
cd services/auth-service
# ... make changes

# Run tests
composer test

# Commit changes
git add .
git commit -m "feat: add new feature"
```

2. **Testing**
```bash
# Run tests for specific service
cd services/auth-service
composer test

# Run all tests
./scripts/run-all-tests.sh
```

3. **Code Quality**
```bash
# Run code formatting
composer pint

# Run static analysis
composer analyse
```

### Adding New Services

1. **Create Service Structure**
```bash
mkdir services/new-service
cd services/new-service

# Copy structure from existing service
cp -r ../auth-service/* .

# Update composer.json and configurations
```

2. **Update Docker Compose**
```yaml
# Add to docker-compose.yml
new-service:
  image: reversetender/new-service:latest
  ports:
    - "8009:8009"
  environment:
    - APP_ENV=production
    - DB_DATABASE=reverse_tender_new
```

3. **Update API Gateway**
```php
// Add routing rules
Route::prefix('new')->group(function () {
    Route::any('{path?}', function ($path = '') {
        return app('gateway')->forward('new-service', $path);
    })->where('path', '.*');
});
```

## 🤝 Contributing

We welcome contributions! Please follow these guidelines:

### Development Setup

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

### Code Standards

- Follow PSR-12 coding standards
- Write comprehensive tests
- Document new features
- Use meaningful commit messages

### Pull Request Process

1. Update documentation if needed
2. Add tests for new features
3. Ensure CI/CD passes
4. Request review from maintainers

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- **Documentation**: [docs/](docs/)
- **Issues**: [GitHub Issues](https://github.com/abdoElHodaky/larvrevrstender/issues)
- **Discussions**: [GitHub Discussions](https://github.com/abdoElHodaky/larvrevrstender/discussions)

## 🙏 Acknowledgments

- Laravel Framework team for the excellent framework
- Laravel Reverb team for real-time capabilities
- ZATCA for e-invoicing standards
- All contributors and supporters

---

**Built with ❤️ for the Saudi Arabian market and beyond**

*Last updated: January 2024*

