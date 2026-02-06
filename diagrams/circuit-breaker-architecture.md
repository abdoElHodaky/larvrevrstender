# 🛡️ Circuit Breaker Architecture

## 🎯 **Overview**

The **Dual Circuit Breaker Architecture** provides comprehensive fault tolerance for both synchronous HTTP requests and asynchronous queue job processing, preventing cascade failures and ensuring system resilience.

## 🏗️ **Complete Circuit Breaker Architecture**

```mermaid
graph TB
    subgraph "Application Layer"
        APP_REQUEST[🌐 Application Request<br/>• HTTP API calls<br/>• Queue job dispatch<br/>• External service calls]
    end

    subgraph "Dual Circuit Breaker Protection"
        subgraph "Synchronous Circuit Breaker"
            SYNC_CB[🛡️ HTTP Circuit Breaker<br/>• REST API protection<br/>• External service calls<br/>• Real-time requests]
            SYNC_STATE{🔄 Circuit State}
            SYNC_CLOSED[✅ CLOSED<br/>Normal Operation<br/>Requests Pass Through]
            SYNC_OPEN[❌ OPEN<br/>Fail Fast Mode<br/>Block All Requests]
            SYNC_HALF[⚠️ HALF_OPEN<br/>Testing Recovery<br/>Limited Requests]
        end
        
        subgraph "Asynchronous Circuit Breaker"
            ASYNC_CB[⚡ Queue Circuit Breaker<br/>Laravel Fuse Integration<br/>• Job processing protection<br/>• Background task safety]
            ASYNC_STATE{🔄 Circuit State}
            ASYNC_CLOSED[✅ CLOSED<br/>Jobs Processed<br/>Normal Queue Operation]
            ASYNC_OPEN[❌ OPEN<br/>Jobs Released<br/>Back to Queue with Delay]
            ASYNC_HALF[⚠️ HALF_OPEN<br/>Testing Recovery<br/>Limited Job Processing]
        end
    end

    subgraph "Target Services"
        HTTP_SERVICE[🌐 HTTP API Services<br/>• Payment gateways<br/>• External APIs<br/>• Microservices]
        QUEUE_SERVICE[📬 Queue-based Services<br/>• Background processing<br/>• Async operations<br/>• Batch jobs]
    end

    subgraph "State Management & Monitoring"
        REDIS_STATE[(🔴 Redis State Store<br/>• Circuit states<br/>• Failure metrics<br/>• Recovery timers<br/>• Request counters)]
        METRICS_COLLECTOR[📊 Metrics Collector<br/>• Success/failure rates<br/>• Response times<br/>• Circuit transitions<br/>• Health status]
        MONITORING[📈 Monitoring Dashboard<br/>• Real-time status<br/>• Alert management<br/>• Performance metrics<br/>• Historical data]
    end

    %% Application to circuit breakers
    APP_REQUEST --> SYNC_CB
    APP_REQUEST --> ASYNC_CB

    %% Synchronous circuit breaker flow
    SYNC_CB --> SYNC_STATE
    SYNC_STATE --> SYNC_CLOSED
    SYNC_STATE --> SYNC_OPEN
    SYNC_STATE --> SYNC_HALF

    %% Asynchronous circuit breaker flow
    ASYNC_CB --> ASYNC_STATE
    ASYNC_STATE --> ASYNC_CLOSED
    ASYNC_STATE --> ASYNC_OPEN
    ASYNC_STATE --> ASYNC_HALF

    %% Service connections
    SYNC_CLOSED --> HTTP_SERVICE
    SYNC_HALF --> HTTP_SERVICE
    SYNC_OPEN -.->|Fail Fast| APP_REQUEST

    ASYNC_CLOSED --> QUEUE_SERVICE
    ASYNC_HALF --> QUEUE_SERVICE
    ASYNC_OPEN -.->|Release with Delay| QUEUE_SERVICE

    %% State management connections
    SYNC_CB --> REDIS_STATE
    ASYNC_CB --> REDIS_STATE
    SYNC_CB --> METRICS_COLLECTOR
    ASYNC_CB --> METRICS_COLLECTOR
    METRICS_COLLECTOR --> MONITORING

    %% State transitions (dotted lines for transitions)
    SYNC_CLOSED -.->|Failure Threshold Exceeded| SYNC_OPEN
    SYNC_OPEN -.->|Timeout Elapsed| SYNC_HALF
    SYNC_HALF -.->|Test Success| SYNC_CLOSED
    SYNC_HALF -.->|Test Failure| SYNC_OPEN

    ASYNC_CLOSED -.->|Failure Threshold Exceeded| ASYNC_OPEN
    ASYNC_OPEN -.->|Timeout Elapsed| ASYNC_HALF
    ASYNC_HALF -.->|Test Success| ASYNC_CLOSED
    ASYNC_HALF -.->|Test Failure| ASYNC_OPEN

    %% Styling
    classDef appStyle fill:#e8eaf6,stroke:#3f51b5,stroke-width:2px,color:#000
    classDef syncStyle fill:#e0f2f1,stroke:#00695c,stroke-width:2px,color:#000
    classDef asyncStyle fill:#fff3e0,stroke:#ef6c00,stroke-width:2px,color:#000
    classDef serviceStyle fill:#fce4ec,stroke:#ad1457,stroke-width:2px,color:#000
    classDef stateStyle fill:#f1f8e9,stroke:#2e7d32,stroke-width:2px,color:#000
    classDef closedStyle fill:#e8f5e8,stroke:#2e7d32,stroke-width:2px,color:#000
    classDef openStyle fill:#ffebee,stroke:#c62828,stroke-width:2px,color:#000
    classDef halfStyle fill:#fff8e1,stroke:#f57c00,stroke-width:2px,color:#000

    class APP_REQUEST appStyle
    class SYNC_CB,SYNC_STATE syncStyle
    class ASYNC_CB,ASYNC_STATE asyncStyle
    class HTTP_SERVICE,QUEUE_SERVICE serviceStyle
    class REDIS_STATE,METRICS_COLLECTOR,MONITORING stateStyle
    class SYNC_CLOSED,ASYNC_CLOSED closedStyle
    class SYNC_OPEN,ASYNC_OPEN openStyle
    class SYNC_HALF,ASYNC_HALF halfStyle
```

