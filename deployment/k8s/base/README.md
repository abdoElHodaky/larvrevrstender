# Kubernetes Configuration Architecture

This directory contains the base Kubernetes configurations for the Reverse Tender Platform using a layered configuration approach.

## Configuration Structure

### 🏗️ **Configuration Layers**

The configuration follows a hierarchical structure with multiple layers:

1. **Common Configuration** (`common-config.yaml`)
   - Shared settings used by all services
   - Database, Redis, logging, and Laravel Octane configurations
   - Application-wide defaults

2. **Generic API Service Configuration** (`api-service-config.yaml`)
   - Reusable patterns for any service acting as an API endpoint
   - Authentication, caching, monitoring, and security configurations
   - Can be inherited by any service

3. **Service-Specific Configurations**
   - Individual configuration files for each microservice
   - Contains only service-specific settings
   - Examples: `auth-service-config.yaml`, `auction-service-config.yaml`, `payment-service-config.yaml`

4. **Cross-Cutting Concern Configurations**
   - Specialized configurations for distributed system patterns
   - `saga-config.yaml` - Distributed transaction coordination
   - `service-discovery-config.yaml` - Service registry and discovery
   - `resilience-config.yaml` - Circuit breakers, retries, timeouts
   - `event-bus-config.yaml` - Inter-service event communication

### 📁 **File Organization**

```
deployment/k8s/base/
├── README.md                           # This documentation
├── kustomization.yaml                  # Kustomize configuration
├── namespace.yaml                      # Kubernetes namespace
│
├── # Common Configurations
├── common-config.yaml                  # Shared application settings
├── api-service-config.yaml            # Generic API service template
│
├── # Service-Specific Configurations
├── gateway-config.yaml                 # Gateway service settings
├── auth-service-config.yaml           # Authentication service settings
├── auction-service-config.yaml        # Auction service settings
├── payment-service-config.yaml        # Payment service settings
│
├── # Cross-Cutting Configurations
├── saga-config.yaml                   # Distributed transactions
├── service-discovery-config.yaml     # Service registry
├── resilience-config.yaml            # Fault tolerance
├── event-bus-config.yaml             # Event communication
│
├── # Secrets
├── secrets/
│   └── app-secrets.yaml              # Application secrets
│
└── # Kubernetes Resources
    ├── deployments.yaml               # Service deployments
    ├── services.yaml                  # Kubernetes services
    ├── ingress.yaml                   # Ingress configuration
    ├── hpa.yaml                       # Horizontal Pod Autoscaler
    ├── pdb.yaml                       # Pod Disruption Budget
    ├── networkpolicy.yaml             # Network policies
    ├── serviceaccount.yaml            # Service accounts
    └── rbac.yaml                      # Role-based access control
```

## 🔧 **Configuration Inheritance**

Services inherit configurations in the following order (later configurations override earlier ones):

1. **Common Configuration** - Base settings for all services
2. **API Service Configuration** - Generic API patterns (if applicable)
3. **Service-Specific Configuration** - Individual service settings
4. **Environment Overlays** - Environment-specific overrides (dev/staging/prod)

### Example: Gateway Service Configuration

The gateway service inherits configurations as follows:

```yaml
# 1. common-config.yaml (base settings)
APP_NAME: "Reverse Tender Platform"
DB_CONNECTION: "pgsql"
CACHE_DRIVER: "redis"

# 2. api-service-config.yaml (generic API patterns)
API_RATE_LIMIT: "60"
AUTH_ENABLED: "true"
METRICS_ENABLED: "true"

# 3. gateway-config.yaml (gateway-specific settings)
SERVICE_NAME: "gateway-service"
SERVICE_ROLE: "main_entry_point"
CACHE_PREFIX: "gateway:"
```

## 🚀 **Adding New Services**

To add a new service configuration:

1. **Create Service-Specific ConfigMap**:
   ```yaml
   # my-service-config.yaml
   apiVersion: v1
   kind: ConfigMap
   metadata:
     name: my-service-config
     labels:
       app.kubernetes.io/component: my-service
   data:
     SERVICE_NAME: "my-service"
     SERVICE_PORT: "8010"
     # Add service-specific configurations
   ```

2. **Update kustomization.yaml**:
   ```yaml
   resources:
     - my-service-config.yaml
   ```

3. **Add to Service Discovery** (if needed):
   Update `service-discovery-config.yaml` with the new service endpoints.

## 🎯 **Configuration Best Practices**

### ✅ **Do:**
- Use the layered approach for configuration inheritance
- Keep service-specific settings in individual ConfigMaps
- Use descriptive configuration keys
- Document configuration purposes with comments
- Use environment overlays for environment-specific values

### ❌ **Don't:**
- Put service-specific settings in common configurations
- Duplicate configurations across multiple files
- Hardcode environment-specific values in base configurations
- Mix secrets with regular configuration data

## 🔐 **Secrets Management**

Sensitive data is stored separately in `secrets/app-secrets.yaml`:

- Database passwords
- API keys
- JWT secrets
- External service credentials

**Note**: Replace placeholder values with actual secrets before deployment.

## 🌍 **Environment Overlays**

Environment-specific configurations are managed through Kustomize overlays:

- `overlays/dev/` - Development environment
- `overlays/staging/` - Staging environment  
- `overlays/production/` - Production environment

Each overlay can override base configurations as needed.

## 📊 **Configuration Categories**

### **Service Identity**
- `SERVICE_NAME` - Unique service identifier
- `SERVICE_PORT` - Service port number
- `SERVICE_ROLE` - Service function description

### **API Configuration**
- Rate limiting settings
- Timeout configurations
- Request/response handling

### **Security Configuration**
- Authentication settings
- CORS policies
- Rate limiting rules

### **Monitoring Configuration**
- Metrics collection
- Distributed tracing
- Health check settings

### **Cache Configuration**
- Cache strategies
- TTL settings
- Cache prefixes

## 🔄 **Configuration Updates**

When updating configurations:

1. Update the appropriate ConfigMap file
2. Test changes in development environment
3. Apply changes through Kustomize overlays
4. Monitor service behavior after deployment

## 📚 **Related Documentation**

- [Kustomize Documentation](https://kustomize.io/)
- [Kubernetes ConfigMaps](https://kubernetes.io/docs/concepts/configuration/configmap/)
- [Laravel Configuration](https://laravel.com/docs/configuration)
