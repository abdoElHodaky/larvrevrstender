# 🚨 Disaster Recovery Procedures
## Laravel Reverse Tender Platform - Blue-Green Deployment

### 📋 **Overview**

This document outlines comprehensive disaster recovery procedures for the Laravel Reverse Tender Platform's blue-green deployment system using FluxCD. These procedures ensure rapid recovery from catastrophic failures while maintaining data integrity and service availability.

---

## 🎯 **Disaster Recovery Objectives**

### **Recovery Time Objectives (RTO)**
- **Critical Services**: 15 minutes
- **Non-Critical Services**: 1 hour
- **Complete System Recovery**: 4 hours

### **Recovery Point Objectives (RPO)**
- **Database**: 5 minutes (continuous replication)
- **Configuration**: 1 minute (Git-based)
- **Application State**: Real-time (stateless design)

---

## 🚨 **Disaster Scenarios and Response**

### **Scenario 1: Complete Kubernetes Cluster Failure**

#### **Detection**
- All services unreachable
- Kubernetes API server not responding
- Monitoring alerts: cluster down

#### **Immediate Response (0-15 minutes)**
```bash
# 1. Assess cluster status
kubectl cluster-info
kubectl get nodes

# 2. If cluster is completely down, initiate cluster recovery
# Contact cloud provider support immediately
# Activate backup cluster if available

# 3. Notify stakeholders
echo "🚨 DISASTER: Complete cluster failure detected at $(date)"
# Send alerts to #incident-response Slack channel
```

#### **Recovery Steps (15-60 minutes)**
```bash
# 1. Restore from backup cluster (if available)
# Switch DNS to backup cluster
# Update load balancer configuration

# 2. If no backup cluster, rebuild from infrastructure as code
terraform apply -var="environment=disaster-recovery"

# 3. Restore FluxCD configuration
flux bootstrap github \
  --owner=$GITHUB_USER \
  --repository=larvrevrstender \
  --branch=v2 \
  --path=./deployment/fluxcd \
  --personal

# 4. Apply blue-green configuration
kubectl apply -f deployment/fluxcd/infrastructure/
kubectl apply -f deployment/fluxcd/environments/
kubectl apply -f deployment/fluxcd/monitoring/
```

#### **Validation Steps**
```bash
# Verify cluster health
kubectl get nodes
kubectl get pods --all-namespaces

# Verify FluxCD
flux check
flux get all

# Verify application services
kubectl exec -n reverse-tender deployment/api-gateway -- curl -s http://localhost/health/deep
```

### **Scenario 2: FluxCD System Failure**

#### **Detection**
- FluxCD controllers not responding
- Deployments stuck in pending state
- Git synchronization failures

#### **Immediate Response (0-5 minutes)**
```bash
# 1. Check FluxCD controller status
kubectl get pods -n flux-system
flux check

# 2. Check recent events
kubectl get events -n flux-system --sort-by='.lastTimestamp'

# 3. Attempt controller restart
kubectl rollout restart deployment/source-controller -n flux-system
kubectl rollout restart deployment/kustomize-controller -n flux-system
```

#### **Recovery Steps (5-30 minutes)**
```bash
# 1. If restart fails, reinstall FluxCD
flux uninstall --silent
flux bootstrap github \
  --owner=$GITHUB_USER \
  --repository=larvrevrstender \
  --branch=v2 \
  --path=./deployment/fluxcd \
  --personal

# 2. Restore blue-green automation
kubectl apply -f deployment/fluxcd/automation/

# 3. Verify reconciliation
flux get kustomizations
```

### **Scenario 3: Database Corruption/Loss**

#### **Detection**
- Database connection failures
- Data integrity errors
- Backup verification failures

#### **Immediate Response (0-10 minutes)**
```bash
# 1. Stop all write operations
kubectl scale deployment api-gateway -n reverse-tender --replicas=0
kubectl scale deployment auth-service -n reverse-tender --replicas=0

# 2. Assess database damage
kubectl exec -n database deployment/mysql -- mysql -u root -p -e "CHECK TABLE users, auctions, bids;"

# 3. Activate read-only mode for remaining services
kubectl patch configmap app-config -n reverse-tender --patch '
data:
  DB_READ_ONLY: "true"
  MAINTENANCE_MODE: "true"
'
```

