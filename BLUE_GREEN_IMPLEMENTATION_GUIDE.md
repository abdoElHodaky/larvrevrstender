# 🚀 Blue-Green Deployment Implementation Guide
## Laravel Reverse Tender Platform

### 🎯 **Implementation Strategy**

This guide provides step-by-step instructions for implementing blue-green deployment on the Laravel Reverse Tender Platform, building on the **87/100 compatibility score** from our analysis.

---

## 📋 **Prerequisites Checklist**

### **Infrastructure Requirements**
- ✅ Kubernetes cluster with ingress controller
- ✅ Load balancer with programmable routing
- ✅ Container registry for image management
- ✅ Monitoring stack (Prometheus, Grafana)
- ✅ Redis cluster for session management

### **Application Requirements**
- ✅ Stateless application design (already implemented)
- ✅ Health check endpoints (already implemented)
- ✅ Database connection pooling (already implemented)
- ⚠️ Graceful shutdown handling (needs implementation)
- ⚠️ Blue-green automation scripts (needs implementation)

---

## 🔧 **Phase 1: Foundation Setup**

### **Step 1: Environment Color Configuration**

#### **1.1 Add Environment Variables**
```yaml
# deployment/k8s/base/common-config.yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: blue-green-config
data:
  ENVIRONMENT_COLOR: "blue"  # or "green"
  DEPLOYMENT_TIMESTAMP: "2024-02-20T13:00:00Z"
  PREVIOUS_COLOR: "green"    # for rollback reference
```

#### **1.2 Update Service Deployments**
```yaml
# deployment/k8s/base/deployments.yaml
spec:
  template:
    metadata:
      labels:
        app: api-gateway
        environment-color: blue  # Dynamic based on deployment
    spec:
      containers:
      - name: api-gateway
        env:
        - name: ENVIRONMENT_COLOR
          valueFrom:
            configMapKeyRef:
              name: blue-green-config
              key: ENVIRONMENT_COLOR
```

### **Step 2: Graceful Shutdown Implementation**

#### **2.1 Laravel Octane Configuration**
```php
// config/octane.php
'graceful_shutdown' => [
    'enabled' => true,
    'timeout' => 30, // seconds
    'max_requests_in_flight' => 100,
    'drain_connections' => true,
],

'health_checks' => [
    'shutdown_in_progress' => false,
    'max_memory_usage' => '512M',
    'max_request_duration' => 30,
],
```

#### **2.2 Signal Handling**
```php
// app/Console/Commands/GracefulShutdown.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class GracefulShutdown extends Command
{
    protected $signature = 'octane:graceful-shutdown';
    
    public function handle()
    {
        // Mark service as shutting down
        Cache::put('shutdown_in_progress', true, 60);
        
        // Wait for in-flight requests to complete
        $this->info('Waiting for requests to complete...');
        sleep(10);
        
        // Stop accepting new requests
        $this->info('Stopping Octane server...');
        $this->call('octane:stop');
        
        return 0;
    }
}
```

#### **2.3 Health Check Updates**
```php
// routes/web.php
Route::get('/health', function () {
    $shutdownInProgress = Cache::get('shutdown_in_progress', false);
    
    if ($shutdownInProgress) {
        return response()->json([
            'status' => 'shutting_down',
            'message' => 'Service is gracefully shutting down'
        ], 503);
    }
    
    return response()->json([
        'status' => 'healthy',
        'environment_color' => env('ENVIRONMENT_COLOR', 'unknown'),
        'timestamp' => now()->toISOString()
    ]);
});

Route::get('/ready', function () {
    $shutdownInProgress = Cache::get('shutdown_in_progress', false);
    
    return response()->json([
        'status' => $shutdownInProgress ? 'not_ready' : 'ready',
        'environment_color' => env('ENVIRONMENT_COLOR', 'unknown')
    ], $shutdownInProgress ? 503 : 200);
});
```

### **Step 3: Service Discovery Validation**

