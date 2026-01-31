#!/bin/bash

# DigitalOcean Deployment Script for Reverse Tender Platform
set -e

echo "🚀 Starting DigitalOcean deployment..."

# Configuration
ENVIRONMENT=${ENVIRONMENT:-production}
REGION=${REGION:-fra1}
NODE_COUNT=${NODE_COUNT:-3}
DOMAIN_NAME=${DOMAIN_NAME:-reverse-tender.com}

# Check required environment variables
if [ -z "$DO_TOKEN" ]; then
    echo "❌ Error: DO_TOKEN environment variable is required"
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
cloud_provider = "digitalocean"
environment = "$ENVIRONMENT"
region = "$REGION"
node_count = $NODE_COUNT
domain_name = "$DOMAIN_NAME"
do_token = "$DO_TOKEN"
EOF

# Step 3: Plan infrastructure
echo "📊 Planning infrastructure..."
terraform plan -var-file="terraform.tfvars"

# Step 4: Apply infrastructure
echo "🏗️ Creating infrastructure..."
terraform apply -var-file="terraform.tfvars" -auto-approve

# Step 5: Get cluster credentials
echo "🔑 Configuring kubectl..."
CLUSTER_ID=$(terraform output -raw cluster_endpoint | cut -d'/' -f3)
doctl kubernetes cluster kubeconfig save $CLUSTER_ID

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

# Redis secret
REDIS_HOST=$(terraform output -raw redis_host)
kubectl create secret generic redis-secret \
  --from-literal=host="$REDIS_HOST" \
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

# Step 7: Deploy Kubernetes manifests
echo "🚀 Deploying services..."
cd ../kubernetes

kubectl apply -f namespace.yaml
kubectl apply -f configmap.yaml
kubectl apply -f deployments.yaml
kubectl apply -f services.yaml
kubectl apply -f nginx-gateway.yaml

# Step 8: Wait for deployments
echo "⏳ Waiting for deployments to be ready..."
kubectl wait --for=condition=available --timeout=300s deployment --all -n reverse-tender

# Step 9: Get service information
echo "📋 Deployment Summary:"
echo "  Cluster Endpoint: $(terraform output cluster_endpoint)"
echo "  Database Host: $(terraform output database_host)"
echo "  Redis Host: $(terraform output redis_host)"
echo "  Load Balancer IP: $(terraform output load_balancer_ip)"

# Step 10: Display service URLs
echo "🌐 Service URLs:"
LB_IP=$(kubectl get service nginx-gateway -n reverse-tender -o jsonpath='{.status.loadBalancer.ingress[0].ip}')
if [ -n "$LB_IP" ]; then
    echo "  API Gateway: http://$LB_IP"
    echo "  Health Check: http://$LB_IP/health"
else
    echo "  Load Balancer IP is being assigned..."
fi

echo "✅ DigitalOcean deployment completed successfully!"
echo "🔍 Monitor deployment: kubectl get pods -n reverse-tender"

