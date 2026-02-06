# 🔌 Third-Party Integration Framework

## 🎯 **Overview**

The **Third-Party Integration Framework** provides standardized patterns for connecting with external services, featuring authentication strategies, rate limiting, circuit breaker protection, and secure webhook handling.

## 🏗️ **Complete Integration Architecture**

```mermaid
graph TB
    subgraph "Integration Management Layer"
        INT_PROC[🔌 Third-Party Integration Procedure<br/>• Service initialization<br/>• API call management<br/>• Webhook handling]
        INT_MANAGER[⚙️ Integration Manager<br/>• Service registry<br/>• Configuration management<br/>• Instance lifecycle]
    end

    subgraph "Base Integration Framework"
        BASE_INT[🏗️ Base Third-Party Integration<br/>• Common functionality<br/>• Authentication handling<br/>• Error management]
        
        subgraph "Authentication Strategies"
            BEARER_AUTH[🔑 Bearer Token Authentication<br/>• JWT tokens<br/>• API keys<br/>• OAuth2 tokens]
            API_KEY_AUTH[🗝️ API Key Authentication<br/>• Header-based keys<br/>• Query parameter keys<br/>• Custom header formats]
            OAUTH_AUTH[🔐 OAuth2 Authentication<br/>• Authorization code flow<br/>• Client credentials<br/>• Token refresh]
        end
        
        subgraph "Protection Mechanisms"
            RATE_LIMITER[⏳ Rate Limiting<br/>• Request throttling<br/>• Sliding window<br/>• Per-service limits]
            RETRY_LOGIC[🔄 Retry Logic<br/>• Exponential backoff<br/>• Configurable delays<br/>• Max retry limits]
            CB_PROTECTION[🛡️ Circuit Breaker<br/>• Request protection<br/>• Failure detection<br/>• Auto recovery]
        end
    end

    subgraph "Service Integrations"
        STRIPE_INT[💳 Stripe Integration<br/>• Payment processing<br/>• Customer management<br/>• Webhook handling]
        MAILGUN_INT[📧 Mailgun Integration<br/>• Email delivery<br/>• Template management<br/>• Delivery tracking]
        TWILIO_INT[📱 Twilio Integration<br/>• SMS delivery<br/>• Voice calls<br/>• Verification codes]
        AWS_INT[☁️ AWS Integration<br/>• S3 storage<br/>• SES email<br/>• CloudWatch metrics]
        CUSTOM_INT[🎯 Custom Integrations<br/>• Business-specific<br/>• Domain services<br/>• Legacy systems]
    end

    subgraph "External Services"
        STRIPE_API[💳 Stripe API<br/>api.stripe.com]
        MAILGUN_API[📧 Mailgun API<br/>api.mailgun.net]
        TWILIO_API[📱 Twilio API<br/>api.twilio.com]
        AWS_API[☁️ AWS Services<br/>Various endpoints]
        CUSTOM_API[🌐 Custom APIs<br/>External services]
    end

    subgraph "Event Handling"
        WEBHOOK_HANDLER[🎣 Webhook Handler<br/>• Signature verification<br/>• Event routing<br/>• Processing logic]
        SIG_VERIFIER[🔐 Signature Verifier<br/>• HMAC validation<br/>• Timestamp checking<br/>• Replay protection]
        EVENT_PROCESSOR[⚡ Event Processor<br/>• Event parsing<br/>• Business logic<br/>• Response handling]
    end

    subgraph "State Management"
        TOKEN_CACHE[(🔴 Token Cache<br/>Redis<br/>• Access tokens<br/>• Refresh tokens<br/>• Expiration tracking)]
        RATE_CACHE[(📊 Rate Limit Cache<br/>Redis<br/>• Request counters<br/>• Window tracking<br/>• Quota management)]
        CB_STATE[(🛡️ Circuit Breaker State<br/>Redis<br/>• Circuit status<br/>• Failure metrics<br/>• Recovery timers)]
    end

    %% Integration management connections
    INT_PROC --> INT_MANAGER
    INT_MANAGER --> BASE_INT

    %% Base integration to auth strategies
    BASE_INT --> BEARER_AUTH
    BASE_INT --> API_KEY_AUTH
    BASE_INT --> OAUTH_AUTH

    %% Base integration to protection mechanisms
    BASE_INT --> RATE_LIMITER
    BASE_INT --> RETRY_LOGIC
    BASE_INT --> CB_PROTECTION

    %% Base integration to service integrations
    BASE_INT --> STRIPE_INT
    BASE_INT --> MAILGUN_INT
    BASE_INT --> TWILIO_INT
    BASE_INT --> AWS_INT
    BASE_INT --> CUSTOM_INT

    %% Service integrations to external APIs
    STRIPE_INT --> STRIPE_API
    MAILGUN_INT --> MAILGUN_API
    TWILIO_INT --> TWILIO_API
    AWS_INT --> AWS_API
    CUSTOM_INT --> CUSTOM_API

    %% Webhook handling
    STRIPE_API -.->|Webhooks| WEBHOOK_HANDLER
    MAILGUN_API -.->|Webhooks| WEBHOOK_HANDLER
    TWILIO_API -.->|Webhooks| WEBHOOK_HANDLER
    
    WEBHOOK_HANDLER --> SIG_VERIFIER
    SIG_VERIFIER --> EVENT_PROCESSOR

    %% State management connections
    BEARER_AUTH --> TOKEN_CACHE
    API_KEY_AUTH --> TOKEN_CACHE
    OAUTH_AUTH --> TOKEN_CACHE
    
    RATE_LIMITER --> RATE_CACHE
    CB_PROTECTION --> CB_STATE

    %% Styling
    classDef managementStyle fill:#e8f5e8,stroke:#2e7d32,stroke-width:2px,color:#000
    classDef baseStyle fill:#e3f2fd,stroke:#1565c0,stroke-width:2px,color:#000
    classDef authStyle fill:#f3e5f5,stroke:#7b1fa2,stroke-width:2px,color:#000
    classDef protectionStyle fill:#ffebee,stroke:#c62828,stroke-width:2px,color:#000
    classDef serviceStyle fill:#fff8e1,stroke:#f9a825,stroke-width:2px,color:#000
    classDef externalStyle fill:#fce4ec,stroke:#ad1457,stroke-width:2px,color:#000
    classDef webhookStyle fill:#e0f2f1,stroke:#00695c,stroke-width:2px,color:#000
    classDef stateStyle fill:#f1f8e9,stroke:#33691e,stroke-width:2px,color:#000

    class INT_PROC,INT_MANAGER managementStyle
    class BASE_INT baseStyle
    class BEARER_AUTH,API_KEY_AUTH,OAUTH_AUTH authStyle
    class RATE_LIMITER,RETRY_LOGIC,CB_PROTECTION protectionStyle
    class STRIPE_INT,MAILGUN_INT,TWILIO_INT,AWS_INT,CUSTOM_INT serviceStyle
    class STRIPE_API,MAILGUN_API,TWILIO_API,AWS_API,CUSTOM_API externalStyle
    class WEBHOOK_HANDLER,SIG_VERIFIER,EVENT_PROCESSOR webhookStyle
    class TOKEN_CACHE,RATE_CACHE,CB_STATE stateStyle
```

