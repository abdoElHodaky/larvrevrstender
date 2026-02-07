# 🛡️ Circuit Breaker Architecture

## 🎯 **Overview**

The **Dual Circuit Breaker Architecture** provides comprehensive fault tolerance for both synchronous HTTP requests and asynchronous queue job processing, preventing cascade failures and ensuring system resilience.

## 🏗️ **Complete Circuit Breaker Architecture**

```mermaid
%%{init: {
  'theme': 'dark',
  'themeVariables': {
    'primaryColor': '#FF4757',
    'primaryTextColor': '#FFFFFF',
    'primaryBorderColor': '#FF6B7A',
    'lineColor': '#4ECDC4',
    'secondaryColor': '#45B7D1',
    'tertiaryColor': '#96CEB4',
    'background': '#0F172A',
    'mainBkg': '#1E293B',
    'secondBkg': '#334155',
    'tertiaryBkg': '#475569',
    'clusterBkg': '#1E293B',
    'clusterBorder': '#FF4757',
    'edgeLabelBackground': '#334155',
    'nodeTextColor': '#FFFFFF',
    'edgeColor': '#4ECDC4'
  }
}}%%

graph TB
    subgraph "🌐 APPLICATION LAYER"
        APP_REQUEST[🌐 Application Request<br/>• HTTP API calls<br/>• Queue job dispatch<br/>• External service calls]
    end

    subgraph "🛡️ DUAL CIRCUIT BREAKER PROTECTION"
        subgraph "⚡ Synchronous Circuit Breaker"
            SYNC_CB[🛡️ HTTP Circuit Breaker<br/>• REST API protection<br/>• External service calls<br/>• Real-time requests]
            SYNC_STATE{🔄 Circuit State}
            SYNC_CLOSED[✅ CLOSED<br/>Normal Operation<br/>Requests Pass Through]
            SYNC_OPEN[❌ OPEN<br/>Fail Fast Mode<br/>Block All Requests]
            SYNC_HALF[⚠️ HALF_OPEN<br/>Testing Recovery<br/>Limited Requests]
        end
        
        subgraph "🔄 Asynchronous Circuit Breaker"
            ASYNC_CB[⚡ Queue Circuit Breaker<br/>Laravel Fuse Integration<br/>• Job processing protection<br/>• Background task safety]
            ASYNC_STATE{🔄 Circuit State}
            ASYNC_CLOSED[✅ CLOSED<br/>Jobs Processed<br/>Normal Queue Operation]
            ASYNC_OPEN[❌ OPEN<br/>Jobs Released<br/>Back to Queue with Delay]
            ASYNC_HALF[⚠️ HALF_OPEN<br/>Testing Recovery<br/>Limited Job Processing]
        end
    end

    subgraph "🎯 TARGET SERVICES"
        HTTP_SERVICE[🌐 HTTP API Services<br/>• Payment gateways<br/>• External APIs<br/>• Microservices]
        QUEUE_SERVICE[📬 Queue-based Services<br/>• Background processing<br/>• Async operations<br/>• Batch jobs]
    end

    subgraph "💾 STATE MANAGEMENT & MONITORING"
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

    %% Distinguished Eye-Catching Styling
    classDef appStyle fill:#FF9FF3,stroke:#FFFFFF,stroke-width:3px,color:#FFFFFF,font-weight:bold
    classDef syncStyle fill:#FF4757,stroke:#FFFFFF,stroke-width:3px,color:#FFFFFF,font-weight:bold
    classDef asyncStyle fill:#5F27CD,stroke:#FFFFFF,stroke-width:3px,color:#FFFFFF,font-weight:bold
    classDef serviceStyle fill:#45B7D1,stroke:#FFFFFF,stroke-width:3px,color:#FFFFFF,font-weight:bold
    classDef stateStyle fill:#FECA57,stroke:#000000,stroke-width:3px,color:#000000,font-weight:bold
    classDef closedStyle fill:#2ED573,stroke:#FFFFFF,stroke-width:3px,color:#FFFFFF,font-weight:bold
    classDef openStyle fill:#FF4757,stroke:#FFFFFF,stroke-width:3px,color:#FFFFFF,font-weight:bold
    classDef halfStyle fill:#FFD93D,stroke:#000000,stroke-width:3px,color:#000000,font-weight:bold

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
   %%{init: {
  'theme': 'base',
  'themeVariables': {
    'background': '#0B0F14',
    'primaryColor': '#2ECC71',
    'primaryTextColor': '#FFFFFF',
    'primaryBorderColor': '#27AE60',
    'lineColor': '#4ECDC4',
    'secondaryColor': '#FF4757',
    'tertiaryColor': '#F1C40F',
    'mainBkg': '#161B22',
    'clusterBkg': 'rgba(255, 255, 255, 0.03)',
    'clusterBorder': '#334155',
    'edgeLabelBackground':'#1A1D23',
    'fontFamily': 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace'
  }
}}%%

stateDiagram-v2
    [*] --> CLOSED: Initialize Circuit Breaker
    
    state "🛡️ Circuit Breaker States" as CBStates {
        state CLOSED {
            [*] --> Processing
            Processing --> Success: Request succeeds
            Processing --> Failure: Request fails
            Success --> Processing: [Count < Threshold]<br/>Continue processing
            Failure --> Processing: [Count++]<br/>Track failure count
        }
        
        state OPEN {
            [*] --> Blocking
            Blocking --> FailFast: Reject all requests
            FailFast --> Blocking: Return cached response
        }
        
        state HALF_OPEN {
            [*] --> Testing
            Testing --> SingleRequest: Allow one test request
            SingleRequest --> Evaluating: Monitor response
        }

        CLOSED --> OPEN: [Failure > 5 in 60s]<br/>Trip Circuit
        OPEN --> HALF_OPEN: [Timer > 30s]<br/>Attempt Reset
        HALF_OPEN --> CLOSED: [Test SUCCESS]<br/>Service recovered
        HALF_OPEN --> OPEN: [Test FAILURE]<br/>Service still down
    }   
    
    CBStates --> [*]: Circuit breaker shutdown

    %% Hybrid Class Definitions
    classDef closedState fill:#064E3B,stroke:#00F2FE,color:#fff,stroke-width:2px
    classDef openState fill:#450A0A,stroke:#FF4757,color:#fff,stroke-width:2px
    classDef halfOpenState fill:#451A03,stroke:#FDCB6E,color:#fff,stroke-width:2px

    class CLOSED closedState
    class OPEN openState
    class HALF_OPEN halfOpenState
```

