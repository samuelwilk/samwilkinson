# Production Deployment Guide

Complete guide for deploying the personal brand site to production.

---

## Prerequisites

### Server Requirements

**Minimum Specifications:**
- OS: Ubuntu 22.04 LTS or newer
- CPU: 1 vCPU (2+ vCPU recommended)
- RAM: 1GB (2GB+ recommended)
- Storage: 20GB SSD minimum
- PHP: 8.3 or higher
- Web Server: Caddy 2.x (recommended) or Nginx

**Software Dependencies:**
- PHP 8.3+ with extensions: `mbstring`, `xml`, `pdo_sqlite`, `gd`, `intl`, `opcache`
- Composer 2.x
- SQLite3 (or PostgreSQL/MySQL if preferred)
- Git
- Node.js (optional, for Tailwind standalone CLI)

---

## Deployment Strategy

### Option 1: Simple VPS Deployment (Recommended)

Deploy to a single VPS (Hetzner, DigitalOcean, Linode, etc.) with Caddy for automatic HTTPS.

**Pros:**
- Simple, low-cost ($5-10/month)
- Automatic HTTPS via Caddy + Let's Encrypt
- SQLite = zero database configuration
- Easy to backup and restore

**Cons:**
- Single point of failure
- Manual scaling if traffic grows

### Option 2: Containerized Deployment

Deploy via Docker Compose on a VPS or container orchestration platform.

**Pros:**
- Reproducible environments
- Easy rollbacks
- Scalable to Kubernetes if needed

**Cons:**
- Slightly more complex setup
- Requires container registry (Docker Hub, GitHub Container Registry)

---

## Production Checklist

### 1. Server Setup

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP 8.3 and extensions
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3 php8.3-fpm php8.3-cli php8.3-mbstring \
    php8.3-xml php8.3-sqlite3 php8.3-gd php8.3-intl php8.3-opcache \
    php8.3-curl php8.3-zip

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Caddy
sudo apt install -y debian-keyring debian-archive-keyring apt-transport-https curl
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | sudo gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | sudo tee /etc/apt/sources.list.d/caddy-stable.list
sudo apt update
sudo apt install caddy

# Install Git
sudo apt install -y git
```

### 2. Application Deployment

```bash
# Create app directory
sudo mkdir -p /var/www/samwilkinson
sudo chown $USER:$USER /var/www/samwilkinson
cd /var/www/samwilkinson

# Clone repository
git clone https://github.com/YOUR_USERNAME/samwilkinson.git .

# Install dependencies
composer install --no-dev --optimize-autoloader

# Create data directory
mkdir -p var/data var/cache var/log public/uploads
chmod 775 var/data var/cache var/log public/uploads

# Configure environment
cp .env.prod.example .env.prod
# Edit .env.prod with production values
nano .env.prod

# Set proper permissions
sudo chown -R www-data:www-data var public/uploads
sudo chmod -R 775 var public/uploads
```

### 3. Database Setup

```bash
# Create production database
APP_ENV=prod php bin/console doctrine:schema:create

# Load initial fixtures (optional, for demo content)
APP_ENV=prod php bin/console doctrine:fixtures:load --no-interaction
```

### 4. Build Assets

```bash
# Build production CSS (Tailwind standalone)
cd tailwind
./tailwindcss -i input.css -o ../public/css/app.css --minify
cd ..

# Clear cache
APP_ENV=prod php bin/console cache:clear
APP_ENV=prod php bin/console cache:warmup
```

### 5. Configure Caddy

Create `/etc/caddy/Caddyfile`:

```caddy
samwilkinson.ca, www.samwilkinson.ca {
    # Automatic HTTPS via Let's Encrypt
    root * /var/www/samwilkinson/public

    # Enable gzip compression
    encode gzip

    # PHP-FPM configuration
    php_fastcgi unix//run/php/php8.3-fpm.sock {
        env APP_ENV prod
        env APP_DEBUG 0
    }

    # Static file handling
    @static {
        file
        path *.css *.js *.jpg *.jpeg *.png *.gif *.svg *.woff *.woff2 *.ttf *.ico
    }
    header @static Cache-Control "public, max-age=31536000, immutable"

    # Symfony front controller
    try_files {path} /index.php?{query}

    # Security headers
    header {
        Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
        X-Content-Type-Options "nosniff"
        X-Frame-Options "SAMEORIGIN"
        X-XSS-Protection "1; mode=block"
        Referrer-Policy "strict-origin-when-cross-origin"
        Permissions-Policy "geolocation=(), microphone=(), camera=()"
    }

    # Logging
    log {
        output file /var/log/caddy/access.log
        format json
    }
}
```

Reload Caddy:
```bash
sudo systemctl reload caddy
```

### 6. PHP-FPM Optimization

Edit `/etc/php/8.3/fpm/pool.d/www.conf`:

```ini
; Process management
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500

