# <span style="font-size: 42px; font-weight: 700; line-height: 1.618;">🔧 Troubleshooting Guide</span>
## <span style="font-size: 20px; font-weight: 500; line-height: 1.618; color: #4ECDC4;">Version 2.0 - Multi-Tier Caching Architecture</span>

<p style="font-size: 16px; line-height: 1.618; margin-bottom: 2rem;">This guide helps diagnose and resolve common issues with the <strong>Reverse Tender Platform V2</strong> deployment and operation, including <strong>multi-tier caching troubleshooting</strong>.</p>

<div style="margin: 2rem 0; padding: 1.5rem; background: linear-gradient(135deg, #FF6B6B10, #4ECDC410); border-radius: 12px; border-left: 4px solid #FF6B6B;">

### <span style="font-size: 18px; font-weight: 600; color: #FF6B6B;">🚀 V2 Troubleshooting Features</span>

**Multi-Tier Cache Monitoring:**
- **L1 (Varnish)**: HTTP cache hit/miss ratios, response times, VCL errors
- **L2 (Upstash Redis)**: Connection health, memory usage, command latency
- **L3 (MongoDB Atlas)**: Cluster status, query performance, connection pooling
- **Cache Coordination**: Cross-tier invalidation, fallback mechanisms

**Performance Diagnostics:**
- Cache layer performance metrics and bottleneck identification
- Multi-tier cache hit ratio analysis and optimization recommendations
- Intelligent cache warming and invalidation troubleshooting

</div>

## 📋 Table of Contents

