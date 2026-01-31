# 🔐 Modern Authentication & Authorization Flow

> **🚀 Laravel 12+ DDD Architecture** | **🛡️ Zero Trust Security** | **⚡ Multi-Factor Authentication**

## 🎯 Authentication Overview

This diagram showcases our **modern authentication system** implementing **Domain-Driven Design**, **JWT with RS256**, **Multi-Factor Authentication**, and **Zero Trust Security** principles.

## 🔐 Complete Authentication Flow

```mermaid
%%{init: {
  'theme': 'dark',
  'themeVariables': {
    'primaryColor': '#ff6b6b',
    'primaryTextColor': '#ffffff',
    'primaryBorderColor': '#ff6b6b',
    'lineColor': '#4ecdc4',
    'secondaryColor': '#4ecdc4',
    'tertiaryColor': '#45b7d1',
    'background': '#1a1a2e',
    'mainBkg': '#16213e',
    'secondBkg': '#0f3460',
    'tertiaryBkg': '#533483',
    'actorBkg': '#ff6b6b',
    'actorBorder': '#ffffff',
    'actorTextColor': '#ffffff',
    'activationBkgColor': '#4ecdc4',
    'activationBorderColor': '#ffffff'
  }
}}%%

sequenceDiagram
    participant Client as 🚀 Client App<br/>Vue.js 3 + PWA
    participant Gateway as 🚪 API Gateway<br/>Laravel 12+ DDD
    participant Auth as 🔐 Auth Service<br/>Domain Layer
    participant User as 👥 User Service<br/>Profile Domain
    participant SMS as 📱 SMS Gateway<br/>Twilio + AWS SNS
    participant Email as 📧 Email Service<br/>SendGrid + SES
    participant Redis as ⚡ Redis Cluster<br/>Cache + Sessions
    participant DB as 🗃️ MySQL 8.0<br/>Primary Database
    participant Biometric as 🔒 Biometric API<br/>Touch/Face ID
    
    rect rgb(255, 107, 107, 0.1)
        Note over Client,DB: 🚀 MODERN REGISTRATION FLOW
        
        Client->>+Gateway: 🔐 POST /api/v1/auth/register<br/>📱 {email, phone, password, biometric_key}
        Gateway->>Gateway: 🛡️ Rate Limiting Check<br/>🔍 Input Validation & Sanitization
        Gateway->>+Auth: 🎯 Domain Command: RegisterUser
        
        Auth->>Auth: 🏗️ Domain Validation<br/>📧 Email ValueObject<br/>📱 Phone ValueObject
        Auth->>+DB: 🔍 Repository: CheckUserExists<br/>📊 Unique Constraint Validation
        
        alt 🚫 User Already Exists
            DB-->>-Auth: ✅ User Found
            Auth->>Auth: 🎭 Domain Exception: UserAlreadyExists
            Auth-->>-Gateway: ❌ 409 Conflict<br/>📝 {error: "User already registered"}
            Gateway-->>-Client: 🚫 Registration Failed
        else 🆕 New User Registration
            DB-->>-Auth: ✅ User Available
            Auth->>+DB: 💾 Create User Entity<br/>🔐 Status: PENDING_VERIFICATION
            DB-->>-Auth: ✅ User Created
            
            par 📱 Multi-Channel Verification
                Auth->>+SMS: 📱 Send OTP<br/>🔢 6-digit code + 5min expiry
                SMS-->>-Auth: ✅ SMS Sent
            and 📧 Email Verification
                Auth->>+Email: 📧 Send Verification Link<br/>🔗 JWT-signed token + 24h expiry
                Email-->>-Auth: ✅ Email Sent
            and ⚡ Cache OTP
                Auth->>+Redis: 💾 Store OTP<br/>🔑 Key: user_id:otp<br/>⏰ TTL: 300 seconds
                Redis-->>-Auth: ✅ OTP Cached
            end
            
            Auth->>Auth: 📊 Domain Event: UserRegistered
            Auth-->>-Gateway: ✅ 201 Created<br/>📝 {status: "pending_verification"}
            Gateway-->>-Client: 🎉 Registration Successful<br/>📱 Please verify OTP
        end
    end
    
    rect rgb(76, 205, 196, 0.1)
        Note over Client,DB: 🔐 ADVANCED OTP VERIFICATION
        
        Client->>+Gateway: 🔢 POST /api/v1/auth/verify-otp<br/>📱 {user_id, otp_code, device_fingerprint}
        Gateway->>+Auth: 🎯 Domain Command: VerifyOTP
        
        Auth->>+Redis: 🔍 Validate OTP<br/>🔑 Get cached OTP + attempts
        
        alt ❌ Invalid/Expired OTP
            Redis-->>-Auth: 🚫 OTP Not Found/Expired
            Auth->>+Redis: 📊 Increment Failed Attempts
            Redis-->>-Auth: ⚠️ Attempt Count Updated
            
            alt 🚨 Max Attempts Reached
                Auth->>Auth: 🔒 Domain Event: AccountLocked
                Auth->>+DB: 🔐 Lock User Account<br/>⏰ Temporary lockout
                DB-->>-Auth: ✅ Account Locked
                Auth-->>-Gateway: 🚫 429 Too Many Requests<br/>📝 {error: "Account temporarily locked"}
            else 🔄 Retry Available
                Auth-->>-Gateway: ❌ 400 Bad Request<br/>📝 {error: "Invalid OTP", attempts_left: X}
            end
            Gateway-->>-Client: 🚫 OTP Verification Failed
        else ✅ Valid OTP
            Redis-->>-Auth: ✅ OTP Valid
            
            Auth->>+DB: 🎉 Mark User as Verified<br/>📊 Status: ACTIVE<br/>⏰ email_verified_at: NOW()
            DB-->>-Auth: ✅ User Verified
            
            Auth->>Auth: 🎫 Generate JWT Token<br/>🔐 RS256 Algorithm<br/>📊 Claims: {user_id, role, verified: true}
            
            Auth->>+Redis: 💾 Store Session<br/>🔑 Key: session:user_id<br/>⏰ TTL: 24 hours
            Redis-->>-Auth: ✅ Session Stored
            
            Auth->>+Redis: 🗑️ Clear OTP Cache<br/>🧹 Cleanup verification data
            Redis-->>-Auth: ✅ OTP Cleared
            
            Auth->>Auth: 📊 Domain Event: UserVerified
            Auth-->>-Gateway: ✅ 200 OK<br/>🎫 {access_token, refresh_token, user_profile}
            Gateway-->>-Client: 🎉 Verification Successful<br/>🔐 User Authenticated
        end
    end
    
    rect rgb(69, 183, 209, 0.1)
        Note over Client,DB: 🚀 MODERN LOGIN FLOW
        
        Client->>+Gateway: 🔐 POST /api/v1/auth/login<br/>📱 {email, password, remember_me, device_info}
        Gateway->>Gateway: 🛡️ Security Checks<br/>🔍 Rate Limiting + Input Validation
        Gateway->>+Auth: 🎯 Domain Command: AuthenticateUser
        
        Auth->>+DB: 🔍 Repository: FindByCredentials<br/>📧 Email ValueObject lookup
        
        alt 🚫 Invalid Credentials
            DB-->>-Auth: ❌ User Not Found
            Auth->>Auth: 📊 Domain Event: LoginFailed
            Auth-->>-Gateway: ❌ 401 Unauthorized<br/>📝 {error: "Invalid credentials"}
            Gateway-->>-Client: 🚫 Login Failed
        else ✅ Valid Credentials
            DB-->>-Auth: ✅ User Found
            
            Auth->>Auth: 🔐 Password Verification<br/>🛡️ Bcrypt Hash Validation
            
            alt 🔐 2FA Required
                Auth->>+SMS: 📱 Send 2FA Code<br/>🔢 6-digit TOTP
                SMS-->>-Auth: ✅ 2FA Sent
                Auth-->>-Gateway: 🔐 202 Accepted<br/>📝 {requires_2fa: true, temp_token}
                Gateway-->>-Client: 🔐 2FA Required
                
                Client->>+Gateway: 🔢 POST /api/v1/auth/verify-2fa<br/>📱 {temp_token, totp_code}
                Gateway->>+Auth: 🎯 Verify 2FA Code
                Auth->>Auth: 🔐 TOTP Validation
                
                alt ✅ Valid 2FA
                    Auth->>Auth: 🎫 Generate Full JWT<br/>📊 Complete authentication
                    Auth-->>-Gateway: ✅ 200 OK<br/>🎫 {access_token, refresh_token}
                    Gateway-->>-Client: 🎉 Login Successful
                else ❌ Invalid 2FA
                    Auth-->>-Gateway: ❌ 401 Unauthorized
                    Gateway-->>-Client: 🚫 2FA Failed
                end
            else 🎫 Direct Login (No 2FA)
                Auth->>Auth: 🎫 Generate JWT Tokens<br/>🔐 Access + Refresh tokens
                
                Auth->>+Redis: 💾 Store Session<br/>🔑 Device fingerprint + location
                Redis-->>-Auth: ✅ Session Stored
                
                Auth->>+DB: 📊 Update Last Login<br/>⏰ Timestamp + IP + Device
                DB-->>-Auth: ✅ Login Recorded
                
                Auth->>Auth: 📊 Domain Event: UserLoggedIn
                Auth-->>-Gateway: ✅ 200 OK<br/>🎫 {access_token, refresh_token, user_profile}
                Gateway-->>-Client: 🎉 Login Successful
            end
        end
    end
    
    rect rgb(150, 206, 180, 0.1)
        Note over Client,DB: 🌐 MODERN OAUTH 2.0 FLOW
        
        Client->>+Gateway: 🌐 POST /api/v1/auth/oauth/google<br/>🔑 {oauth_token, provider, device_info}
        Gateway->>+Auth: 🎯 Domain Command: AuthenticateOAuth
        
        Auth->>Auth: 🔐 Validate OAuth Token<br/>🌐 Google/Apple/Microsoft API
        
        alt ❌ Invalid OAuth Token
            Auth-->>-Gateway: ❌ 401 Unauthorized<br/>📝 {error: "Invalid OAuth token"}
            Gateway-->>-Client: 🚫 OAuth Failed
        else ✅ Valid OAuth Token
            Auth->>+DB: 🔍 Repository: FindByOAuthProvider<br/>📧 Email from OAuth profile
            
            alt 👤 Existing OAuth User
                DB-->>-Auth: ✅ User Found
                Auth->>+DB: 📊 Update OAuth Token<br/>⏰ Last login timestamp
                DB-->>-Auth: ✅ Token Updated
                
                Auth->>Auth: 🎫 Generate JWT Tokens<br/>🔐 Full authentication
                Auth->>+Redis: 💾 Store Session<br/>🔑 OAuth session data
                Redis-->>-Auth: ✅ Session Stored
                
                Auth->>Auth: 📊 Domain Event: OAuthLoginSuccessful
                Auth-->>-Gateway: ✅ 200 OK<br/>🎫 {access_token, refresh_token, user_profile}
                Gateway-->>-Client: 🎉 OAuth Login Successful
            else 🆕 New OAuth User
                DB-->>-Auth: ❌ User Not Found
                Auth->>+DB: 💾 Create User from OAuth<br/>👤 Profile data from provider
                DB-->>-Auth: ✅ User Created
                
                Auth->>+DB: 🔗 Create OAuth Provider Record<br/>🔑 Provider + external_id
                DB-->>-Auth: ✅ OAuth Record Created
                
                Auth->>Auth: 🎫 Generate JWT Tokens<br/>🔐 New user authentication
                Auth->>+Redis: 💾 Store Session<br/>🔑 New user session
                Redis-->>-Auth: ✅ Session Stored
                
                Auth->>Auth: 📊 Domain Event: OAuthRegistrationSuccessful
                Auth-->>-Gateway: ✅ 201 Created<br/>🎫 {access_token, refresh_token, user_profile}
                Gateway-->>-Client: 🎉 OAuth Registration Successful
            end
        end
    end
    
    rect rgb(255, 159, 243, 0.1)
        Note over Client,DB: 🔒 BIOMETRIC AUTHENTICATION
        
        Client->>+Gateway: 🔒 POST /api/v1/auth/biometric<br/>📱 {biometric_token, device_id, biometric_type}
        Gateway->>+Auth: 🎯 Domain Command: AuthenticateBiometric
        
        Auth->>+Biometric: 🔒 Validate Biometric<br/>👆 Touch ID / 👁️ Face ID
        
        alt ✅ Biometric Valid
            Biometric-->>-Auth: ✅ Biometric Verified
            Auth->>+DB: 🔍 Repository: FindByBiometricToken<br/>🔑 Device-specific lookup
            DB-->>-Auth: ✅ User Found
            
            Auth->>Auth: 🎫 Generate JWT Tokens<br/>🔐 Biometric authentication
            Auth->>+Redis: 💾 Store Session<br/>🔑 Biometric session
            Redis-->>-Auth: ✅ Session Stored
            
            Auth->>Auth: 📊 Domain Event: BiometricLoginSuccessful
            Auth-->>-Gateway: ✅ 200 OK<br/>🎫 {access_token, refresh_token}
            Gateway-->>-Client: 🎉 Biometric Login Successful
        else ❌ Biometric Failed
            Biometric-->>-Auth: ❌ Biometric Failed
            Auth->>Auth: 📊 Domain Event: BiometricLoginFailed
            Auth-->>-Gateway: ❌ 401 Unauthorized<br/>📝 {error: "Biometric verification failed"}
            Gateway-->>-Client: 🚫 Biometric Failed
        end
    end
    
    rect rgb(254, 202, 87, 0.1)
        Note over Client,DB: 🔄 TOKEN REFRESH FLOW
        
        Client->>+Gateway: 🔄 POST /api/v1/auth/refresh<br/>🎫 {refresh_token, device_fingerprint}
        Gateway->>+Auth: 🎯 Domain Command: RefreshToken
        
        Auth->>+Redis: 🔍 Validate Refresh Token<br/>🔑 Check token validity + expiry
        
        alt ❌ Invalid/Expired Token
            Redis-->>-Auth: 🚫 Token Invalid/Expired
            Auth->>Auth: 📊 Domain Event: TokenRefreshFailed
            Auth-->>-Gateway: ❌ 401 Unauthorized<br/>📝 {error: "Token expired, please login"}
            Gateway-->>-Client: 🚫 Token Refresh Failed
        else ✅ Valid Refresh Token
            Redis-->>-Auth: ✅ Token Valid
            Auth->>+DB: 🔍 Check User Session Validity<br/>👤 User status + permissions
            DB-->>-Auth: ✅ Session Valid
            
            Auth->>Auth: 🎫 Generate New Access Token<br/>🔐 Rotate tokens for security
            Auth->>+Redis: 💾 Update Session<br/>⏰ Extend expiration
            Redis-->>-Auth: ✅ Session Updated
            
            Auth->>Auth: 📊 Domain Event: TokenRefreshed
            Auth-->>-Gateway: ✅ 200 OK<br/>🎫 {access_token, refresh_token}
            Gateway-->>-Client: 🎉 Token Refreshed
        end
    end
    
    rect rgb(255, 107, 107, 0.1)
        Note over Client,DB: 🔑 PASSWORD RESET FLOW
        
        Client->>+Gateway: 🔑 POST /api/v1/auth/forgot-password<br/>📧 {email_or_phone}
        Gateway->>+Auth: 🎯 Domain Command: InitiatePasswordReset
        
        Auth->>+DB: 🔍 Repository: FindByEmailOrPhone<br/>📧 User lookup
        
        alt 🚫 User Not Found
            DB-->>-Auth: ❌ User Not Found
            Auth->>Auth: 📊 Domain Event: PasswordResetAttemptFailed
            Auth-->>-Gateway: ❌ 404 Not Found<br/>📝 {error: "User not found"}
            Gateway-->>-Client: 🚫 User Not Found
        else ✅ User Found
            DB-->>-Auth: ✅ User Found
            
            par 📱 Multi-Channel Reset
                Auth->>+SMS: 📱 Send Reset OTP<br/>🔢 6-digit code + 10min expiry
                SMS-->>-Auth: ✅ SMS Sent
            and 📧 Email Reset Link
                Auth->>+Email: 📧 Send Reset Link<br/>🔗 Secure reset URL + 1h expiry
                Email-->>-Auth: ✅ Email Sent
            and ⚡ Cache Reset Token
                Auth->>+Redis: 💾 Store Reset Token<br/>🔑 Key: reset:user_id<br/>⏰ TTL: 600 seconds
                Redis-->>-Auth: ✅ Reset Token Cached
            end
            
            Auth->>Auth: 📊 Domain Event: PasswordResetInitiated
            Auth-->>-Gateway: ✅ 200 OK<br/>📝 {message: "Reset instructions sent"}
            Gateway-->>-Client: 🎉 Reset Instructions Sent
            
            Client->>+Gateway: 🔑 POST /api/v1/auth/reset-password<br/>🔢 {reset_token, new_password, confirm_password}
            Gateway->>+Auth: 🎯 Domain Command: ResetPassword
            
            Auth->>+Redis: 🔍 Validate Reset Token<br/>🔑 Check token validity
            
            alt ❌ Invalid Reset Token
                Redis-->>-Auth: 🚫 Token Invalid/Expired
                Auth-->>-Gateway: ❌ 400 Bad Request<br/>📝 {error: "Invalid reset token"}
                Gateway-->>-Client: 🚫 Reset Failed
            else ✅ Valid Reset Token
                Redis-->>-Auth: ✅ Token Valid
                Auth->>+DB: 🔐 Update User Password<br/>🛡️ Hash new password
                DB-->>-Auth: ✅ Password Updated
                
                Auth->>+DB: 🗑️ Invalidate All User Sessions<br/>🔐 Force re-authentication
                DB-->>-Auth: ✅ Sessions Invalidated
                
                Auth->>+Redis: 🧹 Clear User Session Tokens<br/>🗑️ Remove cached sessions
                Redis-->>-Auth: ✅ Sessions Cleared
                
                Auth->>Auth: 📊 Domain Event: PasswordResetSuccessful
                Auth-->>-Gateway: ✅ 200 OK<br/>📝 {message: "Password reset successful"}
                Gateway-->>-Client: 🎉 Password Reset Successful
            end
        end
    end
    
    rect rgb(76, 205, 196, 0.1)
        Note over Client,DB: 🚪 LOGOUT & SESSION MANAGEMENT
        
        Client->>+Gateway: 🚪 POST /api/v1/auth/logout<br/>🎫 {access_token, logout_all_devices}
        Gateway->>+Auth: 🎯 Domain Command: LogoutUser
        
        Auth->>+Redis: 🗑️ Invalidate Session Token<br/>🔑 Remove from cache
        Redis-->>-Auth: ✅ Token Invalidated
        
        Auth->>+DB: 📊 Update Session Status<br/>🔐 Mark as logged out
        DB-->>-Auth: ✅ Session Updated
        
        alt 🔄 Logout All Devices
            Auth->>+DB: 🗑️ Invalidate All User Sessions<br/>🔐 All devices logged out
            DB-->>-Auth: ✅ All Sessions Invalidated
            Auth->>+Redis: 🧹 Clear All User Tokens<br/>🗑️ Remove all cached sessions
            Redis-->>-Auth: ✅ All Tokens Cleared
        end
        
        Auth->>Auth: 📊 Domain Event: UserLoggedOut
        Auth-->>-Gateway: ✅ 200 OK<br/>📝 {message: "Logout successful"}
        Gateway-->>-Client: 🎉 Logout Successful
    end
```

