#!/bin/bash
# Pipeline Migration Monitoring Script
# Tracks migration progress and performance metrics

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
CONFIG_FILE="$REPO_ROOT/.github/pipeline-config.yml"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Logging functions
log() {
    echo -e "${BLUE}[$(date +'%H:%M:%S')]${NC} $1"
}

success() {
    echo -e "${GREEN}✅${NC} $1"
}

warning() {
    echo -e "${YELLOW}⚠️${NC} $1"
}

error() {
    echo -e "${RED}❌${NC} $1"
}

info() {
    echo -e "${CYAN}ℹ️${NC} $1"
}

# Get migration configuration
get_migration_config() {
    if [[ ! -f "$CONFIG_FILE" ]]; then
        error "Configuration file not found: $CONFIG_FILE"
        exit 1
    fi
    
    MIGRATION_ENABLED=$(yq eval '.pipeline_migration.enabled' "$CONFIG_FILE")
    DEFAULT_PIPELINE=$(yq eval '.pipeline_migration.default_pipeline' "$CONFIG_FILE")
    MIGRATION_PHASE=$(yq eval '.pipeline_migration.migration_phase' "$CONFIG_FILE")
}

# Get recent workflow runs
get_workflow_runs() {
    local limit="${1:-10}"
    gh run list --limit "$limit" --json status,conclusion,workflowName,createdAt,url,headSha
}

# Analyze workflow performance
analyze_workflow_performance() {
    log "Analyzing workflow performance..."
    
    local runs
    runs=$(get_workflow_runs 20)
    
    echo ""
    echo "📊 Recent Workflow Performance:"
    echo "================================"
    
    # Count successes and failures by workflow
    local ci_cd_success=0 ci_cd_total=0
    local rpc_success=0 rpc_total=0
    local consolidated_success=0 consolidated_total=0
    local shadow_success=0 shadow_total=0
    
    while IFS= read -r run; do
        local workflow_name conclusion
        workflow_name=$(echo "$run" | jq -r '.workflowName')
        conclusion=$(echo "$run" | jq -r '.conclusion')
        
        case "$workflow_name" in
            "CI/CD Pipeline - Reverse Tender Platform")
                ((ci_cd_total++))
                [[ "$conclusion" == "success" ]] && ((ci_cd_success++))
                ;;
            "RPC Services Deployment Pipeline (Optimized)")
                ((rpc_total++))
                [[ "$conclusion" == "success" ]] && ((rpc_success++))
                ;;
            "Consolidated CI/CD Pipeline with Blue-Green Deployment")
                ((consolidated_total++))
                [[ "$conclusion" == "success" ]] && ((consolidated_success++))
                ;;
            "Unified CI/CD Pipeline (Shadow)")
                ((shadow_total++))
                [[ "$conclusion" == "success" ]] && ((shadow_success++))
                ;;
        esac
    done <<< "$(echo "$runs" | jq -c '.[]')"
    
    # Calculate success rates
    local ci_cd_rate=0 rpc_rate=0 consolidated_rate=0 shadow_rate=0
    
    [[ $ci_cd_total -gt 0 ]] && ci_cd_rate=$((ci_cd_success * 100 / ci_cd_total))
    [[ $rpc_total -gt 0 ]] && rpc_rate=$((rpc_success * 100 / rpc_total))
    [[ $consolidated_total -gt 0 ]] && consolidated_rate=$((consolidated_success * 100 / consolidated_total))
    [[ $shadow_total -gt 0 ]] && shadow_rate=$((shadow_success * 100 / shadow_total))
    
    # Display results with color coding
    printf "%-50s %s/%s (%s%%)\n" "CI/CD Pipeline (Baseline):" "$ci_cd_success" "$ci_cd_total" "$ci_cd_rate"
    if [[ $ci_cd_rate -ge 90 ]]; then
        success "Baseline pipeline performing well"
    elif [[ $ci_cd_rate -ge 70 ]]; then
        warning "Baseline pipeline has some issues"
    else
        error "Baseline pipeline failing frequently"
    fi
    
    printf "%-50s %s/%s (%s%%)\n" "RPC Deployment Pipeline:" "$rpc_success" "$rpc_total" "$rpc_rate"
    printf "%-50s %s/%s (%s%%)\n" "Consolidated Pipeline:" "$consolidated_success" "$consolidated_total" "$consolidated_rate"
    
    printf "%-50s %s/%s (%s%%)\n" "Shadow Pipeline:" "$shadow_success" "$shadow_total" "$shadow_rate"
    if [[ $shadow_total -gt 0 ]]; then
        if [[ $shadow_rate -ge 80 ]]; then
            success "Shadow pipeline validation successful"
        elif [[ $shadow_rate -ge 60 ]]; then
            warning "Shadow pipeline needs improvement"
        else
            error "Shadow pipeline failing - investigate before migration"
        fi
    else
        info "Shadow pipeline not yet executed"
    fi
    
    echo ""
}

