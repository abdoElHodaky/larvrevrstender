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
DOCKER_COMPOSE_FILE="$PILOT_DIR/docker/docker-compose.pilot-simple.yml"

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

# Check for Docker Compose (both legacy and modern versions)
DOCKER_COMPOSE_CMD=""
if command_exists docker-compose; then
    DOCKER_COMPOSE_CMD="docker-compose"
elif docker compose version >/dev/null 2>&1; then
    DOCKER_COMPOSE_CMD="docker compose"
else
    log "${RED}❌ Docker Compose is not installed. Please install Docker Compose first.${NC}"
    exit 1
fi

log "${GREEN}✅ Docker Compose found: $DOCKER_COMPOSE_CMD${NC}"

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

# Skip monitoring configuration for simplified setup
log "${BLUE}⚙️ Simplified Setup - Skipping Monitoring Configuration${NC}"

# Start the pilot environment with staged approach
log "${BLUE}🐳 Starting Docker Environment (Staged Approach)${NC}"
cd "$PILOT_DIR"
$DOCKER_COMPOSE_CMD -f "$DOCKER_COMPOSE_FILE" down --remove-orphans

# Stage 1: Start infrastructure services first
log "${BLUE}📊 Stage 1: Starting Infrastructure Services${NC}"
$DOCKER_COMPOSE_CMD -f "$DOCKER_COMPOSE_FILE" up -d mysql-dev redis-dev
log "${BLUE}⏳ Waiting for infrastructure services...${NC}"
sleep 30

# Stage 2: Start application services
log "${BLUE}🚀 Stage 2: Starting Application Services${NC}"
$DOCKER_COMPOSE_CMD -f "$DOCKER_COMPOSE_FILE" up -d gateway-service-rest auth-service-rest
log "${BLUE}⏳ Waiting for application services...${NC}"
sleep 45

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

# Check simplified services
log "${BLUE}🔍 Checking Service Health${NC}"
check_service "Gateway Service REST" "http://localhost:8010/api/health"
check_service "Auth Service REST" "http://localhost:8011/api/health"

# Display service status
log "${BLUE}📊 Service Status Dashboard${NC}"
echo -e "${BLUE}================================${NC}"
echo -e "${GREEN}✅ Gateway Service REST:${NC} http://localhost:8010"
echo -e "${GREEN}✅ Auth Service REST:${NC}    http://localhost:8011"
echo -e "${GREEN}✅ MySQL Database:${NC}       localhost:3306 (pilot_user/pilot123)"
echo -e "${GREEN}✅ Redis Cache:${NC}          localhost:6379"

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
echo -e "1. ${GREEN}Test Gateway Service:${NC}"
echo -e "   curl http://localhost:8010/api/health"
echo -e ""
echo -e "2. ${GREEN}Test Auth Service:${NC}"
echo -e "   curl http://localhost:8011/api/health"
echo -e ""
echo -e "3. ${GREEN}Run Performance Tests:${NC}"
echo -e "   cd deployment/scripts"
echo -e "   ./pilot-performance-test.sh"
echo -e ""
echo -e "4. ${GREEN}View Logs:${NC}"
echo -e "   $DOCKER_COMPOSE_CMD -f deployment/docker/docker-compose.pilot-simple.yml logs -f"
echo -e ""
echo -e "5. ${GREEN}Stop Environment:${NC}"
echo -e "   $DOCKER_COMPOSE_CMD -f deployment/docker/docker-compose.pilot-simple.yml down"

log "${GREEN}🚀 RPC Pilot Environment is Ready!${NC}"
log "${GREEN}📊 Check the performance results and start developing your RPC procedures.${NC}"