## 🔄 **Circuit Breaker State Transitions**

### **State Transition Diagram**

```mermaid
stateDiagram-v2
    [*] --> CLOSED : Initialize
    
    CLOSED --> OPEN : Failure threshold exceeded
    OPEN --> HALF_OPEN : Timeout elapsed
    HALF_OPEN --> CLOSED : Test request succeeds
    HALF_OPEN --> OPEN : Test request fails
    
    state CLOSED {
        [*] --> ProcessingRequests
        ProcessingRequests --> CountingFailures
        CountingFailures --> EvaluatingThreshold
        EvaluatingThreshold --> ProcessingRequests : Below threshold
    }
    
    state OPEN {
        [*] --> BlockingRequests
        BlockingRequests --> WaitingForTimeout
        WaitingForTimeout --> ReadyForTest
    }
    
    state HALF_OPEN {
        [*] --> TestingService
        TestingService --> EvaluatingResult
        EvaluatingResult --> TestingService : Continue testing
    }
```

### **Detailed State Behavior**

```mermaid
graph TB
    subgraph "CLOSED State Behavior"
        CLOSED_START[🟢 CLOSED State<br/>Normal Operation]
        PROCESS_REQ[📥 Process All Requests]
        COUNT_FAILURES[📊 Count Failures]
        CHECK_THRESHOLD{🔍 Check Failure Rate}
        CONTINUE_CLOSED[✅ Continue Processing]
        TRIGGER_OPEN[🚨 Trigger OPEN State]
    end
    
    subgraph "OPEN State Behavior"
        OPEN_START[🔴 OPEN State<br/>Protection Mode]
        FAIL_FAST[⚡ Fail Fast Response]
        WAIT_TIMEOUT[⏰ Wait for Timeout]
        TRIGGER_HALF[🔄 Trigger HALF_OPEN]
    end
    
    subgraph "HALF_OPEN State Behavior"
        HALF_START[🟡 HALF_OPEN State<br/>Testing Recovery]
        TEST_REQUEST[🧪 Send Test Request]
        EVALUATE_RESULT{📊 Evaluate Result}
        BACK_TO_CLOSED[✅ Return to CLOSED]
        BACK_TO_OPEN[❌ Return to OPEN]
    end

    %% CLOSED state flow
    CLOSED_START --> PROCESS_REQ
    PROCESS_REQ --> COUNT_FAILURES
    COUNT_FAILURES --> CHECK_THRESHOLD
    CHECK_THRESHOLD -->|Below Threshold| CONTINUE_CLOSED
    CHECK_THRESHOLD -->|Above Threshold| TRIGGER_OPEN
    CONTINUE_CLOSED --> PROCESS_REQ

    %% OPEN state flow
    TRIGGER_OPEN --> OPEN_START
    OPEN_START --> FAIL_FAST
    FAIL_FAST --> WAIT_TIMEOUT
    WAIT_TIMEOUT --> TRIGGER_HALF

    %% HALF_OPEN state flow
    TRIGGER_HALF --> HALF_START
    HALF_START --> TEST_REQUEST
    TEST_REQUEST --> EVALUATE_RESULT
    EVALUATE_RESULT -->|Success| BACK_TO_CLOSED
    EVALUATE_RESULT -->|Failure| BACK_TO_OPEN
    BACK_TO_CLOSED --> CLOSED_START
    BACK_TO_OPEN --> OPEN_START

    classDef closedStyle fill:#e8f5e8,stroke:#2e7d32,stroke-width:2px,color:#000
    classDef openStyle fill:#ffebee,stroke:#c62828,stroke-width:2px,color:#000
    classDef halfStyle fill:#fff8e1,stroke:#f57c00,stroke-width:2px,color:#000
    classDef processStyle fill:#e3f2fd,stroke:#1565c0,stroke-width:2px,color:#000

    class CLOSED_START,PROCESS_REQ,COUNT_FAILURES,CONTINUE_CLOSED,BACK_TO_CLOSED closedStyle
    class OPEN_START,FAIL_FAST,WAIT_TIMEOUT,TRIGGER_OPEN,BACK_TO_OPEN openStyle
    class HALF_START,TEST_REQUEST,TRIGGER_HALF halfStyle
    class CHECK_THRESHOLD,EVALUATE_RESULT processStyle
```