## 🔐 **Authentication Strategies**

### **Authentication Flow Diagram**

```mermaid
sequenceDiagram
    participant App
    participant Integration
    participant TokenCache
    participant ExternalAPI
    participant AuthServer

    App->>Integration: Initialize Service
    Integration->>TokenCache: Check Cached Token
    
    alt Token Exists and Valid
        TokenCache-->>Integration: Return Valid Token
        Integration-->>App: Ready for API Calls
    else Token Missing or Expired
        Integration->>AuthServer: Authenticate
        
        alt Bearer Token Auth
            AuthServer-->>Integration: Return Access Token
        else API Key Auth
            Note over Integration: Use Configured API Key
        else OAuth2 Auth
            AuthServer-->>Integration: Return Access + Refresh Token
        end
        
        Integration->>TokenCache: Store Token with TTL
        Integration-->>App: Ready for API Calls
    end
    
    App->>Integration: Make API Call
    Integration->>TokenCache: Get Current Token
    TokenCache-->>Integration: Return Token
    Integration->>ExternalAPI: API Request with Auth
    
    alt Request Success
        ExternalAPI-->>Integration: Success Response
        Integration-->>App: Return Response
    else Auth Error (401)
        ExternalAPI-->>Integration: 401 Unauthorized
        Integration->>AuthServer: Re-authenticate
        AuthServer-->>Integration: New Token
        Integration->>TokenCache: Update Token
        Integration->>ExternalAPI: Retry with New Token
        ExternalAPI-->>Integration: Success Response
        Integration-->>App: Return Response
    end
```