---

## 🚀 Modern Authentication Features

### **🔐 Multi-Factor Authentication (MFA)**
```yaml
🎯 Primary Authentication:
  - Email/Phone + Password (Required)
  - Biometric Authentication (Touch ID, Face ID)
  - Hardware Security Keys (FIDO2/WebAuthn)

📱 Secondary Verification:
  - SMS OTP (6-digit, 5min expiry)
  - Email Verification Links (24h expiry)
  - TOTP Apps (Google Authenticator, Authy)
  - Backup Codes (One-time use)

🌐 Social Authentication:
  - Google OAuth 2.0
  - Apple Sign In
  - Microsoft Azure AD
  - LinkedIn (Business accounts)
```

### **🎫 Advanced JWT Token Management**
```yaml
🔐 Token Types:
  - Access Token: 15 minutes (API access)
  - Refresh Token: 30 days (token renewal)
  - ID Token: User identity claims
  - Session Token: Redis-cached validation

🛡️ Security Features:
  - RS256 Algorithm with key rotation
  - Token fingerprinting per device
  - Automatic token refresh
  - Blacklist for compromised tokens
  - Concurrent session limits
```

### **🔒 Biometric & Device Security**
```yaml
📱 Biometric Support:
  - Touch ID (iOS/Android)
  - Face ID (iOS/Android)
  - Fingerprint (Android)
  - Voice Recognition (Future)

🖥️ Device Management:
  - Device fingerprinting
  - Trusted device registration
  - Suspicious login detection
  - Remote device logout
  - Device-specific tokens
```