## ⚡ **Laravel Fuse Queue Circuit Breaker**

### **Queue Job Protection Flow**

```mermaid
sequenceDiagram
    participant App
    participant QueueCB
    participant Fuse
    participant Queue
    participant Worker
    participant Service
    participant Redis

    App->>QueueCB: Dispatch Job with Circuit Breaker
    QueueCB->>Fuse: Check Circuit State
    Fuse->>Redis: Get Circuit Status
    Redis-->>Fuse: Circuit Status (CLOSED/OPEN/HALF_OPEN)
    
    alt Circuit CLOSED
        Fuse-->>QueueCB: Allow Dispatch
        QueueCB->>Queue: Dispatch Job
        Queue->>Worker: Process Job
        Worker->>Service: Execute Job Logic
        
        alt Job Success
            Service-->>Worker: Success Response
            Worker-->>Queue: Job Completed
            Queue-->>QueueCB: Success
            QueueCB->>Fuse: Record Success
            Fuse->>Redis: Update Success Metrics
        else Job Failure (5xx Error)
            Service-->>Worker: 5xx Error
            Worker-->>Queue: Job Failed
            Queue-->>QueueCB: Failure
            QueueCB->>Fuse: Record Failure
            Fuse->>Redis: Update Failure Metrics
            Fuse->>Redis: Check Threshold
            
            alt Threshold Exceeded
                Fuse->>Redis: Open Circuit
                Note over Fuse: Circuit transitions to OPEN
            end
        else Job Failure (4xx Error)
            Service-->>Worker: 4xx Error
            Worker-->>Queue: Job Failed (No Circuit Trigger)
            Note over Fuse: 4xx errors don't trigger circuit
        end
        
    else Circuit OPEN
        Fuse-->>QueueCB: Block Dispatch
        QueueCB->>Queue: Release Job with Delay
        Note over Queue: Job released back to queue with exponential backoff
        
    else Circuit HALF_OPEN
        Fuse-->>QueueCB: Allow Limited Dispatch
        QueueCB->>Queue: Dispatch Test Job
        Queue->>Worker: Process Test Job
        Worker->>Service: Execute Test Logic
        
        alt Test Success
            Service-->>Worker: Success
            Worker-->>Queue: Test Completed
            QueueCB->>Fuse: Test Success
            Fuse->>Redis: Close Circuit
        else Test Failure
            Service-->>Worker: Failure
            Worker-->>Queue: Test Failed
            QueueCB->>Fuse: Test Failure
            Fuse->>Redis: Reopen Circuit
        end
    end
```

