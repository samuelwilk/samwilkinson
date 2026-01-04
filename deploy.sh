#!/bin/bash
# Production Deployment Script
# Usage: ./deploy.sh [environment]
# Example: ./deploy.sh production

set -e  # Exit on any error

ENV=${1:-production}
APP_DIR="/var/www/samwilkinson"

echo "🚀 Starting deployment to $ENV..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Color codes
GREEN='\033[0.32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Step 1: Pull latest code
echo -e "${YELLOW}📥 Pulling latest code...${NC}"
cd "$APP_DIR"
git fetch origin
git reset --hard origin/main
echo -e "${GREEN}✓ Code updated${NC}\n"

# Step 2: Install dependencies
echo -e "${YELLOW}📦 Installing Composer dependencies...${NC}"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
echo -e "${GREEN}✓ Dependencies installed${NC}\n"

# Step 3: Run database migrations
echo -e "${YELLOW}🗄️  Running database migrations...${NC}"
APP_ENV=prod php bin/console doctrine:migrations:migrate --no-interaction || echo "No migrations to run"
echo -e "${GREEN}✓ Migrations complete${NC}\n"

# Step 4: Build production assets
echo -e "${YELLOW}🎨 Building production CSS...${NC}"
if [ -f "tailwind/tailwindcss" ]; then
    cd tailwind
    ./tailwindcss -i input.css -o ../public/css/app.css --minify
    cd ..
    echo -e "${GREEN}✓ Assets built${NC}\n"
else
    echo -e "${YELLOW}⚠ Tailwind CLI not found, skipping CSS build${NC}\n"
fi

# Step 5: Clear and warm up cache
echo -e "${YELLOW}🧹 Clearing application cache...${NC}"
APP_ENV=prod php bin/console cache:clear --no-warmup
APP_ENV=prod php bin/console cache:warmup
echo -e "${GREEN}✓ Cache cleared and warmed up${NC}\n"

# Step 6: Fix permissions
echo -e "${YELLOW}🔒 Setting permissions...${NC}"
sudo chown -R www-data:www-data var public/uploads 2>/dev/null || \
    echo "Note: Could not set www-data ownership (may need sudo)"
sudo chmod -R 775 var public/uploads 2>/dev/null || \
    chmod -R 775 var public/uploads
echo -e "${GREEN}✓ Permissions set${NC}\n"

# Step 7: Reload PHP-FPM
echo -e "${YELLOW}♻️  Reloading PHP-FPM...${NC}"
if command -v systemctl &> /dev/null; then
    sudo systemctl reload php8.3-fpm 2>/dev/null || \
        sudo systemctl reload php-fpm 2>/dev/null || \
        echo "Could not reload PHP-FPM automatically"
    echo -e "${GREEN}✓ PHP-FPM reloaded${NC}\n"
else
    echo -e "${YELLOW}⚠ systemctl not available, skip PHP-FPM reload${NC}\n"
fi

# Step 8: Health check
echo -e "${YELLOW}🏥 Running health check...${NC}"
HEALTH_URL="https://samwilkinson.ca/health"
if command -v curl &> /dev/null; then
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$HEALTH_URL" || echo "000")
    if [ "$HTTP_CODE" = "200" ]; then
        echo -e "${GREEN}✓ Health check passed (HTTP $HTTP_CODE)${NC}\n"
    else
        echo -e "${YELLOW}⚠ Health check returned HTTP $HTTP_CODE${NC}\n"
    fi
else
    echo -e "${YELLOW}⚠ curl not available, skipping health check${NC}\n"
fi

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo -e "${GREEN}✅ Deployment complete!${NC}"
echo ""
echo "Next steps:"
echo "  - Check logs: tail -f var/log/prod.log"
echo "  - Monitor site: https://samwilkinson.ca"
echo "  - Test critical paths: /build, /stills, /studio"
echo ""
