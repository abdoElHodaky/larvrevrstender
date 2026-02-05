# Outputs for Gateway API Module

output "gateway_name" {
  description = "Name of the Gateway"
  value       = kubernetes_manifest.gateway.manifest.metadata.name
}

output "gateway_namespace" {
  description = "Namespace of the Gateway"
  value       = kubernetes_manifest.gateway.manifest.metadata.namespace
}

output "gateway_class_name" {
  description = "Name of the GatewayClass"
  value       = kubernetes_manifest.gateway_class.manifest.metadata.name
}

output "gateway_endpoint" {
  description = "Gateway endpoint URL"
  value       = var.ssl_enabled ? "https://${var.domain_name}" : "http://${var.domain_name}"
}

output "gateway_listeners" {
  description = "Gateway listeners configuration"
  value = {
    http  = var.ssl_enabled ? null : { port = 80, protocol = "HTTP" }
    https = var.ssl_enabled ? { port = 443, protocol = "HTTPS" } : null
  }
}

output "gateway_routes" {
  description = "List of configured HTTPRoutes"
  value = {
    api_gateway      = kubernetes_manifest.api_gateway_route.manifest.metadata.name
    auth_service     = kubernetes_manifest.auth_service_route.manifest.metadata.name
    user_service     = kubernetes_manifest.user_service_route.manifest.metadata.name
    bidding_service  = kubernetes_manifest.bidding_service_route.manifest.metadata.name
    order_service    = kubernetes_manifest.order_service_route.manifest.metadata.name
    notification_service = kubernetes_manifest.notification_service_route.manifest.metadata.name
    payment_service  = kubernetes_manifest.payment_service_route.manifest.metadata.name
    analytics_service = kubernetes_manifest.analytics_service_route.manifest.metadata.name
    vin_ocr_service  = kubernetes_manifest.vin_ocr_service_route.manifest.metadata.name
  }
}

output "gateway_config" {
  description = "Gateway configuration details"
  value = {
    domain_name           = var.domain_name
    ssl_enabled          = var.ssl_enabled
    cors_enabled         = var.cors_enabled
    rate_limiting_enabled = var.rate_limiting_enabled
    monitoring_enabled   = var.monitoring_enabled
    cloud_provider       = var.cloud_provider
  }
}

output "service_endpoints" {
  description = "Service endpoint mappings"
  value = {
    api_gateway      = "${var.ssl_enabled ? "https" : "http"}://${var.domain_name}/api"
    auth_service     = "${var.ssl_enabled ? "https" : "http"}://${var.domain_name}/auth"
    user_service     = "${var.ssl_enabled ? "https" : "http"}://${var.domain_name}/users"
    bidding_service  = "${var.ssl_enabled ? "https" : "http"}://${var.domain_name}/bidding"
    order_service    = "${var.ssl_enabled ? "https" : "http"}://${var.domain_name}/orders"
    notification_service = "${var.ssl_enabled ? "https" : "http"}://${var.domain_name}/notifications"
    payment_service  = "${var.ssl_enabled ? "https" : "http"}://${var.domain_name}/payments"
    analytics_service = "${var.ssl_enabled ? "https" : "http"}://${var.domain_name}/analytics"
    vin_ocr_service  = "${var.ssl_enabled ? "https" : "http"}://${var.domain_name}/vin-ocr"
  }
}

output "gateway_status" {
  description = "Gateway deployment status"
  value = {
    gateway_deployed     = true
    gateway_class_ready  = true
    routes_configured    = length(local.services)
    ssl_configured       = var.ssl_enabled
    load_balancer_type   = var.cloud_provider
  }
}

