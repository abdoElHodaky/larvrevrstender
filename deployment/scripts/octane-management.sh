#!/bin/bash

# Octane Management Script for Laravel 12 + Octane
# Handles Octane-specific operations for Reverse Tender Platform

set -euo pipefail

# Source common functions
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/lib/common.sh"

# Configuration
OCTANE_SERVICES=(
    "api-gateway:8000"
    "auth-service:8001"
    "bidding-service:8002"
    "user-service:8003"
    "order-service:8004"
    "notification-service:8005"
    "payment-service:8006"
    "analytics-service:8007"
    "vin-ocr-service:8008"
)

# Function to check Octane worker status
check_octane_status() {
    local service_name="$1"
    local port="$2"
    
    log_info "Checking Octane status for ${service_name}..."
    
    if curl -sf "http://localhost:${port}/octane/health" > /dev/null 2>&1; then
        log_success "✅ ${service_name} Octane workers are healthy"
        return 0
    else
        log_error "❌ ${service_name} Octane workers are not responding"
        return 1
    fi
}

# Function to get Octane worker metrics
get_octane_metrics() {
    local service_name="$1"
    local port="$2"
    
    log_info "Getting Octane metrics for ${service_name}..."
    
    if curl -sf "http://localhost:${port}/octane/metrics" 2>/dev/null; then
        log_success "✅ Retrieved metrics for ${service_name}"
    else
        log_warning "⚠️  Could not retrieve metrics for ${service_name}"
    fi
}

# Function to restart Octane workers
restart_octane_workers() {
    local service_name="$1"
    local environment="${2:-docker}"
    
    log_info "Restarting Octane workers for ${service_name}..."
    
    case "$environment" in
        "docker")
            if docker-compose exec "${service_name}" php artisan octane:restart; then
                log_success "✅ Restarted Octane workers for ${service_name}"
            else
                log_error "❌ Failed to restart Octane workers for ${service_name}"
                return 1
            fi
            ;;
        "kubernetes")
            local pod_name
            pod_name=$(kubectl get pods -l app="${service_name}" -o jsonpath='{.items[0].metadata.name}')
            if kubectl exec "${pod_name}" -- php artisan octane:restart; then
                log_success "✅ Restarted Octane workers for ${service_name}"
            else
                log_error "❌ Failed to restart Octane workers for ${service_name}"
                return 1
            fi
            ;;
        *)
            log_error "❌ Unknown environment: ${environment}"
            return 1
            ;;
    esac
}

# Function to warm up Octane cache
warm_octane_cache() {
    local service_name="$1"
    local environment="${2:-docker}"
    
    log_info "Warming up cache for ${service_name}..."
    
    case "$environment" in
        "docker")
            docker-compose exec "${service_name}" php artisan config:cache
            docker-compose exec "${service_name}" php artisan route:cache
            docker-compose exec "${service_name}" php artisan view:cache
            docker-compose exec "${service_name}" php artisan event:cache
            ;;
        "kubernetes")
            local pod_name
            pod_name=$(kubectl get pods -l app="${service_name}" -o jsonpath='{.items[0].metadata.name}')
            kubectl exec "${pod_name}" -- php artisan config:cache
            kubectl exec "${pod_name}" -- php artisan route:cache
            kubectl exec "${pod_name}" -- php artisan view:cache
            kubectl exec "${pod_name}" -- php artisan event:cache
            ;;
    esac
    
    log_success "✅ Cache warmed up for ${service_name}"
}

# Function to scale Octane workers
scale_octane_workers() {
    local service_name="$1"
    local worker_count="$2"
    local environment="${3:-docker}"
    
    log_info "Scaling Octane workers for ${service_name} to ${worker_count}..."
    
    case "$environment" in
        "docker")
            docker-compose exec "${service_name}" php artisan octane:reload --workers="${worker_count}"
            ;;
        "kubernetes")
            # Update the deployment with new worker count
            kubectl patch deployment "${service_name}" -p "{\"spec\":{\"template\":{\"spec\":{\"containers\":[{\"name\":\"${service_name}\",\"env\":[{\"name\":\"OCTANE_WORKERS\",\"value\":\"${worker_count}\"}]}]}}}}"
            kubectl rollout restart deployment/"${service_name}"
            kubectl rollout status deployment/"${service_name}"
            ;;
    esac
    
    log_success "✅ Scaled Octane workers for ${service_name} to ${worker_count}"
}

# Function to perform graceful shutdown
graceful_shutdown() {
    local service_name="$1"
    local environment="${2:-docker}"
    
    log_info "Performing graceful shutdown for ${service_name}..."
    
    case "$environment" in
        "docker")
            # Send SIGTERM to allow graceful shutdown
            docker-compose exec "${service_name}" php artisan octane:stop
            sleep 5
            docker-compose stop "${service_name}"
            ;;
        "kubernetes")
            # Scale down to 0 replicas gracefully
            kubectl scale deployment "${service_name}" --replicas=0
            kubectl wait --for=delete pod -l app="${service_name}" --timeout=60s
            ;;
    esac
    
    log_success "✅ Graceful shutdown completed for ${service_name}"
}

