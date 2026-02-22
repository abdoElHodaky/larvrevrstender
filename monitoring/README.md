# Blue-Green Deployment Monitoring & Alerting

This directory contains comprehensive monitoring and alerting configurations for the blue-green deployment system, implementing AlertManager integration and SLO monitoring to achieve 100% production readiness.

## 🎯 **Components**

### **1. AlertManager Integration** (`alertmanager/`)
- **`alertmanager-config.yaml`** - AlertManager deployment and configuration
- **`alert-rules.yaml`** - Comprehensive alert rules for all components

### **2. SLO Monitoring** (`slo-monitoring/`)
- **`slo-config.yaml`** - SLO recording rules and Grafana dashboard
- **`slo-exporter.yaml`** - Custom SLO metrics exporter

## 🚨 **AlertManager Features**

### **Alert Categories**
- **FluxCD Alerts** - Controller failures, reconciliation issues, suspended resources
- **Deployment Alerts** - Replica mismatches, generation issues, pod failures
- **Blue-Green Alerts** - Environment failures, health check issues, service unavailability
- **Ingress Alerts** - Controller failures, high error rates
- **SLO Alerts** - SLO breaches, error budget exhaustion
- **Resource Alerts** - High CPU/memory usage, disk space issues
- **Database Alerts** - Connection failures, slow queries

### **Notification Channels**
- **Slack Integration** - Real-time alerts to deployment and critical channels
- **Email Notifications** - Critical alerts to on-call and DevOps teams
- **Webhook Support** - Integration with external systems
- **PagerDuty Ready** - Configuration templates for incident management

### **Alert Routing**
- **Critical Alerts** - Immediate notification via Slack + Email
- **FluxCD Alerts** - Deployment team notifications
- **Deployment Alerts** - DevOps team notifications
- **Warning Alerts** - Standard notification channels

## 📊 **SLO Monitoring Features**

### **Service Level Objectives**
- **Deployment Success Rate** - Target: 95%
- **Deployment Duration** - Target: ≤300s (5 minutes)
- **Blue-Green Switch Success** - Target: 99%
- **Service Availability** - Target: 99.9%
- **FluxCD Reconciliation** - Target: 95%

### **SLO Metrics**
- **Success Rate Tracking** - 5m, 1h, 24h windows
- **Duration Percentiles** - P50, P95, P99 measurements
- **Error Budget Calculation** - Remaining budget and burn rates
- **Availability Monitoring** - Service health and uptime

### **SLO Dashboard**
- **Real-time SLO Status** - Current performance vs targets
- **Error Budget Visualization** - Remaining budget and burn rate trends
- **Historical Performance** - Trend analysis and patterns
- **Alert Integration** - Visual indicators for SLO breaches

## 🚀 **Quick Setup**

### **Deploy AlertManager**
```bash
# Apply AlertManager configuration
kubectl apply -f monitoring/alertmanager/alertmanager-config.yaml

# Apply alert rules
kubectl apply -f monitoring/alertmanager/alert-rules.yaml

# Verify deployment
kubectl get pods -n monitoring -l app=alertmanager
```

### **Deploy SLO Monitoring**
```bash
# Apply SLO configuration
kubectl apply -f monitoring/slo-monitoring/slo-config.yaml

# Deploy SLO exporter
kubectl apply -f monitoring/slo-monitoring/slo-exporter.yaml

# Verify SLO exporter
kubectl get pods -n monitoring -l app=slo-exporter
```

### **Configure Notifications**
```bash
# Update Slack webhook URL in alertmanager-config.yaml
sed -i 's/YOUR\/SLACK\/WEBHOOK/T00000000\/B00000000\/XXXXXXXXXXXXXXXXXXXXXXXX/' monitoring/alertmanager/alertmanager-config.yaml

# Update email addresses
sed -i 's/oncall@reverse-tender.com/your-oncall@company.com/' monitoring/alertmanager/alertmanager-config.yaml
sed -i 's/devops@reverse-tender.com/your-devops@company.com/' monitoring/alertmanager/alertmanager-config.yaml

# Reapply configuration
kubectl apply -f monitoring/alertmanager/alertmanager-config.yaml
```

## 📋 **Alert Rules Reference**

### **Critical Alerts**
| Alert | Condition | Duration | Action |
|-------|-----------|----------|--------|
| FluxCDControllerDown | Controller unavailable | 5m | Immediate notification |
| BlueGreenEnvironmentDown | Environment unavailable | 5m | Immediate notification |
| DeploymentSLOBreach | Success rate < 95% | 2m | Immediate notification |
| ErrorBudgetExhausted | Budget < 10% | 1m | Immediate notification |

### **Warning Alerts**
| Alert | Condition | Duration | Action |
|-------|-----------|----------|--------|
| FluxCDReconciliationFailed | Reconciliation failures | 2m | Team notification |
| DeploymentReplicasMismatch | Replica count mismatch | 10m | Team notification |
| PodCrashLooping | Pod restart rate > 0 | 5m | Team notification |
| HighCPUUsage | CPU usage > 80% | 10m | Team notification |

## 🎯 **SLO Targets & Thresholds**

