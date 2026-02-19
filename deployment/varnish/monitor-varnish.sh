#!/bin/bash

# Varnish Monitoring Script
# This script provides real-time monitoring and statistics for Varnish cache

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
VARNISH_HOST="${VARNISH_HOST:-localhost}"
VARNISH_PORT="${VARNISH_PORT:-80}"
VARNISH_ADMIN_PORT="${VARNISH_ADMIN_PORT:-6081}"
REFRESH_INTERVAL="${REFRESH_INTERVAL:-5}"

# Function to print colored output
print_header() {
    echo -e "${BLUE}$1${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to get Varnish statistics
get_varnish_stats() {
    if command_exists varnishstat; then
        varnishstat -1 -f MAIN.cache_hit -f MAIN.cache_miss -f MAIN.client_req -f MAIN.backend_req -f MAIN.n_object
    else
        print_error "varnishstat command not found"
        return 1
    fi
}

# Function to calculate hit ratio
calculate_hit_ratio() {
    local stats=$(get_varnish_stats 2>/dev/null)
    if [ $? -eq 0 ]; then
        local hits=$(echo "$stats" | grep "MAIN.cache_hit" | awk '{print $2}')
        local misses=$(echo "$stats" | grep "MAIN.cache_miss" | awk '{print $2}')
        
        if [ -n "$hits" ] && [ -n "$misses" ] && [ "$hits" -gt 0 ] || [ "$misses" -gt 0 ]; then
            local total=$((hits + misses))
            if [ "$total" -gt 0 ]; then
                local ratio=$(echo "scale=2; $hits * 100 / $total" | bc 2>/dev/null || echo "0")
                echo "$ratio"
            else
                echo "0"
            fi
        else
            echo "0"
        fi
    else
        echo "N/A"
    fi
}

# Function to test Varnish connectivity
test_connectivity() {
    print_header "🔍 Testing Varnish Connectivity"
    
    # Test HTTP port
    if curl -s -f "http://${VARNISH_HOST}:${VARNISH_PORT}/health" >/dev/null 2>&1; then
        print_success "HTTP port ${VARNISH_PORT} is accessible"
    else
        print_warning "HTTP port ${VARNISH_PORT} may not be accessible"
    fi
    
    # Test admin port
    if command_exists varnishadm; then
        if varnishadm -T "${VARNISH_HOST}:${VARNISH_ADMIN_PORT}" ping >/dev/null 2>&1; then
            print_success "Admin port ${VARNISH_ADMIN_PORT} is accessible"
        else
            print_warning "Admin port ${VARNISH_ADMIN_PORT} may not be accessible"
        fi
    else
        print_warning "varnishadm command not found"
    fi
}

# Function to show backend health
show_backend_health() {
    print_header "🏥 Backend Health Status"
    
    if command_exists varnishadm; then
        varnishadm -T "${VARNISH_HOST}:${VARNISH_ADMIN_PORT}" backend.list 2>/dev/null || print_error "Could not retrieve backend status"
    else
        print_warning "varnishadm command not available"
    fi
}

# Function to show cache statistics
show_cache_stats() {
    print_header "📊 Cache Statistics"
    
    local stats=$(get_varnish_stats 2>/dev/null)
    if [ $? -eq 0 ]; then
        echo "$stats" | while read line; do
            if [[ $line == *"cache_hit"* ]]; then
                echo -e "${GREEN}$line${NC}"
            elif [[ $line == *"cache_miss"* ]]; then
                echo -e "${YELLOW}$line${NC}"
            else
                echo "$line"
            fi
        done
        
        local hit_ratio=$(calculate_hit_ratio)
        echo ""
        if [[ $hit_ratio != "N/A" ]]; then
            if (( $(echo "$hit_ratio >= 80" | bc -l 2>/dev/null || echo 0) )); then
                print_success "Cache Hit Ratio: ${hit_ratio}%"
            elif (( $(echo "$hit_ratio >= 50" | bc -l 2>/dev/null || echo 0) )); then
                print_warning "Cache Hit Ratio: ${hit_ratio}%"
            else
                print_error "Cache Hit Ratio: ${hit_ratio}% (Low)"
            fi
        else
            print_warning "Cache Hit Ratio: N/A"
        fi
    else
        print_error "Could not retrieve cache statistics"
    fi
}

# Function to show recent cache activity
show_cache_activity() {
    print_header "🔄 Recent Cache Activity (Last 10 requests)"
    
    if command_exists varnishlog; then
        timeout 5s varnishlog -n default -d -k 10 2>/dev/null | grep -E "(ReqURL|VCL_call|RespStatus)" | head -20 || print_warning "Could not retrieve cache activity"
    else
        print_warning "varnishlog command not available"
    fi
}

# Function to show memory usage
show_memory_usage() {
    print_header "💾 Memory Usage"
    
    if command_exists varnishstat; then
        varnishstat -1 -f SMA.s0.g_bytes -f SMA.s0.g_space 2>/dev/null || print_warning "Could not retrieve memory statistics"
    else
        print_warning "varnishstat command not available"
    fi
}

# Function to show top requested URLs
show_top_urls() {
    print_header "🔝 Top Requested URLs (Last 100 requests)"
    
    if command_exists varnishlog; then
        timeout 10s varnishlog -n default -d -k 100 2>/dev/null | \
        grep "ReqURL" | \
        awk '{print $3}' | \
        sort | \
        uniq -c | \
        sort -nr | \
        head -10 || print_warning "Could not retrieve URL statistics"
    else
        print_warning "varnishlog command not available"
    fi
}

# Function to purge cache
purge_cache() {
    local pattern="${1:-.*}"
    print_header "🧹 Purging Cache Pattern: $pattern"
    
    if command_exists varnishadm; then
        if varnishadm -T "${VARNISH_HOST}:${VARNISH_ADMIN_PORT}" "ban req.url ~ \"$pattern\"" 2>/dev/null; then
            print_success "Cache purged successfully"
        else
            print_error "Failed to purge cache"
        fi
    else
        print_error "varnishadm command not available"
    fi
}

# Function to show help
show_help() {
    echo "Varnish Monitoring Script"
    echo ""
    echo "Usage: $0 [command] [options]"
    echo ""
    echo "Commands:"
    echo "  stats       - Show cache statistics (default)"
    echo "  monitor     - Continuous monitoring"
    echo "  health      - Check backend health"
    echo "  activity    - Show recent cache activity"
    echo "  memory      - Show memory usage"
    echo "  urls        - Show top requested URLs"
    echo "  purge [pattern] - Purge cache by pattern"
    echo "  test        - Test connectivity"
    echo "  help        - Show this help"
    echo ""
    echo "Environment Variables:"
    echo "  VARNISH_HOST        - Varnish host (default: localhost)"
    echo "  VARNISH_PORT        - Varnish HTTP port (default: 80)"
    echo "  VARNISH_ADMIN_PORT  - Varnish admin port (default: 6081)"
    echo "  REFRESH_INTERVAL    - Monitoring refresh interval (default: 5)"
    echo ""
    echo "Examples:"
    echo "  $0 stats"
    echo "  $0 monitor"
    echo "  $0 purge '/api/auctions.*'"
    echo "  VARNISH_HOST=varnish.example.com $0 health"
}

# Function for continuous monitoring
continuous_monitor() {
    print_header "🔄 Starting Continuous Monitoring (Press Ctrl+C to stop)"
    echo "Refresh interval: ${REFRESH_INTERVAL} seconds"
    echo ""
    
    while true; do
        clear
        echo "$(date '+%Y-%m-%d %H:%M:%S') - Varnish Monitor"
        echo "=================================================="
        
        show_cache_stats
        echo ""
        show_backend_health
        echo ""
        show_memory_usage
        
        echo ""
        echo "Next refresh in ${REFRESH_INTERVAL} seconds... (Press Ctrl+C to stop)"
        sleep "$REFRESH_INTERVAL"
    done
}

# Main script logic
case "${1:-stats}" in
    "stats")
        show_cache_stats
        ;;
    "monitor")
        continuous_monitor
        ;;
    "health")
        show_backend_health
        ;;
    "activity")
        show_cache_activity
        ;;
    "memory")
        show_memory_usage
        ;;
    "urls")
        show_top_urls
        ;;
    "purge")
        purge_cache "$2"
        ;;
    "test")
        test_connectivity
        ;;
    "help"|"-h"|"--help")
        show_help
        ;;
    *)
        print_error "Unknown command: $1"
        show_help
        exit 1
        ;;
esac
