# Leantime Deployment Workflows

## Quick Guide: Which Approach to Use?

| Scenario | Use This Approach | Why |
|----------|------------------|-----|
| 🔧 Daily development & testing | **Volume Mount** | Instant feedback, no rebuilds |
| 🚀 Production deployment | **Custom Build** | Secure, self-contained image |
| 🐛 Debugging issues | **Volume Mount** | Easy to modify and test |
| 📦 Releasing a version | **Custom Build** | Reproducible, portable |

---

## Option 1: Volume Mount Mode (Development) ⚡

**How it works:** Your local code directory (`/home/yb/leantime-sb`) is mounted into the official Leantime container at runtime. Your custom code **replaces** the code inside the container.

### Setup (One-time)

1. **Configure docker-compose.yml:**

```yaml
services:
  leantime:
    image: leantime/leantime:latest  # Use official image
    
    # Comment out build configuration:
    # build:
    #   context: /home/yb/leantime-sb
    #   dockerfile: Dockerfile.prod
    
    volumes:
      - /home/yb/leantime-sb:/var/www/html  # YOUR CODE (must be first!)
      - public_userfiles:/var/www/html/public/userfiles
      - userfiles:/var/www/html/userfiles
      - plugins:/var/www/html/app/Plugins
      - logs:/var/www/html/storage/logs
```

2. **Deploy:**

```bash
cd /opt/leantime
sudo docker compose down
sudo docker compose up -d
```

3. **Verify mount:**

```bash
docker exec leantime-leantime-1 head /var/www/html/README.md
# Should show YOUR custom code's README
```

### Daily Development Workflow

```bash
# 1. Edit PHP/Blade files
nano ~/leantime-sb/app/Domain/Widgets/Templates/partials/calendar.blade.php
# ✅ Just refresh browser - changes appear instantly!

# 2. Edit JavaScript/CSS files
nano ~/leantime-sb/app/Domain/Calendar/Js/calendarController.js

# Build frontend assets:
cd ~/leantime-sb
npm run dev  # Takes ~25 seconds

# ✅ Refresh browser - done!

# 3. No container restart needed!
```

### Performance

| Change Type | Time to See Change |
|-------------|-------------------|
| PHP/Blade edit | **0 seconds** (just refresh) ⚡ |
| JavaScript/CSS edit | **~25 seconds** (npm + refresh) |
| Config change | Restart container (~10s) |

### When to Use

- ✅ Active development
- ✅ Testing new features
- ✅ Debugging issues
- ✅ Quick iterations

### When NOT to Use

- ❌ Production servers (security concerns)
- ❌ Shared environments (others might modify files)
- ❌ When you need to distribute your changes

---

## Option 2: Custom Build Mode (Production)

**How it works:** Your code is copied INTO a Docker image during build. The image is self-contained and portable.

### Setup (One-time)

1. **Configure docker-compose.yml:**

```yaml
services:
  leantime:
    # Comment out official image:
    # image: leantime/leantime:latest
    
    build:
      context: /home/yb/leantime-sb
      dockerfile: Dockerfile.prod
    image: leantime/leantime-custom:local
    
    volumes:
      # DON'T mount code directory
      - public_userfiles:/var/www/html/public/userfiles
      - userfiles:/var/www/html/userfiles
      - plugins:/var/www/html/app/Plugins
      - logs:/var/www/html/storage/logs
```

2. **Build and deploy:**

```bash
cd /opt/leantime
sudo docker compose build leantime  # Takes 2-5 minutes
sudo docker compose up -d
```

### Production Deployment Workflow

```bash
# 1. Make and test changes locally (use volume mount mode!)
cd ~/leantime-sb
# ... make changes ...
npm run dev
# Test thoroughly!

# 2. Build production assets
npm run production

# 3. Commit to git
git add .
git commit -m "Feature: Add calendar navigation"
git push origin custom-prod

# 4. Build Docker image
cd /opt/leantime
sudo docker compose build leantime
# ⏳ Wait 2-5 minutes

# 5. Deploy
sudo docker compose up -d

# 6. Verify
docker compose logs -f leantime
```

### Performance

| Operation | Time |
|-----------|------|
| Initial build | **2-5 minutes** |
| Rebuild after change | **2-5 minutes** |
| Deploy (up -d) | **10-30 seconds** |

### When to Use

- ✅ Production deployments
- ✅ Creating release versions
- ✅ Distributing to other servers
- ✅ Final testing before release

### When NOT to Use

- ❌ Active development (too slow!)
- ❌ Quick testing/debugging
- ❌ Frequent code changes

---

## Switching Between Modes

### Development → Production

```bash
cd /opt/leantime
sudo docker compose down

# Backup current config
sudo cp docker-compose.yml docker-compose.dev-backup.yml

# Update docker-compose.yml:
# 1. Comment out: image: leantime/leantime:latest
# 2. Uncomment: build section
# 3. Remove code volume mount: /home/yb/leantime-sb:/var/www/html

sudo docker compose build leantime
sudo docker compose up -d
```