### **🌐 OAuth 2.0 & OpenID Connect**
```yaml
🔗 Supported Providers:
  - Google (OAuth 2.0 + OpenID)
  - Apple (Sign in with Apple)
  - Microsoft (Azure AD)
  - LinkedIn (Professional)

🛡️ Security Features:
  - PKCE (Proof Key for Code Exchange)
  - State parameter validation
  - Nonce verification
  - Token introspection
  - Provider token validation
```

### **🛡️ Advanced Security Features**
```yaml
🔒 Zero Trust Security:
  - Every request authenticated
  - Principle of least privilege
  - Continuous verification
  - Context-aware access

🚨 Threat Protection:
  - Rate limiting (Redis-based)
  - Brute force protection
  - Account lockout policies
  - Suspicious activity detection
  - Geo-location validation

📊 Audit & Compliance:
  - Complete audit trail
  - GDPR compliance
  - ZATCA preparation
  - PCI DSS alignment
  - SOC 2 Type II ready
```

### **🇸🇦 Saudi Arabia Compliance**
```yaml
🏛️ Regulatory Compliance:
  - National ID integration
  - Absher API compatibility
  - ZATCA e-invoicing ready
  - SAMA banking regulations
  - CITC telecommunications compliance

📱 Local Requirements:
  - Arabic language support
  - Saudi phone number validation
  - Hijri calendar support
  - Prayer time considerations
  - Cultural sensitivity
```