# Function to monitor Octane performance
monitor_octane_performance() {
    local duration="${1:-60}"
    
    log_info "Monitoring Octane performance for ${duration} seconds..."
    
    local end_time=$((SECONDS + duration))
    
    while [ $SECONDS -lt $end_time ]; do
        echo "=== Octane Status Check $(date) ==="
        
        for service_port in "${OCTANE_SERVICES[@]}"; do
            IFS=':' read -r service port <<< "$service_port"
            check_octane_status "$service" "$port"
        done
        
        echo ""
        sleep 10
    done
    
    log_success "✅ Performance monitoring completed"
}

# Function to run Octane health checks
health_check_all() {
    local failed_services=()
    
    log_info "Running health checks for all Octane services..."
    
    for service_port in "${OCTANE_SERVICES[@]}"; do
        IFS=':' read -r service port <<< "$service_port"
        if ! check_octane_status "$service" "$port"; then
            failed_services+=("$service")
        fi
    done
    
    if [ ${#failed_services[@]} -eq 0 ]; then
        log_success "✅ All Octane services are healthy"
        return 0
    else
        log_error "❌ Failed services: ${failed_services[*]}"
        return 1
    fi
}

# Function to restart all Octane services
restart_all() {
    local environment="${1:-docker}"
    
    log_info "Restarting all Octane services..."
    
    for service_port in "${OCTANE_SERVICES[@]}"; do
        IFS=':' read -r service port <<< "$service_port"
        restart_octane_workers "$service" "$environment"
        sleep 2
    done
    
    log_success "✅ All Octane services restarted"
}

# Function to warm cache for all services
warm_all_cache() {
    local environment="${1:-docker}"
    
    log_info "Warming cache for all services..."
    
    for service_port in "${OCTANE_SERVICES[@]}"; do
        IFS=':' read -r service port <<< "$service_port"
        warm_octane_cache "$service" "$environment"
    done
    
    log_success "✅ Cache warmed for all services"
}

# Main function
main() {
    local command="${1:-help}"
    local service="${2:-}"
    local environment="${3:-docker}"
    
    case "$command" in
        "status")
            if [ -n "$service" ]; then
                local port
                port=$(echo "${OCTANE_SERVICES[@]}" | grep -o "${service}:[0-9]*" | cut -d: -f2)
                check_octane_status "$service" "$port"
            else
                health_check_all
            fi
            ;;
        "restart")
            if [ -n "$service" ]; then
                restart_octane_workers "$service" "$environment"
            else
                restart_all "$environment"
            fi
            ;;
        "warm-cache")
            if [ -n "$service" ]; then
                warm_octane_cache "$service" "$environment"
            else
                warm_all_cache "$environment"
            fi
            ;;
        "scale")
            local worker_count="${4:-4}"
            if [ -n "$service" ]; then
                scale_octane_workers "$service" "$worker_count" "$environment"
            else
                log_error "❌ Service name required for scaling"
                exit 1
            fi
            ;;
        "shutdown")
            if [ -n "$service" ]; then
                graceful_shutdown "$service" "$environment"
            else
                log_error "❌ Service name required for shutdown"
                exit 1
            fi
            ;;
        "monitor")
            local duration="${4:-60}"
            monitor_octane_performance "$duration"
            ;;
        "metrics")
            if [ -n "$service" ]; then
                local port
                port=$(echo "${OCTANE_SERVICES[@]}" | grep -o "${service}:[0-9]*" | cut -d: -f2)
                get_octane_metrics "$service" "$port"
            else
                for service_port in "${OCTANE_SERVICES[@]}"; do
                    IFS=':' read -r svc port <<< "$service_port"
                    get_octane_metrics "$svc" "$port"
                done
            fi
            ;;
        "help"|*)
            cat << EOF
Octane Management Script for Laravel 12 + Octane

Usage: $0 <command> [service] [environment] [options]

Commands:
  status [service]              - Check Octane worker status
  restart [service]             - Restart Octane workers
  warm-cache [service]          - Warm up application cache
  scale <service> <workers>     - Scale Octane workers
  shutdown <service>            - Graceful shutdown
  monitor [duration]            - Monitor performance (default: 60s)
  metrics [service]             - Get Octane metrics
  help                          - Show this help

Services:
  api-gateway, auth-service, bidding-service, user-service,
  order-service, notification-service, payment-service,
  analytics-service, vin-ocr-service

Environments:
  docker (default), kubernetes

Examples:
  $0 status                     # Check all services
  $0 status api-gateway         # Check specific service
  $0 restart auth-service docker # Restart auth service in docker
  $0 scale bidding-service 6    # Scale bidding service to 6 workers
  $0 monitor 120                # Monitor for 2 minutes
  $0 warm-cache                 # Warm cache for all services

EOF
            ;;
    esac
}

# Run main function with all arguments
main "$@"

