# Advanced Security Hardening

This directory contains comprehensive security hardening configurations for the blue-green deployment system, implementing enterprise-grade security controls and compliance measures.

## 🛡️ **Security Components**

### **1. Pod Security Standards** (`pod-security-standards/`)
- **Pod Security Policies** - Kubernetes Pod Security Standards enforcement
- **Security Context Templates** - Hardened container security configurations
- **Service Account Management** - Least-privilege access controls

### **2. Network Policies** (`network-policies/`)
- **Micro-segmentation** - Namespace-level network isolation
- **Traffic Control** - Ingress/egress rules for all components
- **Zero-Trust Networking** - Default deny-all with explicit allow rules

### **3. Secret Management** (`secret-management/`)
- **External Secrets Operator** - Integration with external secret stores
- **Multi-Provider Support** - AWS Secrets Manager, HashiCorp Vault, Azure Key Vault
- **Secret Rotation** - Automated secret lifecycle management

### **4. Image Security** (`image-scanning/`)
- **Container Image Scanning** - Trivy-based vulnerability detection
- **Admission Control** - Policy enforcement for image security
- **Continuous Monitoring** - Daily vulnerability scans and alerting

## 🎯 **Security Objectives**

### **Defense in Depth**
- **Layer 1**: Container image security and scanning
- **Layer 2**: Pod security standards and runtime protection
- **Layer 3**: Network segmentation and traffic control
- **Layer 4**: Secret management and encryption
- **Layer 5**: Monitoring and incident response

### **Compliance Standards**
- **CIS Kubernetes Benchmark** - Industry security standards
- **NIST Cybersecurity Framework** - Risk management approach
- **SOC 2 Type II** - Security and availability controls
- **ISO 27001** - Information security management

## 🚀 **Quick Setup**

### **Deploy Pod Security Standards**
```bash
# Apply pod security policies
kubectl apply -f security/pod-security-standards/pod-security-policies.yaml

# Verify namespace labels
kubectl get namespaces --show-labels
```

### **Deploy Network Policies**
```bash
# Apply network policies
kubectl apply -f security/network-policies/network-policies.yaml

# Test network connectivity
kubectl exec -n reverse-tender-blue deployment/gateway-service -- curl -m 5 auth-service:8001/health
```

### **Deploy External Secrets**
```bash
# Install External Secrets Operator
kubectl apply -f security/secret-management/external-secrets.yaml

# Configure secret stores (update with your credentials)
# Edit the SecretStore configurations with your provider details
```

### **Deploy Image Security**
```bash
# Install Trivy Operator
kubectl apply -f security/image-scanning/image-security.yaml

# Enable image policy enforcement
kubectl label namespace reverse-tender-blue image-policy=enforced
kubectl label namespace reverse-tender-green image-policy=enforced
```

## 🛡️ **Pod Security Standards**

### **Security Levels**
- **Privileged**: FluxCD system components (minimal restrictions)
- **Baseline**: Monitoring components (basic security controls)
- **Restricted**: Application workloads (maximum security)

### **Security Context Requirements**
```yaml
securityContext:
  runAsNonRoot: true
  runAsUser: 65534  # nobody user
  runAsGroup: 65534
  fsGroup: 65534
  seccompProfile:
    type: RuntimeDefault
  capabilities:
    drop: [ALL]
  allowPrivilegeEscalation: false
  readOnlyRootFilesystem: true
```

### **Service Account Security**
- **Minimal Permissions**: Least-privilege RBAC
- **No Auto-mount**: Service account tokens not auto-mounted
- **Namespace Isolation**: Separate service accounts per environment

## 🔒 **Network Security**

### **Network Segmentation**
```yaml
# Blue Environment Isolation
- Ingress: Only from ingress controller and monitoring
- Egress: DNS, database, external APIs only
- Inter-service: Within namespace only

# Green Environment Isolation  
- Ingress: Only from ingress controller and monitoring
- Egress: DNS, database, external APIs only
- Inter-service: Within namespace only

# Cross-Environment: Disabled by default
```

### **Traffic Flow Control**
- **Default Deny**: All traffic blocked by default
- **Explicit Allow**: Only required connections permitted
- **Monitoring Access**: Prometheus scraping allowed
- **Health Checks**: Ingress controller health checks

