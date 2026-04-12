# 🔧 Troubleshooting Guide
## Laravel Reverse Tender Platform - Blue-Green Deployment

### 📋 **Overview**

This guide provides comprehensive troubleshooting procedures for common issues encountered during blue-green deployments with FluxCD on the Laravel Reverse Tender Platform.

---

## 🚨 **Emergency Quick Reference**

### **Critical Commands**
```bash
# Emergency rollback
kubectl patch configmap blue-green-config -n reverse-tender --patch '{"data":{"ENVIRONMENT_COLOR":"blue"}}'

# Check deployment status
kubectl get kustomizations -n flux-system

# View deployment logs
kubectl logs -n flux-system job/blue-green-deployment-job

# Check service health
kubectl exec -n reverse-tender deployment/api-gateway -- curl -s http://localhost/health
```

### **Emergency Contacts**
- **On-call Engineer**: [oncall@company.com] / Slack: #oncall
- **Platform Team**: [platform-team@company.com] / Slack: #platform
- **Emergency Hotline**: [+1-XXX-XXX-XXXX]

---

## 🔍 **Diagnostic Procedures**

### **System Health Check**
```bash
#!/bin/bash
# comprehensive-health-check.sh

echo "=== FluxCD Health Check ==="
flux check
echo ""

echo "=== Kubernetes Cluster Health ==="
kubectl get nodes
kubectl get pods -n flux-system
kubectl get pods -n reverse-tender
echo ""

echo "=== Blue-Green Configuration ==="
kubectl get configmap blue-green-config -n reverse-tender -o yaml
echo ""

echo "=== Service Health ==="
kubectl exec -n reverse-tender deployment/api-gateway -- curl -s http://localhost/health/deep | jq '.'
echo ""

echo "=== Recent Events ==="
kubectl get events -n reverse-tender --sort-by='.lastTimestamp' | tail -10
```

### **Performance Diagnostics**
```bash
# Resource utilization
kubectl top nodes
kubectl top pods -n reverse-tender

# Network connectivity
kubectl exec -n reverse-tender deployment/api-gateway -- nslookup kubernetes.default.svc.cluster.local

# Database connectivity
kubectl exec -n reverse-tender deployment/api-gateway -- curl -s http://localhost/health | jq '.database'
```

---

## 🚫 **Common Issues and Solutions**

### **1. FluxCD Reconciliation Issues**

#### **Symptom**: Kustomizations stuck in "Progressing" state
```bash
# Diagnosis
kubectl describe kustomization blue-green-deployment -n flux-system
flux get kustomizations

# Common causes and solutions:
```

**Cause A: Git Repository Access Issues**
```bash
# Check GitRepository status
flux get sources git

# Check authentication
kubectl get secrets -n flux-system
kubectl logs -n flux-system deployment/source-controller

# Solution: Refresh Git credentials
flux create secret git flux-system \
  --url=https://github.com/abdoElHodaky/larvrevrstender \
  --username=$GITHUB_USER \
  --password=$GITHUB_TOKEN
```

**Cause B: Resource Conflicts**
```bash
# Check for conflicting resources
kubectl get events -n reverse-tender --field-selector type=Warning

# Solution: Delete conflicting resources
kubectl delete deployment conflicting-deployment -n reverse-tender
```

**Cause C: Invalid Manifests**
```bash
# Check Kustomization logs
kubectl logs -n flux-system deployment/kustomize-controller

# Solution: Validate manifests locally
kustomize build deployment/k8s/overlays/blue
```

### **2. Service Health Issues**

#### **Symptom**: Services failing health checks
```bash
# Diagnosis
kubectl exec -n reverse-tender deployment/api-gateway -- curl -s http://localhost/health/deep
kubectl logs -n reverse-tender deployment/api-gateway --tail=50
```

**Cause A: Database Connection Issues**
```bash
# Check database connectivity
kubectl exec -n reverse-tender deployment/api-gateway -- curl -s http://localhost/health | jq '.database'

# Check database pods
kubectl get pods -n database-namespace

# Solution: Restart database connections
kubectl rollout restart deployment/api-gateway -n reverse-tender
```

**Cause B: Redis Connection Issues**
```bash
# Check Redis connectivity
kubectl exec -n reverse-tender deployment/api-gateway -- curl -s http://localhost/health | jq '.redis'

# Test Redis connection
kubectl exec -n reverse-tender deployment/redis -- redis-cli ping

# Solution: Restart Redis or update connection strings
kubectl rollout restart deployment/redis -n reverse-tender
```

**Cause C: Resource Constraints**
```bash
# Check resource usage
kubectl top pods -n reverse-tender
kubectl describe pod api-gateway-xxx -n reverse-tender

# Solution: Increase resource limits
kubectl patch deployment api-gateway -n reverse-tender --patch '
spec:
  template:
    spec:
      containers:
      - name: api-gateway
        resources:
          limits:
            memory: "1Gi"
            cpu: "500m"
'
```

### **3. Traffic Switching Issues**

#### **Symptom**: Traffic not routing to new environment
```bash
# Diagnosis
kubectl describe ingress reverse-tender-ingress -n reverse-tender
kubectl get services -n reverse-tender
curl -H "Host: api.reversetender.com" http://api-gateway.reverse-tender.svc.cluster.local/health
```

**Cause A: Ingress Configuration Issues**
```bash
# Check ingress controller
kubectl get pods -n ingress-nginx
kubectl logs -n ingress-nginx deployment/ingress-nginx-controller

# Solution: Update ingress configuration
kubectl patch ingress reverse-tender-ingress -n reverse-tender --patch '
spec:
  rules:
  - host: api.reversetender.com
    http:
      paths:
      - path: /
        pathType: Prefix
        backend:
          service:
            name: api-gateway-blue
            port:
              number: 80
'
```

**Cause B: Service Selector Issues**
```bash
# Check service selectors
kubectl get services -n reverse-tender -o wide
kubectl get pods -n reverse-tender --show-labels

# Solution: Update service selectors
kubectl patch service api-gateway -n reverse-tender --patch '
spec:
  selector:
    app: api-gateway
    environment-color: blue
'
```

**Cause C: DNS Resolution Issues**
```bash
# Test DNS resolution
kubectl exec -n reverse-tender deployment/api-gateway -- nslookup api-gateway.reverse-tender.svc.cluster.local

# Check CoreDNS
kubectl get pods -n kube-system -l k8s-app=kube-dns

# Solution: Restart CoreDNS
kubectl rollout restart deployment/coredns -n kube-system
```

### **4. Blue-Green Deployment Job Issues**

#### **Symptom**: Deployment job failing or timing out
```bash
# Diagnosis
kubectl logs -n flux-system job/blue-green-deployment-job
kubectl describe job blue-green-deployment-job -n flux-system
```

**Cause A: Job Timeout**
```bash
# Check job status
kubectl get jobs -n flux-system

# Solution: Increase job timeout
kubectl patch job blue-green-deployment-job -n flux-system --patch '
spec:
  activeDeadlineSeconds: 3600
'
```

**Cause B: Script Execution Errors**
```bash
# Check script logs
kubectl logs -n flux-system job/blue-green-deployment-job

# Solution: Debug script execution
kubectl exec -n flux-system job/blue-green-deployment-job -- /bin/bash -c "ls -la /scripts/"
```

**Cause C: Permission Issues**
```bash
# Check service account permissions
kubectl describe serviceaccount flux-controller -n flux-system
kubectl get clusterrolebindings | grep flux

# Solution: Update RBAC permissions
kubectl apply -f deployment/fluxcd/infrastructure/controllers.yaml
```

### **5. Environment Synchronization Issues**

#### **Symptom**: Blue and green environments out of sync
```bash
# Diagnosis
kubectl get configmaps -n reverse-tender
kubectl get kustomizations -n flux-system
```

**Cause A: ConfigMap Inconsistencies**
```bash
# Check ConfigMap values
kubectl get configmap blue-green-config -n reverse-tender -o yaml
kubectl get configmap blue-environment-config -n reverse-tender -o yaml
kubectl get configmap green-environment-config -n reverse-tender -o yaml

# Solution: Synchronize ConfigMaps
kubectl patch configmap blue-green-config -n reverse-tender --patch '
data:
  ENVIRONMENT_COLOR: "blue"
  DEPLOYMENT_TIMESTAMP: "'$(date -u +%Y-%m-%dT%H:%M:%SZ)'"
'
```

**Cause B: Kustomization Suspension Issues**
```bash
# Check suspension status
kubectl get kustomizations -n flux-system -o json | jq '.items[] | {name: .metadata.name, suspended: .spec.suspend}'

# Solution: Manually manage suspension
kubectl patch kustomization green-environment-standby -n flux-system --patch '{"spec":{"suspend":true}}'
```

---

## 📊 **Performance Troubleshooting**

### **High Response Times**

#### **Diagnosis Steps**
```bash
# Check application metrics
kubectl exec -n reverse-tender deployment/api-gateway -- curl -s http://localhost/metrics | grep http_request_duration

# Check resource utilization
kubectl top pods -n reverse-tender

# Check database performance
kubectl exec -n reverse-tender deployment/api-gateway -- curl -s http://localhost/health | jq '.database'
```

#### **Solutions**
1. **Scale up resources**:
   ```bash
   kubectl scale deployment api-gateway -n reverse-tender --replicas=5
   ```

2. **Increase resource limits**:
   ```bash
   kubectl patch deployment api-gateway -n reverse-tender --patch '
   spec:
     template:
       spec:
         containers:
         - name: api-gateway
           resources:
             limits:
               memory: "2Gi"
               cpu: "1000m"
   '
   ```

3. **Optimize database connections**:
   ```bash
   # Update database connection pool settings
   kubectl patch configmap app-config -n reverse-tender --patch '
   data:
     DB_POOL_SIZE: "20"
     DB_TIMEOUT: "30"
   '
   ```

### **High Error Rates**

#### **Diagnosis Steps**
```bash
# Check error metrics
kubectl exec -n reverse-tender deployment/api-gateway -- curl -s http://localhost/metrics | grep http_requests_total | grep 5

# Check application logs
kubectl logs -n reverse-tender deployment/api-gateway --tail=100 | grep ERROR

# Check service dependencies
kubectl exec -n reverse-tender deployment/api-gateway -- curl -s http://localhost/health/deep
```

#### **Solutions**
1. **Restart failing services**:
   ```bash
   kubectl rollout restart deployment/api-gateway -n reverse-tender
   ```

2. **Check and fix database issues**:
   ```bash
   kubectl exec -n database-namespace deployment/mysql -- mysql -u root -p -e "SHOW PROCESSLIST;"
   ```

3. **Verify external service connectivity**:
   ```bash
   kubectl exec -n reverse-tender deployment/api-gateway -- curl -s http://external-service.com/health
   ```

---

## 🔄 **Recovery Procedures**

### **Complete Environment Reset**
```bash
#!/bin/bash
# emergency-reset.sh

echo "🚨 EMERGENCY RESET - This will reset the entire blue-green deployment"
read -p "Are you sure? (yes/no): " confirm

if [ "$confirm" = "yes" ]; then
    # Suspend all Kustomizations
    kubectl patch kustomization blue-green-deployment -n flux-system --patch '{"spec":{"suspend":true}}'
    kubectl patch kustomization green-environment-standby -n flux-system --patch '{"spec":{"suspend":true}}'
    
    # Reset to blue environment
    kubectl patch configmap blue-green-config -n reverse-tender --patch '
    data:
      ENVIRONMENT_COLOR: "blue"
      DEPLOYMENT_STATUS: "reset"
      RESET_TIMESTAMP: "'$(date -u +%Y-%m-%dT%H:%M:%SZ)'"
    '
    
    # Restart blue environment
    kubectl patch kustomization blue-green-deployment -n flux-system --patch '{"spec":{"suspend":false}}'
    
    echo "✅ Emergency reset completed. Blue environment is now active."
fi
```