#### **Recovery Steps (10-120 minutes)**
```bash
# 1. Restore from latest backup
# Identify latest backup
LATEST_BACKUP=$(kubectl exec -n database deployment/mysql -- ls -t /backups/ | head -1)

# Restore database
kubectl exec -n database deployment/mysql -- mysql -u root -p < /backups/$LATEST_BACKUP

# 2. Verify data integrity
kubectl exec -n database deployment/mysql -- mysql -u root -p -e "
SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM auctions;
SELECT COUNT(*) FROM bids;
"

# 3. Restart services gradually
kubectl scale deployment auth-service -n reverse-tender --replicas=2
kubectl scale deployment api-gateway -n reverse-tender --replicas=3

# 4. Remove read-only mode
kubectl patch configmap app-config -n reverse-tender --patch '
data:
  DB_READ_ONLY: "false"
  MAINTENANCE_MODE: "false"
'
```

### **Scenario 4: Blue-Green Deployment Corruption**

#### **Detection**
- Both environments unhealthy
- Traffic routing failures
- Configuration inconsistencies

#### **Immediate Response (0-5 minutes)**
```bash
# 1. Emergency traffic routing to known good state
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

# 2. Reset blue-green configuration
kubectl patch configmap blue-green-config -n reverse-tender --patch '
data:
  ENVIRONMENT_COLOR: "blue"
  DEPLOYMENT_STATUS: "emergency_reset"
  EMERGENCY_TIMESTAMP: "'$(date -u +%Y-%m-%dT%H:%M:%SZ)'"
'
```

#### **Recovery Steps (5-45 minutes)**
```bash
# 1. Suspend all automated deployments
kubectl patch kustomization blue-green-deployment -n flux-system --patch '{"spec":{"suspend":true}}'
kubectl patch kustomization green-environment-standby -n flux-system --patch '{"spec":{"suspend":true}}'

# 2. Clean up corrupted resources
kubectl delete deployment --all -n reverse-tender
kubectl delete service --all -n reverse-tender
kubectl delete configmap --all -n reverse-tender

# 3. Restore from Git
kubectl apply -f deployment/k8s/base/
kubectl apply -f deployment/k8s/overlays/blue/

# 4. Verify and reactivate
kubectl wait --for=condition=available --timeout=300s deployment/api-gateway -n reverse-tender
kubectl patch kustomization blue-green-deployment -n flux-system --patch '{"spec":{"suspend":false}}'
```

### **Scenario 5: Git Repository Compromise**

#### **Detection**
- Unauthorized commits
- Malicious configuration changes
- Security alerts from GitHub

#### **Immediate Response (0-10 minutes)**
```bash
# 1. Suspend all FluxCD operations
flux suspend kustomization --all

# 2. Revert to known good commit
git revert <malicious-commit-hash>
git push origin v2

# 3. Rotate all secrets
kubectl delete secret flux-system -n flux-system
flux create secret git flux-system \
  --url=https://github.com/abdoElHodaky/larvrevrstender \
  --username=$GITHUB_USER \
  --password=$NEW_GITHUB_TOKEN
```

#### **Recovery Steps (10-60 minutes)**
```bash
# 1. Audit all changes
git log --oneline --since="24 hours ago"
git diff HEAD~10 HEAD

# 2. Restore clean configuration
git checkout <known-good-commit>
git push --force-with-lease origin v2

# 3. Resume operations
flux resume kustomization --all

# 4. Security audit
# Review all access logs
# Update security policies
# Notify security team
```

---

## 🔄 **Recovery Procedures by Component**

### **FluxCD Controllers Recovery**
```bash
#!/bin/bash
# fluxcd-recovery.sh

echo "🔄 Starting FluxCD recovery..."

# 1. Check current status
flux check || echo "FluxCD check failed"

# 2. Restart controllers
kubectl rollout restart deployment/source-controller -n flux-system
kubectl rollout restart deployment/kustomize-controller -n flux-system
kubectl rollout restart deployment/helm-controller -n flux-system

# 3. Wait for rollout
kubectl rollout status deployment/source-controller -n flux-system --timeout=300s
kubectl rollout status deployment/kustomize-controller -n flux-system --timeout=300s

# 4. Verify recovery
flux get all

echo "✅ FluxCD recovery completed"
```

