# Deployment Directory Migration Guide

## 🎯 **Migration Overview**

This guide provides step-by-step instructions for migrating from the current fragmented deployment structure to the new unified deployment system.

## 📊 **Before & After Comparison**

### **Current Structure (Before)**
```
deployment/
├── docker/
│   ├── docker-compose.production.yml (242 lines, 4 services)
│   └── production/docker-compose.yml (391 lines, 8 services)
├── scripts/
│   ├── deploy.sh
│   ├── deploy-digitalocean.sh (95% duplicate)
│   └── deploy-linode.sh (95% duplicate)
├── terraform/
│   ├── main.tf (multi-provider)
│   └── digitalocean/main.tf (provider-specific)
└── kubernetes/ (6 manifest files)
```

### **New Structure (After)**
```
deployment-new/
├── deploy.sh (single entry point)
├── config/ (unified configuration)
├── docker/ (consolidated with overlays)
├── terraform/modules/ (proper module structure)
├── k8s/ (Kustomize-based)
└── scripts/lib/ (shared libraries)
```

## 🔍 **Benefits of Migration**

### **Quantitative Improvements**
- ✅ **50% Reduction** in configuration files (19 → 10)
- ✅ **90% Reduction** in deployment script duplication
- ✅ **Single Entry Point** for all deployments
- ✅ **Unified Configuration** management

### **Qualitative Improvements**
- ✅ **Simplified Mental Model** for deployments
- ✅ **Reduced Cognitive Load** for maintenance
- ✅ **Improved Developer Experience**
- ✅ **Enhanced System Reliability**

## 📋 **Migration Steps**

### **Phase 1: Preparation**

#### **Step 1: Backup Current Deployment**
```bash
# Create backup of current deployment structure
cp -r deployment deployment-backup-$(date +%Y%m%d_%H%M%S)

# Verify backup
ls -la deployment-backup-*
```

#### **Step 2: Document Current Configuration**
```bash
# Export current environment variables
env | grep -E "(DB_|REDIS_|JWT_|TWILIO_|SENDGRID_)" > current-env-vars.txt

# Document current service ports
docker-compose -f deployment/docker/production/docker-compose.yml config --services > current-services.txt

# Save current Terraform state (if applicable)
cd deployment/terraform
terraform show > ../current-terraform-state.txt
cd ../..
```

#### **Step 3: Validate Current Deployment**
```bash
# Test current deployment works
cd deployment
./scripts/deploy.sh --dry-run  # or appropriate current script
cd ..
```

### **Phase 2: Configuration Migration**

#### **Step 4: Migrate Environment Variables**
```bash
# Review new configuration structure
cat deployment-new/config/base.env
cat deployment-new/config/environments/production.env

# Update with your current values
cp current-env-vars.txt deployment-new/config/environments/production.env.backup
# Edit deployment-new/config/environments/production.env with your values
```

#### **Step 5: Migrate Provider Configuration**
```bash
# For DigitalOcean users
cp deployment-new/config/providers/digitalocean.env deployment-new/config/providers/digitalocean.env.backup
# Edit with your specific DO configuration

# For Linode users
cp deployment-new/config/providers/linode.env deployment-new/config/providers/linode.env.backup
# Edit with your specific Linode configuration
```

#### **Step 6: Migrate Custom Configurations**
```bash
# Copy any custom nginx configurations
cp -r deployment/nginx deployment-new/docker/nginx 2>/dev/null || true

# Copy any custom monitoring configurations
cp -r deployment/monitoring deployment-new/docker/monitoring 2>/dev/null || true

# Copy any custom SSL certificates
cp -r deployment/ssl deployment-new/docker/ssl 2>/dev/null || true
```

### **Phase 3: Testing**

#### **Step 7: Test Development Environment**
```bash
cd deployment-new

# Test configuration validation
./scripts/validate.sh

# Test development deployment (dry run)
./deploy.sh -e development -t docker --dry-run

# Test actual development deployment
./deploy.sh -e development -t docker

# Verify services are running
curl http://localhost:8000/health
curl http://localhost:8001/health
```

#### **Step 8: Test Staging Environment**
```bash
# Set required environment variables
export DIGITALOCEAN_TOKEN="your_token"  # or LINODE_TOKEN

# Test staging deployment (dry run)
./deploy.sh -e staging -p digitalocean --dry-run

# If dry run looks good, deploy to staging
./deploy.sh -e staging -p digitalocean

# Verify staging deployment
curl https://staging.reversetender.com/health
```

### **Phase 4: Production Migration**

#### **Step 9: Production Deployment Planning**
```bash
# Create production deployment plan
./deploy.sh -e production -p digitalocean --dry-run > production-deployment-plan.txt

# Review the plan carefully
cat production-deployment-plan.txt

# Schedule maintenance window
# Notify stakeholders
# Prepare rollback plan
```

#### **Step 10: Production Migration**
```bash
# Set all required production environment variables
export DIGITALOCEAN_TOKEN="your_production_token"
export PRODUCTION_DB_PASSWORD="secure_password"
export JWT_SECRET="your_jwt_secret"
# ... other production secrets

# Execute production deployment
./deploy.sh -e production -p digitalocean

# Monitor deployment progress
watch kubectl get pods -n reverse-tender

# Verify production deployment
curl https://reversetender.com/health
```

