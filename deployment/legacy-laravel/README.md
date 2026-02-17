# Legacy Laravel Deployment Configurations

This directory contains legacy deployment configurations that were part of the Laravel ecosystem deployment strategies. These are preserved for reference and potential future use.

## Contents

### `cloud/`
- **Purpose**: Generic cloud deployment configurations
- **Status**: Superseded by cloud-specific directories (azure/, digitalocean/, linode/, openstack/)
- **Contents**: 
  - `cloud-architecture.md` - Generic cloud architecture documentation
  - `cloud.yml` - Generic cloud deployment configuration

### `forge/`
- **Purpose**: Laravel Forge deployment configurations
- **Status**: Not part of current multi-cloud strategy
- **Contents**:
  - `database-setup.sql` - Database initialization scripts
  - `forge-architecture.md` - Laravel Forge architecture documentation
  - `nginx-load-balancer.conf` - NGINX load balancer configuration

### `vapor/`
- **Purpose**: Laravel Vapor (serverless) deployment configurations
- **Status**: Not part of current Kubernetes/container strategy
- **Contents**:
  - `vapor-architecture.md` - Laravel Vapor architecture documentation
  - `vapor-digitalocean.yml` - Vapor deployment for DigitalOcean
  - `vapor-linode.yml` - Vapor deployment for Linode

## Migration Notes

These configurations were moved from the main deployment directory as part of the multi-cloud infrastructure consolidation. The current deployment strategy uses:

- **Cloud-specific Infrastructure**: `azure/`, `digitalocean/`, `linode/`, `openstack/`
- **Container Orchestration**: Kubernetes with Terraform/Heat IaC
- **Unified Configuration**: `shared/configs/services.yaml`

## Future Use

These configurations may be valuable for:
- Reference implementations for Laravel-specific deployment patterns
- Migration scenarios where Laravel ecosystem tools are preferred
- Hybrid deployment strategies combining container and serverless approaches
- Historical context for architectural decisions

## Related Documentation

- Current deployment documentation: `../README.md`
- Multi-cloud architecture: `../shared/README.md`
- Cloud-specific guides: `../azure/README.md`, `../digitalocean/README.md`, etc.