### **Application Services Recovery**
```bash
#!/bin/bash
# services-recovery.sh

SERVICES=("api-gateway" "auth-service" "user-service" "auction-service" "bidding-service" "payment-service" "order-service" "notification-service" "analytics-service")

echo "🔄 Starting services recovery..."

for service in "${SERVICES[@]}"; do
    echo "Recovering $service..."
    
    # Restart service
    kubectl rollout restart deployment/$service -n reverse-tender
    
    # Wait for rollout
    kubectl rollout status deployment/$service -n reverse-tender --timeout=300s
    
    # Verify health
    kubectl exec -n reverse-tender deployment/$service -- curl -s http://localhost/health || echo "⚠️ $service health check failed"
done

echo "✅ Services recovery completed"
```

### **Database Recovery**
```bash
#!/bin/bash
# database-recovery.sh

echo "🔄 Starting database recovery..."

# 1. Stop all write operations
kubectl scale deployment api-gateway -n reverse-tender --replicas=0

# 2. Create database backup before recovery
kubectl exec -n database deployment/mysql -- mysqldump -u root -p --all-databases > pre-recovery-backup-$(date +%Y%m%d_%H%M%S).sql

# 3. Restore from latest backup
LATEST_BACKUP=$(kubectl exec -n database deployment/mysql -- ls -t /backups/ | head -1)
kubectl exec -n database deployment/mysql -- mysql -u root -p < /backups/$LATEST_BACKUP

# 4. Verify data integrity
kubectl exec -n database deployment/mysql -- mysql -u root -p -e "
SELECT 'Users' as table_name, COUNT(*) as count FROM users
UNION ALL
SELECT 'Auctions', COUNT(*) FROM auctions
UNION ALL
SELECT 'Bids', COUNT(*) FROM bids;
"

# 5. Restart services
kubectl scale deployment api-gateway -n reverse-tender --replicas=3

echo "✅ Database recovery completed"
```

---

## 📊 **Monitoring and Alerting During Recovery**

### **Critical Metrics to Monitor**
```bash
# Service availability
kubectl get pods -n reverse-tender -o wide

# FluxCD health
flux get all

# Database connectivity
kubectl exec -n reverse-tender deployment/api-gateway -- curl -s http://localhost/health | jq '.database'

# Traffic routing
curl -H "Host: api.reversetender.com" http://api-gateway.reverse-tender.svc.cluster.local/health
```

### **Recovery Progress Dashboard**
Create a temporary dashboard to monitor recovery:
```yaml
# recovery-dashboard.yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: recovery-dashboard
  namespace: monitoring
  labels:
    grafana_dashboard: "1"
data:
  recovery-dashboard.json: |
    {
      "dashboard": {
        "title": "Disaster Recovery Progress",
        "panels": [
          {
            "title": "Service Health Status",
            "type": "stat",
            "targets": [
              {
                "expr": "up{job=\"reverse-tender\"}",
                "legendFormat": "{{instance}}"
              }
            ]
          },
          {
            "title": "FluxCD Reconciliation",
            "type": "graph",
            "targets": [
              {
                "expr": "gotk_reconcile_condition{type=\"Ready\",status=\"True\"}",
                "legendFormat": "{{kind}}/{{name}}"
              }
            ]
          }
        ]
      }
    }
```

---

## 📞 **Communication During Disasters**

### **Incident Response Team**
- **Incident Commander**: Platform Team Lead
- **Technical Lead**: Senior DevOps Engineer
- **Communications Lead**: Engineering Manager
- **Database Expert**: Database Administrator

### **Communication Channels**
- **Primary**: Slack #incident-response
- **Secondary**: Conference bridge [+1-XXX-XXX-XXXX]
- **External**: Status page updates

### **Status Update Template**
```
🚨 INCIDENT UPDATE - [TIMESTAMP]

Status: [INVESTIGATING/IDENTIFIED/MONITORING/RESOLVED]
Impact: [Description of user impact]
Current Actions: [What we're doing now]
ETA: [Expected resolution time]
Next Update: [When next update will be provided]

Incident Commander: [Name]
```

---

