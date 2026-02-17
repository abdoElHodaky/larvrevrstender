# Shared Multi-Cloud Configuration

This directory contains shared configurations, templates, and utilities that work across all deployment targets (Docker, Azure, OpenStack).

## Purpose

The shared directory eliminates configuration duplication and ensures consistency across different cloud providers by providing:

- Common configuration templates
- Shared service definitions
- Universal monitoring configurations
- Cross-platform deployment scripts
- Unified secrets management

## Directory Structure

```
shared/
├── configs/           # Common configuration templates
│   ├── base.env       # Base environment variables
│   ├── services.yaml  # Service definitions
│   ├── databases.yaml # Database schemas and configs
│   └── redis.yaml     # Redis configuration
├── templates/         # Jinja2/Helm templates
│   ├── service.yaml.j2
│   ├── database.yaml.j2
│   └── ingress.yaml.j2
├── scripts/           # Cross-platform scripts
│   ├── config-generator.py  # Generate cloud-specific configs
│   ├── validate-config.sh   # Configuration validation
│   ├── setup-secrets.sh     # Secrets management
│   └── health-check.sh      # Service health checks
├── monitoring/        # Shared monitoring configurations
│   ├── prometheus.yml
│   ├── grafana-dashboards/
│   │   ├── microservices.json
│   │   ├── infrastructure.json
│   │   └── business-metrics.json
│   └── jaeger-config/
├── service-mesh/      # Service mesh configurations
│   ├── istio/
│   └── linkerd/
├── networking/        # Shared networking templates
│   ├── ingress-rules.yaml
│   └── network-policies.yaml
└── disaster-recovery/ # Backup and recovery procedures
    ├── backup-scripts/
    └── recovery-procedures.md
```

## Configuration Management

### Environment Variables

The `configs/base.env` file contains common environment variables that are used across all deployments:

```bash
# Application Configuration
APP_NAME=reverse-tender
APP_ENV=production
APP_DEBUG=false

# Database Configuration
DB_CONNECTION=pgsql
DB_PORT=5432

# Redis Configuration
REDIS_PORT=6379

# Service Ports
API_GATEWAY_PORT=8000
AUTH_SERVICE_PORT=8001
BIDDING_SERVICE_PORT=8002
# ... etc
```

### Service Definitions

The `configs/services.yaml` file defines all microservices with their common properties:

```yaml
services:
  api-gateway:
    port: 8000
    replicas: 3
    resources:
      requests:
        cpu: 100m
        memory: 128Mi
      limits:
        cpu: 500m
        memory: 512Mi
  auth-service:
    port: 8001
    replicas: 2
    resources:
      requests:
        cpu: 50m
        memory: 64Mi
      limits:
        cpu: 200m
        memory: 256Mi
  # ... other services
```

## Template System

### Jinja2 Templates

Templates use Jinja2 syntax for generating cloud-specific configurations:

```yaml
# templates/service.yaml.j2
apiVersion: apps/v1
kind: Deployment
metadata:
  name: {{ service.name }}
  namespace: {{ namespace }}
spec:
  replicas: {{ service.replicas }}
  selector:
    matchLabels:
      app: {{ service.name }}
  template:
    metadata:
      labels:
        app: {{ service.name }}
    spec:
      containers:
      - name: {{ service.name }}
        image: {{ registry }}/{{ service.name }}:{{ version }}
        ports:
        - containerPort: {{ service.port }}
        env:
        {% for key, value in service.env.items() %}
        - name: {{ key }}
          value: "{{ value }}"
        {% endfor %}
```

### Configuration Generation

Use the `config-generator.py` script to generate cloud-specific configurations:

```bash
# Generate Azure configurations
python scripts/config-generator.py --target azure --env production

# Generate OpenStack configurations
python scripts/config-generator.py --target openstack --env staging

# Generate all configurations
python scripts/config-generator.py --target all --env dev
```

## Monitoring Configuration

### Prometheus Configuration