### **Failure Classification Logic**

```mermaid
graph TD
    JOB_EXECUTION[⚡ Job Execution] --> RESPONSE_CHECK{📊 Check Response}
    
    RESPONSE_CHECK -->|2xx Success| SUCCESS[✅ Success<br/>• Record success<br/>• Update metrics<br/>• Continue processing]
    
    RESPONSE_CHECK -->|4xx Client Error| CLIENT_ERROR[⚠️ Client Error<br/>• Bad request<br/>• Invalid data<br/>• No circuit trigger]
    
    RESPONSE_CHECK -->|429 Rate Limit| RATE_LIMIT[⏳ Rate Limited<br/>• Temporary throttling<br/>• Backoff strategy<br/>• No circuit trigger]
    
    RESPONSE_CHECK -->|5xx Server Error| SERVER_ERROR[❌ Server Error<br/>• Service unavailable<br/>• Internal error<br/>• Trigger circuit breaker]
    
    RESPONSE_CHECK -->|Connection Error| CONNECTION_ERROR[🔌 Connection Error<br/>• Network failure<br/>• Timeout<br/>• Trigger circuit breaker]
    
    SUCCESS --> UPDATE_SUCCESS[📈 Update Success Metrics]
    CLIENT_ERROR --> LOG_CLIENT_ERROR[📝 Log Client Error]
    RATE_LIMIT --> APPLY_BACKOFF[⏰ Apply Backoff Strategy]
    SERVER_ERROR --> UPDATE_FAILURE[📉 Update Failure Metrics]
    CONNECTION_ERROR --> UPDATE_FAILURE
    
    UPDATE_FAILURE --> CHECK_THRESHOLD{🔍 Check Failure Threshold}
    CHECK_THRESHOLD -->|Below Threshold| CONTINUE[✅ Continue Processing]
    CHECK_THRESHOLD -->|Above Threshold| OPEN_CIRCUIT[🚨 Open Circuit Breaker]

    classDef successStyle fill:#e8f5e8,stroke:#2e7d32,stroke-width:2px,color:#000
    classDef warningStyle fill:#fff8e1,stroke:#f57c00,stroke-width:2px,color:#000
    classDef errorStyle fill:#ffebee,stroke:#c62828,stroke-width:2px,color:#000
    classDef processStyle fill:#e3f2fd,stroke:#1565c0,stroke-width:2px,color:#000

    class JOB_EXECUTION,UPDATE_SUCCESS,CONTINUE processStyle
    class SUCCESS,UPDATE_SUCCESS successStyle
    class CLIENT_ERROR,RATE_LIMIT,LOG_CLIENT_ERROR,APPLY_BACKOFF warningStyle
    class SERVER_ERROR,CONNECTION_ERROR,UPDATE_FAILURE,OPEN_CIRCUIT errorStyle
```

## 🔧 **Configuration Management**

### **Circuit Breaker Configuration Structure**

