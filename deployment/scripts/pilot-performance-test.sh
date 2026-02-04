#!/bin/bash

# RPC Pilot Performance Testing Script
# This script runs comprehensive performance tests comparing REST vs RPC

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SHARED_REST_URL="http://localhost:8010"
SHARED_RPC_URL="http://localhost:6010"
AUTH_REST_URL="http://localhost:8011"
AUTH_RPC_URL="http://localhost:6011"
RESULTS_DIR="./results/$(date +%Y%m%d_%H%M%S)"
GRAFANA_URL="http://localhost:9000"

# Test parameters
WARMUP_REQUESTS=100
LIGHT_LOAD_REQUESTS=500
HEAVY_LOAD_REQUESTS=2000
CONCURRENT_USERS=10
HEAVY_CONCURRENT_USERS=50

echo -e "${BLUE}🚀 Starting RPC Pilot Performance Testing${NC}"
echo -e "${BLUE}Results will be saved to: ${RESULTS_DIR}${NC}"

# Create results directory
mkdir -p "$RESULTS_DIR"

# Function to log with timestamp
log() {
    echo -e "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Function to run Apache Bench test
run_ab_test() {
    local name="$1"
    local url="$2"
    local requests="$3"
    local concurrency="$4"
    local output_file="$5"
    
    log "${YELLOW}Running AB test: $name${NC}"
    ab -n "$requests" -c "$concurrency" -g "$output_file.tsv" "$url" > "$output_file.txt" 2>&1
    
    # Extract key metrics
    local response_time=$(grep "Time per request:" "$output_file.txt" | head -1 | awk '{print $4}')
    local requests_per_sec=$(grep "Requests per second:" "$output_file.txt" | awk '{print $4}')
    local failed_requests=$(grep "Failed requests:" "$output_file.txt" | awk '{print $3}')
    
    echo "$name,$response_time,$requests_per_sec,$failed_requests" >> "$RESULTS_DIR/summary.csv"
    log "${GREEN}✅ Completed: $name - ${response_time}ms avg, ${requests_per_sec} RPS${NC}"
}

# Function to run RPC test
run_rpc_test() {
    local name="$1"
    local url="$2"
    local method="$3"
    local procedure="$4"
    local requests="$5"
    local concurrency="$6"
    local output_file="$7"
    
    log "${YELLOW}Running RPC test: $name${NC}"
    node deployment/scripts/rpc-load-test.js "$url" "$method" "$procedure" "$requests" "$concurrency" > "$output_file.txt" 2>&1
    
    # Extract metrics from RPC test output
    local response_time=$(grep "Average response time:" "$output_file.txt" | awk '{print $4}' | sed 's/ms//')
    local requests_per_sec=$(grep "Requests per second:" "$output_file.txt" | awk '{print $4}')
    local failed_requests=$(grep "Failed requests:" "$output_file.txt" | awk '{print $3}' || echo "0")
    
    echo "$name,$response_time,$requests_per_sec,$failed_requests" >> "$RESULTS_DIR/summary.csv"
    log "${GREEN}✅ Completed: $name - ${response_time}ms avg, ${requests_per_sec} RPS${NC}"
}

# Initialize results file
echo "Test Name,Response Time (ms),Requests/sec,Failed Requests" > "$RESULTS_DIR/summary.csv"

log "${BLUE}📊 Phase 1: Warmup Tests${NC}"

# Warmup tests
run_ab_test "Shared-REST-Warmup" "$SHARED_REST_URL/api/health" $WARMUP_REQUESTS 5 "$RESULTS_DIR/shared_rest_warmup"
run_rpc_test "Shared-RPC-Warmup" "$SHARED_RPC_URL" "Health" "ping" $WARMUP_REQUESTS 5 "$RESULTS_DIR/shared_rpc_warmup"

log "${BLUE}📊 Phase 2: Shared Service Performance Tests${NC}"

# Shared Service Health Check Tests
run_ab_test "Shared-REST-Health-Light" "$SHARED_REST_URL/api/health" $LIGHT_LOAD_REQUESTS $CONCURRENT_USERS "$RESULTS_DIR/shared_rest_health_light"
run_rpc_test "Shared-RPC-Health-Light" "$SHARED_RPC_URL" "Health" "ping" $LIGHT_LOAD_REQUESTS $CONCURRENT_USERS "$RESULTS_DIR/shared_rpc_health_light"

run_ab_test "Shared-REST-Health-Heavy" "$SHARED_REST_URL/api/health" $HEAVY_LOAD_REQUESTS $HEAVY_CONCURRENT_USERS "$RESULTS_DIR/shared_rest_health_heavy"
run_rpc_test "Shared-RPC-Health-Heavy" "$SHARED_RPC_URL" "Health" "ping" $HEAVY_LOAD_REQUESTS $HEAVY_CONCURRENT_USERS "$RESULTS_DIR/shared_rpc_health_heavy"

# Shared Service Utility Tests
log "${YELLOW}Testing UUID generation endpoint${NC}"
run_ab_test "Shared-REST-UUID-Light" "$SHARED_REST_URL/api/utility/uuid" $LIGHT_LOAD_REQUESTS $CONCURRENT_USERS "$RESULTS_DIR/shared_rest_uuid_light"
run_rpc_test "Shared-RPC-UUID-Light" "$SHARED_RPC_URL" "Utility" "generateUuid" $LIGHT_LOAD_REQUESTS $CONCURRENT_USERS "$RESULTS_DIR/shared_rpc_uuid_light"

log "${BLUE}📊 Phase 3: Auth Service Performance Tests${NC}"

# Create test user data for auth tests
cat > "$RESULTS_DIR/auth_test_data.json" << EOF
{
  "email": "test@reversetender.com",
  "password": "testpassword123"
}
EOF

# Auth Service Tests (if endpoints are available)
if curl -s "$AUTH_REST_URL/api/health" > /dev/null; then
    run_ab_test "Auth-REST-Health-Light" "$AUTH_REST_URL/api/health" $LIGHT_LOAD_REQUESTS $CONCURRENT_USERS "$RESULTS_DIR/auth_rest_health_light"
    run_rpc_test "Auth-RPC-Health-Light" "$AUTH_RPC_URL" "Health" "ping" $LIGHT_LOAD_REQUESTS $CONCURRENT_USERS "$RESULTS_DIR/auth_rpc_health_light"
else
    log "${YELLOW}⚠️ Auth service not available, skipping auth tests${NC}"
fi

log "${BLUE}📊 Phase 4: Memory and Resource Usage Tests${NC}"

# Memory usage test
log "${YELLOW}Collecting memory usage data${NC}"
docker stats --no-stream --format "table {{.Container}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.MemPerc}}" > "$RESULTS_DIR/docker_stats.txt"

log "${BLUE}📊 Phase 5: Concurrent Load Tests${NC}"

# High concurrency tests
run_ab_test "Shared-REST-Concurrent" "$SHARED_REST_URL/api/health" 1000 100 "$RESULTS_DIR/shared_rest_concurrent"
run_rpc_test "Shared-RPC-Concurrent" "$SHARED_RPC_URL" "Health" "ping" 1000 100 "$RESULTS_DIR/shared_rpc_concurrent"

log "${BLUE}📊 Generating Performance Report${NC}"

# Generate performance comparison report
cat > "$RESULTS_DIR/performance_report.md" << EOF
# RPC Pilot Performance Test Report

**Test Date:** $(date)
**Test Duration:** Approximately 10-15 minutes
**Services Tested:** Shared Service, Auth Service

## Test Configuration
- Light Load: $LIGHT_LOAD_REQUESTS requests, $CONCURRENT_USERS concurrent users
- Heavy Load: $HEAVY_LOAD_REQUESTS requests, $HEAVY_CONCURRENT_USERS concurrent users
- Warmup: $WARMUP_REQUESTS requests

## Results Summary

### Performance Metrics
\`\`\`
$(cat "$RESULTS_DIR/summary.csv")
\`\`\`

### Docker Resource Usage
\`\`\`
$(cat "$RESULTS_DIR/docker_stats.txt")
\`\`\`

## Key Findings

### Response Time Comparison
- Compare REST vs RPC response times from the summary above
- Look for 40-60% improvement in RPC response times

### Throughput Comparison  
- Compare requests per second between REST and RPC
- Target: 2x improvement in RPC throughput

### Memory Usage
- Check Docker stats for memory consumption differences
- Target: 30-50% reduction in memory usage for RPC services

## Recommendations

1. **Performance Gains**: [To be filled based on actual results]
2. **Memory Efficiency**: [To be filled based on actual results]  
3. **Scalability**: [To be filled based on actual results]
4. **Next Steps**: [To be filled based on actual results]

## Grafana Dashboard
View real-time metrics at: $GRAFANA_URL
- Username: admin
- Password: pilot123

## Raw Test Data
All raw test results are available in: $RESULTS_DIR/
EOF

log "${GREEN}✅ Performance testing completed!${NC}"
log "${GREEN}📊 Results saved to: $RESULTS_DIR${NC}"
log "${GREEN}📈 View Grafana dashboard at: $GRAFANA_URL${NC}"
log "${GREEN}📋 Performance report: $RESULTS_DIR/performance_report.md${NC}"

# Display quick summary
echo -e "\n${BLUE}📊 Quick Performance Summary:${NC}"
echo -e "${BLUE}================================${NC}"
tail -n +2 "$RESULTS_DIR/summary.csv" | while IFS=',' read -r name response_time rps failed; do
    if [[ $name == *"RPC"* ]]; then
        echo -e "${GREEN}$name: ${response_time}ms, ${rps} RPS${NC}"
    else
        echo -e "${YELLOW}$name: ${response_time}ms, ${rps} RPS${NC}"
    fi
done

echo -e "\n${BLUE}🎯 Next Steps:${NC}"
echo -e "1. Review detailed results in $RESULTS_DIR/"
echo -e "2. Check Grafana dashboard at $GRAFANA_URL"
echo -e "3. Analyze performance improvements"
echo -e "4. Document findings for full deployment planning"