### **Authentication Strategy Details**

```mermaid
graph TB
    subgraph "Bearer Token Authentication"
        BEARER_START[🔑 Bearer Token Strategy]
        BEARER_CONFIG[📋 Configuration<br/>• Token endpoint<br/>• Client credentials<br/>• Scope requirements]
        BEARER_REQUEST[📤 Token Request<br/>• POST to auth endpoint<br/>• Client credentials<br/>• Grant type]
        BEARER_RESPONSE[📥 Token Response<br/>• Access token<br/>• Token type<br/>• Expires in]
        BEARER_USAGE[🔗 Token Usage<br/>• Authorization header<br/>• Bearer prefix<br/>• Request signing]
    end
    
    subgraph "API Key Authentication"
        API_KEY_START[🗝️ API Key Strategy]
        API_KEY_CONFIG[📋 Configuration<br/>• API key value<br/>• Header name<br/>• Key format]
        API_KEY_STORAGE[💾 Secure Storage<br/>• Environment variables<br/>• Encrypted config<br/>• Key rotation]
        API_KEY_USAGE[🔗 Key Usage<br/>• Custom headers<br/>• Query parameters<br/>• Request signing]
    end
    
    subgraph "OAuth2 Authentication"
        OAUTH_START[🔐 OAuth2 Strategy]
        OAUTH_CONFIG[📋 Configuration<br/>• Client ID/Secret<br/>• Authorization URL<br/>• Token URL<br/>• Scopes]
        OAUTH_FLOW[🔄 Authorization Flow<br/>• Authorization code<br/>• Client credentials<br/>• Refresh token]
        OAUTH_TOKENS[🎫 Token Management<br/>• Access token<br/>• Refresh token<br/>• Auto refresh]
    end

    BEARER_START --> BEARER_CONFIG
    BEARER_CONFIG --> BEARER_REQUEST
    BEARER_REQUEST --> BEARER_RESPONSE
    BEARER_RESPONSE --> BEARER_USAGE

    API_KEY_START --> API_KEY_CONFIG
    API_KEY_CONFIG --> API_KEY_STORAGE
    API_KEY_STORAGE --> API_KEY_USAGE

    OAUTH_START --> OAUTH_CONFIG
    OAUTH_CONFIG --> OAUTH_FLOW
    OAUTH_FLOW --> OAUTH_TOKENS

    classDef bearerStyle fill:#e3f2fd,stroke:#1565c0,stroke-width:2px,color:#000
    classDef apiKeyStyle fill:#f3e5f5,stroke:#7b1fa2,stroke-width:2px,color:#000
    classDef oauthStyle fill:#e8f5e8,stroke:#2e7d32,stroke-width:2px,color:#000

    class BEARER_START,BEARER_CONFIG,BEARER_REQUEST,BEARER_RESPONSE,BEARER_USAGE bearerStyle
    class API_KEY_START,API_KEY_CONFIG,API_KEY_STORAGE,API_KEY_USAGE apiKeyStyle
    class OAUTH_START,OAUTH_CONFIG,OAUTH_FLOW,OAUTH_TOKENS oauthStyle
```

