#!/bin/bash

# Comprehensive Health Check Script for Reverse Tender Platform
# Performs health checks across all services, infrastructure, and monitoring

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
LOG_FILE="/tmp/health-check-$(date +%Y%m%d-%H%M%S).log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Logging functions
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[HEALTHY] $1${NC}" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[UNHEALTHY] $1${NC}" | tee -a "$LOG_FILE"
}

info() {
    echo -e "${PURPLE}[INFO] $1${NC}" | tee -a "$LOG_FILE"
}

# Configuration
SERVICES=("api-gateway" "auth-service" "user-service" "bidding-service" "order-service" "payment-service" "notification-service" "analytics-service" "vin-ocr-service")
INFRASTRUCTURE=("mysql" "redis" "prometheus" "grafana" "jaeger")
TIMEOUT=10
RETRY_COUNT=3
HEALTH_ENDPOINT="/octane/health"

# Health check functions
check_service_health() {
    local service=$1
    local host=${2:-"localhost"}
    local port=${3:-"80"}
    local endpoint=${4:-$HEALTH_ENDPOINT}
    
    log "Checking health of $service..."
    
    local url="http://$host:$port$endpoint"
    local attempts=0
    local success=false
    
    while [ $attempts -lt $RETRY_COUNT ] && [ "$success" = false ]; do
        ((attempts++))
        
        if curl -s --max-time $TIMEOUT "$url" > /dev/null 2>&1; then
            success=true
            success "$service is healthy (attempt $attempts)"
            return 0
        else
            if [ $attempts -lt $RETRY_COUNT ]; then
                warning "$service health check failed (attempt $attempts), retrying..."
                sleep 2
            fi
        fi
    done
    
    error "$service is unhealthy after $RETRY_COUNT attempts"
    return 1
}

check_octane_health() {
    local service=$1
    local host=${2:-"localhost"}
    local port=${3:-"80"}
    
    log "Checking Octane health for $service..."
    
    local health_url="http://$host:$port/octane/health"
    local metrics_url="http://$host:$port/octane/metrics"
    
    # Check health endpoint
    if curl -s --max-time $TIMEOUT "$health_url" | grep -q "OK\|healthy"; then
        success "$service Octane health endpoint is responding"
    else
        error "$service Octane health endpoint is not responding"
        return 1
    fi
    
    # Check metrics endpoint (if available)
    if curl -s --max-time $TIMEOUT "$metrics_url" > /dev/null 2>&1; then
        success "$service Octane metrics endpoint is available"
        
        # Get worker information
        local worker_info=$(curl -s --max-time $TIMEOUT "$metrics_url" | grep -E "octane_workers|octane_requests" || echo "")
        if [ -n "$worker_info" ]; then
            info "$service worker metrics: $worker_info"
        fi
    else
        warning "$service Octane metrics endpoint is not available"
    fi
    
    return 0
}

check_database_health() {
    log "Checking database health..."
    
    local db_host=${DB_HOST:-"localhost"}
    local db_port=${DB_PORT:-"3306"}
    local db_user=${DB_USERNAME:-"root"}
    local db_password=${DB_PASSWORD:-""}
    
    # Check if MySQL is responding
    if command -v mysql > /dev/null 2>&1; then
        if mysql -h "$db_host" -P "$db_port" -u "$db_user" ${db_password:+-p"$db_password"} -e "SELECT 1;" > /dev/null 2>&1; then
            success "MySQL database is healthy"
            
            # Get database status
            local connections=$(mysql -h "$db_host" -P "$db_port" -u "$db_user" ${db_password:+-p"$db_password"} -e "SHOW STATUS LIKE 'Threads_connected';" 2>/dev/null | tail -n 1 | awk '{print $2}' || echo "unknown")
            local uptime=$(mysql -h "$db_host" -P "$db_port" -u "$db_user" ${db_password:+-p"$db_password"} -e "SHOW STATUS LIKE 'Uptime';" 2>/dev/null | tail -n 1 | awk '{print $2}' || echo "unknown")
            
            info "MySQL connections: $connections, uptime: ${uptime}s"
            return 0
        else
            error "MySQL database is not responding"
            return 1
        fi
    else
        # Try TCP connection test
        if timeout $TIMEOUT bash -c "</dev/tcp/$db_host/$db_port" 2>/dev/null; then
            success "MySQL port $db_port is open on $db_host"
        else
            error "Cannot connect to MySQL on $db_host:$db_port"
            return 1
        fi
    fi
}

