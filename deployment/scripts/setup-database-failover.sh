#!/bin/bash
# Database Failover Setup Script for Reverse Tender Platform
# Updated for comprehensive database failover architecture v3.0
# Supports: Neon PostgreSQL, Cloud-Native PostgreSQL, MongoDB Atlas

set -euo pipefail

# Script configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
LOG_FILE="/tmp/database-failover-setup-$(date +%Y%m%d-%H%M%S).log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

# Help function
show_help() {
    cat << EOF
Database Failover Setup Script

Usage: $0 [OPTIONS]

OPTIONS:
    --provider=PROVIDER     Setup specific provider (neon|cnpg|mongodb|all)
    --environment=ENV       Target environment (development|staging|production)
    --namespace=NS          Kubernetes namespace (default: default)
    --dry-run              Show what would be done without making changes
    --skip-validation      Skip prerequisite validation
    --help                 Show this help message

EXAMPLES:
    $0 --provider=all --environment=production
    $0 --provider=neon --environment=development --dry-run
    $0 --provider=cnpg --namespace=database-system

ENVIRONMENT VARIABLES:
    KUBECONFIG             Path to kubeconfig file
    DB_FAILOVER_NAMESPACE  Default namespace for database failover resources
    DB_FAILOVER_DRY_RUN    Enable dry-run mode (true/false)
EOF
}

# Default values
PROVIDER="all"
ENVIRONMENT="development"
NAMESPACE="default"
DRY_RUN=false
SKIP_VALIDATION=false

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --provider=*)
            PROVIDER="${1#*=}"
            shift
            ;;
        --environment=*)
            ENVIRONMENT="${1#*=}"
            shift
            ;;
        --namespace=*)
            NAMESPACE="${1#*=}"
            shift
            ;;
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --skip-validation)
            SKIP_VALIDATION=true
            shift
            ;;
        --help)
            show_help
            exit 0
            ;;
        *)
            error "Unknown option: $1"
            show_help
            exit 1
            ;;
    esac
done

# Override with environment variables
NAMESPACE="${DB_FAILOVER_NAMESPACE:-$NAMESPACE}"
DRY_RUN="${DB_FAILOVER_DRY_RUN:-$DRY_RUN}"

log "🚀 Starting Database Failover Setup"
log "Provider: $PROVIDER"
log "Environment: $ENVIRONMENT"
log "Namespace: $NAMESPACE"
log "Dry Run: $DRY_RUN"
log "Log File: $LOG_FILE"

# Validation function
validate_prerequisites() {
    if [[ "$SKIP_VALIDATION" == "true" ]]; then
        warning "Skipping prerequisite validation"
        return 0
    fi

    log "🔍 Validating prerequisites..."

    # Check kubectl
    if ! command -v kubectl &> /dev/null; then
        error "kubectl is not installed or not in PATH"
        return 1
    fi

    # Check Kubernetes connection
    if ! kubectl cluster-info &> /dev/null; then
        error "Cannot connect to Kubernetes cluster"
        return 1
    fi

    # Check namespace
    if ! kubectl get namespace "$NAMESPACE" &> /dev/null; then
        warning "Namespace '$NAMESPACE' does not exist, will create it"
    fi

    # Check for required tools based on provider
    case $PROVIDER in
        neon|all)
            if ! command -v psql &> /dev/null; then
                warning "psql is not installed - Neon PostgreSQL validation will be limited"
            fi
            ;;
        mongodb|all)
            if ! command -v mongosh &> /dev/null; then
                warning "mongosh is not installed - MongoDB Atlas validation will be limited"
            fi
            ;;
    esac

    success "Prerequisites validation completed"
}

# Create namespace if it doesn't exist
create_namespace() {
    log "📁 Creating namespace if needed..."
    
    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY RUN] Would create namespace: $NAMESPACE"
        return 0
    fi

    if ! kubectl get namespace "$NAMESPACE" &> /dev/null; then
        kubectl create namespace "$NAMESPACE"
        success "Created namespace: $NAMESPACE"
    else
        log "Namespace '$NAMESPACE' already exists"
    fi
}