```mermaid
graph TB
    subgraph "Configuration Hierarchy"
        GLOBAL_CONFIG[🌐 Global Configuration<br/>• Default thresholds<br/>• Default timeouts<br/>• Global settings]
        
        subgraph "Service-Specific Configurations"
            STRIPE_CONFIG[💳 Stripe Configuration<br/>• Threshold: 50%<br/>• Timeout: 30s<br/>• Min requests: 5]
            MAILGUN_CONFIG[📧 Mailgun Configuration<br/>• Threshold: 60%<br/>• Timeout: 120s<br/>• Min requests: 10]
            TWILIO_CONFIG[📱 Twilio Configuration<br/>• Threshold: 40%<br/>• Timeout: 60s<br/>• Min requests: 8]
            PAYMENT_CONFIG[💰 Payment Gateway<br/>• Threshold: 30%<br/>• Timeout: 45s<br/>• Min requests: 5]
        end
        
        subgraph "Queue-Specific Settings"
            RELEASE_CONFIG[📬 Release Configuration<br/>• Release delay: 60s<br/>• Max releases: 10<br/>• Backoff multiplier: 2]
            FAILURE_CONFIG[❌ Failure Classification<br/>• Trigger codes: 5xx<br/>• Ignore codes: 4xx<br/>• Exception types]
        end
    end

    GLOBAL_CONFIG --> STRIPE_CONFIG
    GLOBAL_CONFIG --> MAILGUN_CONFIG
    GLOBAL_CONFIG --> TWILIO_CONFIG
    GLOBAL_CONFIG --> PAYMENT_CONFIG
    
    STRIPE_CONFIG --> RELEASE_CONFIG
    MAILGUN_CONFIG --> RELEASE_CONFIG
    TWILIO_CONFIG --> RELEASE_CONFIG
    PAYMENT_CONFIG --> RELEASE_CONFIG
    
    RELEASE_CONFIG --> FAILURE_CONFIG

    classDef globalStyle fill:#e3f2fd,stroke:#1565c0,stroke-width:2px,color:#000
    classDef serviceStyle fill:#fff3e0,stroke:#f57c00,stroke-width:2px,color:#000
    classDef queueStyle fill:#f1f8e9,stroke:#388e3c,stroke-width:2px,color:#000

    class GLOBAL_CONFIG globalStyle
    class STRIPE_CONFIG,MAILGUN_CONFIG,TWILIO_CONFIG,PAYMENT_CONFIG serviceStyle
    class RELEASE_CONFIG,FAILURE_CONFIG queueStyle
```

### **Configuration Parameters**

```mermaid
graph LR
    subgraph "Circuit Breaker Parameters"
        THRESHOLD[🎯 Failure Threshold<br/>• Percentage of failures<br/>• Default: 50%<br/>• Range: 1-100%]
        TIMEOUT[⏰ Recovery Timeout<br/>• Time before testing<br/>• Default: 60 seconds<br/>• Range: 10-3600s]
        MIN_REQUESTS[📊 Minimum Requests<br/>• Before evaluation<br/>• Default: 10 requests<br/>• Range: 1-100]
        WINDOW_SIZE[🕐 Time Window<br/>• Metrics collection period<br/>• Default: 300 seconds<br/>• Range: 60-3600s]
    end
    
    subgraph "Queue-Specific Parameters"
        RELEASE_DELAY[⏳ Release Delay<br/>• Job release delay<br/>• Default: 60 seconds<br/>• Exponential backoff]
        MAX_RELEASES[🔄 Max Releases<br/>• Maximum job releases<br/>• Default: 10 releases<br/>• Prevents infinite loops]
        BACKOFF_MULTIPLIER[📈 Backoff Multiplier<br/>• Exponential factor<br/>• Default: 2x<br/>• Range: 1.5-5x]
    end

    classDef paramStyle fill:#e8f5e8,stroke:#2e7d32,stroke-width:2px,color:#000
    classDef queueParamStyle fill:#fff3e0,stroke:#f57c00,stroke-width:2px,color:#000

    class THRESHOLD,TIMEOUT,MIN_REQUESTS,WINDOW_SIZE paramStyle
    class RELEASE_DELAY,MAX_RELEASES,BACKOFF_MULTIPLIER queueParamStyle
```

## 📊 **Monitoring & Metrics**

### **Circuit Breaker Metrics Dashboard**