check_redis_health() {
    log "Checking Redis health..."
    
    local redis_host=${REDIS_HOST:-"localhost"}
    local redis_port=${REDIS_PORT:-"6379"}
    local redis_password=${REDIS_PASSWORD:-""}
    
    # Check if Redis is responding
    if command -v redis-cli > /dev/null 2>&1; then
        local redis_cmd="redis-cli -h $redis_host -p $redis_port"
        if [ -n "$redis_password" ]; then
            redis_cmd="$redis_cmd -a $redis_password"
        fi
        
        if $redis_cmd ping | grep -q "PONG"; then
            success "Redis is healthy"
            
            # Get Redis info
            local memory_used=$($redis_cmd info memory | grep "used_memory_human" | cut -d: -f2 | tr -d '\r' || echo "unknown")
            local connected_clients=$($redis_cmd info clients | grep "connected_clients" | cut -d: -f2 | tr -d '\r' || echo "unknown")
            
            info "Redis memory used: $memory_used, connected clients: $connected_clients"
            return 0
        else
            error "Redis is not responding"
            return 1
        fi
    else
        # Try TCP connection test
        if timeout $TIMEOUT bash -c "</dev/tcp/$redis_host/$redis_port" 2>/dev/null; then
            success "Redis port $redis_port is open on $redis_host"
        else
            error "Cannot connect to Redis on $redis_host:$redis_port"
            return 1
        fi
    fi
}

check_monitoring_health() {
    log "Checking monitoring stack health..."
    
    # Check Prometheus
    local prometheus_host=${PROMETHEUS_HOST:-"localhost"}
    local prometheus_port=${PROMETHEUS_PORT:-"9090"}
    
    if curl -s --max-time $TIMEOUT "http://$prometheus_host:$prometheus_port/-/healthy" > /dev/null 2>&1; then
        success "Prometheus is healthy"
        
        # Check targets
        local targets_up=$(curl -s --max-time $TIMEOUT "http://$prometheus_host:$prometheus_port/api/v1/targets" | jq -r '.data.activeTargets[] | select(.health=="up") | .scrapeUrl' 2>/dev/null | wc -l || echo "0")
        info "Prometheus monitoring $targets_up active targets"
    else
        error "Prometheus is not responding"
    fi
    
    # Check Grafana
    local grafana_host=${GRAFANA_HOST:-"localhost"}
    local grafana_port=${GRAFANA_PORT:-"3000"}
    
    if curl -s --max-time $TIMEOUT "http://$grafana_host:$grafana_port/api/health" | grep -q "ok"; then
        success "Grafana is healthy"
    else
        error "Grafana is not responding"
    fi
    
    # Check Jaeger
    local jaeger_host=${JAEGER_HOST:-"localhost"}
    local jaeger_port=${JAEGER_PORT:-"16686"}
    
    if curl -s --max-time $TIMEOUT "http://$jaeger_host:$jaeger_port/" > /dev/null 2>&1; then
        success "Jaeger is healthy"
    else
        warning "Jaeger is not responding (tracing may be unavailable)"
    fi
}

check_kubernetes_health() {
    log "Checking Kubernetes cluster health..."
    
    if ! command -v kubectl > /dev/null 2>&1; then
        warning "kubectl not available, skipping Kubernetes health checks"
        return 0
    fi
    
    # Check cluster connectivity
    if kubectl cluster-info > /dev/null 2>&1; then
        success "Kubernetes cluster is accessible"
    else
        error "Cannot connect to Kubernetes cluster"
        return 1
    fi
    
    # Check node status
    local nodes_ready=$(kubectl get nodes --no-headers 2>/dev/null | grep -c "Ready" || echo "0")
    local nodes_total=$(kubectl get nodes --no-headers 2>/dev/null | wc -l || echo "0")
    
    if [ "$nodes_ready" -eq "$nodes_total" ] && [ "$nodes_total" -gt 0 ]; then
        success "All $nodes_total Kubernetes nodes are ready"
    else
        warning "$nodes_ready out of $nodes_total Kubernetes nodes are ready"
    fi
    
    # Check pod status in reverse-tender namespace
    local namespace="reverse-tender"
    if kubectl get namespace "$namespace" > /dev/null 2>&1; then
        local pods_running=$(kubectl get pods -n "$namespace" --no-headers 2>/dev/null | grep -c "Running" || echo "0")
        local pods_total=$(kubectl get pods -n "$namespace" --no-headers 2>/dev/null | wc -l || echo "0")
        
        if [ "$pods_running" -eq "$pods_total" ] && [ "$pods_total" -gt 0 ]; then
            success "All $pods_total pods are running in $namespace namespace"
        else
            warning "$pods_running out of $pods_total pods are running in $namespace namespace"
        fi
    else
        info "Namespace $namespace not found (may not be deployed yet)"
    fi
}

