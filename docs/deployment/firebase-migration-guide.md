# Firebase to New Infrastructure Migration Guide

Complete step-by-step guide for migrating samwilkinson.ca from Firebase Hosting to the new Symfony-based infrastructure.

---

## Overview

**Current Setup:**
- Domain: samwilkinson.ca
- Hosting: Firebase Hosting
- DNS: Firebase/Google Cloud

**New Setup:**
- Domain: samwilkinson.ca (same)
- Hosting: Hetzner Cloud VPS with Caddy
- DNS: Cloudflare (for better control and R2 integration)
- Infrastructure: Terraform-managed

**Migration Strategy:** Blue-green deployment with DNS cutover (minimal downtime)

---

## Pre-Migration Checklist

### 1. Backup Current Firebase Site

```bash
# Install Firebase CLI if not already installed
npm install -g firebase-tools

# Login to Firebase
firebase login

# Download current site content
firebase hosting:clone samwilkinson-ca:live /tmp/firebase-backup

# Or manually download via Firebase Console:
# https://console.firebase.google.com/ → Hosting → Files
```

### 2. Document Current Configuration

Document your current setup:
- Firebase project ID
- Custom domain configuration
- Environment variables (if any)
- Redirects/rewrites rules
- Security headers

### 3. Check DNS Records

```bash
# Current DNS records
dig samwilkinson.ca
dig www.samwilkinson.ca

# Note current IPs and CNAME targets
```

### 4. Inform Users (Optional)

If you have significant traffic, consider:
- Social media announcement
- Temporary maintenance notice
- Status page update

---

## Migration Steps

### Phase 1: Provision New Infrastructure (No Impact on Live Site)

#### Step 1: Transfer DNS to Cloudflare

