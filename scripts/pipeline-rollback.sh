#!/bin/bash
# Pipeline Migration Rollback Script
# Provides emergency rollback capabilities for pipeline migration

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
CONFIG_FILE="$REPO_ROOT/.github/pipeline-config.yml"
LOG_FILE="/tmp/pipeline-rollback-$(date +%Y%m%d-%H%M%S).log"

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
Pipeline Migration Rollback Script

Usage: $0 [OPTIONS] [SERVICE_NAME]

OPTIONS:
    -h, --help              Show this help message
    -s, --service SERVICE   Rollback specific service to legacy pipeline
    -a, --all              Rollback all services to legacy pipeline
    -p, --phase PHASE      Rollback to specific migration phase
    -f, --force            Force rollback without confirmation
    -d, --dry-run          Show what would be done without making changes
    -v, --verbose          Enable verbose logging

PHASES:
    shadow                 All services use shadow mode (validation only)
    legacy                 All services use legacy pipelines
    canary                 Reset to canary testing phase
    partial                Reset to partial migration phase

EXAMPLES:
    $0 --service notification-service    # Rollback notification-service only
    $0 --all --force                     # Emergency rollback all services
    $0 --phase legacy                    # Rollback entire migration to legacy
    $0 --dry-run --all                   # Preview full rollback changes

EOF
}

# Check dependencies
check_dependencies() {
    local deps=("yq" "git" "gh")
    for dep in "${deps[@]}"; do
        if ! command -v "$dep" &> /dev/null; then
            error "Required dependency '$dep' not found. Please install it first."
            exit 1
        fi
    done
}

# Validate configuration file exists
check_config() {
    if [[ ! -f "$CONFIG_FILE" ]]; then
        error "Configuration file not found: $CONFIG_FILE"
        exit 1
    fi
}

# Get current migration status
get_migration_status() {
    log "Current migration status:"
    echo "=========================="
    
    local default_pipeline
    default_pipeline=$(yq eval '.pipeline_migration.default_pipeline' "$CONFIG_FILE")
    echo "Default Pipeline: $default_pipeline"
    
    local migration_phase
    migration_phase=$(yq eval '.pipeline_migration.migration_phase' "$CONFIG_FILE")
    echo "Migration Phase: $migration_phase"
    
    echo ""
    echo "Service Overrides:"
    yq eval '.pipeline_migration.service_overrides' "$CONFIG_FILE"
    echo ""
}

# Rollback specific service
rollback_service() {
    local service_name="$1"
    local target_pipeline="${2:-legacy}"
    
    log "Rolling back service '$service_name' to '$target_pipeline' pipeline..."
    
    # Update service override in configuration
    yq eval ".pipeline_migration.service_overrides.$service_name = \"$target_pipeline\"" -i "$CONFIG_FILE"
    
    success "Service '$service_name' configuration updated to '$target_pipeline'"
}

# Rollback all services
rollback_all_services() {
    local target_pipeline="${1:-legacy}"
    
    log "Rolling back ALL services to '$target_pipeline' pipeline..."
    
    # Get list of all services
    local services
    services=$(yq eval '.pipeline_migration.service_overrides | keys | .[]' "$CONFIG_FILE")
    
    # Update each service
    while IFS= read -r service; do
        if [[ -n "$service" ]]; then
            rollback_service "$service" "$target_pipeline"
        fi
    done <<< "$services"
    
    # Update default pipeline
    yq eval ".pipeline_migration.default_pipeline = \"$target_pipeline\"" -i "$CONFIG_FILE"
    
    success "All services rolled back to '$target_pipeline' pipeline"
}

# Rollback to specific migration phase
rollback_to_phase() {
    local target_phase="$1"
    
    log "Rolling back migration to phase: $target_phase"
    
    case "$target_phase" in
        "shadow")
            yq eval '.pipeline_migration.migration_phase = "shadow"' -i "$CONFIG_FILE"
            yq eval '.pipeline_migration.default_pipeline = "legacy"' -i "$CONFIG_FILE"
            # Set all services to shadow mode
            rollback_all_services "shadow"
            ;;
        "legacy")
            yq eval '.pipeline_migration.migration_phase = "shadow"' -i "$CONFIG_FILE"
            yq eval '.pipeline_migration.default_pipeline = "legacy"' -i "$CONFIG_FILE"
            rollback_all_services "legacy"
            ;;
        "canary")
            yq eval '.pipeline_migration.migration_phase = "canary"' -i "$CONFIG_FILE"
            # Reset low-risk services to canary
            rollback_service "analytics-service" "unified"
            rollback_service "notification-service" "legacy"
            rollback_service "vin-ocr-service" "legacy"
            ;;
        "partial")
            yq eval '.pipeline_migration.migration_phase = "partial"' -i "$CONFIG_FILE"
            # Reset to partial migration state
            rollback_service "analytics-service" "unified"
            rollback_service "notification-service" "unified"
            rollback_service "vin-ocr-service" "legacy"
            ;;
        *)
            error "Unknown migration phase: $target_phase"
            exit 1
            ;;
    esac
    
    success "Migration rolled back to phase: $target_phase"
}