## 💳 **Stripe Integration Example**

### **Complete Stripe Integration Flow**

```mermaid
sequenceDiagram
    participant App
    participant StripeIntegration
    participant CircuitBreaker
    participant RateLimiter
    participant StripeAPI
    participant WebhookHandler
    participant Database

    %% Payment Intent Creation
    App->>StripeIntegration: Create Payment Intent
    StripeIntegration->>CircuitBreaker: Check Circuit State
    
    alt Circuit Closed
        CircuitBreaker-->>StripeIntegration: Allow Request
        StripeIntegration->>RateLimiter: Check Rate Limit
        
        alt Rate Limit OK
            RateLimiter-->>StripeIntegration: Allow Request
            StripeIntegration->>StripeAPI: POST /payment_intents
            StripeAPI-->>StripeIntegration: Payment Intent Created
            StripeIntegration->>Database: Store Payment Record
            StripeIntegration-->>App: Return Payment Intent
        else Rate Limited
            RateLimiter-->>StripeIntegration: Rate Limited
            StripeIntegration-->>App: Rate Limit Error
        end
    else Circuit Open
        CircuitBreaker-->>StripeIntegration: Circuit Open
        StripeIntegration-->>App: Service Unavailable
    end
    
    %% Webhook Processing
    StripeAPI->>WebhookHandler: Payment Intent Succeeded
    WebhookHandler->>WebhookHandler: Verify Signature
    
    alt Signature Valid
        WebhookHandler->>StripeIntegration: Process Event
        StripeIntegration->>Database: Update Payment Status
        StripeIntegration->>App: Trigger Business Logic
        WebhookHandler-->>StripeAPI: 200 OK
    else Invalid Signature
        WebhookHandler-->>StripeAPI: 400 Bad Request
    end
```

### **Stripe Service Integration Details**

```mermaid
graph TB
    subgraph "Stripe Integration Components"
        STRIPE_CLIENT[💳 Stripe Integration Client<br/>• Payment processing<br/>• Customer management<br/>• Subscription handling]
        
        subgraph "Payment Operations"
            CREATE_INTENT[💰 Create Payment Intent<br/>• Amount & currency<br/>• Customer info<br/>• Metadata]
            CONFIRM_PAYMENT[✅ Confirm Payment<br/>• Payment method<br/>• Return URL<br/>• Confirmation]
            CREATE_CUSTOMER[👤 Create Customer<br/>• Email & name<br/>• Payment methods<br/>• Metadata]
            PROCESS_REFUND[💸 Process Refund<br/>• Refund amount<br/>• Reason code<br/>• Metadata]
        end
        
        subgraph "Webhook Handling"
            WEBHOOK_RECEIVER[🎣 Webhook Receiver<br/>• Signature verification<br/>• Event parsing<br/>• Route to handlers]
            EVENT_HANDLERS[⚡ Event Handlers<br/>• payment_intent.succeeded<br/>• payment_intent.failed<br/>• customer.created]
        end
        
        subgraph "Configuration"
            STRIPE_CONFIG[⚙️ Stripe Configuration<br/>• Secret key<br/>• Webhook secret<br/>• API version]
            RATE_CONFIG[📊 Rate Limiting<br/>• 100 req/sec<br/>• Burst handling<br/>• Backoff strategy]
            CB_CONFIG[🛡️ Circuit Breaker<br/>• 50% threshold<br/>• 60s timeout<br/>• Auto recovery]
        end
    end

    STRIPE_CLIENT --> CREATE_INTENT
    STRIPE_CLIENT --> CONFIRM_PAYMENT
    STRIPE_CLIENT --> CREATE_CUSTOMER
    STRIPE_CLIENT --> PROCESS_REFUND
    
    STRIPE_CLIENT --> WEBHOOK_RECEIVER
    WEBHOOK_RECEIVER --> EVENT_HANDLERS
    
    STRIPE_CLIENT --> STRIPE_CONFIG
    STRIPE_CLIENT --> RATE_CONFIG
    STRIPE_CLIENT --> CB_CONFIG

    classDef stripeStyle fill:#e3f2fd,stroke:#1565c0,stroke-width:2px,color:#000
    classDef paymentStyle fill:#e8f5e8,stroke:#2e7d32,stroke-width:2px,color:#000
    classDef webhookStyle fill:#fff3e0,stroke:#f57c00,stroke-width:2px,color:#000
    classDef configStyle fill:#f3e5f5,stroke:#7b1fa2,stroke-width:2px,color:#000

    class STRIPE_CLIENT stripeStyle
    class CREATE_INTENT,CONFIRM_PAYMENT,CREATE_CUSTOMER,PROCESS_REFUND paymentStyle
    class WEBHOOK_RECEIVER,EVENT_HANDLERS webhookStyle
    class STRIPE_CONFIG,RATE_CONFIG,CB_CONFIG configStyle
```