#### **3.1 Cross-Service Health Checks**
```php
// app/Services/ServiceDiscoveryHealth.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ServiceDiscoveryHealth
{
    private array $services = [
        'auth-service' => 'http://auth-service:8001',
        'user-service' => 'http://user-service:8002',
        'payment-service' => 'http://payment-service:8005',
        // ... other services
    ];
    
    public function checkAllServices(): array
    {
        $results = [];
        
        foreach ($this->services as $name => $url) {
            $results[$name] = $this->checkService($name, $url);
        }
        
        return $results;
    }
    
    private function checkService(string $name, string $url): array
    {
        try {
            $response = Http::timeout(5)->get($url . '/health');
            
            return [
                'status' => $response->successful() ? 'healthy' : 'unhealthy',
                'response_time' => $response->transferStats?->getTransferTime() ?? 0,
                'environment_color' => $response->json('environment_color', 'unknown')
            ];
        } catch (\Exception $e) {
            Log::warning("Service health check failed for {$name}: " . $e->getMessage());
            
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'response_time' => null
            ];
        }
    }
}
```

---

## 🔄 **Phase 2: Blue-Green Automation**

### **Step 1: Traffic Management Scripts**

#### **1.1 Blue-Green Deployment Script**
```bash
#!/bin/bash
# deployment/scripts/blue-green-deploy.sh

set -euo pipefail

# Configuration
NAMESPACE="reverse-tender"
CURRENT_COLOR=""
NEW_COLOR=""
IMAGE_TAG="${1:-latest}"
HEALTH_CHECK_TIMEOUT=300
ROLLBACK_ON_FAILURE=true

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

success() {
    echo -e "${GREEN}[SUCCESS] $1${NC}"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}"
}

# Determine current and new colors
determine_colors() {
    log "Determining current environment color..."
    
    CURRENT_COLOR=$(kubectl get configmap blue-green-config -n $NAMESPACE -o jsonpath='{.data.ENVIRONMENT_COLOR}' 2>/dev/null || echo "blue")
    
    if [ "$CURRENT_COLOR" = "blue" ]; then
        NEW_COLOR="green"
    else
        NEW_COLOR="blue"
    fi
    
    log "Current color: $CURRENT_COLOR, New color: $NEW_COLOR"
}

# Deploy to new environment
deploy_new_environment() {
    log "Deploying to $NEW_COLOR environment..."
    
    # Update ConfigMap
    kubectl patch configmap blue-green-config -n $NAMESPACE --patch "
    data:
      ENVIRONMENT_COLOR: \"$NEW_COLOR\"
      DEPLOYMENT_TIMESTAMP: \"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"
      PREVIOUS_COLOR: \"$CURRENT_COLOR\"
    "
    
    # Update deployments with new color and image
    kubectl patch deployment api-gateway -n $NAMESPACE --patch "
    spec:
      template:
        metadata:
          labels:
            environment-color: \"$NEW_COLOR\"
        spec:
          containers:
          - name: api-gateway
            image: reversetender/api-gateway:$IMAGE_TAG
    "
    
    # Wait for rollout
    kubectl rollout status deployment/api-gateway -n $NAMESPACE --timeout=300s
    
    success "Deployment to $NEW_COLOR environment completed"
}

# Health check validation
validate_new_environment() {
    log "Validating $NEW_COLOR environment health..."
    
    local attempts=0
    local max_attempts=30
    local healthy_checks=0
    local required_healthy_checks=3
    
    while [ $attempts -lt $max_attempts ]; do
        if check_environment_health "$NEW_COLOR"; then
            ((healthy_checks++))
            log "Health check passed ($healthy_checks/$required_healthy_checks)"
            
            if [ $healthy_checks -ge $required_healthy_checks ]; then
                success "$NEW_COLOR environment is healthy and ready"
                return 0
            fi
        else
            healthy_checks=0
            log "Health check failed, resetting counter"
        fi
        
        ((attempts++))
        sleep 10
    done
    
    error "$NEW_COLOR environment failed health validation"
    return 1
}

# Switch traffic to new environment
switch_traffic() {
    log "Switching traffic to $NEW_COLOR environment..."
    
    # Update ingress to point to new environment
    kubectl patch ingress reverse-tender-ingress -n $NAMESPACE --patch "
    spec:
      rules:
      - host: api.reversetender.com
        http:
          paths:
          - path: /
            pathType: Prefix
            backend:
              service:
                name: api-gateway-$NEW_COLOR
                port:
                  number: 80
    "
    
    # Wait for ingress update
    sleep 30
    
    # Validate traffic switch
    if validate_traffic_switch; then
        success "Traffic successfully switched to $NEW_COLOR environment"
        return 0
    else
        error "Traffic switch validation failed"
        return 1
    fi
}

# Main deployment function
main() {
    log "Starting blue-green deployment..."
    
    determine_colors
    
    if deploy_new_environment && validate_new_environment; then
        if switch_traffic; then
            success "Blue-green deployment completed successfully!"
            cleanup_old_environment
        else
            if [ "$ROLLBACK_ON_FAILURE" = true ]; then
                error "Traffic switch failed, initiating rollback..."
                rollback_deployment
            fi
        fi
    else
        error "Deployment or validation failed"
        if [ "$ROLLBACK_ON_FAILURE" = true ]; then
            rollback_deployment
        fi
        exit 1
    fi
}

# Execute main function
main "$@"
```