### **Service Recovery**
```bash
#!/bin/bash
# service-recovery.sh

SERVICE_NAME=$1
if [ -z "$SERVICE_NAME" ]; then
    echo "Usage: $0 <service-name>"
    exit 1
fi

echo "🔄 Recovering service: $SERVICE_NAME"

# Restart the service
kubectl rollout restart deployment/$SERVICE_NAME -n reverse-tender

# Wait for rollout to complete
kubectl rollout status deployment/$SERVICE_NAME -n reverse-tender --timeout=300s

# Verify health
kubectl exec -n reverse-tender deployment/$SERVICE_NAME -- curl -s http://localhost/health

echo "✅ Service $SERVICE_NAME recovery completed"
```

---

## 📝 **Logging and Monitoring**

### **Log Collection**
```bash
#!/bin/bash
# collect-logs.sh

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
LOG_DIR="troubleshooting_logs_$TIMESTAMP"
mkdir -p $LOG_DIR

echo "📋 Collecting troubleshooting logs..."

# FluxCD logs
kubectl logs -n flux-system deployment/source-controller > $LOG_DIR/source-controller.log
kubectl logs -n flux-system deployment/kustomize-controller > $LOG_DIR/kustomize-controller.log

# Application logs
kubectl logs -n reverse-tender deployment/api-gateway > $LOG_DIR/api-gateway.log

# System information
kubectl get all -n flux-system > $LOG_DIR/flux-system-resources.txt
kubectl get all -n reverse-tender > $LOG_DIR/reverse-tender-resources.txt
kubectl get events -n reverse-tender --sort-by='.lastTimestamp' > $LOG_DIR/events.txt

# Configuration
kubectl get configmaps -n reverse-tender -o yaml > $LOG_DIR/configmaps.yaml

echo "✅ Logs collected in $LOG_DIR/"
```

### **Monitoring Setup Verification**
```bash
# Check Prometheus targets
kubectl port-forward -n monitoring svc/prometheus 9090:9090 &
curl -s http://localhost:9090/api/v1/targets | jq '.data.activeTargets[] | select(.labels.job | contains("flux")) | {job: .labels.job, health: .health}'

# Check Grafana dashboards
kubectl port-forward -n monitoring svc/grafana 3000:80 &
curl -s http://admin:admin@localhost:3000/api/dashboards/uid/flux-control-plane
```

---

## 🎯 **Prevention and Best Practices**

### **Proactive Monitoring**
1. **Set up automated health checks**:
   ```bash
   # Create a CronJob for regular health checks
   kubectl apply -f - <<EOF
   apiVersion: batch/v1
   kind: CronJob
   metadata:
     name: health-check
     namespace: reverse-tender
   spec:
     schedule: "*/5 * * * *"
     jobTemplate:
       spec:
         template:
           spec:
             containers:
             - name: health-checker
               image: curlimages/curl
               command:
               - /bin/sh
               - -c
               - curl -f http://api-gateway.reverse-tender.svc.cluster.local/health/deep
             restartPolicy: OnFailure
   EOF
   ```

2. **Monitor key metrics**:
   - FluxCD reconciliation success rate
   - Service response times
   - Error rates
   - Resource utilization

### **Regular Maintenance**
1. **Weekly FluxCD health check**
2. **Monthly configuration backup**
3. **Quarterly disaster recovery testing**
4. **Regular security updates**

---

## 📞 **When to Escalate**

### **Immediate Escalation (P0)**
- Complete service outage lasting >5 minutes
- Data corruption or loss
- Security incidents
- Multiple failed rollback attempts

### **Escalation Procedures**
1. **Notify on-call engineer** via Slack #oncall
2. **Create incident ticket** with severity level
3. **Join incident bridge** if available
4. **Document all actions taken**

---

**Remember**: When in doubt, prioritize service availability. It's better to rollback quickly than to spend time debugging during an outage.
