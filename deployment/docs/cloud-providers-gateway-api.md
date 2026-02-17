# Cloud Provider Gateway API Requirements

## Overview

This document outlines the specific requirements and configurations needed to implement Kubernetes Gateway API across different cloud providers, building upon the existing RPC-optimized Gateway API foundation.

## Google Cloud Platform (GKE)

### **Gateway Controller**
- **Controller**: `gke.io/gateway-controller` (GKE Gateway)
- **Alternative**: `istio.io/gateway-controller` (Istio on GKE)

### **GatewayClass Configuration**
```yaml
apiVersion: gateway.networking.k8s.io/v1
kind: GatewayClass
metadata:
  name: gke-gateway-class
spec:
  controllerName: gke.io/gateway-controller
  parametersRef:
    group: networking.gke.io
    kind: GCPGatewayPolicy
    name: gcp-gateway-policy
```

### **Load Balancer Types**
- **External**: Global HTTP(S) Load Balancer
- **Internal**: Regional Internal HTTP(S) Load Balancer
- **Regional**: Regional External HTTP(S) Load Balancer

### **Required Annotations**
```yaml
# Gateway annotations
networking.gke.io/load-balancer-type: "EXTERNAL_MANAGED"  # or INTERNAL_MANAGED
networking.gke.io/load-balancer-ip-addresses: "static-ip-name"
cloud.google.com/neg: '{"ingress": true}'

# Service annotations  
cloud.google.com/backend-config: '{"default": "backend-config-name"}'
cloud.google.com/load-balancer-type: "External"
```

### **SSL Certificate Management**
- **Google-managed certificates**: Automatic provisioning and renewal
- **Self-managed certificates**: Manual certificate management
```yaml
networking.gke.io/managed-certificates: "ssl-cert-name"
```

### **Prerequisites**
- GKE cluster with Gateway API enabled
- Workload Identity configured
- Cloud DNS for domain management
- Static IP addresses reserved

---

## Microsoft Azure (AKS)

### **Gateway Controller**
- **Controller**: `azure/application-gateway` (Application Gateway Ingress Controller - AGIC)
- **Alternative**: `nginx.org/nginx-gateway-fabric`

### **GatewayClass Configuration**
```yaml
apiVersion: gateway.networking.k8s.io/v1
kind: GatewayClass
metadata:
  name: azure-application-gateway
spec:
  controllerName: azure/application-gateway
  parametersRef:
    group: appgw.ingress.azure.io
    kind: AzureApplicationGatewayConfig
    name: azure-gateway-config
```

### **Load Balancer Integration**
- **Application Gateway**: Layer 7 load balancing with WAF
- **Azure Load Balancer**: Layer 4 load balancing
- **Azure Front Door**: Global load balancing

### **Required Annotations**
```yaml
# Application Gateway annotations
appgw.ingress.kubernetes.io/backend-path-prefix: "/"
appgw.ingress.kubernetes.io/ssl-redirect: "true"
appgw.ingress.kubernetes.io/connection-draining: "true"
appgw.ingress.kubernetes.io/connection-draining-timeout: "30"

# Service annotations
service.beta.kubernetes.io/azure-load-balancer-resource-group: "rg-name"
service.beta.kubernetes.io/azure-load-balancer-internal: "false"
```

### **SSL Certificate Management**
- **Azure Key Vault**: Certificate storage and management
- **Let's Encrypt**: Automatic certificate provisioning
```yaml
appgw.ingress.kubernetes.io/appgw-ssl-certificate: "keyvault-cert-name"
```

### **Prerequisites**
- AKS cluster with AGIC enabled
- Application Gateway provisioned
- Azure Key Vault for certificates
- Managed Identity configured

---

## DigitalOcean (DOKS)

### **Gateway Controller**
- **Controller**: `nginx.org/nginx-gateway-fabric`
- **Alternative**: `envoy-gateway.io/gateway-controller`

### **GatewayClass Configuration**
```yaml
apiVersion: gateway.networking.k8s.io/v1
kind: GatewayClass
metadata:
  name: digitalocean-nginx
spec:
  controllerName: nginx.org/nginx-gateway-fabric
  parametersRef:
    group: gateway.nginx.org
    kind: NginxGateway
    name: do-nginx-config
```

### **Load Balancer Integration**
- **DigitalOcean Load Balancer**: Managed load balancing service
- **NodePort**: Direct node access (development)

### **Required Annotations**
```yaml
# Load Balancer annotations
service.beta.kubernetes.io/do-loadbalancer-protocol: "http"
service.beta.kubernetes.io/do-loadbalancer-algorithm: "round_robin"
service.beta.kubernetes.io/do-loadbalancer-size-slug: "lb-small"
service.beta.kubernetes.io/do-loadbalancer-hostname: "api.reversetender.com"
service.beta.kubernetes.io/do-loadbalancer-certificate-id: "cert-id"
service.beta.kubernetes.io/do-loadbalancer-redirect-http-to-https: "true"
```