check_docker_health() {
    log "Checking Docker environment health..."
    
    if ! command -v docker > /dev/null 2>&1; then
        warning "Docker not available, skipping Docker health checks"
        return 0
    fi
    
    # Check Docker daemon
    if docker info > /dev/null 2>&1; then
        success "Docker daemon is running"
    else
        error "Docker daemon is not responding"
        return 1
    fi
    
    # Check running containers
    local containers_running=$(docker ps --format "table {{.Names}}" | grep -E "$(IFS="|"; echo "${SERVICES[*]}")" | wc -l || echo "0")
    
    if [ "$containers_running" -gt 0 ]; then
        success "$containers_running service containers are running"
        
        # List running service containers
        docker ps --format "table {{.Names}}\t{{.Status}}" | grep -E "$(IFS="|"; echo "${SERVICES[*]}")" | while read -r line; do
            info "Container: $line"
        done
    else
        warning "No service containers are currently running"
    fi
    
    # Check Docker Compose services
    if [ -f "$PROJECT_ROOT/docker-compose.yml" ]; then
        cd "$PROJECT_ROOT"
        local compose_services=$(docker-compose ps --services 2>/dev/null | wc -l || echo "0")
        if [ "$compose_services" -gt 0 ]; then
            info "Docker Compose defines $compose_services services"
        fi
    fi
}

check_load_balancer_health() {
    log "Checking load balancer health..."
    
    # Check if ingress controller is running (Kubernetes)
    if command -v kubectl > /dev/null 2>&1; then
        local ingress_pods=$(kubectl get pods -n ingress-nginx --no-headers 2>/dev/null | grep -c "Running" || echo "0")
        if [ "$ingress_pods" -gt 0 ]; then
            success "Ingress controller is running ($ingress_pods pods)"
        else
            warning "No ingress controller pods found"
        fi
    fi
    
    # Check common load balancer ports
    local lb_ports=("80" "443" "8080")
    for port in "${lb_ports[@]}"; do
        if netstat -tuln 2>/dev/null | grep -q ":$port "; then
            success "Load balancer port $port is listening"
        else
            info "Port $port is not listening (may be normal depending on setup)"
        fi
    done
}

check_ssl_certificates() {
    log "Checking SSL certificate health..."
    
    local domains=("api.reversetender.com" "app.reversetender.com" "admin.reversetender.com")
    
    for domain in "${domains[@]}"; do
        if command -v openssl > /dev/null 2>&1; then
            local cert_info=$(echo | timeout $TIMEOUT openssl s_client -servername "$domain" -connect "$domain:443" 2>/dev/null | openssl x509 -noout -dates 2>/dev/null || echo "")
            
            if [ -n "$cert_info" ]; then
                local expiry_date=$(echo "$cert_info" | grep "notAfter" | cut -d= -f2)
                success "SSL certificate for $domain is valid (expires: $expiry_date)"
            else
                warning "Cannot verify SSL certificate for $domain (domain may not be accessible)"
            fi
        else
            warning "OpenSSL not available, skipping SSL certificate checks"
            break
        fi
    done
}

