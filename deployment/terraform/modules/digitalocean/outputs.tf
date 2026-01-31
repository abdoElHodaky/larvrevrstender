# Outputs for DigitalOcean Module

output "kubernetes_endpoint" {
  description = "Kubernetes cluster endpoint"
  value       = digitalocean_kubernetes_cluster.main.endpoint
}

output "kubernetes_token" {
  description = "Kubernetes cluster token"
  value       = digitalocean_kubernetes_cluster.main.kube_config[0].token
  sensitive   = true
}

output "kubernetes_ca_certificate" {
  description = "Kubernetes cluster CA certificate"
  value       = digitalocean_kubernetes_cluster.main.kube_config[0].cluster_ca_certificate
  sensitive   = true
}

output "kubernetes_config" {
  description = "Complete Kubernetes configuration"
  value = {
    host                   = digitalocean_kubernetes_cluster.main.endpoint
    token                  = digitalocean_kubernetes_cluster.main.kube_config[0].token
    cluster_ca_certificate = digitalocean_kubernetes_cluster.main.kube_config[0].cluster_ca_certificate
  }
  sensitive = true
}

output "load_balancer_ip" {
  description = "Load balancer IP address"
  value       = digitalocean_reserved_ip.main.ip_address
}

output "load_balancer_id" {
  description = "Load balancer ID"
  value       = digitalocean_loadbalancer.main.id
}

output "storage_bucket" {
  description = "Storage bucket name"
  value       = digitalocean_spaces_bucket.main.name
}

output "storage_region" {
  description = "Storage bucket region"
  value       = digitalocean_spaces_bucket.main.region
}

output "storage_endpoint" {
  description = "Storage endpoint URL"
  value       = digitalocean_spaces_bucket.main.bucket_domain_name
}

output "cdn_endpoint" {
  description = "CDN endpoint URL"
  value       = var.environment == "production" ? digitalocean_cdn.main[0].endpoint : ""
}

output "vpc_id" {
  description = "VPC ID"
  value       = digitalocean_vpc.main.id
}

output "vpc_cidr" {
  description = "VPC CIDR block"
  value       = digitalocean_vpc.main.ip_range
}

output "container_registry_endpoint" {
  description = "Container registry endpoint"
  value       = digitalocean_container_registry.main.endpoint
}

output "container_registry_name" {
  description = "Container registry name"
  value       = digitalocean_container_registry.main.name
}

output "database_endpoint" {
  description = "Database cluster endpoint"
  value       = var.use_managed_database ? digitalocean_database_cluster.main[0].host : ""
}

output "database_port" {
  description = "Database cluster port"
  value       = var.use_managed_database ? digitalocean_database_cluster.main[0].port : 3306
}

output "database_name" {
  description = "Database name"
  value       = var.use_managed_database ? digitalocean_database_cluster.main[0].database : "reverse_tender"
}

output "database_username" {
  description = "Database username"
  value       = var.use_managed_database ? digitalocean_database_cluster.main[0].user : "root"
}

output "database_password" {
  description = "Database password"
  value       = var.use_managed_database ? digitalocean_database_cluster.main[0].password : ""
  sensitive   = true
}

output "redis_endpoint" {
  description = "Redis cluster endpoint"
  value       = var.use_managed_redis ? digitalocean_database_cluster.redis[0].host : ""
}

output "redis_port" {
  description = "Redis cluster port"
  value       = var.use_managed_redis ? digitalocean_database_cluster.redis[0].port : 6379
}

output "redis_password" {
  description = "Redis password"
  value       = var.use_managed_redis ? digitalocean_database_cluster.redis[0].password : ""
  sensitive   = true
}

output "ssl_certificate_id" {
  description = "SSL certificate ID"
  value       = digitalocean_certificate.main.id
}

output "firewall_id" {
  description = "Firewall ID"
  value       = digitalocean_firewall.main.id
}

output "project_id" {
  description = "DigitalOcean project ID"
  value       = digitalocean_project.main.id
}

output "cluster_info" {
  description = "Complete cluster information"
  value = {
    name               = digitalocean_kubernetes_cluster.main.name
    id                 = digitalocean_kubernetes_cluster.main.id
    endpoint           = digitalocean_kubernetes_cluster.main.endpoint
    version            = digitalocean_kubernetes_cluster.main.version
    region             = digitalocean_kubernetes_cluster.main.region
    vpc_uuid           = digitalocean_kubernetes_cluster.main.vpc_uuid
    node_pool_id       = digitalocean_kubernetes_cluster.main.node_pool[0].id
    node_pool_name     = digitalocean_kubernetes_cluster.main.node_pool[0].name
    node_count         = digitalocean_kubernetes_cluster.main.node_pool[0].node_count
    auto_scale         = digitalocean_kubernetes_cluster.main.node_pool[0].auto_scale
    min_nodes          = digitalocean_kubernetes_cluster.main.node_pool[0].min_nodes
    max_nodes          = digitalocean_kubernetes_cluster.main.node_pool[0].max_nodes
  }
}

output "network_info" {
  description = "Network configuration information"
  value = {
    vpc_id             = digitalocean_vpc.main.id
    vpc_cidr           = digitalocean_vpc.main.ip_range
    load_balancer_ip   = digitalocean_reserved_ip.main.ip_address
    load_balancer_id   = digitalocean_loadbalancer.main.id
    firewall_id        = digitalocean_firewall.main.id
  }
}

output "storage_info" {
  description = "Storage configuration information"
  value = {
    bucket_name        = digitalocean_spaces_bucket.main.name
    bucket_region      = digitalocean_spaces_bucket.main.region
    bucket_endpoint    = digitalocean_spaces_bucket.main.bucket_domain_name
    cdn_endpoint       = var.environment == "production" ? digitalocean_cdn.main[0].endpoint : ""
    registry_endpoint  = digitalocean_container_registry.main.endpoint
    registry_name      = digitalocean_container_registry.main.name
  }
}

