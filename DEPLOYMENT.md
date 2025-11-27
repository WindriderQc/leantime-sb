# Leantime Deployment Guide

## Current Setup: Docker with File Sync

This is a **hybrid approach** that uses the official Leantime Docker image with manual file syncing for development.

---

## Development Workflow (Current) ⚡

### Quick Start

1. **Edit files locally:**
   ```bash
   nano ~/leantime-sb/app/Domain/Widgets/Templates/partials/calendar.blade.php
   ```

2. **Sync to container:**
   ```bash
   cd ~/leantime-sb
   ./sync-to-container.sh
   ```

3. **Refresh browser** → See changes instantly!

### For JavaScript/CSS Changes

```bash
cd ~/leantime-sb

# Edit your JS/CSS files
nano app/Domain/Calendar/Js/calendarController.js

# Build frontend assets
npm run dev

# Sync to container
./sync-to-container.sh

# Refresh browser
```

### Sync Script Usage

The `sync-to-container.sh` script can sync all modified files or specific ones:

```bash
# Sync all modified files (calendar changes + built assets)
./sync-to-container.sh

# Sync a specific file
./sync-to-container.sh app/Domain/Widgets/Templates/partials/calendar.blade.php

# Sync a directory
./sync-to-container.sh public/dist
```

**What gets synced automatically:**
- Calendar Blade template
- Calendar JavaScript controller
- Built frontend assets (if they exist)

---

## Why This Approach?

### The Problem with Volume Mounting

Volume mounting your entire codebase (`/home/yb/leantime-sb:/var/www/html`) doesn't work because:
- Your local code lacks the `vendor/` directory (PHP dependencies)
- Installing dependencies locally requires PHP extensions (ldap, mysqli, pdo_mysql, etc.)
- The official Leantime image has all dependencies pre-installed

### The Solution: File Sync

- Use official Leantime image (has all dependencies)
- Copy only your modified files into the running container
- Changes are instant (no rebuild needed)
- Simple and fast for development

---

## Production Deployment

When ready to create a production-ready image with your changes baked in:

### Step 1: Configure docker-compose.yml

```bash
cd /opt/leantime
sudo nano docker-compose.yml
```

Change from:
```yaml
services:
  leantime:
    image: leantime/leantime:latest  # Development mode
```

To:
```yaml
services:
  leantime:
    # image: leantime/leantime:latest  # Comment out
    build:
      context: /home/yb/leantime-sb
      dockerfile: Dockerfile.prod
    image: leantime/leantime-custom:local
```

### Step 2: Build Custom Image

```bash
cd /opt/leantime
sudo docker compose build leantime
```

**What the build does:**
1. Copies your custom code into the image
2. Installs Composer
3. Runs `composer install` (PHP dependencies)
4. Runs `npm ci && npm run production` (frontend assets)
5. Sets proper permissions

**Build time:** 5-10 minutes (first time), 2-3 minutes (subsequent builds)

### Step 3: Deploy

```bash
sudo docker compose up -d
```

### Step 4: Verify

```bash
docker compose logs -f leantime
```

Look for successful startup messages (no 500 errors).

---

## Dockerfile.prod Explained

Your production Dockerfile now includes all necessary steps:

```dockerfile
FROM leantime/leantime:latest

# Copy custom code with proper ownership
COPY --chown=www-data:www-data . /var/www/html

# Install Composer (not in official image)
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin --filename=composer

# Install PHP dependencies
RUN cd /var/www/html && \
    composer install --no-dev --optimize-autoloader --no-interaction

# Build frontend assets
RUN cd /var/www/html && npm ci && npm run production

# Clean up to reduce image size
RUN rm -rf /var/www/html/node_modules /root/.composer /root/.npm

# Ensure permissions
RUN chown -R www-data:www-data /var/www/html
```

**Key improvements:**
- ✅ Installs all dependencies automatically
- ✅ Builds frontend assets during image build
- ✅ Cleans up unnecessary files
- ✅ Sets proper permissions

---

## Frontend Asset Building

### When to Build

| You Changed | Build Command | Time |
|-------------|---------------|------|
| PHP/Blade only | **No build needed!** | 0s |
| JavaScript files | `npm run dev` | ~25s |
| CSS/LESS files | `npm run dev` | ~25s |
| package.json | `npm install` then `npm run dev` | ~2min |

### Commands

```bash
cd ~/leantime-sb

# Development build (faster, with source maps)
npm run dev

# Production build (optimized, minified)
npm run production

# Watch mode (auto-rebuild on file changes)
npm run watch
```

### Build Output

```
Input:
  app/Domain/*/Js/*.js        → JavaScript modules
  public/assets/css/*.less    → Stylesheets
  tailwind.config.js          → Tailwind CSS

Output:
  public/dist/compiled-app.3.5.12.min.js   (~178 KiB)
  public/dist/compiled-app.3.5.12.min.css
  public/mix-manifest.json
```

---

## Container Management

