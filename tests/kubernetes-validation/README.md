# Kubernetes Cluster Validation

This directory contains scripts for validating blue-green deployment in real Kubernetes environments. These scripts address the critical gap: **"No proof system works in actual Kubernetes environment"**.

## 🎯 **Validation Components**

### **1. Kind Cluster Setup** (`kind-cluster-setup.sh`)
Creates a local Kubernetes cluster with all required components for testing.

**Features**:
- Multi-node Kind cluster (1 control plane + 2 workers)
- FluxCD installation and configuration
- NGINX ingress controller setup
- Metrics server installation
- Test namespace creation
- Comprehensive validation

**Usage**:
```bash
./kind-cluster-setup.sh
./kind-cluster-setup.sh --workers 3 --k8s-version v1.28.0
./kind-cluster-setup.sh --skip-flux --skip-metrics
```

### **2. Blue-Green Environment Deployment** (`deploy-blue-green-environments.sh`)
Deploys blue-green environments to the Kind cluster for testing.

**Features**:
- Deploys all 5 critical services to both environments
- Creates ingress configurations for traffic routing
- Validates deployment health and connectivity
- Tests service discovery across environments
- Generates deployment summary and testing commands

**Usage**:
```bash
./deploy-blue-green-environments.sh
./deploy-blue-green-environments.sh --image-tag latest
./deploy-blue-green-environments.sh --skip-validation
```

### **3. Staging Validation** (`staging-validation.sh`)
Validates blue-green deployment in staging environment.

**Features**:
- FluxCD deployment validation
- Blue-green environment consistency checks
- Service discovery and health check validation
- Ingress configuration verification
- Traffic switching simulation
- Database connectivity testing
- Monitoring setup validation

**Usage**:
```bash
./staging-validation.sh --context staging-cluster
```

### **4. Pre-flight Checks** (`pre-flight-checks.sh`)
Production readiness validation before deployment.

**Features**:
- Cluster connectivity verification
- Required namespace validation
- FluxCD installation checks
- Ingress controller validation
- Resource availability assessment

**Usage**:
```bash
./pre-flight-checks.sh
```

## 🚀 **Quick Start Guide**

### **Step 1: Set up Kind Cluster**
```bash
# Create local test cluster
./kind-cluster-setup.sh

# Verify cluster is ready
kubectl get nodes
kubectl get pods -A
```

### **Step 2: Deploy Blue-Green Environments**
```bash
# Deploy services to both environments
./deploy-blue-green-environments.sh

# Verify deployments
kubectl get all -n reverse-tender-blue
kubectl get all -n reverse-tender-green
```

### **Step 3: Run Comprehensive Tests**
```bash
# Run all deployment tests
cd ../deployment
./run-all-tests.sh

# Or run specific test categories
./run-all-tests.sh --basic-only
```

### **Step 4: Validate Staging Environment**
```bash
# Switch to staging cluster
kubectl config use-context staging-cluster

# Run staging validation
./staging-validation.sh --context staging-cluster
```

### **Step 5: Pre-flight Checks for Production**
```bash
# Switch to production cluster
kubectl config use-context production-cluster

# Run pre-flight checks
./pre-flight-checks.sh
```

## 📋 **Prerequisites**

### **Required Tools**
- `docker` - Container runtime
- `kubectl` - Kubernetes CLI
- `kind` - Kubernetes in Docker (auto-installed)
- `flux` - FluxCD CLI (auto-installed)
- `curl` - HTTP client
- `jq` - JSON processor (optional)

### **System Requirements**
- Docker running and accessible
- At least 8GB RAM available
- 20GB free disk space
- Internet connectivity for image pulls

### **Cluster Requirements**
- Kubernetes 1.26+ cluster
- FluxCD v2.0+ installed
- NGINX ingress controller
- Required namespaces created

## 🔧 **Configuration Options**

### **Kind Cluster Configuration**
```bash
# Custom cluster name
./kind-cluster-setup.sh --cluster-name my-test-cluster

# More worker nodes
./kind-cluster-setup.sh --workers 4

# Different Kubernetes version
./kind-cluster-setup.sh --k8s-version v1.29.0

# Skip optional components
./kind-cluster-setup.sh --skip-metrics --skip-ingress
```

### **Deployment Configuration**
```bash
# Custom image tag
./deploy-blue-green-environments.sh --image-tag v1.2.3

# Different repository
./deploy-blue-green-environments.sh --repo-url https://github.com/myorg/myrepo.git

# Custom branch
./deploy-blue-green-environments.sh --branch feature-branch
```

## 📊 **Validation Criteria**