---

## 🏗️ Domain-Driven Design Implementation

### **🎯 Domain Layer**
```php
// Domain Entities
Domain/Auth/Models/User.php
Domain/Auth/ValueObjects/UserId.php
Domain/Auth/ValueObjects/Email.php
Domain/Auth/Events/UserAuthenticated.php

// Domain Services
Domain/Auth/Services/AuthenticationService.php
Domain/Auth/Services/PasswordService.php
Domain/Auth/Services/TokenService.php
```

### **🔄 Application Layer**
```php
// Use Cases
Application/UseCases/RegisterUser.php
Application/UseCases/AuthenticateUser.php
Application/UseCases/RefreshToken.php

// DTOs
Application/DTOs/LoginRequest.php
Application/DTOs/RegisterRequest.php
Application/DTOs/AuthResponse.php
```

### **🏗️ Infrastructure Layer**
```php
// Repositories
Infrastructure/Database/UserRepository.php
Infrastructure/Database/SessionRepository.php

// External Services
Infrastructure/Http/OAuthProviders/GoogleProvider.php
Infrastructure/Http/OAuthProviders/AppleProvider.php
Infrastructure/Cache/RedisSessionStore.php
```

---

## 🎯 Performance & Scalability

### **⚡ Caching Strategy**
- **Redis Cluster**: Session storage and token caching
- **Application Cache**: User permissions and roles
- **CDN**: Static authentication assets
- **Database Query Cache**: Optimized user lookups

### **📈 Horizontal Scaling**
- **Stateless Authentication**: JWT-based, no server-side sessions
- **Load Balancing**: Multiple auth service instances
- **Database Sharding**: User data distribution
- **Microservice Architecture**: Independent scaling

### **🔍 Monitoring & Analytics**
- **Real-time Metrics**: Login success/failure rates
- **Security Analytics**: Threat detection and response
- **Performance Monitoring**: Response time optimization
- **User Behavior**: Authentication pattern analysis

---

## 🚀 Future Enhancements

### **🤖 AI-Powered Security**
- **Behavioral Biometrics**: Typing patterns, mouse movements
- **Risk-Based Authentication**: Dynamic MFA requirements
- **Fraud Detection**: ML-powered anomaly detection
- **Adaptive Security**: Context-aware authentication

### **🌐 Emerging Technologies**
- **WebAuthn/FIDO2**: Passwordless authentication
- **Blockchain Identity**: Decentralized identity verification
- **Quantum-Resistant Cryptography**: Future-proof security
- **Zero-Knowledge Proofs**: Privacy-preserving authentication

This modern authentication system provides enterprise-grade security, scalability, and compliance while delivering an exceptional user experience across all platforms and devices.
