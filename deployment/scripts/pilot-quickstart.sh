#!/bin/bash

# RPC Pilot Quick Start Script
# Sets up and runs the complete RPC pilot environment

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PILOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DOCKER_COMPOSE_FILE="$PILOT_DIR/docker/docker-compose.pilot.yml"

echo -e "${BLUE}🚀 RPC Pilot Implementation Quick Start${NC}"
echo -e "${BLUE}======================================${NC}"

# Function to log with timestamp
log() {
    echo -e "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Check prerequisites
log "${BLUE}📋 Checking Prerequisites${NC}"

if ! command_exists docker; then
    log "${RED}❌ Docker is not installed. Please install Docker first.${NC}"
    exit 1
fi

if ! command_exists docker-compose; then
    log "${RED}❌ Docker Compose is not installed. Please install Docker Compose first.${NC}"
    exit 1
fi

if ! command_exists node; then
    log "${RED}❌ Node.js is not installed. Please install Node.js first.${NC}"
    exit 1
fi

if ! command_exists ab; then
    log "${YELLOW}⚠️ Apache Bench (ab) is not installed. Installing...${NC}"
    if command_exists apt-get; then
        sudo apt-get update && sudo apt-get install -y apache2-utils
    elif command_exists yum; then
        sudo yum install -y httpd-tools
    elif command_exists brew; then
        brew install httpd
    else
        log "${RED}❌ Cannot install Apache Bench automatically. Please install it manually.${NC}"
        exit 1
    fi
fi

log "${GREEN}✅ All prerequisites are installed${NC}"

# Create necessary directories
log "${BLUE}📁 Creating Directory Structure${NC}"
mkdir -p "$PILOT_DIR/logs/shared-rest"
mkdir -p "$PILOT_DIR/logs/shared-rpc"
mkdir -p "$PILOT_DIR/logs/auth-rest"
mkdir -p "$PILOT_DIR/logs/auth-rpc"
mkdir -p "$PILOT_DIR/results"
mkdir -p "$PILOT_DIR/monitoring/grafana/dashboards"
mkdir -p "$PILOT_DIR/monitoring/grafana/datasources"
mkdir -p "$PILOT_DIR/monitoring/prometheus"

# Install Node.js dependencies for load testing
log "${BLUE}📦 Installing Node.js Dependencies${NC}"
cd "$PILOT_DIR/scripts"
if [ ! -f package.json ]; then
    cat > package.json << EOF
{
  "name": "rpc-pilot-testing",
  "version": "1.0.0",
  "description": "RPC Pilot Load Testing Tools",
  "main": "rpc-load-test.js",
  "scripts": {
    "test": "node rpc-load-test.js"
  },
  "dependencies": {
    "axios": "^1.6.0"
  }
}
EOF
fi

npm install

# Make scripts executable
chmod +x "$PILOT_DIR/scripts/pilot-performance-test.sh"
chmod +x "$PILOT_DIR/scripts/rpc-load-test.js"
chmod +x "$PILOT_DIR/scripts/pilot-quickstart.sh"

# Create Prometheus configuration
log "${BLUE}⚙️ Creating Monitoring Configuration${NC}"
cat > "$PILOT_DIR/monitoring/prometheus/prometheus.yml" << EOF
global:
  scrape_interval: 15s
  evaluation_interval: 15s

rule_files:
  # - "first_rules.yml"
  # - "second_rules.yml"

scrape_configs:
  - job_name: 'prometheus'
    static_configs:
      - targets: ['localhost:9090']

  - job_name: 'gateway-service-rest'
    static_configs:
      - targets: ['gateway-service-rest:8000']
    metrics_path: '/metrics'
    scrape_interval: 5s

  - job_name: 'gateway-service-rpc'
    static_configs:
      - targets: ['gateway-service-rpc:8000']
    metrics_path: '/metrics'
    scrape_interval: 5s

  - job_name: 'auth-service-rest'
    static_configs:
      - targets: ['auth-service-rest:8000']
    metrics_path: '/metrics'
    scrape_interval: 5s

  - job_name: 'auth-service-rpc'
    static_configs:
      - targets: ['auth-service-rpc:8000']
    metrics_path: '/metrics'
    scrape_interval: 5s
EOF

# Create Grafana datasource configuration
cat > "$PILOT_DIR/monitoring/grafana/datasources/prometheus.yml" << EOF
apiVersion: 1

datasources:
  - name: Prometheus
    type: prometheus
    access: proxy
    url: http://prometheus:9090
    isDefault: true
    editable: true
EOF

# Start the pilot environment
log "${BLUE}🐳 Starting Docker Environment${NC}"
cd "$PILOT_DIR"
docker-compose -f "$DOCKER_COMPOSE_FILE" down --remove-orphans
docker-compose -f "$DOCKER_COMPOSE_FILE" up -d

# Wait for services to be ready
log "${BLUE}⏳ Waiting for Services to Start${NC}"
sleep 30

# Health check function
check_service() {
    local name="$1"
    local url="$2"
    local max_attempts=30
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if curl -s "$url" > /dev/null 2>&1; then
            log "${GREEN}✅ $name is ready${NC}"
            return 0
        fi
        
        log "${YELLOW}⏳ Waiting for $name... (attempt $attempt/$max_attempts)${NC}"
        sleep 5
        attempt=$((attempt + 1))
    done
    
    log "${RED}❌ $name failed to start after $max_attempts attempts${NC}"
    return 1
}

# Check all services
log "${BLUE}🔍 Checking Service Health${NC}"
check_service "MySQL" "http://localhost:3306" || true
check_service "Redis" "http://localhost:6379" || true
check_service "Shared Service REST" "http://localhost:8010/api/health"
check_service "Shared Service RPC" "http://localhost:6010"
check_service "Auth Service REST" "http://localhost:8011/api/health"
check_service "Auth Service RPC" "http://localhost:6011"
check_service "Grafana" "http://localhost:9000"
check_service "Prometheus" "http://localhost:9090"

# Display service status
log "${BLUE}📊 Service Status Dashboard${NC}"
echo -e "${BLUE}================================${NC}"
echo -e "${GREEN}✅ Shared Service REST:${NC} http://localhost:8010"
echo -e "${GREEN}✅ Shared Service RPC:${NC}  http://localhost:6010"
echo -e "${GREEN}✅ Auth Service REST:${NC}    http://localhost:8011"
echo -e "${GREEN}✅ Auth Service RPC:${NC}     http://localhost:6011"
echo -e "${GREEN}✅ Grafana Dashboard:${NC}    http://localhost:9000 (admin/pilot123)"
echo -e "${GREEN}✅ Prometheus:${NC}           http://localhost:9090"
echo -e "${GREEN}✅ MySQL:${NC}                localhost:3306 (pilot_user/pilot123)"
echo -e "${GREEN}✅ Redis:${NC}                localhost:6379"

# Run initial performance test
log "${BLUE}🧪 Running Initial Performance Test${NC}"
echo -e "${YELLOW}This will take approximately 5-10 minutes...${NC}"

cd "$PILOT_DIR/scripts"
if ./pilot-performance-test.sh; then
    log "${GREEN}✅ Initial performance test completed successfully${NC}"
else
    log "${YELLOW}⚠️ Performance test encountered some issues, but environment is ready${NC}"
fi

# Display next steps
echo -e "\n${BLUE}🎯 Next Steps:${NC}"
echo -e "${BLUE}==============${NC}"
echo -e "1. ${GREEN}View Grafana Dashboard:${NC} http://localhost:9000"
echo -e "   - Username: admin"
echo -e "   - Password: pilot123"
echo -e ""
echo -e "2. ${GREEN}Test RPC Endpoints:${NC}"
echo -e "   curl -X POST http://localhost:6010 \\"
echo -e "     -H 'Content-Type: application/json' \\"
echo -e "     -d '{\"jsonrpc\":\"2.0\",\"method\":\"Health@ping\",\"id\":1}'"
echo -e ""
echo -e "3. ${GREEN}Run Custom Load Tests:${NC}"
echo -e "   cd deployment/scripts"
echo -e "   ./pilot-performance-test.sh"
echo -e ""
echo -e "4. ${GREEN}View Logs:${NC}"
echo -e "   docker-compose -f deployment/docker/docker-compose.pilot.yml logs -f"
echo -e ""
echo -e "5. ${GREEN}Stop Environment:${NC}"
echo -e "   docker-compose -f deployment/docker/docker-compose.pilot.yml down"

log "${GREEN}🚀 RPC Pilot Environment is Ready!${NC}"
log "${GREEN}📊 Check the performance results and start developing your RPC procedures.${NC}"