### **Network Policy Validation**
```bash
# Test blue environment isolation
kubectl exec -n reverse-tender-blue deployment/gateway-service -- \
  curl -m 5 gateway-service.reverse-tender-green:8009/health

# Should fail with timeout (network policy blocking)
```

## 🔐 **Secret Management**

### **External Secret Providers**

#### **AWS Secrets Manager**
```yaml
# Configuration
provider:
  aws:
    service: SecretsManager
    region: us-west-2
    auth:
      secretRef:
        accessKeyID: {name: aws-credentials, key: access-key-id}
        secretAccessKey: {name: aws-credentials, key: secret-access-key}
```

#### **HashiCorp Vault**
```yaml
# Configuration
provider:
  vault:
    server: "https://vault.company.com"
    path: "secret"
    version: "v2"
    auth:
      kubernetes:
        mountPath: "kubernetes"
        role: "blue-green-role"
```

#### **Azure Key Vault**
```yaml
# Configuration
provider:
  azurekv:
    vaultUrl: "https://my-keyvault.vault.azure.net/"
    authType: ManagedIdentity
    identityId: "your-managed-identity-client-id"
```

### **Secret Types Managed**
- **Database Credentials**: Connection strings and passwords
- **API Keys**: Third-party service authentication
- **TLS Certificates**: SSL/TLS certificates and private keys
- **OAuth Secrets**: Client secrets and tokens

### **Secret Rotation**
- **Automatic Rotation**: 7-day rotation for sensitive secrets
- **Refresh Intervals**: 1h for database, 30m for API keys
- **Deployment Updates**: Automatic pod restart on secret changes

## 🔍 **Image Security**

### **Vulnerability Scanning**
- **Scanner**: Trivy (CNCF graduated project)
- **Frequency**: Daily automated scans
- **Severity Levels**: CRITICAL, HIGH, MEDIUM
- **Databases**: CVE, GitHub Security Advisories, OS packages

### **Image Policy Enforcement**
```yaml
# Allowed Registries
- ghcr.io/abdoelhodaky/  # Organization registry
- registry.k8s.io/       # Kubernetes official
- quay.io/               # Red Hat Quay
- docker.io/library/     # Docker Hub official

# Prohibited Practices
- :latest tags
- Untagged images
- Privileged containers
- Root user execution
```

### **Admission Control**
- **ValidatingAdmissionWebhook**: Policy enforcement at deployment
- **Failure Policy**: Fail-closed (reject non-compliant images)
- **Bypass**: Emergency override for critical updates

### **Vulnerability Thresholds**
- **CRITICAL**: 0 vulnerabilities allowed
- **HIGH**: Maximum 5 vulnerabilities
- **MEDIUM**: Monitored but not blocking
- **LOW**: Informational only

## 📊 **Security Monitoring**

### **Security Metrics**
- **Vulnerable Images**: Count of images with vulnerabilities
- **Policy Violations**: Admission control rejections
- **Network Policy Drops**: Blocked network connections
- **Secret Access**: External secret retrieval events

### **Security Alerts**
```yaml
# Critical Alerts
- VulnerableImageDetected: Image with critical vulnerabilities
- PolicyViolationHigh: Multiple admission control failures
- UnauthorizedNetworkAccess: Network policy violations
- SecretAccessFailure: External secret retrieval failures

# Warning Alerts  
- ImageScanOutdated: Scan older than 24 hours
- NetworkPolicyMisconfiguration: Connectivity issues
- SecretRotationDue: Secrets approaching rotation
```

### **Security Dashboard**
- **Image Vulnerability Status**: Real-time vulnerability tracking
- **Network Policy Compliance**: Traffic flow visualization
- **Secret Management Health**: External secret sync status
- **Security Event Timeline**: Chronological security events

## 🔧 **Configuration**

### **Environment-Specific Settings**

#### **Production Environment**
```yaml
# Maximum security settings
pod-security.kubernetes.io/enforce: restricted
image-policy: enforced
network-policy: strict
secret-rotation: enabled
vulnerability-scanning: daily
```

#### **Staging Environment**
```yaml
# Balanced security settings
pod-security.kubernetes.io/enforce: baseline
image-policy: warn
network-policy: permissive
secret-rotation: weekly
vulnerability-scanning: weekly
```

