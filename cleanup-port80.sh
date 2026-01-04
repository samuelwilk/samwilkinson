#!/bin/bash
# One-time cleanup script for port 80 conflict
# Run this on the VPS to remove orphan containers blocking port 80

set -e

echo "🔍 Step 1: Identifying what's using port 80..."
echo ""
echo "=== Port 80 listeners ==="
sudo ss -ltnp '( sport = :80 )' || echo "No listeners on port 80 (good!)"
echo ""

echo "=== Docker containers using port 80 ==="
docker ps --format 'table {{.Names}}\t{{.Ports}}\t{{.Status}}' | grep -E '(:80->|0.0.0.0:80)' || echo "No Docker containers using port 80 (good!)"
echo ""

echo "🧹 Step 2: Removing old/orphan containers..."
docker rm -f samwilkinson-web 2>/dev/null && echo "✅ Removed samwilkinson-web" || echo "⏭️  samwilkinson-web not found"
docker rm -f samwilkinson_web 2>/dev/null && echo "✅ Removed samwilkinson_web" || echo "⏭️  samwilkinson_web not found"
docker rm -f samwilkinson-caddy 2>/dev/null && echo "✅ Removed samwilkinson-caddy" || echo "⏭️  samwilkinson-caddy not found"
docker rm -f samwilkinson-app 2>/dev/null && echo "✅ Removed samwilkinson-app" || echo "⏭️  samwilkinson-app not found"
echo ""

echo "🛑 Step 3: Checking for host services (nginx/apache/caddy)..."
if sudo systemctl is-active --quiet nginx 2>/dev/null; then
    echo "⚠️  nginx is running - stopping and disabling..."
    sudo systemctl disable --now nginx
    echo "✅ nginx stopped"
elif sudo systemctl is-active --quiet apache2 2>/dev/null; then
    echo "⚠️  apache2 is running - stopping and disabling..."
    sudo systemctl disable --now apache2
    echo "✅ apache2 stopped"
elif sudo systemctl is-active --quiet caddy 2>/dev/null; then
    echo "⚠️  caddy is running - stopping and disabling..."
    sudo systemctl disable --now caddy
    echo "✅ caddy stopped"
else
    echo "✅ No host web servers running"
fi
echo ""

echo "✅ Step 4: Final verification - port 80 should be free:"
sudo ss -ltnp '( sport = :80 )' || echo "✅ Port 80 is FREE - ready for deployment!"
echo ""
echo "🚀 You can now re-run the deployment"
