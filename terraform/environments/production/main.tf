# Production Infrastructure for samwilkinson.com
# Terraform configuration for VPS + Cloudflare R2 storage

terraform {
  required_version = ">= 1.6"

  required_providers {
    hcloud = {
      source  = "hetznercloud/hcloud"
      version = "~> 1.45"
    }
    cloudflare = {
      source  = "cloudflare/cloudflare"
      version = "~> 4.0"
    }
  }

  # Remote state storage (recommended for production)
  # backend "s3" {
  #   bucket = "terraform-state-samwilkinson"
  #   key    = "production/terraform.tfstate"
  #   region = "auto"
  #   endpoints = {
  #     s3 = "https://[account-id].r2.cloudflarestorage.com"
  #   }
  #   skip_credentials_validation = true
  #   skip_region_validation      = true
  #   skip_requesting_account_id  = true
  # }
}

# Providers
provider "hcloud" {
  token = var.hetzner_api_token
}

provider "cloudflare" {
  api_token = var.cloudflare_api_token
}

# Variables
variable "hetzner_api_token" {
  description = "Hetzner Cloud API token"
  type        = string
  sensitive   = true
}

variable "cloudflare_api_token" {
  description = "Cloudflare API token"
  type        = string
  sensitive   = true
}

variable "cloudflare_account_id" {
  description = "Cloudflare account ID"
  type        = string
}

variable "cloudflare_zone_id" {
  description = "Cloudflare zone ID for samwilkinson.ca"
  type        = string
}

variable "ssh_public_key" {
  description = "SSH public key for server access"
  type        = string
}

# Modules

# VPS Server
module "vps" {
  source = "../../modules/vps"

  server_name    = "samwilkinson-prod"
  server_type    = "cpx11"  # 2 vCPU, 2GB RAM, 40GB SSD - €4.51/month
  location       = "nbg1"    # Nuremberg, Germany
  ssh_public_key = var.ssh_public_key
  environment    = "production"
}

# Cloudflare R2 Storage
module "r2_storage" {
  source = "../../modules/r2"

  cloudflare_account_id = var.cloudflare_account_id
  bucket_name           = "samwilkinson-photos"
  environment           = "production"
}

# DNS Configuration
resource "cloudflare_record" "root" {
  zone_id = var.cloudflare_zone_id
  name    = "@"
  type    = "A"
  value   = module.vps.server_ip
  proxied = false  # Direct to server for Caddy HTTPS
  ttl     = 1      # Auto TTL
}

resource "cloudflare_record" "www" {
  zone_id = var.cloudflare_zone_id
  name    = "www"
  type    = "A"
  value   = module.vps.server_ip
  proxied = false
  ttl     = 1
}

# Outputs
output "server_ip" {
  description = "Production server IP address"
  value       = module.vps.server_ip
}

output "r2_bucket_name" {
  description = "R2 bucket name for photo storage"
  value       = module.r2_storage.bucket_name
}

output "r2_access_key_id" {
  description = "R2 access key ID"
  value       = module.r2_storage.access_key_id
  sensitive   = true
}

output "r2_secret_access_key" {
  description = "R2 secret access key"
  value       = module.r2_storage.secret_access_key
  sensitive   = true
}

output "deployment_instructions" {
  description = "Next steps after infrastructure provisioning"
  value       = <<-EOT
    ✅ Infrastructure provisioned successfully!

    Server IP: ${module.vps.server_ip}
    R2 Bucket: ${module.r2_storage.bucket_name}

    Next steps:
    1. SSH into server: ssh root@${module.vps.server_ip}
    2. Run setup script: curl -sSL https://raw.githubusercontent.com/YOUR_USERNAME/samwilkinson/main/scripts/server-setup.sh | bash
    3. Deploy application: cd /var/www/samwilkinson && ./deploy.sh
    4. Configure R2 credentials in .env.prod
    5. Test site: https://samwilkinson.ca

    DNS Configuration:
    - @ (root) → ${module.vps.server_ip}
    - www → ${module.vps.server_ip}
  EOT
}
