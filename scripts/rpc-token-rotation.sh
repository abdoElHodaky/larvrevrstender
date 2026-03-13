#!/bin/bash

# RPC Token Rotation Script for Production Environments
# This script handles automated token rotation with proper service restart procedures

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
AUTH_SERVICE_DIR="$PROJECT_ROOT/services/auth-service"
LOG_FILE="/var/log/rpc-token-rotation.log"
BACKUP_ENABLED=true
DRY_RUN=false
FORCE_ROTATION=false
NOTIFICATION_WEBHOOK=""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    local level="$1"
    shift
    local message="$*"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo -e "${timestamp} [${level}] ${message}" | tee -a "$LOG_FILE"
}

log_info() {
    log "INFO" "$@"
    echo -e "${BLUE}[INFO]${NC} $*"
}

log_warn() {
    log "WARN" "$@"
    echo -e "${YELLOW}[WARN]${NC} $*"
}

log_error() {
    log "ERROR" "$@"
    echo -e "${RED}[ERROR]${NC} $*"
}

log_success() {
    log "SUCCESS" "$@"
    echo -e "${GREEN}[SUCCESS]${NC} $*"
}

# Help function
show_help() {
    cat << EOF
RPC Token Rotation Script

Usage: $0 [OPTIONS]

OPTIONS:
    --dry-run           Show what would be rotated without making changes
    --force             Force rotation even if tokens are not near expiration
    --service=NAME      Rotate tokens for specific service only
    --expires-in=HOURS  New token expiration in hours (default: 8760)
    --backup            Create backup of current tokens before rotation
    --webhook=URL       Webhook URL for notifications
    --help              Show this help message

EXAMPLES:
    $0 --dry-run                    # Preview what would be rotated
    $0 --force --backup             # Force rotate all tokens with backup
    $0 --service=user-service       # Rotate tokens for specific service
    $0 --expires-in=720             # Set 30-day expiration

ENVIRONMENT VARIABLES:
    RPC_ROTATION_WEBHOOK    Webhook URL for notifications
    RPC_ROTATION_BACKUP     Enable/disable backups (true/false)

EOF
}

# Parse command line arguments
parse_args() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            --dry-run)
                DRY_RUN=true
                shift
                ;;
            --force)
                FORCE_ROTATION=true
                shift
                ;;
            --service=*)
                SERVICE_NAME="${1#*=}"
                shift
                ;;
            --expires-in=*)
                EXPIRES_IN="${1#*=}"
                shift
                ;;
            --backup)
                BACKUP_ENABLED=true
                shift
                ;;
            --webhook=*)
                NOTIFICATION_WEBHOOK="${1#*=}"
                shift
                ;;
            --help)
                show_help
                exit 0
                ;;
            *)
                log_error "Unknown option: $1"
                show_help
                exit 1
                ;;
        esac
    done

    # Set defaults
    EXPIRES_IN="${EXPIRES_IN:-8760}"
    NOTIFICATION_WEBHOOK="${NOTIFICATION_WEBHOOK:-${RPC_ROTATION_WEBHOOK:-}}"
    BACKUP_ENABLED="${RPC_ROTATION_BACKUP:-$BACKUP_ENABLED}"
}

# Check prerequisites
check_prerequisites() {
    log_info "Checking prerequisites..."

    # Check if auth service directory exists
    if [[ ! -d "$AUTH_SERVICE_DIR" ]]; then
        log_error "Auth service directory not found: $AUTH_SERVICE_DIR"
        exit 1
    fi

    # Check if PHP is available
    if ! command -v php &> /dev/null; then
        log_error "PHP is not installed or not in PATH"
        exit 1
    fi

    # Check if Laravel artisan is available
    if [[ ! -f "$AUTH_SERVICE_DIR/artisan" ]]; then
        log_error "Laravel artisan not found in auth service directory"
        exit 1
    fi

    # Check if Docker is available for service restart
    if ! command -v docker &> /dev/null; then
        log_warn "Docker not found - service restart will need to be handled manually"
    fi

    log_success "Prerequisites check passed"
}