# Show migration status
show_migration_status() {
    log "Current Migration Status"
    echo "========================"
    
    echo "Migration Enabled: $MIGRATION_ENABLED"
    echo "Default Pipeline: $DEFAULT_PIPELINE"
    echo "Migration Phase: $MIGRATION_PHASE"
    echo ""
    
    echo "Service Configuration:"
    echo "----------------------"
    
    local services
    services=$(yq eval '.pipeline_migration.service_overrides' "$CONFIG_FILE")
    
    # Count services by pipeline type
    local legacy_count=0 unified_count=0 shadow_count=0
    
    while IFS= read -r line; do
        if [[ "$line" =~ "legacy" ]]; then
            ((legacy_count++))
        elif [[ "$line" =~ "unified" ]]; then
            ((unified_count++))
        elif [[ "$line" =~ "shadow" ]]; then
            ((shadow_count++))
        fi
    done <<< "$services"
    
    echo "$services"
    echo ""
    
    echo "📈 Migration Progress:"
    echo "Legacy: $legacy_count services"
    echo "Shadow: $shadow_count services"
    echo "Unified: $unified_count services"
    
    local total_services=$((legacy_count + unified_count + shadow_count))
    if [[ $total_services -gt 0 ]]; then
        local unified_percentage=$((unified_count * 100 / total_services))
        echo "Migration Progress: $unified_percentage% complete"
        
        if [[ $unified_percentage -eq 0 ]]; then
            info "Migration not yet started"
        elif [[ $unified_percentage -lt 25 ]]; then
            info "Early migration phase"
        elif [[ $unified_percentage -lt 75 ]]; then
            warning "Mid migration phase - monitor closely"
        elif [[ $unified_percentage -lt 100 ]]; then
            warning "Late migration phase - almost complete"
        else
            success "Migration complete!"
        fi
    fi
    
    echo ""
}

# Check for migration issues
check_migration_health() {
    log "Checking Migration Health"
    echo "========================="
    
    local issues_found=0
    
    # Check if shadow pipeline is running
    local recent_shadow_runs
    recent_shadow_runs=$(gh run list --workflow="unified-pipeline-shadow.yml" --limit 5 --json conclusion)
    
    if [[ $(echo "$recent_shadow_runs" | jq length) -eq 0 ]]; then
        warning "No recent shadow pipeline runs found"
        ((issues_found++))
    else
        local failed_runs
        failed_runs=$(echo "$recent_shadow_runs" | jq '[.[] | select(.conclusion == "failure")] | length')
        if [[ $failed_runs -gt 2 ]]; then
            error "Multiple shadow pipeline failures detected ($failed_runs/5)"
            ((issues_found++))
        fi
    fi
    
    # Check configuration consistency
    if [[ "$MIGRATION_ENABLED" != "true" ]]; then
        warning "Migration is disabled in configuration"
        ((issues_found++))
    fi
    
    # Check for services in inconsistent states
    local shadow_services
    shadow_services=$(yq eval '.pipeline_migration.service_overrides | to_entries | map(select(.value == "shadow")) | length' "$CONFIG_FILE")
    
    if [[ "$MIGRATION_PHASE" == "shadow" && $shadow_services -eq 0 ]]; then
        warning "Migration phase is 'shadow' but no services are configured for shadow mode"
        ((issues_found++))
    fi
    
    if [[ $issues_found -eq 0 ]]; then
        success "No migration health issues detected"
    else
        error "Found $issues_found migration health issues"
    fi
    
    echo ""
}

