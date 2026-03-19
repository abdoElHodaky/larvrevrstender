# Auth Service

## Overview

The Auth Service is the central authentication and authorization component of the Reverse Tender platform. It handles user authentication, JWT token management, SMS verification, and provides secure access control for all platform services.

## Features

- **JWT Authentication**: Secure token-based authentication system
- **SMS Verification**: Two-factor authentication via SMS (Twilio integration)
- **User Management**: User registration, login, and profile management
- **Token Management**: JWT token generation, validation, and refresh
- **Multi-Service Integration**: Centralized authentication for all microservices
- **Session Management**: Secure session handling with Redis

## API Endpoints

### Authentication
- `POST /api/auth/register` - User registration
- `POST /api/auth/login` - User login
- `POST /api/auth/logout` - User logout
- `POST /api/auth/refresh` - Refresh JWT token
- `POST /api/auth/verify-email` - Email verification

### SMS Verification
- `POST /api/auth/send-sms` - Send SMS verification code
- `POST /api/auth/verify-sms` - Verify SMS code
- `POST /api/auth/resend-sms` - Resend SMS verification

### Password Management
- `POST /api/auth/forgot-password` - Request password reset
- `POST /api/auth/reset-password` - Reset password with token
- `POST /api/auth/change-password` - Change password (authenticated)

### User Profile
- `GET /api/auth/profile` - Get user profile
- `PUT /api/auth/profile` - Update user profile
- `DELETE /api/auth/account` - Delete user account

## Configuration

### Environment Variables

```bash
# Application Configuration
APP_NAME="Auth Service"
APP_URL=http://localhost:8000

# Database Configuration
DB_CONNECTION=mysql
DB_DATABASE=auth_service

# JWT Configuration
JWT_SECRET=your_jwt_secret_key
JWT_TTL=60
JWT_REFRESH_TTL=20160
JWT_ALGO=HS256

# SMS Configuration (Twilio/Unifonic)
SMS_PROVIDER=twilio
TWILIO_SID=your_twilio_sid
TWILIO_TOKEN=your_twilio_token
TWILIO_FROM=your_twilio_phone_number

# Sanctum Configuration
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,127.0.0.1:8000,::1
SANCTUM_EXPIRATION=43200

# Service URLs
USER_SERVICE_URL=http://localhost:8001
NOTIFICATION_SERVICE_URL=http://localhost:8007
ANALYTICS_SERVICE_URL=http://localhost:8005
```

### RPC Integration

The auth service communicates with other services via RPC:

- **User Service**: User profile management and validation
- **Notification Service**: Authentication notifications and alerts
- **Analytics Service**: Authentication event tracking
- **All Services**: Token validation and user authorization

## Authentication Flow

### Registration Process

1. **User Registration**: User submits registration data
2. **Data Validation**: Validate user input and check for duplicates
3. **SMS Verification**: Send SMS verification code
4. **Code Verification**: User verifies SMS code
5. **Account Creation**: Create user account and profile
6. **JWT Generation**: Generate access and refresh tokens
7. **Welcome Notification**: Send welcome notification

### Login Process

1. **Credential Validation**: Validate username/email and password
2. **Two-Factor Authentication**: SMS verification (if enabled)
3. **JWT Generation**: Generate access and refresh tokens
4. **Session Creation**: Create secure session
5. **Analytics Tracking**: Track login event

### Token Management

```php
// JWT Token Structure
{
  "sub": "user_id",
  "iat": "issued_at",
  "exp": "expiration",
  "jti": "token_id",
  "user": {
    "id": "user_id",
    "email": "user@example.com",
    "role": "user_role"
  }
}
```

## Security Features

### Password Security

- **Bcrypt Hashing**: Secure password hashing with configurable rounds
- **Password Complexity**: Enforced password complexity requirements
- **Password History**: Prevent password reuse
- **Account Lockout**: Temporary lockout after failed attempts

### Token Security

- **JWT Signing**: Cryptographically signed tokens
- **Token Expiration**: Configurable token lifetimes
- **Token Blacklisting**: Revoke compromised tokens
- **Refresh Token Rotation**: Secure token refresh mechanism

### SMS Security

- **Rate Limiting**: Prevent SMS spam and abuse
- **Code Expiration**: Time-limited verification codes
- **Attempt Limiting**: Maximum verification attempts
- **Provider Failover**: Multiple SMS provider support

## Data Models

### User
- Basic user information (email, phone, name)
- Authentication credentials (password hash)
- Verification status (email verified, phone verified)
- Security settings (2FA enabled, login notifications)

### UserSession
- Active user sessions
- Device and location information
- Session expiration and management
- Security audit trail

### VerificationCode
- SMS and email verification codes
- Code expiration and attempt tracking
- Rate limiting and security controls

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
   php artisan jwt:secret
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

# Run authentication tests
php artisan test --group=auth

# Run SMS verification tests
php artisan test --group=sms

# Run JWT tests
php artisan test --group=jwt
```

### API Testing

```bash
# Test registration
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password123","phone":"+1234567890"}'

# Test login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password123"}'
```

## Deployment

### Docker Deployment

```bash
# Build container
docker build -t auth-service .

# Run container
docker run -p 8000:8000 auth-service
```

### Production Configuration

- **Database**: MySQL with connection pooling
- **Cache**: Redis for session and token management
- **SMS Provider**: Twilio for production SMS delivery
- **Monitoring**: Health checks and authentication metrics

## Performance Considerations

### Caching Strategy

- **Token Caching**: Cache valid tokens in Redis
- **User Session Caching**: Cache active sessions
- **Rate Limit Caching**: Cache rate limiting data
- **Verification Code Caching**: Cache SMS codes securely

### Database Optimization

- **Indexed Queries**: Optimized queries for user lookup
- **Connection Pooling**: Efficient database connections
- **Query Optimization**: Minimize database calls
- **Session Storage**: Redis-based session storage

## Security Best Practices

### Authentication Security

- **Secure Headers**: Implement security headers
- **CORS Configuration**: Proper cross-origin settings
- **Rate Limiting**: Prevent brute force attacks
- **Input Validation**: Comprehensive input sanitization

### Token Security

- **Token Rotation**: Regular token refresh
- **Secure Storage**: Secure token storage recommendations
- **Token Validation**: Comprehensive token validation
- **Blacklist Management**: Efficient token blacklisting

## Monitoring

- **Health Check**: `GET /health`
- **Metrics**: `GET /metrics`
- **Authentication Stats**: Login/registration statistics
- **Security Events**: Failed login attempts and security alerts

## Dependencies

- **Laravel Framework**: ^12.0
- **JWT Auth**: Token-based authentication
- **Laravel Sanctum**: API authentication
- **Twilio SDK**: SMS verification
- **Redis**: Session and cache management
- **MySQL**: User data storage

## Integration Guide

### Service Integration

```php
// Validate JWT token in other services
$token = $request->bearerToken();
$user = $authService->validateToken($token);

if (!$user) {
    return response()->json(['error' => 'Unauthorized'], 401);
}
```

### Middleware Integration

```php
// Use auth middleware in other services
Route::middleware('auth:api')->group(function () {
    Route::get('/protected', [Controller::class, 'method']);
});
```

## Contributing

1. Follow PSR-12 coding standards
2. Write comprehensive tests for authentication flows
3. Test security features thoroughly
4. Update documentation for API changes
5. Follow security best practices

## Support

For issues and questions related to the auth service, please contact the development team or create an issue in the project repository.

## Related Documentation

- [JWT Authentication Guide](./docs/jwt-authentication.md)
- [SMS Integration Guide](./docs/sms-integration.md)
- [Security Best Practices](./docs/security.md)
