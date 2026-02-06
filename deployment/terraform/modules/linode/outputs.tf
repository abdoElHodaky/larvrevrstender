# Outputs for Linode Module

output "kubernetes_endpoint" {
  description = "Kubernetes cluster endpoint"
  value       = linode_lke_cluster.main.api_endpoints[0]
}

output "kubernetes_token" {
  description = "Kubernetes cluster token"
  value       = linode_lke_cluster.main.kubeconfig
  sensitive   = true
}

output "kubernetes_ca_certificate" {
  description = "Kubernetes cluster CA certificate"
  value       = base64decode(linode_lke_cluster.main.kubeconfig)
  sensitive   = true
}

output "kubernetes_config" {
  description = "Complete Kubernetes configuration"
  value = {
    host                   = linode_lke_cluster.main.api_endpoints[0]
    token                  = linode_lke_cluster.main.kubeconfig
    cluster_ca_certificate = base64decode(linode_lke_cluster.main.kubeconfig)
  }
  sensitive = true
}

output "load_balancer_ip" {
  description = "Load balancer IP address"
  value       = linode_nodebalancer.main.ipv4
}

output "load_balancer_id" {
  description = "Load balancer ID"
  value       = linode_nodebalancer.main.id
}

output "storage_bucket" {
  description = "Storage bucket name"
  value       = linode_object_storage_bucket.main.label
}

output "storage_region" {
  description = "Storage bucket region"
  value       = linode_object_storage_bucket.main.region
}

output "storage_endpoint" {
  description = "Storage endpoint URL"
  value       = "https://${linode_object_storage_bucket.main.region}.linodeobjects.com"
}

output "cdn_endpoint" {
  description = "CDN endpoint URL (Linode doesn't have native CDN, using storage endpoint)"
  value       = "https://${linode_object_storage_bucket.main.region}.linodeobjects.com"
}

output "vpc_id" {
  description = "VPC ID"
  value       = linode_vpc.main.id
}

output "vpc_cidr" {
  description = "VPC CIDR block"
  value       = var.network_config.vpc_cidr
}

output "container_registry_endpoint" {
  description = "Container registry endpoint (using Docker Hub or external registry)"
  value       = "registry.hub.docker.com"
}

output "container_registry_name" {
  description = "Container registry name"
  value       = "${var.project_name}-registry"
}

output "database_endpoint" {
  description = "Database cluster endpoint"
  value       = var.use_managed_database ? linode_database_mysql.main[0].host : ""
}

output "database_port" {
  description = "Database cluster port"
  value       = var.use_managed_database ? linode_database_mysql.main[0].port : 3306
}

output "database_name" {
  description = "Database name"
  value       = var.use_managed_database ? linode_database_mysql.main[0].root_username : "reverse_tender"
}

output "database_username" {
  description = "Database username"
  value       = var.use_managed_database ? linode_database_mysql.main[0].root_username : "root"
}

output "database_password" {
  description = "Database password"
  value       = var.use_managed_database ? linode_database_mysql.main[0].root_password : ""
  sensitive   = true
}

output "redis_endpoint" {
  description = "Redis cluster endpoint"
  value       = var.use_managed_redis ? linode_instance.redis[0].ip_address : ""
}

output "redis_port" {
  description = "Redis cluster port"
  value       = var.use_managed_redis ? 6379 : 6379
}

output "redis_password" {
  description = "Redis password"
  value       = var.use_managed_redis ? var.redis_password : ""
  sensitive   = true
}

output "ssl_certificate_id" {
  description = "SSL certificate ID (domain record ID)"
  value       = linode_domain_record.ssl_cert.id
}

output "firewall_id" {
  description = "Firewall ID"
  value       = linode_firewall.main.id
}

output "project_id" {
  description = "Linode project ID (using domain ID as project identifier)"
  value       = linode_domain.main.id
}

output "cluster_info" {
  description = "Complete cluster information"
  value = {
    name               = linode_lke_cluster.main.label
    id                 = linode_lke_cluster.main.id
    endpoint           = linode_lke_cluster.main.api_endpoints[0]
    version            = linode_lke_cluster.main.k8s_version
    region             = linode_lke_cluster.main.region
    vpc_uuid           = linode_vpc.main.id
    node_pool_id       = linode_lke_cluster.main.pool[0].id
    node_pool_name     = "default-pool"
    node_count         = linode_lke_cluster.main.pool[0].count
    auto_scale         = linode_lke_cluster.main.pool[0].autoscaler[0].enabled
    min_nodes          = linode_lke_cluster.main.pool[0].autoscaler[0].min
    max_nodes          = linode_lke_cluster.main.pool[0].autoscaler[0].max
  }
}

output "network_info" {
  description = "Network configuration information"
  value = {
    vpc_id             = linode_vpc.main.id
    vpc_cidr           = var.network_config.vpc_cidr
    load_balancer_ip   = linode_nodebalancer.main.ipv4
    load_balancer_id   = linode_nodebalancer.main.id
    firewall_id        = linode_firewall.main.id
  }
}

output "storage_info" {
  description = "Storage configuration information"
  value = {
    bucket_name        = linode_object_storage_bucket.main.label
    bucket_region      = linode_object_storage_bucket.main.region
    bucket_endpoint    = "https://${linode_object_storage_bucket.main.region}.linodeobjects.com"
    cdn_endpoint       = "https://${linode_object_storage_bucket.main.region}.linodeobjects.com"
    registry_endpoint  = "registry.hub.docker.com"
    registry_name      = "${var.project_name}-registry"
    access_key         = linode_object_storage_key.main.access_key
    secret_key         = linode_object_storage_key.main.secret_key
  }
  sensitive = true
}
