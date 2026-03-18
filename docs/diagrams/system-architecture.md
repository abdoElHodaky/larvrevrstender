# 📊 System Architecture Diagrams

## 🏗️ Microservices Ecosystem Overview

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                        Reverse Tender Platform                                 │
│                     Microservices Architecture                                 │
└─────────────────────────────────────────────────────────────────────────────────┘

                                    ┌─────────────────┐
                                    │   Load Balancer │
                                    │   (Nginx/HAProxy)│
                                    └─────────┬───────┘
                                              │
                                    ┌─────────▼───────┐
                                    │  Gateway Service │
                                    │    35 APIs      │
                                    │  (API Gateway)  │
                                    └─────────┬───────┘
                                              │
                    ┌─────────────────────────┼─────────────────────────┐
                    │                         │                         │
          ┌─────────▼───────┐       ┌─────────▼───────┐       ┌─────────▼───────┐
          │  Auth Service   │       │  User Service   │       │Analytics Service│
          │    54 APIs      │       │    81 APIs      │       │    21 APIs      │
          │ (Authentication)│       │(User Management)│       │  (Reporting)    │
          └─────────────────┘       └─────────────────┘       └─────────────────┘

┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│ Auction Service │ │ Bidding Service │ │  Order Service  │ │ Payment Service │
│    30 APIs      │ │    27 APIs      │ │    73 APIs      │ │    52 APIs      │
│ (Auction Mgmt)  │ │(Bid Processing) │ │(Order Processing│ │(Payment Gateway)│
└─────────────────┘ └─────────────────┘ └─────────────────┘ └─────────────────┘

          ┌─────────────────┐                         ┌─────────────────┐
          │Notification Svc │                         │ VIN OCR Service │
          │    25 APIs      │                         │    30 APIs      │
          │ (Notifications) │                         │ (OCR Processing)│
          └─────────────────┘                         └─────────────────┘

                              ┌─────────────────┐
                              │ Shared Library  │
                              │    63 APIs      │
                              │(Common Utilities│
                              │  & RPC Clients) │
                              └─────────────────┘
```

## 🔄 RPC Communication Architecture

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                         RPC Communication Layer                                 │
└─────────────────────────────────────────────────────────────────────────────────┘

Service A                                                            Service B
┌─────────────────┐                                        ┌─────────────────┐
│                 │                                        │                 │
│  ┌───────────┐  │                                        │  ┌───────────┐  │
│  │RPC Client │  │                                        │  │RPC Server │  │
│  │           │  │                                        │  │           │  │
│  │• Auth     │  │          ┌─────────────────┐          │  │• Validate │  │
│  │• Retry    │  │◄────────►│  RPC Middleware │◄────────►│  │• Process  │  │
│  │• Monitor  │  │          │                 │          │  │• Respond  │  │
│  │• Correlate│  │          │• Authentication │          │  │• Log      │  │
│  └───────────┘  │          │• Performance    │          │  └───────────┘  │
│                 │          │• Correlation    │          │                 │
└─────────────────┘          │• Logging        │          └─────────────────┘
                             └─────────────────┘

┌─────────────────────────────────────────────────────────────────────────────────┐
│                           RPC Security Layer                                   │
├─────────────────────────────────────────────────────────────────────────────────┤
│  X-RPC-Token Authentication                                                    │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐                       │
│  │   Service   │    │   Token     │    │   Service   │                       │
│  │  Generates  │───►│ Validation  │───►│  Accepts    │                       │
│  │   Token     │    │   (SHA256)  │    │  Request    │                       │
│  └─────────────┘    └─────────────┘    └─────────────┘                       │
└─────────────────────────────────────────────────────────────────────────────────┘
```

## 💾 Database Failover Architecture

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                        Database Failover System                                │
└─────────────────────────────────────────────────────────────────────────────────┘

                              Application Layer
                    ┌─────────────────────────────────────┐
                    │     Database Failover Manager       │
                    │                                     │
                    │  ┌─────────────────────────────┐   │
                    │  │    Health Monitoring        │   │
                    │  │  • Connection Status        │   │
                    │  │  • Response Time           │   │
                    │  │  • Error Rate              │   │
                    │  └─────────────────────────────┘   │
                    │                                     │
                    │  ┌─────────────────────────────┐   │
                    │  │   Failover Logic            │   │
                    │  │  • Automatic Detection      │   │
                    │  │  • Connection Switching     │   │
                    │  │  • Recovery Monitoring      │   │
                    │  └─────────────────────────────┘   │
                    └─────────────┬───────────────────────┘
                                  │
                    ┌─────────────▼───────────────────────┐
                    │        Connection Pool              │
                    └─────────────┬───────────────────────┘
                                  │
        ┌─────────────────────────┼─────────────────────────┐
        │                         │                         │
┌───────▼───────┐       ┌─────────▼───────┐       ┌─────────▼───────┐
│   Primary     │       │    Backup       │       │    Backup       │
│   Database    │       │   Database      │       │   Database      │
│   (Master)    │       │  (Sync Replica) │       │ (Async Replica) │
│               │       │                 │       │                 │
│ ✅ Healthy    │       │ ⚠️  Standby     │       │ ⚠️  Standby     │
└───────────────┘       └─────────────────┘       └─────────────────┘

Service-Specific Failover Handlers:
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│ Auth Service    │ │ Bidding Service │ │ Order Service   │ │ Payment Service │
│ Failover        │ │ Failover        │ │ Failover        │ │ Failover        │
│ Handler         │ │ Handler         │ │ Handler         │ │ Handler         │
└─────────────────┘ └─────────────────┘ └─────────────────┘ └─────────────────┘
```

## 🔒 Security Architecture

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                           Security Architecture                                 │
└─────────────────────────────────────────────────────────────────────────────────┘

                                External Clients
                                      │
                              ┌───────▼───────┐
                              │  Load Balancer │
                              │   SSL/TLS      │
                              └───────┬───────┘
                                      │
                              ┌───────▼───────┐
                              │ API Gateway   │
                              │ • Rate Limit  │
                              │ • API Keys    │
                              │ • CORS        │
                              └───────┬───────┘
                                      │
                    ┌─────────────────┼─────────────────┐
                    │                 │                 │
            ┌───────▼───────┐ ┌───────▼───────┐ ┌───────▼───────┐
            │ Auth Service  │ │ User Service  │ │ Other Services│
            │ • JWT Tokens  │ │ • RBAC        │ │ • RPC Auth    │
            │ • Sessions    │ │ • Permissions │ │ • Input Valid │
            │ • MFA         │ │ • Audit Logs  │ │ • Encryption  │
            └───────────────┘ └───────────────┘ └───────────────┘

┌─────────────────────────────────────────────────────────────────────────────────┐
│                        Inter-Service Security                                  │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  Service A                    Encrypted Channel                    Service B    │
│  ┌─────────────┐              ┌─────────────┐              ┌─────────────┐     │
│  │             │              │             │              │             │     │
│  │ X-RPC-Token │─────────────►│ Middleware  │─────────────►│ Validation  │     │
│  │ Generation  │              │ • Auth      │              │ • Token     │     │
│  │             │              │ • Encrypt   │              │ • Decrypt   │     │
│  │             │              │ • Log       │              │ • Process   │     │
│  └─────────────┘              └─────────────┘              └─────────────┘     │
│                                                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
```

## 🚀 Container Deployment Architecture

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                        Container Deployment                                    │
└─────────────────────────────────────────────────────────────────────────────────┘

                              Kubernetes Cluster
                    ┌─────────────────────────────────────┐
                    │            Ingress Controller       │
                    │          (Load Balancer)           │
                    └─────────────┬───────────────────────┘
                                  │
                    ┌─────────────▼───────────────────────┐
                    │         Gateway Service Pod         │
                    │      ┌─────────────────────┐       │
                    │      │   Gateway Container │       │
                    │      │   • Laravel Octane  │       │
                    │      │   • PHP 8.3         │       │
                    │      └─────────────────────┘       │
                    └─────────────────────────────────────┘

┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│   Auth Pod      │ │   User Pod      │ │  Order Pod      │ │ Payment Pod     │
│ ┌─────────────┐ │ │ ┌─────────────┐ │ │ ┌─────────────┐ │ │ ┌─────────────┐ │
│ │Auth Container│ │ │ │User Container│ │ │ │Order Container│ │ │Payment Cont.│ │
│ │• Laravel    │ │ │ │• Laravel    │ │ │ │• Laravel    │ │ │ │• Laravel    │ │
│ │• PHP 8.3    │ │ │ │• PHP 8.3    │ │ │ │• PHP 8.3    │ │ │ │• PHP 8.3    │ │
│ │• Redis      │ │ │ │• Redis      │ │ │ │• Redis      │ │ │ │• Redis      │ │
│ └─────────────┘ │ │ └─────────────┘ │ │ └─────────────┘ │ │ └─────────────┘ │
└─────────────────┘ └─────────────────┘ └─────────────────┘ └─────────────────┘

                    ┌─────────────────────────────────────┐
                    │         Infrastructure Layer        │
                    │                                     │
                    │ ┌─────────────┐ ┌─────────────┐    │
                    │ │  Database   │ │   Redis     │    │
                    │ │  Cluster    │ │  Cluster    │    │
                    │ │ (MySQL 8.0) │ │ (Cache/Sess)│    │
                    │ └─────────────┘ └─────────────┘    │
                    │                                     │
                    │ ┌─────────────┐ ┌─────────────┐    │
                    │ │  Message    │ │ Monitoring  │    │
                    │ │  Queue      │ │ Stack       │    │
                    │ │ (Laravel    │ │ (Prometheus │    │
                    │ │  Horizon)   │ │  + Grafana) │    │
                    │ └─────────────┘ └─────────────┘    │
                    └─────────────────────────────────────┘
```

## 📊 Health Monitoring Architecture

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                         Health Monitoring System                               │
└─────────────────────────────────────────────────────────────────────────────────┘

                              Monitoring Dashboard
                              ┌─────────────────┐
                              │     Grafana     │
                              │   Dashboards    │
                              └─────────┬───────┘
                                        │
                              ┌─────────▼───────┐
                              │   Prometheus    │
                              │ Metrics Server  │
                              └─────────┬───────┘
                                        │
                    ┌───────────────────┼───────────────────┐
                    │                   │                   │
            ┌───────▼───────┐   ┌───────▼───────┐   ┌───────▼───────┐
            │ Service Health│   │Database Health│   │  RPC Health   │
            │   Monitors    │   │   Monitors    │   │   Monitors    │
            │               │   │               │   │               │
            │• Endpoint     │   │• Connection   │   │• Call Success │
            │  Availability │   │  Pool Status  │   │• Response Time│
            │• Response     │   │• Query Perf   │   │• Error Rate   │
            │  Times        │   │• Failover     │   │• Correlation  │
            │• Error Rates  │   │  Events       │   │  Tracking     │
            └───────────────┘   └───────────────┘   └───────────────┘

┌─────────────────────────────────────────────────────────────────────────────────┐
│                           Alert Management                                     │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐    ┌─────────────┐     │
│  │   Metric    │    │   Alert     │    │ Notification│    │  Response   │     │
│  │ Threshold   │───►│   Rules     │───►│  Channels   │───►│   Actions   │     │
│  │ Exceeded    │    │ Evaluation  │    │ (Slack/SMS) │    │ (Auto-heal) │     │
│  └─────────────┘    └─────────────┘    └─────────────┘    └─────────────┘     │
│                                                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
```

## 🔄 Data Flow Architecture

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                            Data Flow Diagram                                   │
└─────────────────────────────────────────────────────────────────────────────────┘

Client Request
      │
      ▼
┌─────────────┐
│Load Balancer│
└─────┬───────┘
      │
      ▼
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│   Gateway   │───►│    Auth     │───►│    User     │
│   Service   │    │   Service   │    │   Service   │
└─────┬───────┘    └─────────────┘    └─────────────┘
      │
      ▼
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│   Auction   │◄──►│   Bidding   │◄──►│    Order    │
│   Service   │    │   Service   │    │   Service   │
└─────────────┘    └─────────────┘    └─────┬───────┘
                                            │
                                            ▼
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│ Notification│◄───│   Payment   │◄───│  Analytics  │
│   Service   │    │   Service   │    │   Service   │
└─────────────┘    └─────────────┘    └─────────────┘

┌─────────────────────────────────────────────────────────────────────────────────┐
│                           Event Flow                                           │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  User Action ──► Gateway ──► Auth ──► Business Logic ──► Database              │
│       │                                     │                                  │
│       ▼                                     ▼                                  │
│  Event Queue ◄── Notification ◄── Analytics ◄── Event Triggers                │
│                                                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

These diagrams provide a comprehensive visual representation of the Reverse Tender Platform's architecture, covering all major components, communication patterns, and operational aspects.