### **Kind Cluster Setup**
- ✅ All nodes ready and healthy
- ✅ FluxCD controllers running
- ✅ Ingress controller operational
- ✅ Test namespaces created
- ✅ Metrics server available

### **Blue-Green Deployment**
- ✅ All services deployed to both environments
- ✅ Health checks passing
- ✅ Service discovery working
- ✅ Ingress routing configured
- ✅ Cross-environment connectivity

### **Staging Validation**
- ✅ FluxCD synchronization working
- ✅ Environment consistency maintained
- ✅ Traffic switching ready
- ✅ Database connectivity verified
- ✅ Monitoring systems operational

### **Production Readiness**
- ✅ Cluster accessible and stable
- ✅ All required components installed
- ✅ Resource capacity sufficient
- ✅ Security policies enforced
- ✅ Backup systems operational

## 🔍 **Troubleshooting**

### **Kind Cluster Issues**

#### "Docker not running"
```bash
# Start Docker service
sudo systemctl start docker

# Or on macOS
open -a Docker
```

#### "Cluster creation failed"
```bash
# Clean up and retry
kind delete cluster --name blue-green-test
./kind-cluster-setup.sh
```

#### "FluxCD installation failed"
```bash
# Check prerequisites
flux check --pre

# Manual installation
flux install --version=v2.2.2
```

### **Deployment Issues**

#### "Image pull failures"
```bash
# Check image availability
docker pull ghcr.io/abdoelhodaky/larvrevrstender-gateway-service:v2-blue-green-deploy

# Use different image tag
./deploy-blue-green-environments.sh --image-tag latest
```

#### "Service not ready"
```bash
# Check pod logs
kubectl logs -n reverse-tender-blue deployment/gateway-service

# Check events
kubectl get events -n reverse-tender-blue --sort-by='.lastTimestamp'
```

### **Networking Issues**

#### "Ingress not accessible"
```bash
# Check ingress controller
kubectl get pods -n ingress-nginx

# Port forward for testing
kubectl port-forward -n ingress-nginx service/ingress-nginx-controller 8080:80
```

#### "Service discovery failing"
```bash
# Test DNS resolution
kubectl exec -n reverse-tender-blue deployment/gateway-service -- nslookup auth-service.reverse-tender-blue.svc.cluster.local

# Check service endpoints
kubectl get endpoints -n reverse-tender-blue
```

## 📈 **Performance Considerations**

### **Resource Usage**
- **Kind Cluster**: ~4GB RAM, ~10GB disk
- **Blue-Green Deployment**: ~2GB RAM, ~5GB disk
- **Total**: ~6GB RAM, ~15GB disk

### **Startup Times**
- **Cluster Creation**: 3-5 minutes
- **FluxCD Installation**: 2-3 minutes
- **Service Deployment**: 5-10 minutes
- **Total Setup**: 10-18 minutes

### **Optimization Tips**
- Use local image registry for faster pulls
- Pre-pull images before deployment
- Increase resource limits for faster startup
- Use SSD storage for better performance

## 🔄 **Integration with CI/CD**

### **GitHub Actions Example**
```yaml
name: Kubernetes Validation
on: [push, pull_request]

jobs:
  k8s-validation:
    runs-on: ubuntu-latest
    steps:
    - uses: actions/checkout@v4
    
    - name: Setup Kind Cluster
      run: |
        cd tests/kubernetes-validation
        ./kind-cluster-setup.sh
    
    - name: Deploy Blue-Green Environments
      run: |
        cd tests/kubernetes-validation
        ./deploy-blue-green-environments.sh
    
    - name: Run Validation Tests
      run: |
        cd tests/deployment
        ./run-all-tests.sh --basic-only
    
    - name: Cleanup
      if: always()
      run: kind delete cluster --name blue-green-test
```

### **Local Development Workflow**
```bash
# Daily development cycle
./kind-cluster-setup.sh
./deploy-blue-green-environments.sh
../deployment/run-all-tests.sh --basic-only

# Feature testing
./deploy-blue-green-environments.sh --image-tag feature-branch
../deployment/run-all-tests.sh

# Cleanup
kind delete cluster --name blue-green-test
```

## 📚 **Additional Resources**

- [Kind Documentation](https://kind.sigs.k8s.io/)
- [FluxCD Documentation](https://fluxcd.io/docs/)
- [NGINX Ingress Controller](https://kubernetes.github.io/ingress-nginx/)
- [Kubernetes Testing Guide](https://kubernetes.io/docs/tasks/debug-application-cluster/)

---

**Part of Phase 1 Week 3: Kubernetes Cluster Validation**  
**Laravel Reverse Tender Platform - Blue-Green Deployment Implementation**