### Start/Stop/Restart

```bash
cd /opt/leantime

# Start containers
sudo docker compose up -d

# Stop containers
sudo docker compose down

# Restart specific service
sudo docker compose restart leantime

# View logs (live)
sudo docker compose logs -f leantime

# View last 50 lines
sudo docker compose logs --tail=50 leantime
```

### Check Container Status

```bash
# List running containers
docker compose ps

# Check if file exists in container
docker exec leantime-leantime-1 ls -la /var/www/html/app/Domain/Widgets/Templates/partials/

# View file content
docker exec leantime-leantime-1 cat /var/www/html/path/to/file.php

# Shell into container
docker exec -it leantime-leantime-1 sh
```

---

## Troubleshooting

### Changes Not Visible

1. **Did you sync files to container?**
   ```bash
   cd ~/leantime-sb
   ./sync-to-container.sh
   ```

2. **For JS/CSS changes, did you rebuild assets?**
   ```bash
   npm run dev
   ./sync-to-container.sh
   ```

3. **Clear browser cache:**
   - Chrome/Firefox: Ctrl+F5 or Cmd+Shift+R
   - Or use Incognito/Private mode

4. **Verify files in container:**
   ```bash
   # Check your file in container
   docker exec leantime-leantime-1 head /var/www/html/app/Domain/Widgets/Templates/partials/calendar.blade.php
   
   # Compare with local file
   head ~/leantime-sb/app/Domain/Widgets/Templates/partials/calendar.blade.php
   ```

### Container Shows 500 Errors

Check the logs for details:
```bash
docker compose logs --tail=50 leantime
```

**Common causes:**
- Missing vendor directory (use official image, not custom build without dependencies)
- PHP errors in your code
- Missing environment variables in `.env`

**Solution if using broken custom build:**
```bash
cd /opt/leantime

# Switch back to official image
sudo sed -i 's/^    build:/#    build:/' docker-compose.yml
sudo sed -i 's/^#    image: leantime\/leantime:latest/    image: leantime\/leantime:latest/' docker-compose.yml

# Restart
sudo docker compose down && sudo docker compose up -d

# Sync your files
cd ~/leantime-sb
./sync-to-container.sh
```

### Sync Script Issues

```bash
# Make sure script is executable
chmod +x ~/leantime-sb/sync-to-container.sh

# Check if container is running
docker ps | grep leantime

# Manually copy a file if script fails
docker cp ~/leantime-sb/path/to/file.php leantime-leantime-1:/var/www/html/path/to/file.php
```

### Build Failures (Production)

```bash
# Check Dockerfile syntax
cd ~/leantime-sb
cat Dockerfile.prod

# Check disk space
df -h

# Clear Docker cache and rebuild
cd /opt/leantime
sudo docker system prune -a  # WARNING: Removes unused images
sudo docker compose build --no-cache leantime
```

---

## Configuration Files

### docker-compose.yml Location

**Active configuration:** `/opt/leantime/docker-compose.yml`

**Backups:**
- `/opt/leantime/docker-compose.yml.prod-backup` - Custom build configuration
- `/opt/leantime/docker-compose.dev-backup.yml` - Volume mount attempt (broken)

### Development Mode (Current)

```yaml
services:
  leantime:
    image: leantime/leantime:latest  # Official image
    container_name: leantime-leantime-1
    restart: unless-stopped
    env_file: ./.env
    ports:
      - "${LEAN_PORT:-8080}:8080"
    volumes:
      - public_userfiles:/var/www/html/public/userfiles
      - userfiles:/var/www/html/userfiles
      - plugins:/var/www/html/app/Plugins
      - logs:/var/www/html/storage/logs
    # NO code volume mount - use sync script instead
```

### Production Mode

```yaml
services:
  leantime:
    build:
      context: /home/yb/leantime-sb
      dockerfile: Dockerfile.prod
    image: leantime/leantime-custom:local
    container_name: leantime-leantime-1
    restart: unless-stopped
    env_file: ./.env
    ports:
      - "${LEAN_PORT:-8080}:8080"
    volumes:
      - public_userfiles:/var/www/html/public/userfiles
      - userfiles:/var/www/html/userfiles
      - plugins:/var/www/html/app/Plugins
      - logs:/var/www/html/storage/logs
```

---

## Switching Between Modes

### Development → Production

```bash
cd /opt/leantime
sudo docker compose down

# Edit docker-compose.yml
sudo nano docker-compose.yml
# 1. Comment out: image: leantime/leantime:latest
# 2. Uncomment: build section

# Build and deploy
sudo docker compose build leantime
sudo docker compose up -d
```

### Production → Development

```bash
cd /opt/leantime
sudo docker compose down

# Edit docker-compose.yml
sudo nano docker-compose.yml
# 1. Uncomment: image: leantime/leantime:latest
# 2. Comment out: build section

# Deploy
sudo docker compose up -d

# Sync your files
cd ~/leantime-sb
./sync-to-container.sh
```