## 🔐 **Security Considerations During Recovery**

### **Access Control**
- Limit access to essential personnel only
- Use break-glass procedures for emergency access
- Log all actions taken during recovery
- Rotate credentials after recovery

### **Data Protection**
- Ensure backups are encrypted
- Verify backup integrity before restoration
- Maintain audit trail of all data operations
- Follow data retention policies

### **Network Security**
- Isolate affected systems if compromise suspected
- Monitor for unusual network activity
- Update firewall rules if necessary
- Coordinate with security team

---

## 📝 **Post-Disaster Procedures**

### **Immediate Post-Recovery (0-2 hours)**
```bash
# 1. Verify all services are healthy
kubectl exec -n reverse-tender deployment/api-gateway -- curl -s http://localhost/health/deep

# 2. Run comprehensive health checks
./scripts/comprehensive-health-check.sh

# 3. Verify data integrity
./scripts/data-integrity-check.sh

# 4. Update monitoring dashboards
kubectl apply -f deployment/fluxcd/dashboards/
```

### **Short-term Post-Recovery (2-24 hours)**
- [ ] Conduct post-incident review
- [ ] Document lessons learned
- [ ] Update disaster recovery procedures
- [ ] Test backup and recovery processes
- [ ] Review and update monitoring alerts

### **Long-term Post-Recovery (1-7 days)**
- [ ] Implement improvements identified in post-incident review
- [ ] Update disaster recovery documentation
- [ ] Conduct disaster recovery training
- [ ] Review and update RTO/RPO objectives
- [ ] Enhance monitoring and alerting

---

## 🧪 **Disaster Recovery Testing**

### **Monthly Tests**
```bash
#!/bin/bash
# monthly-dr-test.sh

echo "🧪 Starting monthly disaster recovery test..."

# 1. Test backup restoration (non-production)
kubectl create namespace dr-test
kubectl apply -f deployment/k8s/base/ -n dr-test

# 2. Test FluxCD recovery
flux suspend kustomization blue-green-deployment
sleep 60
flux resume kustomization blue-green-deployment

# 3. Test service recovery
kubectl delete pod -l app=api-gateway -n reverse-tender
kubectl wait --for=condition=ready pod -l app=api-gateway -n reverse-tender --timeout=300s

# 4. Cleanup
kubectl delete namespace dr-test

echo "✅ Monthly disaster recovery test completed"
```

### **Quarterly Full Tests**
- Complete cluster rebuild simulation
- Full database restoration test
- End-to-end recovery validation
- Team coordination exercises

---

## 📚 **Emergency Contacts and Resources**

### **24/7 Emergency Contacts**
- **On-call Engineer**: [+1-XXX-XXX-XXXX]
- **Platform Team Lead**: [+1-XXX-XXX-XXXX]
- **Database Administrator**: [+1-XXX-XXX-XXXX]
- **Security Team**: [+1-XXX-XXX-XXXX]

### **Vendor Support**
- **Cloud Provider**: [Support portal URL]
- **Database Vendor**: [Support phone/email]
- **Monitoring Vendor**: [Support contact]

### **Critical Resources**
- **Backup Storage**: [Location/credentials]
- **Infrastructure as Code**: [Repository URL]
- **Runbook Repository**: [Repository URL]
- **Status Page**: [URL for customer communications]

---

## 🎯 **Recovery Validation Checklist**

### **System Recovery Validation**
- [ ] All Kubernetes nodes are healthy
- [ ] FluxCD controllers are running and reconciling
- [ ] All application services are deployed and healthy
- [ ] Database is accessible and data integrity verified
- [ ] Monitoring and alerting systems are functional

### **Application Recovery Validation**
- [ ] All health check endpoints responding
- [ ] User authentication working
- [ ] Core business functions operational
- [ ] Data consistency verified across services
- [ ] Performance metrics within acceptable ranges

### **Operational Recovery Validation**
- [ ] Blue-green deployment automation functional
- [ ] Monitoring dashboards accessible
- [ ] Alerting rules active and tested
- [ ] Backup processes resumed
- [ ] Documentation updated with lessons learned

---

**Remember**: The goal of disaster recovery is not just to restore systems, but to restore confidence. Thorough validation and clear communication are as important as technical recovery procedures.