#### **1.2 Rollback Script**
```bash
#!/bin/bash
# deployment/scripts/blue-green-rollback.sh

set -euo pipefail

NAMESPACE="reverse-tender"

rollback_deployment() {
    log "Initiating emergency rollback..."
    
    local previous_color=$(kubectl get configmap blue-green-config -n $NAMESPACE -o jsonpath='{.data.PREVIOUS_COLOR}')
    
    if [ -z "$previous_color" ]; then
        error "No previous environment color found for rollback"
        exit 1
    fi
    
    log "Rolling back to $previous_color environment..."
    
    # Switch ingress back
    kubectl patch ingress reverse-tender-ingress -n $NAMESPACE --patch "
    spec:
      rules:
      - host: api.reversetender.com
        http:
          paths:
          - path: /
            pathType: Prefix
            backend:
              service:
                name: api-gateway-$previous_color
                port:
                  number: 80
    "
    
    # Update ConfigMap
    kubectl patch configmap blue-green-config -n $NAMESPACE --patch "
    data:
      ENVIRONMENT_COLOR: \"$previous_color\"
      ROLLBACK_TIMESTAMP: \"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"
    "
    
    success "Rollback to $previous_color environment completed"
}

rollback_deployment
```

### **Step 2: Database Migration Coordination**

#### **2.1 Zero-Downtime Migration Strategy**
```php
// database/migrations/2024_02_20_000000_create_blue_green_migrations_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('blue_green_migrations', function (Blueprint $table) {
            $table->id();
            $table->string('migration_name');
            $table->string('environment_color');
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'rolled_back']);
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['environment_color', 'status']);
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('blue_green_migrations');
    }
};
```

#### **2.2 Migration Coordination Service**
```php
// app/Services/BlueGreenMigrationService.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BlueGreenMigrationService
{
    public function runMigrations(string $environmentColor): bool
    {
        $pendingMigrations = $this->getPendingMigrations();
        
        if (empty($pendingMigrations)) {
            Log::info("No pending migrations for {$environmentColor} environment");
            return true;
        }
        
        DB::beginTransaction();
        
        try {
            foreach ($pendingMigrations as $migration) {
                $this->runSingleMigration($migration, $environmentColor);
            }
            
            DB::commit();
            Log::info("All migrations completed successfully for {$environmentColor}");
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Migration failed for {$environmentColor}: " . $e->getMessage());
            return false;
        }
    }
    
    private function runSingleMigration(string $migration, string $environmentColor): void
    {
        // Record migration start
        DB::table('blue_green_migrations')->insert([
            'migration_name' => $migration,
            'environment_color' => $environmentColor,
            'status' => 'running',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Run the actual migration
        \Artisan::call('migrate', [
            '--path' => "database/migrations/{$migration}.php",
            '--force' => true,
        ]);
        
        // Update status to completed
        DB::table('blue_green_migrations')
            ->where('migration_name', $migration)
            ->where('environment_color', $environmentColor)
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
```

