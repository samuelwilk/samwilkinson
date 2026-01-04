# Infrastructure as Code - Terraform

Terraform configuration for provisioning production infrastructure for samwilkinson.com.

---

## Architecture

**Production Stack:**
- **Compute**: Hetzner Cloud VPS (CPX11: 2 vCPU, 2GB RAM, 40GB SSD)
- **Storage**: Cloudflare R2 (S3-compatible object storage for photos)
- **Web Server**: Caddy 2.x (automatic HTTPS via Let's Encrypt)
- **Runtime**: PHP 8.3-FPM
- **Database**: SQLite (local file-based)
- **DNS**: Cloudflare

**Monthly Cost**: ~€5-7 ($5.50-7.50 USD)
- Hetzner CPX11: €4.51/month
- Cloudflare R2: ~$0.015/GB stored + $0.36/million Class A operations (free tier: 10GB storage, 1M Class A ops)

---

## Prerequisites

1. **Terraform** >= 1.6
   ```bash
   # macOS
   brew install terraform

   # Linux
   wget https://releases.hashicorp.com/terraform/1.6.6/terraform_1.6.6_linux_amd64.zip
   unzip terraform_1.6.6_linux_amd64.zip
   sudo mv terraform /usr/local/bin/
   ```

2. **Hetzner Cloud Account**
   - Sign up: https://console.hetzner.cloud/
   - Create API token: Security → API Tokens → Generate API Token
   - Permissions: Read & Write

3. **Cloudflare Account**
   - Sign up: https://dash.cloudflare.com/sign-up
   - Add domain: samwilkinson.com
   - Create API token: Profile → API Tokens → Create Token
   - Permissions: Zone.DNS (Edit), Account.R2 (Edit)

4. **SSH Key**
   ```bash
   ssh-keygen -t ed25519 -C "your_email@example.com"
   cat ~/.ssh/id_ed25519.pub  # Copy this for terraform.tfvars
   ```

---

## Quick Start

### 1. Configure Variables

```bash
cd terraform/environments/production
cp terraform.tfvars.example terraform.tfvars
# Edit terraform.tfvars with your credentials
nano terraform.tfvars
```

### 2. Initialize Terraform

```bash
terraform init
```

### 3. Plan Infrastructure

```bash
terraform plan
```

Review the planned changes. You should see:
- 1 Hetzner server
- 1 Hetzner SSH key
- 1 Hetzner firewall
- 1 Cloudflare R2 bucket
- 2 Cloudflare DNS records (@ and www)

### 4. Apply Configuration

```bash
terraform apply
```

Type `yes` to confirm. Terraform will:
1. Create VPS server on Hetzner
2. Configure firewall rules
3. Install PHP 8.3, Caddy, Composer via cloud-init
4. Create R2 bucket on Cloudflare
5. Configure DNS records

**This takes ~3-5 minutes.**

### 5. Get Outputs

```bash
terraform output
```

Save these values:
- `server_ip`: SSH to this IP
- `r2_bucket_name`: R2 bucket for photos
- `deployment_instructions`: Next steps

---

## Post-Provisioning Setup

### 1. Wait for Cloud-Init

SSH into the server and wait for cloud-init to complete:

```bash
ssh root@$(terraform output -raw server_ip)

# Check cloud-init status
cloud-init status --wait

# Verify installations
php -v          # Should show PHP 8.3.x
caddy version   # Should show Caddy v2.x
composer --version
```

### 2. Clone and Deploy Application

```bash
# Create application directory
cd /var/www/samwilkinson

# Clone repository
git clone https://github.com/YOUR_USERNAME/samwilkinson.git .

# Install dependencies
composer install --no-dev --optimize-autoloader

# Configure environment
cp .env.prod.example .env.prod
nano .env.prod  # Add APP_SECRET, R2 credentials, etc.

# Set permissions
chown -R www-data:www-data var public/uploads
chmod -R 775 var public/uploads

# Create database
APP_ENV=prod php bin/console doctrine:schema:create

# Build assets
cd tailwind && ./tailwindcss -i input.css -o ../public/css/app.css --minify && cd ..

# Clear cache
APP_ENV=prod php bin/console cache:clear
```

### 3. Configure Caddy

Create `/etc/caddy/Caddyfile`:

```caddy
samwilkinson.com, www.samwilkinson.com {
    root * /var/www/samwilkinson/public
    encode gzip

    php_fastcgi unix//run/php/php8.3-fpm.sock {
        env APP_ENV prod
    }

    @static file
    header @static Cache-Control "public, max-age=31536000"

    try_files {path} /index.php?{query}

    header {
        Strict-Transport-Security "max-age=31536000"
        X-Content-Type-Options "nosniff"
        X-Frame-Options "SAMEORIGIN"
    }
}
```

Reload Caddy:
```bash
systemctl reload caddy
```

### 4. Test Site

```bash
curl -I https://samwilkinson.com
# Should return 200 OK with HTTPS
```

---

## R2 Configuration

### Get R2 Credentials

1. Go to Cloudflare Dashboard → R2
2. Click "Manage R2 API Tokens"
3. Create token with Read & Write for `samwilkinson-photos` bucket
4. Save Access Key ID and Secret Access Key

### Configure in Application

Add to `/var/www/samwilkinson/.env.prod`:

```bash
PHOTO_STORAGE_ADAPTER=s3
AWS_S3_ENDPOINT=https://[ACCOUNT_ID].r2.cloudflarestorage.com
AWS_S3_BUCKET=samwilkinson-photos
AWS_ACCESS_KEY_ID=your_access_key_id
AWS_SECRET_ACCESS_KEY=your_secret_access_key
AWS_REGION=auto
```

### (Optional) Custom CDN Domain

1. R2 → samwilkinson-photos → Settings → Custom Domains
2. Add: `cdn.samwilkinson.com`
3. Update `.env.prod`: `PHOTO_CDN_URL=https://cdn.samwilkinson.com`

---

## Terraform Commands

```bash
# Show current state
terraform show

# List resources
terraform state list

# Get specific output
terraform output server_ip

# Refresh state
terraform refresh

# Destroy infrastructure (⚠️ DANGER)
terraform destroy
```

---

## Modules

### VPS Module (`modules/vps`)

Provisions Hetzner Cloud server with:
- Ubuntu 24.04 LTS
- PHP 8.3-FPM with extensions
- Caddy web server
- Composer
- Firewall (SSH, HTTP, HTTPS)
- Cloud-init for automated setup

### R2 Module (`modules/r2`)

Provisions Cloudflare R2 bucket for photo storage with S3-compatible API.

---

## Monitoring & Maintenance

### Server Health

```bash
# SSH into server
ssh root@$(terraform output -raw server_ip)

# Check services
systemctl status php8.3-fpm caddy

# View logs
tail -f /var/log/caddy/access.log
tail -f /var/log/php8.3-fpm.log
```

### Backups

Automated daily backups via cron (configured in production setup docs).

### Scaling

If traffic grows:
1. Upgrade server type in terraform.tfvars: `cpx11` → `cpx21` (4 vCPU, 4GB RAM)
2. Run `terraform apply`
3. Server will be recreated (brief downtime)

---

## Troubleshooting

**Issue: `terraform init` fails**
```bash
# Clear cache and retry
rm -rf .terraform .terraform.lock.hcl
terraform init
```

**Issue: Cloud-init not completing**
```bash
# Check cloud-init logs
ssh root@SERVER_IP
tail -f /var/log/cloud-init-output.log
```

**Issue: DNS not resolving**
```bash
# Verify DNS records
dig samwilkinson.com
dig www.samwilkinson.com

# Check Cloudflare dashboard for DNS propagation
```

---

## Security Notes

- **Never commit terraform.tfvars** (contains secrets)
- Store state file securely (use remote backend for production)
- Rotate API tokens regularly
- Enable 2FA on Hetzner and Cloudflare accounts
- Use SSH keys only (disable password auth)

---

## Cost Optimization

**Current Setup**: ~€5/month
- Hetzner CPX11: €4.51/month
- R2: Free tier covers moderate usage

**If scaling needed**:
- CPX21 (4 vCPU, 4GB): €8.21/month
- CPX31 (8 vCPU, 8GB): €15.41/month
- R2 overage: $0.015/GB stored

---

**Last Updated**: 2026-01-03