## 🎣 **Webhook Security & Processing**

### **Webhook Security Flow**

```mermaid
sequenceDiagram
    participant ExternalService
    participant WebhookEndpoint
    participant SignatureVerifier
    participant EventProcessor
    participant BusinessLogic
    participant Database

    ExternalService->>WebhookEndpoint: POST /webhook
    Note over ExternalService: Includes signature header
    
    WebhookEndpoint->>SignatureVerifier: Verify Signature
    SignatureVerifier->>SignatureVerifier: Extract Signature Components
    SignatureVerifier->>SignatureVerifier: Check Timestamp (5min tolerance)
    SignatureVerifier->>SignatureVerifier: Calculate Expected Signature
    SignatureVerifier->>SignatureVerifier: Compare Signatures
    
    alt Signature Valid
        SignatureVerifier-->>WebhookEndpoint: Signature Valid
        WebhookEndpoint->>EventProcessor: Process Event
        EventProcessor->>EventProcessor: Parse Event Data
        EventProcessor->>EventProcessor: Route to Handler
        EventProcessor->>BusinessLogic: Execute Business Logic
        BusinessLogic->>Database: Update Records
        BusinessLogic-->>EventProcessor: Processing Complete
        EventProcessor-->>WebhookEndpoint: Event Processed
        WebhookEndpoint-->>ExternalService: 200 OK
    else Invalid Signature
        SignatureVerifier-->>WebhookEndpoint: Invalid Signature
        WebhookEndpoint-->>ExternalService: 400 Bad Request
        Note over WebhookEndpoint: Log security incident
    else Timestamp Too Old
        SignatureVerifier-->>WebhookEndpoint: Timestamp Invalid
        WebhookEndpoint-->>ExternalService: 400 Bad Request
        Note over WebhookEndpoint: Prevent replay attacks
    end
```

### **Webhook Event Processing**