```mermaid
graph TB
    subgraph "Real-time Monitoring Dashboard"
        OVERVIEW[📊 Circuit Breaker Overview<br/>• System-wide status<br/>• Active circuits<br/>• Health summary]
        
        subgraph "Service Metrics"
            SERVICE_STATUS[🔍 Service Status<br/>• Circuit states<br/>• Success/failure rates<br/>• Response times]
            FAILURE_TRENDS[📈 Failure Trends<br/>• Historical data<br/>• Trend analysis<br/>• Prediction models]
            RECOVERY_METRICS[🔄 Recovery Metrics<br/>• Recovery times<br/>• Test success rates<br/>• Circuit transitions]
        end
        
        subgraph "Queue Metrics"
            QUEUE_HEALTH[📬 Queue Health<br/>• Queue sizes<br/>• Processing rates<br/>• Failed jobs]
            RELEASE_METRICS[⏳ Release Metrics<br/>• Release counts<br/>• Backoff effectiveness<br/>• Job completion rates]
            JOB_METRICS[⚡ Job Metrics<br/>• Job success rates<br/>• Execution times<br/>• Error classifications]
        end
        
        subgraph "Alerting System"
            CIRCUIT_ALERTS[🚨 Circuit Alerts<br/>• Circuit state changes<br/>• Threshold breaches<br/>• Recovery failures]
            PERFORMANCE_ALERTS[⚡ Performance Alerts<br/>• Slow responses<br/>• High failure rates<br/>• Queue backlogs]
        end
    end

    OVERVIEW --> SERVICE_STATUS
    OVERVIEW --> QUEUE_HEALTH
    
    SERVICE_STATUS --> FAILURE_TRENDS
    SERVICE_STATUS --> RECOVERY_METRICS
    
    QUEUE_HEALTH --> RELEASE_METRICS
    QUEUE_HEALTH --> JOB_METRICS
    
    FAILURE_TRENDS --> CIRCUIT_ALERTS
    RECOVERY_METRICS --> CIRCUIT_ALERTS
    RELEASE_METRICS --> PERFORMANCE_ALERTS
    JOB_METRICS --> PERFORMANCE_ALERTS

    classDef overviewStyle fill:#e8f5e8,stroke:#2e7d32,stroke-width:2px,color:#000
    classDef serviceStyle fill:#e3f2fd,stroke:#1565c0,stroke-width:2px,color:#000
    classDef queueStyle fill:#fff3e0,stroke:#f57c00,stroke-width:2px,color:#000
    classDef alertStyle fill:#ffebee,stroke:#c62828,stroke-width:2px,color:#000

    class OVERVIEW overviewStyle
    class SERVICE_STATUS,FAILURE_TRENDS,RECOVERY_METRICS serviceStyle
    class QUEUE_HEALTH,RELEASE_METRICS,JOB_METRICS queueStyle
    class CIRCUIT_ALERTS,PERFORMANCE_ALERTS alertStyle
```

### **Metrics Collection Flow**

```mermaid
sequenceDiagram
    participant CircuitBreaker
    participant MetricsCollector
    participant Redis
    participant Prometheus
    participant Grafana
    participant AlertManager

    loop Every Request/Job
        CircuitBreaker->>MetricsCollector: Record Metric
        MetricsCollector->>Redis: Store Metric Data
    end
    
    loop Every 30 seconds
        MetricsCollector->>Redis: Aggregate Metrics
        MetricsCollector->>Prometheus: Export Metrics
    end
    
    loop Every 1 minute
        Prometheus->>Grafana: Update Dashboard
        Prometheus->>AlertManager: Check Alert Rules
    end
    
    alt Alert Triggered
        AlertManager->>AlertManager: Send Notification
        Note over AlertManager: Email, Slack, PagerDuty
    end
```

## 🎯 **Key Benefits**

### **🛡️ Comprehensive Fault Tolerance**
- **Dual Protection** for both sync and async operations
- **Intelligent Failure Classification** (5xx triggers, 4xx ignored)
- **Automatic Recovery Testing** with gradual restoration
- **Cascade Failure Prevention** across service boundaries

### **⚡ Performance Optimization**
- **Fail Fast Responses** during service outages
- **Resource Conservation** by avoiding doomed requests
- **Queue Protection** with intelligent job release strategies
- **Exponential Backoff** to prevent service overload

### **🔍 Advanced Monitoring**
- **Real-time Circuit State Visibility** across all services
- **Comprehensive Metrics Collection** with historical trends
- **Proactive Alerting** for threshold breaches and failures
- **Performance Analytics** for optimization insights

### **🔧 Flexible Configuration**
- **Service-Specific Tuning** for optimal protection
- **Dynamic Configuration** without service restarts
- **Environment-Specific Settings** for different deployment stages
- **Granular Control** over failure thresholds and timeouts

This dual circuit breaker architecture provides enterprise-grade fault tolerance with comprehensive protection for both synchronous and asynchronous operations, ensuring system resilience and optimal performance under various failure conditions.