## ⚙️ **Configuration & Thresholds**

### **Circuit Breaker Configuration**

```mermaid
%%{init: {
  'theme': 'dark',
  'themeVariables': {
    'primaryColor': '#54A0FF',
    'primaryTextColor': '#FFFFFF',
    'primaryBorderColor': '#7BB3FF',
    'lineColor': '#4ECDC4',
    'secondaryColor': '#45B7D1',
    'tertiaryColor': '#96CEB4',
    'background': '#0F172A',
    'mainBkg': '#1E293B',
    'secondBkg': '#334155',
    'tertiaryBkg': '#475569',
    'clusterBkg': '#1E293B',
    'clusterBorder': '#54A0FF',
    'edgeLabelBackground': '#334155',
    'nodeTextColor': '#FFFFFF',
    'edgeColor': '#4ECDC4'
  }
}}%%

graph TB
    subgraph "⚙️ CONFIGURATION MANAGEMENT"
        CONFIG_MANAGER[⚙️ Configuration Manager<br/>• Dynamic configuration<br/>• Environment-specific settings<br/>• Runtime adjustments]
        
        subgraph "🔧 Threshold Settings"
            FAILURE_THRESHOLD[❌ Failure Threshold<br/>• Default: 5 failures<br/>• Time window: 60 seconds<br/>• Configurable per service]
            TIMEOUT_THRESHOLD[⏰ Timeout Threshold<br/>• Default: 30 seconds<br/>• Recovery period<br/>• Exponential backoff]
            SUCCESS_THRESHOLD[✅ Success Threshold<br/>• Default: 1 success<br/>• Half-open validation<br/>• Recovery confirmation]
        end
        
        subgraph "📊 Monitoring Thresholds"
            RESPONSE_TIME[⏱️ Response Time Threshold<br/>• Default: 5000ms<br/>• Slow response detection<br/>• Performance monitoring]
            ERROR_RATE[📈 Error Rate Threshold<br/>• Default: 50%<br/>• Error percentage<br/>• Statistical analysis]
            VOLUME_THRESHOLD[📊 Volume Threshold<br/>• Minimum: 10 requests<br/>• Statistical significance<br/>• Avoid false positives]
        end
    end

    subgraph "🎯 SERVICE-SPECIFIC CONFIGURATION"
        PAYMENT_CONFIG[💳 Payment Service Config<br/>• Failure threshold: 3<br/>• Timeout: 15s<br/>• Critical service]
        EMAIL_CONFIG[📧 Email Service Config<br/>• Failure threshold: 10<br/>• Timeout: 60s<br/>• Non-critical service]
        SMS_CONFIG[📱 SMS Service Config<br/>• Failure threshold: 5<br/>• Timeout: 30s<br/>• Standard service]
    end

    CONFIG_MANAGER --> FAILURE_THRESHOLD
    CONFIG_MANAGER --> TIMEOUT_THRESHOLD
    CONFIG_MANAGER --> SUCCESS_THRESHOLD
    CONFIG_MANAGER --> RESPONSE_TIME
    CONFIG_MANAGER --> ERROR_RATE
    CONFIG_MANAGER --> VOLUME_THRESHOLD

    CONFIG_MANAGER --> PAYMENT_CONFIG
    CONFIG_MANAGER --> EMAIL_CONFIG
    CONFIG_MANAGER --> SMS_CONFIG

    %% Distinguished Eye-Catching Styling
    classDef configStyle fill:#54A0FF,stroke:#FFFFFF,stroke-width:3px,color:#FFFFFF,font-weight:bold
    classDef thresholdStyle fill:#FF4757,stroke:#FFFFFF,stroke-width:3px,color:#FFFFFF,font-weight:bold
    classDef monitoringStyle fill:#FECA57,stroke:#000000,stroke-width:3px,color:#000000,font-weight:bold
    classDef serviceStyle fill:#96CEB4,stroke:#FFFFFF,stroke-width:3px,color:#FFFFFF,font-weight:bold

    class CONFIG_MANAGER configStyle
    class FAILURE_THRESHOLD,TIMEOUT_THRESHOLD,SUCCESS_THRESHOLD thresholdStyle
    class RESPONSE_TIME,ERROR_RATE,VOLUME_THRESHOLD monitoringStyle
    class PAYMENT_CONFIG,EMAIL_CONFIG,SMS_CONFIG serviceStyle
```

## 🚀 **Implementation Features**

### **Key Features**

- **🔄 Dual Protection**: Synchronous HTTP and asynchronous queue protection
- **⚡ Laravel Fuse Integration**: Native Laravel queue circuit breaker
- **🎯 Intelligent Failure Detection**: 5xx errors trigger, 4xx errors ignored
- **📊 Real-time Monitoring**: Redis-based state management
- **🔧 Configurable Thresholds**: Per-service configuration
- **🛡️ Automatic Recovery**: Self-healing with test requests
- **📈 Metrics Collection**: Comprehensive monitoring and alerting
- **🎨 Distinguished Styling**: Eye-catching visual representation

### **Benefits**

- **🛡️ Fault Tolerance**: Prevents cascade failures
- **⚡ Performance**: Fast fail responses
- **🔄 Resilience**: Automatic recovery
- **📊 Observability**: Real-time monitoring
- **🎯 Reliability**: Service protection
- **🚀 Scalability**: Distributed state management

