# Kubernetes Gateway API for RPC Microservices

## 🚀 Overview

This directory contains the complete Kubernetes Gateway API implementation for the Reverse Tender Platform's RPC microservices architecture. The Gateway API provides a modern, cloud-native approach to traffic routing that replaces traditional reverse proxy solutions.

## 📁 Directory Structure

```
deployment/gateway-api/
├── core/                   # Core Gateway API resources
│   ├── gatewayclass.yaml  # GatewayClass and configuration
│   ├── gateway.yaml       # Gateway with HTTP/HTTPS listeners
│   └── rbac.yaml          # RBAC permissions
├── routes/                # HTTPRoute resources for all services
│   ├── gateway-service-route.yaml
│   ├── auth-service-route.yaml
│   ├── user-service-route.yaml
│   └── all-services-routes.yaml
├── policies/              # Security and rate limiting policies
│   ├── security.yaml      # CORS, JWT, NetworkPolicy
│   └── rate-limiting.yaml # Rate limiting configuration
├── monitoring/            # Observability configuration
│   └── prometheus.yaml    # Metrics and Grafana dashboard
└── docs/                  # Documentation
    ├── GATEWAY_API_ARCHITECTURE.md
    ├── DEPLOYMENT_GUIDE.md
    └── README.md (this file)
```

## 🎯 Key Features

### Service-Specific RPC Endpoints
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

### Gateway Service Integration
The gateway-service (renamed from shared-service) provides:
- **Health Monitoring**: `ping()` and `check()` methods
- **Utility Functions**: UUID generation, password hashing, random strings
- **RPC Compliance**: Standard Sajya framework with proper middleware

## 🚀 Quick Start

### 1. Prerequisites
- Kubernetes 1.25+ with Gateway API CRDs
- Nginx Gateway Controller
- Prometheus and Grafana (optional, for monitoring)

### 2. Deploy Core Resources
```bash
kubectl apply -f core/
```

### 3. Deploy Routes
```bash
kubectl apply -f routes/
```

### 4. Deploy Policies (Optional)
```bash
kubectl apply -f policies/
```

### 5. Deploy Monitoring (Optional)
```bash
kubectl apply -f monitoring/
```

### 6. Verify Deployment
```bash
kubectl get gateway,httproute -n reversetender-prod
```

## 🔧 Configuration

### Gateway Configuration
- **Hostname**: `api.reversetender.sa`
- **Ports**: 80 (HTTP), 443 (HTTPS)
- **TLS**: Terminate mode with certificate management
- **Namespace**: `reversetender-prod`

### Service Routing
Each service has a dedicated HTTPRoute that:
- Matches service-specific path prefixes (`/rpc/{service}`)
- Rewrites URLs to standard `/rpc` endpoint
- Routes to appropriate backend service port
- Provides load balancing across replicas

### Security Features
- **CORS**: Configured for app and admin domains
- **Rate Limiting**: 1000 global, 100 per-user requests/minute
- **Network Policies**: Restricted ingress/egress
- **JWT Authentication**: Integration with auth service

## 📊 Monitoring

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

## 🔍 Testing

### Health Check
```bash
curl -H "Host: api.reversetender.sa" http://<GATEWAY_IP>/health/gateway
```

### RPC Request
```bash
curl -X POST -H "Host: api.reversetender.sa" \
     -H "Content-Type: application/json" \
     -d '{"jsonrpc":"2.0","method":"ping","id":1}' \
     http://<GATEWAY_IP>/rpc/gateway
```

### Load Balancing Test
```bash
for i in {1..10}; do
  curl -X POST -H "Host: api.reversetender.sa" \
       -H "Content-Type: application/json" \
       -d '{"jsonrpc":"2.0","method":"ping","id":'$i'}' \
       http://<GATEWAY_IP>/rpc/gateway
done
```

## 🛠️ Troubleshooting

### Common Issues

#### Gateway Not Ready
```bash
kubectl describe gateway rpc-gateway -n reversetender-prod
kubectl logs -l app=nginx-gateway-controller
```

#### Route Not Working
```bash
kubectl describe httproute <route-name> -n reversetender-prod
kubectl get events -n reversetender-prod
```

#### Backend Unavailable
```bash
kubectl get endpoints <service-name> -n reversetender-prod
kubectl logs -l app=<service-name> -n reversetender-prod
```

## 📚 Documentation

- **[Architecture Guide](docs/GATEWAY_API_ARCHITECTURE.md)**: Detailed architecture overview
- **[Deployment Guide](docs/DEPLOYMENT_GUIDE.md)**: Step-by-step deployment instructions
- **[Kubernetes Gateway API Docs](https://gateway-api.sigs.k8s.io/)**: Official Gateway API documentation

## 🔄 Migration from Nginx

### Benefits
- **Cloud-Native**: Kubernetes-native resources and configuration
- **Dynamic Updates**: Configuration changes without restarts
- **Better Integration**: Native service discovery and DNS
- **Policy-Based Security**: Fine-grained access control
- **GitOps Friendly**: Version-controlled YAML configuration

### Compatibility
- Maintains existing RPC semantics
- Preserves service port assignments
- Compatible with existing health checks
- No client-side changes required

## 🚀 Advanced Features

### Traffic Splitting (Future)
```yaml
backendRefs:
- name: service-v1
  port: 8001
  weight: 90
- name: service-v2
  port: 8001
  weight: 10
```

### Request Transformation (Future)
```yaml
filters:
- type: RequestHeaderModifier
  requestHeaderModifier:
    add:
    - name: X-Gateway-Version
      value: "v1.0"
```

## 🤝 Contributing

1. Follow the existing YAML structure and naming conventions
2. Update documentation when adding new features
3. Test changes in a development environment first
4. Ensure monitoring and observability are maintained

## 📝 License

This configuration is part of the Reverse Tender Platform and follows the project's licensing terms.

---

**🎯 This Gateway API implementation provides a robust, scalable, and maintainable foundation for the RPC microservices platform while leveraging modern Kubernetes-native capabilities.**