; Performance tuning
request_terminate_timeout = 30s
```

Edit `/etc/php/8.3/fpm/php.ini`:

```ini
; OPcache settings
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.fast_shutdown=1

; Performance
realpath_cache_size=4096K
realpath_cache_ttl=600

; Security
expose_php=Off
display_errors=Off
log_errors=On
```

Restart PHP-FPM:
```bash
sudo systemctl restart php8.3-fpm
```

### 7. Set Up Automated Backups

Create `/usr/local/bin/backup-samwilkinson.sh`:

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

# Keep only last 30 days of backups
find "$BACKUP_DIR" -type f -mtime +30 -delete

echo "Backup completed: $DATE"
```

Make executable and schedule:
```bash
sudo chmod +x /usr/local/bin/backup-samwilkinson.sh
sudo crontab -e
# Add: 0 2 * * * /usr/local/bin/backup-samwilkinson.sh >> /var/log/backup.log 2>&1
```

---

## Deployment Updates

### Zero-Downtime Deployment

```bash
#!/bin/bash
# deploy.sh - Production deployment script

set -e  # Exit on any error

echo "🚀 Starting deployment..."

# Navigate to app directory
cd /var/www/samwilkinson

# Maintenance mode (optional)
# touch var/maintenance.flag

# Pull latest code
echo "📥 Pulling latest code..."
git fetch origin
git reset --hard origin/main

# Install dependencies
echo "📦 Installing dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Run migrations (if any)
echo "🗄️  Running database migrations..."
APP_ENV=prod php bin/console doctrine:migrations:migrate --no-interaction || true

# Build assets
echo "🎨 Building production assets..."
cd tailwind && ./tailwindcss -i input.css -o ../public/css/app.css --minify && cd ..

# Clear and warm up cache
echo "🧹 Clearing cache..."
APP_ENV=prod php bin/console cache:clear --no-warmup
APP_ENV=prod php bin/console cache:warmup

# Fix permissions
echo "🔒 Setting permissions..."
sudo chown -R www-data:www-data var public/uploads
sudo chmod -R 775 var public/uploads

# Reload PHP-FPM
echo "♻️  Reloading PHP-FPM..."
sudo systemctl reload php8.3-fpm

# Remove maintenance mode
# rm -f var/maintenance.flag

echo "✅ Deployment complete!"
```

Make executable:
```bash
chmod +x deploy.sh
```

---

## Monitoring & Maintenance

### Health Check Endpoint

Create a simple health check at `/health`:

```php
// src/Controller/HealthController.php
#[Route('/health', name: 'app_health')]
public function health(): Response
{
    return new JsonResponse(['status' => 'ok', 'timestamp' => time()]);
}
```

### Log Monitoring

```bash
# Application logs
tail -f /var/www/samwilkinson/var/log/prod.log

# Caddy access logs
tail -f /var/log/caddy/access.log

# PHP-FPM logs
tail -f /var/log/php8.3-fpm.log
```

### Performance Monitoring

Consider adding:
- **Uptime monitoring**: UptimeRobot, Pingdom, or similar
- **Error tracking**: Sentry (optional)
- **Analytics**: Plausible Analytics or self-hosted Matomo

---

## Security Hardening

### Firewall (UFW)

```bash
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP (redirects to HTTPS)
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable
```

### Fail2Ban (SSH Protection)

```bash
sudo apt install fail2ban
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

### SSL/TLS

Caddy handles SSL automatically via Let's Encrypt. Verify:
```bash
curl -I https://samwilkinson.ca
# Should see: Strict-Transport-Security header
```

---

## Troubleshooting

### Common Issues

**Issue: 500 Error**
```bash
# Check logs
tail -f var/log/prod.log
# Check permissions
ls -la var/
```

**Issue: CSS not loading**
```bash
# Rebuild assets
cd tailwind && ./tailwindcss -i input.css -o ../public/css/app.css --minify
```

**Issue: Database locked (SQLite)**
```bash
# Check file permissions
ls -la var/data/database.sqlite
# Ensure www-data owns it
sudo chown www-data:www-data var/data/database.sqlite
```

---

## Rollback Procedure

```bash
# Rollback to previous git commit
git reset --hard HEAD~1

# Reinstall dependencies
composer install --no-dev --optimize-autoloader

# Restore database from backup
cp /var/backups/samwilkinson/database_YYYYMMDD_HHMMSS.sqlite var/data/database.sqlite

# Clear cache
APP_ENV=prod php bin/console cache:clear

# Reload PHP-FPM
sudo systemctl reload php8.3-fpm
```

---

## Next Steps

1. Configure DNS to point to your server IP
2. Set up automated deployments via GitHub Actions (see CI/CD docs)
3. Configure Cloudflare R2 for photo storage (if needed)
4. Set up monitoring and alerts
5. Test thoroughly before going live

**Production URL:** https://samwilkinson.ca

---

**Last Updated:** 2026-01-03