### **Deployment SLOs**
```yaml
Deployment Success Rate:
  Target: 95%
  Warning: < 97%
  Critical: < 95%

Deployment Duration (P95):
  Target: 300s
  Warning: > 300s
  Critical: > 600s

Error Budget:
  Healthy: > 50%
  Warning: 10-50%
  Critical: < 10%
```

### **Service SLOs**
```yaml
Service Availability:
  Target: 99.9%
  Warning: < 99.5%
  Critical: < 99%

Blue-Green Switch Success:
  Target: 99%
  Warning: < 99%
  Critical: < 95%

FluxCD Reconciliation:
  Target: 95%
  Warning: < 97%
  Critical: < 95%
```

## 🔧 **Configuration**

### **Slack Integration**
```yaml
# Update webhook URL in alertmanager-config.yaml
slack_configs:
- api_url: 'https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK'
  channel: '#deployment-alerts'
  title: 'Alert: {{ .GroupLabels.alertname }}'
```

### **Email Configuration**
```yaml
# Update SMTP settings in alertmanager-config.yaml
global:
  smtp_smarthost: 'your-smtp-server:587'
  smtp_from: 'alerts@your-company.com'
  smtp_auth_username: 'your-username'
  smtp_auth_password: 'your-password'
```

### **PagerDuty Integration**
```yaml
# Add PagerDuty receiver in alertmanager-config.yaml
- name: 'pagerduty-critical'
  pagerduty_configs:
  - routing_key: 'YOUR_PAGERDUTY_INTEGRATION_KEY'
    description: 'Critical alert: {{ .GroupLabels.alertname }}'
```

## 📊 **Monitoring Dashboards**

### **SLO Dashboard**
- **URL**: `http://grafana:3000/d/slo-dashboard`
- **Panels**: Success rates, duration metrics, error budgets
- **Refresh**: 30 seconds
- **Time Range**: Last 1 hour (configurable)

### **Alert Dashboard**
- **URL**: `http://grafana:3000/d/alert-dashboard`
- **Panels**: Active alerts, alert history, notification status
- **Integration**: AlertManager API

## 🔍 **Troubleshooting**

### **AlertManager Issues**

#### "AlertManager not receiving alerts"
```bash
# Check Prometheus AlertManager configuration
kubectl get configmap prometheus-config -n monitoring -o yaml

# Verify AlertManager service
kubectl get svc alertmanager -n monitoring

# Check AlertManager logs
kubectl logs -n monitoring deployment/alertmanager
```

#### "Notifications not being sent"
```bash
# Test Slack webhook
curl -X POST -H 'Content-type: application/json' \
  --data '{"text":"Test alert from AlertManager"}' \
  YOUR_SLACK_WEBHOOK_URL

# Check AlertManager configuration
kubectl exec -n monitoring deployment/alertmanager -- amtool config show
```

### **SLO Monitoring Issues**

#### "SLO metrics not appearing"
```bash
# Check SLO exporter logs
kubectl logs -n monitoring deployment/slo-exporter

# Verify metrics endpoint
kubectl port-forward -n monitoring svc/slo-exporter 8080:8080
curl http://localhost:8080/metrics
```

#### "SLO calculations incorrect"
```bash
# Check Prometheus recording rules
kubectl get prometheusrule -n monitoring

# Verify rule evaluation
kubectl port-forward -n monitoring svc/prometheus 9090:9090
# Visit http://localhost:9090/rules
```

## 📈 **Performance Considerations**

### **Resource Requirements**
- **AlertManager**: 100m CPU, 128Mi memory (per replica)
- **SLO Exporter**: 100m CPU, 128Mi memory
- **Additional Prometheus Load**: ~10% increase

### **Retention Policies**
- **Alert History**: 30 days
- **SLO Metrics**: 90 days
- **Detailed Metrics**: 7 days

### **Scaling**
- **AlertManager**: 2 replicas for HA
- **SLO Exporter**: 1 replica (stateless)
- **Notification Rate Limits**: Configured per channel

## 🔄 **Integration with CI/CD**

### **GitHub Actions Integration**
```yaml
- name: Check SLO Status
  run: |
    # Query current SLO status before deployment
    SLO_STATUS=$(curl -s "http://prometheus:9090/api/v1/query?query=deployment_success_rate_24h")
    if [ "$SLO_STATUS" -lt "95" ]; then
      echo "SLO breach detected - deployment blocked"
      exit 1
    fi
```

### **Deployment Gates**
- **Pre-deployment**: Check error budget availability
- **Post-deployment**: Validate SLO impact
- **Rollback Triggers**: Automatic rollback on SLO breach

## 📚 **Additional Resources**

- [AlertManager Documentation](https://prometheus.io/docs/alerting/latest/alertmanager/)
- [SLO Best Practices](https://sre.google/sre-book/service-level-objectives/)
- [Prometheus Recording Rules](https://prometheus.io/docs/prometheus/latest/configuration/recording_rules/)
- [Grafana Dashboard Guide](https://grafana.com/docs/grafana/latest/dashboards/)

---

**Part of Phase 1 Week 4: AlertManager Integration & SLO Monitoring**  
**Laravel Reverse Tender Platform - Blue-Green Deployment Implementation**