The shared Prometheus configuration monitors all services regardless of deployment target:

```yaml
# monitoring/prometheus.yml
global:
  scrape_interval: 15s
  evaluation_interval: 15s

scrape_configs:
  - job_name: 'microservices'
    static_configs:
      - targets:
        - 'api-gateway:8000'
        - 'auth-service:8001'
        - 'bidding-service:8002'
        # ... other services
    metrics_path: /metrics
    scrape_interval: 10s

  - job_name: 'infrastructure'
    static_configs:
      - targets:
        - 'postgresql:9187'
        - 'redis:9121'
```

### Grafana Dashboards

Pre-configured dashboards for monitoring:

- **Microservices Dashboard**: Service-level metrics (response time, error rate, throughput)
- **Infrastructure Dashboard**: System metrics (CPU, memory, disk, network)
- **Business Metrics Dashboard**: Application-specific metrics (orders, bids, payments)

## Service Mesh Configuration

### Istio Configuration

For Kubernetes deployments, Istio service mesh configuration is provided:

```yaml
# service-mesh/istio/virtual-service.yaml
apiVersion: networking.istio.io/v1beta1
kind: VirtualService
metadata:
  name: microservices-vs
spec:
  hosts:
  - api.reverse-tender.com
  http:
  - match:
    - uri:
        prefix: /auth
    route:
    - destination:
        host: auth-service
        port:
          number: 8001
  - match:
    - uri:
        prefix: /bidding
    route:
    - destination:
        host: bidding-service
        port:
          number: 8002
```

## Secrets Management

### Setup Script

The `setup-secrets.sh` script configures secrets across different platforms:

```bash
#!/bin/bash
# scripts/setup-secrets.sh

PLATFORM=$1
ENVIRONMENT=$2

case $PLATFORM in
  "azure")
    # Use Azure Key Vault
    az keyvault secret set --vault-name "rt-kv-$ENVIRONMENT" \
      --name "database-password" --value "$DB_PASSWORD"
    ;;
  "openstack")
    # Use Barbican or Kubernetes secrets
    kubectl create secret generic database-credentials \
      --from-literal=password="$DB_PASSWORD"
    ;;
  "docker")
    # Use Docker secrets or environment files
    echo "DB_PASSWORD=$DB_PASSWORD" >> .env.secrets
    ;;
esac
```

## Validation and Testing

### Configuration Validation

The `validate-config.sh` script validates configurations before deployment:

```bash
#!/bin/bash
# scripts/validate-config.sh

echo "Validating shared configurations..."

# Validate YAML syntax
for file in configs/*.yaml; do
  if ! yamllint "$file"; then
    echo "YAML validation failed for $file"
    exit 1
  fi
done

# Validate environment variables
if ! grep -q "APP_NAME" configs/base.env; then
  echo "Missing required APP_NAME in base.env"
  exit 1
fi

# Validate service definitions
python -c "
import yaml
with open('configs/services.yaml') as f:
    services = yaml.safe_load(f)
    required_services = ['api-gateway', 'auth-service', 'bidding-service']
    for service in required_services:
        if service not in services['services']:
            print(f'Missing required service: {service}')
            exit(1)
"

echo "All configurations are valid!"
```

## Usage Examples

### Generate Azure Kubernetes Manifests

```bash
cd deployment/shared
python scripts/config-generator.py \
  --target azure \
  --env production \
  --output ../azure/kubernetes/overlays/prod/
```

### Deploy Monitoring Stack

```bash
# Deploy Prometheus
kubectl apply -f monitoring/prometheus.yml

# Import Grafana dashboards
for dashboard in monitoring/grafana-dashboards/*.json; do
  curl -X POST \
    -H "Content-Type: application/json" \
    -d @"$dashboard" \
    http://grafana:3000/api/dashboards/db
done
```

### Validate All Configurations

```bash
cd deployment/shared
./scripts/validate-config.sh
```

This shared configuration system ensures consistency across all deployment targets while allowing for platform-specific customizations when needed.
