# Kubernetes Gateway API Configuration

This directory contains the **Kubernetes Gateway API** manifests for the Reverse Tender Platform, providing a modern, generic, and extensible approach to traffic routing.

## 🚀 **What is Gateway API?**

Gateway API is the **next generation** of Kubernetes Ingress, Load Balancing, and Service Mesh APIs. It provides:

- **Role-oriented design** - Clear separation between infrastructure and application concerns
- **Generic and expressive** - More powerful routing capabilities than traditional Ingress
- **Extensible** - Support for advanced features like traffic splitting, header manipulation, and policy attachment
- **Vendor-neutral** - Works with multiple gateway controllers (NGINX, Istio, Envoy, etc.)

## 📁 **File Structure**

```
gateway-api/
├── README.md              # This documentation
├── gatewayclass.yaml      # Gateway controller configuration
├── gateway.yaml           # Gateway listeners and TLS configuration
├── httproutes.yaml        # HTTP routing rules for all services
└── policies.yaml          # Traffic policies (timeouts, retries, health checks)
```

## 🏗️ **Architecture Overview**

### **3-Layer Architecture:**

1. **GatewayClass** (`gatewayclass.yaml`)
   - Defines which controller manages the Gateway
   - Contains controller-specific configuration
   - **Generic**: Can be reused across environments

2. **Gateway** (`gateway.yaml`)
   - Defines listeners (HTTPS, HTTP, WebSocket)
   - Configures TLS termination
   - **Generic**: Uses wildcards and parameterizable hostnames

3. **HTTPRoutes** (`httproutes.yaml`)
   - Defines routing rules for each service
   - Includes traffic policies and header manipulation
   - **Generic**: Service routes can be easily modified

## 🔧 **Generic Design Principles**

### ✅ **What Makes These Manifests Generic:**

1. **Parameterizable Hostnames**
   ```yaml
   hostnames:
     - "*.reversetender.com"  # Wildcard for flexibility
     - "api.reversetender.com"  # Can be overridden in overlays
   ```

2. **Reusable Service Patterns**
   ```yaml
   # Each service follows the same pattern
   - matches:
       - path:
           type: PathPrefix
           value: /auth  # Service-specific path
     backendRefs:
       - name: auth-service  # Service-specific backend
         port: 80
         weight: 100
   ```

3. **Environment-Agnostic Configuration**
   - Controller names can be overridden in overlays
   - Resource limits and replicas are configurable
   - TLS certificates reference generic secret names

4. **Policy-Based Traffic Management**
   - Generic timeout, retry, and health check policies
   - Applied consistently across all services
   - Easy to customize per environment

## 🌍 **Environment Customization**

### **Development Environment Example:**
```yaml
# overlays/dev/gateway-patch.yaml
apiVersion: gateway.networking.k8s.io/v1
kind: Gateway
metadata:
  name: reverse-tender-gateway
spec:
  listeners:
    - name: https-api
      hostname: "*.dev.reversetender.com"  # Dev-specific hostname
```

### **Production Environment Example:**
```yaml
# overlays/production/gatewayclass-patch.yaml
apiVersion: gateway.networking.k8s.io/v1
kind: GatewayClass
metadata:
  name: reverse-tender-gateway-class
spec:
  controllerName: istio.io/gateway-controller  # Production controller
```

## 🚦 **Traffic Routing Features**

### **Supported Routing Patterns:**

1. **Path-Based Routing**
   - `/auth` → auth-service
   - `/auctions` → auction-service
   - `/payments` → payment-service

2. **Host-Based Routing**
   - `api.reversetender.com` → API services
   - `app.reversetender.com` → Frontend application
   - `admin.reversetender.com` → Admin panel

3. **Protocol-Specific Routing**
   - HTTPS for API and web traffic
   - WebSocket for real-time bidding
   - HTTP to HTTPS redirects

4. **Header-Based Routing**
   - WebSocket upgrade detection
   - Service identification headers
   - Request tracing headers

