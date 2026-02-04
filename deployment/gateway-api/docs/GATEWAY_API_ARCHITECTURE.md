# Kubernetes Gateway API Architecture for RPC Microservices

## Overview

This document describes the implementation of Kubernetes Gateway API for the Reverse Tender Platform's RPC microservices architecture. The Gateway API provides a modern, cloud-native approach to traffic routing that replaces traditional reverse proxy solutions.

## Architecture Components

### 1. Gateway API Resources

#### GatewayClass
- **Name**: `rpc-gateway-class`
- **Controller**: `gateway.nginx.org/nginx-gateway-controller`
- **Purpose**: Defines the gateway implementation and configuration

#### Gateway
- **Name**: `rpc-gateway`
- **Listeners**: HTTP (80) and HTTPS (443)
- **Hostname**: `api.reversetender.sa`
- **TLS**: Terminate mode with certificate management

### 2. Service-Specific RPC Endpoints

The Gateway API implements service-specific RPC endpoints that solve the traditional RPC routing challenge:

```
/rpc/auth         → auth-service:8001/rpc
/rpc/users        → user-service:8002/rpc
/rpc/analytics    → analytics-service:8003/rpc
/rpc/orders       → order-service:8004/rpc
/rpc/payments     → payment-service:8005/rpc
/rpc/bidding      → bidding-service:8006/rpc
/rpc/notifications → notification-service:8007/rpc
/rpc/vin-ocr      → vin-ocr-service:8008/rpc
/rpc/gateway      → gateway-service:8009/rpc
```

### 3. HTTPRoute Configuration

Each service has a dedicated HTTPRoute resource that:
- Matches service-specific path prefixes
- Rewrites URLs to standard `/rpc` endpoint
- Routes to appropriate backend service
- Provides load balancing across replicas

### 4. Gateway Service (Renamed from shared-service)

The gateway-service provides essential infrastructure:

#### Health Monitoring
- `ping()`: Basic service health check
- `check()`: Comprehensive infrastructure monitoring
  - Database connectivity
  - Redis availability
  - Cache functionality
  - Memory and disk usage
  - Octane server status

#### Utility Functions
- `generateUuid()`: Correlation ID generation
- `hashPassword()`: Security operations
- `verifyPassword()`: Authentication support
- `generateRandomString()`: Token generation

## Key Advantages

### Cloud-Native Benefits
- **Declarative Configuration**: YAML-based resource definitions
- **Dynamic Updates**: Configuration changes without restarts
- **Kubernetes Integration**: Native service discovery and DNS
- **RBAC Support**: Kubernetes-native access control

### Operational Benefits
- **GitOps Friendly**: Version-controlled configuration
- **Better Observability**: Native Kubernetes metrics
- **Service Mesh Ready**: Integration with Istio/Linkerd
- **Policy-Based Security**: Fine-grained access control

### Performance Benefits
- **Efficient Routing**: Direct Kubernetes service communication
- **Load Balancing**: Built-in across service replicas
- **Health Checking**: Automatic unhealthy endpoint removal
- **Connection Pooling**: Optimized backend connections

## Security Features

### Network Policies
- Ingress: Only from ingress-nginx namespace
- Egress: Only to backend services on specific ports
- Pod-to-pod communication restrictions

### Rate Limiting
- Global: 1000 requests/minute
- Per-user: 100 requests/minute based on X-User-ID header
- Configurable limits per service

### CORS Configuration
- Allowed origins: app.reversetender.sa, admin.reversetender.sa
- Allowed methods: GET, POST, OPTIONS
- Required headers: Content-Type, Authorization, X-User-ID

### JWT Authentication
- Issuer: auth.reversetender.sa
- Audience: reversetender-api
- JWKS endpoint for key validation

## Monitoring and Observability

### Prometheus Metrics
- Request rate per service
- Response time percentiles
- Error rates and status codes
- Gateway-specific metrics

### Grafana Dashboard
- Real-time request monitoring
- Performance visualization
- Error tracking and alerting
- Service health overview

## Deployment Strategy

### Prerequisites
- Kubernetes cluster with Gateway API CRDs
- Nginx Gateway Controller installed
- Prometheus and Grafana for monitoring
- TLS certificates for HTTPS

### Deployment Order
1. Core resources (GatewayClass, Gateway, RBAC)
2. HTTPRoute resources for all services
3. Security policies and rate limiting
4. Monitoring configuration

### Validation
- Verify Gateway status is Ready
- Test service-specific endpoints
- Validate health check integration
- Monitor metrics and logs

## Migration from Traditional Proxy

### Benefits Over Nginx
- No custom configuration files
- Automatic service discovery
- Dynamic configuration updates
- Better Kubernetes integration
- Policy-based security model

### Compatibility
- Maintains existing RPC semantics
- Preserves service port assignments
- Compatible with existing health checks
- No client-side changes required

## Troubleshooting

### Common Issues
- Gateway not Ready: Check GatewayClass and controller
- Route not working: Verify HTTPRoute parentRefs
- Backend unavailable: Check service endpoints
- TLS issues: Validate certificate configuration

### Debugging Commands
```bash
kubectl get gateway -n reversetender-prod
kubectl describe httproute -n reversetender-prod
kubectl logs -l app=nginx-gateway-controller
kubectl get endpoints -n reversetender-prod
```

## Future Enhancements

### Planned Features
- Advanced traffic splitting for A/B testing
- Circuit breaker integration
- Request/response transformation
- Enhanced security policies
- Multi-cluster routing support

This architecture provides a robust, scalable, and maintainable foundation for the RPC microservices platform while leveraging modern Kubernetes-native capabilities.