---

## 📊 **Phase 3: Monitoring & Validation**

### **Step 1: Deployment Metrics**

#### **1.1 Prometheus Metrics**
```php
// app/Http/Middleware/BlueGreenMetrics.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BlueGreenMetrics
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $environmentColor = env('ENVIRONMENT_COLOR', 'unknown');
        
        $response = $next($request);
        
        $duration = microtime(true) - $startTime;
        
        // Record metrics
        $this->recordMetrics([
            'environment_color' => $environmentColor,
            'response_time' => $duration,
            'status_code' => $response->getStatusCode(),
            'endpoint' => $request->path(),
        ]);
        
        return $response;
    }
    
    private function recordMetrics(array $metrics): void
    {
        // Store metrics for Prometheus scraping
        $key = "metrics:blue_green:" . $metrics['environment_color'];
        $current = Cache::get($key, []);
        
        $current[] = [
            'timestamp' => time(),
            'response_time' => $metrics['response_time'],
            'status_code' => $metrics['status_code'],
            'endpoint' => $metrics['endpoint'],
        ];
        
        // Keep only last 1000 entries
        if (count($current) > 1000) {
            $current = array_slice($current, -1000);
        }
        
        Cache::put($key, $current, 3600);
    }
}
```

#### **1.2 Grafana Dashboard Configuration**
```json
{
  "dashboard": {
    "title": "Blue-Green Deployment Monitoring",
    "panels": [
      {
        "title": "Environment Health Status",
        "type": "stat",
        "targets": [
          {
            "expr": "up{job=\"reverse-tender\", environment_color=\"blue\"}",
            "legendFormat": "Blue Environment"
          },
          {
            "expr": "up{job=\"reverse-tender\", environment_color=\"green\"}",
            "legendFormat": "Green Environment"
          }
        ]
      },
      {
        "title": "Response Time by Environment",
        "type": "graph",
        "targets": [
          {
            "expr": "histogram_quantile(0.95, rate(http_request_duration_seconds_bucket{environment_color=\"blue\"}[5m]))",
            "legendFormat": "Blue P95"
          },
          {
            "expr": "histogram_quantile(0.95, rate(http_request_duration_seconds_bucket{environment_color=\"green\"}[5m]))",
            "legendFormat": "Green P95"
          }
        ]
      },
      {
        "title": "Traffic Distribution",
        "type": "piechart",
        "targets": [
          {
            "expr": "sum(rate(http_requests_total{environment_color=\"blue\"}[5m]))",
            "legendFormat": "Blue"
          },
          {
            "expr": "sum(rate(http_requests_total{environment_color=\"green\"}[5m]))",
            "legendFormat": "Green"
          }
        ]
      }
    ]
  }
}
```

### **Step 2: Automated Validation**

