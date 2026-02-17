# Linode Kubernetes Engine (LKE) Module for Reverse Tender Platform

# LKE Cluster
resource "linode_lke_cluster" "main" {
  label       = var.cluster_name
  k8s_version = var.kubernetes_version
  region      = var.region
  tags        = var.tags

  # Control plane configuration
  control_plane {
    high_availability = var.environment == "prod" ? true : false
  }

  # Node pools
  dynamic "pool" {
    for_each = var.node_pools
    content {
      type  = pool.value.type
      count = pool.value.count
      
      # Auto-scaling configuration
      autoscaler {
        enabled = var.enable_autoscaling
        min     = var.min_nodes_per_pool
        max     = var.max_nodes_per_pool
      }
    }
  }
}

# Kubernetes provider configuration
provider "kubernetes" {
  host                   = linode_lke_cluster.main.api_endpoints[0]
  token                  = linode_lke_cluster.main.token
  cluster_ca_certificate = base64decode(linode_lke_cluster.main.ca_certificate)
}

# Helm provider configuration
provider "helm" {
  kubernetes {
    host                   = linode_lke_cluster.main.api_endpoints[0]
    token                  = linode_lke_cluster.main.token
    cluster_ca_certificate = base64decode(linode_lke_cluster.main.ca_certificate)
  }
}

# Namespace for microservices
resource "kubernetes_namespace" "microservices" {
  metadata {
    name = "microservices"
    
    labels = {
      environment = var.environment
      project     = var.project_name
    }
  }
}

# Namespace for monitoring
resource "kubernetes_namespace" "monitoring" {
  metadata {
    name = "monitoring"
    
    labels = {
      environment = var.environment
      project     = var.project_name
    }
  }
}

# NGINX Ingress Controller
resource "helm_release" "nginx_ingress" {
  name       = "nginx-ingress"
  repository = "https://kubernetes.github.io/ingress-nginx"
  chart      = "ingress-nginx"
  namespace  = "ingress-nginx"
  
  create_namespace = true
  
  set {
    name  = "controller.service.type"
    value = "LoadBalancer"
  }
  
  set {
    name  = "controller.service.annotations.service\\.beta\\.kubernetes\\.io/linode-loadbalancer-enable-proxy-protocol"
    value = "true"
  }
  
  set {
    name  = "controller.config.use-proxy-protocol"
    value = "true"
  }
  
  set {
    name  = "controller.metrics.enabled"
    value = "true"
  }
  
  set {
    name  = "controller.podAnnotations.prometheus\\.io/scrape"
    value = "true"
  }
  
  set {
    name  = "controller.podAnnotations.prometheus\\.io/port"
    value = "10254"
  }
}

# Cert-Manager for SSL certificates
resource "helm_release" "cert_manager" {
  name       = "cert-manager"
  repository = "https://charts.jetstack.io"
  chart      = "cert-manager"
  namespace  = "cert-manager"
  version    = "v1.13.0"
  
  create_namespace = true
  
  set {
    name  = "installCRDs"
    value = "true"
  }
  
  set {
    name  = "global.leaderElection.namespace"
    value = "cert-manager"
  }
}

# Let's Encrypt ClusterIssuer
resource "kubernetes_manifest" "letsencrypt_issuer" {
  depends_on = [helm_release.cert_manager]
  
  manifest = {
    apiVersion = "cert-manager.io/v1"
    kind       = "ClusterIssuer"
    metadata = {
      name = "letsencrypt-prod"
    }
    spec = {
      acme = {
        server = "https://acme-v02.api.letsencrypt.org/directory"
        email  = var.letsencrypt_email
        privateKeySecretRef = {
          name = "letsencrypt-prod"
        }
        solvers = [
          {
            http01 = {
              ingress = {
                class = "nginx"
              }
            }
          }
        ]
      }
    }
  }
}

# Prometheus monitoring stack
resource "helm_release" "prometheus" {
  name       = "prometheus"
  repository = "https://prometheus-community.github.io/helm-charts"
  chart      = "kube-prometheus-stack"
  namespace  = kubernetes_namespace.monitoring.metadata[0].name
  
  values = [
    yamlencode({
      prometheus = {
        prometheusSpec = {
          serviceMonitorSelectorNilUsesHelmValues = false
          podMonitorSelectorNilUsesHelmValues     = false
          retention = "30d"
          storageSpec = {
            volumeClaimTemplate = {
              spec = {
                storageClassName = "linode-block-storage-retain"
                accessModes      = ["ReadWriteOnce"]
                resources = {
                  requests = {
                    storage = "50Gi"
                  }
                }
              }
            }
          }
        }
      }
      grafana = {
        adminPassword = var.grafana_admin_password
        persistence = {
          enabled          = true
          storageClassName = "linode-block-storage-retain"
          size             = "10Gi"
        }
        ingress = {
          enabled = true
          ingressClassName = "nginx"
          annotations = {
            "cert-manager.io/cluster-issuer" = "letsencrypt-prod"
          }
          hosts = [
            {
              host = "grafana-${var.environment}.${var.domain_name}"
              paths = [
                {
                  path     = "/"
                  pathType = "Prefix"
                }
              ]
            }
          ]
          tls = [
            {
              secretName = "grafana-tls"
              hosts      = ["grafana-${var.environment}.${var.domain_name}"]
            }
          ]
        }
      }
      alertmanager = {
        alertmanagerSpec = {
          storage = {
            volumeClaimTemplate = {
              spec = {
                storageClassName = "linode-block-storage-retain"
                accessModes      = ["ReadWriteOnce"]
                resources = {
                  requests = {
                    storage = "10Gi"
                  }
                }
              }
            }
          }
        }
      }
    })
  ]
}