### **SSL Certificate Management**
- **DigitalOcean Certificates**: Managed SSL certificates
- **Let's Encrypt**: Free SSL certificates
```yaml
service.beta.kubernetes.io/do-loadbalancer-certificate-id: "your-cert-id"
```

### **Prerequisites**
- DOKS cluster
- DigitalOcean Load Balancer
- SSL certificates in DigitalOcean
- DNS configuration

---

## Linode (LKE)

### **Gateway Controller**
- **Controller**: `nginx.org/nginx-gateway-fabric`
- **Alternative**: `envoy-gateway.io/gateway-controller`

### **GatewayClass Configuration**
```yaml
apiVersion: gateway.networking.k8s.io/v1
kind: GatewayClass
metadata:
  name: linode-nginx
spec:
  controllerName: nginx.org/nginx-gateway-fabric
  parametersRef:
    group: gateway.nginx.org
    kind: NginxGateway
    name: linode-nginx-config
```

### **Load Balancer Integration**
- **Linode NodeBalancer**: Managed load balancing service
- **Cloud Controller Manager**: Automatic NodeBalancer provisioning

### **Required Annotations**
```yaml
# NodeBalancer annotations
service.beta.kubernetes.io/linode-loadbalancer-throttle: "20"
service.beta.kubernetes.io/linode-loadbalancer-region: "us-east"
service.beta.kubernetes.io/linode-loadbalancer-hostname-only-ingress: "true"
service.beta.kubernetes.io/linode-loadbalancer-check-type: "http"
service.beta.kubernetes.io/linode-loadbalancer-check-path: "/health"
```

### **SSL Certificate Management**
- **Manual certificates**: Upload to Linode
- **Let's Encrypt**: Cert-manager integration
```yaml
service.beta.kubernetes.io/linode-loadbalancer-tls: '[{"tls-secret-name": "api-tls"}]'
```

### **Prerequisites**
- LKE cluster
- Linode Cloud Controller Manager
- SSL certificates
- DNS configuration

---

## OpenStack

### **Gateway Controller**
- **Controller**: `nginx.org/nginx-gateway-fabric`
- **Alternative**: `envoy-gateway.io/gateway-controller`

### **GatewayClass Configuration**
```yaml
apiVersion: gateway.networking.k8s.io/v1
kind: GatewayClass
metadata:
  name: openstack-nginx
spec:
  controllerName: nginx.org/nginx-gateway-fabric
  parametersRef:
    group: gateway.nginx.org
    kind: NginxGateway
    name: openstack-nginx-config
```

### **Load Balancer Integration**
- **Octavia Load Balancer**: OpenStack LBaaS v2
- **Neutron Load Balancer**: Network load balancing

### **Required Annotations**
```yaml
# OpenStack Load Balancer annotations
service.beta.kubernetes.io/openstack-internal-load-balancer: "false"
loadbalancer.openstack.org/class: "amphora"
loadbalancer.openstack.org/load-balancer-id: "lb-id"
loadbalancer.openstack.org/member-subnet-id: "subnet-id"
loadbalancer.openstack.org/network-id: "network-id"
```

### **SSL Certificate Management**
- **Barbican**: OpenStack Key Manager service
- **Manual certificates**: Direct certificate management
```yaml
loadbalancer.openstack.org/default-tls-container-ref: "barbican-container-ref"
```

### **Prerequisites**
- OpenStack Kubernetes cluster
- Octavia service enabled
- Barbican for certificate management
- Neutron networking configured

---

## Implementation Priority Matrix

| Provider | Complexity | Maturity | Gateway API Support | Priority |
|----------|------------|----------|-------------------|----------|
| **GCP GKE** | Medium | High | Native | High |
| **Azure AKS** | Medium | High | Good | High |
| **DigitalOcean** | Low | Medium | Good | Medium |
| **Linode** | Low | Medium | Basic | Medium |
| **OpenStack** | High | Medium | Basic | Low |

## Common Requirements

### **Gateway API CRDs**
All providers require Gateway API CRDs installation:
```bash
kubectl apply -f https://github.com/kubernetes-sigs/gateway-api/releases/download/v1.0.0/standard-install.yaml
```

### **RBAC Configuration**
Gateway controllers require appropriate RBAC permissions for:
- Gateway API resources management
- Service and Endpoint discovery
- Load balancer provisioning
- Certificate management

### **Monitoring Integration**
- Prometheus metrics collection
- OpenTelemetry tracing
- Access logging configuration
- Health check endpoints

## Next Steps

1. Create provider-specific Gateway API configurations
2. Implement cloud provider detection automation
3. Develop testing and validation frameworks
4. Create deployment documentation for each provider

