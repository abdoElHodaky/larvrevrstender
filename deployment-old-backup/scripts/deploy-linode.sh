#!/bin/bash

# Linode Deployment Script for Reverse Tender Platform
set -e

echo "🚀 Starting Linode deployment..."

# Configuration
ENVIRONMENT=${ENVIRONMENT:-production}
REGION=${REGION:-eu-west}
NODE_COUNT=${NODE_COUNT:-3}
DOMAIN_NAME=${DOMAIN_NAME:-reverse-tender.com}

# Check required environment variables
if [ -z "$LINODE_TOKEN" ]; then
    echo "❌ Error: LINODE_TOKEN environment variable is required"
    exit 1
fi

echo "📋 Deployment Configuration:"
echo "  Environment: $ENVIRONMENT"
echo "  Region: $REGION"
echo "  Node Count: $NODE_COUNT"
echo "  Domain: $DOMAIN_NAME"

# Step 1: Initialize Terraform
echo "🔧 Initializing Terraform..."
cd deployment/terraform
terraform init

# Step 2: Create terraform.tfvars
echo "📝 Creating terraform.tfvars..."
cat > terraform.tfvars << EOF
cloud_provider = "linode"
environment = "$ENVIRONMENT"
region = "$REGION"
node_count = $NODE_COUNT
domain_name = "$DOMAIN_NAME"
linode_token = "$LINODE_TOKEN"
EOF

# Step 3: Plan infrastructure
echo "📊 Planning infrastructure..."
terraform plan -var-file="terraform.tfvars"

# Step 4: Apply infrastructure
echo "🏗️ Creating infrastructure..."
terraform apply -var-file="terraform.tfvars" -auto-approve

# Step 5: Get cluster credentials
echo "🔑 Configuring kubectl..."
CLUSTER_ID=$(terraform output -raw cluster_endpoint | cut -d'/' -f5)
linode-cli lke kubeconfig-view $CLUSTER_ID --text --no-headers | base64 -d > ~/.kube/config-linode
export KUBECONFIG=~/.kube/config-linode

# Step 6: Create Kubernetes secrets
echo "🔐 Creating Kubernetes secrets..."
kubectl create namespace reverse-tender --dry-run=client -o yaml | kubectl apply -f -

# Database secret
DB_HOST=$(terraform output -raw database_host)
kubectl create secret generic database-secret \
  --from-literal=host="$DB_HOST" \
  --from-literal=username="root" \
  --from-literal=password="$DB_PASSWORD" \
  --namespace=reverse-tender \
  --dry-run=client -o yaml | kubectl apply -f -

# Redis secret (using in-cluster Redis for Linode)
kubectl create secret generic redis-secret \
  --from-literal=host="redis-service.reverse-tender.svc.cluster.local" \
  --namespace=reverse-tender \
  --dry-run=client -o yaml | kubectl apply -f -

# Payment secrets
kubectl create secret generic payment-secret \
  --from-literal=stripe-key="$STRIPE_KEY" \
  --from-literal=stripe-secret="$STRIPE_SECRET" \
  --namespace=reverse-tender \
  --dry-run=client -o yaml | kubectl apply -f -

# Pusher secrets
kubectl create secret generic pusher-secret \
  --from-literal=app-key="$PUSHER_APP_KEY" \
  --from-literal=app-secret="$PUSHER_APP_SECRET" \
  --namespace=reverse-tender \
  --dry-run=client -o yaml | kubectl apply -f -

# Step 7: Deploy Redis (for Linode)
echo "🔴 Deploying Redis..."
kubectl apply -f - << EOF
apiVersion: apps/v1
kind: Deployment
metadata:
  name: redis
  namespace: reverse-tender
spec:
  replicas: 1
  selector:
    matchLabels:
      app: redis
  template:
    metadata:
      labels:
        app: redis
    spec:
      containers:
      - name: redis
        image: redis:7-alpine
        ports:
        - containerPort: 6379
        resources:
          requests:
            memory: "256Mi"
            cpu: "250m"
          limits:
            memory: "512Mi"
            cpu: "500m"
---
apiVersion: v1
kind: Service
metadata:
  name: redis-service
  namespace: reverse-tender
spec:
  selector:
    app: redis
  ports:
  - port: 6379
    targetPort: 6379
EOF

# Step 8: Deploy Kubernetes manifests
echo "🚀 Deploying services..."
cd ../kubernetes

kubectl apply -f namespace.yaml
kubectl apply -f configmap.yaml
kubectl apply -f deployments.yaml
kubectl apply -f services.yaml
kubectl apply -f nginx-gateway.yaml

# Step 9: Wait for deployments
echo "⏳ Waiting for deployments to be ready..."
kubectl wait --for=condition=available --timeout=300s deployment --all -n reverse-tender

# Step 10: Create NodeBalancer (Linode Load Balancer)
echo "🔄 Setting up NodeBalancer..."
# Note: This would typically be done through Linode API or manually
# For now, we'll use the nginx-gateway service as LoadBalancer type

# Step 11: Get service information
echo "📋 Deployment Summary:"
echo "  Cluster Endpoint: $(terraform output cluster_endpoint)"
echo "  Database Host: $(terraform output database_host)"

# Step 12: Display service URLs
echo "🌐 Service URLs:"
LB_IP=$(kubectl get service nginx-gateway -n reverse-tender -o jsonpath='{.status.loadBalancer.ingress[0].ip}')
if [ -n "$LB_IP" ]; then
    echo "  API Gateway: http://$LB_IP"
    echo "  Health Check: http://$LB_IP/health"
else
    echo "  Load Balancer IP is being assigned..."
fi

echo "✅ Linode deployment completed successfully!"
echo "🔍 Monitor deployment: kubectl get pods -n reverse-tender"

