# DigitalOcean Infrastructure for Reverse Tender Platform

terraform {
  required_version = ">= 1.0"
  required_providers {
    digitalocean = {
      source  = "digitalocean/digitalocean"
      version = "~> 2.0"
    }
  }
}

# Configure DigitalOcean Provider
provider "digitalocean" {
  token = var.do_token
}

# Variables
variable "do_token" {
  description = "DigitalOcean API token"
  type        = string
  sensitive   = true
}

variable "environment" {
  description = "Environment name (production, staging, development)"
  type        = string
  default     = "production"
}

variable "region" {
  description = "DigitalOcean region"
  type        = string
  default     = "fra1"
}

variable "ssh_key_name" {
  description = "SSH key name for server access"
  type        = string
  default     = "reverse-tender-key"
}

# SSH Key
resource "digitalocean_ssh_key" "reverse_tender" {
  name       = var.ssh_key_name
  public_key = file("~/.ssh/reverse_tender_rsa.pub")
}

# VPC Network
resource "digitalocean_vpc" "reverse_tender_vpc" {
  name     = "reverse-tender-${var.environment}-vpc"
  region   = var.region
  ip_range = "10.10.0.0/16"
}

# Load Balancer
resource "digitalocean_loadbalancer" "reverse_tender_lb" {
  name   = "reverse-tender-${var.environment}-lb"
  region = var.region
  vpc_uuid = digitalocean_vpc.reverse_tender_vpc.id

  forwarding_rule {
    entry_protocol  = "https"
    entry_port      = 443
    target_protocol = "http"
    target_port     = 8000
    certificate_name = digitalocean_certificate.reverse_tender_cert.name
  }

  forwarding_rule {
    entry_protocol  = "http"
    entry_port      = 80
    target_protocol = "http"
    target_port     = 8000
  }

  healthcheck {
    protocol               = "http"
    port                   = 8000
    path                   = "/health"
    check_interval_seconds = 10
    response_timeout_seconds = 5
    unhealthy_threshold    = 3
    healthy_threshold      = 2
  }

  droplet_ids = [
    digitalocean_droplet.app_server_1.id,
    digitalocean_droplet.app_server_2.id,
    digitalocean_droplet.app_server_3.id
  ]
}

# SSL Certificate
resource "digitalocean_certificate" "reverse_tender_cert" {
  name    = "reverse-tender-${var.environment}-cert"
  type    = "lets_encrypt"
  domains = ["reversetender.com", "www.reversetender.com"]
}

# App Servers
resource "digitalocean_droplet" "app_server_1" {
  image    = "ubuntu-22-04-x64"
  name     = "reverse-tender-${var.environment}-app-1"
  region   = var.region
  size     = "s-2vcpu-4gb"
  vpc_uuid = digitalocean_vpc.reverse_tender_vpc.id
  ssh_keys = [digitalocean_ssh_key.reverse_tender.id]

  user_data = templatefile("${path.module}/user_data/app_server.sh", {
    environment = var.environment
    server_role = "app-primary"
  })

  tags = ["reverse-tender", var.environment, "app-server", "primary"]
}

resource "digitalocean_droplet" "app_server_2" {
  image    = "ubuntu-22-04-x64"
  name     = "reverse-tender-${var.environment}-app-2"
  region   = var.region
  size     = "s-2vcpu-4gb"
  vpc_uuid = digitalocean_vpc.reverse_tender_vpc.id
  ssh_keys = [digitalocean_ssh_key.reverse_tender.id]

  user_data = templatefile("${path.module}/user_data/app_server.sh", {
    environment = var.environment
    server_role = "app-secondary"
  })

  tags = ["reverse-tender", var.environment, "app-server", "secondary"]
}

resource "digitalocean_droplet" "app_server_3" {
  image    = "ubuntu-22-04-x64"
  name     = "reverse-tender-${var.environment}-app-3"
  region   = var.region
  size     = "s-2vcpu-4gb"
  vpc_uuid = digitalocean_vpc.reverse_tender_vpc.id
  ssh_keys = [digitalocean_ssh_key.reverse_tender.id]

  user_data = templatefile("${path.module}/user_data/app_server.sh", {
    environment = var.environment
    server_role = "app-tertiary"
  })

  tags = ["reverse-tender", var.environment, "app-server", "tertiary"]
}

# Database Primary
resource "digitalocean_droplet" "db_primary" {
  image    = "ubuntu-22-04-x64"
  name     = "reverse-tender-${var.environment}-db-primary"
  region   = var.region
  size     = "s-4vcpu-8gb"
  vpc_uuid = digitalocean_vpc.reverse_tender_vpc.id
  ssh_keys = [digitalocean_ssh_key.reverse_tender.id]

  user_data = templatefile("${path.module}/user_data/db_server.sh", {
    environment = var.environment
    server_role = "db-primary"
  })

  tags = ["reverse-tender", var.environment, "database", "primary"]
}

# Database Replica
resource "digitalocean_droplet" "db_replica" {
  image    = "ubuntu-22-04-x64"
  name     = "reverse-tender-${var.environment}-db-replica"
  region   = var.region
  size     = "s-2vcpu-4gb"
  vpc_uuid = digitalocean_vpc.reverse_tender_vpc.id
  ssh_keys = [digitalocean_ssh_key.reverse_tender.id]

  user_data = templatefile("${path.module}/user_data/db_server.sh", {
    environment = var.environment
    server_role = "db-replica"
  })

  tags = ["reverse-tender", var.environment, "database", "replica"]
}

