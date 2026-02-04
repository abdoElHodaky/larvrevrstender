# Kubernetes Gateway API Deployment Guide

## Prerequisites

### 1. Kubernetes Cluster Requirements
- Kubernetes 1.25+ with Gateway API support
- Gateway API CRDs installed
- Nginx Gateway Controller deployed
- Prometheus and Grafana for monitoring

### 2. Install Gateway API CRDs
```bash
kubectl apply -f https://github.com/kubernetes-sigs/gateway-api/releases/download/v1.0.0/standard-install.yaml
```

### 3. Install Nginx Gateway Controller
```bash
kubectl apply -f https://raw.githubusercontent.com/nginxinc/nginx-gateway-fabric/main/deploy/manifests/nginx-gateway.yaml
```

## Deployment Steps

### Step 1: Deploy Core Resources
```bash
# Deploy GatewayClass and Gateway
kubectl apply -f deployment/gateway-api/core/gatewayclass.yaml
kubectl apply -f deployment/gateway-api/core/gateway.yaml
kubectl apply -f deployment/gateway-api/core/rbac.yaml
```

### Step 2: Verify Gateway Status
```bash
kubectl get gateway rpc-gateway -n reversetender-prod
kubectl describe gateway rpc-gateway -n reversetender-prod
```

Expected output:
```
NAME          CLASS               ADDRESS   PROGRAMMED   AGE
rpc-gateway   rpc-gateway-class   <IP>      True         1m
```

### Step 3: Deploy HTTPRoute Resources
```bash
# Deploy individual service routes
kubectl apply -f deployment/gateway-api/routes/gateway-service-route.yaml
kubectl apply -f deployment/gateway-api/routes/auth-service-route.yaml
kubectl apply -f deployment/gateway-api/routes/user-service-route.yaml
kubectl apply -f deployment/gateway-api/routes/all-services-routes.yaml
```

### Step 4: Deploy Security Policies
```bash
kubectl apply -f deployment/gateway-api/policies/security.yaml
kubectl apply -f deployment/gateway-api/policies/rate-limiting.yaml
```

### Step 5: Deploy Monitoring
```bash
kubectl apply -f deployment/gateway-api/monitoring/prometheus.yaml
```

## Validation and Testing

### 1. Check Gateway Status
```bash
kubectl get gateway,httproute -n reversetender-prod
```

### 2. Test Service Endpoints
```bash
# Test gateway service health
curl -H "Host: api.reversetender.sa" http://<GATEWAY_IP>/health/gateway

# Test RPC endpoints
curl -X POST -H "Host: api.reversetender.sa" \
     -H "Content-Type: application/json" \
     -d '{"jsonrpc":"2.0","method":"ping","id":1}' \
     http://<GATEWAY_IP>/rpc/gateway

# Test other services
curl -X POST -H "Host: api.reversetender.sa" \
     -H "Content-Type: application/json" \
     -d '{"jsonrpc":"2.0","method":"healthCheck","id":1}' \
     http://<GATEWAY_IP>/rpc/auth
```

### 3. Verify Load Balancing
```bash
# Check service endpoints
kubectl get endpoints -n reversetender-prod | grep -E "(auth|user|gateway)-service"

# Test multiple requests to verify load balancing
for i in {1..10}; do
  curl -X POST -H "Host: api.reversetender.sa" \
       -H "Content-Type: application/json" \
       -d '{"jsonrpc":"2.0","method":"ping","id":'$i'}' \
       http://<GATEWAY_IP>/rpc/gateway
done
```

### 4. Test Rate Limiting
```bash
# Generate high request volume to test rate limiting
for i in {1..1100}; do
  curl -H "Host: api.reversetender.sa" \
       http://<GATEWAY_IP>/rpc/gateway &
done
wait

# Check for 429 Too Many Requests responses
```

## Service Configuration Updates

### Update Gateway Service Deployment
The gateway-service (renamed from shared-service) needs to be deployed with updated configuration:

```bash
kubectl apply -f deployment/kubernetes/production/gateway-service-deployment.yaml
```

### Verify Service Discovery
```bash
kubectl get service gateway-service-rpc -n reversetender-prod
kubectl describe service gateway-service-rpc -n reversetender-prod
```

## Monitoring Setup

### 1. Verify Prometheus Targets
```bash
kubectl port-forward -n monitoring svc/prometheus 9090:9090
# Open http://localhost:9090/targets
# Look for gateway-related targets
```

### 2. Import Grafana Dashboard
```bash
kubectl get configmap gateway-grafana-dashboard -n reversetender-prod -o yaml
# Import the dashboard JSON into Grafana
```

### 3. Key Metrics to Monitor
- `gateway_requests_total`: Total requests per service
- `gateway_request_duration_seconds`: Request latency
- `gateway_active_connections`: Active connections
- `gateway_upstream_response_time`: Backend response time

## Troubleshooting

### Gateway Not Ready
```bash
kubectl describe gateway rpc-gateway -n reversetender-prod
kubectl logs -l app=nginx-gateway-controller -n nginx-gateway
```

### HTTPRoute Not Working
```bash
kubectl describe httproute <route-name> -n reversetender-prod
kubectl get events -n reversetender-prod --sort-by='.lastTimestamp'
```

### Backend Service Unavailable
```bash
kubectl get endpoints <service-name> -n reversetender-prod
kubectl describe service <service-name> -n reversetender-prod
kubectl logs -l app=<service-name> -n reversetender-prod
```

### TLS Certificate Issues
```bash
kubectl describe secret reversetender-tls-cert -n reversetender-prod
kubectl logs -l app=cert-manager -n cert-manager
```

## Rollback Procedure

### Emergency Rollback
If issues occur, you can quickly rollback to the previous configuration:

```bash
# Remove Gateway API resources
kubectl delete -f deployment/gateway-api/routes/
kubectl delete -f deployment/gateway-api/core/gateway.yaml

# Restore previous nginx configuration (if available)
kubectl apply -f deployment/nginx/nginx-config.yaml
```

### Gradual Migration
For production environments, consider a gradual migration:

1. Deploy Gateway API alongside existing proxy
2. Route test traffic through Gateway API
3. Gradually shift production traffic
4. Monitor metrics and performance
5. Complete migration when confident

## Performance Tuning

### Gateway Configuration
```yaml
# In gatewayclass.yaml ConfigMap
upstream_keepalive_connections: 64
upstream_keepalive_requests: 1000
upstream_keepalive_timeout: 120s
proxy_connect_timeout: 30s
proxy_send_timeout: 30s
proxy_read_timeout: 30s
```

### Resource Limits
```yaml
# In gateway deployment
resources:
  requests:
    cpu: 500m
    memory: 512Mi
  limits:
    cpu: 2000m
    memory: 2Gi
```

### Horizontal Pod Autoscaling
```bash
kubectl autoscale deployment nginx-gateway-controller \
  --cpu-percent=70 --min=2 --max=10 -n nginx-gateway
```

This deployment guide ensures a smooth transition to the Kubernetes Gateway API architecture while maintaining high availability and performance for the RPC microservices platform.