### **Phase 5: Validation & Cleanup**

#### **Step 11: Post-Migration Validation**
```bash
# Run comprehensive health checks
./scripts/validate.sh

# Check all service endpoints
for port in 8000 8001 8002 8003 8004 8005 8006 8007 8008; do
  curl -f http://localhost:$port/health || echo "Service on port $port failed"
done

# Check monitoring
curl http://localhost:9090/api/v1/query?query=up
curl http://localhost:3000/api/health

# Run integration tests
cd tests/integration
./run-tests.sh
```

#### **Step 12: Performance Comparison**
```bash
# Compare deployment times
time ./deploy.sh -e staging -p digitalocean

# Compare resource usage
docker stats --no-stream
kubectl top pods -n reverse-tender

# Compare configuration complexity
wc -l deployment-backup-*/docker/*.yml
wc -l deployment-new/docker/*.yml
```

#### **Step 13: Cleanup Old Structure**
```bash
# Only after successful migration and validation
# Move old deployment to archive
mv deployment deployment-old-$(date +%Y%m%d_%H%M%S)

# Rename new deployment to primary
mv deployment-new deployment

# Update any CI/CD pipelines to use new structure
# Update documentation references
# Notify team of migration completion
```

## 🔄 **Rollback Procedure**

### **If Migration Fails**
```bash
# Stop new deployment
cd deployment-new
./deploy.sh -e production -p digitalocean -t application --force-stop

# Restore old deployment
mv deployment deployment-new-failed
mv deployment-backup-YYYYMMDD_HHMMSS deployment

# Redeploy using old system
cd deployment
./scripts/deploy-digitalocean.sh  # or appropriate script

# Verify rollback
curl https://reversetender.com/health
```

### **Emergency Rollback**
```bash
# Quick rollback for production issues
kubectl rollout undo deployment/api-gateway -n reverse-tender
kubectl rollout undo deployment/auth-service -n reverse-tender
# ... for each service

# Or restore from backup
kubectl apply -f deployment-backup/kubernetes/
```

## 📝 **Migration Checklist**

### **Pre-Migration**
- [ ] Backup current deployment structure
- [ ] Document current configuration
- [ ] Test current deployment works
- [ ] Schedule maintenance window
- [ ] Notify stakeholders
- [ ] Prepare rollback plan

### **Configuration Migration**
- [ ] Migrate environment variables
- [ ] Migrate provider configuration
- [ ] Migrate custom configurations
- [ ] Validate new configuration structure

### **Testing**
- [ ] Test development environment
- [ ] Test staging environment
- [ ] Validate all services work
- [ ] Run integration tests
- [ ] Performance testing

### **Production Migration**
- [ ] Create deployment plan
- [ ] Execute production deployment
- [ ] Monitor deployment progress
- [ ] Verify all services
- [ ] Run health checks

### **Post-Migration**
- [ ] Comprehensive validation
- [ ] Performance comparison
- [ ] Update documentation
- [ ] Update CI/CD pipelines
- [ ] Team notification
- [ ] Cleanup old structure

## ⚠️ **Important Considerations**

### **Database Migration**
- The new system uses the same database structure
- No data migration is required
- Database connection strings remain the same
- Backup databases before migration

### **SSL Certificates**
- Copy existing SSL certificates to new structure
- Update certificate paths in configuration
- Test HTTPS endpoints after migration

### **Monitoring Data**
- Prometheus data will be preserved in volumes
- Grafana dashboards may need to be reconfigured
- Historical data should be maintained

### **External Integrations**
- API endpoints remain the same
- Webhook URLs don't change
- Third-party service configurations are preserved

## 🆘 **Troubleshooting**

### **Common Migration Issues**

#### **Configuration Errors**
```bash
# Validate configuration
./scripts/validate.sh

# Check environment variables
env | grep -E "(DB_|REDIS_|JWT_)"

# Test configuration loading
./deploy.sh --dry-run --verbose
```

#### **Service Startup Issues**
```bash
# Check container logs
docker-compose logs [service_name]

# Check Kubernetes pod logs
kubectl logs [pod_name] -n reverse-tender

# Check resource constraints
kubectl describe pod [pod_name] -n reverse-tender
```

#### **Network Connectivity Issues**
```bash
# Test service connectivity
curl http://localhost:8000/health

# Check Docker networks
docker network ls

# Check Kubernetes services
kubectl get services -n reverse-tender
```

### **Getting Help**
1. Check migration logs for specific errors
2. Compare old vs new configuration files
3. Verify all environment variables are set correctly
4. Test individual components in isolation
5. Use verbose mode for detailed output

## 📞 **Support**

If you encounter issues during migration:
1. Check this guide for common solutions
2. Review the deployment logs
3. Test with `--dry-run` first
4. Use the rollback procedure if needed
5. Document any issues for future reference

---

**🎯 Migration Success Criteria:**
- All services start successfully
- Health checks pass
- Performance is maintained or improved
- Configuration is simplified
- Team can use new deployment system effectively

