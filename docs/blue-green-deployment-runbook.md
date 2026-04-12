# 🔄 Blue-Green Deployment Runbook
## Laravel Reverse Tender Platform

### 📋 **Overview**

This runbook provides operational procedures for executing blue-green deployments using FluxCD automation on the Laravel Reverse Tender Platform.

---

## 🎯 **Deployment Process Overview**

### **Blue-Green Deployment Flow**
1. **Pre-deployment validation** - Current environment health check
2. **New environment activation** - Deploy to standby environment
3. **Health validation** - Comprehensive service health checks
4. **Traffic switching** - Route traffic to new environment
5. **Post-deployment monitoring** - Monitor for issues
6. **Environment cleanup** - Suspend old environment

### **Key Metrics**
- **Deployment Duration**: Target <5 minutes
- **Rollback Time**: Target <30 seconds
- **Success Rate**: Target 99.9%
- **Health Threshold**: 7/9 services minimum

---

## 🚀 **Standard Deployment Procedure**

### **Step 1: Pre-Deployment Checklist**

```bash
# Check current environment status
kubectl get configmap blue-green-config -n reverse-tender -o yaml

# Verify FluxCD health
flux check

# Check current environment health
kubectl exec -n reverse-tender deployment/api-gateway -- curl -s http://localhost/health/deep | jq '.'

# Verify monitoring dashboards are accessible
# - FluxCD Control Plane dashboard
# - Blue-Green Deployment dashboard
```

**Pre-deployment Validation Checklist:**
- [ ] Current environment is healthy (all services responding)
- [ ] FluxCD controllers are running and reconciling
- [ ] No ongoing deployments or maintenance
- [ ] Monitoring dashboards are accessible
- [ ] On-call engineer is available

### **Step 2: Trigger Blue-Green Deployment**

#### **Option A: Automated Deployment (Recommended)**
```bash
# Create deployment job
kubectl apply -f deployment/fluxcd/automation/deployment-job.yaml

# Monitor deployment progress
kubectl logs -n flux-system job/blue-green-deployment-job -f
```

#### **Option B: Manual Deployment Steps**
```bash
# Get current environment color
CURRENT_COLOR=$(kubectl get configmap blue-green-config -n reverse-tender -o jsonpath='{.data.ENVIRONMENT_COLOR}')
NEW_COLOR="green"
if [ "$CURRENT_COLOR" = "green" ]; then
    NEW_COLOR="blue"
fi

echo "Deploying from $CURRENT_COLOR to $NEW_COLOR environment"

# Activate standby environment
if [ "$NEW_COLOR" = "green" ]; then
    kubectl patch kustomization green-environment-standby -n flux-system --patch '{"spec":{"suspend":false}}'
else
    kubectl patch kustomization blue-green-deployment -n flux-system --patch '{"spec":{"suspend":false}}'
fi

# Wait for deployment to complete
kubectl wait --for=condition=Ready kustomization/${NEW_COLOR}-environment* -n flux-system --timeout=600s
```

### **Step 3: Monitor Deployment Progress**

```bash
# Watch deployment status
watch kubectl get kustomizations -n flux-system

# Monitor service health
watch kubectl exec -n reverse-tender deployment/api-gateway-${NEW_COLOR} -- curl -s http://localhost/health/deep

# Check Grafana dashboards
# - Blue-Green Deployment dashboard for real-time metrics
# - FluxCD Control Plane dashboard for reconciliation status
```

**Monitoring Checklist:**
- [ ] New environment Kustomization shows "Ready: True"
- [ ] All services are healthy in new environment
- [ ] Service discovery validation passes
- [ ] Cross-environment connectivity verified

### **Step 4: Traffic Switch Validation**

```bash
# Verify traffic switch completed
ACTIVE_COLOR=$(kubectl get configmap blue-green-config -n reverse-tender -o jsonpath='{.data.ENVIRONMENT_COLOR}')
echo "Active environment: $ACTIVE_COLOR"

# Test traffic routing
curl -H "Host: api.reversetender.com" http://api-gateway-${ACTIVE_COLOR}.reverse-tender.svc.cluster.local/health

# Monitor error rates
kubectl exec -n reverse-tender deployment/api-gateway-${ACTIVE_COLOR} -- curl -s http://localhost/metrics | grep http_requests_total
```

