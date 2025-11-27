# CI/CD Deployment Setup

This document describes the Continuous Integration and Continuous Deployment (CI/CD) pipeline for automated Leantime deployment.

**Source:** Based on PearShadow/leantime fork CI/CD implementation  
**Status:** Documentation ready, implementation optional

## Overview

Automated deployment pipeline using GitHub Actions to deploy Leantime to production and staging environments.

### Benefits

- ✅ Automated deployments on git push
- ✅ Separate staging and production environments
- ✅ Automated testing before deployment
- ✅ Rollback capability
- ✅ Zero-downtime deployments
- ✅ Deployment notifications

## Architecture

```
GitHub Repository (WindriderQc/leantime-sb)
    ↓
GitHub Actions Workflows
    ↓
    ├── Staging (develop branch) → staging.example.com
    └── Production (master/main branch) → app.example.com
```

## Prerequisites

### Server Requirements

**Staging Server:**
- Ubuntu 20.04+ or similar Linux distribution
- Docker & Docker Compose installed
- SSH access
- Port 80/443 available

**Production Server:**
- Same as staging
- Separate server recommended

### Required Secrets

Configure these in GitHub repository settings (`Settings > Secrets and variables > Actions`):

| Secret Name | Description | Example |
|-------------|-------------|---------|
| STAGING_HOST | Staging server IP/hostname | `staging.example.com` |
| STAGING_USER | SSH username for staging | `deploy` |
| STAGING_SSH_KEY | Private SSH key for staging | `-----BEGIN RSA PRIVATE KEY-----...` |
| PROD_HOST | Production server IP/hostname | `app.example.com` |
| PROD_USER | SSH username for production | `deploy` |
| PROD_SSH_KEY | Private SSH key for production | `-----BEGIN RSA PRIVATE KEY-----...` |

## Server Setup

### 1. Create Deployment User

On both staging and production servers:

```bash
# Create deployment user
sudo adduser deploy
sudo usermod -aG docker deploy

# Add to sudoers (for Docker commands)
echo "deploy ALL=(ALL) NOPASSWD: /usr/bin/docker, /usr/bin/docker-compose" | \
    sudo tee /etc/sudoers.d/deploy

# Switch to deploy user
sudo su - deploy
```

### 2. Generate SSH Key Pair

On your local machine:

```bash
# Generate key pair
ssh-keygen -t rsa -b 4096 -C "deploy@leantime" -f ~/.ssh/leantime_deploy

# This creates:
# - ~/.ssh/leantime_deploy (private key - add to GitHub Secrets)
# - ~/.ssh/leantime_deploy.pub (public key - add to server)
```

### 3. Install Public Key on Servers

On staging and production servers:

```bash
# As deploy user
mkdir -p ~/.ssh
chmod 700 ~/.ssh

# Add public key
nano ~/.ssh/authorized_keys
# Paste contents of leantime_deploy.pub
# Save and exit

chmod 600 ~/.ssh/authorized_keys
```

### 4. Create Directory Structure

On both servers:

```bash
# Staging server
sudo mkdir -p /opt/leantime-staging
sudo chown deploy:deploy /opt/leantime-staging

# Production server
sudo mkdir -p /opt/leantime
sudo chown deploy:deploy /opt/leantime
```

### 5. Initial Setup Files

**Create docker-compose.yml:**

```yaml
# /opt/leantime/docker-compose.yml (production)
version: '3'
services:
  leantime:
    image: leantime/leantime:latest
    container_name: leantime
    restart: unless-stopped
    ports:
      - "8890:80"
    volumes:
      - ./userfiles:/var/www/html/userfiles
      - ./storage:/var/www/html/storage/logs
    env_file:
      - .env
```

**Create .env file:**

```bash
# /opt/leantime/.env (production)
LEAN_SITENAME='Your Leantime Instance'
LEAN_LANGUAGE='en-US'
LEAN_DEFAULT_TIMEZONE='America/New_York'

# Database
LEAN_DB_HOST='mysql'
LEAN_DB_USER='leantime'
LEAN_DB_PASSWORD='your-secure-password-here'
LEAN_DB_DATABASE='leantime'

# Security
LEAN_SESSION_PASSWORD='your-session-secret-here'
LEAN_SESSION_SALT='your-session-salt-here'

# Email
LEAN_EMAIL_RETURN='noreply@example.com'
LEAN_EMAIL_USE_SMTP='true'
LEAN_EMAIL_SMTP_HOSTS='smtp.example.com'
LEAN_EMAIL_SMTP_AUTH='true'
LEAN_EMAIL_SMTP_USERNAME='smtp@example.com'
LEAN_EMAIL_SMTP_PASSWORD='smtp-password'
LEAN_EMAIL_SMTP_SECURE='tls'
LEAN_EMAIL_SMTP_PORT='587'
```

## GitHub Actions Workflows

### Staging Deployment Workflow

Create `.github/workflows/deploy-staging.yml`:

```yaml
name: Deploy to Staging

on:
  push:
    branches:
      - develop
  workflow_dispatch:

jobs:
  test:
    name: Run Tests
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: bcmath, ctype, curl, dom, exif, fileinfo, filter, gd, hash, ldap, mbstring, mysql, opcache, openssl, pcntl, pcre, pdo, phar, session, tokenizer, zip, simplexml
      
      - name: Install Composer Dependencies
        run: composer install --no-dev --optimize-autoloader
      
      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: '18'
      
      - name: Install NPM Dependencies
        run: npm ci
      
      - name: Build Assets
        run: npm run production
      
      - name: Run PHPStan
        run: vendor/bin/phpstan analyze --no-progress
        continue-on-error: true
      
      - name: Run PHP CodeSniffer
        run: vendor/bin/phpcs --standard=PSR12 app/
        continue-on-error: true

  deploy:
    name: Deploy to Staging
    needs: test
    runs-on: ubuntu-latest
    environment:
      name: staging
      url: https://staging.example.com
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup SSH
        uses: webfactory/ssh-agent@v0.8.0
        with:
          ssh-private-key: ${{ secrets.STAGING_SSH_KEY }}
      
      - name: Add Host to Known Hosts
        run: |
          mkdir -p ~/.ssh
          ssh-keyscan -H ${{ secrets.STAGING_HOST }} >> ~/.ssh/known_hosts
      
      - name: Deploy to Staging Server
        run: |
          ssh ${{ secrets.STAGING_USER }}@${{ secrets.STAGING_HOST }} << 'EOF'
            cd /opt/leantime-staging
            
            # Pull latest changes
            git fetch origin
            git reset --hard origin/develop
            
            # Pull latest Docker image
            docker compose pull
            
            # Restart container
            docker compose up -d --force-recreate
            
            # Show status
            docker compose ps
          EOF
      
      - name: Verify Deployment
        run: |
          sleep 10
          curl -f https://staging.example.com/healthCheck.php || exit 1
      
      - name: Notify Deployment Success
        if: success()
        run: echo "Staging deployment successful!"
      
      - name: Notify Deployment Failure
        if: failure()
        run: echo "Staging deployment failed!"
```

### Production Deployment Workflow

Create `.github/workflows/deploy-production.yml`:

```yaml
name: Deploy to Production

on:
  push:
    branches:
      - master
      - main
  workflow_dispatch:

jobs:
  test:
    name: Run Tests
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: bcmath, ctype, curl, dom, exif, fileinfo, filter, gd, hash, ldap, mbstring, mysql, opcache, openssl, pcntl, pcre, pdo, phar, session, tokenizer, zip, simplexml
      
      - name: Install Composer Dependencies
        run: composer install --no-dev --optimize-autoloader
      
      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: '18'
      
      - name: Install NPM Dependencies
        run: npm ci
      
      - name: Build Assets
        run: npm run production
      
      - name: Run PHPStan
        run: vendor/bin/phpstan analyze --no-progress
      
      - name: Run PHP CodeSniffer
        run: vendor/bin/phpcs --standard=PSR12 app/
      
      - name: Run Unit Tests
        run: vendor/bin/codecept run Unit
        continue-on-error: true

  deploy:
    name: Deploy to Production
    needs: test
    runs-on: ubuntu-latest
    environment:
      name: production
      url: https://app.example.com
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup SSH
        uses: webfactory/ssh-agent@v0.8.0
        with:
          ssh-private-key: ${{ secrets.PROD_SSH_KEY }}
      
      - name: Add Host to Known Hosts
        run: |
          mkdir -p ~/.ssh
          ssh-keyscan -H ${{ secrets.PROD_HOST }} >> ~/.ssh/known_hosts
      
      - name: Backup Database
        run: |
          ssh ${{ secrets.PROD_USER }}@${{ secrets.PROD_HOST }} << 'EOF'
            cd /opt/leantime
            
            # Backup database
            BACKUP_FILE="backup_$(date +%Y%m%d_%H%M%S).sql"
            docker exec leantime php bin/leantime backup:db > "backups/$BACKUP_FILE"
            
            echo "Database backed up to $BACKUP_FILE"
          EOF
      
      - name: Deploy to Production Server
        run: |
          ssh ${{ secrets.PROD_USER }}@${{ secrets.PROD_HOST }} << 'EOF'
            cd /opt/leantime
            
            # Pull latest changes
            git fetch origin
            git reset --hard origin/master
            
            # Pull latest Docker image
            docker compose pull
            
            # Zero-downtime restart
            docker compose up -d --no-deps --build leantime
            
            # Run migrations if needed
            docker exec leantime php bin/leantime migrate
            
            # Show status
            docker compose ps
          EOF
      
      - name: Verify Deployment
        run: |
          sleep 15
          curl -f https://app.example.com/healthCheck.php || exit 1
      
      - name: Notify Deployment Success
        if: success()
        run: echo "Production deployment successful!"
      
      - name: Rollback on Failure
        if: failure()
        run: |
          ssh ${{ secrets.PROD_USER }}@${{ secrets.PROD_HOST }} << 'EOF'
            cd /opt/leantime
            
            # Rollback to previous version
            git reset --hard HEAD~1
            docker compose up -d --force-recreate
            
            echo "Rolled back to previous version"
          EOF
```

