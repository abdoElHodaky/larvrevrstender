# 🚀 FluxCD Setup Guide
## Laravel Reverse Tender Platform

### 📋 **Overview**

This guide provides step-by-step instructions for setting up FluxCD with blue-green deployment capabilities for the Laravel Reverse Tender Platform.

---

## 🎯 **Prerequisites**

### **Infrastructure Requirements**
- ✅ Kubernetes cluster (v1.24+)
- ✅ kubectl configured with cluster admin access
- ✅ Git repository access (GitHub)
- ✅ Container registry access
- ✅ Prometheus and Grafana monitoring stack

### **Access Requirements**
- GitHub personal access token with repo permissions
- Kubernetes cluster admin privileges
- Container registry push/pull access

---

## 🔧 **Installation Steps**

### **Step 1: Install FluxCD CLI**

```bash
# Install Flux CLI
curl -s https://fluxcd.io/install.sh | sudo bash

# Verify installation
flux --version
```

### **Step 2: Bootstrap FluxCD**

```bash
# Set environment variables
export GITHUB_TOKEN=<your-github-token>
export GITHUB_USER=<your-github-username>
export GITHUB_REPO=larvrevrstender

# Bootstrap Flux
flux bootstrap github \
  --owner=$GITHUB_USER \
  --repository=$GITHUB_REPO \
  --branch=v2 \
  --path=./deployment/fluxcd \
  --personal
```

### **Step 3: Verify FluxCD Installation**

```bash
# Check FluxCD controllers
kubectl get pods -n flux-system

# Expected output:
# NAME                                       READY   STATUS    RESTARTS   AGE
# helm-controller-xxx                        1/1     Running   0          2m
# kustomize-controller-xxx                   1/1     Running   0          2m
# notification-controller-xxx                1/1     Running   0          2m
# source-controller-xxx                      1/1     Running   0          2m

# Check GitRepository status
flux get sources git

# Check Kustomizations
flux get kustomizations
```

### **Step 4: Apply Blue-Green Configuration**

```bash
# Apply namespace configuration
kubectl apply -f deployment/fluxcd/infrastructure/namespace.yaml

# Apply FluxCD controllers
kubectl apply -f deployment/fluxcd/infrastructure/controllers.yaml

# Apply blue-green environment configuration
kubectl apply -f deployment/fluxcd/environments/blue-green-config.yaml

# Apply monitoring configuration
kubectl apply -f deployment/fluxcd/monitoring/servicemonitor.yaml
kubectl apply -f deployment/fluxcd/monitoring/prometheus-rules.yaml

# Apply dashboard configuration
kubectl apply -f deployment/fluxcd/dashboards/configmap.yaml
```

### **Step 5: Configure Blue-Green Automation**

```bash
# Apply blue-green deployment automation
kubectl apply -f deployment/fluxcd/automation/blue-green-deployment.yaml

# Verify Kustomizations
kubectl get kustomizations -n flux-system

# Expected output:
# NAME                        AGE   READY   STATUS
# blue-green-deployment       1m    True    Applied revision: v2/abc123
# green-environment-standby   1m    True    Applied revision: v2/abc123 (suspended)
```

---

## 🔍 **Verification Checklist**

### **FluxCD Health Check**
```bash
# Check all FluxCD components
flux check

# Check reconciliation status
flux get all

# Check for any suspended resources
kubectl get kustomizations -n flux-system -o json | jq '.items[] | select(.spec.suspend == true) | .metadata.name'
```

### **Blue-Green Environment Check**
```bash
# Check blue-green configuration
kubectl get configmap blue-green-config -n reverse-tender -o yaml

# Check environment overlays
kubectl get kustomizations -n flux-system | grep -E "(blue|green)"

# Verify service discovery health
kubectl exec -n reverse-tender deployment/api-gateway -- curl -s http://localhost/health/deep
```

### **Monitoring Integration Check**
```bash
# Check ServiceMonitors
kubectl get servicemonitors -n flux-system
kubectl get servicemonitors -n reverse-tender

# Check Prometheus rules
kubectl get prometheusrules -n flux-system

# Check Grafana dashboards
kubectl get configmaps -n monitoring -l grafana_dashboard=1
```

---

## 🛠️ **Configuration Management**

### **Environment Variables**
Key environment variables used in the blue-green setup:

```yaml
# Blue-Green Configuration
ENVIRONMENT_COLOR: "blue" | "green"
DEPLOYMENT_TIMESTAMP: "2024-02-20T14:00:00Z"
PREVIOUS_COLOR: "green" | "blue"
DEPLOYMENT_STRATEGY: "blue-green"

# Health Check Configuration
HEALTH_CHECK_TIMEOUT: "300"
TRAFFIC_SWITCH_TIMEOUT: "60"
ROLLBACK_ENABLED: "true"
VALIDATION_REQUIRED: "true"

# Service Thresholds
MIN_HEALTHY_SERVICES: "7"
MAX_ERROR_RATE: "5"
MIN_SUCCESS_RATE: "95"
```

### **Git Repository Structure**
```
deployment/fluxcd/
├── infrastructure/
│   ├── namespace.yaml
│   └── controllers.yaml
├── environments/
│   └── blue-green-config.yaml
├── monitoring/
│   ├── servicemonitor.yaml
│   └── prometheus-rules.yaml
├── dashboards/
│   ├── fluxcd-control-plane.json
│   ├── blue-green-deployment.json
│   └── configmap.yaml
└── automation/
    ├── blue-green-deployment.yaml
    └── deployment-job.yaml
```

---

## 🔄 **Maintenance Procedures**

### **Regular Health Checks**
```bash
# Daily health check script
#!/bin/bash
echo "=== FluxCD Health Check ==="
flux check
echo ""

echo "=== Reconciliation Status ==="
flux get all
echo ""

echo "=== Blue-Green Environment Status ==="
kubectl get configmap blue-green-config -n reverse-tender -o jsonpath='{.data.ENVIRONMENT_COLOR}'
echo ""

echo "=== Service Health ==="
kubectl exec -n reverse-tender deployment/api-gateway -- curl -s http://localhost/health | jq '.status'
```

### **Log Monitoring**
```bash
# FluxCD controller logs
kubectl logs -n flux-system deployment/source-controller -f
kubectl logs -n flux-system deployment/kustomize-controller -f

# Blue-green deployment job logs
kubectl logs -n flux-system job/blue-green-deployment-job -f
```

### **Backup Procedures**
```bash
# Backup FluxCD configuration
kubectl get all -n flux-system -o yaml > fluxcd-backup-$(date +%Y%m%d).yaml

# Backup blue-green configuration
kubectl get configmaps -n reverse-tender -o yaml > blue-green-config-backup-$(date +%Y%m%d).yaml
```

---

## 🚨 **Security Considerations**

### **RBAC Configuration**
- FluxCD controllers use `flux-controller` service account
- Minimum required permissions for blue-green operations
- Regular audit of service account permissions

### **Secret Management**
```bash
# Check for secrets in flux-system namespace
kubectl get secrets -n flux-system

# Rotate GitHub token if needed
flux create secret git flux-system \
  --url=https://github.com/abdoElHodaky/larvrevrstender \
  --username=$GITHUB_USER \
  --password=$GITHUB_TOKEN
```

### **Network Policies**
- Ensure proper network segmentation
- FluxCD controllers need access to Kubernetes API
- Git repository access for source controller

---

## 📊 **Monitoring and Alerting**

### **Key Metrics to Monitor**
- FluxCD reconciliation success rate
- Blue-green deployment duration
- Environment health scores
- Service discovery response times

### **Critical Alerts**
- FluxCD reconciliation failures
- Blue-green deployment stuck
- Environment health degradation
- High service discovery error rates

### **Dashboard Access**
- FluxCD Control Plane: Grafana → Dashboards → FluxCD Control Plane
- Blue-Green Deployment: Grafana → Dashboards → Blue-Green Deployment

---

## 🔧 **Troubleshooting Quick Reference**

### **Common Issues**
1. **Reconciliation Failures**: Check Git repository access and branch permissions
2. **Suspended Kustomizations**: Verify resource health and dependencies
3. **Health Check Failures**: Check service endpoints and network connectivity
4. **Dashboard Missing**: Verify ConfigMap labels and Grafana configuration

### **Emergency Contacts**
- Platform Team: [platform-team@company.com]
- DevOps Team: [devops@company.com]
- On-call Engineer: [oncall@company.com]

---

**Next Steps**: After FluxCD setup is complete, proceed to the [Blue-Green Deployment Runbook](./blue-green-deployment-runbook.md) for operational procedures.