```mermaid
graph TD
    WEBHOOK_RECEIVED[🎣 Webhook Received] --> EXTRACT_HEADERS[📋 Extract Headers]
    EXTRACT_HEADERS --> VERIFY_SIG[🔐 Verify Signature]
    
    VERIFY_SIG --> SIG_VALID{✅ Signature Valid?}
    SIG_VALID -->|Yes| PARSE_EVENT[📊 Parse Event Data]
    SIG_VALID -->|No| REJECT_REQUEST[❌ Reject Request]
    
    PARSE_EVENT --> IDENTIFY_TYPE[🏷️ Identify Event Type]
    IDENTIFY_TYPE --> ROUTE_HANDLER{🔀 Route to Handler}
    
    ROUTE_HANDLER -->|payment_intent.succeeded| PAYMENT_SUCCESS[💰 Handle Payment Success]
    ROUTE_HANDLER -->|payment_intent.failed| PAYMENT_FAILED[❌ Handle Payment Failure]
    ROUTE_HANDLER -->|customer.created| CUSTOMER_CREATED[👤 Handle Customer Creation]
    ROUTE_HANDLER -->|invoice.payment_succeeded| INVOICE_PAID[📄 Handle Invoice Payment]
    ROUTE_HANDLER -->|Unknown Event| LOG_UNKNOWN[📝 Log Unknown Event]
    
    PAYMENT_SUCCESS --> UPDATE_ORDER[📦 Update Order Status]
    PAYMENT_FAILED --> HANDLE_FAILURE[🔄 Handle Payment Failure]
    CUSTOMER_CREATED --> SYNC_CUSTOMER[👤 Sync Customer Data]
    INVOICE_PAID --> UPDATE_SUBSCRIPTION[📋 Update Subscription]
    
    UPDATE_ORDER --> SEND_CONFIRMATION[📧 Send Confirmation]
    HANDLE_FAILURE --> NOTIFY_FAILURE[📱 Notify Failure]
    SYNC_CUSTOMER --> UPDATE_PROFILE[👤 Update Profile]
    UPDATE_SUBSCRIPTION --> SEND_RECEIPT[📄 Send Receipt]
    
    SEND_CONFIRMATION --> SUCCESS_RESPONSE[✅ Return 200 OK]
    NOTIFY_FAILURE --> SUCCESS_RESPONSE
    UPDATE_PROFILE --> SUCCESS_RESPONSE
    SEND_RECEIPT --> SUCCESS_RESPONSE
    LOG_UNKNOWN --> SUCCESS_RESPONSE
    
    REJECT_REQUEST --> ERROR_RESPONSE[❌ Return 400 Error]

    classDef receiveStyle fill:#e8f5e8,stroke:#2e7d32,stroke-width:2px,color:#000
    classDef processStyle fill:#e3f2fd,stroke:#1565c0,stroke-width:2px,color:#000
    classDef handlerStyle fill:#fff3e0,stroke:#f57c00,stroke-width:2px,color:#000
    classDef actionStyle fill:#f1f8e9,stroke:#388e3c,stroke-width:2px,color:#000
    classDef responseStyle fill:#f3e5f5,stroke:#7b1fa2,stroke-width:2px,color:#000
    classDef errorStyle fill:#ffebee,stroke:#c62828,stroke-width:2px,color:#000

    class WEBHOOK_RECEIVED,EXTRACT_HEADERS receiveStyle
    class VERIFY_SIG,PARSE_EVENT,IDENTIFY_TYPE processStyle
    class PAYMENT_SUCCESS,PAYMENT_FAILED,CUSTOMER_CREATED,INVOICE_PAID,LOG_UNKNOWN handlerStyle
    class UPDATE_ORDER,HANDLE_FAILURE,SYNC_CUSTOMER,UPDATE_SUBSCRIPTION actionStyle
    class SUCCESS_RESPONSE responseStyle
    class REJECT_REQUEST,ERROR_RESPONSE errorStyle
```

## ⏳ **Rate Limiting & Retry Logic**

### **Rate Limiting Implementation**

