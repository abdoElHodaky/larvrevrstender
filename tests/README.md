# Tests Directory

This directory will contain comprehensive test suites for the Reverse Tender Platform.

## Test Structure

### Backend Tests
- **Unit Tests**: Individual service component testing
- **Integration Tests**: Service-to-service communication testing  
- **API Tests**: Endpoint functionality and contract testing
- **Database Tests**: Migration and data integrity testing

### Frontend Tests
- **Unit Tests**: Vue component testing
- **Integration Tests**: Component interaction testing
- **E2E Tests**: Full user workflow testing
- **Performance Tests**: Lighthouse and load testing

### System Tests
- **Load Tests**: Artillery performance testing
- **Security Tests**: Vulnerability scanning
- **Contract Tests**: API contract validation
- **Smoke Tests**: Basic functionality verification

## Test Categories

### Phase 1: Core Service Tests
```
tests/
├── unit/
│   ├── auth-service/
│   ├── user-service/
│   └── order-service/
├── integration/
│   ├── api-gateway/
│   └── service-communication/
└── fixtures/
    └── test-data/
```

### Phase 2: Bidding System Tests
```
tests/
├── unit/
│   └── bidding-service/
├── integration/
│   └── websocket/
└── performance/
    └── real-time-load/
```

### Phase 3: Frontend Tests
```
tests/
├── frontend/
│   ├── pwa/
│   │   ├── unit/
│   │   ├── integration/
│   │   └── e2e/
│   └── admin/
│       ├── unit/
│       ├── integration/
│       └── e2e/
└── lighthouse/
    └── performance/
```

## Testing Tools

### Backend Testing
- **PHPUnit**: Unit and integration testing
- **Pest**: Modern PHP testing framework
- **Laravel Dusk**: Browser automation testing
- **Mockery**: Mocking framework

### Frontend Testing  
- **Vitest**: Unit testing for Vue components
- **Vue Test Utils**: Vue-specific testing utilities
- **Cypress**: End-to-end testing
- **Playwright**: Cross-browser testing

### Performance Testing
- **Artillery**: Load testing and performance
- **Lighthouse CI**: Performance auditing
- **K6**: Load testing scripts

### Security Testing
- **Trivy**: Vulnerability scanning
- **OWASP ZAP**: Security testing
- **Snyk**: Dependency vulnerability scanning

## Test Data Management

### Fixtures
- User accounts (customers, merchants, admins)
- Part categories and sample parts
- Sample bids and orders
- Test payment methods

### Database Seeding
- Automated test data generation
- Realistic data relationships
- Performance test datasets
- Edge case scenarios

## CI/CD Integration

Tests are automatically executed in the CI/CD pipeline:

1. **Code Quality**: PHPStan, ESLint, formatting checks
2. **Unit Tests**: Fast, isolated component tests
3. **Integration Tests**: Service interaction validation
4. **Security Scans**: Vulnerability detection
5. **Performance Tests**: Load and lighthouse testing
6. **E2E Tests**: Full user workflow validation

## Development Status

🚧 **Phase 0: Foundation Setup** (Current)
- Test directory structure planned
- CI/CD pipeline configured for testing
- Testing tools and frameworks identified

⏳ **Phase 1: Core Service Testing** (Next)
- Unit tests for auth, user, and order services
- Integration tests for API endpoints
- Database migration testing

⏳ **Phase 2: Bidding System Testing** (Upcoming)
- Real-time WebSocket testing
- Bidding logic validation
- Performance testing under load

⏳ **Phase 3: Frontend Testing** (Upcoming)
- Vue component unit tests
- User interface integration tests
- End-to-end workflow testing

## Running Tests

```bash
# Backend tests
composer test                    # Run all PHP tests
composer test:unit              # Unit tests only
composer test:integration       # Integration tests only

# Frontend tests  
npm run test                    # Run all frontend tests
npm run test:unit              # Unit tests only
npm run test:e2e               # End-to-end tests only

# Performance tests
npm run test:performance       # Load testing with Artillery
npm run test:lighthouse        # Performance auditing

# Security tests
composer security:scan         # PHP dependency scanning
npm audit                      # Node.js dependency scanning
```