### Production → Development

```bash
cd /opt/leantime
sudo docker compose down

# Backup current config
sudo cp docker-compose.yml docker-compose.prod-backup.yml

# Update docker-compose.yml:
# 1. Uncomment: image: leantime/leantime:latest
# 2. Comment out: build section
# 3. Add volume mount as FIRST item: /home/yb/leantime-sb:/var/www/html

sudo docker compose up -d
```

### Maintain Both Configs

```bash
# Keep separate files
/opt/leantime/
├── docker-compose.yml              # Active configuration
├── docker-compose.dev.yml          # Development (volume mount)
└── docker-compose.prod.yml         # Production (custom build)

# Quick switch to dev:
sudo cp docker-compose.dev.yml docker-compose.yml
sudo docker compose down && sudo docker compose up -d

# Quick switch to prod:
sudo cp docker-compose.prod.yml docker-compose.yml
sudo docker compose build leantime
sudo docker compose up -d
```

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

# Development build (faster, includes source maps)
npm run dev

# Production build (optimized, minified)
npm run production

# Watch mode (auto-rebuild on changes)
npm run watch
```

### What Gets Built

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

## Troubleshooting

### Changes Not Visible

**1. Check which mode you're in:**

```bash
docker inspect leantime-leantime-1 | grep -A 5 "Mounts"
```

Should show for **volume mount**:
```json
"Source": "/home/yb/leantime-sb",
"Destination": "/var/www/html"
```

**2. Verify files in container match host:**

```bash
# Host file
head ~/leantime-sb/app/Domain/Widgets/Templates/partials/calendar.blade.php

# Container file (should match!)
docker exec leantime-leantime-1 head /var/www/html/app/Domain/Widgets/Templates/partials/calendar.blade.php
```

**3. Clear browser cache:**
- Chrome/Firefox: Ctrl+F5 or Cmd+Shift+R
- Or use Incognito/Private window

**4. Check frontend assets:**

```bash
# Did you build?
ls -lh ~/leantime-sb/public/dist/compiled-app*.js

# Is it recent?
stat ~/leantime-sb/public/dist/compiled-app.3.5.12.min.js
```

### Volume Mount Not Working

```bash
# Check docker-compose.yml volume order
# Code mount MUST be FIRST in volumes list:
    volumes:
      - /home/yb/leantime-sb:/var/www/html  # ✅ FIRST
      - public_userfiles:/var/www/html/public/userfiles
      - userfiles:/var/www/html/userfiles
      # ...

# Not this:
    volumes:
      - public_userfiles:/var/www/html/public/userfiles  # ❌ Wrong order
      - /home/yb/leantime-sb:/var/www/html
```

### Build Failures

```bash
# Frontend build fails
cd ~/leantime-sb
rm -rf node_modules package-lock.json
npm install
npm run dev

# Docker build fails
cd /opt/leantime
sudo docker compose config  # Check syntax
df -h                       # Check disk space
sudo docker system prune -a # Clear cache (careful!)
```

### Permission Issues

```bash
# Fix file ownership
sudo chown -R $USER:$USER ~/leantime-sb

# Or use Docker helper
cd /opt/leantime
docker compose --profile helper up -d
```

---

## Best Practices

### ✅ DO

- Use **volume mount** for development
- Use **custom build** for production
- Test changes in volume mount mode BEFORE building image
- Build frontend assets after every JS/CSS change
- Commit to git regularly
- Keep separate docker-compose configs for dev/prod

### ❌ DON'T

- Don't use custom build for active development (too slow!)
- Don't skip frontend builds after JS/CSS changes
- Don't deploy to production without testing
- Don't forget to version your custom images
- Don't mount code directory in production mode

---

## Quick Commands Reference

```bash
# === DEVELOPMENT (Volume Mount) ===
cd ~/leantime-sb
npm run dev                          # Build assets (~25s)
# Refresh browser - done!

# === PRODUCTION (Custom Build) ===
cd ~/leantime-sb
npm run production                   # Build assets (~35s)

cd /opt/leantime
sudo docker compose build leantime   # Build image (2-5min)
sudo docker compose up -d            # Deploy

# === DEBUGGING ===
docker compose logs -f leantime                 # Live logs
docker exec leantime-leantime-1 bash            # Shell
docker inspect leantime-leantime-1 | grep Mounts  # Check volumes

# === FILE VERIFICATION ===
docker exec leantime-leantime-1 cat /var/www/html/path/to/file.php
head ~/leantime-sb/path/to/file.php  # Compare

# === CLEANUP ===
sudo docker compose down              # Stop containers
sudo docker system prune -a           # Free space
sudo docker volume prune              # Remove unused volumes
```

---

## Summary

**For daily development:** Use **Volume Mount**
- Edit → Refresh → See changes ⚡
- No rebuilds
- Fast iteration

**For production:** Use **Custom Build**
- Secure
- Portable
- Reproducible

**Switch modes** based on what you're doing!