```mermaid
graph TB
    subgraph "Rate Limiting System"
        REQUEST_IN[📥 Incoming Request] --> CHECK_LIMIT{📊 Check Rate Limit}
        
        CHECK_LIMIT -->|Within Limit| ALLOW_REQUEST[✅ Allow Request]
        CHECK_LIMIT -->|Limit Exceeded| BLOCK_REQUEST[🚫 Block Request]
        
        ALLOW_REQUEST --> UPDATE_COUNTER[📈 Update Request Counter]
        UPDATE_COUNTER --> MAKE_REQUEST[🌐 Make API Request]
        
        BLOCK_REQUEST --> WAIT_RESET[⏰ Wait for Reset]
        WAIT_RESET --> RETRY_REQUEST[🔄 Retry Request]
        
        subgraph "Rate Limit Storage"
            REDIS_COUNTER[(🔴 Redis Counter<br/>• Request count<br/>• Window start time<br/>• TTL expiration)]
        end
        
        subgraph "Rate Limit Configuration"
            LIMIT_CONFIG[⚙️ Limit Configuration<br/>• Max requests<br/>• Time window<br/>• Burst allowance]
        end
    end

    UPDATE_COUNTER --> REDIS_COUNTER
    CHECK_LIMIT --> REDIS_COUNTER
    CHECK_LIMIT --> LIMIT_CONFIG

    classDef requestStyle fill:#e8f5e8,stroke:#2e7d32,stroke-width:2px,color:#000
    classDef allowStyle fill:#e3f2fd,stroke:#1565c0,stroke-width:2px,color:#000
    classDef blockStyle fill:#ffebee,stroke:#c62828,stroke-width:2px,color:#000
    classDef storageStyle fill:#f1f8e9,stroke:#388e3c,stroke-width:2px,color:#000
    classDef configStyle fill:#f3e5f5,stroke:#7b1fa2,stroke-width:2px,color:#000

    class REQUEST_IN,MAKE_REQUEST requestStyle
    class ALLOW_REQUEST,UPDATE_COUNTER allowStyle
    class BLOCK_REQUEST,WAIT_RESET,RETRY_REQUEST blockStyle
    class REDIS_COUNTER storageStyle
    class LIMIT_CONFIG configStyle
```

### **Retry Logic with Exponential Backoff**

```mermaid
sequenceDiagram
    participant Integration
    participant RetryLogic
    participant ExternalAPI
    participant Timer

    Integration->>RetryLogic: Make API Call
    RetryLogic->>ExternalAPI: Attempt 1
    
    alt Request Success
        ExternalAPI-->>RetryLogic: 200 OK
        RetryLogic-->>Integration: Success Response
    else Retryable Error (5xx, timeout)
        ExternalAPI-->>RetryLogic: 503 Service Unavailable
        RetryLogic->>Timer: Wait 1 second (2^0)
        Timer-->>RetryLogic: Wait Complete
        
        RetryLogic->>ExternalAPI: Attempt 2
        alt Still Failing
            ExternalAPI-->>RetryLogic: 502 Bad Gateway
            RetryLogic->>Timer: Wait 2 seconds (2^1)
            Timer-->>RetryLogic: Wait Complete
            
            RetryLogic->>ExternalAPI: Attempt 3
            alt Final Attempt
                ExternalAPI-->>RetryLogic: 500 Internal Error
                RetryLogic->>Timer: Wait 4 seconds (2^2)
                Timer-->>RetryLogic: Wait Complete
                
                RetryLogic->>ExternalAPI: Attempt 4 (Final)
                alt Success
                    ExternalAPI-->>RetryLogic: 200 OK
                    RetryLogic-->>Integration: Success Response
                else Max Retries Exceeded
                    ExternalAPI-->>RetryLogic: Still Failing
                    RetryLogic-->>Integration: Max Retries Exceeded
                end
            end
        end
    else Non-Retryable Error (4xx)
        ExternalAPI-->>RetryLogic: 400 Bad Request
        RetryLogic-->>Integration: Client Error (No Retry)
    end
```

## 📊 **Integration Monitoring**

### **Integration Health Dashboard**