---

## Best Practices

### ✅ DO

- Use **official image + sync script** for daily development
- Build frontend assets after every JS/CSS change
- Test changes thoroughly before building production image
- Commit to git regularly
- Keep docker-compose.yml backups for different modes

### ❌ DON'T

- Don't try to volume mount entire codebase (missing vendor/ causes 500 errors)
- Don't skip `npm run dev` after JavaScript/CSS changes
- Don't deploy custom build without testing in development first
- Don't forget to sync files after editing locally
- Don't edit files directly in container (changes lost on restart)

---

## Quick Commands Reference

```bash
# === DEVELOPMENT WORKFLOW ===
cd ~/leantime-sb
nano app/Domain/Widgets/Templates/partials/calendar.blade.php
./sync-to-container.sh
# Refresh browser

# For JS/CSS:
npm run dev
./sync-to-container.sh
# Refresh browser

# === PRODUCTION BUILD ===
cd ~/leantime-sb
npm run production
git add . && git commit -m "Feature: XYZ"

cd /opt/leantime
sudo docker compose build leantime
sudo docker compose up -d

# === DEBUGGING ===
docker compose logs -f leantime                # Live logs
docker exec leantime-leantime-1 sh             # Shell into container
docker compose ps                              # Container status
docker exec leantime-leantime-1 cat /var/www/html/path/to/file.php  # View file

# === CLEANUP ===
sudo docker compose down                       # Stop containers
sudo docker system prune                       # Free space
sudo docker volume prune                       # Remove unused volumes
```

---

## Summary

**Current Setup:**
- Official Leantime Docker image (has all dependencies)
- Manual file sync via `sync-to-container.sh` script
- Fast development iteration (~0 seconds for PHP, ~25 seconds for JS)

**Workflow:**
1. Edit files locally in `~/leantime-sb/`
2. Run `./sync-to-container.sh`
3. Refresh browser
4. Repeat!

**For Production:**
1. Test everything with sync script
2. Switch docker-compose.yml to build mode
3. Build custom image (5-10 min)
4. Deploy

This gives you fast development + reproducible production builds! 🚀
# Make your changes...

# 2. Build frontend assets
npm run prod  # or: make build

# 3. Commit and push
git add .
git commit -m "Your changes"
git push origin custom-prod

# 4. On server, pull latest code
cd ~/leantime-sb
git pull origin custom-prod

# 5. Rebuild and restart with custom compose file
cd /opt/leantime
docker compose -f docker-compose.custom-build.yml up -d --build leantime

# 6. Run system update if needed
docker exec -it leantime bash
php bin/leantime system:update
```

**Pros:**
- ✅ True production deployment
- ✅ No external code dependencies
- ✅ Consistent across environments

**Cons:**
- ⚠️ Slower - requires rebuild for every change
- ⚠️ Larger image size

---

## Current Active Configuration

**File:** `.docker/docker-compose.yml` (modified for volume mount)
**Method:** Volume Mount (Option 1) - **CURRENTLY ACTIVE**
**Code Location:** `/home/yb/leantime-sb` mounted to `/var/www/html`

**Note:** This was changed from the custom build approach to allow instant code changes without rebuilds.

---

## Frontend Asset Building

### When to rebuild assets:
- ✅ After editing JavaScript files (`app/Domain/*/Js/*.js`)
- ✅ After editing LESS files (`public/assets/less/*.less`)
- ✅ After editing Blade templates (sometimes - for cached views)
- ❌ PHP changes - no rebuild needed

### Build commands:
```bash
cd ~/leantime-sb

# Development build (faster, with source maps)
npm run development

# Production build (minified, optimized)
npm run production

# Or use make commands
make build-dev   # Development
make build       # Production
```

---

## Quick Reference

### Restart after code changes:
```bash
cd /opt/leantime
docker compose restart leantime
```

### View logs:
```bash
docker compose logs -f leantime
```

### Clear Leantime cache:
```bash
docker exec -it leantime php bin/leantime cache:clear
```

### Run migrations/updates:
```bash
docker exec -it leantime php bin/leantime system:update
```

### Switch between deployment methods:

**To Volume Mount:**
```bash
cd /opt/leantime
docker compose -f docker-compose.yml up -d
```

**To Custom Build:**
```bash
cd /opt/leantime
docker compose -f docker-compose.custom-build.yml up -d --build
```

---

## File Permissions (Volume Mount Method)

If you encounter permission issues:

```bash
# Fix ownership (run on host)
sudo chown -R www-data:www-data /home/yb/leantime-sb

# Or inside container
docker exec -it leantime chown -R www-data:www-data /var/www/html
```

---

## Notes

- **Frontend assets** are compiled to `public/dist/` - these need rebuilding after JS/CSS changes
- **PHP code** changes are immediate with volume mount (no restart usually needed)
- **Configuration changes** in `.env` require container restart
- **Database migrations** require running `system:update` command
