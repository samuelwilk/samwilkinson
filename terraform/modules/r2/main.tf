# Cloudflare R2 Storage Module
# Provisions R2 bucket for photo storage with access credentials

terraform {
  required_providers {
    cloudflare = {
      source  = "cloudflare/cloudflare"
      version = "~> 4.0"
    }
  }
}

variable "cloudflare_account_id" {
  description = "Cloudflare account ID"
  type        = string
}

variable "bucket_name" {
  description = "R2 bucket name"
  type        = string
}

variable "environment" {
  description = "Environment name"
  type        = string
}

# R2 Bucket
resource "cloudflare_r2_bucket" "photos" {
  account_id = var.cloudflare_account_id
  name       = var.bucket_name
  location   = "WEUR"  # Western Europe (automatic redundancy)
}

# Note: R2 API tokens must be created manually via Cloudflare Dashboard
# Dashboard → R2 → Manage R2 API Tokens → Create API Token
# This is because creating API tokens requires additional Cloudflare permissions

# Outputs
output "bucket_name" {
  description = "R2 bucket name"
  value       = cloudflare_r2_bucket.photos.name
}

output "bucket_id" {
  description = "R2 bucket ID"
  value       = cloudflare_r2_bucket.photos.id
}

output "bucket_endpoint" {
  description = "R2 bucket S3-compatible endpoint"
  value       = "https://${var.cloudflare_account_id}.r2.cloudflarestorage.com"
}

output "access_key_id" {
  description = "Access key ID for R2 (use with AWS SDK)"
  value       = "RETRIEVE_FROM_CLOUDFLARE_DASHBOARD"
  sensitive   = true
}

output "secret_access_key" {
  description = "Secret access key for R2 (use with AWS SDK)"
  value       = "RETRIEVE_FROM_CLOUDFLARE_DASHBOARD"
  sensitive   = true
}

output "configuration_instructions" {
  description = "Instructions for configuring R2 in the application"
  value       = <<-EOT
    R2 Configuration:

    1. Get R2 credentials from Cloudflare Dashboard:
       - Go to R2 → Manage R2 API Tokens
       - Create API token with Read & Write permissions for ${var.bucket_name}
       - Save Access Key ID and Secret Access Key

    2. Add to .env.prod:
       PHOTO_STORAGE_ADAPTER=s3
       AWS_S3_ENDPOINT=https://${var.cloudflare_account_id}.r2.cloudflarestorage.com
       AWS_S3_BUCKET=${var.bucket_name}
       AWS_ACCESS_KEY_ID=<your_access_key_id>
       AWS_SECRET_ACCESS_KEY=<your_secret_access_key>
       AWS_REGION=auto

    3. (Optional) Configure custom domain for CDN:
       - R2 → ${var.bucket_name} → Settings → Custom Domains
       - Add: cdn.samwilkinson.ca
       - Update .env.prod: PHOTO_CDN_URL=https://cdn.samwilkinson.ca
  EOT
}