```mermaid
graph TB
    subgraph "Integration Monitoring Dashboard"
        OVERVIEW[📊 Integration Overview<br/>• Service status<br/>• Success rates<br/>• Response times]
        
        subgraph "Service Health Metrics"
            AUTH_STATUS[🔐 Authentication Status<br/>• Token validity<br/>• Auth success rate<br/>• Token refresh frequency]
            API_METRICS[📈 API Call Metrics<br/>• Request volume<br/>• Success/failure rates<br/>• Response time trends]
            RATE_METRICS[⏳ Rate Limit Metrics<br/>• Current usage<br/>• Limit utilization<br/>• Throttling events]
        end
        
        subgraph "Circuit Breaker Monitoring"
            CB_STATUS[🛡️ Circuit Breaker Status<br/>• Circuit states<br/>• Failure thresholds<br/>• Recovery attempts]
            CB_METRICS[📊 Circuit Breaker Metrics<br/>• State transitions<br/>• Failure patterns<br/>• Recovery times]
        end
        
        subgraph "Webhook Monitoring"
            WEBHOOK_METRICS[🎣 Webhook Metrics<br/>• Event volume<br/>• Processing times<br/>• Success rates]
            SECURITY_METRICS[🔐 Security Metrics<br/>• Signature failures<br/>• Replay attempts<br/>• Invalid requests]
        end
        
        subgraph "Alerting System"
            SERVICE_ALERTS[🚨 Service Alerts<br/>• Service outages<br/>• High failure rates<br/>• Auth failures]
            PERFORMANCE_ALERTS[⚡ Performance Alerts<br/>• Slow responses<br/>• Rate limit hits<br/>• Circuit breaker trips]
        end
    end

    OVERVIEW --> AUTH_STATUS
    OVERVIEW --> API_METRICS
    OVERVIEW --> RATE_METRICS
    
    AUTH_STATUS --> CB_STATUS
    API_METRICS --> CB_METRICS
    RATE_METRICS --> CB_STATUS
    
    CB_STATUS --> WEBHOOK_METRICS
    CB_METRICS --> SECURITY_METRICS
    
    API_METRICS --> SERVICE_ALERTS
    CB_METRICS --> SERVICE_ALERTS
    WEBHOOK_METRICS --> PERFORMANCE_ALERTS
    SECURITY_METRICS --> PERFORMANCE_ALERTS

    classDef overviewStyle fill:#e8f5e8,stroke:#2e7d32,stroke-width:2px,color:#000
    classDef healthStyle fill:#e3f2fd,stroke:#1565c0,stroke-width:2px,color:#000
    classDef cbStyle fill:#fff3e0,stroke:#f57c00,stroke-width:2px,color:#000
    classDef webhookStyle fill:#f1f8e9,stroke:#388e3c,stroke-width:2px,color:#000
    classDef alertStyle fill:#ffebee,stroke:#c62828,stroke-width:2px,color:#000

    class OVERVIEW overviewStyle
    class AUTH_STATUS,API_METRICS,RATE_METRICS healthStyle
    class CB_STATUS,CB_METRICS cbStyle
    class WEBHOOK_METRICS,SECURITY_METRICS webhookStyle
    class SERVICE_ALERTS,PERFORMANCE_ALERTS alertStyle
```

## 🎯 **Key Benefits**

### **🔌 Standardized Integration Patterns**
- **Consistent API** for all external service integrations
- **Pluggable Authentication** strategies for different services
- **Unified Error Handling** across all integrations
- **Common Configuration** patterns and management

### **🛡️ Comprehensive Protection**
- **Circuit Breaker Protection** for external service failures
- **Rate Limiting** to respect service quotas and limits
- **Retry Logic** with intelligent exponential backoff
- **Authentication Management** with automatic token refresh

### **🔐 Security & Reliability**
- **Webhook Signature Verification** for secure event handling
- **Replay Attack Prevention** with timestamp validation
- **Secure Token Storage** with encryption and TTL management
- **Comprehensive Logging** for security audit trails

### **📈 Monitoring & Observability**
- **Real-time Integration Health** monitoring and alerting
- **Performance Metrics** for optimization and troubleshooting
- **Security Metrics** for threat detection and prevention
- **Historical Analytics** for capacity planning and optimization

This third-party integration framework provides enterprise-grade capabilities for secure, reliable, and scalable external service connectivity with comprehensive monitoring and fault tolerance.