# Show recommendations
show_recommendations() {
    log "Migration Recommendations"
    echo "=========================="
    
    local recommendations=()
    
    # Analyze current state and provide recommendations
    if [[ "$MIGRATION_PHASE" == "shadow" ]]; then
        local shadow_services
        shadow_services=$(yq eval '.pipeline_migration.service_overrides | to_entries | map(select(.value == "shadow")) | length' "$CONFIG_FILE")
        
        if [[ $shadow_services -gt 0 ]]; then
            recommendations+=("✅ Continue monitoring shadow pipeline performance for 24-48 hours")
            recommendations+=("📊 Compare shadow pipeline metrics with baseline CI/CD pipeline")
            recommendations+=("🎯 Consider moving to canary phase for low-risk services")
        else
            recommendations+=("🚀 Configure low-risk services (analytics, notification, vin-ocr) for shadow mode")
            recommendations+=("📋 Review service risk assessment in docs/phase3-traffic-switch-plan.md")
        fi
    elif [[ "$MIGRATION_PHASE" == "canary" ]]; then
        recommendations+=("📈 Monitor canary services for 24 hours before expanding")
        recommendations+=("🔍 Check failure rates and performance metrics")
        recommendations+=("⚡ Consider moving to partial migration if canary is successful")
    fi
    
    # Performance-based recommendations
    local recent_runs
    recent_runs=$(get_workflow_runs 10)
    local shadow_failures
    shadow_failures=$(echo "$recent_runs" | jq '[.[] | select(.workflowName == "Unified CI/CD Pipeline (Shadow)" and .conclusion == "failure")] | length')
    
    if [[ $shadow_failures -gt 3 ]]; then
        recommendations+=("🚨 URGENT: Investigate shadow pipeline failures before proceeding")
        recommendations+=("🔧 Consider running rollback script: ./scripts/pipeline-rollback.sh --phase legacy")
    fi
    
    # General recommendations
    recommendations+=("📝 Review migration timeline in .github/pipeline-config.yml")
    recommendations+=("🔔 Set up monitoring alerts for migration metrics")
    recommendations+=("📚 Keep migration documentation updated")
    
    for rec in "${recommendations[@]}"; do
        echo "$rec"
    done
    
    echo ""
}

# Main monitoring dashboard
show_dashboard() {
    clear
    echo "🚀 Pipeline Migration Monitoring Dashboard"
    echo "=========================================="
    echo "Last Updated: $(date)"
    echo ""
    
    get_migration_config
    show_migration_status
    analyze_workflow_performance
    check_migration_health
    show_recommendations
    
    echo "💡 Commands:"
    echo "  ./scripts/pipeline-rollback.sh --help    # Emergency rollback options"
    echo "  ./scripts/monitor-migration.sh           # Refresh this dashboard"
    echo "  gh run list --limit 10                   # View recent workflow runs"
    echo ""
}

# Continuous monitoring mode
continuous_monitor() {
    local interval="${1:-300}"  # Default 5 minutes
    
    log "Starting continuous monitoring (refresh every ${interval}s)"
    log "Press Ctrl+C to stop"
    
    while true; do
        show_dashboard
        sleep "$interval"
    done
}

# Export metrics to JSON
export_metrics() {
    local output_file="${1:-migration-metrics-$(date +%Y%m%d-%H%M%S).json}"
    
    log "Exporting migration metrics to $output_file"
    
    local runs
    runs=$(get_workflow_runs 50)
    
    local metrics
    metrics=$(cat << EOF
{
  "timestamp": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "migration_config": $(yq eval '.pipeline_migration' "$CONFIG_FILE" -o json),
  "recent_runs": $runs,
  "summary": {
    "migration_enabled": $MIGRATION_ENABLED,
    "default_pipeline": "$DEFAULT_PIPELINE",
    "migration_phase": "$MIGRATION_PHASE"
  }
}
EOF
)
    
    echo "$metrics" | jq '.' > "$output_file"
    success "Metrics exported to $output_file"
}

# Help function
show_help() {
    cat << EOF
Pipeline Migration Monitoring Script

Usage: $0 [OPTIONS]

OPTIONS:
    -h, --help              Show this help message
    -d, --dashboard         Show migration dashboard (default)
    -c, --continuous [SEC]  Continuous monitoring mode (default: 300s)
    -e, --export [FILE]     Export metrics to JSON file
    -s, --status            Show migration status only
    -p, --performance       Show performance analysis only
    -r, --recommendations   Show recommendations only

EXAMPLES:
    $0                      # Show dashboard once
    $0 --continuous 60      # Monitor every 60 seconds
    $0 --export metrics.json # Export metrics to file
    $0 --status             # Quick status check

EOF
}

# Main function
main() {
    case "${1:-dashboard}" in
        -h|--help)
            show_help
            ;;
        -d|--dashboard)
            show_dashboard
            ;;
        -c|--continuous)
            continuous_monitor "${2:-300}"
            ;;
        -e|--export)
            export_metrics "${2:-}"
            ;;
        -s|--status)
            get_migration_config
            show_migration_status
            ;;
        -p|--performance)
            analyze_workflow_performance
            ;;
        -r|--recommendations)
            get_migration_config
            show_recommendations
            ;;
        *)
            show_dashboard
            ;;
    esac
}

# Check dependencies
if ! command -v gh &> /dev/null; then
    error "GitHub CLI (gh) is required but not installed"
    exit 1
fi

if ! command -v yq &> /dev/null; then
    error "yq is required but not installed"
    exit 1
fi

if ! command -v jq &> /dev/null; then
    error "jq is required but not installed"
    exit 1
fi

# Run main function
main "$@"