### **Step 5: Post-Deployment Monitoring**

```bash
# Monitor for 5 minutes after deployment
for i in {1..10}; do
    echo "=== Health Check $i/10 ==="
    kubectl exec -n reverse-tender deployment/api-gateway -- curl -s http://localhost/health | jq '.status'
    sleep 30
done

# Check error rates in Grafana
# - Response time should be stable
# - Error rate should be <5%
# - All services should be healthy
```

---

## 🚨 **Emergency Rollback Procedure**

### **When to Rollback**
- Health checks failing for >2 minutes
- Error rate >5% for >1 minute
- Critical service unavailable
- Performance degradation >20%

### **Immediate Rollback Steps**

```bash
# Execute emergency rollback
kubectl exec -n flux-system deployment/kustomize-controller -- /scripts/rollback_script

# OR manual rollback
PREVIOUS_COLOR=$(kubectl get configmap blue-green-config -n reverse-tender -o jsonpath='{.data.PREVIOUS_COLOR}')

# Switch ingress back
kubectl patch ingress reverse-tender-ingress -n reverse-tender --patch "
spec:
  rules:
  - host: api.reversetender.com
    http:
      paths:
      - path: /
        pathType: Prefix
        backend:
          service:
            name: api-gateway-$PREVIOUS_COLOR
            port:
              number: 80
"

# Update ConfigMap
kubectl patch configmap blue-green-config -n reverse-tender --patch "
data:
  ENVIRONMENT_COLOR: \"$PREVIOUS_COLOR\"
  ROLLBACK_TIMESTAMP: \"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"
"
```

### **Post-Rollback Validation**
```bash
# Verify rollback completed
kubectl get configmap blue-green-config -n reverse-tender -o jsonpath='{.data.ENVIRONMENT_COLOR}'

# Test service health
kubectl exec -n reverse-tender deployment/api-gateway -- curl -s http://localhost/health

# Monitor for 10 minutes to ensure stability
```

---

## 🔍 **Health Check Procedures**

### **Service Health Validation**
```bash
# Check all service endpoints
services=("api-gateway" "auth-service" "user-service" "auction-service" "bidding-service" "payment-service" "order-service" "notification-service" "analytics-service")

for service in "${services[@]}"; do
    echo "Checking $service..."
    kubectl exec -n reverse-tender deployment/$service -- curl -s http://localhost/health | jq '.status'
done
```

### **Cross-Environment Health Check**
```bash
# Validate cross-environment connectivity
kubectl exec -n reverse-tender deployment/api-gateway -- curl -s http://localhost/health/cross-environment | jq '.'
```

### **Database Connectivity Check**
```bash
# Check database connections
kubectl exec -n reverse-tender deployment/api-gateway -- curl -s http://localhost/health | jq '.database'

# Check Redis connectivity
kubectl exec -n reverse-tender deployment/api-gateway -- curl -s http://localhost/health | jq '.redis'
```

---

## 📊 **Monitoring and Alerting**

### **Key Dashboards**
1. **FluxCD Control Plane**
   - Reconciliation success rate
   - Controller resource usage
   - Git sync status

2. **Blue-Green Deployment**
   - Active environment status
   - Traffic distribution
   - Response times by environment
   - Service health scores

### **Critical Alerts**
- `BlueGreenDeploymentStuck` - Deployment taking >30 minutes
- `BlueGreenEnvironmentUnhealthy` - Environment health <80%
- `FluxReconciliationFailure` - FluxCD sync failures
- `ServiceDiscoveryHighErrorRate` - Service errors >5%

### **Alert Response Procedures**

#### **BlueGreenDeploymentStuck**
1. Check deployment job logs: `kubectl logs -n flux-system job/blue-green-deployment-job`
2. Verify Kustomization status: `kubectl get kustomizations -n flux-system`
3. Check service health in new environment
4. Consider manual rollback if deployment exceeds 45 minutes