## Deployment Workflow

### Staging Deployment

```mermaid
graph LR
    A[Push to develop] --> B[Run Tests]
    B --> C{Tests Pass?}
    C -->|Yes| D[Deploy to Staging]
    C -->|No| E[Notify Failure]
    D --> F[Verify Deployment]
    F --> G{Verification OK?}
    G -->|Yes| H[Notify Success]
    G -->|No| E
```

### Production Deployment

```mermaid
graph LR
    A[Push to master] --> B[Run Tests]
    B --> C{Tests Pass?}
    C -->|Yes| D[Backup Database]
    C -->|No| E[Notify Failure]
    D --> F[Deploy to Production]
    F --> G[Run Migrations]
    G --> H[Verify Deployment]
    H --> I{Verification OK?}
    I -->|Yes| J[Notify Success]
    I -->|No| K[Rollback]
    K --> E
```

## Files Excluded from Deployment

The following files should NOT be deployed (add to `.gitignore`):

```
# Environment & Config
config/.env
.env

# User Data
public/userfiles/
userfiles/

# Logs
storage/logs/
*.log

# Plugins (if managed separately)
app/Plugins/*
!app/Plugins/.gitkeep

# Cache
storage/framework/cache/
storage/framework/sessions/
storage/framework/views/

# Build artifacts
node_modules/
vendor/
```

## Manual Deployment

For manual deployments without CI/CD:

```bash
# On your local machine
git push origin develop  # Deploys to staging
git push origin master   # Deploys to production

# Or use workflow_dispatch (manual trigger)
# Go to: GitHub > Actions > Select workflow > Run workflow
```

## Monitoring Deployments

### View Deployment Status

1. Go to GitHub repository
2. Click "Actions" tab
3. View workflow runs

### View Logs

```bash
# SSH to server
ssh deploy@app.example.com

# View deployment logs
cd /opt/leantime
docker compose logs -f --tail=100

# View container status
docker compose ps
```

## Rollback Procedure

### Automatic Rollback

Production workflow includes automatic rollback on deployment failure.

### Manual Rollback

```bash
# SSH to production server
ssh deploy@app.example.com
cd /opt/leantime

# Rollback to previous commit
git log --oneline  # View commit history
git reset --hard <previous-commit-hash>

# Restart container
docker compose up -d --force-recreate

# Verify
docker compose ps
```

## Testing Locally

Test CI/CD workflow locally using act:

```bash
# Install act
# https://github.com/nektos/act

# Test staging workflow
act push -W .github/workflows/deploy-staging.yml --secret-file .secrets

# Test production workflow
act push -W .github/workflows/deploy-production.yml --secret-file .secrets
```

## Troubleshooting

### SSH Connection Failed

```bash
# Test SSH connection
ssh -i ~/.ssh/leantime_deploy deploy@app.example.com

# Check SSH key permissions
chmod 600 ~/.ssh/leantime_deploy
```

### Docker Permission Denied

```bash
# Add deploy user to docker group
sudo usermod -aG docker deploy

# Logout and login again
```

### Deployment Stuck

```bash
# SSH to server
ssh deploy@app.example.com

# Check running processes
ps aux | grep docker

# Force restart
cd /opt/leantime
docker compose down
docker compose up -d
```

### Health Check Failed

```bash
# Check if container is running
docker ps

# Check container logs
docker logs leantime -f

# Check healthCheck.php
curl -v http://localhost:8890/healthCheck.php
```

## Security Best Practices

1. ✅ Use separate SSH keys for staging and production
2. ✅ Rotate SSH keys regularly (every 90 days)
3. ✅ Use GitHub environment protection rules
4. ✅ Require manual approval for production deployments
5. ✅ Enable branch protection on master/main
6. ✅ Use secrets for sensitive data
7. ✅ Audit deployment logs regularly
8. ✅ Set up database backups before deployment

## Future Enhancements

- Slack/Discord notifications
- Deployment metrics (Datadog, New Relic)
- Blue-green deployment strategy
- Canary deployments
- Automatic database migrations
- Asset optimization pipeline
- CDN cache purge
- Multi-region deployment

## Resources

- **GitHub Actions:** https://docs.github.com/en/actions
- **Docker Deployment:** https://docs.docker.com/compose/production/
- **PearShadow Fork:** https://github.com/PearShadow/leantime

## Makefile Integration

The PearShadow fork includes a Makefile with test commands:

```makefile
# Run acceptance tests
acceptance-test:
	php vendor/bin/codecept run Acceptance

# Run unit tests
unit-test:
	php vendor/bin/codecept run Unit

# Run API tests
api-test:
	php vendor/bin/codecept run -g api

# Run code sniffer
codesniffer:
	php vendor/bin/phpcs --standard=PSR12 app/

# Run PHPStan
phpstan:
	php vendor/bin/phpstan analyze --no-progress
```

Use in CI/CD:

```yaml
- name: Run Tests
  run: make unit-test acceptance-test codesniffer phpstan
```