# Setup Neon PostgreSQL
setup_neon() {
    log "🐘 Setting up Neon PostgreSQL failover..."

    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY RUN] Would setup Neon PostgreSQL configuration"
        return 0
    fi

    # Apply Neon-specific configurations
    kubectl apply -f "$PROJECT_ROOT/deployment/k8s/base/database-failover-config.yaml" -n "$NAMESPACE"
    kubectl apply -f "$PROJECT_ROOT/deployment/k8s/base/database-failover-secrets.yaml" -n "$NAMESPACE"

    success "Neon PostgreSQL configuration applied"
}

# Setup Cloud-Native PostgreSQL (CNPG)
setup_cnpg() {
    log "🏗️ Setting up Cloud-Native PostgreSQL (CNPG)..."

    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY RUN] Would setup CNPG operator and cluster"
        return 0
    fi

    # Install CNPG operator
    log "Installing CNPG operator..."
    kubectl apply -f "$PROJECT_ROOT/deployment/k8s/base/cnpg-operator.yaml"

    # Wait for operator to be ready
    log "Waiting for CNPG operator to be ready..."
    kubectl wait --for=condition=available --timeout=300s deployment/cnpg-operator -n cnpg-system

    # Create CNPG cluster
    log "Creating CNPG cluster..."
    kubectl apply -f "$PROJECT_ROOT/deployment/k8s/base/cnpg-cluster.yaml" -n "$NAMESPACE"

    # Wait for cluster to be ready
    log "Waiting for CNPG cluster to be ready..."
    kubectl wait --for=condition=ready --timeout=600s cluster/reverse-tender-postgres -n "$NAMESPACE"

    success "CNPG setup completed"
}

# Setup MongoDB Atlas
setup_mongodb() {
    log "🍃 Setting up MongoDB Atlas failover..."

    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY RUN] Would setup MongoDB Atlas configuration"
        return 0
    fi

    # Apply MongoDB-specific configurations
    kubectl apply -f "$PROJECT_ROOT/deployment/k8s/base/database-failover-config.yaml" -n "$NAMESPACE"
    kubectl apply -f "$PROJECT_ROOT/deployment/k8s/base/database-failover-secrets.yaml" -n "$NAMESPACE"

    success "MongoDB Atlas configuration applied"
}

# Setup Gateway API with database failover
setup_gateway_api() {
    log "🌐 Setting up Gateway API with database failover..."

    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY RUN] Would setup Gateway API with database failover"
        return 0
    fi

    # Apply Gateway API configurations
    kubectl apply -f "$PROJECT_ROOT/deployment/k8s/base/gateway-api/database-failover-gateway.yaml" -n "$NAMESPACE"
    kubectl apply -f "$PROJECT_ROOT/deployment/k8s/base/gateway-api/database-failover-policies.yaml" -n "$NAMESPACE"

    success "Gateway API with database failover configured"
}

# Setup deployments with database failover
setup_deployments() {
    log "🚀 Setting up deployments with database failover..."

    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY RUN] Would setup deployments with database failover"
        return 0
    fi

    # Apply deployment configurations
    kubectl apply -f "$PROJECT_ROOT/deployment/k8s/base/deployments-with-database-failover.yaml" -n "$NAMESPACE"

    # Wait for deployments to be ready
    log "Waiting for deployments to be ready..."
    kubectl wait --for=condition=available --timeout=300s deployment/api-gateway -n "$NAMESPACE"
    kubectl wait --for=condition=available --timeout=300s deployment/auth-service -n "$NAMESPACE"

    success "Deployments with database failover configured"
}