output "security_config" {
  description = "Security configuration details"
  value = {
    cors_enabled             = var.cors_enabled
    cors_allowed_origins     = var.cors_allowed_origins
    rate_limiting_enabled    = var.rate_limiting_enabled
    rate_limit_requests      = var.rate_limit_requests
    rate_limit_window        = var.rate_limit_window
    backend_tls_enabled      = var.backend_tls_enabled
    security_policies_enabled = var.security_policies_enabled
    allowed_source_ranges    = var.allowed_source_ranges
  }
}

output "performance_config" {
  description = "Performance configuration details"
  value = {
    request_timeout          = var.request_timeout
    idle_timeout            = var.idle_timeout
    retry_enabled           = var.retry_enabled
    retry_attempts          = var.retry_attempts
    retry_timeout           = var.retry_timeout
    circuit_breaker_enabled = var.circuit_breaker_enabled
    compression_enabled     = var.compression_enabled
    websocket_enabled       = var.websocket_enabled
  }
}

output "monitoring_config" {
  description = "Monitoring configuration details"
  value = {
    monitoring_enabled    = var.monitoring_enabled
    access_logging_enabled = var.access_logging_enabled
    log_level            = var.log_level
    metrics_port         = var.metrics_port
  }
}

output "high_availability_config" {
  description = "High availability configuration"
  value = {
    enabled          = var.high_availability_enabled
    gateway_replicas = var.gateway_replicas
    resource_limits  = var.gateway_resources
  }
}

output "gateway_annotations" {
  description = "Gateway annotations for cloud provider integration"
  value = var.cloud_provider == "digitalocean" ? {
    "service.beta.kubernetes.io/do-loadbalancer-name"                = "${local.gateway_name}-lb"
    "service.beta.kubernetes.io/do-loadbalancer-protocol"            = "http"
    "service.beta.kubernetes.io/do-loadbalancer-algorithm"           = var.load_balancer_algorithm
    "service.beta.kubernetes.io/do-loadbalancer-health-check-path"   = var.health_check_path
    "service.beta.kubernetes.io/do-loadbalancer-health-check-protocol" = "http"
    "service.beta.kubernetes.io/do-loadbalancer-size-unit"           = var.environment == "production" ? "2" : "1"
  } : {
    "service.beta.kubernetes.io/linode-loadbalancer-throttle" = "4"
    "service.beta.kubernetes.io/linode-loadbalancer-default-protocol" = "http"
  }
}

output "reference_grant_name" {
  description = "Name of the ReferenceGrant for cross-namespace access"
  value       = kubernetes_manifest.reference_grant.manifest.metadata.name
}

output "backend_tls_policy_name" {
  description = "Name of the BackendTLSPolicy (if enabled)"
  value       = var.backend_tls_enabled ? kubernetes_manifest.backend_tls_policy[0].manifest.metadata.name : null
}

output "gateway_system_namespace" {
  description = "Gateway system namespace details"
  value = {
    name   = kubernetes_namespace.gateway_system.metadata[0].name
    labels = kubernetes_namespace.gateway_system.metadata[0].labels
  }
}

output "helm_release_info" {
  description = "Helm release information for Gateway API"
  value = {
    name      = helm_release.gateway_api.name
    namespace = helm_release.gateway_api.namespace
    version   = helm_release.gateway_api.version
    status    = helm_release.gateway_api.status
  }
}

output "gateway_config_map" {
  description = "Gateway configuration ConfigMap details"
  value = {
    name      = kubernetes_config_map.gateway_config.metadata[0].name
    namespace = kubernetes_config_map.gateway_config.metadata[0].namespace
  }
}

output "deployment_summary" {
  description = "Complete deployment summary"
  value = {
    project_name     = var.project_name
    environment      = var.environment
    cloud_provider   = var.cloud_provider
    domain_name      = var.domain_name
    gateway_name     = local.gateway_name
    gateway_namespace = local.namespace
    services_count   = length(local.services)
    ssl_enabled      = var.ssl_enabled
    monitoring_enabled = var.monitoring_enabled
    high_availability = var.high_availability_enabled
    created_at       = timestamp()
  }
}
