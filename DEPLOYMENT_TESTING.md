# Deployment Testing & Troubleshooting Guide

This guide helps you validate your Prism Wallet deployment before pushing to Render and diagnose any issues that arise.

---

## Quick Start: Local Testing

### 1. Run the Validation Script

```bash
chmod +x validate-deployment.sh
./validate-deployment.sh
```

This script will:
- ✅ Build the Docker image
- ✅ Validate Nginx configuration
- ✅ Validate PHP-FPM configuration
- ✅ Check that PHP-FPM listens on `127.0.0.1:9000`
- ✅ Start a test container
- ✅ Test the health endpoint
- ✅ Show you all the logs

### 2. Manual Testing (Alternative)

If you prefer to test manually:

```bash
# Build the image
docker build -t prism-wallet:test .

# Run with environment variables
docker run -d \
    -p 8080:80 \
    -e DB_HOST=your-db-host \
    -e DB_PORT=5432 \
    -e DB_NAME=your-db-name \
    -e DB_USER=your-db-user \
    -e DB_PASSWORD=your-db-password \
    -e AUTH_PROVIDER=local \
    -e APP_URL=http://localhost:8080 \
    --name prism-test \
    prism-wallet:test

# Check logs
docker logs -f prism-test

# Test the health endpoint
curl http://localhost:8080/health.php

# Run diagnostics inside container
docker exec prism-test /usr/local/bin/debug-startup.sh
```

---

## What Was Fixed

### Problem: "host not found in upstream 'php-fpm'" Error

**Root Cause:**  
Mixed configuration where Nginx tried to resolve a hostname `php-fpm` (common in multi-container setups) but PHP-FPM was running in the same container without that hostname.

**Solution:**
1. **PHP-FPM Pool Config** (`docker/php/www.conf`):
   - Forces PHP-FPM to listen on `127.0.0.1:9000` (TCP loopback)
   - Previously might have been using a Unix socket or not explicitly configured

2. **Nginx Configuration** (`nginx.default.conf` & `nginx.conf`):
   - Both use `fastcgi_pass 127.0.0.1:9000;` (loopback TCP)
   - Removed any references to `fastcgi_pass php-fpm:9000;`

3. **Dockerfile Changes**:
   - Copies `docker/php/www.conf` → `/usr/local/etc/php-fpm.d/www.conf`
   - Copies `nginx.default.conf` → both `/etc/nginx/conf.d/` and `/etc/nginx/http.d/`
   - Ensures consistent configuration across environments

4. **Startup Script Improvements** (`startup.sh`):
   - Tests PHP-FPM config with `php-fpm -t` before starting
   - Actively waits for PHP-FPM to accept connections on `127.0.0.1:9000`
   - Better error messages and non-fatal handling for race conditions
   - Verbose logging with clear section markers

---

## Understanding the Logs

### Successful Startup Logs Should Show:

```
===================================
Prism Wallet Container Starting
Date: ...
===================================
✓ www.conf exists
✓ php-fpm configuration test PASSED
===================================
Starting PHP-FPM
===================================
✓ PHP-FPM started with PID: 123
===================================
Waiting for PHP-FPM to Listen
===================================
✓ PHP-FPM is ready and accepting connections on 127.0.0.1:9000
===================================
Starting Nginx
===================================
✓ Nginx configuration is valid
✓ Nginx started with PID: 456
===================================
Startup Complete - Services Running
===================================
Container is ready to serve traffic
```

### Common Error Patterns:

#### 1. "PHP-FPM process has died"
```
✗ PHP-FPM process has died! Check logs above.
```
**Diagnosis:** Check earlier in logs for PHP-FPM errors (e.g., module loading issues, permission problems)

#### 2. "PHP-FPM did not respond after 20 attempts"
```
✗ WARNING: PHP-FPM did not respond after 20 attempts
```
**Diagnosis:** PHP-FPM started but isn't listening on the expected port
- Run: `docker exec <container> netstat -tlnp | grep 9000`
- Check `/usr/local/etc/php-fpm.d/www.conf` has `listen = 127.0.0.1:9000`

#### 3. "Nginx configuration test failed"
```
✗ ERROR: Nginx configuration test failed!
nginx: [emerg] host not found in upstream "php-fpm"
```
**Diagnosis:** Nginx config still references hostname instead of IP
- Check all nginx conf files: `grep -r "fastcgi_pass" /etc/nginx/`
- Should see `127.0.0.1:9000`, not `php-fpm:9000`

---

## In-Container Diagnostics

If your container starts but doesn't work correctly:

```bash
# Run the built-in diagnostics script
docker exec <container-name> /usr/local/bin/debug-startup.sh

# Check if services are running
docker exec <container-name> ps aux | grep -E "(nginx|php-fpm)"

# Check listening ports
docker exec <container-name> netstat -tlnp

# Test PHP-FPM connection manually
docker exec <container-name> php -r '$s=@fsockopen("127.0.0.1",9000,$e,$str,1); if($s){echo "Connected\n"; fclose($s);} else {echo "Failed: $e - $str\n";}'

# Check logs
docker exec <container-name> cat /var/log/startup.log
docker exec <container-name> cat /var/log/phpfpm-config-check.log
```

---

## Render-Specific Testing

### Before Deploying to Render:

1. **Test Locally First** - Always run `validate-deployment.sh`

2. **Check Environment Variables** - Ensure Render has all required env vars:
   - `DB_HOST`
   - `DB_PORT`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASSWORD`
   - `AUTH_PROVIDER`
   - `APP_URL`

3. **Review Render Settings**:
   - Docker Command: Should be empty (uses `CMD` from Dockerfile)
   - Health Check Path: `/health.php`
   - Port: `80` (internal)

### After Deploying to Render:

1. **Check Render Logs** - Look for the startup banner and success messages

2. **If You See "Exited with status 2"**:
   - This means something failed during startup
   - Look for error messages before the exit
   - Common causes:
     - Missing environment variables
     - Database connection failure (non-fatal but logged)
     - File permission issues
     - PHP extension problems

3. **Run Shell in Render** (if available):
   ```bash
   /usr/local/bin/debug-startup.sh
   ```

---

## Configuration Validation Checklist

- [ ] `docker/php/www.conf` has `listen = 127.0.0.1:9000`
- [ ] `nginx.default.conf` has `fastcgi_pass 127.0.0.1:9000;`
- [ ] `nginx.conf` has `fastcgi_pass 127.0.0.1:9000;` (in PHP location block)
- [ ] No configs reference `fastcgi_pass php-fpm:9000;` or similar hostnames
- [ ] `Dockerfile` copies `www.conf` to `/usr/local/etc/php-fpm.d/`
- [ ] `Dockerfile` copies nginx config to `conf.d`
- [ ] `startup.sh` is executable and has Unix line endings
- [ ] All required environment variables are set in Render

---

## Multi-Service Setup (Optional)

If you later want to support both single-container and multi-service deployments:

### Add Upstream with Fallback

In `nginx.conf` or `nginx.default.conf`, before the `server` block:

```nginx
upstream php_backend {
    server php-fpm:9000 max_fails=1 fail_timeout=1s;
    server 127.0.0.1:9000 backup;
}

server {
    # ... 
    location ~ \.php$ {
        fastcgi_pass php_backend;
        # ...
    }
}
```

This tries `php-fpm:9000` first (multi-service mode) and falls back to `127.0.0.1:9000` (single-container mode).

---

## Known Limitations & Notes

1. **Base Image Vulnerabilities**: `php:8.2-fpm-alpine` may have CVEs
   - Consider using `php:8.3-fpm-alpine` or a specific patched version
   - Scan with: `docker scout cves prism-wallet:test`

2. **Health Check Timing**: 
   - First check at 20s + startup period
   - Interval: 2m (may want shorter for faster failure detection)
   - Start interval: 5s (requires Docker 25+)

3. **Composer Dependencies**:
   - If `vendor/` is not in image, container installs on first run
   - May slow down startup; consider baking into image

4. **Database Connection**:
   - Migration runs during startup but is non-fatal
   - Container will start even if DB is unreachable
   - Check application logs for actual connection errors

---

## Troubleshooting Decision Tree

```
Container exits with status 2
├─ Check startup logs for "ERROR" or "FAILED"
├─ Config test failed?
│  ├─ nginx -t fails → Fix nginx.conf syntax
│  └─ php-fpm -t fails → Fix www.conf or PHP extensions
├─ Process died?
│  ├─ PHP-FPM died → Check extension loading, permissions
│  └─ Nginx died → Check port conflicts, config errors
└─ No clear error?
   └─ Run debug-startup.sh in container for full diagnostics
```

---

## Next Steps

1. **Run Local Test**: `./validate-deployment.sh`
2. **Fix Any Errors**: See diagnostics output
3. **Commit Changes**: `git add . && git commit -m "Enhanced deployment logging"`
4. **Push to Render**: `git push origin main`
5. **Monitor Render Logs**: Watch for the startup banner and success messages
6. **Validate Health**: `curl https://your-app.onrender.com/health.php`

---

## Getting Help

If you're still stuck:

1. Run `validate-deployment.sh` and save output
2. Get container logs: `docker logs <container> > logs.txt 2>&1`
3. Run diagnostics: `docker exec <container> /usr/local/bin/debug-startup.sh > diag.txt`
4. Review:
   - Startup logs for error patterns
   - Nginx error log: `/var/log/nginx/error.log`
   - PHP-FPM log: `/var/log/phpfpm-config-check.log`

Include all these outputs when asking for help!
