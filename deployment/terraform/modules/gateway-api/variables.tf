# Variables for Gateway API Module

variable "project_name" {
  description = "Project name"
  type        = string
}

variable "environment" {
  description = "Environment name"
  type        = string
}

variable "cloud_provider" {
  description = "Cloud provider (digitalocean or linode)"
  type        = string
  validation {
    condition     = contains(["digitalocean", "linode"], var.cloud_provider)
    error_message = "Cloud provider must be either 'digitalocean' or 'linode'."
  }
}

variable "domain_name" {
  description = "Domain name for the gateway"
  type        = string
  default     = "reversetender.com"
}

variable "gateway_namespace" {
  description = "Kubernetes namespace for Gateway API resources"
  type        = string
  default     = "gateway-system"
}

variable "app_namespace" {
  description = "Kubernetes namespace for application services"
  type        = string
  default     = "default"
}

variable "gateway_api_version" {
  description = "Gateway API version"
  type        = string
  default     = "v1.0.0"
}

variable "gateway_controller_name" {
  description = "Gateway controller name"
  type        = string
  default     = "gateway.networking.k8s.io/gateway-controller"
}

variable "services" {
  description = "Service configurations"
  type = map(object({
    port     = number
    replicas = number
  }))
  default = {
    api_gateway      = { port = 8000, replicas = 2 }
    auth_service     = { port = 8001, replicas = 2 }
    bidding_service  = { port = 8002, replicas = 2 }
    user_service     = { port = 8003, replicas = 2 }
    order_service    = { port = 8004, replicas = 2 }
    notification_service = { port = 8005, replicas = 2 }
    payment_service  = { port = 8006, replicas = 2 }
    analytics_service = { port = 8007, replicas = 2 }
    vin_ocr_service  = { port = 8008, replicas = 2 }
  }
}

# SSL Configuration
variable "ssl_enabled" {
  description = "Enable SSL/TLS termination"
  type        = bool
  default     = true
}

variable "ssl_certificate_name" {
  description = "Name of the SSL certificate secret"
  type        = string
  default     = "gateway-tls-cert"
}

# Load Balancer Configuration
variable "load_balancer_algorithm" {
  description = "Load balancer algorithm"
  type        = string
  default     = "round_robin"
  validation {
    condition     = contains(["round_robin", "least_connections", "ip_hash"], var.load_balancer_algorithm)
    error_message = "Load balancer algorithm must be one of: round_robin, least_connections, ip_hash."
  }
}

variable "health_check_path" {
  description = "Health check path"
  type        = string
  default     = "/health"
}

# CORS Configuration
variable "cors_enabled" {
  description = "Enable CORS"
  type        = bool
  default     = true
}

variable "cors_allowed_origins" {
  description = "Allowed CORS origins"
  type        = list(string)
  default     = ["*"]
}

# Rate Limiting Configuration
variable "rate_limiting_enabled" {
  description = "Enable rate limiting"
  type        = bool
  default     = true
}

variable "rate_limit_requests" {
  description = "Number of requests allowed per window"
  type        = number
  default     = 100
}

variable "rate_limit_window" {
  description = "Rate limit window duration"
  type        = string
  default     = "1m"
}

# Backend TLS Configuration
variable "backend_tls_enabled" {
  description = "Enable TLS for backend communication"
  type        = bool
  default     = false
}

variable "backend_ca_certificate_name" {
  description = "Name of the backend CA certificate ConfigMap"
  type        = string
  default     = "backend-ca-cert"
}

variable "backend_hostname" {
  description = "Backend hostname for TLS verification"
  type        = string
  default     = ""
}

# Monitoring Configuration
variable "monitoring_enabled" {
  description = "Enable monitoring and metrics"
  type        = bool
  default     = true
}

variable "metrics_port" {
  description = "Port for metrics endpoint"
  type        = number
  default     = 9090
}

# Security Configuration
variable "security_policies_enabled" {
  description = "Enable security policies"
  type        = bool
  default     = true
}

variable "allowed_source_ranges" {
  description = "Allowed source IP ranges"
  type        = list(string)
  default     = ["0.0.0.0/0"]
}

# Timeout Configuration
variable "request_timeout" {
  description = "Request timeout duration"
  type        = string
  default     = "30s"
}

variable "idle_timeout" {
  description = "Idle timeout duration"
  type        = string
  default     = "60s"
}

# Retry Configuration
variable "retry_enabled" {
  description = "Enable request retries"
  type        = bool
  default     = true
}

variable "retry_attempts" {
  description = "Number of retry attempts"
  type        = number
  default     = 3
}

variable "retry_timeout" {
  description = "Retry timeout duration"
  type        = string
  default     = "10s"
}

# Circuit Breaker Configuration
variable "circuit_breaker_enabled" {
  description = "Enable circuit breaker"
  type        = bool
  default     = true
}

variable "circuit_breaker_threshold" {
  description = "Circuit breaker failure threshold"
  type        = number
  default     = 5
}

variable "circuit_breaker_timeout" {
  description = "Circuit breaker timeout duration"
  type        = string
  default     = "30s"
}

# Logging Configuration
variable "access_logging_enabled" {
  description = "Enable access logging"
  type        = bool
  default     = true
}

variable "log_level" {
  description = "Log level"
  type        = string
  default     = "info"
  validation {
    condition     = contains(["debug", "info", "warn", "error"], var.log_level)
    error_message = "Log level must be one of: debug, info, warn, error."
  }
}

# Resource Limits
variable "gateway_resources" {
  description = "Resource limits for gateway pods"
  type = object({
    requests = object({
      cpu    = string
      memory = string
    })
    limits = object({
      cpu    = string
      memory = string
    })
  })
  default = {
    requests = {
      cpu    = "100m"
      memory = "128Mi"
    }
    limits = {
      cpu    = "500m"
      memory = "512Mi"
    }
  }
}

# High Availability Configuration
variable "high_availability_enabled" {
  description = "Enable high availability mode"
  type        = bool
  default     = true
}

variable "gateway_replicas" {
  description = "Number of gateway replicas"
  type        = number
  default     = 2
}

# Custom Headers
variable "custom_headers" {
  description = "Custom headers to add to requests"
  type        = map(string)
  default     = {}
}

# WebSocket Configuration
variable "websocket_enabled" {
  description = "Enable WebSocket support"
  type        = bool
  default     = true
}

# Compression Configuration
variable "compression_enabled" {
  description = "Enable response compression"
  type        = bool
  default     = true
}

variable "compression_types" {
  description = "MIME types to compress"
  type        = list(string)
  default     = [
    "text/html",
    "text/css",
    "text/javascript",
    "application/javascript",
    "application/json",
    "application/xml",
    "text/xml"
  ]
}