#### **Development Environment**
```yaml
# Minimal security settings
pod-security.kubernetes.io/enforce: privileged
image-policy: disabled
network-policy: disabled
secret-rotation: disabled
vulnerability-scanning: on-demand
```

### **Provider Configuration**

#### **AWS Integration**
```bash
# Create AWS credentials secret
kubectl create secret generic aws-credentials \
  --from-literal=access-key-id=YOUR_ACCESS_KEY \
  --from-literal=secret-access-key=YOUR_SECRET_KEY \
  -n reverse-tender-blue

# Configure IAM role for Secrets Manager access
aws iam create-role --role-name EKSSecretsManagerRole \
  --assume-role-policy-document file://trust-policy.json
```

#### **Vault Integration**
```bash
# Enable Kubernetes auth in Vault
vault auth enable kubernetes

# Configure Vault policy
vault policy write blue-green-policy - <<EOF
path "secret/data/reverse-tender/*" {
  capabilities = ["read"]
}
EOF
```

## 🔍 **Security Validation**

### **Compliance Checks**
```bash
# Run CIS Kubernetes Benchmark
kube-bench run --targets node,policies,managedservices

# Check Pod Security Standards compliance
kubectl get pods --all-namespaces -o jsonpath='{range .items[*]}{.metadata.namespace}{"\t"}{.metadata.name}{"\t"}{.spec.securityContext.runAsNonRoot}{"\n"}{end}'

# Validate network policies
kubectl get networkpolicies --all-namespaces
```

### **Penetration Testing**
```bash
# Test network segmentation
kubectl run test-pod --image=nicolaka/netshoot -it --rm -- /bin/bash

# Test image policy enforcement
kubectl apply -f - <<EOF
apiVersion: v1
kind: Pod
metadata:
  name: test-vulnerable
spec:
  containers:
  - name: test
    image: vulnerable:latest
EOF
```

### **Security Audit**
```bash
# Generate security report
kubectl get pods --all-namespaces -o json | \
  jq '.items[] | select(.spec.securityContext.runAsRoot == true) | .metadata.name'

# Check for privileged containers
kubectl get pods --all-namespaces -o json | \
  jq '.items[] | select(.spec.containers[].securityContext.privileged == true) | .metadata.name'
```

## 📚 **Security Best Practices**

### **Container Security**
- **Minimal Base Images**: Use distroless or alpine images
- **Non-Root Users**: Always run as non-root user
- **Read-Only Filesystems**: Mount root filesystem as read-only
- **Capability Dropping**: Drop all Linux capabilities
- **Resource Limits**: Set CPU and memory limits

### **Network Security**
- **Zero Trust**: Default deny all network traffic
- **Least Privilege**: Minimal required network access
- **Encryption**: TLS for all inter-service communication
- **Monitoring**: Log and monitor all network connections

### **Secret Security**
- **External Storage**: Never store secrets in container images
- **Encryption**: Encrypt secrets at rest and in transit
- **Rotation**: Regular secret rotation and lifecycle management
- **Access Control**: Minimal required secret access

### **Operational Security**
- **Regular Updates**: Keep all components updated
- **Vulnerability Scanning**: Continuous image and cluster scanning
- **Incident Response**: Defined security incident procedures
- **Compliance Monitoring**: Regular compliance assessments

## 🚨 **Incident Response**

### **Security Incident Types**
- **Vulnerable Image Deployed**: Critical vulnerability in production
- **Policy Violation**: Security policy bypass attempt
- **Unauthorized Access**: Network or secret access violation
- **Compliance Breach**: Regulatory compliance failure

### **Response Procedures**
1. **Detection**: Automated alerting and monitoring
2. **Assessment**: Severity and impact evaluation
3. **Containment**: Immediate threat mitigation
4. **Eradication**: Root cause elimination
5. **Recovery**: Service restoration
6. **Lessons Learned**: Post-incident review

### **Emergency Contacts**
- **Security Team**: security@company.com
- **DevOps Team**: devops@company.com
- **On-Call Engineer**: +1-555-ONCALL

---

**Part of Phase 2 Week 1: Advanced Security Hardening**  
**Laravel Reverse Tender Platform - Blue-Green Deployment Implementation**