# Test database connections
test_connections() {
    log "🧪 Testing database connections..."

    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY RUN] Would test database connections"
        return 0
    fi

    # Test database health endpoints
    log "Testing database health endpoints..."
    
    # Wait for health monitor to be ready
    kubectl wait --for=condition=available --timeout=300s deployment/database-health-monitor -n "$NAMESPACE" || true

    # Test health endpoints (if available)
    if kubectl get service database-health-service -n "$NAMESPACE" &> /dev/null; then
        log "Database health service is available"
        # Port forward and test (in background)
        kubectl port-forward service/database-health-service 8080:8080 -n "$NAMESPACE" &
        PF_PID=$!
        sleep 5
        
        if curl -s http://localhost:8080/health/database &> /dev/null; then
            success "Database health endpoint is responding"
        else
            warning "Database health endpoint is not responding yet"
        fi
        
        kill $PF_PID 2>/dev/null || true
    fi

    success "Connection testing completed"
}

# Create monitoring and alerting
setup_monitoring() {
    log "📊 Setting up database failover monitoring..."

    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY RUN] Would setup monitoring and alerting"
        return 0
    fi

    # Create monitoring resources
    kubectl apply -f - <<EOF
apiVersion: v1
kind: ConfigMap
metadata:
  name: database-failover-monitoring
  namespace: $NAMESPACE
  labels:
    app.kubernetes.io/name: database-failover-monitoring
    app.kubernetes.io/component: monitoring
data:
  prometheus-rules.yaml: |
    groups:
    - name: database-failover
      rules:
      - alert: DatabaseFailoverTriggered
        expr: database_failover_active != database_failover_primary
        for: 1m
        labels:
          severity: warning
        annotations:
          summary: "Database failover has been triggered"
          description: "Database failover from {{ \$labels.from }} to {{ \$labels.to }}"
      
      - alert: AllDatabasesDown
        expr: database_failover_available_count == 0
        for: 30s
        labels:
          severity: critical
        annotations:
          summary: "All databases are unavailable"
          description: "All configured databases are currently unavailable"
EOF

    success "Monitoring and alerting configured"
}

# Generate summary report
generate_report() {
    log "📋 Generating setup report..."

    cat << EOF

🎉 Database Failover Setup Complete!

Configuration Summary:
- Provider: $PROVIDER
- Environment: $ENVIRONMENT
- Namespace: $NAMESPACE
- Dry Run: $DRY_RUN

Resources Created:
- Database failover ConfigMaps and Secrets
- CNPG operator and cluster (if selected)
- Gateway API with failover routing
- Deployments with database failover support
- Health monitoring and alerting

Next Steps:
1. Update database connection secrets with actual credentials
2. Configure monitoring and alerting endpoints
3. Test failover scenarios in non-production environment
4. Review and adjust resource limits based on workload

Useful Commands:
- Check database health: kubectl get pods -l database.kubernetes.io/failover-enabled=true -n $NAMESPACE
- View failover logs: kubectl logs -l app=database-health-monitor -n $NAMESPACE
- Test failover: kubectl port-forward service/database-health-service 8080:8080 -n $NAMESPACE

Documentation:
- Database Failover Guide: docs/DATABASE_FAILOVER_GUIDE.md
- Troubleshooting: docs/DATABASE_FAILOVER_TROUBLESHOOTING.md

Log File: $LOG_FILE

EOF
}

# Main execution
main() {
    # Validate prerequisites
    validate_prerequisites

    # Create namespace
    create_namespace

    # Setup based on provider
    case $PROVIDER in
        neon)
            setup_neon
            ;;
        cnpg)
            setup_cnpg
            ;;
        mongodb)
            setup_mongodb
            ;;
        all)
            setup_neon
            setup_cnpg
            setup_mongodb
            ;;
        *)
            error "Unknown provider: $PROVIDER"
            exit 1
            ;;
    esac

    # Setup Gateway API and deployments
    setup_gateway_api
    setup_deployments

    # Test connections
    test_connections

    # Setup monitoring
    setup_monitoring

    # Generate report
    generate_report

    success "Database failover setup completed successfully!"
}

# Error handling
trap 'error "Script failed at line $LINENO"' ERR

# Run main function
main "$@"