# Commit and push changes
commit_changes() {
    local commit_message="$1"
    
    if [[ "$DRY_RUN" == "true" ]]; then
        log "DRY RUN: Would commit changes with message: $commit_message"
        return
    fi
    
    log "Committing rollback changes..."
    
    cd "$REPO_ROOT"
    git add "$CONFIG_FILE"
    git commit -m "$commit_message"
    
    if git push; then
        success "Rollback changes committed and pushed successfully"
    else
        error "Failed to push rollback changes"
        exit 1
    fi
}

# Validate rollback
validate_rollback() {
    log "Validating rollback configuration..."
    
    # Check if configuration is valid YAML
    if ! yq eval '.' "$CONFIG_FILE" > /dev/null 2>&1; then
        error "Configuration file is not valid YAML after rollback"
        exit 1
    fi
    
    # Check if required fields exist
    local required_fields=(
        ".pipeline_migration.default_pipeline"
        ".pipeline_migration.migration_phase"
        ".pipeline_migration.service_overrides"
    )
    
    for field in "${required_fields[@]}"; do
        if ! yq eval "$field" "$CONFIG_FILE" > /dev/null 2>&1; then
            error "Required field missing after rollback: $field"
            exit 1
        fi
    done
    
    success "Rollback configuration validated successfully"
}

# Send notification
send_notification() {
    local message="$1"
    local severity="${2:-info}"
    
    log "Sending rollback notification..."
    
    # This would integrate with your notification system
    # For now, just log the notification
    case "$severity" in
        "critical")
            error "CRITICAL ROLLBACK: $message"
            ;;
        "warning")
            warning "ROLLBACK WARNING: $message"
            ;;
        *)
            log "ROLLBACK INFO: $message"
            ;;
    esac
    
    # TODO: Integrate with Slack, email, or other notification systems
    # Example: curl -X POST -H 'Content-type: application/json' \
    #          --data "{\"text\":\"$message\"}" \
    #          "$SLACK_WEBHOOK_URL"
}

# Main function
main() {
    local service_name=""
    local rollback_all=false
    local target_phase=""
    local force=false
    local dry_run=false
    local verbose=false
    
    # Parse command line arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            -h|--help)
                show_help
                exit 0
                ;;
            -s|--service)
                service_name="$2"
                shift 2
                ;;
            -a|--all)
                rollback_all=true
                shift
                ;;
            -p|--phase)
                target_phase="$2"
                shift 2
                ;;
            -f|--force)
                force=true
                shift
                ;;
            -d|--dry-run)
                dry_run=true
                shift
                ;;
            -v|--verbose)
                verbose=true
                shift
                ;;
            *)
                error "Unknown option: $1"
                show_help
                exit 1
                ;;
        esac
    done
    
    # Set global variables
    DRY_RUN="$dry_run"
    VERBOSE="$verbose"
    
    # Check dependencies and configuration
    check_dependencies
    check_config
    
    # Show current status
    get_migration_status
    
    # Determine rollback action
    if [[ -n "$service_name" ]]; then
        # Service-specific rollback
        if [[ "$force" == "false" ]]; then
            read -p "Are you sure you want to rollback service '$service_name'? (y/N): " -n 1 -r
            echo
            if [[ ! $REPLY =~ ^[Yy]$ ]]; then
                log "Rollback cancelled by user"
                exit 0
            fi
        fi
        
        rollback_service "$service_name"
        commit_changes "🚨 Emergency rollback: $service_name to legacy pipeline"
        send_notification "Service $service_name rolled back to legacy pipeline" "warning"
        
    elif [[ "$rollback_all" == "true" ]]; then
        # Full rollback
        if [[ "$force" == "false" ]]; then
            read -p "Are you sure you want to rollback ALL services? This is a major operation! (y/N): " -n 1 -r
            echo
            if [[ ! $REPLY =~ ^[Yy]$ ]]; then
                log "Rollback cancelled by user"
                exit 0
            fi
        fi
        
        rollback_all_services
        commit_changes "🚨 EMERGENCY: Full pipeline rollback to legacy workflows"
        send_notification "CRITICAL: All services rolled back to legacy pipelines" "critical"
        
    elif [[ -n "$target_phase" ]]; then
        # Phase rollback
        if [[ "$force" == "false" ]]; then
            read -p "Are you sure you want to rollback to phase '$target_phase'? (y/N): " -n 1 -r
            echo
            if [[ ! $REPLY =~ ^[Yy]$ ]]; then
                log "Rollback cancelled by user"
                exit 0
            fi
        fi
        
        rollback_to_phase "$target_phase"
        commit_changes "🔄 Pipeline migration rollback to phase: $target_phase"
        send_notification "Pipeline migration rolled back to phase: $target_phase" "warning"
        
    else
        error "No rollback action specified. Use --help for usage information."
        exit 1
    fi
    
    # Validate the rollback
    validate_rollback
    
    # Show final status
    echo ""
    log "Rollback completed successfully!"
    get_migration_status
    
    log "Rollback log saved to: $LOG_FILE"
}

# Run main function with all arguments
main "$@"