#### **BlueGreenEnvironmentUnhealthy**
1. Identify unhealthy services: `kubectl exec -n reverse-tender deployment/api-gateway -- curl -s http://localhost/health/deep`
2. Check service logs for errors
3. Verify database and Redis connectivity
4. Initiate rollback if health doesn't improve within 5 minutes

---

## 🛠️ **Troubleshooting Guide**

### **Common Issues and Solutions**

#### **1. Deployment Stuck in "Progressing" State**
```bash
# Check Kustomization status
kubectl describe kustomization blue-green-deployment -n flux-system

# Check for resource conflicts
kubectl get events -n reverse-tender --sort-by='.lastTimestamp'

# Solution: Check for resource quotas, node capacity, or image pull issues
```

#### **2. Health Checks Failing**
```bash
# Check service logs
kubectl logs -n reverse-tender deployment/api-gateway --tail=100

# Check service endpoints
kubectl get endpoints -n reverse-tender

# Solution: Verify service configuration, database connectivity, and resource limits
```

#### **3. Traffic Not Switching**
```bash
# Check ingress configuration
kubectl describe ingress reverse-tender-ingress -n reverse-tender

# Check service selectors
kubectl get services -n reverse-tender -o wide

# Solution: Verify ingress controller, service labels, and DNS resolution
```

#### **4. FluxCD Reconciliation Failures**
```bash
# Check GitRepository status
flux get sources git

# Check for authentication issues
kubectl logs -n flux-system deployment/source-controller

# Solution: Verify Git credentials, branch permissions, and network connectivity
```

### **Performance Issues**

#### **High Response Times**
1. Check resource utilization: `kubectl top pods -n reverse-tender`
2. Verify database performance
3. Check for network latency issues
4. Consider scaling up resources

#### **High Error Rates**
1. Check application logs for exceptions
2. Verify database connectivity
3. Check for rate limiting or throttling
4. Review recent code changes

---

## 📞 **Escalation Procedures**

### **Severity Levels**

#### **P0 - Critical (Immediate Response)**
- Complete service outage
- Data corruption or loss
- Security breach
- **Response Time**: 15 minutes
- **Escalation**: Immediately notify on-call engineer and platform team lead

#### **P1 - High (1 Hour Response)**
- Partial service degradation
- Failed deployment with successful rollback
- Performance issues affecting users
- **Response Time**: 1 hour
- **Escalation**: Notify platform team during business hours

#### **P2 - Medium (4 Hour Response)**
- Non-critical service issues
- Monitoring alerts without user impact
- **Response Time**: 4 hours
- **Escalation**: Create ticket for platform team

### **Contact Information**
- **On-call Engineer**: [oncall@company.com] / Slack: #oncall
- **Platform Team Lead**: [platform-lead@company.com]
- **DevOps Team**: [devops@company.com] / Slack: #devops
- **Engineering Manager**: [eng-manager@company.com]

---

## 📝 **Post-Deployment Checklist**

### **Immediate (0-30 minutes)**
- [ ] Verify deployment completed successfully
- [ ] Confirm all services are healthy
- [ ] Check error rates and response times
- [ ] Validate traffic routing to new environment
- [ ] Monitor critical business metrics

### **Short-term (30 minutes - 2 hours)**
- [ ] Monitor application performance
- [ ] Check for any user-reported issues
- [ ] Verify database performance
- [ ] Review deployment metrics in Grafana
- [ ] Update deployment status in tracking system

### **Long-term (2-24 hours)**
- [ ] Analyze deployment metrics and performance
- [ ] Document any issues or improvements
- [ ] Update runbook based on lessons learned
- [ ] Schedule post-deployment review if needed
- [ ] Clean up old environment resources

---

## 📚 **Additional Resources**

- [FluxCD Setup Guide](./fluxcd-setup-guide.md)
- [Troubleshooting Guide](./troubleshooting-guide.md)
- [Disaster Recovery Procedures](./disaster-recovery-procedures.md)
- [FluxCD Documentation](https://fluxcd.io/docs/)
- [Kubernetes Troubleshooting](https://kubernetes.io/docs/tasks/debug-application-cluster/)

---

**Emergency Hotline**: For critical issues outside business hours, call the emergency hotline: [+1-XXX-XXX-XXXX]