## 🛡️ **Security Features**

### **Built-in Security:**

1. **TLS Termination**
   ```yaml
   tls:
     mode: Terminate
     certificateRefs:
       - name: tls-secret  # Generic certificate reference
   ```

2. **Automatic HTTPS Redirects**
   ```yaml
   filters:
     - type: RequestRedirect
       requestRedirect:
         scheme: https
         statusCode: 301
   ```

3. **Request Header Injection**
   ```yaml
   filters:
     - type: RequestHeaderModifier
       requestHeaderModifier:
         add:
           - name: X-Service-Route
             value: auth-service  # Service identification
   ```

## 📊 **Traffic Policies**

### **Generic Policies Applied:**

1. **Timeout Policy**
   - Request timeout: 30s
   - Backend timeout: 25s
   - Applied to all HTTPRoutes

2. **Retry Policy**
   - 3 retry attempts
   - Exponential backoff (1s to 10s)
   - Retry on 5xx, connection failures

3. **Health Check Policy**
   - Path: `/health`
   - Interval: 30s
   - Timeout: 5s

4. **Load Balancing Policy**
   - Session persistence with cookies
   - Applied to all backend services

## 🔄 **Migration from Ingress**

### **Benefits Over Traditional Ingress:**

| Feature | Ingress | Gateway API |
|---------|---------|-------------|
| **Configuration** | Annotation-based | Resource-based |
| **Extensibility** | Limited | Highly extensible |
| **Multi-tenancy** | Basic | Advanced |
| **Traffic Policies** | Controller-specific | Standardized |
| **Role Separation** | Mixed | Clear separation |

### **Migration Steps:**

1. **Install Gateway API CRDs**
   ```bash
   kubectl apply -f https://github.com/kubernetes-sigs/gateway-api/releases/download/v1.0.0/standard-install.yaml
   ```

2. **Deploy Gateway Controller**
   ```bash
   # Example for NGINX Gateway
   kubectl apply -f https://github.com/nginxinc/nginx-gateway-fabric/releases/download/v1.0.0/nginx-gateway-fabric.yaml
   ```

3. **Apply Gateway API Manifests**
   ```bash
   kubectl apply -k deployment/k8s/overlays/dev/
   ```

4. **Gradually Migrate Traffic**
   - Start with non-critical services
   - Use traffic splitting for gradual migration
   - Monitor and validate routing behavior

## 🔍 **Troubleshooting**

### **Common Issues:**

1. **Gateway Not Ready**
   ```bash
   kubectl describe gateway reverse-tender-gateway -n reverse-tender
   ```

2. **HTTPRoute Not Accepted**
   ```bash
   kubectl describe httproute api-routes -n reverse-tender
   ```

3. **Policy Not Applied**
   ```bash
   kubectl describe timeoutpolicy generic-timeout -n reverse-tender
   ```

### **Validation Commands:**

```bash
# Check Gateway status
kubectl get gateway -n reverse-tender

# Check HTTPRoute status
kubectl get httproute -n reverse-tender

# Check policy attachment
kubectl get backendlbpolicy,healthcheckpolicy,timeoutpolicy,retrypolicy -n reverse-tender
```

## 📚 **Additional Resources**

- [Gateway API Official Documentation](https://gateway-api.sigs.k8s.io/)
- [Gateway API Implementations](https://gateway-api.sigs.k8s.io/implementations/)
- [Migration Guide from Ingress](https://gateway-api.sigs.k8s.io/guides/migrating-from-ingress/)
- [Traffic Policies Guide](https://gateway-api.sigs.k8s.io/guides/traffic-splitting/)

## 🎯 **Next Steps**

1. **Choose Gateway Controller** - Select appropriate controller for your environment
2. **Customize Overlays** - Create environment-specific configurations
3. **Add More Policies** - Implement rate limiting, circuit breakers, etc.
4. **Monitor Traffic** - Set up observability for Gateway API resources
5. **Gradual Migration** - Move from Ingress to Gateway API incrementally