#### **2.1 Deployment Validation Script**
```bash
#!/bin/bash
# deployment/scripts/validate-deployment.sh

validate_deployment() {
    local environment_color=$1
    local validation_duration=300  # 5 minutes
    local start_time=$(date +%s)
    local end_time=$((start_time + validation_duration))
    
    log "Starting deployment validation for $environment_color environment..."
    
    while [ $(date +%s) -lt $end_time ]; do
        # Check health endpoints
        if ! check_health_endpoints "$environment_color"; then
            error "Health endpoint validation failed"
            return 1
        fi
        
        # Check response times
        if ! check_response_times "$environment_color"; then
            error "Response time validation failed"
            return 1
        fi
        
        # Check error rates
        if ! check_error_rates "$environment_color"; then
            error "Error rate validation failed"
            return 1
        fi
        
        sleep 30
    done
    
    success "Deployment validation completed successfully"
    return 0
}

check_health_endpoints() {
    local environment_color=$1
    local services=("api-gateway" "auth-service" "user-service" "payment-service")
    
    for service in "${services[@]}"; do
        local url="http://${service}-${environment_color}.reverse-tender.svc.cluster.local/health"
        
        if ! curl -s --max-time 10 "$url" | grep -q "healthy"; then
            error "$service health check failed in $environment_color environment"
            return 1
        fi
    done
    
    return 0
}

check_response_times() {
    local environment_color=$1
    local max_response_time=2000  # 2 seconds in milliseconds
    
    local avg_response_time=$(curl -s "http://prometheus:9090/api/v1/query?query=avg(http_request_duration_seconds{environment_color=\"$environment_color\"})" | jq -r '.data.result[0].value[1]')
    
    if (( $(echo "$avg_response_time * 1000 > $max_response_time" | bc -l) )); then
        error "Average response time ($avg_response_time ms) exceeds threshold ($max_response_time ms)"
        return 1
    fi
    
    return 0
}

check_error_rates() {
    local environment_color=$1
    local max_error_rate=0.01  # 1%
    
    local error_rate=$(curl -s "http://prometheus:9090/api/v1/query?query=rate(http_requests_total{environment_color=\"$environment_color\",status=~\"5..\"}[5m])/rate(http_requests_total{environment_color=\"$environment_color\"}[5m])" | jq -r '.data.result[0].value[1]')
    
    if (( $(echo "$error_rate > $max_error_rate" | bc -l) )); then
        error "Error rate ($error_rate) exceeds threshold ($max_error_rate)"
        return 1
    fi
    
    return 0
}
```

---

## 🎯 **Implementation Timeline**

### **Week 1: Foundation**
- [ ] Implement graceful shutdown handling
- [ ] Add environment color configuration
- [ ] Create service discovery health checks
- [ ] Update health endpoints

### **Week 2: Automation**
- [ ] Develop blue-green deployment scripts
- [ ] Implement rollback procedures
- [ ] Create traffic switching automation
- [ ] Add deployment validation

### **Week 3: Database Strategy**
- [ ] Implement migration coordination
- [ ] Create backward compatibility validation
- [ ] Develop zero-downtime migration patterns
- [ ] Add migration rollback capability

### **Week 4: Monitoring**
- [ ] Set up deployment metrics
- [ ] Create Grafana dashboards
- [ ] Implement automated validation
- [ ] Add alerting rules

### **Week 5: Testing**
- [ ] Conduct end-to-end testing
- [ ] Perform failure scenario testing
- [ ] Validate rollback procedures
- [ ] Load testing with traffic switching

### **Week 6: Production Readiness**
- [ ] Create operational runbooks
- [ ] Train operations team
- [ ] Implement monitoring alerts
- [ ] Conduct production deployment

---

## ✅ **Success Criteria**

### **Functional Requirements**
- [ ] Zero-downtime deployments achieved
- [ ] Rollback time < 30 seconds
- [ ] Health validation before traffic switch
- [ ] Database migration coordination
- [ ] Service discovery validation

### **Performance Requirements**
- [ ] Deployment time < 5 minutes
- [ ] Response time impact < 5%
- [ ] Error rate increase < 0.1%
- [ ] Resource utilization < 80%

### **Operational Requirements**
- [ ] Automated deployment process
- [ ] Comprehensive monitoring
- [ ] Clear rollback procedures
- [ ] Incident response playbooks

---

**Next Steps**: Begin with Phase 1 implementation, focusing on graceful shutdown and environment configuration. Each phase builds upon the previous, ensuring a stable and reliable blue-green deployment capability.

