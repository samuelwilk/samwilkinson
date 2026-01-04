# Production Setup Guide

This guide covers initial production setup tasks that must be run manually on the server.

## Prerequisites

- Deployment has completed successfully
- Database migrations have run
- Containers are healthy

## 1. Create Admin User

Create an admin user to access the EasyAdmin dashboard at `/admin`:

```bash
# SSH into the server
ssh deploy@samwilkinson.ca

# Navigate to project directory
cd /var/www/samwilkinson

# Create admin user (interactive)
docker compose -p samwilkinson -f docker-compose.prod.yml exec app \
  php bin/console app:create-admin

# Or non-interactive (useful for automation)
docker compose -p samwilkinson -f docker-compose.prod.yml exec app \
  php bin/console app:create-admin \
  --email="your@email.com" \
  --password="YourSecurePassword123"
```

**Requirements:**
- Valid email address
- Password must be at least 8 characters
- User will be created with `ROLE_ADMIN`

**Access:**
- URL: https://samwilkinson.ca/admin
- Login with the email and password you just created

## 2. Generate API Token

Create an API token for iOS Shortcut bulk photo uploads:

```bash
# Generate token with auto-generated secure value
docker compose -p samwilkinson -f docker-compose.prod.yml exec app \
  php bin/console app:create-api-token

# Or with custom name
docker compose -p samwilkinson -f docker-compose.prod.yml exec app \
  php bin/console app:create-api-token \
  --name="iPhone 15 Pro Shortcut"

# Or with custom token value (not recommended - use auto-generated)
docker compose -p samwilkinson -f docker-compose.prod.yml exec app \
  php bin/console app:create-api-token \
  --name="Custom Token" \
  --token="your-custom-token-value"
```

**Output:**
```
✓ API token created: iOS Shortcut

Token Details
=============

Property  Value
--------  ----------------------------------------------------------------
Name      iOS Shortcut
Token     a1b2c3d4e5f6...  (64 character hex string)
Created   2026-01-04 22:00:00

[NOTE] Save this token securely!
       You will need to add it to your iOS Shortcut configuration.
       Add as Authorization header: Bearer a1b2c3d4e5f6...
```

**Important:**
- Save the token value immediately - it's only shown once
- Add to iOS Shortcut as: `Authorization: Bearer <token>`
- Tokens are stored in the database (table: `api_token`)

## 3. Managing Tokens

### List existing tokens (via database query)

```bash
docker compose -p samwilkinson -f docker-compose.prod.yml exec app \
  php bin/console doctrine:query:sql "SELECT id, name, created_at FROM api_token"
```

### Revoke a token (delete from database)

```bash
# Via EasyAdmin dashboard at /admin
# Or via SQL:
docker compose -p samwilkinson -f docker-compose.prod.yml exec app \
  php bin/console doctrine:query:sql "DELETE FROM api_token WHERE id = 1"
```

## Notes

- **No fixtures in production:** Demo data (projects, posts, collections) is NOT loaded automatically
- **One-time setup:** These commands only need to be run once after initial deployment
- **Security:** Use strong passwords and keep API tokens secure
- **Backup:** Store admin credentials and API tokens in your password manager

## Troubleshooting

### "User already exists"
```bash
# List existing users
docker compose -p samwilkinson -f docker-compose.prod.yml exec app \
  php bin/console doctrine:query:sql "SELECT id, email, roles FROM user"
```

### "Command not found"
- Ensure you're in `/var/www/samwilkinson` directory
- Verify containers are running: `docker compose -p samwilkinson -f docker-compose.prod.yml ps`
- Check Symfony is installed: `docker compose -p samwilkinson -f docker-compose.prod.yml exec app php bin/console --version`