# Redis Primary
resource "digitalocean_droplet" "cache_primary" {
  image    = "ubuntu-22-04-x64"
  name     = "reverse-tender-${var.environment}-cache-primary"
  region   = var.region
  size     = "s-1vcpu-2gb"
  vpc_uuid = digitalocean_vpc.reverse_tender_vpc.id
  ssh_keys = [digitalocean_ssh_key.reverse_tender.id]

  user_data = templatefile("${path.module}/user_data/cache_server.sh", {
    environment = var.environment
    server_role = "cache-primary"
  })

  tags = ["reverse-tender", var.environment, "cache", "primary"]
}

# Redis Replica
resource "digitalocean_droplet" "cache_replica" {
  image    = "ubuntu-22-04-x64"
  name     = "reverse-tender-${var.environment}-cache-replica"
  region   = var.region
  size     = "s-1vcpu-2gb"
  vpc_uuid = digitalocean_vpc.reverse_tender_vpc.id
  ssh_keys = [digitalocean_ssh_key.reverse_tender.id]

  user_data = templatefile("${path.module}/user_data/cache_server.sh", {
    environment = var.environment
    server_role = "cache-replica"
  })

  tags = ["reverse-tender", var.environment, "cache", "replica"]
}

# Monitoring Server
resource "digitalocean_droplet" "monitoring" {
  image    = "ubuntu-22-04-x64"
  name     = "reverse-tender-${var.environment}-monitoring"
  region   = var.region
  size     = "s-2vcpu-4gb"
  vpc_uuid = digitalocean_vpc.reverse_tender_vpc.id
  ssh_keys = [digitalocean_ssh_key.reverse_tender.id]

  user_data = templatefile("${path.module}/user_data/monitoring_server.sh", {
    environment = var.environment
    server_role = "monitoring"
  })

  tags = ["reverse-tender", var.environment, "monitoring"]
}

# Block Storage for Database
resource "digitalocean_volume" "db_storage" {
  region                  = var.region
  name                    = "reverse-tender-${var.environment}-db-storage"
  size                    = 100
  initial_filesystem_type = "ext4"
  description             = "Database storage for Reverse Tender Platform"
}

resource "digitalocean_volume_attachment" "db_storage_attachment" {
  droplet_id = digitalocean_droplet.db_primary.id
  volume_id  = digitalocean_volume.db_storage.id
}

# Firewall
resource "digitalocean_firewall" "reverse_tender_firewall" {
  name = "reverse-tender-${var.environment}-firewall"

  droplet_ids = [
    digitalocean_droplet.app_server_1.id,
    digitalocean_droplet.app_server_2.id,
    digitalocean_droplet.app_server_3.id,
    digitalocean_droplet.db_primary.id,
    digitalocean_droplet.db_replica.id,
    digitalocean_droplet.cache_primary.id,
    digitalocean_droplet.cache_replica.id,
    digitalocean_droplet.monitoring.id
  ]

  inbound_rule {
    protocol         = "tcp"
    port_range       = "22"
    source_addresses = ["0.0.0.0/0", "::/0"]
  }

  inbound_rule {
    protocol         = "tcp"
    port_range       = "80"
    source_addresses = ["0.0.0.0/0", "::/0"]
  }

  inbound_rule {
    protocol         = "tcp"
    port_range       = "443"
    source_addresses = ["0.0.0.0/0", "::/0"]
  }

  inbound_rule {
    protocol    = "tcp"
    port_range  = "8000-8008"
    source_tags = ["reverse-tender"]
  }

  inbound_rule {
    protocol    = "tcp"
    port_range  = "3306"
    source_tags = ["reverse-tender"]
  }

  inbound_rule {
    protocol    = "tcp"
    port_range  = "6379"
    source_tags = ["reverse-tender"]
  }

  outbound_rule {
    protocol              = "tcp"
    port_range            = "1-65535"
    destination_addresses = ["0.0.0.0/0", "::/0"]
  }

  outbound_rule {
    protocol              = "udp"
    port_range            = "1-65535"
    destination_addresses = ["0.0.0.0/0", "::/0"]
  }
}

# Outputs
output "load_balancer_ip" {
  description = "Load balancer public IP"
  value       = digitalocean_loadbalancer.reverse_tender_lb.ip
}

output "app_server_ips" {
  description = "Application server IPs"
  value = {
    app_1 = digitalocean_droplet.app_server_1.ipv4_address
    app_2 = digitalocean_droplet.app_server_2.ipv4_address
    app_3 = digitalocean_droplet.app_server_3.ipv4_address
  }
}

output "database_ips" {
  description = "Database server IPs"
  value = {
    primary = digitalocean_droplet.db_primary.ipv4_address_private
    replica = digitalocean_droplet.db_replica.ipv4_address_private
  }
}

output "cache_ips" {
  description = "Cache server IPs"
  value = {
    primary = digitalocean_droplet.cache_primary.ipv4_address_private
    replica = digitalocean_droplet.cache_replica.ipv4_address_private
  }
}

output "monitoring_ip" {
  description = "Monitoring server IP"
  value       = digitalocean_droplet.monitoring.ipv4_address
}