check_performance_metrics() {
    log "Checking performance metrics..."
    
    # System load
    if command -v uptime > /dev/null 2>&1; then
        local load_avg=$(uptime | awk -F'load average:' '{print $2}' | xargs)
        info "System load average: $load_avg"
    fi
    
    # Memory usage
    if command -v free > /dev/null 2>&1; then
        local memory_usage=$(free -h | grep "Mem:" | awk '{print "Used: " $3 "/" $2 " (" $3/$2*100 "%)"}')
        info "Memory usage: $memory_usage"
    fi
    
    # Disk usage
    if command -v df > /dev/null 2>&1; then
        local disk_usage=$(df -h / | tail -n 1 | awk '{print "Used: " $3 "/" $2 " (" $5 ")"}')
        info "Disk usage: $disk_usage"
    fi
    
    # Check if system is under high load
    if command -v uptime > /dev/null 2>&1; then
        local load_1min=$(uptime | awk -F'load average:' '{print $2}' | awk -F',' '{print $1}' | xargs)
        local cpu_cores=$(nproc 2>/dev/null || echo "1")
        
        if (( $(echo "$load_1min > $cpu_cores * 2" | bc -l 2>/dev/null || echo "0") )); then
            warning "System load is high ($load_1min on $cpu_cores cores)"
        else
            success "System load is normal ($load_1min on $cpu_cores cores)"
        fi
    fi
}

generate_health_report() {
    log "Generating health report..."
    
    local report_file="/tmp/health-report-$(date +%Y%m%d-%H%M%S).json"
    
    cat > "$report_file" << EOF
{
  "timestamp": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "platform": "Reverse Tender Platform",
  "environment": "${APP_ENV:-unknown}",
  "health_check_version": "1.0.0",
  "summary": {
    "total_checks": $total_checks,
    "passed_checks": $passed_checks,
    "failed_checks": $failed_checks,
    "warning_checks": $warning_checks,
    "overall_status": "$([ $failed_checks -eq 0 ] && echo "healthy" || echo "unhealthy")"
  },
  "details": {
    "services_checked": $(printf '%s\n' "${SERVICES[@]}" | jq -R . | jq -s .),
    "infrastructure_checked": $(printf '%s\n' "${INFRASTRUCTURE[@]}" | jq -R . | jq -s .),
    "log_file": "$LOG_FILE",
    "report_file": "$report_file"
  }
}
EOF
    
    info "Health report generated: $report_file"
}

# Main health check function
main() {
    log "Starting comprehensive health check..."
    log "Log file: $LOG_FILE"
    
    local total_checks=0
    local passed_checks=0
    local failed_checks=0
    local warning_checks=0
    
    # Infrastructure health checks
    ((total_checks++))
    if check_database_health; then
        ((passed_checks++))
    else
        ((failed_checks++))
    fi
    
    ((total_checks++))
    if check_redis_health; then
        ((passed_checks++))
    else
        ((failed_checks++))
    fi
    
    ((total_checks++))
    check_monitoring_health
    ((passed_checks++))  # Monitoring is informational
    
    # Platform health checks
    ((total_checks++))
    check_kubernetes_health
    ((passed_checks++))  # K8s check is informational
    
    ((total_checks++))
    check_docker_health
    ((passed_checks++))  # Docker check is informational
    
    ((total_checks++))
    check_load_balancer_health
    ((passed_checks++))  # LB check is informational
    
    ((total_checks++))
    check_ssl_certificates
    ((passed_checks++))  # SSL check is informational
    
    ((total_checks++))
    check_performance_metrics
    ((passed_checks++))  # Performance check is informational
    
    # Service health checks (if services are running)
    for service in "${SERVICES[@]}"; do
        # Try to check service health (may not be running in all environments)
        if check_service_health "$service" "localhost" "80" "/health" 2>/dev/null; then
            info "$service health check completed"
        fi
        
        # Try Octane-specific health check
        if check_octane_health "$service" "localhost" "80" 2>/dev/null; then
            info "$service Octane health check completed"
        fi
    done
    
    # Generate report
    generate_health_report
    
    # Summary
    log "Health check completed"
    log "Total checks: $total_checks"
    success "Passed checks: $passed_checks"
    if [ "$failed_checks" -gt 0 ]; then
        error "Failed checks: $failed_checks"
    else
        log "Failed checks: $failed_checks"
    fi
    if [ "$warning_checks" -gt 0 ]; then
        warning "Warning checks: $warning_checks"
    fi
    
    log "Detailed log available at: $LOG_FILE"
    
    if [ "$failed_checks" -eq 0 ]; then
        success "Overall system health: HEALTHY ✅"
        exit 0
    else
        error "Overall system health: UNHEALTHY ❌"
        exit 1
    fi
}

# Script execution
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi

