# Gateway API Module for Reverse Tender Platform
# This module deploys Gateway API resources that work with both DigitalOcean and Linode

terraform {
  required_providers {
    kubernetes = {
      source  = "hashicorp/kubernetes"
      version = "~> 2.24"
    }
    helm = {
      source  = "hashicorp/helm"
      version = "~> 2.12"
    }
  }
}

# Local variables
locals {
  gateway_name = "${var.project_name}-${var.environment}-gateway"
  namespace    = var.gateway_namespace
  
  labels = {
    "app.kubernetes.io/name"       = "gateway-api"
    "app.kubernetes.io/instance"   = local.gateway_name
    "app.kubernetes.io/component"  = "gateway"
    "app.kubernetes.io/part-of"    = var.project_name
    "app.kubernetes.io/managed-by" = "terraform"
    "environment"                  = var.environment
  }
  
  # Service configurations
  services = var.services
}

# Create namespace for Gateway API resources
resource "kubernetes_namespace" "gateway_system" {
  metadata {
    name = local.namespace
    labels = merge(local.labels, {
      "name" = local.namespace
    })
  }
}

# Install Gateway API CRDs using Helm
resource "helm_release" "gateway_api" {
  name       = "gateway-api"
  repository = "https://gateway-api.github.io/gateway-api"
  chart      = "gateway-api"
  version    = var.gateway_api_version
  namespace  = kubernetes_namespace.gateway_system.metadata[0].name

  set {
    name  = "image.tag"
    value = var.gateway_api_version
  }

  set {
    name  = "resources.requests.cpu"
    value = "100m"
  }

  set {
    name  = "resources.requests.memory"
    value = "128Mi"
  }

  set {
    name  = "resources.limits.cpu"
    value = "500m"
  }

  set {
    name  = "resources.limits.memory"
    value = "512Mi"
  }

  depends_on = [kubernetes_namespace.gateway_system]
}

# GatewayClass - defines the controller for the Gateway
resource "kubernetes_manifest" "gateway_class" {
  manifest = {
    apiVersion = "gateway.networking.k8s.io/v1"
    kind       = "GatewayClass"
    metadata = {
      name   = "${local.gateway_name}-class"
      labels = local.labels
    }
    spec = {
      controllerName = var.gateway_controller_name
      parametersRef = {
        group = ""
        kind  = "ConfigMap"
        name  = kubernetes_config_map.gateway_config.metadata[0].name
        namespace = kubernetes_namespace.gateway_system.metadata[0].name
      }
      description = "Gateway class for ${var.project_name} ${var.environment} environment"
    }
  }

  depends_on = [helm_release.gateway_api]
}

# ConfigMap for Gateway configuration
resource "kubernetes_config_map" "gateway_config" {
  metadata {
    name      = "${local.gateway_name}-config"
    namespace = kubernetes_namespace.gateway_system.metadata[0].name
    labels    = local.labels
  }

  data = {
    "gateway.yaml" = yamlencode({
      loadBalancer = {
        type = var.cloud_provider == "digitalocean" ? "digitalocean" : "linode"
        annotations = var.cloud_provider == "digitalocean" ? {
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
      ssl = {
        enabled = var.ssl_enabled
        certificateRef = {
          name      = var.ssl_certificate_name
          namespace = local.namespace
        }
      }
      cors = {
        enabled = var.cors_enabled
        allowOrigins = var.cors_allowed_origins
        allowMethods = ["GET", "POST", "PUT", "DELETE", "OPTIONS"]
        allowHeaders = ["Content-Type", "Authorization", "X-Requested-With"]
      }
      rateLimit = {
        enabled = var.rate_limiting_enabled
        requests = var.rate_limit_requests
        window   = var.rate_limit_window
      }
    })
  }

  depends_on = [kubernetes_namespace.gateway_system]
}

# Gateway - the actual gateway instance
resource "kubernetes_manifest" "gateway" {
  manifest = {
    apiVersion = "gateway.networking.k8s.io/v1"
    kind       = "Gateway"
    metadata = {
      name      = local.gateway_name
      namespace = local.namespace
      labels    = local.labels
      annotations = {
        "gateway.networking.k8s.io/description" = "Main gateway for ${var.project_name} ${var.environment}"
      }
    }
    spec = {
      gatewayClassName = kubernetes_manifest.gateway_class.manifest.metadata.name
      listeners = concat(
        # HTTP listener
        [{
          name     = "http"
          hostname = var.domain_name
          port     = 80
          protocol = "HTTP"
          allowedRoutes = {
            namespaces = {
              from = "All"
            }
          }
        }],
        # HTTPS listener (if SSL is enabled)
        var.ssl_enabled ? [{
          name     = "https"
          hostname = var.domain_name
          port     = 443
          protocol = "HTTPS"
          tls = {
            mode = "Terminate"
            certificateRefs = [{
              name      = var.ssl_certificate_name
              namespace = local.namespace
            }]
          }
          allowedRoutes = {
            namespaces = {
              from = "All"
            }
          }
        }] : []
      )
    }
  }

  depends_on = [kubernetes_manifest.gateway_class]
}

# HTTPRoute for API Gateway service
resource "kubernetes_manifest" "api_gateway_route" {
  manifest = {
    apiVersion = "gateway.networking.k8s.io/v1"
    kind       = "HTTPRoute"
    metadata = {
      name      = "${local.gateway_name}-api-route"
      namespace = var.app_namespace
      labels    = local.labels
    }
    spec = {
      parentRefs = [{
        name      = kubernetes_manifest.gateway.manifest.metadata.name
        namespace = local.namespace
      }]
      hostnames = [var.domain_name]
      rules = [
        {
          matches = [{
            path = {
              type  = "PathPrefix"
              value = "/api"
            }
          }]
          backendRefs = [{
            name = "api-gateway-service"
            port = local.services.api_gateway.port
          }]
          filters = var.rate_limiting_enabled ? [{
            type = "RequestHeaderModifier"
            requestHeaderModifier = {
              add = [{
                name  = "X-RateLimit-Limit"
                value = tostring(var.rate_limit_requests)
              }]
            }
          }] : []
        }
      ]
    }
  }

  depends_on = [kubernetes_manifest.gateway]
}

# HTTPRoute for Auth service
resource "kubernetes_manifest" "auth_service_route" {
  manifest = {
    apiVersion = "gateway.networking.k8s.io/v1"
    kind       = "HTTPRoute"
    metadata = {
      name      = "${local.gateway_name}-auth-route"
      namespace = var.app_namespace
      labels    = local.labels
    }
    spec = {
      parentRefs = [{
        name      = kubernetes_manifest.gateway.manifest.metadata.name
        namespace = local.namespace
      }]
      hostnames = [var.domain_name]
      rules = [
        {
          matches = [{
            path = {
              type  = "PathPrefix"
              value = "/auth"
            }
          }]
          backendRefs = [{
            name = "auth-service"
            port = local.services.auth_service.port
          }]
        }
      ]
    }
  }

  depends_on = [kubernetes_manifest.gateway]
}

# HTTPRoute for User service
resource "kubernetes_manifest" "user_service_route" {
  manifest = {
    apiVersion = "gateway.networking.k8s.io/v1"
    kind       = "HTTPRoute"
    metadata = {
      name      = "${local.gateway_name}-user-route"
      namespace = var.app_namespace
      labels    = local.labels
    }
    spec = {
      parentRefs = [{
        name      = kubernetes_manifest.gateway.manifest.metadata.name
        namespace = local.namespace
      }]
      hostnames = [var.domain_name]
      rules = [
        {
          matches = [{
            path = {
              type  = "PathPrefix"
              value = "/users"
            }
          }]
          backendRefs = [{
            name = "user-service"
            port = local.services.user_service.port
          }]
        }
      ]
    }
  }

  depends_on = [kubernetes_manifest.gateway]
}

# HTTPRoute for Bidding service
resource "kubernetes_manifest" "bidding_service_route" {
  manifest = {
    apiVersion = "gateway.networking.k8s.io/v1"
    kind       = "HTTPRoute"
    metadata = {
      name      = "${local.gateway_name}-bidding-route"
      namespace = var.app_namespace
      labels    = local.labels
    }
    spec = {
      parentRefs = [{
        name      = kubernetes_manifest.gateway.manifest.metadata.name
        namespace = local.namespace
      }]
      hostnames = [var.domain_name]
      rules = [
        {
          matches = [{
            path = {
              type  = "PathPrefix"
              value = "/bidding"
            }
          }]
          backendRefs = [{
            name = "bidding-service"
            port = local.services.bidding_service.port
          }]
        }
      ]
    }
  }

  depends_on = [kubernetes_manifest.gateway]
}

# HTTPRoute for Order service
resource "kubernetes_manifest" "order_service_route" {
  manifest = {
    apiVersion = "gateway.networking.k8s.io/v1"
    kind       = "HTTPRoute"
    metadata = {
      name      = "${local.gateway_name}-order-route"
      namespace = var.app_namespace
      labels    = local.labels
    }
    spec = {
      parentRefs = [{
        name      = kubernetes_manifest.gateway.manifest.metadata.name
        namespace = local.namespace
      }]
      hostnames = [var.domain_name]
      rules = [
        {
          matches = [{
            path = {
              type  = "PathPrefix"
              value = "/orders"
            }
          }]
          backendRefs = [{
            name = "order-service"
            port = local.services.order_service.port
          }]
        }
      ]
    }
  }

  depends_on = [kubernetes_manifest.gateway]
}

# HTTPRoute for Notification service
resource "kubernetes_manifest" "notification_service_route" {
  manifest = {
    apiVersion = "gateway.networking.k8s.io/v1"
    kind       = "HTTPRoute"
    metadata = {
      name      = "${local.gateway_name}-notification-route"
      namespace = var.app_namespace
      labels    = local.labels
    }
    spec = {
      parentRefs = [{
        name      = kubernetes_manifest.gateway.manifest.metadata.name
        namespace = local.namespace
      }]
      hostnames = [var.domain_name]
      rules = [
        {
          matches = [{
            path = {
              type  = "PathPrefix"
              value = "/notifications"
            }
          }]
          backendRefs = [{
            name = "notification-service"
            port = local.services.notification_service.port
          }]
        }
      ]
    }
  }

  depends_on = [kubernetes_manifest.gateway]
}

# HTTPRoute for Payment service
resource "kubernetes_manifest" "payment_service_route" {
  manifest = {
    apiVersion = "gateway.networking.k8s.io/v1"
    kind       = "HTTPRoute"
    metadata = {
      name      = "${local.gateway_name}-payment-route"
      namespace = var.app_namespace
      labels    = local.labels
    }
    spec = {
      parentRefs = [{
        name      = kubernetes_manifest.gateway.manifest.metadata.name
        namespace = local.namespace
      }]
      hostnames = [var.domain_name]
      rules = [
        {
          matches = [{
            path = {
              type  = "PathPrefix"
              value = "/payments"
            }
          }]
          backendRefs = [{
            name = "payment-service"
            port = local.services.payment_service.port
          }]
        }
      ]
    }
  }

  depends_on = [kubernetes_manifest.gateway]
}

# HTTPRoute for Analytics service
resource "kubernetes_manifest" "analytics_service_route" {
  manifest = {
    apiVersion = "gateway.networking.k8s.io/v1"
    kind       = "HTTPRoute"
    metadata = {
      name      = "${local.gateway_name}-analytics-route"
      namespace = var.app_namespace
      labels    = local.labels
    }
    spec = {
      parentRefs = [{
        name      = kubernetes_manifest.gateway.manifest.metadata.name
        namespace = local.namespace
      }]
      hostnames = [var.domain_name]
      rules = [
        {
          matches = [{
            path = {
              type  = "PathPrefix"
              value = "/analytics"
            }
          }]
          backendRefs = [{
            name = "analytics-service"
            port = local.services.analytics_service.port
          }]
        }
      ]
    }
  }

  depends_on = [kubernetes_manifest.gateway]
}

# HTTPRoute for VIN OCR service
resource "kubernetes_manifest" "vin_ocr_service_route" {
  manifest = {
    apiVersion = "gateway.networking.k8s.io/v1"
    kind       = "HTTPRoute"
    metadata = {
      name      = "${local.gateway_name}-vin-ocr-route"
      namespace = var.app_namespace
      labels    = local.labels
    }
    spec = {
      parentRefs = [{
        name      = kubernetes_manifest.gateway.manifest.metadata.name
        namespace = local.namespace
      }]
      hostnames = [var.domain_name]
      rules = [
        {
          matches = [{
            path = {
              type  = "PathPrefix"
              value = "/vin-ocr"
            }
          }]
          backendRefs = [{
            name = "vin-ocr-service"
            port = local.services.vin_ocr_service.port
          }]
        }
      ]
    }
  }

  depends_on = [kubernetes_manifest.gateway]
}

# ReferenceGrant to allow cross-namespace references
resource "kubernetes_manifest" "reference_grant" {
  manifest = {
    apiVersion = "gateway.networking.k8s.io/v1beta1"
    kind       = "ReferenceGrant"
    metadata = {
      name      = "${local.gateway_name}-reference-grant"
      namespace = local.namespace
      labels    = local.labels
    }
    spec = {
      from = [{
        group     = "gateway.networking.k8s.io"
        kind      = "HTTPRoute"
        namespace = var.app_namespace
      }]
      to = [{
        group = ""
        kind  = "Service"
      }]
    }
  }

  depends_on = [kubernetes_manifest.gateway]
}

# BackendTLSPolicy for secure backend communication (if enabled)
resource "kubernetes_manifest" "backend_tls_policy" {
  count = var.backend_tls_enabled ? 1 : 0

  manifest = {
    apiVersion = "gateway.networking.k8s.io/v1alpha2"
    kind       = "BackendTLSPolicy"
    metadata = {
      name      = "${local.gateway_name}-backend-tls"
      namespace = var.app_namespace
      labels    = local.labels
    }
    spec = {
      targetRef = {
        group = ""
        kind  = "Service"
        name  = "api-gateway-service"
      }
      tls = {
        caCertRefs = [{
          name = var.backend_ca_certificate_name
          kind = "ConfigMap"
        }]
        hostname = var.backend_hostname
      }
    }
  }

  depends_on = [kubernetes_manifest.gateway]
}