# Create service accounts for microservices
resource "kubernetes_service_account" "microservices" {
  for_each = var.services
  
  metadata {
    name      = "${each.key}-sa"
    namespace = kubernetes_namespace.microservices.metadata[0].name
    
    labels = {
      service     = each.key
      environment = var.environment
    }
  }
}

# Create deployments for microservices
resource "kubernetes_deployment" "microservices" {
  for_each = var.services
  
  metadata {
    name      = each.key
    namespace = kubernetes_namespace.microservices.metadata[0].name
    
    labels = {
      app         = each.key
      environment = var.environment
    }
  }
  
  spec {
    replicas = each.value.replicas
    
    selector {
      match_labels = {
        app = each.key
      }
    }
    
    template {
      metadata {
        labels = {
          app         = each.key
          environment = var.environment
        }
      }
      
      spec {
        service_account_name = kubernetes_service_account.microservices[each.key].metadata[0].name
        
        container {
          name  = each.key
          image = "${var.container_registry}/${each.key}:${var.image_tag}"
          
          port {
            container_port = each.value.port
            name          = "http"
          }
          
          env {
            name  = "SERVICE_NAME"
            value = each.key
          }
          
          env {
            name  = "SERVICE_PORT"
            value = tostring(each.value.port)
          }
          
          env {
            name  = "ENVIRONMENT"
            value = var.environment
          }
          
          # Resource limits
          resources {
            requests = {
              cpu    = var.resource_requests.cpu
              memory = var.resource_requests.memory
            }
            limits = {
              cpu    = var.resource_limits.cpu
              memory = var.resource_limits.memory
            }
          }
          
          # Health checks
          liveness_probe {
            http_get {
              path = "/health"
              port = "http"
            }
            initial_delay_seconds = 30
            period_seconds        = 10
            timeout_seconds       = 5
            failure_threshold     = 3
          }
          
          readiness_probe {
            http_get {
              path = "/ready"
              port = "http"
            }
            initial_delay_seconds = 5
            period_seconds        = 5
            timeout_seconds       = 3
            failure_threshold     = 3
          }
        }
      }
    }
  }
}

# Create services for microservices
resource "kubernetes_service" "microservices" {
  for_each = var.services
  
  metadata {
    name      = each.key
    namespace = kubernetes_namespace.microservices.metadata[0].name
    
    labels = {
      app         = each.key
      environment = var.environment
    }
  }
  
  spec {
    selector = {
      app = each.key
    }
    
    port {
      name        = "http"
      port        = each.value.port
      target_port = "http"
      protocol    = "TCP"
    }
    
    type = "ClusterIP"
  }
}

# Create horizontal pod autoscalers
resource "kubernetes_horizontal_pod_autoscaler_v2" "microservices" {
  for_each = var.enable_hpa ? var.services : {}
  
  metadata {
    name      = each.key
    namespace = kubernetes_namespace.microservices.metadata[0].name
  }
  
  spec {
    scale_target_ref {
      api_version = "apps/v1"
      kind        = "Deployment"
      name        = each.key
    }
    
    min_replicas = 1
    max_replicas = var.max_replicas_per_service
    
    metric {
      type = "Resource"
      resource {
        name = "cpu"
        target {
          type                = "Utilization"
          average_utilization = 70
        }
      }
    }
    
    metric {
      type = "Resource"
      resource {
        name = "memory"
        target {
          type                = "Utilization"
          average_utilization = 80
        }
      }
    }
  }
}

# Main ingress for microservices
resource "kubernetes_ingress_v1" "microservices" {
  metadata {
    name      = "microservices-ingress"
    namespace = kubernetes_namespace.microservices.metadata[0].name
    
    annotations = {
      "kubernetes.io/ingress.class"                = "nginx"
      "cert-manager.io/cluster-issuer"             = "letsencrypt-prod"
      "nginx.ingress.kubernetes.io/rate-limit"     = "100"
      "nginx.ingress.kubernetes.io/rate-limit-window" = "1m"
    }
  }
  
  spec {
    tls {
      hosts       = [var.domain_name]
      secret_name = "microservices-tls"
    }
    
    rule {
      host = var.domain_name
      
      http {
        # API Gateway - main entry point
        path {
          path      = "/"
          path_type = "Prefix"
          
          backend {
            service {
              name = "api-gateway"
              port {
                number = var.services["api_gateway"].port
              }
            }
          }
        }
        
        # Individual service paths
        dynamic "path" {
          for_each = var.services
          content {
            path      = "/${replace(path.key, "_", "-")}/"
            path_type = "Prefix"
            
            backend {
              service {
                name = path.key
                port {
                  number = path.value.port
                }
              }
            }
          }
        }
      }
    }
  }
}
