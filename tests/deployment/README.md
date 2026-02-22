# Blue-Green Deployment Testing Framework

This comprehensive testing framework validates the blue-green deployment system for the Laravel Reverse Tender Platform. It includes multiple test categories designed to ensure production readiness.

## 🎯 **Test Categories**

### **1. Basic Deployment Tests**
- **FluxCD Deployment Test** (`fluxcd-deployment-test.sh`) - Validates FluxCD controllers, Git sync, and reconciliation
- **Blue-Green Validation** (`blue-green-validation.sh`) - Tests environment switching and service discovery
- **Traffic Switch Test** (`traffic-switch-test.sh`) - Validates ingress routing and load balancing
- **End-to-End Deployment** (`e2e-deployment-test.sh`) - Complete deployment cycle validation

### **2. Chaos Engineering Tests**
- **Service Failure Simulation** (`chaos-engineering/service-failure-simulation.sh`) - Tests system resilience to failures

### **3. Performance Tests**
- **Deployment Duration Profiling** (`performance-testing/deployment-duration-profiling.sh`) - Measures deployment performance

## 🚀 **Quick Start**

### Run All Tests
```bash
./run-all-tests.sh
```

### Run Only Basic Tests
```bash
./run-all-tests.sh --basic-only
```

### Run Without Chaos Tests
```bash
./run-all-tests.sh --no-chaos
```

## 📋 **Prerequisites**

### Required Tools
- `kubectl` - Kubernetes CLI
- `curl` - HTTP client for health checks
- `bc` - Calculator for performance metrics
- `jq` - JSON processor (optional, for enhanced reporting)

### Required Kubernetes Resources
- **Namespaces**: `reverse-tender-blue`, `reverse-tender-green`, `flux-system`
- **FluxCD Controllers**: source-controller, kustomize-controller, helm-controller
- **Services**: All 11 microservices deployed in both environments

### Cluster Access
- Valid kubeconfig with access to the target cluster
- Sufficient permissions to read/write pods, deployments, services, and ingress resources

## 🔧 **Individual Test Usage**

### FluxCD Deployment Test
```bash
./fluxcd-deployment-test.sh
```
**Purpose**: Validates FluxCD controller health, Git synchronization, and drift detection
**Duration**: ~5-10 minutes
**Requirements**: FluxCD installed and configured

### Blue-Green Validation Test
```bash
./blue-green-validation.sh
```
**Purpose**: Tests blue-green environment consistency and service discovery
**Duration**: ~10-15 minutes
**Requirements**: Both blue and green environments deployed

### Traffic Switch Test
```bash
./traffic-switch-test.sh
```
**Purpose**: Validates ingress configuration and traffic routing
**Duration**: ~5-10 minutes
**Requirements**: Ingress controller and ingress resources configured

### End-to-End Deployment Test
```bash
./e2e-deployment-test.sh
```
**Purpose**: Complete deployment cycle with pre/post validation
**Duration**: ~15-20 minutes
**Requirements**: Full blue-green deployment setup

### Service Failure Simulation
```bash
./chaos-engineering/service-failure-simulation.sh
```
**Purpose**: Tests system resilience to various failure scenarios
**Duration**: ~10-15 minutes
**Requirements**: Running services in green environment
**Warning**: This test intentionally causes service failures

### Deployment Duration Profiling
```bash
./performance-testing/deployment-duration-profiling.sh
```
**Purpose**: Measures deployment performance and identifies bottlenecks
**Duration**: ~10-15 minutes
**Requirements**: metrics-server (optional, for resource metrics)

## 📊 **Test Results**

### Output Locations
- **Main Log**: `/tmp/comprehensive-test-run-YYYYMMDD-HHMMSS.log`
- **Results Directory**: `/tmp/test-results-YYYYMMDD-HHMMSS/`
- **Individual Test Logs**: `{results-dir}/{test-name}.log`
- **Comprehensive Report**: `{results-dir}/comprehensive-test-report.md`
- **JSON Results**: `{results-dir}/test-results.json`

### Success Criteria
- **All Basic Tests Pass**: Required for production readiness
- **Chaos Tests Pass**: Validates system resilience
- **Performance Tests Pass**: Ensures acceptable deployment speed

## 🎯 **Performance Targets**