- [Multi-Tier Caching Issues](#multi-tier-caching-issues) **🆕 V2**
- [Common Issues](#common-issues)
- [Deployment Problems](#deployment-problems)
- [Service Discovery Issues](#service-discovery-issues)
- [Configuration Problems](#configuration-problems)
- [Performance Issues](#performance-issues)
- [Monitoring and Debugging](#monitoring-and-debugging)
- [Emergency Procedures](#emergency-procedures)

## 🚀 Multi-Tier Caching Issues

### Varnish Cache (L1) Problems

#### Symptoms
- High cache miss ratios (below 90%)
- Slow response times despite caching
- VCL compilation errors

#### Diagnosis
```bash
# Check Varnish cache statistics
varnishstat -1

# Monitor cache hit/miss ratios
varnishstat -f MAIN.cache_hit -f MAIN.cache_miss

# Check VCL configuration
varnishadm vcl.list
varnishadm vcl.show <vcl-name>

# Monitor Varnish logs
varnishlog -q "VCL_Error"
```

#### Solutions
```bash
# Restart Varnish service
docker-compose restart varnish

# Reload VCL configuration
varnishadm vcl.load new_config /etc/varnish/default.vcl
varnishadm vcl.use new_config

# Clear Varnish cache
varnishadm ban req.url ~ ".*"
```

### Upstash Redis (L2) Problems

#### Symptoms
- Connection timeouts to Redis
- High memory usage warnings
- Slow Redis command execution

#### Diagnosis
```bash
# Check Redis connection
redis-cli -h <upstash-host> -p <port> --tls ping

# Monitor Redis memory usage
redis-cli -h <upstash-host> -p <port> --tls info memory

# Check command latency
redis-cli -h <upstash-host> -p <port> --tls --latency

# Monitor slow queries
redis-cli -h <upstash-host> -p <port> --tls slowlog get 10
```

#### Solutions
```bash
# Clear Redis cache
redis-cli -h <upstash-host> -p <port> --tls flushall

# Check connection pool settings
# Update REDIS_POOL_SIZE in .env files

# Monitor connection health
redis-cli -h <upstash-host> -p <port> --tls client list
```

### MongoDB Atlas (L3) Problems

#### Symptoms
- Slow database queries
- Connection pool exhaustion
- Atlas cluster scaling issues

#### Diagnosis
```bash
# Check MongoDB connection
mongosh "mongodb+srv://<cluster-url>" --eval "db.runCommand('ping')"

# Monitor query performance
# Use MongoDB Atlas Performance Advisor

# Check connection pool status
# Monitor application logs for connection errors
```

#### Solutions
```bash
# Optimize database indexes
# Use MongoDB Atlas Index Advisor

# Scale cluster resources
# Adjust cluster tier in Atlas dashboard

# Update connection string settings
# Increase maxPoolSize in connection string
```

## 🚨 Common Issues

### Pod Startup Failures

#### Symptoms
- Pods stuck in `Pending`, `CrashLoopBackOff`, or `ImagePullBackOff` state
- Services not responding to health checks

#### Diagnosis
```bash
# Check pod status
kubectl get pods -n <namespace>

# Describe problematic pod
kubectl describe pod <pod-name> -n <namespace>

# Check pod logs
kubectl logs <pod-name> -n <namespace>

# Check previous container logs if pod restarted
kubectl logs <pod-name> -n <namespace> --previous
```

#### Common Causes & Solutions

1. **Image Pull Errors**
   ```bash
   # Check if image exists and is accessible
   docker pull <image-name>:<tag>
   
   # Verify image registry credentials
   kubectl get secrets -n <namespace>
   kubectl describe secret <registry-secret> -n <namespace>
   ```

2. **Resource Constraints**
   ```bash
   # Check node resources
   kubectl top nodes
   kubectl describe nodes
   
   # Adjust resource requests/limits
   kubectl patch deployment <deployment-name> -p='{"spec":{"template":{"spec":{"containers":[{"name":"<container-name>","resources":{"requests":{"memory":"256Mi","cpu":"200m"}}}]}}}}' -n <namespace>
   ```

3. **Configuration Issues**
   ```bash
   # Check ConfigMaps
   kubectl get configmaps -n <namespace>
   kubectl describe configmap <configmap-name> -n <namespace>
   
   # Check Secrets
   kubectl get secrets -n <namespace>
   kubectl describe secret <secret-name> -n <namespace>
   ```

### Service Discovery Issues

#### Symptoms
- Services cannot communicate with each other
- DNS resolution failures
- Connection timeouts between services

#### Diagnosis
```bash
# Check service endpoints
kubectl get endpoints -n <namespace>

# Check service configuration
kubectl get svc -n <namespace>
kubectl describe svc <service-name> -n <namespace>

# Test DNS resolution from within a pod
kubectl exec -it <pod-name> -n <namespace> -- nslookup <service-name>

# Test service connectivity
kubectl exec -it <pod-name> -n <namespace> -- curl http://<service-name>:<port>/health
```

#### Solutions

1. **Service Port Mismatch**
   ```bash
   # Verify service ports match container ports
   kubectl get svc <service-name> -o yaml -n <namespace>
   kubectl get deployment <deployment-name> -o yaml -n <namespace>
   ```

2. **Network Policy Issues**
   ```bash
   # Check network policies
   kubectl get networkpolicies -n <namespace>
   kubectl describe networkpolicy <policy-name> -n <namespace>
   ```

3. **DNS Issues**
   ```bash
   # Check CoreDNS status
   kubectl get pods -n kube-system | grep coredns
   kubectl logs -n kube-system deployment/coredns
   ```

## 🚀 Deployment Problems

### Kustomize Build Failures

#### Symptoms
- `kustomize build` command fails
- Invalid YAML output
- Missing resources in generated manifests

#### Diagnosis
```bash
# Test kustomize build
kustomize build deployment/k8s/overlays/<environment>

# Validate YAML syntax
kustomize build deployment/k8s/overlays/<environment> | kubectl apply --dry-run=client -f -

# Check for missing files
ls -la deployment/k8s/base/
ls -la deployment/k8s/overlays/<environment>/
```

#### Solutions

1. **Missing Base Resources**
   ```bash
   # Ensure all referenced files exist
   cat deployment/k8s/base/kustomization.yaml
   
   # Check file paths
   find deployment/k8s/base/ -name "*.yaml"
   ```

2. **Invalid Patches**
   ```bash
   # Validate patch syntax
   kubectl patch --dry-run=client deployment <name> --patch-file=<patch-file>
   ```

### Rolling Update Failures

#### Symptoms
- Deployments stuck in rolling update
- New pods not starting
- Service disruption during updates

#### Diagnosis
```bash
# Check rollout status
kubectl rollout status deployment/<deployment-name> -n <namespace>

# Check rollout history
kubectl rollout history deployment/<deployment-name> -n <namespace>

# Check replica sets
kubectl get rs -n <namespace>
```

#### Solutions

1. **Rollback to Previous Version**
   ```bash
   # Rollback deployment
   kubectl rollout undo deployment/<deployment-name> -n <namespace>
   
   # Rollback to specific revision
   kubectl rollout undo deployment/<deployment-name> --to-revision=<revision> -n <namespace>
   ```

2. **Fix and Retry**
   ```bash
   # Pause rollout
   kubectl rollout pause deployment/<deployment-name> -n <namespace>
   
   # Fix issues and resume
   kubectl rollout resume deployment/<deployment-name> -n <namespace>
   ```

## ⚙️ Configuration Problems

### ConfigMap Issues

#### Symptoms
- Services using wrong configuration values
- Configuration not updating after changes
- Missing environment variables

#### Diagnosis
```bash
# Check ConfigMap contents
kubectl get configmap <configmap-name> -o yaml -n <namespace>

# Check if pods are using correct ConfigMap
kubectl describe pod <pod-name> -n <namespace> | grep -A 10 "Environment"

# Check ConfigMap references in deployments
kubectl get deployment <deployment-name> -o yaml -n <namespace> | grep -A 5 configMap
```

#### Solutions

1. **Update ConfigMap**
   ```bash
   # Update ConfigMap
   kubectl patch configmap <configmap-name> -p='{"data":{"KEY":"new-value"}}' -n <namespace>
   
   # Restart deployment to pick up changes
   kubectl rollout restart deployment/<deployment-name> -n <namespace>
   ```

2. **Fix ConfigMap References**
   ```bash
   # Check deployment configuration
   kubectl edit deployment <deployment-name> -n <namespace>
   ```

### Secret Issues

#### Symptoms
- Authentication failures
- Database connection errors
- External service API failures

#### Diagnosis
```bash
# Check secret existence
kubectl get secrets -n <namespace>

# Check secret contents (base64 encoded)
kubectl get secret <secret-name> -o yaml -n <namespace>

# Decode secret values
kubectl get secret <secret-name> -o jsonpath='{.data.KEY}' -n <namespace> | base64 -d
```

#### Solutions

1. **Update Secrets**
   ```bash
   # Update secret
   kubectl patch secret <secret-name> -p='{"data":{"KEY":"bmV3LXZhbHVl"}}' -n <namespace>
   
   # Restart pods to pick up new secrets
   kubectl rollout restart deployment/<deployment-name> -n <namespace>
   ```

2. **Create Missing Secrets**
   ```bash
   # Create secret from literal
   kubectl create secret generic <secret-name> --from-literal=KEY=value -n <namespace>
   
   # Create secret from file
   kubectl create secret generic <secret-name> --from-file=KEY=./secret-file -n <namespace>
   ```

## 📊 Performance Issues

### High Resource Usage

#### Symptoms
- Pods consuming excessive CPU/memory
- Node resource exhaustion
- Slow response times

#### Diagnosis
```bash
# Check resource usage
kubectl top pods -n <namespace>
kubectl top nodes

# Check resource limits
kubectl describe pod <pod-name> -n <namespace> | grep -A 10 "Limits"

# Check HPA status
kubectl get hpa -n <namespace>
kubectl describe hpa <hpa-name> -n <namespace>
```

#### Solutions

1. **Adjust Resource Limits**
   ```bash
   # Update resource limits
   kubectl patch deployment <deployment-name> -p='{"spec":{"template":{"spec":{"containers":[{"name":"<container>","resources":{"limits":{"memory":"1Gi","cpu":"500m"}}}]}}}}' -n <namespace>
   ```

2. **Scale Services**
   ```bash
   # Manual scaling
   kubectl scale deployment <deployment-name> --replicas=<count> -n <namespace>
   
   # Enable HPA
   kubectl autoscale deployment <deployment-name> --cpu-percent=70 --min=2 --max=10 -n <namespace>
   ```

### Database Connection Issues

#### Symptoms
- Database connection timeouts
- Connection pool exhaustion
- Slow database queries

#### Diagnosis
```bash
# Check database pod status
kubectl get pods -l app=mysql -n <namespace>

# Check database logs
kubectl logs <mysql-pod> -n <namespace>

# Test database connectivity
kubectl exec -it <app-pod> -n <namespace> -- mysql -h <db-host> -u <user> -p
```

#### Solutions

1. **Check Database Configuration**
   ```bash
   # Verify database connection settings
   kubectl get configmap <db-config> -o yaml -n <namespace>
   
   # Check database secrets
   kubectl get secret <db-secret> -o yaml -n <namespace>
   ```

2. **Scale Database Resources**
   ```bash
   # Increase database resources
   kubectl patch deployment mysql -p='{"spec":{"template":{"spec":{"containers":[{"name":"mysql","resources":{"limits":{"memory":"2Gi","cpu":"1000m"}}}]}}}}' -n <namespace>
   ```

## 🔍 Monitoring and Debugging

### Log Analysis

```bash
# View logs from all pods in deployment
kubectl logs -f deployment/<deployment-name> -n <namespace>

# View logs from specific container
kubectl logs <pod-name> -c <container-name> -n <namespace>

# View logs with timestamps
kubectl logs <pod-name> --timestamps -n <namespace>

# Follow logs from multiple pods
kubectl logs -f -l app=<app-label> -n <namespace>
```

### Health Check Debugging

```bash
# Check liveness probe
kubectl describe pod <pod-name> -n <namespace> | grep -A 5 "Liveness"

# Check readiness probe
kubectl describe pod <pod-name> -n <namespace> | grep -A 5 "Readiness"

# Test health endpoints manually
kubectl exec -it <pod-name> -n <namespace> -- curl http://localhost:<port>/health
```

### Network Debugging

```bash
# Test network connectivity
kubectl exec -it <pod-name> -n <namespace> -- ping <target-host>

# Check network policies
kubectl get networkpolicies -n <namespace>

# Test service connectivity
kubectl exec -it <pod-name> -n <namespace> -- telnet <service-name> <port>
```

## 🚨 Emergency Procedures

### Service Outage Response

1. **Immediate Assessment**
   ```bash
   # Check overall cluster health
   kubectl get nodes
   kubectl get pods --all-namespaces | grep -v Running
   
   # Check critical services
   kubectl get pods -n <namespace> | grep -E "(gateway|auth|payment)"
   ```

2. **Quick Recovery**
   ```bash
   # Restart failed services
   kubectl rollout restart deployment/<critical-service> -n <namespace>
   
   # Scale up if needed
   kubectl scale deployment/<service> --replicas=<count> -n <namespace>
   ```

3. **Rollback if Necessary**
   ```bash
   # Rollback to last known good version
   kubectl rollout undo deployment/<deployment-name> -n <namespace>
   ```

### Data Recovery

1. **Database Issues**
   ```bash
   # Check database backups
   kubectl get pvc -n <namespace>
   
   # Restore from backup if available
   kubectl exec -it <mysql-pod> -n <namespace> -- mysql -u root -p < backup.sql
   ```

2. **Configuration Recovery**
   ```bash
   # Restore ConfigMaps from Git
   git checkout HEAD -- deployment/k8s/
   kubectl apply -k deployment/k8s/overlays/<environment>
   ```

### Escalation Procedures

1. **Level 1**: Application team handles service-specific issues
2. **Level 2**: DevOps team handles infrastructure issues
3. **Level 3**: Platform team handles cluster-wide issues

### Communication

- Update status page
- Notify stakeholders
- Document incident in post-mortem

## 📚 Useful Commands

### Quick Diagnostics
```bash
# Overall cluster health
kubectl get nodes,pods,svc --all-namespaces

# Resource usage
kubectl top nodes && kubectl top pods --all-namespaces

# Recent events
kubectl get events --sort-by=.metadata.creationTimestamp -n <namespace>

# Pod restart counts
kubectl get pods -n <namespace> -o custom-columns=NAME:.metadata.name,RESTARTS:.status.containerStatuses[0].restartCount
```

### Emergency Commands
```bash
# Force delete stuck pod
kubectl delete pod <pod-name> --force --grace-period=0 -n <namespace>

# Drain node for maintenance
kubectl drain <node-name> --ignore-daemonsets --delete-emptydir-data

# Emergency scale down
kubectl scale deployment --all --replicas=0 -n <namespace>
```

---

**Last Updated**: February 2026  
**Version**: 1.0.0
