# OpenStack Terraform Configuration for Reverse Tender Platform
# This file orchestrates the deployment of microservices on OpenStack

terraform {
  required_version = ">= 1.6"
  required_providers {
    openstack = {
      source  = "terraform-provider-openstack/openstack"
      version = "~> 1.54"
    }
    kubernetes = {
      source  = "hashicorp/kubernetes"
      version = "~> 2.24"
    }
    helm = {
      source  = "hashicorp/helm"
      version = "~> 2.12"
    }
    external = {
      source  = "hashicorp/external"
      version = "~> 2.3"
    }
  }
}

# Configure the OpenStack Provider
provider "openstack" {
  user_name           = var.os_username
  password            = var.os_password
  auth_url            = var.os_auth_url
  tenant_id           = var.os_tenant_id
  tenant_name         = var.os_tenant_name
  user_domain_name    = var.os_user_domain_name
  project_domain_name = var.os_project_domain_name
  region              = var.os_region_name
}

# Local variables for common configuration
locals {
  project_name = var.project_name
  environment  = var.environment
  region       = var.os_region_name
  
  common_tags = [
    "${local.project_name}:${local.environment}",
    "managed-by:terraform",
    "project:${local.project_name}",
    "environment:${local.environment}",
    "cloud-provider:openstack"
  ]
}

# Data source for available flavors
data "openstack_compute_flavor_v2" "main" {
  name = var.heat_flavor
}

# Data source for available images
data "openstack_images_image_v2" "main" {
  name        = var.heat_image
  most_recent = true
}

# Data source for networks
data "openstack_networking_network_v2" "external" {
  name     = "external"
  external = true
}

data "openstack_networking_network_v2" "internal" {
  name = var.heat_network
}

# Security group for the application
resource "openstack_networking_secgroup_v2" "main" {
  name        = "${local.project_name}-sg-${local.environment}"
  description = "Security group for ${local.project_name} ${local.environment}"
  region      = local.region
}

# Security group rules
resource "openstack_networking_secgroup_rule_v2" "http" {
  direction         = "ingress"
  ethertype         = "IPv4"
  protocol          = "tcp"
  port_range_min    = 80
  port_range_max    = 80
  remote_ip_prefix  = "0.0.0.0/0"
  security_group_id = openstack_networking_secgroup_v2.main.id
}

resource "openstack_networking_secgroup_rule_v2" "https" {
  direction         = "ingress"
  ethertype         = "IPv4"
  protocol          = "tcp"
  port_range_min    = 443
  port_range_max    = 443
  remote_ip_prefix  = "0.0.0.0/0"
  security_group_id = openstack_networking_secgroup_v2.main.id
}

resource "openstack_networking_secgroup_rule_v2" "ssh" {
  direction         = "ingress"
  ethertype         = "IPv4"
  protocol          = "tcp"
  port_range_min    = 22
  port_range_max    = 22
  remote_ip_prefix  = "10.0.0.0/8"
  security_group_id = openstack_networking_secgroup_v2.main.id
}

resource "openstack_networking_secgroup_rule_v2" "kubernetes_api" {
  direction         = "ingress"
  ethertype         = "IPv4"
  protocol          = "tcp"
  port_range_min    = 6443
  port_range_max    = 6443
  remote_ip_prefix  = "10.0.0.0/8"
  security_group_id = openstack_networking_secgroup_v2.main.id
}

# Key pair for instances
resource "openstack_compute_keypair_v2" "main" {
  name       = "${local.project_name}-key-${local.environment}"
  region     = local.region
  public_key = file("~/.ssh/id_rsa.pub")
}

# Floating IP for load balancer
resource "openstack_networking_floatingip_v2" "main" {
  pool   = data.openstack_networking_network_v2.external.name
  region = local.region
  
  tags = local.common_tags
}

# Output values for integration
output "openstack_infrastructure" {
  description = "OpenStack infrastructure details"
  value = {
    region              = local.region
    project_id          = var.os_project_id
    project_name        = var.os_project_name
    security_group_id   = openstack_networking_secgroup_v2.main.id
    key_pair_name       = openstack_compute_keypair_v2.main.name
    floating_ip         = openstack_networking_floatingip_v2.main.address
    floating_network_id = data.openstack_networking_network_v2.external.id
    internal_network_id = data.openstack_networking_network_v2.internal.id
    
    # For Heat stack parameters
    heat_parameters = {
      flavor         = var.heat_flavor
      image          = var.heat_image
      key_name       = openstack_compute_keypair_v2.main.name
      network        = data.openstack_networking_network_v2.internal.name
      security_group = openstack_networking_secgroup_v2.main.name
      environment    = local.environment
      project_name   = local.project_name
    }
  }
}