| Metric | Target | Test |
|--------|--------|------|
| Total Deployment Duration | ≤ 300s (5 min) | E2E Deployment |
| Service Startup Time | ≤ 60s | Performance Profiling |
| Health Check Response | ≤ 1000ms | Blue-Green Validation |
| Rollback Duration | ≤ 120s (2 min) | E2E Deployment |
| Service Recovery | ≤ 180s (3 min) | Chaos Engineering |

## 🔍 **Troubleshooting**

### Common Issues

#### "kubectl not found"
```bash
# Install kubectl
curl -LO "https://dl.k8s.io/release/$(curl -L -s https://dl.k8s.io/release/stable.txt)/bin/linux/amd64/kubectl"
chmod +x kubectl
sudo mv kubectl /usr/local/bin/
```

#### "Cannot access Kubernetes cluster"
```bash
# Check cluster connectivity
kubectl cluster-info

# Verify kubeconfig
kubectl config current-context
```

#### "Namespace does not exist"
```bash
# Check existing namespaces
kubectl get namespaces

# Create missing namespaces (if needed)
kubectl create namespace reverse-tender-blue
kubectl create namespace reverse-tender-green
```

#### "FluxCD controllers not ready"
```bash
# Check FluxCD status
kubectl get deployments -n flux-system

# Check FluxCD logs
kubectl logs -n flux-system deployment/source-controller
```

### Test-Specific Issues

#### FluxCD Test Failures
- Verify FluxCD installation: `flux check`
- Check Git repository access and credentials
- Validate kustomization resources

#### Blue-Green Test Failures
- Ensure both environments have services deployed
- Check service discovery DNS resolution
- Validate network policies and connectivity

#### Traffic Switch Test Failures
- Verify ingress controller is running
- Check ingress resource configuration
- Validate service endpoints and ports

#### Chaos Test Failures
- Ensure sufficient cluster resources
- Check pod security policies
- Validate service recovery mechanisms

#### Performance Test Failures
- Install metrics-server for resource metrics
- Check cluster resource availability
- Validate performance targets are realistic

## 📈 **Interpreting Results**

### Test Status Meanings
- **✅ PASSED**: Test completed successfully, all validations passed
- **❌ FAILED**: Test failed, issue requires investigation
- **⚠️ WARNING**: Test passed with warnings, review recommended
- **ℹ️ INFO**: Informational message, no action required

### Performance Metrics
- **Deployment Duration**: Time from start to all services ready
- **Startup Time**: Time from pod creation to ready state
- **Response Time**: Health check endpoint response time
- **Recovery Time**: Time to recover from failures
- **Resource Usage**: CPU and memory utilization

### Failure Analysis
1. **Check individual test logs** in the results directory
2. **Review error messages** for specific failure reasons
3. **Validate prerequisites** are met
4. **Check cluster resources** and capacity
5. **Verify configuration** matches expected setup

## 🔄 **Continuous Integration**

### GitHub Actions Integration
```yaml
- name: Run Blue-Green Deployment Tests
  run: |
    cd tests/deployment
    ./run-all-tests.sh --basic-only
```

### Automated Testing Schedule
- **Pre-deployment**: Run basic tests before any deployment
- **Post-deployment**: Run full test suite after deployment
- **Nightly**: Run chaos and performance tests
- **Weekly**: Full comprehensive test suite

## 📚 **Additional Resources**

- [FluxCD Documentation](https://fluxcd.io/docs/)
- [Kubernetes Testing Guide](https://kubernetes.io/docs/tasks/debug-application-cluster/)
- [Blue-Green Deployment Patterns](https://martinfowler.com/bliki/BlueGreenDeployment.html)
- [Chaos Engineering Principles](https://principlesofchaos.org/)

## 🤝 **Contributing**

### Adding New Tests
1. Create test script in appropriate category directory
2. Follow naming convention: `{test-name}-test.sh`
3. Include comprehensive logging and error handling
4. Add test to appropriate array in `run-all-tests.sh`
5. Update this README with test description

### Test Script Requirements
- Use `set -euo pipefail` for error handling
- Include comprehensive logging with timestamps
- Provide clear success/failure indicators
- Generate detailed error messages
- Clean up resources after testing
- Follow consistent output formatting

---

**Part of Phase 1: Comprehensive Testing Framework**  
**Laravel Reverse Tender Platform - Blue-Green Deployment Implementation**

