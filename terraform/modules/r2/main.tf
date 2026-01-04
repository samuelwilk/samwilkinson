# Cloudflare R2 Storage Module
# Provisions R2 bucket for photo storage with access credentials

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

# API Token for R2 access
resource "cloudflare_api_token" "r2_access" {
  name = "${var.bucket_name}-access-token"

  policy {
    permission_groups = [
      data.cloudflare_api_token_permission_groups.all.r2["Workers R2 Storage Write"],
      data.cloudflare_api_token_permission_groups.all.r2["Workers R2 Storage Read"],
    ]

    resources = {
      "com.cloudflare.edge.r2.bucket.${var.cloudflare_account_id}_default_${var.bucket_name}" = "*"
    }
  }
}

# Permission groups data source
data "cloudflare_api_token_permission_groups" "all" {}

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
