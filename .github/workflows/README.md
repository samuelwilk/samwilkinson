# GitHub Actions CI/CD

Automated testing, building, and deployment workflows for samwilkinson.com.

---

## Workflows

### CI/CD Pipeline (`ci-cd.yml`)

**Triggers:**
- Push to `main` or `develop` branches
- Pull requests to `main`
- Manual dispatch

**Jobs:**

1. **Test** - Run full PHPUnit test suite with fixtures
2. **Code Quality** - PHP syntax checks, linting (optional: PHPStan, PHP-CS-Fixer)
3. **Security** - Composer dependency security audit
4. **Build Assets** - Compile production Tailwind CSS
5. **Deploy Production** - Automated deployment to production server (main branch only)
6. **Terraform Plan** - Show infrastructure changes on PRs

---

## Required Secrets

Configure these in GitHub: Settings → Secrets and variables → Actions

### Deployment Secrets

| Secret Name | Description | How to Get |
|-------------|-------------|------------|
| `SSH_PRIVATE_KEY` | SSH private key for server access | `cat ~/.ssh/id_ed25519` |
| `SSH_USER` | SSH username (usually `root` or `deploy`) | Server user |
| `SERVER_IP` | Production server IP address | From Terraform output or Hetzner dashboard |

### Terraform Secrets (for infrastructure changes)

| Secret Name | Description | How to Get |
|-------------|-------------|------------|
| `HETZNER_API_TOKEN` | Hetzner Cloud API token | Hetzner Console → Security → API Tokens |
| `CLOUDFLARE_API_TOKEN` | Cloudflare API token | Cloudflare Dashboard → Profile → API Tokens |
| `CLOUDFLARE_ACCOUNT_ID` | Cloudflare account ID | Cloudflare Dashboard → R2 |
| `CLOUDFLARE_ZONE_ID` | Zone ID for samwilkinson.com | Cloudflare → Domain → Overview → API |
| `SSH_PUBLIC_KEY` | SSH public key for server provisioning | `cat ~/.ssh/id_ed25519.pub` |

---

## Setup Instructions

### 1. Generate SSH Key (if you haven't already)

```bash
ssh-keygen -t ed25519 -C "deploy@samwilkinson.com"
```

### 2. Add Secrets to GitHub

```bash
# Get private key
cat ~/.ssh/id_ed25519  # Copy entire output including -----BEGIN/END-----

# Get public key
cat ~/.ssh/id_ed25519.pub  # Copy for SSH_PUBLIC_KEY secret
```

Go to GitHub repo → Settings → Secrets and variables → Actions → New repository secret

Add each secret from the tables above.

### 3. Configure Server Access

```bash
# SSH into your production server
ssh root@YOUR_SERVER_IP

# Add GitHub Actions public key to authorized_keys
echo "YOUR_SSH_PUBLIC_KEY" >> ~/.ssh/authorized_keys

# Or for deploy user:
mkdir -p /home/deploy/.ssh
echo "YOUR_SSH_PUBLIC_KEY" >> /home/deploy/.ssh/authorized_keys
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys
```

### 4. Test Workflow

Push a commit to `main` branch or create a PR to trigger the workflow:

```bash
git add .
git commit -m "Test CI/CD workflow"
git push origin main
```

Watch the workflow run: GitHub repo → Actions tab

---

## Workflow Behavior

### On Pull Request

- ✅ Run tests
- ✅ Code quality checks
- ✅ Security audit
- ✅ Build assets
- ✅ Terraform plan (shows infrastructure changes)
- ❌ **NO deployment** (safe for PRs)

### On Push to `main`

- ✅ Run tests
- ✅ Code quality checks
- ✅ Security audit
- ✅ Build assets
- ✅ **Deploy to production**
- ✅ Health check

### On Push to `develop`

- ✅ Run tests
- ✅ Code quality checks
- ✅ Security audit
- ✅ Build assets
- ❌ **NO deployment**

---

## Deployment Process

When code is pushed to `main`:

1. **Tests run** - If tests fail, deployment is skipped
2. **Assets build** - Tailwind CSS compiled and minified
3. **SSH to server** - Connect via SSH using secrets
4. **Pull latest code** - `git reset --hard origin/main`
5. **Install dependencies** - `composer install --no-dev`
6. **Run migrations** - `doctrine:migrations:migrate`
7. **Build production assets** - Tailwind CSS minified
8. **Clear cache** - Symfony cache clear + warmup
9. **Fix permissions** - Ensure www-data owns var/public/uploads
10. **Reload PHP-FPM** - Apply changes
11. **Health check** - Verify site is responding

**Deployment time:** ~30-60 seconds

---

## Health Check Endpoint

Create a health check endpoint for automated monitoring:

```php
// src/Controller/HealthController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HealthController extends AbstractController
{
    #[Route('/health', name: 'app_health')]
    public function health(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'timestamp' => time(),
            'environment' => $this->getParameter('kernel.environment'),
        ]);
    }
}
```

---

## Rollback Procedure

If deployment fails or introduces issues:

### Option 1: Revert via Git

```bash
# Locally, revert the problematic commit
git revert HEAD
git push origin main

# GitHub Actions will automatically deploy the reverted version
```

### Option 2: Manual Rollback on Server

```bash
ssh root@SERVER_IP
cd /var/www/samwilkinson

# Rollback to previous commit
git reset --hard HEAD~1

# Reinstall dependencies
composer install --no-dev --optimize-autoloader

# Clear cache
APP_ENV=prod php bin/console cache:clear

# Reload PHP-FPM
sudo systemctl reload php8.3-fpm
```

---

## Troubleshooting

### Deployment Fails with "Permission Denied"

**Cause:** SSH key not added to server

**Fix:**
```bash
ssh root@SERVER_IP
cat >> ~/.ssh/authorized_keys << EOF
YOUR_SSH_PUBLIC_KEY_HERE
EOF
```

### Tests Fail in CI

**Cause:** Missing dependencies or database issues

**Fix:** Check workflow logs in Actions tab, fix locally, then push

### Health Check Fails

**Cause:** Site not responding or error in health endpoint

**Fix:**
```bash
# SSH to server and check logs
ssh root@SERVER_IP
tail -f /var/www/samwilkinson/var/log/prod.log
tail -f /var/log/php8.3-fpm.log
```

---

## Best Practices

1. **Always test locally first** - Run `make test-phpunit` before pushing
2. **Use feature branches** - Create PRs for new features to trigger CI checks
3. **Monitor deployments** - Watch GitHub Actions tab during deployment
4. **Keep secrets secure** - Never commit secrets to repository
5. **Review Terraform plans** - Check infrastructure changes in PR comments

---

## Advanced: Blue-Green Deployment (Future Enhancement)

For zero-downtime deployments:

1. Clone app to `/var/www/samwilkinson-new`
2. Run tests on new version
3. Swap symlink: `/var/www/samwilkinson` → `/var/www/samwilkinson-new`
4. Reload PHP-FPM
5. Keep old version for instant rollback

---

**Last Updated:** 2026-01-03