# Send notification
send_notification() {
    local status="$1"
    local message="$2"
    
    if [[ -n "$NOTIFICATION_WEBHOOK" ]]; then
        local payload=$(cat << EOF
{
    "text": "RPC Token Rotation: $status",
    "attachments": [
        {
            "color": "$([[ "$status" == "SUCCESS" ]] && echo "good" || echo "danger")",
            "fields": [
                {
                    "title": "Status",
                    "value": "$status",
                    "short": true
                },
                {
                    "title": "Message",
                    "value": "$message",
                    "short": false
                },
                {
                    "title": "Timestamp",
                    "value": "$(date '+%Y-%m-%d %H:%M:%S')",
                    "short": true
                }
            ]
        }
    ]
}
EOF
        )
        
        curl -X POST -H 'Content-type: application/json' \
             --data "$payload" \
             "$NOTIFICATION_WEBHOOK" &> /dev/null || true
    fi
}

# Perform token rotation
perform_rotation() {
    log_info "Starting RPC token rotation..."
    
    local rotation_args=()
    
    if [[ "$DRY_RUN" == "true" ]]; then
        rotation_args+=(--dry-run)
        log_info "Running in DRY RUN mode"
    fi
    
    if [[ "$FORCE_ROTATION" == "true" ]]; then
        rotation_args+=(--force)
        log_info "Force rotation enabled"
    fi
    
    if [[ -n "${SERVICE_NAME:-}" ]]; then
        rotation_args+=(--service="$SERVICE_NAME")
        log_info "Rotating tokens for service: $SERVICE_NAME"
    fi
    
    rotation_args+=(--expires-in="$EXPIRES_IN")
    
    if [[ "$BACKUP_ENABLED" == "true" && "$DRY_RUN" != "true" ]]; then
        rotation_args+=(--backup)
        log_info "Backup enabled"
    fi
    
    # Change to auth service directory and run rotation
    cd "$AUTH_SERVICE_DIR"
    
    if php artisan rpc:rotate-tokens "${rotation_args[@]}"; then
        log_success "Token rotation completed successfully"
        return 0
    else
        log_error "Token rotation failed"
        return 1
    fi
}

# Restart services (placeholder - customize for your deployment)
restart_services() {
    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "Would restart services (dry run mode)"
        return 0
    fi

    log_info "Restarting services to load new tokens..."
    
    # Example for Docker Compose deployment
    if command -v docker-compose &> /dev/null; then
        if docker-compose restart; then
            log_success "Services restarted successfully"
        else
            log_error "Failed to restart services"
            return 1
        fi
    elif command -v docker &> /dev/null && command -v docker compose &> /dev/null; then
        if docker compose restart; then
            log_success "Services restarted successfully"
        else
            log_error "Failed to restart services"
            return 1
        fi
    else
        log_warn "Docker Compose not found - please restart services manually"
        log_warn "Services that need restart:"
        log_warn "  • All microservices to load new RPC tokens"
    fi
}

# Test RPC authentication
test_authentication() {
    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "Would test RPC authentication (dry run mode)"
        return 0
    fi

    log_info "Testing RPC authentication..."
    
    cd "$AUTH_SERVICE_DIR"
    
    if php artisan rpc:test-authentication --timeout=30; then
        log_success "RPC authentication test passed"
        return 0
    else
        log_error "RPC authentication test failed"
        return 1
    fi
}

# Main execution
main() {
    local start_time=$(date '+%Y-%m-%d %H:%M:%S')
    log_info "Starting RPC token rotation process at $start_time"
    
    # Parse arguments
    parse_args "$@"
    
    # Check prerequisites
    check_prerequisites
    
    # Perform rotation
    if perform_rotation; then
        # Restart services if not dry run
        if [[ "$DRY_RUN" != "true" ]]; then
            restart_services
            
            # Wait a moment for services to start
            sleep 10
            
            # Test authentication
            test_authentication
        fi
        
        local end_time=$(date '+%Y-%m-%d %H:%M:%S')
        log_success "RPC token rotation process completed successfully at $end_time"
        send_notification "SUCCESS" "Token rotation completed successfully"
        exit 0
    else
        log_error "RPC token rotation process failed"
        send_notification "FAILED" "Token rotation failed - check logs for details"
        exit 1
    fi
}

# Handle script interruption
trap 'log_error "Script interrupted"; send_notification "INTERRUPTED" "Token rotation was interrupted"; exit 130' INT TERM

# Run main function with all arguments
main "$@"
