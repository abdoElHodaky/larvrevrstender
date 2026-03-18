# 📚 Reverse Tender Platform Documentation

Welcome to the comprehensive documentation for the Reverse Tender Platform - a modern microservices-based auction and bidding system built with PHP 8.3 and Laravel 12.

## 📖 Documentation Structure

### 🏗️ [Architecture Documentation](./architecture/)
- [System Overview](./architecture/system-overview.md)
- [Service Architecture](./architecture/service-architecture.md)
- [RPC Communication](./architecture/rpc-communication.md)
- [Database Design](./architecture/database-design.md)
- [Security Architecture](./architecture/security-architecture.md)

### 🚀 [Setup & Deployment](./setup/)
- [Quick Start Guide](./setup/quick-start.md)
- [Development Environment](./setup/development-environment.md)
- [Production Deployment](./setup/production-deployment.md)
- [Environment Configuration](./setup/environment-configuration.md)
- [Docker Setup](./setup/docker-setup.md)

### 🔄 [Refactoring Documentation](./refactoring/)
- [Refactoring Overview](./refactoring/overview.md)
- [Gateway Service Refactoring](./refactoring/gateway-service-refactoring.md)
- [Auth Service Delegation](./refactoring/auth-service-delegation.md)
- [Database Failover Implementation](./refactoring/database-failover.md)
- [RPC Migration Guide](./refactoring/rpc-migration.md)

### 📊 [Architecture Diagrams](./diagrams/)
- [System Architecture Diagram](./diagrams/system-architecture.md)
- [Service Communication Flow](./diagrams/service-communication.md)
- [Database Schema Diagrams](./diagrams/database-schema.md)
- [Refactoring Flow Diagrams](./diagrams/refactoring-flows.md)

## 🎯 Quick Navigation

### For Developers
- **New to the project?** Start with [Quick Start Guide](./setup/quick-start.md)
- **Setting up development?** See [Development Environment](./setup/development-environment.md)
- **Understanding the architecture?** Check [System Overview](./architecture/system-overview.md)

### For DevOps/Infrastructure
- **Deploying to production?** See [Production Deployment](./setup/production-deployment.md)
- **Configuring environments?** Check [Environment Configuration](./setup/environment-configuration.md)
- **Understanding service communication?** See [RPC Communication](./architecture/rpc-communication.md)

### For Architects/Technical Leads
- **Understanding refactoring decisions?** See [Refactoring Overview](./refactoring/overview.md)
- **Service architecture details?** Check [Service Architecture](./architecture/service-architecture.md)
- **Security considerations?** See [Security Architecture](./architecture/security-architecture.md)

## 🏢 System Overview

The Reverse Tender Platform is a comprehensive microservices ecosystem consisting of:

### Core Services (11 Services)
- **Auth Service** - Authentication and authorization
- **User Service** - User management and profiles
- **Gateway Service** - API gateway and routing
- **Auction Service** - Auction management
- **Bidding Service** - Bidding logic and processing
- **Order Service** - Order processing and management
- **Payment Service** - Payment processing with ZATCA integration
- **Notification Service** - Multi-channel notifications
- **Analytics Service** - Data analytics and reporting
- **VIN OCR Service** - Vehicle identification processing
- **Shared Library** - Common utilities and RPC clients

### Key Features
- ✅ **Modern PHP 8.3 & Laravel 12** implementation
- ✅ **Comprehensive RPC communication** between services
- ✅ **Database failover and resilience** mechanisms
- ✅ **Token-based authentication** with Laravel Sanctum
- ✅ **Real-time notifications** and event processing
- ✅ **Payment integration** with Stripe, PayPal, and ZATCA
- ✅ **Comprehensive testing** infrastructure
- ✅ **Docker containerization** support

## 📈 Current Status

### ✅ Completed
- Core service architecture implementation
- RPC communication infrastructure
- Database failover mechanisms
- Authentication and authorization system
- Payment processing integration
- Testing infrastructure setup

### 🔄 In Progress
- Documentation completion
- Production deployment optimization
- Performance monitoring enhancement
- Additional test coverage

### 📋 Planned
- Advanced analytics features
- Mobile API optimization
- Enhanced security features
- Scalability improvements

## 🤝 Contributing

Please refer to our development documentation for contribution guidelines:
- [Development Environment Setup](./setup/development-environment.md)
- [Code Standards and Guidelines](./setup/code-standards.md)
- [Testing Guidelines](./setup/testing-guidelines.md)

## 📞 Support

For technical support and questions:
- **Architecture Questions**: See [Architecture Documentation](./architecture/)
- **Setup Issues**: Check [Setup Documentation](./setup/)
- **Deployment Problems**: Refer to [Production Deployment](./setup/production-deployment.md)

---

**Last Updated**: March 2026  
**Version**: 2.0  
**Maintained by**: Reverse Tender Development Team