1. **Sign up for Cloudflare** (if you don't have an account)
   - Go to: https://dash.cloudflare.com/sign-up
   - Free plan is sufficient

2. **Add samwilkinson.ca to Cloudflare**
   - Dashboard → Add Site
   - Enter: `samwilkinson.ca`
   - Select Free plan

3. **Copy existing DNS records**
   Cloudflare will scan and import your current DNS records from Firebase.

   **Important:** DO NOT change nameservers yet! Just set up the zone.

4. **Note Cloudflare Information**
   Save these for later:
   - Zone ID (samwilkinson.ca → Overview → API section)
   - Account ID (R2 → Overview)
   - Nameservers (you'll use these later)

#### Step 2: Create Cloudflare API Token

1. Go to: https://dash.cloudflare.com/profile/api-tokens
2. Click "Create Token"
3. Use "Edit zone DNS" template
4. Permissions:
   - Zone → DNS → Edit
   - Account → Cloudflare R2 → Edit
5. Zone Resources: Include → Specific zone → samwilkinson.ca
6. Create Token and save it securely

#### Step 3: Get Hetzner API Token

1. Sign up at: https://console.hetzner.cloud/
2. Create new project: "samwilkinson-prod"
3. Go to: Security → API Tokens
4. Generate API Token with Read & Write permissions
5. Save token securely

#### Step 4: Configure Terraform

```bash
cd terraform/environments/production

# Copy example file
cp terraform.tfvars.example terraform.tfvars

# Edit with your credentials
nano terraform.tfvars
```

Fill in:
```hcl
hetzner_api_token     = "YOUR_HETZNER_TOKEN"
cloudflare_api_token  = "YOUR_CLOUDFLARE_TOKEN"
cloudflare_account_id = "YOUR_CLOUDFLARE_ACCOUNT_ID"
cloudflare_zone_id    = "YOUR_CLOUDFLARE_ZONE_ID"
ssh_public_key        = "ssh-ed25519 AAAA... your_email@example.com"
```

#### Step 5: Provision Infrastructure

```bash
# Initialize Terraform
terraform init

# Preview changes
terraform plan

# Apply (this creates the VPS but doesn't affect DNS yet)
terraform apply
```

**What this does:**
- Creates VPS on Hetzner
- Installs PHP 8.3, Caddy, Composer
- Creates R2 bucket on Cloudflare
- **Does NOT change DNS** (site still on Firebase)

**Save the server IP:**
```bash
terraform output server_ip
# Note this IP address
```

#### Step 6: Deploy Application to New Server

```bash
# SSH to new server
ssh root@$(terraform output -raw server_ip)

# Wait for cloud-init to complete
cloud-init status --wait

# Clone repository
cd /var/www/samwilkinson
git clone https://github.com/YOUR_USERNAME/samwilkinson.git .

# Configure environment
cp .env.prod.example .env.prod

# Generate APP_SECRET
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
# Copy output and add to .env.prod

# Edit .env.prod
nano .env.prod
```

**.env.prod configuration:**
```bash
APP_ENV=prod
APP_SECRET=<generated_secret>
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data/database.sqlite"
TRUSTED_HOSTS=^samwilkinson\.ca$,^www\.samwilkinson\.ca$
```

```bash
# Install dependencies
composer install --no-dev --optimize-autoloader

# Create database
mkdir -p var/data
APP_ENV=prod php bin/console doctrine:schema:create

# Load initial fixtures (optional - for demo content)
APP_ENV=prod php bin/console doctrine:fixtures:load --no-interaction

# Build assets
cd tailwind
curl -sLO https://github.com/tailwindlabs/tailwindcss/releases/latest/download/tailwindcss-linux-x64
mv tailwindcss-linux-x64 tailwindcss
chmod +x tailwindcss
./tailwindcss -i input.css -o ../public/css/app.css --minify
cd ..

# Set permissions
chown -R www-data:www-data var public/uploads
chmod -R 775 var public/uploads

# Clear cache
APP_ENV=prod php bin/console cache:clear
APP_ENV=prod php bin/console cache:warmup
```

#### Step 7: Configure Caddy

```bash
# Create Caddyfile
nano /etc/caddy/Caddyfile
```

**Caddyfile content:**
```caddy
samwilkinson.ca, www.samwilkinson.ca {
    root * /var/www/samwilkinson/public
    encode gzip

    php_fastcgi unix//run/php/php8.3-fpm.sock {
        env APP_ENV prod
        env APP_DEBUG 0
    }

    @static file
    header @static Cache-Control "public, max-age=31536000, immutable"

    try_files {path} /index.php?{query}

    header {
        Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
        X-Content-Type-Options "nosniff"
        X-Frame-Options "SAMEORIGIN"
        X-XSS-Protection "1; mode=block"
        Referrer-Policy "strict-origin-when-cross-origin"
    }

    log {
        output file /var/log/caddy/access.log
        format json
    }
}
```

```bash
# Reload Caddy
systemctl reload caddy

# Verify configuration
systemctl status caddy
```

#### Step 8: Test New Server (Before DNS Switch)

```bash
# Get server IP
SERVER_IP=$(terraform output -raw server_ip)

# Test via IP (add host header)
curl -H "Host: samwilkinson.ca" http://$SERVER_IP

# Or add to /etc/hosts locally for browser testing
echo "$SERVER_IP samwilkinson.ca" | sudo tee -a /etc/hosts

# Visit http://samwilkinson.ca in browser
# Test all pages: /build, /stills, /studio

# Remove from hosts when done testing
sudo sed -i '/samwilkinson.ca/d' /etc/hosts
```

**Verify everything works:**
- ✅ Homepage loads
- ✅ /build shows projects
- ✅ /studio shows posts
- ✅ /stills loads
- ✅ CSS loads correctly
- ✅ No errors in browser console

---

### Phase 2: DNS Cutover (5-10 minutes downtime)

#### Step 9: Update DNS to Point to New Server

**Option A: Cloudflare Dashboard (Recommended - Faster)**

1. Go to Cloudflare Dashboard → samwilkinson.ca → DNS
2. Delete Firebase DNS records:
   - Delete any A records pointing to Firebase IPs
   - Delete any CNAME records pointing to Firebase domains
3. Add new A records:
   - **A record:** Name: `@`, Content: `<your_server_ip>`, Proxy: OFF (orange cloud disabled)
   - **A record:** Name: `www`, Content: `<your_server_ip>`, Proxy: OFF
4. TTL: Auto (or 1 minute for faster propagation)

**Option B: Terraform (Automated)**

Terraform already created the DNS records when you ran `terraform apply`, but they may be overridden by Firebase records. Update them:

```bash
cd terraform/environments/production

# Force recreation of DNS records
terraform taint cloudflare_record.root
terraform taint cloudflare_record.www
terraform apply
```

#### Step 10: Update Nameservers at Domain Registrar

**This is the critical step that switches DNS control from Firebase/Google to Cloudflare.**

1. Log into your domain registrar (where you purchased samwilkinson.ca)
   - Common Canadian registrars: Namecheap, GoDaddy, Domain.com, etc.

2. Find DNS/Nameserver settings for samwilkinson.ca

3. Change nameservers to Cloudflare's:
   ```
   NAMESERVER 1: aaron.ns.cloudflare.com
   NAMESERVER 2: june.ns.cloudflare.com
   ```
   *(Your actual Cloudflare nameservers are shown in Cloudflare Dashboard)*

4. Save changes

**⏱️ Propagation time:** 5 minutes to 24 hours (usually <1 hour)

#### Step 11: Monitor DNS Propagation

```bash
# Check if DNS has switched
dig samwilkinson.ca +short

# Should show your new server IP, not Firebase IP

# Check from multiple locations
curl https://dns.google/resolve?name=samwilkinson.ca&type=A

# Online tools:
# https://www.whatsmydns.net/#A/samwilkinson.ca
```

#### Step 12: Enable HTTPS (Automatic via Caddy)

Once DNS points to your server:

```bash
# Caddy will automatically request Let's Encrypt certificate
# Check logs
journalctl -u caddy -f

# Verify HTTPS works
curl -I https://samwilkinson.ca

# Should return 200 OK with HTTPS headers
```

---

### Phase 3: Cleanup and Optimization

#### Step 13: Verify Site is Live

Test all functionality:
- [ ] https://samwilkinson.ca loads
- [ ] https://www.samwilkinson.ca redirects to main domain
- [ ] /build section works
- [ ] /stills section works
- [ ] /studio section works
- [ ] SSL certificate is valid (green lock)
- [ ] No mixed content warnings

#### Step 14: Disable Firebase Hosting

**Only after confirming new site works!**

```bash
# Disconnect custom domain in Firebase Console
firebase hosting:sites:delete samwilkinson-ca

# Or via console:
# https://console.firebase.google.com/ → Hosting → Custom Domains
# Remove samwilkinson.ca
```

#### Step 15: Enable Cloudflare Features (Optional)

Now that DNS is on Cloudflare:

1. **Enable Proxy (Orange Cloud)** - For DDoS protection and caching
   - Dashboard → DNS → Toggle orange cloud for A records
   - This routes traffic through Cloudflare's CDN

2. **Configure SSL/TLS**
   - SSL/TLS → Overview → Set to "Full (strict)"

3. **Enable Security Features**
   - Security → WAF → Enable managed rules
   - Under Attack Mode (if needed)

4. **Page Rules** (if needed)
   - Force HTTPS
   - Cache everything for static assets

#### Step 16: Set Up Monitoring

1. **Uptime Monitoring**
   - UptimeRobot: https://uptimerobot.com/ (free)
   - Add: https://samwilkinson.ca/health

2. **Cloudflare Analytics**
   - Dashboard → Analytics → Traffic

3. **Server Monitoring**
   ```bash
   ssh root@SERVER_IP

   # Install htop for resource monitoring
   apt install htop
   htop
   ```

#### Step 17: Configure Automated Backups

```bash
# On server
nano /usr/local/bin/backup-samwilkinson.sh
```

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/samwilkinson"
APP_DIR="/var/www/samwilkinson"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p "$BACKUP_DIR"

# Backup database
cp "$APP_DIR/var/data/database.sqlite" "$BACKUP_DIR/database_$DATE.sqlite"

# Backup uploads
tar -czf "$BACKUP_DIR/uploads_$DATE.tar.gz" -C "$APP_DIR/public" uploads

# Keep only last 30 days
find "$BACKUP_DIR" -type f -mtime +30 -delete

echo "Backup completed: $DATE"
```

```bash
# Make executable
chmod +x /usr/local/bin/backup-samwilkinson.sh

# Add to crontab
crontab -e
# Add: 0 2 * * * /usr/local/bin/backup-samwilkinson.sh >> /var/log/backup.log 2>&1
```

---

## Rollback Plan

If something goes wrong during migration:

### Quick Rollback (Emergency)

1. **Revert DNS in Cloudflare**
   - Dashboard → DNS
   - Change A records back to Firebase IPs (if you noted them)

2. **Or revert nameservers at registrar**
   - Change back to Firebase/Google nameservers
   - Traffic goes back to old site

### Complete Rollback

```bash
# Change nameservers back to Firebase
# At domain registrar → DNS settings

# Delete Terraform infrastructure (optional)
cd terraform/environments/production
terraform destroy
```

---

## Post-Migration Checklist

- [ ] Site accessible via https://samwilkinson.ca
- [ ] SSL certificate valid
- [ ] All pages load correctly
- [ ] No broken links or images
- [ ] Firebase hosting disabled
- [ ] Uptime monitoring configured
- [ ] Automated backups running
- [ ] GitHub Actions CI/CD working
- [ ] Health check endpoint responding
- [ ] Analytics/monitoring active

---

## Troubleshooting

### Site Not Loading After DNS Change

**Check:**
```bash
# Verify DNS propagation
dig samwilkinson.ca +short

# Should show new server IP
```

**If still showing old IP:**
- Clear DNS cache: `sudo dscacheutil -flushcache` (macOS)
- Wait longer (up to 24 hours for full propagation)
- Check with online DNS checker

### SSL Certificate Not Working

**Caddy needs DNS to point to server for Let's Encrypt:**

```bash
# Check Caddy logs
journalctl -u caddy -n 50

# Manually trigger certificate
systemctl restart caddy
```

### Site Shows Error 500

```bash
# Check application logs
ssh root@SERVER_IP
tail -f /var/www/samwilkinson/var/log/prod.log

# Check PHP-FPM logs
tail -f /var/log/php8.3-fpm.log

# Verify permissions
ls -la /var/www/samwilkinson/var
```

### Database Not Found

```bash
# Recreate database
cd /var/www/samwilkinson
APP_ENV=prod php bin/console doctrine:schema:create
APP_ENV=prod php bin/console doctrine:fixtures:load --no-interaction
```

---

## Timeline Estimate

| Phase | Task | Duration |
|-------|------|----------|
| Phase 1 | Cloudflare setup | 15 min |
| Phase 1 | Terraform provisioning | 5 min |
| Phase 1 | Application deployment | 20 min |
| Phase 1 | Testing new server | 15 min |
| **Phase 2** | **DNS cutover** | **5-10 min** |
| Phase 2 | DNS propagation | 5 min - 24 hrs |
| Phase 2 | HTTPS activation | 2-5 min |
| Phase 3 | Verification | 10 min |
| Phase 3 | Firebase cleanup | 5 min |

**Total active work:** ~1.5 hours
**Actual downtime:** 5-15 minutes (during DNS cutover)

---

## Support Resources

- **Cloudflare Docs:** https://developers.cloudflare.com/
- **Hetzner Docs:** https://docs.hetzner.com/
- **Caddy Docs:** https://caddyserver.com/docs/
- **Symfony Docs:** https://symfony.com/doc/current/index.html

---

**Migration completed:** Test thoroughly and enjoy your new infrastructure!

**Last Updated:** 2026-01-03
