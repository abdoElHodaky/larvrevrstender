# Developer Quick Start Guide

## Welcome to Reverse Tender! 🚀

This guide will get you up and running with our simplified microservices architecture in minutes.

## 🏗️ Architecture Overview

We use a **simplified naming convention** that makes development faster and more intuitive:

### Services (11 total)
```
services/
├── analytics     # Analytics & reporting
├── auctions      # Auction management  
├── auth          # Authentication & authorization
├── bidding       # Real-time bidding
├── gateway       # API gateway & routing
├── notifications # Push notifications & messaging
├── orders        # Order processing
├── payments      # Payment processing
├── users         # User management
├── vin-ocr       # VIN recognition & OCR
└── shared        # Shared libraries & utilities
```

## 🚀 Quick Setup (5 minutes)

### 1. Clone & Navigate
```bash
git clone https://github.com/abdoElHodaky/larvrevrstender.git
cd larvrevrstender
git checkout v2
```

### 2. Environment Setup
```bash
# Copy environment files for all services
find services -name ".env.example" -exec sh -c 'cp "$1" "${1%.example}"' _ {} \;

# Install dependencies (run from root)
./install-dependencies.sh
```

### 3. Database Setup
```bash
# Start databases
docker-compose -f docker-compose.database.yml up -d

# Run migrations for all services
./scripts/migrate_all_services.sh
```

### 4. Start Services
```bash
# Start all services
docker-compose up -d

# Or start individual services
cd services/auth && php artisan serve --port=8001
cd services/users && php artisan serve --port=8002
# ... etc
```

## 🔧 Configuration Made Simple

### Service URLs (Simplified)
```bash
# Old (verbose)
AUTH_SERVICE_RPC_URL=http://localhost:8001
USER_SERVICE_RPC_URL=http://localhost:8002

# New (simplified)
AUTH_URL=http://localhost:8001
USERS_URL=http://localhost:8002
```

### Authentication Tokens (Simplified)
```bash
# Old (verbose)
RPC_AUTH_SERVICE_TOKEN=your_token_here
RPC_USER_SERVICE_TOKEN=your_token_here

# New (simplified)
AUTH_TOKEN=your_token_here
USERS_TOKEN=your_token_here
```

## 🎯 Development Patterns

### Dual Controller Pattern
Each service uses a **simplified dual controller pattern**:

```php
// Direct operations (internal)
app/Http/Controllers/AuctionController.php

// API operations (cross-service)
app/Http/Controllers/AuctionApi.php
```

### API Endpoints (Simplified)
```bash
# Old (verbose)
POST /api/analytics/events/track
GET  /api/analytics/dashboard/overview

# New (simplified)
POST /events
GET  /dashboard
```

## 📚 Service-Specific Guides

### Core Services
- **[Auth Service](services/auth/README.md)** - JWT authentication, SMS verification
- **[Users Service](services/users/README.md)** - User management & profiles
- **[Auctions Service](services/auctions/README.md)** - Auction management
- **[Bidding Service](services/bidding/README.md)** - Real-time bidding with WebSockets

### Supporting Services
- **[Analytics Service](services/analytics/README.md)** - Data collection & reporting
- **[Payments Service](services/payments/README.md)** - Payment processing
- **[Orders Service](services/orders/README.md)** - Order management
- **[Notifications Service](services/notifications/README.md)** - Push notifications

## 🛠️ Common Development Tasks

### Adding a New Feature
1. **Choose the right service** based on domain responsibility
2. **Use the dual controller pattern** for internal vs API operations
3. **Follow simplified naming conventions**
4. **Update relevant documentation**

### Inter-Service Communication
```php
// Use simplified service names
$response = Http::withToken(env('USERS_TOKEN'))
    ->post(env('USERS_URL') . '/api/users', $userData);
```

### Testing
```bash
# Run tests for specific service
cd services/auth && php artisan test

# Run all service tests
./scripts/test_all_services.sh
```

## 🔍 Debugging & Monitoring

### Health Checks
```bash
# Check all services
curl http://localhost:8000/health

# Check specific service
curl http://localhost:8001/health  # Auth service
```

### Logs
```bash
# View service logs
docker-compose logs -f auth
docker-compose logs -f users

# View all logs
docker-compose logs -f
```

## 📖 Key Documentation

- **[Architecture Overview](ARCHITECTURE.md)** - System design & patterns
- **[Dual Controller Pattern](DUAL_CONTROLLER_PATTERN.md)** - Controller architecture
- **[Naming Simplification Guide](NAMING_SIMPLIFICATION_GUIDE.md)** - Naming conventions
- **[Migration Guide](MIGRATION_GUIDE.md)** - Upgrading from old naming

## 🚨 Common Gotchas

### ❌ Don't Do This
```bash
# Old verbose naming
RPC_AUTH_SERVICE_TOKEN=token
AUTH_SERVICE_RPC_URL=http://localhost:8001
```

### ✅ Do This Instead
```bash
# Simplified naming
AUTH_TOKEN=token
AUTH_URL=http://localhost:8001
```

### ❌ Don't Do This
```php
// Verbose controller paths
app/Http/Controllers/Api/AuctionController.php
```

### ✅ Do This Instead
```php
// Simplified controller naming
app/Http/Controllers/AuctionApi.php
```

## 🎓 Learning Path

### Week 1: Foundation
1. Set up development environment
2. Understand service architecture
3. Learn dual controller pattern
4. Practice with auth & users services

### Week 2: Core Features
1. Work with auctions & bidding
2. Implement payment flows
3. Add notification features
4. Practice inter-service communication

### Week 3: Advanced Topics
1. Analytics integration
2. Performance optimization
3. Error handling & monitoring
4. Testing strategies

## 🤝 Getting Help

### Documentation
- Check service-specific README files
- Review architecture documentation
- Look at existing code examples

### Team Support
- Ask questions in team chat
- Schedule pair programming sessions
- Request code reviews early and often

### Debugging
- Use simplified service names for easier debugging
- Check logs in Docker containers
- Validate environment configurations

## 🎉 You're Ready!

With our simplified naming conventions and clear architecture patterns, you should be productive within your first day. The system is designed to be intuitive and developer-friendly.

**Happy coding!** 🚀

---

*This guide reflects the v2 branch improvements with simplified naming conventions and enhanced developer experience.*

